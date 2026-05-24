<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\UserPageDefaultEdit;
use Fisharebest\Webtrees\Registry;
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
 * @see docs/tds_conditions_ref.md A18
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserPageDefaultEdit
 */
class UserPageDefaultEditIntegrationTest extends MysqlTestCase
{
    /**
     * UserPageDefaultEdit: Container-Resolution + handle() → 200 mit realen Services.
     *
     * Verhaltens-Test (BEHAVIOR_HANDLE): ersetzt die ehemalige `class_exists`-Tautologie
     * durch einen vollstaendigen Request-Durchlauf gegen die real verdrahtete Klasse
     * aus dem DI-Container. Smoke-Aspekt (Aufloesbarkeit) ist im 200-OK enthalten,
     * zusaetzlich wird die dokumentierte Side-Effect-Postcondition geprueft: nach
     * Aufruf von checkDefaultUserBlocksExist() existieren in der block-Tabelle
     * Default-Block-Zeilen mit user_id = -1.
     *
     * @group ported-l2-doubles
     */
    public function test_user_page_default_edit_handles_request_via_container(): void
    {
        // Arrange: vor dem Lauf sicherstellen, dass keine Defaults existieren —
        // der Handler soll die Default-Block-Zeilen idempotent anlegen.
        DB::table('block')->where('user_id', '=', -1)->delete();

        $handler = Registry::container()->get(UserPageDefaultEdit::class);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert: 200 OK — Handler ist real aufloesbar und liefert die Edit-Blocks-Page aus.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Postcondition: HomePageService::checkDefaultUserBlocksExist hat
        // Default-Block-Zeilen fuer den virtuellen Default-User (-1) angelegt.
        self::assertTrue(
            DB::table('block')->where('user_id', '=', -1)->exists(),
            'Default-Block-Zeilen fuer user_id = -1 wurden vom Handler nicht angelegt.',
        );
    }

    /**
     * UserPageDefaultEdit::handle liefert HTTP 200 und delegiert an den
     * HomePageService fuer Default-Block-Pruefung, User-Bloecke und die
     * verfuegbaren User-Bloecke.
     *
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
