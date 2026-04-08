#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# security-audit-mark-done.sh — setzt eine Audit-Task auf Status `done`.
#
# Spec: docs/security-audit/10_fixing_and_disclosure.md §6
#
# Aufruf:
#   ./scripts/security-audit-mark-done.sh <NNN>
#   ./scripts/security-audit-mark-done.sh 042
#
# Vorbedingungen:
#   - Task SEC-AUDIT-<NNN> existiert unter docs/security-audit/tasks/
#   - Aktueller Status ist `fix_verified` oder `awaiting_user_review`
#   - Regression-Tests wurden vom User manuell verifiziert
#
# Aktionen:
#   1. Task-Datei: Frontmatter `status: done`, `last_updated: <heute>`
#   2. Status-Lifecycle-Tabelle: neue Zeile anhängen
#   3. INDEX.md: Task von „Aktive Queue" in „Abgeschlossen" verschieben

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TASKS_DIR="$REPO_ROOT/docs/security-audit/tasks"

usage() {
    cat <<EOF
Usage: $(basename "$0") <NNN>

Setzt Status von SEC-AUDIT-<NNN> auf 'done'.

Argument:
  NNN    Drei- bis viersstellige Task-Nummer (z. B. 042)

Beispiel:
  $(basename "$0") 042
EOF
}

if [[ $# -ne 1 || "$1" == "-h" || "$1" == "--help" ]]; then
    usage
    exit 0
fi

nnn="$1"
if [[ ! "$nnn" =~ ^[0-9]{3,4}$ ]]; then
    echo "ERROR: NNN muss 3 bis 4 Ziffern sein, erhalten: '$nnn'" >&2
    exit 2
fi

task_id="SEC-AUDIT-$nnn"

# Finde die Task-Datei (Name kann Slug enthalten, z. B. SEC-AUDIT-042_gedcom_note_xss.md)
shopt -s nullglob
matches=("$TASKS_DIR/${task_id}"*.md)
shopt -u nullglob

if [[ ${#matches[@]} -eq 0 ]]; then
    echo "ERROR: Keine Task-Datei für $task_id unter $TASKS_DIR/ gefunden." >&2
    exit 3
fi
if [[ ${#matches[@]} -gt 1 ]]; then
    echo "ERROR: Mehrere Task-Dateien für $task_id gefunden:" >&2
    printf '  %s\n' "${matches[@]}" >&2
    exit 3
fi

task_file="${matches[0]}"
today="$(date +%F)"
now="$(date +'%Y-%m-%d %H:%M')"

current_status="$(sed -n 's/^status:[[:space:]]*\(.*\)$/\1/p' "$task_file" | head -n1)"

case "$current_status" in
    done)
        echo "INFO: $task_id ist bereits 'done' — keine Aktion."
        exit 0
        ;;
    fix_verified|awaiting_user_review)
        ;;
    *)
        echo "ERROR: $task_id hat Status '$current_status' — erwartet 'fix_verified' oder 'awaiting_user_review'." >&2
        echo "       Driver stoppt, um versehentliches Überspringen der Regression-Verifikation zu verhindern." >&2
        exit 4
        ;;
esac

# 1. Frontmatter: status + last_updated aktualisieren
tmp="$(mktemp)"
awk -v today="$today" '
    /^status:/       { print "status: done"; next }
    /^last_updated:/ { print "last_updated: " today; next }
                     { print }
' "$task_file" > "$tmp"
mv "$tmp" "$task_file"

# 2. Status-Lifecycle-Tabelle: neue Zeile anhängen (vor der nächsten Überschrift)
#    Die Tabelle endet am ersten `##` oder EOF nach dem letzten `|` (heuristisch: wir appenden am Dateiende).
#    Um die Tabelle korrekt zu finden, suchen wir das Marker-Pattern der Lifecycle-Tabelle.
lifecycle_line="| $now | done | User-Review abgeschlossen (scripts/security-audit-mark-done.sh) |"

# Prüfen ob Lifecycle-Header existiert; wenn ja, anhängen innerhalb des Tabellen-Blocks.
if grep -q "^### Status-Lifecycle (dieser Task)" "$task_file"; then
    tmp="$(mktemp)"
    awk -v newrow="$lifecycle_line" '
        /^### Status-Lifecycle \(dieser Task\)/ { in_sec = 1; print; next }
        in_sec && /^## / { print newrow; print ""; in_sec = 0; print; next }
        in_sec && /^### / && !/^### Status-Lifecycle/ { print newrow; print ""; in_sec = 0; print; next }
        { print }
        END { if (in_sec) print newrow }
    ' "$task_file" > "$tmp"
    mv "$tmp" "$task_file"
fi

# 3. INDEX.md aktualisieren — wir persistieren hier bewusst nur einen Hinweis,
#    die vollständige Index-Pflege ist Driver-Arbeit (siehe INDEX.md §3 „Driver-Invarianten").
index_file="$TASKS_DIR/INDEX.md"
if [[ -f "$index_file" ]]; then
    # Ersetze den Status des Tasks in der Queue-Tabelle
    tmp="$(mktemp)"
    sed -E "s|^(\| $task_id \| )[a-z_]+( \|)|\1done\2|" "$index_file" > "$tmp"
    mv "$tmp" "$index_file"
fi

echo "OK: $task_id → status=done"
echo "    Datei: $task_file"
echo "    Index: $index_file"
echo
echo "Nächste Schritte (manuell, siehe docs/security-audit/10_fixing_and_disclosure.md §6.3):"
echo "  - Falls disclosure_state umgeschaltet werden soll (embargoed → pr_opened / merged / private):"
echo "      ${EDITOR:-vi} $task_file"
echo "  - Falls Fixture-Redaction-Policy gewechselt werden muss (embargoed → public-ready):"
echo "      ${EDITOR:-vi} $REPO_ROOT/fixtures/security/payloads/sec_audit_$nnn.json"
