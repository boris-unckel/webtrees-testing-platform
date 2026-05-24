<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage;
use Fisharebest\Webtrees\Services\SearchService;

/**
 * Komponentenintegrationstest: SearchGeneralPage HTTP-Handler.
 *
 * Testet handle() — CRAP 1.722 (cx=41).
 * SearchGeneralPage ruft SearchService → DB::table() mehrfach auf.
 *
 * @see docs/tds_conditions_ref.md S42
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage
 */
class SearchRequestHandlerIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED    = '/fixtures/demo.ged';
    private const REDIRECT_GED = '/fixtures/search-redirect-test.ged';

    private SearchGeneralPage $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new SearchGeneralPage(
            new SearchService($this->treeService),
            $this->treeService,
        );
    }

    /**
     * SearchGeneralPage gibt 200 OK für leere Suchanfrage (nur Personen).
     */
    public function test_search_general_page_empty_query_individuals_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            query: ['query' => '', 'search_individuals' => '1'],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * SearchGeneralPage gibt 200 OK für Personensuche mit Treffer.
     */
    public function test_search_general_page_individuals_with_query_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            query: [
                'query'              => 'Windsor',
                'search_individuals' => '1',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * SearchGeneralPage gibt 200 OK für Familien + Quellen + Notizen suche.
     */
    public function test_search_general_page_families_sources_notes_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            query: [
                'query'              => 'Royal',
                'search_families'    => '1',
                'search_sources'     => '1',
                'search_notes'       => '1',
                'search_locations'   => '1',
                'search_repositories'=> '1',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Genau 1 Individual-Treffer leitet direkt auf das Individual weiter (EP2).
     * Fixture: 1 Individual mit eindeutigem Namen Zinthrop2026.
     */
    public function test_search_single_individual_result_redirects(): void
    {
        $this->createTreeWithGedcom('redirect', 'Redirect Test', self::REDIRECT_GED);

        $request = $this->createRequest(
            query: [
                'query'              => 'Zinthrop2026',
                'search_individuals' => '1',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    /**
     * Genau 1 Family-Treffer leitet direkt auf die Familie weiter (EP4).
     * Fixture: 1 Familie mit Mitglied Zooper2026 — gefunden via searchFamilyNames.
     */
    public function test_search_single_family_result_redirects(): void
    {
        $this->createTreeWithGedcom('redirect-fam', 'Redirect Family Test', self::REDIRECT_GED);

        $request = $this->createRequest(
            query: [
                'query'           => 'Zooper2026',
                'search_families' => '1',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    /**
     * Ohne Such-Typ-Flags werden individuals + families als Fallback gesetzt (EP8).
     * Kein Redirect, da Windsor viele Treffer liefert.
     */
    public function test_search_no_type_flags_defaults_to_individuals_and_families(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $request = $this->createRequest(
            query: ['query' => 'Windsor'],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Default page (kein Query-Parameter): Handler liefert STATUS_OK und einen Body.
     *
     * @group ported-l2-doubles
     */
    public function test_search_general_page_default_no_query_returns_ok_with_body(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertNotEmpty((string) $response->getBody());
    }

    /**
     * Personensuche ohne Treffer liefert STATUS_OK (kein Redirect bei 0 Treffern).
     *
     * @group ported-l2-doubles
     */
    public function test_search_individuals_with_no_results_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            query: [
                'query'              => 'nonexistent-person-xyz',
                'search_individuals' => '1',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
