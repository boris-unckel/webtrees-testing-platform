#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Orchestrator-Lifecycle-Hilfsskript für die Portierung
# port-layer2-test-doubles → Layer 3.
#
# Vier Operationen rund um den port-worker-Aufruf:
#
#   start  <id>                                            (vor dem Worker)
#   done   <id> <decision> <target> <methods> <lines> [notes]   (Worker validated=true)
#   skip   <id> <target> [notes]                           (decision=skip_already_ported)
#   failed <id> <failure_reason>                           (Worker validated=false)
#
# Manifest-Update atomar (write-tmp + mv). Audit-Log append-only.
# JSON-Encoding der Strings via jq, damit Doppel-Quotes und Backslashes
# sicher escapt werden.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
MANIFEST="${PORT_HELPER_MANIFEST:-$SCRIPT_DIR/manifest.jsonl}"
AUDIT="${PORT_HELPER_AUDIT:-$SCRIPT_DIR/audit.log}"

now_utc() { date -u +%Y-%m-%dT%H:%M:%SZ; }

# Bash-String → gequoteter JSON-Stringliteral.
jq_str() { jq -Rn --arg v "$1" '$v'; }

# Leerer Bash-String → "null", sonst gequoteter JSON-Stringliteral.
jq_str_or_null() {
    if [[ -z "${1:-}" ]]; then printf 'null'; else jq_str "$1"; fi
}

# Manifest-Feld als Roh-String (leer, falls null oder Eintrag fehlt).
read_field() {
    local id="$1" field="$2"
    jq -r --arg id "$id" "select(.id == \$id) | .${field} // \"\"" "$MANIFEST"
}

# Manifest-Feld als JSON-Token ("..." oder null).
read_field_json() {
    local id="$1" field="$2"
    local out
    out=$(jq -c --arg id "$id" "select(.id == \$id) | .${field}" "$MANIFEST")
    if [[ -z "$out" ]]; then printf 'null'; else printf '%s' "$out"; fi
}

# Ersetzt die Manifest-Zeile zur id atomar.
# Argumente: id status decision_json target_json started_at_json finished_at_json notes_json
write_manifest_line() {
    local id="$1" status="$2" decision="$3" target="$4"
    local started_at="$5" finished_at="$6" notes="$7"

    local source category
    source=$(read_field "$id" source)
    if [[ -z "$source" ]]; then
        printf 'Fehler: id %s nicht im Manifest.\n' "$id" >&2
        exit 2
    fi
    category=$(read_field "$id" category)

    local tmp="${MANIFEST}.tmp"
    local newline_file="${MANIFEST}.newline"

    # Neue Zeile in eine Hilfsdatei schreiben, damit awk sie per getline
    # ohne Backslash-Escape-Verarbeitung einliest. `awk -v` würde \" zu "
    # umwandeln und das JSONL kaputt machen.
    printf '{"id": %s, "source": %s, "category": %s, "status": %s, "decision": %s, "target": %s, "started_at": %s, "finished_at": %s, "notes": %s}\n' \
        "$(jq_str "$id")" \
        "$(jq_str "$source")" \
        "$(jq_str "$category")" \
        "$(jq_str "$status")" \
        "$decision" "$target" "$started_at" "$finished_at" "$notes" > "$newline_file"

    awk -v target_id="$id" -v newline_file="$newline_file" '
        BEGIN { getline new_content < newline_file; close(newline_file) }
        match($0, /"id": "([^"]+)"/, arr) {
            if (arr[1] == target_id) {
                print new_content
                found = 1
                next
            }
        }
        { print }
        END {
            if (!found) {
                printf "Fehler: id %s nicht gefunden\n", target_id > "/dev/stderr"
                exit 2
            }
        }
    ' "$MANIFEST" > "$tmp"

    rm -f "$newline_file"
    mv "$tmp" "$MANIFEST"
}

append_audit() { printf '%s\n' "$1" >> "$AUDIT"; }

