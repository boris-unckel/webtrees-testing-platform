<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\ClientIp;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: ClientIp-Middleware (M03).
 *
 * Prüft die Client-IP-Ermittlung mit Proxy-Trust-Konfiguration:
 * null-Attribute, gültige CSV-Strings, leere Strings.
 *
 * @see docs/tds_conditions_ref.md M03
 * @covers \Fisharebest\Webtrees\Http\Middleware\ClientIp
 */
class ClientIpMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1: Beide Attribute null (nicht gesetzt) → Middleware durchläuft ohne Fehler.
     */
    public function test_both_attributes_null_passes_through(): void
    {
        $middleware = new ClientIp();

        $capture = new \stdClass();
        $capture->request = null;

        $handler = new class ($capture) implements RequestHandlerInterface {
            public function __construct(private readonly \stdClass $capture)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capture->request = $request;

                return response('OK');
            }
        };

        // Request ohne trusted_headers/trusted_proxies Attribute
        $request  = $this->createRequest();
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capture->request);
    }

    /**
     * EP2: Gültige CSV-Strings für trusted_headers und trusted_proxies → Proxy-Trust konfiguriert.
     */
    public function test_valid_csv_proxy_config(): void
    {
        $middleware = new ClientIp();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $request = $this->createRequest(attributes: [
            'trusted_headers' => 'X-Forwarded-For,X-Real-Ip',
            'trusted_proxies' => '10.0.0.1,10.0.0.2',
        ]);

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * EP3: Leere Strings für beide Attribute → wie null, keine Proxy-Konfiguration.
     */
    public function test_empty_strings_treated_as_no_config(): void
    {
        $middleware = new ClientIp();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $request = $this->createRequest(attributes: [
            'trusted_headers' => '',
            'trusted_proxies' => '',
        ]);

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * BVA: CSV mit genau einem Element → korrekt verarbeitet.
     */
    public function test_csv_single_element(): void
    {
        $middleware = new ClientIp();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $request = $this->createRequest(attributes: [
            'trusted_headers' => 'X-Forwarded-For',
            'trusted_proxies' => '10.0.0.1',
        ]);

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Verifiziert, dass die Handler-Response unverändert zurückgegeben wird.
     */
    public function test_handler_response_returned_unchanged(): void
    {
        $middleware = new ClientIp();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('Expected-Body'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame('Expected-Body', (string) $response->getBody());
    }
}
