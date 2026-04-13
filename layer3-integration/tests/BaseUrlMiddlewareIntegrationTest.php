<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\BaseUrl;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: BaseUrl-Middleware (M09).
 *
 * Prüft die Base-URL-Ermittlung: Auto-Detection bei leerem base_url,
 * URI-Aktualisierung bei konfiguriertem base_url, Port-Handling.
 *
 * @see docs/tds_conditions_ref.md M09
 * @covers \Fisharebest\Webtrees\Http\Middleware\BaseUrl
 */
class BaseUrlMiddlewareIntegrationTest extends MysqlTestCase
{
    private function captureHandler(\stdClass $capture): RequestHandlerInterface
    {
        return new class ($capture) implements RequestHandlerInterface {
            public function __construct(private readonly \stdClass $capture)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capture->request = $request;

                return response('OK');
            }
        };
    }

    /**
     * EP1/B1: base_url leer + URI mit index.php → Auto-Detection.
     */
    public function test_empty_base_url_auto_detects_from_uri(): void
    {
        $middleware = new BaseUrl();

        $capture          = new \stdClass();
        $capture->request = null;

        // createRequest erzeugt URI mit /index.php, base_url wird auf '' überschrieben
        $request  = $this->createRequest(attributes: ['base_url' => '']);
        $response = $middleware->process($request, $this->captureHandler($capture));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capture->request);

        // base_url-Attribut muss auf auto-detektierten Wert gesetzt sein
        $baseUrl = $capture->request->getAttribute('base_url');
        $this->assertNotEmpty($baseUrl);
        $this->assertStringStartsWith('https://', $baseUrl);
    }

    /**
     * EP3/B4: base_url gesetzt → Scheme, Host und Port aus konfigurierter URL.
     */
    public function test_configured_base_url_updates_request_uri(): void
    {
        $middleware = new BaseUrl();

        $capture          = new \stdClass();
        $capture->request = null;

        $request  = $this->createRequest(attributes: ['base_url' => 'https://example.com/family']);
        $response = $middleware->process($request, $this->captureHandler($capture));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capture->request);

        $uri = $capture->request->getUri();
        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
    }

    /**
     * EP5/B4: base_url mit explizitem Port → Port korrekt übernommen.
     */
    public function test_configured_base_url_with_explicit_port(): void
    {
        $middleware = new BaseUrl();

        $capture          = new \stdClass();
        $capture->request = null;

        $request  = $this->createRequest(attributes: ['base_url' => 'https://example.com:8080/path']);
        $response = $middleware->process($request, $this->captureHandler($capture));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capture->request);

        $uri = $capture->request->getUri();
        $this->assertSame(8080, $uri->getPort());
    }

    /**
     * EP4/B5: base_url ohne Port → Default-Port (null in PSR-7).
     */
    public function test_configured_base_url_without_port_uses_default(): void
    {
        $middleware = new BaseUrl();

        $capture          = new \stdClass();
        $capture->request = null;

        $request  = $this->createRequest(attributes: ['base_url' => 'http://localhost']);
        $response = $middleware->process($request, $this->captureHandler($capture));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capture->request);

        $uri = $capture->request->getUri();
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('localhost', $uri->getHost());
        $this->assertNull($uri->getPort());
    }
}
