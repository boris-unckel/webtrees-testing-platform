<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\AutoCompleteFolder;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectIndividual;
use Fisharebest\Webtrees\Http\RequestHandlers\TomSelectSource;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\SearchService;

/**
 * Komponentenintegrationstest: TomSelect & AutoComplete (Edit-Hilfs-APIs) — E08.
 *
 * TomSelectIndividual/Source → JSON {data: [...], nextUrl: null}
 * AutoCompleteFolder → JSON [{value: ...}, ...]
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectIndividual
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TomSelectSource
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AutoCompleteFolder
 * @see docs/testquality_improve_E08.md
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
}
