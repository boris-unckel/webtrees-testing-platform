#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# security-audit-sweep.sh — Entry-Point für den Sweep-Modus (S0–S8).
#
# Spec: docs/security-audit/06_agentic_loop_driver.md §3
#       docs/security-audit/04_triage_pipeline.md T0/T1/T2/T3
#
# V1-Workflow (Initial-Setup): Dieses Skript implementiert die **mechanischen**
# Phasen (S0 Pre-Flight, S1 Trace-Enable, S7 Task-Sync-Vorbereitung, S8 Summary).
# Die LLM-gestützten Phasen (S3 T1-Triage via Sonnet, spätere Hypothesen-Runden)
# werden durch **interaktiven Claude-Code-Einsatz** im gleichen Terminal gefahren
# — der Driver druckt die nötigen Prompt-Pfade und ruft keine eigenen LLM-APIs.
#
# Aufruf:
#   ./scripts/security-audit-sweep.sh
#   ./scripts/security-audit-sweep.sh --run-id 2026-04-08T14-00-00
#   ./scripts/security-audit-sweep.sh --dry-run
#
# Exit Codes:
#   0   — Sweep sauber initialisiert, Driver-Phasen bereit für Claude-Invocation
#   2   — CLI-Parse-Fehler
#   3   — Pre-Flight-Check fehlgeschlagen (Stack nicht healthy, Lock aktiv, etc.)
#   4   — Fork-Repo in unerwartetem Zustand

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
AUDIT_ROOT="$REPO_ROOT/artifacts/security-audit"
TASKS_DIR="$REPO_ROOT/docs/security-audit/tasks"
COMPOSE="${COMPOSE:-podman-compose}"

run_id=""
dry_run=0

usage() {
    cat <<EOF
Usage: $(basename "$0") [OPTIONS]

Startet einen Sweep-Lauf (Batch-Triage nach docs/security-audit/06_agentic_loop_driver.md §3).

Optionen:
  --run-id <iso-ts>   Explizite Run-ID setzen (Default: current UTC timestamp)
  --dry-run           Nur Pre-Flight + Instruktionen drucken, keine Artefakte schreiben
  -h, --help          Diese Hilfe

ENV:
  COMPOSE             Compose-CLI (Default: podman-compose)

Beispiel:
  $(basename "$0")
  $(basename "$0") --run-id 2026-04-08T14-00-00
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --run-id)
            run_id="$2"
            shift 2
            ;;
        --dry-run)
            dry_run=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "ERROR: Unbekannte Option '$1'" >&2
            usage >&2
            exit 2
            ;;
    esac
done

if [[ -z "$run_id" ]]; then
    run_id="$(date -u +'%Y-%m-%dT%H-%M-%S')"
fi

# ---- Phase S0: Pre-Flight ----
echo "== Phase S0: Pre-Flight =="

# HALT-Flag prüfen
halt_flag="$AUDIT_ROOT/HALT_CRITICAL.flag"
if [[ -e "$halt_flag" ]]; then
    echo "ABORT: Halt-Flag aktiv — $halt_flag" >&2
    echo "       Ein kritisches Finding (Visitor→Sandbox-Escape) wartet auf User-Review." >&2
    echo "       Siehe docs/security-audit/10_fixing_and_disclosure.md §8." >&2
    exit 3
fi

# Advisory-Lock prüfen
lock_file="$AUDIT_ROOT/.lock"
if [[ -e "$lock_file" ]]; then
    echo "ABORT: Advisory-Lock aktiv — $lock_file" >&2
    echo "       Ein anderer Audit-Lauf ist bereits aktiv." >&2
    echo "       Wenn sicher: rm '$lock_file'" >&2
    exit 3
fi

# Stack-Health — podman-compose ps nimmt keinen Service-Filter-Arg,
# also parsen wir die Full-Output-Tabelle und matchen auf die NAMES-Spalte.
compose_ps="$("$COMPOSE" ps 2>/dev/null || true)"
if ! awk '$NF == "webtrees"' <<<"$compose_ps" | grep -qE 'Up|running'; then
    echo "ABORT: webtrees-Container nicht aktiv — 'make up' und 'make setup' zuerst ausführen." >&2
    exit 3
fi
if ! awk '$NF == "mysql"' <<<"$compose_ps" | grep -qE 'Up|running'; then
    echo "ABORT: mysql-Container nicht aktiv." >&2
    exit 3
fi

# Fork-Repo-Zustand prüfen
fork_repo="${FORK_REPO:-$(cd "$REPO_ROOT/.." && pwd)/webtrees-upstream/webtrees}"
if [[ -d "$fork_repo/.git" ]]; then
    pushd "$fork_repo" >/dev/null
    if [[ -n "$(git status --porcelain)" ]]; then
        echo "ABORT: Fork-Repo hat uncommittete Änderungen — $fork_repo" >&2
        exit 4
    fi
    popd >/dev/null
fi

echo "OK: Pre-Flight sauber."

# ---- Run-Directory anlegen ----
run_dir="$AUDIT_ROOT/$run_id"

if [[ $dry_run -eq 0 ]]; then
    # Lock setzen
    echo "$$" > "$lock_file"
    trap 'rm -f "$lock_file"' EXIT

    mkdir -p "$run_dir" "$run_dir/tasks"
    echo "Run-Directory: $run_dir"
