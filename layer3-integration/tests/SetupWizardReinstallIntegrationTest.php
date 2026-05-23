<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SetupWizard;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\ServerCheckService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\User;
use RuntimeException;
use Throwable;

/**
 * Komponentenintegrationstest: SetupWizard Reinstall-Pfad.
 *
 * SEC-AUDIT-007 Regressionsabdeckung (verhaltens-definitiv):
 *   Im Reinstall-Branch von SetupWizard::createConfigFile() muss `wtpass`
 *   aus der validierten parsedBody-Pipeline (`$data['wtpass']`) stammen,
 *   nicht aus dem rohen Superglobal `$_POST['wtpass']`. Asserts auf die
 *   Eigenschaft: der Wert, der bei `User::setPassword()` ankommt, ist der
 *   aus parsedBody — unabhängig vom Zustand von `$_POST`.
 *
 * Isolation: alle Service-Dependencies, die heavy Seiteneffekte hätten
 * (UserService, MigrationService), werden gemockt. Die echte DB-Verbindung
 * wird über die Container-MYSQL-* Umgebungsvariablen rekonstruiert und ist
 * idempotent — `connectToDatabase()` ruft `CREATE DATABASE IF NOT EXISTS`
 * und reconnectet zum laufenden MySQL.
 *
 * Cut-off: die Mock-`setPreference()` wirft auf `PREF_IS_ADMINISTRATOR`,
 * damit der Handler abbricht, BEVOR `file_put_contents()` die laufende
 * `config.ini` überschreiben würde. Die Exception wird vom Wrapper in
 * `step6Install()` abgefangen.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SetupWizard
 * @see docs/security-audit/tasks/SEC-AUDIT-007_setupwizard_superglobal.md
 */
final class SetupWizardReinstallIntegrationTest extends MysqlTestCase
{
    public function test_sec_audit_007_reinstall_setpassword_uses_validated_wtpass(): void
    {
        // Voraussetzung: $_POST darf wtpass nicht enthalten, damit der
        // Unterschied zwischen parsedBody und $_POST sichtbar wird.
        $originalPost = $_POST;
        $_POST        = [];

        $capturedPassword = null;

        $admin = $this->createMock(User::class);
        $admin->expects(self::once())
            ->method('setPassword')
            ->with(self::callback(function ($pwd) use (&$capturedPassword): bool {
                $capturedPassword = $pwd;
                return true;
            }))
            ->willReturnSelf();
        // Verhindere, dass der Handler weiter bis zum file_put_contents(config.ini)
        // läuft — setPreference(PREF_IS_ADMINISTRATOR) wirft, step6Install fängt.
        $admin->method('setPreference')
            ->willReturnCallback(function (string $k) use ($admin): void {
                if ($k === UserInterface::PREF_IS_ADMINISTRATOR) {
                    throw new RuntimeException('test cut-off — wtpass captured, halt before config.ini write');
                }
            });
        $admin->method('userName')->willReturn('sec007admin');

        $userService = $this->createStub(UserService::class);
        $userService->method('findByIdentifier')->willReturn($admin);

        // Migration-Service no-op (createStub liefert für jede Methode null/void).
        $migrationService = $this->createStub(MigrationService::class);

        $handler = new SetupWizard(
            $migrationService,
            new ModuleService(),
            new PhpService(),
            new ServerCheckService(new PhpService()),
            $userService,
        );

        try {
            $request = $this->createRequest(
                method: RequestMethodInterface::METHOD_POST,
                params: [
                    'step'     => '6',
                    'wtname'   => 'SEC AUDIT 007',
                    'wtuser'   => 'sec007admin',
                    'wtemail'  => 'sec007@local',
                    'wtpass'   => 'NEW-FROM-PARSEDBODY',
                    'lang'     => 'en-US',
                    'baseurl'  => '/',
                    'dbtype'   => 'mysql',
                    'dbhost'   => $_ENV['MYSQL_HOST'] ?? 'mysql',
                    'dbport'   => $_ENV['MYSQL_PORT'] ?? '3306',
                    'dbuser'   => $_ENV['MYSQL_USER'] ?? 'webtrees',
                    'dbpass'   => $_ENV['MYSQL_PASSWORD'] ?? '',
                    'dbname'   => $_ENV['MYSQL_DATABASE'] ?? 'webtrees_test',
                    'tblpfx'   => 'wt_',
                    'dbkey'    => '',
                    'dbcert'   => '',
                    'dbca'     => '',
                    'dbverify' => '',
                ],
            );

            try {
                $handler->handle($request);
            } catch (Throwable) {
                // Verhalten zulässig — der Handler fängt Throwable in step6Install,
                // aber unser Cut-off-Throw kommt nach setPassword, also ist die
                // Capture-Side-Effect-Schreibung bereits erfolgt.
            }

            self::assertSame(
                'NEW-FROM-PARSEDBODY',
                $capturedPassword,
                'User::setPassword() must receive the validated parsedBody wtpass, '
                . 'not a value sourced from $_POST',
            );
        } finally {
            $_POST = $originalPost;
        }
    }
}
