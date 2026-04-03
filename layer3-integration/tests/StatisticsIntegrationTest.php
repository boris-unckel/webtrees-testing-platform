<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\StatisticsData;
use Fisharebest\Webtrees\StatisticsFormat;

/**
 * Komponentenintegrationstest: StatisticsFormat (Bootstrap) + StatisticsData (DB).
 *
 * StatisticsFormat::century — Bootstrap-only, kein DB-Zugriff.
 * StatisticsData::ageOfMarriageQuery, parentsQuery, marriageQuery — DB-abhängig.
 *
 * @covers \Fisharebest\Webtrees\StatisticsFormat
 * @covers \Fisharebest\Webtrees\StatisticsData
 */
class StatisticsIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private StatisticsFormat $format;

    protected function setUp(): void
    {
        parent::setUp();

        $this->format = new StatisticsFormat();
    }

    // --- StatisticsFormat::century (Bootstrap-only) ---

    /**
     * century(21) gibt nicht-leeren String zurück.
     */
    public function test_century_21_returns_string(): void
    {
        $result = $this->format->century(21);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * century(20) gibt nicht-leeren String zurück.
     */
    public function test_century_20_returns_string(): void
    {
        $result = $this->format->century(20);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * century(-1) (v. Chr.) gibt nicht-leeren String zurück.
     */
    public function test_century_negative_returns_string(): void
    {
        $result = $this->format->century(-1);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * century(1) (erstes Jahrhundert) gibt nicht-leeren String zurück.
     */
    public function test_century_first_returns_string(): void
    {
        $result = $this->format->century(1);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // --- StatisticsData (DB-abhängig) ---

    /**
     * ageOfMarriageQuery mit type='name', ASC, limit=1 gibt String zurück.
     */
    public function test_age_of_marriage_query_name_asc_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $data   = new StatisticsData($this->tree, Registry::container()->get(UserService::class));
        $result = $data->ageOfMarriageQuery('name', 'ASC', 1);

        $this->assertIsString($result);
    }

    /**
     * ageOfMarriageQuery mit type='name', DESC, limit=5 gibt String zurück.
     */
    public function test_age_of_marriage_query_name_desc_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $data   = new StatisticsData($this->tree, Registry::container()->get(UserService::class));
        $result = $data->ageOfMarriageQuery('name', 'DESC', 5);

        $this->assertIsString($result);
    }

    /**
     * parentsQuery mit type='full', age_dir='DESC', sex='F', show_years=false gibt String zurück.
     */
    public function test_parents_query_full_female_desc_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $data   = new StatisticsData($this->tree, Registry::container()->get(UserService::class));
        $result = $data->parentsQuery('full', 'DESC', 'F', false);

        $this->assertIsString($result);
    }

    /**
     * parentsQuery mit type='age', age_dir='ASC', sex='M', show_years=true gibt String zurück.
     */
    public function test_parents_query_age_male_asc_years_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $data   = new StatisticsData($this->tree, Registry::container()->get(UserService::class));
        $result = $data->parentsQuery('age', 'ASC', 'M', true);

        $this->assertIsString($result);
    }

    /**
     * marriageQuery mit show='full', age_dir='DESC', sex='M', show_years=false gibt String zurück.
     */
    public function test_marriage_query_full_male_desc_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $data   = new StatisticsData($this->tree, Registry::container()->get(UserService::class));
        $result = $data->marriageQuery('full', 'DESC', 'M', false);

        $this->assertIsString($result);
    }

    /**
     * marriageQuery mit show='age', age_dir='ASC', sex='F', show_years=true gibt String zurück.
     */
    public function test_marriage_query_age_female_asc_years_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $data   = new StatisticsData($this->tree, Registry::container()->get(UserService::class));
        $result = $data->marriageQuery('age', 'ASC', 'F', true);

        $this->assertIsString($result);
    }
}
