<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageDefaultUpdate;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
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
     * Klassen-Smoke-Test: TreePageDefaultUpdate existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageDefaultUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_default_update_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(TreePageDefaultUpdate::class));
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
