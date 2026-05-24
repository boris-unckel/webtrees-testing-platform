<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePage;
use Fisharebest\Webtrees\Services\HomePageService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: TreePage-Handler.
 *
 * Deckt den Handler ab, der die Startseite eines Stammbaums (TreePage)
 * rendert. Der Handler delegiert an den HomePageService, der die fuer den
 * aktuellen Benutzer konfigurierten Tree-Bloecke liefert. Geprueft werden
 * beide Bootstrap-Pfade gegen einen real erzeugten Baum mit gemocktem
 * HomePageService (damit die View-Renderingkette der echten Block-Module
 * umgangen wird): der Default-Insert-Pfad (Baum ohne Bloecke, HTTP 200)
 * und der Skip-Pfad (Baum mit vorhandenen Bloecken, kein erneuter
 * checkDefaultTreeBlocksExist-Aufruf, keine zusaetzlichen Block-Inserts).
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePage
 */
class TreePageIntegrationTest extends MysqlTestCase
{
    /**
     * Wenn der Baum bereits eigene Block-Eintraege in der Tabelle `block`
     * besitzt, ueberspringt TreePage::handle den Default-Bootstrap und ruft
     * HomePageService::checkDefaultTreeBlocksExist() nicht erneut auf.
     * Geprueft wird der Skip-Pfad ueber eine never()-Erwartung am Mock und die
     * Postcondition, dass kein zusaetzlicher Block-Datensatz fuer den Baum
     * angelegt wurde.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_skips_default_block_bootstrap_when_blocks_exist(): void
    {
        // Arrange
        $tree       = $this->treeService->create('tree-page-blocks', 'Tree Page Blocks');
        $this->tree = $tree;

        // Baum bekommt einen existierenden Block-Eintrag, damit der
        // has_blocks-Pfad in TreePage::handle greift. module_name='todo' ist
        // ein in der wt_module-Tabelle registriertes Kernmodul (FK-sicher).
        DB::table('block')->insert([
            'gedcom_id'   => $tree->id(),
            'location'    => 'main',
            'block_order' => 0,
            'module_name' => 'todo',
        ]);

        $blocks_before = DB::table('block')->where('gedcom_id', '=', $tree->id())->count();

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::never())->method('checkDefaultTreeBlocksExist');
        $home_page_service->method('treeBlocks')->willReturn(new Collection());

        $handler = new TreePage($home_page_service);
        $request = $this->createRequest('GET', [], [], ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame(
            $blocks_before,
            DB::table('block')->where('gedcom_id', '=', $tree->id())->count(),
            'Vorhandene Blocks duerfen nicht durch Defaults ergaenzt werden.',
        );
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
