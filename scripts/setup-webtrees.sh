#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# setup-webtrees.sh — Automatischer webtrees-Installer für den Test-Container
# Umgeht den Setup-Wizard vollständig (programmatischer Install)

set -euo pipefail

WEBTREES_DIR="/var/www/html"
DATA_DIR="${WEBTREES_DIR}/data"
FIXTURES_DIR="/fixtures"

MYSQL_HOST="${MYSQL_HOST:-mysql}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_DATABASE="${MYSQL_DATABASE:-webtrees_test}"
MYSQL_USER="${MYSQL_USER:-webtrees}"

# Pflicht-Variablen pruefen (Passwoerter ohne Fallback)
for _var in MYSQL_PASSWORD WEBTREES_ADMIN_PASSWORD WEBTREES_TEST_USER_PASSWORD; do
    if [[ -z "${!_var:-}" ]]; then
        echo "FEHLER: ${_var} nicht gesetzt — wurde 'make up' ausgefuehrt?" >&2
        exit 1
    fi
done

echo "=== webtrees Test-Setup ==="

# 0a. Apache-Rewrite-Regel ergaenzen (FallbackResource greift nicht fuer .php-URLs,
# da mod_php diese vor FallbackResource abfaengt und 404 liefert).
# Die RewriteRule leitet nicht-existierende .php-URLs an index.php weiter,
# was fuer die Legacy-URL-Redirects (S53: individual.php, family.php etc.) noetig ist.
VHOST_CONF="/etc/apache2/sites-enabled/000-default.conf"
if ! grep -q 'RewriteEngine On' "${VHOST_CONF}" 2>/dev/null; then
    echo "[0a] Apache-Rewrite-Regel fuer Legacy-URLs ergaenzen..."
    cat > "${VHOST_CONF}" << 'VHOSTEOF'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
        FallbackResource /index.php
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ /index.php [L]
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
VHOSTEOF
    apachectl graceful 2>/dev/null || true
    echo "  Rewrite-Regel aktiv."
fi

# 0. tests/data-Volume seeden (Fixtures für Upstream-Unit-Tests)
# Das Volume /var/www/html/tests/data überschreibt den ro-Bind-Mount. Die
# Originaldaten werden einmalig aus /webtrees-tests-data-seed kopiert.
TESTS_DATA_DIR="${WEBTREES_DIR}/tests/data"
TESTS_DATA_SEED="/webtrees-tests-data-seed"
if [ -d "${TESTS_DATA_SEED}" ] && [ ! -f "${TESTS_DATA_DIR}/.seeded" ]; then
    echo "[0/4] tests/data-Volume mit Fixtures seeden..."
    cp -a "${TESTS_DATA_SEED}/." "${TESTS_DATA_DIR}/"
    touch "${TESTS_DATA_DIR}/.seeded"
    echo "  Seed abgeschlossen."
fi
# Temporäre Dateien aus früheren Testläufen bereinigen
rm -f "${TESTS_DATA_DIR}/offline.txt" "${TESTS_DATA_DIR}/foo"

# 0c. resources/lang-Volume seeden (analog tests/data). webtrees kompiliert
# .po zur Laufzeit nach .php und cached das Ergebnis in
# resources/lang/<locale>/messages.php (Build-Artefakt, in upstream-.gitignore).
# Ohne RW-Cache schlaegt file_put_contents am :ro-Mount fehl → 500 sobald
# UseLanguage-Middleware einen unbekannten locale instanziiert.
LANG_DIR="${WEBTREES_DIR}/resources/lang"
LANG_SEED="/webtrees-lang-seed"
if [ -d "${LANG_SEED}" ] && [ ! -f "${LANG_DIR}/.seeded" ]; then
    echo "[0b/4] resources/lang-Volume seeden..."
    cp -a "${LANG_SEED}/." "${LANG_DIR}/"
    chown -R www-data:www-data "${LANG_DIR}"
    touch "${LANG_DIR}/.seeded"
    echo "  Seed abgeschlossen."
fi

# 1. Composer install
echo "[1/4] composer install..."
cd "${WEBTREES_DIR}"
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress --no-ansi --prefer-dist 2>&1
else
    echo "  vendor/autoload.php existiert bereits, übersprungen"
fi

