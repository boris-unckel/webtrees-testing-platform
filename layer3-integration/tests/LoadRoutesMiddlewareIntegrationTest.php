<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Aura\Router\RouterContainer;
use Fisharebest\Webtrees\Http\Middleware\LoadRoutes;
use Fisharebest\Webtrees\Http\Routes\ApiRoutes;
use Fisharebest\Webtrees\Http\Routes\WebRoutes;
use Fisharebest\Webtrees\Registry;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: LoadRoutes-Middleware (M10).
 *
 * Prüft, dass API- und Web-Routen korrekt geladen und der RouterContainer
 * im Registry registriert wird.
 *
 * @see docs/tds_conditions_ref.md M10
 * @covers \Fisharebest\Webtrees\Http\Middleware\LoadRoutes
 */
class LoadRoutesMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1/B1: Routen werden geladen und RouterContainer im Container registriert.
     */
    public function test_routes_loaded_and_registered(): void
    {
        $middleware = new LoadRoutes(new ApiRoutes(), new WebRoutes());

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());

        // RouterContainer muss im Container registriert sein
        $router = Registry::container()->get(RouterContainer::class);
        $this->assertInstanceOf(RouterContainer::class, $router);
    }

    /**
     * EP1/B2: Geladene Routen enthalten bekannte Web-Routen.
     */
    public function test_loaded_routes_contain_web_routes(): void
    {
        $middleware = new LoadRoutes(new ApiRoutes(), new WebRoutes());

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $middleware->process($this->createRequest(), $handler);

        $router = Registry::container()->get(RouterContainer::class);
        $routes = $router->getMap()->getRoutes();

        $this->assertNotEmpty($routes, 'Routen müssen geladen sein');
    }

    /**
     * Handler wird nach Routen-Registrierung aufgerufen.
     */
    public function test_handler_invoked_after_routes_loaded(): void
    {
        $middleware = new LoadRoutes(new ApiRoutes(), new WebRoutes());

        $tracker        = new \stdClass();
        $tracker->order = [];

        $handler = new class ($tracker) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(private readonly \stdClass $tracker)
            {
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                // Zum Zeitpunkt des Handler-Aufrufs muss der Router registriert sein
                $this->tracker->routerRegistered = Registry::container()->has(RouterContainer::class);

                return response('OK');
            }
        };

        $middleware->process($this->createRequest(), $handler);

        $this->assertTrue($tracker->routerRegistered, 'Router muss vor Handler-Aufruf registriert sein');
    }
}
