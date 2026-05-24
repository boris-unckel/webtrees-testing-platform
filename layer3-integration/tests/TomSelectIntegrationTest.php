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
     * EP6: TomSelectFamily mit gueltigem XREF → liefert genau diese Familie zurueck.
     *
     * Loest den Smoke-Test (class_exists) ab durch Pruefung des XREF-Lookup-Zweigs
     * in TomSelectFamily::search(): Registry::familyFactory()->make($query, $tree)
     * findet die Familie direkt und liefert eine Collection mit genau einem Treffer.
     *
     * Verifiziert:
     *   - Statuscode 200
     *   - JSON-Struktur {data: [...], nextUrl: null}
     *   - data ist nicht leer (XREF-Lookup hat getroffen)
     *   - jeder Treffer hat die Felder text/value
     *   - value enthaelt den XREF der gefundenen Familie
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectFamilyTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_family_xref_query_returns_family(): void
    {
        $this->tree = $this->createTreeWithGedcom('tomselect-fam-xref', 'TomSelect Family XREF', self::DEMO_GED);

        $handler = new TomSelectFamily($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'f1', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
        self::assertNull($json['nextUrl']);
        self::assertNotEmpty($json['data']);
        self::assertCount(1, $json['data']);

        $hit = $json['data'][0];
        self::assertIsArray($hit);
        self::assertArrayHasKey('text', $hit);
        self::assertArrayHasKey('value', $hit);
        self::assertSame('f1', $hit['value']);
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
     * EP9: TomSelectIndividual mit gueltigem XREF → liefert genau dieses Individuum zurueck.
     *
     * Loest den Smoke-Test (class_exists) ab durch Pruefung des XREF-Lookup-Zweigs
     * in TomSelectIndividual::search(): Registry::individualFactory()->make($query, $tree)
     * findet das Individuum direkt und liefert eine Collection mit genau einem Treffer.
     *
     * Verifiziert:
     *   - Statuscode 200
     *   - JSON-Struktur {data: [...], nextUrl: null}
     *   - data ist nicht leer (XREF-Lookup hat getroffen)
     *   - genau ein Treffer
     *   - jeder Treffer hat die Felder text/value
     *   - value enthaelt den XREF des gefundenen Individuums
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectIndividualTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_individual_xref_query_returns_single_individual(): void
    {
        $this->tree = $this->createTreeWithGedcom('tomselect-ind-xref-single', 'TomSelect Individual XREF Single', self::DEMO_GED);

        $handler = new TomSelectIndividual($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'X1030', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
        self::assertNull($json['nextUrl']);
        self::assertNotEmpty($json['data']);
        self::assertCount(1, $json['data']);

        $hit = $json['data'][0];
        self::assertIsArray($hit);
        self::assertArrayHasKey('text', $hit);
        self::assertArrayHasKey('value', $hit);
        self::assertSame('X1030', $hit['value']);
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
     * EP11: TomSelectLocation mit XREF-aehnlichem Query → leere data-Liste, Strukturassertion.
     *
     * Loest den Smoke-Test (class_exists) ab durch Pruefung des XREF-Lookup-Zweigs
     * in TomSelectLocation::search(): Registry::locationFactory()->make($query, $tree)
     * liefert null, weil demo.ged keinen _LOC-Record mit dieser XREF enthaelt; der
     * Fallback-Pfad ueber SearchService::searchLocations() liefert ebenfalls eine leere
     * Collection. Geprueft wird die vollstaendige JSON-Struktur des Handlers.
     *
     * Verifiziert:
     *   - Statuscode 200
     *   - JSON-Struktur {data: [...], nextUrl: null}
     *   - data ist leer (kein _LOC-Record in demo.ged matcht den XREF)
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectLocationTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_location_xref_query_returns_empty_data(): void
    {
        $this->tree = $this->createTreeWithGedcom('tomselect-loc-xref', 'TomSelect Location XREF', self::DEMO_GED);

        $handler = new TomSelectLocation($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'L1', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
        self::assertNull($json['nextUrl']);
        self::assertEmpty($json['data']);
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
     * EP14: TomSelectMediaObject mit gueltigem XREF → liefert genau dieses Medienobjekt zurueck.
     *
     * Loest den Smoke-Test (class_exists) ab durch Pruefung des XREF-Lookup-Zweigs
     * in TomSelectMediaObject::search(): Registry::mediaFactory()->make($query, $tree)
     * findet das Medienobjekt direkt und liefert eine Collection mit genau einem Treffer.
     *
     * Verifiziert:
     *   - Statuscode 200
     *   - JSON-Struktur {data: [...], nextUrl: null}
     *   - data ist nicht leer (XREF-Lookup hat getroffen)
     *   - genau ein Treffer
     *   - jeder Treffer hat die Felder text/value
     *   - value enthaelt den XREF des gefundenen Medienobjekts
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectMediaObjectTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_media_object_xref_query_returns_media_object(): void
    {
        $this->tree = $this->createTreeWithGedcom('tomselect-med-xref', 'TomSelect Media XREF', self::DEMO_GED);

        $handler = new TomSelectMediaObject($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'X247', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
        self::assertNull($json['nextUrl']);
        self::assertNotEmpty($json['data']);
        self::assertCount(1, $json['data']);

        $hit = $json['data'][0];
        self::assertIsArray($hit);
        self::assertArrayHasKey('text', $hit);
        self::assertArrayHasKey('value', $hit);
        self::assertSame('X247', $hit['value']);
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
     * EP17: TomSelectNote mit XREF-aehnlichem Query → leere data-Liste, Strukturassertion.
     *
     * Loest den Smoke-Test (class_exists) ab durch Pruefung des XREF-Lookup-Zweigs
     * in TomSelectNote::search(): Registry::noteFactory()->make($query, $tree) liefert
     * null, weil demo.ged keinen Level-0-NOTE-Record mit dieser XREF enthaelt; der
     * Fallback-Pfad ueber SearchService::searchNotes() liefert ebenfalls eine leere
     * Collection (demo.ged enthaelt nur Inline-Notes, keine Shared/Level-0-Notes in
     * der `other`-Tabelle vom Typ NOTE). Geprueft wird die vollstaendige JSON-Struktur
     * des Handlers.
     *
     * Verifiziert:
     *   - Statuscode 200
     *   - JSON-Struktur {data: [...], nextUrl: null}
     *   - data ist leer (kein Level-0-NOTE-Record in demo.ged matcht den XREF)
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectNoteTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_note_xref_query_returns_empty_data(): void
    {
        $this->tree = $this->createTreeWithGedcom('tomselect-note-xref', 'TomSelect Note XREF', self::DEMO_GED);

        $handler = new TomSelectNote($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'N1', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
        self::assertNull($json['nextUrl']);
        self::assertEmpty($json['data']);
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
     * EP20: TomSelectPlace mit Orts-Query → liefert konkreten Place-Treffer mit text/value.
     *
     * Loest den Smoke-Test (class_exists) ab durch Pruefung des einzigen Such-Zweigs in
     * TomSelectPlace::search(): SearchService::searchPlaces() laeuft an MySQL und liefert
     * eine Collection von Place-Objekten. Im Gegensatz zu TomSelectFamily/Individual/Media
     * besitzt TomSelectPlace keinen separaten XREF-Lookup-Zweig — die mappende Closure
     * setzt text auf Place::gedcomName() und value auf die numerische Place::id().
     *
     * Verifiziert:
     *   - Statuscode 200
     *   - JSON-Struktur {data: [...], nextUrl: null}
     *   - data ist nicht leer (searchPlaces hat getroffen)
     *   - jeder Treffer hat die Felder text/value
     *   - text enthaelt den Such-Term (Place::gedcomName)
     *   - value ist eine numerische String-ID (Place::id)
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectPlaceTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_place_query_returns_place_hits(): void
    {
        $this->tree = $this->createTreeWithGedcom('tomselect-plc-hit', 'TomSelect Place Hit', self::DEMO_GED);

        $handler = new TomSelectPlace($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'Althorp', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
        self::assertNull($json['nextUrl']);
        self::assertNotEmpty($json['data']);

        $hit = $json['data'][0];
        self::assertIsArray($hit);
        self::assertArrayHasKey('text', $hit);
        self::assertArrayHasKey('value', $hit);
        self::assertStringContainsString('Althorp', $hit['text']);
        self::assertMatchesRegularExpression('/^\d+$/', $hit['value']);
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
     * EP23: TomSelectRepository mit XREF-Query → gibt genau dieses Repository zurueck.
     *
     * Loest den Smoke-Test (class_exists) ab durch Pruefung des XREF-Lookup-Zweigs
     * in TomSelectRepository::search(): Registry::repositoryFactory()->make($query, $tree)
     * findet das Repository direkt und liefert eine Collection mit genau einem Treffer.
     *
     * Verifiziert:
     *   - Statuscode 200
     *   - JSON-Struktur {data: [...], nextUrl: null}
     *   - data ist nicht leer (XREF-Lookup hat getroffen)
     *   - genau ein Treffer
     *   - jeder Treffer hat die Felder text/value
     *   - value enthaelt den XREF des gefundenen Repositories
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectRepositoryTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_repository_xref_query_returns_repository(): void
    {
        $this->tree = $this->createTreeWithGedcom('tomselect-rep-xref', 'TomSelect Repository XREF', self::DEMO_GED);

        $handler = new TomSelectRepository($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'X1165', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
        self::assertNull($json['nextUrl']);
        self::assertNotEmpty($json['data']);
        self::assertCount(1, $json['data']);

        $hit = $json['data'][0];
        self::assertIsArray($hit);
        self::assertArrayHasKey('text', $hit);
        self::assertArrayHasKey('value', $hit);
        self::assertSame('X1165', $hit['value']);
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
     * EP26: TomSelectSource mit XREF-Query → gibt genau diese Source zurueck.
     *
     * Loest den Smoke-Test (class_exists) ab durch Pruefung des XREF-Lookup-Zweigs
     * in TomSelectSource::search(): Registry::sourceFactory()->make($query, $tree)
     * findet die Source direkt und liefert eine Collection mit genau einem Treffer.
     *
     * Verifiziert:
     *   - Statuscode 200
     *   - JSON-Struktur {data: [...], nextUrl: null}
     *   - data ist nicht leer (XREF-Lookup hat getroffen)
     *   - genau ein Treffer
     *   - jeder Treffer hat die Felder text/value
     *   - value enthaelt den XREF der gefundenen Source
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectSourceTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_source_xref_query_returns_source(): void
    {
        $this->tree = $this->createTreeWithGedcom('tomselect-src-xref', 'TomSelect Source XREF', self::DEMO_GED);

        $handler = new TomSelectSource($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'X1102', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
        self::assertNull($json['nextUrl']);
        self::assertNotEmpty($json['data']);
        self::assertCount(1, $json['data']);

        $hit = $json['data'][0];
        self::assertIsArray($hit);
        self::assertArrayHasKey('text', $hit);
        self::assertArrayHasKey('value', $hit);
        self::assertSame('X1102', $hit['value']);
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
     * EP28: TomSelectSubmitter mit XREF-Query → gibt genau diesen Submitter zurueck.
     *
     * Loest den Smoke-Test (class_exists) ab durch Pruefung des XREF-Lookup-Zweigs
     * in TomSelectSubmitter::search(): Registry::submitterFactory()->make($query, $tree)
     * findet den Submitter direkt und liefert eine Collection mit genau einem Treffer.
     *
     * Verifiziert:
     *   - Statuscode 200
     *   - JSON-Struktur {data: [...], nextUrl: null}
     *   - data ist nicht leer (XREF-Lookup hat getroffen)
     *   - genau ein Treffer
     *   - jeder Treffer hat die Felder text/value
     *   - value enthaelt den XREF des gefundenen Submitters
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TomSelectSubmitterTest.php
     * @group ported-l2-doubles
     */
    public function test_tomselect_submitter_xref_query_returns_submitter(): void
    {
        $this->tree = $this->createTreeWithGedcom('tomselect-sub-xref', 'TomSelect Submitter XREF', self::DEMO_GED);

        $handler = new TomSelectSubmitter($this->search_service);
        $request = $this->createRequest(
            query: ['query' => 'X1166', 'at' => ''],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('nextUrl', $json);
        self::assertNull($json['nextUrl']);
        self::assertNotEmpty($json['data']);
        self::assertCount(1, $json['data']);

        $hit = $json['data'][0];
        self::assertIsArray($hit);
        self::assertArrayHasKey('text', $hit);
        self::assertArrayHasKey('value', $hit);
        self::assertSame('X1166', $hit['value']);
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
