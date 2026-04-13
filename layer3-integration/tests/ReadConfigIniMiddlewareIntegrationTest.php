<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\ReadConfigIni;
use Fisharebest\Webtrees\Http\RequestHandlers\SetupWizard;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\ServerCheckService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Webtrees;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: ReadConfigIni-Middleware (M21).
 *
 * Prüft das Einlesen der config.ini.php und die Weiterleitung zum SetupWizard
 * bei fehlender Konfigurationsdatei. Nutzt das reale Container-Dateisystem.
 *
 * @see docs/tds_conditions_ref.md M21
 * @covers \Fisharebest\Webtrees\Http\Middleware\ReadConfigIni
 */
class ReadConfigIniMiddlewareIntegrationTest extends MysqlTestCase
{
    private string $configFile;
    private string $backupFile;
    private bool $configRenamed = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configFile = Webtrees::CONFIG_FILE;
        $this->backupFile = $this->configFile . '.bak.test';
    }

    protected function tearDown(): void
    {
        if ($this->configRenamed && file_exists($this->backupFile)) {
            rename($this->backupFile, $this->configFile);
            $this->configRenamed = false;
        }

        parent::tearDown();
    }

    /**
     * Erzeugt einen echten SetupWizard mit gemockten Dependencies.
     * SetupWizard ist final und kann nicht per PHPUnit gemockt werden.
     */
    private function createRealSetupWizard(): SetupWizard
    {
        return new SetupWizard(
            $this->createStub(MigrationService::class),
            $this->createStub(ModuleService::class),
            $this->createStub(PhpService::class),
            $this->createStub(ServerCheckService::class),
            $this->createStub(UserService::class),
        );
    }

    /**
     * B1/EP1: Config-Datei vorhanden und gültig → Keys als Request-Attribute gesetzt.
     */
    public function test_config_file_parsed_and_attributes_set(): void
    {
        $this->assertTrue(file_exists($this->configFile), 'Config-Datei muss existieren');

        $capture = new \stdClass();
        $capture->request = null;

        $handler = new class ($capture) implements RequestHandlerInterface {
            public function __construct(private readonly \stdClass $capture)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capture->request = $request;

                return response('OK');
            }
        };

        $middleware = new ReadConfigIni($this->createRealSetupWizard());
        $middleware->process($this->createRequest(), $handler);

        $this->assertNotNull($capture->request);
        $this->assertNotNull($capture->request->getAttribute('dbhost'));
        $this->assertNotNull($capture->request->getAttribute('dbname'));
        $this->assertNotNull($capture->request->getAttribute('dbuser'));
    }

    /**
     * B2/EP3: Config-Datei fehlt → SetupWizard wird als Handler verwendet.
     *
     * SetupWizard ist final und kann nicht gemockt werden. Der echte SetupWizard
     * mit gemockten Dependencies wirft einen Error, weil interne Service-Aufrufe
     * null zurückgeben. Wir verifizieren indirekt: der normale Handler wird NICHT
     * aufgerufen, und der Fehler stammt aus dem SetupWizard (beweist, dass die
     * Middleware ihn als Handler eingesetzt hat).
     */
    public function test_missing_config_invokes_setup_wizard(): void
    {
        $this->assertTrue(file_exists($this->configFile), 'Config-Datei muss vor Umbenennung existieren');
        rename($this->configFile, $this->backupFile);
        $this->configRenamed = true;
        $this->assertFalse(file_exists($this->configFile));

        $tracker = new \stdClass();
        $tracker->normalHandlerCalled = false;

        $normalHandler = new class ($tracker) implements RequestHandlerInterface {
            public function __construct(private readonly \stdClass $tracker)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->tracker->normalHandlerCalled = true;

                return response('Normal-Handler');
            }
        };

        $middleware = new ReadConfigIni($this->createRealSetupWizard());

        // SetupWizard mit gemockten Dependencies wirft einen Error — das beweist,
        // dass die Middleware den SetupWizard aufgerufen hat.
        $setupWizardInvoked = false;
        try {
            $middleware->process($this->createRequest(), $normalHandler);
        } catch (\Error $e) {
            // Fehler stammt aus SetupWizard → Middleware hat den Wizard aufgerufen
            $this->assertStringContainsString('SetupWizard', $e->getFile());
            $setupWizardInvoked = true;
        }

        $this->assertTrue($setupWizardInvoked, 'SetupWizard muss aufgerufen worden sein');
        $this->assertFalse($tracker->normalHandlerCalled, 'Normaler Handler darf nicht aufgerufen werden');
    }

    /**
     * EP1 Zusatz: Alle Config-Keys werden als Attribute gesetzt.
     */
    public function test_all_config_keys_become_request_attributes(): void
    {
        $this->assertTrue(file_exists($this->configFile));

        $expectedConfig = parse_ini_file($this->configFile);
        $this->assertNotEmpty($expectedConfig, 'Config-Datei darf nicht leer sein');

        $capture = new \stdClass();
        $capture->request = null;

        $handler = new class ($capture) implements RequestHandlerInterface {
            public function __construct(private readonly \stdClass $capture)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capture->request = $request;

                return response('OK');
            }
        };

        $middleware = new ReadConfigIni($this->createRealSetupWizard());
        $middleware->process($this->createRequest(), $handler);

        $this->assertNotNull($capture->request);

        foreach ($expectedConfig as $key => $value) {
            $this->assertSame(
                $value,
                $capture->request->getAttribute($key),
                "Config-Key '{$key}' muss als Request-Attribut gesetzt sein",
            );
        }
    }
}
