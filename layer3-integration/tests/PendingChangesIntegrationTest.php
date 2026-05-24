<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Algorithm\MyersDiff;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Factories\GedcomRecordFactory;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesAcceptChange;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesAcceptRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesAcceptTree;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogAction;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogData;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogDelete;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogDownload;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogPage;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesRejectChange;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesRejectRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesRejectTree;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\DatatablesService;
use Fisharebest\Webtrees\Services\PendingChangesService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;

/**
 * Komponentenintegrationstest: PendingChanges-Aktionen — P40.
 *
 * Tests:
 * - AcceptRecord mit ungültiger XREF → response() 200 (kein acceptRecord-Aufruf)
 * - RejectRecord mit ungültiger XREF → response() 200
 * - PendingChanges GET → 200
 * - Aus port-layer2-test-doubles importierte Methoden für AcceptChange,
 *   AcceptRecord, AcceptTree, LogAction, LogData, LogDelete, LogDownload,
 *   LogPage, RejectChange, RejectRecord, RejectTree (Mock-/Stub-basiert,
 *   prüfen die Handler-Verdrahtung gegen Service- und Factory-Schnittstellen).
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesAcceptChange
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesAcceptRecord
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesAcceptTree
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogData
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogDelete
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogDownload
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesRejectChange
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesRejectRecord
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesRejectTree
 * @see docs/tds_conditions_ref.md P40
 * @see docs/testquality_improve_P40.md
 */
class PendingChangesIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('p40-pending', 'P40 Pending', self::DEMO_GED);
    }

    /**
     * EP1: AcceptRecord — ungültige XREF → kein acceptRecord-Aufruf → response() 200.
     * Validator::isXref() lässt 'DOESNOTEXIST' durch; make() gibt null zurück.
     */
    public function test_accept_record_unknown_xref_returns_200(): void
    {
        $handler = new PendingChangesAcceptRecord(
            Registry::container()->get(PendingChangesService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'DOESNOTEXIST',
            ],
        );

        $response = $handler->handle($request);

        // response() in webtrees returns HTTP 204 (No Content)
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * EP3: RejectRecord — ungültige XREF → kein rejectRecord-Aufruf → response() 204.
     */
    public function test_reject_record_unknown_xref_returns_204(): void
    {
        $handler = new PendingChangesRejectRecord(
            Registry::container()->get(PendingChangesService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'DOESNOTEXIST',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * EP5: PendingChanges GET → 200.
     */
    public function test_pending_changes_page_returns_200(): void
    {
        $handler = new PendingChanges(
            Registry::container()->get(PendingChangesService::class),
        );

        $request = $this->createRequest(
            query: ['url' => 'https://webtrees.test/'],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * PendingChanges: Custom-Limit aus Query-Parameter `n` wird an Service::pendingChanges() durchgereicht.
     *
     * Verifiziert per Mock, dass `pendingXrefs($tree)` und `pendingChanges($tree, 50)`
     * mit den erwarteten Argumenten aufgerufen werden.
     *
     * @group ported-l2-doubles
     */
    public function test_pending_changes_passes_custom_limit_to_service(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');
        $tree->method('id')->willReturn(1);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service->expects(self::once())
            ->method('pendingXrefs')
            ->with($tree)
            ->willReturn(new Collection());
        $pending_changes_service->expects(self::once())
            ->method('pendingChanges')
            ->with($tree, 50)
            ->willReturn([]);

        $handler = new PendingChanges($pending_changes_service);
        $request = $this->createRequest(
            query: ['n' => '50', 'url' => 'https://webtrees.test/'],
            attributes: ['tree' => $tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AcceptChange: Handler delegiert (Tree, xref, change) an
     * PendingChangesService::acceptChange. Signatur seit Upstream-Commit
     * f24e5c62fe: 3 Argumente (Tree, string xref, string change), vorher
     * 2 (GedcomRecord, string). Der Handler holt den Record nicht mehr per
     * GedcomRecordFactory; der Service ueberninnt den Lookup selbst.
     *
     * @group ported-l2-doubles
     */
    public function test_accept_change_delegates_to_service(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service->expects(self::once())
            ->method('acceptChange')
            ->with($tree, 'I1', '42');

        $handler = new PendingChangesAcceptChange($pending_changes_service);
        $request = $this->createRequest(
            attributes: [
                'tree'   => $tree,
                'xref'   => 'I1',
                'change' => '42',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * Regressionspin Upstream-Commit f24e5c62fe ("Fix: cannot accept/reject
     * individual changes for record where all changes are still pending"):
     * Fuer einen XREF, fuer den `GedcomRecordFactory::make()` keinen
     * kanonischen Record liefert (alle Aenderungen noch in wt_change pending),
     * MUSS der Handler den Service trotzdem aufrufen. Pre-Fix hat der Handler
     * via `if ($record instanceof GedcomRecord)` den Aufruf uebersprungen
     * — das ist das Bug-Verhalten, das hier verhindert wird.
     *
     * @group ported-l2-doubles
     */
    public function test_accept_change_delegates_to_service_for_unknown_xref(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service->expects(self::once())
            ->method('acceptChange')
            ->with($tree, 'X999', '42');

        $handler = new PendingChangesAcceptChange($pending_changes_service);
        $request = $this->createRequest(
            attributes: [
                'tree'   => $tree,
                'xref'   => 'X999',
                'change' => '42',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * Verhaltens-definitiver Integrationstest fuer AcceptChange ueber den DB-Zustand
     * statt ueber Mock-Aufrufe. Pinnt die User-beobachtbare Wirkung:
     *
     *   - Eingangsbedingung: eine Pending-Change-Row in `wt_change` mit Status
     *     'pending' fuer einen XREF, fuer den noch keine kanonische Reihe in
     *     `wt_individuals` existiert (= fully-pending Record, der Bug-Fall aus
     *     Upstream-Commit f24e5c62fe).
     *   - Erwartete Wirkung: die `wt_change`-Row hat Status 'accepted' und die
     *     kanonische Reihe in `wt_individuals` ist angelegt.
     *
     * Dieser Test schlaegt fehl, wenn jemand den Upstream-Bug reanimiert (Handler
     * holt wieder per `Registry::gedcomRecordFactory()->make()` und springt bei
     * `null` ueber den Service-Aufruf hinweg) — dann bliebe die Row 'pending' und
     * die kanonische Reihe entstuende nicht.
     *
     * Ergaenzung zum signatur-orientierten Mock-Test
     * test_accept_change_delegates_to_service_for_unknown_xref: jener faengt nur
     * den Wechsel der Mock-Erwartung, dieser hier faengt jede Aenderung am
     * tatsaechlich am DB-Zustand sichtbaren Outcome.
     */
    public function test_accept_change_applies_pending_record_to_canonical_table(): void
    {
        // Arrange: pending Change fuer einen neuen, noch nicht freigegebenen XREF.
        $xref      = 'I_REGR_F24E5C_A';
        $newGedcom = "0 @{$xref}@ INDI\n1 NAME Regression /Subject f24e5c62fe Accept/\n1 SEX U\n";

        $changeId = (int) DB::table('change')->insertGetId([
            'gedcom_id'  => $this->tree->id(),
            'xref'       => $xref,
            'status'     => 'pending',
            'old_gedcom' => '',
            'new_gedcom' => $newGedcom,
            'user_id'    => Auth::id() ?? 0,
        ]);

        // Sanity-Vorbedingung: kein kanonischer Record und genau eine pending Change.
        self::assertSame(
            0,
            DB::table('individuals')->where('i_file', $this->tree->id())->where('i_id', $xref)->count(),
            'Vorbedingung: kein kanonischer Record fuer den XREF',
        );
        self::assertSame(
            'pending',
            (string) DB::table('change')->where('change_id', $changeId)->value('status'),
            'Vorbedingung: Change-Row steht auf pending',
        );

        $handler = Registry::container()->get(PendingChangesAcceptChange::class);
        $request = $this->createRequest(
            attributes: [
                'tree'   => $this->tree,
                'xref'   => $xref,
                'change' => (string) $changeId,
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Response 204 + DB-Zustand wie spezifiziert.
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());

        self::assertSame(
            'accepted',
            (string) DB::table('change')->where('change_id', $changeId)->value('status'),
            'Akzeptierte Change-Row muss Status accepted tragen.',
        );
        self::assertSame(
            1,
            DB::table('individuals')->where('i_file', $this->tree->id())->where('i_id', $xref)->count(),
            'Akzeptierter fully-pending Record muss in wt_individuals erscheinen — Regressionspin gegen Upstream-Commit f24e5c62fe.',
        );
    }

    /**
     * Verhaltens-definitiver Integrationstest fuer RejectChange ueber den DB-Zustand.
     *
     * Symmetrisch zu test_accept_change_applies_pending_record_to_canonical_table,
     * mit umgekehrter Wirkungs-Erwartung:
     *
     *   - Eingangsbedingung: pending Change-Row, kein kanonischer Record.
     *   - Erwartete Wirkung: Change-Row hat Status 'rejected', kanonische Reihe
     *     entsteht *nicht* (Reject wendet den GEDCOM-Patch nicht an).
     */
    public function test_reject_change_marks_row_rejected_without_applying_to_canonical_table(): void
    {
        // Arrange
        $xref      = 'I_REGR_F24E5C_R';
        $newGedcom = "0 @{$xref}@ INDI\n1 NAME Regression /Subject f24e5c62fe Reject/\n1 SEX U\n";

        $changeId = (int) DB::table('change')->insertGetId([
            'gedcom_id'  => $this->tree->id(),
            'xref'       => $xref,
            'status'     => 'pending',
            'old_gedcom' => '',
            'new_gedcom' => $newGedcom,
            'user_id'    => Auth::id() ?? 0,
        ]);

        $handler = Registry::container()->get(PendingChangesRejectChange::class);
        $request = $this->createRequest(
            attributes: [
                'tree'   => $this->tree,
                'xref'   => $xref,
                'change' => (string) $changeId,
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());

        self::assertSame(
            'rejected',
            (string) DB::table('change')->where('change_id', $changeId)->value('status'),
            'Abgelehnte Change-Row muss Status rejected tragen.',
        );
        self::assertSame(
            0,
            DB::table('individuals')->where('i_file', $this->tree->id())->where('i_id', $xref)->count(),
            'Abgelehnter Record darf nicht in wt_individuals erscheinen.',
        );
    }

    /**
     * AcceptRecord: existierender Record (kein Pending-Delete) → Service::acceptRecord() aufgerufen.
     *
     * @group ported-l2-doubles
     */
    public function test_accept_record_existing_record_accepts(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $record = self::createStub(GedcomRecord::class);
        $record->method('isPendingDeletion')->willReturn(false);
        $record->method('fullName')->willReturn('John Doe');

        $gedcom_record_factory = $this->createMock(GedcomRecordFactory::class);
        $gedcom_record_factory
            ->expects(self::once())
            ->method('make')
            ->with('X100', $tree)
            ->willReturn($record);

        Registry::gedcomRecordFactory($gedcom_record_factory);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service
            ->expects(self::once())
            ->method('acceptRecord')
            ->with($record);

        $handler = new PendingChangesAcceptRecord($pending_changes_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $tree,
                'xref' => 'X100',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * AcceptRecord: Pending-Deletion-Record → Service::acceptRecord() aufgerufen.
     *
     * @group ported-l2-doubles
     */
    public function test_accept_record_pending_deletion_accepts(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $record = self::createStub(GedcomRecord::class);
        $record->method('isPendingDeletion')->willReturn(true);
        $record->method('fullName')->willReturn('Jane Doe');

        $gedcom_record_factory = $this->createMock(GedcomRecordFactory::class);
        $gedcom_record_factory
            ->expects(self::once())
            ->method('make')
            ->with('X200', $tree)
            ->willReturn($record);

        Registry::gedcomRecordFactory($gedcom_record_factory);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service
            ->expects(self::once())
            ->method('acceptRecord')
            ->with($record);

        $handler = new PendingChangesAcceptRecord($pending_changes_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $tree,
                'xref' => 'X200',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * AcceptRecord: nicht-existierender Record (Mock-Factory) → kein acceptRecord-Aufruf.
     *
     * Ergänzt das existierende test_accept_record_unknown_xref_returns_200 mit dem
     * Mock-Factory-Pfad aus der Quelle.
     *
     * @group ported-l2-doubles
     */
    public function test_accept_record_skips_when_record_not_found_mocked(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $gedcom_record_factory = $this->createMock(GedcomRecordFactory::class);
        $gedcom_record_factory
            ->expects(self::once())
            ->method('make')
            ->with('X999', $tree)
            ->willReturn(null);

        Registry::gedcomRecordFactory($gedcom_record_factory);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service
            ->expects(self::never())
            ->method('acceptRecord');

        $handler = new PendingChangesAcceptRecord($pending_changes_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $tree,
                'xref' => 'X999',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * AcceptTree: ruft Service::acceptTree() mit Tree und Count auf.
     *
     * @group ported-l2-doubles
     */
    public function test_accept_tree_invokes_service_with_count(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);
        $tree->method('title')->willReturn('Test Tree');

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service
            ->expects(self::once())
            ->method('acceptTree')
            ->with($tree, 25);

        $handler = new PendingChangesAcceptTree($pending_changes_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            query: ['n' => '25'],
            attributes: ['tree' => $tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * LogAction: POST-Formular → Redirect auf LogPage mit übernommenen Parametern.
     *
     * @group ported-l2-doubles
     */
    public function test_log_action_redirects_to_log_page(): void
    {
        // Arrange
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'tree'     => 'test-tree',
                'from'     => '2026-01-01',
                'to'       => '2026-12-31',
                'type'     => 'pending',
                'oldged'   => '',
                'newged'   => '',
                'xref'     => 'I1',
                'username' => 'admin',
            ],
        );

        $handler = new PendingChangesLogAction();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringContainsString('test-tree', $response->getHeaderLine('location'));
        self::assertStringContainsString('from=2026-01-01', $response->getHeaderLine('location'));
    }

    /**
     * LogData: delegiert die Query an DatatablesService und gibt dessen Antwort weiter.
     *
     * @group ported-l2-doubles
     */
    public function test_log_data_delegates_query_to_datatables_service(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $query = self::createStub(Builder::class);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service->expects(self::once())
            ->method('changesQuery')
            ->willReturn($query);

        $expected_response = self::createStub(ResponseInterface::class);
        $expected_response->method('getStatusCode')->willReturn(StatusCodeInterface::STATUS_OK);

        $datatables_service = $this->createMock(DatatablesService::class);
        $datatables_service->expects(self::once())
            ->method('handleQuery')
            ->willReturn($expected_response);

        $myers_diff = self::createStub(MyersDiff::class);

        $handler = new PendingChangesLogData($datatables_service, $myers_diff, $pending_changes_service);
        $request = $this->createRequest(attributes: ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * LogDelete: ruft Service::changesQuery()->delete() auf und liefert 204.
     *
     * @group ported-l2-doubles
     */
    public function test_log_delete_deletes_changes_and_returns_no_content(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $query = $this->createMock(Builder::class);
        $query->expects(self::once())
            ->method('delete');

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service->expects(self::once())
            ->method('changesQuery')
            ->willReturn($query);

        $handler = new PendingChangesLogDelete($pending_changes_service);
        $request = $this->createRequest(attributes: ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * LogDownload: CSV-Antwort mit Content-Type und Filename-Header.
     *
     * @group ported-l2-doubles
     */
    public function test_log_download_returns_csv_response(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $row = (object) [
            'change_time' => '2026-01-01 12:00:00',
            'status'      => 'pending',
            'xref'        => 'I1',
            'old_gedcom'  => '0 @I1@ INDI',
            'new_gedcom'  => '0 @I1@ INDI\n1 NAME Test /User/',
            'user_name'   => 'admin',
            'gedcom_name' => 'tree1',
        ];

        $query = self::createStub(Builder::class);
        $query->method('get')->willReturn(new Collection([$row]));

        $pending_changes_service = self::createStub(PendingChangesService::class);
        $pending_changes_service->method('changesQuery')->willReturn($query);

        $handler = new PendingChangesLogDownload($pending_changes_service);
        $request = $this->createRequest(attributes: ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('text/csv; charset=UTF-8', $response->getHeaderLine('content-type'));
        self::assertSame('attachment; filename="changes.csv"', $response->getHeaderLine('content-disposition'));
    }

    /**
     * LogDownload: CSV-Body enthält die Zeilen-Inhalte.
     *
     * @group ported-l2-doubles
     */
    public function test_log_download_returns_body_content(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $row = (object) [
            'change_time' => '2026-01-01 12:00:00',
            'status'      => 'pending',
            'xref'        => 'I1',
            'old_gedcom'  => '',
            'new_gedcom'  => '0 @I1@ INDI',
            'user_name'   => 'admin',
            'gedcom_name' => 'tree1',
        ];

        $query = self::createStub(Builder::class);
        $query->method('get')->willReturn(new Collection([$row]));

        $pending_changes_service = self::createStub(PendingChangesService::class);
        $pending_changes_service->method('changesQuery')->willReturn($query);

        $handler = new PendingChangesLogDownload($pending_changes_service);
        $request = $this->createRequest(attributes: ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        $body = (string) $response->getBody();
        self::assertStringContainsString('2026-01-01 12:00:00', $body);
        self::assertStringContainsString('pending', $body);
        self::assertStringContainsString('I1', $body);
        self::assertStringContainsString('admin', $body);
        self::assertStringContainsString('tree1', $body);
    }

    /**
     * LogDownload: Anführungszeichen in Feldern werden im CSV korrekt verdoppelt.
     *
     * @group ported-l2-doubles
     */
    public function test_log_download_escapes_double_quotes_in_csv(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $row = (object) [
            'change_time' => '2026-01-01 12:00:00',
            'status'      => 'pending',
            'xref'        => 'I1',
            'old_gedcom'  => 'old "data"',
            'new_gedcom'  => 'new "data"',
            'user_name'   => 'user "name"',
            'gedcom_name' => 'tree "1"',
        ];

        $query = self::createStub(Builder::class);
        $query->method('get')->willReturn(new Collection([$row]));

        $pending_changes_service = self::createStub(PendingChangesService::class);
        $pending_changes_service->method('changesQuery')->willReturn($query);

        $handler = new PendingChangesLogDownload($pending_changes_service);
        $request = $this->createRequest(attributes: ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        $body = (string) $response->getBody();
        // Double quotes are escaped as "" in CSV
        self::assertStringContainsString('""data""', $body);
        self::assertStringContainsString('""name""', $body);
        self::assertStringContainsString('""1""', $body);
    }

    /**
     * LogDownload: keine Änderungen vorhanden → 204 No Content.
     *
     * @group ported-l2-doubles
     */
    public function test_log_download_with_empty_changes_returns_no_content(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('test');

        $query = self::createStub(Builder::class);
        $query->method('get')->willReturn(new Collection());

        $pending_changes_service = self::createStub(PendingChangesService::class);
        $pending_changes_service->method('changesQuery')->willReturn($query);

        $handler = new PendingChangesLogDownload($pending_changes_service);
        $request = $this->createRequest(attributes: ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * LogPage: Bootstrap mit TreeService/UserService → 200.
     *
     * @group ported-l2-doubles
     */
    public function test_log_page_returns_ok_response(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $tree_service = self::createStub(TreeService::class);
        $tree_service->method('titles')->willReturn([]);

        $user = self::createStub(User::class);
        $user->method('userName')->willReturn('admin');
        $user->method('getPreference')->willReturn('UTC');

        $user_service = self::createStub(UserService::class);
        $user_service->method('all')->willReturn(new Collection([$user]));

        $handler = new PendingChangesLogPage($tree_service, $user_service);
        $request = $this->createRequest(attributes: ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * RejectChange: Handler delegiert (Tree, xref, change) an
     * PendingChangesService::rejectChange. Signatur seit Upstream-Commit
     * f24e5c62fe: 3 Argumente (Tree, string xref, string change), vorher
     * 2 (GedcomRecord, string). Symmetrisch zum AcceptChange-Pfad.
     *
     * @group ported-l2-doubles
     */
    public function test_reject_change_delegates_to_service(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service->expects(self::once())
            ->method('rejectChange')
            ->with($tree, 'I1', '42');

        $handler = new PendingChangesRejectChange($pending_changes_service);
        $request = $this->createRequest(
            attributes: [
                'tree'   => $tree,
                'xref'   => 'I1',
                'change' => '42',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * Regressionspin Upstream-Commit f24e5c62fe: symmetrisch zu
     * test_accept_change_delegates_to_service_for_unknown_xref. Auch fuer
     * einen XREF ohne kanonischen Record muss der Handler RejectChange
     * an den Service durchreichen — vor dem Upstream-Fix wurde der Aufruf
     * vom `if ($record instanceof GedcomRecord)`-Guard verschluckt.
     *
     * @group ported-l2-doubles
     */
    public function test_reject_change_delegates_to_service_for_unknown_xref(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service->expects(self::once())
            ->method('rejectChange')
            ->with($tree, 'X999', '42');

        $handler = new PendingChangesRejectChange($pending_changes_service);
        $request = $this->createRequest(
            attributes: [
                'tree'   => $tree,
                'xref'   => 'X999',
                'change' => '42',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * RejectRecord: existierender Record → Service::rejectRecord() aufgerufen.
     *
     * @group ported-l2-doubles
     */
    public function test_reject_record_existing_record_rejects(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $record = self::createStub(GedcomRecord::class);
        $record->method('fullName')->willReturn('John Doe');

        $gedcom_record_factory = $this->createMock(GedcomRecordFactory::class);
        $gedcom_record_factory
            ->expects(self::once())
            ->method('make')
            ->with('X100', $tree)
            ->willReturn($record);

        Registry::gedcomRecordFactory($gedcom_record_factory);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service
            ->expects(self::once())
            ->method('rejectRecord')
            ->with($record);

        $handler = new PendingChangesRejectRecord($pending_changes_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $tree,
                'xref' => 'X100',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * RejectRecord: nicht-existierender Record (Mock-Factory) → kein rejectRecord-Aufruf.
     *
     * Ergänzt das existierende test_reject_record_unknown_xref_returns_204 mit dem
     * Mock-Factory-Pfad aus der Quelle.
     *
     * @group ported-l2-doubles
     */
    public function test_reject_record_skips_when_record_not_found_mocked(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);

        $gedcom_record_factory = $this->createMock(GedcomRecordFactory::class);
        $gedcom_record_factory
            ->expects(self::once())
            ->method('make')
            ->with('X999', $tree)
            ->willReturn(null);

        Registry::gedcomRecordFactory($gedcom_record_factory);

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service
            ->expects(self::never())
            ->method('rejectRecord');

        $handler = new PendingChangesRejectRecord($pending_changes_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $tree,
                'xref' => 'X999',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * RejectTree: ruft Service::rejectTree() mit dem übergebenen Tree auf
     * und liefert 204 No Content zurück.
     *
     * @group ported-l2-doubles
     */
    public function test_reject_tree_invokes_service_with_tree(): void
    {
        // Arrange
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(1);
        $tree->method('title')->willReturn('Test Tree');

        $pending_changes_service = $this->createMock(PendingChangesService::class);
        $pending_changes_service
            ->expects(self::once())
            ->method('rejectTree')
            ->with($tree);

        $handler = new PendingChangesRejectTree($pending_changes_service);
        $request = $this->createRequest(attributes: ['tree' => $tree]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }
}
