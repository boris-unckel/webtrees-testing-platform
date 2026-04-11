#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Systemtest — Playwright mit OTel-Korrelation
# Wird im Playwright-Container ausgefuehrt. Zusaetzliche Argumente werden
# als Spec-Filter an Playwright durchgereicht (fuer quick-Variante).
#
# TEST_RUN_ID wird vom Makefile gesetzt und hier nicht neu erzeugt.

set -euo pipefail

ARTIFACTS="/artifacts/layer4"
mkdir -p "${ARTIFACTS}"

echo "=== Teststufe 3 — Systemtest ==="

cd /tests/e2e

EXIT_CODE=0
npx playwright test \
    --config=playwright.config.ts \
    "$@" || EXIT_CODE=$?

echo "=== Systemtest abgeschlossen (Exit: ${EXIT_CODE}) ==="
exit "${EXIT_CODE}"