fi

# ---- Phase S1: Trace-Middleware-Vorbereitung ----
echo
echo "== Phase S1: Trace-Middleware-Verifikation =="
echo "   Die SecurityTraceMiddleware ist per compose.yaml gemountet und wird mit"
echo "   WEBTREES_SECURITY_TRACE=1 aktiviert. Der Sweep setzt diese ENV nicht"
echo "   global — einzelne Probe-Runs setzen sie per 'exec -e ...' im Deep-Dive."

# ---- Phase S2: T0 Inventarisierung ----
echo
echo "== Phase S2: T0 Inventarisierung (mechanisch) =="
echo "   Die mechanische T0-Analyse ist in docs/security-audit/04_triage_pipeline.md §2"
echo "   spezifiziert. Im V1-Workflow führt sie der User interaktiv per Claude Code durch:"
echo
echo "   Prompt-Referenzen:"
echo "     - docs/security-audit/04_triage_pipeline.md §2  (Grep-Patterns für input_sinks/db_sinks/dangerous_functions)"
echo "     - Scope-Dateien: app/Http/RequestHandlers/*.php, app/Http/Middleware/*.php,"
echo "                      app/Services/*.php, app/Module/*.php, app/Factories/*.php,"
echo "                      app/Http/Exceptions/*.php, app/Auth.php, app/Validator.php"
echo
if [[ $dry_run -eq 0 ]]; then
    t0_target="$run_dir/t0_signals.json"
    echo "   Ziel: $t0_target"
fi

# ---- Phase S3: T1 LLM-Triage ----
echo
echo "== Phase S3: T1 LLM-Triage (Sonnet) =="
echo "   Prompt: docs/security-audit/07_prompts/prompt_01_triage_llm.md"
if [[ $dry_run -eq 0 ]]; then
    t1_target="$run_dir/t1_llm_scores.json"
    echo "   Ziel: $t1_target"
fi

# ---- Phase S4: T2 Track-Zuordnung ----
echo
echo "== Phase S4: T2 Track-Zuordnung (mechanisch) =="
echo "   Regeln: docs/security-audit/04_triage_pipeline.md §4"
if [[ $dry_run -eq 0 ]]; then
    t2_target="$run_dir/t2_tracks.json"
    echo "   Ziel: $t2_target"
fi

# ---- Phase S5: T3 Priorisierung ----
echo
echo "== Phase S5: T3 Priorisierung =="
echo "   Formel: final_score = 0.25*crap + 0.15*inputs + 0.15*db + 0.25*danger*reach + 0.20*llm"
echo "   Cutoff: final_score < 0.25 → kein Task"
if [[ $dry_run -eq 0 ]]; then
    echo "   Ziel: $run_dir/priorities.md + $run_dir/task_deltas.json"
fi

# ---- Phase S6: Task-Sync ----
echo
echo "== Phase S6: Task-Sync =="
echo "   Task-Dateien: $TASKS_DIR/SEC-AUDIT-<NNN>_<slug>.md (Template: _template.md)"
echo "   Index:        $TASKS_DIR/INDEX.md"

# ---- Phase S7: Erste Hypothesen-Runde ----
echo
echo "== Phase S7: Erste Hypothesen-Runde (optional, Opus) =="
echo "   Prompt: docs/security-audit/07_prompts/prompt_02_whitebox_deep_dive.md"

# ---- Phase S8: Summary ----
echo
echo "== Phase S8: Summary =="
if [[ $dry_run -eq 0 ]]; then
    summary_file="$run_dir/run-summary.md"
    cat > "$summary_file" <<EOF
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Sweep Run Summary — $run_id

- **Started:** $(date -u +'%Y-%m-%dT%H:%M:%SZ')
- **Status:** initialized (awaiting Claude-interactive phases S2–S7)
- **Run dir:** \`$run_dir\`
- **Initiator:** scripts/security-audit-sweep.sh

## Pre-Flight (S0)

- [x] Halt-Flag: absent
- [x] Advisory-Lock: claimed
- [x] webtrees: up
- [x] mysql: up
- [x] Fork-Repo: clean

## Next Steps

Siehe docs/security-audit/06_agentic_loop_driver.md §3 für die Reihenfolge S2 → S8.
Die LLM-Phasen (S3, S7) werden **manuell im Claude-Code-Terminal** gefahren,
dieses Skript hat keine LLM-API-Invokation.
EOF
    echo "OK: $summary_file erzeugt."
fi

echo
echo "========================================================================"
echo "Sweep-Run $run_id initialisiert."
echo
echo "Weiter mit interaktivem Claude-Code-Lauf:"
echo "  1. T0 Inventarisierung (Bash/Grep) — siehe 04_triage_pipeline.md §2"
echo "  2. T1 LLM-Triage — 07_prompts/prompt_01_triage_llm.md"
echo "  3. T2 Track-Zuordnung — 04_triage_pipeline.md §4"
echo "  4. T3 Scoring + Task-Dateien — 04_triage_pipeline.md §5"
echo "  5. Deep-Dive auf Top-Task: ./scripts/security-audit-deepdive.sh <NNN>"
echo "========================================================================"
