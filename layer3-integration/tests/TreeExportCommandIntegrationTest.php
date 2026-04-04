<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\TreeExport;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $responseFactory    = Registry::container()->get(ResponseFactoryInterface::class);
        $streamFactory      = Registry::container()->get(StreamFactoryInterface::class);
        $gedcomExportService = new GedcomExportService($responseFactory, $streamFactory);

        $command = new TreeExport($gedcomExportService);
        $app     = new Application();
        $app->add($command);
        $this->tester = new CommandTester($app->find('tree-export'));
    }

    /**
     * Baum als GEDCOM exportieren — Command gibt SUCCESS zurück.
     * Die Datei wird ins Arbeitsverzeichnis (/tmp) geschrieben.
     */
    public function test_export_tree_to_file(): void
    {
        $cwd = getcwd();
        chdir('/tmp');

        try {
            $exitCode = $this->tester->execute([
                'tree_name' => $this->tree->name(),
            ]);
        } finally {
            chdir($cwd);
        }

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('exported successfully', $output);
    }

    /**
     * Baum als GEDCOM exportieren mit explizitem Format.
     */
    public function test_export_tree_gedcom_format(): void
    {
        $cwd = getcwd();
        chdir('/tmp');

        try {
            $exitCode = $this->tester->execute([
                'tree_name' => $this->tree->name(),
                '--format'  => 'gedcom',
            ]);
        } finally {
            chdir($cwd);
        }

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
