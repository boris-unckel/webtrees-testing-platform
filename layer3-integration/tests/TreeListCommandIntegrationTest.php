<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\TreeList;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: TreeList CLI-Command (A16).
 *
 * Prüft die Baumauflistung in verschiedenen Formaten (table, csv, json),
 * das imported-Flag-Mapping (yes/no) und die Fehlerbehandlung bei ungültigem Format.
 *
 * @see docs/tds_conditions_ref.md A16
 * @covers \Fisharebest\Webtrees\Cli\Commands\TreeList
 */
class TreeListCommandIntegrationTest extends MysqlTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('treelist', 'TreeList-Test', '/fixtures/demo.ged');

        $command = new TreeList($this->treeService);
        $app     = new Application();
        $app->add($command);
        $this->tester = new CommandTester($app->find('tree-list'));
    }

    /**
     * EP1/B1: Tabellenformat — SUCCESS, enthält Baum-Daten.
     */
    public function test_table_format_lists_tree(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'table']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString($this->tree->title(), $display);
    }

    /**
     * EP2/B2: CSV-Format — SUCCESS, CSV-Struktur mit Header und Daten.
     */
    public function test_csv_format_lists_tree(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'csv']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('"ID"', $display);
        $this->assertStringContainsString('"Name"', $display);
        $this->assertStringContainsString($this->tree->name(), $display);
    }

    /**
     * EP3/B3: JSON-Format — SUCCESS, valides JSON mit erwarteten Feldern.
     */
    public function test_json_format_lists_tree(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $data = json_decode($this->tester->getDisplay(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $found = false;
        foreach ($data as $entry) {
            $this->assertArrayHasKey('id', $entry);
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('title', $entry);
            $this->assertArrayHasKey('media_folder', $entry);
            $this->assertArrayHasKey('imported', $entry);

            if ($entry['name'] === $this->tree->name()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Testbaum muss in JSON-Ausgabe enthalten sein');
    }

    /**
     * EP4/B4: Ungültiges Format — FAILURE mit Fehlermeldung.
     */
    public function test_invalid_format_returns_failure(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'yaml']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid format', $this->tester->getDisplay());
    }

    /**
     * B5: Imported-Flag wird als 'yes' oder 'no' dargestellt.
     */
    public function test_imported_flag_mapping(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $data = json_decode($this->tester->getDisplay(), true);

        foreach ($data as $entry) {
            $this->assertContains($entry['imported'], ['yes', 'no'],
                'imported-Feld muss "yes" oder "no" sein');
        }
    }

    /**
     * EP1/B6: Default-Format (kein --format) ist table — SUCCESS.
     */
    public function test_default_format_is_table(): void
    {
        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString($this->tree->title(), $this->tester->getDisplay());
    }
}
