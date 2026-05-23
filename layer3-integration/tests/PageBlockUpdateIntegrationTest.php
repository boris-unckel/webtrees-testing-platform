<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageBlockUpdate;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageBlockUpdate;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Services\HomePageService;

/**
 * Komponentenintegrationstest: PageBlock-Update-Handler (TreePage/UserPage).
 *
 * Sammelt die Block-Update-Handler aus dem Stammbaum- und Benutzer-Dashboard
 * (TreePageBlockUpdate, UserPageBlockUpdate). Initialer Import deckt
 * TreePageBlockUpdate ab; weitere Quellen werden in dieser Datei ergaenzt.
 *
 * Pendant zu PageBlockEditIntegrationTest (Edit-Phase, GET); Update-Phase
 * speichert die Block-Konfiguration und liefert einen Redirect.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockUpdateTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockUpdateTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageBlockUpdate
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPageBlockUpdate
 */
class PageBlockUpdateIntegrationTest extends MysqlTestCase
{
    /**
     * TreePageBlockUpdate::handle leitet nach dem Speichern (HTTP 302)
     * weiter und delegiert das Persistieren an den Block selbst.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_block_update_redirects_after_save(): void
    {
        // Arrange
        $tree       = $this->treeService->create('blk-upd', 'Block Update');
        $this->tree = $tree;

        $block = $this->createMock(ModuleBlockInterface::class);
        $block->expects(self::once())
            ->method('saveBlockConfiguration');

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('treeBlock')
            ->willReturn($block);

        $handler = new TreePageBlockUpdate($home_page_service);
        $request = $this->createRequest('POST', [], [], ['tree' => $tree, 'block_id' => 1]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Klassen-Smoke-Test: TreePageBlockUpdate existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_block_update_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(TreePageBlockUpdate::class));
    }

    /**
     * UserPageBlockUpdate::handle leitet nach dem Speichern (HTTP 302) weiter
     * und delegiert das Persistieren der Block-Konfiguration an den Block.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_block_update_redirects_after_save(): void
    {
        // Arrange
        $tree       = $this->treeService->create('upbu', 'User Block Update');
        $this->tree = $tree;

        $user = $this->userService->findByUserName('upbu')
            ?? $this->userService->create('upbu', 'User Block Update', 'upbu@example.com', 'secret');

        $block = $this->createMock(ModuleBlockInterface::class);
        $block->expects(self::once())
            ->method('saveBlockConfiguration');

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('userBlock')
            ->willReturn($block);

        $handler = new UserPageBlockUpdate($home_page_service);
        $request = $this->createRequest('POST', [], [], ['tree' => $tree, 'user' => $user, 'block_id' => 1]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Klassen-Smoke-Test: UserPageBlockUpdate existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_block_update_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(UserPageBlockUpdate::class));
    }
}
