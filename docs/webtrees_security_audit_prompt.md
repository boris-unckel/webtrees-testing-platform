<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# webtrees Security Audit — Master Prompt & Navigation

Dieser Master-Prompt erweitert die generische Vorlage aus `docs/php_security_audit_suggestion.md` zu einem **iterativen, whitebox-basierten Security-Audit-Framework** für webtrees — abgestimmt auf die vorhandene Test-Infrastruktur dieses Repos und gekoppelt mit dem Fork-Repo `webtrees-upstream/webtrees` für Fix-Entwicklung.

Der Prompt ist in **11 Subdokumente** zerlegt (`docs/security-audit/`). Jedes Subdokument ist eigenständig lesbar; dieses Master-Dokument liefert den Einstieg, die Kapitelübersicht und den kanonischen Workflow.

## 1. Warum kein Blackbox-Audit

Die generische Vorlage (`docs/php_security_audit_suggestion.md`) beschreibt einen **stateless Blackbox-Flow** mit OWASP-Checkliste und 4-Phasen-LLM-Pipeline. Die Schwächen für webtrees:

- **Blackbox** ignoriert die dichte Test-Infrastruktur (Layer 2/3/4, SecurityTraceMiddleware, OTel-Traces, MySQL PerfSchema, CRAP-Report).
- **Stateless** macht Iteration auf derselben Codezeile unmöglich — jedes Finding fängt bei Null an.
- **Generisch** priorisiert nicht nach webtrees-spezifischen Hotspots (GEDCOM-Parsing, Tree-Privacy, Media-Handling).
- **Stateless-LLM-Ranking** ignoriert mechanische Signale (CRAP, Reachability, Sink-Dichte), die in Minuten statt Stunden berechnet sind.

**Antwort:** Ein **Whitebox**-Framework mit:

1. **Multi-Signal-Triage** (T0 mechanisch + T1 LLM-Overlay + T2 Track-Zuordnung + T3 Score-Aggregation).
2. **SecurityTraceMiddleware** als aktive, iterative Laufzeit-Instrumentierung.
3. **Zwei Tracks:** Non-Admin-OWASP + Admin→PHP-Sandbox-Escape, mit `visitor-sandbox-escape` als explizitem Maximum.
4. **Statusbasierte, persistente Tasks** (`SEC-AUDIT-NNN`) mit vollständigem Lifecycle.
5. **Fork-Repo-Kopplung** via `WEBTREES_SOURCE`-Override für Baseline-vs-Patched-Vergleich.
6. **Manuelle Disclosure** — kein automatischer PR, keine automatische Veröffentlichung.

## 2. Kapitel-Übersicht

| # | Datei | Inhalt |
|---|---|---|
| 01 | [scope_and_tracks](security-audit/01_scope_and_tracks.md) | Geltungsbereich, Rollen-Taxonomie, zwei Tracks, Impact-Hierarchie, Abgrenzung zu SEC-Features |
| 02 | [threat_model](security-audit/02_threat_model.md) | 7 Domänen (D-AUTH…D-INFRA), OWASP × Domain-Matrix, Datenflüsse, 12 vertikale Hypothesen V1–V12 |
| 03 | [infrastructure_usage](security-audit/03_infrastructure_usage.md) | 8 Feedback-Kanäle (OTel, PerfSchema, Coverage-Delta, SecurityTrace, Apache-Log, App-Log, MySQL-Log, Regression), Parallel-Regeln |
| 04 | [triage_pipeline](security-audit/04_triage_pipeline.md) | T0 mechanische Signale, T1 LLM-Triage (Sonnet), T2 Track-Zuordnung, T3 Score-Aggregation, Task-Erzeugung |
| 05 | [security_trace_middleware](security-audit/05_security_trace_middleware.md) | `SecurityTraceModule` Spec, PSR-15-Position, Double-Guard, JSON-Schema, OTel-Integration, Redaction |
| 06 | [agentic_loop_driver](security-audit/06_agentic_loop_driver.md) | Sweep-Modus (S0–S8), Deep-Dive-Modus (D0–D7), Status-Lifecycle, Context-Management, Parallelitäts-Budgets |
| 07 | [07_prompts/](security-audit/07_prompts/) | 5 Prompt-Templates (T1-Triage, Whitebox-Deep-Dive, Exploit-Attempt, Trace-Korrelation, Validation) |
| 08 | [layer_integration](security-audit/08_layer_integration.md) | `SecurityAuditTestCase` auf `MysqlTestCase`, Layer-3/4-Zuordnung, DataProvider-Muster, Make-Targets |
| 09 | [fixture_register](security-audit/09_fixture_register.md) | `fixtures/security/payloads/` Struktur, JSON-Schema, 12 Kategorien, 3 Redaction-Policies |
| 10 | [fixing_and_disclosure](security-audit/10_fixing_and_disclosure.md) | Fork-Branch-Naming, Fix-Draft-Regeln, Verification-Doppel-Run, manueller User-Workflow |
| 11 | [finding_report_template](security-audit/11_finding_report_template.md) | Standardisierte Report-Struktur, CVSSv3.1-Heuristik, GSA-Mapping |
| Tasks | [tasks/](security-audit/tasks/) | [`_template.md`](security-audit/tasks/_template.md) + [`INDEX.md`](security-audit/tasks/INDEX.md) |

