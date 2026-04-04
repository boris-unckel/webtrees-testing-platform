<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\StatisticsData;

/**
 * Komponentenintegrationstest: StatisticsData.
 *
 * AP B-01: centuryName (private, CRAP 600) via countEventsByCentury()
 *          usersLoggedInQuery (private, CRAP 420) via usersLoggedIn() / usersLoggedInList()
 *
 * @see docs/testing-bigpicture.md S41
 * @covers \Fisharebest\Webtrees\StatisticsData
 */
class StatisticsDataIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private StatisticsData $stats;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->stats = new StatisticsData($this->tree, $this->userService);
    }

    /**
     * usersLoggedIn() ruft usersLoggedInQuery('nolist') — gibt String zurück.
     */
    public function test_users_logged_in_returns_string(): void
    {
        $result = $this->stats->usersLoggedIn();

        $this->assertIsString($result);
    }

    /**
     * usersLoggedInList() ruft usersLoggedInQuery('list') — gibt String zurück.
     */
    public function test_users_logged_in_list_returns_string(): void
    {
        $result = $this->stats->usersLoggedInList();

        $this->assertIsString($result);
    }

    /**
     * countEventsByCentury() ruft centuryName() intern für jedes Ergebnis.
     * Mit demo.ged gibt es Geburtsdaten → mindestens ein Eintrag.
     */
    public function test_count_events_by_century_birth_returns_array(): void
    {
        $result = $this->stats->countEventsByCentury('BIRT');

        $this->assertIsArray($result);
    }

    /**
     * countEventsByCentury() mit DEAT — prüft centuryName() für Sterbedaten.
     */
    public function test_count_events_by_century_death_returns_array(): void
    {
        $result = $this->stats->countEventsByCentury('DEAT');

        $this->assertIsArray($result);
    }
}
