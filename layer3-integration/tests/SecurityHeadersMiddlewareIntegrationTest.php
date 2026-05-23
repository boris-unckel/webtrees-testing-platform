<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\SecurityHeaders;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: SecurityHeaders-Middleware.
 *
 * Prüft das Hinzufügen sicherheitsrelevanter HTTP-Header (Permissions-Policy,
 * Referrer-Policy, X-Content-Type-Options, X-Frame-Options, X-XSS-Protection)
 * sowie das HSTS-Verhalten in Abhängigkeit von HTTPS-Base-URL.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/Middleware/SecurityHeadersTest.php
 * @covers \Fisharebest\Webtrees\Http\Middleware\SecurityHeaders
 */
class SecurityHeadersMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * Verifiziert, dass alle Standard-Security-Header gesetzt werden — inkl. HSTS bei HTTPS-Base-URL.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/Middleware/SecurityHeadersTest.php
     * @group ported-l2-doubles
     */
    public function test_security_headers_are_added(): void
    {
        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('', 200));

        $request    = $this->createRequest();
        $middleware = new SecurityHeaders();
        $response   = $middleware->process($request, $handler);

        self::assertSame('browsing-topics=()', $response->getHeaderLine('Permissions-Policy'));
        self::assertSame('same-origin', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('1; mode=block', $response->getHeaderLine('X-XSS-Protection'));
        self::assertSame('max-age=31536000', $response->getHeaderLine('Strict-Transport-Security'));
    }

    /**
     * Verifiziert, dass die Middleware bereits vorhandene Header (z. B. X-Frame-Options: DENY) nicht überschreibt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/Middleware/SecurityHeadersTest.php
     * @group ported-l2-doubles
     */
    public function test_existing_headers_are_not_overwritten(): void
    {
        $inner_response = response('', 200)
            ->withHeader('X-Frame-Options', 'DENY');

        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($inner_response);

        $request    = $this->createRequest();
        $middleware = new SecurityHeaders();
        $response   = $middleware->process($request, $handler);

        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
    }

    /**
     * Verifiziert, dass Strict-Transport-Security nur bei HTTPS-Base-URL gesetzt wird,
     * während die übrigen Security-Header weiterhin appliziert werden.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/Middleware/SecurityHeadersTest.php
     * @group ported-l2-doubles
     */
    public function test_hsts_only_for_https(): void
    {
        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('', 200));

        $request    = $this->createRequest(attributes: ['base_url' => 'http://webtrees.test']);
        $middleware = new SecurityHeaders();
        $response   = $middleware->process($request, $handler);

        self::assertSame('', $response->getHeaderLine('Strict-Transport-Security'));
        // Other security headers should still be present.
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }
}
