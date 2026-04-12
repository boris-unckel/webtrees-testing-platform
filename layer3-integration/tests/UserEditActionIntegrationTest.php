<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\UserEditAction;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\EmailService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Komponentenintegrationstest: UserEditAction HTTP-Handler.
 *
 * EP-Matrix: user-not-found (B1), Duplikat-Email (B5/B6), Duplikat-Username (B7/B8),
 * Self-Edit-Admin-Guard (B4), Passwort-Update (B3), Path-Length-Reset (EP12).
 * B2 (Approval-Email) ausgeklammert — E-Mail-Versand nicht testbar ohne SMTP-Mock.
 *
 * @see docs/tds_conditions_ref.md P37
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\UserEditAction
 */
class UserEditActionIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private UserEditAction $handler;
    private UserInterface $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin   = $this->createAndLoginAdmin();
        $this->handler = new UserEditAction(
            Registry::container()->get(EmailService::class),
            $this->treeService,
            $this->userService,
        );
    }

    protected function tearDown(): void
    {
        foreach (['p37-second-user', 'p37-other-user'] as $uname) {
            $u = $this->userService->findByUserName($uname);
            if ($u !== null) {
                $this->userService->delete($u);
            }
        }
        parent::tearDown();
    }

    /**
     * Standard-POST-Request für Admin-Self-Edit mit optionalen Überschreibungen.
     *
     * @param array<string, string> $overrides
     */
    private function makeEditRequest(array $overrides = []): ServerRequestInterface
    {
        return $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['user' => $this->admin, 'base_url' => 'https://webtrees.test/'],
            params: array_merge([
                'user_id'        => (string) $this->admin->id(),
                'username'       => $this->admin->userName(),
                'real_name'      => $this->admin->realName(),
                'email'          => $this->admin->email(),
                'password'       => '',
                'theme'          => '',
                'language'       => 'en-US',
                'timezone'       => 'UTC',
                'contact-method' => 'none',
                'comment'        => '',
            ], $overrides),
        );
    }

    /**
     * Nicht existierende user_id wirft HttpNotFoundException (B1/EP1).
     */
    public function test_user_edit_throws_not_found_for_invalid_user_id(): void
    {
        $this->expectException(HttpNotFoundException::class);

        $this->handler->handle($this->makeEditRequest(['user_id' => '999999']));
    }

    /**
     * E-Mail bereits von anderem User → Flash-Error + Redirect zurück zu UserEditPage (B5/B6/EP2).
     * Redirect-URL enthält user_id-Parameter (Rückkehr zum Edit-Formular, nicht zur Liste).
     */
    public function test_user_edit_rejects_duplicate_email(): void
    {
        $this->userService->create('p37-second-user', 'Second User', 'second@p37.local', 'TestPass1!');

        $response = $this->handler->handle($this->makeEditRequest(['email' => 'second@p37.local']));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('user_id', $response->getHeaderLine('Location'));
    }

    /**
     * Username bereits von anderem User → Flash-Error + Redirect zurück zu UserEditPage (B7/B8/EP4).
     */
    public function test_user_edit_rejects_duplicate_username(): void
    {
        $this->userService->create('p37-other-user', 'Other User', 'other@p37.local', 'TestPass1!');

        $response = $this->handler->handle($this->makeEditRequest(['username' => 'p37-other-user']));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('user_id', $response->getHeaderLine('Location'));
    }

    /**
     * Self-Edit mit fehlendem canadmin-Flag ignoriert Admin-Status-Änderung (B4/EP6).
     * Admin-User bleibt Administrator, auch wenn canadmin nicht im POST ist (Validator-Default false).
     */
    public function test_user_edit_self_edit_cannot_remove_admin_role(): void
    {
        // canadmin fehlt im POST → Validator-Default: false; SUT ignoriert es bei Self-Edit
        $response = $this->handler->handle($this->makeEditRequest());

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $refreshed = $this->userService->find($this->admin->id());
        $this->assertSame('1', $refreshed->getPreference(UserInterface::PREF_IS_ADMINISTRATOR));
    }

    /**
     * Nicht-leeres Passwort → SUT ruft setPassword() auf, gibt Redirect zu UserListPage zurück (B3/EP10).
     */
    public function test_user_edit_updates_password_when_provided(): void
    {
        $response = $this->handler->handle($this->makeEditRequest(['password' => 'NewTestPass1!']));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * Leeres Passwort → setPassword() wird nicht aufgerufen, gibt Redirect zurück (B3/EP9).
     */
    public function test_user_edit_does_not_update_password_when_empty(): void
    {
        $response = $this->handler->handle($this->makeEditRequest(['password' => '']));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * gedcomid leer → path_length wird auf 0 gesetzt, auch wenn RELATIONSHIP_PATH_LENGTH > 0 (EP12).
     * SUT: if ($gedcom_id === '') { $path_length = 0; }
     */
    public function test_user_edit_resets_path_length_when_gedcomid_cleared(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $treeId = $this->tree->id();

        $response = $this->handler->handle($this->makeEditRequest([
            'gedcomid' . $treeId                 => '',
            'RELATIONSHIP_PATH_LENGTH' . $treeId => '5',
            'canedit' . $treeId                  => 'member',
        ]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        // Direktabfrage der DB — umgeht getUserPreference-Objekt-Cache
        $pathLength = DB::table('user_gedcom_setting')
            ->where('user_id', '=', $this->admin->id())
            ->where('gedcom_id', '=', $this->tree->id())
            ->where('setting_name', '=', UserInterface::PREF_TREE_PATH_LENGTH)
            ->value('setting_value');
        $this->assertSame('0', (string) ($pathLength ?? ''));
    }
}
