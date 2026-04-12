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
use PHPUnit\Framework\Attributes\DataProvider;
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
 * @see docs/tds_conditions_ref.md SEC-BOT01
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

    /**
     * Erstellt einen minimal ausgestatteten Server-Request mit UA, Pfad und optionalen Cookies.
     *
     * @param array<string, string> $cookies
     */
    private function makeRequest(
        string $ua = 'Mozilla/5.0 (compatible; TestClient/1.0)',
        string $path = '/',
        array $cookies = [],
    ): ServerRequestInterface {
        $route = new Route();
        $route->name('dummy');

        $request = new ServerRequest(
            'GET',
            'https://webtrees.test' . $path,
            [],
            null,
            '1.1',
            ['HTTP_USER_AGENT' => $ua],
        );

        $request = $request
            ->withAttribute('base_url', 'https://webtrees.test')
            ->withAttribute('client-ip', '127.0.0.1')
            ->withAttribute('route', $route);

        if ($cookies !== []) {
            $request = $request->withCookieParams($cookies);
        }

        Registry::container()->set(ServerRequestInterface::class, $request);

        return $request;
    }

    /** Erstellt einen minimal ausgestatteten Server-Request mit gesetztem UA. */
    private function makeRequestWithUa(string $ua): ServerRequestInterface
    {
        return $this->makeRequest($ua);
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

    /**
     * @return array<string, array{string}>
     */
    public static function badRobotUserAgents(): array
    {
        return [
            'AhrefsBot (SEO)'      => ['AhrefsBot/7.0'],
            'SemrushBot (SEO)'     => ['SemrushBot/7.0'],
            'GPTBot (AI)'          => ['GPTBot/1.0'],
            'ClaudeBot (AI)'       => ['ClaudeBot/1.0'],
            'CensysInspect (Sec)'  => ['CensysInspect/1.1'],
        ];
    }

    /**
     * Verschiedene bekannte Bad-Bot-Kategorien werden via BAD_ROBOTS blockiert (EP1–EP5).
     */
    #[DataProvider('badRobotUserAgents')]
    public function test_bad_robots_by_category_blocked(string $ua): void
    {
        $request  = $this->makeRequest($ua);
        $response = $this->middleware->process($request, $this->okHandler);

        $this->assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function wordpressScannerPaths(): array
    {
        return [
            '/wp-admin/'         => ['/wp-admin/'],
            '/wp-login.php'      => ['/wp-login.php'],
            '/xmlrpc.php'        => ['/xmlrpc.php'],
            '/wp-content/themes' => ['/wp-content/themes/'],
        ];
    }

    /**
     * WordPress-Scanner-Pfade werden blockiert (EP12–EP14, EP16).
     */
    #[DataProvider('wordpressScannerPaths')]
    public function test_wordpress_scanner_paths_blocked(string $path): void
    {
        $request  = $this->makeRequest('Mozilla/5.0 (compatible; TestClient/1.0)', $path);
        $response = $this->middleware->process($request, $this->okHandler);

        $this->assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    /**
     * Normaler Pfad `/` wird nicht als WordPress-Scanner erkannt (EP15).
     */
    public function test_normal_path_not_blocked(): void
    {
        $request  = $this->makeRequest('Mozilla/5.0 (compatible; TestClient/1.0)', '/');
        $response = $this->middleware->process($request, $this->okHandler);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Browser mit Cookies: Cookie-Heuristik greift nicht, Handler wird aufgerufen (EP8).
     */
    public function test_browser_with_cookies_passes_through(): void
    {
        $request  = $this->makeRequest(
            'Mozilla/5.0 Chrome/120.0 Safari/537.36',
            '/',
            ['session' => 'abc123'],
        );
        $response = $this->middleware->process($request, $this->okHandler);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Browser ohne Cookies, Chrome-UA: Cookie-Check-Response (406 + set-cookie) (EP9).
     * Der SUT sendet eine Cookie-Check-Seite als 406-Antwort mit Set-Cookie-Header.
     */
    public function test_browser_without_cookies_gets_cookie_check_response(): void
    {
        $request  = $this->makeRequest('Mozilla/5.0 Chrome/120.0 Safari/537.36');
        $response = $this->middleware->process($request, $this->okHandler);

        $this->assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('set-cookie'));
    }
}
