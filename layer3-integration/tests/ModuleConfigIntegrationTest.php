<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesAllAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesAllPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesAnalyticsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesAnalyticsPage;
use Fisharebest\Webtrees\Module\ModuleAnalyticsInterface;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleDataFixInterface;
use Fisharebest\Webtrees\Module\ModuleFooterInterface;
use Fisharebest\Webtrees\Module\ModuleHistoricEventsInterface;
use Fisharebest\Webtrees\Module\ModuleLanguageInterface;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleMapAutocompleteInterface;
use Fisharebest\Webtrees\Module\ModuleMapGeoLocationInterface;
use Fisharebest\Webtrees\Module\ModuleMapLinkInterface;
use Fisharebest\Webtrees\Module\ModuleMapProviderInterface;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleReportInterface;
use Fisharebest\Webtrees\Module\ModuleShareInterface;
use Fisharebest\Webtrees\Module\ModuleSidebarInterface;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesBlocksAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesBlocksPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesChartsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesChartsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesDataFixesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesDataFixesPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesFootersAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesFootersPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesHistoricEventsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesHistoricEventsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesLanguagesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesLanguagesPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesListsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesListsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapAutocompleteAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapAutocompletePage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapGeoLocationsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapGeoLocationsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapLinksAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapLinksPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapProvidersAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapProvidersPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMenusAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMenusPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesReportsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesReportsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesSharesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesSharesPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesSidebarsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesSidebarsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesTabsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesTabsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesThemesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesThemesPage;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Illuminate\Support\Collection;
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
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesBlocksAction
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

    /**
     * Edge-Case: ModulesAllAction mit leerer Modul-Collection → 302.
     *
     * Wenn ModuleService::all(true) eine leere Collection liefert (z. B. in
     * Testumgebungen ohne registrierte Module), wird die foreach-Schleife
     * vollständig übersprungen, und der Handler redirected unmittelbar zur
     * ModulesAllPage. Verifiziert die Mock-basierte Interaktion und stellt
     * sicher, dass `all(true)` mit dem „include disabled" Flag genau einmal
     * aufgerufen wird.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesAllActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_all_action_returns_302_with_empty_module_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('all')
            ->with(true)
            ->willReturn(new Collection());

        $handler  = new ModulesAllAction($module_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesAllPage mit leerer Modul-Collection und leerer Deleted-Liste → 200.
     *
     * Wenn ModuleService::all(true) und ModuleService::deletedModules() beide
     * leere Collections liefern, rendert der Handler die Übersichtsseite trotzdem
     * erfolgreich. Verifiziert die Mock-basierte Interaktion und stellt sicher,
     * dass `all(true)` mit dem „include disabled" Flag und `deletedModules()`
     * jeweils genau einmal aufgerufen werden.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesAllPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_all_page_returns_200_with_empty_module_list(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('all')
            ->with(true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('deletedModules')
            ->willReturn(new Collection());

        $handler  = new ModulesAllPage($module_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesAnalyticsAction mit leerer Modul-Collection → 302.
     *
     * Wenn ModuleService::findByInterface(ModuleAnalyticsInterface::class) eine
     * leere Collection liefert, durchläuft updateStatus() keine Iteration und
     * der Handler leitet direkt zur ModulesAnalyticsPage weiter. Verifiziert die
     * Mock-Interaktion: findByInterface() wird genau einmal aufgerufen, der
     * TreeService bleibt unberührt (ModulesAnalyticsAction nutzt nur updateStatus()
     * und damit lediglich den ModuleService).
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesAnalyticsAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesAnalyticsActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_analytics_action_returns_302_with_empty_module_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesAnalyticsAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesAnalyticsPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleAnalyticsInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich. Verifiziert die Mock-Interaktion über die
     * geerbte listComponents()-Pipeline: findByInterface() wird mit den Flags
     * (true, true) genau einmal aufgerufen, componentsWithAccess() und
     * componentsWithOrder() jeweils genau einmal, TreeService::all() genau einmal.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesAnalyticsPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_analytics_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleAnalyticsInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesAnalyticsPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesBlocksAction mit leeren Modul- und Tree-Collections → 302.
     *
     * ModulesBlocksAction (via AbstractModuleComponentAction) ruft sowohl
     * updateStatus() als auch updateAccessLevel() auf — beide nutzen
     * ModuleService::findByInterface(ModuleBlockInterface::class). Liefert der
     * ModuleService eine leere Collection, durchlaufen beide Update-Schritte
     * keine Iteration und der Handler leitet zur ModulesBlocksPage weiter.
     * Verifiziert die Mock-Interaktion: findByInterface() wird genau zweimal
     * aufgerufen, TreeService::all() (von updateAccessLevel benötigt) genau
     * einmal.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesBlocksActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_blocks_action_returns_302_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus + updateAccessLevel = 2 Aufrufe an findByInterface
        $module_service->expects(self::exactly(2))
            ->method('findByInterface')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesBlocksAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesBlocksPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleBlockInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich. Verifiziert die Mock-Interaktion über die
     * geerbte listComponents()-Pipeline: findByInterface() wird mit den Flags
     * (true, true) genau einmal aufgerufen, componentsWithAccess() und
     * componentsWithOrder() jeweils genau einmal, TreeService::all() genau einmal.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesBlocksPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_blocks_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleBlockInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesBlocksPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesChartsAction mit leeren Modul- und Tree-Collections → 302.
     *
     * ModulesChartsAction (via AbstractModuleComponentAction) ruft sowohl
     * updateStatus() als auch updateAccessLevel() auf — beide nutzen
     * ModuleService::findByInterface(ModuleChartInterface::class). Liefert der
     * ModuleService eine leere Collection, durchlaufen beide Update-Schritte
     * keine Iteration und der Handler leitet zur ModulesChartsPage weiter.
     * Verifiziert die Mock-Interaktion: findByInterface() wird genau zweimal
     * aufgerufen, TreeService::all() (von updateAccessLevel benötigt) genau
     * einmal.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesChartsAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesChartsActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_charts_action_returns_302_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus + updateAccessLevel = 2 Aufrufe an findByInterface
        $module_service->expects(self::exactly(2))
            ->method('findByInterface')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesChartsAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesChartsPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleChartInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich. Verifiziert die Mock-Interaktion über die
     * geerbte listComponents()-Pipeline.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesChartsPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_charts_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleChartInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesChartsPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesFootersAction mit leerer Modul-Collection → 302.
     *
     * ModulesFootersAction ruft updateStatus() und updateOrder() auf — beide
     * nutzen ModuleService::findByInterface(ModuleFooterInterface::class). Bei
     * leerer Collection läuft keine Iteration und der Handler leitet direkt zur
     * ModulesFootersPage weiter. Verifiziert die Mock-Interaktion: findByInterface()
     * wird genau zweimal aufgerufen, der TreeService bleibt unberührt
     * (updateOrder benötigt keinen Baum-Kontext).
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesFootersAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesFootersActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_footers_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus + updateOrder = 2 Aufrufe an findByInterface
        $module_service->expects(self::exactly(2))
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesFootersAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST, params: ['order' => []]);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesFootersPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleFooterInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich. Verifiziert die Mock-Interaktion.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesFootersPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesFootersPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_footers_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleFooterInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesFootersPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesHistoricEventsAction mit leerer Modul-Collection → 302.
     *
     * ModulesHistoricEventsAction ruft ausschließlich updateStatus() auf — also
     * genau einen findByInterface(ModuleHistoricEventsInterface::class)-Aufruf.
     * Bei leerer Collection läuft keine Iteration und der Handler leitet direkt
     * zur ModulesHistoricEventsPage weiter. Verifiziert die Mock-Interaktion:
     * findByInterface() wird genau einmal aufgerufen, der TreeService bleibt
     * unberührt.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesHistoricEventsAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesHistoricEventsActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_historic_events_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus only = 1 Aufruf an findByInterface
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesHistoricEventsAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesHistoricEventsPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleHistoricEventsInterface, true, true)
     * sowie componentsWithAccess()/componentsWithOrder() leere Collections liefern
     * und der TreeService::all() ebenfalls leer ist, rendert
     * AbstractModuleComponentPage die Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesHistoricEventsPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesHistoricEventsPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_historic_events_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleHistoricEventsInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesHistoricEventsPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesLanguagesAction mit leerer Modul-Collection → 302.
     *
     * ModulesLanguagesAction ruft ausschließlich updateStatus() auf — also
     * genau einen findByInterface(ModuleLanguageInterface::class)-Aufruf. Bei
     * leerer Collection läuft keine Iteration und der Handler leitet direkt
     * zur ModulesLanguagesPage weiter. Verifiziert die Mock-Interaktion:
     * findByInterface() wird genau einmal aufgerufen, der TreeService bleibt
     * unberührt.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesLanguagesAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesLanguagesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_languages_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus only = 1 Aufruf an findByInterface
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesLanguagesAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesLanguagesPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleLanguageInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesLanguagesPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesLanguagesPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_languages_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleLanguageInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesLanguagesPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesListsAction mit leeren Modul- und Tree-Collections → 302.
     *
     * ModulesListsAction (via AbstractModuleComponentAction) ruft sowohl
     * updateStatus() als auch updateAccessLevel() auf — beide nutzen
     * ModuleService::findByInterface(ModuleListInterface::class). Liefert der
     * ModuleService eine leere Collection, durchlaufen beide Update-Schritte
     * keine Iteration und der Handler leitet zur ModulesListsPage weiter.
     * Verifiziert die Mock-Interaktion: findByInterface() wird genau zweimal
     * aufgerufen, TreeService::all() genau einmal.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesListsAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesListsActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_lists_action_returns_302_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus + updateAccessLevel = 2 Aufrufe an findByInterface
        $module_service->expects(self::exactly(2))
            ->method('findByInterface')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesListsAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesListsPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleListInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesListsPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesListsPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_lists_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleListInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesListsPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesDataFixesAction mit leerer Modul-Collection → 302.
     *
     * ModulesDataFixesAction ruft ausschließlich updateStatus() auf — also
     * genau einen findByInterface(ModuleDataFixInterface::class)-Aufruf. Bei
     * leerer Collection läuft keine Iteration und der Handler leitet direkt
     * zur ModulesDataFixesPage weiter. Verifiziert die Mock-Interaktion:
     * findByInterface() wird genau einmal aufgerufen, der TreeService bleibt
     * unberührt.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesDataFixesAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesDataFixesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_data_fixes_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus only = 1 Aufruf an findByInterface
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesDataFixesAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesDataFixesPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleDataFixInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich. Verifiziert die Mock-Interaktion über die
     * geerbte listComponents()-Pipeline: findByInterface() wird mit den Flags
     * (true, true) genau einmal aufgerufen, componentsWithAccess() und
     * componentsWithOrder() jeweils genau einmal, TreeService::all() genau einmal.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesDataFixesPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesDataFixesPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_data_fixes_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleDataFixInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesDataFixesPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMapAutocompleteAction mit leerer Modul-Collection → 302.
     *
     * ModulesMapAutocompleteAction ruft ausschließlich updateStatus() auf — also
     * genau einen findByInterface(ModuleMapAutocompleteInterface::class)-Aufruf.
     * Bei leerer Collection läuft keine Iteration und der Handler leitet direkt
     * zur ModulesMapAutocompletePage weiter. Verifiziert die Mock-Interaktion:
     * findByInterface() wird genau einmal aufgerufen, der TreeService bleibt
     * unberührt.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapAutocompleteAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMapAutocompleteActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_map_autocomplete_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus only = 1 Aufruf an findByInterface
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesMapAutocompleteAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMapAutocompletePage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleMapAutocompleteInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapAutocompletePage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMapAutocompletePageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_map_autocomplete_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleMapAutocompleteInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesMapAutocompletePage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMapGeoLocationsAction mit leerer Modul-Collection → 302.
     *
     * ModulesMapGeoLocationsAction ruft ausschließlich updateStatus() auf — also
     * genau einen findByInterface(ModuleMapGeoLocationInterface::class)-Aufruf.
     * Bei leerer Collection läuft keine Iteration und der Handler leitet direkt
     * zur ModulesMapGeoLocationsPage weiter. Verifiziert die Mock-Interaktion:
     * findByInterface() wird genau einmal aufgerufen, der TreeService bleibt
     * unberührt.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapGeoLocationsAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMapGeoLocationsActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_map_geo_locations_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus only = 1 Aufruf an findByInterface
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesMapGeoLocationsAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMapGeoLocationsPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleMapGeoLocationInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapGeoLocationsPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMapGeoLocationsPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_map_geo_locations_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleMapGeoLocationInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesMapGeoLocationsPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMapLinksAction mit leerer Modul-Collection → 302.
     *
     * ModulesMapLinksAction ruft ausschließlich updateStatus() auf — also
     * genau einen findByInterface(ModuleMapLinkInterface::class)-Aufruf. Bei
     * leerer Collection läuft keine Iteration und der Handler leitet direkt
     * zur ModulesMapLinksPage weiter. Verifiziert die Mock-Interaktion:
     * findByInterface() wird genau einmal aufgerufen, der TreeService bleibt
     * unberührt.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapLinksAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMapLinksActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_map_links_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus only = 1 Aufruf an findByInterface
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesMapLinksAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMapLinksPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleMapLinkInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapLinksPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMapLinksPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_map_links_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleMapLinkInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesMapLinksPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMapProvidersAction mit leerer Modul-Collection → 302.
     *
     * ModulesMapProvidersAction ruft ausschließlich updateStatus() auf — also
     * genau einen findByInterface(ModuleMapProviderInterface::class)-Aufruf. Bei
     * leerer Collection läuft keine Iteration und der Handler leitet direkt
     * zur ModulesMapProvidersPage weiter. Verifiziert die Mock-Interaktion:
     * findByInterface() wird genau einmal aufgerufen, der TreeService bleibt
     * unberührt.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapProvidersAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMapProvidersActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_map_providers_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus only = 1 Aufruf an findByInterface
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesMapProvidersAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMapProvidersPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleMapProviderInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMapProvidersPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMapProvidersPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_map_providers_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleMapProviderInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesMapProvidersPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMenusAction mit leerer Modul-Collection → 302.
     *
     * ModulesMenusAction ruft updateStatus(), updateOrder() und updateAccessLevel()
     * auf — also drei findByInterface(ModuleMenuInterface::class)-Aufrufe. Bei
     * leerer Collection durchlaufen alle drei Update-Schritte keine Iteration und
     * der Handler leitet direkt zur ModulesMenusPage weiter. Verifiziert die
     * Mock-Interaktion: findByInterface() wird genau dreimal aufgerufen,
     * TreeService::all() (von updateAccessLevel benötigt) genau einmal.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesMenusAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMenusActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_menus_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus + updateOrder + updateAccessLevel = 3 Aufrufe an findByInterface
        $module_service->expects(self::exactly(3))
            ->method('findByInterface')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesMenusAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST, params: ['order' => []]);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesMenusPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleMenuInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesMenusPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_menus_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleMenuInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesMenusPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesReportsAction mit leerer Modul-Collection → 302.
     *
     * ModulesReportsAction (via AbstractModuleComponentAction) ruft sowohl
     * updateStatus() als auch updateAccessLevel() auf — beide nutzen
     * ModuleService::findByInterface(ModuleReportInterface::class). Liefert der
     * ModuleService eine leere Collection, durchlaufen beide Update-Schritte
     * keine Iteration und der Handler leitet zur ModulesReportsPage weiter.
     * Verifiziert die Mock-Interaktion: findByInterface() wird genau zweimal
     * aufgerufen, TreeService::all() (von updateAccessLevel benötigt) genau
     * einmal.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesReportsAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesReportsActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_reports_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus + updateAccessLevel = 2 Aufrufe an findByInterface
        $module_service->expects(self::exactly(2))
            ->method('findByInterface')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesReportsAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesReportsPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleReportInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesReportsPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_reports_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleReportInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesReportsPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesSharesAction mit leerer Modul-Collection → 302.
     *
     * ModulesSharesAction ruft ausschließlich updateStatus() auf — also
     * genau einen findByInterface(ModuleShareInterface::class)-Aufruf. Bei
     * leerer Collection läuft keine Iteration und der Handler leitet direkt
     * zur ModulesSharesPage weiter. Verifiziert die Mock-Interaktion:
     * findByInterface() wird genau einmal aufgerufen, der TreeService bleibt
     * unberührt.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesSharesAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesSharesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_shares_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus only = 1 Aufruf an findByInterface
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesSharesAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesSharesPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleShareInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich. Verifiziert die Mock-Interaktion über die
     * geerbte listComponents()-Pipeline: findByInterface() wird mit den Flags
     * (true, true) genau einmal aufgerufen, componentsWithAccess() und
     * componentsWithOrder() jeweils genau einmal, TreeService::all() genau einmal.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesSharesPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesSharesPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_shares_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleShareInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesSharesPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesSidebarsAction mit leerer Modul-Collection → 302.
     *
     * ModulesSidebarsAction ruft updateStatus(), updateOrder() und updateAccessLevel()
     * auf — also drei findByInterface(ModuleSidebarInterface::class)-Aufrufe. Bei
     * leerer Collection durchlaufen alle drei Update-Schritte keine Iteration und
     * der Handler leitet direkt zur ModulesSidebarsPage weiter. Verifiziert die
     * Mock-Interaktion: findByInterface() wird genau dreimal aufgerufen,
     * TreeService::all() (von updateAccessLevel benötigt) genau einmal.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesSidebarsAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesSidebarsActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_sidebars_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus + updateOrder + updateAccessLevel = 3 Aufrufe an findByInterface
        $module_service->expects(self::exactly(3))
            ->method('findByInterface')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesSidebarsAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST, params: ['order' => []]);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesSidebarsPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleSidebarInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesSidebarsPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesSidebarsPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_sidebars_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleSidebarInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesSidebarsPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesTabsAction mit leerer Modul-Collection → 302.
     *
     * ModulesTabsAction ruft updateStatus(), updateOrder() und updateAccessLevel()
     * auf — also drei findByInterface(ModuleTabInterface::class)-Aufrufe. Bei
     * leerer Collection durchlaufen alle drei Update-Schritte keine Iteration und
     * der Handler leitet direkt zur ModulesTabsPage weiter. Verifiziert die
     * Mock-Interaktion: findByInterface() wird genau dreimal aufgerufen,
     * TreeService::all() (von updateAccessLevel benötigt) genau einmal.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesTabsAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesTabsActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_tabs_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus + updateOrder + updateAccessLevel = 3 Aufrufe an findByInterface
        $module_service->expects(self::exactly(3))
            ->method('findByInterface')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesTabsAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST, params: ['order' => []]);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesTabsPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleTabInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesTabsPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesTabsPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_tabs_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleTabInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesTabsPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesThemesAction mit leerer Modul-Collection → 302.
     *
     * ModulesThemesAction ruft ausschließlich updateStatus() auf — also genau
     * einen findByInterface(ModuleThemeInterface::class)-Aufruf. Bei leerer
     * Collection läuft keine Iteration und der Handler leitet direkt zur
     * ModulesThemesPage weiter. Verifiziert die Mock-Interaktion:
     * findByInterface() wird genau einmal aufgerufen, der TreeService bleibt
     * unberührt.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesThemesAction
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesThemesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_themes_action_returns_302_with_empty_collection(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        // updateStatus only = 1 Aufruf an findByInterface
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->willReturn(new Collection());

        // Wird vom Handler nicht aufgerufen → Stub ohne Expectations reicht.
        $tree_service = self::createStub(TreeService::class);

        $handler  = new ModulesThemesAction($module_service, $tree_service);
        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Edge-Case: ModulesThemesPage mit leeren Modul- und Tree-Collections → 200.
     *
     * Wenn ModuleService::findByInterface(ModuleThemeInterface, true, true) sowie
     * componentsWithAccess()/componentsWithOrder() leere Collections liefern und der
     * TreeService::all() ebenfalls leer ist, rendert AbstractModuleComponentPage die
     * Übersichtsseite trotzdem erfolgreich.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModulesThemesPage
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/ModulesThemesPageTest.php
     * @group ported-l2-doubles
     */
    public function test_modules_themes_page_returns_200_with_empty_collections(): void
    {
        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(ModuleThemeInterface::class, true, true)
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithAccess')
            ->willReturn(new Collection());
        $module_service->expects(self::once())
            ->method('componentsWithOrder')
            ->willReturn(new Collection());

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $handler  = new ModulesThemesPage($module_service, $tree_service);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
