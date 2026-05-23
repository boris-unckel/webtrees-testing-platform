<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchPhoneticPage;
use Fisharebest\Webtrees\Services\SearchService;

/**
 * Komponentenintegrationstest: SearchPhoneticPage HTTP-Handler.
 *
 * Der Handler rendert die phonetische Suchseite (Russell- oder
 * Daitch-Mokotoff-Soundex). Die Quell-Datei prueft drei Pfade:
 * Default-Aufruf ohne Parameter, Suche mit Nachname (Russell) sowie
 * Suche mit Vor-/Nachname/Ort und Daitch-Mokotoff. Hier wird das
 * Verhalten gegen eine echte MySQL-DB-getragene Tree-Instanz
 * verifiziert.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchPhoneticPageTest.php
 * @group ported-l2-doubles
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchPhoneticPage
 */
class SearchPhoneticPageIntegrationTest extends MysqlTestCase
{
    private SearchPhoneticPage $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new SearchPhoneticPage(
            new SearchService($this->treeService),
            $this->treeService,
        );
    }

    /**
     * Default-Seite (keine Query-Parameter) rendert mit STATUS_OK und
     * liefert einen nicht-leeren Body.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchPhoneticPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_default_page_returns_ok_with_body(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $tree_slug  = 'search-phon-page-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($tree_slug, 'Test');

        $request = $this->createRequest(attributes: ['tree' => $this->tree]);

        // Act
        $response = $this->handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertNotEmpty((string) $response->getBody());
    }

    /**
     * Phonetische Suche mit Nachname und Russell-Soundex rendert die
     * Seite mit STATUS_OK.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchPhoneticPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_lastname_russell_returns_ok(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $tree_slug  = 'search-phon-page-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($tree_slug, 'Test');

        $request = $this->createRequest(
            query: ['lastname' => 'Smith', 'soundex' => 'Russell'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $this->handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Phonetische Suche mit Vorname/Nachname/Ort und Daitch-Mokotoff-
     * Soundex rendert die Seite mit STATUS_OK.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SearchPhoneticPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_daitch_mokotoff_returns_ok(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $tree_slug  = 'search-phon-page-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($tree_slug, 'Test');

        $request = $this->createRequest(
            query: [
                'firstname' => 'John',
                'lastname'  => 'Doe',
                'place'     => 'London',
                'soundex'   => 'DaitchM',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $this->handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
