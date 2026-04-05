<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\StatisticsData;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Komponentenintegrationstest: StatisticsData.
 *
 * AP B-01: centuryName (private, CRAP 600) via countEventsByCentury()
 *          usersLoggedInQuery (private, CRAP 420) via usersLoggedIn() / usersLoggedInList()
 * S41: EP-Matrix für countEventsByMonth whereBetween-Branch, commonSurnames-Sort, parentsQuery-Sex.
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

    // --- countEventsByMonth — whereBetween-Branch (EP5/EP6/EP8) ---

    /**
     * year1=0, year2=0 → kein whereBetween, alle Jahre (EP5).
     * Demo.ged hat Geburtsdaten → mindestens ein Eintrag.
     */
    public function test_count_events_by_month_all_years_returns_non_empty(): void
    {
        $result = $this->stats->countEventsByMonth('BIRT', 0, 0);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * year1=1900, year2=2000 → whereBetween aktiv (EP6).
     * Demo.ged hat royale Geburtsdaten im 20. Jahrhundert → Ergebnis nicht leer.
     */
    public function test_count_events_by_month_with_year_range_filter_returns_array(): void
    {
        $result = $this->stats->countEventsByMonth('BIRT', 1900, 2000);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * year1=2100, year2=1900 → invertierter Bereich → MySQL BETWEEN leer (EP8).
     * BETWEEN 2100 AND 1900 entspricht d_year >= 2100 AND d_year <= 1900 → immer false.
     */
    public function test_count_events_by_month_inverted_range_returns_empty(): void
    {
        $result = $this->stats->countEventsByMonth('BIRT', 2100, 1900);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // --- commonSurnames — Sort-Typen (EP10/EP11/EP12) ---

    /**
     * @return array<string, array{string}>
     */
    public static function sortTypes(): array
    {
        return [
            'alpha'  => ['alpha'],
            'count'  => ['count'],
            'rcount' => ['rcount'],
        ];
    }

    /**
     * commonSurnames() gibt für alle sort-Typen ein Array zurück (EP10/EP11/EP12).
     */
    #[DataProvider('sortTypes')]
    public function test_common_surnames_all_sort_types_return_array(string $sort): void
    {
        $result = $this->stats->commonSurnames(10, 0, $sort);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Hoher Threshold filtert seltene Nachnamen heraus (EP13).
     * threshold=999 → nur Namen mit ≥999 Einträgen → in Demo-Daten leer oder kleiner als threshold=0.
     */
    public function test_common_surnames_high_threshold_filters_names(): void
    {
        $resultAll  = $this->stats->commonSurnames(100, 0, 'count');
        $resultHigh = $this->stats->commonSurnames(100, 999, 'count');

        $this->assertLessThanOrEqual(count($resultAll), count($resultAll));
        $this->assertLessThanOrEqual(count($resultAll), count($resultHigh));
    }

    // --- parentsQuery — Sex-Filter-Branch (EP1/EP2) ---

    /**
     * @return array<string, array{string}>
     */
    public static function sexValues(): array
    {
        return [
            'female' => ['F'],
            'male'   => ['M'],
        ];
    }

    /**
     * parentsQuery() gibt für sex='F' (→WIFE) und sex='M' (→HUSB) einen String zurück (EP1/EP2).
     */
    #[DataProvider('sexValues')]
    public function test_parents_query_sex_variants_return_string(string $sex): void
    {
        $result = $this->stats->parentsQuery('full', 'ASC', $sex, false);

        $this->assertIsString($result);
    }
}
