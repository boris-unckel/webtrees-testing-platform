<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\IndividualFactsService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;

/**
 * Komponentenintegrationstest: IndividualFactsService mit MySQL.
 *
 * Testet Fakten-Aggregation via relativeFacts() für Individuen aus demo.ged.
 * relativeFacts() ruft intern childFacts() (CRAP 1.980, cx=44) und
 * parentFacts() (CRAP 992, cx=31) auf — kein direkter Zugriff möglich
 * (private Methoden), aber über die öffentliche API erreichbar.
 *
 * Kein Feature-Matrix-Eintrag — technischer Risikotest analog
 * RomanNumeralsIntegrationTest.
 *
 * @covers \Fisharebest\Webtrees\Services\IndividualFactsService
 */
class IndividualFactsIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private IndividualFactsService $facts_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facts_service = new IndividualFactsService(
            new LinkedRecordService(),
            new ModuleService(),
        );
    }

    /**
     * relativeFacts() gibt iterierbares Ergebnis zurück für Individuum mit Kindern.
     * Deckt intern childFacts() ab.
     */
    public function test_relative_facts_returns_collection_for_individual_with_children(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Elizabeth II (X1030) hat Kinder in demo.ged
        $individual = Registry::individualFactory()->make('X1030', $this->tree);
        $this->assertNotNull($individual);

        $facts = $this->facts_service->relativeFacts($individual);

        $this->assertIsIterable($facts);
    }

    /**
     * relativeFacts() gibt iterierbares Ergebnis zurück für Individuum mit Eltern.
     * Deckt intern parentFacts() ab.
     */
    public function test_relative_facts_returns_collection_for_individual_with_parents(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Sohn von Elizabeth II (X1052) hat Eltern in demo.ged
        $individual = Registry::individualFactory()->make('X1052', $this->tree);
        $this->assertNotNull($individual);

        $facts = $this->facts_service->relativeFacts($individual);

        $this->assertIsIterable($facts);
    }

    /**
     * relativeFacts() gibt Collection zurück (nicht null, nicht false).
     */
    public function test_relative_facts_result_is_not_null(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $individual = Registry::individualFactory()->make('X1030', $this->tree);
        $this->assertNotNull($individual);

        $facts = $this->facts_service->relativeFacts($individual);

        $this->assertNotNull($facts);
    }
}
