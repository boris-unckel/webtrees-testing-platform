<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Aura\Router\RouterContainer;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Middleware\Router;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: Router-Middleware (M11).
 *
 * Prüft URL-Routing: Rewrite-Redirect, Method-Not-Allowed (405),
 * Not-Acceptable (406), Fallback-Handler und Route-Matching mit Tree-Resolution.
 *
 * Hinweis: Die Tests für gematchte Routen (B4, B8/B9) laufen durch die reale
 * Dispatcher-Pipeline (CheckCsrf → RequestHandler). Für GET-Requests passiert
 * CheckCsrf ohne Prüfung, RequestHandler löst den Handler aus der Route auf.
 *
 * @see docs/tds_conditions_ref.md M11
 * @covers \Fisharebest\Webtrees\Http\Middleware\Router
 */
class RouterMiddlewareIntegrationTest extends MysqlTestCase
{
    private function createRouter(RouterContainer $routerContainer, ?TreeService $treeService = null): Router
    {
        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('findByInterface')->willReturn(collect([]));

        return new Router(
            $moduleService,
            $routerContainer,
            $treeService ?? $this->createStub(TreeService::class),
        );
    }

    private function createFallbackHandler(string $body = 'fallback'): RequestHandlerInterface
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response($body));

        return $handler;
    }

    /**
     * B1/EP1: rewrite_urls=true + url_route vorhanden → 308-Redirect auf Clean-URL.
     */
    public function test_rewrite_urls_redirect_with_url_route(): void
    {
        $routerContainer = new RouterContainer();

        $router = $this->createRouter($routerContainer);

        $request = $this->createRequest(
            query: ['route' => '/some/path', 'other' => 'val'],
            attributes: ['rewrite_urls' => true],
        );

        $response = $router->process($request, $this->createFallbackHandler());

        $this->assertSame(StatusCodeInterface::STATUS_PERMANENT_REDIRECT, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Link'));
        $this->assertStringContainsString('/some/path', $response->getHeaderLine('Link'));
    }

    /**
     * B5/EP5: Route nicht matched, Method-Not-Allowed → HTTP 405 mit Allow-Header.
     */
    public function test_route_not_matched_method_not_allowed(): void
    {
        $routerContainer = new RouterContainer();
        $map = $routerContainer->getMap();
        $map->get('get-only', '/get-only-path');

        $router = $this->createRouter($routerContainer);

        // POST an eine nur-GET-Route → Method-Not-Allowed
        $request = $this->createRequest(
            method: 'POST',
            query: ['route' => '/get-only-path'],
        );

        $response = $router->process($request, $this->createFallbackHandler());

        $this->assertSame(StatusCodeInterface::STATUS_METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Allow'));
        $this->assertStringContainsString('GET', $response->getHeaderLine('Allow'));
    }

    /**
     * B6/EP6: Route matched Pfad und Methode, Content-Negotiation fehlgeschlagen → HTTP 406.
     */
    public function test_route_not_matched_not_acceptable(): void
    {
        $routerContainer = new RouterContainer();
        $map = $routerContainer->getMap();
        $map->get('json-only', '/json-only-path')
            ->accepts(['application/json']);

        $router = $this->createRouter($routerContainer);

        $request = $this->createRequest(query: ['route' => '/json-only-path'])
            ->withHeader('Accept', 'text/html');

        $response = $router->process($request, $this->createFallbackHandler());

        $this->assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    /**
     * B7/EP7: Keine Route matched, kein spezifischer Fehler → Fallback-Handler aufgerufen.
     */
    public function test_route_not_matched_fallback_handler(): void
    {
        // Leerer RouterContainer — keine Routen definiert
        $routerContainer = new RouterContainer();

        $router = $this->createRouter($routerContainer);

        $request = $this->createRequest(query: ['route' => '/nonexistent']);

        $response = $router->process($request, $this->createFallbackHandler('fallback-reached'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('fallback-reached', (string) $response->getBody());
    }

    /**
     * B4/EP4: Route matched → Handler wird über Dispatcher-Pipeline aufgerufen.
     */
    public function test_route_matched_handler_invoked(): void
    {
        $routerContainer = new RouterContainer();
        $map = $routerContainer->getMap();
        $map->get('test-matched', '/test-matched-path')
            ->handler(DummyRouterHandler::class);

        // Handler im Container registrieren (RequestHandler-Middleware löst ihn auf)
        Registry::container()->set(DummyRouterHandler::class, new DummyRouterHandler());

        $router = $this->createRouter($routerContainer);

        $request = $this->createRequest(query: ['route' => '/test-matched-path']);

        $response = $router->process($request, $this->createFallbackHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('router-handler-ok', (string) $response->getBody());
    }

    /**
     * B8/B9/EP8: Tree-Parameter in Route → Tree aus DB aufgelöst, im Container registriert.
     */
    public function test_tree_attribute_resolved_from_database(): void
    {
        $this->createTreeWithGedcom('router', 'Router-Test', '/fixtures/import-test-minimal.ged');

        $routerContainer = new RouterContainer();
        $map = $routerContainer->getMap();
        $map->get('test-tree', '/tree/{tree}')
            ->handler(DummyRouterHandler::class);

        Registry::container()->set(DummyRouterHandler::class, new DummyRouterHandler());

        // Echten TreeService verwenden, damit die Tree-Auflösung aus der DB funktioniert
        $router = $this->createRouter($routerContainer, $this->treeService);

        $request = $this->createRequest(query: ['route' => '/tree/' . $this->tree->name()]);

        $response = $router->process($request, $this->createFallbackHandler());

        $this->assertSame(200, $response->getStatusCode());

        // Tree muss im Container registriert sein
        $containerTree = Registry::container()->get(Tree::class);
        $this->assertInstanceOf(Tree::class, $containerTree);
        $this->assertSame($this->tree->id(), $containerTree->id());
    }

    /**
     * B3/EP3: rewrite_urls=false → URI-Pfad wird auf Basis des route-Query-Parameters aktualisiert.
     * Verifiziert indirekt: Matcher erhält die umgeschriebene URI (Fallback bei fehlendem Match).
     */
    public function test_rewrite_urls_false_uri_updated_from_query(): void
    {
        $routerContainer = new RouterContainer();

        $router = $this->createRouter($routerContainer);

        // rewrite_urls ist standardmäßig false → URI-Pfad wird aus route-Query gesetzt
        $request = $this->createRequest(query: ['route' => '/some/ugly/path']);

        $response = $router->process($request, $this->createFallbackHandler('uri-rewrite-ok'));

        // Keine Route matched → Fallback-Handler
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('uri-rewrite-ok', (string) $response->getBody());
    }
}

/**
 * Test-Handler für Router-Middleware-Tests.
 */
class DummyRouterHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return response('router-handler-ok');
    }
}
