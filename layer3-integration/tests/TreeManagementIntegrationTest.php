<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateTreeAction;
use Fisharebest\Webtrees\Http\RequestHandlers\DeleteTreeAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ManageTrees;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\ModuleService;

/**
 * Komponentenintegrationstest: Stammbaum-Management — A01.
 *
 * Tests:
 * - CreateTreeAction: Name bereits vorhanden → 302 zu CreateTreePage
 * - CreateTreeAction: Neuer Name → 302 zu ManageTrees, Baum in DB
 * - DeleteTreeAction: Baum löschen → 200, Baum nicht mehr in DB
 * - ManageTrees GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateTreeAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DeleteTreeAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ManageTrees
 * @see docs/testquality_improve_A01.md
 */
class TreeManagementIntegrationTest extends MysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        // Basisbaum für ManageTrees-View benötigt
        $this->createTreeWithGedcom('a01-mgmt', 'A01 Management', '/fixtures/demo.ged');
    }

    /**
     * EP1: CreateTreeAction — Name bereits vorhanden → 302 zu CreateTreePage.
     */
    public function test_create_tree_redirects_when_name_exists(): void
    {
        // Ersten Baum anlegen (unique name um Konflikte zu vermeiden)
        $existing_tree = $this->treeService->create('existing-tree-a01-' . uniqid(), 'Existing Tree A01');

        $handler = new CreateTreeAction($this->treeService);

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'name'  => $existing_tree->name(),
                'title' => 'Existierender Baum',
            ],
        );

        $response = $handler->handle($request);

        // 302 zu CreateTreePage (URL enthält route=%2Ftrees%2Fcreate)
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringContainsString('create', $response->getHeaderLine('location'));
    }

    /**
     * EP2: CreateTreeAction — Neuer Name → 302 zu ManageTrees, Baum in DB.
     */
    public function test_create_tree_creates_tree_and_redirects(): void
    {
        $new_name  = 'new-tree-a01-' . uniqid();
        $handler   = new CreateTreeAction($this->treeService);

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'name'  => $new_name,
                'title' => 'Neuer Baum A01',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: Baum in DB
        $exists = DB::table('gedcom')
            ->where('gedcom_name', '=', $new_name)
            ->exists();

        self::assertTrue($exists);
    }

    /**
     * EP3: DeleteTreeAction → 200, Baum nicht mehr in DB.
     */
    public function test_delete_tree_removes_tree(): void
    {
        $tree_to_delete = $this->treeService->create('delete-tree-a01-' . uniqid(), 'Delete Tree A01');
        $tree_id        = $tree_to_delete->id();

        $handler = new DeleteTreeAction($this->treeService);

        $request = $this->createRequest(
            attributes: ['tree' => $tree_to_delete],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());

        // Postcondition: Baum nicht mehr in DB
        $exists = DB::table('gedcom')
            ->where('gedcom_id', '=', $tree_id)
            ->exists();

        self::assertFalse($exists);
    }

    /**
     * EP7: ManageTrees GET → 200.
     */
    public function test_manage_trees_page_returns_200(): void
    {
        $handler = new ManageTrees(
            Registry::container()->get(AdminService::class),
            $this->treeService,
        );

        $request  = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
