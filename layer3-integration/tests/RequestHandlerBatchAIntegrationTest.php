<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\GedcomRecordPage;
use Fisharebest\Webtrees\Http\RequestHandlers\HelpText;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePrivacyAction;
use Fisharebest\Webtrees\Http\RequestHandlers\UnconnectedAction;
use Fisharebest\Webtrees\Http\RequestHandlers\UnconnectedPage;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\LinkedRecordService;

/**
 * Komponentenintegrationstest: RequestHandlers Batch A.
 *
 * HelpText, GedcomRecordPage, DeleteRecord (DB-abhängig),
 * TreePrivacyAction (POST-Action → Redirect).
 *
 * @see docs/tds_conditions_ref.md S50, P32, P33
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\HelpText
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\GedcomRecordPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePrivacyAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UnconnectedAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UnconnectedPage
 */
class RequestHandlerBatchAIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    // --- HelpText (Bootstrap-only, kein Tree) ---

    /**
     * HelpText für topic=DATE gibt 200 OK zurück.
     */
    public function test_help_text_date_returns_ok(): void
    {
        $handler  = new HelpText();
        $request  = $this->createRequest(attributes: ['topic' => 'DATE']);
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * HelpText für topic=NAME gibt 200 OK zurück.
     */
    public function test_help_text_name_returns_ok(): void
    {
        $handler  = new HelpText();
        $request  = $this->createRequest(attributes: ['topic' => 'NAME']);
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * HelpText für topic=pending_changes gibt 200 OK zurück.
     */
    public function test_help_text_pending_changes_returns_ok(): void
    {
        $handler  = new HelpText();
        $request  = $this->createRequest(attributes: ['topic' => 'pending_changes']);
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * HelpText für topic=relationship-privacy gibt 200 OK zurück.
     */
    public function test_help_text_relationship_privacy_returns_ok(): void
    {
        $handler  = new HelpText();
        $request  = $this->createRequest(attributes: ['topic' => 'relationship-privacy']);
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // --- GedcomRecordPage (DB-abhängig) ---

    /**
     * GedcomRecordPage für SOUR-Record gibt Response zurück (2xx oder 3xx).
     * Für bekannte Record-Typen folgt ein Redirect zur spezifischen Seite.
     */
    public function test_gedcom_record_page_source_returns_response(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler  = new GedcomRecordPage(new ClipboardService(), new LinkedRecordService());
        $request  = $this->createRequest(attributes: ['tree' => $this->tree, 'xref' => 'X1102']);
        $response = $handler->handle($request);

        $this->assertGreaterThanOrEqual(200, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    /**
     * GedcomRecordPage für REPO-Record gibt Response zurück.
     */
    public function test_gedcom_record_page_repository_returns_response(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler  = new GedcomRecordPage(new ClipboardService(), new LinkedRecordService());
        $request  = $this->createRequest(attributes: ['tree' => $this->tree, 'xref' => 'X1165']);
        $response = $handler->handle($request);

        $this->assertGreaterThanOrEqual(200, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    // --- TreePrivacyAction (POST → Redirect) ---

    /**
     * TreePrivacyAction mit leeren Arrays gibt Redirect zurück.
     */
    public function test_tree_privacy_action_post_empty_gives_redirect(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler = new TreePrivacyAction();
        $request = $this->createRequest(
            method: 'POST',
            attributes: ['tree' => $this->tree],
            params: [
                'delete'                     => [],
                'xref'                       => [],
                'tag_type'                   => [],
                'resn'                       => [],
                'HIDE_LIVE_PEOPLE'           => '1',
                'KEEP_ALIVE_YEARS_BIRTH'     => '0',
                'KEEP_ALIVE_YEARS_DEATH'     => '0',
                'MAX_ALIVE_AGE'              => '120',
                'REQUIRE_AUTHENTICATION'     => '0',
                'SHOW_DEAD_PEOPLE'           => '15',
                'SHOW_LIVING_NAMES'          => '0',
                'SHOW_PRIVATE_RELATIONSHIPS' => '1',
            ],
        );

        $response = $handler->handle($request);

        // Redirect nach ManageTrees
        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    // --- DeleteRecord (DB-abhängig, schreibend) ---

    /**
     * DeleteRecord für SOUR-Record gibt 200 OK zurück.
     * Der Record wird aus dem frisch importierten demo.ged-Baum gelöscht.
     */
    public function test_delete_record_source_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler  = new DeleteRecord(new LinkedRecordService());
        $request  = $this->createRequest(attributes: ['tree' => $this->tree, 'xref' => 'X1103']);
        $response = $handler->handle($request);

        // DeleteRecord gibt 204 No Content zurück (leere JSON-Antwort)
        $this->assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    // --- UnconnectedAction (POST → Redirect zur UnconnectedPage) ---

    /**
     * UnconnectedAction leitet POST auf die UnconnectedPage um (302 Found).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UnconnectedActionTest.php
     * @group ported-l2-doubles
     */
    public function test_unconnected_action_post_redirects_to_unconnected_page(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler  = new UnconnectedAction();
        $request  = $this->createRequest(
            method: 'POST',
            params: [
                'aliases'    => '0',
                'associates' => '0',
            ],
            attributes: ['tree' => $this->tree],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    // --- UnconnectedPage (GET, tree-bezogen) ---

    /**
     * UnconnectedPage liefert 200 OK für einen importierten Baum ohne Query-Parameter.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UnconnectedPageTest.php
     * @group ported-l2-doubles
     */
    public function test_unconnected_page_returns_ok_for_tree(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $handler  = new UnconnectedPage();
        $request  = $this->createRequest(
            attributes: ['tree' => $this->tree, 'user' => $admin],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * UnconnectedPage liefert 200 OK mit aktivierten aliases- und associates-Optionen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UnconnectedPageTest.php
     * @group ported-l2-doubles
     */
    public function test_unconnected_page_returns_ok_with_aliases_and_associates(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $handler  = new UnconnectedPage();
        $request  = $this->createRequest(
            query: [
                'aliases'    => '1',
                'associates' => '1',
            ],
            attributes: ['tree' => $this->tree, 'user' => $admin],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
