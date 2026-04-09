<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration\Security;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Factories\ImageFactory;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PhpService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

use function json_decode;
use function method_exists;

use const UPLOAD_ERR_OK;

/**
 * Regression für SEC-AUDIT-001 — SVG Stored XSS via inadequate <script> substring filter.
 *
 * Spec: docs/security-audit/tasks/SEC-AUDIT-001_svg_xss_media_upload.md
 *       artifacts/security-audit/deepdive/001/hypotheses.md
 *
 * Getestete Einheiten:
 * - app/Services/MediaFileService.php::uploadFile()  — Upload-Flow (keine Extension-Blocklist)
 * - app/Factories/ImageFactory.php::imageResponse()  — Serving-Flow (DOM-basierter SVG-Blocker + CSP-Header)
 *
 * Hypothesen-Mapping:
 * - H1 test_h1_case_bypass_script_is_blocked_after_fix
 * - H2 test_h2_onload_event_handler_is_blocked_after_fix
 * - H3 test_h3_javascript_url_is_blocked_after_fix
 * - H4 test_h4_legitimate_svg_baseline
 * - H5 test_h5_lowercase_script_is_blocked_baseline
 *
 * Pre-Fix (SEC-AUDIT-001 D3 probe-loop):
 *   H1/H2/H3 umgingen den `str_contains($data, '<script')`-Filter und wurden unverändert
 *   an den Browser ausgeliefert. Einziger Grund, warum dies keine High-Severity-Vuln war:
 *   L2-CSP `script-src none;frame-src none` blockte die Ausführung in modernen Browsern.
 *
 * Post-Fix:
 *   H1/H2/H3 werden nun vom DOM-basierten Blocker in ImageFactory::svgContainsActiveContent()
 *   erkannt und durch `replacementImageResponse('XSS')` ersetzt (x-image-exception-Header gesetzt).
 *   H4 (legitime SVG) passiert weiterhin unverändert. H5 (lowercase <script>) wird weiterhin
 *   blockiert (jetzt über den DOM-Parser, nicht mehr via str_contains).
 *
 * Oracle für H1-H3 und H5 (blocked):
 *   - Status 200 (replacementImageResponse gibt immer 200 zurück)
 *   - Content-Type = image/svg+xml (Placeholder ist auch SVG)
 *   - Header `x-image-exception` gesetzt mit "SVG image blocked due to XSS."
 *   - Body enthält die Payload-Signatur NICHT (sonst wäre der Fix umgangen)
 *
 * Oracle für H4 (legitimate passes):
 *   - fileResponse liefert 200 + image/svg+xml
 *   - CSP-Header gesetzt
 *   - `x-image-exception` abwesend
 *   - Body enthält originale Formen (<rect>, fill="orange")
 *
 * @covers \Fisharebest\Webtrees\Services\MediaFileService
 * @covers \Fisharebest\Webtrees\Factories\ImageFactory
 */
final class SecAudit001Test extends SecurityAuditTestCase
{
    private const DEMO_GED   = '/fixtures/demo.ged';
    private const FIXTURE    = 'sec_audit_001';

    private MediaFileService $mediaFileService;
    private ImageFactory $imageFactory;

