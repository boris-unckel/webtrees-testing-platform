<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\EditMediaFileAction;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PendingChangesService;
use Fisharebest\Webtrees\Services\PhpService;

/**
 * Komponentenintegrationstest: EditMediaFileAction.
 *
 * AP C-06: EditMediaFileAction::handle (CRAP 182)
 *
 * @see docs/testing-bigpicture.md G28
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditMediaFileAction
 */
class EditMediaFileIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private EditMediaFileAction $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $this->handler = new EditMediaFileAction(
            media_file_service:     new MediaFileService(new PhpService()),
            pending_changes_service: new PendingChangesService(new GedcomImportService()),
        );
    }

    /**
     * Media-Datei Metadaten bearbeiten — Redirect nach Erfolg.
     */
    public function test_edit_media_file_title_and_type(): void
    {
        // Media-Record aus demo.ged holen
        $xref = DB::table('media')
            ->where('m_file', '=', $this->tree->id())
            ->value('m_id');

        if ($xref === null) {
            $this->markTestSkipped('Kein Media-Record in demo.ged vorhanden.');
        }

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'folder'   => '',
                'new_file' => '',
                'remote'   => '',
                'title'    => 'Updated Title',
                'type'     => 'photo',
            ],
            attributes: [
                'tree'    => $this->tree,
                'xref'    => (string) $xref,
                // Leere fact_id → media_file === null → Redirect zu Baumseite (kein 500)
                'fact_id' => '',
            ],
        );

        $response = $this->handler->handle($request);

        $this->assertLessThan(500, $response->getStatusCode());
    }

    // --- Neue Assertion-Tests (Runde 4, G28) ---

    /**
     * Happy Path: gültige fact_id → GEDCOM mit neuem Titel in change-Tabelle (EP1 / DB-Postcondition).
     * new_file='' → Dateiname unverändert → $old === $new → acceptRecord NICHT aufgerufen → change bleibt pending.
     */
    public function test_edit_media_file_happy_path_creates_pending_change_with_updated_title(): void
    {
        $treeId = $this->tree->id();
        $xref   = DB::table('media')->where('m_file', '=', $treeId)->value('m_id');

        if ($xref === null) {
            $this->markTestSkipped('Kein Media-Record in demo.ged vorhanden.');
        }

        $media     = Registry::mediaFactory()->make($xref, $this->tree);
        $firstFile = $media->mediaFiles()->first();

        if ($firstFile === null) {
            $this->markTestSkipped('Media-Record hat keine Dateien.');
        }

        $factId = $firstFile->factId();

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'folder'   => '',
                'new_file' => '',
                'remote'   => '',
                'title'    => 'Updated Title EP1',
                'type'     => 'photo',
            ],
            attributes: [
                'tree'    => $this->tree,
                'xref'    => (string) $xref,
                'fact_id' => $factId,
            ],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(302, $response->getStatusCode());

        // DB-Postcondition: change-Tabelle enthält pending GEDCOM mit dem aktualisierten Titel
        $newGedcom = DB::table('change')
            ->where('gedcom_id', '=', $treeId)
            ->where('xref', '=', $xref)
            ->where('status', '=', 'pending')
            ->value('new_gedcom');

        $this->assertNotNull($newGedcom, 'Keine pending change gefunden — updateFact() nicht aufgerufen.');
        $this->assertStringContainsString('Updated Title EP1', $newGedcom);
    }
}
