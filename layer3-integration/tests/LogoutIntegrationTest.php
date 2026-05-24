<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\GuestUser;
use Fisharebest\Webtrees\Http\RequestHandlers\Logout;

/**
 * Komponentenintegrationstest: Logout HTTP-Handler.
 *
 * Tests:
 * - Angemeldeter Benutzer → 302 (Redirect zur HomePage), Auth::id() === null.
 * - Gast-Benutzer → 302 (Redirect zur HomePage), kein Logout-Effekt notwendig.
 * - Ajax-Request (x-requested-with: XMLHttpRequest) → 204 (No Content), Auth::id() === null.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\Logout
 * @see docs/tds_conditions_ref.md P43
 */
class LogoutIntegrationTest extends MysqlTestCase
{
    /**
     * Angemeldeter Benutzer ruft Logout auf: Handler protokolliert das
     * Logout, ruft Auth::logout() auf, setzt eine Flash-Message und leitet
     * zur HomePage weiter (302). Auth::id() ist danach null.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_logs_out_authenticated_user(): void
    {
        // Arrange
        $user = $this->ensureUser('logoutuser', 'Logout User', 'logout@example.com', 'secret');
        $user->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1');
        $user->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '1');

        Auth::login($user);
        self::assertSame($user->id(), Auth::id());

        $handler = new Logout();
        $request = $this->createRequest()->withAttribute('user', $user);

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect (302) zur HomePage, Auth-Status ist abgemeldet.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertNull(Auth::id());
    }

    /**
     * Gast-Benutzer (kein eingeloggter User) ruft Logout auf: Handler tut
     * fachlich nichts (kein Log, kein Auth::logout), liefert aber den
     * gleichen Redirect (302) zur HomePage zurück.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_guest_user_without_logout(): void
    {
        // Arrange
        $handler = new Logout();
        $request = $this->createRequest()->withAttribute('user', new GuestUser());

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect (302) zur HomePage.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Ajax-Logout: Trägt der Request den Header `x-requested-with:
     * XMLHttpRequest`, antwortet der Handler statt mit 302 mit 204 (No
     * Content). Auth::id() ist danach null.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_returns_no_content_for_ajax_logout(): void
    {
        // Arrange
        $user = $this->ensureUser('ajaxlogout', 'Ajax Logout', 'ajax@example.com', 'secret');
        $user->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1');
        $user->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '1');

        Auth::login($user);

        $handler = new Logout();
        $request = $this->createRequest()
            ->withAttribute('user', $user)
            ->withHeader('x-requested-with', 'XMLHttpRequest');

        // Act
        $response = $handler->handle($request);

        // Assert: 204 No Content für Ajax, Auth-Status ist abgemeldet.
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
        self::assertNull(Auth::id());
    }

    /**
     * Liefert einen Test-User idempotent: bei Wiederholung des Test-Laufs
     * (DB bleibt zwischen Test-Aufrufen befüllt) wird der vorhandene User
     * wieder verwendet, statt einen Duplicate-Key-Fehler zu provozieren.
     */
    private function ensureUser(string $userName, string $realName, string $email, string $password): UserInterface
    {
        return $this->userService->findByUserName($userName)
            ?? $this->userService->create($userName, $realName, $email, $password);
    }
}
