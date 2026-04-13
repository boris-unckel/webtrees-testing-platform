<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Middleware\ContentLength;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: ContentLength-Middleware (M20).
 *
 * Prüft das Setzen des Content-Length-Headers auf Basis der Body-Größe.
 * Drei Branches: Header bereits vorhanden, Body-Größe null, Body-Größe bekannt.
 *
 * @see docs/tds_conditions_ref.md M20
 * @covers \Fisharebest\Webtrees\Http\Middleware\ContentLength
 */
class ContentLengthMiddlewareIntegrationTest extends MysqlTestCase
{
    private ContentLength $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ContentLength();
    }

    /**
     * B1/EP1: content-length Header bereits vorhanden → Response unverändert.
     */
    public function test_existing_header_not_overwritten(): void
    {
        $innerResponse = response('Hello World')
            ->withHeader('content-length', '42');

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($innerResponse);

        $response = $this->middleware->process($this->createRequest(), $handler);

        $this->assertSame('42', $response->getHeaderLine('content-length'));
    }

    /**
     * B2/EP2: Body getSize() gibt null zurück → kein Header gesetzt.
     */
    public function test_body_size_null_no_header_set(): void
    {
        $body = $this->createStub(StreamInterface::class);
        $body->method('getSize')->willReturn(null);

        $innerResponse = response('')->withBody($body);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($innerResponse);

        $response = $this->middleware->process($this->createRequest(), $handler);

        $this->assertFalse($response->hasHeader('content-length'));
    }

    /**
     * B3/EP3: Body-Size bekannt → content-length Header mit korrektem Wert.
     */
    public function test_body_size_known_header_set(): void
    {
        $innerResponse = response('Hello World');
        // response() erzeugt einen Body mit bekannter Größe (11 Bytes)

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($innerResponse);

        $response = $this->middleware->process($this->createRequest(), $handler);

        $this->assertTrue($response->hasHeader('content-length'));
        $this->assertSame((string) strlen('Hello World'), $response->getHeaderLine('content-length'));
    }

    /**
     * BVA: Body-Size 0 → content-length Header mit Wert "0".
     */
    public function test_body_size_zero_header_set(): void
    {
        $innerResponse = response('');

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($innerResponse);

        $response = $this->middleware->process($this->createRequest(), $handler);

        // Leerer Body hat getSize() === 0, nicht null
        $this->assertTrue($response->hasHeader('content-length'));
        $this->assertSame('0', $response->getHeaderLine('content-length'));
    }
}
