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

    // --- EP-Matrix mit korrektem CSV-Format (MapDataService::CSV_SEPARATOR = ';') ---

    /**
     * Korrektes CSV-Format (Semikolon-Trenner, Level-basiert): option=add legt Ort in DB an (EP1 + EP5).
     * DB-Postcondition: place_location enthält Eintrag mit importierten Koordinaten.
     */
    public function test_import_add_creates_location_in_db(): void
    {
        // Korrektes Format: Level;Country;...;Longitude;Latitude;Zoom;Icon
        // Level=0: 0;Canada;;;W106;N56;3;
        $csv = implode("\n", [
            '"Level";"Country";"State";"Longitude";"Latitude";"Zoom level";"Icon";',
            '0;TestCountry99;;;E013.4050;N52.5200;10;',
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

        // DB-Postcondition: Ort wurde mit Koordinaten angelegt
        $location = DB::table('place_location')
            ->where('place', '=', 'TestCountry99')
            ->first();

        $this->assertNotNull($location, 'TestCountry99 muss in place_location vorhanden sein');
        $this->assertEqualsWithDelta(52.52, (float) $location->latitude, 0.01);
        $this->assertEqualsWithDelta(13.405, (float) $location->longitude, 0.01);
    }

    /**
     * Null-Island-Koordinaten (0,0) für Unter-Ort werden gefiltert — nicht in DB (EP6).
     * Nur Orte mit Komma im Namen (multi-level) UND (0,0) werden gefiltert.
     */
    public function test_import_null_island_filtered_for_sublocation(): void
    {
        // Level=1: TestLand99;TestOrt99 → Name = "TestOrt99, TestLand99" (enthält Komma)
        // Koordinaten E0.0;N0.0 → (0,0) → gefiltert
        $csv = implode("\n", [
            '"Level";"Country";"State";"Longitude";"Latitude";"Zoom level";"Icon";',
            '1;TestLand99;TestOrt99;E0.0;N0.0;3;',
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

        // DB-Postcondition: Null-Island-Ort wurde NICHT angelegt
        $exists = DB::table('place_location')
            ->where('place', '=', 'TestOrt99')
            ->exists();

        $this->assertFalse($exists, 'Null-Island-Ort TestOrt99 darf nicht in place_location sein');
    }
}
