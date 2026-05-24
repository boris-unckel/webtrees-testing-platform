#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Klont webtrees-Upstream, wenn nicht bereits vorhanden, und konfiguriert
# den Default-Checkout so, dass kuenftige git-Operationen world-readable
# Dateien erzeugen (umask-unabhaengig). Externe WEBTREES_SOURCE-Pfade
# werden weder geklont noch in ihrer git-Konfiguration veraendert.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

DEFAULT_SOURCE="${PROJECT_DIR}/upstream/webtrees"
WEBTREES_SOURCE="${WEBTREES_SOURCE:-${DEFAULT_SOURCE}}"
WEBTREES_REPO="${WEBTREES_REPO:-https://github.com/fisharebest/webtrees.git}"
WEBTREES_REF="${WEBTREES_REF:-main}"

# Pfade kanonisieren, damit Makefile-Relativpfad ('./upstream/webtrees') und
# script-interner Absolutpfad als 'gleich' erkannt werden. Wenn das Ziel
# noch nicht existiert (Erstklone), nehmen wir das Eltern-Verzeichnis als
# Vergleichsbasis.
canonicalize() {
    local p="$1"
    if [ -e "${p}" ]; then
        readlink -f -- "${p}"
    else
        printf '%s/%s\n' "$(readlink -f -- "$(dirname -- "${p}")")" "$(basename -- "${p}")"
    fi
}
WEBTREES_SOURCE_ABS="$(canonicalize "${WEBTREES_SOURCE}")"
DEFAULT_SOURCE_ABS="$(canonicalize "${DEFAULT_SOURCE}")"

# Externer Pfad: fremdes Working Tree — nicht klonen, nicht git-config aendern.
if [ "${WEBTREES_SOURCE_ABS}" != "${DEFAULT_SOURCE_ABS}" ]; then
    if [ -d "${WEBTREES_SOURCE}" ]; then
        echo "webtrees-Source (extern): ${WEBTREES_SOURCE} (uebersprungen, fremdes Working Tree)"
        exit 0
    fi
    echo "ERROR: WEBTREES_SOURCE=${WEBTREES_SOURCE} existiert nicht." >&2
    echo "  Externe Pfade werden nicht automatisch geklont — bitte selbst bereitstellen." >&2
    exit 1
fi

if [ -d "${WEBTREES_SOURCE_ABS}" ]; then
    echo "webtrees-Source vorhanden: ${WEBTREES_SOURCE_ABS} (Klone uebersprungen)"
else
    echo "webtrees-Source klonen..."
    echo "  Repo: ${WEBTREES_REPO}"
    echo "  Ref:  ${WEBTREES_REF}"
    echo "  Ziel: ${WEBTREES_SOURCE_ABS}"
    mkdir -p "$(dirname "${WEBTREES_SOURCE_ABS}")"
    git clone --branch "${WEBTREES_REF}" "${WEBTREES_REPO}" "${WEBTREES_SOURCE_ABS}"
fi

# core.sharedRepository=all sorgt dafuer, dass git neue/geaenderte Dateien
# unabhaengig von der Prozess-Umask mit 0664/0775 ablegt. Damit bleibt der
# Checkout nach jedem 'git pull' fuer www-data im Container lesbar (uid 33,
# host-seitig via subuid auf ~100032 gemappt). Die Repair-Phase
# (normalize-source-perms.sh) bleibt als Reconciliation fuer divergente
# Bestaende noetig.
current=$(git -C "${WEBTREES_SOURCE_ABS}" config --local --default '' core.sharedRepository)
if [ "${current}" != "all" ] && [ "${current}" != "world" ]; then
    git -C "${WEBTREES_SOURCE_ABS}" config --local core.sharedRepository all
    echo "git core.sharedRepository=all gesetzt (vorher: '${current:-<nicht gesetzt>}')."
fi
