<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\TimestampInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\BroadcastAction;
use Fisharebest\Webtrees\Http\RequestHandlers\BroadcastPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ControlPanel;
use Fisharebest\Webtrees\Http\RequestHandlers\EmailPreferencesPage;
use Fisharebest\Webtrees\Http\RequestHandlers\Masquerade;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\HousekeepingService;
use Fisharebest\Webtrees\Services\MessageService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\ServerCheckService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UpgradeService;
use Fisharebest\Webtrees\Services\UserService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: System & Upgrade (HTTP-Handler) — A11.
 *
 * Tests:
 * - Masquerade POST: unbekannte user_id → HttpNotFoundException
 * - Masquerade POST: Selbst-Masquerade → 204, Auth::id() unverändert
 * - Masquerade POST: andere User-ID → 204, Auth::id() ändert sich
 * - BroadcastPage GET → 200
 * - EmailPreferencesPage GET → 200
 * - BroadcastAction POST: erfolgreiche Auslieferung an alle Empfänger → 302
 * - BroadcastAction POST: gescheiterte Auslieferung → 302 (redirect immer)
 * - BroadcastAction POST: mehrere Empfänger → deliverMessage pro Empfänger
 * - BroadcastAction POST: keine Empfänger → deliverMessage wird nie aufgerufen
 * - ControlPanel GET: gestubbte Services → 200 OK (Admin-Dashboard)
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\Masquerade
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\BroadcastPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\BroadcastAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EmailPreferencesPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ControlPanel
 * @see docs/tds_conditions_ref.md A11
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

    /**
     * BroadcastAction POST: erfolgreiche Auslieferung an alle Empfänger → 302.
     *
     * @group ported-l2-doubles
     */
    public function test_broadcast_action_delivers_to_all_recipients(): void
    {
        // Arrange
        $recipient = $this->userService->create(
            'bcrecip1-' . substr(uniqid(), -6),
            'BC Recip',
            'bcrecip1-' . substr(uniqid(), -6) . '@test.local',
            'dummy-password',
        );

        $message_service = $this->createMock(MessageService::class);
        $message_service
            ->expects(self::once())
            ->method('recipientTypes')
            ->willReturn(['all' => 'Send a message to all users']);
        $message_service
            ->expects(self::once())
            ->method('recipientUsers')
            ->with('all')
            ->willReturn(new Collection([$recipient]));
        $message_service
            ->expects(self::once())
            ->method('deliverMessage')
            ->willReturn(true);

        $handler = new BroadcastAction($message_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'body'    => 'Broadcast message',
                'subject' => 'Important notice',
            ],
            attributes: [
                'user' => $this->admin,
                'to'   => 'all',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $this->userService->delete($recipient);
    }

    /**
     * BroadcastAction POST: gescheiterte Auslieferung → 302 (Redirect immer).
     *
     * @group ported-l2-doubles
     */
    public function test_broadcast_action_returns_302_when_delivery_fails(): void
    {
        // Arrange
        $recipient = $this->userService->create(
            'bcrecip2-' . substr(uniqid(), -6),
            'BC Recip 2',
            'bcrecip2-' . substr(uniqid(), -6) . '@test.local',
            'dummy-password',
        );

        $message_service = $this->createMock(MessageService::class);
        $message_service
            ->expects(self::once())
            ->method('recipientTypes')
            ->willReturn(['all' => 'all']);
        $message_service
            ->expects(self::once())
            ->method('recipientUsers')
            ->with('all')
            ->willReturn(new Collection([$recipient]));
        $message_service
            ->expects(self::once())
            ->method('deliverMessage')
            ->willReturn(false);

        $handler = new BroadcastAction($message_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'body'    => 'Broadcast message',
                'subject' => 'Important notice',
            ],
            attributes: [
                'user' => $this->admin,
                'to'   => 'all',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — redirect to control panel even on failure
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $this->userService->delete($recipient);
    }

    /**
     * BroadcastAction POST: mehrere Empfänger → deliverMessage pro Empfänger.
     *
     * @group ported-l2-doubles
     */
    public function test_broadcast_action_delivers_to_multiple_recipients(): void
    {
        // Arrange
        $recipient1 = $this->userService->create(
            'bcrecip3a-' . substr(uniqid(), -6),
            'BC Recip 3a',
            'bcrecip3a-' . substr(uniqid(), -6) . '@test.local',
            'dummy-password',
        );
        $recipient2 = $this->userService->create(
            'bcrecip3b-' . substr(uniqid(), -6),
            'BC Recip 3b',
            'bcrecip3b-' . substr(uniqid(), -6) . '@test.local',
            'dummy-password',
        );

        $message_service = $this->createMock(MessageService::class);
        $message_service
            ->expects(self::once())
            ->method('recipientTypes')
            ->willReturn(['all' => 'all']);
        $message_service
            ->expects(self::once())
            ->method('recipientUsers')
            ->with('all')
            ->willReturn(new Collection([$recipient1, $recipient2]));
        $message_service
            ->expects(self::exactly(2))
            ->method('deliverMessage')
            ->willReturn(true);

        $handler = new BroadcastAction($message_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'body'    => 'Broadcast to all',
                'subject' => 'Notice',
            ],
            attributes: [
                'user' => $this->admin,
                'to'   => 'all',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $this->userService->delete($recipient1);
        $this->userService->delete($recipient2);
    }

    /**
     * BroadcastAction POST: keine Empfänger → deliverMessage wird nie aufgerufen.
     *
     * @group ported-l2-doubles
     */
    public function test_broadcast_action_with_no_recipients_does_not_deliver(): void
    {
        // Arrange
        $message_service = $this->createMock(MessageService::class);
        $message_service
            ->expects(self::once())
            ->method('recipientTypes')
            ->willReturn(['all' => 'all']);
        $message_service
            ->expects(self::once())
            ->method('recipientUsers')
            ->with('all')
            ->willReturn(new Collection([]));
        $message_service
            ->expects(self::never())
            ->method('deliverMessage');

        $handler = new BroadcastAction($message_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'body'    => 'Broadcast to none',
                'subject' => 'Notice',
            ],
            attributes: [
                'user' => $this->admin,
                'to'   => 'all',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * ControlPanel GET: gestubbte Services liefern 200 OK (Admin-Dashboard).
     *
     * @group ported-l2-doubles
     */
    public function test_control_panel_returns_ok_response(): void
    {
        // Arrange — alle Service-Abhängigkeiten als Stubs, da ControlPanel sie
        // nur read-only zur Dashboard-Aggregation nutzt.
        $admin_service = self::createStub(AdminService::class);
        $admin_service->method('multipleTreeThreshold')->willReturn(100);
        $admin_service->method('gedcomFiles')->willReturn(new Collection());

        $housekeeping_service = self::createStub(HousekeepingService::class);
        $housekeeping_service->method('deleteOldWebtreesFiles')->willReturn([]);

        $message_service = self::createStub(MessageService::class);
        $message_service->method('recipientTypes')->willReturn([]);

        $module_service = self::createStub(ModuleService::class);
        $module_service->method('findByInterface')->willReturn(new Collection());
        $module_service->method('all')->willReturn(new Collection());
        $module_service->method('deletedModules')->willReturn(new Collection());
        $module_service->method('otherModules')->willReturn(new Collection());

        $server_check_service = self::createStub(ServerCheckService::class);
        $server_check_service->method('serverErrors')->willReturn(new Collection());
        $server_check_service->method('serverWarnings')->willReturn(new Collection());

        $tree_service = self::createStub(TreeService::class);
        $tree_service->method('all')->willReturn(new Collection());

        $timestamp = self::createStub(TimestampInterface::class);

        $upgrade_service = self::createStub(UpgradeService::class);
        $upgrade_service->method('latestVersion')->willReturn('');
        $upgrade_service->method('latestVersionError')->willReturn('');
        $upgrade_service->method('latestVersionTimestamp')->willReturn($timestamp);

        $user_service = self::createStub(UserService::class);
        $user_service->method('all')->willReturn(new Collection());
        $user_service->method('administrators')->willReturn(new Collection());
        $user_service->method('managers')->willReturn(new Collection());
        $user_service->method('moderators')->willReturn(new Collection());
        $user_service->method('unapproved')->willReturn(new Collection());
        $user_service->method('unverified')->willReturn(new Collection());

        $handler = new ControlPanel(
            $admin_service,
            $housekeeping_service,
            $message_service,
            $module_service,
            $server_check_service,
            $tree_service,
            $upgrade_service,
            $user_service,
        );

        $request = $this->createRequest(attributes: ['user' => $this->admin]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
