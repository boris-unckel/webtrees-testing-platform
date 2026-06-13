#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# security-audit-deepdive.sh — Entry-Point für den Deep-Dive-Modus (D0–D7).
#
# Spec: docs/security-audit/06_agentic_loop_driver.md §4
#
# V1-Workflow: Dieses Skript initialisiert das Deep-Dive-Verzeichnis für einen
# einzelnen Task und prüft die Vorbedingungen. Die LLM-getriebenen Phasen
# (D1 Context, D2 Hypothesen, D3 Probe-Loop, D4 Trace-Korrelation, D5 Regression,
# D6 Fix-Draft, D7 Validation) werden **interaktiv im Claude-Code-Terminal**
# ausgeführt — siehe docs/security-audit/07_prompts/prompt_02..05.md.
#
# Aufruf:
#   ./scripts/security-audit-deepdive.sh <NNN>
#   ./scripts/security-audit-deepdive.sh --auto      # nächster queued-Task mit höchstem Score
#
# Exit Codes:
#   0 — Deep-Dive-Verzeichnis bereit, Status in_progress gesetzt
#   2 — CLI-Parse-Fehler
#   3 — Task nicht gefunden oder im falschen Status
#   4 — Pre-Flight-Check fehlgeschlagen

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
AUDIT_ROOT="$REPO_ROOT/artifacts/security-audit"
DEEPDIVE_ROOT="$AUDIT_ROOT/deepdive"
TASKS_DIR="$REPO_ROOT/docs/security-audit/tasks"

usage() {
    cat <<EOF
Usage: $(basename "$0") <NNN>
       $(basename "$0") --auto

Startet einen Deep-Dive-Lauf für SEC-AUDIT-<NNN>.

Argumente:
  NNN           Drei- bis vierstellige Task-Nummer
  --auto        Zieht den nächsten 'queued'-Task mit höchstem final_score aus
                docs/security-audit/tasks/ (nicht-blockierend bezüglich Sweep-Lock)

ENV:
  FORK_REPO     Pfad zum Fork-Repo (Default: ../webtrees-upstream/webtrees relativ zum Repo-Root)

Beispiele:
  $(basename "$0") 042
  $(basename "$0") --auto
EOF
}

