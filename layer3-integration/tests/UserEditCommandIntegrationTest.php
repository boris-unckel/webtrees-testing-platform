<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\UserEdit;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: UserEdit CLI-Command.
 *
 * AP B-02: UserEdit::execute (CRAP 552)
 *
 * @see docs/testing-bigpicture.md P35
 * @covers \Fisharebest\Webtrees\Cli\Commands\UserEdit
 */
class UserEditCommandIntegrationTest extends MysqlTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();
        // Testuser von früheren Läufen bereinigen
        $this->cleanupTestUsers();
        $this->tester = new CommandTester(new UserEdit($this->userService));
    }

    protected function tearDown(): void
    {
        $this->cleanupTestUsers();
        parent::tearDown();
    }

    private function cleanupTestUsers(): void
    {
        foreach (['cli-create-test', 'cli-edit-test', 'cli-delete-test', 'cli-randompw-test'] as $userName) {
            $user = $this->userService->findByUserName($userName);
            if ($user !== null) {
                $this->userService->delete($user);
            }
        }
    }

    /**
     * Neuen User anlegen.
     */
    public function test_create_user_exits_successfully(): void
    {
        $exitCode = $this->tester->execute([
            'user-name'    => 'cli-create-test',
            '--create'     => true,
            '--real-name'  => 'CLI Create Test',
            '--email'      => 'cli-create@test.local',
            '--password'   => 'Test1234!',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertNotNull($this->userService->findByUserName('cli-create-test'));
    }

    /**
     * Bestehenden User bearbeiten (real-name ändern).
     */
    public function test_edit_user_real_name(): void
    {
        // Erst anlegen
        $this->tester->execute([
            'user-name'   => 'cli-edit-test',
            '--create'    => true,
            '--real-name' => 'Original Name',
            '--email'     => 'cli-edit@test.local',
            '--password'  => 'Test1234!',
        ]);

        $exitCode = $this->tester->execute([
            'user-name'   => 'cli-edit-test',
            '--real-name' => 'Changed Name',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * Bestehenden User löschen.
     */
    public function test_delete_user_exits_successfully(): void
    {
        // Erst anlegen
        $this->tester->execute([
            'user-name'  => 'cli-delete-test',
            '--create'   => true,
            '--real-name'=> 'Delete Me',
            '--email'    => 'cli-delete@test.local',
            '--password' => 'Test1234!',
        ]);

        $exitCode = $this->tester->execute([
            'user-name' => 'cli-delete-test',
            '--delete'  => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertNull($this->userService->findByUserName('cli-delete-test'));
    }

    // ─── Neue Tests: Validierungs-Branches (B1–B11, B13–B15) ───────────────────

    /**
     * Leerer Username liefert INVALID (B1).
     */
    public function test_empty_username_returns_invalid(): void
    {
        $exitCode = $this->tester->execute(['user-name' => '']);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString('cannot be empty', $this->tester->getDisplay());
    }

    /**
     * --create und --delete gleichzeitig liefern INVALID (B2).
     */
    public function test_create_and_delete_flags_together_returns_invalid(): void
    {
        $exitCode = $this->tester->execute([
            'user-name' => 'cli-create-test',
            '--create'  => true,
            '--delete'  => true,
        ]);

        $this->assertSame(Command::INVALID, $exitCode);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function deleteWithConflictingOptions(): array
    {
        return [
            '--real-name' => ['--real-name', 'Some Name'],
            '--email'     => ['--email', 'conflict@test.local'],
            '--password'  => ['--password', 'Test1234!'],
        ];
    }

    /**
     * --delete zusammen mit --real-name, --email oder --password liefert INVALID (B3/B4/B5).
     */
    #[DataProvider('deleteWithConflictingOptions')]
    public function test_delete_with_conflicting_option_returns_invalid(string $option, string $value): void
    {
        $exitCode = $this->tester->execute([
            'user-name' => 'cli-create-test',
            '--delete'  => true,
            $option     => $value,
        ]);

        $this->assertSame(Command::INVALID, $exitCode);
    }

    /**
     * --create für einen bereits vorhandenen User liefert FAILURE (B6).
     */
    public function test_create_fails_when_user_already_exists(): void
    {
        $this->tester->execute([
            'user-name'   => 'cli-create-test',
            '--create'    => true,
            '--real-name' => 'First',
            '--email'     => 'cli-create@test.local',
            '--password'  => 'Test1234!',
        ]);

        $exitCode = $this->tester->execute([
            'user-name'   => 'cli-create-test',
            '--create'    => true,
            '--real-name' => 'Second',
            '--email'     => 'cli-create2@test.local',
            '--password'  => 'Test1234!',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('already exists', $this->tester->getDisplay());
    }

    /**
     * --create ohne --real-name liefert FAILURE (B7).
     */
    public function test_create_fails_when_real_name_empty(): void
    {
        $exitCode = $this->tester->execute([
            'user-name'   => 'cli-create-test',
            '--create'    => true,
            '--real-name' => '',
            '--email'     => 'cli-create@test.local',
            '--password'  => 'Test1234!',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('--real-name is required', $this->tester->getDisplay());
    }

    /**
     * --create ohne --email liefert FAILURE (B8).
     */
    public function test_create_fails_when_email_empty(): void
    {
        $exitCode = $this->tester->execute([
            'user-name'   => 'cli-create-test',
            '--create'    => true,
            '--real-name' => 'Test User',
            '--email'     => '',
            '--password'  => 'Test1234!',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('--email is required', $this->tester->getDisplay());
    }

    /**
     * --create ohne --password generiert ein zufälliges Passwort und gibt SUCCESS (B9).
     */
    public function test_create_with_empty_password_generates_random_password(): void
    {
        $exitCode = $this->tester->execute([
            'user-name'   => 'cli-randompw-test',
            '--create'    => true,
            '--real-name' => 'Random PW User',
            '--email'     => 'cli-randompw@test.local',
            '--password'  => '',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('random password', $this->tester->getDisplay());
        $this->assertNotNull($this->userService->findByUserName('cli-randompw-test'));
    }

    /**
     * Edit eines nicht vorhandenen Users liefert FAILURE (B10).
     */
    public function test_edit_fails_when_user_not_found(): void
    {
        $exitCode = $this->tester->execute([
            'user-name'   => 'nonexistent-user-xyz',
            '--real-name' => 'New Name',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('does not exist', $this->tester->getDisplay());
    }

    /**
     * Edit ohne Felder liefert INVALID (B11).
     */
    public function test_edit_fails_when_no_fields_provided(): void
    {
        $this->tester->execute([
            'user-name'   => 'cli-edit-test',
            '--create'    => true,
            '--real-name' => 'Edit Base',
            '--email'     => 'cli-edit@test.local',
            '--password'  => 'Test1234!',
        ]);

        $exitCode = $this->tester->execute(['user-name' => 'cli-edit-test']);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString('Nothing to do', $this->tester->getDisplay());
    }

    /**
     * Edit nur mit --email aktualisiert die E-Mail-Adresse (B14).
     */
    public function test_edit_updates_email_only(): void
    {
        $this->tester->execute([
            'user-name'   => 'cli-edit-test',
            '--create'    => true,
            '--real-name' => 'Edit Base',
            '--email'     => 'cli-edit@test.local',
            '--password'  => 'Test1234!',
        ]);

        $exitCode = $this->tester->execute([
            'user-name' => 'cli-edit-test',
            '--email'   => 'new-email@test.local',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('E-mail set to', $this->tester->getDisplay());
    }

    /**
     * Edit nur mit --password aktualisiert das Passwort (B15).
     */
    public function test_edit_updates_password(): void
    {
        $this->tester->execute([
            'user-name'   => 'cli-edit-test',
            '--create'    => true,
            '--real-name' => 'Edit Base',
            '--email'     => 'cli-edit@test.local',
            '--password'  => 'OldPassword1!',
        ]);

        $exitCode = $this->tester->execute([
            'user-name'  => 'cli-edit-test',
            '--password' => 'NewPassword1!',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Password set to', $this->tester->getDisplay());
    }
}
