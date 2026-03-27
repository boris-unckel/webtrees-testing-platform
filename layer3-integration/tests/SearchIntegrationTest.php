<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Services\SearchService;

/**
 * Komponentenintegrationstest: SearchService mit MySQL.
 *
 * Testet Suche über INDI, FAM, SOUR, REPO, Orte, Medien und Submitter.
 *
 * @covers \Fisharebest\Webtrees\Services\SearchService
 * @see docs/testing-bigpicture-prompt.md S01, S02, S03, S04, S07, S10, S12, S22
 */
class SearchIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private SearchService $search_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->search_service = new SearchService($this->treeService);
    }

    /**
     * S01 — Personensuche findet bekannte Person.
     */
    public function test_search_individuals_finds_known_person(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividuals([$this->tree], ['Elizabeth']);

        $this->assertNotEmpty($results, 'Suche nach "Elizabeth" muss Ergebnisse liefern');
    }

    /**
     * S01 — Suche ohne Treffer liefert leeres Ergebnis.
     */
    public function test_search_individuals_returns_empty_for_no_match(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividuals([$this->tree], ['xyznonexistent99']);

        $this->assertEmpty($results);
    }

    /**
     * S02 — Familiensuche findet Familien.
     */
    public function test_search_families_finds_matching_families(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchFamilies([$this->tree], ['Windsor']);

        $this->assertNotEmpty($results, 'Suche nach "Windsor"-Familien muss Ergebnisse liefern');
    }

    /**
     * S03 — Quellensuche findet vorhandene Quellen.
     */
    public function test_search_sources_finds_matching_sources(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchSources([$this->tree], ['a']);

        $this->assertGreaterThan(0, count($results), 'Quellen-Suche muss mindestens eine Quelle finden');
    }

    /**
     * S04 — Repository-Suche findet vorhandene Repositories.
     */
    public function test_search_repositories_finds_matching_repositories(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchRepositories([$this->tree], ['a']);

        $this->assertGreaterThan(0, count($results), 'Repository-Suche muss mindestens ein Repository finden');
    }

    /**
     * S01 — Mehrwort-Suche schränkt Ergebnisse ein.
     */
    public function test_search_with_multiple_words_narrows_results(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $broad  = $this->search_service->searchIndividuals([$this->tree], ['Elizabeth']);
        $narrow = $this->search_service->searchIndividuals([$this->tree], ['Elizabeth', 'Queen']);

        $this->assertGreaterThanOrEqual(count($narrow), count($broad),
            'Mehrwort-Suche darf nicht mehr Ergebnisse liefern als Einzelwort-Suche');
    }

    /**
     * S12 — Gast-Suche liefert weniger/gleich viele Ergebnisse als Admin-Suche.
     */
    public function test_guest_search_returns_fewer_results_than_admin(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Admin-Suche
        $this->createAndLoginAdmin();
        $admin_results = $this->search_service->searchIndividuals([$this->tree], ['Elizabeth']);

        // Logout → Gast
        Auth::logout();

        $guest_results = $this->search_service->searchIndividuals([$this->tree], ['Elizabeth']);

        $this->assertGreaterThanOrEqual(count($guest_results), count($admin_results),
            'Admin muss mindestens so viele Ergebnisse sehen wie ein Gast');
    }

    /**
     * S07 — Ortssuche findet passende Orte.
     */
    public function test_search_places_finds_matching_places(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $results = $this->search_service->searchPlaces($this->tree, 'England');

        $this->assertNotEmpty($results, 'Ortssuche nach "England" muss Treffer liefern');
    }

    /**
     * S07 — Ortssuche liefert leer bei unbekanntem Ort.
     */
    public function test_search_places_returns_empty_for_non_match(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $results = $this->search_service->searchPlaces($this->tree, 'xyznonexistent99');

        $this->assertEmpty($results);
    }

    /**
     * S10 — Mediensuche findet Medienobjekte.
     */
    public function test_search_media_finds_matching_media(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchMedia([$this->tree], ['a']);

        $this->assertNotEmpty($results, 'Medien-Suche muss mindestens ein Medium finden');
    }

    /**
     * S22 — Submitter-Suche findet Einsender.
     */
    public function test_search_submitters_finds_matching_submitters(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // demo.ged hat nur einen Submitter: "greg"
        $results = $this->search_service->searchSubmitters([$this->tree], ['greg']);

        $this->assertGreaterThan(0, count($results), 'Submitter-Suche muss mindestens einen Einsender finden');
    }

    /**
     * S01 — Suche nach bekanntem Nachnamen findet passende Ergebnisse.
     */
    public function test_search_individuals_by_known_surname(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividuals([$this->tree], ['Windsor']);

        $this->assertNotEmpty($results, 'Suche nach "Windsor" muss Ergebnisse liefern');
    }
}
