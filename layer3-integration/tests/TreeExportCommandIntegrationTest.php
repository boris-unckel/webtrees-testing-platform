<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\TreeExport;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: TreeExport CLI-Command.
 *
 * AP C-03: TreeExport::execute (CRAP 240)
 *
 * @see docs/testing-bigpicture.md G26
 * @covers \Fisharebest\Webtrees\Cli\Commands\TreeExport
 */
class TreeExportCommandIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private CommandTester $tester;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $responseFactory     = Registry::container()->get(ResponseFactoryInterface::class);
        $streamFactory       = Registry::container()->get(StreamFactoryInterface::class);
        $gedcomExportService = new GedcomExportService($responseFactory, $streamFactory);

        $command = new TreeExport($gedcomExportService);
        $app     = new Application();
        $app->add($command);
        $this->tester = new CommandTester($app->find('tree-export'));

        $this->originalCwd = getcwd();
        chdir('/tmp');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        parent::tearDown();
    }

    /**
     * Baum als GEDCOM exportieren — Command gibt SUCCESS zurück.
     * Die Datei wird ins Arbeitsverzeichnis (/tmp) geschrieben.
     */
    public function test_export_tree_to_file(): void
    {
        $exitCode = $this->tester->execute([
            'tree_name' => $this->tree->name(),
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('exported successfully', $output);
    }

    /**
     * Baum als GEDCOM exportieren mit explizitem Format.
     */
    public function test_export_tree_gedcom_format(): void
    {
        $exitCode = $this->tester->execute([
            'tree_name' => $this->tree->name(),
            '--format'  => 'gedcom',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validFormats(): array
    {
        return [
            'gedcom'   => ['gedcom', '.ged'],
            'gedzip'   => ['gedzip', '.gdz'],
            'zip'      => ['zip', '.zip'],
            'zipmedia' => ['zipmedia', '.zip'],
        ];
    }

    /**
     * Alle validen Export-Formate liefern SUCCESS und die erwartete Datei-Endung im Output.
     */
    #[DataProvider('validFormats')]
    public function test_export_all_valid_formats(string $format, string $expectedExtension): void
    {
        $exitCode = $this->tester->execute([
            'tree_name' => $this->tree->name(),
            '--format'  => $format,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString($expectedExtension, $this->tester->getDisplay());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validPrivacyLevels(): array
    {
        return [
            'none'    => ['none'],
            'manager' => ['manager'],
            'member'  => ['member'],
            'visitor' => ['visitor'],
        ];
    }

    /**
     * Alle validen Privacy-Level liefern SUCCESS.
     */
    #[DataProvider('validPrivacyLevels')]
    public function test_export_all_valid_privacy_levels(string $privacy): void
    {
        $exitCode = $this->tester->execute([
            'tree_name' => $this->tree->name(),
            '--privacy' => $privacy,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('exported successfully', $this->tester->getDisplay());
    }

    /**
     * Ungültiges Format liefert FAILURE mit erklärender Fehlermeldung.
     * EP6: 'XML' (Großschreibung, case-sensitive), EP7: 'json'
     */
    public function test_export_fails_with_invalid_format(): void
    {
        $exitCode = $this->tester->execute([
            'tree_name' => $this->tree->name(),
            '--format'  => 'XML',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Format option should be', $this->tester->getDisplay());
    }

    /**
     * Ungültiger Privacy-Wert liefert FAILURE mit erklärender Fehlermeldung.
     * EP12: 'admin' ist nicht in ACCESS_LEVELS.
     */
    public function test_export_fails_with_invalid_privacy(): void
    {
        $exitCode = $this->tester->execute([
            'tree_name' => $this->tree->name(),
            '--privacy' => 'admin',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('privacy option should be', $this->tester->getDisplay());
    }

    /**
     * Nicht existierender Baum-Name liefert FAILURE mit „not found"-Meldung.
     * EP14: Tree nicht in DB.
     */
    public function test_export_fails_when_tree_not_found(): void
    {
        $exitCode = $this->tester->execute([
            'tree_name' => 'nonexistent_tree_xyz',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $this->tester->getDisplay());
    }
}
