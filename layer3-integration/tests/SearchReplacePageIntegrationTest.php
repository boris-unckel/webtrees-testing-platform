<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchReplacePage;

/**
 * Komponentenintegrationstest: SearchReplacePage HTTP-Handler.
 *
 * SearchReplacePage rendert das Formular fuer die Such- und Ersetzen-Operation
 * und nimmt optional vorbelegte Query-Parameter (search, replace, context)
 * entgegen. Hier wird das Render-Verhalten gegen eine echte MySQL-DB-getragene
 * Tree-Instanz verifiziert; der Handler hat keine externen Abhaengigkeiten.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchReplacePageTest.php
 * @group ported-l2-doubles
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchReplacePage
 */
class SearchReplacePageIntegrationTest extends MysqlTestCase
{
    /**
     * Vorbelegte Query-Parameter (search, replace, context) muessen unveraendert
     * in den gerenderten Response-Body durchgereicht werden — das ist der
     * eigentliche Kontrakt des Page-Handlers, der das Formular fuer den
     * SearchReplaceAction-Endpoint vorbereitet.
     *
     * Ersetzt den frueheren `class_exists`-Tautologietest, der nur die
     * Autoloader-Konfiguration prueft und keine Verhaltensinformation traegt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchReplacePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_renders_query_parameters_into_body(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create(
            'search-replace-page-' . substr(md5($this->name()), 0, 8),
            'Test 3'
        );

        $handler = new SearchReplacePage();
        $request = $this->createRequest(
            query: [
                'search'  => 'Mueller-Token-' . substr(md5($this->name()), 0, 8),
                'replace' => 'Schmidt-Token-' . substr(md5($this->name()), 0, 8),
                'context' => 'name',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);
        $body     = (string) $response->getBody();

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringContainsString('Mueller-Token-' . substr(md5($this->name()), 0, 8), $body);
        self::assertStringContainsString('Schmidt-Token-' . substr(md5($this->name()), 0, 8), $body);
    }

    /**
     * Default-Seite ohne Query-Parameter rendert mit STATUS_OK und liefert
     * einen nicht-leeren Body.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchReplacePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_default_page_returns_ok_with_body(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create(
            'search-replace-page-' . substr(md5($this->name()), 0, 8),
            'Test'
        );

        $handler = new SearchReplacePage();
        $request = $this->createRequest(attributes: ['tree' => $this->tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertNotEmpty((string) $response->getBody());
    }

    /**
     * Mit vorbelegten Query-Parametern (search, replace, context) rendert die
     * Seite ebenfalls mit STATUS_OK.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchReplacePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_query_params_returns_ok(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create(
            'search-replace-page-' . substr(md5($this->name()), 0, 8),
            'Test 2'
        );

        $handler = new SearchReplacePage();
        $request = $this->createRequest(
            query: [
                'search'  => 'Doe',
                'replace' => 'Smith',
                'context' => 'all',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
