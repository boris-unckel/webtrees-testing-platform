<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\AccountDelete;
use Fisharebest\Webtrees\Http\RequestHandlers\AccountEdit;
use Fisharebest\Webtrees\Http\RequestHandlers\AccountUpdate;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MessageService;
use Fisharebest\Webtrees\Services\ModuleService;

/**
 * Komponentenintegrationstest: Account-Selbstverwaltung — P38.
 *
 * Tests:
 * - AccountEdit GET → 200
 * - AccountUpdate POST → 302, E-Mail aktualisiert
 * - AccountDelete Admin → 302, Benutzer noch in DB
 * - AccountDelete Non-Admin → 302, Benutzer gelöscht
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AccountEdit
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AccountUpdate
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AccountDelete
 * @see docs/testquality_improve_P38.md
 */
class AccountSelfManagementIntegrationTest extends MysqlTestCase
{
    /**
     * EP1: AccountEdit GET als Admin → 200.
     */
    public function test_account_edit_page_returns_200(): void
    {
        $admin = $this->createAndLoginAdmin();

        $handler = new AccountEdit(
            Registry::container()->get(MessageService::class),
            new ModuleService(),
        );

        $request = $this->createRequest(
            attributes: ['user' => $admin],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP8: AccountUpdate POST — Happy Path → 302, E-Mail in DB geändert.
     */
    public function test_account_update_redirects_and_updates_email(): void
    {
        $admin   = $this->createAndLoginAdmin();
        $handler = new AccountUpdate($this->userService);

        $new_email = 'updated-' . uniqid() . '@example.test';

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['user' => $admin],
            params: [
                'contact-method' => 'none',
                'email'          => $new_email,
                'language'       => 'en-GB',
                'real_name'      => 'Admin User',
                'password'       => '',
                'timezone'       => 'UTC',
                'user_name'      => $admin->userName(),
                'visible-online' => '0',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: E-Mail gespeichert
        $saved = DB::table('user')
            ->where('user_id', '=', $admin->id())
            ->value('email');

        self::assertSame($new_email, $saved);
    }

    /**
     * EP9: AccountDelete Admin versucht Selbstlöschung → 302, Admin noch in DB.
     * PREF_IS_ADMINISTRATOR = '1' → delete() wird nicht aufgerufen.
     */
    public function test_account_delete_admin_does_not_delete_self(): void
    {
        $admin   = $this->createAndLoginAdmin();
        $handler = new AccountDelete($this->userService);

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['user' => $admin],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Admin noch in DB
        $exists = DB::table('user')->where('user_id', '=', $admin->id())->exists();
        self::assertTrue($exists);
    }

    /**
     * EP10: AccountDelete Non-Admin → 302, Benutzer aus DB entfernt.
     */
    public function test_account_delete_non_admin_deletes_user(): void
    {
        // Admin muss vorhanden sein (für DB-Konsistenz)
        $this->createAndLoginAdmin();

        // Nicht-Admin anlegen und einloggen
        $non_admin = $this->userService->create('testuser_p38', 'Test User P38', 'testuser_p38@example.test', 'password');
        Auth::login($non_admin);

        $handler = new AccountDelete($this->userService);

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['user' => $non_admin],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // User nicht mehr in DB
        $exists = DB::table('user')->where('user_id', '=', $non_admin->id())->exists();
        self::assertFalse($exists);

        // Session geleert
        self::assertNull(Auth::id());
    }
}
