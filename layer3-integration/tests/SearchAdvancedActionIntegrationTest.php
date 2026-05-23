<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedAction;

/**
 * Komponentenintegrationstest: SearchAdvancedAction HTTP-Handler.
 *
 * Der Handler liest fields/modifiers/other_field aus dem POST-Body, ergaenzt ein
 * leeres Feld fuer den optionalen other_field-Eintrag und leitet als 302-Redirect
 * auf SearchAdvancedPage weiter. Hier wird das Verhalten gegen eine echte
 * MySQL-DB-getragene Tree-Instanz verifiziert.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchAdvancedActionTest.php
 * @group ported-l2-doubles
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedAction
 */
class SearchAdvancedActionIntegrationTest extends MysqlTestCase
{
    /**
     * SearchAdvancedAction liefert HTTP 302 fuer einen einfachen Submit mit
     * fields/modifiers — der Handler leitet stets auf SearchAdvancedPage weiter.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchAdvancedActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirects(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-adv-' . substr(md5($this->name()), 0, 8), 'Test');

        $handler = new SearchAdvancedAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'fields'      => ['NAME' => 'John'],
                'modifiers'   => ['NAME' => 'exact'],
                'other_field' => '',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Ist other_field gesetzt, fuegt der Handler ein leeres Feld unter diesem
     * Schluessel zu fields hinzu — die Folge-URL muss den (url-codierten)
     * Schluessel "BIRT:PLAC" enthalten.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchAdvancedActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_other_field(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-adv-of-' . substr(md5($this->name()), 0, 8), 'Test 2');

        $handler = new SearchAdvancedAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'fields'      => ['NAME' => 'Jane'],
                'modifiers'   => [],
                'other_field' => 'BIRT:PLAC',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringContainsString('BIRT%3APLAC', $response->getHeaderLine('location'));
    }
}
