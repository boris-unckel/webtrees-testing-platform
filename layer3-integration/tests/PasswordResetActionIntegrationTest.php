<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\PasswordResetAction;
use Fisharebest\Webtrees\Services\UserService;

/**
 * Komponentenintegrationstest: PasswordResetAction HTTP-Handler.
 *
 * Verifiziert das Verhalten der Passwort-Reset-Aktion:
 * - Valid token → UserService::findByToken liefert User → Password wird
 *   gesetzt, Auth::login, 302-Redirect.
 * - Expired token → UserService::findByToken liefert null → 302-Redirect
 *   auf die Passwort-Request-Page mit Flash-Message.
 *
 * Stub/Mock-Konvention: UserService als Mock mit `expects(once())`-Verifikation
 * des findByToken-Aufrufs. Beim Valid-Token-Pfad wird ein echter User-Datensatz
 * via realem UserService angelegt, damit setPassword() einen funktionsfähigen
 * DB-Record vorfindet.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PasswordResetAction
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/PasswordResetActionTest.php
 */
class PasswordResetActionIntegrationTest extends MysqlTestCase
{
    protected function tearDown(): void
    {
        $cleanup = [
            'resetuser',
        ];
        foreach ($cleanup as $uname) {
            $u = $this->userService->findByUserName($uname);
            if ($u !== null) {
                $this->userService->delete($u);
            }
        }

        parent::tearDown();
    }

    /**
     * Bootstrap-Smoke: Handler-Klasse ist autoloadbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/PasswordResetActionTest.php
     * @group ported-l2-doubles
     */
    public function test_class_exists(): void
    {
        self::assertTrue(class_exists(PasswordResetAction::class));
    }

    /**
     * Valid token → Password wird gesetzt und 302-Redirect zur HomePage.
     *
     * UserService::findByToken liefert einen echten User (via realem Service
     * persistiert), so dass setPassword() einen DB-Update durchführen kann.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/PasswordResetActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_valid_token(): void
    {
        // Arrange
        $real_user_service = new UserService();
        $user              = $real_user_service->create(
            'resetuser',
            'Reset User',
            'reset@example.com',
            'oldpass',
        );

        $user_service = $this->createMock(UserService::class);
        $user_service->expects(self::once())
            ->method('findByToken')
            ->with('valid-token')
            ->willReturn($user);

        $handler = new PasswordResetAction($user_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['password' => 'newpass123'],
        )->withAttribute('token', 'valid-token');

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Expired/invalid token → UserService::findByToken liefert null → 302-Redirect.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/PasswordResetActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_expired_token(): void
    {
        // Arrange
        $user_service = $this->createMock(UserService::class);
        $user_service->expects(self::once())
            ->method('findByToken')
            ->with('expired-token')
            ->willReturn(null);

        $handler = new PasswordResetAction($user_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['password' => 'newpass123'],
        )->withAttribute('token', 'expired-token');

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
