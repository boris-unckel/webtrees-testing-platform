<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Middleware\UseSession;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: UseSession-Middleware (M06).
 *
 * Prüft Session-Lifecycle: Start, User-Zuweisung im Request und Container,
 * Session-Save nach Handler.
 *
 * Hinweis: Die Timestamp-Aktualisierung (EP3–EP5) und Masquerade-Erkennung
 * erfordern eine authentifizierte PHP-Session mit Session-Cookie im Request.
 * Da die Middleware Session::start($request) aufruft (was bei fehlendem Cookie
 * eine leere Session erzeugt), sind diese Szenarien hier nur für den Guest-Fall
 * testbar. Vollständige Timestamp-Tests finden in L4 (Playwright) statt, wo
 * der komplette HTTP-Lifecycle mit Login-Cookie zur Verfügung steht.
 *
 * @see docs/tds_conditions_ref.md M06
 * @covers \Fisharebest\Webtrees\Http\Middleware\UseSession
 */
class UseSessionMiddlewareIntegrationTest extends MysqlTestCase
{
    private UseSession $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new UseSession();
    }

    /**
     * EP2: Session wird gestartet, User im Request gesetzt.
     */
    public function test_session_started_and_user_set_in_request(): void
    {
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

        $request = $this->createRequest();
        $this->middleware->process($request, $handler);

        $this->assertNotNull($capture->request);
        $user = $capture->request->getAttribute('user');
        $this->assertInstanceOf(UserInterface::class, $user);
    }

    /**
     * Verifiziert, dass der User nach Middleware-Durchlauf im Container registriert ist.
     */
    public function test_user_registered_in_container(): void
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $this->middleware->process($this->createRequest(), $handler);

        $containerUser = Registry::container()->get(UserInterface::class);
        $this->assertInstanceOf(UserInterface::class, $containerUser);
    }

    /**
     * Verifiziert, dass Session::save() nach dem Handler aufgerufen wird.
     * Indirekt: Session-Daten, die im Handler gesetzt werden, bleiben erhalten.
     */
    public function test_session_saved_after_handler(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // Setze einen Session-Wert im Handler
                Session::put('test_session_m06', 'handler-was-here');

                return response('OK');
            }
        };

        $this->middleware->process($this->createRequest(), $handler);

        // Nach Session::save() sollte der Wert noch verfügbar sein
        // (Session::save() schreibt die Daten, Session::clear() in tearDown räumt auf)
        $this->assertSame('handler-was-here', Session::get('test_session_m06'));
    }

    /**
     * EP1: Bereits aktive Session wird vor Neustart zerstört.
     * Verifiziert, dass die Middleware keine Exception wirft, wenn eine Session bereits aktiv ist.
     */
    public function test_active_session_destroyed_before_restart(): void
    {
        // Starte eine Session manuell, um den "already active" Zustand herzustellen
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        // Die Middleware sollte session_destroy() aufrufen und neu starten
        $response = $this->middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Verifiziert, dass die Response des Handlers unverändert zurückgegeben wird.
     */
    public function test_handler_response_returned_unchanged(): void
    {
        $expectedResponse = response('Expected-Response-Body');

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $response = $this->middleware->process($this->createRequest(), $handler);

        $this->assertSame('Expected-Response-Body', (string) $response->getBody());
    }
}
