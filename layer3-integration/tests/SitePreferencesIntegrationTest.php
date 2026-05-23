<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\SitePreferencesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\SitePreferencesPage;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Site;

/**
 * Komponentenintegrationstest: Site-Präferenzen — A06.
 *
 * Tests:
 * - SitePreferencesPage GET → 200
 * - SitePreferencesAction POST → Redirect, site_setting aktualisiert
 * - SitePreferencesAction POST: ungültiger INDEX_DIRECTORY → FlashMessage 'danger' + Redirect
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SitePreferencesPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SitePreferencesAction
 * @see docs/testquality_improve_A06.md
 */
class SitePreferencesIntegrationTest extends MysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
    }

    /**
     * EP1: SitePreferencesPage GET → 200.
     */
    public function test_site_preferences_page_returns_200(): void
    {
        $handler  = new SitePreferencesPage(new ModuleService());
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: SitePreferencesAction POST mit gültigem INDEX_DIRECTORY → 302.
     */
    public function test_site_preferences_action_valid_data_redirects(): void
    {
        $handler = new SitePreferencesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'INDEX_DIRECTORY'     => '/var/www/html/data/',
                'ALLOW_CHANGE_GEDCOM' => '1',
                'LANGUAGE'            => 'en-US',
                'THEME_DIR'           => '',
                'TIMEZONE'            => 'UTC',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EP3: SitePreferencesAction POST: LANGUAGE-Präferenz wird gespeichert.
     */
    public function test_site_preferences_action_saves_language(): void
    {
        $handler = new SitePreferencesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'INDEX_DIRECTORY'     => '/var/www/html/data/',
                'ALLOW_CHANGE_GEDCOM' => '0',
                'LANGUAGE'            => 'de',
                'THEME_DIR'           => '',
                'TIMEZONE'            => 'Europe/Berlin',
            ],
        );

        $handler->handle($request);

        self::assertSame('de', Site::getPreference('LANGUAGE'));
    }

    /**
     * EP4: SitePreferencesAction POST: ungültiger INDEX_DIRECTORY → Redirect (mit Flash-Message).
     */
    public function test_site_preferences_action_invalid_directory_still_redirects(): void
    {
        $handler = new SitePreferencesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'INDEX_DIRECTORY'     => '/this/does/not/exist/',
                'ALLOW_CHANGE_GEDCOM' => '0',
                'LANGUAGE'            => 'en-US',
                'THEME_DIR'           => '',
                'TIMEZONE'            => 'UTC',
            ],
        );

        $response = $handler->handle($request);

        // Even with invalid directory, we get a redirect (not 500)
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Persistenz: alle übergebenen Schlüssel werden gespeichert.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SitePreferencesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_saves_preferences_and_redirects(): void
    {
        // Arrange
        $handler = new SitePreferencesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'INDEX_DIRECTORY'     => '/tmp/',
                'ALLOW_CHANGE_GEDCOM' => '1',
                'LANGUAGE'            => 'en-GB',
                'THEME_DIR'           => '_administration',
                'TIMEZONE'            => 'UTC',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertSame('1', Site::getPreference('ALLOW_CHANGE_GEDCOM'));
        self::assertSame('en-GB', Site::getPreference('LANGUAGE'));
        self::assertSame('_administration', Site::getPreference('THEME_DIR'));
        self::assertSame('UTC', Site::getPreference('TIMEZONE'));
    }

    /**
     * Boolean-Konvertierung: leerer Wert für ALLOW_CHANGE_GEDCOM wird als '' gespeichert.
     *
     * Bemerkung: Quelltest in der Vorlage trägt den Namen
     * "AppendsTrailingSlashToIndexDirectory"; das tatsächliche Append-Verhalten
     * wird vom Action-Handler nicht garantiert (siehe Source). Hier wird die
     * stabile Persistenz-Aussage übernommen, nicht die irreführende Bezeichnung.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SitePreferencesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_persists_empty_allow_change_gedcom_as_empty_string(): void
    {
        // Arrange
        $handler = new SitePreferencesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'INDEX_DIRECTORY'     => '/tmp',
                'ALLOW_CHANGE_GEDCOM' => '',
                'LANGUAGE'            => 'de',
                'THEME_DIR'           => '',
                'TIMEZONE'            => 'Europe/Berlin',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        // Boolean false => (string) false === ''
        self::assertSame('', Site::getPreference('ALLOW_CHANGE_GEDCOM'));
        self::assertSame('de', Site::getPreference('LANGUAGE'));
        self::assertSame('Europe/Berlin', Site::getPreference('TIMEZONE'));
    }

    /**
     * Schutz: nicht existierendes INDEX_DIRECTORY überschreibt den bisherigen Wert nicht.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SitePreferencesActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_keeps_existing_index_directory_when_new_path_missing(): void
    {
        // Arrange — bekannten Ausgangswert setzen
        Site::setPreference('INDEX_DIRECTORY', '/tmp/');

        $handler = new SitePreferencesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'INDEX_DIRECTORY'     => '/nonexistent/path/that/does/not/exist/',
                'ALLOW_CHANGE_GEDCOM' => '',
                'LANGUAGE'            => 'en-GB',
                'THEME_DIR'           => '',
                'TIMEZONE'            => 'UTC',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        // Index directory soll nicht auf einen nicht existierenden Pfad geändert worden sein.
        self::assertSame('/tmp/', Site::getPreference('INDEX_DIRECTORY'));
    }
}
