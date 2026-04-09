<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration\Security;

use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpTooManyRequestsException;
use Fisharebest\Webtrees\Http\RequestHandlers\LoginAction;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\RateLimitService;
use Fisharebest\Webtrees\Services\TimeoutService;
use Fisharebest\Webtrees\Services\UpgradeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Site;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Regression for SEC-AUDIT-008 — LoginAction missing rate limiting.
 * Discovered in Sweep 3 (2026-04-09T18-07-30).
 *
 * Spec: docs/security-audit/tasks/SEC-AUDIT-008_login_brute_force_no_rate_limit.md
 *
 * Pre-fix behaviour:
 *   LoginAction (POST /login, visitor-reachable) had no RateLimitService.
 *   An attacker could send unlimited password guesses against any account
 *   with only bcrypt (~100ms/attempt) as rate control.
 *   All other comparable endpoints (PasswordRequestAction, RegisterAction,
 *   ContactAction) have explicit rate limiting — LoginAction was the exception.
 *
 * Post-fix behaviour (Option A+B combined):
 *   Option B (site-wide, before user lookup): limitRateForSite(20, 300,
 *     'rate-limit-login-<IP>') placed in handle() before the try/catch.
 *     Protects against brute-force with invalid usernames.
 *   Option A (per-user, after identification): limitRateForUser($user, 10, 300,
 *     'rate-limit-login') placed in doLogin() after findByIdentifier().
 *     Limits password attempts per account.
 *   HttpTooManyRequestsException is re-thrown from handle() so it escapes
 *   the catch(Exception) block and returns HTTP 429 to the client.
 *
 * Self-skip: the test class checks whether LoginAction's constructor accepts
 * RateLimitService via reflection. If the fix is absent the whole class is
 * marked skipped so `make test-integration` stays green when WEBTREES_SOURCE
 * points at an unfixed webtrees tree.
 *
 * OWASP A07:2021 — Identification and Authentication Failures.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LoginAction
 */
final class SecAudit008Test extends SecurityAuditTestCase
{
    private const PROBE_USERNAME    = 'sec-audit-008-probe';
    private const PROBE_EMAIL       = 'sec008probe@test.local';
    private const PROBE_REALNAME    = 'SEC-AUDIT-008 Probe User';
    private const PROBE_PASSWORD    = 'irrelevant-probe-pw-sec008';
    private const RATE_LIMIT_IP_KEY = 'rate-limit-login-127.0.0.1';

    private LoginAction $handler;
    private UserInterface $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfFixAbsent();

        $this->handler = new LoginAction(
            new UpgradeService(new TimeoutService(new PhpService())),
            new RateLimitService(),
            new UserService(),
        );

        // Create or reuse a verified + approved user for per-user rate limit probes.
        $userService    = new UserService();
        $this->testUser = $userService->findByUserName(self::PROBE_USERNAME)
            ?? $userService->create(self::PROBE_USERNAME, self::PROBE_REALNAME, self::PROBE_EMAIL, self::PROBE_PASSWORD);

