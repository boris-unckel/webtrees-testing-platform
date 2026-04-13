<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\CompressResponse;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\PhpService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: CompressResponse-Middleware (M19).
 *
 * Prüft gzip/deflate-Kompression: Accept-Encoding-Varianten,
 * Content-Type-Filterung, zlib-Verfügbarkeit, bereits komprimierte Responses.
 *
 * @see docs/tds_conditions_ref.md M19
 * @covers \Fisharebest\Webtrees\Http\Middleware\CompressResponse
 */
class CompressResponseMiddlewareIntegrationTest extends MysqlTestCase
{
    private function createMiddleware(bool $zlibLoaded = true): CompressResponse
    {
        $phpService = $this->createStub(PhpService::class);
        $phpService->method('extensionLoaded')->willReturn($zlibLoaded);

        $streamFactory = Registry::container()->get(StreamFactoryInterface::class);

        return new CompressResponse($phpService, $streamFactory);
    }

    private function handlerWithResponse(string $body, string $contentType = 'text/html'): RequestHandlerInterface
    {
        return new class ($body, $contentType) implements RequestHandlerInterface {
            public function __construct(
                private readonly string $body,
                private readonly string $contentType,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return response($this->body)
                    ->withHeader('content-type', $this->contentType);
            }
        };
    }

    /**
     * EP1/B1: zlib nicht geladen → keine Kompression.
     */
    public function test_no_compression_without_zlib(): void
    {
        $middleware = $this->createMiddleware(zlibLoaded: false);

        $request  = $this->createRequest()
            ->withHeader('Accept-Encoding', 'gzip');
        $response = $middleware->process($request, $this->handlerWithResponse('Hello World'));

        $this->assertSame('Hello World', (string) $response->getBody());
        $this->assertEmpty($response->getHeaderLine('content-encoding'));
    }

    /**
     * EP2/B2: gzip + text/html → Gzip-Kompression.
     */
    public function test_gzip_compression_text_html(): void
    {
        $middleware = $this->createMiddleware();

        $request  = $this->createRequest()
            ->withHeader('Accept-Encoding', 'gzip');
        $response = $middleware->process($request, $this->handlerWithResponse('Hello World'));

        $this->assertSame('gzip', $response->getHeaderLine('content-encoding'));
        $this->assertSame('accept-encoding', $response->getHeaderLine('vary'));
        $this->assertSame('Hello World', gzdecode((string) $response->getBody()));
    }

    /**
     * EP3/B3: deflate + text/html → Deflate-Kompression.
     */
    public function test_deflate_compression_text_html(): void
    {
        $middleware = $this->createMiddleware();

        $request  = $this->createRequest()
            ->withHeader('Accept-Encoding', 'deflate');
        $response = $middleware->process($request, $this->handlerWithResponse('Hello World'));

        $this->assertSame('deflate', $response->getHeaderLine('content-encoding'));
        $this->assertSame('Hello World', gzinflate((string) $response->getBody()));
    }

    /**
     * EP4/B4: kein Accept-Encoding → keine Kompression.
     */
    public function test_no_compression_without_accept_encoding(): void
    {
        $middleware = $this->createMiddleware();

        $response = $middleware->process(
            $this->createRequest(),
            $this->handlerWithResponse('Hello World')
        );

        $this->assertSame('Hello World', (string) $response->getBody());
        $this->assertEmpty($response->getHeaderLine('content-encoding'));
    }

    /**
     * EP5/B7: application/json (MIME_TYPES-Whitelist) → komprimiert.
     */
    public function test_compression_application_json(): void
    {
        $middleware = $this->createMiddleware();

        $request  = $this->createRequest()
            ->withHeader('Accept-Encoding', 'gzip');
        $response = $middleware->process(
            $request,
            $this->handlerWithResponse('{"key":"value"}', 'application/json')
        );

        $this->assertSame('gzip', $response->getHeaderLine('content-encoding'));
    }

    /**
     * EP6/B8: image/png → nicht komprimierbar.
     */
    public function test_no_compression_image_png(): void
    {
        $middleware = $this->createMiddleware();

        $request  = $this->createRequest()
            ->withHeader('Accept-Encoding', 'gzip');
        $response = $middleware->process(
            $request,
            $this->handlerWithResponse("\x89PNG binary", 'image/png')
        );

        $this->assertSame("\x89PNG binary", (string) $response->getBody());
        $this->assertEmpty($response->getHeaderLine('content-encoding'));
    }

    /**
     * EP7/B5: content-encoding bereits vorhanden → nicht erneut komprimieren.
     */
    public function test_no_recompression_if_already_encoded(): void
    {
        $middleware = $this->createMiddleware();

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return response('already compressed')
                    ->withHeader('content-type', 'text/html')
                    ->withHeader('content-encoding', 'br');
            }
        };

        $request  = $this->createRequest()
            ->withHeader('Accept-Encoding', 'gzip');
        $response = $middleware->process($request, $handler);

        $this->assertSame('br', $response->getHeaderLine('content-encoding'));
        $this->assertSame('already compressed', (string) $response->getBody());
    }

    /**
     * B6: text/css → komprimierbar (text/*-Regel).
     */
    public function test_compression_text_css(): void
    {
        $middleware = $this->createMiddleware();

        $request  = $this->createRequest()
            ->withHeader('Accept-Encoding', 'gzip');
        $response = $middleware->process(
            $request,
            $this->handlerWithResponse('body { color: red; }', 'text/css')
        );

        $this->assertSame('gzip', $response->getHeaderLine('content-encoding'));
    }

    /**
     * gzip wird gegenüber deflate bevorzugt (Reihenfolge in compressionMethod).
     */
    public function test_gzip_preferred_over_deflate(): void
    {
        $middleware = $this->createMiddleware();

        $request  = $this->createRequest()
            ->withHeader('Accept-Encoding', 'gzip, deflate');
        $response = $middleware->process(
            $request,
            $this->handlerWithResponse('Hello', 'text/html')
        );

        $this->assertSame('gzip', $response->getHeaderLine('content-encoding'));
    }
}
