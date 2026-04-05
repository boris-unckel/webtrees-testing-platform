<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Http\RequestHandlers\GedcomLoad;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\TimeoutService;
use Fisharebest\Webtrees\Tree;

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

    /** Minimaler GEDCOM-Header-Chunk (kein BOM, beginnt mit 0 HEAD) */
    private const HEAD_CHUNK = "0 HEAD\n1 GEDC\n2 VERS 5.5.1";

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

    // --- Neue EP-Tests (Runde 3, G25) ---

    /**
     * keep_media='0': alle Tabellen inkl. media und media_file werden beim ersten Chunk gelöscht (EP1 / B1b).
     */
    public function test_handle_deletes_media_tables_when_keep_media_is_zero(): void
    {
        $treeId = $this->tree->id();
        $this->tree->setPreference('keep_media', '0');

        DB::table('media')->insert([
            'm_id'     => 'MTESTDEL',
            'm_file'   => $treeId,
            'm_gedcom' => '0 @MTESTDEL@ OBJE',
        ]);
        DB::table('gedcom_chunk')->insert([
            'gedcom_id'  => $treeId,
            'chunk_data' => self::HEAD_CHUNK,
            'imported'   => 0,
        ]);

        $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_GET, attributes: ['tree' => $this->tree])
        );

        // offset=0 → Löschphase: media muss entfernt worden sein
        $this->assertSame(0, DB::table('media')->where('m_file', '=', $treeId)->count());
    }

    /**
     * keep_media='1': media- und media_file-Tabellen bleiben beim ersten Chunk erhalten (EP2 / B1a).
     */
    public function test_handle_preserves_media_tables_when_keep_media_is_one(): void
    {
        $treeId = $this->tree->id();
        $this->tree->setPreference('keep_media', '1');

        DB::table('media')->insert([
            'm_id'     => 'MTESTKEEP',
            'm_file'   => $treeId,
            'm_gedcom' => '0 @MTESTKEEP@ OBJE',
        ]);
        DB::table('gedcom_chunk')->insert([
            'gedcom_id'  => $treeId,
            'chunk_data' => self::HEAD_CHUNK,
            'imported'   => 0,
        ]);

        $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_GET, attributes: ['tree' => $this->tree])
        );

        // offset=0, keep_media='1' → media bleibt erhalten
        $this->assertGreaterThan(0, DB::table('media')->where('m_file', '=', $treeId)->count());
    }

    /**
     * UTF-8 BOM am Anfang des ersten Chunks wird entfernt → kein Fail-View (EP3 / C2a).
     * Ohne BOM-Stripping würde str_starts_with('HEAD') fehlschlagen → import-fail zurückgegeben.
     */
    public function test_handle_strips_utf8_bom_from_first_chunk(): void
    {
        DB::table('gedcom_chunk')->insert([
            'gedcom_id'  => $this->tree->id(),
            'chunk_data' => UTF8::BYTE_ORDER_MARK . self::HEAD_CHUNK,
            'imported'   => 0,
        ]);

        $response = $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_GET, attributes: ['tree' => $this->tree])
        );
        $body = (string) $response->getBody();

        // BOM entfernt → Header erkannt → kein Fail
        $this->assertStringNotContainsString('no header record found', $body);
        // Progress-View zurückgegeben (nicht Fail-View)
        $this->assertStringContainsString('progress-bar', $body);
    }

    /**
     * Erster Chunk beginnt nicht mit '0 HEAD' → Fail-View mit Fehlermeldung (EP4 / C2b).
     */
    public function test_handle_returns_fail_when_first_chunk_lacks_head_record(): void
    {
        DB::table('gedcom_chunk')->insert([
            'gedcom_id'  => $this->tree->id(),
            'chunk_data' => "0 INDI @X1@ INDI\n1 NAME Test /Test/",
            'imported'   => 0,
        ]);

        $response = $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_GET, attributes: ['tree' => $this->tree])
        );
        $body = (string) $response->getBody();

        $this->assertStringContainsString('no header record found', $body);
    }

    /**
     * Alle Chunks importiert, Baum aber nicht als abgeschlossen markiert → Fail-View „no trailer" (EP5 / A1a).
     * DB-Default für imported ist 1 — muss explizit auf 0 gesetzt werden.
     * Tree-Objekt wird frisch aus DB geholt (in-memory imported=true sonst).
     */
    public function test_handle_returns_fail_when_all_chunks_imported_but_tree_not_imported(): void
    {
        $treeId = $this->tree->id();

        // DB-Default imported=1 überschreiben: kein TRLR vorhanden → noch nicht abgeschlossen
        DB::table('gedcom')->where('gedcom_id', '=', $treeId)->update(['imported' => 0]);

        // Frisches Tree-Objekt aus DB mit imported=false
        $freshRow  = DB::table('gedcom')->where('gedcom_id', '=', $treeId)->select(['gedcom.*'])->first();
        $freshTree = Tree::fromDB($freshRow);

        // Chunk als importiert markieren → offset === total
        DB::table('gedcom_chunk')->insert([
            'gedcom_id'  => $treeId,
            'chunk_data' => '',
            'imported'   => 1,
        ]);

        $response = $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_GET, attributes: ['tree' => $freshTree])
        );
        $body = (string) $response->getBody();

        $this->assertStringContainsString('no trailer record found', $body);
    }

    /**
     * Alle Chunks importiert und Baum als abgeschlossen markiert → Complete-View (EP6 / A1b).
     * DB-Default imported=1 (gesetzt von treeService->create) reicht für diesen Test.
     */
    public function test_handle_returns_complete_view_when_tree_imported(): void
    {
        // Chunk als importiert markieren → offset === total; tree.imported=1 per DB-Default
        DB::table('gedcom_chunk')->insert([
            'gedcom_id'  => $this->tree->id(),
            'chunk_data' => '',
            'imported'   => 1,
        ]);

        $response = $this->handler->handle(
            $this->createRequest(method: RequestMethodInterface::METHOD_GET, attributes: ['tree' => $this->tree])
        );
        $body = (string) $response->getBody();

        // import-complete.phtml enthält 'classList.remove'
        $this->assertStringContainsString('classList.remove', $body);
    }
}
