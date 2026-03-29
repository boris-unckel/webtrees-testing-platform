<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\GuestUser;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tree;

/**
 * Basisklasse fuer Privacy-/Zugriffskontrolle-Integrationstests.
 *
 * Stellt bereit:
 * - GEDCOM-Template-Generierung mit dynamischen Datums-Platzhaltern
 * - Privacy-Tree-Erstellung mit importierter Fixture
 * - Rollen-basierte User-Erstellung
 * - Tree-Preference-Helfer
 *
 */
abstract class PrivacyTestCase extends MysqlTestCase
{
    private static int $userCounter = 0;
    private static string $userSuffix = '';

    /**
     * Generiert GEDCOM aus dem Template mit ersetzten __YEAR_MINUS_N__-Platzhaltern.
     */
    protected function generatePrivacyGedcom(): string
    {
        // Im Container: /fixtures/ (Mount), auf dem Host: relative Position
        $containerPath = '/fixtures/privacy-test-template.ged';
        $relativePath = __DIR__ . '/../../fixtures/privacy-test-template.ged';
        $templatePath = is_file($containerPath) ? $containerPath : $relativePath;
        $template = file_get_contents($templatePath);
        assert($template !== false, "Privacy-Template nicht gefunden: {$templatePath}");

        $currentYear = (int) date('Y');

        $replacements = [];
        preg_match_all('/__YEAR_MINUS_(\d+)__/', $template, $matches);
        foreach ($matches[1] as $offset) {
            $replacements['__YEAR_MINUS_' . $offset . '__'] = (string) ($currentYear - (int) $offset);
        }

        return strtr($template, $replacements);
    }

    /**
     * Erstellt einen Privacy-Test-Baum mit importierter Fixture.
     *
     * Generiert GEDCOM aus dem Template, schreibt eine temporaere Datei,
     * importiert sie und gibt das Tree-Objekt zurueck.
     */
    protected function createPrivacyTree(): Tree
    {
        $gedcom = $this->generatePrivacyGedcom();

        $tmpFile = tempnam(sys_get_temp_dir(), 'privacy_') . '.ged';
        file_put_contents($tmpFile, $gedcom);

        $tree = $this->createTreeWithGedcom('privacy', 'Privacy Test', $tmpFile);

        unlink($tmpFile);

        return $tree;
    }

    /**
     * Erstellt einen User mit der angegebenen Rolle im gegebenen Baum.
     *
     * @param string $role visitor|member|editor|moderator|manager
     */
    protected function createUserWithRole(string $role, Tree $tree): UserInterface
    {
        self::$userCounter++;
        if (self::$userSuffix === '') {
            self::$userSuffix = substr(md5(uniqid('', true)), 0, 8);
        }

        $uniqueId = self::$userCounter . '_' . self::$userSuffix;
        $user = $this->userService->create(
            "test-{$role}-{$uniqueId}",
            'Test ' . ucfirst($role),
            "test-{$role}-{$uniqueId}@test.local",
            getenv('WEBTREES_TEST_USER_PASSWORD') ?: throw new \RuntimeException('WEBTREES_TEST_USER_PASSWORD nicht gesetzt')
        );
        $user->setPreference('verified', '1');
        $user->setPreference('verified_by_admin', '1');

        $treeRole = match ($role) {
            'visitor'   => '',
            'member'    => UserInterface::ROLE_MEMBER,
            'editor'    => UserInterface::ROLE_EDITOR,
            'moderator' => UserInterface::ROLE_MODERATOR,
            'manager'   => UserInterface::ROLE_MANAGER,
            default     => throw new \InvalidArgumentException("Unknown role: {$role}"),
        };

        if ($treeRole !== '') {
            $tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, $treeRole);
        }

        return $user;
    }

    /**
     * Setzt eine Stammbaum-Einstellung.
     */
    protected function setTreePreference(Tree $tree, string $key, string $value): void
    {
        $tree->setPreference($key, $value);
    }

    /**
     * Simuliert den Zugriff als gegebener User (setzt Auth-Kontext).
     *
     * Fuer Besucher-Simulation wird ein GuestUser verwendet und der
     * Auth-Login zurueckgesetzt.
     */
    protected function actAs(UserInterface $user): void
    {
        Auth::login($user);
        // Request-Attribut aktualisieren
        $this->createRequest(attributes: ['user' => $user, 'tree' => $this->tree]);
    }

    /**
     * Simuliert den Zugriff als Besucher (nicht angemeldet).
     */
    protected function actAsVisitor(): void
    {
        Auth::logout();
        $this->createRequest(attributes: ['user' => new GuestUser(), 'tree' => $this->tree]);
    }
}
