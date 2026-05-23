<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePage;
use Fisharebest\Webtrees\Services\HomePageService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: TreePage-Handler.
 *
 * Deckt den Handler ab, der die Startseite eines Stammbaums (TreePage)
 * rendert. Der Handler delegiert an den HomePageService, der die fuer den
 * aktuellen Benutzer konfigurierten Tree-Bloecke liefert. Geprueft werden
 * die Klassenexistenz und der erfolgreiche GET-Pfad (HTTP 200) gegen einen
 * real erzeugten Baum mit gemocktem HomePageService, damit die
 * View-Renderingkette der echten Block-Module umgangen wird.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePage
 */
class TreePageIntegrationTest extends MysqlTestCase
{
    /**
     * Klassen-Smoke-Test: TreePage existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(TreePage::class));
    }

    /**
     * TreePage::handle liefert HTTP 200 und ruft am HomePageService die
     * tree-Bloecke fuer den Baum ab. HomePageService wird gemockt, um das
     * View-Rendering der echten Block-Module zu vermeiden.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_response(): void
    {
        // Arrange
        $tree       = $this->treeService->create('tree-page', 'Tree Page');
        $this->tree = $tree;

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->method('treeBlocks')->willReturn(new Collection());

        $handler = new TreePage($home_page_service);
        $request = $this->createRequest('GET', [], [], ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
