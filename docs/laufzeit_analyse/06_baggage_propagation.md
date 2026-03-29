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

**Trace-Kette:** Playwright-Span → Boomerang-Span → PHP-Span → DB-Spans

Diese Option ist die **systematisch korrekte** Lösung für eine vollständige End-to-End-Trace-Korrelation, weil sie die tatsächliche Kausalitätskette abbildet: Der Test-Orchestrator (Playwright) ist die wahre Ursache jeder Interaktion. Wird dies mit der Browser-Instrumentierung durch Boomerang kombiniert, entsteht eine lückenlose, vierstufige Span-Hierarchie:

```
Playwright Root-Span (test: "homepage loads without errors")
  ├── Boomerang documentFetch (Browser → Server, via traceparent)
  │     └── PHP Request-Span (Server, Child des propagierten traceparent)
  │           ├── PDO Span (SELECT users ...)
  │           ├── PDO Span (SELECT trees ...)
  │           └── PDO Span (SELECT modules ...)
  ├── Boomerang documentLoad (Browser-seitiges Timing)
  │     └── resourceFetch Spans (CSS, JS, Bilder)
  └── Boomerang XHR/Fetch Spans (AJAX-Requests nach Page-Load)
        └── PHP Request-Span (nachfolgende Requests)
              └── PDO Spans
```

**Technische Voraussetzungen für die Kette:**

| Hop | Mechanismus | Voraussetzung |
|---|---|---|
| Playwright → Browser | `traceparent` via `setExtraHTTPHeaders` oder `page.route()` | OTel SDK in Node.js erzeugt Span + Header pro Navigation |
| Playwright → PHP | `traceparent` als HTTP-Header auf `page.goto()` | PHP `TraceContextPropagator` (Default aktiv) |
| PHP → Boomerang (Rückkanal) | `Server-Timing`-Response-Header mit Trace-Context | PHP-seitige Emission oder Apache-Config nötig |
| Boomerang → gleicher Trace | `@opentelemetry/instrumentation-document-load` liest `Server-Timing` | Plugin-Konfiguration, Server-Timing-Header vorhanden |
| Boomerang → PHP (XHR/Fetch) | W3C Trace Context Propagation (automatisch via OTel-Plugin) | CORS erlaubt `traceparent`-Header |

**Herausforderung — initialer Page-Load:** Bei `page.goto()` setzt Playwright den `traceparent` auf den HTTP-Request. PHP empfängt ihn und erzeugt einen Child-Span. Boomerang im Browser kennt den `traceparent` jedoch nicht direkt — es initialisiert sich erst NACH dem Page-Load. Die Verknüpfung des Browser-seitigen Document-Load-Spans mit dem Server-Span erfolgt über den **Rückkanal**: PHP sendet den Trace-Context im `Server-Timing`-Response-Header, den Boomerangs `instrumentation-document-load` ausliest. Damit entsteht die Verbindung Playwright-Span → PHP-Span ← Boomerang-Span innerhalb desselben Trace-Baums.

**Herausforderung — pro-Request Span-Erzeugung:** Anders als `baggage` (pro Testfall konstant) muss `traceparent` pro HTTP-Request eine neue Span-ID enthalten. `setExtraHTTPHeaders` setzt einen statischen Header — für echtes Per-Request-Tracing müsste `page.route()` mit dynamischer Header-Generierung verwendet werden:

```typescript
await page.route('**/*', async (route) => {
  const span = tracer.startSpan(`browser: ${route.request().url()}`);
  const ctx = trace.setSpan(context.active(), span);
  const traceparent = /* traceparent aus ctx erzeugen */;
  await route.continue({
    headers: { ...route.request().headers(), traceparent }
  });
  span.end();
});
```

Dies erhöht die Komplexität erheblich gegenüber dem statischen `setExtraHTTPHeaders`-Ansatz.

