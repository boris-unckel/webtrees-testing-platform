<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Probe Decision — SEC-AUDIT-001 / H3 / iter1

- **Hypothesis:** H3 — `javascript:` URL in SVG `<a xlink:href="…">`
- **Iteration:** 1
- **Run-Timestamp:** 2026-04-08 21:32 CEST
- **Executor:** `SecAudit001Test::test_h3_javascript_url_passes_l1_filter`

## Probe-Aufbau

```xml
<?xml version="1.0" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg"
     xmlns:xlink="http://www.w3.org/1999/xlink"
     width="200" height="100">
  <a xlink:href="javascript:fetch('/audit-probe-beacon?h=H3&c=' + encodeURIComponent(document.cookie));">
    <rect width="200" height="100" fill="green"/>
    <text x="10" y="60" font-size="20" fill="white">Click me</text>
  </a>
</svg>
```

## Oracle-Ergebnisse

| Assertion | Erwartet | Beobachtet | ✓ / ✗ |
|---|---|---|---|
| Upload liefert nicht-leeren Pfad | `!== ''` | `xlink_js.svg` | ✓ |
| Datei auf Filesystem vorhanden | `fileExists == true` | `true` | ✓ |
| Response-Status | `200` | `200` | ✓ |
| Response Content-Type | `image/svg+xml` | `image/svg+xml` | ✓ |
| Response CSP-Header | `script-src none;frame-src none` | `script-src none;frame-src none` | ✓ |
| Response `x-image-exception` Header | **abwesend** | abwesend | ✓ |
| Response-Body enthält `javascript:` | ja | ja | ✓ |
| Response-Body enthält `cookie` | ja | ja | ✓ |

## Decision

**`hypothesis_confirmed` für L1-Layer**: Der Filter erkennt `javascript:`-URLs in `xlink:href` nicht. Payload unverändert ausgeliefert.

**Real-World-Exploit-Lage**: Erfordert **User-Interaction** (Klick auf die SVG als Top-Level-Dokument). CSP `script-src 'none'` blockiert in CSP Level 2/3 `javascript:`-URLs bei Navigation (klassifiziert als inline script source). In modernen Browsern ist diese Ausführung blockiert.

**Einschränkung**: Einige ältere Browser (IE11, frühe Firefox-Versionen ≤ 40) haben CSP `script-src`-Validierung für `javascript:`-URLs nicht konsistent implementiert. Da webtrees moderne Browser voraussetzt, ist das kein realistischer Angriffsvektor.

## Next Action

- H5 (Baseline) prüfen um Filter-Integrität zu bestätigen.
- Danach D4 Gesamt-Korrelation und D5 Regression-Test-Konsolidierung.
