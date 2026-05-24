<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpBadRequestException;
use Fisharebest\Webtrees\Http\RequestHandlers\CalendarAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CalendarEvents;
use Fisharebest\Webtrees\Http\RequestHandlers\CalendarPage;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\CalendarService;
use Fisharebest\Webtrees\Services\RelationshipService;
use Fisharebest\Webtrees\Services\TreeService;

/**
 * Komponentenintegrationstest: CalendarService und RelationshipsChartModule mit MySQL.
 *
 * Keine Feature-Matrix-IDs — technische Risikotests:
 * - CalendarService::getAnniversaryEvents (CRAP 870, cx=29)
 * - RelationshipsChartModule::chart (CRAP 756, cx=27)
 *
 * chart() wird nicht über handle() erreicht (handle() benötigt zwei XREFs),
 * sondern direkt mit Individual-Objekten aus demo.ged aufgerufen.
 *
 * @covers \Fisharebest\Webtrees\Services\CalendarService
 * @covers \Fisharebest\Webtrees\Module\RelationshipsChartModule
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CalendarEvents
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CalendarAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CalendarPage
 */
class CalendarChartIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private CalendarService $calendar_service;
    private RelationshipsChartModule $relationships_module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calendar_service     = new CalendarService();
        $this->relationships_module = new RelationshipsChartModule(
            new RelationshipService(),
            Registry::container()->get(TreeService::class),
        );
    }

    /**
     * CalendarService::getAnniversaryEvents gibt Array zurück für bekannten Julian Day.
     */
    public function test_get_anniversary_events_returns_array(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Julian Day: ca. 1. Januar 1960 ≈ JD 2436935
        $events = $this->calendar_service->getAnniversaryEvents(2436935, 'BIRT DEAT MARR', $this->tree);

        $this->assertIsArray($events);
    }

    /**
     * CalendarService::getAnniversaryEvents gibt Array zurück auch wenn keine Treffer.
     */
    public function test_get_anniversary_events_returns_empty_array_for_no_matches(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Sehr früher Julian Day — keine demo.ged-Ereignisse erwartet
        $events = $this->calendar_service->getAnniversaryEvents(1000000, 'BIRT', $this->tree);

        $this->assertIsArray($events);
    }

    /**
     * CalendarEvents::handle mit view=day gibt 200 OK zurück.
     */
    public function test_calendar_events_handle_day_view_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler = new CalendarEvents($this->calendar_service);
        $request = $this->createRequest(
            query:      ['cal' => '', 'day' => '1', 'month' => 'JAN', 'year' => '1960', 'filterev' => 'BIRT DEAT MARR', 'filterof' => 'all', 'filtersx' => ''],
            attributes: ['tree' => $this->tree, 'view' => 'day'],
        );

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CalendarEvents::handle mit view=month gibt 200 OK zurück.
     */
    public function test_calendar_events_handle_month_view_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler = new CalendarEvents($this->calendar_service);
        $request = $this->createRequest(
            query:      ['cal' => '', 'day' => '1', 'month' => 'JAN', 'year' => '1960', 'filterev' => 'BIRT', 'filterof' => 'all', 'filtersx' => ''],
            attributes: ['tree' => $this->tree, 'view' => 'month'],
        );

        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CalendarService::getCalendarEvents gibt Array zurück.
     */
    public function test_get_calendar_events_returns_array(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $events = $this->calendar_service->getCalendarEvents(2436935, 2436935, 'BIRT DEAT MARR', $this->tree);

        $this->assertIsArray($events);
    }

    /**
     * CalendarService::getEventsList gibt Collection zurück.
     */
    public function test_get_events_list_returns_collection(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $events = $this->calendar_service->getEventsList(2436935, 2436935, 'BIRT DEAT MARR', false, 'anniv', $this->tree);

        $this->assertNotNull($events);
    }

    /**
     * RelationshipsChartModule::chart gibt Response zurück für Elizabeth II → Sohn.
     * Deckt chart() direkt ab (nicht nur handle()).
     */
    public function test_relationships_chart_chart_method_returns_response(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $individual1 = Registry::individualFactory()->make('X1030', $this->tree);
        $individual2 = Registry::individualFactory()->make('X1052', $this->tree);

        $this->assertNotNull($individual1);
        $this->assertNotNull($individual2);

        $response = $this->relationships_module->chart($individual1, $individual2, 1, 1);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CalendarAction::handle leitet POST mit view=month auf CalendarPage um.
     *
     * Ergaenzt den day-View-Test um den month-View-Pfad der isInArray-Allowlist
     * (view in {day, month, year}) und sichert die Routen-Erzeugung fuer einen
     * zweiten erlaubten Allowlist-Wert.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CalendarActionTest.php
     * @group ported-l2-doubles
     */
    public function test_calendar_action_handle_month_view_redirects_to_calendar_page(): void
    {
        // Arrange.
        $this->tree = $this->treeService->create('cal-action-month', 'Calendar Action Month');
        $this->createAndLoginAdmin();

        $handler = new CalendarAction();
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'cal'      => '@#DGREGORIAN@',
                'day'      => 1,
                'month'    => 'JAN',
                'year'     => 2026,
                'filterev' => 'BIRT DEAT MARR',
                'filterof' => 'all',
                'filtersx' => '',
            ],
            attributes: ['tree' => $this->tree, 'view' => 'month'],
        );

        // Act.
        $response = $handler->handle($request);

        // Assert: 302 mit Ziel-Route calendar/month.
        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('calendar%2Fmonth', $response->getHeaderLine('location'));
        $this->assertStringContainsString('JAN', $response->getHeaderLine('location'));
        $this->assertStringContainsString('2026', $response->getHeaderLine('location'));
    }

    /**
     * CalendarAction::handle leitet POST mit Datumsparametern auf CalendarPage um.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CalendarActionTest.php
     * @group ported-l2-doubles
     */
    public function test_calendar_action_handle_redirects_to_calendar_page(): void
    {
        // Arrange.
        $this->tree = $this->treeService->create('cal-action', 'Calendar Action');
        $this->createAndLoginAdmin();

        $handler = new CalendarAction();
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'cal'      => '@#DGREGORIAN@',
                'day'      => 15,
                'month'    => 'JUN',
                'year'     => 2026,
                'filterev' => 'BIRT',
                'filterof' => 'all',
                'filtersx' => 'M',
            ],
            attributes: ['tree' => $this->tree, 'view' => 'day'],
        );

        // Act.
        $response = $handler->handle($request);

        // Assert.
        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('calendar%2Fday', $response->getHeaderLine('location'));
        $this->assertStringContainsString('JUN', $response->getHeaderLine('location'));
    }

    /**
     * CalendarPage::handle wirft HttpBadRequestException bei view ausserhalb der Allowlist.
     *
     * Ersetzt vormaligen class_exists-Smoke (L3SP-006). Validator::attributes()->isInArray(['day','month','year'])
     * mappt ungueltige Werte auf null; ->string('view') wirft daraufhin HttpBadRequestException
     * ("parameter missing"). Sichert die Validator-Allowlist als Verhaltens-Property ab.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CalendarPageTest.php
     * @group ported-l2-doubles
     */
    public function test_calendar_page_handle_invalid_view_throws_bad_request(): void
    {
        // Arrange.
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('cal-page-invalid', 'Calendar Page Invalid View');

        $handler = new CalendarPage($this->calendar_service);
        $request = $this->createRequest(
            query:      ['cal' => '@#DGREGORIAN@', 'day' => '1', 'month' => 'JAN', 'year' => '2000'],
            attributes: ['tree' => $this->tree, 'view' => 'decade'],
        );

        // Assert + Act.
        $this->expectException(HttpBadRequestException::class);
        $handler->handle($request);
    }

    /**
     * CalendarPage::handle mit view=day rendert mit STATUS_OK.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CalendarPageTest.php
     * @group ported-l2-doubles
     */
    public function test_calendar_page_handle_day_view_returns_ok(): void
    {
        // Arrange.
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('cal-page-day', 'Calendar Page Day');

        $handler = new CalendarPage($this->calendar_service);
        $request = $this->createRequest(
            query:      ['cal' => '@#DGREGORIAN@', 'day' => '1', 'month' => 'JAN', 'year' => '2000'],
            attributes: ['tree' => $this->tree, 'view' => 'day'],
        );

        // Act.
        $response = $handler->handle($request);

        // Assert.
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertNotEmpty((string) $response->getBody());
    }

    /**
     * CalendarPage::handle mit view=month rendert mit STATUS_OK.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CalendarPageTest.php
     * @group ported-l2-doubles
     */
    public function test_calendar_page_handle_month_view_returns_ok(): void
    {
        // Arrange.
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('cal-page-month', 'Calendar Page Month');

        $handler = new CalendarPage($this->calendar_service);
        $request = $this->createRequest(
            query:      ['cal' => '@#DGREGORIAN@', 'month' => 'JAN', 'year' => '2000'],
            attributes: ['tree' => $this->tree, 'view' => 'month'],
        );

        // Act.
        $response = $handler->handle($request);

        // Assert.
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CalendarPage::handle mit view=year rendert mit STATUS_OK.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CalendarPageTest.php
     * @group ported-l2-doubles
     */
    public function test_calendar_page_handle_year_view_returns_ok(): void
    {
        // Arrange.
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('cal-page-year', 'Calendar Page Year');

        $handler = new CalendarPage($this->calendar_service);
        $request = $this->createRequest(
            query:      ['cal' => '@#DGREGORIAN@', 'year' => '2000'],
            attributes: ['tree' => $this->tree, 'view' => 'year'],
        );

        // Act.
        $response = $handler->handle($request);

        // Assert.
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CalendarPage::handle ohne Datumsparameter waehlt Defaults und rendert mit STATUS_OK.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CalendarPageTest.php
     * @group ported-l2-doubles
     */
    public function test_calendar_page_handle_default_date_returns_ok(): void
    {
        // Arrange.
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('cal-page-default', 'Calendar Page Default');

        $handler = new CalendarPage($this->calendar_service);
        $request = $this->createRequest(
            attributes: ['tree' => $this->tree, 'view' => 'day'],
        );

        // Act.
        $response = $handler->handle($request);

        // Assert.
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
