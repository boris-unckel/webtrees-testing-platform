<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Http\Middleware\RegisterGedcomTags;
use Fisharebest\Webtrees\Registry;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: RegisterGedcomTags-Middleware (M25).
 *
 * Prüft, dass Gedcom::registerTags() korrekt aufgerufen wird und
 * der Request anschließend an den Handler weitergegeben wird.
 *
 * @see docs/tds_conditions_ref.md M25
 * @covers \Fisharebest\Webtrees\Http\Middleware\RegisterGedcomTags
 */
class RegisterGedcomTagsMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1: registerTags() wird aufgerufen, Handler wird ausgeführt.
     */
    public function test_register_tags_called_and_handler_invoked(): void
    {
        $gedcom = $this->createMock(Gedcom::class);
        $gedcom->expects($this->once())
            ->method('registerTags')
            ->with(Registry::elementFactory(), true);

        $expectedResponse = response('OK');
        $handler          = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $middleware = new RegisterGedcomTags($gedcom);
        $response   = $middleware->process($this->createRequest(), $handler);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Verifiziert, dass registerTags() VOR dem Handler aufgerufen wird.
     */
    public function test_register_tags_runs_before_handler(): void
    {
        $tracker        = new \stdClass();
        $tracker->order = [];

        $gedcom = $this->createMock(Gedcom::class);
        $gedcom->expects($this->once())
            ->method('registerTags')
            ->willReturnCallback(function () use ($tracker): void {
                $tracker->order[] = 'registerTags';
            });

        $handler = new class ($tracker) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(private readonly \stdClass $tracker)
            {
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->tracker->order[] = 'handler';

                return response('OK');
            }
        };

        $middleware = new RegisterGedcomTags($gedcom);
        $middleware->process($this->createRequest(), $handler);

        $this->assertSame(['registerTags', 'handler'], $tracker->order);
    }
}
