<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# A6: W3C Baggage — Testlauf- und Testfall-Korrelation — Analyse

## 1. Fakten

### 1.1 W3C Baggage Specification (W3C Recommendation)

**Spezifikation:** W3C Baggage, verabschiedet als W3C Recommendation (November 2023).
**URL:** https://www.w3.org/TR/baggage/

**Header-Format:**

```
baggage: key1=value1,key2=value2;property1=propval,key3=value3
```

**Syntax-Regeln:**

| Element | Regel |
|---|---|
| Header-Name | `baggage` (Kleinbuchstaben) |
| Trennzeichen zwischen Einträgen | `,` (Komma) |
| Key-Value-Trennzeichen | `=` (Gleichheitszeichen) |
| Schlüssel-Zeichen | `token` gemäß RFC 7230 — Buchstaben, Ziffern, `!#$%&'*+-.^_\|~` |
| Wert-Zeichen | Printable ASCII ohne `"` `,` `;` `\` — oder Percent-Encoded |
| Maximale Gesamtgröße | 8192 Bytes (SHOULD) |
| Maximale Anzahl Einträge | 180 (SHOULD) |
| Maximale Größe pro Eintrag | 4096 Bytes (SHOULD) |

**Für den Anwendungsfall:**
- Schlüssel dürfen `.` (Punkt) enthalten: `test.run_id` und `test.case_id` sind gültige Schlüssel.
- UUID-Werte benötigen kein Encoding. Test-Titel mit Leerzeichen/Umlauten müssen Percent-Encoded werden.
- Der Header wird pro HTTP-Request gesendet.

**Beispiel:**

```
baggage: test.run_id=a1b2c3d4-e5f6-7890-abcd-ef1234567890,test.case_id=homepage%20loads%20without%20errors
```

### 1.2 Playwright APIs für Header-Injection

#### `page.setExtraHTTPHeaders(headers)`

```typescript
await page.setExtraHTTPHeaders({
  'baggage': 'test.run_id=<uuid>,test.case_id=<encoded-title>',
});
```

- Header für ALLE Requests (Navigation, XHR, Fetch, Subresource)
- Persistent für die gesamte Lebensdauer der Page-Instanz
- Kann keinen `traceparent` effektiv setzen (jeder Request braucht eigene Trace-ID)
- Für `baggage` ideal, da `test.run_id` und `test.case_id` pro Testfall konstant

#### `context.setExtraHTTPHeaders(headers)`

Wie `page.setExtraHTTPHeaders()`, aber auf BrowserContext-Ebene (alle Pages).

#### Playwright Fixture für automatische Header-Injection

```typescript
// layer4-e2e/helpers/otel-fixture.ts
import { test as base } from '@playwright/test';
import { randomUUID } from 'crypto';

export const test = base.extend<{}>({
  page: async ({ page }, use, testInfo) => {
    const runId = process.env.TEST_RUN_ID || randomUUID();
    const caseId = encodeURIComponent(testInfo.title);

    await page.setExtraHTTPHeaders({
      'baggage': `test.run_id=${runId},test.case_id=${caseId}`,
    });

    await use(page);
  },
});

export { expect } from '@playwright/test';
```

### 1.3 traceparent-Erzeugung: Optionen

#### Option A: Playwright erzeugt Root-Span (OTel SDK in Node.js)

**Benötigte npm-Pakete:** `@opentelemetry/api`, `@opentelemetry/sdk-node`, `@opentelemetry/exporter-trace-otlp-http`, `@opentelemetry/resources`

**Vorteile:** Saubere Trace-Hierarchie (Playwright-Span → PHP-Span → DB-Spans)
**Nachteile:** Erhöhte Komplexität, Collector braucht HTTP-Receiver auf 4318

#### Option B: Boomerang erzeugt Root-Span im Browser

**Vorteile:** Keine OTel-SDK-Integration in Playwright nötig
**Nachteile:** Initiale Navigation (`page.goto()`) vor Boomerang-Laden → kein Browser-Span für den ersten Request. Kein Testfall-Level-Span.

#### Option C: PHP erzeugt Root-Span (kein Browser-Span)

**Vorteile:** Einfachste Option, keine Änderungen an Playwright/Boomerang
**Nachteile:** Kein Browser-Level-Span, jeder Request = neuer Trace, keine Parent-Child-Korrelation

#### Empfehlung: Vereinfachter Ansatz — kein Playwright OTel SDK

Korrelation über `test.run_id` und `test.case_id` als Span-Attribute statt über Trace-Parent-Child-Beziehungen. PHP erzeugt pro Request automatisch einen Root-Span (wenn kein `traceparent` gesendet wird). Jeder Root-Span wird mit Baggage-Attributen angereichert.

**Kein OTel SDK im Playwright-Container nötig** — drastische Komplexitätsreduktion.

**Trace-Hierarchie pro Testfall (ohne Playwright OTel SDK):**

```
Testfall: "homepage loads without errors [minimal]"
  |
  |-- Trace 1 (Login):     POST /login/demo
  |     |-- PHP Root-Span  {test.run_id=abc, test.case_id=homepage...}
  |     |-- PDO Span
  |
  |-- Trace 2 (Homepage):  GET /tree/demo
  |     |-- PHP Root-Span  {test.run_id=abc, test.case_id=homepage...}
  |     |-- Custom Span    {webtrees.action=tree_page}
  |     |-- PDO Span (×3)
  |
  |-- Boomerang-Spans (separater Trace, unkorreliert über Trace-ID)
        |-- Document Load  {service.name=webtrees-browser}
