<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageBlockUpdate;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageBlockUpdate;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Services\HomePageService;
use Psr\Http\Message\ServerRequestInterface;

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
     * TreePageBlockUpdate::handle reicht die `block_id` aus den Request-Attributen
     * unveraendert an `ModuleBlockInterface::saveBlockConfiguration($request, $block_id)`
     * weiter und erzeugt einen Redirect auf die Tree-Page (`/tree/<tree-name>`).
     * Komplementaer zu test_tree_page_block_update_redirects_after_save, das nur den
     * Statuscode 302 pint — hier wird zusaetzlich asserted, dass
     *
     *   1. der zweite Aufrufparameter von `saveBlockConfiguration` exakt die aus
     *      den Attributen entnommene `block_id` ist (kein stilles Defaulting auf 0),
     *   2. der `Location`-Header die Tree-Page-Route fuer den uebergebenen Tree
     *      traegt (Tree-Name wird URL-encoded in den Redirect-Pfad propagiert).
     *
     * Damit ist sichergestellt, dass weder block_id noch Tree-Bezug stillschweigend
     * verloren gehen koennen, ohne dass dieser Test rot wird.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageBlockUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_block_update_propagates_block_id_and_redirects_to_tree_page(): void
    {
        // Arrange — realer Tree (Admin-Login fuer TreeService::create), distinkter block_id-Wert
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('tpbu-l3sp041', 'TreePageBlockUpdate L3SP-041');

        $block_id = 4713;

        $block = $this->createMock(ModuleBlockInterface::class);
        $block->expects(self::once())
            ->method('saveBlockConfiguration')
            ->with(self::isInstanceOf(ServerRequestInterface::class), $block_id);

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('treeBlock')
            ->willReturn($block);

        $handler = new TreePageBlockUpdate($home_page_service);
        $request = $this->createRequest(
            method: 'POST',
            attributes: ['tree' => $this->tree, 'block_id' => $block_id],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Statuscode 302 und Location-Header mit Tree-Name (URL-encoded)
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringContainsString('tpbu-l3sp041', $response->getHeaderLine('location'));
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
     * UserPageBlockUpdate::handle reicht die `block_id` aus den Request-Attributen
     * unveraendert an `ModuleBlockInterface::saveBlockConfiguration($request, $block_id)`
     * weiter und erzeugt einen Redirect auf die User-Page-Route fuer den uebergebenen
     * Tree (`/tree/<tree-name>`). Komplementaer zu
     * test_user_page_block_update_redirects_after_save, das nur den Statuscode 302
     * pint — hier wird zusaetzlich asserted, dass
     *
     *   1. der zweite Aufrufparameter von `saveBlockConfiguration` exakt die aus
     *      den Attributen entnommene `block_id` ist (kein stilles Defaulting auf 0),
     *   2. der `Location`-Header die User-Page-Route fuer den uebergebenen Tree
     *      traegt (Tree-Name wird URL-encoded in den Redirect-Pfad propagiert).
     *
     * Damit ist sichergestellt, dass weder block_id noch Tree-Bezug stillschweigend
     * verloren gehen koennen, ohne dass dieser Test rot wird.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageBlockUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_block_update_propagates_block_id_and_redirects_to_user_page(): void
    {
        // Arrange — realer Tree (Admin-Login fuer TreeService::create), distinkter block_id-Wert
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('upbu-l3sp042', 'UserPageBlockUpdate L3SP-042');

        $user = $this->userService->findByUserName('upbu-l3sp042')
            ?? $this->userService->create('upbu-l3sp042', 'UserPageBlockUpdate L3SP-042', 'upbu-l3sp042@example.com', 'secret');

        $block_id = 4714;

        $block = $this->createMock(ModuleBlockInterface::class);
        $block->expects(self::once())
            ->method('saveBlockConfiguration')
            ->with(self::isInstanceOf(ServerRequestInterface::class), $block_id);

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('userBlock')
            ->willReturn($block);

        $handler = new UserPageBlockUpdate($home_page_service);
        $request = $this->createRequest(
            method: 'POST',
            attributes: ['tree' => $this->tree, 'user' => $user, 'block_id' => $block_id],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Statuscode 302 und Location-Header mit Tree-Name (URL-encoded)
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringContainsString('upbu-l3sp042', $response->getHeaderLine('location'));
    }
}
