<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ChangeFamilyMembersAction;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeFactsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsPage;
use Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreeAction;
use Fisharebest\Webtrees\Http\RequestHandlers\UserEditAction;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\TimeoutService;
use Fisharebest\Webtrees\Services\UserService;

/**
 * Komponentenintegrationstest: RequestHandlers Batch B.
 *
 * ChangeFamilyMembersAction, MergeRecordsPage, MergeFactsPage,
 * RenumberTreeAction, UserEditAction.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ChangeFamilyMembersAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeFactsPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreeAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserEditAction
 */
class RequestHandlerBatchBIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    // --- MergeRecordsPage (GET, kein Redirect erwartet bei leeren xrefs) ---

    /**
     * MergeRecordsPage ohne xref-Parameter gibt 200 OK zurück.
     */
    public function test_merge_records_page_empty_xrefs_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler  = new MergeRecordsPage();
        $request  = $this->createRequest(
            query: ['xref1' => '', 'xref2' => ''],
            attributes: ['tree' => $this->tree],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * MergeRecordsPage mit gültigen INDI-XREFs gibt 200 OK zurück.
     */
    public function test_merge_records_page_with_xrefs_returns_ok(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler  = new MergeRecordsPage();
        $request  = $this->createRequest(
            query: ['xref1' => 'X1030', 'xref2' => 'X1041'],
            attributes: ['tree' => $this->tree],
        );
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    // --- MergeFactsPage (GET, kann redirect oder 200 sein) ---

    /**
     * MergeFactsPage mit zwei bekannten INDI-XREFs gibt Response zurück.
     * Bei kompatiblen Records: Schritt-2-Seite (200 OK).
     */
    public function test_merge_facts_page_with_two_individuals_returns_response(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler  = new MergeFactsPage();
        $request  = $this->createRequest(
            query: ['xref1' => 'X1030', 'xref2' => 'X1041'],
            attributes: ['tree' => $this->tree],
        );
        $response = $handler->handle($request);

        // 200 OK (Merge-Schritt-2) oder 302 (Redirect zurück zu Schritt 1)
        $this->assertGreaterThanOrEqual(200, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    // --- ChangeFamilyMembersAction (POST → Redirect) ---

    /**
     * ChangeFamilyMembersAction mit bekannter Familie gibt Redirect zurück.
     * Setzt Husb + Wife + leere Kinder-Liste (keine Änderung am Inhalt).
     */
    public function test_change_family_members_action_returns_redirect(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler = new ChangeFamilyMembersAction();
        $request = $this->createRequest(
            method: 'POST',
            attributes: ['tree' => $this->tree],
            params: [
                'xref' => 'f1',
                'HUSB' => '',
                'WIFE' => '',
                'CHIL' => [],
            ],
        );

        $response = $handler->handle($request);

        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    // --- RenumberTreeAction (POST → Redirect) ---

    /**
     * RenumberTreeAction gibt Redirect zurück.
     */
    public function test_renumber_tree_action_returns_redirect(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $handler = new RenumberTreeAction(
            new AdminService(),
            new TimeoutService(new PhpService()),
        );
        $request = $this->createRequest(
            method: 'POST',
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    // --- UserEditAction (POST → Redirect) ---

    /**
     * UserEditAction aktualisiert Admin-User und gibt Redirect zurück.
     */
    public function test_user_edit_action_updates_admin_returns_redirect(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $admin = $this->createAndLoginAdmin();

        $handler = new UserEditAction(
            Registry::container()->get(EmailService::class),
            $this->treeService,
            Registry::container()->get(UserService::class),
        );

        $request = $this->createRequest(
            method: 'POST',
            attributes: ['user' => $admin, 'base_url' => 'https://webtrees.test/'],
            params: [
                'user_id'        => (string) $admin->id(),
                'username'       => $admin->userName(),
                'real_name'      => $admin->realName(),
                'email'          => $admin->email(),
                'password'       => '',
                'theme'          => '',
                'language'       => 'en-US',
                'timezone'       => 'UTC',
                'contact-method' => 'none',
                'comment'        => '',
            ],
        );

        $response = $handler->handle($request);

        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }
}
