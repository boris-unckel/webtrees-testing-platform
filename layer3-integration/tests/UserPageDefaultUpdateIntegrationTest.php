<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageDefaultUpdate;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Services\HomePageService;

/**
 * Komponentenintegrationstest: UserPageDefaultUpdate-Handler.
 *
 * Pendant zu UserPageDefaultEditIntegrationTest (Edit-Phase, GET); die
 * Update-Phase speichert die Default-Block-Konfiguration fuer die UserPage
 * und liefert nach erfolgreichem Persistieren einen Redirect (HTTP 302)
 * zum Control-Panel. Der Handler delegiert die Persistenz an den
 * HomePageService (updateUserBlocks mit user_id = -1 fuer die Defaults).
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageDefaultUpdateTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPageDefaultUpdate
 */
class UserPageDefaultUpdateIntegrationTest extends MysqlTestCase
{
    /**
     * Klassen-Smoke-Test: UserPageDefaultUpdate existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageDefaultUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_default_update_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(UserPageDefaultUpdate::class));
    }

    /**
     * UserPageDefaultUpdate::handle persistiert die Default-Blocks ueber
     * den HomePageService (user_id = -1) und leitet nach dem Speichern
     * per HTTP 302 zum Control-Panel weiter.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageDefaultUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_to_control_panel(): void
    {
        // Arrange
        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->expects(self::once())
            ->method('updateUserBlocks')
            ->with(-1, self::anything(), self::anything());

        $handler = new UserPageDefaultUpdate($home_page_service);
        $request = $this->createRequest('POST', [], [
            ModuleBlockInterface::MAIN_BLOCKS => [],
            ModuleBlockInterface::SIDE_BLOCKS => [],
        ]);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
