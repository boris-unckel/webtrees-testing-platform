<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageUpdate;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Registry;
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
                // Verwaiste `block`-Zeilen des Testbenutzers vor dem Loeschen
                // entfernen — der Persistenzpfad des Update-Handlers legt
                // diese an und sie haengen am `user_id`, nicht am Tree.
                DB::table('block')->where('user_id', '=', $u->id())->delete();
                $this->userService->delete($u);
            }
        }

        // Auch fuer den Admin-Benutzer ggf. verbliebene Bloecke aus dem
        // Container-Pfad-Test aufraeumen.
        $admin = $this->userService->findByUserName('test-admin');
        if ($admin !== null) {
            DB::table('block')->where('user_id', '=', $admin->id())->delete();
        }

        parent::tearDown();
    }

    /**
     * UserPageUpdate: Container-Resolution + handle() → 302 mit realem HomePageService.
     *
     * Verhaltens-Test (BEHAVIOR_HANDLE / L3SP-079): ersetzt die ehemalige
     * `class_exists`-Tautologie durch einen vollstaendigen Request-Durchlauf
     * gegen die real verdrahtete Klasse aus dem DI-Container. Smoke-Aspekt
     * (Aufloesbarkeit) ist im 302-Redirect enthalten; zusaetzlich werden
     * zwei spezifische Postconditions geprueft:
     *   1. Redirect-Ziel ist die `UserPage`-Route (`/my-page`) des aktuellen
     *      Trees — d. h. die Routen-Berechnung im Handler hat den Tree-Namen
     *      korrekt eingesetzt.
     *   2. Der dokumentierte Side-Effekt des Handlers (Persistenz der
     *      uebermittelten Block-Konfiguration ueber den HomePageService) hat
     *      stattgefunden — der nicht-numerische Block-Name landet als neue
     *      `block`-Zeile fuer die `user_id` des Test-Admins in der MAIN_BLOCKS-
     *      Location.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/UserPageUpdateTest.php
     * @group ported-l2-doubles
     */
    public function test_user_page_update_handles_request_via_container(): void
    {
        // Arrange: Tree, eingeloggter Admin als Request-User; UserPageUpdate
        // setzt einen angemeldeten Benutzer voraus (Validator::attributes->user()).
        $this->tree = $this->treeService->create('up-upd', 'User Page Update');
        $admin      = $this->createAndLoginAdmin();

        // Saubere Ausgangslage: keine Block-Zeilen fuer diesen Benutzer.
        DB::table('block')->where('user_id', '=', $admin->id())->delete();

        $handler = Registry::container()->get(UserPageUpdate::class);
        $request = $this->createRequest(
            method: 'POST',
            params: [
                // Nicht-numerischer Eintrag → HomePageService::updateUserBlocks
                // legt eine neue `block`-Zeile mit `module_name='user_welcome'` an.
                ModuleBlockInterface::MAIN_BLOCKS => ['user_welcome'],
                ModuleBlockInterface::SIDE_BLOCKS => [],
            ],
            attributes: ['tree' => $this->tree, 'user' => $admin],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 302 FOUND — Handler ist real aufloesbar und leitet nach dem
        // Persistieren weiter.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition 1: Redirect-Ziel ist die UserPage-Route des Trees.
        // Bei nicht-pretty-URLs wird der Slash in `route` URL-kodiert, daher
        // wird gegen die dekodierte Location-Variante geprueft.
        $location = urldecode($response->getHeaderLine('Location'));
        self::assertStringContainsString('/my-page', $location);
        self::assertStringContainsString($this->tree->name(), $location);

        // Postcondition 2: der uebermittelte Block wurde fuer den Benutzer
        // persistiert — neue `block`-Zeile mit MAIN_BLOCKS-Location und
        // `module_name='user_welcome'`.
        $block_row = DB::table('block')
            ->where('user_id', '=', $admin->id())
            ->where('location', '=', ModuleBlockInterface::MAIN_BLOCKS)
            ->where('module_name', '=', 'user_welcome')
            ->first();
        self::assertNotNull($block_row);
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
