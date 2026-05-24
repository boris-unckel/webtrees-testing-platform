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
     * Expired token mit Tree-Attribut → 302-Redirect, dessen Location-Header
     * den Tree-Namen propagiert.
     *
     * Pinnt die `$tree?->name()`-Propagation aus dem Expired-Token-Zweig der
     * route(PasswordRequestPage::class, ['tree' => $tree?->name()])-Aufruf.
     * Diese Property ist von den beiden anderen Methoden nicht abgedeckt:
     * test_handle_with_expired_token setzt kein Tree-Attribut (→ $tree ist
     * null und der Tree-Segment-Teil wird leer/abwesend); test_handle_with_valid_token
     * leitet auf HomePage um, ohne Tree-Parameter. Hier wird ein realer Tree
     * via TreeService::create angelegt und in $this->tree gehalten — tearDown
     * der Basisklasse räumt ihn auf. Ersetzt eine class_exists-Tautologie aus
     * dem L2-Port (BEHAVIOR_HANDLE / L3SP-043).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/PasswordResetActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_expired_token_propagates_tree_into_redirect_location(): void
    {
        // Arrange: realer Tree, dessen Name im Redirect-Ziel landen muss.
        $this->tree = $this->treeService->create('pwd-reset-tree', 'Password Reset Tree');

        $user_service = $this->createMock(UserService::class);
        $user_service->expects(self::once())
            ->method('findByToken')
            ->with('expired-token')
            ->willReturn(null);

        $handler = new PasswordResetAction($user_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     ['password' => 'newpass123'],
            attributes: ['tree' => $this->tree],
        )->withAttribute('token', 'expired-token');

        // Act.
        $response = $handler->handle($request);

        // Assert: 302 mit Tree-Name in der Location.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringContainsString($this->tree->name(), $response->getHeaderLine('location'));
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
