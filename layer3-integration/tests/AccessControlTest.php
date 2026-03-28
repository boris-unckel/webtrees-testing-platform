<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\PendingChangesService;
use Fisharebest\Webtrees\Tree;

/**
 * Tests fuer Zugriffskontrolle: Edit, Accept, Lock, auto_accept.
 *
 * @covers \Fisharebest\Webtrees\GedcomRecord::canEdit
 * @covers \Fisharebest\Webtrees\GedcomRecord::updateRecord
 * @see docs/plan-privacy-testing-prompt.md P27–P29
 * @see docs/plan-privacy-implementation.md Phase P6
 */
class AccessControlTest extends PrivacyTestCase
{
    private Tree $privacyTree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privacyTree = $this->createPrivacyTree();
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
    }

    // -------------------------------------------------------
    // P27 — Bearbeiter-Edit
    // -------------------------------------------------------

    public function test_editor_adds_fact_creates_pending_change(): void
    {
        $editor = $this->createUserWithRole('editor', $this->privacyTree);
        // auto_accept deaktiviert → Pending Changes bleiben stehen
        $this->privacyTree->setUserPreference($editor, UserInterface::PREF_AUTO_ACCEPT_EDITS, '');
        $this->actAs($editor);

        $individual = Registry::individualFactory()->make('P_EDIT_TARGET', $this->privacyTree);
        self::assertNotNull($individual, 'P_EDIT_TARGET nicht gefunden');
        self::assertTrue($individual->canEdit(), 'Editor sollte P_EDIT_TARGET bearbeiten koennen');

        // Fakt hinzufuegen (OCCU)
        $newGedcom = $individual->gedcom() . "\n1 OCCU Testberuf";
        $individual->updateRecord($newGedcom, true);

        // Pending Change in DB pruefen
        $pendingCount = DB::table('change')
            ->where('gedcom_id', '=', $this->privacyTree->id())
            ->where('xref', '=', 'P_EDIT_TARGET')
            ->where('status', '=', 'pending')
            ->count();

        self::assertGreaterThan(0, $pendingCount, 'Nach Edit sollte ein Pending Change in der DB existieren');
    }

    public function test_editor_with_auto_accept_change_immediately_accepted(): void
    {
        $editor = $this->createUserWithRole('editor', $this->privacyTree);
        // auto_accept ist ein User-Preference (nicht Tree-User-Preference)
        $editor->setPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS, '1');
        $this->actAs($editor);

        $individual = Registry::individualFactory()->make('P_EDIT_TARGET', $this->privacyTree);
        self::assertNotNull($individual);

        $newGedcom = $individual->gedcom() . "\n1 OCCU AutoAcceptBeruf";
        $individual->updateRecord($newGedcom, true);

        // Kein offener Pending Change mehr (auto-akzeptiert)
        $pendingCount = DB::table('change')
            ->where('gedcom_id', '=', $this->privacyTree->id())
            ->where('xref', '=', 'P_EDIT_TARGET')
            ->where('status', '=', 'pending')
            ->count();

        self::assertSame(0, $pendingCount, 'Mit auto_accept sollte kein Pending Change verbleiben');
    }

    public function test_member_cannot_edit(): void
    {
        $member = $this->createUserWithRole('member', $this->privacyTree);
        $this->actAs($member);

        $individual = Registry::individualFactory()->make('P_EDIT_TARGET', $this->privacyTree);
        self::assertNotNull($individual);
        self::assertFalse(
            $individual->canEdit(),
            'Mitglied sollte keinen Record bearbeiten koennen'
        );
    }

    public function test_visitor_cannot_edit(): void
    {
        $this->actAsVisitor();

        $individual = Registry::individualFactory()->make('P_EDIT_TARGET', $this->privacyTree);
        self::assertNotNull($individual);
        self::assertFalse(
            $individual->canEdit(),
            'Besucher sollte keinen Record bearbeiten koennen'
        );
    }

    // -------------------------------------------------------
    // P28 — Moderator-Akzeptanz
    // -------------------------------------------------------

    public function test_moderator_accepts_pending_change(): void
    {
        // Editor erstellt Pending Change
        $editor = $this->createUserWithRole('editor', $this->privacyTree);
        $this->privacyTree->setUserPreference($editor, UserInterface::PREF_AUTO_ACCEPT_EDITS, '');
        $this->actAs($editor);

        $individual = Registry::individualFactory()->make('P_EDIT_TARGET', $this->privacyTree);
        self::assertNotNull($individual);
        $newGedcom = $individual->gedcom() . "\n1 OCCU ModeratorTestBeruf";
        $individual->updateRecord($newGedcom, true);

        // Pending Change existiert
        $pendingChange = DB::table('change')
            ->where('gedcom_id', '=', $this->privacyTree->id())
            ->where('xref', '=', 'P_EDIT_TARGET')
            ->where('status', '=', 'pending')
            ->orderByDesc('change_id')
            ->first();
        self::assertNotNull($pendingChange, 'Pending Change sollte existieren');

        // Moderator akzeptiert
        $moderator = $this->createUserWithRole('moderator', $this->privacyTree);
        $this->actAs($moderator);

        $pendingChangesService = new PendingChangesService(new GedcomImportService());
        // Akzeptiere ueber acceptRecord (akzeptiert alle offenen Changes fuer diesen Record)
        $freshIndividual = Registry::individualFactory()->make('P_EDIT_TARGET', $this->privacyTree);
        self::assertNotNull($freshIndividual);
        $pendingChangesService->acceptRecord($freshIndividual);

        // Status sollte jetzt 'accepted' sein
        $acceptedCount = DB::table('change')
            ->where('gedcom_id', '=', $this->privacyTree->id())
            ->where('xref', '=', 'P_EDIT_TARGET')
            ->where('status', '=', 'accepted')
            ->count();
        self::assertGreaterThan(0, $acceptedCount, 'Nach Akzeptanz sollte der Status accepted sein');

        $pendingCount = DB::table('change')
            ->where('gedcom_id', '=', $this->privacyTree->id())
            ->where('xref', '=', 'P_EDIT_TARGET')
            ->where('status', '=', 'pending')
            ->count();
        self::assertSame(0, $pendingCount, 'Nach Akzeptanz sollte kein Pending Change verbleiben');
    }

    public function test_moderator_rejects_pending_change(): void
    {
        // Editor erstellt Pending Change
        $editor = $this->createUserWithRole('editor', $this->privacyTree);
        $this->privacyTree->setUserPreference($editor, UserInterface::PREF_AUTO_ACCEPT_EDITS, '');
        $this->actAs($editor);

        $individual = Registry::individualFactory()->make('P_EDIT_TARGET', $this->privacyTree);
        self::assertNotNull($individual);
        $newGedcom = $individual->gedcom() . "\n1 OCCU RejectTestBeruf";
        $individual->updateRecord($newGedcom, true);

        // Moderator verwirft
        $moderator = $this->createUserWithRole('moderator', $this->privacyTree);
        $this->actAs($moderator);

        $pendingChangesService = new PendingChangesService(new GedcomImportService());
        $freshIndividual = Registry::individualFactory()->make('P_EDIT_TARGET', $this->privacyTree);
        self::assertNotNull($freshIndividual);
        $pendingChangesService->rejectRecord($freshIndividual);

        // Kein offener Pending Change mehr
        $pendingCount = DB::table('change')
            ->where('gedcom_id', '=', $this->privacyTree->id())
            ->where('xref', '=', 'P_EDIT_TARGET')
            ->where('status', '=', 'pending')
            ->count();
        self::assertSame(0, $pendingCount, 'Nach Verwerfung sollte kein Pending Change verbleiben');

        // Rejected Status pruefen
        $rejectedCount = DB::table('change')
            ->where('gedcom_id', '=', $this->privacyTree->id())
            ->where('xref', '=', 'P_EDIT_TARGET')
            ->where('status', '=', 'rejected')
            ->count();
        self::assertGreaterThan(0, $rejectedCount, 'Nach Verwerfung sollte der Status rejected sein');
    }

    // -------------------------------------------------------
    // P29 — RESN locked
    // -------------------------------------------------------

    public function test_editor_cannot_edit_resn_locked_record(): void
    {
        $editor = $this->createUserWithRole('editor', $this->privacyTree);
        $this->actAs($editor);

        $individual = Registry::individualFactory()->make('P_RESN_LOCKED', $this->privacyTree);
        self::assertNotNull($individual, 'P_RESN_LOCKED nicht gefunden');
        self::assertFalse(
            $individual->canEdit(),
            'Editor sollte RESN-locked-Record nicht bearbeiten koennen'
        );
    }

    public function test_manager_can_edit_resn_locked_record(): void
    {
        $manager = $this->createUserWithRole('manager', $this->privacyTree);
        $this->actAs($manager);

        $individual = Registry::individualFactory()->make('P_RESN_LOCKED', $this->privacyTree);
        self::assertNotNull($individual, 'P_RESN_LOCKED nicht gefunden');
        self::assertTrue(
            $individual->canEdit(),
            'Verwalter sollte RESN-locked-Record bearbeiten koennen'
        );
    }

    public function test_resn_priv_locked_member_sees_but_cannot_edit(): void
    {
        $member = $this->createUserWithRole('member', $this->privacyTree);
        $this->actAs($member);

        $individual = Registry::individualFactory()->make('P_RESN_PRIV_LOCKED', $this->privacyTree);
        self::assertNotNull($individual, 'P_RESN_PRIV_LOCKED nicht gefunden');

        // Mitglied sieht den Record (RESN privacy = PRIV_USER)
        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'Mitglied sollte RESN privacy+locked sehen koennen'
        );

        // Mitglied kann nicht editieren (locked)
        self::assertFalse(
            $individual->canEdit(),
            'Mitglied sollte RESN privacy+locked nicht bearbeiten koennen'
        );
    }

    public function test_resn_priv_locked_visitor_cannot_see(): void
    {
        $individual = Registry::individualFactory()->make('P_RESN_PRIV_LOCKED', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertFalse(
            $individual->canShow(Auth::PRIV_PRIVATE),
            'Besucher sollte RESN privacy+locked nicht sehen koennen'
        );
    }

    public function test_resn_priv_locked_manager_sees_and_edits(): void
    {
        $manager = $this->createUserWithRole('manager', $this->privacyTree);
        $this->actAs($manager);

        $individual = Registry::individualFactory()->make('P_RESN_PRIV_LOCKED', $this->privacyTree);
        self::assertNotNull($individual);

        self::assertTrue(
            $individual->canShow(Auth::PRIV_NONE),
            'Verwalter sollte RESN privacy+locked sehen koennen'
        );
        self::assertTrue(
            $individual->canEdit(),
            'Verwalter sollte RESN privacy+locked bearbeiten koennen'
        );
    }

    public function test_editor_on_normal_record_can_edit(): void
    {
        $editor = $this->createUserWithRole('editor', $this->privacyTree);
        $this->actAs($editor);

        // P_EDIT_TARGET hat kein RESN → Editor sollte editieren koennen
        $individual = Registry::individualFactory()->make('P_EDIT_TARGET', $this->privacyTree);
        self::assertNotNull($individual);
        self::assertTrue(
            $individual->canEdit(),
            'Editor sollte normalen Record bearbeiten koennen'
        );
    }
}
