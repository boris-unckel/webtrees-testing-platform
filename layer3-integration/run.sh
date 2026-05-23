#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Teststufe 2 — Komponentenintegrationstest (PHPUnit + MySQL)
# Führt Tests gegen die echte MySQL-Datenbank im Container aus

set -euo pipefail

WEBTREES_DIR="/var/www/html"
ARTIFACTS="/artifacts/layer3"

mkdir -p "${ARTIFACTS}"

echo "=== Teststufe 2 — Komponentenintegrationstest ==="

cd "${WEBTREES_DIR}"

COVERAGE_DIR="/coverage"

EXIT_CODE=0
vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --log-junit="${ARTIFACTS}/phpunit-integration.xml" \
    --coverage-clover="${COVERAGE_DIR}/layer3-coverage.xml" || EXIT_CODE=$?

# Bei Fehler: DB-Dump als Artefakt
if [ "${EXIT_CODE}" -ne 0 ]; then
    echo "  Fehler — erstelle DB-Dump..."
    mysqldump -h "${MYSQL_HOST:-mysql}" -u "${MYSQL_USER:-webtrees}" \
        -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE:-webtrees_test}" \
        > "${ARTIFACTS}/db-dump.sql" 2>/dev/null || true

    # PHP-Fehlerlog kopieren
    cp /var/log/php_errors.log "${ARTIFACTS}/php-errors.log" 2>/dev/null || true
fi

# Spuernasen-Sweep: Tests, die unabhaengig vom Verhalten gruen melden oder
# skippen (Java-@Ignore-Aequivalent + tautologische Smoke-Tests + Kein-Exception-
# Erfolg + Phantom-Assertions). Laeuft immer, auch bei rotem PHPUnit-Lauf.
echo
echo "=== Spuernasen-Sweep: stille Tests (Skip / Tautologie / No-Throw / Phantom) ==="
SWEEP_FILE="${ARTIFACTS}/silent-tests-sweep.txt"
grep -rnE \
    "markTestSkipped|markTestIncomplete|assertTrue\(class_exists|assertTrue\(method_exists|assertTrue\(interface_exists|assertTrue\(trait_exists|assertTrue\(true|addToAssertionCount" \
    /tests/layer3-integration/tests/ --include='*.php' \
    > "${SWEEP_FILE}" || true
cat "${SWEEP_FILE}"
SWEEP_COUNT=$(wc -l < "${SWEEP_FILE}")
echo "--- ${SWEEP_COUNT} Treffer (Report: artifacts/layer3/silent-tests-sweep.txt) ---"

echo "=== Komponentenintegrationstest abgeschlossen (Exit: ${EXIT_CODE}) ==="
exit "${EXIT_CODE}"
