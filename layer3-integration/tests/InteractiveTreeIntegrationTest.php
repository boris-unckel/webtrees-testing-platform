<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Module\InteractiveTree\TreeView;
use Fisharebest\Webtrees\Registry;

/**
 * Komponentenintegrationstest: TreeView (InteractiveTree).
 *
 * drawPerson() (private, CRAP 1.122) und drawChildren() (private, CRAP 132) sind
 * nur über die public API erreichbar:
 * - getIndividuals(Tree, 'p{familyXref}@{order}') → drawPerson
 * - getIndividuals(Tree, 'c{familyXref,...}') → drawChildren
 * - getDetails(Individual) → drawPersonDetails
 *
 * @see docs/testing-bigpicture.md S47
 * @covers \Fisharebest\Webtrees\Module\InteractiveTree\TreeView
 */
class InteractiveTreeIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private TreeView $tree_view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tree_view = new TreeView('tree');
    }

    /**
     * getDetails gibt HTML-String für bekannte Person zurück und enthält deren XREF (EP5: Person mit Partner).
     * X1030 ist mit X1041 verheiratet → XREF sollte im Output erscheinen (CSS-ID, AJAX-Link).
     */
    public function test_get_details_returns_html_for_known_individual(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $individual = Registry::individualFactory()->make('X1030', $this->tree);
        $this->assertNotNull($individual);

        $result = $this->tree_view->getDetails($individual);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('X1030', $result);
    }

    /**
     * getIndividuals mit 'p'-Request triggert drawPerson für eine bekannte Familie (EP1: Person mit Eltern).
     * Output muss non-empty HTML enthalten.
     */
    public function test_get_individuals_parent_request_triggers_draw_person(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $individual = Registry::individualFactory()->make('X1030', $this->tree);
        $this->assertNotNull($individual);

        // Erste Elternfamilie verwenden, falls vorhanden
        $families = $individual->spouseFamilies();
        if ($families->isEmpty()) {
            $this->markTestSkipped('X1030 hat keine Partnerfamilien in demo.ged');
        }

        $familyXref = $families->first()->xref();
        // Format: p{familyXref}@{order} → drawPerson wird aufgerufen
        $request = 'p' . $familyXref . '@1';

        $result = $this->tree_view->getIndividuals($this->tree, $request);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<', $result);
    }

    /**
     * getIndividuals mit 'c'-Request triggert drawChildren für eine bekannte Familie (EP3: Person mit Kindern).
     * Output muss non-empty HTML enthalten.
     */
    public function test_get_individuals_children_request_triggers_draw_children(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $individual = Registry::individualFactory()->make('X1030', $this->tree);
        $this->assertNotNull($individual);

        // Erste Elternfamilie verwenden
        $families = $individual->spouseFamilies();
        if ($families->isEmpty()) {
            $this->markTestSkipped('X1030 hat keine Partnerfamilien in demo.ged');
        }

        $familyXref = $families->first()->xref();
        // Format: c{familyXref,...} → drawChildren wird aufgerufen
        $request = 'c' . $familyXref;

        $result = $this->tree_view->getIndividuals($this->tree, $request);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<', $result);
    }
}
