<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\ContactAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ContactPage;
use Fisharebest\Webtrees\Services\CaptchaService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\MessageService;
use Fisharebest\Webtrees\Services\RateLimitService;
use Fig\Http\Message\RequestMethodInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Komponentenintegrationstest: ContactForm — ContactPage + ContactAction (K01).
 *
 * Prüft Kontaktformular: Validierung (User, Captcha, E-Mail, ext. Links),
 * Nachrichtenzustellung und Fehlerbehandlung.
 *
 * Mock: MessageService, CaptchaService, EmailService.
 * Real: UserService (DB-Lookup), Tree.
 *
 * @see docs/tds_conditions_ref.md K01
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ContactPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ContactAction
 */
class ContactFormIntegrationTest extends MysqlTestCase
{
    private UserInterface $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('contact', 'Contact-Test', '/fixtures/demo.ged');

        // Target-User erstellen (Empfänger der Kontaktnachricht)
        $target = $this->userService->findByUserName('contact-target');
        if ($target === null) {
            $testPassword = getenv('WEBTREES_TEST_USER_PASSWORD')
                ?: throw new \RuntimeException('WEBTREES_TEST_USER_PASSWORD nicht gesetzt');
            $target = $this->userService->create(
                'contact-target', 'Contact Target', 'contact@test.local', $testPassword
            );
        }
        $target->setPreference(UserInterface::PREF_CONTACT_METHOD, 'messaging');
        $this->targetUser = $target;
    }

    // ── ContactPage (GET) ───────────────────────────────────────────────

    /**
     * EP1/B1: Gültiger Kontakt-User → Seite wird gerendert (200).
     */
    public function test_contact_page_renders_for_valid_contact(): void
    {
        $captchaService = $this->createStub(CaptchaService::class);
        $captchaService->method('createCaptcha')->willReturn('');

        $messageService = $this->createStub(MessageService::class);
        $messageService->method('validContacts')->willReturn([$this->targetUser]);

        $handler = new ContactPage($captchaService, $messageService, $this->userService);

        $request = $this->createRequest(
            query: ['to' => 'contact-target'],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * EP2/B2: Nicht existierender User → HttpAccessDeniedException.
     */
    public function test_contact_page_throws_for_unknown_user(): void
    {
        $captchaService = $this->createStub(CaptchaService::class);
        $messageService = $this->createStub(MessageService::class);

        $handler = new ContactPage($captchaService, $messageService, $this->userService);

        $request = $this->createRequest(
            query: ['to' => 'nonexistent_xyz'],
            attributes: ['tree' => $this->tree],
        );

        $this->expectException(HttpAccessDeniedException::class);
        $handler->handle($request);
    }

    /**
     * EP3/B3: User existiert, aber nicht in validContacts → HttpAccessDeniedException.
     */
    public function test_contact_page_throws_for_invalid_contact(): void
    {
        $captchaService = $this->createStub(CaptchaService::class);
        $messageService = $this->createStub(MessageService::class);
        $messageService->method('validContacts')->willReturn([]);

        $handler = new ContactPage($captchaService, $messageService, $this->userService);

        $request = $this->createRequest(
            query: ['to' => 'contact-target'],
            attributes: ['tree' => $this->tree],
        );

        $this->expectException(HttpAccessDeniedException::class);
        $handler->handle($request);
    }

    // ── ContactAction (POST) ────────────────────────────────────────────

    /**
     * Hilfsmethode: Erstellt POST-Request für ContactAction.
     *
     * @param array<string, string> $overrides Felder zum Überschreiben
     */
    private function createContactPostRequest(array $overrides = []): ServerRequestInterface
    {
        $defaults = [
            'body'       => 'Testnachricht ohne externe Links',
            'from_email' => 'sender@example.com',
            'from_name'  => 'Test Sender',
            'subject'    => 'Testbetreff ohne Links',
            'to'         => 'contact-target',
            'url'        => 'https://webtrees.test/index.php',
        ];

        return $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: array_merge($defaults, $overrides),
            attributes: ['tree' => $this->tree],
        );
    }

    /**
     * Hilfsmethode: Erstellt ContactAction mit Standard-Stubs für Erfolgsfall.
     */
    private function createSuccessContactAction(bool $deliverResult = true): ContactAction
    {
        $captchaService = $this->createStub(CaptchaService::class);
        $captchaService->method('isRobot')->willReturn(false);

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('isValidEmail')->willReturn(true);

        $messageService = $this->createStub(MessageService::class);
        $messageService->method('validContacts')->willReturn([$this->targetUser]);
        $messageService->method('deliverMessage')->willReturn($deliverResult);

        $rateLimitService = $this->createStub(RateLimitService::class);

        return new ContactAction(
            $captchaService, $emailService, $messageService,
            $rateLimitService, $this->userService,
        );
    }

    /**
     * EP4/B4: Alle Felder gültig, Zustellung erfolgreich → Redirect zur URL.
     */
    public function test_contact_action_success(): void
    {
        $handler  = $this->createSuccessContactAction(deliverResult: true);
        $response = $handler->handle($this->createContactPostRequest());

        $this->assertSame(302, $response->getStatusCode());
        // Erfolgs-Redirect geht zur angegebenen URL (nicht zurück zum Formular)
        $this->assertStringContainsString('webtrees.test/index.php', $response->getHeaderLine('Location'));
    }

    /**
     * EP5/B5: Leere Pflichtfelder → Redirect zurück (Fehlermeldung).
     */
    public function test_contact_action_redirects_on_empty_fields(): void
    {
        $handler  = $this->createSuccessContactAction();
        $response = $handler->handle($this->createContactPostRequest(['body' => '']));

        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * EP6/B6: Captcha erkennt Robot → Redirect zurück.
     */
    public function test_contact_action_redirects_on_robot(): void
    {
        $captchaService = $this->createStub(CaptchaService::class);
        $captchaService->method('isRobot')->willReturn(true);

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('isValidEmail')->willReturn(true);

        $messageService = $this->createStub(MessageService::class);
        $messageService->method('validContacts')->willReturn([$this->targetUser]);

        $rateLimitService = $this->createStub(RateLimitService::class);

        $handler = new ContactAction(
            $captchaService, $emailService, $messageService,
            $rateLimitService, $this->userService,
        );
        $response = $handler->handle($this->createContactPostRequest());

        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * EP7/B7: Ungültige E-Mail-Adresse → Redirect zurück.
     */
    public function test_contact_action_redirects_on_invalid_email(): void
    {
        $captchaService = $this->createStub(CaptchaService::class);
        $captchaService->method('isRobot')->willReturn(false);

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('isValidEmail')->willReturn(false);

        $messageService = $this->createStub(MessageService::class);
        $messageService->method('validContacts')->willReturn([$this->targetUser]);

        $rateLimitService = $this->createStub(RateLimitService::class);

        $handler = new ContactAction(
            $captchaService, $emailService, $messageService,
            $rateLimitService, $this->userService,
        );
        $response = $handler->handle($this->createContactPostRequest());

        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * EP8/B8: Externe Links in Body → Redirect zurück.
     */
    public function test_contact_action_redirects_on_external_links(): void
    {
        $handler  = $this->createSuccessContactAction();
        $response = $handler->handle($this->createContactPostRequest([
            'body' => 'Schau mal hier: http://external-spam.com/link',
        ]));

        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * EP9/B9: Zustellung fehlgeschlagen → Redirect zurück.
     */
    public function test_contact_action_delivery_failure(): void
    {
        $handler  = $this->createSuccessContactAction(deliverResult: false);
        $response = $handler->handle($this->createContactPostRequest());

        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * EP10/B10: Unbekannter Empfänger → HttpNotFoundException.
     */
    public function test_contact_action_throws_for_unknown_user(): void
    {
        $messageService = $this->createStub(MessageService::class);
        $messageService->method('validContacts')->willReturn([]);

        $rateLimitService = $this->createStub(RateLimitService::class);

        $handler = new ContactAction(
            $this->createStub(CaptchaService::class),
            $this->createStub(EmailService::class),
            $messageService, $rateLimitService, $this->userService,
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($this->createContactPostRequest(['to' => 'nonexistent_xyz']));
    }

    /**
     * EP11/B11: User nicht in validContacts → HttpAccessDeniedException.
     */
    public function test_contact_action_throws_for_invalid_contact(): void
    {
        $messageService = $this->createStub(MessageService::class);
        $messageService->method('validContacts')->willReturn([]);

        $rateLimitService = $this->createStub(RateLimitService::class);

        $handler = new ContactAction(
            $this->createStub(CaptchaService::class),
            $this->createStub(EmailService::class),
            $messageService, $rateLimitService, $this->userService,
        );

        $this->expectException(HttpAccessDeniedException::class);
        $handler->handle($this->createContactPostRequest());
    }
}
