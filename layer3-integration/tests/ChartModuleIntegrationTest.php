<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\AncestorsChartModule;
use Fisharebest\Webtrees\Module\CompactTreeChartModule;
use Fisharebest\Webtrees\Module\DescendancyChartModule;
use Fisharebest\Webtrees\Module\FamilyBookChartModule;
use Fisharebest\Webtrees\Module\FanChartModule;
use Fisharebest\Webtrees\Module\HourglassChartModule;
use Fisharebest\Webtrees\Module\LifespansChartModule;
use Fisharebest\Webtrees\Module\PedigreeChartModule;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;
use Fisharebest\Webtrees\Module\TimelineChartModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Services\RelationshipService;
use Fisharebest\Webtrees\Services\TreeService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Komponentenintegrationstest: Chart-Module mit MySQL.
 *
 * Testet alle Chart-Handler mit HTTP-Request → Response → 200 OK.
 *
 * @covers \Fisharebest\Webtrees\Module\AncestorsChartModule
 * @covers \Fisharebest\Webtrees\Module\CompactTreeChartModule
 * @covers \Fisharebest\Webtrees\Module\DescendancyChartModule
 * @covers \Fisharebest\Webtrees\Module\FanChartModule
 * @covers \Fisharebest\Webtrees\Module\HourglassChartModule
 * @covers \Fisharebest\Webtrees\Module\PedigreeChartModule
 * @covers \Fisharebest\Webtrees\Module\TimelineChartModule
 * @covers \Fisharebest\Webtrees\Module\LifespansChartModule
 * @covers \Fisharebest\Webtrees\Module\FamilyBookChartModule
 * @covers \Fisharebest\Webtrees\Module\RelationshipsChartModule
 * @see docs/testing-bigpicture.md S15, S16, S17, S18
 */
class ChartModuleIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    // --- AncestorsChartModule ---

    /**
     * @return array<string,array{style:string}>
     */
    public static function ancestorsChartStyles(): array
    {
        return [
            'tree'        => ['style' => 'tree'],
            'individuals' => ['style' => 'individuals'],
            'families'    => ['style' => 'families'],
        ];
    }

    #[DataProvider('ancestorsChartStyles')]
    public function test_ancestors_chart_returns_page(string $style): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new AncestorsChartModule(new ChartService());
        $request = $this->createRequest(attributes: [
            'tree'        => $this->tree,
            'xref'        => 'X1030',
            'style'       => $style,
            'generations' => 4,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    #[DataProvider('ancestorsChartStyles')]
    public function test_ancestors_chart_ajax_returns_content(string $style): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new AncestorsChartModule(new ChartService());
        $request = $this->createRequest(query: ['ajax' => '1'], attributes: [
            'tree'        => $this->tree,
            'xref'        => 'X1030',
            'style'       => $style,
            'generations' => 4,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody()->getContents());
    }

    public function test_ancestors_chart_title_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $individual = Registry::individualFactory()->make('X1030', $this->tree);
        $this->assertNotNull($individual);

        $module = new AncestorsChartModule(new ChartService());
        $title  = $module->chartTitle($individual);

        $this->assertNotEmpty($title);
    }

    // --- CompactTreeChartModule ---

    public function test_compact_tree_chart_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new CompactTreeChartModule(new ChartService());
        $request = $this->createRequest(attributes: [
            'tree' => $this->tree,
            'xref' => 'X1030',
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function test_compact_tree_chart_ajax_returns_content(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new CompactTreeChartModule(new ChartService());
        $request = $this->createRequest(query: ['ajax' => '1'], attributes: [
            'tree' => $this->tree,
            'xref' => 'X1030',
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody()->getContents());
    }

    // --- DescendancyChartModule ---

    /**
     * @return array<string,array{style:string}>
     */
    public static function descendancyChartStyles(): array
    {
        return [
            'tree'        => ['style' => 'tree'],
            'individuals' => ['style' => 'individuals'],
            'families'    => ['style' => 'families'],
        ];
    }

    #[DataProvider('descendancyChartStyles')]
    public function test_descendancy_chart_returns_page(string $style): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new DescendancyChartModule(new ChartService());
        $request = $this->createRequest(attributes: [
            'tree'        => $this->tree,
            'xref'        => 'X1030',
            'style'       => $style,
            'generations' => 3,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // --- FanChartModule ---

    public function test_fan_chart_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new FanChartModule(new ChartService());
        $request = $this->createRequest(attributes: [
            'tree'        => $this->tree,
            'xref'        => 'X1030',
            'style'       => 4,
            'generations' => 4,
            'width'       => 210,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // --- HourglassChartModule ---

    public function test_hourglass_chart_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new HourglassChartModule();
        $request = $this->createRequest(attributes: [
            'tree'        => $this->tree,
            'xref'        => 'X1030',
            'generations' => 3,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // --- PedigreeChartModule ---

    /**
     * @return array<string,array{style:string}>
     */
    public static function pedigreeChartStyles(): array
    {
        return [
            'left'  => ['style' => 'left'],
            'right' => ['style' => 'right'],
            'up'    => ['style' => 'up'],
            'down'  => ['style' => 'down'],
        ];
    }

    #[DataProvider('pedigreeChartStyles')]
    public function test_pedigree_chart_returns_page(string $style): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $module  = new PedigreeChartModule(new ChartService());
        $request = $this->createRequest(attributes: [
            'tree'        => $this->tree,
            'xref'        => 'X1030',
            'style'       => $style,
            'generations' => 4,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // --- AP 8-6: Fehlende Chart-Smoke-Tests (S18) ---

    /**
     * S18 — Timeline-Chart rendert ohne Fehler.
     */
    public function test_timeline_chart_renders_without_error(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new TimelineChartModule();
        $request = $this->createRequest(
            query: ['xrefs' => ['X1030']],
            attributes: [
                'tree'  => $this->tree,
                'user'  => $admin,
                'scale' => 10,
            ],
        );

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * S18 — Lifespans-Chart rendert ohne Fehler.
     */
    public function test_lifespan_chart_renders_without_error(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new LifespansChartModule();
        $request = $this->createRequest(
            query: ['xrefs' => ['X1030']],
            attributes: [
                'tree' => $this->tree,
                'user' => $admin,
            ],
        );

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * S18 — FamilyBook-Chart rendert ohne Fehler.
     */
    public function test_family_book_chart_renders_without_error(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new FamilyBookChartModule();
        $request = $this->createRequest(attributes: [
            'tree'        => $this->tree,
            'user'        => $admin,
            'xref'        => 'X1030',
            'book_size'   => 2,
            'generations' => 2,
            'spouses'     => false,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * S18 — Relationships-Chart rendert ohne Fehler.
     */
    public function test_relationships_chart_renders_without_error(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new RelationshipsChartModule(
            new RelationshipService(),
            $this->treeService,
        );
        $request = $this->createRequest(attributes: [
            'tree'      => $this->tree,
            'user'      => $admin,
            'xref'      => 'X1030',
            'xref2'     => '',
            'ancestors' => 1,
            'recursion' => 1,
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * S18/S20 — Branches-Liste rendert ohne Fehler (R6-Klärung: ist ListModule, nicht Chart).
     * Wird hier als Chart-Smoke mitgetestet, da im Plan unter S18 aufgelistet.
     */
    public function test_branches_list_renders_without_error(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new \Fisharebest\Webtrees\Module\BranchesListModule(
            new \Fisharebest\Webtrees\Services\ModuleService(),
        );
        $request = $this->createRequest(attributes: [
            'tree'    => $this->tree,
            'user'    => $admin,
            'surname' => 'Windsor',
        ]);

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * S18/S20 — Branches ajax-Branch: getDescendantsHtml (private) wird intern aufgerufen.
     *
     * handle() mit ajax=1 betritt den AJAX-Branch und ruft intern
     * getPatriarchsHtml() → getDescendantsHtml() auf.
     */
    public function test_branches_list_ajax_calls_get_descendants_html(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $module  = new \Fisharebest\Webtrees\Module\BranchesListModule(
            new \Fisharebest\Webtrees\Services\ModuleService(),
        );
        $request = $this->createRequest(
            attributes: [
                'tree'    => $this->tree,
                'user'    => $admin,
                'surname' => 'Windsor',
            ],
            query: ['ajax' => '1'],
        );

        $response = $module->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
