<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

/**
 * Tests fuer RESN-Tags (Record-Level und Fact-Level) sowie default_resn.
 *
 * @see docs/testing-bigpicture.md P16, P17, P18, P19, P20, P21
 * @covers \Fisharebest\Webtrees\GedcomRecord::canShowRecord
 * @covers \Fisharebest\Webtrees\Fact::canShow
 */
class ResnPrivacyTest extends PrivacyTestCase
{
    private Tree $privacyTree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privacyTree = $this->createPrivacyTree();
        // Standard-Einstellungen fuer Privacy
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
    }

    // -------------------------------------------------------
    // P16 — RESN none (Record)
    // -------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('resnNoneRolesProvider')]
    public function test_resn_none_visible_to_all_roles(int $accessLevel, string $roleName): void
    {
        $individual = Registry::individualFactory()->make('P_RESN_NONE', $this->privacyTree);
        self::assertNotNull($individual, 'P_RESN_NONE nicht gefunden');

        self::assertTrue(
            $individual->canShow($accessLevel),
            "RESN none: {$roleName} sollte Person sehen (RESN none ueberschreibt Privacy)"
        );
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function resnNoneRolesProvider(): array
    {
        return [
            'Besucher'  => [Auth::PRIV_PRIVATE, 'Besucher'],
            'Mitglied'  => [Auth::PRIV_USER, 'Mitglied'],
            'Verwalter' => [Auth::PRIV_NONE, 'Verwalter'],
        ];
    }

    // -------------------------------------------------------
    // P17 — RESN privacy (Record)
    // -------------------------------------------------------

    public function test_resn_privacy_visitor_cannot_see(): void
    {
        $individual = Registry::individualFactory()->make('P_RESN_PRIVACY', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertFalse(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'RESN privacy: Besucher sollte Person nicht sehen'
        );
    }

    public function test_resn_privacy_member_can_see(): void
    {
        $individual = Registry::individualFactory()->make('P_RESN_PRIVACY', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'RESN privacy: Mitglied sollte Person sehen'
        );
    }

    public function test_resn_privacy_manager_can_see(): void
    {
        $individual = Registry::individualFactory()->make('P_RESN_PRIVACY', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertTrue(
            $individual->canShow(Auth::PRIV_NONE),
            'RESN privacy: Verwalter sollte Person sehen'
        );
    }

    // -------------------------------------------------------
    // P18 — RESN confidential (Record)
    // -------------------------------------------------------

    public function test_resn_confidential_visitor_cannot_see(): void
    {
        $individual = Registry::individualFactory()->make('P_RESN_CONFIDENTIAL', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertFalse(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'RESN confidential: Besucher sollte Person nicht sehen'
        );
    }

    public function test_resn_confidential_member_cannot_see(): void
    {
        $individual = Registry::individualFactory()->make('P_RESN_CONFIDENTIAL', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertFalse(
            $individual->canShow(Auth::PRIV_USER),
            'RESN confidential: Mitglied sollte Person nicht sehen'
        );
    }

    public function test_resn_confidential_manager_can_see(): void
    {
        $individual = Registry::individualFactory()->make('P_RESN_CONFIDENTIAL', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertTrue(
            $individual->canShow(Auth::PRIV_NONE),
            'RESN confidential: Verwalter sollte Person sehen'
        );
    }

    // -------------------------------------------------------
    // P19 — Fact-Level RESN
    // -------------------------------------------------------

    public function test_fact_resn_privacy_on_birt_person_visible_birt_hidden_for_visitor(): void
    {
        $individual = Registry::individualFactory()->make('P_FACT_RESN_BIRT', $this->privacyTree);
        self::assertNotNull($individual, 'P_FACT_RESN_BIRT nicht gefunden');

        // Person (verstorben) sollte sichtbar sein
        self::assertTrue(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'Person mit Fact-RESN auf BIRT sollte fuer Besucher sichtbar sein'
        );

        // BIRT-Fakt mit RESN privacy: Besucher sieht BIRT nicht
        $birtFact = null;
        foreach ($individual->facts(['BIRT']) as $fact) {
            $birtFact = $fact;
            break;
        }
        self::assertNotNull($birtFact, 'BIRT-Fakt nicht gefunden');
        self::assertFalse(
            $birtFact->canShow(Auth::PRIV_PRIVATE),
            'BIRT-Fakt mit RESN privacy: Besucher sollte Fakt nicht sehen'
        );
    }

    public function test_fact_resn_privacy_on_birt_member_sees_birt(): void
    {
        $individual = Registry::individualFactory()->make('P_FACT_RESN_BIRT', $this->privacyTree);
        self::assertNotNull($individual);

        $birtFact = null;
        foreach ($individual->facts(['BIRT']) as $fact) {
            $birtFact = $fact;
            break;
        }
        self::assertNotNull($birtFact, 'BIRT-Fakt nicht gefunden');
        self::assertTrue(
            $birtFact->canShow(Auth::PRIV_USER),
            'BIRT-Fakt mit RESN privacy: Mitglied sollte Fakt sehen'
        );
    }

    public function test_fact_resn_confidential_on_deat_member_cannot_see(): void
    {
        $individual = Registry::individualFactory()->make('P_FACT_RESN_DEAT', $this->privacyTree);
        self::assertNotNull($individual, 'P_FACT_RESN_DEAT nicht gefunden');

        // Person sichtbar
        self::assertTrue($individual->canShow(Auth::PRIV_PRIVATE));

        // DEAT-Fakt mit RESN confidential: Mitglied sieht nicht
        $deatFact = null;
        foreach ($individual->facts(['DEAT']) as $fact) {
            $deatFact = $fact;
            break;
        }
        self::assertNotNull($deatFact, 'DEAT-Fakt nicht gefunden');
        self::assertFalse(
            $deatFact->canShow(Auth::PRIV_USER),
            'DEAT-Fakt mit RESN confidential: Mitglied sollte Fakt nicht sehen'
        );
    }

    public function test_fact_resn_confidential_on_deat_manager_sees(): void
    {
        $individual = Registry::individualFactory()->make('P_FACT_RESN_DEAT', $this->privacyTree);
        self::assertNotNull($individual);

        $deatFact = null;
        foreach ($individual->facts(['DEAT']) as $fact) {
            $deatFact = $fact;
            break;
        }
        self::assertNotNull($deatFact, 'DEAT-Fakt nicht gefunden');
        self::assertTrue(
            $deatFact->canShow(Auth::PRIV_NONE),
            'DEAT-Fakt mit RESN confidential: Verwalter sollte Fakt sehen'
        );
    }

    public function test_fact_without_resn_visible_to_all(): void
    {
        // P_DEAD_HISTORIC hat BIRT und DEAT ohne RESN
        $individual = Registry::individualFactory()->make('P_DEAD_HISTORIC', $this->privacyTree);
        self::assertNotNull($individual);

        $birtFact = null;
        foreach ($individual->facts(['BIRT']) as $fact) {
            $birtFact = $fact;
            break;
        }
        self::assertNotNull($birtFact, 'BIRT-Fakt nicht gefunden bei P_DEAD_HISTORIC');
        self::assertTrue(
            $birtFact->canShow(Auth::PRIV_PRIVATE),
            'Fakt ohne RESN: Besucher sollte Fakt sehen'
        );
    }

    // -------------------------------------------------------
    // P20 — default_resn (Individuum)
    // -------------------------------------------------------

    public function test_default_resn_xref_restricts_entire_record(): void
    {
        // default_resn Eintrag per DB setzen: xref=P_EDIT_TARGET, tag_type=NULL → gesamter Record eingeschraenkt
        DB::table('default_resn')->insert([
            'gedcom_id'  => $this->privacyTree->id(),
            'xref'       => 'P_EDIT_TARGET',
            'tag_type'   => null,
            'resn'       => 'privacy',
        ]);

        // Cache leeren (weil Tree die default_resn Daten cached)
        $freshTree = $this->treeService->find($this->privacyTree->id());
        self::assertNotNull($freshTree);

        $individual = Registry::individualFactory()->make('P_EDIT_TARGET', $freshTree);
        self::assertNotNull($individual, 'P_EDIT_TARGET nicht gefunden');

        self::assertFalse(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'default_resn(xref, privacy): Besucher sollte eingeschraenkten Record nicht sehen'
        );
        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'default_resn(xref, privacy): Mitglied sollte Record sehen'
        );
    }

    // -------------------------------------------------------
    // P21 — default_resn (Faktentyp)
    // -------------------------------------------------------

    public function test_default_resn_tag_type_restricts_all_facts_of_type(): void
    {
        // default_resn: xref=NULL, tag_type=BIRT → alle BIRT-Fakten eingeschraenkt
        DB::table('default_resn')->insert([
            'gedcom_id'  => $this->privacyTree->id(),
            'xref'       => null,
            'tag_type'   => 'BIRT',
            'resn'       => 'privacy',
        ]);

        $freshTree = $this->treeService->find($this->privacyTree->id());
        self::assertNotNull($freshTree);

        // P_DEAD_HISTORIC hat BIRT ohne expliziten RESN
        $individual = Registry::individualFactory()->make('P_DEAD_HISTORIC', $freshTree);
        self::assertNotNull($individual);

        // Person insgesamt sichtbar
        self::assertTrue($individual->canShow(Auth::PRIV_PRIVATE));

        // BIRT sollte fuer Besucher eingeschraenkt sein
        $birtFact = null;
        foreach ($individual->facts(['BIRT']) as $fact) {
            $birtFact = $fact;
            break;
        }
        self::assertNotNull($birtFact);
        self::assertFalse(
            $birtFact->canShow(Auth::PRIV_PRIVATE),
            'default_resn(BIRT, privacy): Besucher sollte BIRT nicht sehen'
        );
        self::assertTrue(
            $birtFact->canShow(Auth::PRIV_USER),
            'default_resn(BIRT, privacy): Mitglied sollte BIRT sehen'
        );
    }

    public function test_default_resn_xref_and_tag_type_restricts_specific_fact(): void
    {
        // default_resn: xref=P_EDIT_TARGET, tag_type=DEAT → nur DEAT dieses Records eingeschraenkt
        DB::table('default_resn')->insert([
            'gedcom_id'  => $this->privacyTree->id(),
            'xref'       => 'P_EDIT_TARGET',
            'tag_type'   => 'DEAT',
            'resn'       => 'confidential',
        ]);

        $freshTree = $this->treeService->find($this->privacyTree->id());
        self::assertNotNull($freshTree);

        $individual = Registry::individualFactory()->make('P_EDIT_TARGET', $freshTree);
        self::assertNotNull($individual);

        // Person insgesamt sichtbar
        self::assertTrue($individual->canShow(Auth::PRIV_PRIVATE));

        // BIRT sollte sichtbar sein (nicht eingeschraenkt)
        $birtFact = null;
        foreach ($individual->facts(['BIRT']) as $fact) {
            $birtFact = $fact;
            break;
        }
        self::assertNotNull($birtFact);
        self::assertTrue(
            $birtFact->canShow(Auth::PRIV_PRIVATE),
            'BIRT-Fakt von P_EDIT_TARGET sollte fuer Besucher sichtbar sein (nur DEAT eingeschraenkt)'
        );

        // DEAT sollte fuer Mitglied eingeschraenkt sein (confidential)
        $deatFact = null;
        foreach ($individual->facts(['DEAT']) as $fact) {
            $deatFact = $fact;
            break;
        }
        self::assertNotNull($deatFact);
        self::assertFalse(
            $deatFact->canShow(Auth::PRIV_USER),
            'default_resn(P_EDIT_TARGET, DEAT, confidential): Mitglied sollte DEAT nicht sehen'
        );
        self::assertTrue(
            $deatFact->canShow(Auth::PRIV_NONE),
            'default_resn(P_EDIT_TARGET, DEAT, confidential): Verwalter sollte DEAT sehen'
        );
    }

    // -------------------------------------------------------
    // RESN auf Familien-Record (FAM)
    // -------------------------------------------------------

    public function test_family_record_visibility_via_registry(): void
    {
        $family = Registry::familyFactory()->make('F_REL_1', $this->privacyTree);
        self::assertNotNull($family, 'F_REL_1 nicht gefunden');

        // FAM ohne RESN: Sichtbarkeit haengt von den Mitgliedern ab
        // Verwalter sollte immer sehen
        self::assertTrue(
            $family->canShow(Auth::PRIV_NONE),
            'Verwalter sollte FAM-Record sehen'
        );
    }
}