```

Korrelation über gemeinsames Attribut `test.run_id` + `test.case_id`.

### 1.4 Boomerang OTel Plugin und Baggage

Das Plugin basiert auf `@opentelemetry/sdk-trace-web`. Seit Version 2.x ist der Default-Propagator `CompositePropagator([W3CTraceContextPropagator, W3CBaggagePropagator])` — Baggage wird automatisch in XHR/Fetch propagiert.

**Aber:** Da Playwright `baggage` als HTTP-Header auf ALLE Requests setzt, ist die Browser-seitige Baggage-Propagation für den Testfall-Korrelations-Zweck irrelevant.

**Boomerang-Spans und Baggage:** Baggage wird NICHT automatisch als Span-Attribute gesetzt. Baggage ist Propagation-Context, nicht Span-Content.

### 1.5 OTel Collector: Baggage-zu-Span-Attribute Konvertierung

**Kernproblem:** Der OTel Collector empfängt Spans via OTLP (gRPC/HTTP). Das OTLP-Protokoll transportiert **keine** Baggage-Header. Baggage ist ein HTTP-Propagation-Mechanismus zwischen instrumentierten Services, nicht zwischen Service und Collector.

**Konsequenz:** Baggage-zu-Span-Attribute-Konvertierung muss **in den instrumentierten Services** stattfinden:

| Komponente | Konvertierungsort | Mechanismus |
|---|---|---|
| PHP (Server-Spans) | Im PHP-Prozess | OTel-Spans-Modul liest Baggage, setzt Span-Attribute |
| Boomerang (Browser-Spans) | Im Browser | Nicht trivial (kein Standard-Mechanismus) |
| Playwright (Root-Span) | Im Node.js-Prozess | Beim Span-Create direkt als Attribute (nur bei Option A) |

### 1.6 PHP OTel SDK: Baggage-Handling

#### Automatische Baggage-Extraktion

Das PHP OTel SDK registriert standardmäßig `CompositePropagator` mit:
1. `TraceContextPropagator` (liest `traceparent` + `tracestate`)
2. `BaggagePropagator` (liest `baggage`)

Die Auto-Instrumentation löst beim Request-Eingang die Context-Extraktion aus. Der `baggage`-HTTP-Header ist in PHP als `$_SERVER['HTTP_BAGGAGE']` zugänglich.

#### Baggage API in PHP

```php
use OpenTelemetry\API\Baggage\Baggage;

$baggage = Baggage::getCurrent();
$entry = $baggage->getEntry('test.run_id');

