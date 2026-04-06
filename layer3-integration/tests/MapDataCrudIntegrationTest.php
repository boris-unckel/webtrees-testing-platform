<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataDelete;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataExportCSV;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataList;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataSave;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MapDataService;
use Fisharebest\Webtrees\Services\ModuleService;

/**
 * Komponentenintegrationstest: Standortdaten-CRUD — S52.
 *
 * Tests:
 * - MapDataSave POST: Insert (neue location) → redirect + DB-Eintrag
 * - MapDataSave POST: Update (vorhandene location) → redirect + DB aktualisiert
 * - MapDataDelete POST: Eintrag löschen → redirect + DB leer
 * - MapDataExportCSV GET → 200 + text/csv
 * - MapDataList GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataSave
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataDelete
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataExportCSV
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataList
 * @see docs/testquality_improve_S52.md
 */
class MapDataCrudIntegrationTest extends MysqlTestCase
{
    private const LOCAL_URL = 'https://webtrees.test/admin/locations';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
    }

    /**
     * EP1: MapDataSave POST Insert (leere place_id) → redirect + neuer DB-Eintrag.
     */
    public function test_map_data_save_inserts_new_location(): void
    {
        $handler = new MapDataSave();

        $place_name = 'TestOrt-S52-' . uniqid();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'parent_id'      => '',
                'place_id'       => '',
                'new_place_lati' => '51.5',
                'new_place_long' => '9.0',
                'new_place_name' => $place_name,
                'url'            => self::LOCAL_URL,
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: Eintrag in DB
        $exists = DB::table('place_location')
            ->where('place', '=', $place_name)
            ->whereNull('parent_id')
            ->exists();

        self::assertTrue($exists);
    }

    /**
     * EP2: MapDataSave POST Update (vorhandene place_id) → redirect + DB geändert.
     */
    public function test_map_data_save_updates_existing_location(): void
    {
        // Vorhandenen Eintrag anlegen
        DB::table('place_location')->insert([
            'parent_id' => null,
            'place'     => 'OrtZuAendern-S52',
            'latitude'  => 50.0,
            'longitude' => 8.0,
        ]);

        $place_id = (string) DB::table('place_location')
            ->where('place', '=', 'OrtZuAendern-S52')
            ->value('id');

        $handler = new MapDataSave();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'parent_id'      => '',
                'place_id'       => $place_id,
                'new_place_lati' => '52.0',
                'new_place_long' => '10.0',
                'new_place_name' => 'OrtGeaendert-S52',
                'url'            => self::LOCAL_URL,
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: Name aktualisiert
        $updated = DB::table('place_location')
            ->where('id', '=', $place_id)
            ->value('place');

        self::assertSame('OrtGeaendert-S52', $updated);
    }

    /**
     * EP4: MapDataDelete POST → redirect + Eintrag aus DB entfernt.
     */
    public function test_map_data_delete_removes_location(): void
    {
        // Eintrag anlegen
        DB::table('place_location')->insert([
            'parent_id' => null,
            'place'     => 'OrtZuLoeschen-S52',
            'latitude'  => null,
            'longitude' => null,
        ]);

        $location_id = (int) DB::table('place_location')
            ->where('place', '=', 'OrtZuLoeschen-S52')
            ->value('id');

        $handler = new MapDataDelete(
            Registry::container()->get(MapDataService::class),
        );

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['location_id' => $location_id],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: nicht mehr in DB
        $exists = DB::table('place_location')
            ->where('id', '=', $location_id)
            ->exists();

        self::assertFalse($exists);
    }

    /**
     * EP6: MapDataExportCSV GET → 200 + text/csv.
     */
    public function test_map_data_export_csv_returns_csv(): void
    {
        $handler = new MapDataExportCSV(
            Registry::container()->get(MapDataService::class),
        );

        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringStartsWith('text/csv', $response->getHeaderLine('content-type'));
    }

    /**
     * EP7: MapDataList GET → 200.
     */
    public function test_map_data_list_returns_200(): void
    {
        $handler = new MapDataList(
            Registry::container()->get(MapDataService::class),
            new ModuleService(),
            $this->treeService,
        );

        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
