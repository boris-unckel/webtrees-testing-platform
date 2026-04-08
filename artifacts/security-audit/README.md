<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# `artifacts/security-audit/`

Artefakt-Baum des Whitebox-Security-Audit-Drivers.

**Spec:** `docs/security-audit/06_agentic_loop_driver.md`, `docs/security-audit/04_triage_pipeline.md`.

## Struktur

```
artifacts/security-audit/
├── .lock                      ← Advisory-Lock des Drivers (nur einer gleichzeitig)
├── HALT_CRITICAL.flag         ← (optional) Visitor→Sandbox-Escape bestätigt, User muss freigeben
├── NEEDS_USER_REVIEW.md       ← Append-only: Tasks warten auf manuelles Review
├── <run-id>/                  ← Ein Sweep-Lauf, run-id ≙ ISO-Zeitstempel
│   ├── t0_signals.json
│   ├── t1_llm_scores.json
│   ├── t2_tracks.json
│   ├── priorities.md
│   ├── reachability-matrix.md
│   ├── run-summary.md
│   └── tasks/SEC-AUDIT-<NNN>/
│       └── context.md         ← kumulativer Deep-Dive-Kontext
├── deepdive/<NNN>/            ← Pro Task: Deep-Dive-Artefakte
│   ├── hypotheses.md
│   ├── validation_context.md
│   ├── validation.md
│   ├── correlation_context_H<n>_iter<i>.md
│   ├── probe_H<n>_iter<i>/decision.md
│   └── verification/
└── traces/                    ← Roh-Trace-Artefakte (SecurityTraceMiddleware)
    └── <NNN>_H<n>_iter<i>_<ts>.json
```

## Persistenz

`artifacts/security-audit/deepdive/<NNN>/` wird **nach** `fix_verified` nicht gelöscht — Audit-Trail für Disclosure (`10_fixing_and_disclosure.md`). Sweep-Run-Verzeichnisse sind zeitstempel-getaggt und werden vom Driver nicht automatisch aufgeräumt.
