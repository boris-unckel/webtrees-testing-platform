<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageEdit;
use Fisharebest\Webtrees\Services\HomePageService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: UserPageEdit-Handler.
 *
 * Deckt den Handler ab, der das persoenliche Dashboard (UserPage) eines
 * angemeldeten Benutzers zum Editieren anzeigt. Der Handler delegiert an den
 * HomePageService, ruft die User-Bloecke des aktuellen Benutzers und die
 * verfuegbaren User-Bloecke fuer das Edit-Formular ab.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageEditTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPageEdit
 */
class UserPageEditIntegrationTest extends MysqlTestCase
{
    protected function tearDown(): void
    {
        $cleanup = [
            'up-edit',
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
     * Klassen-Smoke-Test: UserPageEdit existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageEditTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_edit_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(UserPageEdit::class));
    }

    /**
     * UserPageEdit::handle liefert HTTP 200 und ruft am HomePageService die
     * User-Bloecke und die verfuegbaren User-Bloecke fuer den angemeldeten
     * Benutzer ab.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageEditTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_response(): void
    {
        // Arrange
        $tree       = $this->treeService->create('up-edit', 'User Page Edit');
        $this->tree = $tree;

        $user = $this->userService->create(
            'up-edit',
            'User Page Edit',
            'upedit@example.com',
            'TestPass1!',
        );

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->method('userBlocks')->willReturn(new Collection());
        $home_page_service->method('availableUserBlocks')->willReturn(new Collection());

        $handler = new UserPageEdit($home_page_service);
        $request = $this->createRequest(
            'GET',
            [],
            [],
            ['tree' => $tree, 'user' => $user],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
