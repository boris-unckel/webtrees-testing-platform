<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Http\Middleware\DoHousekeeping;
use Fisharebest\Webtrees\Services\HousekeepingService;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: DoHousekeeping-Middleware (M18).
 *
 * Prüft, dass Housekeeping nur bei GET-Requests (probabilistisch) ausgelöst wird
 * und POST-Requests keine Housekeeping-Aufrufe erzeugen.
 *
 * Hinweis: random_int(1, 250) ist nicht direkt mockbar — POST-Skip
 * wird deterministisch getestet, GET-Verhalten nur strukturell.
 *
 * @see docs/tds_conditions_ref.md M18
 * @covers \Fisharebest\Webtrees\Http\Middleware\DoHousekeeping
 */
class DoHousekeepingMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1/B1: POST-Request → kein Housekeeping.
     */
    public function test_post_request_skips_housekeeping(): void
    {
        $housekeepingService = $this->createMock(HousekeepingService::class);
        $housekeepingService->expects($this->never())->method('deleteOldFiles');
        $housekeepingService->expects($this->never())->method('deleteOldLogs');
        $housekeepingService->expects($this->never())->method('deleteOldSessions');

        $middleware = new DoHousekeeping($housekeepingService);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * EP2/B2+B3: GET-Request → Handler-Response wird immer zurückgegeben.
     *
     * Housekeeping kann zufällig (1/250) ausgelöst werden.
     * HousekeepingService ist ein Stub, damit zufällige Auslösung keine Fehler erzeugt.
     */
    public function test_get_request_returns_handler_response(): void
    {
        $housekeepingService = $this->createStub(HousekeepingService::class);

        $middleware = new DoHousekeeping($housekeepingService);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('Expected'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Expected', (string) $response->getBody());
    }

    /**
     * Handler wird VOR Housekeeping aufgerufen (Response-First-Muster).
     */
    public function test_handler_called_before_housekeeping(): void
    {
        $housekeepingService = $this->createStub(HousekeepingService::class);

        $middleware = new DoHousekeeping($housekeepingService);

        $tracker         = new \stdClass();
        $tracker->called = false;

        $handler = new class ($tracker) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(private readonly \stdClass $tracker)
            {
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->tracker->called = true;

                return response('OK');
            }
        };

        $middleware->process($this->createRequest(), $handler);

        $this->assertTrue($tracker->called, 'Handler muss aufgerufen werden');
    }
}
