<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Exceptions\FileUploadException;
use Fisharebest\Webtrees\Http\RequestHandlers\ImportGedcomAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ImportGedcomPage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\AdminService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_PARTIAL;

/**
 * Komponentenintegrationstest: Stammbaum-Import (HTTP) — A02.
 *
 * Tests:
 * - ImportGedcomPage GET → 200
 * - ImportGedcomAction POST source=client, kein File → 302
 * - ImportGedcomAction POST source=client, UPLOAD_ERR_PARTIAL → FileUploadException
 * - ImportGedcomAction POST source=server, leerer Dateiname → 302
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ImportGedcomPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ImportGedcomAction
 * @see docs/testquality_improve_A02.md
 */
class ImportGedcomActionIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('a02-import', 'A02 Import', self::DEMO_GED);
    }

    private function makeUploadedFile(int $error): UploadedFileInterface
    {
        $factory = new Psr17Factory();
        $stream  = $factory->createStream('');

        return $factory->createUploadedFile($stream, 0, $error, 'test.ged', 'text/plain');
    }

    private function makeImportAction(): ImportGedcomAction
    {
        return new ImportGedcomAction(
            Registry::container()->get(StreamFactoryInterface::class),
            $this->treeService,
        );
    }

    /**
     * EP5: ImportGedcomPage GET → 200.
     */
    public function test_import_gedcom_page_returns_200(): void
    {
        $handler = new ImportGedcomPage(
            Registry::container()->get(AdminService::class),
        );

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP1: source=client, UPLOAD_ERR_NO_FILE → 302 zu ImportGedcomPage.
     */
    public function test_import_action_with_no_file_redirects(): void
    {
        $handler = $this->makeImportAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'keep_media'         => '0',
                'WORD_WRAPPED_NOTES' => '0',
                'GEDCOM_MEDIA_PATH'  => '',
                'encoding'           => '',
                'source'             => 'client',
            ],
        )->withUploadedFiles(['client_file' => $this->makeUploadedFile(UPLOAD_ERR_NO_FILE)]);

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EP2: source=client, UPLOAD_ERR_PARTIAL → FileUploadException.
     */
    public function test_import_action_with_partial_upload_throws_exception(): void
    {
        $handler = $this->makeImportAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'keep_media'         => '0',
                'WORD_WRAPPED_NOTES' => '0',
                'GEDCOM_MEDIA_PATH'  => '',
                'encoding'           => '',
                'source'             => 'client',
            ],
        )->withUploadedFiles(['client_file' => $this->makeUploadedFile(UPLOAD_ERR_PARTIAL)]);

        $this->expectException(FileUploadException::class);
        $handler->handle($request);
    }

    /**
     * EP4: source=server, leerer Dateiname → 302 zu ImportGedcomPage.
     */
    public function test_import_action_with_empty_server_file_redirects(): void
    {
        $handler = $this->makeImportAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'keep_media'         => '0',
                'WORD_WRAPPED_NOTES' => '0',
                'GEDCOM_MEDIA_PATH'  => '',
                'encoding'           => '',
                'source'             => 'server',
                'server_file'        => '',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
