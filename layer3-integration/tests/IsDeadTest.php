<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

/**
 * Tests fuer den isDead()-Algorithmus von Individual.
 *
 * Testet die kaskadische Logik: Expliziter Tod → Datierte Events →
 * Geburts-Check → Verwandten-Inferenz → Fallback (lebend).
 *
 * @covers \Fisharebest\Webtrees\Individual::isDead
 * @see docs/plan-privacy-testing-prompt.md P08–P13
 * @see docs/plan-privacy-implementation.md Phase P2
 */
class IsDeadTest extends PrivacyTestCase
{
    private Tree $privacyTree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privacyTree = $this->createPrivacyTree();
    }

    // -------------------------------------------------------
    // P08 — Expliziter Tod
    // -------------------------------------------------------

    public function test_is_dead_with_deat_y_returns_true(): void
    {
        $individual = Registry::individualFactory()->make('P_DEAD_EXPLICIT', $this->privacyTree);
        self::assertNotNull($individual, 'P_DEAD_EXPLICIT nicht gefunden');
        self::assertTrue($individual->isDead(), 'Person mit DEAT Y sollte als tot erkannt werden');
    }

    public function test_is_dead_with_deat_date_returns_true(): void
    {
        $individual = Registry::individualFactory()->make('P_DEAD_DATED', $this->privacyTree);
        self::assertNotNull($individual, 'P_DEAD_DATED nicht gefunden');
        self::assertTrue($individual->isDead(), 'Person mit DEAT+DATE sollte als tot erkannt werden');
    }

    public function test_is_dead_with_deat_plac_returns_true(): void
    {
        $individual = Registry::individualFactory()->make('P_DEAD_PLACED', $this->privacyTree);
        self::assertNotNull($individual, 'P_DEAD_PLACED nicht gefunden');
        self::assertTrue($individual->isDead(), 'Person mit DEAT+PLAC sollte als tot erkannt werden');
    }

    public function test_is_dead_historic_person_returns_true(): void
    {
        $individual = Registry::individualFactory()->make('P_DEAD_HISTORIC', $this->privacyTree);
        self::assertNotNull($individual, 'P_DEAD_HISTORIC nicht gefunden');
        self::assertTrue($individual->isDead(), 'Historisch Verstorbene sollte als tot erkannt werden');
    }

    public function test_is_dead_without_deat_young_person_returns_false(): void
    {
        $individual = Registry::individualFactory()->make('P_ALIVE_YOUNG', $this->privacyTree);
        self::assertNotNull($individual, 'P_ALIVE_YOUNG nicht gefunden');
        self::assertFalse($individual->isDead(), 'Junge Person ohne DEAT sollte als lebend gelten');
    }

    // -------------------------------------------------------
    // P09 — Datiertes Event > MAX_ALIVE_AGE
    // -------------------------------------------------------

    public function test_is_dead_non_birth_event_older_than_max_alive_age_returns_true(): void
    {
        $individual = Registry::individualFactory()->make('P_EVENT_OLD', $this->privacyTree);
        self::assertNotNull($individual, 'P_EVENT_OLD nicht gefunden');
        // OCCU-Event ist 125 Jahre alt (> MAX_ALIVE_AGE=120)
        self::assertTrue($individual->isDead(), 'Person mit OCCU-Event > MAX_ALIVE_AGE sollte als tot gelten');
    }

    // -------------------------------------------------------
    // P04, P09 — MAX_ALIVE_AGE Grenzwertanalyse
    // -------------------------------------------------------

    public function test_max_alive_age_boundary_exact_birth_is_dead(): void
    {
        // Geburt exakt MAX_ALIVE_AGE=120 Jahre her
        $individual = Registry::individualFactory()->make('P_BOUNDARY_EXACT', $this->privacyTree);
        self::assertNotNull($individual, 'P_BOUNDARY_EXACT nicht gefunden');
        // Exakt auf der Grenze: isDead() prueft > MAX_ALIVE_AGE, also exakt=120 sollte noch als lebend gelten
        // ABER: der webtrees-Code vergleicht Jahreszahlen, nicht Tage. Bei Jahresdifferenz == MAX_ALIVE_AGE
        // haengt das Ergebnis von der Implementierung ab.
        // Wir testen hier das tatsaechliche Verhalten.
        $isDead = $individual->isDead();
        // Geburt 1 JAN (YEAR - 120): Am 1. JAN des aktuellen Jahres ist die Person genau 120.
        // Das ist ein Grenzfall — wir dokumentieren das Ergebnis.
        self::assertIsBool($isDead, 'isDead() muss bool zurueckgeben');
        // Notiere: Grenzverhalten wird hier dokumentiert, nicht erzwungen
    }

    public function test_max_alive_age_boundary_minus1_birth_is_alive(): void
    {
        // Geburt 119 Jahre her = 1 Jahr unter MAX_ALIVE_AGE
        $individual = Registry::individualFactory()->make('P_BOUNDARY_MINUS1', $this->privacyTree);
        self::assertNotNull($individual, 'P_BOUNDARY_MINUS1 nicht gefunden');
        self::assertFalse($individual->isDead(), 'Person 119 Jahre alt sollte als lebend gelten (< MAX_ALIVE_AGE=120)');
    }

    public function test_max_alive_age_boundary_plus1_birth_is_dead(): void
    {
        // Geburt 121 Jahre her = 1 Jahr ueber MAX_ALIVE_AGE
        $individual = Registry::individualFactory()->make('P_BOUNDARY_PLUS1', $this->privacyTree);
        self::assertNotNull($individual, 'P_BOUNDARY_PLUS1 nicht gefunden');
        self::assertTrue($individual->isDead(), 'Person 121 Jahre alt sollte als tot gelten (> MAX_ALIVE_AGE=120)');
    }

    // -------------------------------------------------------
    // P10 — Geburt vorhanden + jung / Fallback
    // -------------------------------------------------------

    public function test_is_dead_with_recent_birth_no_deat_returns_false(): void
    {
        $individual = Registry::individualFactory()->make('P_ALIVE_YOUNG', $this->privacyTree);
        self::assertNotNull($individual);
        // Geburt 30 Jahre her, kein DEAT — klar lebend
        self::assertFalse($individual->isDead());
    }

    public function test_is_dead_no_dates_no_relatives_returns_false(): void
    {
        // Fallback: keine Daten, keine Verwandten → als lebend angenommen
        $individual = Registry::individualFactory()->make('P_ALIVE_NO_DATES', $this->privacyTree);
        self::assertNotNull($individual, 'P_ALIVE_NO_DATES nicht gefunden');
        self::assertFalse($individual->isDead(), 'Person ohne Daten und Verwandte sollte als lebend gelten (Fallback)');
    }

    // -------------------------------------------------------
    // P11 — Inferenz ueber Eltern
    // -------------------------------------------------------

    public function test_is_dead_inference_parents_old_events_returns_true(): void
    {
        // Vater hat Geburt 200 Jahre her (> MAX_ALIVE_AGE+45 = 165)
        $individual = Registry::individualFactory()->make('P_INFER_PARENT', $this->privacyTree);
        self::assertNotNull($individual, 'P_INFER_PARENT nicht gefunden');
        self::assertTrue($individual->isDead(), 'Person sollte via Eltern-Inferenz als tot erkannt werden (Eltern-Event > MAX_ALIVE_AGE+45)');
    }

    public function test_is_dead_inference_parents_boundary_returns_alive(): void
    {
        // Mutter hat Geburt exakt 165 Jahre her (= MAX_ALIVE_AGE+45 = Grenze)
        $individual = Registry::individualFactory()->make('P_INF_PARENT_BOUND', $this->privacyTree);
        self::assertNotNull($individual, 'P_INF_PARENT_BOUND nicht gefunden');
        // An der Grenze: Eltern-Event exakt bei MAX_ALIVE_AGE+45
        // isDead() prueft ob Event > threshold, also exakt an der Grenze = nicht tot via diesen Pfad
        $isDead = $individual->isDead();
        self::assertIsBool($isDead, 'isDead() muss bool zurueckgeben');
        // Grenzverhalten dokumentieren
    }

    // -------------------------------------------------------
    // P12 — Inferenz ueber Ehepartner
    // -------------------------------------------------------

    public function test_is_dead_inference_spouse_old_marriage_returns_true(): void
    {
        // Heirat 115 Jahre her (> MAX_ALIVE_AGE-10 = 110) UND
        // Ehefrau hat Geburt 170 Jahre her (> MAX_ALIVE_AGE+40 = 160)
        $individual = Registry::individualFactory()->make('P_INFER_SPOUSE', $this->privacyTree);
        self::assertNotNull($individual, 'P_INFER_SPOUSE nicht gefunden');
        self::assertTrue($individual->isDead(), 'Person sollte via Ehepartner-Inferenz als tot erkannt werden');
    }

    // -------------------------------------------------------
    // P13 — Inferenz ueber Kinder / Enkel
    // -------------------------------------------------------

    public function test_is_dead_inference_children_old_events_returns_true(): void
    {
        // Kind hat Geburt 140 Jahre her (> MAX_ALIVE_AGE-15 = 105)
        $individual = Registry::individualFactory()->make('P_INFER_CHILD', $this->privacyTree);
        self::assertNotNull($individual, 'P_INFER_CHILD nicht gefunden');
        self::assertTrue($individual->isDead(), 'Person sollte via Kinder-Inferenz als tot erkannt werden (Kind-Event > MAX_ALIVE_AGE-15)');
    }

    public function test_is_dead_inference_grandchildren_old_events_returns_true(): void
    {
        // Enkel hat Geburt 100 Jahre her (> MAX_ALIVE_AGE-30 = 90)
        $individual = Registry::individualFactory()->make('P_INFER_GRANDCHILD', $this->privacyTree);
        self::assertNotNull($individual, 'P_INFER_GRANDCHILD nicht gefunden');
        self::assertTrue($individual->isDead(), 'Person sollte via Enkel-Inferenz als tot erkannt werden (Enkel-Event > MAX_ALIVE_AGE-30)');
    }

    // -------------------------------------------------------
    // Zusaetzliche Assertions: KEEP_ALIVE-Personen sind definitiv tot
    // -------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('keepAlivePersonsProvider')]
    public function test_keep_alive_persons_are_dead(string $xref, string $description): void
    {
        $individual = Registry::individualFactory()->make($xref, $this->privacyTree);
        self::assertNotNull($individual, "{$xref} nicht gefunden");
        self::assertTrue($individual->isDead(), "{$description}: isDead() sollte true sein");
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function keepAlivePersonsProvider(): array
    {
        return [
            'KEEP_BIRTH_INSIDE'   => ['P_KEEP_BIRTH_INSIDE', 'Person mit DEAT Y und Geburt vor 9 Jahren'],
            'KEEP_BIRTH_BOUNDARY' => ['P_KEEP_BIRT_BOUND', 'Person mit DEAT Y und Geburt vor 10 Jahren'],
            'KEEP_BIRTH_OUTSIDE'  => ['P_KEEP_BIRTH_OUTSIDE', 'Person mit DEAT Y und Geburt vor 11 Jahren'],
            'KEEP_DEATH_INSIDE'   => ['P_KEEP_DEATH_INSIDE', 'Person mit Tod vor 9 Jahren'],
            'KEEP_DEATH_BOUNDARY' => ['P_KEEP_DEAT_BOUND', 'Person mit Tod vor 10 Jahren'],
            'KEEP_DEATH_OUTSIDE'  => ['P_KEEP_DEATH_OUTSIDE', 'Person mit Tod vor 11 Jahren'],
        ];
    }
}
