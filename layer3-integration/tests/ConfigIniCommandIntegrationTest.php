<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\ConfigIni;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Webtrees;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: ConfigIni CLI-Command (A14).
 *
 * Prüft das Erstellen der config.ini.php über den CLI-Command: Gültige Credentials,
 * base-url-Warnung, Trailing-Slash-Trimming, dbverify-Flag und Fehlerbehandlung.
 *
 * @see docs/tds_conditions_ref.md A14
 * @covers \Fisharebest\Webtrees\Cli\Commands\ConfigIni
 */
class ConfigIniCommandIntegrationTest extends MysqlTestCase
{
    private string $configFile;
    private string $backupFile;
    private bool $configBackedUp = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configFile = Webtrees::CONFIG_FILE;
        $this->backupFile = $this->configFile . '.bak.a14test';

        // Config-Datei sichern
        if (file_exists($this->configFile)) {
            copy($this->configFile, $this->backupFile);
            $this->configBackedUp = true;
        }
    }

    protected function tearDown(): void
    {
        // Config-Datei zuerst wiederherstellen
        if ($this->configBackedUp && file_exists($this->backupFile)) {
            rename($this->backupFile, $this->configFile);
            $this->configBackedUp = false;
        }

        // DB-Verbindung wiederherstellen (ConfigIni-Command könnte sie modifiziert haben)
        try {
            DB::connection()->disconnect();
        } catch (\Throwable) {
            // Verbindung könnte bereits kaputt sein — ignorieren
        }

        DB::connect(
            driver:             DB::MYSQL,
            host:               getenv('MYSQL_HOST') ?: 'mysql',
            port:               getenv('MYSQL_PORT') ?: '3306',
            database:           getenv('MYSQL_DATABASE') ?: 'webtrees_test',
            username:           getenv('MYSQL_USER') ?: 'webtrees',
            password:           getenv('MYSQL_PASSWORD') ?: 'webtrees_test',
            prefix:             'wt_',
            key:                '',
            certificate:        '',
            ca:                 '',
            verify_certificate: false,
        );

        parent::tearDown();
    }

    private function makeTester(): CommandTester
    {
        $command = new ConfigIni();
        $app     = new Application();
        $app->add($command);

        return new CommandTester($app->find('config-ini'));
    }

    /**
     * Liefert die gültigen DB-Optionen aus der Testumgebung.
     *
     * @return array<string, string>
     */
    private function getDbOptions(): array
    {
        return [
            '--dbtype' => 'mysql',
            '--dbhost' => getenv('MYSQL_HOST') ?: 'mysql',
            '--dbport' => getenv('MYSQL_PORT') ?: '3306',
            '--dbname' => getenv('MYSQL_DATABASE') ?: 'webtrees_test',
            '--dbuser' => getenv('MYSQL_USER') ?: 'webtrees',
            '--dbpass' => getenv('MYSQL_PASSWORD') ?: 'webtrees_test',
            '--tblpfx' => 'wt_',
        ];
    }

    /**
     * EP1/EP4: Gültige DB-Credentials + base-url → SUCCESS, Config-Datei erstellt,
     * DB-Verbindungstest bestanden.
     */
    public function test_config_ini_with_valid_credentials_returns_success(): void
    {
        $tester = $this->makeTester();

        $exitCode = $tester->execute(array_merge($this->getDbOptions(), [
            '--base-url' => 'https://webtrees.test',
        ]));

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Database connection successful', $tester->getDisplay());
        $this->assertFileExists($this->configFile);
    }

    /**
     * EP3: base-url leer → WARNING-Ausgabe + SUCCESS (DB-Verbindung funktioniert dennoch).
     */
    public function test_config_ini_empty_base_url_shows_warning(): void
    {
        $tester = $this->makeTester();

        $exitCode = $tester->execute(array_merge($this->getDbOptions(), [
            '--base-url' => '',
        ]));

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('base URL', $tester->getDisplay());
    }

    /**
     * EP7: base-url mit Trailing Slashes → Slashes via rtrim entfernt.
     */
    public function test_config_ini_base_url_trailing_slashes_trimmed(): void
    {
        $tester = $this->makeTester();

        $exitCode = $tester->execute(array_merge($this->getDbOptions(), [
            '--base-url' => 'https://webtrees.test///',
        ]));

        $this->assertSame(Command::SUCCESS, $exitCode);

        $config = parse_ini_file($this->configFile);
        $this->assertSame('https://webtrees.test', $config['base_url']);
    }

    /**
     * EP8: dbverify=true → Wert '1' in der INI-Datei.
     */
    public function test_config_ini_dbverify_flag_writes_one(): void
    {
        $tester = $this->makeTester();

        $exitCode = $tester->execute(array_merge($this->getDbOptions(), [
            '--base-url' => 'https://webtrees.test',
            '--dbverify' => true,
        ]));

        $this->assertSame(Command::SUCCESS, $exitCode);

        $config = parse_ini_file($this->configFile);
        $this->assertSame('1', $config['dbverify']);
    }

    /**
     * EP5: Ungültige DB-Credentials → FAILURE mit Fehlermeldung.
     */
    public function test_config_ini_invalid_db_credentials_returns_failure(): void
    {
        $tester = $this->makeTester();

        $exitCode = $tester->execute([
            '--dbtype'   => 'mysql',
            '--dbhost'   => getenv('MYSQL_HOST') ?: 'mysql',
            '--dbport'   => getenv('MYSQL_PORT') ?: '3306',
            '--dbname'   => 'webtrees_test',
            '--dbuser'   => 'invalid_user_xyz',
            '--dbpass'   => 'invalid_password_xyz',
            '--tblpfx'   => 'wt_',
            '--base-url' => 'https://webtrees.test',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Database connection failed', $tester->getDisplay());
    }
}
