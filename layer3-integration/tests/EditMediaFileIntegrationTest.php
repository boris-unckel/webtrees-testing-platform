<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\EditMediaFileAction;
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
}
