<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
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
 * @see docs/tds_conditions_ref.md S46
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
     * TreePageBlock::handle resolviert die `block_id`-Query gegen die
     * `block`-Tabelle und uebergibt das aufgeloeste Ergebnis an den
     * HomePageService. Komplementaer zu test_tree_page_block_returns_ok_response
     * (Tree-Stub, block_id=0) — hier wird ein realer Tree und eine echte
     * block-Zeile angelegt, sodass der DB-Lookup eine konkrete block_id liefert
     * (nicht 0). Die Mock-Erwartung pinnt die durchgereichte block_id auf den
     * eingefuegten Primaerschluessel; zusaetzlich wird der gerenderte
     * Block-Inhalt im Response-Body verifiziert (view layouts/ajax). Damit ist
     * sichergestellt, dass die DB-Lookup-Branch nicht stillschweigend uebersprungen
     * werden kann, ohne dass dieser Test rot wird.
     *
     * @group ported-l2-doubles
     */
    public function test_tree_page_block_resolves_block_id_from_db_and_renders_content(): void
    {
        // Arrange — realer Tree mit Admin-Login (TreeService::create benoetigt Schreibrecht)
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('tpb-l3sp037', 'TreePageBlock L3SP-037');

        // Echte block-Zeile fuer diesen Tree anlegen
        $expected_block_id = (int) DB::table('block')->insertGetId([
            'gedcom_id'   => $this->tree->id(),
            'user_id'     => null,
            'xref'        => null,
            'location'    => 'main',
            'block_order' => 1,
            'module_name' => 'todo',
        ]);

        $rendered_content = '<p>Block-Inhalt L3SP-037</p>';

        $block = self::createStub(ModuleBlockInterface::class);
        $block->method('getBlock')->willReturn($rendered_content);

        // Mock-Erwartung pinnt: Handler reicht die aufgeloeste block_id (nicht 0) durch
        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('getBlockModule')
            ->with($this->tree, $expected_block_id)
            ->willReturn($block);

        $handler = new TreePageBlock($home_page_service);
        $request = $this->createRequest(
            method: 'GET',
            query: ['block_id' => (string) $expected_block_id],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Statuscode 200 und gerenderter Block-Inhalt landet im Response-Body
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString($rendered_content, $body);

        // Aufraeumen: angelegte block-Zeile vor tearDown loeschen (FK auf gedcom_id)
        DB::table('block')->where('block_id', '=', $expected_block_id)->delete();
        Auth::logout();
    }

    /**
     * UserPageBlock::handle liefert HTTP 200, wenn der HomePageService den
     * Block-Modul fuer das angefragte User/Block-ID-Paar zurueckgibt. Die
     * DB-Lookup auf der `block`-Tabelle erfolgt fuer block_id=0 — keine
     * Treffer-Zeile, dadurch wird der HomePageService mit block_id=0
     * aufgerufen.
     *
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
     * UserPageBlock::handle resolviert die `block_id`-Query gegen die
     * `block`-Tabelle und uebergibt das aufgeloeste Ergebnis an den
     * HomePageService. Komplementaer zu test_user_page_block_returns_ok_response
     * (Tree-Stub, block_id=0) — hier wird ein realer Tree, ein realer User und
     * eine echte block-Zeile (mit user_id, gedcom_id=null) angelegt, sodass der
     * DB-Lookup eine konkrete block_id liefert (nicht 0). Die Mock-Erwartung
     * pinnt die durchgereichte block_id auf den eingefuegten Primaerschluessel;
     * zusaetzlich wird der gerenderte Block-Inhalt im Response-Body verifiziert
     * (view layouts/ajax). Damit ist sichergestellt, dass die DB-Lookup-Branch
     * (Filter auf user_id, nicht gedcom_id) nicht stillschweigend uebersprungen
     * werden kann, ohne dass dieser Test rot wird.
     *
     * @group ported-l2-doubles
     */
    public function test_user_page_block_resolves_block_id_from_db_and_renders_content(): void
    {
        // Arrange — realer Admin-User und realer Tree (TreeService::create benoetigt Schreibrecht)
        $admin = $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('upb-l3sp038', 'UserPageBlock L3SP-038');

        // Echte block-Zeile fuer diesen User anlegen (user_id gesetzt, gedcom_id=null gemaess UserPageBlock-WHERE)
        $expected_block_id = (int) DB::table('block')->insertGetId([
            'gedcom_id'   => null,
            'user_id'     => $admin->id(),
            'xref'        => null,
            'location'    => 'main',
            'block_order' => 1,
            'module_name' => 'todo',
        ]);

        $rendered_content = '<p>User-Block-Inhalt L3SP-038</p>';

        $block = self::createStub(ModuleBlockInterface::class);
        $block->method('getBlock')->willReturn($rendered_content);

        // Mock-Erwartung pinnt: Handler reicht die aufgeloeste block_id (nicht 0) durch
        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('getBlockModule')
            ->with($this->tree, $expected_block_id)
            ->willReturn($block);

        $handler = new UserPageBlock($home_page_service);
        $request = $this->createRequest(
            method: 'GET',
            query: ['block_id' => (string) $expected_block_id],
            attributes: ['tree' => $this->tree, 'user' => $admin],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Statuscode 200 und gerenderter Block-Inhalt landet im Response-Body
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString($rendered_content, $body);

        // Aufraeumen: angelegte block-Zeile vor tearDown loeschen (FK auf user_id)
        DB::table('block')->where('block_id', '=', $expected_block_id)->delete();
        Auth::logout();
    }
}
