<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\AutoCompleteCitation;
use Fisharebest\Webtrees\Http\RequestHandlers\AutoCompletePlace;
use Fisharebest\Webtrees\Http\RequestHandlers\AutoCompleteSurname;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\SearchService;

/**
 * Komponentenintegrationstest: AutoComplete-Handler mit MySQL.
 *
 * Testet AJAX-Endpoints für Ort-, Nachname- und Zitat-Autocomplete.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AutoCompletePlace
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AutoCompleteSurname
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AutoCompleteCitation
 * @see docs/tds_conditions_ref.md S07, S08
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AutoCompleteCitationTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AutoCompletePlaceTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AutoCompleteSurnameTest.php
 */
class AutoCompleteIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private SearchService $search_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->search_service = new SearchService($this->treeService);
    }

    protected function tearDown(): void
    {
        // Deterministische User-Namen aus substr(md5($this->name()), 0, 8); aufgelistet,
        // damit der Cleanup statisch und lesbar bleibt (Bestand-Konvention).
        $cleanup = [
            'ac-cite-a82898ba',       // test_autocomplete_citation_returns_json_for_valid_source_with_minimal_records
            'ac-cite-hdr-7ab3d393',   // test_autocomplete_citation_response_includes_cache_header
            'ac-cite-empty-276b17b2', // test_autocomplete_citation_empty_result_for_unmatched_query
        ];
        foreach ($cleanup as $uname) {
            $u = $this->userService->findByUserName($uname);
            if ($u !== null) {
                $this->userService->delete($u);
            }
        }

        parent::tearDown();
    }

    // --- AutoCompletePlace ---

    public function test_autocomplete_place_returns_json_with_results(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $module_service = new ModuleService();
        $handler = new AutoCompletePlace($module_service, $this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'England'],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertNotEmpty($json, 'Suche nach "England" muss Treffer liefern');
    }

    public function test_autocomplete_place_returns_empty_for_non_match(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $module_service = new ModuleService();
        $handler = new AutoCompletePlace($module_service, $this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'xyznonexistent'],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEmpty($json);
    }

    /**
     * AutoCompletePlace-Response muss einen cache-control-Header enthalten
     * (gesetzt von AbstractAutocompleteHandler).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AutoCompletePlaceTest.php
     * @group ported-l2-doubles
     */
    public function test_autocomplete_place_response_includes_cache_header(): void
    {
        // Arrange
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $module_service = new ModuleService();
        $handler        = new AutoCompletePlace($module_service, $this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'test'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        $this->assertNotEmpty($response->getHeaderLine('cache-control'));
    }

    // --- AutoCompleteSurname ---

    public function test_autocomplete_surname_returns_json_with_results(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $handler = new AutoCompleteSurname($this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'a'],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertNotEmpty($json, 'Suche nach "a" muss Nachnamen finden');
    }

    public function test_autocomplete_surname_returns_empty_for_non_match(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $handler = new AutoCompleteSurname($this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'Xyznonexistent'],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEmpty($json);
    }

    /**
     * AutoCompleteSurname-Response muss einen cache-control-Header enthalten
     * (gesetzt von AbstractAutocompleteHandler).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AutoCompleteSurnameTest.php
     * @group ported-l2-doubles
     */
    public function test_autocomplete_surname_response_includes_cache_header(): void
    {
        // Arrange
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $handler = new AutoCompleteSurname($this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'test'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        $this->assertNotEmpty($response->getHeaderLine('cache-control'));
    }

    // --- AutoCompleteCitation ---

    /**
     * BUG-PIN: AutoCompleteCitation gibt auf demo.ged eine JSON-Antwort zurück,
     * deren Body ein JSON-Objekt mit numerischen String-Keys (`{"0":..., "2":...}`)
     * statt eines JSON-Arrays (`[..., ...]`) ist.
     *
     * Ursache liegt im Upstream-Code: `search()` kombiniert Individuen und Familien
     * via `merge()`, filtert privacy-gefilterte Records via `accessFilter()` heraus
     * und ruft `uniqueStrict()` auf — beides bewahrt die ursprünglichen, nun
     * lückenhaften numerischen Keys. `json_encode()` auf einer `Collection` mit
     * nicht-fortlaufenden Integer-Keys serialisiert als Objekt statt Array.
     *
     * AJAX-Clients (TomSelect/jQuery-Autocomplete) erwarten ein Array. Der Defekt
     * wird hier als reproduzierbares Verhalten gepinnt: sobald Upstream auf
     * `values()` vor `json_encode()` umstellt (oder `uniqueStrict()`-Keys
     * normalisiert), beginnt der Response-Body mit `[` und der Test geht rot —
     * die Bug-Behebung wird sichtbar, nicht maskiert.
     *
     * Verwandt: `FamilyFactory::mapper()` deklariert `Closure(object):Family`,
     * kann aber `null` zurückliefern (Cache-Hit auf bereits gelöschtem Record).
     * Die accessFilter-Closure würde bei null mit TypeError abbrechen — auf
     * demo.ged in dieser Konstellation aber nicht beobachtbar (keine null-Trefer
     * in der Collection bis zum Filter).
     *
     * @see https://github.com/fisharebest/webtrees/issues/XXXX
     * @see AutoCompleteCitation::search() — uniqueStrict() bewahrt gappy keys
     */
    public function test_autocomplete_citation_returns_json_for_valid_source(): void
    {
        // Arrange — demo.ged enthält Familien-/Individuen-Records mit privacy-Filter
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $source_xref = DB::table('sources')
            ->where('s_file', '=', $this->tree->id())
            ->value('s_id');

        $this->assertNotNull($source_xref, 'demo.ged muss Quellen enthalten');

        $handler = new AutoCompleteCitation($this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'a', 'extra' => $source_xref],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — Statuscode und Content-Type stimmen bereits
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('content-type'));

        $body = (string) $response->getBody();

        // BUG-CANDIDATE: Body ist ein JSON-Objekt (beginnt mit `{`) statt eines
        // JSON-Arrays (beginnt mit `[`). Sobald Upstream den uniqueStrict-/Key-
        // Re-Index-Defekt behebt, beginnt der Body mit `[` und diese Assertion
        // wird rot — der Bugfix wird sichtbar.
        $this->assertNotSame('', $body, 'Body darf nicht leer sein — Treffer in demo.ged erwartet');
        $this->assertSame(
            '{',
            $body[0],
            'BUG-CANDIDATE: Body soll laut Schnittstellenvertrag ein JSON-Array (`[`) sein, '
            . 'liefert aber wegen gappy keys nach uniqueStrict() ein JSON-Objekt (`{`). '
            . 'Wenn diese Assertion grün bleibt, ist der Upstream-Defekt aktiv.'
        );

        // Begleit-Assertion: json_decode liefert eine assoziative Map mit numerischen
        // String-Keys — Beweis, dass die Serialisierung keinen List-Layout liefert.
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded);
        $this->assertNotSame(
            array_values($decoded),
            $decoded,
            'BUG-CANDIDATE: Decoded payload ist eine assoziative Map mit lückenhaften '
            . 'Integer-Keys (entspricht JSON-Objekt), nicht eine List mit fortlaufenden Keys.'
        );
    }

    /**
     * AutoCompleteCitation mit gültigem Source + PAGE-Treffer → STATUS_OK JSON.
     *
     * Importiert minimale SOUR/INDI-Records statt demo.ged, um den Upstream-Bug
     * (FamilyFactory::mapper() null für private Familienmitglieder) zu umgehen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AutoCompleteCitationTest.php
     * @group ported-l2-doubles
     */
    public function test_autocomplete_citation_returns_json_for_valid_source_with_minimal_records(): void
    {
        // Arrange — eindeutiger Baumname, Member-User (SOUR-Privacy = members only)
        $uniqueName = 'ac-cite-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'AutoComplete Citation Test');

        $user = $this->userService->create(
            'ac-cite-' . substr(md5($this->name()), 0, 8),
            'AC Citation User',
            'ac-cite-' . substr(md5($this->name()), 0, 8) . '@test.local',
            getenv('WEBTREES_TEST_USER_PASSWORD') ?: throw new \RuntimeException('WEBTREES_TEST_USER_PASSWORD nicht gesetzt'),
        );
        $this->tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, UserInterface::ROLE_MEMBER);
        Auth::login($user);

        $this->gedcomImportService->importRecord(
            "0 @S1@ SOUR\n1 TITL Test Source",
            $this->tree,
            false,
        );
        $this->gedcomImportService->importRecord(
            "0 @I1@ INDI\n1 NAME John /Doe/\n1 SOUR @S1@\n2 PAGE Page 42",
            $this->tree,
            false,
        );

        $handler = new AutoCompleteCitation($this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'Page', 'extra' => 'S1'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('content-type'));
    }

    /**
     * AutoCompleteCitation-Response muss einen cache-control-Header enthalten.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AutoCompleteCitationTest.php
     * @group ported-l2-doubles
     */
    public function test_autocomplete_citation_response_includes_cache_header(): void
    {
        // Arrange
        $uniqueName = 'ac-cite-hdr-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'AutoComplete Citation Header Test');

        $user = $this->userService->create(
            'ac-cite-hdr-' . substr(md5($this->name()), 0, 8),
            'AC Citation Header User',
            'ac-cite-hdr-' . substr(md5($this->name()), 0, 8) . '@test.local',
            getenv('WEBTREES_TEST_USER_PASSWORD') ?: throw new \RuntimeException('WEBTREES_TEST_USER_PASSWORD nicht gesetzt'),
        );
        $this->tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, UserInterface::ROLE_MEMBER);
        Auth::login($user);

        $this->gedcomImportService->importRecord(
            "0 @S1@ SOUR\n1 TITL Test Source",
            $this->tree,
            false,
        );

        $handler = new AutoCompleteCitation($this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'anything', 'extra' => 'S1'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        $this->assertNotEmpty($response->getHeaderLine('cache-control'));
    }

    /**
     * Wenn keine Records die Quelle zitieren → leeres JSON-Array '[]'.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AutoCompleteCitationTest.php
     * @group ported-l2-doubles
     */
    public function test_autocomplete_citation_empty_result_for_unmatched_query(): void
    {
        // Arrange
        $uniqueName = 'ac-cite-empty-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'AutoComplete Citation Empty Test');

        $user = $this->userService->create(
            'ac-cite-empty-' . substr(md5($this->name()), 0, 8),
            'AC Citation Empty User',
            'ac-cite-empty-' . substr(md5($this->name()), 0, 8) . '@test.local',
            getenv('WEBTREES_TEST_USER_PASSWORD') ?: throw new \RuntimeException('WEBTREES_TEST_USER_PASSWORD nicht gesetzt'),
        );
        $this->tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, UserInterface::ROLE_MEMBER);
        Auth::login($user);

        $this->gedcomImportService->importRecord(
            "0 @S1@ SOUR\n1 TITL Test Source",
            $this->tree,
            false,
        );

        $handler = new AutoCompleteCitation($this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'nonexistent-citation-text', 'extra' => 'S1'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $body = (string) $response->getBody();
        $this->assertSame('[]', $body);
    }
}
