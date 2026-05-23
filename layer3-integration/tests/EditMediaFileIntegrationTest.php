<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\MediaFactoryInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\EditMediaFileAction;
use Fisharebest\Webtrees\Http\RequestHandlers\EditMediaFileModal;
use Fisharebest\Webtrees\Media;
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
 * @see docs/tds_conditions_ref.md G28
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditMediaFileModalTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditMediaFileAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditMediaFileModal
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

    /**
     * Redirect auf Tree-Seite, wenn die MediaFactory ein Media-Objekt
     * ohne passende Media-Datei liefert (z. B. nicht existierende fact_id).
     *
     * Der Handler darf in diesem Fall kein 500 erzeugen, sondern
     * mit HTTP 302 zur Baumseite weiterleiten.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditMediaFileActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_to_tree_page_when_media_file_not_found(): void
    {
        // Arrange — Media-Stub ohne passende Media-Datei
        $media = self::createStub(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canShow')->willReturn(true);
        $media->method('canEdit')->willReturn(true);
        $media->method('mediaFiles')->willReturn(collect([]));

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects($this->once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);

        Registry::mediaFactory($media_factory);

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'folder'   => '',
                'new_file' => 'photo.jpg',
                'remote'   => '',
                'title'    => '',
                'type'     => '',
            ],
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'M1',
                'fact_id' => 'nonexistent',
            ],
        );

        // Act
        $response = $this->handler->handle($request);

        // Assert — keine 500, Handler leitet auf Baumseite weiter
        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    // --- Portierte Tests aus port-layer2-test-doubles (EditMediaFileModal) ---

    /**
     * EditMediaFileModal: HTTP 403, wenn der angefragte Media-Datensatz
     * nicht existiert (MediaFactory liefert null → Auth::checkMediaAccess wirft
     * HttpNotFoundException → Handler antwortet mit STATUS_FORBIDDEN).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditMediaFileModalTest.php
     * @group ported-l2-doubles
     */
    public function test_modal_handle_returns_forbidden_when_media_does_not_exist(): void
    {
        // Arrange — MediaFactory liefert für 'X999' kein Media-Objekt
        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects($this->once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);

        Registry::mediaFactory($media_factory);

        $handler = new EditMediaFileModal(new MediaFileService(new PhpService()));
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_GET,
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X999',
                'fact_id' => 'abc',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — Auth::checkMediaAccess wirft HttpNotFoundException, Handler antwortet 403
        $this->assertSame(StatusCodeInterface::STATUS_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * EditMediaFileModal: HTTP 404, wenn der Media-Datensatz existiert,
     * die übergebene fact_id aber zu keiner Media-Datei passt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditMediaFileModalTest.php
     * @group ported-l2-doubles
     */
    public function test_modal_handle_returns_not_found_when_fact_id_not_matched(): void
    {
        // Arrange — Media-Stub: zugreifbar, aber ohne Media-Dateien
        $media = self::createStub(Media::class);
        $media->method('canShow')->willReturn(true);
        $media->method('canEdit')->willReturn(true);
        $media->method('mediaFiles')->willReturn(collect([]));

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects($this->once())
            ->method('make')
            ->with('M100', $this->tree)
            ->willReturn($media);

        Registry::mediaFactory($media_factory);

        $handler = new EditMediaFileModal(new MediaFileService(new PhpService()));
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_GET,
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'M100',
                'fact_id' => 'nonexistent',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — keine passende Media-Datei → 404
        $this->assertSame(StatusCodeInterface::STATUS_NOT_FOUND, $response->getStatusCode());
    }
}
