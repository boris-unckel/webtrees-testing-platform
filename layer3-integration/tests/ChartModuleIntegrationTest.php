<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\AncestorsChartModule;
use Fisharebest\Webtrees\Module\CompactTreeChartModule;
use Fisharebest\Webtrees\Module\DescendancyChartModule;
use Fisharebest\Webtrees\Module\FanChartModule;
use Fisharebest\Webtrees\Module\HourglassChartModule;
use Fisharebest\Webtrees\Module\PedigreeChartModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ChartService;
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
 * @see docs/testing-bigpicture-prompt.md S15, S16, S17
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
}
