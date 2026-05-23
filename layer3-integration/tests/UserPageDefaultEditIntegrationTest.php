<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageDefaultEdit;
use Fisharebest\Webtrees\Services\HomePageService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: UserPageDefaultEdit-Handler.
 *
 * Deckt den Admin-Handler ab, der die Default-Block-Konfiguration fuer
 * die UserPage anzeigt. Der Handler delegiert an den HomePageService,
 * pruegft die Existenz der Default-Bloecke und liefert die verfuegbaren
 * User-Bloecke an das Edit-Formular.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageDefaultEditTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPageDefaultEdit
 */
class UserPageDefaultEditIntegrationTest extends MysqlTestCase
{
    /**
     * Klassen-Smoke-Test: UserPageDefaultEdit existiert und ist ladbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageDefaultEditTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_default_edit_class_exists(): void
    {
        // Arrange / Act / Assert
        self::assertTrue(class_exists(UserPageDefaultEdit::class));
    }

    /**
     * UserPageDefaultEdit::handle liefert HTTP 200 und delegiert an den
     * HomePageService fuer Default-Block-Pruefung, User-Bloecke und die
     * verfuegbaren User-Bloecke.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageDefaultEditTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_response(): void
    {
        // Arrange
        $home_page_service = $this->createMock(HomePageService::class);
        $home_page_service->method('checkDefaultUserBlocksExist');
        $home_page_service->method('userBlocks')->willReturn(new Collection());
        $home_page_service->method('availableUserBlocks')->willReturn(new Collection());

        $handler = new UserPageDefaultEdit($home_page_service);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
