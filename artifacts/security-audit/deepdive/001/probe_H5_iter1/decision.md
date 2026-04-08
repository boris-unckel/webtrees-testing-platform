<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Probe Decision — SEC-AUDIT-001 / H5 / iter1

- **Hypothesis:** H5 — Baseline: lowercase `<script>` IST blockiert
- **Iteration:** 1
- **Run-Timestamp:** 2026-04-08 21:32 CEST
- **Executor:** `SecAudit001Test::test_h5_lowercase_script_is_blocked_baseline`

## Zweck

Baseline-Hypothese — keine Vulnerability, sondern Beweis dass der L1-Filter in seinem **einen** abgedeckten Fall (exakte lowercase `<script` Substring) tatsächlich auslöst. Ohne diesen Beweis könnte man H1–H3 fehldeuten als "Filter ist komplett kaputt", was die Fix-Strategie verwässern würde.

## Oracle-Ergebnisse

| Assertion | Erwartet | Beobachtet | ✓ / ✗ |
|---|---|---|---|
| Response-Status | `200` | `200` | ✓ |
| Response-Body = replacementImageResponse | ja | ja (`SVG image blocked` placeholder) | ✓ |
| Response `x-image-exception` Header | **gesetzt** (Filter greift) | `SVG image blocked due to XSS.` | ✓ |

## Decision

**`hypothesis_confirmed`** — Der Filter ist nicht komplett kaputt, er ist **sparsam korrekt** (trifft nur den trivialsten Fall). Dies bestätigt die Analyse: der Filter ist ein Fragment einer echten Verteidigungsschicht, keine vollständige.

**Implikation für Fix:** Der Fix muss das Filter-Design grundsätzlich überarbeiten, nicht nur den einen bekannten Bypass patchen. Anderenfalls entsteht ein Katz-und-Maus-Spiel zwischen neuen Case-Variationen, Event-Handler-Namen und URL-Schemata.
