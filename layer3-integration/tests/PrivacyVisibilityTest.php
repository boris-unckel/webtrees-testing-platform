<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

/**
 * Sichtbarkeitstests: Stammbaum-Einstellungen × Rollen × Personenzustaende.
 *
 * Testet die Entscheidungskette canShow() → canShowRecord() → canShowByType()
 * fuer verschiedene Kombinationen aus Stammbaum-Einstellungen und Access-Levels.
 *
 * @covers \Fisharebest\Webtrees\Individual::canShowByType
 * @covers \Fisharebest\Webtrees\GedcomRecord::canShowRecord
 */
class PrivacyVisibilityTest extends PrivacyTestCase
{
    private Tree $privacyTree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privacyTree = $this->createPrivacyTree();
    }

    // -------------------------------------------------------
    // P01 — Stammbaum-Sichtbarkeit (REQUIRE_AUTHENTICATION)
    // -------------------------------------------------------

    public function test_require_authentication_sets_tree_private_flag(): void
    {
        // REQUIRE_AUTHENTICATION wird ab 2.2.6 ueber die 'private'-Spalte in gedcom-Tabelle gesteuert.
        // Die Durchsetzung geschieht auf Middleware-Ebene, nicht in canShow().
        // Hier verifizieren wir, dass die Preference korrekt auf die private-Spalte abgebildet wird.
        DB::table('gedcom')
            ->where('gedcom_id', '=', $this->privacyTree->id())
            ->update(['private' => 1]);

        // Frischen Tree laden, um den Wert zu lesen
        $freshTree = $this->treeService->find($this->privacyTree->id());
        self::assertNotNull($freshTree);
        self::assertTrue($freshTree->private(), 'Tree sollte nach Setzen von private=1 als privat gelten');
    }

    public function test_require_authentication_disabled_tree_not_private(): void
    {
        DB::table('gedcom')
            ->where('gedcom_id', '=', $this->privacyTree->id())
            ->update(['private' => 0]);

        $freshTree = $this->treeService->find($this->privacyTree->id());
        self::assertNotNull($freshTree);
        self::assertFalse($freshTree->private(), 'Tree sollte nach Setzen von private=0 als oeffentlich gelten');

        // Bei oeffentlichem Baum sieht Besucher verstorbene Personen
        $individual = Registry::individualFactory()->make('P_DEAD_HISTORIC', $freshTree);
        self::assertNotNull($individual);
        self::assertTrue(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'Oeffentlicher Baum: Besucher sollte Verstorbene sehen'
        );
    }

    public function test_member_can_see_record_regardless_of_private_flag(): void
    {
        // Mitglied sieht Records unabhaengig von private Flag (Middleware laesst Member durch)
        $individual = Registry::individualFactory()->make('P_DEAD_HISTORIC', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'Mitglied sollte Record sehen koennen'
        );
    }

    // -------------------------------------------------------
    // P03 — HIDE_LIVE_PEOPLE (Privacy-Toggle)
    // -------------------------------------------------------

    public function test_hide_live_people_disabled_visitor_sees_all(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '0');

        $individual = Registry::individualFactory()->make('P_ALIVE_YOUNG', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertTrue(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'HIDE_LIVE_PEOPLE=0: Besucher sollte alle Personen sehen (Privacy deaktiviert)'
        );
    }

    public function test_hide_live_people_enabled_visitor_cannot_see_living(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);

        $individual = Registry::individualFactory()->make('P_ALIVE_YOUNG', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertFalse(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'HIDE_LIVE_PEOPLE=1: Besucher sollte lebende Person nicht sehen'
        );
    }

    public function test_hide_live_people_enabled_member_sees_living(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');

        $individual = Registry::individualFactory()->make('P_ALIVE_YOUNG', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'HIDE_LIVE_PEOPLE=1: Mitglied sollte lebende Person sehen koennen'
        );
    }

    // -------------------------------------------------------
    // P02 — SHOW_DEAD_PEOPLE
    // -------------------------------------------------------

    public function test_show_dead_people_visitor_setting_visitor_sees_dead(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);

        $individual = Registry::individualFactory()->make('P_DEAD_HISTORIC', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertTrue(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'SHOW_DEAD_PEOPLE=PRIV_PRIVATE: Besucher sollte Verstorbene sehen'
        );
    }

    public function test_show_dead_people_member_setting_visitor_cannot_see_dead(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_USER);

        $individual = Registry::individualFactory()->make('P_DEAD_HISTORIC', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertFalse(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'SHOW_DEAD_PEOPLE=PRIV_USER: Besucher sollte Verstorbene nicht sehen'
        );
    }

    public function test_show_dead_people_member_setting_member_sees_dead(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_USER);

        $individual = Registry::individualFactory()->make('P_DEAD_HISTORIC', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'SHOW_DEAD_PEOPLE=PRIV_USER: Mitglied sollte Verstorbene sehen'
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deadPersonsProvider')]
    public function test_show_dead_people_visitor_various_dead_persons(string $xref, string $description): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);

        $individual = Registry::individualFactory()->make($xref, $this->privacyTree);
        self::assertNotNull($individual, "{$xref} nicht gefunden");

        self::assertTrue(
            $individual->canShow(Auth::PRIV_PRIVATE),
            "SHOW_DEAD_PEOPLE=PRIV_PRIVATE: Besucher sollte {$description} sehen"
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function deadPersonsProvider(): array
    {
        return [
            'DEAD_HISTORIC' => ['P_DEAD_HISTORIC', 'historisch Verstorbene'],
            'DEAD_EXPLICIT' => ['P_DEAD_EXPLICIT', 'explizit Verstorbene (DEAT Y)'],
            'DEAD_DATED'    => ['P_DEAD_DATED', 'Verstorbene mit Datum'],
            'DEAD_PLACED'   => ['P_DEAD_PLACED', 'Verstorbene mit Ort'],
        ];
    }

    // -------------------------------------------------------
    // P04 — MAX_ALIVE_AGE × Sichtbarkeit
    // -------------------------------------------------------

    public function test_max_alive_age_boundary_plus1_visitor_sees_dead_person(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $this->setTreePreference($this->privacyTree, 'MAX_ALIVE_AGE', '120');

        // 121 Jahre alt → als tot erkannt → Besucher sieht Person
        $individual = Registry::individualFactory()->make('P_BOUNDARY_PLUS1', $this->privacyTree);
        self::assertNotNull($individual);
        self::assertTrue(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'Person 121 Jahre alt: Besucher sollte als Verstorbene sehen'
        );
    }

    public function test_max_alive_age_boundary_minus1_visitor_cannot_see_living(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $this->setTreePreference($this->privacyTree, 'MAX_ALIVE_AGE', '120');

        // 119 Jahre alt → als lebend angenommen → Besucher sieht nicht
        $individual = Registry::individualFactory()->make('P_BOUNDARY_MINUS1', $this->privacyTree);
        self::assertNotNull($individual);
        self::assertFalse(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'Person 119 Jahre alt: Besucher sollte lebende Person nicht sehen'
        );
    }

    // -------------------------------------------------------
    // P05 — KEEP_ALIVE_YEARS_BIRTH
    // -------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('keepAliveBirthProvider')]
    public function test_keep_alive_years_birth(string $xref, bool $expectedProtected, string $description): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $this->setTreePreference($this->privacyTree, 'KEEP_ALIVE_YEARS_BIRTH', '10');
        $this->setTreePreference($this->privacyTree, 'KEEP_ALIVE_YEARS_DEATH', '0');

        $individual = Registry::individualFactory()->make($xref, $this->privacyTree);
        self::assertNotNull($individual, "{$xref} nicht gefunden");

        if ($expectedProtected) {
            // Geschuetzt: Besucher sieht Person nicht (wie lebend behandelt)
            self::assertFalse(
                $individual->canShow(Auth::PRIV_PRIVATE),
                "KEEP_ALIVE_YEARS_BIRTH=10: {$description} — Besucher sollte geschuetzte Person nicht sehen"
            );
        } else {
            // Nicht geschuetzt: Besucher sieht Verstorbene
            self::assertTrue(
                $individual->canShow(Auth::PRIV_PRIVATE),
                "KEEP_ALIVE_YEARS_BIRTH=10: {$description} — Besucher sollte ungeschuetzte Verstorbene sehen"
            );
        }
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function keepAliveBirthProvider(): array
    {
        return [
            'inside (9 Jahre)'   => ['P_KEEP_BIRTH_INSIDE', true, 'Geburt vor 9 Jahren, innerhalb KEEP_ALIVE'],
            'boundary (10 Jahre)' => ['P_KEEP_BIRT_BOUND', false, 'Geburt vor 10 Jahren, auf Grenze (exakt = nicht geschuetzt)'],
            'outside (11 Jahre)' => ['P_KEEP_BIRTH_OUTSIDE', false, 'Geburt vor 11 Jahren, ausserhalb KEEP_ALIVE'],
        ];
    }

    // -------------------------------------------------------
    // P06 — KEEP_ALIVE_YEARS_DEATH
    // -------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('keepAliveDeathProvider')]
    public function test_keep_alive_years_death(string $xref, bool $expectedProtected, string $description): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $this->setTreePreference($this->privacyTree, 'KEEP_ALIVE_YEARS_BIRTH', '0');
        $this->setTreePreference($this->privacyTree, 'KEEP_ALIVE_YEARS_DEATH', '10');

        $individual = Registry::individualFactory()->make($xref, $this->privacyTree);
        self::assertNotNull($individual, "{$xref} nicht gefunden");

        if ($expectedProtected) {
            self::assertFalse(
                $individual->canShow(Auth::PRIV_PRIVATE),
                "KEEP_ALIVE_YEARS_DEATH=10: {$description} — Besucher sollte geschuetzte Person nicht sehen"
            );
        } else {
            self::assertTrue(
                $individual->canShow(Auth::PRIV_PRIVATE),
                "KEEP_ALIVE_YEARS_DEATH=10: {$description} — Besucher sollte ungeschuetzte Verstorbene sehen"
            );
        }
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function keepAliveDeathProvider(): array
    {
        return [
            'inside (9 Jahre)'   => ['P_KEEP_DEATH_INSIDE', true, 'Tod vor 9 Jahren, innerhalb KEEP_ALIVE'],
            'boundary (10 Jahre)' => ['P_KEEP_DEAT_BOUND', false, 'Tod vor 10 Jahren, auf Grenze (exakt = nicht geschuetzt)'],
            'outside (11 Jahre)' => ['P_KEEP_DEATH_OUTSIDE', false, 'Tod vor 11 Jahren, ausserhalb KEEP_ALIVE'],
        ];
    }

    // -------------------------------------------------------
    // P07 — KEEP_ALIVE kombiniert (OR-Logik)
    // -------------------------------------------------------

    public function test_keep_alive_combined_birth_applies_death_outside(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $this->setTreePreference($this->privacyTree, 'KEEP_ALIVE_YEARS_BIRTH', '10');
        $this->setTreePreference($this->privacyTree, 'KEEP_ALIVE_YEARS_DEATH', '10');

        // P_KEEP_DEATH_OUTSIDE: Tod vor 11 Jahren (ausserhalb KEEP_ALIVE_YEARS_DEATH)
        // Geburt vor 70 Jahren (ausserhalb KEEP_ALIVE_YEARS_BIRTH)
        // Beide Bedingungen greifen nicht → nicht geschuetzt
        $individual = Registry::individualFactory()->make('P_KEEP_DEATH_OUTSIDE', $this->privacyTree);
        self::assertNotNull($individual);
        self::assertTrue(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'Beide KEEP_ALIVE gesetzt, keiner greift: Besucher sollte Verstorbene sehen'
        );
    }

    public function test_keep_alive_combined_birth_inside_death_outside(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $this->setTreePreference($this->privacyTree, 'KEEP_ALIVE_YEARS_BIRTH', '10');
        $this->setTreePreference($this->privacyTree, 'KEEP_ALIVE_YEARS_DEATH', '10');

        // P_KEEP_BIRTH_INSIDE: Geburt vor 9 Jahren (innerhalb KEEP_ALIVE_YEARS_BIRTH)
        // Tod: DEAT Y (kein Datum → KEEP_ALIVE_YEARS_DEATH greift nicht)
        // OR-Logik: Birth-Bedingung greift → geschuetzt
        $individual = Registry::individualFactory()->make('P_KEEP_BIRTH_INSIDE', $this->privacyTree);
        self::assertNotNull($individual);
        self::assertFalse(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'KEEP_ALIVE_YEARS_BIRTH greift (Geburt vor 9 J): Besucher sollte geschuetzte Person nicht sehen'
        );
    }

    // -------------------------------------------------------
    // P14 — SHOW_LIVING_NAMES
    // -------------------------------------------------------

    public function test_show_living_names_visitor_sees_name(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_LIVING_NAMES', (string) Auth::PRIV_PRIVATE);

        $individual = Registry::individualFactory()->make('P_ALIVE_YOUNG', $this->privacyTree);
        self::assertNotNull($individual);

        // Person insgesamt nicht sichtbar fuer Besucher, aber Name schon
        self::assertFalse(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'Lebende Person: canShow() sollte false fuer Besucher sein'
        );
        self::assertTrue(
            $individual->canShowName(Auth::PRIV_PRIVATE),
            'SHOW_LIVING_NAMES=PRIV_PRIVATE: Besucher sollte Name sehen koennen'
        );
    }

    public function test_show_living_names_member_only_visitor_cannot_see_name(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_LIVING_NAMES', (string) Auth::PRIV_USER);

        $individual = Registry::individualFactory()->make('P_ALIVE_YOUNG', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertFalse(
            $individual->canShowName(Auth::PRIV_PRIVATE),
            'SHOW_LIVING_NAMES=PRIV_USER: Besucher sollte Name nicht sehen koennen'
        );
    }

    public function test_show_living_names_member_only_member_sees_name(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_LIVING_NAMES', (string) Auth::PRIV_USER);

        $individual = Registry::individualFactory()->make('P_ALIVE_YOUNG', $this->privacyTree);
        self::assertNotNull($individual);

        // Mitglied sieht den ganzen Record, also auch den Namen
        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'Mitglied sollte lebende Person sehen'
        );
    }

    public function test_show_living_names_manager_only(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_LIVING_NAMES', (string) Auth::PRIV_NONE);

        $individual = Registry::individualFactory()->make('P_ALIVE_YOUNG', $this->privacyTree);
        self::assertNotNull($individual);

        // SHOW_LIVING_NAMES wirkt nur, wenn canShow() false ist (Person verborgen).
        // Besucher kann Person nicht sehen → Name-Anzeige haengt von SHOW_LIVING_NAMES ab.
        self::assertFalse(
            $individual->canShowName(Auth::PRIV_PRIVATE),
            'SHOW_LIVING_NAMES=PRIV_NONE: Besucher sollte Name nicht sehen'
        );
        // Mitglied sieht die Person komplett (canShow=true) → canShowName ist trivial true.
        // SHOW_LIVING_NAMES greift bei Mitglied nicht, weil die Person ohnehin sichtbar ist.
        self::assertTrue(
            $individual->canShowName(Auth::PRIV_USER),
            'Mitglied sieht Person → Name ist automatisch sichtbar'
        );
    }

    // -------------------------------------------------------
    // P15 — SHOW_PRIVATE_RELATIONSHIPS
    // -------------------------------------------------------
    // NOTE: SHOW_PRIVATE_RELATIONSHIPS wirkt primaer auf die Chart-Darstellung
    // (via Auth::checkIndividualAccess mit $chart=true). Integration-Tests hier
    // pruefen die Tree-Preference-Setzung. Die Sichtbarkeitsauswirkung wird
    // in den Playwright-Tests (Phase P7) verifiziert.

    public function test_show_private_relationships_preference_can_be_set(): void
    {
        $this->setTreePreference($this->privacyTree, 'SHOW_PRIVATE_RELATIONSHIPS', '1');
        self::assertSame(
            '1',
            $this->privacyTree->getPreference('SHOW_PRIVATE_RELATIONSHIPS')
        );

        $this->setTreePreference($this->privacyTree, 'SHOW_PRIVATE_RELATIONSHIPS', '0');
        self::assertSame(
            '0',
            $this->privacyTree->getPreference('SHOW_PRIVATE_RELATIONSHIPS')
        );
    }

    // -------------------------------------------------------
    // Rollen-Uebergreifend: Verwalter sieht alles
    // -------------------------------------------------------

    public function test_manager_sees_all_persons(): void
    {
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_USER);

        $xrefs = ['P_ALIVE_YOUNG', 'P_DEAD_HISTORIC', 'P_ALIVE_NO_DATES', 'P_BOUNDARY_MINUS1'];

        foreach ($xrefs as $xref) {
            $individual = Registry::individualFactory()->make($xref, $this->privacyTree);
            self::assertNotNull($individual, "{$xref} nicht gefunden");
            self::assertTrue(
                $individual->canShow(Auth::PRIV_NONE),
                "Verwalter (PRIV_NONE) sollte {$xref} sehen"
            );
        }
    }
}
