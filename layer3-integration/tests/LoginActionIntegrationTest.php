<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpTooManyRequestsException;
use Fisharebest\Webtrees\Http\RequestHandlers\LoginAction;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Komponentenintegrationstest: LoginAction (Anmeldungs-Aktion) — P39.
 *
 * Tests:
 * - LoginAction POST in CLI-Kontext (kein Cookie-Support) → 302 zu LoginPage
 * - Erfolgreiche Anmeldung mit gültigen Credentials → 302, wt_user in Session
 * - Falsches Passwort → 302, keine Session-Anmeldung
 * - Unbekannter Benutzer → 302, keine Session-Anmeldung
 * - Nicht verifizierte E-Mail → 302, keine Session-Anmeldung
 * - Nicht freigegebenes Konto → 302, keine Session-Anmeldung
 *
 * SEC-AUDIT-008 Regressionsabdeckung (verhaltens-definitiv):
 *   Nach Erreichen einer Versuchs-Schwelle muss der Login-Endpoint
 *   einen Rate-Limit-Block auslösen. Akzeptierte Signale: HTTP-Status 429
 *   ODER HttpTooManyRequestsException. Der Handler wird über den DI-
 *   Container geholt, damit die Tests nicht an die konkrete Konstruktor-
 *   Signatur gebunden sind — egal ob die Implementierung als
 *   konstruktor-injiziertes Service, als Middleware oder anders erfolgt.
 *
 * @see docs/tds_conditions_ref.md P39, P44
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LoginAction
 * @see docs/testquality_improve_P39.md
 * @see docs/security-audit/tasks/SEC-AUDIT-008_login_brute_force_no_rate_limit.md
 */
