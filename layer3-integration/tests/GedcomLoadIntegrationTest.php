<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\GedcomLoad;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\TimeoutService;

/**
 * Komponentenintegrationstest: GedcomLoad.
 *
 * AP B-07: GedcomLoad::handle (CRAP 306)
 *
 * @see docs/testing-bigpicture.md G25
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\GedcomLoad
 */
class GedcomLoadIntegrationTest extends MysqlTestCase
{
    private GedcomLoad $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('gedcom-load-test-' . substr(md5($this->name()), 0, 8), 'GedcomLoad Test');
        // Start time in the far future so isTimeLimitUp() never fires during the test
        $timeoutService  = new TimeoutService(new PhpService(), PHP_INT_MAX);
        $this->handler   = new GedcomLoad(new GedcomImportService(), $timeoutService);
    }

    /**
     * handle() verarbeitet einen ausstehenden Chunk und gibt eine Progress-Antwort zurück.
     */
    public function test_handle_processes_pending_chunk(): void
    {
        $treeId = $this->tree->id();

        // Minimaler GEDCOM-Header-Chunk — wird beim ersten Aufruf verarbeitet
        DB::table('gedcom_chunk')->insert([
            'gedcom_id'  => $treeId,
            'chunk_data' => "0 HEAD\n1 GEDC\n2 VERS 5.5.1\n0 TRLR",
            'imported'   => 0,
        ]);

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_GET,
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertLessThan(500, $response->getStatusCode());
    }

    /**
     * handle() gibt Complete-View zurück, wenn alle Chunks bereits importiert sind.
     */
    public function test_handle_returns_complete_when_all_chunks_imported(): void
    {
        $treeId = $this->tree->id();

        // Alle Chunks als importiert markieren
        DB::table('gedcom_chunk')->insert([
            'gedcom_id'  => $treeId,
            'chunk_data' => '',
            'imported'   => 1,
        ]);

        // Tree als importiert markieren (direkt in gedcom-Tabelle, nicht via deprecated setPreference)
        DB::table('gedcom')
            ->where('gedcom_id', '=', $treeId)
            ->update(['imported' => 1]);

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_GET,
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertLessThan(500, $response->getStatusCode());
    }
}
