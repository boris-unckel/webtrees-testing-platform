<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\ChartsBlockModule;
use PHPUnit\Framework\Attributes\DataProvider;
use Fisharebest\Webtrees\Module\ClippingsCartModule;
use Fisharebest\Webtrees\Module\ReviewChangesModule;
use Fisharebest\Webtrees\Module\SlideShowModule;
use Fisharebest\Webtrees\Module\UpcomingAnniversariesModule;
use Fisharebest\Webtrees\Module\YahrzeitModule;
use Fisharebest\Webtrees\Module\TopSurnamesModule;
use Fisharebest\Webtrees\Module\ResearchTaskModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\CalendarService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\UserService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Komponentenintegrationstest: Block-Module getBlock() Smoke-Tests.
 *
 * @see docs/testing-bigpicture.md S46
 * @covers \Fisharebest\Webtrees\Module\SlideShowModule
 * @covers \Fisharebest\Webtrees\Module\YahrzeitModule
 * @covers \Fisharebest\Webtrees\Module\ReviewChangesModule
 * @covers \Fisharebest\Webtrees\Module\ChartsBlockModule
 * @covers \Fisharebest\Webtrees\Module\UpcomingAnniversariesModule
 * @covers \Fisharebest\Webtrees\Module\TopSurnamesModule
 * @covers \Fisharebest\Webtrees\Module\ResearchTaskModule
 * @covers \Fisharebest\Webtrees\Module\ClippingsCartModule
 */
class BlockModuleIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * SlideShowModule::getBlock gibt non-empty String zurück (EP1: Medienblock standard).
     * demo.ged enthält Medien → entweder Bild-HTML oder "no images"-Text.
     */
    public function test_slide_show_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new SlideShowModule(new LinkedRecordService());
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertNotEmpty($result);
    }

    /**
     * YahrzeitModule::getBlock gibt String zurück.
     */
    public function test_yahrzeit_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new YahrzeitModule(new CalendarService());
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }

    /**
     * ReviewChangesModule::getBlock gibt String zurück.
     */
    public function test_review_changes_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new ReviewChangesModule(
            Registry::container()->get(EmailService::class),
            $this->treeService,
            Registry::container()->get(UserService::class),
        );
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }

    /**
     * ChartsBlockModule::getBlock gibt String zurück.
     */
    public function test_charts_block_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new ChartsBlockModule(new ModuleService());
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }

    /**
     * UpcomingAnniversariesModule::getBlock gibt String zurück.
     */
    public function test_upcoming_anniversaries_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new UpcomingAnniversariesModule(new CalendarService());
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }

    /**
     * TopSurnamesModule::getBlock gibt String zurück.
     */
    public function test_top_surnames_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new TopSurnamesModule(new ModuleService());
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }

    /**
     * ResearchTaskModule::getBlock gibt String zurück.
     */
    public function test_research_task_module_get_block_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new ResearchTaskModule(new ModuleService());
        $result = $module->getBlock($this->tree, 0, 'main');

        $this->assertIsString($result);
    }

    // --- TopSurnamesModule — info_style EP-Matrix (EP4/EP5/EP6) ---

    /**
     * @return array<string, array{string}>
     */
    public static function infoStyles(): array
    {
        return [
            'table'    => ['table'],
            'tagcloud' => ['tagcloud'],
            'list'     => ['list'],
            'array'    => ['array'],
        ];
    }

    /**
     * TopSurnamesModule::getBlock mit allen info_style-Varianten gibt non-empty String zurück.
     * extract($config) überschreibt $info_style → alle 4 View-Branches testbar (EP4–EP6 + 'array').
     */
    #[DataProvider('infoStyles')]
    public function test_top_surnames_block_all_info_styles_return_non_empty_string(string $infoStyle): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module = new TopSurnamesModule(new ModuleService());
        $result = $module->getBlock($this->tree, 0, 'main', ['info_style' => $infoStyle]);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<', $result);
    }

    // --- AP13: ClippingsCartModule (CRAP 272 + 110) ---

    private function makeClippingsCartModule(): ClippingsCartModule
    {
        return new ClippingsCartModule(
            new GedcomExportService(
                Registry::container()->get(ResponseFactoryInterface::class),
                Registry::container()->get(StreamFactoryInterface::class),
            ),
            new LinkedRecordService(),
            new PhpService(),
        );
    }

    /**
     * AP13 — ClippingsCartModule::postAddIndividualAction gibt Redirect zurück.
     * option='record' fügt nur die Person selbst hinzu (CRAP 110).
     */
    public function test_clippings_cart_post_add_individual_record_only_gives_redirect(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = $this->makeClippingsCartModule();
        $request = $this->createRequest(
            method: 'POST',
            attributes: ['tree' => $this->tree],
            params: ['xref' => 'X1030', 'option' => 'record'],
        );

        $response = $module->postAddIndividualAction($request);

        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    /**
     * AP13 — ClippingsCartModule::postAddIndividualAction mit option='ancestors'.
     */
    public function test_clippings_cart_post_add_individual_ancestors_gives_redirect(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = $this->makeClippingsCartModule();
        $request = $this->createRequest(
            method: 'POST',
            attributes: ['tree' => $this->tree],
            params: ['xref' => 'X1030', 'option' => 'ancestors'],
        );

        $response = $module->postAddIndividualAction($request);

        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    /**
     * AP13 — ClippingsCartModule::postDownloadAction mit leerem Cart gibt Response zurück.
     * Triggert den Download-Pfad (CRAP 272); leerer Cart → leere GED-Datei.
     */
    public function test_clippings_cart_post_download_action_returns_response(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = $this->makeClippingsCartModule();
        $request = $this->createRequest(
            method: 'POST',
            attributes: ['tree' => $this->tree],
            params: [
                'filename'     => 'clippings',
                'format'       => 'gedcom',
                'privacy'      => 'none',
                'encoding'     => 'UTF-8',
                'line_endings' => 'LF',
            ],
        );

        $response = $module->postDownloadAction($request);

        $this->assertNotNull($response);
        $this->assertGreaterThanOrEqual(200, $response->getStatusCode());
    }
}
