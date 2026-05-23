<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedPage;
use Fisharebest\Webtrees\Services\SearchService;

/**
 * Komponentenintegrationstest: SearchAdvancedPage HTTP-Handler.
 *
 * SearchAdvancedPage rendert das Formular fuer die erweiterte Suche und fuehrt
 * (falls Felder belegt sind) eine SearchService-getragene Suche aus. Hier wird
 * das Render- und Such-Verhalten gegen eine echte MySQL-DB-getragene
 * Tree-Instanz verifiziert.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchAdvancedPageTest.php
 * @group ported-l2-doubles
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedPage
 */
class SearchAdvancedPageIntegrationTest extends MysqlTestCase
{
    /**
     * Default-Seite ohne ausgefuellte Suchfelder rendert mit HTTP 200 OK
     * und liefert nicht-leeren Body.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchAdvancedPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_default_page_returns_ok(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-adv-page-' . substr(md5($this->name()), 0, 8), 'Test');

        $handler = new SearchAdvancedPage(new SearchService($this->treeService));
        $request = $this->createRequest(attributes: ['tree' => $this->tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertNotEmpty((string) $response->getBody());
    }

    /**
     * Sind Suchfelder uebergeben, aber leer, wird keine Suche ausgefuehrt; die
     * Seite rendert dennoch mit HTTP 200 OK.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchAdvancedPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_empty_fields_returns_ok(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-adv-empty-' . substr(md5($this->name()), 0, 8), 'Test 2');

        $handler = new SearchAdvancedPage(new SearchService($this->treeService));
        $request = $this->createRequest(
            query: [
                'fields' => ['INDI:NAME:GIVN' => '', 'INDI:NAME:SURN' => ''],
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Sind Suchfelder mit Werten belegt, wird eine Suche ausgefuehrt und die
     * Seite rendert mit HTTP 200 OK und nicht-leerem Body.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchAdvancedPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_search_fields_returns_ok(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-adv-fields-' . substr(md5($this->name()), 0, 8), 'Test 3');

        $handler = new SearchAdvancedPage(new SearchService($this->treeService));
        $request = $this->createRequest(
            query: [
                'fields'    => ['INDI:NAME:GIVN' => 'John', 'INDI:NAME:SURN' => 'Doe'],
                'modifiers' => ['INDI:NAME:GIVN' => 'CONTAINS', 'INDI:NAME:SURN' => 'CONTAINS'],
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertNotEmpty((string) $response->getBody());
    }
}
