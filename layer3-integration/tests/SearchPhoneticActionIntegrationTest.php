<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchPhoneticAction;

/**
 * Komponentenintegrationstest: SearchPhoneticAction HTTP-Handler.
 *
 * Der Handler liest firstname/lastname/place sowie den gewuenschten
 * Soundex-Algorithmus (Russell/DaitchMokotoff) aus dem POST-Body und leitet
 * als 302-Redirect auf SearchPhoneticPage weiter. Hier wird das Verhalten
 * gegen eine echte MySQL-DB-getragene Tree-Instanz verifiziert.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchPhoneticActionTest.php
 * @group ported-l2-doubles
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchPhoneticAction
 */
class SearchPhoneticActionIntegrationTest extends MysqlTestCase
{
    /**
     * SearchPhoneticAction liefert HTTP 302 fuer einen einfachen Submit mit
     * firstname/lastname/place — der Handler leitet stets auf
     * SearchPhoneticPage weiter und uebernimmt die Suchparameter in die
     * Folge-URL.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchPhoneticActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirects(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $tree_slug  = 'search-phon-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($tree_slug, 'Test');

        $handler = new SearchPhoneticAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'firstname'    => 'John',
                'lastname'     => 'Smith',
                'place'        => 'London',
                'search_trees' => [$tree_slug],
                'soundex'      => 'Russell',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $location = $response->getHeaderLine('location');
        self::assertStringContainsString('John', $location);
        self::assertStringContainsString('Smith', $location);
        self::assertStringContainsString('London', $location);
    }
}
