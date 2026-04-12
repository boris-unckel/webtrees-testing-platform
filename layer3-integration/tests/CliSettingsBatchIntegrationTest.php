<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Cli\Commands\SiteSetting;
use Fisharebest\Webtrees\Cli\Commands\TreeSetting;
use Fisharebest\Webtrees\Cli\Commands\UserSetting;
use Fisharebest\Webtrees\Cli\Commands\UserTreeSetting;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Komponentenintegrationstest: CLI-Settings-Batch.
 *
 * AP B-05: UserTreeSetting::execute (380), TreeSetting::execute (342),
 *          SiteSetting::execute (306), UserSetting::execute (306)
 *
 * @see docs/tds_conditions_ref.md P36
 * @covers \Fisharebest\Webtrees\Cli\Commands\SiteSetting
 * @covers \Fisharebest\Webtrees\Cli\Commands\TreeSetting
 * @covers \Fisharebest\Webtrees\Cli\Commands\UserSetting
 * @covers \Fisharebest\Webtrees\Cli\Commands\UserTreeSetting
 */
class CliSettingsBatchIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
    }

    private function makeTester(Command $command): CommandTester
    {
        $app = new Application();
        $app->add($command);
        $found = $app->find($command->getName());
        return new CommandTester($found);
    }

    // --- SiteSetting ---

    /**
     * SiteSetting: Wert setzen und lesen (--list).
     */
    public function test_site_setting_set_and_list(): void
    {
        $tester = $this->makeTester(new SiteSetting());

        $exitCode = $tester->execute([
            'setting-name'  => 'INTEGRATION_TEST_KEY',
            'setting-value' => 'test_value_42',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * SiteSetting: --list zeigt vorhandene Settings.
     */
    public function test_site_setting_list(): void
    {
        $tester = $this->makeTester(new SiteSetting());

        $exitCode = $tester->execute(['--list' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * SiteSetting: Wert löschen mit --delete.
     */
    public function test_site_setting_delete(): void
    {
        $tester = $this->makeTester(new SiteSetting());
        // Erst setzen
        $tester->execute(['setting-name' => 'TO_DELETE_KEY', 'setting-value' => 'val']);
        // Dann löschen
        $exitCode = $tester->execute(['setting-name' => 'TO_DELETE_KEY', '--delete' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    // --- TreeSetting ---

    /**
     * TreeSetting: Wert für Demo-Baum setzen.
     */
    public function test_tree_setting_set(): void
    {
        $tester = $this->makeTester(new TreeSetting());

        $exitCode = $tester->execute([
            'tree-name'     => $this->tree->name(),
            'setting-name'  => 'INTEGRATION_TEST_KEY',
            'setting-value' => 'tree_test_value',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * TreeSetting: --list für Demo-Baum.
     */
    public function test_tree_setting_list(): void
    {
        $tester = $this->makeTester(new TreeSetting());

        $exitCode = $tester->execute([
            'tree-name' => $this->tree->name(),
            '--list'    => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    // --- UserSetting ---

    /**
     * UserSetting: Wert für Admin setzen.
     */
    public function test_user_setting_set(): void
    {
        $tester = $this->makeTester(new UserSetting());

        $exitCode = $tester->execute([
            'user-name'     => 'test-admin',
            'setting-name'  => 'INTEGRATION_TEST_KEY',
            'setting-value' => 'user_test_value',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * UserSetting: --list für Admin.
     */
    public function test_user_setting_list(): void
    {
        $tester = $this->makeTester(new UserSetting());

        $exitCode = $tester->execute([
            'user-name' => 'test-admin',
            '--list'    => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    // --- UserTreeSetting ---

    /**
     * UserTreeSetting: Wert für Admin + Demo-Baum setzen.
     */
    public function test_user_tree_setting_set(): void
    {
        $tester = $this->makeTester(new UserTreeSetting());

        $exitCode = $tester->execute([
            'user-name'     => 'test-admin',
            'tree-name'     => $this->tree->name(),
            'setting-name'  => 'INTEGRATION_TEST_KEY',
            'setting-value' => 'user_tree_test_value',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * UserTreeSetting: --list für Admin + Demo-Baum.
     */
    public function test_user_tree_setting_list(): void
    {
        $tester = $this->makeTester(new UserTreeSetting());

        $exitCode = $tester->execute([
            'user-name' => 'test-admin',
            'tree-name' => $this->tree->name(),
            '--list'    => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    // ─── Neue Tests: Validierungs-Branches (B1, B6, B9, B12, B14, EP11) ────────

    /**
     * SiteSetting: --list + --delete zusammen liefert FAILURE (B1/EP2).
     */
    public function test_site_setting_list_conflicts_with_delete(): void
    {
        $tester   = $this->makeTester(new SiteSetting());
        $exitCode = $tester->execute(['--list' => true, '--delete' => true]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Cannot specify --list and --delete', $tester->getDisplay());
    }

    /**
     * SiteSetting: --delete auf nicht vorhandene Einstellung gibt Warning aber SUCCESS (B6/EP4).
     */
    public function test_site_setting_delete_nonexistent_warns_but_succeeds(): void
    {
        $tester   = $this->makeTester(new SiteSetting());
        $exitCode = $tester->execute([
            'setting-name' => 'P36_NONEXISTENT_KEY_XYZ',
            '--delete'     => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    /**
     * SiteSetting: Get auf nicht vorhandene Einstellung meldet "not currently set" (B9/EP7).
     */
    public function test_site_setting_get_nonexistent_reports_not_set(): void
    {
        $tester   = $this->makeTester(new SiteSetting());
        $exitCode = $tester->execute(['setting-name' => 'P36_NEVER_SET_KEY_XYZ']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('not currently set', $tester->getDisplay());
    }

    /**
     * SiteSetting: Gleichen Wert erneut setzen gibt Warning "already set" (B12/EP9).
     */
    public function test_site_setting_set_same_value_warns_already_set(): void
    {
        $tester = $this->makeTester(new SiteSetting());
        $tester->execute(['setting-name' => 'P36_SAME_VAL_KEY', 'setting-value' => 'same_value']);

        $exitCode = $tester->execute(['setting-name' => 'P36_SAME_VAL_KEY', 'setting-value' => 'same_value']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('already set', $tester->getDisplay());
    }

    /**
     * SiteSetting: Vorhandenen Wert mit neuem Wert aktualisieren gibt SUCCESS (B14/EP10).
     */
    public function test_site_setting_update_existing_value(): void
    {
        $tester = $this->makeTester(new SiteSetting());
        $tester->execute(['setting-name' => 'P36_UPDATE_KEY', 'setting-value' => 'first_value']);

        $exitCode = $tester->execute(['setting-name' => 'P36_UPDATE_KEY', 'setting-value' => 'second_value']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('changed from', $tester->getDisplay());
    }

    /**
     * TreeSetting: Nicht vorhandener Baum liefert FAILURE (EP11).
     */
    public function test_tree_setting_fails_when_tree_not_found(): void
    {
        $tester   = $this->makeTester(new TreeSetting());
        $exitCode = $tester->execute([
            'tree-name'    => 'nonexistent-tree-xyz',
            'setting-name' => 'SOME_KEY',
            '--list'       => true,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    /**
     * UserSetting: Nicht vorhandener User liefert FAILURE (EP11).
     */
    public function test_user_setting_fails_when_user_not_found(): void
    {
        $tester   = $this->makeTester(new UserSetting());
        $exitCode = $tester->execute([
            'user-name'    => 'nonexistent-user-xyz',
            'setting-name' => 'SOME_KEY',
            '--list'       => true,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    /**
     * UserTreeSetting: Nicht vorhandener Baum liefert FAILURE (EP11).
     */
    public function test_user_tree_setting_fails_when_tree_not_found(): void
    {
        $tester   = $this->makeTester(new UserTreeSetting());
        $exitCode = $tester->execute([
            'user-name'    => 'test-admin',
            'tree-name'    => 'nonexistent-tree-xyz',
            'setting-name' => 'SOME_KEY',
            '--list'       => true,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }
}
