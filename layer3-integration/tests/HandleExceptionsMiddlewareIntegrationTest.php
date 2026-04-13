<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Middleware\HandleExceptions;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\PhpService;
use League\Flysystem\UnableToReadFile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: HandleExceptions-Middleware (M16).
 *
 * Prüft Exception-Handling: HttpException, FilesystemException, Throwable
 * mit verschiedenen Request-Kontexten (regulär, AJAX GET, AJAX POST).
 *
 * @see docs/tds_conditions_ref.md M16
 * @covers \Fisharebest\Webtrees\Http\Middleware\HandleExceptions
 */
class HandleExceptionsMiddlewareIntegrationTest extends MysqlTestCase
{
    private function createMiddleware(): HandleExceptions
    {
        $phpService = $this->createStub(PhpService::class);
        $phpService->method('displayErrors')->willReturn(false);

        return new HandleExceptions($phpService, $this->treeService);
    }

    private function throwingHandler(\Throwable $exception): RequestHandlerInterface
    {
        return new class ($exception) implements RequestHandlerInterface {
            public function __construct(private readonly \Throwable $exception)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };
    }

    /**
     * Kein Exception → Response wird durchgereicht.
     */
    public function test_no_exception_passes_through(): void
    {
        $middleware = $this->createMiddleware();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', (string) $response->getBody());
    }

    /**
     * EP1/B1: HttpException → httpExceptionResponse mit passendem Status-Code.
     */
    public function test_http_exception_returns_status_code(): void
    {
        $middleware = $this->createMiddleware();
        $handler   = $this->throwingHandler(new HttpNotFoundException('Page not found'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * EP1: HttpException-Response enthält Alert-Message.
     */
    public function test_http_exception_response_contains_message(): void
    {
        $middleware = $this->createMiddleware();
        $handler   = $this->throwingHandler(new HttpNotFoundException('Custom 404 message'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertStringContainsString('Custom 404 message', (string) $response->getBody());
    }

    /**
     * EP3/B3: HttpException + AJAX GET → Status 200 (layouts/ajax).
     */
    public function test_http_exception_ajax_get_returns_200(): void
    {
        $middleware = $this->createMiddleware();
        $handler   = $this->throwingHandler(new HttpNotFoundException('Not Found'));

        // Header auf dem Request setzen UND im Container aktualisieren,
        // weil HandleExceptions den Request aus dem Container holt.
        $request = $this->createRequest()
            ->withHeader('X-Requested-With', 'XMLHttpRequest');
        Registry::container()->set(ServerRequestInterface::class, $request);

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * EP2/B2: FilesystemException → 500 Internal Server Error.
     */
    public function test_filesystem_exception_returns_500(): void
    {
        $middleware = $this->createMiddleware();
        $handler   = $this->throwingHandler(UnableToReadFile::fromLocation('/some/path'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(500, $response->getStatusCode());
    }

    /**
     * EP5/B5: Throwable + regulärer Request → 500.
     *
     * HandleExceptions räumt bei Throwable alle Output-Buffer auf (ob_end_clean-Schleife).
     * PHPUnit-OB-Level muss danach wiederhergestellt werden.
     */
    public function test_throwable_returns_500(): void
    {
        $middleware = $this->createMiddleware();
        $handler   = $this->throwingHandler(new \RuntimeException('Unexpected error'));

        $obLevel  = ob_get_level();
        $response = $middleware->process($this->createRequest(), $handler);
        while (ob_get_level() < $obLevel) {
            ob_start();
        }

        $this->assertSame(500, $response->getStatusCode());
    }

    /**
     * EP5/B5: Throwable-Response enthält Exception-Message.
     */
    public function test_throwable_response_contains_error_message(): void
    {
        $middleware = $this->createMiddleware();
        $handler   = $this->throwingHandler(new \RuntimeException('Test error message'));

        $obLevel  = ob_get_level();
        $response = $middleware->process($this->createRequest(), $handler);
        while (ob_get_level() < $obLevel) {
            ob_start();
        }

        $this->assertStringContainsString('Test error message', (string) $response->getBody());
    }
}