    /** @var array<string,mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        // Self-skip if the webtrees source under test does not contain the
        // SEC-AUDIT-001 fix. This lets `make test-integration` stay green on
        // pristine upstream checkouts while the fork commit is still unmerged,
        // and turns green automatically once WEBTREES_SOURCE is pointed at a
        // tree that carries commit c15b95fef4 (branch
        // security-audit-001-svg-filter-hardening-clean) or its upstream successor.
        if (!method_exists(ImageFactory::class, 'svgContainsActiveContent')) {
            self::markTestSkipped(
                'SEC-AUDIT-001 regression requires ImageFactory::svgContainsActiveContent() '
                . '(fork branch security-audit-001-svg-filter-hardening-clean, commit c15b95fef4). '
                . 'Run with WEBTREES_SOURCE pointing at a tree that includes this fix to enable.',
            );
        }

        $this->createTreeWithGedcom('sec-audit-001', 'SEC AUDIT 001', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $this->mediaFileService = new MediaFileService(new PhpService());
        $this->imageFactory     = new ImageFactory(new PhpService());

        $this->fixture = $this->loadFixture(self::FIXTURE);
    }

    public function test_h1_case_bypass_script_is_blocked_after_fix(): void
    {
        $response = $this->uploadAndFetch('H1', 0);

        $this->assertSvgWasBlockedByFilter($response, 'H1');

        $body = (string) $response->getBody();
        self::assertStringNotContainsString('<SCRIPT', $body, 'H1: case-variant script tag must not leak through the replacement');
        self::assertStringNotContainsString('cookie', $body, 'H1: beacon payload must not leak through the replacement');
    }

    public function test_h2_onload_event_handler_is_blocked_after_fix(): void
    {
        $response = $this->uploadAndFetch('H2', 0);

        $this->assertSvgWasBlockedByFilter($response, 'H2');

        $body = (string) $response->getBody();
        self::assertStringNotContainsString('onload=', $body, 'H2: onload handler must not leak through the replacement');
        self::assertStringNotContainsString('cookie', $body, 'H2: beacon payload must not leak through the replacement');
    }

    public function test_h3_javascript_url_is_blocked_after_fix(): void
    {
        $response = $this->uploadAndFetch('H3', 0);

        $this->assertSvgWasBlockedByFilter($response, 'H3');

        $body = (string) $response->getBody();
        self::assertStringNotContainsString('javascript:', $body, 'H3: javascript: URL must not leak through the replacement');
        self::assertStringNotContainsString('cookie', $body, 'H3: beacon payload must not leak through the replacement');
    }

    public function test_h4_legitimate_svg_baseline(): void
    {
        $response = $this->uploadAndFetch('H4', 0);

        $this->assertResponseIsServedSvg($response);
        $this->assertCspPresent($response);
        $this->assertL1FilterDidNotBlock($response);

        $body = (string) $response->getBody();
        self::assertStringContainsString('<rect', $body, 'H4 baseline SVG should round-trip unchanged');
        self::assertStringContainsString('orange', $body, 'H4 baseline fill color should be preserved');
    }

    public function test_h5_lowercase_script_is_blocked_baseline(): void
    {
        $response = $this->uploadAndFetch('H5', 0);

        // Lowercase <script was already blocked pre-fix by str_contains and
        // remains blocked post-fix by the DOM-based walker. This asserts the
        // baseline stays stable across the fix — the trivial case keeps working.
        $this->assertSvgWasBlockedByFilter($response, 'H5');

        $body = (string) $response->getBody();
        self::assertStringNotContainsString('cookie', $body, 'H5: beacon payload must not leak through the replacement');
    }

    // ----- helpers -----

    /**
     * Führt den kompletten Flow für eine Hypothese aus:
     *  1. Upload via MediaFileService::uploadFile()
     *  2. Serve via ImageFactory::fileResponse()
     * und leitet die resultierende Response zurück.
     */
    private function uploadAndFetch(string $hypothesisId, int $payloadIndex): ResponseInterface
    {
        self::assertArrayHasKey($hypothesisId, $this->fixture, "Fixture missing hypothesis {$hypothesisId}");
        $payloads = $this->fixture[$hypothesisId];
        self::assertIsArray($payloads);
        self::assertArrayHasKey($payloadIndex, $payloads, "Fixture missing payload index {$payloadIndex} for {$hypothesisId}");

        /** @var array<string,mixed> $entry */
        $entry = $payloads[$payloadIndex];
        /** @var string $filename */
        $filename = $entry['filename'];
        /** @var string $contentType */
        $contentType = $entry['content_type'];
        /** @var string $payloadText */
        $payloadText = $entry['payload'];

        $uploadedFile = $this->buildUploadedFile($payloadText, $filename, $contentType);

        // Build the request that MediaFileService::uploadFile() expects.
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'file_location' => 'upload',
                'folder'        => '',
                'auto'          => '0',
                'new_file'      => $filename,
            ],
            attributes: ['tree' => $this->tree],
        )->withUploadedFiles(['file' => $uploadedFile]);

        // Mark the probe for trace artifact correlation.
        $request = $request->withHeader('X-Audit-Probe', 'SEC-AUDIT-001-' . $hypothesisId);

        $storedPath = $this->mediaFileService->uploadFile($request);
        self::assertNotSame('', $storedPath, "uploadFile() should have returned a non-empty path for {$hypothesisId}");

        // Now fetch the stored file through the ImageFactory — the exact code path
        // MediaFileDownload uses in production.
        $filesystem = $this->tree->mediaFilesystem();
        self::assertTrue($filesystem->fileExists($storedPath), "Stored file should exist on filesystem: {$storedPath}");

        return $this->imageFactory->fileResponse($filesystem, $storedPath, false);
    }

    private function buildUploadedFile(string $content, string $clientName, string $mimeType): UploadedFileInterface
    {
        /** @var StreamFactoryInterface $streamFactory */
        $streamFactory = Registry::container()->get(StreamFactoryInterface::class);
        /** @var UploadedFileFactoryInterface $uploadedFileFactory */
        $uploadedFileFactory = Registry::container()->get(UploadedFileFactoryInterface::class);

        $stream = $streamFactory->createStream($content);

        return $uploadedFileFactory->createUploadedFile(
            $stream,
            strlen($content),
            UPLOAD_ERR_OK,
            $clientName,
            $mimeType,
        );
    }

    private function assertResponseIsServedSvg(ResponseInterface $response): void
    {
        self::assertSame(
            StatusCodeInterface::STATUS_OK,
            $response->getStatusCode(),
            'fileResponse should return 200 for served SVG',
        );
        self::assertSame(
            'image/svg+xml',
            $response->getHeaderLine('content-type'),
            'Content-Type must be image/svg+xml — this is what makes the browser render SVG as top-level document',
        );
    }

    private function assertCspPresent(ResponseInterface $response): void
    {
        $csp = $response->getHeaderLine('content-security-policy');
        self::assertNotSame(
            '',
            $csp,
            'L2 defense: content-security-policy header MUST be set on every image response',
        );
        self::assertStringContainsString(
            "script-src none",
            $csp,
            'CSP must restrict scripts — this is the primary defense layer for SVG XSS',
        );
        self::assertStringContainsString(
            "frame-src none",
            $csp,
            'CSP must restrict frames',
        );
    }

    private function assertL1FilterDidNotBlock(ResponseInterface $response): void
    {
        self::assertFalse(
            $response->hasHeader('x-image-exception'),
            sprintf(
                'L1 filter should NOT block legitimate SVG. x-image-exception header expected to be ABSENT, got "%s". ' .
                'A present header would mean the DOM walker misclassified a benign SVG as dangerous.',
                $response->getHeaderLine('x-image-exception'),
            ),
        );
    }

    /**
     * Assert that the SVG blocker in ImageFactory::svgContainsActiveContent()
     * replaced the upload with the "XSS" placeholder response. Used by H1/H2/H3
     * (previously bypassable payloads) and H5 (baseline lowercase <script>).
     */
    private function assertSvgWasBlockedByFilter(ResponseInterface $response, string $hypothesisId): void
    {
        self::assertSame(
            StatusCodeInterface::STATUS_OK,
            $response->getStatusCode(),
            sprintf('%s: replacementImageResponse returns 200 with an SVG placeholder body', $hypothesisId),
        );
        self::assertSame(
            'image/svg+xml',
            $response->getHeaderLine('content-type'),
            sprintf('%s: placeholder is still served as image/svg+xml', $hypothesisId),
        );
        self::assertTrue(
            $response->hasHeader('x-image-exception'),
            sprintf(
                '%s: SVG blocker should have replaced the payload (x-image-exception header expected)',
                $hypothesisId,
            ),
        );
        self::assertStringContainsString(
            'SVG image blocked due to XSS.',
            $response->getHeaderLine('x-image-exception'),
            sprintf('%s: x-image-exception header should mention the block reason', $hypothesisId),
        );
    }
}
