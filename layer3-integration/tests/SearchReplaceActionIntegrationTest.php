<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchReplaceAction;
use Fisharebest\Webtrees\Services\SearchService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: SearchReplaceAction HTTP-Handler.
 *
 * Der Handler nimmt einen Such- und Ersetzungstext entgegen und delegiert je nach
 * Kontext (all|name|place) an unterschiedliche Methoden des SearchService. Anders
 * als bei reinen Service-Tests wird hier der Tree aus einer echten MySQL-Instanz
 * erzeugt; der SearchService bleibt gemockt, um die delegierten Aufrufe pro
 * Kontextzweig praezise zu verifizieren.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchReplaceActionTest.php
 * @group ported-l2-doubles
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchReplaceAction
 */
class SearchReplaceActionIntegrationTest extends MysqlTestCase
{
    /**
     * Die Handler-Klasse muss unter ihrem voll qualifizierten Namen ladbar sein.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchReplaceActionTest.php
     * @group ported-l2-doubles
     */
    public function test_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(SearchReplaceAction::class));
    }

    /**
     * Im Kontext "all" muss der Handler ueber alle fuenf Record-Typen suchen
     * (Individuals, Families, Repositories, Sources, Notes) und am Ende mit
     * einem HTTP-302-Redirect auf die SearchReplacePage zurueckkehren.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchReplaceActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_context_all_searches_all_record_types(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-replace-all-' . substr(md5($this->name()), 0, 8), 'Test');

        $search_service = $this->createMock(SearchService::class);
        $search_service->expects(self::once())
            ->method('searchIndividuals')
            ->with([$this->tree], ['old-text'])
            ->willReturn(new Collection());
        $search_service->expects(self::once())
            ->method('searchFamilies')
            ->with([$this->tree], ['old-text'])
            ->willReturn(new Collection());
        $search_service->expects(self::once())
            ->method('searchRepositories')
            ->with([$this->tree], ['old-text'])
            ->willReturn(new Collection());
        $search_service->expects(self::once())
            ->method('searchSources')
            ->with([$this->tree], ['old-text'])
            ->willReturn(new Collection());
        $search_service->expects(self::once())
            ->method('searchNotes')
            ->with([$this->tree], ['old-text'])
            ->willReturn(new Collection());

        $handler = new SearchReplaceAction($search_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'search'  => 'old-text',
                'replace' => 'new-text',
                'context' => 'all',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Im Kontext "name" muss der Handler ausschliesslich die Individuen-Suche
     * aufrufen; Familien-, Repositorien-, Quellen- und Notiz-Suchen duerfen
     * dabei nicht angefasst werden.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchReplaceActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_context_name_searches_individuals_only(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-replace-name-' . substr(md5($this->name()), 0, 8), 'Test 2');

        $search_service = $this->createMock(SearchService::class);
        $search_service->expects(self::once())
            ->method('searchIndividuals')
            ->with([$this->tree], ['old-text'])
            ->willReturn(new Collection());
        $search_service->expects(self::never())
            ->method('searchFamilies');

        $handler = new SearchReplaceAction($search_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'search'  => 'old-text',
                'replace' => 'new-text',
                'context' => 'name',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Im Kontext "place" sucht der Handler ueber Individuen und Familien;
     * Repositorien-, Quellen- und Notiz-Suchen muessen ausgelassen werden, da
     * dort keine Ortsangaben anfallen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchReplaceActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_context_place_searches_individuals_and_families_only(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-replace-place-' . substr(md5($this->name()), 0, 8), 'Test 3');

        $search_service = $this->createMock(SearchService::class);
        $search_service->expects(self::once())
            ->method('searchIndividuals')
            ->with([$this->tree], ['old-text'])
            ->willReturn(new Collection());
        $search_service->expects(self::once())
            ->method('searchFamilies')
            ->with([$this->tree], ['old-text'])
            ->willReturn(new Collection());
        $search_service->expects(self::never())
            ->method('searchRepositories');
        $search_service->expects(self::never())
            ->method('searchSources');
        $search_service->expects(self::never())
            ->method('searchNotes');

        $handler = new SearchReplaceAction($search_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'search'  => 'old-text',
                'replace' => 'new-text',
                'context' => 'place',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
