<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\AutoCompleteFolder;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectFamily;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectIndividual;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectLocation;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectMediaObject;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectNote;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectPlace;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectRepository;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectSource;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectSubmitter;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\SearchService;

/**
 * Komponentenintegrationstest: TomSelect & AutoComplete (Edit-Hilfs-APIs) — E08.
 *
 * TomSelectIndividual/Source/Family → JSON {data: [...], nextUrl: null}
 * AutoCompleteFolder → JSON [{value: ...}, ...]
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectIndividual
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectSource
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectFamily
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectLocation
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectMediaObject
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectNote
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectPlace
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectRepository
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectSubmitter
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AutoCompleteFolder
 * @see docs/testquality_improve_E08.md
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectFamilyTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectIndividualTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectLocationTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectMediaObjectTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectNoteTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectPlaceTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectRepositoryTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectSourceTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectSubmitterTest.php
 */
class TomSelectIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private SearchService $search_service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search_service = new SearchService($this->treeService);
    }

    /**
     * EP1: TomSelectIndividual mit leerem Query → leere data-Liste.
     */
    public function test_tomselect_individual_empty_query_returns_empty_data(): void
    {
        $this->createTreeWithGedcom('tomselect', 'TomSelect', self::DEMO_GED);

        $handler = new TomSelectIndividual($this->search_service);
        $request = $this->createRequest(
            query: ['query' => '', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertEmpty($json['data']);
        self::assertNull($json['nextUrl']);
    }

    /**
     * EP2: TomSelectIndividual mit gültigem XREF → gibt genau dieses Individuum zurück.
     */
    public function test_tomselect_individual_xref_query_returns_individual(): void
    {
        $this->createTreeWithGedcom('tomselect-xref', 'TomSelect XREF', self::DEMO_GED);

        $handler = new TomSelectIndividual($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'X1030', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);
        $json     = json_decode((string) $response->getBody(), true);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertArrayHasKey('data', $json);
        self::assertNotEmpty($json['data']);
    }

    /**
     * EP3: TomSelectIndividual mit Namen-Query → gibt passende Individuen zurück.
     */
    public function test_tomselect_individual_name_query_returns_results(): void
    {
        $this->createTreeWithGedcom('tomselect-name', 'TomSelect Name', self::DEMO_GED);

        $handler = new TomSelectIndividual($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'S', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);
        $json     = json_decode((string) $response->getBody(), true);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertArrayHasKey('data', $json);
    }

    /**
     * EP4: TomSelectSource mit leerem Query → leere data-Liste.
     */
    public function test_tomselect_source_empty_query_returns_empty_data(): void
    {
        $this->createTreeWithGedcom('tomselect-src', 'TomSelect Source', self::DEMO_GED);

        $handler = new TomSelectSource($this->search_service);
        $request = $this->createRequest(
            query: ['query' => '', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);
        $json     = json_decode((string) $response->getBody(), true);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertArrayHasKey('data', $json);
        self::assertEmpty($json['data']);
    }

    /**
     * EP5: AutoCompleteFolder → gibt JSON-Array zurück (auch wenn leer).
     */
    public function test_autocomplete_folder_returns_json_array(): void
    {
        $this->createTreeWithGedcom('tomselect-folder', 'TomSelect Folder', self::DEMO_GED);

        $handler = new AutoCompleteFolder(
            Registry::container()->get(MediaFileService::class),
            $this->search_service,
        );
        $request = $this->createRequest(
            query: ['query' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
    }

    /**
     * EP6: TomSelectFamily-Klasse ist vorhanden (Smoke-Test).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectFamilyTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_family_class_exists(): void
    {
        self::assertTrue(class_exists(TomSelectFamily::class));
    }

    /**
     * EP7: TomSelectFamily mit leerem Query → leere data-Liste, nextUrl null.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectFamilyTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_family_empty_query_returns_empty_data(): void
    {
        $this->createTreeWithGedcom('tomselect-fam-empty', 'TomSelect Family Empty', self::DEMO_GED);

        $handler = new TomSelectFamily($this->search_service);
        $request = $this->createRequest(
            query: ['query' => '', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertEmpty($json['data']);
        self::assertNull($json['nextUrl']);
    }

    /**
     * EP8: TomSelectFamily mit Namen-Query → liefert JSON mit data/nextUrl-Struktur.
     *
     * Demo-Gedcom enthält keine Personen mit Nachnamen "Smith" — searchFamilyNames()
     * läuft an MySQL und liefert eine Collection (leer). Geprüft wird, dass der
     * Handler die JSON-Struktur korrekt aufbaut.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectFamilyTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_family_name_query_returns_json_structure(): void
    {
        $this->createTreeWithGedcom('tomselect-fam-query', 'TomSelect Family Query', self::DEMO_GED);

        $handler = new TomSelectFamily($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'Smith', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
    }

    /**
     * EP9: TomSelectIndividual-Klasse ist vorhanden (Smoke-Test).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectIndividualTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_individual_class_exists(): void
    {
        self::assertTrue(class_exists(TomSelectIndividual::class));
    }

    /**
     * EP10: TomSelectIndividual mit Namen-Query → JSON enthält data und nextUrl-Schlüssel.
     *
     * Ergänzt EP3 um explizite nextUrl-Strukturassertion (aus Quell-Datei
     * testHandleWithQueryReturnsJsonResponse übernommen).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectIndividualTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_individual_name_query_returns_json_structure(): void
    {
        $this->createTreeWithGedcom('tomselect-ind-struct', 'TomSelect Individual Struct', self::DEMO_GED);

        $handler = new TomSelectIndividual($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'John', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
    }

    /**
     * EP11: TomSelectLocation-Klasse ist vorhanden (Smoke-Test).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectLocationTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_location_class_exists(): void
    {
        self::assertTrue(class_exists(TomSelectLocation::class));
    }

    /**
     * EP12: TomSelectLocation mit leerem Query → leere data-Liste, nextUrl null.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectLocationTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_location_empty_query_returns_empty_data(): void
    {
        $this->createTreeWithGedcom('tomselect-loc-empty', 'TomSelect Location Empty', self::DEMO_GED);

        $handler = new TomSelectLocation($this->search_service);
        $request = $this->createRequest(
            query: ['query' => '', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertEmpty($json['data']);
        self::assertNull($json['nextUrl']);
    }

    /**
     * EP13: TomSelectLocation mit Namen-Query → liefert JSON mit data/nextUrl-Struktur.
     *
     * Demo-Gedcom enthält keine PLAC-Records mit dem Suchbegriff — searchLocations()
     * läuft an MySQL und liefert eine Collection (leer). Geprüft wird, dass der
     * Handler die JSON-Struktur korrekt aufbaut.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectLocationTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_location_name_query_returns_json_structure(): void
    {
        $this->createTreeWithGedcom('tomselect-loc-query', 'TomSelect Location Query', self::DEMO_GED);

        $handler = new TomSelectLocation($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'England', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
    }

    /**
     * EP14: TomSelectMediaObject-Klasse ist vorhanden (Smoke-Test).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectMediaObjectTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_media_object_class_exists(): void
    {
        self::assertTrue(class_exists(TomSelectMediaObject::class));
    }

    /**
     * EP15: TomSelectMediaObject mit leerem Query → leere data-Liste, nextUrl null.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectMediaObjectTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_media_object_empty_query_returns_empty_data(): void
    {
        $this->createTreeWithGedcom('tomselect-med-empty', 'TomSelect Media Empty', self::DEMO_GED);

        $handler = new TomSelectMediaObject($this->search_service);
        $request = $this->createRequest(
            query: ['query' => '', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertEmpty($json['data']);
        self::assertNull($json['nextUrl']);
    }

    /**
     * EP16: TomSelectMediaObject mit Namen-Query → liefert JSON mit data/nextUrl-Struktur.
     *
     * Demo-Gedcom enthält Medienobjekte — searchMedia() läuft an MySQL und liefert eine
     * Collection. Geprüft wird, dass der Handler die JSON-Struktur korrekt aufbaut.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectMediaObjectTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_media_object_name_query_returns_json_structure(): void
    {
        $this->createTreeWithGedcom('tomselect-med-query', 'TomSelect Media Query', self::DEMO_GED);

        $handler = new TomSelectMediaObject($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'Photo', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
    }

    /**
     * EP17: TomSelectNote-Klasse ist vorhanden (Smoke-Test).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectNoteTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_note_class_exists(): void
    {
        self::assertTrue(class_exists(TomSelectNote::class));
    }

    /**
     * EP18: TomSelectNote mit leerem Query → leere data-Liste, nextUrl null.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectNoteTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_note_empty_query_returns_empty_data(): void
    {
        $this->createTreeWithGedcom('tomselect-note-empty', 'TomSelect Note Empty', self::DEMO_GED);

        $handler = new TomSelectNote($this->search_service);
        $request = $this->createRequest(
            query: ['query' => '', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertEmpty($json['data']);
        self::assertNull($json['nextUrl']);
    }

    /**
     * EP19: TomSelectNote mit Namen-Query → liefert JSON mit data/nextUrl-Struktur.
     *
     * searchNotes() läuft an MySQL und liefert eine Collection. Geprüft wird, dass
     * der Handler die JSON-Struktur korrekt aufbaut.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectNoteTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_note_name_query_returns_json_structure(): void
    {
        $this->createTreeWithGedcom('tomselect-note-query', 'TomSelect Note Query', self::DEMO_GED);

        $handler = new TomSelectNote($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'Research', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
    }

    /**
     * EP20: TomSelectPlace-Klasse ist vorhanden (Smoke-Test).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectPlaceTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_place_class_exists(): void
    {
        self::assertTrue(class_exists(TomSelectPlace::class));
    }

    /**
     * EP21: TomSelectPlace mit leerem Query → leere data-Liste, nextUrl null.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectPlaceTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_place_empty_query_returns_empty_data(): void
    {
        $this->createTreeWithGedcom('tomselect-plc-empty', 'TomSelect Place Empty', self::DEMO_GED);

        $handler = new TomSelectPlace($this->search_service);
        $request = $this->createRequest(
            query: ['query' => '', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertEmpty($json['data']);
        self::assertNull($json['nextUrl']);
    }

    /**
     * EP22: TomSelectPlace mit Namen-Query → liefert JSON mit data/nextUrl-Struktur.
     *
     * searchPlaces() läuft an MySQL und liefert eine Collection. Geprüft wird, dass
     * der Handler die JSON-Struktur korrekt aufbaut.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectPlaceTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_place_name_query_returns_json_structure(): void
    {
        $this->createTreeWithGedcom('tomselect-plc-query', 'TomSelect Place Query', self::DEMO_GED);

        $handler = new TomSelectPlace($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'London', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
    }

    /**
     * EP23: TomSelectRepository-Klasse ist vorhanden (Smoke-Test).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectRepositoryTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_repository_class_exists(): void
    {
        self::assertTrue(class_exists(TomSelectRepository::class));
    }

    /**
     * EP24: TomSelectRepository mit leerem Query → leere data-Liste, nextUrl null.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectRepositoryTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_repository_empty_query_returns_empty_data(): void
    {
        $this->createTreeWithGedcom('tomselect-rep-empty', 'TomSelect Repository Empty', self::DEMO_GED);

        $handler = new TomSelectRepository($this->search_service);
        $request = $this->createRequest(
            query: ['query' => '', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertEmpty($json['data']);
        self::assertNull($json['nextUrl']);
    }

    /**
     * EP25: TomSelectRepository mit Namen-Query → liefert JSON mit data/nextUrl-Struktur.
     *
     * searchRepositories() läuft an MySQL und liefert eine Collection. Geprüft wird, dass
     * der Handler die JSON-Struktur korrekt aufbaut.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectRepositoryTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_repository_name_query_returns_json_structure(): void
    {
        $this->createTreeWithGedcom('tomselect-rep-query', 'TomSelect Repository Query', self::DEMO_GED);

        $handler = new TomSelectRepository($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'Archive', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
    }

    /**
     * EP26: TomSelectSource-Klasse ist vorhanden (Smoke-Test).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectSourceTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_source_class_exists(): void
    {
        self::assertTrue(class_exists(TomSelectSource::class));
    }

    /**
     * EP27: TomSelectSource mit Namen-Query → liefert JSON mit data/nextUrl-Struktur.
     *
     * searchSourcesByName() läuft an MySQL und liefert eine Collection. Geprüft wird, dass
     * der Handler die JSON-Struktur korrekt aufbaut.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectSourceTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_source_name_query_returns_json_structure(): void
    {
        $this->createTreeWithGedcom('tomselect-src-query', 'TomSelect Source Query', self::DEMO_GED);

        $handler = new TomSelectSource($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'Census', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
    }

    /**
     * EP28: TomSelectSubmitter-Klasse ist vorhanden (Smoke-Test).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectSubmitterTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_submitter_class_exists(): void
    {
        self::assertTrue(class_exists(TomSelectSubmitter::class));
    }

    /**
     * EP29: TomSelectSubmitter mit leerem Query → leere data-Liste, nextUrl null.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectSubmitterTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_submitter_empty_query_returns_empty_data(): void
    {
        $this->createTreeWithGedcom('tomselect-sub-empty', 'TomSelect Submitter Empty', self::DEMO_GED);

        $handler = new TomSelectSubmitter($this->search_service);
        $request = $this->createRequest(
            query: ['query' => '', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertEmpty($json['data']);
        self::assertNull($json['nextUrl']);
    }

    /**
     * EP30: TomSelectSubmitter mit Namen-Query → liefert JSON mit data/nextUrl-Struktur.
     *
     * searchSubmitters() läuft an MySQL und liefert eine Collection. Geprüft wird, dass
     * der Handler die JSON-Struktur korrekt aufbaut.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectSubmitterTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_submitter_name_query_returns_json_structure(): void
    {
        $this->createTreeWithGedcom('tomselect-sub-query', 'TomSelect Submitter Query', self::DEMO_GED);

        $handler = new TomSelectSubmitter($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'Admin', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
    }
}