## 3. Kanonischer Workflow

### 3.1 Initial-Setup (einmalig)

```bash
# 1. Stack starten
make up
make setup

# 2. Driver erzeugt Initial-Artefakte (idempotent):
#    - modules/security-trace/SecurityTraceModule.php  (aus 05_security_trace_middleware.md)
#    - layer3-integration/tests/Security/SecurityAuditTestCase.php  (aus 08_layer_integration.md §2)
#    - layer4-e2e/helpers/security-audit.ts            (aus 08_layer_integration.md §3.3)
#    - fixtures/security/payloads/.schema.json
#    - fixtures/security/payloads/.example.json        (Demo, nicht geladen)
#    - Makefile-Target: test-integration-security-%
#    - scripts/security-audit-mark-done.sh
#    - scripts/security-audit-rollback.sh
#    - scripts/security-audit-draft-finding.sh
#    - artifacts/security-audit/ Verzeichnisbaum
```

### 3.2 Sweep-Lauf (batch)

```bash
# Sweep durchläuft S0–S8 (siehe 06_agentic_loop_driver.md §3):
./scripts/security-audit-sweep.sh

# Ergebnis:
#   - artifacts/security-audit/triage/T0_inventory_batch_*.json
#   - artifacts/security-audit/triage/T1_llm_votes_batch_*.json (via prompt_01_triage_llm.md)
#   - artifacts/security-audit/triage/T2_track_assignment.json
#   - artifacts/security-audit/triage/T3_scored_tasks.json
#   - docs/security-audit/tasks/SEC-AUDIT-*.md (neue oder aktualisierte)
#   - docs/security-audit/tasks/INDEX.md (aktualisiert)
#   - artifacts/security-audit/sweep_summary_<ts>.md
```

Sweep ist **idempotent**: Tasks, die bereits in Deep-Dive oder weiter sind, werden nicht neu erzeugt.

### 3.3 Deep-Dive-Lauf (pro Task)

```bash
# Driver zieht den nächsten queued-Task mit höchstem final_score:
./scripts/security-audit-deepdive.sh <NNN>

# Alternativ: automatische Queue-Abarbeitung bis max_parallel erreicht:
./scripts/security-audit-deepdive.sh --auto

# Ergebnis pro Task:
#   - artifacts/security-audit/deepdive/<NNN>/context.md        (D1)
#   - artifacts/security-audit/deepdive/<NNN>/hypotheses.md     (D2, prompt_02)
#   - artifacts/security-audit/deepdive/<NNN>/probe_H<n>_iter<i>/ (D3, prompt_03)
#   - artifacts/security-audit/deepdive/<NNN>/correlation_*.md  (D4, prompt_04)
#   - layer3-integration/tests/Security/SecAudit<NNN>Test.php   (D5)
#   - fixtures/security/payloads/sec_audit_<NNN>.json           (D5)
#   - Fork-Repo: Branch security-audit-<NNN>-<slug>             (D6)
#   - artifacts/security-audit/deepdive/<NNN>/validation.md     (D7, prompt_05)
```

### 3.4 User-Review (manuell)