**Ergänzung durch BOOMR.addVar():** Unabhängig von der Trace-Hierarchie kann Boomerangs `BOOMR.addVar()`-API (vgl. [Boomerang Tutorial: Adding Arbitrary Data to Beacons](https://akamai.github.io/boomerang/oss/tutorial-howto-add-arbitrary-data-to-the-beacon.html)) die Test-Korrelation in den proprietären Beacons tragen:

```javascript
BOOMR.addVar({ "test.run_id": runId, "test.case_id": testTitle });
```

Diese Daten landen ausschließlich in den Boomerang-Beacons (URI-encoded als Query-Parameter), NICHT in den OTel-Spans. Für die OTel-Span-Anreicherung im Browser ist `commonAttributes` in der Plugin-Konfiguration zuständig. `BOOMR.addVar()` bildet somit einen **komplementären Korrelationskanal**, der auch ohne funktionierendes OTel-Tracing die Testfall-Zuordnung sicherstellt.

**Vorteile:**
- Kausal korrekte Trace-Hierarchie (ein Trace pro Testinteraktion)
- Vollständige Parent-Child-Korrelation ohne nachträgliche Attribut-Suche
- Jaeger zeigt den gesamten Request-Lifecycle in einer Trace-Ansicht
- Boomerang-Spans (Browser-Timing) und PHP-Spans (Server-Timing) in einem gemeinsamen Trace
- Komplementäre Beacon-Korrelation via `BOOMR.addVar()` als Fallback-Kanal

**Nachteile:**
- Erhöhte Komplexität: OTel SDK im Playwright-Container, Server-Timing-Emission in PHP
- Collector braucht HTTP-Receiver auf 4318
- Per-Request `traceparent`-Erzeugung erfordert `page.route()` statt einfachem `setExtraHTTPHeaders`
- Server-Timing-Header muss PHP-seitig emittiert werden (nicht automatisch im PHP OTel SDK)
- Boomerang-Verknüpfung via Server-Timing ist von Issue #40 (Propagation mit XHR/Fetch) potenziell betroffen

#### Option B: Boomerang erzeugt Root-Span im Browser

**Vorteile:** Keine OTel-SDK-Integration in Playwright nötig. Boomerang erzeugt automatisch Spans für Document Load, XHR/Fetch und User Interactions.

**Nachteile:** Initiale Navigation (`page.goto()`) vor Boomerang-Laden → kein Browser-Span für den ersten HTTP-Request. Kein Testfall-Level-Span. Die Trace-Kette beginnt erst ab dem Zeitpunkt, an dem Boomerang initialisiert ist — der kausal erste Schritt (Playwright-Aktion) bleibt unsichtbar. Für nachfolgende XHR/Fetch-Requests funktioniert die Propagation (Boomerang-Span → PHP-Span → DB-Spans), sofern CORS `traceparent` erlaubt.

#### Option C: PHP erzeugt Root-Span (kein Browser-Span)

**Vorteile:** Einfachste Option, keine Änderungen an Playwright/Boomerang.

**Nachteile:** Kein Browser-Level-Span, jeder Request = neuer Trace, keine Parent-Child-Korrelation. Die Trace-Hierarchie bildet nur den Server-Teil ab — Browser-Ladezeiten und Netzwerk-Latenz sind nicht im Trace sichtbar.

#### Einordnung der Optionen

| Kriterium | Option A (Playwright Root) | Option B (Boomerang Root) | Option C (PHP Root) |
|---|---|---|---|
| Kausalitätskette vollständig | **Ja** (4 Stufen) | Teilweise (ohne initialen Load) | Nein (nur Server) |
| Komplexität | Hoch | Mittel | Niedrig |
| Implementierungsaufwand | Hoch (OTel SDK + Server-Timing + `page.route()`) | Mittel (CORS-Config) | Gering |
| Ein Trace pro Testfall | **Ja** | Nur für Post-Init-Requests | Nein (1 Trace pro Request) |
| Browser-Timing im Trace | **Ja** | Ja (ab Init) | Nein |
| Abhängigkeit von Server-Timing | Ja (für Rückkanal) | Optional | Nein |
| Komplementär mit Baggage | **Ja** (Baggage + Traces) | Ja | Ja |

#### Empfehlung: Vereinfachter Ansatz — kein Playwright OTel SDK

Für die initiale Implementierung: Korrelation über `test.run_id` und `test.case_id` als Span-Attribute statt über Trace-Parent-Child-Beziehungen. PHP erzeugt pro Request automatisch einen Root-Span (wenn kein `traceparent` gesendet wird). Jeder Root-Span wird mit Baggage-Attributen angereichert.

**Kein OTel SDK im Playwright-Container nötig** — drastische Komplexitätsreduktion.

Option A bleibt die **systematisch korrekte Ausbaustufe**. Die Baggage-basierte Korrelation ist als Fundament so konzipiert, dass ein späterer Umstieg auf Option A ohne Umbau der bestehenden Infrastruktur möglich ist: Baggage-Header, Span-Attribute und `BOOMR.addVar()`-Beacon-Daten bleiben erhalten — die `traceparent`-Kette kommt als zusätzliche Schicht hinzu.

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
  |     |-- Document Load  {service.name=webtrees-browser}
  |
  Korrelation über gemeinsames Attribut test.run_id + test.case_id
  (+ optional: BOOMR.addVar() in Beacons als komplementärer Kanal)
```

### 1.4 Boomerang OTel Plugin und Baggage

Das Plugin basiert auf `@opentelemetry/sdk-trace-web`. Seit Version 2.x ist der Default-Propagator `CompositePropagator([W3CTraceContextPropagator, W3CBaggagePropagator])` — Baggage wird automatisch in XHR/Fetch propagiert.

**Aber:** Da Playwright `baggage` als HTTP-Header auf ALLE Requests setzt, ist die Browser-seitige Baggage-Propagation für den Testfall-Korrelations-Zweck irrelevant.

**Boomerang-Spans und Baggage:** Baggage wird NICHT automatisch als Span-Attribute gesetzt. Baggage ist Propagation-Context, nicht Span-Content.

**BOOMR.addVar() — Beacon-Anreicherung (vgl. [Boomerang Tutorial](https://akamai.github.io/boomerang/oss/tutorial-howto-add-arbitrary-data-to-the-beacon.html)):** Boomerang bietet mit `BOOMR.addVar()` eine API zum Anreichern der proprietären Beacons mit beliebigen Key-Value-Paaren. Keys und Values werden URI-encoded und als Query-Parameter im Beacon übertragen. `BOOMR.removeVar()` erlaubt das gezielte Entfernen einzelner Einträge. **Wichtig:** `addVar()`-Daten landen ausschließlich in den Boomerang-Beacons — NICHT in den OTel-Spans. Für die OTel-Span-Anreicherung im Browser ist `commonAttributes` in der OTel-Plugin-Konfiguration zuständig. Die beiden Mechanismen sind komplementär: `commonAttributes` für OTel-Trace-Korrelation, `addVar()` für Beacon-Korrelation.

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

### 2.6 Bewertung: Baggage-Korrelation vs. Trace-Hierarchie (Option A)

Die Baggage-basierte Korrelation (empfohlener Ansatz) und die Trace-Hierarchie via Playwright Root-Span (Option A) schließen sich nicht gegenseitig aus — sie adressieren unterschiedliche Aspekte:

| Aspekt | Baggage-Korrelation | Trace-Hierarchie (Option A) |
|---|---|---|
| **Korrelationsmechanismus** | Gemeinsame Attribute (`test.run_id`) | Parent-Child-Beziehung im Trace-Baum |
| **Abfrage in Jaeger** | Tag-Suche: `test.run_id=X` → Liste unabhängiger Traces | Trace-Suche: ein Trace zeigt die gesamte Kette |
| **Kausalität sichtbar** | Nein — zeitliche Nähe, aber keine explizite Beziehung | **Ja** — Playwright → Boomerang → PHP → DB |
| **Boomerang-Integration** | Separater Trace, Korrelation nur über Attribute | Im selben Trace via Server-Timing-Rückkanal |
| **Beacon-Daten (BOOMR.addVar)** | Komplementär nutzbar | Komplementär nutzbar |
| **Implementierungsaufwand** | Gering (Fixture + ~10 Zeilen PHP) | Hoch (OTel SDK + Server-Timing + `page.route()`) |

**Kernfrage:** Reicht Attribut-basierte Korrelation für die Auswertung (A8), oder braucht die Analyse die kausale Verknüpfung?

Für die initiale Implementierung reicht die Attribut-Korrelation: Das Auswertungs-Script kann über `test.run_id` alle Spans eines Testlaufs aggregieren und die Laufzeiten berechnen. Die Baggage-Infrastruktur (Fixture, PHP-Modul, Makefile) ist als Fundament konzipiert, das bei einem späteren Umstieg auf Option A vollständig erhalten bleibt.

Option A wird relevant, wenn die Trace-Analyse eine **request-übergreifende Kausalitätskette** erfordert — etwa um zu unterscheiden, ob eine Latenz im Browser-Rendering, in der Netzwerk-Übertragung oder im PHP-Backend entsteht. Die vierstufige Kette (Playwright-Span → Boomerang-Span → PHP-Span → DB-Spans) bildet genau diese Kausalität ab.

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

## 4. Offene Punkte — Entscheidungsstatus

### 4.1 Entschieden

1. **Interaktion Playwright `setExtraHTTPHeaders` mit Boomerang:** → **Kein Konflikt.** Playwright setzt `baggage` auf Request-Level; der Browser ergaenzt nicht zusaetzlich. Boomerangs OTel-Plugin propagiert Baggage auf XHR/Fetch, aber Playwright-Header hat Vorrang auf Navigation-Requests.

2. **Boomerang-Spans ohne test.run_id:** → **Option (a): Akzeptiert.** Browser-Spans werden ueber temporale Korrelation (Zeitfenster) zugeordnet. Bei `workers: 1` ist Ueberlappung unwahrscheinlich. Option (b) (dynamische `commonAttributes` via Server-Rendering) wuerde webtrees-Modul (Ansatz A) erfordern — initial zu aufwaendig.

3. **Resource-Attribute Unterscheidung:** → **Geklaert.** PHP-Spans: `service.name=webtrees`; Boomerang-Spans: `service.name=webtrees-browser`. Auswertungs-Script (A8) nutzt `service.name` fuer Layer-Zuordnung.

4. **Implementierungsreihenfolge:** → **S5 (OTel-Spans-Modul) vor S7 (Baggage-Fixture).** Das Modul muss existieren, bevor die Fixture Baggage-Header setzt, da sonst keine Konvertierung zu Span-Attributen stattfindet.

### 4.2 Bei Implementierung zu verifizieren

5. **OTEL_PROPAGATORS Default:** Im Container pruefen, ob Default `tracecontext,baggage` gilt. Falls nicht: explizit `OTEL_PROPAGATORS=tracecontext,baggage` in `compose.yaml` setzen.

6. **Baggage::getCurrent() im Middleware-Kontext:** Verifizieren, dass Auto-Instrumentation den Baggage-Context propagiert, bevor das OTel-Spans-Modul in der inneren Pipeline ausfuehrt.

7. **Percent-Encoding-Roundtrip:** Testen, dass URL-encoded `test.case_id`-Werte korrekt durch den Stack fliessen. `Entry::getValue()` liefert den **encoded** Wert — OTel-Modul muss `urldecode()` anwenden.

### 4.3 Aufgeschoben: Trace-Hierarchie via Playwright Root-Span (Option A)

8. **Playwright OTel SDK:** Falls die Auswertung (A8) kausale Verknuepfung ueber Attribute hinaus erfordert, kann der Playwright-Container um `@opentelemetry/sdk-node` erweitert werden. Die resultierende Kette (Playwright-Span → Boomerang-Span → PHP-Span → DB-Spans) erfordert zusaetzlich:
   - **Server-Timing-Header:** PHP muss den Trace-Context im `Server-Timing`-Response-Header emittieren, damit Boomerangs `instrumentation-document-load` den Browser-Span mit dem Server-Span verknuepfen kann.
   - **Dynamische traceparent-Erzeugung:** `page.route()` statt `setExtraHTTPHeaders` fuer per-Request Span-ID-Generierung.
   - **BOOMR.addVar()-Komplementaritaet:** Unabhaengig von der Trace-Hierarchie kann `BOOMR.addVar()` die Test-Korrelation in den Boomerang-Beacons tragen — als Fallback-Kanal, der keine OTel-Infrastruktur voraussetzt.
   - Die bestehende Baggage-Infrastruktur (Fixture, PHP-Modul, Makefile) bleibt vollstaendig erhalten — Option A ist eine zusaetzliche Schicht, kein Umbau.

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

*Analyse basiert auf: W3C Baggage Specification (W3C Recommendation, November 2023), OpenTelemetry PHP SDK API, OpenTelemetry JavaScript SDK, Playwright Test API, [Boomerang Tutorial: Adding Arbitrary Data to Beacons](https://akamai.github.io/boomerang/oss/tutorial-howto-add-arbitrary-data-to-the-beacon.html), und den Analysen A1, A3, A4, A7.*
