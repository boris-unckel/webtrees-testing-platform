<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageEdit;
use Fisharebest\Webtrees\Services\HomePageService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: TreePageEdit-Handler.
 *
 * Deckt den Handler ab, der die TreePage-Block-Konfiguration zum Editieren
 * anzeigt. Der Handler delegiert an den HomePageService, ruft die main- und
 * side-Bloecke des Baums fuer den aktuellen Benutzer ab und liefert die
 * verfuegbaren Bloecke an das Edit-Formular.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageEditTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageEdit
 */
class TreePageEditIntegrationTest extends MysqlTestCase
{
    /**
     * Klassen-Smoke-Test: TreePageEdit existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageEditTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_edit_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(TreePageEdit::class));
    }

    /**
     * TreePageEdit::handle liefert HTTP 200 und ruft am HomePageService die
     * main- und side-Bloecke sowie die verfuegbaren Bloecke fuer den Baum ab.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageEditTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_response(): void
    {
        // Arrange
        $tree       = $this->treeService->create('tree-edit', 'Tree Edit');
        $this->tree = $tree;

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->method('treeBlocks')->willReturn(new Collection());
        $home_page_service->method('availableTreeBlocks')->willReturn(new Collection());

        $handler = new TreePageEdit($home_page_service);
        $request = $this->createRequest('GET', [], [], ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
