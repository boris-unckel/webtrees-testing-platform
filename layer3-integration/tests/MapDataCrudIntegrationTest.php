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
 * @see docs/tds_conditions_ref.md S52
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
     * MapDataExportCSV GET ohne parent_id-Attribut: pinnt CSV-Header-Zeile und
     * Default-Dateinamen "Global.csv" im Content-Disposition.
     *
     * Property aus SUT (MapDataExportCSV::handle, Zeile 70 + 130-142): bei null
     * parent_id wird "Global" als Hierarchie-Fallback verwendet (Dateiname:
     * "Global.csv"). Erste CSV-Zeile ist immer die englische Header-Zeile mit
     * Spalten Level;Longitude;Latitude;Zoom;Icon (Trenner aus
     * MapDataService::CSV_SEPARATOR = ";").
     *
     * Komplementär zu test_map_data_export_csv_returns_csv: dort wird nur Status
     * und content-type geprüft — hier werden CSV-Body-Property und
     * content-disposition-Header gepinnt.
     *
     * @group ported-l2-doubles
     */
    public function test_map_data_export_csv_pins_header_and_default_filename(): void
    {
        // Arrange
        $handler = new MapDataExportCSV(
            Registry::container()->get(MapDataService::class),
        );
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);
        $body     = (string) $response->getBody();

        // Assert: Status + content-disposition mit Default-Dateinamen "Global.csv"
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame(
            'attachment; filename="Global.csv"',
            $response->getHeaderLine('content-disposition'),
        );

        // Assert: Header-Zeile als erste CSV-Zeile mit ; als Trenner
        // Level kann von weiteren PlaceN-Spalten gefolgt sein (max_level >= 0),
        // gefolgt von Longitude;Latitude;Zoom;Icon als Suffix.
        self::assertNotSame('', $body);
        $first_line = strtok($body, "\n");
        self::assertNotFalse($first_line);
        self::assertStringStartsWith('Level', $first_line);
        self::assertStringEndsWith('Longitude;Latitude;Zoom;Icon', rtrim($first_line, "\r"));
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
     * CreateLocationModal GET: pinnt Modal-Form-Action mit Tree-Name-Routing und
     * CSRF-Token im Response-Body.
     *
     * Property aus SUT (CreateLocationModal::handle): rendert View
     * "modals/create-location" mit dem Tree-Attribut aus dem Request. Die
     * Template-Datei resources/views/modals/create-location.phtml baut das
     * Form-Action via route(CreateLocationAction::class, ['tree' => $tree->name()])
     * und fügt einen csrf_field()-Aufruf in das Form ein.
     *
     * Komplementär zu test_create_location_modal_handle_returns_ok: dort wird nur
     * der Statuscode 200 geprüft — hier werden Form-Action-Route und CSRF-Hidden-Field
     * im Body gepinnt.
     *
     * @group ported-l2-doubles
     */
    public function test_create_location_modal_pins_form_action_and_csrf(): void
    {
        // Arrange
        // Tree an $this->tree binden, damit MysqlTestCase::tearDown() ihn aufräumt.
        $this->tree = $this->treeService->create('clm-' . substr(md5($this->name()), 0, 8), 'CLM Modal Pin');
        $handler    = new CreateLocationModal();
        $request    = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);
        $body     = (string) $response->getBody();

        // Assert: Form-Action enthält Tree-Name als Bestandteil des Routing-Pfads
        // (URL-encoded: "/<tree-name>/create-location" wird zu
        // "route=%2F<tree-name>%2Fcreate-location"). Pinnt damit, dass das Template
        // den Tree-Namen aus dem Request-Attribut in das Form-Action propagiert.
        self::assertNotSame('', $body);
        self::assertStringContainsString('id="wt-modal-form"', $body);
        self::assertStringContainsString(
            'route=%2F' . $this->tree->name() . '%2Fcreate-location',
            $body,
        );

        // Assert: CSRF-Hidden-Field via csrf_field() im Form.
        self::assertStringContainsString('name="_csrf"', $body);
    }

    /**
     * CreateLocationModal GET → 200 (Modal-Dialog mit gültigem Tree-Attribut).
     *
     * @group ported-l2-doubles
     */
    public function test_create_location_modal_handle_returns_ok(): void
    {
        // Arrange
        // Tree an $this->tree binden, damit MysqlTestCase::tearDown() ihn aufräumt.
        // Sonst bleibt der wt_gedcom-Eintrag stehen und blockt den nächsten Lauf
        // mit Duplicate-Key (gedcom_name aus md5(Testname) ist deterministisch).
        $this->tree = $this->treeService->create('clm-' . substr(md5($this->name()), 0, 8), 'CLM Tree');
        $handler    = new CreateLocationModal();
        $request    = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * MapDataAdd GET ohne parent_id-Attribut: pinnt Form-Action-Route zu MapDataSave
     * und Hidden-Input für leere parent_id im Response-Body.
     *
     * Property aus SUT (MapDataAdd::handle): bei null parent_id wird ein neuer
     * PlaceLocation('') als Wurzel-Parent gesetzt (id() === null). Das gerenderte
     * Template "admin/location-edit" baut das Form-Action via route(MapDataSave::class)
     * und schreibt parent->id() (= null → leerer String) in das Hidden-Input
     * name="parent_id". Die MapDataSave-Route ist auf /map-data-update registriert
     * (siehe WebRoutes.php), URL-encoded "%2Fmap-data-update".
     *
     * Komplementär zu test_map_data_add_handle_with_no_parent_returns_ok: dort wird
     * nur der Statuscode 200 geprüft — hier werden Form-Action-Route (Beleg für
     * korrekt gewähltes location-edit-Template) und leeres parent_id-Hidden-Input
     * (Beleg für den null-parent-Pfad in handle()) gepinnt.
     *
     * @group ported-l2-doubles
     */
    public function test_map_data_add_pins_form_action_and_empty_parent_id(): void
    {
        // Arrange
        $leaflet_js_service = new LeafletJsService(new ModuleService());
        $map_data_service   = Registry::container()->get(MapDataService::class);
        self::assertInstanceOf(MapDataService::class, $map_data_service);

        $handler = new MapDataAdd($leaflet_js_service, $map_data_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);
        $body     = (string) $response->getBody();

        // Assert: Body ist nicht leer und enthält das Form-Action zur MapDataSave-Route.
        // Die Route /map-data-update wird URL-encoded als route=%2Fmap-data-update
        // im Action-Attribut serialisiert — pinnt damit, dass das location-edit-Template
        // gewählt und mit dem korrekten Save-Routing-Ziel gerendert wurde.
        self::assertNotSame('', $body);
        self::assertStringContainsString('route=%2Fmap-data-update', $body);

        // Assert: Hidden-Input parent_id ist leer, weil PlaceLocation('') id() === null
        // liefert und das Template $parent->id() (also null → '') in den value schreibt.
        // Pinnt den null-parent-Verzweigungspfad in MapDataAdd::handle.
        self::assertStringContainsString('name="parent_id" value=""', $body);
    }

    /**
     * MapDataAdd GET ohne parent_id-Attribut → 200 (Welt-Wurzelknoten als Default-Parent).
     *
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
     * MapDataDelete::handle: Location-Header der 302-Redirect-Response enthält die
     * MapDataList-Route mit parent_id aus place->parent()->id(), nicht aus dem
     * Request-Attribut.
     *
     * Property aus SUT (MapDataDelete::handle, Zeile 45): der Redirect-URL wird via
     * route(MapDataList::class, ['parent_id' => $place->parent()->id()]) gebaut —
     * also propagiert der Handler die Parent-Id des per findById() ermittelten
     * Place-Records, nicht die im Request übergebene location_id. Die MapDataList-Route
     * ist auf /map-data{/parent_id} registriert (siehe WebRoutes.php Zeile 392),
     * URL-encoded als "route=%2Fmap-data%2F<parent_id>".
     *
     * Komplementär zu test_map_data_delete_handle_with_mock_service_returns_found:
     * dort werden findById(42)/deleteRecursively(42) und Statuscode 302 verifiziert —
     * hier wird der Pfad place->parent()->id() → Redirect-URL gepinnt (der Test geht
     * rot, wenn der Handler stattdessen die location_id aus dem Request für die
     * Redirect-Ziel-Route nutzen würde).
     *
     * @group ported-l2-doubles
     */
    public function test_map_data_delete_redirects_to_parent_in_list(): void
    {
        // Arrange
        // Parent mit bekannter id=7; deleteRecursively wird auf location_id=42 ausgeführt.
        // So lässt sich pinnen, dass der Redirect-URL die parent->id() (=7) und nicht
        // die location_id (=42) als parent_id-Parameter trägt.
        $parent = self::createStub(PlaceLocation::class);
        $parent->method('id')->willReturn(7);

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

        // Assert: Statuscode 302 (redundant zur Schwester-Methode, aber Voraussetzung
        // für eine sinnvolle Location-Header-Prüfung).
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Assert: Location-Header enthält die URL-encoded MapDataList-Route
        // /map-data/7 → "route=%2Fmap-data%2F7". Pinnt damit den Pfad
        // place->parent()->id() → route(MapDataList::class, ['parent_id' => 7]).
        $location_header = $response->getHeaderLine('location');
        self::assertNotSame('', $location_header);
        self::assertStringContainsString('route=%2Fmap-data%2F7', $location_header);
    }

    /**
     * MapDataDelete::handle: gestubter PlaceLocation + gemockter MapDataService →
     * findById(42) und deleteRecursively(42) werden aufgerufen, Response ist 302.
     *
     * Stub/Mock-Konvention: PlaceLocation = Domain-Objekt → createStub;
     * MapDataService = Service → createMock mit Verhaltens-Assertion.
     *
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
     * MapDataDeleteUnused::handle: Location-Header der 302-Redirect-Response enthält
     * die MapDataList-Route ohne parent_id — der Handler ruft route(MapDataList::class)
     * ohne Parameter auf und propagiert deshalb keine Parent-Id in den Redirect.
     *
     * Property aus SUT (MapDataDeleteUnused::handle, Zeile 41): der Redirect-URL wird
     * via route(MapDataList::class) gebaut — also ohne parent_id-Parameter. Die
     * MapDataList-Route ist auf /map-data{/parent_id} registriert (siehe WebRoutes.php
     * Zeile 392) mit parent_id als optionalem Segment, URL-encoded als
     * "route=%2Fmap-data" (kein nachgestelltes "%2F<id>").
     *
     * Komplementär zu test_map_data_delete_unused_handle_deletes_and_redirects: dort
     * werden deleteUnusedLocations(null, [0]) und Statuscode 302 verifiziert — hier
     * wird der Pfad route(MapDataList::class) → Redirect-URL gepinnt (der Test geht
     * rot, wenn der Handler stattdessen eine parent_id in die Redirect-Ziel-Route
     * einbauen würde, etwa route(MapDataList::class, ['parent_id' => ...])).
     *
     * @group ported-l2-doubles
     */
    public function test_map_data_delete_unused_redirects_to_list_without_parent_id(): void
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

        // Assert: Statuscode 302 (redundant zur Schwester-Methode, aber Voraussetzung
        // für eine sinnvolle Location-Header-Prüfung).
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Assert: Location-Header enthält die URL-encoded MapDataList-Route /map-data
        // → "route=%2Fmap-data" und gerade KEIN nachgestelltes "%2F<id>". Pinnt damit
        // den Pfad route(MapDataList::class) ohne parent_id im Handler.
        $location_header = $response->getHeaderLine('location');
        self::assertNotSame('', $location_header);
        self::assertStringContainsString('route=%2Fmap-data', $location_header);
        self::assertDoesNotMatchRegularExpression(
            '/route=%2Fmap-data%2F[^&]+/',
            $location_header,
        );
    }

    /**
     * MapDataDeleteUnused::handle: gemockter MapDataService → deleteUnusedLocations(null, [0])
     * wird genau einmal aufgerufen, Response ist 302 (Redirect zur Map-Data-Liste).
     *
     * Stub/Mock-Konvention: MapDataService = Service → createMock mit Verhaltens-Assertion.
     *
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
     * MapDataEdit::handle: für eine in DB existierende Location (id !== null) rendert
     * der Handler das Template "admin/location-edit" mit Status 200 und schreibt
     * Form-Action zur MapDataSave-Route sowie die Hidden-Inputs place_id (= DB-id)
     * und new_place_name (= locationName) in den Response-Body.
     *
     * Property aus SUT (MapDataEdit::handle, Zeile 54–102): bei nicht-null location->id()
     * wird der Redirect-Pfad übersprungen und stattdessen viewResponse('admin/location-edit',
     * [...]) gerendert. Das Template (admin/location-edit.phtml) baut das Form-Action via
     * route(MapDataSave::class) — URL-encoded "%2Fmap-data-update" — und schreibt
     * $location->id() in das Hidden-Input name="place_id" sowie $location->locationName()
     * in das Input name="new_place_name".
     *
     * Komplementär zu test_map_data_edit_handle_non_existent_location_redirects: dort wird
     * der id===null-Pfad (302-Redirect) gepinnt — hier wird der id!==null-Pfad
     * (200 + gerenderter Form-Body) gepinnt. Beweist, dass der Handler im "edit"-Fall
     * tatsächlich das location-edit-Template rendert und die DB-Identität der Location
     * sowie ihren place-Namen in das Form propagiert (geht rot, falls der Handler bei
     * existierender Location stattdessen umleitet oder ein anderes Template wählt).
     *
     * @group ported-l2-doubles
     */
    public function test_map_data_edit_handle_existing_location_renders_form(): void
    {
        // Arrange: reale Location in DB anlegen, damit MapDataService::findById eine
        // PlaceLocation mit id() !== null zurückliefert und der Handler den
        // Render-Pfad statt des Redirect-Pfads geht.
        $place_name = 'OrtZumBearbeiten-S52-' . uniqid();
        DB::table('place_location')->insert([
            'parent_id' => null,
            'place'     => $place_name,
            'latitude'  => null,
            'longitude' => null,
        ]);
        $location_id = (int) DB::table('place_location')
            ->where('place', '=', $place_name)
            ->value('id');

        $handler = new MapDataEdit(
            new LeafletJsService(new ModuleService()),
            Registry::container()->get(MapDataService::class),
        );
        $request = $this->createRequest(
            attributes: ['location_id' => (string) $location_id],
        );

        // Act
        $response = $handler->handle($request);
        $body     = (string) $response->getBody();

        // Assert: Statuscode 200 (Render-Pfad, nicht Redirect).
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Assert: Body ist nicht leer und enthält das Form-Action zur MapDataSave-Route.
        // URL-encoded "%2Fmap-data-update" beweist, dass das Template location-edit
        // gerendert wurde und route(MapDataSave::class) korrekt aufgelöst hat.
        self::assertNotSame('', $body);
        self::assertStringContainsString('route=%2Fmap-data-update', $body);

        // Assert: Hidden-Input place_id trägt die DB-id der Location — pinnt damit,
        // dass der Handler die per findById() ermittelte Location-Identität in das
        // Form propagiert (Beleg für $location->id() im Template).
        self::assertStringContainsString(
            'name="place_id" value="' . $location_id . '"',
            $body,
        );

        // Assert: locationName wird in das Input new_place_name geschrieben — pinnt
        // damit den Pfad $location->locationName() → Template-Value.
        self::assertStringContainsString(
            'name="new_place_name" value="' . $place_name . '"',
            $body,
        );
    }

    /**
     * MapDataEdit::handle: für eine nicht-existierende Location liefert MapDataService::findById()
     * eine PlaceLocation mit id() === null. Der Handler leitet in diesem Fall auf die Listen-Seite
     * um (HTTP 302).
     *
     * Stub/Mock-Konvention: PlaceLocation = Domain-Objekt (hier real instanziiert mit leerem Pfad,
     * da das Original-Verhalten id() === null erfordert); MapDataService = Service → createMock
     * mit Verhaltens-Assertion auf findById(); LeafletJsService wird hier nicht aufgerufen
     * (Handler bricht beim null-id-Pfad vor dem Render ab) → createStub statt createMock,
     * um eine PHPUnit-Notice wegen "no expectations on mock" zu vermeiden.
     *
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

        $leaflet_js_service = self::createStub(LeafletJsService::class);

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
     * MapDataExportGeoJson GET ohne parent_id-Attribut: pinnt Default-Dateinamen
     * "Global.geojson" im Content-Disposition.
     *
     * Property aus SUT (MapDataExportGeoJson::handle, Zeile 47-62): bei null
     * parent_id wird ein neuer PlaceLocation('') als Wurzel erzeugt; dessen
     * id() === null bricht die hierarchy-Aufbau-Schleife sofort ab, so dass
     * $hierarchy leer bleibt. Anschließend wird der Dateiname via
     * preg_replace(..., $hierarchy[0] ?? 'Global') . '.geojson' gebildet —
     * also "Global.geojson" als Fallback.
     *
     * Komplementär zu test_map_data_export_geojson_returns_geojson: dort werden
     * nur Statuscode 200 und content-type application/vnd.geo+json geprüft —
     * hier wird der content-disposition-Header mit dem Default-Dateinamen
     * "Global.geojson" gepinnt.
     *
     * @group ported-l2-doubles
     */
    public function test_map_data_export_geojson_pins_default_filename(): void
    {
        // Arrange
        $handler = new MapDataExportGeoJson(
            Registry::container()->get(MapDataService::class),
        );
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert: Statuscode 200 (Voraussetzung für sinnvolle Header-Prüfung).
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Assert: content-disposition mit Default-Dateinamen "Global.geojson".
        // Pinnt den null-parent_id-Pfad: PlaceLocation('') liefert id() === null,
        // hierarchy bleibt leer, Fallback "Global" wird zum Dateinamen.
        self::assertSame(
            'attachment; filename="Global.geojson"',
            $response->getHeaderLine('content-disposition'),
        );
    }

    /**
     * MapDataExportGeoJson GET → 200 + content-type application/vnd.geo+json.
     *
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
     * MapDataSave POST mit leerer place_id und bereits existierendem Namen
     * (parent_id=null) → 302-Redirect + keine zweite DB-Zeile.
     *
     * Property aus SUT (MapDataSave::handle, Zeile 64-83): bei place_id === null wird
     * vor dem INSERT geprüft, ob bereits ein Eintrag mit demselben place-Namen unter
     * derselben parent_id existiert (Wurzelebene via whereNull('parent_id')). Wenn ja,
     * wird der INSERT übersprungen — das Verhalten ist idempotent in Bezug auf
     * (name, parent_id). Der Handler liefert in beiden Verzweigungen einen 302-Redirect
     * auf die übergebene URL zurück.
     *
     * Komplementär zu test_map_data_save_inserts_new_location (Neuanlage einer noch
     * nicht existierenden Location) und test_map_data_save_with_empty_coordinates_creates_location
     * (Neuanlage ohne Koords): hier wird der Pfad "Name existiert bereits" gepinnt,
     * der in keinem anderen Test abgedeckt ist. Der Test geht rot, falls der Handler
     * den Duplicate-Check entfernt und doppelte Zeilen erlaubt.
     *
     * @group ported-l2-doubles
     */
    public function test_map_data_save_does_not_insert_duplicate_on_existing_name(): void
    {
        // Arrange: Vorhandenen Wurzel-Eintrag direkt in die DB schreiben, damit der
        // exists()-Check in MapDataSave::handle den INSERT-Zweig überspringen muss.
        $place_name = 'OrtDoppelt-S52-' . uniqid();
        DB::table('place_location')->insert([
            'parent_id' => null,
            'place'     => $place_name,
            'latitude'  => 40.0,
            'longitude' => 7.0,
        ]);

        $handler = new MapDataSave();

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

        // Act
        $response = $handler->handle($request);

        // Assert: Statuscode 302 — der Handler beendet auch bei Duplikat-Treffer mit
        // dem regulären Redirect, nicht mit Fehler.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: genau eine Zeile mit (place, parent_id=null) existiert —
        // kein doppelter INSERT. Pinnt den Pfad "exists_query trifft → kein insert".
        $count = DB::table('place_location')
            ->where('place', '=', $place_name)
            ->whereNull('parent_id')
            ->count();

        self::assertSame(1, $count);

        // Postcondition: die Original-Koordinaten der bestehenden Zeile bleiben
        // unverändert — der Idempotenz-Zweig führt weder INSERT noch UPDATE aus.
        // Pinnt damit, dass der "Name existiert"-Pfad gerade NICHT als verdeckter
        // Update-Pfad reinterpretiert wird.
        $row = DB::table('place_location')
            ->where('place', '=', $place_name)
            ->whereNull('parent_id')
            ->first();

        self::assertNotNull($row);
        self::assertSame(40.0, (float) $row->latitude);
        self::assertSame(7.0, (float) $row->longitude);
    }

    /**
     * MapDataSave POST mit leeren Koordinaten → 302-Redirect + DB-Eintrag mit
     * NULL-Latitude/Longitude (Edge Case: Standort ohne Geokoordinaten).
     *
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
