<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePageUpdate;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Services\HomePageService;

/**
 * Komponentenintegrationstest: TreePageUpdate-Handler.
 *
 * Pendant zu TreePageEditIntegrationTest (Edit-Phase, GET); die Update-Phase
 * persistiert die TreePage-Block-Konfiguration eines konkreten Baums und
 * liefert nach erfolgreichem Speichern einen Redirect (HTTP 302). Der Handler
 * delegiert die Persistenz an den HomePageService::updateTreeBlocks.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageUpdateTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePageUpdate
 */
class TreePageUpdateIntegrationTest extends MysqlTestCase
{
    /**
     * Klassen-Smoke-Test: TreePageUpdate existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_tree_page_update_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(TreePageUpdate::class));
    }

    /**
     * TreePageUpdate::handle persistiert die TreePage-Blocks ueber den
     * HomePageService (mit konkretem Baum) und leitet nach dem Speichern
     * per HTTP 302 weiter.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/TreePageUpdateTest.php
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
