<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPage;
use Fisharebest\Webtrees\Services\HomePageService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: UserPage-Handler.
 *
 * Deckt den Handler ab, der das persoenliche Dashboard (UserPage) eines
 * angemeldeten Benutzers rendert. Der Handler delegiert an den
 * HomePageService, der die fuer den aktuellen Benutzer konfigurierten
 * User-Bloecke liefert. Geprueft werden die Klassenexistenz und der
 * erfolgreiche GET-Pfad (HTTP 200) gegen einen real erzeugten Benutzer
 * mit gemocktem HomePageService, damit die View-Renderingkette der
 * echten Block-Module umgangen wird.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPage
 */
class UserPageIntegrationTest extends MysqlTestCase
{
    protected function tearDown(): void
    {
        $cleanup = [
            'user-page',
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
     * Klassen-Smoke-Test: UserPage existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(UserPage::class));
    }

    /**
     * UserPage::handle liefert HTTP 200 und ruft am HomePageService die
     * User-Bloecke fuer den angemeldeten Benutzer ab. HomePageService wird
     * gemockt, um das View-Rendering der echten Block-Module zu vermeiden.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_response(): void
    {
        // Arrange
        $tree       = $this->treeService->create('user-page', 'User Page');
        $this->tree = $tree;

        $user = $this->userService->create(
            'user-page',
            'User Page',
            'user@example.com',
            'TestPass1!',
        );

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->method('userBlocks')->willReturn(new Collection());

        $handler = new UserPage($home_page_service);
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
