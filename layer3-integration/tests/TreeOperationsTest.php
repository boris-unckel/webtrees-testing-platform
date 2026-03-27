<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Komponentenintegrationstest: Baum-Operationen gegen MySQL.
 *
 * Prüft Baum-Erstellung, -Löschung und GEDCOM-Export.
 *
 * @covers \Fisharebest\Webtrees\Services\TreeService
 * @covers \Fisharebest\Webtrees\Services\GedcomExportService
 * @see docs/testing-bigpicture-prompt.md G13, G16
 */
class TreeOperationsTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * Baum erstellen und GEDCOM importieren → Baum existiert in DB.
     */
    public function test_create_tree_with_gedcom_import(): void
    {
        // Arrange & Act
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Assert
        $treeExists = DB::table('gedcom')
            ->where('gedcom_id', '=', $this->tree->id())
            ->exists();

        $this->assertTrue($treeExists, 'Baum muss in der DB existieren');
        $this->assertStringStartsWith('demo-', $this->tree->name());
    }

    /**
     * Baum löschen → alle zugehörigen Records werden entfernt.
     */
    public function test_delete_tree_removes_all_records(): void
    {
        // Arrange
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $treeId = $this->tree->id();

        // Act
        $this->treeService->delete($this->tree);
        $this->tree = null; // tearDown soll nicht nochmal löschen

        // Assert
        $individualsRemaining = DB::table('individuals')
            ->where('i_file', '=', $treeId)
            ->count();

        $this->assertSame(0, $individualsRemaining, 'Nach Löschung dürfen keine Individuen übrig sein');
    }

    /**
     * G13 — GEDCOM-Export erzeugt valide Ausgabe.
     */
    public function test_export_produces_valid_gedcom(): void
    {
        // Arrange
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $container = Registry::container();
        $exportService = new GedcomExportService(
            $container->get(ResponseFactoryInterface::class),
            $container->get(StreamFactoryInterface::class),
        );

        // Act
        $resource = $exportService->export($this->tree);
        $exported = stream_get_contents($resource);

        // Assert — muss mit HEAD-Record beginnen und mit TRLR enden
        $this->assertStringStartsWith('0 HEAD', $exported, 'Export muss mit HEAD-Record beginnen');
        $this->assertStringContainsString('0 TRLR', $exported, 'Export muss TRLR-Record enthalten');

        // Muss INDI-Records enthalten
        $this->assertStringContainsString('0 @', $exported, 'Export muss XREF-Records enthalten');
    }

    /**
     * findAll() liefert mindestens den erstellten Baum.
     */
    public function test_find_all_trees_includes_created_tree(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $all = $this->treeService->all();

        $this->assertNotEmpty($all);
        // all() ist nach tree-name indexiert, nicht nach tree-id
        $this->assertTrue($all->has($this->tree->name()), 'findAll muss den erstellten Baum enthalten');
    }

    /**
     * Baum per Name finden → korrektes Tree-Objekt.
     */
    public function test_find_tree_by_name(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $all = $this->treeService->all();
        $found = $all->get($this->tree->name());

        $this->assertNotNull($found);
        $this->assertSame($this->tree->id(), $found->id());
    }

    /**
     * Baum-Titel entspricht dem bei Erstellung angegebenen Titel.
     */
    public function test_tree_has_correct_title(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $this->assertSame('Demo', $this->tree->title());
    }

    /**
     * Zwei Bäume mit gleichem Basis-Namen bekommen eindeutige Namen.
     */
    public function test_create_generates_unique_tree_names(): void
    {
        $this->createAndLoginAdmin();

        $tree1 = $this->treeService->create('unique-test-1', 'Test 1');
        $tree2 = $this->treeService->create('unique-test-2', 'Test 2');

        $this->assertNotSame($tree1->name(), $tree2->name());

        // Aufräumen
        $this->treeService->delete($tree1);
        $this->treeService->delete($tree2);
    }

    /**
     * G16 — Export mit Privacy-Level PRIV_HIDE enthält alle Records.
     */
    public function test_export_with_priv_hide_produces_valid_gedcom(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $container = Registry::container();
        $exportService = new GedcomExportService(
            $container->get(ResponseFactoryInterface::class),
            $container->get(StreamFactoryInterface::class),
        );

        $resource = $exportService->export($this->tree, access_level: Auth::PRIV_HIDE);
        $exported = stream_get_contents($resource);

        $this->assertStringStartsWith('0 HEAD', $exported);
        $this->assertStringContainsString('0 TRLR', $exported);

        preg_match_all('/^0 @\w+@ INDI\r?$/m', $exported, $indi_matches);
        preg_match_all('/^0 @\w+@ FAM\r?$/m', $exported, $fam_matches);

        $this->assertSame(72, count($indi_matches[0]), 'Export muss 72 INDI-Records enthalten');
        $this->assertSame(29, count($fam_matches[0]), 'Export muss 29 FAM-Records enthalten');
    }

    /**
     * G14 — Export sortiert Records nach XREF.
     */
    public function test_export_sorts_records_by_xref(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $container = Registry::container();
        $exportService = new GedcomExportService(
            $container->get(ResponseFactoryInterface::class),
            $container->get(StreamFactoryInterface::class),
        );

        $resource = $exportService->export($this->tree, sort_by_xref: true);
        $exported = stream_get_contents($resource);

        preg_match_all('/^0 @(\w+)@ INDI\r?$/m', $exported, $matches);
        $xrefs = $matches[1];
        $sorted = $xrefs;
        sort($sorted, SORT_STRING);

        $this->assertSame($sorted, $xrefs, 'INDI-Records müssen nach XREF sortiert sein');
    }

    /**
     * G15 — Export-Download liefert korrekte HTTP-Response.
     */
    public function test_export_download_response(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $container = Registry::container();
        $exportService = new GedcomExportService(
            $container->get(ResponseFactoryInterface::class),
            $container->get(StreamFactoryInterface::class),
        );

        $response = $exportService->downloadResponse($this->tree, false, 'UTF-8', 'none', 'CRLF', 'test', 'gedcom');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment', $response->getHeaderLine('content-disposition'));
    }

    /**
     * G19 — Export-Header enthält SOUR und GEDC.
     */
    public function test_export_header_contains_source_and_gedc(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $container = Registry::container();
        $exportService = new GedcomExportService(
            $container->get(ResponseFactoryInterface::class),
            $container->get(StreamFactoryInterface::class),
        );

        $resource = $exportService->export($this->tree);
        $exported = stream_get_contents($resource);

        $this->assertStringContainsString('1 SOUR', $exported, 'Export muss SOUR-Tag im Header haben');
        $this->assertStringContainsString('1 GEDC', $exported, 'Export muss GEDC-Tag im Header haben');
    }

    /**
     * G18 — Lange Zeilen werden mit CONC umgebrochen.
     */
    public function test_export_wraps_long_lines_with_conc(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'conc-test-' . substr(md5($this->name()), 0, 8);
        $tree = $this->treeService->create($uniqueName, 'CONC Test');
        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();

        $long_note = str_repeat('A', 500);
        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 NOTE {$long_note}";
        $this->gedcomImportService->importRecord($gedcom, $tree, false);

        $container = Registry::container();
        $exportService = new GedcomExportService(
            $container->get(ResponseFactoryInterface::class),
            $container->get(StreamFactoryInterface::class),
        );

        $resource = $exportService->export($tree);
        $exported = stream_get_contents($resource);

        $this->assertStringContainsString('2 CONC', $exported, 'Lange Zeilen müssen mit CONC umgebrochen werden');

        // Aufräumen
        $this->treeService->delete($tree);
    }
}
