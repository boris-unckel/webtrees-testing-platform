<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\FamilyListModule;
use Fisharebest\Webtrees\Module\IndividualListModule;
use Fisharebest\Webtrees\Module\MediaListModule;
use Fisharebest\Webtrees\Module\NoteListModule;
use Fisharebest\Webtrees\Module\RepositoryListModule;
use Fisharebest\Webtrees\Module\SourceListModule;
use Fisharebest\Webtrees\Module\SubmitterListModule;
use Fisharebest\Webtrees\Services\LinkedRecordService;

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
 * @see docs/testing-bigpicture-prompt.md S19, S20
 */
class ListModuleIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

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
}
