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
 * @see docs/tds_conditions_ref.md S47
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
     * Die Familie f1 (HUSB X1041 + WIFE X1030) ist im Fixture demo.ged fest verankert
     * (vgl. fixtures/demo.ged: "0 @f1@ FAM" mit HUSB @X1041@ und WIFE @X1030@).
     * Die Skip-Pfad-Vorsicht aus dem Vorgaenger-Test wird durch eine harte Pruefung des
     * Fixture-Imports ersetzt: schlaegt der Import fehl, soll der Test rot sein, nicht still uebersprungen.
     */
    public function test_get_individuals_parent_request_triggers_draw_person(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // FIX_SET-Voraussetzung: Familie f1 existiert im importierten Tree.
        // Verifiziert sowohl den Fixture-Inhalt als auch den GedcomImportService.
        $family = Registry::familyFactory()->make('f1', $this->tree);
        $this->assertNotNull($family, 'Fixture demo.ged muss Familie f1 enthalten (HUSB X1041 + WIFE X1030)');
        $this->assertSame('X1041', $family->husband()?->xref(), 'f1.HUSB muss X1041 sein');
        $this->assertSame('X1030', $family->wife()?->xref(), 'f1.WIFE muss X1030 sein');

        // Format: p{familyXref}@{order} → drawPerson wird auf dem Husband-Parent (X1041) aufgerufen.
        $request = 'p' . $family->xref() . '@1';

        $result = $this->tree_view->getIndividuals($this->tree, $request);

        // SUT gibt json_encode($r) zurueck. Verhaltens-Assertions statt non-empty/contains-'<':
        // - gueltiges JSON
        // - genau ein Eintrag (eine 'p'-Anfrage → ein drawPerson-Render)
        // - der gerenderte Person-Block enthaelt die abbr="X1041"-Marker aus TreeView::drawPerson
        //   (Husband wird vor Wife in getIndividuals bevorzugt: "$family->husband() ?? $family->wife()")
        $decoded = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertIsString($decoded[0]);
        $this->assertStringContainsString('abbr="X1041"', $decoded[0]);
        $this->assertStringContainsString('class="tv_box', $decoded[0]);
    }

    /**
     * getIndividuals mit 'c'-Request triggert drawChildren für eine bekannte Familie (EP3: Person mit Kindern).
     * Die Familie f1 (HUSB X1041 + WIFE X1030) hat in demo.ged genau vier Kinder
     * (X1052, X1063, X1074, X1085). Damit ist drawChildren deterministisch testbar.
     * Die Skip-Pfad-Vorsicht des Vorgaengers (X1030->spouseFamilies leer) wird durch eine harte
     * Pruefung des Fixture-Imports ersetzt: schlaegt der Import fehl, soll der Test rot sein, nicht
     * still uebersprungen.
     */
    public function test_get_individuals_children_request_triggers_draw_children(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        // FIX_SET-Voraussetzung: Familie f1 existiert mit genau vier Kindern im importierten Tree.
        // Verifiziert sowohl den Fixture-Inhalt als auch den GedcomImportService.
        $family = Registry::familyFactory()->make('f1', $this->tree);
        $this->assertNotNull($family, 'Fixture demo.ged muss Familie f1 enthalten');

        $child_xrefs = $family->children()
            ->map(static fn ($child) => $child->xref())
            ->all();
        $this->assertSame(
            ['X1052', 'X1063', 'X1074', 'X1085'],
            $child_xrefs,
            'f1 muss in demo.ged genau die Kinder X1052,X1063,X1074,X1085 enthalten',
        );

        // Format: c{familyXref,...} → drawChildren wird auf den Kindern der Familie aufgerufen.
        $request = 'c' . $family->xref();

        $result = $this->tree_view->getIndividuals($this->tree, $request);

        // SUT gibt json_encode($r) zurueck. Verhaltens-Assertions statt non-empty/contains-'<':
        // - gueltiges JSON
        // - genau ein Eintrag (eine 'c'-Anfrage → ein drawChildren-Render)
        // - der gerenderte Children-Block enthaelt fuer jedes Kind den abbr="<xref>"-Marker
        //   aus TreeView::drawPerson (drawChildren ruft pro Kind drawPerson(..., $gen=0) auf)
        $decoded = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertIsString($decoded[0]);
        $this->assertStringContainsString('class="tv_box', $decoded[0]);
        foreach ($child_xrefs as $xref) {
            $this->assertStringContainsString(
                'abbr="' . $xref . '"',
                $decoded[0],
                'drawChildren muss jedes Kind als abbr="<xref>" einbetten',
            );
        }
    }
}
