<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\StatisticsChartModule;

/**
 * Komponentenintegrationstest: StatisticsChartModule mit MySQL.
 *
 * Testet postCustomChartAction() — höchster CRAP-Score im Layer-3-Bereich (14.042, cx=118).
 * Container auto-wired: Statistics::class via Reflection (ModuleService + Tree + UserService).
 * Keine Feature-Matrix-ID — technischer Risikotest.
 *
 * @covers \Fisharebest\Webtrees\Module\StatisticsChartModule
 */
class StatisticsChartIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private StatisticsChartModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = new StatisticsChartModule();
    }

    /**
     * postCustomChartAction mit X_AXIS_BIRTH_MONTH: gibt 200 OK zurück.
     */
    public function test_post_custom_chart_action_birth_month_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'x-as' => StatisticsChartModule::X_AXIS_BIRTH_MONTH,
                'y-as' => StatisticsChartModule::Y_AXIS_NUMBERS,
                'z-as' => StatisticsChartModule::Z_AXIS_ALL,
            ],
        );

        $response = $this->module->postCustomChartAction($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * postCustomChartAction mit X_AXIS_DEATH_MONTH: gibt 200 OK zurück.
     */
    public function test_post_custom_chart_action_death_month_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'x-as' => StatisticsChartModule::X_AXIS_DEATH_MONTH,
                'y-as' => StatisticsChartModule::Y_AXIS_NUMBERS,
                'z-as' => StatisticsChartModule::Z_AXIS_ALL,
            ],
        );

        $response = $this->module->postCustomChartAction($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * postCustomChartAction mit X_AXIS_MARRIAGE_MONTH: gibt 200 OK zurück.
     */
    public function test_post_custom_chart_action_marriage_month_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'x-as' => StatisticsChartModule::X_AXIS_MARRIAGE_MONTH,
                'y-as' => StatisticsChartModule::Y_AXIS_NUMBERS,
                'z-as' => StatisticsChartModule::Z_AXIS_ALL,
            ],
        );

        $response = $this->module->postCustomChartAction($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
