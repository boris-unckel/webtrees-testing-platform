<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPage;
use Fisharebest\Webtrees\Registry;
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
     * UserPage: Container-Resolution + handle() → 200 mit realem HomePageService.
     *
     * Verhaltens-Test (BEHAVIOR_HANDLE / L3SP-078): ersetzt die ehemalige
     * `class_exists`-Tautologie durch einen vollstaendigen Request-Durchlauf
     * gegen die real verdrahtete Klasse aus dem DI-Container. Smoke-Aspekt
     * (Aufloesbarkeit) ist im 200-OK enthalten; zusaetzlich werden zwei
     * spezifische Postconditions geprueft:
     *   1. Das gerenderte View ist `user-page` — Body enthaelt die in der
     *      View-Datei verankerte Block-Container-Klasse `wt-main-blocks`.
     *   2. Der dokumentierte Side-Effekt des Handlers (Default-User-Bloecke
     *      kopieren, wenn der User noch keine eigenen Bloecke hat) hat
     *      stattgefunden — nach `handle()` existieren `block`-Zeilen fuer
     *      die `user_id` des Test-Admins.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_handles_request_via_container(): void
    {
        // Arrange: Tree, eingeloggter Admin als Request-User; UserPage setzt
        // einen angemeldeten Benutzer voraus (Validator::attributes->user()).
        $this->tree = $this->treeService->create('user-page', 'User Page');
        $admin      = $this->createAndLoginAdmin();

        // Ausgangslage: Admin hat noch keine eigenen User-Bloecke. Damit der
        // Default-Copy-Pfad des Handlers tatsaechlich greift, evtl. von
        // frueheren Laeufen liegen gebliebene Bloecke fuer diese user_id
        // entfernen.
        DB::table('block')->where('user_id', '=', $admin->id())->delete();

        $handler = Registry::container()->get(UserPage::class);
        $request = $this->createRequest(
            attributes: ['tree' => $this->tree, 'user' => $admin],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 200 OK — Handler ist real aufloesbar und liefert die
        // User-Page aus.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Postcondition 1: das gerenderte View ist `user-page` — die
        // Block-Container-Klasse aus der View-Datei steht im Body.
        $body = (string) $response->getBody();
        self::assertStringContainsString('wt-main-blocks', $body);

        // Postcondition 2: der Side-Effekt des Handlers (Defaults kopieren,
        // wenn der User noch keine eigenen Bloecke hat) ist eingetreten.
        $block_count = DB::table('block')
            ->where('user_id', '=', $admin->id())
            ->count();
        self::assertGreaterThan(0, $block_count);
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
