#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# security-audit-draft-finding.sh — erzeugt eine Vorbefüllung des Finding-Reports
# aus dem Template in docs/security-audit/11_finding_report_template.md.
#
# Spec: docs/security-audit/11_finding_report_template.md §1
#
# Aufruf:
#   ./scripts/security-audit-draft-finding.sh <NNN>
#
# Output:
#   docs/security-audit/findings/FINDING-SEC-AUDIT-<NNN>.md
#
# Der Report bleibt in weiten Teilen Handarbeit — dieses Skript füllt nur die
# mechanisch extrahierbaren Felder aus Frontmatter und Tasks-Datei vor:
#   - Finding-ID
#   - Report-Datum
#   - Status (aus Frontmatter `disclosure_state`)
#   - Track (aus Frontmatter)
#   - Impact (aus Frontmatter, falls vorhanden)
#   - Fix-Branch (aus Frontmatter `fix_branch`)
#
# Idempotent: existierender Report wird **nicht** überschrieben, sondern meldet Fehler.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TASKS_DIR="$REPO_ROOT/docs/security-audit/tasks"
FINDINGS_DIR="$REPO_ROOT/docs/security-audit/findings"

usage() {
    cat <<EOF
Usage: $(basename "$0") <NNN>

Erzeugt docs/security-audit/findings/FINDING-SEC-AUDIT-<NNN>.md aus dem Template.

Argument:
  NNN    Drei- bis vierstellige Task-Nummer

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
today="$(date +%F)"

# Task-Datei finden
shopt -s nullglob
matches=("$TASKS_DIR/${task_id}"*.md)
shopt -u nullglob

if [[ ${#matches[@]} -eq 0 ]]; then
    echo "ERROR: Keine Task-Datei für $task_id gefunden unter $TASKS_DIR/" >&2
    exit 3
fi
if [[ ${#matches[@]} -gt 1 ]]; then
    echo "ERROR: Mehrere Task-Dateien für $task_id — bitte eindeutig machen." >&2
    exit 3
fi
task_file="${matches[0]}"

extract_frontmatter_value() {
    local key="$1"
    sed -n "s/^${key}:[[:space:]]*\(.*\)$/\1/p" "$task_file" | head -n1
}

title="$(extract_frontmatter_value 'title')"
track="$(extract_frontmatter_value 'track')"
status_field="$(extract_frontmatter_value 'status')"
disclosure_state="$(extract_frontmatter_value 'disclosure_state')"
fix_branch="$(extract_frontmatter_value 'fix_branch')"

title="${title:-<kurzer, neutraler Titel>}"
track="${track:-<non-admin | sandbox-escape | both>}"
disclosure_state="${disclosure_state:-embargoed}"
fix_branch="${fix_branch:-security-audit-${nnn}-<slug>}"

# Zielverzeichnis & Datei
mkdir -p "$FINDINGS_DIR"
out_file="$FINDINGS_DIR/FINDING-${task_id}.md"

if [[ -e "$out_file" ]]; then
    echo "ERROR: $out_file existiert bereits — draft-finding überschreibt nicht." >&2
    echo "       Öffne die Datei manuell oder lösche sie, wenn ein Neu-Draft gewünscht ist." >&2
    exit 4
fi

cat > "$out_file" <<EOF
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# FINDING $task_id — $title

| Feld | Wert |
|---|---|
| Finding-ID | $task_id |
| Projekt | webtrees |
| Upstream-Repo | fisharebest/webtrees |
| Auditor | <Name des Users> |
| Report-Datum | $today |
| Status | $disclosure_state |
| Track | $track |
| Impact | <visitor-sandbox-escape \\| visitor-rce \\| non-admin-rce \\| ...> |
| Confidence | <high \\| medium \\| low> |
| Severity (CVSSv3.1) | <vector-string> / <score> |
| Affected Versions | <z. B. all up to 2.1.21> |
| Fixed in (Fork) | Branch \`$fix_branch\` on \`boris-unckel/webtrees\` |
| Fixed in (Upstream) | <PR-URL oder "pending"> |

## Zusammenfassung (1 Absatz)

<TODO: 2–4 Sätze ohne Jargon. Ziel-Lesergruppe: Upstream-Maintainer, Release-Manager.>

## Technische Beschreibung

### Angriffsvektor

<TODO: Aus artifacts/security-audit/deepdive/$nnn/hypotheses.md und validation.md übernehmen.>

### Vorbedingungen

- Auth: <TODO>
- Config: <TODO>
- Daten: <TODO>
- Timing: <TODO>

### Reproduktion

<TODO: Schritt-für-Schritt ohne Audit-Infrastruktur-Referenzen.>

### Root Cause

<TODO: Aus validation.md §Root Cause übernehmen.>

### Impact-Begründung

<TODO: Begründung der Impact-Kategorie mit Rollen-Matrix-Bezug.>

## Fix

### Patch-Beschreibung

<TODO: 1–3 Sätze über Fix-Strategie.>

### Patch (inline)

\`\`\`diff
<TODO: Fix-Diff aus dem Fork-Branch ($fix_branch), max. 30 Zeilen.>
\`\`\`

### Warum dieser Fix minimal ist

<TODO: Bezug auf Layer-2-Regression-Check aus validation.md.>

## Regression-Test

### Testklasse

\`layer3-integration/tests/Security/SecAudit${nnn}Test.php\`

### Testmethoden

| Methode | Hypothese | Oracle |
|---|---|---|
| \`test_h1_<name>\` | H1 | <assertResponseBlocked \\| assertNoSecurityTraceArtifact \\| ...> |

### Ausführung

\`\`\`bash
# Unpatched: Test schlägt fehl
WEBTREES_SOURCE=./upstream/webtrees make test-integration-security-${nnn}

# Patched: Test läuft grün
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees \\
  make test-integration-security-${nnn}
\`\`\`

## Zeitachse

| Datum | Ereignis |
|---|---|
| <YYYY-MM-DD> | Finding bestätigt (Status \`exploit_confirmed\`) |
| <YYYY-MM-DD> | Fix-Draft committet in Fork |
| <YYYY-MM-DD> | Validation abgeschlossen (Status \`fix_verified\`) |
| $today | Draft-Report erzeugt (scripts/security-audit-draft-finding.sh) |
| <YYYY-MM-DD> | PR geöffnet: <URL> |

## Danksagungen

Credits: PHPUnit, Playwright, OpenTelemetry, Claude Code.

## Embargo-Hinweis

<Nur ausfüllen, wenn Finding embargoed ist. Siehe 11_finding_report_template.md §2.>

---

**Hinweis:** Dieser Report wurde durch \`scripts/security-audit-draft-finding.sh\` vorbefüllt.
Felder mit \`<TODO: ...>\` müssen manuell aus Quelldokumenten ergänzt werden:
- \`$(realpath --relative-to="$REPO_ROOT" "$task_file")\`
- \`artifacts/security-audit/deepdive/$nnn/hypotheses.md\`
- \`artifacts/security-audit/deepdive/$nnn/validation.md\`
EOF

echo "OK: $out_file erzeugt."
echo
echo "Nächste Schritte:"
echo "  1. TODO-Felder manuell aus den Quelldokumenten ergänzen"
echo "  2. Severity (CVSSv3.1) mit Hilfe von docs/security-audit/11_finding_report_template.md §3 bestimmen"
echo "  3. Vor Publikation Review-Checkliste aus §4.2 abhaken"
