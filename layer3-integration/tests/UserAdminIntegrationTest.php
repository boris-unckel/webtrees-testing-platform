<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\UserAddAction;
use Fisharebest\Webtrees\Http\RequestHandlers\UserAddPage;
use Fisharebest\Webtrees\Http\RequestHandlers\UserEditPage;
use Fisharebest\Webtrees\Http\RequestHandlers\UserListData;
use Fisharebest\Webtrees\Http\RequestHandlers\UserListPage;
use Fisharebest\Webtrees\Http\RequestHandlers\UsersCleanupAction;
use Fisharebest\Webtrees\Http\RequestHandlers\UsersCleanupPage;
use Fisharebest\Webtrees\Services\DatatablesService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\MessageService;
use Fisharebest\Webtrees\Services\ModuleService;

/**
 * Komponentenintegrationstest: Benutzerverwaltung Admin — A07.
 *
 * Tests:
 * - UserListPage GET → 200
 * - UsersCleanupPage GET → 200
 * - UserListPage GET mit filter-Parameter → 200
 * - UserAddAction: Anlegen, Duplikat-Username, Duplikat-Email, beides Duplikate
 * - UserAddPage: Formular GET (ohne/mit Prefill-Query/mit Leerwerten) → 200
 * - UserEditPage: Edit-Formular für existierenden User → 200, NotFound für unbekannte ID
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserListPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UsersCleanupPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UsersCleanupAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserAddAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserAddPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserEditPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserListData
 * @see docs/tds_conditions_ref.md A07
 * @see docs/testquality_improve_A07.md
 */
