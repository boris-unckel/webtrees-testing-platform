<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\RequestHandlers\MessageAction;
use Fisharebest\Webtrees\Http\RequestHandlers\MessagePage;
use Fisharebest\Webtrees\Http\RequestHandlers\MessageSelect;
use Fisharebest\Webtrees\Services\MessageService;
use Fig\Http\Message\RequestMethodInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Komponentenintegrationstest: UserMessage — MessagePage + MessageAction + MessageSelect (K02).
 *
 * Prüft Benutzernachrichten: Seitenaufbau, Validierung, Zustellung,
 * Fehlerbehandlung und POST→GET-Redirect.
 *
 * Mock: MessageService.
 * Real: UserService (DB-Lookup), authentifizierter User.
 *
 * @see docs/tds_conditions_ref.md K02
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MessagePage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MessageAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MessageSelect
 */
class UserMessageIntegrationTest extends MysqlTestCase
{
    private UserInterface $sender;
    private UserInterface $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('message', 'Message-Test', '/fixtures/demo.ged');

        // Sender = eingeloggter Admin-User (von createTreeWithGedcom erstellt)
        $this->sender = $this->userService->findByUserName('test-admin');

        // Target-User erstellen
        $target = $this->userService->findByUserName('message-target');
        if ($target === null) {
            $testPassword = getenv('WEBTREES_TEST_USER_PASSWORD')
                ?: throw new \RuntimeException('WEBTREES_TEST_USER_PASSWORD nicht gesetzt');
            $target = $this->userService->create(
                'message-target', 'Message Target', 'message@test.local', $testPassword
            );
        }
        $target->setPreference(UserInterface::PREF_CONTACT_METHOD, 'messaging');
        $this->targetUser = $target;
    }

    // ── MessagePage (GET) ───────────────────────────────────────────────

    /**
     * EP1/B1: Gültiger Empfänger mit Contact-Method → Seite wird gerendert (200).
     */
    public function test_message_page_renders_for_valid_recipient(): void
    {
        $handler = new MessagePage($this->userService);

        $request = $this->createRequest(
            query: ['to' => 'message-target'],
            attributes: ['tree' => $this->tree, 'user' => $this->sender],
        );

        $response = $handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * EP2/B2: Nicht existierender Empfänger → HttpAccessDeniedException.
     */
    public function test_message_page_throws_for_unknown_user(): void
    {
        $handler = new MessagePage($this->userService);

        $request = $this->createRequest(
            query: ['to' => 'nonexistent_xyz'],
            attributes: ['tree' => $this->tree, 'user' => $this->sender],
        );

        $this->expectException(HttpAccessDeniedException::class);
        $handler->handle($request);
    }

    /**
     * EP3/B3: Empfänger mit CONTACT_METHOD_NONE → HttpAccessDeniedException.
     */
    public function test_message_page_throws_for_contact_method_none(): void
    {
        $this->targetUser->setPreference(
            UserInterface::PREF_CONTACT_METHOD,
            MessageService::CONTACT_METHOD_NONE,
        );

        $handler = new MessagePage($this->userService);

        $request = $this->createRequest(
            query: ['to' => 'message-target'],
            attributes: ['tree' => $this->tree, 'user' => $this->sender],
        );

        $this->expectException(HttpAccessDeniedException::class);
        $handler->handle($request);
    }

    // ── MessageAction (POST) ────────────────────────────────────────────

    /**
     * Hilfsmethode: Erstellt POST-Request für MessageAction.
     *
     * @param array<string, string> $overrides Felder zum Überschreiben
     */
    private function createMessagePostRequest(array $overrides = []): ServerRequestInterface
    {
        $defaults = [
            'body'    => 'Testnachricht',
            'subject' => 'Testbetreff',
            'to'      => 'message-target',
            'url'     => 'https://webtrees.test/index.php',
        ];

        return $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: array_merge($defaults, $overrides),
            attributes: ['tree' => $this->tree, 'user' => $this->sender],
        );
    }

    /**
     * EP4/B4: Gültige Nachricht, Zustellung erfolgreich → Redirect zur URL.
     */
    public function test_message_action_success(): void
    {
        $messageService = $this->createStub(MessageService::class);
        $messageService->method('deliverMessage')->willReturn(true);

        $handler  = new MessageAction($messageService, $this->userService);
        $response = $handler->handle($this->createMessagePostRequest());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('webtrees.test', $response->getHeaderLine('Location'));
    }

    /**
     * EP5/B5: Leerer Body → Redirect zu MessagePage (kein deliverMessage-Aufruf).
     */
    public function test_message_action_redirects_on_empty_body(): void
    {
        $messageService = $this->createMock(MessageService::class);
        $messageService->expects($this->never())->method('deliverMessage');

        $handler  = new MessageAction($messageService, $this->userService);
        $response = $handler->handle($this->createMessagePostRequest(['body' => '']));

        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * EP6/B6: Leerer Betreff → Redirect zu MessagePage.
     */
    public function test_message_action_redirects_on_empty_subject(): void
    {
        $messageService = $this->createMock(MessageService::class);
        $messageService->expects($this->never())->method('deliverMessage');

        $handler  = new MessageAction($messageService, $this->userService);
        $response = $handler->handle($this->createMessagePostRequest(['subject' => '']));

        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * EP7/B7: Zustellung fehlgeschlagen → Redirect zu MessagePage.
     */
    public function test_message_action_delivery_failure(): void
    {
        $messageService = $this->createStub(MessageService::class);
        $messageService->method('deliverMessage')->willReturn(false);

        $handler  = new MessageAction($messageService, $this->userService);
        $response = $handler->handle($this->createMessagePostRequest());

        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * EP8/B8: Empfänger mit CONTACT_METHOD_NONE → HttpAccessDeniedException.
     */
    public function test_message_action_throws_for_contact_method_none(): void
    {
        $this->targetUser->setPreference(
            UserInterface::PREF_CONTACT_METHOD,
            MessageService::CONTACT_METHOD_NONE,
        );

        $messageService = $this->createStub(MessageService::class);
        $handler = new MessageAction($messageService, $this->userService);

        $this->expectException(HttpAccessDeniedException::class);
        $handler->handle($this->createMessagePostRequest());
    }

    /**
     * EP10/B10: Empfänger existiert nicht → HttpAccessDeniedException.
     *
     * @group ported-l2-doubles
     */
    public function test_message_action_throws_for_nonexistent_recipient(): void
    {
        $messageService = $this->createStub(MessageService::class);
        $handler        = new MessageAction($messageService, $this->userService);

        $this->expectException(HttpAccessDeniedException::class);
        $handler->handle($this->createMessagePostRequest(['to' => 'nonexistent_recipient_xyz']));
    }

    // ── MessageSelect (POST→GET) ───────────────────────────────────────

    /**
     * EP9/B9: POST-Daten werden als GET-Parameter an MessagePage weitergeleitet.
     */
    public function test_message_select_redirects_to_message_page(): void
    {
        $handler = new MessageSelect();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'body'    => 'Testnachricht',
                'subject' => 'Testbetreff',
                'to'      => 'message-target',
                'url'     => 'https://webtrees.test',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertStringContainsString('to=message-target', $location);
    }

    /**
     * MessageSelect leitet auch ohne POST-Felder per Default-Werten auf MessagePage weiter.
     *
     * @group ported-l2-doubles
     */
    public function test_message_select_redirects_with_default_values(): void
    {
        $handler = new MessageSelect();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        $this->assertSame(302, $response->getStatusCode());
    }
}
