<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-NNN
title: <kurzer Titel, aus T1 llm_reason oder manuell gesetzt>
created: YYYY-MM-DD
last_updated: YYYY-MM-DD
status: queued
track: non-admin | sandbox-escape | both
file: app/Http/Controllers/...
verticals_hit: []
final_score: 0.0
llm_score: 0
t0_signals:
  crap: 0
  crap_coverage_pct: 0.0
  input_sinks: []
  db_sinks: []
  dangerous_functions: []
  routing_entry_points: []
  reachability: unknown
  type_weight: 1.0
  auth_requirement: none
  loc: 0
hypotheses: []
current_hypothesis: null
probe_iteration_count: 0
validation_failure_count: 0
fixture_rev: 0
fix_branch: null
disclosure_state: not_ready
blocked_by: []
notes_for_opus: ""
---

# SEC-AUDIT-NNN — <titel>

## Triage-Kontext
<ausgefüllt in Phase S5/T3; manuell lesbar>

- **Warum queued:** <kurze Begründung aus T3-Score-Aggregation>
- **Verticals:** <V1..V12>
- **Track-Assignment:** <aus T2-Zuordnung>

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/deepdive/<NNN>/context.md`
- generated_at: <YYYY-MM-DD HH:MM>

### Phase D2 — Hypothesen
- hypotheses_file: `artifacts/security-audit/deepdive/<NNN>/hypotheses.md`
- hypothesen_count: 0
- highest_confidence: <low|medium|high>

### Phase D3/D4 — Probe-Loop
- iteration_log:
  - iter1: <kurzstatus>
  - iter2: <kurzstatus>

### Phase D5 — Regression
- regression_file: `layer3-integration/tests/Security/SecAuditNNNTest.php` | `layer4-e2e/tests/security-audit/sec-audit-NNN.spec.ts`
- fixture_file: `fixtures/security/payloads/sec_audit_NNN.json`

### Phase D6 — Fix-Draft
- fix_branch: `security-audit-NNN-<slug>`
- fix_commit: <hash>
- diff_size: <N lines>

### Phase D7 — Validation
- validation_file: `artifacts/security-audit/deepdive/<NNN>/validation.md`
- gesamturteil: <fix_verified | fix_rejected | validation_incomplete>

## Confirmed Vectors
<aus decision.md §Bei `hypothesis_confirmed` kopiert>

## Finding Summary
<aus validation.md §Findings-Summary kopiert, nach Phase D7>

## Offene Punkte
- [ ] <aus validation.md §Offene Punkte>

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| YYYY-MM-DD HH:MM | queued | Erzeugt in T3 |
| YYYY-MM-DD HH:MM | in_analysis | Deep-Dive D1 gestartet |
| YYYY-MM-DD HH:MM | in_progress | D2 Hypothesen bestätigt |
| YYYY-MM-DD HH:MM | exploit_attempted | D3 erster Probe-Run |
| YYYY-MM-DD HH:MM | exploit_confirmed | D4 Trace-Korrelation positiv |
| YYYY-MM-DD HH:MM | regression_drafted | D5 Testklasse erzeugt |
| YYYY-MM-DD HH:MM | fix_in_progress | D6 Fix-Draft gestartet |
| YYYY-MM-DD HH:MM | fix_committed | D6 Diff committet in Fork |
| YYYY-MM-DD HH:MM | fix_verified | D7 Validation grün |
| YYYY-MM-DD HH:MM | awaiting_user_review | NEEDS_USER_REVIEW.md ergänzt |
| YYYY-MM-DD HH:MM | done | User-Review abgeschlossen |