usage() {
    cat <<'USAGE'
Verwendung:
  port-helper.sh start  <id>
  port-helper.sh done   <id> <decision> <target> <methods> <lines> [notes]
  port-helper.sh skip   <id> <target> [notes]
  port-helper.sh failed <id> <failure_reason>

start   Manifest <id>: status=in_progress, started_at=jetzt.
done    Manifest <id>: status=done, decision/target/finished_at/notes.
        Audit: action=done, validated=true.
skip    Manifest <id>: status=done, decision=skip_already_ported.
        Audit: action=skip_already_ported.
failed  Manifest <id>: status=failed, finished_at, notes=failure_reason.
        Audit: action=failed, failure_reason.

Umgebungsvariablen:
  PORT_HELPER_MANIFEST  Pfad zur manifest.jsonl (Standard: neben dem Skript)
  PORT_HELPER_AUDIT     Pfad zur audit.log     (Standard: neben dem Skript)
USAGE
}

cmd="${1:-}"
shift || true

case "$cmd" in
    start)
        id="${1:?Verwendung: start <id>}"
        ts=$(now_utc)
        write_manifest_line "$id" "in_progress" \
            "null" "null" "$(jq_str "$ts")" "null" "null"
        ;;

    done)
        id="${1:?Verwendung: done <id> <decision> <target> <methods> <lines> [notes]}"
        decision="${2:?decision fehlt}"
        target="${3:?target fehlt}"
        methods_added="${4:?methods_added fehlt}"
        lines_added="${5:?lines_added fehlt}"
        notes="${6:-}"
        ts=$(now_utc)

        # decision muss enrich oder new sein (skip_already_ported läuft über skip).
        if [[ "$decision" != "enrich" && "$decision" != "new" ]]; then
            printf 'Fehler: decision muss "enrich" oder "new" sein (für skip nutze: skip).\n' >&2
            exit 1
        fi
        # methods_added und lines_added müssen Integer sein.
        if ! [[ "$methods_added" =~ ^[0-9]+$ ]]; then
            printf 'Fehler: methods_added muss eine Ganzzahl sein.\n' >&2
            exit 1
        fi
        if ! [[ "$lines_added" =~ ^-?[0-9]+$ ]]; then
            printf 'Fehler: lines_added muss eine Ganzzahl sein.\n' >&2
            exit 1
        fi

        started_at=$(read_field_json "$id" started_at)

        write_manifest_line "$id" "done" \
            "$(jq_str "$decision")" \
            "$(jq_str "$target")" \
            "$started_at" \
            "$(jq_str "$ts")" \
            "$(jq_str_or_null "$notes")"

        line=$(printf '{"ts": %s, "id": %s, "action": "done", "decision": %s, "target": %s, "methods_added": %s, "lines_added": %s, "validated": true, "notes": %s}' \
            "$(jq_str "$ts")" \
            "$(jq_str "$id")" \
            "$(jq_str "$decision")" \
            "$(jq_str "$target")" \
            "$methods_added" \
            "$lines_added" \
            "$(jq_str_or_null "$notes")")
        append_audit "$line"
        ;;

    skip)
        id="${1:?Verwendung: skip <id> <target> [notes]}"
        target="${2:?target fehlt}"
        notes="${3:-source_is_unfilled_stub}"
        ts=$(now_utc)

        started_at=$(read_field_json "$id" started_at)

        write_manifest_line "$id" "done" \
            "$(jq_str "skip_already_ported")" \
            "$(jq_str "$target")" \
            "$started_at" \
            "$(jq_str "$ts")" \
            "$(jq_str "$notes")"

        line=$(printf '{"ts": %s, "id": %s, "action": "skip_already_ported", "decision": "skip_already_ported", "target": %s, "notes": %s}' \
            "$(jq_str "$ts")" \
            "$(jq_str "$id")" \
            "$(jq_str "$target")" \
            "$(jq_str "$notes")")
        append_audit "$line"
        ;;

    failed)
        id="${1:?Verwendung: failed <id> <failure_reason>}"
        failure_reason="${2:?failure_reason fehlt}"
        ts=$(now_utc)

        started_at=$(read_field_json "$id" started_at)

        write_manifest_line "$id" "failed" \
            "null" "null" "$started_at" "$(jq_str "$ts")" \
            "$(jq_str "$failure_reason")"

        line=$(printf '{"ts": %s, "id": %s, "action": "failed", "failure_reason": %s}' \
            "$(jq_str "$ts")" \
            "$(jq_str "$id")" \
            "$(jq_str "$failure_reason")")
        append_audit "$line"
        ;;

    ""|-h|--help|help)
        usage
        exit 0
        ;;

    *)
        printf 'Unbekanntes Kommando: %s\n\n' "$cmd" >&2
        usage >&2
        exit 1
        ;;
esac