class LoginActionIntegrationTest extends MysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();

        // IP-basierter Rate-Limit-Zähler (SEC-AUDIT-008) wird in Site-
        // Preferences persistiert — beim Testklassen-Start zurücksetzen,
        // damit kein Reststand früherer Läufe den ersten Test in 429 zwingt.
        Site::$preferences = [];
        Site::setPreference('rate-limit-login-127.0.0.1', '');
    }

    protected function tearDown(): void
    {
        // Cookie-Superglobal zwischen Tests bereinigen
        $_COOKIE = [];

        // IP-basierter Rate-Limit-Zähler (SEC-AUDIT-008) wird in Site-
        // Preferences persistiert — Cross-Test-Contamination verhindern,
        // damit nachfolgende Tests nicht ungewollt 429 statt 302 sehen.
        Site::$preferences = [];
        Site::setPreference('rate-limit-login-127.0.0.1', '');

        parent::tearDown();
    }

    /**
     * EP1: In PHP CLI ist $_COOKIE === [] → doLogin() wirft Cookie-Exception.
     * LoginAction fängt die Exception und leitet zu LoginPage weiter → 302.
     */
    public function test_login_action_redirects_when_cookies_disabled(): void
    {
        $handler = Registry::container()->get(LoginAction::class);

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

    /**
     * Erfolgreiche Anmeldung: gültiger, verifizierter, freigegebener Benutzer
     * → handle() ruft Auth::login(), legt wt_user in Session ab und gibt 302 zurück.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_logs_in_user_with_valid_credentials(): void
    {
        // Arrange
        $this->prepareLoginAttempt();
        $user = $this->ensureUser('testuser', 'Test User', 'test@example.com', 'secret123');
        $user->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1');
        $user->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '1');

        $handler  = Registry::container()->get(LoginAction::class);
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username' => 'testuser',
                'password' => 'secret123',
                'url'      => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertSame($user->id(), Session::get('wt_user'));
    }

    /**
     * Falsches Passwort: doLogin() wirft Exception („username or password is
     * incorrect"), handle() fängt sie und gibt 302 zur LoginPage zurück; wt_user
     * bleibt der ursprüngliche Admin-User (keine Anmeldung des Probanden).
     *
     * @group ported-l2-doubles
     */
    public function test_handle_rejects_login_with_invalid_password(): void
    {
        // Arrange
        $this->prepareLoginAttempt();
        $user = $this->ensureUser('wrongpass', 'Wrong Pass', 'wrong@example.com', 'correct');
        $user->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1');
        $user->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '1');
        // Per-User-Rate-Limit für diesen User zurücksetzen (Test isoliert halten).
        $user->setPreference('rate-limit-login', '');

        $handler = Registry::container()->get(LoginAction::class);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username' => 'wrongpass',
                'password' => 'incorrect',
                'url'      => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect zur LoginPage, der Proband ist nicht in der Session.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertNotSame($user->id(), Session::get('wt_user'));
    }

    /**
     * Unbekannter Benutzer: doLogin() findet keinen User, wirft Exception,
     * handle() fängt sie und gibt 302 zurück; Session wt_user bleibt unverändert.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_rejects_login_for_unknown_user(): void
    {
        // Arrange
        $this->prepareLoginAttempt();
        $session_user_before = Session::get('wt_user');

        $handler = Registry::container()->get(LoginAction::class);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username' => 'nonexistent',
                'password' => 'anything',
                'url'      => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect; Session-User unverändert (keine Anmeldung erfolgt).
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertSame($session_user_before, Session::get('wt_user'));
    }

    /**
     * Benutzer mit nicht verifizierter E-Mail: doLogin() wirft Exception,
     * handle() fängt sie und leitet zur LoginPage zurück (302).
     *
     * @group ported-l2-doubles
     */
    public function test_handle_rejects_login_for_unverified_email(): void
    {
        // Arrange
        $this->prepareLoginAttempt();
        $user = $this->ensureUser('unverified', 'Unverified', 'unverified@example.com', 'secret');
        $user->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '0');
        $user->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '1');
        $user->setPreference('rate-limit-login', '');

        $handler = Registry::container()->get(LoginAction::class);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username' => 'unverified',
                'password' => 'secret',
                'url'      => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect, der Proband ist nicht in der Session angemeldet.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertNotSame($user->id(), Session::get('wt_user'));
    }

    /**
     * Benutzer ohne Admin-Freigabe: doLogin() wirft Exception, handle() fängt
     * sie und leitet zur LoginPage zurück (302).
     *
     * @group ported-l2-doubles
     */
    public function test_handle_rejects_login_for_unapproved_account(): void
    {
        // Arrange
        $this->prepareLoginAttempt();
        $user = $this->ensureUser('unapproved', 'Unapproved', 'unapproved@example.com', 'secret');
        $user->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1');
        $user->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '0');
        $user->setPreference('rate-limit-login', '');

        $handler = Registry::container()->get(LoginAction::class);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username' => 'unapproved',
                'password' => 'secret',
                'url'      => '',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect, der Proband ist nicht in der Session angemeldet.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertNotSame($user->id(), Session::get('wt_user'));
    }

    // =========================================================================
    // SEC-AUDIT-008 — Login Rate-Limit (verhaltens-definitiv)
    // =========================================================================

    /**
     * Pre-fix: alle Versuche → 302 (keine Drosselung).
     * Post-fix (egal in welcher Form): nach 10 Fehlversuchen → 429 / Throw.
     */
    public function test_per_user_rate_limit_fires_after_threshold(): void
    {
        $this->prepareLoginAttempt();

        $user = $this->ensureUser(
            'sec008probe',
            'SEC-AUDIT-008 Probe',
            'sec008probe@test.local',
            'irrelevant-probe-pw',
        );
        $user->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1');
        $user->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '1');
        $user->setPreference('rate-limit-login', '');

        $handler = Registry::container()->get(LoginAction::class);

        for ($i = 0; $i < 10; $i++) {
            $response = $handler->handle($this->makeLoginRequest('sec008probe', 'bad-' . $i));
            self::assertSame(
                StatusCodeInterface::STATUS_FOUND,
                $response->getStatusCode(),
                sprintf('Attempt %d: expected 302 (under threshold), got %d', $i + 1, $response->getStatusCode()),
            );
        }

        $this->assertRateLimitBlocks(
            fn (): mixed => $handler->handle($this->makeLoginRequest('sec008probe', 'bad-trigger')),
            'Per-user rate-limit',
            10,
        );
    }

    /**
     * Pre-fix: alle Versuche → 302 (kein Site-Wide-Limit).
     * Post-fix: nach 20 Versuchen auf nicht existierende User → 429 / Throw,
     * unabhängig von der Existenz des Accounts (Schutz vor User-Enumeration).
     */
    public function test_site_wide_rate_limit_fires_for_unknown_users(): void
    {
        $this->prepareLoginAttempt();

        $handler = Registry::container()->get(LoginAction::class);

        for ($i = 0; $i < 20; $i++) {
            $response = $handler->handle($this->makeLoginRequest('no-such-sec008', 'bad-' . $i));
            self::assertSame(
                StatusCodeInterface::STATUS_FOUND,
                $response->getStatusCode(),
                sprintf('Attempt %d: expected 302 (under site-wide threshold), got %d', $i + 1, $response->getStatusCode()),
            );
        }

        $this->assertRateLimitBlocks(
            fn (): mixed => $handler->handle($this->makeLoginRequest('no-such-sec008', 'bad-trigger')),
            'Site-wide rate-limit fuer unbekannte User',
            20,
        );
    }

    /**
     * FAILURE_PIN-Helper nach wf_test-iteration_guide.md §i.7. Asserted das
     * Soll-Verhalten (Throttle nach Threshold) und liefert eine sprechende
     * Failure-Message, solange Upstream den Rate-Limit nicht implementiert.
     */
    private function assertRateLimitBlocks(callable $invoke, string $variant, int $threshold): void
    {
        try {
            $response = $invoke();
        } catch (HttpTooManyRequestsException) {
            self::assertTrue(true, 'Rate limit fired via HttpTooManyRequestsException');
            return;
        } catch (Throwable $e) {
            self::fail(sprintf(
                'Unexpected exception %s instead of rate-limit signal: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        self::assertSame(
            429,
            $response->getStatusCode(),
            sprintf(
                'LoginAction::handle (%s): nach %d fehlgeschlagenen Login-Versuchen muss eine Rate-Limit-Drosselung greifen '
                . '(HTTP 429 oder HttpTooManyRequestsException). Aktuell HTTP %d — Upstream `main` enthaelt keine '
                . 'Login-Rate-Limit-Implementierung (Services\\RateLimitService fehlt). '
                . 'FAILURE_PIN nach wf_test-iteration_guide.md §i.7; siehe '
                . 'docs/security-audit/tasks/SEC-AUDIT-008_login_brute_force_no_rate_limit.md.',
                $variant,
                $threshold,
                $response->getStatusCode(),
            ),
        );
    }

    private function makeLoginRequest(string $username, string $password): ServerRequestInterface
    {
        return $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username' => $username,
                'password' => $password,
                'url'      => '',
            ],
        );
    }

    /**
     * Setzt einen Browser-Cookie (damit doLogin() nicht am Cookie-Guard scheitert),
     * leert die wt_user-Session (gesetzt von createAndLoginAdmin()) und stellt
     * sicher, dass IP-basierte Rate-Limit-Zähler aus früheren Tests gelöscht sind
     * (sonst kann der 21. Aufruf in einer Suite HTTP 429 statt 302 zurückgeben).
     */
    private function prepareLoginAttempt(): void
    {
        $_COOKIE['PHPSESSID'] = 'test-session-login-action';

        Session::forget('wt_user');

        // IP-basierter Rate-Limit-Zähler (Option B aus SEC-AUDIT-008 Fix).
        // 127.0.0.1 ist die Default-IP aus MysqlTestCase::createRequest().
        Site::$preferences = [];
        Site::setPreference('rate-limit-login-127.0.0.1', '');
    }

    /**
     * Liefert einen Test-User idempotent: bei Wiederholung des Test-Laufs (DB
     * bleibt zwischen Test-Aufrufen befüllt) wird der vorhandene User wieder
     * verwendet, statt einen Duplicate-Key-Fehler zu provozieren.
     */
    private function ensureUser(string $userName, string $realName, string $email, string $password): UserInterface
    {
        return $this->userService->findByUserName($userName)
            ?? $this->userService->create($userName, $realName, $email, $password);
    }
}
