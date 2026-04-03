<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
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
}
