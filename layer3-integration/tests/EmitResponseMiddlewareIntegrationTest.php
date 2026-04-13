<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\EmitResponse;
use Fisharebest\Webtrees\Services\PhpService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: EmitResponse-Middleware (M28).
 *
 * Prüft die HTTP-Response-Emittierung: Body-Ausgabe (echo), cache-control-Header,
 * FastCGI-Erkennung. Globale PHP-Funktionen (header, echo) werden über
 * Output-Buffering und PhpService-Stub abgefangen.
 *
 * Hinweis: PHP 8.5 erzeugt eine Warnung wenn http_response_code() nach header('HTTP/...')
 * im selben Prozess aufgerufen wird. Diese Warnung wird für die Ausgabetests unterdrückt.
 *
 * @see docs/tds_conditions_ref.md M28
 * @covers \Fisharebest\Webtrees\Http\Middleware\EmitResponse
 */
class EmitResponseMiddlewareIntegrationTest extends MysqlTestCase
{
    private function createMiddleware(): EmitResponse
    {
        $phpService = $this->createStub(PhpService::class);
        $phpService->method('functionExists')->willReturn(false);

        return new EmitResponse($phpService);
    }

    /**
     * Führt process() mit unterdrückter PHP-8.5-Warnung aus.
     *
     * emitStatusLine() ruft http_response_code() + header('HTTP/...') auf.
     * Ab PHP 8.5 erzeugt der zweite Testaufruf im selben Prozess eine Warnung,
     * die sonst in den Output-Buffer gelangt und expectOutputString bricht.
     */
    private function processWithSuppressedWarnings(
        EmitResponse $middleware,
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        set_error_handler(static function (int $errno, string $errstr): bool {
            if (str_contains($errstr, 'http_response_code()')) {
                return true;
            }

            return false;
        });

        try {
            return $middleware->process($request, $handler);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * EP3/B3: Body wird via echo emittiert.
     */
    public function test_body_is_emitted(): void
    {
        $middleware = $this->createMiddleware();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('Test Body Content'));

        $this->expectOutputString('Test Body Content');

        $response = $this->processWithSuppressedWarnings(
            $middleware,
            $this->createRequest(),
            $handler,
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * EP5/B4: cache-control fehlt → 'no-store' wird gesetzt.
     */
    public function test_cache_control_added_when_missing(): void
    {
        $middleware = $this->createMiddleware();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $this->expectOutputString('OK');

        $response = $this->processWithSuppressedWarnings(
            $middleware,
            $this->createRequest(),
            $handler,
        );

        $this->assertSame('no-store', $response->getHeaderLine('cache-control'));
    }

    /**
     * EP4/B5: cache-control vorhanden → bleibt erhalten.
     */
    public function test_cache_control_preserved_when_present(): void
    {
        $middleware = $this->createMiddleware();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(
            response('OK')->withHeader('cache-control', 'max-age=3600')
        );

        $this->expectOutputString('OK');

        $response = $this->processWithSuppressedWarnings(
            $middleware,
            $this->createRequest(),
            $handler,
        );

        $this->assertSame('max-age=3600', $response->getHeaderLine('cache-control'));
    }

    /**
     * EP9/B9: FastCGI nicht verfügbar → kein Fehler, functionExists geprüft.
     */
    public function test_fastcgi_not_available(): void
    {
        $phpService = $this->createMock(PhpService::class);
        $phpService->expects($this->once())
            ->method('functionExists')
            ->with('fastcgi_finish_request')
            ->willReturn(false);

        $middleware = new EmitResponse($phpService);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $this->expectOutputString('OK');

        $response = $this->processWithSuppressedWarnings(
            $middleware,
            $this->createRequest(),
            $handler,
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Leerer Body → keine Ausgabe. response('') liefert Status 204 (No Content).
     */
    public function test_empty_body_no_output(): void
    {
        $middleware = $this->createMiddleware();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response(''));

        $this->expectOutputString('');

        $response = $this->processWithSuppressedWarnings(
            $middleware,
            $this->createRequest(),
            $handler,
        );

        $this->assertSame(204, $response->getStatusCode());
    }
}
