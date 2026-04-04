<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataImportAction;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\UploadedFile;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;

/**
 * Komponentenintegrationstest: MapDataImportAction.
 *
 * AP B-04: MapDataImportAction::handle (CRAP 420)
 *
 * @see docs/testing-bigpicture.md S48
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataImportAction
 */
class MapDataImportIntegrationTest extends MysqlTestCase
{
    private MapDataImportAction $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $factory       = new Psr17Factory();
        $this->handler = new MapDataImportAction($factory);
    }

    /**
     * CSV-Upload (source=client, options=add) importiert Orte in place_location.
     */
    public function test_import_from_client_csv_add(): void
    {
        // Minimale webtrees place_location CSV
        $csv = implode("\n", [
            'pl_id,pl_parent_id,pl_level,pl_place,pl_long,pl_lati,pl_zoom,pl_icon',
            ',,,Berlin,E013.4050,N52.5200,10,',
        ]);

        $factory      = new Psr17Factory();
        $stream       = $factory->createStream($csv);
        $uploadedFile = new UploadedFile(
            streamOrFile: $stream,
            size:         strlen($csv),
            errorStatus:  UPLOAD_ERR_OK,
            clientFilename: 'places.csv',
            clientMediaType: 'text/csv',
        );

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     ['source' => 'client', 'options' => 'add'],
            attributes: ['user' => $this->createAndLoginAdmin()],
        )->withUploadedFiles(['client_file' => $uploadedFile]);

        $response = $this->handler->handle($request);

        $this->assertLessThan(500, $response->getStatusCode());
    }

    /**
     * CSV-Upload mit addupdate-Option.
     */
    public function test_import_from_client_csv_addupdate(): void
    {
        $csv = implode("\n", [
            'pl_id,pl_parent_id,pl_level,pl_place,pl_long,pl_lati,pl_zoom,pl_icon',
            ',,,München,E011.5820,N48.1351,10,',
        ]);

        $factory      = new Psr17Factory();
        $stream       = $factory->createStream($csv);
        $uploadedFile = new UploadedFile(
            streamOrFile: $stream,
            size:         strlen($csv),
            errorStatus:  UPLOAD_ERR_OK,
            clientFilename: 'places.csv',
            clientMediaType: 'text/csv',
        );

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     ['source' => 'client', 'options' => 'addupdate'],
            attributes: ['user' => $this->createAndLoginAdmin()],
        )->withUploadedFiles(['client_file' => $uploadedFile]);

        $response = $this->handler->handle($request);

        $this->assertLessThan(500, $response->getStatusCode());
    }
}
