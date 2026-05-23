<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\RegisterAction;
use Fisharebest\Webtrees\Http\RequestHandlers\RegisterPage;
use Fisharebest\Webtrees\Http\RequestHandlers\VerifyEmail;
use Fisharebest\Webtrees\Services\CaptchaService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\RateLimitService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Site;

/**
 * Komponentenintegrationstest: Benutzerregistrierung (RegisterAction, RegisterPage).
 *
 * Deckt das Pre-Condition-Gate der RegisterAction ab: ist das Site-Preference
 * USE_REGISTRATION_MODULE deaktiviert, wirft handle() HttpNotFoundException
 * noch bevor irgendwelche Captcha-, Email- oder UserService-Aktionen erfolgen.
 *
 * Ergänzend für RegisterPage: Rendern des Registrierungsformulars bei aktivierter
 * Registrierung (STATUS_OK) und HttpNotFoundException bei deaktivierter
 * Registrierung — analog zum Pre-Condition-Gate.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RegisterAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RegisterPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\VerifyEmail
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/RegisterActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/RegisterPageTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/VerifyEmailTest.php
 */
class UserRegistrationIntegrationTest extends MysqlTestCase
{
    /**
     * Wenn die Registrierung site-weit deaktiviert ist
     * (USE_REGISTRATION_MODULE !== '1'), wirft handle() eine
     * HttpNotFoundException — unabhängig vom Inhalt des POST-Bodys.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/RegisterActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_throws_not_found_when_registration_disabled(): void
    {
        // Arrange
        Site::setPreference('USE_REGISTRATION_MODULE', '0');

        // Alle Service-Doubles als Stubs: handle() bricht in checkRegistrationAllowed()
        // ab, bevor irgendeine Service-Methode aufgerufen wird — keine Interaktionen
        // zu verifizieren.
        $captcha_service    = self::createStub(CaptchaService::class);
        $email_service      = self::createStub(EmailService::class);
        $rate_limit_service = self::createStub(RateLimitService::class);
        $user_service       = self::createStub(UserService::class);

        $handler = new RegisterAction(
            $captcha_service,
            $email_service,
            $rate_limit_service,
            $user_service,
        );
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username' => 'test',
                'realname' => 'Test',
                'email'    => 'test@example.com',
                'password' => 'secret',
                'comments' => 'Hello',
            ],
        );

        // Assert
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }

    /**
     * Wenn die Registrierung site-weit aktiviert ist
     * (USE_REGISTRATION_MODULE === '1'), rendert RegisterPage::handle() das
     * Formular mit STATUS_OK und liefert einen nicht-leeren Body.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/RegisterPageTest.php
     * @group ported-l2-doubles
     */
    public function test_register_page_renders_form_when_registration_enabled(): void
    {
        // Arrange
        Site::setPreference('USE_REGISTRATION_MODULE', '1');

        $captcha_service = self::createStub(CaptchaService::class);

        $handler = new RegisterPage($captcha_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertNotEmpty((string) $response->getBody());
    }

    /**
     * Wenn die Registrierung deaktiviert ist (USE_REGISTRATION_MODULE !== '1'),
     * wirft RegisterPage::handle() eine HttpNotFoundException — analog zum
     * Pre-Condition-Gate der RegisterAction.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/RegisterPageTest.php
     * @group ported-l2-doubles
     */
    public function test_register_page_throws_not_found_when_registration_disabled(): void
    {
        // Arrange
        Site::setPreference('USE_REGISTRATION_MODULE', '0');

        $captcha_service = self::createStub(CaptchaService::class);

        $handler = new RegisterPage($captcha_service);
        $request = $this->createRequest();

        // Assert
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }

    /**
     * Wenn zusätzlich SHOW_REGISTER_CAUTION aktiviert ist, rendert RegisterPage
     * weiterhin mit STATUS_OK (der Caution-Block wird in das Template
     * eingebunden, aber der Status-Code bleibt OK).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/RegisterPageTest.php
     * @group ported-l2-doubles
     */
    public function test_register_page_renders_with_caution_when_enabled(): void
    {
        // Arrange
        Site::setPreference('USE_REGISTRATION_MODULE', '1');
        Site::setPreference('SHOW_REGISTER_CAUTION', '1');

        $captcha_service = self::createStub(CaptchaService::class);

        $handler = new RegisterPage($captcha_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * VerifyEmail::handle() rendert die Failure-Seite (STATUS_OK), wenn der
     * angegebene Benutzername nicht existiert. Der EmailService wird in diesem
     * Pfad nicht aufgerufen — Domain-Stub reicht; UserService wird gemockt, um
     * den Lookup-Aufruf zu verifizieren.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/VerifyEmailTest.php
     * @group ported-l2-doubles
     */
    public function test_verify_email_renders_failure_page_for_unknown_user(): void
    {
        // Arrange
        $email_service = self::createStub(EmailService::class);
        $user_service  = $this->createMock(UserService::class);
        $user_service->expects(self::once())
            ->method('findByUserName')
            ->with('unknown')
            ->willReturn(null);

        $handler = new VerifyEmail($email_service, $user_service);
        $request = $this->createRequest(
            attributes: [
                'username' => 'unknown',
                'token'    => 'some-token',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Unbekannter Benutzer → Failure-Seite mit Status 200.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * VerifyEmail::handle() rendert die Failure-Seite (STATUS_OK), wenn der
     * Benutzer existiert, aber das übergebene Token nicht zum gespeicherten
     * Verification-Token passt. Der EmailService wird in diesem Pfad nicht
     * aufgerufen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/VerifyEmailTest.php
     * @group ported-l2-doubles
     */
    public function test_verify_email_renders_failure_page_for_invalid_token(): void
    {
        // Arrange
        $user = $this->ensureVerifyUser('verifyuser', 'Verify User', 'verify@example.com', 'secret');
        $user->setPreference(UserInterface::PREF_VERIFICATION_TOKEN, 'correct-token');

        $email_service      = self::createStub(EmailService::class);
        $mock_user_service  = $this->createMock(UserService::class);
        $mock_user_service->expects(self::once())
            ->method('findByUserName')
            ->with('verifyuser')
            ->willReturn($user);

        $handler = new VerifyEmail($email_service, $mock_user_service);
        $request = $this->createRequest(
            attributes: [
                'username' => 'verifyuser',
                'token'    => 'wrong-token',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Falsches Token → Failure-Seite mit Status 200.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Idempotente Benutzer-Bereitstellung für VerifyEmail-Tests: bei
     * Wiederholungslauf wird der vorhandene User wiederverwendet, damit kein
     * Duplicate-Key entsteht.
     */
    private function ensureVerifyUser(
        string $userName,
        string $realName,
        string $email,
        string $password,
    ): UserInterface {
        return $this->userService->findByUserName($userName)
            ?? $this->userService->create($userName, $realName, $email, $password);
    }
}
