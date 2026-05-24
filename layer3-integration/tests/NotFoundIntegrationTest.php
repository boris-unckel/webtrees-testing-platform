<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\Middleware\BadBotBlocker;
use Fisharebest\Webtrees\Http\RequestHandlers\NotFound;

/**
 * Komponentenintegrationstest: NotFound HTTP-Handler.
 *
 * Verifiziert das Verhalten des 404-Handlers in drei Kanten:
 * - Robot-Request liefert eine schlichte 404-Antwort.
 * - GET ohne Robot-Attribut leitet auf die Home-Page um (302).
 * - Nicht-GET ohne Robot-Attribut wirft HttpNotFoundException.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\NotFound
 * @see docs/tds_conditions_ref.md M29
 */
class NotFoundIntegrationTest extends MysqlTestCase
{
    /**
     * GET ohne Robot-Attribut → Location-Header zeigt auf Home-Page-Route.
     *
     * Komplementaer zu test_handle_get_request_redirects_to_home_page, das nur
     * den 302-Statuscode prueft. Hier wird das eigentliche Redirect-Ziel
     * fixiert: redirect(route(HomePage::class)) erzeugt einen 'location'-Header,
     * der auf die index.php-URL der Webtrees-Installation zeigt
     * (Same-Origin: nutzt das base_url-Attribut der Request).
     *
     * Damit wird der zweite Zweig des Handlers (Container-Registry +
     * route-Generierung) verhaltens-definitiv gepinnt — ohne Annahmen ueber
     * das interne Format der Route-Query (z. B. ?route=0).
     *
     * @group ported-l2-doubles
     */
    public function test_handle_get_request_location_header_points_to_home_page(): void
    {
        $handler = new NotFound();
        $request = $this->createRequest();

        $response = $handler->handle($request);

        $location = $response->getHeaderLine('location');
        self::assertNotSame('', $location, 'Location-Header muss bei 302-Redirect gesetzt sein');
        // Same-Origin: Redirect-Ziel liegt auf der gleichen Webtrees-Installation,
        // erkennbar am base_url-Praefix aus createRequest() und am index.php-Skript.
        self::assertStringStartsWith('https://webtrees.test/', $location);
        self::assertStringContainsString('index.php', $location);
    }

    /**
     * Robot-Request → 404 ohne Redirect.
     *
     * Wenn das Request-Attribut BadBotBlocker::ROBOT_ATTRIBUTE_NAME gesetzt ist,
     * liefert der Handler eine simple 404-Response (kein Routing, keine
     * Fehler-Page).
     *
     * @group ported-l2-doubles
     */
    public function test_handle_robot_returns_not_found(): void
    {
        $handler = new NotFound();
        $request = $this->createRequest()
            ->withAttribute(BadBotBlocker::ROBOT_ATTRIBUTE_NAME, true);

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * GET ohne Robot-Attribut → Redirect zur Home-Page (302).
     *
     * @group ported-l2-doubles
     */
    public function test_handle_get_request_redirects_to_home_page(): void
    {
        $handler = new NotFound();
        $request = $this->createRequest();

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * POST ohne Robot-Attribut → HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_post_request_throws_not_found_exception(): void
    {
        $this->expectException(HttpNotFoundException::class);

        $handler = new NotFound();
        $request = $this->createRequest(method: RequestMethodInterface::METHOD_POST);

        $handler->handle($request);
    }
}
