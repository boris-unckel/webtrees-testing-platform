<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Tree;

/**
 * Tests fuer Privacy in Suchergebnissen.
 *
 * Geschuetzte Personen duerfen fuer unbefugte Rollen nicht in
 * Suchergebnissen auftauchen.
 *
 * @see docs/testing-bigpicture.md P24
 * @covers \Fisharebest\Webtrees\Services\SearchService::searchIndividuals
 */
class PrivacySearchTest extends PrivacyTestCase
{
    private Tree $privacyTree;
    private SearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privacyTree = $this->createPrivacyTree();
        $this->searchService = new SearchService($this->treeService);
        $this->setTreePreference($this->privacyTree, 'HIDE_LIVE_PEOPLE', '1');
        $this->setTreePreference($this->privacyTree, 'SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
    }

    // -------------------------------------------------------
    // P24 — Lebende Person in Suchergebnissen
    // -------------------------------------------------------

    public function test_visitor_search_living_person_not_in_results(): void
    {
        // Als Besucher suchen
        Auth::logout();

        $results = $this->searchService->searchIndividuals([$this->privacyTree], ['Engel']);

        // P_ALIVE_YOUNG (Thomas Engel) ist lebend → sollte nicht in Ergebnissen sein
        $xrefs = $results->map(fn($i) => $i->xref())->toArray();
        self::assertNotContains(
            'P_ALIVE_YOUNG',
            $xrefs,
            'Besucher sollte lebende Person (P_ALIVE_YOUNG) nicht in Suchergebnissen finden'
        );
    }

    public function test_member_search_living_person_in_results(): void
    {
        // Als Mitglied einloggen
        $member = $this->createUserWithRole('member', $this->privacyTree);
        $this->actAs($member);

        $results = $this->searchService->searchIndividuals([$this->privacyTree], ['Engel']);

        $xrefs = $results->map(fn($i) => $i->xref())->toArray();
        self::assertContains(
            'P_ALIVE_YOUNG',
            $xrefs,
            'Mitglied sollte lebende Person (P_ALIVE_YOUNG) in Suchergebnissen finden'
        );
    }

    // -------------------------------------------------------
    // P24 — RESN-Personen in Suchergebnissen
    // -------------------------------------------------------

    public function test_visitor_search_resn_confidential_not_in_results(): void
    {
        Auth::logout();

        $results = $this->searchService->searchIndividuals([$this->privacyTree], ['Schreiber']);

        $xrefs = $results->map(fn($i) => $i->xref())->toArray();
        self::assertNotContains(
            'P_RESN_CONFIDENTIAL',
            $xrefs,
            'Besucher sollte RESN-confidential-Person nicht in Suchergebnissen finden'
        );
    }

    public function test_manager_search_resn_confidential_in_results(): void
    {
        $manager = $this->createUserWithRole('manager', $this->privacyTree);
        $this->actAs($manager);

        $results = $this->searchService->searchIndividuals([$this->privacyTree], ['Schreiber']);

        $xrefs = $results->map(fn($i) => $i->xref())->toArray();
        self::assertContains(
            'P_RESN_CONFIDENTIAL',
            $xrefs,
            'Verwalter sollte RESN-confidential-Person in Suchergebnissen finden'
        );
    }

    public function test_visitor_search_resn_none_in_results(): void
    {
        Auth::logout();

        $results = $this->searchService->searchIndividuals([$this->privacyTree], ['Peters']);

        $xrefs = $results->map(fn($i) => $i->xref())->toArray();
        self::assertContains(
            'P_RESN_NONE',
            $xrefs,
            'Besucher sollte Person mit RESN none in Suchergebnissen finden'
        );
    }
}
