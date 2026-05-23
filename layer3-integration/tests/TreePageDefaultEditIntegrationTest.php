<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageDefaultEdit;
use Fisharebest\Webtrees\Services\HomePageService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: TreePageDefaultEdit-Handler.
 *
 * Deckt den Admin-Handler ab, der die Default-Block-Konfiguration fuer
 * die TreePage anzeigt. Der Handler delegiert an den HomePageService,
 * pruegft die Existenz der Default-Bloecke und liefert die verfuegbaren
 * Bloecke (zwei Spalten + verfuegbare Liste) an das Edit-Formular.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageDefaultEditTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageDefaultEdit
 */
class TreePageDefaultEditIntegrationTest extends MysqlTestCase
{
    /**
     * Klassen-Smoke-Test: TreePageDefaultEdit existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageDefaultEditTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_default_edit_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(TreePageDefaultEdit::class));
    }

    /**
     * TreePageDefaultEdit::handle liefert HTTP 200 und ruft am
     * HomePageService die Default-Block-Pruefung, beide Spalten und die
     * verfuegbaren Bloecke ab.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageDefaultEditTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_response(): void
    {
        // Arrange
        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('checkDefaultTreeBlocksExist');
        $home_page_service->expects(self::exactly(2))
            ->method('treeBlocks')
            ->willReturn(new Collection());
        $home_page_service->expects(self::once())
            ->method('availableTreeBlocks')
            ->willReturn(new Collection());

        $handler = new TreePageDefaultEdit($home_page_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
