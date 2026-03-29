#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# export-traces.sh — OTel-Traces als JSON exportieren
#
# Exportiert Traces aus dem OTel Collector File-Exporter und
# speichert sie als versionierte Baseline oder als Vergleichsdatei.
#
# Aufruf:
#   ./scripts/export-traces.sh baseline 2.2.5    # Baseline speichern
#   ./scripts/export-traces.sh compare  2.2.6    # Vergleich mit Baseline

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$(dirname "${SCRIPT_DIR}")"
ARTIFACTS="${BASE_DIR}/artifacts"
BASELINES="${BASE_DIR}/layer5-performance/baselines"

ACTION="${1:-}"
VERSION="${2:-}"

if [ -z "${ACTION}" ] || [ -z "${VERSION}" ]; then
    echo "Aufruf: $0 <baseline|compare> <version>"
    echo "  baseline 2.2.5  — aktuelle Traces als Baseline für Version 2.2.5 speichern"
    echo "  compare  2.2.6  — aktuelle Traces mit gespeicherter Baseline vergleichen"
    exit 1
fi

TRACES_FILE="${ARTIFACTS}/traces.json"

if [ ! -f "${TRACES_FILE}" ]; then
    echo "FEHLER: ${TRACES_FILE} nicht gefunden."
    echo "Wurde ein Testlauf mit OTel-Tracing durchgeführt?"
    exit 1
fi

case "${ACTION}" in
    baseline)
        mkdir -p "${BASELINES}"
        cp "${TRACES_FILE}" "${BASELINES}/${VERSION}-traces.json"
        echo "Baseline gespeichert: ${BASELINES}/${VERSION}-traces.json"
        ;;
    compare)
        BASELINE_FILE="${BASELINES}/${VERSION}-traces.json"
        if [ ! -f "${BASELINE_FILE}" ]; then
            echo "FEHLER: Keine Baseline für Version ${VERSION} gefunden."
            echo "Zuerst: $0 baseline ${VERSION}"
            exit 1
        fi

        DIFF_FILE="${ARTIFACTS}/layer5/trace-diff-${VERSION}.json"
        mkdir -p "$(dirname "${DIFF_FILE}")"

        # Einfacher JSON-Diff: Zeilenanzahl und Span-Count vergleichen
        BASELINE_SPANS=$(grep -c '"traceId"' "${BASELINE_FILE}" 2>/dev/null || echo 0)
        CURRENT_SPANS=$(grep -c '"traceId"' "${TRACES_FILE}" 2>/dev/null || echo 0)

        cat > "${DIFF_FILE}" << EOF
{
  "baseline_version": "${VERSION}",
  "baseline_spans": ${BASELINE_SPANS},
  "current_spans": ${CURRENT_SPANS},
  "span_diff": $((CURRENT_SPANS - BASELINE_SPANS)),
  "timestamp": "$(date -Iseconds)"
}
EOF
        echo "Trace-Diff: ${DIFF_FILE}"
        echo "  Baseline: ${BASELINE_SPANS} Spans"
        echo "  Aktuell:  ${CURRENT_SPANS} Spans"
        echo "  Differenz: $((CURRENT_SPANS - BASELINE_SPANS)) Spans"
        ;;
    *)
        echo "Unbekannte Aktion: ${ACTION}. Verwende 'baseline' oder 'compare'."
        exit 1
        ;;
esac
