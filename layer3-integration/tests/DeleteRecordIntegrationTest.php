<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord;
use Fisharebest\Webtrees\Services\LinkedRecordService;

/**
 * Komponentenintegrationstest: DeleteRecord HTTP-Handler.
 *
 * EP-Matrix: Standard-Löschung (EP1) mit DB-Postcondition via change-Tabelle;
 * Familie-Kaskade (EP5): Einzel-Mitglied + keine Fakten → Familie mitgelöscht.
 * change-Tabelle: deleteRecord() schreibt immer new_gedcom='' (unabhängig von auto_accept).
 *
 * @see docs/tds_conditions_ref.md P32
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord
 */
class DeleteRecordIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';
    private const P32_GED  = '/fixtures/p32-delete-test.ged';
    private const SOUR_XREF = 'X1102';

    /**
     * Standard-Löschung: SOUR-Record löschen → change-Tabelle hat Eintrag mit new_gedcom='' (EP1).
     * deleteRecord() schreibt immer einen change-Eintrag — Postcondition wird direkt verifiziert.
     */
    public function test_delete_source_creates_pending_change_in_change_table(): void
    {
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('p32-delete', 'P32 Delete', self::DEMO_GED);
        $handler  = new DeleteRecord(new LinkedRecordService());
        $request  = $this->createRequest(attributes: ['tree' => $this->tree, 'xref' => self::SOUR_XREF]);
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue(
            $this->hasDeletionChange(self::SOUR_XREF),
            'change-Tabelle muss Löscheintrag (new_gedcom="") für ' . self::SOUR_XREF . ' enthalten'
        );
    }

    /**
     * Familie-Kaskade: Individual (@P1@) löschen → @F1@ hat danach genau 1 Mitglied und keine
     * Fakten → Familie wird automatisch mitgelöscht (EP5).
     *
     * Fixture p32-delete-test.ged: @P1@ HUSB + @P2@ WIFE in @F1@, keine Genealogie-Fakten.
     * Nach Löschen von @P1@: @F1@ hat nur noch @P2@ → Kaskade → @F1@ ebenfalls gelöscht.
     */
    public function test_delete_individual_cascades_to_empty_family(): void
    {
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('p32-cascade', 'P32 Cascade', self::P32_GED);
        $handler  = new DeleteRecord(new LinkedRecordService());
        $request  = $this->createRequest(attributes: ['tree' => $this->tree, 'xref' => 'P1']);
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
        // @P1@ selbst gelöscht
        $this->assertTrue(
            $this->hasDeletionChange('P1'),
            'change-Tabelle muss Löscheintrag für P1 enthalten'
        );
        // @F1@ kaskadiert gelöscht (nur noch 1 Mitglied + keine Fakten)
        $this->assertTrue(
            $this->hasDeletionChange('F1'),
            'change-Tabelle muss Kaskaden-Löscheintrag für F1 enthalten'
        );
    }

    /**
     * Unbekannte XREF: Auth::checkRecordAccess(null) wirft HttpNotFoundException —
     * der LinkedRecordService darf in diesem Fall nicht angefragt werden.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_throws_not_found_for_missing_record(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('p32-missing', 'P32 Missing');

        $linked_record_service = $this->createMock(LinkedRecordService::class);
        $linked_record_service->expects(self::never())->method('allLinkedRecords');

        $handler = new DeleteRecord($linked_record_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree, 'xref' => 'X_NONEXISTENT'],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * Hilfsmethode: change-Eintrag mit new_gedcom='' (Löschmarkierung) für $xref vorhanden?
     */
    private function hasDeletionChange(string $xref): bool
    {
        return DB::table('change')
            ->where('gedcom_id', '=', $this->tree->id())
            ->where('xref', '=', $xref)
            ->where('new_gedcom', '=', '')
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();
    }
}