if [[ $# -lt 1 || "$1" == "-h" || "$1" == "--help" ]]; then
    usage
    exit 0
fi

# --- Auto-Modus: nächsten queued-Task finden ---
resolve_auto_nnn() {
    # Greife auf Frontmatter-Felder zu, sortiere nach final_score DESC
    shopt -s nullglob
    local best_nnn=""
    local best_score="0.0"
    for f in "$TASKS_DIR"/SEC-AUDIT-*.md; do
        local status fs id
        status="$(sed -n 's/^status:[[:space:]]*\(.*\)$/\1/p' "$f" | head -n1)"
        [[ "$status" == "queued" ]] || continue
        fs="$(sed -n 's/^final_score:[[:space:]]*\(.*\)$/\1/p' "$f" | head -n1)"
        fs="${fs:-0.0}"
        id="$(sed -n 's/^id:[[:space:]]*SEC-AUDIT-\([0-9][0-9]*\).*/\1/p' "$f" | head -n1)"
        # Vergleich als Float via awk (kein bc/python nötig)
        if awk -v a="$fs" -v b="$best_score" 'BEGIN { exit !(a > b) }'; then
            best_score="$fs"
            best_nnn="$id"
        fi
    done
    shopt -u nullglob
    if [[ -z "$best_nnn" ]]; then
        echo "ERROR: Kein 'queued'-Task mit final_score > 0.0 gefunden." >&2
        exit 3
    fi
    echo "$best_nnn"
}

if [[ "$1" == "--auto" ]]; then
    nnn="$(resolve_auto_nnn)"
    echo "INFO: --auto hat SEC-AUDIT-$nnn gewählt."
else
    nnn="$1"
fi

if [[ ! "$nnn" =~ ^[0-9]{3,4}$ ]]; then
    echo "ERROR: NNN muss 3 bis 4 Ziffern sein, erhalten: '$nnn'" >&2
    exit 2
fi

task_id="SEC-AUDIT-$nnn"
today="$(date +%F)"
now="$(date +'%Y-%m-%d %H:%M')"

# --- Task-Datei finden ---
shopt -s nullglob
matches=("$TASKS_DIR/${task_id}"*.md)
shopt -u nullglob

if [[ ${#matches[@]} -eq 0 ]]; then
    echo "ERROR: Task-Datei für $task_id nicht gefunden unter $TASKS_DIR/" >&2
    echo "       Führe zunächst 'scripts/security-audit-sweep.sh' aus, um Tasks zu erzeugen." >&2
    exit 3
fi
if [[ ${#matches[@]} -gt 1 ]]; then
    echo "ERROR: Mehrere Task-Dateien für $task_id — bitte eindeutig machen:" >&2
    printf '  %s\n' "${matches[@]}" >&2
    exit 3
fi
task_file="${matches[0]}"

current_status="$(sed -n 's/^status:[[:space:]]*\(.*\)$/\1/p' "$task_file" | head -n1)"

case "$current_status" in
    queued)
        new_status="in_analysis"
        lifecycle_reason="Deep-Dive D1 gestartet (scripts/security-audit-deepdive.sh)"
        ;;
    in_analysis|in_progress|exploit_attempted|exploit_confirmed|regression_drafted|fix_in_progress|fix_committed)
        echo "INFO: $task_id Status '$current_status' — Deep-Dive kann fortgesetzt werden."
        new_status="$current_status"
        lifecycle_reason=""
        ;;
    fix_verified|awaiting_user_review|done)
        echo "ABORT: $task_id hat bereits Status '$current_status'. Kein Deep-Dive erforderlich." >&2
        exit 3
        ;;
    no_finding|needs_manual_review)
        echo "ABORT: $task_id hat Terminal-Status '$current_status'. Kein Deep-Dive." >&2
        exit 3
        ;;
    *)
        echo "ERROR: Unbekannter Status '$current_status' für $task_id" >&2
        exit 3
        ;;
esac

# --- Pre-Flight: HALT-Flag ---
halt_flag="$AUDIT_ROOT/HALT_CRITICAL.flag"
if [[ -e "$halt_flag" ]]; then
    echo "WARN: Halt-Flag aktiv ($halt_flag) — Deep-Dive nur mit User-Freigabe fortsetzen." >&2
fi

# --- Deep-Dive-Verzeichnis anlegen ---
dd_dir="$DEEPDIVE_ROOT/$nnn"
mkdir -p "$dd_dir"
echo "Deep-Dive-Verzeichnis: $dd_dir"

# Kontext-Datei vorbereiten (wenn noch nicht vorhanden)
context_file="$dd_dir/context.md"
if [[ ! -e "$context_file" ]]; then
    cat > "$context_file" <<EOF
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Deep-Dive Context — $task_id

- **Started:** $now
- **Task file:** \`$(realpath --relative-to="$REPO_ROOT" "$task_file")\`
- **Source file:** (wird in D1 aus Task-Frontmatter übernommen)

## Ziel dieser Datei

Kumulativer Kontext über alle Probe-Iterationen. Wird in D1 initial gefüllt
(Prompt: 07_prompts/prompt_02_whitebox_deep_dive.md), danach pro Iteration
erweitert. Ältere Iterationen werden nach 5 Runden auf Zusammenfassungen verdichtet
(siehe 06_agentic_loop_driver.md §5 "Context-Management").

## D1 — Context-Extraktion

<TODO: Durch prompt_02 ausgefüllt.>

## D2 — Hypothesen

Siehe \`hypotheses.md\` im selben Verzeichnis.

## Probe-Log

<Wird pro Iteration von prompt_03/prompt_04 angehängt.>
EOF
    echo "OK: $context_file initialisiert."
fi

# --- Status-Übergang in Task-Datei ---
if [[ "$current_status" != "$new_status" ]]; then
    tmp="$(mktemp)"
    awk -v today="$today" -v ns="$new_status" '
        /^status:/       { print "status: " ns; next }
        /^last_updated:/ { print "last_updated: " today; next }
                         { print }
    ' "$task_file" > "$tmp"
    mv "$tmp" "$task_file"

    # Lifecycle-Eintrag
    lifecycle_line="| $now | $new_status | $lifecycle_reason |"
    if grep -q "^### Status-Lifecycle (dieser Task)" "$task_file"; then
        tmp="$(mktemp)"
        awk -v newrow="$lifecycle_line" '
            { print }
            /^### Status-Lifecycle \(dieser Task\)/ { found=1 }
            END { if (found) print newrow }
        ' "$task_file" > "$tmp"
        mv "$tmp" "$task_file"
    fi

    echo "OK: $task_id → status=$new_status"
fi

# --- Instruktionen drucken ---
echo
echo "========================================================================"
echo "Deep-Dive $task_id bereit."
echo
echo "Interaktive Phasen (Claude Code im selben Terminal öffnen):"
echo
echo "  D1 Context:       Prompt: docs/security-audit/07_prompts/prompt_02_whitebox_deep_dive.md"
echo "                    Ziel:   $dd_dir/context.md"
echo
echo "  D2 Hypothesen:    Prompt: docs/security-audit/07_prompts/prompt_02_whitebox_deep_dive.md §3"
echo "                    Ziel:   $dd_dir/hypotheses.md"
echo
echo "  D3 Probe-Loop:    Prompt: docs/security-audit/07_prompts/prompt_03_probe_construction.md"
echo "                    Ziel:   $dd_dir/probe_H<n>_iter<i>/decision.md"
echo
echo "  D4 Trace-Korr.:   Prompt: docs/security-audit/07_prompts/prompt_04_trace_correlation.md"
echo "                    Trace:  \$WEBTREES_SECURITY_TRACE=1 + X-Audit-Probe Header"
echo
echo "  D5 Regression:    Testklasse: layer3-integration/tests/Security/SecAudit${nnn}Test.php"
echo "                    Fixture:    fixtures/security/payloads/sec_audit_${nnn}.json"
echo "                    Runner:     make test-integration-security-${nnn}"
echo
echo "  D6 Fix-Draft:     Fork-Branch: security-audit-${nnn}-<slug>"
echo "                    Basis:       5349_add_tests"
echo "                    Commit:      GPG-signed, Signed-off-by (siehe 10_fixing_and_disclosure.md §4)"
echo
echo "  D7 Validation:    Prompt: docs/security-audit/07_prompts/prompt_05_validation.md"
echo "                    Ziel:   $dd_dir/validation.md"
echo "                    Gate:   make test-integration-security-${nnn} (fork) muss grün sein"
echo
echo "Nach fix_verified:"
echo "  ./scripts/security-audit-mark-done.sh ${nnn}   # nach User-Review"
echo "========================================================================"
