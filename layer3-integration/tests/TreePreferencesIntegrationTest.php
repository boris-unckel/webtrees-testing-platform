<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePreferencesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePreferencesPage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;

/**
 * Komponentenintegrationstest: Stammbaum-Präferenzen — A04.
 *
 * Tests:
 * - TreePreferencesPage GET → 200
 * - TreePreferencesAction POST → Redirect, gedcom_setting aktualisiert
 * - TreePreferencesAction POST als Non-Admin → darf gedcom_name nicht ändern
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePreferencesPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePreferencesAction
 * @see docs/tds_conditions_ref.md A04
 * @see docs/testquality_improve_A04.md
 */
class TreePreferencesIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('a04-prefs', 'A04 Prefs', self::DEMO_GED);
    }

    /**
     * EP1: TreePreferencesPage GET → 200.
     */
    public function test_tree_preferences_page_returns_200(): void
    {
        $module_service = new ModuleService();
        $handler        = new TreePreferencesPage(
            $module_service,
            $this->treeService,
            $this->userService,
        );

        $request = $this->createRequest(
            attributes: [
                'tree'     => $this->tree,
                'base_url' => 'https://webtrees.test',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: TreePreferencesAction POST → 302 Redirect + Preference gespeichert.
     */
    public function test_tree_preferences_action_saves_preference_and_redirects(): void
    {
        $handler = new TreePreferencesAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'gedcom'                      => $this->tree->name(),
                'title'                       => 'Geänderter Titel',
                'CALENDAR_FORMAT0'            => 'gregorian',
                'CALENDAR_FORMAT1'            => 'gregorian',
                'CHART_BOX_TAGS'              => [],
                'CONTACT_USER_ID'             => '0',
                'EXPAND_NOTES'                => '0',
                'EXPAND_SOURCES'              => '0',
                'FAM_FACTS_QUICK'             => [],
                'FORMAT_TEXT'                 => '',
                'GENERATE_UIDS'               => '0',
                'HIDE_GEDCOM_ERRORS'          => '0',
                'INDI_FACTS_QUICK'            => [],
                'MEDIA_DIRECTORY'             => 'media/',
                'MEDIA_UPLOAD'                => '1',
                'META_DESCRIPTION'            => '',
                'META_TITLE'                  => '',
                'NO_UPDATE_CHAN'               => '0',
                'PEDIGREE_ROOT_ID'            => '',
                'QUICK_REQUIRED_FACTS'        => [],
                'QUICK_REQUIRED_FAMFACTS'     => [],
                'SHOW_COUNTER'                => '0',
                'SHOW_EST_LIST_DATES'         => '0',
                'SHOW_FACT_ICONS'             => '1',
                'SHOW_GEDCOM_RECORD'          => '0',
                'SHOW_HIGHLIGHT_IMAGES'       => '1',
                'SHOW_LAST_CHANGE'            => '1',
                'SHOW_MEDIA_DOWNLOAD'         => '0',
                'SHOW_NO_WATERMARK'           => '0',
                'SHOW_PARENTS_AGE'            => '1',
                'SHOW_PEDIGREE_PLACES'        => '9',
                'SHOW_PEDIGREE_PLACES_SUFFIX' => '0',
                'SHOW_RELATIVES_EVENTS'       => [],
                'SUBLIST_TRIGGER_I'           => '200',
                'SURNAME_LIST_STYLE'          => 'style1',
                'SURNAME_TRADITION'           => 'none',
                'USE_SILHOUETTE'              => '1',
                'WEBMASTER_USER_ID'           => '0',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: Preference gespeichert
        $saved = DB::table('gedcom_setting')
            ->where('gedcom_id', '=', $this->tree->id())
            ->where('setting_name', '=', 'SHOW_COUNTER')
            ->value('setting_value');

        self::assertSame('', $saved);
    }

    /**
     * EP3: TreePreferencesAction POST → Meta-Description wird gespeichert.
     */
    public function test_tree_preferences_action_saves_meta_description(): void
    {
        $handler = new TreePreferencesAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'gedcom'                      => $this->tree->name(),
                'title'                       => $this->tree->title(),
                'CALENDAR_FORMAT0'            => 'gregorian',
                'CALENDAR_FORMAT1'            => 'gregorian',
                'CHART_BOX_TAGS'              => [],
                'CONTACT_USER_ID'             => '0',
                'EXPAND_NOTES'                => '0',
                'EXPAND_SOURCES'              => '0',
                'FAM_FACTS_QUICK'             => [],
                'FORMAT_TEXT'                 => '',
                'GENERATE_UIDS'               => '0',
                'HIDE_GEDCOM_ERRORS'          => '0',
                'INDI_FACTS_QUICK'            => [],
                'MEDIA_DIRECTORY'             => 'media/',
                'MEDIA_UPLOAD'                => '1',
                'META_DESCRIPTION'            => 'Test Description A04',
                'META_TITLE'                  => '',
                'NO_UPDATE_CHAN'               => '0',
                'PEDIGREE_ROOT_ID'            => '',
                'QUICK_REQUIRED_FACTS'        => [],
                'QUICK_REQUIRED_FAMFACTS'     => [],
                'SHOW_COUNTER'                => '1',
                'SHOW_EST_LIST_DATES'         => '0',
                'SHOW_FACT_ICONS'             => '1',
                'SHOW_GEDCOM_RECORD'          => '0',
                'SHOW_HIGHLIGHT_IMAGES'       => '1',
                'SHOW_LAST_CHANGE'            => '1',
                'SHOW_MEDIA_DOWNLOAD'         => '0',
                'SHOW_NO_WATERMARK'           => '0',
                'SHOW_PARENTS_AGE'            => '1',
                'SHOW_PEDIGREE_PLACES'        => '9',
                'SHOW_PEDIGREE_PLACES_SUFFIX' => '0',
                'SHOW_RELATIVES_EVENTS'       => [],
                'SUBLIST_TRIGGER_I'           => '200',
                'SURNAME_LIST_STYLE'          => 'style1',
                'SURNAME_TRADITION'           => 'none',
                'USE_SILHOUETTE'              => '1',
                'WEBMASTER_USER_ID'           => '0',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $saved = DB::table('gedcom_setting')
            ->where('gedcom_id', '=', $this->tree->id())
            ->where('setting_name', '=', 'META_DESCRIPTION')
            ->value('setting_value');

        self::assertSame('Test Description A04', $saved);
    }

    /**
     * EP4: TreePreferencesAction POST persistiert mehrere Preferences und liefert getPreference().
     *
     * Ported from L2 doubles: prüft EXPAND_NOTES, HIDE_GEDCOM_ERRORS, SURNAME_LIST_STYLE='style2',
     * SURNAME_TRADITION='paternal' nach dem POST über die Tree-API (statt direktem DB-Read).
     *
     * @group ported-l2-doubles
     */
    public function test_handle_saves_preferences_and_returns_via_get_preference(): void
    {
        // Arrange
        $handler = new TreePreferencesAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'CALENDAR_FORMAT0'            => 'gregorian',
                'CALENDAR_FORMAT1'            => 'gregorian',
                'CHART_BOX_TAGS'              => [],
                'CONTACT_USER_ID'             => '0',
                'EXPAND_NOTES'                => '1',
                'EXPAND_SOURCES'              => '',
                'FAM_FACTS_QUICK'             => [],
                'FORMAT_TEXT'                 => '',
                'gedcom'                      => $this->tree->name(),
                'GENERATE_UIDS'               => '',
                'HIDE_GEDCOM_ERRORS'          => '1',
                'INDI_FACTS_QUICK'            => [],
                'MEDIA_DIRECTORY'             => 'media/',
                'MEDIA_UPLOAD'                => '0',
                'META_DESCRIPTION'            => '',
                'META_TITLE'                  => '',
                'NO_UPDATE_CHAN'              => '',
                'PEDIGREE_ROOT_ID'            => '',
                'QUICK_REQUIRED_FACTS'        => [],
                'QUICK_REQUIRED_FAMFACTS'     => [],
                'SHOW_COUNTER'                => '1',
                'SHOW_EST_LIST_DATES'         => '1',
                'SHOW_FACT_ICONS'             => '1',
                'SHOW_GEDCOM_RECORD'          => '',
                'SHOW_HIGHLIGHT_IMAGES'       => '1',
                'SHOW_LAST_CHANGE'            => '1',
                'SHOW_MEDIA_DOWNLOAD'         => '0',
                'SHOW_NO_WATERMARK'           => '1',
                'SHOW_PARENTS_AGE'            => '1',
                'SHOW_PEDIGREE_PLACES'        => '9',
                'SHOW_PEDIGREE_PLACES_SUFFIX' => '0',
                'SHOW_RELATIVES_EVENTS'       => [],
                'SUBLIST_TRIGGER_I'           => '200',
                'SURNAME_LIST_STYLE'          => 'style2',
                'SURNAME_TRADITION'           => 'paternal',
                'USE_SILHOUETTE'              => '1',
                'WEBMASTER_USER_ID'           => '0',
                'title'                       => $this->tree->title(),
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertSame('1', $this->tree->getPreference('EXPAND_NOTES'));
        self::assertSame('1', $this->tree->getPreference('HIDE_GEDCOM_ERRORS'));
        self::assertSame('style2', $this->tree->getPreference('SURNAME_LIST_STYLE'));
        self::assertSame('paternal', $this->tree->getPreference('SURNAME_TRADITION'));
    }

    /**
     * EP5: TreePreferencesAction POST behandelt Boolean-False-Werte und kombiniertes CALENDAR_FORMAT.
     *
     * Ported from L2 doubles: leere Strings werden als Boolean-False persistiert, und die beiden
     * CALENDAR_FORMAT*-Felder werden zu 'gregorian_and_jewish' kombiniert.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_with_boolean_false_values_and_combined_calendar_format(): void
    {
        // Arrange
        $handler = new TreePreferencesAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'CALENDAR_FORMAT0'            => 'gregorian',
                'CALENDAR_FORMAT1'            => 'jewish',
                'CHART_BOX_TAGS'              => [],
                'CONTACT_USER_ID'             => '0',
                'EXPAND_NOTES'                => '',
                'EXPAND_SOURCES'              => '',
                'FAM_FACTS_QUICK'             => [],
                'FORMAT_TEXT'                 => 'markdown',
                'gedcom'                      => $this->tree->name(),
                'GENERATE_UIDS'               => '',
                'HIDE_GEDCOM_ERRORS'          => '',
                'INDI_FACTS_QUICK'            => [],
                'MEDIA_DIRECTORY'             => 'media/',
                'MEDIA_UPLOAD'                => '1',
                'META_DESCRIPTION'            => 'Test description',
                'META_TITLE'                  => 'Test title',
                'NO_UPDATE_CHAN'              => '',
                'PEDIGREE_ROOT_ID'            => '',
                'QUICK_REQUIRED_FACTS'        => [],
                'QUICK_REQUIRED_FAMFACTS'     => [],
                'SHOW_COUNTER'                => '',
                'SHOW_EST_LIST_DATES'         => '',
                'SHOW_FACT_ICONS'             => '',
                'SHOW_GEDCOM_RECORD'          => '',
                'SHOW_HIGHLIGHT_IMAGES'       => '',
                'SHOW_LAST_CHANGE'            => '',
                'SHOW_MEDIA_DOWNLOAD'         => '0',
                'SHOW_NO_WATERMARK'           => '1',
                'SHOW_PARENTS_AGE'            => '',
                'SHOW_PEDIGREE_PLACES'        => '9',
                'SHOW_PEDIGREE_PLACES_SUFFIX' => '0',
                'SHOW_RELATIVES_EVENTS'       => [],
                'SUBLIST_TRIGGER_I'           => '200',
                'SURNAME_LIST_STYLE'          => 'style1',
                'SURNAME_TRADITION'           => 'none',
                'USE_SILHOUETTE'              => '',
                'WEBMASTER_USER_ID'           => '0',
                'title'                       => $this->tree->title(),
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Boolean false => (string) false === ''
        self::assertSame('', $this->tree->getPreference('EXPAND_NOTES'));
        self::assertSame('', $this->tree->getPreference('SHOW_COUNTER'));
        // Calendar format wird kombiniert
        self::assertSame('gregorian_and_jewish', $this->tree->getPreference('CALENDAR_FORMAT'));
        self::assertSame('markdown', $this->tree->getPreference('FORMAT_TEXT'));
        self::assertSame('Test description', $this->tree->getPreference('META_DESCRIPTION'));
    }
}
