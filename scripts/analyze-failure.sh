#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# analyze-failure.sh — Artefakt-Sammler → Claude Code CLI
#
# Sammelt Artefakte des letzten fehlgeschlagenen Testlaufs und öffnet
# Claude Code CLI mit vorgeladenem Kontext zur Fehleranalyse.
#
# Aufruf:
#   ./scripts/analyze-failure.sh        # Alle Teststufen
#   ./scripts/analyze-failure.sh 1      # Nur Statischer Test
#   ./scripts/analyze-failure.sh 2      # Nur Komponententest
#   ./scripts/analyze-failure.sh 3      # Nur Komponentenintegrationstest
#   ./scripts/analyze-failure.sh 4      # Nur Systemtest
#   ./scripts/analyze-failure.sh 5      # Nur Performanztest

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(dirname "${SCRIPT_DIR}")"
ARTIFACTS="${BASE_DIR}/artifacts"
LAYER="${1:-all}"

CONTEXT_FILE=$(mktemp /tmp/webtrees-test-context-XXXXXX.md)
trap 'rm -f "${CONTEXT_FILE}"' EXIT

cat > "${CONTEXT_FILE}" << 'HEADER'
# webtrees Test-Fehleranalyse

Du analysierst fehlgeschlagene Tests des webtrees Test-Stacks.
Untersuche die folgenden Artefakte, grenze die Fehlerursache ein
und schlage konkrete nächste Debugging-Schritte vor.

---

HEADER

collect_layer1() {
    echo "## Statischer Test (Layer 1)" >> "${CONTEXT_FILE}"
    for f in "${ARTIFACTS}/layer1/phpstan.json" "${ARTIFACTS}/layer1/phpcs.json"; do
        if [ -f "$f" ]; then
            echo "### $(basename "$f")" >> "${CONTEXT_FILE}"
            echo '```json' >> "${CONTEXT_FILE}"
            head -200 "$f" >> "${CONTEXT_FILE}"
            echo '```' >> "${CONTEXT_FILE}"
        fi
    done
}

collect_layer2() {
    echo "## Komponententest (Layer 2)" >> "${CONTEXT_FILE}"
    for f in "${ARTIFACTS}/layer2/phpunit-unit.xml" "${ARTIFACTS}/layer2/phpunit-output.log"; do
        if [ -f "$f" ]; then
            echo "### $(basename "$f")" >> "${CONTEXT_FILE}"
            echo '```' >> "${CONTEXT_FILE}"
            tail -100 "$f" >> "${CONTEXT_FILE}"
            echo '```' >> "${CONTEXT_FILE}"
        fi
    done
}

collect_layer3() {
    echo "## Komponentenintegrationstest (Layer 3)" >> "${CONTEXT_FILE}"
    for f in "${ARTIFACTS}/layer3/phpunit-integration.xml" "${ARTIFACTS}/layer3/phpunit-output.log" "${ARTIFACTS}/layer3/php-errors.log"; do
        if [ -f "$f" ]; then
            echo "### $(basename "$f")" >> "${CONTEXT_FILE}"
            echo '```' >> "${CONTEXT_FILE}"
            tail -100 "$f" >> "${CONTEXT_FILE}"
            echo '```' >> "${CONTEXT_FILE}"
        fi
    done
    # DB-Dump (nur Schema-Info, nicht komplett)
    if [ -f "${ARTIFACTS}/layer3/db-dump.sql" ]; then
        echo "### DB-Dump (erste 50 Zeilen)" >> "${CONTEXT_FILE}"
        echo '```sql' >> "${CONTEXT_FILE}"
        head -50 "${ARTIFACTS}/layer3/db-dump.sql" >> "${CONTEXT_FILE}"
        echo '```' >> "${CONTEXT_FILE}"
    fi
}

collect_layer4() {
    echo "## Systemtest (Layer 4)" >> "${CONTEXT_FILE}"
    # Playwright-Output
    for f in "${ARTIFACTS}/layer4/"*.log; do
        if [ -f "$f" ]; then
            echo "### $(basename "$f")" >> "${CONTEXT_FILE}"
            echo '```' >> "${CONTEXT_FILE}"
            tail -100 "$f" >> "${CONTEXT_FILE}"
            echo '```' >> "${CONTEXT_FILE}"
        fi
    done
    # Screenshots
    for f in "${ARTIFACTS}/layer4/test-results/"*.png; do
        if [ -f "$f" ]; then
            echo "### Screenshot: $(basename "$f")" >> "${CONTEXT_FILE}"
            echo "(Pfad: $f)" >> "${CONTEXT_FILE}"
        fi
    done
}

collect_layer5() {
    echo "## Performanztest (Layer 5)" >> "${CONTEXT_FILE}"
    for f in "${ARTIFACTS}/layer5/"*.json "${ARTIFACTS}/layer5/"*.log; do
        if [ -f "$f" ]; then
            echo "### $(basename "$f")" >> "${CONTEXT_FILE}"
            echo '```json' >> "${CONTEXT_FILE}"
            cat "$f" >> "${CONTEXT_FILE}"
            echo '```' >> "${CONTEXT_FILE}"
        fi
    done
}

# Artefakte sammeln
case "${LAYER}" in
    1)   collect_layer1 ;;
    2)   collect_layer2 ;;
    3)   collect_layer3 ;;
    4)   collect_layer4 ;;
    5)   collect_layer5 ;;
    all) collect_layer1; collect_layer2; collect_layer3; collect_layer4; collect_layer5 ;;
    *)   echo "Unbekannte Teststufe: ${LAYER}. Verwende 1-5 oder 'all'." >&2; exit 1 ;;
esac

# Prüfen ob Kontext-Inhalt vorhanden
CONTENT_LINES=$(wc -l < "${CONTEXT_FILE}")
if [ "${CONTENT_LINES}" -le 10 ]; then
    echo "Keine Artefakte gefunden in artifacts/. Wurde ein Testlauf durchgeführt?"
    exit 0
fi

echo "Artefakte gesammelt (${CONTENT_LINES} Zeilen). Starte Claude Code CLI..."

# Claude Code CLI mit Kontext starten
claude --context "${CONTEXT_FILE}"