if ($entry !== null) {
    $value = $entry->getValue();       // z.B. "a1b2c3d4-..."
    $metadata = $entry->getMetadata();  // Optional: Properties
}
```

#### Automatisch vs. Manuell

- **Automatisch:** `baggage`-Header wird geparsed, im OTel-Context verfügbar, wird NICHT automatisch als Span-Attribute gesetzt
- **Manuell:** OTel-Spans-Modul (A7) muss Baggage-Einträge aus Context lesen und als Span-Attribute setzen

#### OTEL_PROPAGATORS

```
OTEL_PROPAGATORS=tracecontext,baggage
```

Default: `tracecontext,baggage` — Baggage-Propagation ist standardmäßig aktiv. Die `compose.yaml` setzt `OTEL_PROPAGATORS` nicht explizit. **Keine Änderung nötig.**

### 1.7 Jaeger: Filterung nach Span-Attributen

Jaeger UI erlaubt Suche nach Span-Tags (= Span-Attributen):

```
Service: webtrees
Tags: test.run_id=a1b2c3d4-e5f6-7890-abcd-ef1234567890
```

**Voraussetzung:** `test.run_id` muss als Span-Attribut gesetzt sein. Jaeger indiziert Baggage-Context NICHT — nur Span-Attribute sind suchbar.

**Jaeger API (für Auswertungs-Script A8):**

```
GET http://jaeger:16686/api/traces?service=webtrees&tags=test.run_id%3D<uuid>
```

**File-Exporter Alternative:** `/artifacts/traces.json` (OTLP-JSON) — Auswertungs-Script kann nach `test.run_id`-Attributen filtern.

---

## 2. Bewertung

### 2.1 Propagation durch den Stack

| Hop | Baggage | traceparent | Automatisch? | Änderung nötig? |
|---|---|---|---|---|
| Playwright → Browser | `setExtraHTTPHeaders` | Nicht gesetzt | Manuell (Fixture) | Ja — Fixture |
| Browser → Apache | HTTP-Header | HTTP-Header | Ja | Nein |
| Apache → PHP | Standard-Passthrough | Standard-Passthrough | Ja | Nein |
| PHP (Extraktion) | `BaggagePropagator` liest automatisch | `TraceContextPropagator` liest | Ja (SDK Default) | Nein |
| PHP (→ Span-Attribute) | **NICHT automatisch** | N/A | **NEIN** | Ja — OTel-Modul |
| PHP → MySQL | Nicht möglich | Nicht möglich | N/A | N/A |
| Boomerang → Collector | Kein Baggage im OTLP | Eigene Trace-IDs | N/A | Nein |

### 2.2 Machbarkeit: HOCH

1. Playwright setzt `baggage`-Header (1 Fixture-Datei)
2. Apache leitet transparent weiter (keine Änderung)
3. PHP SDK extrahiert automatisch (keine Änderung)
4. OTel-Spans-Modul (A7) kopiert Baggage zu Span-Attributen (~10 Zeilen)
5. Jaeger indiziert Span-Attribute
6. Auswertungs-Script (A8) filtert nach `test.run_id`

### 2.3 test.run_id Scope

**Empfehlung: Pro Testlauf (Umgebungsvariable).**

```makefile
test-e2e:
    @TEST_RUN_ID=$$(uuidgen) \
    podman-compose exec playwright npx playwright test ...
```

Da `workers: 1` konfiguriert ist: ein run_id pro Testlauf = ein run_id pro Worker.

### 2.4 test.case_id Format

**Empfehlung: Playwright Test-Titel (Percent-Encoded).**

- Direkt in Jaeger UI lesbar
- Automatisch via `testInfo.title`
- Kein zusätzlicher Pflegeaufwand

### 2.5 Risiken

| Risiko | Schwere | Wahrscheinlichkeit | Mitigation |
|---|---|---|---|
| PHP `BaggagePropagator` nicht aktiv | Hoch | Niedrig | Explizit `OTEL_PROPAGATORS=tracecontext,baggage` setzen |
| Baggage-Header zu groß | Niedrig | Sehr niedrig | Nur 2 Einträge — weit unter 8192 Bytes |
| `test.case_id` mit Sonderzeichen | Mittel | Mittel | Percent-Encoding in Fixture |
| Boomerang-Spans nicht korrelierbar | Mittel | Hoch | Akzeptiert — Korrelation über `test.run_id` |
| `setExtraHTTPHeaders` auf Subresources | Niedrig | Sicher | Irrelevant — PHP ignoriert Baggage auf statischen Ressourcen |

---

## 3. Empfehlung

### 3.1 Architektur: Baggage-Only (kein Playwright OTel SDK)

```
Playwright                    Apache (transparent)          PHP (OTel Auto-Instr.)
  |                              |                              |
  |-- setExtraHTTPHeaders        |                              |
  |   baggage: test.run_id=X,   |                              |
  |            test.case_id=Y   |                              |
  |                              |                              |
  |-- page.goto('/tree/demo') -->|---[baggage Header]---------->|
  |                              |                              |
  |                              |    BaggagePropagator (Auto)  |
  |                              |    OTel-Spans-Modul:         |
  |                              |      setAttribute(run_id, X) |
  |                              |      setAttribute(case_id, Y)|
  |                              |      setAttribute(action,...)|
  |                              |                              |
  |                              |    Spans → Collector → Jaeger|
```

### 3.2 Implementierungsschritte

#### Teil 1: Playwright Fixture

```typescript
// layer4-e2e/helpers/otel-fixture.ts
import { test as base } from '@playwright/test';
import { randomUUID } from 'crypto';

export const test = base.extend<{}>({
  page: async ({ page }, use, testInfo) => {
    const runId = process.env.TEST_RUN_ID || randomUUID();
    const caseId = encodeURIComponent(testInfo.title);
    await page.setExtraHTTPHeaders({
      'baggage': `test.run_id=${runId},test.case_id=${caseId}`,
    });
    await use(page);
  },
});
export { expect } from '@playwright/test';
```

Integration: Import-Änderung in Test-Dateien (`from '../helpers/otel-fixture'` statt `@playwright/test`).

#### Teil 2: OTel-Spans-Modul erweitern (A7-Erweiterung)

```php
// In OtelSpansModule::process()
$baggage = \OpenTelemetry\API\Baggage\Baggage::getCurrent();
$testRunId = $baggage->getEntry('test.run_id');
$testCaseId = $baggage->getEntry('test.case_id');

