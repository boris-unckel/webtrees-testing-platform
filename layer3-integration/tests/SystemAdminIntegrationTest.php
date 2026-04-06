<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\BroadcastPage;
use Fisharebest\Webtrees\Http\RequestHandlers\EmailPreferencesPage;
use Fisharebest\Webtrees\Http\RequestHandlers\Masquerade;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\MessageService;

/**
 * Komponentenintegrationstest: System & Upgrade (HTTP-Handler) — A11.
 *
 * Tests:
 * - Masquerade POST: unbekannte user_id → HttpNotFoundException
 * - Masquerade POST: Selbst-Masquerade → 204, Auth::id() unverändert
 * - Masquerade POST: andere User-ID → 204, Auth::id() ändert sich
 * - BroadcastPage GET → 200
 * - EmailPreferencesPage GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\Masquerade
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\BroadcastPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EmailPreferencesPage
 * @see docs/testquality_improve_A11.md
 */
class SystemAdminIntegrationTest extends MysqlTestCase
{
    private UserInterface $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAndLoginAdmin();
    }

    /**
     * EP1: Masquerade POST mit unbekannter user_id → HttpNotFoundException.
     */
    public function test_masquerade_throws_for_unknown_user_id(): void
    {
        $handler = new Masquerade($this->userService);

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            attributes: [
                'user_id' => 99999,
                'user'    => $this->admin,
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP2: Masquerade POST: Selbst-Masquerade → 204, Auth::id() bleibt gleich.
     */
    public function test_masquerade_self_returns_204(): void
    {
        $handler = new Masquerade($this->userService);

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            attributes: [
                'user_id' => $this->admin->id(),
                'user'    => $this->admin,
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
        self::assertSame($this->admin->id(), Auth::id());
    }

    /**
     * EP3: Masquerade POST: andere User-ID → 204, Auth::id() wechselt.
     */
    public function test_masquerade_other_user_returns_204_and_changes_auth(): void
    {
        $otherUser = $this->userService->create(
            'masq-other-' . substr(uniqid(), -6),
            'Masquerade Test User',
            'masq-other@test.local',
            'dummy-password',
        );

        $handler = new Masquerade($this->userService);

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            attributes: [
                'user_id' => $otherUser->id(),
                'user'    => $this->admin,
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
        self::assertSame($otherUser->id(), Auth::id());

        $this->userService->delete($otherUser);
    }

    /**
     * EP4: BroadcastPage GET → 200.
     */
    public function test_broadcast_page_returns_200(): void
    {
        $handler = new BroadcastPage(
            Registry::container()->get(MessageService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'user' => $this->admin,
                'to'   => 'all',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP5: EmailPreferencesPage GET → 200.
     */
    public function test_email_preferences_page_returns_200(): void
    {
        $handler = new EmailPreferencesPage(
            Registry::container()->get(EmailService::class),
        );

        $request = $this->createRequest();

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
