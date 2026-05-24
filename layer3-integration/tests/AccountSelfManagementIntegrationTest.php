<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
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
 * @see docs/tds_conditions_ref.md P38
 * @see docs/testquality_improve_P38.md
 */
class AccountSelfManagementIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function tearDown(): void
    {
        // Nur deterministische User-Namen (Bestand-Konvention).
        // uniqid()-basierte Namen sind pro Lauf neu und ohne Kollisionsrisiko.
        $cleanup = [
            'testuser_p38',
            'accedit_notree',
            'accedit_user',
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

    /**
     * AccountDelete mit GuestUser im Request → 302, kein delete() auf UserService.
     *
     * Der Default-Request enthält einen GuestUser, der nicht instanceof User ist.
     * Der Handler darf in diesem Fall weder löschen noch ausloggen, sondern nur
     * auf AccountEdit umleiten.
     *
     * @group ported-l2-doubles
     */
    public function test_account_delete_with_guest_user_redirects_without_delete(): void
    {
        // Arrange: UserService als Mock, der delete() nie sehen darf
        $user_service = $this->createMock(\Fisharebest\Webtrees\Services\UserService::class);
        $user_service->expects(self::never())->method('delete');

        $handler = new AccountDelete($user_service);

        // Default-Request → user-Attribut ist GuestUser
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect, ohne dass am UserService gelöscht wurde
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * AccountEdit GET ohne Tree-Attribut im Request → 200.
     *
     * Der Validator behandelt das tree-Attribut als optional (treeOptional()).
     * Ein angemeldeter Non-Admin-Benutzer ohne aktiv ausgewählten Stammbaum
     * darf seine eigene Account-Seite weiterhin öffnen.
     *
     * @group ported-l2-doubles
     */
    public function test_account_edit_page_handles_request_without_tree_attribute(): void
    {
        // Arrange: echter Non-Admin-Benutzer, eingeloggt, Request ohne tree-Attribut
        $this->createAndLoginAdmin();
        $user = $this->userService->create(
            'accedit_notree',
            'Acc Edit NoTree',
            'accedit_notree@example.test',
            'password1',
        );
        Auth::login($user);

        $handler = new AccountEdit(
            Registry::container()->get(MessageService::class),
            new ModuleService(),
        );

        $request = $this->createRequest(
            attributes: ['user' => $user],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AccountEdit GET als echt erzeugter Non-Admin-Benutzer → 200.
     *
     * Sichert ab, dass die Handler-Logik nicht von Administrator-Privilegien
     * abhängt: ein normaler Benutzer kann seine eigene Account-Seite öffnen.
     *
     * @group ported-l2-doubles
     */
    public function test_account_edit_page_returns_200_for_non_admin_user(): void
    {
        // Arrange: Admin muss existieren (DB-Konsistenz), Non-Admin wird eingeloggt
        $this->createAndLoginAdmin();
        $user = $this->userService->create(
            'accedit_user',
            'Acc Edit User',
            'accedit_user@example.test',
            'password1',
        );
        Auth::login($user);

        $handler = new AccountEdit(
            Registry::container()->get(MessageService::class),
            new ModuleService(),
        );

        $request = $this->createRequest(
            attributes: ['user' => $user],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AccountUpdate POST mit leerem Passwort-Feld → 302, Passwort unverändert.
     *
     * Der Handler überspringt setPassword(), wenn das Formularfeld "password"
     * leer ist. Der zuvor gesetzte Passwort-Hash muss erhalten bleiben.
     *
     * @group ported-l2-doubles
     */
    public function test_account_update_with_empty_password_keeps_old_password(): void
    {
        // Arrange: realer Benutzer mit bekanntem Passwort
        $this->createAndLoginAdmin();
        $username     = 'accupd_pw_' . uniqid();
        $old_password = 'OriginalPassword!42';
        $user         = $this->userService->create(
            $username,
            'Acc Update PW',
            $username . '@example.test',
            $old_password,
        );
        Auth::login($user);

        $handler = new AccountUpdate($this->userService);

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['user' => $user],
            params: [
                'contact-method' => 'none',
                'email'          => $user->email(),
                'language'       => 'en-GB',
                'real_name'      => 'Acc Update PW',
                'password'       => '',
                'timezone'       => 'UTC',
                'user_name'      => $username,
                'visible-online' => '0',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect und altes Passwort prüft weiterhin grün
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $refreshed = $this->userService->findByIdentifier($username);
        self::assertNotNull($refreshed);
        self::assertTrue($refreshed->checkPassword($old_password));
    }

    /**
     * AccountUpdate POST mit bereits vergebener E-Mail → 302, eigene E-Mail unverändert.
     *
     * Der Handler erkennt Duplikate über UserService::findByEmail und unterdrückt
     * dann setEmail(); das Routing endet trotzdem in 302 (FlashMessage gesetzt).
     *
     * @group ported-l2-doubles
     */
    public function test_account_update_with_duplicate_email_does_not_change_email(): void
    {
        // Arrange: zwei reale Benutzer, der zweite besitzt die "begehrte" E-Mail
        $this->createAndLoginAdmin();
        $suffix      = uniqid();
        $own_name    = 'accupd_dup_email_' . $suffix;
        $own_email   = $own_name . '@example.test';
        $taken_email = 'taken_' . $suffix . '@example.test';

        $user = $this->userService->create($own_name, 'Acc Update Email', $own_email, 'pass1234');
        $this->userService->create('other_' . $suffix, 'Other User', $taken_email, 'pass5678');
        Auth::login($user);

        $handler = new AccountUpdate($this->userService);

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['user' => $user],
            params: [
                'contact-method' => 'none',
                'email'          => $taken_email,
                'language'       => 'en-GB',
                'real_name'      => 'Acc Update Email',
                'password'       => '',
                'timezone'       => 'UTC',
                'user_name'      => $own_name,
                'visible-online' => '0',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect und eigene E-Mail unverändert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $refreshed = $this->userService->findByIdentifier($own_name);
        self::assertNotNull($refreshed);
        self::assertSame($own_email, $refreshed->email());
    }

    /**
     * AccountUpdate POST mit bereits vergebenem Benutzernamen → 302, eigener Name unverändert.
     *
     * Der Handler erkennt Namens-Duplikate über UserService::findByUserName und
     * unterdrückt dann setUserName(); das Routing endet trotzdem in 302.
     *
     * @group ported-l2-doubles
     */
    public function test_account_update_with_duplicate_username_does_not_change_name(): void
    {
        // Arrange: zwei reale Benutzer, der zweite besitzt den "begehrten" Namen
        $this->createAndLoginAdmin();
        $suffix     = uniqid();
        $own_name   = 'accupd_dup_user_' . $suffix;
        $taken_name = 'taken_user_' . $suffix;
        $own_email  = $own_name . '@example.test';

        $user = $this->userService->create($own_name, 'Acc Update User', $own_email, 'pass1234');
        $this->userService->create($taken_name, 'Taken User', 'taken_user_' . $suffix . '@example.test', 'pass5678');
        Auth::login($user);

        $handler = new AccountUpdate($this->userService);

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['user' => $user],
            params: [
                'contact-method' => 'none',
                'email'          => $own_email,
                'language'       => 'en-GB',
                'real_name'      => 'Acc Update User',
                'password'       => '',
                'timezone'       => 'UTC',
                'user_name'      => $taken_name,
                'visible-online' => '0',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect und eigener Benutzername unverändert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $refreshed = $this->userService->findByIdentifier($own_name);
        self::assertNotNull($refreshed);
        self::assertSame($own_name, $refreshed->userName());
    }

    /**
     * AccountUpdate POST mit tree-Attribut → 302, default-xref als PREF_TREE_DEFAULT_XREF gespeichert.
     *
     * Der Handler ruft Tree::setUserPreference(PREF_TREE_DEFAULT_XREF, …) nur dann auf,
     * wenn das Request einen echten Tree mitführt. Wert landet in user_gedcom_setting.
     *
     * @group ported-l2-doubles
     */
    public function test_account_update_with_tree_sets_default_xref(): void
    {
        // Arrange: realer Tree + realer Benutzer, eingeloggt
        $tree = $this->createTreeWithGedcom('accupd-tree', 'Acc Update Tree', self::DEMO_GED);
        $suffix   = uniqid();
        $username = 'accupd_tree_' . $suffix;
        $user     = $this->userService->create(
            $username,
            'Acc Update Tree',
            $username . '@example.test',
            'pass1234',
        );
        Auth::login($user);

        $handler = new AccountUpdate($this->userService);

        $default_xref = 'I123';

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree' => $tree,
                'user' => $user,
            ],
            params: [
                'contact-method' => 'none',
                'email'          => $user->email(),
                'language'       => 'en-GB',
                'real_name'      => 'Acc Update Tree',
                'password'       => '',
                'default-xref'   => $default_xref,
                'timezone'       => 'UTC',
                'user_name'      => $username,
                'visible-online' => '0',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect und Tree-User-Preference persistiert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $saved_xref = DB::table('user_gedcom_setting')
            ->where('gedcom_id', '=', $tree->id())
            ->where('user_id', '=', $user->id())
            ->where('setting_name', '=', UserInterface::PREF_TREE_DEFAULT_XREF)
            ->value('setting_value');

        self::assertSame($default_xref, $saved_xref);
    }

    /**
     * AccountUpdate POST ohne tree-Attribut → 302, default-xref nicht gesetzt.
     *
     * Der Handler überspringt Tree::setUserPreference(PREF_TREE_DEFAULT_XREF, …),
     * wenn der Request kein Tree-Attribut mitführt; user_gedcom_setting bleibt leer.
     *
     * @group ported-l2-doubles
     */
    public function test_account_update_without_tree_skips_default_xref(): void
    {
        // Arrange: realer Benutzer, eingeloggt, KEIN tree-Attribut
        $this->createAndLoginAdmin();
        $suffix   = uniqid();
        $username = 'accupd_notree_' . $suffix;
        $user     = $this->userService->create(
            $username,
            'Acc Update NoTree',
            $username . '@example.test',
            'pass1234',
        );
        Auth::login($user);

        $handler = new AccountUpdate($this->userService);

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['user' => $user],
            params: [
                'contact-method' => 'none',
                'email'          => $user->email(),
                'language'       => 'en-GB',
                'real_name'      => 'Acc Update NoTree',
                'password'       => '',
                'default-xref'   => 'I999',
                'timezone'       => 'UTC',
                'user_name'      => $username,
                'visible-online' => '0',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Redirect, aber kein user_gedcom_setting für diesen User
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $exists = DB::table('user_gedcom_setting')
            ->where('user_id', '=', $user->id())
            ->where('setting_name', '=', UserInterface::PREF_TREE_DEFAULT_XREF)
            ->exists();

        self::assertFalse($exists);
    }
}
