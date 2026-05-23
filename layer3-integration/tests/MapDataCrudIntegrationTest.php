<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateLocationModal;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataAdd;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataDelete;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataDeleteUnused;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataEdit;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataExportCSV;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataExportGeoJson;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataList;
use Fisharebest\Webtrees\Http\RequestHandlers\MapDataSave;
use Fisharebest\Webtrees\PlaceLocation;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\LeafletJsService;
use Fisharebest\Webtrees\Services\MapDataService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: Standortdaten-CRUD — S52.
 *
 * Tests:
 * - MapDataSave POST: Insert (neue location) → redirect + DB-Eintrag
 * - MapDataSave POST: Update (vorhandene location) → redirect + DB aktualisiert
 * - MapDataDelete POST: Eintrag löschen → redirect + DB leer
 * - MapDataExportCSV GET → 200 + text/csv
 * - MapDataList GET → 200
 * - CreateLocationModal GET → 200 (Modal-Dialog für neue Location)
 * - MapDataAdd GET → 200 (Add-Formular ohne parent_id).
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataSave
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataDelete
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataDeleteUnused
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataExportCSV
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataExportGeoJson
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataList
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateLocationModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataAdd
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MapDataEdit
 * @see docs/testquality_improve_S52.md
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateLocationModalTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataAddTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataDeleteTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataDeleteUnusedTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataEditTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataExportCSVTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataExportGeoJsonTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataListTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataSaveTest.php
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
     * Sanity-Check: MapDataExportCSV-Klasse ist im Autoloader verfügbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataExportCSVTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_export_csv_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(MapDataExportCSV::class));
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

    /**
     * Sanity-Check: CreateLocationModal-Klasse ist im Autoloader verfügbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateLocationModalTest.php
     * @group ported-l2-doubles
     */
    public function test_create_location_modal_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(CreateLocationModal::class));
    }

    /**
     * CreateLocationModal GET → 200 (Modal-Dialog mit gültigem Tree-Attribut).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateLocationModalTest.php
     * @group ported-l2-doubles
     */
    public function test_create_location_modal_handle_returns_ok(): void
    {
        // Arrange
        $tree    = $this->treeService->create('clm-' . substr(md5($this->name()), 0, 8), 'CLM Tree');
        $handler = new CreateLocationModal();
        $request = $this->createRequest(
            attributes: ['tree' => $tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Sanity-Check: MapDataAdd-Klasse ist im Autoloader verfügbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataAddTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_add_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(MapDataAdd::class));
    }

    /**
     * MapDataAdd GET ohne parent_id-Attribut → 200 (Welt-Wurzelknoten als Default-Parent).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataAddTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_add_handle_with_no_parent_returns_ok(): void
    {
        // Arrange
        $leaflet_js_service = new LeafletJsService(new ModuleService());
        $map_data_service   = Registry::container()->get(MapDataService::class);
        self::assertInstanceOf(MapDataService::class, $map_data_service);

        $handler = new MapDataAdd($leaflet_js_service, $map_data_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Sanity-Check: MapDataDelete-Klasse ist im Autoloader verfügbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataDeleteTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_delete_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(MapDataDelete::class));
    }

    /**
     * MapDataDelete::handle: gestubter PlaceLocation + gemockter MapDataService →
     * findById(42) und deleteRecursively(42) werden aufgerufen, Response ist 302.
     *
     * Stub/Mock-Konvention: PlaceLocation = Domain-Objekt → createStub;
     * MapDataService = Service → createMock mit Verhaltens-Assertion.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataDeleteTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_delete_handle_with_mock_service_returns_found(): void
    {
        // Arrange
        $parent = self::createStub(PlaceLocation::class);
        $parent->method('id')->willReturn(null);

        $location = self::createStub(PlaceLocation::class);
        $location->method('parent')->willReturn($parent);

        $map_data_service = $this->createMock(MapDataService::class);
        $map_data_service->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($location);
        $map_data_service->expects(self::once())
            ->method('deleteRecursively')
            ->with(42);

        $handler = new MapDataDelete($map_data_service);
        $request = $this->createRequest(
            attributes: ['location_id' => '42'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Sanity-Check: MapDataDeleteUnused-Klasse ist im Autoloader verfügbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataDeleteUnusedTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_delete_unused_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(MapDataDeleteUnused::class));
    }

    /**
     * MapDataDeleteUnused::handle: gemockter MapDataService → deleteUnusedLocations(null, [0])
     * wird genau einmal aufgerufen, Response ist 302 (Redirect zur Map-Data-Liste).
     *
     * Stub/Mock-Konvention: MapDataService = Service → createMock mit Verhaltens-Assertion.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataDeleteUnusedTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_delete_unused_handle_deletes_and_redirects(): void
    {
        // Arrange
        $map_data_service = $this->createMock(MapDataService::class);
        $map_data_service->expects(self::once())
            ->method('deleteUnusedLocations')
            ->with(null, [0]);

        $handler = new MapDataDeleteUnused($map_data_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Sanity-Check: MapDataEdit-Klasse ist im Autoloader verfügbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataEditTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_edit_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(MapDataEdit::class));
    }

    /**
     * MapDataEdit::handle: für eine nicht-existierende Location liefert MapDataService::findById()
     * eine PlaceLocation mit id() === null. Der Handler leitet in diesem Fall auf die Listen-Seite
     * um (HTTP 302).
     *
     * Stub/Mock-Konvention: PlaceLocation = Domain-Objekt (hier real instanziiert mit leerem Pfad,
     * da das Original-Verhalten id() === null erfordert); MapDataService und LeafletJsService =
     * Services → createMock mit Verhaltens-Assertion auf findById().
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataEditTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_edit_handle_non_existent_location_redirects(): void
    {
        // Arrange
        // PlaceLocation mit leerem Pfad liefert id() === null (unbekannte Location).
        $location = new PlaceLocation('');

        $map_data_service = $this->createMock(MapDataService::class);
        $map_data_service->expects(self::once())
            ->method('findById')
            ->with(999)
            ->willReturn($location);

        $leaflet_js_service = $this->createMock(LeafletJsService::class);

        $handler = new MapDataEdit($leaflet_js_service, $map_data_service);
        $request = $this->createRequest(
            attributes: ['location_id' => '999'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Sanity-Check: MapDataExportGeoJson-Klasse ist im Autoloader verfügbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataExportGeoJsonTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_export_geojson_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(MapDataExportGeoJson::class));
    }

    /**
     * MapDataExportGeoJson GET → 200 + content-type application/vnd.geo+json.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataExportGeoJsonTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_export_geojson_returns_geojson(): void
    {
        // Arrange
        $handler = new MapDataExportGeoJson(
            Registry::container()->get(MapDataService::class),
        );

        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('application/vnd.geo+json', $response->getHeaderLine('content-type'));
    }

    /**
     * MapDataList::handle: ohne parent_id-Attribut werden importMissingLocations(),
     * activePlaces(), getPlaceListLocation(null), ModuleService::findByInterface()
     * sowie TreeService::all() jeweils genau einmal aufgerufen; Response ist 200.
     *
     * Stub/Mock-Konvention: MapDataService, ModuleService, TreeService sind Services
     * (Verhaltens-Verifikation) → createMock mit expects(self::once()).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataListTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_list_handle_with_no_parent_id_invokes_collaborators(): void
    {
        // Arrange
        $map_data_service = $this->createMock(MapDataService::class);
        $map_data_service->expects(self::once())
            ->method('importMissingLocations');
        $map_data_service->expects(self::once())
            ->method('activePlaces')
            ->willReturn([]);
        $map_data_service->expects(self::once())
            ->method('getPlaceListLocation')
            ->with(null)
            ->willReturn(new Collection());

        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler = new MapDataList($map_data_service, $module_service, $tree_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Sanity-Check: MapDataSave-Klasse ist im Autoloader verfügbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataSaveTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_save_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(MapDataSave::class));
    }

    /**
     * MapDataSave POST mit leeren Koordinaten → 302-Redirect + DB-Eintrag mit
     * NULL-Latitude/Longitude (Edge Case: Standort ohne Geokoordinaten).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MapDataSaveTest.php
     * @group ported-l2-doubles
     */
    public function test_map_data_save_with_empty_coordinates_creates_location(): void
    {
        // Arrange
        $handler    = new MapDataSave();
        $place_name = 'OrtOhneKoords-S52-' . uniqid();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'parent_id'      => '',
                'place_id'       => '',
                'new_place_lati' => '',
                'new_place_long' => '',
                'new_place_name' => $place_name,
                'url'            => self::LOCAL_URL,
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: Eintrag in DB existiert mit NULL-Koordinaten
        $row = DB::table('place_location')
            ->where('place', '=', $place_name)
            ->whereNull('parent_id')
            ->first();

        self::assertNotNull($row);
        self::assertNull($row->latitude);
        self::assertNull($row->longitude);
    }
}
