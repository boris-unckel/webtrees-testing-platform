<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GuestUser;
use Fisharebest\Webtrees\Http\Routes\WebRoutes;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use Fisharebest\Webtrees\Module\WebtreesTheme;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Webtrees;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Basis-Klasse für Komponentenintegrationstests mit echter MySQL-Datenbank.
 *
 * Folgt dem Muster der webtrees-eigenen TestCase, nutzt aber MySQL statt SQLite.
 *
 * @see docs/tds_conditions_ref.md N4 (Phase 4)
 */
abstract class MysqlTestCase extends TestCase
{
    protected static bool $schemaInitialized = false;

    protected TreeService $treeService;
    protected UserService $userService;
    protected GedcomImportService $gedcomImportService;
    protected ?Tree $tree = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Boot webtrees (analog zu webtrees TestCase::setUp)
        $webtrees = new Webtrees();
        $webtrees->bootstrap();

        // Theme setzen (normalerweise in Middleware)
        Registry::container()->set(ModuleThemeInterface::class, new WebtreesTheme());

        // Routing-Tabelle (für URL-Generierung)
        $router_container = new RouterContainer('/');
        (new WebRoutes())->load($router_container->getMap());
        Registry::container()->set(RouterContainer::class, $router_container);

        I18N::init('en-US', true);

        $this->connectToMysql();
        $this->initializeSchema();

        // GEDCOM-Tags registrieren
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        // Module booten
        (new ModuleService())->bootModules(new WebtreesTheme());

        I18N::init('en-US');

        $this->gedcomImportService = new GedcomImportService();
        $this->treeService = new TreeService($this->gedcomImportService);
        $this->userService = new UserService();

