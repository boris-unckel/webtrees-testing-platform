<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SiteRegistrationAction;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Site;

/**
 * Komponentenintegrationstest: Site-weite Registrierungs-Konfiguration.
 *
 * Deckt den Admin-Handler ab, der die Registrierungs-bezogenen Site-Präferenzen
 * (Welcome-Modus, Welcome-Text, Modul-Aktivierung, Caution-Hinweis) persistiert.
 *
 * Abgrenzung:
 * - User-seitiger Registrierungs-Flow (Formular, Verifikation) liegt in
 *   UserRegistrationIntegrationTest.
 * - Allgemeine Site-Präferenzen (Index-Verzeichnis, Sprache, Theme) liegen
 *   in SitePreferencesIntegrationTest.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SiteRegistrationAction
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SiteRegistrationActionTest.php
 */
class SiteRegistrationIntegrationTest extends MysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
    }

    /**
     * Die Handler-Klasse existiert und ist instanziierbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SiteRegistrationActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handler_class_exists(): void
    {
        // Arrange & Act
        $handler = new SiteRegistrationAction();

        // Assert
        self::assertInstanceOf(SiteRegistrationAction::class, $handler);
    }

    /**
     * POST mit gefüllten Werten → 302 und Persistenz aller Registrierungs-Präferenzen.
     *
     * Anmerkung: Der Handler speichert den Welcome-Text unter dem sprachabhängigen
     * Schlüssel WELCOME_TEXT_AUTH_MODE_<langTag> (siehe SiteRegistrationAction).
     * Die Boolean-Felder werden als '1'/'' persistiert (Validator::boolean → (string)).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SiteRegistrationActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_saves_registration_preferences_and_redirects(): void
    {
        // Arrange
        $handler = new SiteRegistrationAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'WELCOME_TEXT_AUTH_MODE'   => '0',
                'WELCOME_TEXT_AUTH_MODE_4' => 'Custom welcome text',
                'USE_REGISTRATION_MODULE'  => '1',
                'SHOW_REGISTER_CAUTION'    => '1',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertSame('0', Site::getPreference('WELCOME_TEXT_AUTH_MODE'));
        self::assertSame('1', Site::getPreference('USE_REGISTRATION_MODULE'));
        self::assertSame('1', Site::getPreference('SHOW_REGISTER_CAUTION'));
        // Welcome-Text liegt unter dem aktuellen Language-Tag.
        self::assertSame(
            'Custom welcome text',
            Site::getPreference('WELCOME_TEXT_AUTH_MODE_' . I18N::languageTag()),
        );
    }

    /**
     * Deaktivierung: leere Boolean-Werte werden als '' persistiert, Modul abgeschaltet.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SiteRegistrationActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_disables_registration_when_flags_are_empty(): void
    {
        // Arrange
        $handler = new SiteRegistrationAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'WELCOME_TEXT_AUTH_MODE'   => '1',
                'WELCOME_TEXT_AUTH_MODE_4' => '',
                'USE_REGISTRATION_MODULE'  => '0',
                'SHOW_REGISTER_CAUTION'    => '0',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertSame('', Site::getPreference('USE_REGISTRATION_MODULE'));
        self::assertSame('', Site::getPreference('SHOW_REGISTER_CAUTION'));
        self::assertSame('1', Site::getPreference('WELCOME_TEXT_AUTH_MODE'));
    }
}
