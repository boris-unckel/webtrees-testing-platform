<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\CompilePoFiles;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: CompilePoFiles CLI-Command (A15).
 *
 * Prüft die PO→PHP-Kompilierung: PO-Dateien finden, übersetzen,
 * PHP-Dateien schreiben, Fehlerbehandlung bei Schreibproblemen.
 *
 * Hinweis: CompilePoFiles hat keine DI-Injection — der Glob-Pfad ist
 * als Konstante fest verdrahtet (Webtrees::ROOT_DIR . 'resources/lang/...').
 * Die Tests arbeiten deshalb direkt mit dem realen Dateisystem im Container.
 *
 * @see docs/tds_conditions_ref.md A15
 * @covers \Fisharebest\Webtrees\Cli\Commands\CompilePoFiles
 */
class CompilePoFilesCommandIntegrationTest extends MysqlTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $command = new CompilePoFiles();
        $app     = new Application();
        $app->add($command);
        $this->tester = new CommandTester($app->find('compile-po-files'));
    }

    /**
     * Führt den Command aus und unterdrückt erwartete file_put_contents-Warnungen
     * bei Nur-Lese-Dateisystem im Container.
     */
    private function executeWithSuppressedFsWarnings(): int
    {
        set_error_handler(function (int $errno, string $errstr): bool {
            if (str_contains($errstr, 'Read-only file system')) {
                return true;
            }

            return false;
        });

        try {
            return $this->tester->execute([]);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * EP1/B1: PO-Dateien werden gefunden und verarbeitet.
     *
     * Im Container existieren PO-Dateien unter resources/lang/.
     * Bei beschreibbarem Dateisystem → SUCCESS mit "Created"-Meldungen.
     * Bei Nur-Lese-Mount → FAILURE mit "Failed to write"-Meldungen.
     */
    public function test_compile_finds_and_processes_po_files(): void
    {
        $exitCode = $this->executeWithSuppressedFsWarnings();
        $display  = $this->tester->getDisplay();

        // PO-Dateien müssen im Container vorhanden sein
        $this->assertStringNotContainsString('Failed to find any PO files', $display,
            'PO-Dateien müssen im Container vorhanden sein');

        if ($exitCode === Command::SUCCESS) {
            // Erfolgreiche Kompilierung: Mindestens eine "Created"-Meldung
            $this->assertStringContainsString('Created', $display);
            $this->assertStringContainsString('translations', $display);
        } else {
            // Schreibfehler (z.B. Nur-Lese-Mount): "Failed to write"-Meldung
            $this->assertSame(Command::FAILURE, $exitCode);
            $this->assertStringContainsString('Failed to write to', $display);
        }
    }

    /**
     * B2: Erfolgs- oder Fehlermeldungen enthalten den Dateipfad.
     */
    public function test_output_contains_file_paths(): void
    {
        $this->executeWithSuppressedFsWarnings();
        $display = $this->tester->getDisplay();

        // Jede Meldung referenziert eine .php-Datei
        $this->assertMatchesRegularExpression('/\.php/', $display,
            'Ausgabe muss PHP-Dateipfade enthalten');
    }
}