        // Default-Request registrieren (wie webtrees TestCase)
        $this->createRequest();
    }

    protected function tearDown(): void
    {
        // Baum aufräumen
        if ($this->tree !== null) {
            $this->treeService->delete($this->tree);
            $this->tree = null;
        }

        DB::connection()->disconnect();

        Session::clear();
        Site::$preferences = [];

        parent::tearDown();
    }

    private function connectToMysql(): void
    {
        $host     = getenv('MYSQL_HOST') ?: 'mysql';
        $port     = getenv('MYSQL_PORT') ?: '3306';
        $database = getenv('MYSQL_DATABASE') ?: 'webtrees_test';
        $username = getenv('MYSQL_USER') ?: 'webtrees';
        $password = getenv('MYSQL_PASSWORD') ?: throw new \RuntimeException('MYSQL_PASSWORD nicht gesetzt');

        DB::connect(
            driver:             DB::MYSQL,
            host:               $host,
            port:               $port,
            database:           $database,
            username:           $username,
            password:           $password,
            prefix:             'wt_',
            key:                '',
            certificate:        '',
            ca:                 '',
            verify_certificate: false,
        );
    }

    private function initializeSchema(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        $migration = new MigrationService();
        $migration->updateSchema('\Fisharebest\Webtrees\Schema', 'WT_SCHEMA_VERSION', Webtrees::SCHEMA_VERSION);
        $migration->seedDatabase();

        self::$schemaInitialized = true;
    }

    /**
     * Erstellt einen Testbaum und importiert eine GEDCOM-Datei.
     * Folgt dem Muster von webtrees TestCase::importTree().
     * Verwendet einen eindeutigen Baumnamen basierend auf dem Testnamen.
     */
    protected function createTreeWithGedcom(string $name, string $title, string $gedcomPath): Tree
    {
        // Admin-User anlegen und einloggen (für TreeService::create)
        $this->createAndLoginAdmin();

        // Eindeutiger Baumname (verhindert Kollisionen mit Setup-Bäumen und parallelen Tests)
        $uniqueName = $name . '-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, $title);

        // Default-Records löschen (wie TestCase::importTree)
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();
        DB::table('families')->where('f_file', '=', $this->tree->id())->delete();
        DB::table('sources')->where('s_file', '=', $this->tree->id())->delete();
        DB::table('other')->where('o_file', '=', $this->tree->id())->delete();
        DB::table('places')->where('p_file', '=', $this->tree->id())->delete();
        DB::table('placelinks')->where('pl_file', '=', $this->tree->id())->delete();
        DB::table('name')->where('n_file', '=', $this->tree->id())->delete();
        DB::table('dates')->where('d_file', '=', $this->tree->id())->delete();
        DB::table('change')->where('gedcom_id', '=', $this->tree->id())->delete();
        DB::table('link')->where('l_file', '=', $this->tree->id())->delete();
        DB::table('media_file')->where('m_file', '=', $this->tree->id())->delete();
        DB::table('media')->where('m_file', '=', $this->tree->id())->delete();

        // GEDCOM-Datei Record für Record importieren
        $gedcom = file_get_contents($gedcomPath);
        assert($gedcom !== false, "GEDCOM-Datei nicht gefunden: {$gedcomPath}");

        // BOM entfernen und Zeilenenden normalisieren
        $gedcom = str_replace("\xEF\xBB\xBF", '', $gedcom);
        $gedcom = str_replace("\r\n", "\n", $gedcom);
        $records = preg_split('/\n(?=0 )/', $gedcom);
        foreach ($records as $record) {
            $this->gedcomImportService->importRecord($record, $this->tree, false);
        }

        return $this->tree;
    }

    /**
     * Erstellt einen Admin-User und loggt ihn ein.
     *
     * @return UserInterface Der eingeloggte Admin-User (für Request-Attribute).
     */
    protected function createAndLoginAdmin(): UserInterface
    {
        $admin = $this->userService->findByUserName('test-admin');
        if ($admin === null) {
            $testPassword = getenv('WEBTREES_TEST_USER_PASSWORD') ?: throw new \RuntimeException('WEBTREES_TEST_USER_PASSWORD nicht gesetzt');
            $admin = $this->userService->create('test-admin', 'Test Admin', 'admin@test.local', $testPassword);
        }

        // Preferences immer setzen (nicht nur beim Erstellen), damit auch bei
        // vorhandenem User aus früheren Testläufen der Admin-Status sicher gesetzt ist.
        $admin->setPreference(UserInterface::PREF_IS_ADMINISTRATOR, '1');
        $admin->setPreference('verified', '1');
        $admin->setPreference('verified_by_admin', '1');

        Auth::login($admin);

        return $admin;
    }

    /**
     * Erstellt einen PSR-7 ServerRequest — analog zu webtrees TestCase::createRequest().
     *
     * @param array<string|array<string>>  $query
     * @param array<string>                $params
     * @param array<string|Tree>           $attributes
     */
    protected function createRequest(
        string $method = RequestMethodInterface::METHOD_GET,
        array $query = [],
        array $params = [],
        array $attributes = [],
    ): ServerRequestInterface {
        $server_request_factory = Registry::container()->get(ServerRequestFactoryInterface::class);
        self::assertInstanceOf(ServerRequestFactoryInterface::class, $server_request_factory);

        $uri = 'https://webtrees.test/index.php?' . http_build_query($query);

        $route = new Route();
        $route->name('dummy');

        $request = $server_request_factory
            ->createServerRequest($method, $uri)
            ->withQueryParams($query)
            ->withParsedBody($params)
            ->withAttribute('base_url', 'https://webtrees.test')
            ->withAttribute('client-ip', '127.0.0.1')
            ->withAttribute('user', new GuestUser())
            ->withAttribute('route', $route);

        foreach ($attributes as $key => $value) {
            $request = $request->withAttribute($key, $value);

            if ($key === 'tree' && $value instanceof Tree) {
                Registry::container()->set(Tree::class, $value);
            }
        }

        Registry::container()->set(ServerRequestInterface::class, $request);

        return $request;
    }
}
