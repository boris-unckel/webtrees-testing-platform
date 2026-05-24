<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageEdit;
use Fisharebest\Webtrees\Registry;
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
 * @see docs/tds_conditions_ref.md S35, S46
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
     * UserPageEdit: Container-Resolution + handle() → 200 mit realem HomePageService.
     *
     * Verhaltens-Test (BEHAVIOR_HANDLE / L3SP-077): ersetzt die ehemalige
     * `class_exists`-Tautologie durch einen vollstaendigen Request-Durchlauf gegen
     * die real verdrahtete Klasse aus dem DI-Container. Smoke-Aspekt
     * (Aufloesbarkeit) ist im 200-OK enthalten; zusaetzlich wird die HTML-Form
     * fuer die Block-Edit-Page beobachtet (`url_save` → UserPageUpdate-Route),
     * damit das ausgelieferte View tatsaechlich zur Edit-Action verlinkt.
     *
     * @group ported-l2-doubles
     */
    public function test_user_page_edit_handles_request_via_container(): void
    {
        // Arrange: Tree, eingeloggter Admin als Request-User; UserPageEdit setzt
        // einen angemeldeten Benutzer voraus (Validator::attributes->user()).
        $this->tree = $this->treeService->create('up-edit', 'User Page Edit');
        $admin      = $this->createAndLoginAdmin();

        $handler = Registry::container()->get(UserPageEdit::class);
        $request = $this->createRequest(
            attributes: ['tree' => $this->tree, 'user' => $admin],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 200 OK — Handler ist real aufloesbar und liefert die Edit-Blocks-Page aus.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Postcondition: Body enthaelt die UserPageUpdate-Route als Save-Ziel des
        // Edit-Formulars — beweist, dass das Edit-Blocks-View wirklich gerendert wurde.
        $body = (string) $response->getBody();
        self::assertStringContainsString('my-page-edit', $body);
        self::assertStringContainsString('id="edit-blocks"', $body);
    }

    /**
     * UserPageEdit::handle liefert HTTP 200 und ruft am HomePageService die
     * User-Bloecke und die verfuegbaren User-Bloecke fuer den angemeldeten
     * Benutzer ab.
     *
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
