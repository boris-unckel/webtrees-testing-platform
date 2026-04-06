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
}