if ($testRunId !== null) {
    $span->setAttribute('test.run_id', $testRunId->getValue());
}
if ($testCaseId !== null) {
    $span->setAttribute('test.case_id', urldecode($testCaseId->getValue()));
}
```

~10 Zeilen zusätzlich im bereits geplanten Modul.

#### Teil 3: Makefile-Integration

```makefile
test-e2e:
    @TEST_RUN_ID=$$(uuidgen) \
    podman-compose exec playwright npx playwright test --config=/tests/e2e/playwright.config.ts
```

#### Teil 4: OTel Collector Config

Keine Änderung nötig für Baggage-Propagation (Konvertierung erfolgt PHP-seitig).

### 3.3 Propagation-Matrix

| Komponente | Setzt Baggage | Liest Baggage | → Span-Attribute |
|---|---|---|---|
| **Playwright** | Ja (`setExtraHTTPHeaders`) | Nein | Nein |
| **Boomerang** | Möglicherweise (JS SDK) | Möglicherweise | Nein |
| **Apache** | Nein (transparent) | Nein | Nein |
| **PHP** (Auto-Instr.) | Nein | **Ja** (automatisch) | Nein (nur Context) |
| **PHP** (OTel-Modul) | Nein | **Ja** (via API) | **Ja** (setAttribute) |
| **OTel Collector** | Nein | Nein (nicht im OTLP) | Nein |
| **Jaeger** | Nein | Nein | Indiziert Attribute |

---

## 4. Offene Punkte

### 4.1 Vor Implementierung zu klären

1. **OTEL_PROPAGATORS Default verifizieren:** Im Container prüfen, ob Default `tracecontext,baggage` gilt. Falls nicht: explizit in `compose.yaml` setzen.

2. **Baggage::getCurrent() im Middleware-Kontext:** Verifizieren, dass Auto-Instrumentation den Baggage-Context propagiert, bevor das Custom-Modul ausgeführt wird.

3. **Percent-Encoding-Roundtrip:** Testen, dass URL-encoded `test.case_id`-Werte korrekt durch den Stack fließen. `Entry::getValue()` liefert den **encoded** Wert — OTel-Modul muss `urldecode()` anwenden.

4. **Interaktion Playwright `setExtraHTTPHeaders` mit Boomerang:** Wenn beide `baggage`-Header setzen, werden sie gemäß HTTP-Spec zusammengeführt (Komma-separiert). In der Praxis: Playwright setzt auf Request-Level, Browser ergänzt nicht zusätzlich.

### 4.2 Abhängigkeiten

5. **A7 (OTel-Spans-Modul):** Baggage-zu-Span-Attribute-Konvertierung ist Erweiterung des A7-Moduls. **Implementierungsreihenfolge: A7 vor A6.**

6. **Boomerang-Spans:** Browser-Spans werden NICHT automatisch mit `test.run_id` angereichert. Optionen:
   - (a) Akzeptieren — Zeitkorrelation reicht
   - (b) In Boomerang-Config `commonAttributes` dynamisch setzen (erfordert Server-seitiges Rendering → webtrees-Modul A2)

7. **Resource-Attribute:** Für die Auswertung (A8) unterscheidbar durch:
   - PHP-Spans: `service.name=webtrees`
   - Boomerang-Spans: `service.name=webtrees-browser`

### 4.3 Zukunftsoptionen

8. **Playwright OTel SDK (Ausbaustufe):** Falls End-to-End-Traces mit Parent-Child gewünscht, kann Playwright-Container um `@opentelemetry/sdk-node` erweitert werden. Baggage-Infrastruktur bleibt identisch.

---

## Anhang: Vollständige Header-Kette

```http
GET /tree/demo HTTP/1.1
Host: webtrees:80
baggage: test.run_id=a1b2c3d4-e5f6-7890-abcd-ef1234567890,test.case_id=homepage%20loads%20without%20errors
```

PHP sieht:
```
$_SERVER['HTTP_BAGGAGE'] = 'test.run_id=a1b2c3d4-...,test.case_id=homepage%20loads%20without%20errors'
```

Span-Attribute nach urldecode():
```
test.run_id  = a1b2c3d4-e5f6-7890-abcd-ef1234567890
test.case_id = homepage loads without errors
```

---

*Analyse basiert auf: W3C Baggage Specification (W3C Recommendation, November 2023), OpenTelemetry PHP SDK API, OpenTelemetry JavaScript SDK, Playwright Test API, und den Analysen A1, A3, A4, A7.*