```bash
# 1. Finding sichten
less artifacts/security-audit/deepdive/<NNN>/validation.md
less artifacts/security-audit/deepdive/<NNN>/hypotheses.md
cat docs/security-audit/tasks/SEC-AUDIT-<NNN>.md

# 2. Regression lokal nachvollziehen (beide Runs)
WEBTREES_SOURCE=./upstream/webtrees make test-integration-security-<NNN>
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees \
  make test-integration-security-<NNN>

# 3. Fix im Fork prüfen
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
git log -1 security-audit-<NNN>-<slug>
git diff 5349_add_tests..security-audit-<NNN>-<slug>

# 4. Entscheidung: PR öffnen / im Fork belassen / zurückziehen
#    (manuelle gh-Befehle, siehe 10_fixing_and_disclosure.md §6.3)

# 5. Task als done markieren
./scripts/security-audit-mark-done.sh <NNN>
```

## 4. Planungs-Entscheidungen (aus Runde 1 und 2)

Diese Entscheidungen liegen der gesamten Architektur zugrunde und sind in den Subdokumenten referenziert:

| ID | Frage | Entscheidung | Wirkung auf |
|---|---|---|---|
| F1 | Primäres Ausgabe-Artefakt | Strukturiertes Dokument, in Subdokumente zerlegt | Diese Datei + `security-audit/` |
| F2 | Scope-Modell | Non-Admin-OWASP **plus** Admin→PHP-Sandbox-Escape als expliziter Track | `01_scope_and_tracks.md` |
| F3 | Trace-Quelle | Vorhandene OTel-Infrastruktur + neue `SecurityTraceMiddleware` | `05_security_trace_middleware.md` |
| F4 | Triage-Priorisierung | Mechanisch (CRAP + Sinks + Reachability) **plus** LLM-Overlay | `04_triage_pipeline.md` |
| F5 | Fix-Workflow | V1-Workflow: Fork-Branch + manuelle PR-Öffnung, ohne Schritte 10/11 automatisiert | `10_fixing_and_disclosure.md` |
| F6 | Task-Persistenz | Statusbasiert, pro Task eigene Datei, Index-File | `tasks/_template.md`, `tasks/INDEX.md` |
| F7 | Probe-Exklusivität | Ein Probe-Run systemweit (konsistent mit CLAUDE.md) | `06_agentic_loop_driver.md` §5 |
| F8 | Admin-Fälle | Nicht ausgeschlossen, aber nur im Track `sandbox-escape` | `01_scope_and_tracks.md` |
| V1 | User-Review-Gate | Driver stoppt nach `fix_verified`, User macht Schritte 10 (PR) und 11 (gh) manuell | `10_fixing_and_disclosure.md` §6 |
| V2 | Impact-Maximum | `visitor-sandbox-escape` als MAX-Kategorie | `01_scope_and_tracks.md` §3, `10_fixing_and_disclosure.md` §8 |
| V4 | Status-Update-Timing | „Sofort nach Erledigung, nicht erst am Ende" | Alle `07_prompts/*.md` §7, `06_agentic_loop_driver.md` §4 |

## 5. Exklusivitäts- und Parallelitäts-Regeln

Aus CLAUDE.md übernommen und für den Audit geschärft (`06_agentic_loop_driver.md` §5):

| Ressource | Regel |
|---|---|
| **Probe-Run** (HTTP-Requests gegen den Stack) | **Exklusiv systemweit** — nur ein Run gleichzeitig, auch über Tasks hinweg. |
| **Make-Testlauf** (`make test-integration` etc.) | Exklusiv systemweit, wie bestehende CLAUDE.md-Regel. |
| **Sonnet-Triage-Calls** (`prompt_01`, `prompt_04`) | Max. **4** parallel. |
| **Opus-Deep-Dive-Calls** (`prompt_02`, `prompt_03`, `prompt_05`) | Max. **2** parallel. |
| **SecurityTraceMiddleware-Aktivierung** | Pro Container-Lebenszeit nur einmal aktiviert; Deaktivierung in `tearDown`. |
| **Fork-Repo Branch-Erzeugung** | Sequenziell pro Task, keine gleichzeitigen Commits in denselben Branch. |

## 6. Fork-Repo-Kopplung

Das Testing-Repo und das Fork-Repo arbeiten über **zwei** Mechanismen zusammen:

1. **`WEBTREES_SOURCE`-Override:** Der Container-Compose-Stack mountet den webtrees-Code aus dem Pfad, der in `WEBTREES_SOURCE` steht (Default: `./upstream/webtrees`). Für Validation-Runs gegen den Fix-Branch wird `WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees` gesetzt. Gleichzeitig muss der Fork auf dem Fix-Branch ausgecheckt sein (`git checkout security-audit-<NNN>-<slug>`).

