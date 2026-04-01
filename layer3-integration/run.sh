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

vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --log-junit="${ARTIFACTS}/phpunit-integration.xml" \
    --coverage-html="${ARTIFACTS}/coverage-html" \
    --coverage-clover="${ARTIFACTS}/coverage.xml" \
    2>&1 | tee "${ARTIFACTS}/phpunit-output.log"

EXIT_CODE=${PIPESTATUS[0]}

# Bei Fehler: DB-Dump als Artefakt
if [ "${EXIT_CODE}" -ne 0 ]; then
    echo "  Fehler — erstelle DB-Dump..."
    mysqldump -h "${MYSQL_HOST:-mysql}" -u "${MYSQL_USER:-webtrees}" \
        -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE:-webtrees_test}" \
        > "${ARTIFACTS}/db-dump.sql" 2>/dev/null || true

    # PHP-Fehlerlog kopieren
    cp /var/log/php_errors.log "${ARTIFACTS}/php-errors.log" 2>/dev/null || true
fi

echo "=== Komponentenintegrationstest abgeschlossen (Exit: ${EXIT_CODE}) ==="
exit "${EXIT_CODE}"
