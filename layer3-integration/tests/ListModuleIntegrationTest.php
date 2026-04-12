<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\BranchesListModule;
use Fisharebest\Webtrees\Module\FamilyListModule;
use Fisharebest\Webtrees\Module\IndividualListModule;
use Fisharebest\Webtrees\Module\LocationListModule;
use Fisharebest\Webtrees\Module\MediaListModule;
use Fisharebest\Webtrees\Module\NoteListModule;
use Fisharebest\Webtrees\Module\PlaceHierarchyListModule;
use Fisharebest\Webtrees\Module\RepositoryListModule;
use Fisharebest\Webtrees\Module\SourceListModule;
use Fisharebest\Webtrees\Module\SubmitterListModule;
use Fisharebest\Webtrees\Services\LeafletJsService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\SearchService;

/**
 * Komponentenintegrationstest: List-Module mit MySQL.
 *
 * Testet alle Listen-Handler: handle() → 200 OK und listIsEmpty() → false.
 *
 * @covers \Fisharebest\Webtrees\Module\IndividualListModule
 * @covers \Fisharebest\Webtrees\Module\FamilyListModule
 * @covers \Fisharebest\Webtrees\Module\SourceListModule
 * @covers \Fisharebest\Webtrees\Module\RepositoryListModule
 * @covers \Fisharebest\Webtrees\Module\NoteListModule
 * @covers \Fisharebest\Webtrees\Module\MediaListModule
 * @covers \Fisharebest\Webtrees\Module\SubmitterListModule
 * @covers \Fisharebest\Webtrees\Module\LocationListModule
 * @covers \Fisharebest\Webtrees\Module\PlaceHierarchyListModule
 * @covers \Fisharebest\Webtrees\Module\BranchesListModule
 * @see docs/tds_conditions_ref.md S19, S20
 */
class ListModuleIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';
    private const MUSTER_GED = '/fixtures/gedcom-l-muster.ged';

    // --- IndividualListModule ---

    public function test_individual_list_handle_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new IndividualListModule();
        $request = $this->createRequest(attributes: ['tree' => $this->tree]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function test_individual_list_show_all_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new IndividualListModule();
        $request = $this->createRequest(
            query: ['show_all' => 'yes'],
            attributes: ['tree' => $this->tree],
        );

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function test_individual_list_is_not_empty(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $module = new IndividualListModule();

        $this->assertFalse($module->listIsEmpty($this->tree));
    }

    // --- FamilyListModule ---

    public function test_family_list_handle_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new FamilyListModule();
        $request = $this->createRequest(attributes: ['tree' => $this->tree]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function test_family_list_is_not_empty(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $module = new FamilyListModule();

        $this->assertFalse($module->listIsEmpty($this->tree));
    }

    // --- SourceListModule ---

    public function test_source_list_handle_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new SourceListModule();
        $request = $this->createRequest(attributes: [
            'tree' => $this->tree,
            'user' => $admin,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function test_source_list_is_not_empty(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $module = new SourceListModule();

        $this->assertFalse($module->listIsEmpty($this->tree));
    }

    // --- RepositoryListModule ---

    public function test_repository_list_handle_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new RepositoryListModule();
        $request = $this->createRequest(attributes: [
            'tree' => $this->tree,
            'user' => $admin,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function test_repository_list_is_not_empty(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $module = new RepositoryListModule();

        $this->assertFalse($module->listIsEmpty($this->tree));
    }

    // --- NoteListModule ---

    public function test_note_list_handle_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new NoteListModule();
        $request = $this->createRequest(attributes: ['tree' => $this->tree]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // --- MediaListModule ---

    public function test_media_list_handle_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new MediaListModule(new LinkedRecordService());
        $request = $this->createRequest(attributes: [
            'tree' => $this->tree,
            'user' => $admin,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function test_media_list_is_not_empty(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $module = new MediaListModule(new LinkedRecordService());

        $this->assertFalse($module->listIsEmpty($this->tree));
    }

    // --- SubmitterListModule ---

    public function test_submitter_list_handle_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new SubmitterListModule();
        $request = $this->createRequest(attributes: [
            'tree' => $this->tree,
            'user' => $admin,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // --- AP: S19-Collation (Nachnamen-Initial-Filter) ---

    /**
     * S19 — Personenliste nach Nachnamen-Initial 'W' gefiltert: 200 OK.
     */
    public function test_individual_list_filtered_by_initial_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new IndividualListModule();
        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
            query:      ['alpha' => 'W'],
        );

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        // demo.ged enthält Windsor-Nachnamen → Body enthält 'W'
        $this->assertStringContainsString('W', (string) $response->getBody());
    }

    // --- AP 8-7: Fehlende Listen-Smoke-Tests (S20) ---

    /**
     * S20 — Location-Liste rendert mit muster-Tree (_LOC-Records vorhanden).
     */
    public function test_location_list_module_renders_with_muster_tree(): void
    {
        $this->createTreeWithGedcom('muster', 'Muster', self::MUSTER_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new LocationListModule();
        $request = $this->createRequest(attributes: [
            'tree' => $this->tree,
            'user' => $admin,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * S20 — Orts-Hierarchie-Liste rendert.
     */
    public function test_place_hierarchy_list_module_renders(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new PlaceHierarchyListModule(
            new LeafletJsService(new ModuleService()),
            new ModuleService(),
            new SearchService($this->treeService),
        );
        $request = $this->createRequest(attributes: [
            'tree'     => $this->tree,
            'user'     => $admin,
            'place_id' => 0,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * S20 — Branches-Liste rendert.
     */
    public function test_branches_list_module_renders(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new BranchesListModule(new ModuleService());
        $request = $this->createRequest(attributes: [
            'tree'    => $this->tree,
            'user'    => $admin,
            'surname' => 'Windsor',
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