2. **Fork-Branch-Naming:** `security-audit-<NNN>-<slug>` (siehe `10_fixing_and_disclosure.md` §2). Basis-Branch ist `5349_add_tests` (aktueller Arbeitszweig des Forks), kann aber vom Driver per Git-Prüfung abgeleitet werden.

## 7. Was der Audit **nicht** ist

- **Kein** Ersatz für menschliche Code-Review. Der LLM hat blinde Flecken bei semantischen Eigenheiten webtrees' (Genealogie-Datenmodell, GEDCOM-Quirks).
- **Kein** Compliance-Framework. CVSS-Scoring ist heuristisch, nicht zertifiziert.
- **Kein** Continuous-Security-Monitoring. Der Audit läuft als Kampagne, nicht als Dauerbetrieb.
- **Kein** Penetration-Test mit breitem Scope — jeder Finding ist eigenständig.
- **Kein** Dependency-Audit. Für Supply-Chain-Risiken sind andere Tools zuständig (`composer audit`, `npm audit`, Renovate).
- **Kein** automatischer Disclosure-Kanal — User ist alleinige Instanz für Upstream-Kommunikation.

## 8. Distribution-Container-Ausschluss

Der Security-Track nutzt **zwei** Container-Profile (siehe `03_infrastructure_usage.md`):

- `webtrees` (Dev-Source mit Bind-Mount) — **einziges Audit-Ziel**.
- `webtrees-security` (Distribution-Container) — **nicht** Audit-Ziel. Der Distribution-Container läuft in einem separaten Profile (`--profile security` in Compose) und wird nur für Layer-4-Feature-Tests der bestehenden SEC-* Features benutzt.

Der Audit-Driver fragt niemals den Distribution-Container an. Die Begründung ist in `03_infrastructure_usage.md` §3 ausgeführt.

## 9. Abort-Bedingungen

Der Driver stoppt und übergibt an User, wenn:

1. **Globaler Halt-Flag gesetzt** (`artifacts/security-audit/HALT_CRITICAL.flag`) — nach bestätigtem Visitor→Sandbox-Escape (`prompt_04_trace_correlation.md` §7).
2. **Task hat `validation_failure_count >= 2`** — Fix wiederholt fehlgeschlagen, manuelle Sichtung nötig.
3. **Probe-Loop hat `i == 5`** und weiterhin `need_iteration` — Hypothese strukturell fragwürdig.
4. **Schema-Drift in T1-Batches** (≥3 aufeinanderfolgende Schemafehler der Sonnet-Ausgabe) — Prompt-Drift.
5. **`git apply` des Fix-Diffs schlägt 2× fehl** — Opus-Diff passt nicht zum Code.
6. **Fork-Repo ist nicht im erwarteten Zustand** (wrong branch, uncommitted changes, GPG-signing fails) — Driver macht keine destruktiven Aktionen.

In allen Fällen: Task-Status auf `needs_manual_review`, Eintrag in `artifacts/security-audit/NEEDS_USER_REVIEW.md`, Driver beendet die aktuelle Phase sauber ab.

## 10. Minimaler Einstieg

Wer zum ersten Mal in dieses Framework einsteigt, liest in dieser Reihenfolge:

1. Dieses Dokument (Master) — Workflow-Überblick.
2. `security-audit/01_scope_and_tracks.md` — Was ist überhaupt im Scope?
3. `security-audit/02_threat_model.md` — Welche vertikalen Hypothesen gibt es?
4. `security-audit/06_agentic_loop_driver.md` — Wie läuft der agentische Loop?
5. `security-audit/07_prompts/prompt_02_whitebox_deep_dive.md` — Das Herzstück: der Whitebox-Analyse-Prompt.
6. Der Rest in Kapitel-Reihenfolge.

Für den schnellen Start eines ersten Sweeps reichen `01`, `04`, `06` und die Prompts `01`–`05`.

## 11. Update-Policy für diesen Prompt

Änderungen an der Architektur (neue Tracks, neue Phasen, neue Prompts) werden **zuerst** in den Subdokumenten eingepflegt und **dann** in Kapitel 2 dieser Datei indexiert. Das Master-Dokument darf nur Verweise enthalten, keine materielle Wiederholung der Subdokumente.

Bei tiefgreifenden Änderungen der Workflow-Entscheidungen aus §4 wird eine neue Planungs-Runde mit dem User durchgeführt; der Driver führt **keine** Workflow-Änderungen autonom durch.
