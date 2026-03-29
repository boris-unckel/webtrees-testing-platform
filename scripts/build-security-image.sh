#!/usr/bin/env bash
# Build-Helper für Containerfile.security
# Mountet die Upstream-Source per --volume zur Build-Zeit,
# da COPY nicht außerhalb des Build-Contexts referenzieren kann.
#
# @see docs/security_plan.md Abschnitt 6.1

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
WEBTREES_SOURCE="${WEBTREES_SOURCE:-${PROJECT_DIR}/upstream/webtrees}"

# Prüfe, ob Upstream-Source existiert
if [[ ! -d "${WEBTREES_SOURCE}" ]]; then
    echo "FEHLER: webtrees-Source nicht gefunden: ${WEBTREES_SOURCE}" >&2
    echo "Setze WEBTREES_SOURCE=/pfad/zum/checkout oder führe 'make clone-upstream' aus." >&2
    exit 1
fi

WEBTREES_SOURCE_REAL="$(realpath "${WEBTREES_SOURCE}")"
echo "Build Security-Image: Source=${WEBTREES_SOURCE_REAL}"

podman build \
    --volume "${WEBTREES_SOURCE_REAL}:/webtrees-src-mount:ro" \
    -t webtrees-security \
    -f "${PROJECT_DIR}/Containerfile.security" \
    "${PROJECT_DIR}"
