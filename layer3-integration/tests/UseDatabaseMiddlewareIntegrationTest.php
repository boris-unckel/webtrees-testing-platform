<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\Middleware\UseDatabase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: UseDatabase-Middleware (M07).
 *
 * Prüft die Datenbank-Verbindungsherstellung über Request-Attribute.
 * Nutzt die echte MySQL-Test-Datenbank für den Happy-Path.
 *
 * @see docs/tds_conditions_ref.md M07
 * @covers \Fisharebest\Webtrees\Http\Middleware\UseDatabase
 */
class UseDatabaseMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1: Alle MySQL-Parameter gültig → Verbindung wird hergestellt, Handler aufgerufen.
     */
    public function test_mysql_connection_with_valid_parameters(): void
    {
        // Bestehende Verbindung trennen, damit die Middleware eine neue aufbaut
        DB::connection()->disconnect();

        $host     = getenv('MYSQL_HOST') ?: 'mysql';
        $port     = getenv('MYSQL_PORT') ?: '3306';
        $database = getenv('MYSQL_DATABASE') ?: 'webtrees_test';
        $username = getenv('MYSQL_USER') ?: 'webtrees';
        $password = getenv('MYSQL_PASSWORD') ?: 'webtrees_test';

        $request = $this->createRequest(attributes: [
            'dbtype'   => DB::MYSQL,
            'dbhost'   => $host,
            'dbport'   => $port,
            'dbname'   => $database,
            'dbuser'   => $username,
            'dbpass'   => $password,
            'tblpfx'   => 'wt_',
            'dbkey'    => '',
            'dbcert'   => '',
            'dbca'     => '',
            'dbverify' => false,
        ]);

        $tracker = new \stdClass();
        $tracker->called = false;

        $handler = new class ($tracker) implements RequestHandlerInterface {
            public function __construct(private readonly \stdClass $tracker)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->tracker->called = true;

                // Nach der Middleware sollte eine aktive DB-Verbindung bestehen
                DB::table('site_setting')->count();

                return response('OK');
            }
        };

        $middleware = new UseDatabase();
        $response = $middleware->process($request, $handler);

        $this->assertTrue($tracker->called);
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: dbtype nicht gesetzt → Default greift (MySQL).
     */
    public function test_default_dbtype_uses_mysql(): void
    {
        DB::connection()->disconnect();

        $host     = getenv('MYSQL_HOST') ?: 'mysql';
        $port     = getenv('MYSQL_PORT') ?: '3306';
        $database = getenv('MYSQL_DATABASE') ?: 'webtrees_test';
        $username = getenv('MYSQL_USER') ?: 'webtrees';
        $password = getenv('MYSQL_PASSWORD') ?: 'webtrees_test';

        // dbtype wird nicht gesetzt → Default DB::MYSQL in der Middleware
        $request = $this->createRequest(attributes: [
            'dbhost'   => $host,
            'dbport'   => $port,
            'dbname'   => $database,
            'dbuser'   => $username,
            'dbpass'   => $password,
            'tblpfx'   => 'wt_',
        ]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn(response('OK'));

        $middleware = new UseDatabase();
        $response = $middleware->process($request, $handler);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Verifizieren, dass die Verbindung aktiv ist
        $count = DB::table('site_setting')->count();
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * EP4: Optionale Parameter leer → Defaults werden verwendet (kein Fehler).
     */
    public function test_optional_parameters_empty_uses_defaults(): void
    {
        DB::connection()->disconnect();

        $host     = getenv('MYSQL_HOST') ?: 'mysql';
        $port     = getenv('MYSQL_PORT') ?: '3306';
        $database = getenv('MYSQL_DATABASE') ?: 'webtrees_test';
        $username = getenv('MYSQL_USER') ?: 'webtrees';
        $password = getenv('MYSQL_PASSWORD') ?: 'webtrees_test';

        $request = $this->createRequest(attributes: [
            'dbtype'   => DB::MYSQL,
            'dbhost'   => $host,
            'dbport'   => $port,
            'dbname'   => $database,
            'dbuser'   => $username,
            'dbpass'   => $password,
            'tblpfx'   => 'wt_',
            // dbkey, dbcert, dbca, dbverify: nicht gesetzt → Defaults
        ]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn(response('OK'));

        $middleware = new UseDatabase();
        $response = $middleware->process($request, $handler);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
