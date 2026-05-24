<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageDefaultUpdate;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\HomePageService;

/**
 * Komponentenintegrationstest: TreePageDefaultUpdate-Handler.
 *
 * Pendant zu TreePageDefaultEditIntegrationTest (Edit-Phase, GET); die
 * Update-Phase speichert die Default-Block-Konfiguration fuer die TreePage
 * und liefert nach erfolgreichem Persistieren einen Redirect (HTTP 302)
 * zum Control-Panel. Der Handler delegiert die Persistenz an den
 * HomePageService (updateTreeBlocks mit tree_id = -1 fuer die Defaults).
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageDefaultUpdateTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageDefaultUpdate
 */
class TreePageDefaultUpdateIntegrationTest extends MysqlTestCase
{
    /**
     * TreePageDefaultUpdate: Container-Resolution + handle() → 302 mit realem HomePageService.
     *
     * Verhaltens-Test (BEHAVIOR_HANDLE / L3SP-071): ersetzt die ehemalige
     * `class_exists`-Tautologie durch einen vollstaendigen Request-Durchlauf gegen
     * die real verdrahtete Klasse aus dem DI-Container. Smoke-Aspekt
     * (Auflöesbarkeit) ist im 302-Redirect enthalten; zusaetzlich wird die
     * dokumentierte Persistenz-Postcondition geprueft: nach Aufruf von
     * updateTreeBlocks(-1, [], []) mit leerem Body sind in der `block`-Tabelle
     * keine Zeilen mehr mit gedcom_id = -1 (alle vorher vorhandenen Defaults
     * werden geloescht, neue werden mangels Input nicht angelegt).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageDefaultUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_default_update_handles_request_via_container(): void
    {
        // Arrange: einen vorhandenen Default-Block fuer gedcom_id = -1 anlegen,
        // damit die Persistenz-Postcondition (Loeschen aller Bestands-Blocks bei
        // leerem Body) ueberhaupt beobachtbar wird.
        DB::table('block')->where('gedcom_id', '=', -1)->delete();
        DB::table('block')->insert([
            'gedcom_id'   => -1,
            'location'    => ModuleBlockInterface::MAIN_BLOCKS,
            'block_order' => 0,
            'module_name' => 'gedcom_stats',
        ]);

        $handler = Registry::container()->get(TreePageDefaultUpdate::class);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                ModuleBlockInterface::MAIN_BLOCKS => [],
                ModuleBlockInterface::SIDE_BLOCKS => [],
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 302 — Handler ist real auflöesbar und leitet zum Control-Panel weiter.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertNotSame('', $response->getHeaderLine('location'));

        // Postcondition: leerer Body → updateTreeBlocks(-1, [], []) entfernt
        // alle vorhandenen Default-Block-Zeilen, ohne neue anzulegen.
        self::assertFalse(
            DB::table('block')->where('gedcom_id', '=', -1)->exists(),
            'TreePageDefaultUpdate sollte bei leerem Body alle Default-Block-Zeilen (gedcom_id = -1) entfernen.',
        );
    }

    /**
     * TreePageDefaultUpdate::handle persistiert die Default-Blocks ueber
     * den HomePageService (tree_id = -1) und leitet nach dem Speichern
     * per HTTP 302 zum Control-Panel weiter.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageDefaultUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_to_control_panel(): void
    {
        // Arrange
        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('updateTreeBlocks')
            ->with(-1, self::anything(), self::anything());

        $handler = new TreePageDefaultUpdate($home_page_service);
        $request = $this->createRequest('POST', [], [
            ModuleBlockInterface::MAIN_BLOCKS => [],
            ModuleBlockInterface::SIDE_BLOCKS => [],
        ]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
