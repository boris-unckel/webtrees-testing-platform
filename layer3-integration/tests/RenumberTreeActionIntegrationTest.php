<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreeAction;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\TimeoutService;

/**
 * Komponentenintegrationstest: RenumberTreeAction.
 *
 * @see docs/testing-bigpicture.md P34
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreeAction
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
}
