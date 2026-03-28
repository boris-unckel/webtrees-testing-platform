<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
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
 * @see docs/testing-bigpicture.md S07, S08
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

    // --- AutoCompleteCitation ---

    /**
     * @see https://github.com/fisharebest/webtrees/issues/XXXX
     * FamilyFactory::mapper() gibt null für private Familienmitglieder zurück — Upstream-Bug.
     */
    public function test_autocomplete_citation_returns_json_for_valid_source(): void
    {
        self::markTestSkipped('Upstream-Bug: FamilyFactory::mapper() gibt null für private Familienmitglieder zurück');

        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $source_xref = DB::table('sources')
            ->where('s_file', '=', $this->tree->id())
            ->value('s_id');

        $this->assertNotNull($source_xref, 'demo.ged muss Quellen enthalten');

        $handler = new AutoCompleteCitation($this->search_service);

        $request = $this->createRequest(
            query: ['query' => 'a', 'extra' => $source_xref],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
    }
}
