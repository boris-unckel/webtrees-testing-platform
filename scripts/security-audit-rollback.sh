#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# security-audit-rollback.sh — rollt einen verifizierten Fix zurück, ohne den Diff zu verlieren.
#
# Spec: docs/security-audit/10_fixing_and_disclosure.md §9
#
# Einsatz: Nach fix_verified stellt sich heraus, dass ein nicht gegateter Layer (z. B. Playwright-E2E)
# regression hat. Der User will den Commit im Fork zurücknehmen, aber den Diff erhalten, damit
# der Fix nachgebessert werden kann.
#
# Aufruf:
#   ./scripts/security-audit-rollback.sh <NNN>
#
# Vorbedingungen:
#   - Fork-Repo existiert unter $FORK_REPO (Default: ../webtrees-upstream/webtrees relativ zum Repo-Root)
#   - Aktueller HEAD ist der Fix-Commit für SEC-AUDIT-<NNN>
#   - Keine uncommitteten Änderungen im Fork
#
# Aktionen:
#   1. Vorprüfungen: Branch-Name, sauberer Working Tree, HEAD~1 ist parent
#   2. git reset --soft HEAD~1 im Fork (Diff bleibt im Index)
#   3. verification/-Artefakte unter artifacts/security-audit/deepdive/<NNN>/verification/ löschen
#   4. Task-Status zurück auf fix_in_progress

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TASKS_DIR="$REPO_ROOT/docs/security-audit/tasks"
DEEPDIVE_DIR="$REPO_ROOT/artifacts/security-audit/deepdive"
FORK_REPO="${FORK_REPO:-$(cd "$REPO_ROOT/.." && pwd)/webtrees-upstream/webtrees}"

usage() {
    cat <<EOF
Usage: $(basename "$0") <NNN>

Rollt den letzten Fix-Commit im Fork-Branch von SEC-AUDIT-<NNN> per 'git reset --soft' zurück.

ENV:
  FORK_REPO    Pfad zum Fork-Repo (Default: $FORK_REPO)

Argument:
  NNN          Drei- bis vierstellige Task-Nummer

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
now="$(date +'%Y-%m-%d %H:%M')"
today="$(date +%F)"

# 1. Fork-Repo prüfen
if [[ ! -d "$FORK_REPO/.git" ]]; then
    echo "ERROR: Fork-Repo nicht gefunden unter $FORK_REPO" >&2
    echo "       Setze FORK_REPO=<pfad> als ENV oder passe den Default in diesem Skript an." >&2
    exit 3
fi

cd "$FORK_REPO"

current_branch="$(git rev-parse --abbrev-ref HEAD)"
if [[ ! "$current_branch" =~ ^security-audit-${nnn}- ]]; then
    echo "ERROR: Aktueller Branch '$current_branch' passt nicht zu security-audit-${nnn}-*" >&2
    echo "       Wechsle zunächst auf den richtigen Branch: git checkout security-audit-${nnn}-<slug>" >&2
    exit 4
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: Fork-Repo hat uncommittete Änderungen. Commit oder stash diese zuerst." >&2
    git status --short >&2
    exit 5
fi

# Letzten Commit identifizieren
last_commit_subject="$(git log -1 --pretty=%s)"
last_commit_hash="$(git log -1 --pretty=%H)"

echo "Fork:       $FORK_REPO"
echo "Branch:     $current_branch"
echo "Rollback:   $last_commit_hash  $last_commit_subject"
echo

# Safety-Confirmation (nur interaktiv)
if [[ -t 0 && "${SECURITY_AUDIT_ROLLBACK_YES:-}" != "1" ]]; then
    read -r -p "Weiter mit 'git reset --soft HEAD~1'? [y/N] " answer
    case "$answer" in
        y|Y|yes|YES) ;;
        *) echo "Abgebrochen."; exit 0 ;;
    esac
fi

# 2. Soft-Reset
git reset --soft HEAD~1
echo "OK: git reset --soft durchgeführt. Diff ist im Index (git diff --cached)."

# 3. verification/-Artefakte löschen
ver_dir="$DEEPDIVE_DIR/$nnn/verification"
if [[ -d "$ver_dir" ]]; then
    rm -rf "$ver_dir"
    echo "OK: $ver_dir gelöscht."
fi

# 4. Task-Status zurück auf fix_in_progress
cd "$REPO_ROOT"
shopt -s nullglob
matches=("$TASKS_DIR/${task_id}"*.md)
shopt -u nullglob

if [[ ${#matches[@]} -eq 1 ]]; then
    task_file="${matches[0]}"
    tmp="$(mktemp)"
    awk -v today="$today" '
        /^status:/       { print "status: fix_in_progress"; next }
        /^last_updated:/ { print "last_updated: " today; next }
                         { print }
    ' "$task_file" > "$tmp"
    mv "$tmp" "$task_file"
    echo "OK: $task_id → status=fix_in_progress"

    # Lifecycle-Eintrag
    lifecycle_line="| $now | fix_in_progress | Rollback durch scripts/security-audit-rollback.sh |"
    if grep -q "^### Status-Lifecycle (dieser Task)" "$task_file"; then
        tmp="$(mktemp)"
        awk -v newrow="$lifecycle_line" '
            { print }
            /^### Status-Lifecycle \(dieser Task\)/ { found=1 }
            END { if (found) print newrow }
        ' "$task_file" > "$tmp"
        mv "$tmp" "$task_file"
    fi
else
    echo "WARN: Task-Datei für $task_id nicht eindeutig gefunden — Status manuell aktualisieren:" >&2
    echo "      ${EDITOR:-vi} $TASKS_DIR/${task_id}*.md" >&2
fi

echo
echo "Nächste Schritte:"
echo "  - Diff inspizieren:      cd $FORK_REPO && git diff --cached"
echo "  - Fix nachbessern und neu committen (GPG-signed, siehe 10_fixing_and_disclosure.md §4)"
echo "  - Dann Validation erneut triggern:"
echo "      WEBTREES_SOURCE=$FORK_REPO make test-integration-security-$nnn"
