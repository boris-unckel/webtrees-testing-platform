<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageUpdate;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\HomePageService;

/**
 * Komponentenintegrationstest: TreePageUpdate-Handler.
 *
 * Pendant zu TreePageEditIntegrationTest (Edit-Phase, GET); die Update-Phase
 * persistiert die TreePage-Block-Konfiguration eines konkreten Baums und
 * liefert nach erfolgreichem Speichern einen Redirect (HTTP 302). Der Handler
 * delegiert die Persistenz an den HomePageService::updateTreeBlocks.
 *
 * @see docs/tds_conditions_ref.md S46
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageUpdate
 */
class TreePageUpdateIntegrationTest extends MysqlTestCase
{
    /**
     * TreePageUpdate: Container-Resolution + handle() → 302 mit realem HomePageService.
     *
     * Verhaltens-Test (BEHAVIOR_HANDLE / L3SP-074): ersetzt die ehemalige
     * `class_exists`-Tautologie durch einen vollstaendigen Request-Durchlauf gegen
     * die real verdrahtete Klasse aus dem DI-Container. Smoke-Aspekt (Auflöesbarkeit)
     * ist im 302-Redirect enthalten; zusaetzlich wird die dokumentierte
     * Persistenz-Postcondition geprueft: nach Aufruf von updateTreeBlocks(tree_id, [], [])
     * mit leerem Body sind in der `block`-Tabelle keine Zeilen mehr fuer den
     * konkreten Baum (alle vorher vorhandenen Blocks werden geloescht, neue
     * werden mangels Input nicht angelegt) und das Location-Header zeigt auf
     * die TreePage-Route des Baums.
     *
     * @group ported-l2-doubles
     */
    public function test_tree_page_update_handles_request_via_container(): void
    {
        // Arrange: Tree fuer den Request-Kontext anlegen — der Handler liest
        // Validator::attributes($request)->tree() und ruft updateTreeBlocks
        // mit der tree_id auf.
        $this->tree = $this->treeService->create('tree-update-l3sp074', 'Tree Update L3SP-074');
        $admin      = $this->createAndLoginAdmin();

        // Bestandsblock anlegen, damit die Persistenz-Postcondition
        // (Loeschen aller Bestands-Blocks bei leerem Body) beobachtbar wird.
        DB::table('block')->insert([
            'gedcom_id'   => $this->tree->id(),
            'location'    => ModuleBlockInterface::MAIN_BLOCKS,
            'block_order' => 0,
            'module_name' => 'todo',
        ]);

        $handler = Registry::container()->get(TreePageUpdate::class);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                ModuleBlockInterface::MAIN_BLOCKS => [],
                ModuleBlockInterface::SIDE_BLOCKS => [],
            ],
            attributes: [
                'tree' => $this->tree,
                'user' => $admin,
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 302 — Handler ist real auflöesbar und leitet nach dem Speichern weiter.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: Location-Header zeigt auf die TreePage-Route des konkreten Baums.
        // Im Testkontext (ohne Pretty-URLs) wird die Route als Query-Parameter ausgeliefert:
        // `?route=%2F<tree>` (TreePage-Route ist '/tree/{tree}' mit leerem Suffix, der Router
        // generiert hier den Baumnamen-Token). Geprueft wird, dass der konkrete Tree-Name
        // im Location-Header eincodiert ist — Beweis, dass der Handler die Route mit dem
        // aufgeloesten Tree-Namen parametrisiert und nicht auf eine statische Seite leitet.
        $tree_segment = rawurlencode($this->tree->name());
        self::assertStringContainsString(
            $tree_segment,
            $response->getHeaderLine('location'),
            'Redirect-Location sollte den Tree-Namen der TreePage-Route enthalten.',
        );

        // Postcondition: leerer Body → updateTreeBlocks(tree_id, [], []) entfernt
        // alle vorhandenen Block-Zeilen fuer den Baum, ohne neue anzulegen.
        self::assertFalse(
            DB::table('block')->where('gedcom_id', '=', $this->tree->id())->exists(),
            'TreePageUpdate sollte bei leerem Body alle Block-Zeilen des Baums entfernen.',
        );
    }

    /**
     * TreePageUpdate::handle persistiert die TreePage-Blocks ueber den
     * HomePageService (mit konkretem Baum) und leitet nach dem Speichern
     * per HTTP 302 weiter.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_with_explicit_blocks_redirects(): void
    {
        // Arrange
        $tree       = $this->treeService->create('tree-update', 'Tree Update');
        $this->tree = $tree;

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('updateTreeBlocks');

        $handler = new TreePageUpdate($home_page_service);
        $request = $this->createRequest('POST', [], [
            ModuleBlockInterface::MAIN_BLOCKS => [],
            ModuleBlockInterface::SIDE_BLOCKS => [],
        ], ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
