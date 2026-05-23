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
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotFoundTest.php
 */
class NotFoundIntegrationTest extends MysqlTestCase
{
    /**
     * Bootstrap-Smoke: Handler-Klasse ist autoloadbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotFoundTest.php
     * @group ported-l2-doubles
     */
    public function test_class_exists(): void
    {
        self::assertTrue(class_exists(NotFound::class));
    }

    /**
     * Robot-Request → 404 ohne Redirect.
     *
     * Wenn das Request-Attribut BadBotBlocker::ROBOT_ATTRIBUTE_NAME gesetzt ist,
     * liefert der Handler eine simple 404-Response (kein Routing, keine
     * Fehler-Page).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotFoundTest.php
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
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotFoundTest.php
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
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotFoundTest.php
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
