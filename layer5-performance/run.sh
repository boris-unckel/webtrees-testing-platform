#!/usr/bin/env bash
# Performanztest — Playwright-Metrics + Baseline-Vergleich
# Wird im Playwright-Container ausgeführt

set -euo pipefail

ARTIFACTS="/artifacts/layer5"
mkdir -p "${ARTIFACTS}"

echo "=== Performanztest ==="

cd /tests/performance

npx playwright test \
    --config=playwright.config.ts \
    2>&1 | tee "${ARTIFACTS}/performance-output.log"

EXIT_CODE=${PIPESTATUS[0]}

echo "=== Performanztest abgeschlossen (Exit: ${EXIT_CODE}) ==="
exit "${EXIT_CODE}"
