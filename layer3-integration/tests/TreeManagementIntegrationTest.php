<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateTreeAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateTreePage;
use Fisharebest\Webtrees\Http\RequestHandlers\DeleteTreeAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ManageTrees;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeTreesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeTreesPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SelectDefaultTree;
use Fisharebest\Webtrees\Http\RequestHandlers\SynchronizeTrees;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TimeoutService;
use Fisharebest\Webtrees\Services\TreeService;
use Illuminate\Support\Collection;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Komponentenintegrationstest: Stammbaum-Management — A01.
 *
 * Tests:
 * - CreateTreeAction: Name bereits vorhanden → 302 zu CreateTreePage
 * - CreateTreeAction: Neuer Name → 302 zu ManageTrees, Baum in DB
 * - DeleteTreeAction: Baum löschen → 200, Baum nicht mehr in DB
 * - ManageTrees GET → 200
 * - CreateTreePage: Klassenexistenz, GET → 200, mit/ohne Query-Parameter
 * - MergeTreesAction: zwei leere Bäume → 302, selber Baum → 302
 * - MergeTreesPage: GET ohne Bäume → 200, mit zwei Bäumen → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateTreeAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateTreePage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DeleteTreeAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ManageTrees
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeTreesAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeTreesPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SelectDefaultTree
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SynchronizeTrees
 * @see docs/testquality_improve_A01.md
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateTreePageTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MergeTreesActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MergeTreesPageTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SelectDefaultTreeTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SynchronizeTreesTest.php
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

    /**
     * CreateTreePage: Klasse existiert (Smoke).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateTreePageTest.php
     * @group ported-l2-doubles
     */
    public function test_create_tree_page_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(CreateTreePage::class));
    }

    /**
     * CreateTreePage: GET ohne Query-Parameter → 200, uniqueTreeName() einmal aufgerufen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateTreePageTest.php
     * @group ported-l2-doubles
     */
    public function test_create_tree_page_handle_returns_ok_response(): void
    {
        // Arrange
        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('uniqueTreeName')
            ->willReturn('tree1');

        $handler = new CreateTreePage($tree_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CreateTreePage: GET mit Query-Parametern (name, title) → 200.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateTreePageTest.php
     * @group ported-l2-doubles
     */
    public function test_create_tree_page_handle_with_custom_query_params(): void
    {
        // Arrange
        $tree_service = self::createStub(TreeService::class);
        $tree_service->method('uniqueTreeName')->willReturn('default');

        $handler = new CreateTreePage($tree_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: ['name' => 'custom-tree', 'title' => 'Custom Title'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CreateTreePage: Defaults werden genutzt, wenn keine Query-Parameter vorhanden sind.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateTreePageTest.php
     * @group ported-l2-doubles
     */
    public function test_create_tree_page_handle_uses_defaults_when_no_query_params(): void
    {
        // Arrange
        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('uniqueTreeName')
            ->willReturn('tree-auto');

        $handler = new CreateTreePage($tree_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * ManageTrees: Klasse existiert (Smoke).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ManageTreesTest.php
     * @group ported-l2-doubles
     */
    public function test_manage_trees_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(ManageTrees::class));
    }

    /**
     * MergeTreesAction: Klasse existiert (Smoke).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MergeTreesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_merge_trees_action_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(MergeTreesAction::class));
    }

    /**
     * MergeTreesAction: zwei leere Bäume zusammenführen → 302 Redirect.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MergeTreesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_merge_trees_action_merges_empty_trees_and_redirects(): void
    {
        // Arrange
        $tree1         = $this->treeService->create('merge-src-' . uniqid(), 'Merge Source');
        $tree2         = $this->treeService->create('merge-dst-' . uniqid(), 'Merge Destination');
        $admin_service = Registry::container()->get(AdminService::class);

        $handler = new MergeTreesAction($admin_service, $this->treeService);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'tree1_name' => $tree1->name(),
                'tree2_name' => $tree2->name(),
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * MergeTreesAction: derselbe Baum kann nicht zusammengeführt werden → 302 zurück zur Merge-Page.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MergeTreesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_merge_trees_action_redirects_when_same_tree(): void
    {
        // Arrange
        $tree          = $this->treeService->create('merge-same-' . uniqid(), 'Merge Same');
        $admin_service = Registry::container()->get(AdminService::class);

        $handler = new MergeTreesAction($admin_service, $this->treeService);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'tree1_name' => $tree->name(),
                'tree2_name' => $tree->name(),
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: derselbe Baum kann nicht gemerged werden, Redirect zurück zur Page
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * MergeTreesPage: Klasse existiert (Smoke).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MergeTreesPageTest.php
     * @group ported-l2-doubles
     */
    public function test_merge_trees_page_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(MergeTreesPage::class));
    }

    /**
     * MergeTreesPage: GET ohne ausgewählte Bäume → 200.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MergeTreesPageTest.php
     * @group ported-l2-doubles
     */
    public function test_merge_trees_page_returns_200_without_selected_trees(): void
    {
        // Arrange
        $admin_service = Registry::container()->get(AdminService::class);

        $handler = new MergeTreesPage($admin_service, $this->treeService);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * MergeTreesPage: GET mit zwei ausgewählten Bäumen (Query-Parameter) → 200.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MergeTreesPageTest.php
     * @group ported-l2-doubles
     */
    public function test_merge_trees_page_returns_200_with_selected_trees(): void
    {
        // Arrange
        $tree1         = $this->treeService->create('merge-page-a-' . uniqid(), 'Merge Page A');
        $tree2         = $this->treeService->create('merge-page-b-' . uniqid(), 'Merge Page B');
        $admin_service = Registry::container()->get(AdminService::class);

        $handler = new MergeTreesPage($admin_service, $this->treeService);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: [
                'tree1_name' => $tree1->name(),
                'tree2_name' => $tree2->name(),
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * SelectDefaultTree: Klasse existiert (Smoke).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SelectDefaultTreeTest.php
     * @group ported-l2-doubles
     */
    public function test_select_default_tree_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(SelectDefaultTree::class));
    }

    /**
     * SelectDefaultTree: setzt DEFAULT_GEDCOM auf den Baumnamen und leitet weiter (302).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SelectDefaultTreeTest.php
     * @group ported-l2-doubles
     */
    public function test_select_default_tree_sets_default_and_redirects(): void
    {
        // Arrange
        $previous_default = Site::getPreference('DEFAULT_GEDCOM');
        $tree_name        = 'default-test-' . uniqid();
        $tree             = $this->treeService->create($tree_name, 'Default Test');

        $handler = new SelectDefaultTree();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $tree],
        );

        try {
            // Act
            $response = $handler->handle($request);

            // Assert: Redirect (302) und DEFAULT_GEDCOM gesetzt
            self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
            self::assertSame($tree_name, Site::getPreference('DEFAULT_GEDCOM'));
        } finally {
            // Isolation (FIRST): vorherigen Site-State wiederherstellen
            Site::setPreference('DEFAULT_GEDCOM', $previous_default);
        }
    }

    /**
     * SynchronizeTrees: Klasse existiert (Smoke).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SynchronizeTreesTest.php
     * @group ported-l2-doubles
     */
    public function test_synchronize_trees_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(SynchronizeTrees::class));
    }

    /**
     * SynchronizeTrees: ohne GEDCOM-Dateien wird nach ManageTrees weitergeleitet (302).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SynchronizeTreesTest.php
     * @group ported-l2-doubles
     */
    public function test_synchronize_trees_redirects_when_no_gedcom_files(): void
    {
        // Arrange
        $tree_service = new TreeService(new GedcomImportService());
        // Mindestens ein Baum vorhanden, damit der finale Redirect ein Ziel hat
        $tree_service->create('sync-test-' . uniqid(), 'Sync Test');

        $admin_service = $this->createMock(AdminService::class);
        $admin_service->expects(self::once())
            ->method('gedcomFiles')
            ->willReturn(new Collection());

        $stream_factory  = self::createStub(StreamFactoryInterface::class);
        $timeout_service = self::createStub(TimeoutService::class);

        $handler = new SynchronizeTrees($admin_service, $stream_factory, $timeout_service, $tree_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect (302) nach Verarbeitung
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
