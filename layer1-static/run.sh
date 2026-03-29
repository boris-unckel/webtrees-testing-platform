#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Statischer Test: PHPStan + PHPCS im webtrees-Container
# Ergebnisse werden als JSON in artifacts/layer1/ gespeichert

set -euo pipefail

WEBTREES_DIR="/var/www/html"
ARTIFACTS="/artifacts/layer1"
EXIT_CODE=0

mkdir -p "${ARTIFACTS}"

echo "=== Statischer Test ==="

# PHPStan — konfiguriertes Level aus phpstan.neon.dist (Level 2 + Baseline)
# Hinweis: Analysiert den webtrees-Core (dev-Branch). Fehler im dev-Branch sind
# upstream-Bugs, keine lokalen Regressionen. Layer 1 ist daher informell (exit 0).
echo "[1/2] PHPStan..."
cd "${WEBTREES_DIR}"
if vendor/bin/phpstan analyse \
    --configuration=phpstan.neon.dist \
    --error-format=json \
    --no-progress \
    > "${ARTIFACTS}/phpstan.json" 2>&1; then
    echo "  PHPStan: OK (0 Errors)"
else
    ERROR_COUNT=$(php -r "echo json_decode(file_get_contents('${ARTIFACTS}/phpstan.json'), true)['totals']['file_errors'];" 2>/dev/null || echo "?")
    echo "  PHPStan: ${ERROR_COUNT} Fehler im upstream-Code (informell, siehe ${ARTIFACTS}/phpstan.json)"
fi

# PHPCS (PSR-12) — informell (upstream webtrees folgt nicht vollständig PSR-12)
echo "[2/2] PHP CodeSniffer (PSR-12)..."
if vendor/bin/phpcs \
    --standard=PSR12 \
    --report=json \
    --report-file="${ARTIFACTS}/phpcs.json" \
    --extensions=php \
    app/ 2>/dev/null; then
    echo "  PHPCS: OK (0 Violations)"
else
    VIOLATION_COUNT=$(php -r "
        \$d = json_decode(file_get_contents('${ARTIFACTS}/phpcs.json'), true);
        echo array_sum(array_map(fn(\$f) => \$f['errors'] + \$f['warnings'], \$d['files']));
    " 2>/dev/null || echo "?")
    echo "  PHPCS: ${VIOLATION_COUNT} Verstöße im upstream-Code (informell, siehe ${ARTIFACTS}/phpcs.json)"
fi

echo "=== Statischer Test abgeschlossen (Exit: ${EXIT_CODE}) ==="
exit "${EXIT_CODE}"
