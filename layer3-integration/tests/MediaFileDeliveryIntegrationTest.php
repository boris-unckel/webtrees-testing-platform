<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\MediaFactoryInterface;
use Fisharebest\Webtrees\Factories\ImageFactory;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaFileDownload;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaFileThumbnail;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PhpService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use const UPLOAD_ERR_OK;

/**
 * Komponentenintegrationstest: MediaFileDownload & MediaFileThumbnail — E07.
 *
 * Guards:
 * - MediaFileThumbnail: XREF nicht gefunden → replacementImageResponse (kein Exception)
 * - MediaFileDownload: XREF nicht gefunden → HttpNotFoundException via Auth::checkMediaAccess
 * - MediaFileThumbnail: XREF gefunden, canShow=true, kein fact_id match → replacementImageResponse
 *
 * SEC-AUDIT-001 Regressionsabdeckung (verhaltens-definitiv):
 *   Hochgeladene SVGs mit Skript-Inhalt dürfen den Browser nicht erreichen, ohne
 *   dass entweder der ausführbare Inhalt entfernt wurde ODER ein CSP-Header die
 *   Ausführung verhindert. Asserts auf die Sicherheits-Eigenschaft, nicht auf
 *   konkrete Symbol-Namen (Header-Bezeichner, Methodennamen).
 *
 * SEC-AUDIT-003 Regressionsabdeckung (verhaltens-definitiv):
 *   Auch Replacement-Image-Responses (Not-Found-/Forbidden-Placeholder) müssen
 *   einen CSP-Header tragen, der Skriptausführung verhindert.
 *
 * @see docs/tds_conditions_ref.md E07, E09
 * @covers \Fisharebest\Webtrees\Factories\ImageFactory
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MediaFileDownload
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MediaFileThumbnail
 * @covers \Fisharebest\Webtrees\Services\MediaFileService
 * @see docs/testquality_improve_E07.md
 * @see docs/security-audit/tasks/SEC-AUDIT-001_svg_xss_media_upload.md
 * @see docs/security-audit/tasks/SEC-AUDIT-003_replacement_image_response_csp.md
 */
class MediaFileDeliveryIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * EP1: MediaFileThumbnail — ungültige XREF → replacementImageResponse (kein 500).
     * Der Handler gibt einen Ersatz-Bild-Response zurück statt zu werfen.
     */
    public function test_thumbnail_unknown_xref_returns_replacement_image(): void
    {
        $this->createTreeWithGedcom('e07-thumb', 'E07 Thumb', self::DEMO_GED);
        $handler = new MediaFileThumbnail();
        $request = $this->createRequest(
            query: [
                'xref'        => 'DOESNOTEXIST',
                'fact_id'     => '',
                'disposition' => 'inline',
                's'           => 'invalid',
                'w'           => '100',
                'h'           => '100',
                'fit'         => 'contain',
            ],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        $response = $handler->handle($request);

        // replacementImageResponse returns HTTP 200 regardless of the status argument
        // (the status code is encoded in the placeholder image, not the HTTP response)
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: MediaFileThumbnail — XREF existiert, fact_id passt nicht → replacementImageResponse NOT_FOUND.
     */
    public function test_thumbnail_valid_xref_unknown_fact_id_returns_not_found(): void
    {
        $this->createTreeWithGedcom('e07-thumb2', 'E07 Thumb2', self::DEMO_GED);
        $handler = new MediaFileThumbnail();
        $request = $this->createRequest(
            query: [
                'xref'        => 'X247',
                'fact_id'     => 'NONEXISTENT_FACT',
                'disposition' => 'inline',
                's'           => 'invalid',
                'w'           => '100',
                'h'           => '100',
                'fit'         => 'contain',
            ],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        $response = $handler->handle($request);

        // No matching media file found → replacementImageResponse HTTP 200
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP3: MediaFileDownload — ungültige XREF → HttpNotFoundException.
     * Auth::checkMediaAccess() wirft wenn Media null ist.
     */
    public function test_download_unknown_xref_throws_not_found(): void
    {
        $this->createTreeWithGedcom('e07-dl', 'E07 Download', self::DEMO_GED);
        $this->createAndLoginAdmin();
        $handler = new MediaFileDownload();
        $request = $this->createRequest(
            query: [
                'xref'        => 'DOESNOTEXIST',
                'fact_id'     => '',
                'disposition' => 'inline',
            ],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP5: MediaFileThumbnail — Media existiert, canShow=false → replacementImageResponse (kein Throw).
     * Wenn der Viewer das Media-Objekt nicht sehen darf, liefert der Handler ein „forbidden"-
     * Ersatzbild (HTTP 200) statt eine Exception zu werfen.
     *
     * @group ported-l2-doubles
     */
    public function test_thumbnail_media_not_visible_returns_replacement_image(): void
    {
        // Arrange — Tree, MediaFactory-Mock, Media-Stub mit canShow=false
        $this->createTreeWithGedcom('e07-thumb-forbidden', 'E07 Thumb Forbidden', self::DEMO_GED);

        $media = self::createStub(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canShow')->willReturn(false);

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects(self::once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);

        Registry::mediaFactory($media_factory);

        $handler = new MediaFileThumbnail();
        $request = $this->createRequest(
            query:      [
                'xref'    => 'M1',
                'fact_id' => 'abc',
                'w'       => '100',
                'h'       => '100',
                'fit'     => 'contain',
            ],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — replacementImageResponse() liefert HTTP 200; Forbidden-Status steckt im Bild
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP4: MediaFileDownload — Media gefunden, aber kein matching fact_id → replacementImageResponse.
     * Bei leerer mediaFiles()-Collection bzw. nicht passender fact_id fällt der Handler auf
     * das Ersatzbild (HTTP 200) zurück, ohne zu werfen.
     *
     * @group ported-l2-doubles
     */
    public function test_download_valid_media_no_matching_fact_id_returns_replacement_image(): void
    {
        // Arrange — Tree anlegen, MediaFactory mocken, Media-Stub mit leerer mediaFiles()-Collection
        $this->createTreeWithGedcom('e07-dl-fact', 'E07 Download Fact', self::DEMO_GED);

        $media = self::createStub(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canShow')->willReturn(true);
        $media->method('canEdit')->willReturn(true);
        $media->method('mediaFiles')->willReturn(collect([]));

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects(self::once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);

        Registry::mediaFactory($media_factory);

        $handler = new MediaFileDownload();
        $request = $this->createRequest(
            query:      ['xref' => 'M1', 'fact_id' => 'missing', 'disposition' => 'inline'],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — replacementImageResponse() liefert HTTP 200 (Statuscode steckt im Bild, nicht im Header)
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // =========================================================================
    // SEC-AUDIT-003 — CSP auf Replacement-Image-Response (verhaltens-definitiv)
    // =========================================================================

    /**
     * SEC-AUDIT-003 — replacementImageResponse() muss einen CSP-Header tragen,
     * der Skriptausführung verhindert. Ausgelöst über MediaFileThumbnail mit
     * unbekannter XREF, was den Placeholder-Pfad ansteuert.
     */
    public function test_sec_audit_003_replacement_image_carries_csp(): void
    {
        $this->createTreeWithGedcom('sec003-csp', 'SEC AUDIT 003', self::DEMO_GED);
        $handler = new MediaFileThumbnail();
        $request = $this->createRequest(
            query:      [
                'xref'        => 'DOESNOTEXIST',
                'fact_id'     => '',
                'disposition' => 'inline',
                's'           => 'invalid',
                'w'           => '100',
                'h'           => '100',
                'fit'         => 'contain',
            ],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('image/svg+xml', $response->getHeaderLine('content-type'));

        $csp = $response->getHeaderLine('content-security-policy');
        self::assertStringContainsString(
            'script-src none',
            $csp,
            'Replacement-Image-Response must carry CSP that prevents script execution',
        );
    }

    // =========================================================================
    // SEC-AUDIT-001 — SVG XSS Regression (verhaltens-definitiv)
    // =========================================================================
    //
    // Asserts the security property a user actually observes: a malicious SVG
    // that reaches the browser must either have its executable payload removed
    // OR be delivered with a CSP that prevents script execution. The fix may
    // take any form (DOM-based filter, replacement-image fallback, CSP-only
    // hardening) — these tests do not depend on a specific implementation.

    /**
     * H1 — case-bypass `<SCRIPT>` payload must not reach the browser executable.
     */
    public function test_sec_audit_001_h1_case_bypass_script_is_browser_safe(): void
    {
        $response = $this->uploadAndFetchSvg('H1', 0, 'sec001-h1');
        $this->assertSvgBrowserSafe($response, ['<SCRIPT', '<script', 'cookie']);
    }

    /**
     * H2 — `onload=` event handler payload must not reach the browser executable.
     */
    public function test_sec_audit_001_h2_onload_handler_is_browser_safe(): void
    {
        $response = $this->uploadAndFetchSvg('H2', 0, 'sec001-h2');
        $this->assertSvgBrowserSafe($response, ['onload=', 'cookie']);
    }

    /**
     * H3 — `javascript:` URL in xlink:href must not reach the browser executable.
     */
    public function test_sec_audit_001_h3_javascript_url_is_browser_safe(): void
    {
        $response = $this->uploadAndFetchSvg('H3', 0, 'sec001-h3');
        $this->assertSvgBrowserSafe($response, ['javascript:', 'cookie']);
    }

    /**
     * H4 — legitimate SVG (no active content) must round-trip with CSP protection.
     */
    public function test_sec_audit_001_h4_legitimate_svg_passes_with_csp(): void
    {
        $response = $this->uploadAndFetchSvg('H4', 0, 'sec001-h4');

        self::assertNotNull($response, 'Legitimate SVG must not be rejected at upload');
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('image/svg+xml', $response->getHeaderLine('content-type'));

        $csp = $response->getHeaderLine('content-security-policy');
        self::assertStringContainsString(
            'script-src none',
            $csp,
            'Legitimate SVG must be delivered with CSP script-src none',
        );

        $body = (string) $response->getBody();
        self::assertStringContainsString('<rect', $body, 'Legitimate SVG content must round-trip');
        self::assertStringContainsString('orange', $body);
    }

    /**
     * H5 — lowercase `<script>` baseline must not reach the browser executable.
     */
    public function test_sec_audit_001_h5_lowercase_script_is_browser_safe(): void
    {
        $response = $this->uploadAndFetchSvg('H5', 0, 'sec001-h5');
        $this->assertSvgBrowserSafe($response, ['<script', 'cookie']);
    }

    /**
     * Upload an SVG fixture payload and fetch it back through ImageFactory.
     *
     * Returns null when the upload pipeline rejected the file outright — that
     * is the strongest mitigation (defense at L0) and is treated as safe.
     */
    private function uploadAndFetchSvg(string $hypothesisId, int $payloadIndex, string $treeName): ?ResponseInterface
    {
        $this->createTreeWithGedcom($treeName, 'SEC AUDIT 001 ' . $hypothesisId, self::DEMO_GED);
        $this->createAndLoginAdmin();

        $fixture = $this->loadSecAudit001Fixture();
        self::assertArrayHasKey($hypothesisId, $fixture);
        $payloads = $fixture[$hypothesisId];
        self::assertIsArray($payloads);
        self::assertArrayHasKey($payloadIndex, $payloads);

        /** @var array<string,mixed> $entry */
        $entry = $payloads[$payloadIndex];
        /** @var string $filename */
        $filename = $entry['filename'];
        /** @var string $contentType */
        $contentType = $entry['content_type'];
        /** @var string $payloadText */
        $payloadText = $entry['payload'];

        $uploaded = $this->buildUploadedFile($payloadText, $filename, $contentType);

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'file_location' => 'upload',
                'folder'        => '',
                'auto'          => '0',
                'new_file'      => $filename,
            ],
            attributes: ['tree' => $this->tree],
        )->withUploadedFiles(['file' => $uploaded]);

        $mediaFileService = new MediaFileService(new PhpService());
        $stored           = $mediaFileService->uploadFile($request);

        if ($stored === '') {
            return null;
        }

        $filesystem = $this->tree->mediaFilesystem();
        self::assertTrue($filesystem->fileExists($stored), "Stored file should exist: {$stored}");

        $imageFactory = new ImageFactory(new PhpService());

        return $imageFactory->fileResponse($filesystem, $stored, false);
    }

    /**
     * Behavior-only assertion: the served SVG cannot execute scripts in a
     * conformant browser. Two independent paths are accepted:
     *
     *   (a) The executable signature is absent from the body (L1 — content sanitized).
     *   (b) The response carries `content-security-policy: …script-src none…`
     *       (L2 — browser blocks execution regardless of body content).
     *
     * Either suffices. A null response (upload rejected) is the strongest form.
     *
     * @param list<string> $forbiddenSignatures Strings that must not appear in the body
     *                                          unless L2 (CSP) is in place.
     */
    private function assertSvgBrowserSafe(?ResponseInterface $response, array $forbiddenSignatures): void
    {
        if ($response === null) {
            self::assertTrue(true, 'Upload rejected — strongest mitigation (L0)');
            return;
        }

        self::assertSame(
            StatusCodeInterface::STATUS_OK,
            $response->getStatusCode(),
            'Image responses use HTTP 200 even for blocked/placeholder content',
        );

        $csp = $response->getHeaderLine('content-security-policy');
        if (str_contains($csp, 'script-src none')) {
            return;
        }

        $body = (string) $response->getBody();
        foreach ($forbiddenSignatures as $signature) {
            self::assertStringNotContainsString(
                $signature,
                $body,
                sprintf(
                    'Without CSP script-src none, body must not contain executable signature %s',
                    var_export($signature, true),
                ),
            );
        }
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

    /**
     * @return array<string,mixed>
     */
    private function loadSecAudit001Fixture(): array
    {
        $path = '/fixtures/security/payloads/sec_audit_001.json';
        if (!is_file($path)) {
            throw new RuntimeException("Fixture not found: {$path}");
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Fixture not readable: {$path}");
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException("Fixture invalid JSON: {$path}");
        }
        return $data;
    }
}
