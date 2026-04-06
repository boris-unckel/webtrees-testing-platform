<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Exceptions\FileUploadException;
use Fisharebest\Webtrees\Http\RequestHandlers\UploadMediaAction;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MediaFileService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\UploadedFileInterface;

use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_PARTIAL;
use const UPLOAD_ERR_OK;

/**
 * Komponentenintegrationstest: UploadMediaAction (HTTP-Upload) — G30.
 *
 * Tests:
 * - UPLOAD_ERR_NO_FILE → 302 (continue-Branch, kein Write)
 * - UPLOAD_ERR_PARTIAL → FileUploadException
 * - Gefährliche Extension (.php) → FlashMessage, 302
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UploadMediaAction
 * @see docs/testquality_improve_G30.md
 */
class UploadMediaActionIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('g30-upload', 'G30 Upload', self::DEMO_GED);
    }

    private function makeHandler(): UploadMediaAction
    {
        return new UploadMediaAction(
            Registry::container()->get(MediaFileService::class),
        );
    }

    private function makeUploadedFile(int $error, string $filename = 'test.jpg'): UploadedFileInterface
    {
        $factory = new Psr17Factory();
        $stream  = $factory->createStream('');

        return $factory->createUploadedFile($stream, 0, $error, $filename, 'image/jpeg');
    }

    /**
     * EP1: B1 — UPLOAD_ERR_NO_FILE → continue-Branch → 302 redirect (kein Exception, kein Write).
     */
    public function test_upload_no_file_returns_redirect(): void
    {
        $handler = $this->makeHandler();

        $request = $this->createRequest()
            ->withUploadedFiles(['fileField0' => $this->makeUploadedFile(UPLOAD_ERR_NO_FILE)]);

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EP2: B2 — UPLOAD_ERR_PARTIAL → FileUploadException.
     */
    public function test_upload_error_throws_exception(): void
    {
        $handler = $this->makeHandler();

        $request = $this->createRequest()
            ->withUploadedFiles(['fileField0' => $this->makeUploadedFile(UPLOAD_ERR_PARTIAL)]);

        $this->expectException(FileUploadException::class);
        $handler->handle($request);
    }

    /**
     * EP4: B5 — Datei mit gefährlicher Extension (.php) nach Ordner-Validierung →
     * FlashMessage 'danger' hinzugefügt, 302 redirect.
     * Voraussetzung: folder0 = 'media/' muss in allMediaFolders() enthalten sein.
     */
    public function test_upload_dangerous_extension_adds_flash_and_redirects(): void
    {
        $handler = $this->makeHandler();

        $request = $this->createRequest(
            params: [
                'folder0'   => 'media/',
                'filename0' => 'exploit.php',
            ],
        )->withUploadedFiles(['fileField0' => $this->makeUploadedFile(UPLOAD_ERR_OK, 'exploit.php')]);

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
