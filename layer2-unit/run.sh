#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Teststufe 1 — Komponententest (PHPUnit, SQLite in-memory)
# Verwendet die webtrees-eigene Test-Infrastruktur

set -euo pipefail

WEBTREES_DIR="/var/www/html"
ARTIFACTS="/artifacts/layer2"

mkdir -p "${ARTIFACTS}"

echo "=== Teststufe 1 — Komponententest ==="

cd "${WEBTREES_DIR}"

# Testdaten bereinigen — Dateien, die Upstream-Tests (MaintenanceModeServiceTest)
# hinterlassen könnten: offline.txt (ggf. chmod 0 oder Verzeichnis), foo
chmod +w tests/data/offline.txt 2>/dev/null || true
rm -f tests/data/offline.txt tests/data/foo
rmdir tests/data/offline.txt 2>/dev/null || true

# Testlauf-Artefakte aus früheren Läufen bereinigen (data/tmp/, exportierte .ged-Dateien)
rm -rf /var/www/html/data/tmp 2>/dev/null || true
find /var/www/html/data -maxdepth 1 -name "*.ged" -delete 2>/dev/null || true

# Verzeichnisse für non-root-Ausführung vorbereiten.
# Upstream-Tests (MaintenanceModeServiceTest) prüfen is_readable() auf chmod-0-Dateien
# und erwarten false — das trifft nur zu, wenn der Prozess nicht root ist.
chown -R www-data:www-data "${WEBTREES_DIR}/tests/data/"
chown -R www-data:www-data "${ARTIFACTS}" 2>/dev/null || true
mkdir -p /tmp/phpunit-cache && chown www-data:www-data /tmp/phpunit-cache

# Host-Umask kann der Bind-Mount-Source restriktive Permissions verleihen (z. B. 0640),
# sodass www-data die Source nicht lesen kann. Sicherstellen, dass app/, tests/ und vendor/
# world-readable sind — die Source ist read-only und ihre Sichtbarkeit unkritisch.
chmod -R o+rX "${WEBTREES_DIR}/app" "${WEBTREES_DIR}/tests" "${WEBTREES_DIR}/vendor" 2>/dev/null || true

EXIT_CODE=0
su -s /bin/bash www-data -c "cd ${WEBTREES_DIR} && vendor/bin/phpunit \
    --configuration=/tests/layer2-unit/phpunit-unit.xml \
    --log-junit='${ARTIFACTS}/phpunit-unit.xml' \
    --coverage-clover='${ARTIFACTS}/coverage.xml'" || EXIT_CODE=$?

echo "=== Komponententest abgeschlossen (Exit: ${EXIT_CODE}) ==="
exit "${EXIT_CODE}"
