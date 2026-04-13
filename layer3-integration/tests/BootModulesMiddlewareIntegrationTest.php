<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\BootModules;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use Fisharebest\Webtrees\Services\ModuleService;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: BootModules-Middleware (M26).
 *
 * Prüft, dass ModuleService::bootModules() korrekt aufgerufen wird und
 * der Request anschließend an den Handler weitergegeben wird.
 *
 * @see docs/tds_conditions_ref.md M26
 * @covers \Fisharebest\Webtrees\Http\Middleware\BootModules
 */
class BootModulesMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1: bootModules() wird mit Theme aufgerufen, Handler wird ausgeführt.
     */
    public function test_boot_modules_called_and_handler_invoked(): void
    {
        $theme = $this->createStub(ModuleThemeInterface::class);

        $moduleService = $this->createMock(ModuleService::class);
        $moduleService->expects($this->once())
            ->method('bootModules')
            ->with($theme);

        $expectedResponse = response('OK');
        $handler          = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $middleware = new BootModules($moduleService, $theme);
        $response   = $middleware->process($this->createRequest(), $handler);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Verifiziert, dass bootModules() VOR dem Handler aufgerufen wird.
     */
    public function test_boot_modules_runs_before_handler(): void
    {
        $tracker        = new \stdClass();
        $tracker->order = [];

        $theme = $this->createStub(ModuleThemeInterface::class);

        $moduleService = $this->createMock(ModuleService::class);
        $moduleService->expects($this->once())
            ->method('bootModules')
            ->willReturnCallback(function () use ($tracker): void {
                $tracker->order[] = 'bootModules';
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

        $middleware = new BootModules($moduleService, $theme);
        $middleware->process($this->createRequest(), $handler);

        $this->assertSame(['bootModules', 'handler'], $tracker->order);
    }
}
