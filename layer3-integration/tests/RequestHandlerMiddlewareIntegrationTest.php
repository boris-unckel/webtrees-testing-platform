<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Aura\Router\Route;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Middleware\RequestHandler;
use Fisharebest\Webtrees\Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: RequestHandler-Middleware (M12).
 *
 * Prüft die Auflösung des Request-Handlers aus dem Routing-Ergebnis:
 * String-Klassennamen (Container-Lookup) vs. Objekt-Instanzen (direkter Aufruf).
 *
 * @see docs/tds_conditions_ref.md M12
 * @covers \Fisharebest\Webtrees\Http\Middleware\RequestHandler
 */
class RequestHandlerMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1: Handler als String-Klassenname → Container löst Klasse auf, handle() wird aufgerufen.
     */
    public function test_handler_string_resolved_via_container(): void
    {
        // Anonyme Handler-Klasse registrieren — muss einen echten FQCN haben
        $handlerFqcn = DummyStringHandler::class;
        Registry::container()->set($handlerFqcn, new DummyStringHandler());

        $route = new Route();
        $route->name('test-string-handler');
        $route->handler($handlerFqcn);  // Setzt protected $handler via Setter

        $request = $this->createRequest(attributes: ['route' => $route]);

        $middleware = new RequestHandler();
        // Der zweite Handler wird von RequestHandler-Middleware ignoriert (terminale Middleware)
        $fallback = $this->createMock(RequestHandlerInterface::class);
        $fallback->expects($this->never())->method('handle');

        $response = $middleware->process($request, $fallback);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertSame('string-handler-ok', (string) $response->getBody());
    }

    /**
     * EP2: Handler als Objekt-Instanz → handle() wird direkt aufgerufen.
     */
    public function test_handler_object_called_directly(): void
    {
        $handlerObject = new DummyObjectHandler();

        $route = new Route();
        $route->name('test-object-handler');
        $route->handler($handlerObject);

        $request = $this->createRequest(attributes: ['route' => $route]);

        $middleware = new RequestHandler();
        $fallback = $this->createMock(RequestHandlerInterface::class);
        $fallback->expects($this->never())->method('handle');

        $response = $middleware->process($request, $fallback);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertSame('object-handler-ok', (string) $response->getBody());
    }
}

/**
 * Test-Handler für EP1 (String-Auflösung via Container).
 */
class DummyStringHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return response('string-handler-ok');
    }
}

/**
 * Test-Handler für EP2 (Objekt-Instanz direkt).
 */
class DummyObjectHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return response('object-handler-ok');
    }
}
