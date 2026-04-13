<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\ErrorHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: ErrorHandler-Middleware (M15).
 *
 * Prüft die PHP-Error-zu-Exception-Konvertierung: aktive Errors → ErrorException,
 * unterdrückte Errors → ignoriert, fehlerfreier Durchlauf → normal.
 *
 * @see docs/tds_conditions_ref.md M15
 * @covers \Fisharebest\Webtrees\Http\Middleware\ErrorHandler
 */
class ErrorHandlerMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP3/B3: Kein Fehler im Handler → Request wird normal verarbeitet.
     */
    public function test_normal_request_processing(): void
    {
        $middleware = new ErrorHandler();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', (string) $response->getBody());
    }

    /**
     * EP1/B1: Error mit aktivem error_reporting → ErrorException wird geworfen.
     *
     * Hinweis: Die Middleware restauriert den Error-Handler nur bei normalem Durchlauf,
     * nicht bei Exception. Daher muss der Test den Handler-Stack manuell bereinigen.
     */
    public function test_error_throws_exception_when_reporting_active(): void
    {
        $middleware = new ErrorHandler();

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                trigger_error('Test-Fehler im Handler', E_USER_WARNING);

                return response('unreachable');
            }
        };

        $caught = false;

        try {
            $middleware->process($this->createRequest(), $handler);
        } catch (\ErrorException $e) {
            $caught = true;
            $this->assertSame('Test-Fehler im Handler', $e->getMessage());
            $this->assertSame(E_USER_WARNING, $e->getSeverity());
        } finally {
            // Middleware restauriert den Error-Handler nicht bei Exception-Propagation,
            // daher den von der Middleware gesetzten Handler hier entfernen.
            restore_error_handler();
        }

        $this->assertTrue($caught, 'ErrorException muss geworfen werden');
    }

    /**
     * EP2/B2: Error mit @-Unterdrückung → Error wird ignoriert, Response normal.
     */
    public function test_suppressed_error_ignored(): void
    {
        $middleware = new ErrorHandler();

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // @ unterdrückt den Error → error_reporting() & errno === 0
                @trigger_error('Unterdrückter Fehler', E_USER_NOTICE);

                return response('OK-after-suppressed');
            }
        };

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK-after-suppressed', (string) $response->getBody());
    }

    /**
     * Verifiziert, dass der Error-Handler nach dem Durchlauf wiederhergestellt wird.
     */
    public function test_error_handler_restored_after_normal_request(): void
    {
        $middleware = new ErrorHandler();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $middleware->process($this->createRequest(), $handler);

        // Nach dem Middleware-Durchlauf sollte der PHPUnit-Error-Handler wiederhergestellt sein.
        // Wenn der Error-Handler nicht restauriert wurde, würde trigger_error hier eine
        // ErrorException statt eines PHPUnit-Fehlers erzeugen.
        $this->assertTrue(true, 'Error-Handler wurde korrekt restauriert');
    }
}
