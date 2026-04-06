<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesAllAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesAllPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesAnalyticsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesBlocksPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesChartsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMenusPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesReportsPage;
use Fisharebest\Webtrees\Services\ModuleService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Komponentenintegrationstest: Modul-Konfiguration (Admin) — A05.
 *
 * Tests:
 * - ModulesAllPage GET → 200
 * - ModulesAllAction POST (keine Änderungen) → 302
 * - DataProvider: Weitere Module*Page GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesAllPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesAllAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesAnalyticsPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesBlocksPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesChartsPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMenusPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesReportsPage
 * @see docs/testquality_improve_A05.md
 */
class ModuleConfigIntegrationTest extends MysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
    }

    /**
     * EP1: ModulesAllPage GET → 200.
     */
    public function test_modules_all_page_returns_200(): void
    {
        $handler = new ModulesAllPage(new ModuleService());

        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: ModulesAllAction POST → 302.
     *
     * Alle Module erhalten ihren aktuellen Status übergeben → keine DB-Änderung → Redirect.
     */
    public function test_modules_all_action_returns_302(): void
    {
        $moduleService = new ModuleService();
        $modules       = $moduleService->all(true);

        // Aktuellen Status jedes Moduls senden → keine Änderungen im DB
        $params = [];
        foreach ($modules as $module) {
            $params['status-' . $module->name()] = $module->isEnabled() ? '1' : '0';
        }

        $handler = new ModulesAllAction($moduleService);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: $params,
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function moduleComponentPageHandlers(): array
    {
        return [
            'analytics' => [ModulesAnalyticsPage::class],
            'blocks'    => [ModulesBlocksPage::class],
            'charts'    => [ModulesChartsPage::class],
            'menus'     => [ModulesMenusPage::class],
            'reports'   => [ModulesReportsPage::class],
        ];
    }

    /**
     * EP4–EP8: Weitere Module*Page-Handler GET → 200 (DataProvider-Smoke).
     *
     * @param class-string $handlerClass
     */
    #[DataProvider('moduleComponentPageHandlers')]
    public function test_module_component_page_returns_200(string $handlerClass): void
    {
        $handler = new $handlerClass(new ModuleService(), $this->treeService);

        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