class UserAdminIntegrationTest extends MysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
    }

    protected function tearDown(): void
    {
        $cleanup = [
            'newuser1',
            'existinguser',
            'emailowner',
            'differentuser',
            'dupboth',
            'edituser-p244',
            'ulist',
            'cleanup-noop',
            'cleanup-delete',
        ];
        foreach ($cleanup as $uname) {
            $u = $this->userService->findByUserName($uname);
            if ($u !== null) {
                $this->userService->delete($u);
            }
        }
        parent::tearDown();
    }

    /**
     * EP1: UserListPage GET → 200.
     */
    public function test_user_list_page_returns_200(): void
    {
        $handler  = new UserListPage();
        $request  = $this->createRequest(
            attributes: ['user' => $this->createAndLoginAdmin()],
        );
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: UserListPage GET mit filter-Parameter → 200.
     */
    public function test_user_list_page_with_filter_returns_200(): void
    {
        $handler = new UserListPage();
        $request = $this->createRequest(
            query: ['filter' => 'admin'],
            attributes: ['user' => $this->createAndLoginAdmin()],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP3: UsersCleanupPage GET → 200.
     */
    public function test_users_cleanup_page_returns_200(): void
    {
        $handler  = new UsersCleanupPage($this->userService);
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * UserAddAction POST mit validen Daten legt User an und redirected (302).
     *
     * @group ported-l2-doubles
     */
    public function test_user_add_action_creates_new_user(): void
    {
        $handler  = new UserAddAction($this->userService);
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username'  => 'newuser1',
                'email'     => 'newuser1@example.com',
                'real_name' => 'New User One',
                'password'  => 'Secret1234',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $created = $this->userService->findByUserName('newuser1');
        self::assertNotNull($created);
        self::assertSame('New User One', $created->realName());
        self::assertSame('newuser1@example.com', $created->email());
    }

    /**
     * UserAddAction POST mit existierendem Username → Redirect zurück zum Formular mit username-Parameter.
     *
     * @group ported-l2-doubles
     */
    public function test_user_add_action_redirects_on_duplicate_username(): void
    {
        $this->userService->create('existinguser', 'Existing User', 'existing@example.com', 'existpass');

        $handler  = new UserAddAction($this->userService);
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username'  => 'existinguser',
                'email'     => 'different@example.com',
                'real_name' => 'Another User',
                'password'  => 'Secret1234',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringContainsString('username', $response->getHeaderLine('Location'));
    }

    /**
     * UserAddAction POST mit existierender Email → Redirect zurück zum Formular mit email-Parameter.
     *
     * @group ported-l2-doubles
     */
    public function test_user_add_action_redirects_on_duplicate_email(): void
    {
        $this->userService->create('emailowner', 'Email Owner', 'taken@example.com', 'ownerpass');

        $handler  = new UserAddAction($this->userService);
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username'  => 'differentuser',
                'email'     => 'taken@example.com',
                'real_name' => 'Different User',
                'password'  => 'Secret1234',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringContainsString('email', $response->getHeaderLine('Location'));
    }

    /**
     * UserAddAction POST mit doppeltem Username und Email → Redirect (302).
     *
     * @group ported-l2-doubles
     */
    public function test_user_add_action_redirects_on_both_duplicate_username_and_email(): void
    {
        $this->userService->create('dupboth', 'Dup Both', 'dupboth@example.com', 'duppass');

        $handler  = new UserAddAction($this->userService);
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'username'  => 'dupboth',
                'email'     => 'dupboth@example.com',
                'real_name' => 'Dup Both Again',
                'password'  => 'Secret1234',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * UserAddPage GET ohne Query-Parameter → 200.
     *
     * @group ported-l2-doubles
     */
    public function test_user_add_page_returns_200(): void
    {
        $handler  = new UserAddPage();
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * UserAddPage GET mit Prefill-Query-Parametern (email, real_name, username) → 200.
     *
     * @group ported-l2-doubles
     */
    public function test_user_add_page_with_prefill_query_params_returns_200(): void
    {
        $handler  = new UserAddPage();
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: [
                'email'     => 'prefill@example.com',
                'real_name' => 'Prefilled Name',
                'username'  => 'prefilluser',
            ],
        );
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * UserAddPage GET mit leeren Query-Parametern → 200.
     *
     * @group ported-l2-doubles
     */
    public function test_user_add_page_with_empty_query_params_returns_200(): void
    {
        $handler  = new UserAddPage();
        $request  = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: [
                'email'     => '',
                'real_name' => '',
                'username'  => '',
            ],
        );
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * UserEditPage GET mit user_id eines existierenden Users → 200 (Edit-Formular).
     *
     * @group ported-l2-doubles
     */
    public function test_user_edit_page_returns_200_for_existing_user(): void
    {
        // Arrange
        $user            = $this->userService->create(
            'edituser-p244',
            'Edit User P244',
            'edit-p244@example.com',
            'Secret1234',
        );
        $mail_service    = new EmailService();
        $message_service = new MessageService($mail_service, $this->userService);
        $module_service  = new ModuleService();
        $handler         = new UserEditPage(
            $message_service,
            $module_service,
            $this->treeService,
            $this->userService,
        );
        $request         = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: ['user_id' => (string) $user->id()],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * UserEditPage GET mit nicht existierender user_id → HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_user_edit_page_throws_not_found_for_non_existing_user(): void
    {
        // Arrange
        $mail_service    = new EmailService();
        $message_service = new MessageService($mail_service, $this->userService);
        $module_service  = self::createStub(ModuleService::class);
        $handler         = new UserEditPage(
            $message_service,
            $module_service,
            $this->treeService,
            $this->userService,
        );
        $request         = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: ['user_id' => '99999'],
        );

        // Assert
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }

    /**
     * UserListData GET mit Datatables-Parametern → 200 und valides Datatables-JSON.
     *
     * @group ported-l2-doubles
     */
    public function test_user_list_data_returns_datatable_json(): void
    {
        // Arrange
        $admin   = $this->createAndLoginAdmin();
        $handler = new UserListData(
            new DatatablesService(),
            new ModuleService(),
            $this->userService,
        );
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: [
                'draw'   => '1',
                'start'  => '0',
                'length' => '10',
                'search' => ['value' => ''],
            ],
            attributes: ['user' => $admin],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('recordsTotal', $json);
        self::assertArrayHasKey('recordsFiltered', $json);
    }

    /**
     * UserListData GET mit Datatables-Suchwert → 200 und gefiltertes JSON enthält den Admin.
     *
     * @group ported-l2-doubles
     */
    public function test_user_list_data_returns_filtered_datatable_json(): void
    {
        // Arrange
        $admin   = $this->createAndLoginAdmin();
        $handler = new UserListData(
            new DatatablesService(),
            new ModuleService(),
            $this->userService,
        );
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query: [
                'draw'   => '1',
                'start'  => '0',
                'length' => '10',
                'search' => ['value' => 'test-admin'],
            ],
            attributes: ['user' => $admin],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $json = json_decode((string) $response->getBody(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('data', $json);
        self::assertGreaterThanOrEqual(1, (int) ($json['recordsFiltered'] ?? 0));
    }

    /**
     * UserListPage GET mit dem aktuellen Auth::user() als Request-Attribut → 200.
     *
     * @group ported-l2-doubles
     */
    public function test_user_list_page_with_auth_user_attribute_returns_200(): void
    {
        // Arrange
        $handler = new UserListPage();
        $request = $this->createRequest(
            attributes: ['user' => Auth::user()],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * UserListPage GET mit einem frisch angelegten registrierten User als Request-Attribut → 200.
     *
     * @group ported-l2-doubles
     */
    public function test_user_list_page_with_registered_user_attribute_returns_200(): void
    {
        // Arrange
        $user    = $this->userService->create('ulist', 'User List', 'ulist@example.com', 'Secret1234');
        $handler = new UserListPage();
        $request = $this->createRequest(
            attributes: ['user' => $user],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * UsersCleanupAction POST mit leerer delete-Liste → Redirect (302), kein User wird gelöscht.
     *
     * @group ported-l2-doubles
     */
    public function test_users_cleanup_action_with_no_deletes_redirects(): void
    {
        // Arrange
        $user    = $this->userService->create(
            'cleanup-noop',
            'Cleanup Noop',
            'cleanup-noop@example.com',
            'Secret1234',
        );
        $handler = new UsersCleanupAction($this->userService);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['delete' => []],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // User wurde nicht gelöscht.
        $row = DB::table('user')->where('user_id', '=', $user->id())->first();
        self::assertNotNull($row);
    }

    /**
     * UsersCleanupAction POST mit einer User-ID → Redirect (302), der User wird aus der Datenbank entfernt.
     *
     * @group ported-l2-doubles
     */
    public function test_users_cleanup_action_deletes_user_and_redirects(): void
    {
        // Arrange
        $user    = $this->userService->create(
            'cleanup-delete',
            'Cleanup Delete',
            'cleanup-delete@example.com',
            'Secret1234',
        );
        $handler = new UsersCleanupAction($this->userService);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['delete' => [(string) $user->id()]],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // DB direkt abfragen, da UserService::find() Ergebnisse cached.
        $row = DB::table('user')->where('user_id', '=', $user->id())->first();
        self::assertNull($row);
    }
}
