<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\Middleware\DebugLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: DebugLogger-Middleware (M17).
 *
 * Prüft das Debug-Logging: deaktiviert (Passthrough), aktiviert
 * (SQL-Query-Log, Processing-Time, Memory-Headers).
 *
 * @see docs/tds_conditions_ref.md M17
 * @covers \Fisharebest\Webtrees\Http\Middleware\DebugLogger
 */
class DebugLoggerMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * Handler, der mindestens eine SQL-Abfrage ausführt.
     */
    private function sqlQueryHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                DB::table('site_setting')->count();

                return response('OK');
            }
        };
    }

    /**
     * EP1/B1: debug=false → Passthrough, keine Debug-Headers.
     */
    public function test_debug_disabled_passthrough(): void
    {
        $middleware = new DebugLogger();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEmpty($response->getHeaderLine('x-debug-sql'));
        $this->assertEmpty($response->getHeaderLine('x-debug-processing-time'));
        $this->assertEmpty($response->getHeaderLine('x-debug-memory-peak-usage'));
    }

    /**
     * EP2/B2: debug=true + SQL-Abfragen → Debug-Headers mit Query-Stats.
     */
    public function test_debug_enabled_adds_response_headers(): void
    {
        $middleware = new DebugLogger();

        $request  = $this->createRequest(attributes: ['debug' => true]);
        $response = $middleware->process($request, $this->sqlQueryHandler());

        $this->assertSame(200, $response->getStatusCode());

        // x-debug-sql muss Query-Statistik enthalten
        $sqlHeader = $response->getHeaderLine('x-debug-sql');
        $this->assertNotEmpty($sqlHeader, 'x-debug-sql Header muss vorhanden sein');
        $this->assertStringContainsString('Queries:', $sqlHeader);

        // Processing-Time und Memory-Headers
        $this->assertNotEmpty($response->getHeaderLine('x-debug-processing-time'));
        $this->assertNotEmpty($response->getHeaderLine('x-debug-memory-peak-usage'));
    }

    /**
     * EP2/B2: Processing-Time-Header enthält Sekunden-Angabe.
     */
    public function test_debug_processing_time_format(): void
    {
        $middleware = new DebugLogger();

        $request  = $this->createRequest(attributes: ['debug' => true]);
        $response = $middleware->process($request, $this->sqlQueryHandler());

        $this->assertMatchesRegularExpression('/\d+\.\d+ seconds/',
            $response->getHeaderLine('x-debug-processing-time'));
    }

    /**
     * EP2/B2: Memory-Header enthält KB-Angabe.
     */
    public function test_debug_memory_header_format(): void
    {
        $middleware = new DebugLogger();

        $request  = $this->createRequest(attributes: ['debug' => true]);
        $response = $middleware->process($request, $this->sqlQueryHandler());

        $this->assertMatchesRegularExpression('/\d+ KB/',
            $response->getHeaderLine('x-debug-memory-peak-usage'));
    }
}