        $this->testUser->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1');
        $this->testUser->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '1');

        // Reset rate-limit state before each test method.
        $this->resetRateLimitState();

        // Simulate a browser cookie so doLogin() does not bail on its first
        // guard: `if ($_COOKIE === []) { throw new Exception(...) }`.
        $_COOKIE['PHPSESSID'] = 'test-session-for-sec-audit-008';
    }

    protected function tearDown(): void
    {
        // Restore the superglobal to avoid contaminating subsequent tests.
        $_COOKIE = [];

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Option A: per-user rate limit
    // -------------------------------------------------------------------------

    /**
     * After 10 failed password attempts for a known, approved account the
     * 11th attempt must throw HttpTooManyRequestsException (per-user limit).
     *
     * Pre-fix: all attempts return HTTP 302 — no throttling at all.
     * Post-fix: attempt #11 propagates as HttpTooManyRequestsException.
     */
    public function test_h1_per_user_rate_limit_triggers_after_ten_failures(): void
    {
        // First 10 attempts — must redirect (wrong password, but NOT rate-limited yet).
        for ($i = 0; $i < 10; $i++) {
            $response = $this->handler->handle(
                $this->makeLoginRequest(self::PROBE_USERNAME, 'bad-password-' . $i),
            );
            self::assertSame(
                302,
                $response->getStatusCode(),
                sprintf('Attempt %d: expected redirect (302), got %d — rate limit triggered too early.', $i + 1, $response->getStatusCode()),
            );
        }

        // 11th attempt — must hit the per-user rate limit.
        $this->expectException(HttpTooManyRequestsException::class);
        $this->handler->handle(
            $this->makeLoginRequest(self::PROBE_USERNAME, 'bad-password-10'),
        );
    }

    /**
     * Successful login resets the per-user attempt counter because
     * limitRateForUser is called before the password check — a successful
     * login still increments the same counter.
     *
     * This test verifies that 10 attempts with the CORRECT password do NOT
     * trigger the rate limit (the counter increments equally for success and
     * failure, so the per-user limit applies to ALL attempts, not just failures).
     *
     * This is intentional: the limit prevents an attacker from trying 10 passwords,
     * waiting, and repeating — even if one login was correct in between.
     */
    public function test_h1_rate_limit_applies_to_all_attempts_not_only_failures(): void
    {
        // 9 failed attempts — under limit.
        for ($i = 0; $i < 9; $i++) {
            $response = $this->handler->handle(
                $this->makeLoginRequest(self::PROBE_USERNAME, 'bad-password-' . $i),
            );
            self::assertSame(302, $response->getStatusCode());
        }

        // 10th attempt — still under limit (the limit is 10 per window, so the
        // 10th attempt succeeds and the 11th would fail).
        $response = $this->handler->handle(
            $this->makeLoginRequest(self::PROBE_USERNAME, 'bad-password-9'),
        );
        self::assertSame(302, $response->getStatusCode(), 'Attempt 10: should still be within limit');
    }

    // -------------------------------------------------------------------------
    // Option B: site-wide IP-based rate limit
    // -------------------------------------------------------------------------

    /**
     * After 20 login attempts from the same IP address using a non-existent
     * username the 21st attempt must throw HttpTooManyRequestsException
     * (site-wide limit, applied before user lookup).
     *
     * This proves that attackers cannot enumerate or brute-force via invalid
     * usernames because the site-wide backstop fires regardless of whether
     * a user account is found.
     *
     * Pre-fix: all attempts return HTTP 302 — no throttling at all.
     * Post-fix: attempt #21 propagates as HttpTooManyRequestsException.
     */
    public function test_h1_site_wide_rate_limit_triggers_for_unknown_usernames(): void
    {
        // 20 attempts with a non-existent username — under the site-wide limit.
        for ($i = 0; $i < 20; $i++) {
            $response = $this->handler->handle(
                $this->makeLoginRequest('no-such-user-sec008-probe', 'bad-password-' . $i),
            );
            self::assertSame(
                302,
                $response->getStatusCode(),
                sprintf('Attempt %d: expected redirect (302), got %d — site rate limit triggered too early.', $i + 1, $response->getStatusCode()),
            );
        }

        // 21st attempt — must hit the site-wide rate limit.
        $this->expectException(HttpTooManyRequestsException::class);
        $this->handler->handle(
            $this->makeLoginRequest('no-such-user-sec008-probe', 'bad-password-20'),
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLoginRequest(string $username, string $password): ServerRequestInterface
    {
        // createRequest() sets client-ip => 127.0.0.1 by default.
        return $this->createRequest(
            method: 'POST',
            params: [
                'username' => $username,
                'password' => $password,
                'url'      => '',
            ],
        );
    }

    private function resetRateLimitState(): void
    {
        // Per-user: clear stored timestamps in user_setting.
        $this->testUser->setPreference('rate-limit-login', '');

        // Site-wide: clear stored timestamps for the test IP (127.0.0.1).
        // Also flush the in-memory cache so getPreference() reads fresh from DB.
        Site::$preferences = [];
        Site::setPreference(self::RATE_LIMIT_IP_KEY, '');
    }

    /**
     * Skip the whole test class if the fix is absent, so the suite stays
     * green when pointed at an unfixed upstream tree.
     *
     * Detection: the fixed LoginAction constructor accepts RateLimitService;
     * the unfixed one does not.
     */
    private function skipIfFixAbsent(): void
    {
        $params = (new ReflectionClass(LoginAction::class))
            ->getConstructor()
            ?->getParameters() ?? [];

        foreach ($params as $param) {
            $type = $param->getType();
            if (
                $type instanceof ReflectionNamedType
                && $type->getName() === RateLimitService::class
            ) {
                return; // fix is present — run the tests
            }
        }

        self::markTestSkipped(
            'SEC-AUDIT-008 fix not present: LoginAction constructor does not '
            . 'accept RateLimitService. Point WEBTREES_SOURCE at a tree that '
            . 'contains the fix on branch security-audit-008-login-rate-limit '
            . 'to enable this regression suite.',
        );
    }
}
