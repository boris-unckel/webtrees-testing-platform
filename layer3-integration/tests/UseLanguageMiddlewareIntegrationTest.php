<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Middleware\UseLanguage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\LanguageEnglishUnitedStates;
use Fisharebest\Webtrees\Module\ModuleLanguageInterface;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: UseLanguage-Middleware (M13).
 *
 * Prüft die Sprachauswahl: Session-basiert (wenn Modul aktiv), Fallback
 * auf en-US bei fehlendem/unbekanntem Session-Wert.
 *
 * @see docs/tds_conditions_ref.md M13
 * @covers \Fisharebest\Webtrees\Http\Middleware\UseLanguage
 */
class UseLanguageMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1/B1: Session enthält Language-Code, Modul aktiv → Sprache aus Session verwendet.
     */
    public function test_session_language_used_when_module_active(): void
    {
        Session::put('language', 'en-US');

        $enUs = new LanguageEnglishUnitedStates();

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('findByInterface')->willReturn(collect([$enUs]));

        $middleware = new UseLanguage($moduleService);
        $handler   = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('en-US', Session::get('language'));
    }

    /**
     * EP4/B4: Kein Session-Wert, kein Browser-Header → Fallback auf en-US.
     */
    public function test_no_session_language_falls_back_to_english(): void
    {
        Session::forget('language');

        $enUs = new LanguageEnglishUnitedStates();

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('findByInterface')->willReturn(collect([$enUs]));

        $middleware = new UseLanguage($moduleService);
        $handler   = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        // Fallback-Sprache muss in Session gespeichert werden
        $this->assertSame('en-US', Session::get('language'));
    }

    /**
     * EP2/B2: Session enthält ungültigen Language-Code → Fallback auf en-US.
     */
    public function test_unknown_session_language_falls_back_to_english(): void
    {
        Session::put('language', 'nonexistent_xyz');

        $enUs = new LanguageEnglishUnitedStates();

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('findByInterface')->willReturn(collect([$enUs]));

        $middleware = new UseLanguage($moduleService);
        $handler   = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        // Session wird auf Fallback-Sprache aktualisiert
        $this->assertSame('en-US', Session::get('language'));
    }

    /**
     * Verifiziert, dass die Handler-Response unverändert zurückgegeben wird.
     */
    public function test_handler_response_returned_unchanged(): void
    {
        $enUs = new LanguageEnglishUnitedStates();

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('findByInterface')->willReturn(collect([$enUs]));

        $middleware = new UseLanguage($moduleService);
        $handler   = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('Expected-Body'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame('Expected-Body', (string) $response->getBody());
    }
}
