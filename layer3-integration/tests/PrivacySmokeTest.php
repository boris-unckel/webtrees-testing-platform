<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Registry;

/**
 * Smoke-Test fuer die Privacy-Test-Infrastruktur.
 *
 * Verifiziert, dass createPrivacyTree() funktioniert und die
 * importierten Personen ueber Registry abrufbar sind.
 *
 * @see docs/testing-bigpicture.md P01
 */
class PrivacySmokeTest extends PrivacyTestCase
{
    public function test_create_privacy_tree_returns_tree_with_id(): void
    {
        $tree = $this->createPrivacyTree();

        self::assertGreaterThan(0, $tree->id());
        self::assertStringContainsString('privacy', $tree->name());
    }

    public function test_privacy_tree_contains_expected_individuals(): void
    {
        $tree = $this->createPrivacyTree();

        // Stichprobe: einige Schluessel-Personen muessen vorhanden sein
        $expectedXrefs = [
            'P_DEAD_HISTORIC',
            'P_DEAD_EXPLICIT',
            'P_ALIVE_YOUNG',
            'P_ALIVE_NO_DATES',
            'P_BOUNDARY_EXACT',
            'P_RESN_NONE',
            'P_RESN_PRIVACY',
            'P_RESN_CONFIDENTIAL',
            'P_REL_USER',
            'P_REL_CLOSE',
            'P_REL_FAR',
            'P_REL_UNRELATED',
            'P_INFER_PARENT',
            'P_EDIT_TARGET',
            'P_RESN_LOCKED',
        ];

        foreach ($expectedXrefs as $xref) {
            $individual = Registry::individualFactory()->make($xref, $tree);
            self::assertNotNull($individual, "Individual {$xref} nicht im Privacy-Tree gefunden");
        }
    }

    public function test_privacy_tree_contains_expected_families(): void
    {
        $tree = $this->createPrivacyTree();

        $expectedFamXrefs = [
            'F_REL_1',
            'F_REL_CHAIN',
            'F_INFER_PARENT',
            'F_INFER_SPOUSE',
            'F_INFER_CHILD',
        ];

        foreach ($expectedFamXrefs as $xref) {
            $family = Registry::familyFactory()->make($xref, $tree);
            self::assertNotNull($family, "Family {$xref} nicht im Privacy-Tree gefunden");
        }
    }

    public function test_create_user_with_role_creates_member(): void
    {
        $tree = $this->createPrivacyTree();
        $member = $this->createUserWithRole('member', $tree);

        self::assertNotNull($member);
        self::assertStringContainsString('member', $member->userName());
    }

    public function test_create_user_with_role_creates_manager(): void
    {
        $tree = $this->createPrivacyTree();
        $manager = $this->createUserWithRole('manager', $tree);

        self::assertNotNull($manager);
        self::assertStringContainsString('manager', $manager->userName());
    }
}
