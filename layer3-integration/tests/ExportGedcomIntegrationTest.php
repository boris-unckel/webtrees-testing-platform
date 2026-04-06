<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ExportGedcomClient;
use Fisharebest\Webtrees\Http\RequestHandlers\ExportGedcomPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ExportGedcomServer;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\PhpService;

/**
 * Komponentenintegrationstest: Stammbaum-Export (HTTP) — A03.
 *
 * Tests:
 * - ExportGedcomPage GET → 200
 * - ExportGedcomClient POST: format=gedcom → 200 + attachment
 * - ExportGedcomClient POST: format=zip → 200 + application/zip
 * - ExportGedcomServer POST → 302 redirect
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ExportGedcomPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ExportGedcomClient
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ExportGedcomServer
 * @see docs/testquality_improve_A03.md
 */
class ExportGedcomIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('a03-export', 'A03 Export', self::DEMO_GED);
    }

    /**
     * EP6: ExportGedcomPage GET → 200.
     */
    public function test_export_gedcom_page_returns_200(): void
    {
        $handler = new ExportGedcomPage(
            Registry::container()->get(PhpService::class),
        );

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP1: ExportGedcomClient POST format=gedcom → 200 + attachment header.
     */
    public function test_export_client_gedcom_format_returns_200_with_attachment(): void
    {
        $handler = new ExportGedcomClient(
            Registry::container()->get(GedcomExportService::class),
        );

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'filename'     => 'export-test',
                'format'       => 'gedcom',
                'privacy'      => 'none',
                'encoding'     => 'UTF-8',
                'line_endings' => 'LF',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringContainsString('attachment', $response->getHeaderLine('content-disposition'));
    }

    /**
     * EP2: ExportGedcomClient POST format=zip → 200 + application/zip.
     */
    public function test_export_client_zip_format_returns_200(): void
    {
        $handler = new ExportGedcomClient(
            Registry::container()->get(GedcomExportService::class),
        );

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'filename'     => 'export-zip',
                'format'       => 'zip',
                'privacy'      => 'none',
                'encoding'     => 'UTF-8',
                'line_endings' => 'LF',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringStartsWith('application/zip', $response->getHeaderLine('content-type'));
    }

    /**
     * EP4: ExportGedcomServer POST → 302 redirect (success or failure in writing).
     */
    public function test_export_server_redirects(): void
    {
        $handler = new ExportGedcomServer(
            Registry::container()->get(GedcomExportService::class),
        );

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: ['filename' => 'server-export-a03'],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
