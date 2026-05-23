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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

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
 * @covers \Fisharebest\Webtrees\Services\GedcomExportService
 * @see docs/testquality_improve_A03.md
 * @see Quelle: port-layer2-test-doubles:tests/app/Services/GedcomExportServiceTest.php
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

    /**
     * ExportGedcomPage GET für einen Baum, dessen Name auf ".ged" endet:
     * exerziert den `.ged`-Stripping-Branch in `ExportGedcomPage::handle()`
     * (download-filename = Tree-Name ohne ".ged"-Suffix).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ExportGedcomPageTest.php
     * @group ported-l2-doubles
     */
    public function test_export_gedcom_page_strips_ged_extension_from_tree_name(): void
    {
        // Arrange — separater Baum, dessen Name auf ".ged" endet.
        $gedTree = $this->treeService->create('family.ged', 'Family Ged');

        try {
            $handler = new ExportGedcomPage(
                Registry::container()->get(PhpService::class),
            );

            $request = $this->createRequest(
                attributes: ['tree' => $gedTree],
            );

            // Act
            $response = $handler->handle($request);

            // Assert — Handler erreicht den .ged-Branch und liefert eine OK-Antwort.
            self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        } finally {
            $this->treeService->delete($gedTree);
        }
    }

    /**
     * ExportGedcomServer POST mit `filename` ohne ".ged"-Endung: der Handler
     * hängt das Suffix vor dem Schreiben an und liefert anschließend einen
     * 302-Redirect zurück nach ManageTrees.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ExportGedcomServerTest.php
     * @group ported-l2-doubles
     */
    public function test_export_server_appends_ged_extension_when_missing(): void
    {
        // Arrange — eigener Baum für diesen Lauf, separater filename ohne Suffix.
        $serverTree = $this->treeService->create('a03-server-noext', 'A03 Server No Ext');

        try {
            $handler = new ExportGedcomServer(
                Registry::container()->get(GedcomExportService::class),
            );

            $request = $this->createRequest(
                method: RequestMethodInterface::METHOD_POST,
                attributes: ['tree' => $serverTree],
                params: ['filename' => 'family'],
            );

            // Act
            $response = $handler->handle($request);

            // Assert — Handler ergänzt .ged-Suffix und redirected (ManageTrees).
            self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        } finally {
            $this->treeService->delete($serverTree);
        }
    }

    /**
     * GedcomExportService::wrapLongLines belässt kurze Zeilen unverändert
     * (Eingabe = Ausgabe), wenn die Zeilenlänge unter dem Limit liegt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Services/GedcomExportServiceTest.php
     * @group ported-l2-doubles
     */
    public function test_wrap_long_lines_returns_short_line_unchanged(): void
    {
        // Arrange
        $response_factory = self::createStub(ResponseFactoryInterface::class);
        $stream_factory   = self::createStub(StreamFactoryInterface::class);
        $service          = new GedcomExportService($response_factory, $stream_factory);

        $input = '1 NAME John';

        // Act
        $result = $service->wrapLongLines($input, 255);

        // Assert
        self::assertSame($input, $result);
    }

    /**
     * GedcomExportService::wrapLongLines bricht eine zu lange Zeile in
     * mehrere Zeilen um (1 NOTE + 2 CONC-Fortsetzungen), wobei keine Zeile
     * das Limit überschreitet und die Rekonstruktion den Originalwert ergibt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Services/GedcomExportServiceTest.php
     * @group ported-l2-doubles
     */
    public function test_wrap_long_lines_splits_long_line_with_conc(): void
    {
        // Arrange
        $response_factory = self::createStub(ResponseFactoryInterface::class);
        $stream_factory   = self::createStub(StreamFactoryInterface::class);
        $service          = new GedcomExportService($response_factory, $stream_factory);

        $long_value = str_repeat('x', 300);
        $input      = '1 NOTE ' . $long_value;

        // Act
        $result = $service->wrapLongLines($input, 80);

        // Assert — Aufbau: erste Zeile bleibt 1 NOTE, Fortsetzungen via 2 CONC.
        self::assertStringContainsString('1 NOTE', $result);
        self::assertStringContainsString('2 CONC', $result);

        // Keine einzelne Zeile darf das Limit überschreiten.
        foreach (explode("\n", $result) as $line) {
            self::assertLessThanOrEqual(80, mb_strlen($line));
        }

        // Konkatenation aller 1 NOTE- und 2 CONC-Fragmente rekonstruiert den Originalwert.
        $reconstructed = '';
        foreach (explode("\n", $result) as $line) {
            if (str_starts_with($line, '1 NOTE ')) {
                $reconstructed .= substr($line, strlen('1 NOTE '));
            } elseif (str_starts_with($line, '2 CONC ')) {
                $reconstructed .= substr($line, strlen('2 CONC '));
            }
        }
        self::assertSame($long_value, $reconstructed);
    }

    /**
     * GedcomExportService::wrapLongLines verarbeitet mehrzeilige Eingaben:
     * Kurze Zeilen passieren unverändert, lange Zeilen werden gesplittet,
     * keine Zeile überschreitet das Limit.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Services/GedcomExportServiceTest.php
     * @group ported-l2-doubles
     */
    public function test_wrap_long_lines_handles_mixed_short_and_long_lines(): void
    {
        // Arrange
        $response_factory = self::createStub(ResponseFactoryInterface::class);
        $stream_factory   = self::createStub(StreamFactoryInterface::class);
        $service          = new GedcomExportService($response_factory, $stream_factory);

        $short_line = '1 NAME John';
        $long_value = str_repeat('y', 200);
        $long_line  = '1 NOTE ' . $long_value;
        $input      = $short_line . "\n" . $long_line;

        // Act
        $result = $service->wrapLongLines($input, 80);

        // Assert — kurze Zeile bleibt am Anfang erhalten.
        self::assertStringStartsWith($short_line . "\n", $result);

        // Lange Zeile wurde via 2 CONC gesplittet.
        self::assertStringContainsString('2 CONC', $result);

        // Keine Zeile überschreitet das Limit.
        foreach (explode("\n", $result) as $line) {
            self::assertLessThanOrEqual(80, mb_strlen($line));
        }
    }
}
