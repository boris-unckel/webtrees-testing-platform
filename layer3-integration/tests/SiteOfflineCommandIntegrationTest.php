<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\SiteOffline;
use Fisharebest\Webtrees\Services\MaintenanceModeService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: SiteOffline CLI-Command (A12).
 *
 * Prüft das Aktivieren des Wartungsmodus über den CLI-Command site-offline.
 * Verifiziert Datei-Erstellung, Nachricht-Speicherung und Überschreib-Verhalten.
 *
 * @see docs/tds_conditions_ref.md A12
 * @covers \Fisharebest\Webtrees\Cli\Commands\SiteOffline
 */
class SiteOfflineCommandIntegrationTest extends MysqlTestCase
{
    private CommandTester $tester;
    private MaintenanceModeService $maintenanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceService = new MaintenanceModeService();
        // Sicherstellen, dass wir online starten
        $this->maintenanceService->online();

        $command = new SiteOffline($this->maintenanceService);
        $app     = new Application();
        $app->add($command);
        $this->tester = new CommandTester($app->find('site-offline'));
    }

    protected function tearDown(): void
    {
        // Offline-Datei immer aufräumen, damit nachfolgende Tests nicht betroffen sind
        $this->maintenanceService->online();
        parent::tearDown();
    }

    /**
     * EP1: Ohne Message-Argument → SUCCESS, Offline-Datei erstellt.
     */
    public function test_site_offline_returns_success_and_creates_file(): void
    {
        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertTrue($this->maintenanceService->isOffline());
        $this->assertStringContainsString('Site is offline', $this->tester->getDisplay());
    }

    /**
     * EP2: Benutzerdefinierte Nachricht → SUCCESS, Nachricht in Datei gespeichert.
     */
    public function test_site_offline_with_custom_message(): void
    {
        $exitCode = $this->tester->execute(['message' => 'Wartung bis 18:00']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertTrue($this->maintenanceService->isOffline());
        $this->assertSame('Wartung bis 18:00', $this->maintenanceService->message());
    }

    /**
     * EP3: Spezialzeichen in Nachricht → korrekt gespeichert, kein Escaping-Verlust.
     */
    public function test_site_offline_with_special_characters(): void
    {
        $message  = '<script>alert("xss")</script> & "quotes"';
        $exitCode = $this->tester->execute(['message' => $message]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame($message, $this->maintenanceService->message());
    }

    /**
     * EP5: Offline-Datei existiert bereits → wird überschrieben, SUCCESS.
     */
    public function test_site_offline_overwrites_existing_file(): void
    {
        $this->maintenanceService->offline('Erste Nachricht');
        $this->assertTrue($this->maintenanceService->isOffline());

        $exitCode = $this->tester->execute(['message' => 'Zweite Nachricht']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame('Zweite Nachricht', $this->maintenanceService->message());
    }
}
