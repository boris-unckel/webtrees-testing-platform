<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\SiteOnline;
use Fisharebest\Webtrees\Services\MaintenanceModeService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: SiteOnline CLI-Command (A13).
 *
 * Prüft das Deaktivieren des Wartungsmodus über den CLI-Command site-online.
 * Verifiziert Datei-Löschung, Idempotenz und Sequenz-Verhalten.
 *
 * @see docs/tds_conditions_ref.md A13
 * @covers \Fisharebest\Webtrees\Cli\Commands\SiteOnline
 */
class SiteOnlineCommandIntegrationTest extends MysqlTestCase
{
    private CommandTester $tester;
    private MaintenanceModeService $maintenanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceService = new MaintenanceModeService();

        $command = new SiteOnline($this->maintenanceService);
        $app     = new Application();
        $app->add($command);
        $this->tester = new CommandTester($app->find('site-online'));
    }

    protected function tearDown(): void
    {
        // Sicherstellen, dass der Wartungsmodus deaktiviert ist
        $this->maintenanceService->online();
        parent::tearDown();
    }

    /**
     * EP1: Offline-Datei existiert → wird gelöscht, SUCCESS.
     */
    public function test_site_online_deletes_offline_file(): void
    {
        $this->maintenanceService->offline('Wartung');
        $this->assertTrue($this->maintenanceService->isOffline());

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse($this->maintenanceService->isOffline());
        $this->assertStringContainsString('Site is online', $this->tester->getDisplay());
    }

    /**
     * EP2: Offline-Datei fehlt (idempotent) → SUCCESS, "already online".
     */
    public function test_site_online_already_online_returns_success(): void
    {
        $this->maintenanceService->online();
        $this->assertFalse($this->maintenanceService->isOffline());

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('already online', $this->tester->getDisplay());
    }

    /**
     * EP4: Sequenz site-offline → site-online → Wartungsmodus korrekt aktiviert und deaktiviert.
     */
    public function test_offline_then_online_sequence(): void
    {
        // Offline setzen
        $this->maintenanceService->offline('Sequenz-Test');
        $this->assertTrue($this->maintenanceService->isOffline());

        // Online setzen via Command
        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse($this->maintenanceService->isOffline());
    }
}
