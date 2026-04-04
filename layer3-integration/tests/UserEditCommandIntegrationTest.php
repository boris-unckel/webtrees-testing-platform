<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\UserEdit;
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
        foreach (['cli-create-test', 'cli-edit-test', 'cli-delete-test'] as $userName) {
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
}
