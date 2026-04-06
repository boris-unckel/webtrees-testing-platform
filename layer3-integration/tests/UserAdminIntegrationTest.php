<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\UserListPage;
use Fisharebest\Webtrees\Http\RequestHandlers\UsersCleanupPage;

/**
 * Komponentenintegrationstest: Benutzerverwaltung Admin — A07.
 *
 * Tests:
 * - UserListPage GET → 200
 * - UsersCleanupPage GET → 200
 * - UserListPage GET mit filter-Parameter → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserListPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UsersCleanupPage
 * @see docs/testquality_improve_A07.md
 */
class UserAdminIntegrationTest extends MysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
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
}
