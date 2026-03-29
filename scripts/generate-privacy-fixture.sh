#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# generate-privacy-fixture.sh — Ersetzt __YEAR_MINUS_N__ Platzhalter im Template
# Erzeugt fixtures/privacy-test.ged aus fixtures/privacy-test-template.ged
#
# Idempotent: überschreibt existierendes privacy-test.ged.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FIXTURES_DIR="${SCRIPT_DIR}/../fixtures"

TEMPLATE="${FIXTURES_DIR}/privacy-test-template.ged"
OUTPUT="${FIXTURES_DIR}/privacy-test.ged"

if [ ! -f "${TEMPLATE}" ]; then
    echo "FEHLER: Template nicht gefunden: ${TEMPLATE}" >&2
    exit 1
fi

CURRENT_YEAR=$(date +%Y)

# Alle __YEAR_MINUS_N__ Platzhalter ersetzen
# Perl-Einzeiler: ersetzt jeden Platzhalter durch das berechnete Jahr
perl -pe "s/__YEAR_MINUS_(\d+)__/${CURRENT_YEAR} - \$1/ge" "${TEMPLATE}" > "${OUTPUT}"

echo "privacy-test.ged generiert (Basisjahr: ${CURRENT_YEAR})"
