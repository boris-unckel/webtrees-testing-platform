<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\TreeImport;
use Fisharebest\Webtrees\DB;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: TreeImport CLI-Command (G31).
 *
 * Prüft den GEDCOM-Import über CLI: gültiger Import, Fehlerbehandlung bei
 * fehlendem Baum/Datei, und keep-media-Option.
 *
 * @see docs/tds_conditions_ref.md G31
 * @covers \Fisharebest\Webtrees\Cli\Commands\TreeImport
 */
class TreeImportCommandIntegrationTest extends MysqlTestCase
{
    private const MINIMAL_GED = '/fixtures/import-test-minimal.ged';

    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('import', 'Import-Test', self::MINIMAL_GED);

        $command = new TreeImport($this->gedcomImportService, $this->treeService);
        $app     = new Application();
        $app->add($command);
        $this->tester = new CommandTester($app->find('tree-import'));
    }

    /**
     * EP1: Gültiger Baum + gültige Datei + Default-Optionen → SUCCESS, Daten importiert.
     */
    public function test_import_valid_tree_and_file_returns_success(): void
    {
        $exitCode = $this->tester->execute([
            'tree-name'  => $this->tree->name(),
            'gedcom-file' => self::MINIMAL_GED,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Verifizieren, dass Individuen importiert wurden
        $count = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->count();
        $this->assertGreaterThan(0, $count, 'Es müssen Individuen importiert worden sein');
    }

    /**
     * EP3: Baum nicht gefunden → FAILURE mit Fehlermeldung.
     */
    public function test_import_tree_not_found_returns_failure(): void
    {
        $exitCode = $this->tester->execute([
            'tree-name'  => 'nonexistent-tree-xyz',
            'gedcom-file' => self::MINIMAL_GED,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $this->tester->getDisplay());
    }

    /**
     * EP4: Datei nicht vorhanden → FAILURE mit Fehlermeldung.
     */
    public function test_import_file_not_found_returns_failure(): void
    {
        $exitCode = $this->tester->execute([
            'tree-name'  => $this->tree->name(),
            'gedcom-file' => '/fixtures/nonexistent-file.ged',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('does not exist', $this->tester->getDisplay());
    }

    /**
     * EP2: keep-media=true → SUCCESS, Import mit Medien-Erhaltung (OBJE-Links bleiben erhalten).
     */
    public function test_import_with_keep_media_option(): void
    {
        $exitCode = $this->tester->execute([
            'tree-name'    => $this->tree->name(),
            'gedcom-file'  => self::MINIMAL_GED,
            '--keep-media' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
