#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Stellt sicher, dass der webtrees-Source-Baum world-readable ist —
# Voraussetzung dafuer, dass der :ro-Bind-Mount in compose.yaml vom
# www-data (uid 33, host-seitig via subuid gemappt) im Container gelesen
# werden kann.
#
# Idempotent. Reconciliation fuer Drift, der trotz core.sharedRepository=all
# entstehen kann (Editor-Speicherungen, manuelle Aenderungen, Tools mit
# eigener Umask).
#
# Default-Pfad (./upstream/webtrees) wird repariert. Externer
# WEBTREES_SOURCE wird nur verifiziert; fremde Working Trees werden nicht
# angefasst, im Fehlerfall folgt eine konkrete Reparaturanweisung.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

DEFAULT_SOURCE="${PROJECT_DIR}/upstream/webtrees"
WEBTREES_SOURCE="${WEBTREES_SOURCE:-${DEFAULT_SOURCE}}"

if [ ! -d "${WEBTREES_SOURCE}" ]; then
    echo "ERROR: WEBTREES_SOURCE=${WEBTREES_SOURCE} existiert nicht." >&2
    exit 1
fi

# Pfade kanonisieren, sonst klafft 'Makefile-Relativpfad ./upstream/webtrees'
# vs. 'script-interner Absolutpfad' und der Default-Pfad wird faelschlich
# als 'extern' eingestuft.
WEBTREES_SOURCE="$(readlink -f -- "${WEBTREES_SOURCE}")"
DEFAULT_SOURCE="$(readlink -f -- "${DEFAULT_SOURCE}")"

is_default=0
[ "${WEBTREES_SOURCE}" = "${DEFAULT_SOURCE}" ] && is_default=1

# .git/ wird zwar mitgemountet, von der Anwendung aber nicht gelesen; git
# verwaltet seine internen Permissions selbst (core.sharedRepository).
# Wir lassen .git/ daher aus dem Scan/Repair heraus, um Rauschen zu sparen.
count_dirs=$(find "${WEBTREES_SOURCE}" -path "${WEBTREES_SOURCE}/.git" -prune -o \
    -type d ! -perm -o=rx -print 2>/dev/null | wc -l)
count_files=$(find "${WEBTREES_SOURCE}" -path "${WEBTREES_SOURCE}/.git" -prune -o \
    -type f ! -perm -o=r -print 2>/dev/null | wc -l)

if [ "${count_dirs}" -eq 0 ] && [ "${count_files}" -eq 0 ]; then
    echo "Source-Permissions OK: ${WEBTREES_SOURCE} (alle Eintraege world-readable)"
    exit 0
fi

if [ "${is_default}" -eq 0 ]; then
    echo "ERROR: WEBTREES_SOURCE=${WEBTREES_SOURCE} hat ${count_dirs} Verzeichnisse und ${count_files} Dateien ohne world-read — externer Pfad wird nicht automatisch veraendert." >&2
    cat >&2 <<EOF
  Reparatur (vor naechstem 'make up'):
    find ${WEBTREES_SOURCE} -path '*/.git' -prune -o -type d -exec chmod a+rx {} +
    find ${WEBTREES_SOURCE} -path '*/.git' -prune -o -type f -exec chmod a+r  {} +

  Dauerhaft (umask-unabhaengig fuer kuenftige git-Operationen):
    git -C ${WEBTREES_SOURCE} config core.sharedRepository all
EOF
    exit 1
fi

# Default-Pfad: idempotent reparieren. -exec wirkt nur auf Eintraege, die
# das ! -perm-Filter passieren; .git/ ist gepruned.
find "${WEBTREES_SOURCE}" -path "${WEBTREES_SOURCE}/.git" -prune -o \
    -type d ! -perm -o=rx -exec chmod a+rx {} +
find "${WEBTREES_SOURCE}" -path "${WEBTREES_SOURCE}/.git" -prune -o \
    -type f ! -perm -o=r  -exec chmod a+r  {} +

echo "Source-Permissions repariert: ${count_dirs} Verzeichnis(se) chmod a+rx, ${count_files} Datei(en) chmod a+r."
