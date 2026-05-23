<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageUpdate;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Services\HomePageService;

/**
 * Komponentenintegrationstest: UserPageUpdate-Handler.
 *
 * Pendant zu UserPageEditIntegrationTest (Edit-Phase, GET); die Update-Phase
 * speichert die Block-Konfiguration der persoenlichen UserPage eines
 * angemeldeten Benutzers und liefert nach erfolgreichem Persistieren einen
 * Redirect (HTTP 302). Der Handler delegiert die Persistenz an den
 * HomePageService (updateUserBlocks fuer die User-ID des aktuellen Benutzers).
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageUpdateTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPageUpdate
 */
class UserPageUpdateIntegrationTest extends MysqlTestCase
{
    protected function tearDown(): void
    {
        $cleanup = [
            'up-upd',
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
     * Klassen-Smoke-Test: UserPageUpdate existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_update_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(UserPageUpdate::class));
    }

    /**
     * UserPageUpdate::handle persistiert die uebermittelten User-Bloecke
     * ueber den HomePageService und leitet danach per HTTP 302 weiter.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_explicit_blocks_redirects(): void
    {
        // Arrange
        $tree       = $this->treeService->create('up-upd', 'User Page Update');
        $this->tree = $tree;

        $user = $this->userService->create(
            'up-upd',
            'User Page Update',
            'upupd@example.com',
            'TestPass1!',
        );

        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('updateUserBlocks');

        $handler = new UserPageUpdate($home_page_service);
        $request = $this->createRequest(
            'POST',
            [],
            [
                ModuleBlockInterface::MAIN_BLOCKS => [],
                ModuleBlockInterface::SIDE_BLOCKS => [],
            ],
            ['tree' => $tree, 'user' => $user],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
