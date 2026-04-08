<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Probe Decision — SEC-AUDIT-001 / H2 / iter1

- **Hypothesis:** H2 — SVG Event-Handler ohne `<script>`-Tag (`onload="…"`)
- **Iteration:** 1
- **Run-Timestamp:** 2026-04-08 21:32 CEST
- **Executor:** `SecAudit001Test::test_h2_onload_event_handler_passes_l1_filter`

## Probe-Aufbau

Identisch zu H1, nur mit Payload:
```xml
<?xml version="1.0" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"
     onload="fetch('/audit-probe-beacon?h=H2&c=' + encodeURIComponent(document.cookie));">
  <rect width="100" height="100" fill="blue"/>
</svg>
```

## Oracle-Ergebnisse

| Assertion | Erwartet | Beobachtet | ✓ / ✗ |
|---|---|---|---|
| Upload liefert nicht-leeren Pfad | `!== ''` | `onload_handler.svg` | ✓ |
| Datei auf Filesystem vorhanden | `fileExists == true` | `true` | ✓ |
| Response-Status | `200` | `200` | ✓ |
| Response Content-Type | `image/svg+xml` | `image/svg+xml` | ✓ |
| Response CSP-Header | `script-src none;frame-src none` | `script-src none;frame-src none` | ✓ |
| Response `x-image-exception` Header | **abwesend** (L1-Bypass) | abwesend | ✓ |
| Response-Body enthält `onload=` | ja | ja | ✓ |
| Response-Body enthält `cookie` | ja | ja | ✓ |

## Decision

**`hypothesis_confirmed` für L1-Layer**: `str_contains($data, '<script')` liefert bei einer SVG ohne `<script`-Substring einen `false`, der Filter greift nicht. Ein `onload`-Event-Handler ist eine vollwertige Script-Ausführungsmöglichkeit, die vom Filter komplett ignoriert wird.

**Weiterer Gap:** Der Filter ist in seiner Natur unvollständig — selbst wenn er die Case-Variation korrigieren würde (z. B. `stripos` statt `str_contains`), würde er weiterhin alle Event-Handler-Attribute übersehen. Eine vollständige Blacklist ist nicht wartbar; nur ein allowlist-basierter XML/DOM-Parser ist robust.

**L2-Layer (CSP) ist intakt**: `script-src 'none'` blockt Event-Handler-Attribute laut CSP Level 2/3 Spec (als Form von "inline script") in allen modernen Browsern. Der real-world Exploit bleibt blockiert.

## Next Action

- H3 (javascript:-URL) als weitere Bypass-Variante dokumentieren.
- Danach H5 (Baseline).
- Danach Gesamt-Korrelation + D5 Regression-Konsolidierung.
