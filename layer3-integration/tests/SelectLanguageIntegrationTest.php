<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\GuestUser;
use Fisharebest\Webtrees\Http\RequestHandlers\SelectLanguage;
use Fisharebest\Webtrees\Session;

/**
 * Komponentenintegrationstest: SelectLanguage HTTP-Handler.
 *
 * Prüft, dass der SelectLanguage-Handler den Sprachcode in der Session
 * persistiert und an der User-Preference des Anfragenden setzt, sowohl
 * für Gäste als auch für registrierte Benutzer. Antwortet mit 204 (No
 * Content).
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SelectLanguage
 * @see docs/tds_conditions_ref.md S51
 */
class SelectLanguageIntegrationTest extends MysqlTestCase
{
    /**
     * Gast-Anfrage: Handler setzt das `language`-Attribut in der Session
     * und ruft `setPreference(PREF_LANGUAGE, $code)` am Gast-User auf.
     * Antwort ist 204 (No Content).
     *
     * @group ported-l2-doubles
     */
    public function test_handle_sets_language_for_guest(): void
    {
        // Arrange
        $user = $this->createMock(GuestUser::class);
        $user->expects(self::once())
            ->method('setPreference')
            ->with(UserInterface::PREF_LANGUAGE, 'fr');

        $handler = new SelectLanguage();
        $request = $this->createRequest()
            ->withAttribute('user', $user)
            ->withAttribute('language', 'fr');

        // Act
        $response = $handler->handle($request);

        // Assert: 204 No Content, Session enthält den Sprachcode.
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
        self::assertSame('fr', Session::get('language'));
    }

    /**
     * Registrierter Benutzer: Handler persistiert den Sprachcode in der
     * Session und ruft `setPreference(PREF_LANGUAGE, $code)` am echten
     * User auf. Antwort ist 204 (No Content) und die Preference ist nach
     * dem Handler-Aufruf in der DB gesetzt.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_sets_language_for_user(): void
    {
        // Arrange: echten User in der MySQL-DB anlegen.
        $user = $this->ensureUser('langpicker', 'Lang Picker', 'langpicker@example.com', 'secret');
        // Preference vor dem Test definiert auf einen abweichenden Wert setzen,
        // damit die Änderung durch den Handler eindeutig nachweisbar ist.
        $user->setPreference(UserInterface::PREF_LANGUAGE, 'en-US');

        $handler = new SelectLanguage();
        $request = $this->createRequest()
            ->withAttribute('user', $user)
            ->withAttribute('language', 'de');

        // Act
        $response = $handler->handle($request);

        // Assert: 204 No Content, Session und User-Preference auf 'de' gesetzt.
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
        self::assertSame('de', Session::get('language'));
        self::assertSame('de', $user->getPreference(UserInterface::PREF_LANGUAGE));
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
