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
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage
 */
class SearchRequestHandlerIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

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
}
