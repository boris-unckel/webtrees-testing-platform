<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

/**
 * Tests fuer Relationship Privacy (Pfadlaengen-basierte Sichtbarkeit).
 *
 * @see docs/testing-bigpicture.md P22, P23
 * @covers \Fisharebest\Webtrees\Individual::canShowByType
 */
class RelationshipPrivacyTest extends PrivacyTestCase
{
    private Tree $privacyTree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privacyTree = $this->createPrivacyTree();
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
    }

    /**
     * Erstellt einen Member-User mit Relationship-Privacy-Einstellungen.
     */
    private function createRelationshipUser(string $xref, int $pathLength): UserInterface
    {
        $user = $this->createUserWithRole('member', $this->privacyTree);
        $this->privacyTree->setUserPreference($user, UserInterface::PREF_TREE_ACCOUNT_XREF, $xref);
        $this->privacyTree->setUserPreference($user, UserInterface::PREF_TREE_PATH_LENGTH, (string) $pathLength);
        return $user;
    }

    // -------------------------------------------------------
    // P22 — Pfadlaenge
    // -------------------------------------------------------

    public function test_relationship_close_person_visible_within_path_length(): void
    {
        // PATH_LENGTH=2 → Distanz=4 (webtrees verdoppelt intern: distance = pathLength * 2)
        $user = $this->createRelationshipUser('P_REL_USER', 2);
        $this->actAs($user);

        // P_REL_CLOSE: 2 GEDCOM-Schritte von P_REL_USER (Kind) → innerhalb Distanz 4
        $individual = Registry::individualFactory()->make('P_REL_CLOSE', $this->privacyTree);
        self::assertNotNull($individual, 'P_REL_CLOSE nicht gefunden');
        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'P_REL_CLOSE (2 Schritte): Mitglied mit PATH_LENGTH=2 sollte nahes Familienmitglied sehen'
        );
    }

    public function test_relationship_far_person_not_visible_beyond_path_length(): void
    {
        // PATH_LENGTH=2 → Distanz=4 (webtrees verdoppelt intern)
        $user = $this->createRelationshipUser('P_REL_USER', 2);
        $this->actAs($user);

        // P_REL_FAR: 6 GEDCOM-Schritte von P_REL_USER → ausserhalb Distanz 4
        $individual = Registry::individualFactory()->make('P_REL_FAR', $this->privacyTree);
        self::assertNotNull($individual, 'P_REL_FAR nicht gefunden');
        self::assertFalse(
            $individual->canShow(Auth::PRIV_USER),
            'P_REL_FAR (6 Schritte): Mitglied mit PATH_LENGTH=2 sollte entferntes Familienmitglied nicht sehen'
        );
    }

    public function test_relationship_unrelated_person_not_visible(): void
    {
        $user = $this->createRelationshipUser('P_REL_USER', 2);
        $this->actAs($user);

        // P_REL_UNRELATED: keine Familienverbindung → nicht sichtbar
        $individual = Registry::individualFactory()->make('P_REL_UNRELATED', $this->privacyTree);
        self::assertNotNull($individual, 'P_REL_UNRELATED nicht gefunden');
        self::assertFalse(
            $individual->canShow(Auth::PRIV_USER),
            'P_REL_UNRELATED: Mitglied sollte nicht-verwandte Person nicht sehen'
        );
    }

    public function test_relationship_path_length_zero_disables_restriction(): void
    {
        // PATH_LENGTH=0 → Relationship Privacy deaktiviert → alle sichtbar
        $user = $this->createRelationshipUser('P_REL_USER', 0);
        $this->actAs($user);

        $individual = Registry::individualFactory()->make('P_REL_UNRELATED', $this->privacyTree);
        self::assertNotNull($individual);
        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'PATH_LENGTH=0: Mitglied sollte alle Personen sehen (Restriction deaktiviert)'
        );
    }

    // -------------------------------------------------------
    // P23 — Kein XREF gesetzt (Fallback)
    // -------------------------------------------------------

    public function test_relationship_no_xref_fallback_all_visible(): void
    {
        // Mitglied mit PATH_LENGTH=3, aber OHNE PREF_TREE_ACCOUNT_XREF
        $user = $this->createUserWithRole('member', $this->privacyTree);
        $this->privacyTree->setUserPreference($user, UserInterface::PREF_TREE_PATH_LENGTH, '3');
        // Kein PREF_TREE_ACCOUNT_XREF gesetzt
        $this->actAs($user);

        $individual = Registry::individualFactory()->make('P_REL_UNRELATED', $this->privacyTree);
        self::assertNotNull($individual);
        self::assertTrue(
            $individual->canShow(Auth::PRIV_USER),
            'PATH_LENGTH=3 ohne XREF: Mitglied sollte alle Personen sehen (Fallback)'
        );
    }
}
