<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageBlock;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageBlock;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Services\HomePageService;
use Fisharebest\Webtrees\Tree;

/**
 * Komponentenintegrationstest: PageBlock-Display-Handler (TreePage/UserPage).
 *
 * Sammelt die Block-Anzeige-Handler des Stammbaum- und Benutzer-Dashboards
 * (TreePageBlock, UserPageBlock). Komplementär zu PageBlockEditIntegrationTest
 * (Edit-Handler). Initialer Import deckt TreePageBlock ab; weitere Quellen
 * werden in dieser Datei ergaenzt.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageBlock
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPageBlock
 */
class PageBlockDisplayIntegrationTest extends MysqlTestCase
{
    /**
     * TreePageBlock::handle liefert HTTP 200, wenn der HomePageService den
     * Block-Modul fuer das angefragte Tree/Block-ID-Paar zurueckgibt. Die
     * DB-Lookup auf der `block`-Tabelle erfolgt fuer block_id=0 — keine
     * Treffer-Zeile, dadurch wird der HomePageService mit block_id=0
     * aufgerufen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_block_returns_ok_response(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $block = self::createStub(ModuleBlockInterface::class);
        $block->method('getBlock')->willReturn('<p>Block content</p>');

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('getBlockModule')
            ->willReturn($block);

        $handler = new TreePageBlock($home_page_service);
        $request = $this->createRequest('GET', ['block_id' => '0'], [], ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Klassen-Smoke-Test: TreePageBlock existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_block_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(TreePageBlock::class));
    }

    /**
     * UserPageBlock::handle liefert HTTP 200, wenn der HomePageService den
     * Block-Modul fuer das angefragte User/Block-ID-Paar zurueckgibt. Die
     * DB-Lookup auf der `block`-Tabelle erfolgt fuer block_id=0 — keine
     * Treffer-Zeile, dadurch wird der HomePageService mit block_id=0
     * aufgerufen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_block_returns_ok_response(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $user = $this->userService->findByUserName('upb')
            ?? $this->userService->create('upb', 'User Page Block', 'upb@example.com', 'secret');

        $block = self::createStub(ModuleBlockInterface::class);
        $block->method('getBlock')->willReturn('<p>User block content</p>');

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('getBlockModule')
            ->willReturn($block);

        $handler = new UserPageBlock($home_page_service);
        $request = $this->createRequest('GET', ['block_id' => '0'], [], ['tree' => $tree, 'user' => $user]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Klassen-Smoke-Test: UserPageBlock existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_block_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(UserPageBlock::class));
    }
}
