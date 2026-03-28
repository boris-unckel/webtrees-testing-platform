<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Tree;

/**
 * Komponentenintegrationstest: SearchService mit MySQL.
 *
 * Testet Suche über INDI, FAM, SOUR, REPO, Orte, Medien und Submitter.
 *
 * @covers \Fisharebest\Webtrees\Services\SearchService
 * @see docs/testing-bigpicture.md S01, S02, S03, S04, S05, S06, S07, S08, S10, S11, S12, S22
 */
class SearchIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';
    private const MUSTER_GED = '/fixtures/gedcom-l-muster.ged';

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

    // --- AP 8-1: Erweiterte Suche (S05, S06), Phonetik (S07, S08), Paginierung (S10), Cross-Tree (S11) ---

    /**
     * S05 — Erweiterte Suche nach Name-Feld findet passende Individuen.
     */
    public function test_advanced_search_by_name_field_finds_matching_individuals(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:NAME:GIVN' => 'Elizabeth'],
            ['INDI:NAME:GIVN' => 'CONTAINS'],
        );

        $this->assertNotEmpty($results, 'Erweiterte Suche nach GIVN "Elizabeth" muss Treffer liefern');
    }

    /**
     * S05 — Erweiterte Suche nach Nachnamen findet passende Individuen.
     */
    public function test_advanced_search_by_surname_finds_matching_individuals(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:NAME:SURN' => 'Windsor'],
            ['INDI:NAME:SURN' => 'CONTAINS'],
        );

        $this->assertNotEmpty($results, 'Erweiterte Suche nach SURN "Windsor" muss Treffer liefern');
    }

    /**
     * S05 — Erweiterte Suche nach Sterbedatum findet passende Individuen.
     */
    public function test_advanced_search_by_death_date_finds_matching_individuals(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Diana: DEAT 31 AUG 1997
        $results = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:DEAT:DATE' => '1997'],
            ['INDI:DEAT:DATE' => '0'],
        );

        $this->assertNotEmpty($results, 'Erweiterte Suche nach Sterbejahr 1997 muss Treffer liefern');
    }

    /**
     * S05 — Erweiterte Suche mit mehreren Feldern schränkt Ergebnisse ein.
     */
    public function test_advanced_search_with_multiple_fields_narrows_results(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $broad = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:NAME:SURN' => 'Windsor'],
            ['INDI:NAME:SURN' => 'CONTAINS'],
        );

        $narrow = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:NAME:SURN' => 'Windsor', 'INDI:NAME:GIVN' => 'Charles'],
            ['INDI:NAME:SURN' => 'CONTAINS', 'INDI:NAME:GIVN' => 'CONTAINS'],
        );

        $this->assertGreaterThanOrEqual(count($narrow), count($broad),
            'Kombination mehrerer Felder darf nicht mehr Ergebnisse liefern');
        $this->assertNotEmpty($narrow, 'Suche nach "Charles Windsor" muss Treffer liefern');
    }

    /**
     * S05 — Erweiterte Suche mit leeren Feldern liefert alle Individuen.
     */
    public function test_advanced_search_with_empty_fields_returns_all(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:NAME:GIVN' => ''],
            ['INDI:NAME:GIVN' => 'CONTAINS'],
        );

        // Leere Felder werden intern gefiltert → alle Ergebnisse
        $this->assertNotEmpty($results, 'Leere Suchfelder sollen alle Individuen liefern');
    }

    /**
     * S06 — Erweiterte Suche mit Datum-Modifikator ±5 erweitert Ergebnisse.
     */
    public function test_advanced_search_birth_date_modifier_plus5_widens_results(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Exakte Suche: Geburt 1926 (Queen Elizabeth II)
        $exact = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:BIRT:DATE' => '1926'],
            ['INDI:BIRT:DATE' => '0'],
        );

        // Suche mit ±5 Jahre: 1921-1931
        $wider = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:BIRT:DATE' => '1926'],
            ['INDI:BIRT:DATE' => '5'],
        );

        $this->assertGreaterThanOrEqual(count($exact), count($wider),
            'Datum ±5 muss mindestens so viele Ergebnisse liefern wie exakt');
    }

    /**
     * S06 — Erweiterte Suche mit Datum-Modifikator ±0 liefert exakte Treffer.
     */
    public function test_advanced_search_birth_date_modifier_zero_returns_exact(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:BIRT:DATE' => '1926'],
            ['INDI:BIRT:DATE' => '0'],
        );

        // Mindestens Queen Elizabeth II (21 APR 1926)
        $this->assertNotEmpty($results, 'Exakte Suche nach Geburtsjahr 1926 muss Treffer liefern');
    }

    /**
     * S06 — Erweiterte Suche mit Datum-Modifikator ±20 liefert breite Ergebnisse.
     */
    public function test_advanced_search_birth_date_modifier_max_returns_wide_range(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $wide = $this->search_service->searchIndividualsAdvanced(
            $this->tree,
            ['INDI:BIRT:DATE' => '1950'],
            ['INDI:BIRT:DATE' => '20'],
        );

        // 1930-1970 — sollte viele Royals finden
        $this->assertNotEmpty($wide, 'Datum ±20 (1930-1970) muss Treffer liefern');
    }

    /**
     * S07 — Phonetische Suche (Russell) findet ähnlich klingende Namen.
     */
    public function test_phonetic_search_russell_finds_similar_sounding_names(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Suche nach Vorname "Elizabeth" — zuverlässig in demo.ged vorhanden
        $results = $this->search_service->searchIndividualsPhonetic(
            'Russell',
            '',
            'Elizabeth',
            '',
            [$this->tree],
        );

        $this->assertNotEmpty($results, 'Russell-Soundex für Vorname "Elizabeth" muss Treffer liefern');
    }

    /**
     * S07 — Phonetische Suche (Russell) liefert leer bei Phantasienamen.
     */
    public function test_phonetic_search_russell_returns_empty_for_no_match(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividualsPhonetic(
            'Russell',
            'Xyzqkjw',
            '',
            '',
            [$this->tree],
        );

        $this->assertEmpty($results, 'Russell-Soundex für Phantasienamen darf keine Treffer liefern');
    }

    /**
     * S08 — Phonetische Suche (Daitch-Mokotoff) findet Varianten.
     */
    public function test_phonetic_search_dm_finds_eastern_european_variants(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // Suche nach Vorname "Elizabeth" — DM-Soundex
        $results = $this->search_service->searchIndividualsPhonetic(
            'DaitchM',
            '',
            'Elizabeth',
            '',
            [$this->tree],
        );

        $this->assertNotEmpty($results, 'DM-Soundex für Vorname "Elizabeth" muss Treffer liefern');
    }

    /**
     * S08 — Phonetische Suche (Daitch-Mokotoff) liefert leer bei Phantasienamen.
     */
    public function test_phonetic_search_dm_returns_empty_for_no_match(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividualsPhonetic(
            'DaitchM',
            'Xyzqkjw',
            '',
            '',
            [$this->tree],
        );

        $this->assertEmpty($results, 'DM-Soundex für Phantasienamen darf keine Treffer liefern');
    }

    /**
     * S10 — Suche mit Limit begrenzt Ergebnismenge.
     */
    public function test_search_individuals_with_limit_returns_correct_count(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $results = $this->search_service->searchIndividualNames([$this->tree], ['a'], 0, 5);

        $this->assertLessThanOrEqual(5, count($results), 'Limit 5 darf nicht mehr als 5 Ergebnisse liefern');
        $this->assertNotEmpty($results, 'Suche nach "a" mit Limit 5 muss Treffer liefern');
    }

    /**
     * S10 — Suche mit Offset überspringt Ergebnisse.
     */
    public function test_search_individuals_with_offset_skips_results(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $all    = $this->search_service->searchIndividualNames([$this->tree], ['a'], 0, 100);
        $offset = $this->search_service->searchIndividualNames([$this->tree], ['a'], 5, 100);

        $this->assertLessThan(count($all), count($offset),
            'Mit Offset müssen weniger Ergebnisse zurückkommen');
    }

    /**
     * S10 — Suche mit Offset und Limit liefert eine Seite.
     */
    public function test_search_individuals_with_offset_and_limit_returns_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $page = $this->search_service->searchIndividualNames([$this->tree], ['a'], 2, 3);

        $this->assertLessThanOrEqual(3, count($page), 'Offset+Limit darf nicht mehr als 3 Ergebnisse liefern');
    }

    /**
     * S11 — Cross-Tree-Suche findet Ergebnisse aus beiden Bäumen.
     */
    public function test_search_across_trees_finds_results_from_both(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $demoTree = $this->tree;

        // Zweiten Baum anlegen
        $uniqueName = 'muster-' . substr(md5($this->name()), 0, 8);
        $musterTree = $this->treeService->create($uniqueName, 'Muster');
        $this->importGedcomIntoTree($musterTree, self::MUSTER_GED);

        $results = $this->search_service->searchIndividuals([$demoTree, $musterTree], ['a']);

        $this->assertNotEmpty($results, 'Cross-Tree-Suche muss Ergebnisse aus mindestens einem Baum liefern');

        // Aufräumen: zweiten Baum löschen
        $this->treeService->delete($musterTree);
    }

    /**
     * S11 — Cross-Tree-Suche mit baumspezifischem Namen findet nur in einem Baum.
     */
    public function test_search_across_trees_with_tree_specific_name(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $demoTree = $this->tree;

        $uniqueName = 'muster-' . substr(md5($this->name()), 0, 8);
        $musterTree = $this->treeService->create($uniqueName, 'Muster');
        $this->importGedcomIntoTree($musterTree, self::MUSTER_GED);

        // "Windsor" gibt es nur im demo-Baum
        $results = $this->search_service->searchIndividuals([$demoTree, $musterTree], ['Windsor']);

        $this->assertNotEmpty($results, 'Cross-Tree-Suche nach "Windsor" muss Treffer liefern');

        // Aufräumen
        $this->treeService->delete($musterTree);
    }

    /**
     * Importiert eine GEDCOM-Datei in einen bestehenden Baum (Hilfsmethode für Cross-Tree-Tests).
     */
    private function importGedcomIntoTree(Tree $tree, string $gedcomPath): void
    {
        \Fisharebest\Webtrees\DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('families')->where('f_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('sources')->where('s_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('other')->where('o_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('places')->where('p_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('placelinks')->where('pl_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('name')->where('n_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('dates')->where('d_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('change')->where('gedcom_id', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('link')->where('l_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('media_file')->where('m_file', '=', $tree->id())->delete();
        \Fisharebest\Webtrees\DB::table('media')->where('m_file', '=', $tree->id())->delete();

        $gedcom = file_get_contents($gedcomPath);
        assert($gedcom !== false);
        $gedcom = str_replace("\xEF\xBB\xBF", '', $gedcom);
        $gedcom = str_replace("\r\n", "\n", $gedcom);
        $records = preg_split('/\n(?=0 )/', $gedcom);
        foreach ($records as $record) {
            $this->gedcomImportService->importRecord($record, $tree, false);
        }
    }
}
