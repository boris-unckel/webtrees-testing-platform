<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Probe Decision — SEC-AUDIT-001 / H4 / iter1

- **Hypothesis:** H4 — Kontroll-Hypothese: legitime SVG ohne Payload
- **Iteration:** 1
- **Run-Timestamp:** 2026-04-08 21:32 CEST
- **Executor:** `SecAudit001Test::test_h4_legitimate_svg_baseline`

## Zweck

Sanity-Check der gesamten Upload-/Download-Pipeline. Bei negativem Ergebnis (legitime SVG wird verändert oder blockiert) wäre H1–H3 irreführend, weil wir nicht wüssten, ob der gesamte Flow funktioniert.

## Oracle-Ergebnisse

| Assertion | Erwartet | Beobachtet | ✓ / ✗ |
|---|---|---|---|
| Upload + Download roundtrip | identische Bytes | identisch | ✓ |
| Response Content-Type | `image/svg+xml` | `image/svg+xml` | ✓ |
| CSP gesetzt | ja | ja | ✓ |
| `x-image-exception` | abwesend | abwesend | ✓ |

## Decision

**`hypothesis_confirmed`** — Pipeline funktioniert wie erwartet. H1–H3 Ergebnisse sind nicht durch Pipeline-Fehler kontaminiert.
