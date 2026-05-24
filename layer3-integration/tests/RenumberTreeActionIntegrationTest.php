<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreeAction;
use Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreePage;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\TimeoutService;

use function e;

/**
 * Komponentenintegrationstest: RenumberTreeAction.
 *
 * SEC-AUDIT-006 Regressionsabdeckung (verhaltens-definitiv):
 *   Malformed xrefs (Verstoß gegen Gedcom::REGEX_XREF = [A-Za-z0-9:_.-]{1,20})
 *   dürfen nicht in die rohen REPLACE()-Expressions des Renumber-Pfads
 *   gelangen — Defense-in-depth gegen Data-Corruption-Szenarien. Der Handler
 *   muss solche xrefs überspringen, ohne zu crashen und ohne sie umzubenennen.
 *
 * @see docs/tds_conditions_ref.md P34
 * @see docs/security-audit/tasks/SEC-AUDIT-006_renumber_tree_raw_expression.md
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreeAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreePage
 */
class RenumberTreeActionIntegrationTest extends MysqlTestCase
{
    private RenumberTreeAction $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create(
            'renumber-test-' . substr(md5($this->name()), 0, 8),
            'RenumberTree Test'
        );
        // PHP_INT_MAX als Start-Zeit → isTimeNearlyUp() feuert nie während des Tests
        $this->handler = new RenumberTreeAction(
            new AdminService(),
            new TimeoutService(new PhpService(), PHP_INT_MAX),
        );
    }

    /**
     * Keine Cross-Tree-Duplikate → kein Umbenennen, Redirect zurück (B2 / EP1).
     */
    public function test_renumber_tree_no_action_when_no_cross_tree_duplicates(): void
    {
        // Kein anderer Baum mit gleichen XREFs → duplicateXrefs() = []
        $countBefore = DB::table('individuals')->where('i_file', '=', $this->tree->id())->count();

        $response = $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_POST, attributes: ['tree' => $this->tree])
        );

        $this->assertSame(302, $response->getStatusCode());
        // Keine Änderungen in der individuals-Tabelle
        $this->assertSame($countBefore, DB::table('individuals')->where('i_file', '=', $this->tree->id())->count());
    }

    /**
     * Cross-Tree-Duplikat vorhanden, keine Pending-Edits → INDI-XREF wird umbenannt (B3 / EP2 + DB-Postcondition).
     * Setup: tree1 und tree2 haben beide INDI @DUPXREF@.
     * duplicateXrefs(tree1) gibt ['DUPXREF' => 'INDI'] zurück → XREF wird in tree1 umbenannt.
     */
    public function test_renumber_tree_renames_duplicate_individual_xref(): void
    {
        $tree1Id = $this->tree->id();

        // Zweiten Baum anlegen (für Cross-Tree-Konflikt) — uniqid() für Einmaligkeit über Testläufe hinweg
        $tree2 = $this->treeService->create(
            'rn2-' . uniqid(),
            'RenumberTree Test 2'
        );
        $tree2Id = $tree2->id();

        // Gleiche XREF in beiden Bäumen → Cross-Tree-Kollision
        DB::table('individuals')->insert([
            'i_file'   => $tree1Id,
            'i_id'     => 'DUPXREF',
            'i_rin'    => '',
            'i_sex'    => 'U',
            'i_gedcom' => '0 @DUPXREF@ INDI',
        ]);
        DB::table('individuals')->insert([
            'i_file'   => $tree2Id,
            'i_id'     => 'DUPXREF',
            'i_rin'    => '',
            'i_sex'    => 'U',
            'i_gedcom' => '0 @DUPXREF@ INDI',
        ]);

        // Keine Pending-Edits für tree1 → Guard wird nicht ausgelöst
        $this->assertFalse($this->tree->hasPendingEdit());

        $response = $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_POST, attributes: ['tree' => $this->tree])
        );

        $this->assertSame(302, $response->getStatusCode());

        // DB-Postcondition: DUPXREF existiert nicht mehr in tree1 (umbenannt)
        // Hinweis: treeService->create() legt automatisch @X1@ INDI an, daher hat tree1 > 1 Individuum.
        // Wichtig ist nur, dass DUPXREF umbenannt wurde.
        $this->assertSame(
            0,
            DB::table('individuals')->where('i_file', '=', $tree1Id)->where('i_id', '=', 'DUPXREF')->count(),
            'DUPXREF sollte in tree1 umbenannt worden sein.'
        );
    }

    /**
     * Cross-Tree-Duplikat vorhanden + Pending-Edits → Guard feuert, kein Umbenennen (B1 / EP4).
     */
    public function test_renumber_tree_blocked_when_pending_edits_and_duplicates(): void
    {
        $tree1Id = $this->tree->id();

        // Zweiten Baum anlegen — uniqid() für Einmaligkeit über Testläufe hinweg
        $tree2 = $this->treeService->create(
            'rn2b-' . uniqid(),
            'RenumberTree Test 2b'
        );
        $tree2Id = $tree2->id();

        // Cross-Tree-Duplikat aufbauen
        DB::table('individuals')->insert([
            'i_file'   => $tree1Id,
            'i_id'     => 'DUPXREF2',
            'i_rin'    => '',
            'i_sex'    => 'U',
            'i_gedcom' => '0 @DUPXREF2@ INDI',
        ]);
        DB::table('individuals')->insert([
            'i_file'   => $tree2Id,
            'i_id'     => 'DUPXREF2',
            'i_rin'    => '',
            'i_sex'    => 'U',
            'i_gedcom' => '0 @DUPXREF2@ INDI',
        ]);

        // Pending-Edit für tree1 einsetzen → hasPendingEdit() = true
        DB::table('change')->insert([
            'gedcom_id'  => $tree1Id,
            'xref'       => 'DUPXREF2',
            'old_gedcom' => '0 @DUPXREF2@ INDI',
            'new_gedcom' => '0 @DUPXREF2@ INDI\n1 NAME Test /Test/',
            'status'     => 'pending',
            'user_id'    => 1,
        ]);

        $this->assertTrue($this->tree->hasPendingEdit());

        $response = $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_POST, attributes: ['tree' => $this->tree])
        );

        $this->assertSame(302, $response->getStatusCode());

        // DB-Postcondition: DUPXREF2 muss noch in tree1 existieren (nicht umbenannt)
        $this->assertSame(
            1,
            DB::table('individuals')->where('i_file', '=', $tree1Id)->where('i_id', '=', 'DUPXREF2')->count(),
            'DUPXREF2 darf NICHT umbenannt worden sein (Guard hat gefeuert).'
        );
    }

    /**
     * SEC-AUDIT-006 — Malformed xref (z. B. mit Whitespace oder Quote) muss
     * vom Renumber-Loop übersprungen werden: kein SQL-Crash, kein Rename.
     *
     * Setup: zwei Bäume mit identischer Malformed-XREF → duplicateXrefs()
     * liefert sie als Cross-Tree-Kollision → der xref-Format-Guard im Loop
     * muss greifen.
     */
    public function test_sec_audit_006_malformed_xref_is_skipped_not_renamed(): void
    {
        $tree1Id = $this->tree->id();
        $tree2   = $this->treeService->create('rn-sec006-' . uniqid(), 'SEC AUDIT 006 Tree 2');
        $tree2Id = $tree2->id();

        // Beide Verletzen REGEX_XREF: [A-Za-z0-9:_.-]{1,20}
        $malformedXrefs = [
            'BAD XREF',   // Whitespace ist nicht erlaubt
            "X'INJECT",   // Single-Quote (klassische SQL-Injection-Signatur)
        ];

        foreach ($malformedXrefs as $bad) {
            DB::table('individuals')->insert([
                'i_file'   => $tree1Id,
                'i_id'     => $bad,
                'i_rin'    => '',
                'i_sex'    => 'U',
                'i_gedcom' => '0 INDI',
            ]);
            DB::table('individuals')->insert([
                'i_file'   => $tree2Id,
                'i_id'     => $bad,
                'i_rin'    => '',
                'i_sex'    => 'U',
                'i_gedcom' => '0 INDI',
            ]);
        }

        // Property 1: Handler darf nicht crashen — definierte 302-Antwort.
        $response = $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_POST, attributes: ['tree' => $this->tree])
        );
        $this->assertSame(302, $response->getStatusCode(), 'Handler must not crash on malformed xref');

        // Property 2: Malformed xrefs müssen in tree1 unverändert vorliegen
        // (Guard hat sie übersprungen, kein Rename, kein SQL ausgeführt).
        foreach ($malformedXrefs as $bad) {
            $this->assertSame(
                1,
                DB::table('individuals')->where('i_file', '=', $tree1Id)->where('i_id', '=', $bad)->count(),
                sprintf('Malformed xref [%s] must remain untouched (guard must skip).', $bad),
            );
        }
    }

    /**
     * RenumberTreePage::handle() rendert die Renumber-Übersicht für einen Baum
     * mit Cross-Tree-Duplikat-XREF: 200 OK, escapter Baumtitel im Response-Body
     * und das Renumber-Formular (POST → RenumberTreeAction) ist im Body
     * vorhanden — letzteres rendert die View nur, wenn duplicateXrefs() einen
     * nicht-leeren Treffer liefert (`<?php if (!empty($xrefs)) ?>`).
     *
     * Komplementär zu test_renumber_tree_page_handle_returns_ok_for_empty_tree
     * (leerer Baum, kein Formular) — hier wird der nicht-triviale Fall mit
     * Cross-Tree-Konflikt + gerendertem Formular gepinnt.
     *
     * @group ported-l2-doubles
     */
    public function test_renumber_tree_page_handle_renders_title_and_renumber_form(): void
    {
        // Frischen Primärbaum mit Titel anlegen, der ein '&' enthält — so kann
        // verifiziert werden, dass der Titel via e() entitisiert im Body landet
        // (rohes '&' darf nicht erscheinen, '&amp;' muss erscheinen).
        $titleRaw = 'Renumber Page L3SP-046 & ' . uniqid();
        $this->treeService->delete($this->tree);
        $this->tree = $this->treeService->create(
            'renum-page-pin-' . substr(md5($this->name()), 0, 8),
            $titleRaw,
        );
        $tree1Id = $this->tree->id();

        // Cross-Tree-Duplikat-XREF: Ein zweiter Baum mit identischer INDI-XREF
        // sorgt dafür, dass duplicateXrefs() einen nicht-leeren Treffer liefert
        // und die View damit das Formular rendert.
        $tree2 = $this->treeService->create(
            'renum-page-pin-other-' . uniqid(),
            'Renumber Page Sibling',
        );
        $tree2Id = $tree2->id();

        $dupXref = 'PAGEDUP' . strtoupper(substr(uniqid(), -6));
        foreach ([$tree1Id, $tree2Id] as $treeId) {
            DB::table('individuals')->insert([
                'i_file'   => $treeId,
                'i_id'     => $dupXref,
                'i_rin'    => '',
                'i_sex'    => 'U',
                'i_gedcom' => '0 @' . $dupXref . '@ INDI',
            ]);
        }

        $pageHandler = new RenumberTreePage(new AdminService());
        $request     = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: ['tree' => $this->tree],
        );

        $response = $pageHandler->handle($request);

        $body = (string) $response->getBody();

        // Property 1: Statuscode 200 — Page rendert ohne Fehler.
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Property 2: Der escape'te Baumtitel landet im Body (h1 / breadcrumb).
        // Der SUT pre-escaped den Titel via e() und konkateniert ihn in $title;
        // die View gibt $title unescaped aus — d. h. der Body muss den
        // entitisierten Titel enthalten.
        $this->assertStringContainsString(e($titleRaw), $body);

        // Property 3: Bei nicht-leerer Trefferliste rendert die View das POST-
        // Formular Richtung RenumberTreeAction. Das ist der diskriminierende
        // Render-Pfad gegenüber dem leeren Baum (kein <form …>).
        $this->assertStringContainsString('<form method="post"', $body);
        $this->assertStringContainsString('renumber', $body); // Form-Action enthält den Route-Namen
    }

    /**
     * RenumberTreePage::handle() liefert 200 OK für einen leeren Baum (GET-Render).
     *
     * @group ported-l2-doubles
     */
    public function test_renumber_tree_page_handle_returns_ok_for_empty_tree(): void
    {
        // Arrange
        $emptyTree = $this->treeService->create(
            'renum-page-' . uniqid(),
            'Renumber Page'
        );
        $pageHandler = new RenumberTreePage(new AdminService());
        $request     = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: ['tree' => $emptyTree]
        );

        // Act
        $response = $pageHandler->handle($request);

        // Assert
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
