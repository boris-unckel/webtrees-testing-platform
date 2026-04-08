<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Security-Audit Task Index

Dieser Index wird vom Sweep-Driver (Phase S6 in `06_agentic_loop_driver.md` §3) und Deep-Dive-Driver (bei Status-Wechseln) **automatisch** aktualisiert. Manuelle Änderungen sind erlaubt, werden aber beim nächsten Driver-Lauf überschrieben, wenn sie mit dem tatsächlichen Frontmatter der Task-Dateien kollidieren.

## Legende — Status

| Status | Bedeutung |
|---|---|
| `queued` | Task aus T3 erzeugt, noch kein Deep-Dive |
| `in_analysis` | Deep-Dive Phase D1 (Context-Erzeugung) |
| `in_progress` | D2 läuft oder abgeschlossen, Probe-Loop bereit |
| `exploit_attempted` | D3 mindestens 1 Probe-Run ausgeführt |
| `exploit_confirmed` | D4 Hypothese durch Trace bestätigt |
| `regression_drafted` | D5 Regression-Testklasse erzeugt |
| `fix_in_progress` | D6 aktiv |
| `fix_committed` | D6 Diff committet in Fork |
| `fix_verified` | D7 Validation grün |
| `awaiting_user_review` | User-Sichtung ausstehend |
| `done` | Vollständig abgeschlossen (inkl. User-Review) |
| `no_finding` | Alle Hypothesen rejected |
| `needs_manual_review` | Driver hat aufgegeben (Abort-Bedingung erreicht) |

## Legende — Track

- `non-admin` = OWASP-Top-10-Sichtbarkeit für Visitor/Member/Editor
- `sandbox-escape` = PHP→Shell/Filesystem/Netzwerk-Ausbruch (auch Admin)
- `both` = beides

## Legende — Impact

- `visitor-sandbox-escape` (MAX) — Unauthentifiziert → Container-Shell
- `visitor-rce` — Unauthentifiziert → PHP-Execution ohne Sandbox-Escape
- `non-admin-rce` — Authentifizierter Non-Admin → PHP-Execution
- `auth-bypass` — Privilege Escalation zwischen Rollen
- `stored-xss`, `sqli-readonly`, `sqli-readwrite`, `path-traversal-read`, `path-traversal-write`, `csrf-state-change`, `info-disclosure`, `ssrf`, `deserialization` — siehe `02_threat_model.md` §4

## Aktive Queue

| ID | Status | Track | Impact | Final Score | Datei | Verticals | Letzte Änderung |
|---|---|---|---|---|---|---|---|
| *(leer — SEC-AUDIT-001 verschoben nach „Abgeschlossen")* | | | | | | | |

## Abgeschlossen

| ID | Final-Status | Impact | Disclosure | Closed at |
|---|---|---|---|---|
| SEC-AUDIT-001 | fix_verified | stored-xss (defense-in-depth-gap) | ready_for_manual_pr | 2026-04-08 |

## Needs Manual Review

| ID | Status | Grund | Seit |
|---|---|---|---|
| *(leer bei Initial-Setup)* | | | |

## Aggregat-Zahlen

- Tasks gesamt: 1
- In Queue: 0
- In Deep-Dive: 0
- Fix verified: 1 (SEC-AUDIT-001, Fork-Commit b2dc869b90, bereit für manuelle PR)
- Done: 0
- Dropped: 3 (SetupWizard, UpgradeWizardStep, ContactAction — siehe run-2026-04-08T19-01-49/priorities.md)
- Critical Findings (visitor-sandbox-escape): 0
- Halt-Flag aktiv: nein

## Driver-Invarianten

Der Driver prüft bei jedem Lauf:

1. **Eindeutige IDs:** Jede `SEC-AUDIT-<NNN>` kommt genau einmal in dieser Datei vor. Doppelte Einträge = Index-Korruption, Driver stoppt.
2. **Status-Konsistenz:** Der Status in dieser Tabelle muss mit dem `status`-Feld im Frontmatter der Task-Datei übereinstimmen. Bei Divergenz: Frontmatter ist die Quelle der Wahrheit, Index wird korrigiert.
3. **Keine Lücken bei abgeschlossenen Tasks:** Eine Task im Abschnitt „Abgeschlossen" hat auch eine Datei unter `docs/security-audit/tasks/SEC-AUDIT-<NNN>.md` mit Status `done` oder `dropped`.
4. **Halt-Flag-Spiegelung:** Wenn `artifacts/security-audit/HALT_CRITICAL.flag` existiert, ist „Halt-Flag aktiv: ja" im Aggregat-Block und der Sweep pausiert.

## Maschinell lesbar (optional)

Der Driver kann zusätzlich eine JSON-Spiegelung unter `docs/security-audit/tasks/INDEX.json` pflegen, die dieselben Daten in strukturierter Form enthält. Diese Datei ist **sekundär** — bei Konflikt gewinnt die Markdown-Version. Sie wird bei jeder Index-Aktualisierung neu aus dem Markdown generiert (nicht umgekehrt).

## Cross-Referenzen

- Task-Template: `_template.md`
- Task-Erzeugung: `../04_triage_pipeline.md` §4
- Status-Übergänge: `../06_agentic_loop_driver.md` §4
- Fix-Workflow: `../10_fixing_and_disclosure.md`
