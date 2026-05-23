<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageBlockEdit;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageBlockEdit;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Services\HomePageService;

/**
 * Komponentenintegrationstest: PageBlock-Edit-Handler (TreePage/UserPage).
 *
 * Sammelt die Block-Edit-Handler aus dem Stammbaum- und Benutzer-Dashboard
 * (TreePageBlockEdit, UserPageBlockEdit). Initialer Import deckt
 * TreePageBlockEdit ab; weitere Quellen werden in dieser Datei ergaenzt.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockEditTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockEditTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageBlockEdit
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPageBlockEdit
 */
class PageBlockEditIntegrationTest extends MysqlTestCase
{
    /**
     * TreePageBlockEdit::handle liefert HTTP 200, wenn der HomePageService
     * den Block fuer das angefragte Tree/Block-ID-Paar zurueckgibt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockEditTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_block_edit_returns_ok_response(): void
    {
        // Arrange
        $tree = $this->treeService->create('blk-edit', 'Block Edit');
        $this->tree = $tree;

        $block = self::createStub(ModuleBlockInterface::class);
        $block->method('title')->willReturn('Test Block');
        $block->method('editBlockConfiguration')->willReturn('');

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('treeBlock')
            ->willReturn($block);

        $handler = new TreePageBlockEdit($home_page_service);
        $request = $this->createRequest('GET', [], [], ['tree' => $tree, 'block_id' => 1]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Klassen-Smoke-Test: TreePageBlockEdit existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockEditTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_block_edit_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(TreePageBlockEdit::class));
    }

    /**
     * UserPageBlockEdit::handle liefert HTTP 200, wenn der HomePageService
     * den Block fuer das angefragte User/Block-ID-Paar zurueckgibt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockEditTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_block_edit_returns_ok_response(): void
    {
        // Arrange
        $tree = $this->treeService->create('upbe', 'User Block Edit');
        $this->tree = $tree;

        $user = $this->userService->findByUserName('upbe')
            ?? $this->userService->create('upbe', 'User Block Edit', 'upbe@example.com', 'secret');

        $block = self::createStub(ModuleBlockInterface::class);
        $block->method('title')->willReturn('Test User Block');
        $block->method('editBlockConfiguration')->willReturn('');

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('userBlock')
            ->willReturn($block);

        $handler = new UserPageBlockEdit($home_page_service);
        $request = $this->createRequest('GET', [], [], ['tree' => $tree, 'user' => $user, 'block_id' => 1]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Klassen-Smoke-Test: UserPageBlockEdit existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockEditTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_block_edit_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(UserPageBlockEdit::class));
    }
}
