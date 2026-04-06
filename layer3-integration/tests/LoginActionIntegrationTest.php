<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\LoginAction;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\UpgradeService;

/**
 * Komponentenintegrationstest: LoginAction (Anmeldungs-Aktion) — P39.
 *
 * Tests:
 * - LoginAction POST in CLI-Kontext (kein Cookie-Support) → 302 zu LoginPage
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LoginAction
 * @see docs/testquality_improve_P39.md
 */
class LoginActionIntegrationTest extends MysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
    }

    /**
     * EP1: In PHP CLI ist $_COOKIE === [] → doLogin() wirft Cookie-Exception.
     * LoginAction fängt die Exception und leitet zu LoginPage weiter → 302.
     */
    public function test_login_action_redirects_when_cookies_disabled(): void
    {
        $handler = new LoginAction(
            Registry::container()->get(UpgradeService::class),
            $this->userService,
        );

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username' => 'nonexistent-user',
                'password' => 'wrong-password',
                'url'      => '',
            ],
        );

        // In CLI: $_COOKIE === [] → doLogin wirft Exception → handler fängt → 302 zu LoginPage
        $response = $handler->handle($request);

        // Handler fängt die Cookie-Exception → Redirect (302), kein Exception-Propagation
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        // Location-Header enthält den angegebenen Username (aus LoginPage-Route-Parameter)
        self::assertStringContainsString('nonexistent-user', $response->getHeaderLine('Location'));
    }
}
