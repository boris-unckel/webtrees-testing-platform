<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Hypothesen — SEC-AUDIT-001

**Task:** SEC-AUDIT-001 — SVG Stored XSS via inadequate `<script>` substring filter (ImageFactory) + extension blocklist gap (MediaFileService)

**Kontext:** Siehe `context.md` §1–6.

## Zentrale Entscheidungsfrage

**Ist die `content-security-policy: script-src 'none'; frame-src 'none'`-Header (ImageFactory.php:278) effektiv gegen alle drei L1-Bypässe?**

- Falls **ja** → Finding ist **Defense-in-Depth-Gap** (niedrige Severity). Der fragile `str_contains`-Filter sollte durch einen robusten SVG-Sanitizer oder strikte XML-Parser-Validierung ersetzt werden. Der CSP-Header bleibt als primäre Verteidigungsschicht bestehen.
- Falls **nein** → Finding ist **Stored XSS** (hohe Severity). Editor kann SVG mit Payload uploaden, jeder Viewer ist betroffen.

Die Hypothesen H1–H3 probieren das L1-Umgehen; H4 ist eine **Kontroll-Hypothese**, die prüft ob die CSP tatsächlich Script-Ausführung blockiert.

---

## H1 — Case-Bypass des `<script`-Substring-Filters

**Confidence:** high (Filter definitiv umgangen), medium/low (real exploit — CSP-abhängig)

**Root-Cause-Logik:**
- PHP `str_contains('<SCRIPT>...<\/SCRIPT>', '<script')` → `false` (case-sensitiv).
- HTML/SVG-Parser der Browser sind case-insensitiv → `<SCRIPT>alert(1)</SCRIPT>` wird als Script-Tag interpretiert.
- **Erwartung L1:** Filter lässt Payload durch → gespeichert → ausgeliefert mit `image/svg+xml`-Content-Type.
- **Erwartung L2 (CSP):** `script-src 'none'` blockiert inline `<script>`-Tags unabhängig von Case → Ausführung blockiert, Console-Violation.

**Payload (Probe):**
```xml
<?xml version="1.0" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <rect width="100" height="100" fill="red"/>
  <SCRIPT type="text/javascript">
    fetch('/audit-probe-beacon?h=H1&c=' + encodeURIComponent(document.cookie));
  </SCRIPT>
</svg>
```

**Oracle (L1 check):**
1. Upload-Response: `200 OK`, Media-Record erzeugt (kein FileUploadException).
2. Download-Response: `200 OK`, Content-Type `image/svg+xml`, Body enthält `<SCRIPT>`, **kein** `x-image-exception` Header.

**Oracle (L2 check — CSP-Effektivität):**
- Browser lädt SVG-Download, Playwright Page.goto().
- Erwartung wenn CSP greift: `console.error` mit "Refused to execute inline script because it violates the following Content Security Policy directive: \"script-src 'none'\"".
- Erwartung wenn CSP versagt: `console.log` oder Netzwerk-Request zu `/audit-probe-beacon?h=H1&c=…` (via SecurityTraceModule protokolliert).

**Trace-Artefakt-Pfad:**
- `/artifacts/security-trace/SEC-AUDIT-001/H1_iter1.json`

---

## H2 — SVG Event-Handler ohne `<script>`-Tag

**Confidence:** high (Filter definitiv umgangen), medium (real exploit — CSP blockiert inline handler per Spec, aber verdient Validierung)

**Root-Cause-Logik:**
- `<svg onload="…">` enthält keinen `<script`-Substring → `str_contains` liefert `false` → Filter greift nicht.
- CSP Level 2/3 Spec: `script-src 'none'` blockiert **alle** inline Script-Ausführung inkl. Event-Handler-Attributen (ohne `'unsafe-inline'`).

**Payload:**
```xml
<?xml version="1.0" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"
     onload="fetch('/audit-probe-beacon?h=H2&c=' + encodeURIComponent(document.cookie));">
  <rect width="100" height="100" fill="blue"/>
</svg>
```

**Oracle (L1):** wie H1.

**Oracle (L2):**
- Wenn CSP greift: Console-Violation "Refused to execute inline event handler".
- Wenn CSP versagt: Beacon-Request im Trace.

**Trace-Artefakt-Pfad:**
- `/artifacts/security-trace/SEC-AUDIT-001/H2_iter1.json`

---

## H3 — `javascript:` URL in SVG `<a xlink:href="…">`

**Confidence:** high (Filter definitiv umgangen), low (CSP + User-Interaction erforderlich)

**Root-Cause-Logik:**
- `<a xlink:href="javascript:…">` enthält keinen `<script`-Substring → Filter greift nicht.
- CSP Level 2+: `script-src 'none'` blockiert `javascript:`-URLs in Navigation (als Form inline Script-Ausführung klassifiziert).

**Payload:**
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

**Oracle (L1):** wie H1.

**Oracle (L2):**
- Erfordert `page.click()` im Playwright-Test.
- Wenn CSP greift: Klick führt zu keiner Navigation; Console-Violation.
- Wenn CSP versagt: Beacon-Request im Trace.

**Trace-Artefakt-Pfad:**
- `/artifacts/security-trace/SEC-AUDIT-001/H3_iter1.json`

---

## H4 — Kontroll-Hypothese: Legitime SVG ohne Payload

**Zweck:** Baseline-Beweis, dass die Upload-/Download-Pipeline grundsätzlich funktioniert und kein unbekannter Filter eine zusätzliche Schicht bildet, die H1–H3 bereits blockiert.

**Payload:**
```xml
<?xml version="1.0" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <rect width="100" height="100" fill="orange"/>
  <text x="10" y="50">OK</text>
</svg>
```

**Oracle (L1):**
- Upload: `200 OK`, Media-Record angelegt.
- Download: `200 OK`, Content-Type `image/svg+xml`, Body **identisch** zum Upload (SHA256-Match).
- CSP-Header **present** (`script-src none;frame-src none`).

**Trace-Artefakt-Pfad:**
- `/artifacts/security-trace/SEC-AUDIT-001/H4_iter1.json`

---

## Hypothesen-Auswahl für D3 Probe-Loop

| Hypothese | Priorität | Confidence (L1) | Confidence (Gesamt-Exploit) | Begründung |
|---|---|---|---|---|
| H4 | 1. (zuerst) | — (Kontrolle) | — | Baseline/Sanity-Check vor H1–H3 |
| H2 | 2. (empfohlen) | high | medium | Vollständiger Bypass ohne `<script`-Substring; Event-Handler-CSP-Test ist aussagekräftig |
| H1 | 3. | high | medium | Einfachster Case-Bypass; CSP-Test trivial |
| H3 | 4. | high | low | Erfordert User-Interaction, deswegen zuletzt |

**Start-Iteration:** H4 (Kontrolle). Bei Erfolg → H2 (beste Aussagekraft für CSP-Effektivität) → H1 → H3.

**Stopp-Kriterium:** Sobald eine Hypothese die CSP UMGEHT (Script-Ausführung nachgewiesen via Beacon), STOP und HALT_CRITICAL.flag setzen, da dann Editor→Admin-Hijack möglich ist.

**Timeout-Kriterium:** Nach H1, H2, H3 ohne CSP-Bypass → Finding downgraded auf Defense-in-Depth-Gap (low severity). Regression-Test und Fix zielen dann auf L1-Hardening + CSP-Härtung.
