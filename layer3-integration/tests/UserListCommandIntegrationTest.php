<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\UserList;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: UserList CLI-Command (P42).
 *
 * Prüft die Benutzerauflistung in verschiedenen Formaten (table, csv, json),
 * Admin-/Verified-/Approved-Flags und die Fehlerbehandlung bei ungültigem Format.
 *
 * @see docs/tds_conditions_ref.md P42
 * @covers \Fisharebest\Webtrees\Cli\Commands\UserList
 */
class UserListCommandIntegrationTest extends MysqlTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();

        $command = new UserList($this->userService);
        $app     = new Application();
        $app->add($command);
        $this->tester = new CommandTester($app->find('user-list'));
    }

    /**
     * EP1/B1: Tabellenformat — SUCCESS, enthält Benutzerdaten.
     */
    public function test_table_format_lists_users(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'table']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('test-admin', $this->tester->getDisplay());
    }

    /**
     * EP2/B2: CSV-Format — SUCCESS, CSV-Struktur mit Header und Daten.
     */
    public function test_csv_format_lists_users(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'csv']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('"ID"', $display);
        $this->assertStringContainsString('"Username"', $display);
        $this->assertStringContainsString('test-admin', $display);
    }

    /**
     * EP3/B3: JSON-Format — SUCCESS, valides JSON mit allen erwarteten Feldern.
     */
    public function test_json_format_lists_users(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $data = json_decode($this->tester->getDisplay(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $entry = $data[0];
        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('username', $entry);
        $this->assertArrayHasKey('real_name', $entry);
        $this->assertArrayHasKey('email', $entry);
        $this->assertArrayHasKey('admin', $entry);
        $this->assertArrayHasKey('approved', $entry);
        $this->assertArrayHasKey('verified', $entry);
        $this->assertArrayHasKey('language', $entry);
        $this->assertArrayHasKey('timezone', $entry);
        $this->assertArrayHasKey('contact', $entry);
        $this->assertArrayHasKey('registered', $entry);
        $this->assertArrayHasKey('last_login', $entry);
    }

    /**
     * EP4/B4: Ungültiges Format — FAILURE mit Fehlermeldung.
     */
    public function test_invalid_format_returns_failure(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'xml']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid format', $this->tester->getDisplay());
    }

    /**
     * B5: Admin-Flag wird korrekt als 'yes' dargestellt für test-admin.
     */
    public function test_admin_flag_shown_correctly(): void
    {
        $exitCode = $this->tester->execute(['--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $data = json_decode($this->tester->getDisplay(), true);

        $adminEntry = null;
        foreach ($data as $entry) {
            if ($entry['username'] === 'test-admin') {
                $adminEntry = $entry;
                break;
            }
        }
        $this->assertNotNull($adminEntry, 'test-admin muss in der Liste enthalten sein');
        $this->assertSame('yes', $adminEntry['admin']);
    }

    /**
     * B6: Default-Format (kein --format) ist table — SUCCESS.
     */
    public function test_default_format_is_table(): void
    {
        $exitCode = $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('test-admin', $this->tester->getDisplay());
    }
}
