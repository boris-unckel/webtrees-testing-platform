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
 * @see docs/tds_conditions_ref.md S46
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageBlockEdit
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPageBlockEdit
 */
class PageBlockEditIntegrationTest extends MysqlTestCase
{
    /**
     * TreePageBlockEdit::handle liefert HTTP 200, wenn der HomePageService
     * den Block fuer das angefragte Tree/Block-ID-Paar zurueckgibt.
     *
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
     * TreePageBlockEdit::handle reicht die `block_id` aus den Request-Attributen
     * sowie den Titel des aufgeloesten Blocks in das gerenderte Edit-Formular
     * durch. Komplementaer zu test_tree_page_block_edit_returns_ok_response, das
     * lediglich den Statuscode 200 pinnt — hier wird zusaetzlich asserted, dass
     *
     *   1. der `save_url` des Formulars die uebergebene block_id traegt
     *      (`route(TreePageBlockUpdate::class, [..., block_id => $block_id])`,
     *      URL-encoded als `tree-page-block-update%2F<id>` im action-Attribut),
     *   2. der berechnete Titel `$block->title() . ' — Preferences'` als
     *      `<h2>`-Inhalt in der Response erscheint.
     *
     * Damit ist sichergestellt, dass weder block_id noch Titel stillschweigend
     * verloren gehen koennen, ohne dass dieser Test rot wird.
     *
     * @group ported-l2-doubles
     */
    public function test_tree_page_block_edit_propagates_block_id_and_title_into_response(): void
    {
        // Arrange — realer Tree (Admin-Login fuer TreeService::create), distinkter block_id-Wert
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('tpbe-l3sp039', 'TreePageBlockEdit L3SP-039');

        $block_id   = 4711;
        $block_name = 'L3SP-039 Edit-Block';

        $block = self::createStub(ModuleBlockInterface::class);
        $block->method('title')->willReturn($block_name);
        $block->method('description')->willReturn('');
        $block->method('editBlockConfiguration')->willReturn('');

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('treeBlock')
            ->willReturn($block);

        $handler = new TreePageBlockEdit($home_page_service);
        $request = $this->createRequest(
            method: 'GET',
            attributes: ['tree' => $this->tree, 'block_id' => $block_id],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Statuscode 200, block_id im save_url, Block-Titel im H2
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('tree-page-block-update%2F' . $block_id, $body);
        self::assertStringContainsString($block_name . ' — Preferences', $body);
    }

    /**
     * UserPageBlockEdit::handle liefert HTTP 200, wenn der HomePageService
     * den Block fuer das angefragte User/Block-ID-Paar zurueckgibt.
     *
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
     * UserPageBlockEdit::handle reicht die `block_id` aus den Request-Attributen
     * sowie den Titel des aufgeloesten Blocks in das gerenderte Edit-Formular
     * durch. Komplementaer zu test_user_page_block_edit_returns_ok_response, das
     * lediglich den Statuscode 200 pinnt — hier wird zusaetzlich asserted, dass
     *
     *   1. der `save_url` des Formulars die uebergebene block_id traegt
     *      (`route(UserPageBlockUpdate::class, [..., block_id => $block_id])`,
     *      URL-encoded als `my-page-block-edit%2F<id>` im action-Attribut),
     *   2. der berechnete Titel `$block->title() . ' — Preferences'` als
     *      `<h2>`-Inhalt in der Response erscheint.
     *
     * Damit ist sichergestellt, dass weder block_id noch Titel stillschweigend
     * verloren gehen koennen, ohne dass dieser Test rot wird.
     *
     * @group ported-l2-doubles
     */
    public function test_user_page_block_edit_propagates_block_id_and_title_into_response(): void
    {
        // Arrange — realer Tree (Admin-Login fuer TreeService::create), distinkter block_id-Wert
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('upbe-l3sp040', 'UserPageBlockEdit L3SP-040');

        $user = $this->userService->findByUserName('upbe-l3sp040')
            ?? $this->userService->create('upbe-l3sp040', 'UserPageBlockEdit L3SP-040', 'upbe-l3sp040@example.com', 'secret');

        $block_id   = 4712;
        $block_name = 'L3SP-040 Edit-Block';

        $block = self::createStub(ModuleBlockInterface::class);
        $block->method('title')->willReturn($block_name);
        $block->method('description')->willReturn('');
        $block->method('editBlockConfiguration')->willReturn('');

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('userBlock')
            ->willReturn($block);

        $handler = new UserPageBlockEdit($home_page_service);
        $request = $this->createRequest(
            method: 'GET',
            attributes: ['tree' => $this->tree, 'user' => $user, 'block_id' => $block_id],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Statuscode 200, block_id im save_url, Block-Titel im H2
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('my-page-block-edit%2F' . $block_id, $body);
        self::assertStringContainsString($block_name . ' — Preferences', $body);
    }
}
