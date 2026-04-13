<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\UseTheme;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use Fisharebest\Webtrees\Module\WebtreesTheme;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: UseTheme-Middleware (M14).
 *
 * Prüft die Theme-Auswahl: Session-Theme, Site-Default, Fallback auf WebtreesTheme.
 *
 * @see docs/tds_conditions_ref.md M14
 * @covers \Fisharebest\Webtrees\Http\Middleware\UseTheme
 */
class UseThemeMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * Capture-Handler, der das Theme-Attribut aus dem Request extrahiert.
     */
    private function captureHandler(\stdClass $capture): RequestHandlerInterface
    {
        return new class ($capture) implements RequestHandlerInterface {
            public function __construct(private readonly \stdClass $capture)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capture->theme = $request->getAttribute('theme');

                return response('OK');
            }
        };
    }

    /**
     * EP1/B1: Session enthält Theme-Name, Modul aktiv → Session-Theme verwendet.
     */
    public function test_session_theme_used_when_module_active(): void
    {
        $theme = new WebtreesTheme();
        Session::put('theme', $theme->name());

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('findByInterface')->willReturn(collect([$theme]));

        $middleware = new UseTheme($moduleService);
        $capture   = new \stdClass();

        $response = $middleware->process($this->createRequest(), $this->captureHandler($capture));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(ModuleThemeInterface::class, $capture->theme);
        $this->assertSame($theme->name(), Session::get('theme'));
    }

    /**
     * EP2/B2+B3: Kein Session-Theme, Site-Default gesetzt → Site-Default verwendet.
     */
    public function test_site_default_theme_used_when_no_session_theme(): void
    {
        Session::forget('theme');
        $theme = new WebtreesTheme();
        Site::setPreference('THEME_DIR', $theme->name());

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('findByInterface')->willReturn(collect([$theme]));

        $middleware = new UseTheme($moduleService);
        $capture   = new \stdClass();

        $response = $middleware->process($this->createRequest(), $this->captureHandler($capture));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(ModuleThemeInterface::class, $capture->theme);
        $this->assertSame($theme->name(), Session::get('theme'));
    }

    /**
     * EP3/B4: Keine Themes in Collection → Fallback auf WebtreesTheme.
     */
    public function test_fallback_to_webtrees_theme(): void
    {
        Session::forget('theme');

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('findByInterface')->willReturn(collect([]));

        $middleware = new UseTheme($moduleService);
        $capture   = new \stdClass();

        $response = $middleware->process($this->createRequest(), $this->captureHandler($capture));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(WebtreesTheme::class, $capture->theme);
    }

    /**
     * Handler-Response wird unverändert zurückgegeben.
     */
    public function test_handler_response_returned_unchanged(): void
    {
        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('findByInterface')->willReturn(collect([new WebtreesTheme()]));

        $middleware = new UseTheme($moduleService);
        $handler   = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('Expected'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame('Expected', (string) $response->getBody());
    }
}
