<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Aura\Router\Route;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Middleware\BadBotBlocker;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\NetworkService;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: BadBotBlocker HTTP-Middleware.
 *
 * AP C-07: BadBotBlocker::process (CRAP 870)
 *
 * DNS-Branches (ROBOT_REV_FWD_DNS, ROBOT_REV_ONLY_DNS) bleiben ungetestet —
 * erfordern Netzwerkzugriff und sind in Unit-Tests nicht sinnvoll abbildbar.
 *
 * @see docs/testing-bigpicture.md SEC-BOT01
 * @covers \Fisharebest\Webtrees\Http\Middleware\BadBotBlocker
 */
class BadBotBlockerIntegrationTest extends MysqlTestCase
{
    private BadBotBlocker $middleware;

    /** Minimaler Request-Handler-Stub, der immer 200 zurückgibt. */
    private RequestHandlerInterface $okHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new BadBotBlocker(new NetworkService());

        $this->okHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return response('OK', StatusCodeInterface::STATUS_OK);
            }
        };
    }

    /** Erstellt einen minimal ausgestatteten Server-Request mit gesetztem UA. */
    private function makeRequestWithUa(string $ua): ServerRequestInterface
    {
        $route = new Route();
        $route->name('dummy');

        $request = new ServerRequest(
            'GET',
            'https://webtrees.test/',
            [],
            null,
            '1.1',
            ['HTTP_USER_AGENT' => $ua],
        );

        $request = $request
            ->withAttribute('base_url', 'https://webtrees.test')
            ->withAttribute('client-ip', '127.0.0.1')
            ->withAttribute('route', $route);

        Registry::container()->set(ServerRequestInterface::class, $request);

        return $request;
    }

    /**
     * Leerer User-Agent → 406 Not Acceptable.
     */
    public function test_empty_user_agent_blocked(): void
    {
        $request  = $this->makeRequestWithUa('');
        $response = $this->middleware->process($request, $this->okHandler);

        $this->assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    /**
     * Bad-Robot User-Agent → 406 Not Acceptable.
     */
    public function test_bad_robot_user_agent_blocked(): void
    {
        // '008' ist in BadBotBlocker::BAD_ROBOTS
        $request  = $this->makeRequestWithUa('008');
        $response = $this->middleware->process($request, $this->okHandler);

        $this->assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    /**
     * Normaler User-Agent → Handler aufgerufen (200 OK).
     */
    public function test_legitimate_user_agent_passes(): void
    {
        $request  = $this->makeRequestWithUa('Mozilla/5.0 (compatible; TestClient/1.0)');
        $response = $this->middleware->process($request, $this->okHandler);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