# 1b. OTel Auto-Instrumentation (bedingt)
# composer.json liegt auf dem read-only Bind-Mount — Kopie in /tmp für Modifikation.
# COMPOSER_VENDOR_DIR zeigt auf das beschreibbare vendor-Volume.
if [ "${OTEL_SDK_DISABLED:-false}" != "true" ]; then
    echo "[1b/4] OTel Auto-Instrumentation installieren..."
    cp "${WEBTREES_DIR}/composer.json" /tmp/composer.json
    cp "${WEBTREES_DIR}/composer.lock" /tmp/composer.lock
    COMPOSER=/tmp/composer.json \
    COMPOSER_VENDOR_DIR="${WEBTREES_DIR}/vendor" \
    composer require --dev --no-interaction --no-progress --no-ansi \
      open-telemetry/sdk \
      open-telemetry/exporter-otlp \
      open-telemetry/opentelemetry-auto-pdo \
      open-telemetry/opentelemetry-auto-psr18 \
      open-telemetry/opentelemetry-auto-psr15 2>&1
fi

# 2. Warten auf MySQL (via PHP PDO — umgeht TLS-Probleme von mysqladmin)
echo "[2/4] Warte auf MySQL (${MYSQL_HOST}:${MYSQL_PORT})..."
MAX_WAIT=60
WAITED=0
until php -r "
    try {
        new PDO('mysql:host=${MYSQL_HOST};port=${MYSQL_PORT}', '${MYSQL_USER}', '${MYSQL_PASSWORD}');
        echo 'ok';
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null; do
    WAITED=$((WAITED + 1))
    if [ "${WAITED}" -ge "${MAX_WAIT}" ]; then
        echo "FEHLER: MySQL nicht erreichbar nach ${MAX_WAIT}s" >&2
        exit 1
    fi
    sleep 1
done
echo "  MySQL bereit (${WAITED}s)"

# 3. config.ini.php generieren
echo "[3/4] config.ini.php generieren..."
mkdir -p "${DATA_DIR}"
cat > "${DATA_DIR}/config.ini.php" << CONFIGEOF
; webtrees configuration (generated by setup-webtrees.sh)
[Database]
dbtype = "mysql"
dbhost = "${MYSQL_HOST}"
dbport = "${MYSQL_PORT}"
dbuser = "${MYSQL_USER}"
dbpass = "${MYSQL_PASSWORD}"
dbname = "${MYSQL_DATABASE}"
tblpfx = "wt_"
base_url = "http://webtrees:80"
rewrite_urls = "1"
CONFIGEOF
chown www-data:www-data "${DATA_DIR}/config.ini.php"
chmod 600 "${DATA_DIR}/config.ini.php"

# 3a. data/media-Verzeichnis sicherstellen (www-data:755)
# L3-Integrationstests erstellen Dateien unter data/media/ als root,
# was ManageMediaPage (RecursiveDirectoryIterator) bei L4 mit 500 quittiert.
MEDIA_DIR="${DATA_DIR}/media"
mkdir -p "${MEDIA_DIR}"
chown -R www-data:www-data "${MEDIA_DIR}"
chmod -R 755 "${MEDIA_DIR}"

# 3b. Privacy-Fixture pruefen (NICHT generieren — /fixtures ist read-only gemountet).
# Die Generierung aus dem Template (privacy-test-template.ged → privacy-test.ged)
# erfolgt HOST-seitig via scripts/generate-privacy-fixture.sh (Makefile-Target
# generate-fixtures, Prerequisite von up). Hier nur Existenzpruefung mit Fail-Fast.
echo "[3b/4] Privacy-Fixture pruefen..."
PRIVACY_OUTPUT="${FIXTURES_DIR}/privacy-test.ged"
if [ -f "${PRIVACY_OUTPUT}" ]; then
    echo "  privacy-test.ged vorhanden (host-seitig generiert)"
else
    echo "FEHLER: ${PRIVACY_OUTPUT} fehlt." >&2
    echo "  /fixtures ist read-only gemountet — die Generierung erfolgt auf dem Host." >&2
    echo "  Auf dem Host ausfuehren: make generate-fixtures (laeuft automatisch bei make up/setup)." >&2
    exit 1
fi

# 4. DB-Migration, Admin-User, GEDCOM-Import — alles in einem PHP-Aufruf
echo "[4/4] DB-Migration, Admin-User und GEDCOM-Import..."
php "${WEBTREES_DIR}/vendor/autoload.php" 2>/dev/null || true
php << 'PHPEOF'
<?php

declare(strict_types=1);

$webtreesDir = '/var/www/html';
$fixturesDir = '/fixtures';

require $webtreesDir . '/vendor/autoload.php';

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Webtrees;

// Boot webtrees (korrekte Sequenz: new → bootstrap)
$webtrees = new Webtrees();
$webtrees->bootstrap();
I18N::init('en-US', true);

// DB-Verbindung
$host     = getenv('MYSQL_HOST') ?: 'mysql';
$port     = getenv('MYSQL_PORT') ?: '3306';
$database = getenv('MYSQL_DATABASE') ?: 'webtrees_test';
$username = getenv('MYSQL_USER') ?: 'webtrees';
$password = getenv('MYSQL_PASSWORD') ?: throw new \RuntimeException('MYSQL_PASSWORD nicht gesetzt');

// App-Passwoerter aus Umgebungsvariablen
$adminPassword    = getenv('WEBTREES_ADMIN_PASSWORD') ?: throw new \RuntimeException('WEBTREES_ADMIN_PASSWORD nicht gesetzt');
$testUserPassword = getenv('WEBTREES_TEST_USER_PASSWORD') ?: throw new \RuntimeException('WEBTREES_TEST_USER_PASSWORD nicht gesetzt');

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

// Schema-Migration
echo "  Migration...\n";
$migration = new MigrationService();
$migration->updateSchema('\Fisharebest\Webtrees\Schema', 'WT_SCHEMA_VERSION', Webtrees::SCHEMA_VERSION);
$migration->seedDatabase();

// GEDCOM-Tags registrieren
(new Gedcom())->registerTags(Registry::elementFactory(), true);

// Admin-User anlegen
echo "  Admin-User anlegen...\n";
$userService = new UserService();
$admin = $userService->findByUserName('admin');
if ($admin === null) {
    $admin = $userService->create('admin', 'Test Admin', 'admin@example.com', $adminPassword);
    echo "  Admin-User erstellt.\n";
}
// Passwort immer aktualisieren (konsistent mit generiertem Passwort nach make clean)
DB::table('user')
    ->where('user_name', '=', 'admin')
    ->update(['password' => password_hash($adminPassword, PASSWORD_DEFAULT)]);

// Preferences immer setzen (nicht nur bei Erstellung), damit auch bei
// vorhandenem User aus frueheren Testlaeufen der Admin-Status sicher gesetzt ist.
$admin->setPreference(\Fisharebest\Webtrees\Contracts\UserInterface::PREF_IS_ADMINISTRATOR, '1');
$admin->setPreference('verified', '1');
$admin->setPreference('verified_by_admin', '1');

// Admin einloggen (für TreeService::create, das Auth::id() verwendet)
Auth::login($admin);

// GEDCOM-Import Funktion (nach TestCase-Muster: importRecord pro Record)
$gedcomImportService = new GedcomImportService();
$treeService = new TreeService($gedcomImportService);

$fixtures = [
    ['name' => 'demo',    'title' => 'Demo Tree',         'file' => $fixturesDir . '/demo.ged'],
    ['name' => 'muster',  'title' => 'Muster (GEDCOM-L)', 'file' => $fixturesDir . '/gedcom-l-muster.ged'],
    ['name' => 'privacy', 'title' => 'Privacy Test Tree', 'file' => $fixturesDir . '/privacy-test.ged'],
];

foreach ($fixtures as $fixture) {
    if (DB::table('gedcom')->where('gedcom_name', '=', $fixture['name'])->count() > 0) {
        echo "  Baum '{$fixture['name']}' existiert bereits.\n";
        continue;
    }

    echo "  Baum '{$fixture['name']}' erstellen und importieren...\n";
    $tree = $treeService->create($fixture['name'], $fixture['title']);

    // Default-Records löschen (wie TestCase::importTree)
    DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
    DB::table('families')->where('f_file', '=', $tree->id())->delete();
    DB::table('sources')->where('s_file', '=', $tree->id())->delete();
    DB::table('other')->where('o_file', '=', $tree->id())->delete();
    DB::table('places')->where('p_file', '=', $tree->id())->delete();
    DB::table('placelinks')->where('pl_file', '=', $tree->id())->delete();
    DB::table('name')->where('n_file', '=', $tree->id())->delete();
    DB::table('dates')->where('d_file', '=', $tree->id())->delete();
    DB::table('change')->where('gedcom_id', '=', $tree->id())->delete();
    DB::table('link')->where('l_file', '=', $tree->id())->delete();
    DB::table('media_file')->where('m_file', '=', $tree->id())->delete();
    DB::table('media')->where('m_file', '=', $tree->id())->delete();

    // GEDCOM-Datei Record für Record importieren
    $gedcom = file_get_contents($fixture['file']);
    // BOM entfernen und Zeilenenden normalisieren
    $gedcom = str_replace("\xEF\xBB\xBF", '', $gedcom);
    $gedcom = str_replace("\r\n", "\n", $gedcom);
    $records = preg_split('/\n(?=0 )/', $gedcom);

    $count = 0;
    foreach ($records as $record) {
        $gedcomImportService->importRecord($record, $tree, false);
        $count++;
    }

    echo "  {$count} Records importiert.\n";
}

// Test-User für Privacy-Tests anlegen (member, editor, moderator, manager, relationship)
echo "  Privacy-Test-User anlegen...\n";

$privacyTree = null;
$demoTree = null;
foreach (DB::table('gedcom')->get() as $row) {
    if ($row->gedcom_name === 'privacy') {
        $privacyTree = $treeService->find((int) $row->gedcom_id);
    }
    if ($row->gedcom_name === 'demo') {
        $demoTree = $treeService->find((int) $row->gedcom_id);
    }
}

if ($privacyTree !== null) {
    // Privacy-Baum oeffentlich machen (private=0), damit Besucher zugreifen koennen
    DB::table('gedcom')
        ->where('gedcom_id', '=', $privacyTree->id())
        ->update(['private' => 0]);
    echo "  Privacy-Baum auf oeffentlich gesetzt (private=0).\n";

    $roles = [
        'member'    => \Fisharebest\Webtrees\Contracts\UserInterface::ROLE_MEMBER,
        'editor'    => \Fisharebest\Webtrees\Contracts\UserInterface::ROLE_EDITOR,
        'moderator' => \Fisharebest\Webtrees\Contracts\UserInterface::ROLE_MODERATOR,
        'manager'   => \Fisharebest\Webtrees\Contracts\UserInterface::ROLE_MANAGER,
    ];

    foreach ($roles as $roleName => $roleConst) {
        $username = "test-{$roleName}";
        $testUser = $userService->findByUserName($username);
        if ($testUser === null) {
            $testUser = $userService->create(
                $username,
                'Test ' . ucfirst($roleName),
                "test-{$roleName}@test.local",
                $testUserPassword
            );
            echo "  User '{$username}' erstellt.\n";
        }
        // Passwort immer aktualisieren (konsistent mit generiertem Passwort)
        DB::table('user')
            ->where('user_name', '=', $username)
            ->update(['password' => password_hash($testUserPassword, PASSWORD_DEFAULT)]);
        $testUser->setPreference('verified', '1');
        $testUser->setPreference('verified_by_admin', '1');

        // Rolle im Privacy-Baum
        $privacyTree->setUserPreference($testUser, \Fisharebest\Webtrees\Contracts\UserInterface::PREF_TREE_ROLE, $roleConst);

        // Gleiche Rolle im Demo-Baum (falls vorhanden)
        if ($demoTree !== null) {
            $demoTree->setUserPreference($testUser, \Fisharebest\Webtrees\Contracts\UserInterface::PREF_TREE_ROLE, $roleConst);
        }

        // Editor: Pending Changes bleiben stehen (kein auto_accept)
        if ($roleName === 'editor') {
            $privacyTree->setUserPreference($testUser, 'auto_accept', '');
            if ($demoTree !== null) {
                $demoTree->setUserPreference($testUser, 'auto_accept', '');
            }
        }
    }

    // Relationship-Privacy-User (eigener User mit XREF-Verknuepfung)
    $relUser = $userService->findByUserName('test-relationship');
    if ($relUser === null) {
        $relUser = $userService->create(
            'test-relationship',
            'Test Relationship',
            'test-relationship@test.local',
            $testUserPassword
        );
        echo "  User 'test-relationship' erstellt.\n";
    }
    // Passwort immer aktualisieren (konsistent mit generiertem Passwort)
    DB::table('user')
        ->where('user_name', '=', 'test-relationship')
        ->update(['password' => password_hash($testUserPassword, PASSWORD_DEFAULT)]);
    $relUser->setPreference('verified', '1');
    $relUser->setPreference('verified_by_admin', '1');
    $privacyTree->setUserPreference($relUser, \Fisharebest\Webtrees\Contracts\UserInterface::PREF_TREE_ROLE, \Fisharebest\Webtrees\Contracts\UserInterface::ROLE_MEMBER);
    $privacyTree->setUserPreference($relUser, \Fisharebest\Webtrees\Contracts\UserInterface::PREF_TREE_ACCOUNT_XREF, 'P_REL_USER');
    $privacyTree->setUserPreference($relUser, \Fisharebest\Webtrees\Contracts\UserInterface::PREF_TREE_PATH_LENGTH, '2');

    echo "  Privacy-Test-User konfiguriert.\n";
} else {
    echo "  WARNUNG: Privacy-Baum nicht gefunden, Test-User nicht angelegt.\n";
}

echo "  Setup abgeschlossen.\n";
PHPEOF

echo "=== Setup abgeschlossen ==="
