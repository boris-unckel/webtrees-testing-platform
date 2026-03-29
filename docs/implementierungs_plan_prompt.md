<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Implementierungs-Planprompt: Laufzeitmessung — End-to-End Trace Correlation

## Zweck

Dieses Dokument ist der vollstaendige, eigenstaendige Planprompt fuer die Implementierung der Laufzeitmessung in der webtrees-Testing-Plattform. Es enthaelt alle Architekturentscheidungen, technischen Details, Konfigurationen und Codebeispiele, die fuer die Plangenerierung noetig sind. Die Analysedokumente unter `docs/laufzeit_analyse/` werden fuer die Implementierung nicht mehr benoetigt.

**Aufgabe:** Erstelle aus diesem Dokument einen konkreten, schrittweisen Implementierungsplan mit zwei Ausbaustufen. Nach jeder Ausbaustufe findet ein Komponentenintegrationstest- und Systemtestlauf statt, dessen Artefakte persistiert und als ZIP archiviert werden.
Der Implementierungsplan soll ein Statuskonzept beinhalten. An jedem Teilschritt soll ein Status vorgesehen werden, der fortlaufend, nicht erst bei Abschluss, bei der Umsetzung mitgetrackt wird, so dass jederzeit der konkrete Umsetzungsstand ersichtlich ist.

---

## 1. Ausgangslage

### 1.1 Bestehender Stack

| Komponente | Ist-Zustand | Image / Version |
|---|---|---|
| PHP | `php:8.5-apache` (Debian Bookworm) mit OTel PECL Extension | 8.5 |
| Apache httpd | Standard `mod_rewrite`, kein OTel-Modul | aus php:8.5-apache |
| MySQL | `mysql:8.0` | 8.0.x |
| OTel Collector | `otel/opentelemetry-collector-contrib:latest` | latest (ungepinnt) |
| Jaeger | `jaegertracing/all-in-one:latest` | latest (ungepinnt) |
| Playwright | `node:22-bookworm`, Chromium headless | Container |

### 1.2 Bestehende PHP-Instrumentierung

Bereits installiert (via `setup-webtrees.sh`, bedingt auf `OTEL_SDK_DISABLED != true`):

- `open-telemetry/sdk`
- `open-telemetry/exporter-otlp`
- `open-telemetry/opentelemetry-auto-pdo` (DB-Queries)
- `open-telemetry/opentelemetry-auto-psr18` (HTTP-Client)

### 1.3 Bestehende Trace-Pipeline

```
PHP (gRPC) → OTel Collector (:4317, nur gRPC) → Jaeger (UI auf :16686)
                                                → file (/artifacts/traces.json)
```

### 1.4 Bestehende Dateien (relevant fuer Aenderungen)

| Datei | Inhalt |
|---|---|
| `compose.yaml` | 6 Services: webtrees, mysql, playwright, otel-collector, jaeger, adminer (debug). Zusaetzlich: webtrees-security, mysql-security (security-Profil) |
| `Containerfile.webtrees` | php:8.5-apache, ext-opentelemetry (pecl), mod_rewrite |
| `scripts/setup-webtrees.sh` | Composer-Install (inkl. OTel-Pakete bedingt), DB-Migration, Admin-User, Fixture-Import |
| `otel/otel-collector-config.yaml` | gRPC-Receiver (:4317), Exporter: Jaeger (OTLP) + File (/artifacts/traces.json) |
| `Makefile` | Targets: up, down, clean, setup, test-all, test-static, test-unit, test-integration, test-e2e, test-performance, security-* |

### 1.5 Nicht im Scope

- Security-Testing-Setup (eigenes Vorhaben)
- inspectIT als Gesamtprodukt
- Produktiv-Deployment
- Metriken und Logs (nur Traces)
- Aenderungen am webtrees-Upstream-Code
- Git-Strategie (Commits, Branches) — wird nicht vorgeschrieben

---

## 2. Zielarchitektur

### 2.1 Ausbaustufe 1 — Initiale Implementierung

```
Playwright (Layer 4/5)
  |-- setExtraHTTPHeaders({baggage: test.run_id=X, test.case_id=Y})
  |
  v
Browser (Boomerang + OTel-Plugin)
  |-- Document Load, XHR, Fetch Spans → OTLP/HTTP → Collector:4318
  |-- traceparent in Fetch/XHR Requests (OTel-Plugin automatisch)
  |-- BOOMR.addVar('test.run_id', X) fuer Beacon-Korrelation (komplementaer)
  |
  v
Apache httpd (TRANSPARENT — kein OTel-Modul)
  |-- Leitet traceparent + baggage Header unveraendert an PHP weiter
  |-- mod_substitute injiziert Boomerang-Scripts in HTML-Responses
  |
  v
PHP (OTel Auto-Instrumentation + Custom OTel-Spans-Modul)
  |-- auto-psr15: Root-Span pro Request (HTTP Method, URL, Status)
  |-- auto-pdo: DB-Query-Spans (Statement, Dauer)
  |-- OTel-Spans-Modul: Semantische Attribute (webtrees.action, tree, xref)
  |-- OTel-Spans-Modul: Baggage → Span-Attribute (test.run_id, test.case_id)
  |-- Spans → gRPC → Collector:4317
  |
  v
MySQL 8.4 LTS (KEINE Server-Spans)
  |-- Performance Schema: Aggregierte Query-Statistiken (Default ON)
  |-- Stage-Instrumentierung aktiviert (5-10% Overhead, akzeptabel)
  |-- Extraktion per Bash-Script am Testlauf-Ende
  |
  v
OTel Collector (Contrib, gepinnt)
  |-- Empfaengt: gRPC (PHP, :4317) + HTTP (Boomerang, :4318)
  |-- Exportiert: Jaeger (OTLP) + File (/artifacts/traces.json)
  |
  v
Jaeger (All-in-One, gepinnt)              traces.json (NDJSON)
  |-- UI: http://localhost:16686           |-- trace-report.py (Python)
  |-- API: /api/traces?tags=...            |-- PerfSchema-Integration
```

**Korrelation:** W3C Baggage (`test.run_id`, `test.case_id`) — keine durchgehende traceparent-Kette. Boomerang-Spans nur ueber Zeitfenster korrelierbar (kein `test.run_id` in Browser-Spans).

### 2.2 Ausbaustufe 2 — Playwright Root-Span (Option A)

```
Playwright (OTel SDK in Node.js)
  |-- Erzeugt Root-Span pro Testfall
  |-- Setzt traceparent + baggage via page.route() (dynamisch pro Request)
  |
  v
Browser (Boomerang + OTel-Plugin)
  |-- Document Load Span liest Server-Timing Header → gleicher Trace
  |-- XHR/Fetch Spans propagieren traceparent automatisch
  |
  v
Apache httpd (TRANSPARENT)
  |
  v
PHP (OTel) — sendet Server-Timing Response-Header
  |-- traceparent aus Playwright als Parent-Context
  |-- Server-Timing Header fuer Boomerang-Rueckkanal
  |
  v
MySQL (PerfSchema, unveraendert)
```

**Korrelation:** Kausale Parent-Child-Beziehung via traceparent:
`Playwright-Span → Boomerang-Span → PHP-Span → DB-Spans`

W3C Baggage bleibt als Fundament. BOOMR.addVar() bleibt als komplementaerer Kanal.

### 2.3 Was NICHT implementiert wird

| Komponente | Begruendung |
|---|---|
| Apache OTel-Modul | Kein kompatibles Pre-built Binary fuer Debian Bookworm/Apache 2.4.62 |
| MySQL Telemetry Plugin | Enterprise-only, nicht in Community Edition verfuegbar |
| End-to-End Trace-ID-Propagation PHP→MySQL | Architektonisch nicht moeglich (keine TRACE_ID in PerfSchema Community) |
| mysqld_exporter (Prometheus) | Redundant — PerfSchema-SQL liefert dieselben Daten |
| Boomerang Beacon-Receiver | Nicht noetig — OTel-Traces reichen aus |
| Boomerang Grunt-Build-Pipeline | Unnoetig — synchrones Laden der Roh-Dateien genuegt |

---

## 3. Ausbaustufe 1 — Detailspezifikation

### 3.1 Implementierungsschritte (S1–S10)

#### S1: MySQL 8.0 → 8.4 LTS

**Dateien:** `compose.yaml`

**Aenderungen:**

```yaml
# mysql Service
mysql:
  image: docker.io/library/mysql:lts    # war: mysql:8.0
  command: >
    --character-set-server=utf8mb4
    --collation-server=utf8mb4_bin
    --performance-schema-instrument='stage/%=ON'
    --performance-schema-consumer-events-stages-current=ON
    --performance-schema-consumer-events-stages-history=ON

# mysql-security Service (parallel aktualisieren)
mysql-security:
  image: docker.io/library/mysql:lts    # war: mysql:8.0
```

**Hinweis:** Nach Image-Wechsel muss `make clean` (Volume loeschen) und `make setup` (DB neu initialisieren) ausgefuehrt werden. Ein In-Place-Upgrade von 8.0 auf 8.4 ist nicht vorgesehen.

**Verifizierung (V4):** `mysqladmin ping` Healthcheck mit `caching_sha2_password` muss funktionieren.

#### S2: Container-Image-Versionen pinnen

**Dateien:** `compose.yaml`

**Aenderungen:** Collector und Jaeger auf zum Implementierungszeitpunkt aktuelle stabile Versionen pinnen.

```yaml
otel-collector:
  image: docker.io/otel/opentelemetry-collector-contrib:0.120.0  # war: :latest
  # Konkrete Version beim Implementieren waehlen

jaeger:
  image: docker.io/jaegertracing/all-in-one:1.66  # war: :latest
  # Konkrete Version beim Implementieren waehlen
```

#### S3: OTel Collector HTTP-Receiver fuer Browser-Traces

**Dateien:** `otel/otel-collector-config.yaml`, `compose.yaml`

**Collector-Config erweitern:**

```yaml
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317
      http:                          # NEU: fuer Boomerang Browser-Traces
        endpoint: 0.0.0.0:4318
        cors:
          allowed_origins:
            - "http://localhost:8080"
            - "http://webtrees:80"
          allowed_headers:
            - "*"
          max_age: 7200
```

**compose.yaml — Port 4318 exponieren:**

```yaml
otel-collector:
  ports:
    - "4317:4317"
    - "4318:4318"     # NEU: OTLP/HTTP fuer Boomerang
```

#### S4: `auto-psr15` installieren

**Dateien:** `scripts/setup-webtrees.sh`

In der bestehenden `composer require`-Liste (bedingt auf `OTEL_SDK_DISABLED != true`) hinzufuegen:

```
open-telemetry/opentelemetry-auto-psr15
```

**Verifizierung (V2):** `auto-psr15` 1.2.0 hat Requirement `php: ^8.1` — muss mit PHP 8.5 kompatibel sein. Beim `composer require` verifizieren.

**Ergebnis:** Automatischer Root-Span pro HTTP-Request mit HTTP Method, URL, Status Code. Vollstaendige Trace-Hierarchie: Request → DB-Queries.

#### S5: OTel-Spans-Modul entwickeln

**Dateien:** `modules/otel-spans/module.php`, `modules/otel-spans/OtelSpansModule.php`, `compose.yaml`

**Verzeichnisstruktur:**

```
modules/otel-spans/
  module.php            — return new OtelSpansModule()
  OtelSpansModule.php   — extends AbstractModule implements ModuleCustomInterface, MiddlewareInterface
```

**compose.yaml — Volume-Mount:**

```yaml
webtrees:
  volumes:
    # ... bestehende Volumes ...
    - ./modules/otel-spans:/var/www/html/modules_v4/otel_spans:ro,z  # NEU
```

**Modul-Architektur:**

Das Modul implementiert `MiddlewareInterface` und wird automatisch in die innere Request-Pipeline eingefuegt (nach Auth-Middlewares, vor RequestHandler). Es nutzt das bewiesene Pattern des existierenden `HitCountFooterModule`.

In `process()`:

1. Route-Name extrahieren via `Validator::attributes($request)->route()` → `$route->name` (FQCN des Handlers)
2. Route-Name gegen `ROUTE_MAP` pruefen
3. Wenn gemappt: Span starten mit semantischen Attributen
4. Baggage aus Context extrahieren: `Baggage::getCurrent()->getEntry('test.run_id')`, `->getEntry('test.case_id')`
5. Baggage-Werte als Span-Attribute setzen (mit `urldecode()` fuer test.case_id)
6. `$handler->handle($request)` ausfuehren
7. Span beenden (mit Status-Code aus Response)

**Semantische Attribute pro Span:**

| Attribut | Quelle | Beispiel |
|---|---|---|
| `webtrees.action` | ROUTE_MAP | `view_individual` |
| `webtrees.type` | ROUTE_MAP | `query` oder `edit` |
| `webtrees.tree` | `Validator::attributes($request)->treeOptional()` | `demo` |
| `webtrees.xref` | `Validator::attributes($request)->string('xref', '')` | `I123` |
| `webtrees.route` | `$route->name` (Short-Name) | `IndividualPage` |
| `http.method` | `$request->getMethod()` | `GET` |
| `http.status_code` | `$response->getStatusCode()` | `200` |
| `test.run_id` | Baggage | `a1b2c3d4-e5f6-7890-abcd-ef1234567890` |
| `test.case_id` | Baggage (urldecoded) | `homepage loads without errors` |

**Span-Name-Konvention:** `webtrees.<action>` (z.B. `webtrees.view_individual`). Ungemappte Routes werden ignoriert — `auto-psr15` deckt sie generisch ab.

**Interaktion mit auto-psr15:** Beide Spans existieren. auto-psr15 erzeugt den generischen Request-Root-Span (Parent). Das OTel-Spans-Modul erzeugt einen semantischen Child-Span. Die Dopplung ist erwuenscht: PSR-15 = technische HTTP-Metriken, Custom = geschaeftliche Semantik.

**OTel API-Nutzung:** Das Modul nutzt `OpenTelemetry\API\Globals::tracerProvider()`. Wenn OTel disabled ist (`OTEL_SDK_DISABLED=true`), liefert die API einen NoOp-Tracer — kein `class_exists()`-Check noetig, das Modul funktioniert transparent.

**Verifizierungen:**
- V1: `OTEL_PROPAGATORS` Default `tracecontext,baggage` im Container pruefen; falls nicht, explizit in compose.yaml setzen
- V5: `Baggage::getCurrent()` Timing — Baggage-Context muss propagiert sein bevor das Modul ausfuehrt
- V6: Percent-Encoding Roundtrip — URL-encoded `test.case_id` korrekt durch Stack; `urldecode()` im Modul

**ROUTE_MAP — 56 Routes in 6 Kategorien:**

Kategorie 1: Record-Ansicht (13 Routes)

| Handler-Klasse | `webtrees.action` | `webtrees.type` |
|---|---|---|
| `IndividualPage::class` | `view_individual` | `query` |
| `FamilyPage::class` | `view_family` | `query` |
| `SourcePage::class` | `view_source` | `query` |
| `MediaPage::class` | `view_media` | `query` |
| `NotePage::class` | `view_note` | `query` |
| `SharedNotePage::class` | `view_shared_note` | `query` |
| `RepositoryPage::class` | `view_repository` | `query` |
| `LocationPage::class` | `view_location` | `query` |
| `SubmitterPage::class` | `view_submitter` | `query` |
| `SubmissionPage::class` | `view_submission` | `query` |
| `HeaderPage::class` | `view_header` | `query` |
| `GedcomRecordPage::class` | `view_record` | `query` |
| `TreePage::class` | `view_tree` | `query` |

Kategorie 2: Suche (8 Routes)

| Handler-Klasse | `webtrees.action` | `webtrees.type` |
|---|---|---|
| `SearchGeneralPage::class` | `search_general` | `query` |
| `SearchGeneralAction::class` | `search_general` | `query` |
| `SearchAdvancedPage::class` | `search_advanced` | `query` |
| `SearchAdvancedAction::class` | `search_advanced` | `query` |
| `SearchPhoneticPage::class` | `search_phonetic` | `query` |
| `SearchPhoneticAction::class` | `search_phonetic` | `query` |
| `SearchQuickAction::class` | `search_quick` | `query` |
| `SearchReplacePage::class` | `search_replace_form` | `edit` |

Kategorie 3: Bearbeitung (12 Routes)

| Handler-Klasse | `webtrees.action` | `webtrees.type` |
|---|---|---|
| `EditFactPage::class` | `edit_fact_form` | `edit` |
| `EditFactAction::class` | `edit_fact_save` | `edit` |
| `EditRecordPage::class` | `edit_record_form` | `edit` |
| `EditRecordAction::class` | `edit_record_save` | `edit` |
| `EditRawFactPage::class` | `edit_raw_fact_form` | `edit` |
| `EditRawFactAction::class` | `edit_raw_fact_save` | `edit` |
| `EditRawRecordPage::class` | `edit_raw_record_form` | `edit` |
| `EditRawRecordAction::class` | `edit_raw_record_save` | `edit` |
| `EditNotePage::class` | `edit_note_form` | `edit` |
| `EditNoteAction::class` | `edit_note_save` | `edit` |
| `DeleteRecord::class` | `delete_record` | `edit` |
| `DeleteFact::class` | `delete_fact` | `edit` |

Kategorie 4: Erstellung (8 Routes)

| Handler-Klasse | `webtrees.action` | `webtrees.type` |
|---|---|---|
| `CreateMediaObjectAction::class` | `create_media` | `edit` |
| `CreateNoteAction::class` | `create_note` | `edit` |
| `CreateSourceAction::class` | `create_source` | `edit` |
| `CreateRepositoryAction::class` | `create_repository` | `edit` |
| `CreateLocationAction::class` | `create_location` | `edit` |
| `CreateSubmitterAction::class` | `create_submitter` | `edit` |
| `AddNewFact::class` | `add_fact_form` | `edit` |
| `AddUnlinkedAction::class` | `create_individual` | `edit` |

Kategorie 5: Beziehungen (10 Routes)

| Handler-Klasse | `webtrees.action` | `webtrees.type` |
|---|---|---|
| `AddChildToIndividualPage::class` | `add_child_form` | `edit` |
| `AddChildToIndividualAction::class` | `add_child_save` | `edit` |
| `AddSpouseToIndividualPage::class` | `add_spouse_form` | `edit` |
| `AddSpouseToIndividualAction::class` | `add_spouse_save` | `edit` |
| `AddParentToIndividualPage::class` | `add_parent_form` | `edit` |
| `AddParentToIndividualAction::class` | `add_parent_save` | `edit` |
| `AddChildToFamilyAction::class` | `add_child_to_family` | `edit` |
| `AddSpouseToFamilyAction::class` | `add_spouse_to_family` | `edit` |
| `LinkChildToFamilyAction::class` | `link_child_to_family` | `edit` |
| `LinkSpouseToIndividualAction::class` | `link_spouse` | `edit` |

Kategorie 6: Navigation & Berichte (5 Routes)

| Handler-Klasse | `webtrees.action` | `webtrees.type` |
|---|---|---|
| `CalendarPage::class` | `calendar` | `query` |
| `ReportListPage::class` | `report_list` | `query` |
| `ReportGenerate::class` | `report_generate` | `query` |
| `ContactPage::class` | `contact_form` | `query` |
| `PendingChanges::class` | `pending_changes` | `query` |

**Design-Prinzipien der Route-Map:**
- Nur POST-Actions der Create-/Relationship-Modale (nicht die GET-Modal-Seiten)
- Search-Pages und -Actions teilen denselben `webtrees.action`-Wert
- `SearchReplacePage` als `edit` klassifiziert (schreibende Operation)
- Admin-Routen bewusst ausgelassen (nicht im Scope "Nutzerinteraktion")
- TomSelect/Autocomplete ausgelassen (hochfrequente AJAX-Calls ohne semantischen Wert)

#### S6: Boomerang + mod_substitute

**Dateien:** `Containerfile.webtrees`, `otel/boomerang-init.js`, `otel/boomerang-apache.conf`

**Containerfile.webtrees — Neue Build-Schritte:**

```dockerfile
# Apache-Module: rewrite + substitute (erweitert)
RUN a2enmod rewrite substitute

# Boomerang + OTel-Plugin installieren
ARG BOOMERANG_VERSION=1.815.1
ARG BOOMERANG_OTEL_VERSION=2.0.0-2

# Boomerang via npm-Registry-Tarball (kein npm noetig, nur curl)
RUN mkdir -p /opt/rum/plugins \
    && curl -fSL -o /tmp/boomerang.tgz \
       "https://registry.npmjs.org/boomerangjs/-/boomerangjs-${BOOMERANG_VERSION}.tgz" \
    && tar xzf /tmp/boomerang.tgz -C /tmp \
    && cp /tmp/package/boomerang.js /opt/rum/ \
    && cp /tmp/package/plugins/rt.js /opt/rum/plugins/ \
    && cp /tmp/package/plugins/navtiming.js /opt/rum/plugins/ \
    && cp /tmp/package/plugins/restiming.js /opt/rum/plugins/ \
    && cp /tmp/package/plugins/painttiming.js /opt/rum/plugins/ \
    && cp /tmp/package/plugins/eventtiming.js /opt/rum/plugins/ \
    && rm -rf /tmp/boomerang.tgz /tmp/package

# OTel-Plugin als GitHub Release Asset (minified, 639 KB)
RUN curl -fSL -o /opt/rum/boomerang-opentelemetry.js \
    "https://github.com/inspectIT/boomerang-opentelemetry-plugin/releases/download/${BOOMERANG_OTEL_VERSION}/boomerang-opentelemetry.js"

# Boomerang-Initialisierung und Apache-Config
COPY otel/boomerang-init.js /opt/rum/
COPY otel/boomerang-apache.conf /etc/apache2/conf-available/boomerang.conf
RUN a2enconf boomerang
```

**Reihenfolge im Containerfile:** Die neuen Schritte kommen zwischen die Apache-Modul-Aktivierung und die VirtualHost-Config. Bestehendes `a2enmod rewrite` wird zu `a2enmod rewrite substitute` erweitert.

**otel/boomerang-init.js:**

```javascript
// SPDX-License-Identifier: AGPL-3.0-or-later
// Boomerang + OTel-Plugin Initialisierung fuer webtrees-Testing-Plattform
(function() {
  // Dynamische Collector-URL: Hostname des aktuellen Fensters verwenden
  var collectorHost = window.location.hostname;
  var collectorUrl = 'http://' + collectorHost + ':4318/v1/traces';

  BOOMR.init({
    beacon_url: '/beacon/',  // 404 akzeptiert — OTel-Traces gehen ueber collectorUrl
    OpenTelemetry: {
      samplingRate: 1.0,
      collectorConfiguration: {
        url: collectorUrl
      },
      serviceName: 'webtrees-browser',
      commonAttributes: {
        'deployment.environment': 'test'
      }
    }
  });
})();
```

**otel/boomerang-apache.conf:**

```apache
# SPDX-License-Identifier: AGPL-3.0-or-later
# Boomerang-Injection via mod_substitute (nur wenn OTel aktiv)

# Statische JS-Dateien ausliefern
Alias /rum/ /opt/rum/

<Directory /opt/rum>
    Require all granted
    Options -Indexes
</Directory>

# Boomerang-Injection nur wenn OTEL_SDK_DISABLED != true
PassEnv OTEL_SDK_DISABLED
<If "reqenv('OTEL_SDK_DISABLED') != 'true'">
    AddOutputFilterByType SUBSTITUTE text/html
    Substitute "s|</head>|<script src=\"/rum/boomerang.js\"></script><script src=\"/rum/plugins/rt.js\"></script><script src=\"/rum/plugins/navtiming.js\"></script><script src=\"/rum/plugins/restiming.js\"></script><script src=\"/rum/plugins/painttiming.js\"></script><script src=\"/rum/plugins/eventtiming.js\"></script><script src=\"/rum/boomerang-opentelemetry.js\"></script><script src=\"/rum/boomerang-init.js\"></script></head>|i"
</If>
```

**Verifizierung (V3):** mod_substitute Interaktion mit `FallbackResource /index.php` (interner Subrequest) testen.

**Gepinnte Versionen:**

| Komponente | Version | Quelle |
|---|---|---|
| Boomerang | `1.815.1` | npm-Registry-Tarball |
| Boomerang-OTel-Plugin | `2.0.0-2` | GitHub Release: inspectIT/boomerang-opentelemetry-plugin |

**Boomerang-Relevante Plugins:**

| Plugin | Datei | Funktion |
|---|---|---|
| RT (Round-Trip) | `plugins/rt.js` | Seitenlade-Zeiten |
| NavigationTiming | `plugins/navtiming.js` | W3C Navigation Timing API |
| ResourceTiming | `plugins/restiming.js` | Waterfall-Daten |
| PaintTiming | `plugins/painttiming.js` | FCP, LCP |
| EventTiming | `plugins/eventtiming.js` | FID |

**OTel-Plugin-Protokoll:** OTLP/HTTP mit JSON-Encoding (Content-Type: `application/json`) auf `/v1/traces`. Kein Zipkin, kein proprietaeres Format.

**Beziehung Beacons vs. Traces:** `beacon_url` ist der proprietaere Boomerang-Kanal (wird auf `/beacon/` gesetzt, 404 akzeptiert). `collectorConfiguration.url` ist der OTel-OTLP-Kanal — der relevante Datenfluss.

#### S7: Playwright Baggage-Fixture

**Dateien:** `layer4-e2e/helpers/otel-fixture.ts`

```typescript
// SPDX-License-Identifier: AGPL-3.0-or-later
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

**Verwendung in Tests:** Bestehende Tests aendern ihren Import von `@playwright/test` auf die lokale Fixture-Datei:

```typescript
import { test, expect } from '../helpers/otel-fixture';
```

**Baggage-Format:** W3C Baggage Specification. Schluessel duerfen `.` enthalten. UUIDs brauchen kein Encoding. Testtitel mit Sonderzeichen werden per `encodeURIComponent` encoded.

**RUN_ID-Erzeugung:** `TEST_RUN_ID` als Umgebungsvariable (gesetzt vom Makefile-Target); Fallback: `randomUUID()` pro Testlauf.

#### S8: PerfSchema-Extraktion

**Dateien:** `scripts/extract-perfschema.sh`, `scripts/truncate-perfschema.sh`, `Makefile`

**scripts/truncate-perfschema.sh:**

TRUNCATE vor jedem Testlauf (Clean-Slate). Erfordert Root-Zugriff. Akzeptiert Container-Name als Parameter (Default: `mysql`, fuer Security-Track: `mysql-security`).

```bash
#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail
CONTAINER="${1:-mysql}"
podman-compose exec "$CONTAINER" mysql -u root -p"${MYSQL_ROOT_PASSWORD:-webtrees_test}" -e "
  TRUNCATE TABLE performance_schema.events_statements_summary_by_digest;
  TRUNCATE TABLE performance_schema.events_stages_summary_global_by_event_name;
  TRUNCATE TABLE performance_schema.table_io_waits_summary_by_table;
  TRUNCATE TABLE performance_schema.events_transactions_summary_global_by_event_name;
"
```

**scripts/extract-perfschema.sh:**

Extrahiert nach Testlauf-Ende. CLI-Argument bestimmt Zielverzeichnis (`layer3`, `layer4`, `layer5`). Erzeugt JSON ueber SQL-seitiges `JSON_ARRAYAGG(JSON_OBJECT(...))`.

Zu extrahierende Tabellen und Spalten:

1. `statements_by_digest.json` — Top-50 Queries nach Gesamtzeit:
   - SCHEMA_NAME, DIGEST, DIGEST_TEXT, COUNT_STAR, SUM_TIMER_WAIT (→ ms), AVG_TIMER_WAIT (→ ms), MAX_TIMER_WAIT (→ ms), QUANTILE_95 (→ ms), QUANTILE_99 (→ ms), SUM_ROWS_EXAMINED, SUM_ROWS_SENT, SUM_SELECT_SCAN, SUM_NO_INDEX_USED, SUM_CREATED_TMP_DISK_TABLES, SUM_LOCK_TIME (→ ms), QUERY_SAMPLE_TEXT (LEFT 500), FIRST_SEEN, LAST_SEEN
   - Einheitenumrechnung: Picosekunden / 1e9 = Millisekunden

2. `table_io_waits.json` — I/O pro Tabelle:
   - OBJECT_NAME, COUNT_STAR, COUNT_READ, COUNT_WRITE, COUNT_FETCH, COUNT_INSERT, COUNT_UPDATE, COUNT_DELETE, SUM_TIMER_WAIT (→ ms)

3. `stages_global.json` — Query-Phasen:
   - EVENT_NAME, COUNT_STAR, SUM_TIMER_WAIT (→ ms), AVG_TIMER_WAIT (→ ms)

4. `transactions_global.json` — Transaktions-Statistiken

**summary.txt** — Menschenlesbare Zusammenfassung: Top-10-Queries, Top-5-Tabellen, Warnungen (Full Table Scans, No-Index-Queries, Temp-Tabellen auf Disk).

**Artefakt-Struktur:**

```
artifacts/
├── layer3/
│   └── perfschema/
│       ├── statements_by_digest.json
│       ├── table_io_waits.json
│       ├── stages_global.json
│       ├── transactions_global.json
│       └── summary.txt
├── layer4/
│   └── perfschema/...
└── layer5/
    └── perfschema/...
```

**Makefile-Targets:**

```makefile
perfschema-truncate: ## PerfSchema-Daten zuruecksetzen
    scripts/truncate-perfschema.sh

perfschema-extract: ## PerfSchema-Daten extrahieren (LAYER=3|4|5)
    scripts/extract-perfschema.sh $(LAYER)
```

#### S9: Trace-Report-Script

**Dateien:** `scripts/trace-report.py`, `scripts/trace-report.sh`, `Makefile`

**Technologie:** Python 3 (Standardbibliothek, keine externen Pakete). Laeuft auf dem Host (Fedora, Python 3.12+) oder im Playwright-Container (Python 3.11).

**scripts/trace-report.sh** — Bash-Wrapper:

```bash
#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail
exec python3 "$(dirname "$0")/trace-report.py" "$@"
```

**scripts/trace-report.py — Architektur:**

```
Eingabe:
  --run-id <UUID>                              (erforderlich)
  --traces-file /artifacts/traces.json         (Default)
  --perfschema-dir /artifacts/layerN/perfschema/ (optional)
  --output-json /artifacts/trace-report.json   (optional)
  --layer <3|4|5>                              (bestimmt PerfSchema-Pfad)

Verarbeitung:
  1. OTLP NDJSON parsen (json.loads pro Zeile)
  2. Spans nach test.run_id filtern
  3. Spans nach test.case_id gruppieren (urldecode)
  4. Hierarchie aufloesen (parentSpanId → Children)
  5. Layer zuordnen (service.name + scope)
  6. Boomerang-Spans temporal korrelieren (Zeitfenster)
  7. PerfSchema-Daten laden (falls vorhanden)

Ausgabe:
  1. stdout: Formatierter Text-Report
  2. JSON-Datei unter /artifacts/ (maschinenlesbar)
```

**Layer-Zuordnung:**

| service.name | scope (enthaelt) | Display-Layer |
|---|---|---|
| `webtrees-browser` | * | Browser (RUM) |
| `webtrees` | `pdo` | DB Query |
| `webtrees` | `psr15` | PHP Backend |
| `webtrees` | `otel-spans` | webtrees Custom |
| `webtrees` | * (andere) | PHP |

**Gewuenschtes Ausgabeformat (Beispiel):**

```
=== Testlauf: a1b2c3d4 (2026-03-29T14:30:00Z) ===

Testfall: homepage loads without errors
  Browser (RUM):       120ms  [documentLoad]
  PHP Backend:         280ms  [psr15]
    +-- webtrees.action: tree_page  [otel-spans]
    +-- DB Query:        45ms  SELECT ... FROM wt_individuals  [pdo]
    +-- DB Query:        12ms  SELECT ... FROM wt_name         [pdo]

--- Performance Schema (Testlauf-Aggregat) ---
Top SQL by Latenz:
  1. SELECT ... FROM wt_individuals WHERE ...  avg=12ms  calls=847  rows=4230
  2. SELECT ... FROM wt_name WHERE ...         avg=8ms   calls=1203 rows=2406

Table I/O:
  wt_individuals:  reads=4230  writes=0  total_wait=9.8s

Warnungen: keine
```

**PerfSchema-Integration:** Testlauf-Aggregat (nicht pro Testfall, da PerfSchema keine Testfall-Korrelation hat). TRUNCATE vor Testlauf garantiert, dass Daten exakt den Testlauf abdecken.

**Verifizierung (V7):** File-Exporter Flush-Timing. Collector flusht Spans im Batch (Default: 5s). Bei automatischer Integration ggf. `sleep 5` vor Report-Generierung.

#### S10: Makefile-Integration

**Dateien:** `Makefile`

**Neue Targets:**

```makefile
perfschema-truncate: ## PerfSchema-Daten zuruecksetzen
    scripts/truncate-perfschema.sh

perfschema-extract: ## PerfSchema-Daten extrahieren (LAYER=3|4|5)
    scripts/extract-perfschema.sh $(LAYER)

trace-report: ## Trace-Report generieren (RUN_ID=... LAYER=...)
    @if [ -z "$(RUN_ID)" ]; then \
        echo "Fehler: RUN_ID nicht gesetzt. Aufruf: make trace-report RUN_ID=<uuid> [LAYER=3|4|5]"; \
        exit 1; \
    fi
    scripts/trace-report.sh \
        --run-id "$(RUN_ID)" \
        --traces-file artifacts/traces.json \
        $(if $(LAYER),--layer $(LAYER)) \
        --output-json artifacts/trace-report-$(RUN_ID).json
```

**Integrierte Test-Targets (spaetere Phase):**

```makefile
test-e2e:
    @RUN_ID=$$(uuidgen); \
    echo "Testlauf: $$RUN_ID"; \
    -scripts/truncate-perfschema.sh; \
    TEST_RUN_ID=$$RUN_ID $(COMPOSE) exec playwright npx playwright test \
        --config=/tests/e2e/playwright.config.ts; \
    -scripts/extract-perfschema.sh layer4; \
    scripts/trace-report.sh --run-id $$RUN_ID --layer 4 \
        --output-json artifacts/trace-report-$$RUN_ID.json || true
```

### 3.2 Abhaengigkeitsgraph

```
                S1 (MySQL 8.4)
                |           \
                |            S8 (PerfSchema)──────────────────┐
                |                                              |
S2 (Pinning)    S3 (HTTP-Receiver)    S4 (auto-psr15)         |
                |                      |                       |
                |                      S5 (OTel-Spans-Modul)   |
                |                      |                       |
                S6 (Boomerang)         S7 (Baggage-Fixture)    |
                |                      |                       |
                |                      └───────────┬───────────┘
                |                                  |
                |                         S9 (Trace-Report)
                |                                  |
                └──────────────────────────────────┤
                                                   |
                                          S10 (Makefile-Integration)
```

**Parallelisierbar:** S1, S2, S3, S4 sind voneinander unabhaengig. S5 und S6 koennen parallel laufen. S7 und S8 koennen parallel laufen.

**Kritischer Pfad:** S4 → S5 → S7 → S9

### 3.3 Empfohlene Phasen

| Phase | Schritte | Aufwand | Kerninhalt |
|---|---|---|---|
| 1 Infrastruktur | S1–S4 | 0.5 PT | MySQL 8.4, Image-Pinning, HTTP-Receiver, auto-psr15 |
| 2 PHP-Instrumentierung | S5 | 0.5–1 PT | OTel-Spans-Modul (semantische Attribute, Baggage-Konvertierung) |
| 3 Testlauf-Korrelation | S7–S8 | 0.75 PT | Playwright Baggage-Fixture, PerfSchema-Extraktion |
| 4 Auswertung | S9–S10 | 1–2 PT | Trace-Report-Script, Makefile-Integration |
| 5 Browser-RUM | S6 | 1–2 PT | Boomerang + mod_substitute |

---

## 4. Ausbaustufe 2 — Detailspezifikation

### 4.1 Scope

Ausbaustufe 2 erweitert Ausbaustufe 1 um eine kausale Trace-Kette mittels Playwright OTel SDK. Alle Komponenten aus Ausbaustufe 1 bleiben unveraendert. Baggage-Korrelation bleibt als Fundament bestehen.

### 4.2 Zusaetzliche Komponenten

#### Playwright OTel SDK (Node.js)

**Neue npm-Pakete im Playwright-Container:**

- `@opentelemetry/api`
- `@opentelemetry/sdk-node`
- `@opentelemetry/exporter-trace-otlp-http`
- `@opentelemetry/resources`

**Aenderung am Playwright Baggage-Fixture:**

Die Fixture erzeugt einen Root-Span pro Testfall und setzt `traceparent` dynamisch pro Request:

```typescript
// Erweiterte Fixture (Ausbaustufe 2)
page: async ({ page }, use, testInfo) => {
  const tracer = trace.getTracer('playwright-tests');
  const span = tracer.startSpan(`test: ${testInfo.title}`);
  const ctx = trace.setSpan(context.active(), span);

  // Dynamischer traceparent pro Request via page.route()
  await page.route('**/*', async (route) => {
    const traceId = span.spanContext().traceId;
    const spanId = /* neue Span-ID pro Request */;
    const traceparent = `00-${traceId}-${spanId}-01`;
    await route.continue({
      headers: {
        ...route.request().headers(),
        'traceparent': traceparent,
        'baggage': `test.run_id=${runId},test.case_id=${caseId}`,
      },
    });
  });

  await use(page);
  span.end();
},
```

#### PHP Server-Timing Header

PHP muss den Trace-Context im `Server-Timing`-Response-Header zuruecksenden, damit Boomerangs `instrumentation-document-load` den Browser-Span in den gleichen Trace einordnen kann.

**Aenderung am OTel-Spans-Modul:** Nach `$handler->handle($request)` den aktuellen Span-Context als `Server-Timing`-Header in die Response schreiben:

```php
$response = $handler->handle($request);
$spanContext = Span::getCurrent()->getContext();
$serverTiming = sprintf(
    'traceparent;desc="00-%s-%s-01"',
    $spanContext->getTraceId(),
    $spanContext->getSpanId()
);
return $response->withHeader('Server-Timing', $serverTiming);
```

#### Resultierende Trace-Kette (4 Stufen)

```
Playwright Root-Span (test: "homepage loads without errors")
  ├── Boomerang documentFetch (Browser → Server, via traceparent)
  │     └── PHP Request-Span (Server, Child des propagierten traceparent)
  │           ├── webtrees.view_tree (Custom-Span)
  │           ├── PDO Span (SELECT users ...)
  │           ├── PDO Span (SELECT trees ...)
  │           └── PDO Span (SELECT modules ...)
  ├── Boomerang documentLoad (Browser-seitiges Timing)
  │     └── resourceFetch Spans (CSS, JS, Bilder)
  └── Boomerang XHR/Fetch Spans (AJAX-Requests nach Page-Load)
        └── PHP Request-Span (nachfolgende Requests)
              └── PDO Spans
```

### 4.3 Aenderungen am Trace-Report

Das `trace-report.py` wird erweitert um:

1. Erkennung von Playwright-Spans (`service.name` fuer Playwright)
2. Darstellung der vierstufigen Hierarchie
3. Validierung der Parent-Child-Beziehungen (traceparent-Konsistenz)

---

## 5. Testlauf und Archivierung nach jeder Ausbaustufe

### 5.1 Testlauf-Prozedur (nach jeder Ausbaustufe)

Nach Abschluss jeder Ausbaustufe wird ein vollstaendiger Komponentenintegrationstest- und Systemtestlauf durchgefuehrt:

```bash
# 1. Stack neu aufsetzen (sauberer Zustand)
make clean && make up && make setup

# 2. Komponentenintegrationstest (Layer 3)
make test-integration

# 3. Systemtest (Layer 4)
make test-e2e

# 4. Optional: Performanztest (Layer 5)
make test-performance
```

### 5.2 Artefakt-Archivierung (einmalig pro Ausbaustufe)

Nach jedem Testlauf werden die Artefakte als ZIP archiviert, bevor ein Cleanup fuer die naechste Ausbaustufe stattfindet. Diese Archivierung ist ein einmaliger manueller Schritt im Plan — sie wird NICHT dauerhaft geskriptet oder in Makefile-Targets eingebaut.

**Archivierungsschritte:**

```bash
# Ausbaustufe N: Artefakte sichern
STUFE="ausbaustufe-1"  # bzw. "ausbaustufe-2"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
ARCHIVE="artifacts_${STUFE}_${TIMESTAMP}.zip"

# ZIP erstellen (alle Artefakte inkl. Traces, PerfSchema, Reports)
zip -r "$ARCHIVE" artifacts/

# Archiv aus dem Arbeitsverzeichnis verschieben (optional)
mkdir -p docs/laufzeit_analyse/archives
mv "$ARCHIVE" docs/laufzeit_analyse/archives/

# Aufraeumen fuer naechste Ausbaustufe
make clean
```

**Archivierte Inhalte:**

| Pfad | Inhalt |
|---|---|
| `artifacts/traces.json` | Alle OTel-Spans (NDJSON) |
| `artifacts/layer3/perfschema/*.json` | PerfSchema-Daten Layer 3 |
| `artifacts/layer4/perfschema/*.json` | PerfSchema-Daten Layer 4 |
| `artifacts/layer4/*.html` | Playwright-Report |
| `artifacts/trace-report-*.json` | Trace-Report (JSON) |

### 5.3 Erwartete Testlauf-Ergebnisse

**Nach Ausbaustufe 1:**

| Pruefpunkt | Erwartung |
|---|---|
| `make test-all` (ohne OTel) | Gruen — `OTEL_SDK_DISABLED=true` getestet |
| `make test-all` (mit OTel) | Gruen — Default-Konfiguration |
| Jaeger UI: PHP-Spans | Sichtbar: auto-psr15 + auto-pdo + OTel-Spans-Modul |
| Jaeger UI: Browser-Spans | Sichtbar: `webtrees-browser` (Boomerang) |
| Jaeger UI: `test.run_id` Filter | Filterbar nach Baggage-Attribut |
| PerfSchema JSON | Vorhanden unter `artifacts/layerN/perfschema/` |
| Trace-Report | Generierbar via `make trace-report RUN_ID=... LAYER=...` |

**Nach Ausbaustufe 2:**

| Pruefpunkt | Erwartung |
|---|---|
| Jaeger UI: Playwright-Spans | Sichtbar: Root-Span pro Testfall |
| Jaeger UI: Trace-Kette | 4 Stufen: Playwright → Boomerang → PHP → DB |
| Server-Timing Header | In PHP-Responses vorhanden |
| traceparent Propagation | Browser-Spans im selben Trace wie PHP-Spans |

---

## 6. Abnahmekriterien

### 6.1 Ausbaustufe 1

1. **Jaeger UI zeigt Spans:** PHP-Spans (auto-psr15, auto-pdo, Custom OTel-Spans-Modul) und Browser-Spans (Boomerang) sind in der Jaeger-Oberflaeche sichtbar und inspizierbar.
2. **Korrelationskette funktioniert:** PHP-Spans enthalten `test.run_id` und `test.case_id` als Attribute. Die Span-Hierarchie ist korrekt: PSR-15 Request-Root-Span → Custom-Span → DB-Spans.
3. **Testfallzuordnung funktioniert:** Jeder Playwright-Testfall setzt `test.run_id` und `test.case_id` als Baggage-Header. Diese Werte erscheinen als Span-Attribute in Jaeger.
4. **Auswertung moeglich:** `trace-report.py` kann die OTLP-NDJSON-Daten parsen, Spans nach Testfall gruppieren und Layer-Aufschluesselung ausgeben. PerfSchema-Daten ergaenzen den Report.
5. **Releasefaehigkeit:** `make test-all` laeuft sowohl mit `OTEL_SDK_DISABLED=false` (Default) als auch mit `OTEL_SDK_DISABLED=true` fehlerfrei.

### 6.2 Ausbaustufe 2

1. **Playwright Root-Span:** Ein Root-Span pro Playwright-Testfall ist in Jaeger sichtbar.
2. **Kausale Trace-Kette:** Playwright-Span → Boomerang-Span → PHP-Span → DB-Spans bilden eine zusammenhaengende Trace-Hierarchie (Parent-Child via traceparent).
3. **Server-Timing Rueckkanal:** PHP sendet `Server-Timing`-Header; Boomerang `instrumentation-document-load` liest ihn und ordnet den Document-Load-Span in den Trace ein.

---

## 7. Risiken und Mitigationen

| # | Risiko | Schwere | Mitigation |
|---|---|---|---|
| R1 | Boomerang hat kein fertiges Bundle | Mittel | Synchrones Laden der Roh-JS-Dateien aus npm-Tarball (kein Build noetig) |
| R2 | CORS-Probleme Browser → OTel Collector | Mittel | CORS-Config im Collector HTTP-Receiver mit Origin-Whitelist |
| R3 | Boomerang OTel-Plugin geringe Community (10 Sterne) | Mittel | Funktional ausgereift; OTel JS SDK stabil; Version gepinnt |
| R4 | zone.js-Seiteneffekte durch Boomerang OTel-Plugin | Niedrig | webtrees ist kein SPA; jQuery-basiert; zone.js-Patches harmlos |
| R5 | mod_substitute fragile Config-Syntax | Niedrig | Init-Script in externe Datei ausgelagert |
| R8 | MySQL 8.4 Healthcheck-Kompatibilitaet | Mittel | Bestehender Healthcheck mit `mysqladmin ping` kompatibel |
| R9 | Root-Zugriff fuer PerfSchema TRUNCATE | Niedrig | Root-Passwort in compose.yaml als ENV — im Testkontext akzeptabel |
| R10 | PerfSchema-Daten gehen bei Container-Neustart verloren | Mittel | Extraktion muss VOR `make down` erfolgen |
| R11 | BaggagePropagator nicht aktiv in PHP | Hoch | Default ist `tracecontext,baggage`; im Container verifizieren |
| R12 | Percent-Encoding-Roundtrip fuer test.case_id | Mittel | `urldecode()` im Modul; Tests mit Sonderzeichen |
| R13 | Upstream-Aenderung der Route-Namen | Mittel | Route-Namen sind FQCN; ungemappte Routen werden ignoriert |
| R14 | Doppelte Spans durch auto-psr15 + OTel-Spans-Modul | Niedrig | Erwuenscht — PSR-15 = technisch, Custom = semantisch |
| R16 | Boomerang-Spans ohne test.run_id | Mittel | Workers=1 minimiert Ueberlappung; temporale Korrelation |

**Eliminierte Risiken (durch Architekturentscheidung):**
- Apache ABI-Inkompatibilitaet — Apache OTel-Modul wird nicht verwendet
- MySQL Telemetry Enterprise-only — MySQL Telemetry wird nicht verwendet

---

## 8. Verifizierungspunkte (bei Implementierung zu pruefen)

| # | Punkt | Was zu pruefen ist |
|---|---|---|
| V1 | OTEL_PROPAGATORS Default | Im Container pruefen ob Default `tracecontext,baggage` gilt |
| V2 | ext-opentelemetry + PHP 8.5 | `auto-psr15` 1.2.0 Kompatibilitaet beim `composer require` |
| V3 | mod_substitute + FallbackResource | Korrekte Interaktion mit `FallbackResource /index.php` |
| V4 | MySQL 8.4 Healthcheck | `mysqladmin ping` mit `caching_sha2_password` |
| V5 | Baggage::getCurrent() Timing | Baggage-Context muss vor OTel-Spans-Modul propagiert sein |
| V6 | Percent-Encoding Roundtrip | URL-encoded `test.case_id` korrekt durch Stack |
| V7 | File-Exporter Flush-Timing | Collector-Flush vor Report-Generierung (ggf. `sleep 5`) |

---

## 9. Komponentenversionen (Ziel-Zustand)

| Komponente | Version | Quelle | Verwaltungsort |
|---|---|---|---|
| MySQL | `mysql:lts` (= 8.4.x) | Docker Hub | `compose.yaml` |
| OTel Collector | Gepinnte Version (z.B. `0.120.0`) | Docker Hub | `compose.yaml` |
| Jaeger | Gepinnte Version (z.B. `1.66`) | Docker Hub | `compose.yaml` |
| Boomerang | `1.815.1` | npm-Registry-Tarball | `Containerfile.webtrees` (ARG) |
| Boomerang OTel-Plugin | `2.0.0-2` | GitHub Release | `Containerfile.webtrees` (ARG) |
| `opentelemetry-auto-psr15` | (aktuell: 1.2.0) | Composer | `setup-webtrees.sh` |
| OTel-Spans-Modul | Custom | Repo: `modules/otel-spans/` | `compose.yaml` (Volume-Mount) |

---

## 10. SELinux-Hinweise (Fedora/rootless Podman)

- Neue Bind-Mounts verwenden `:z` (shared Label), konsistent mit bestehenden Mounts
- **Kein `:Z`** auf Verzeichnisse, die der Compose-Stack gleichzeitig mountet
- `modules/otel-spans` wird als `:ro,z` gemountet (Read-only, shared)
- Bei MySQL-Upgrade: `make clean` (Volume loeschen), dann `make up && make setup`
- JS-Dateien (Boomerang) sind per COPY im Image — kein SELinux-Relevanz

---

## 11. Graceful Degradation (OTEL_SDK_DISABLED=true)

| Komponente | Verhalten bei disabled OTel |
|---|---|
| `opentelemetry-auto-psr15` | Nicht installiert (Composer-Skip in setup-webtrees.sh) |
| OTel-Spans-Modul | NoOp-Tracer via OTel API — keine Spans, kein Overhead |
| Boomerang + OTel-Plugin | Injection per `<If>`-Direktive unterdrueckt |
| PerfSchema-Extraktion | Unabhaengig von OTel — laeuft gegen MySQL |
| Trace-Report | Keine Spans → Fehlermeldung, kein Abbruch |

**Testpflicht:** `make test-all` muss sowohl mit `OTEL_SDK_DISABLED=false` als auch mit `OTEL_SDK_DISABLED=true` fehlerfrei laufen.

---

## 12. Aufgeschobene Punkte (nicht blockierend)

| # | Punkt | Prioritaet |
|---|---|---|
| S1 | Server-Timing Header (Voraussetzung Ausbaustufe 2) | Steigt bei Ausbaustufe 2 |
| S2 | Content-Security-Policy (falls CSP eingefuehrt) | Niedrig |
| S3 | PerfSchema Baseline-Vergleich (automatische Schwellwerte) | Mittel |
| S4 | Digest-Text-Matching (PDO-Span ↔ PerfSchema Korrelation) | Mittel |
| S5 | ARM64-Support (fuer Fedora/x86-64 irrelevant) | Niedrig |

---

## Anhang A: PerfSchema-Extraktions-SQL (Referenz fuer S8)

### A.1 statements_by_digest.json

```sql
SELECT JSON_ARRAYAGG(JSON_OBJECT(
  'schema', SCHEMA_NAME,
  'digest', DIGEST,
  'digest_text', DIGEST_TEXT,
  'count', COUNT_STAR,
  'total_ms', ROUND(SUM_TIMER_WAIT/1000000000, 2),
  'avg_ms', ROUND(AVG_TIMER_WAIT/1000000000, 2),
  'max_ms', ROUND(MAX_TIMER_WAIT/1000000000, 2),
  'p95_ms', ROUND(QUANTILE_95/1000000000, 2),
  'p99_ms', ROUND(QUANTILE_99/1000000000, 2),
  'rows_examined', SUM_ROWS_EXAMINED,
  'rows_sent', SUM_ROWS_SENT,
  'full_scans', SUM_SELECT_SCAN,
  'no_index', SUM_NO_INDEX_USED,
  'tmp_disk_tables', SUM_CREATED_TMP_DISK_TABLES,
  'lock_time_ms', ROUND(SUM_LOCK_TIME/1000000000, 2),
  'sample_text', LEFT(QUERY_SAMPLE_TEXT, 500),
  'first_seen', FIRST_SEEN,
  'last_seen', LAST_SEEN
))
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = '${MYSQL_DATABASE:-webtrees_test}'
  AND DIGEST_TEXT IS NOT NULL
ORDER BY SUM_TIMER_WAIT DESC
LIMIT 50
```

**Einheitenumrechnung:** PerfSchema speichert in Picosekunden. Division durch 1e9 = Millisekunden.

### A.2 table_io_waits.json

```sql
SELECT JSON_ARRAYAGG(JSON_OBJECT(
  'table_name', OBJECT_NAME,
  'count_star', COUNT_STAR,
  'count_read', COUNT_READ,
  'count_write', COUNT_WRITE,
  'count_fetch', COUNT_FETCH,
  'count_insert', COUNT_INSERT,
  'count_update', COUNT_UPDATE,
  'count_delete', COUNT_DELETE,
  'total_wait_ms', ROUND(SUM_TIMER_WAIT/1000000000, 2)
))
FROM performance_schema.table_io_waits_summary_by_table
WHERE OBJECT_SCHEMA = '${MYSQL_DATABASE:-webtrees_test}'
ORDER BY SUM_TIMER_WAIT DESC
```

### A.3 stages_global.json

```sql
SELECT JSON_ARRAYAGG(JSON_OBJECT(
  'event_name', EVENT_NAME,
  'count', COUNT_STAR,
  'total_ms', ROUND(SUM_TIMER_WAIT/1000000000, 2),
  'avg_ms', ROUND(AVG_TIMER_WAIT/1000000000, 2)
))
FROM performance_schema.events_stages_summary_global_by_event_name
WHERE COUNT_STAR > 0
ORDER BY SUM_TIMER_WAIT DESC
```

### A.4 Aufruf-Muster

```bash
podman-compose exec mysql mysql -u root -p"${MYSQL_ROOT_PASSWORD:-webtrees_test}" \
  --batch --raw --skip-column-names -e "<SQL>" > "${TARGET_DIR}/datei.json"
```

---

## Anhang B: trace-report.py — Referenzimplementierung (fuer S9)

### B.1 Kern-Datenstruktur

```python
@dataclass
class Span:
    trace_id: str
    span_id: str
    parent_span_id: Optional[str]
    name: str
    start_ns: int
    end_ns: int
    duration_ms: float
    service_name: str
    scope: str
    attributes: dict = field(default_factory=dict)
    children: list = field(default_factory=list)
```

### B.2 OTLP NDJSON Parser

```python
def parse_traces(traces_path: str, run_id: str) -> list[Span]:
    """Parse OTLP NDJSON, filtere nach test.run_id."""
    spans = []
    with open(traces_path) as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            data = json.loads(line)
            for rs in data.get("resourceSpans", []):
                res_attrs = _extract_attrs(
                    rs.get("resource", {}).get("attributes", [])
                )
                svc = res_attrs.get("service.name", "unknown")
                for ss in rs.get("scopeSpans", []):
                    scope = ss.get("scope", {}).get("name", "unknown")
                    for s in ss.get("spans", []):
                        attrs = _extract_attrs(s.get("attributes", []))
                        if attrs.get("test.run_id") != run_id:
                            continue
                        start = int(s["startTimeUnixNano"])
                        end = int(s["endTimeUnixNano"])
                        spans.append(Span(
                            trace_id=s["traceId"],
                            span_id=s["spanId"],
                            parent_span_id=s.get("parentSpanId") or None,
                            name=s["name"],
                            start_ns=start,
                            end_ns=end,
                            duration_ms=round((end - start) / 1_000_000, 2),
                            service_name=svc,
                            scope=scope,
                            attributes=attrs,
                        ))
    return spans


def _extract_attrs(attr_list: list) -> dict:
    """OTLP-Attribute-Liste in flaches dict konvertieren."""
    result = {}
    for a in attr_list:
        val = a.get("value", {})
        result[a["key"]] = (
            val.get("stringValue")
            or val.get("intValue")
            or val.get("doubleValue")
            or val.get("boolValue")
            or str(val)
        )
    return result
```

### B.3 Hierarchie-Aufloesung und Gruppierung

```python
def group_by_test_case(spans: list[Span]) -> dict[str, list[Span]]:
    """Spans nach test.case_id gruppieren."""
    groups = defaultdict(list)
    for span in spans:
        case_id = span.attributes.get("test.case_id", "(unbekannt)")
        case_id = unquote(case_id)  # Percent-Decoding
        groups[case_id].append(span)
    return dict(groups)


def build_hierarchy(spans: list[Span]) -> list[Span]:
    """Span-Liste in Baumstruktur umwandeln. Gibt Root-Spans zurueck."""
    by_id = {s.span_id: s for s in spans}
    roots = []
    for span in spans:
        if span.parent_span_id and span.parent_span_id in by_id:
            by_id[span.parent_span_id].children.append(span)
        else:
            roots.append(span)
    for span in spans:
        span.children.sort(key=lambda s: s.start_ns)
    roots.sort(key=lambda s: s.start_ns)
    return roots


def classify_span(span: Span) -> str:
    """Span einem Display-Layer zuordnen."""
    if span.service_name == "webtrees-browser":
        return "Browser (RUM)"
    if span.scope and "pdo" in span.scope:
        return "DB Query"
    if span.scope and "psr15" in span.scope:
        return "PHP Backend"
    if span.scope and "otel-spans" in span.scope:
        return "webtrees Custom"
    if span.service_name == "webtrees":
        return "PHP"
    return "Unknown"
```

### B.4 CLI-Interface

```python
parser = argparse.ArgumentParser(description="Trace-Report Generator")
parser.add_argument("--run-id", required=True, help="test.run_id UUID")
parser.add_argument("--traces-file", default="artifacts/traces.json")
parser.add_argument("--layer", choices=["3", "4", "5"], default=None)
parser.add_argument("--perfschema-dir", default=None)
parser.add_argument("--output-json", default=None)
```

### B.5 Boomerang-Spans temporale Korrelation

Boomerang-Spans haben kein `test.run_id`-Attribut. Sie werden ueber das Zeitfenster des Testlaufs korreliert:

1. Zeitfenster aus PHP-Spans bestimmen (min startNano, max endNano)
2. Boomerang-Spans (`service.name == "webtrees-browser"`) im Zeitfenster filtern
3. Bei `workers: 1` ist Ueberlappung unwahrscheinlich

### B.6 Python-Abhaengigkeiten

Ausschliesslich Standardbibliothek: `json`, `sys`, `os`, `argparse`, `datetime`, `urllib.parse`, `dataclasses`, `collections`, `typing`.

---

## Anhang C: OTel-Spans-Modul — PHP-Referenzstruktur (fuer S5)

### C.1 module.php

```php
<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace OtelSpans;

return new OtelSpansModule();
```

### C.2 OtelSpansModule.php — Struktur

```php
<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace OtelSpans;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Baggage\Baggage;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

class OtelSpansModule extends AbstractModule implements ModuleCustomInterface, MiddlewareInterface
{
    use ModuleCustomTrait;

    // 56 Routes in 6 Kategorien — siehe ROUTE_MAP in Abschnitt 3.1 S5
    private const ROUTE_MAP = [
        // Kategorie 1: Record-Ansicht
        \Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage::class
            => ['action' => 'view_individual', 'type' => 'query'],
        // ... (alle 56 Eintraege aus der Route-Map)
    ];

    public function title(): string
    {
        return 'OTel Spans';
    }

    public function description(): string
    {
        return 'OpenTelemetry semantic spans for webtrees testing platform';
    }

    public function customModuleVersion(): string
    {
        return '1.0.0';
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $route = Validator::attributes($request)->route();
        $routeName = $route->name ?? '';
        $mapping = self::ROUTE_MAP[$routeName] ?? null;

        if ($mapping === null) {
            return $handler->handle($request);
        }

        $tracer = Globals::tracerProvider()->getTracer('otel-spans');
        $span = $tracer->spanBuilder('webtrees.' . $mapping['action'])
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $scope = $span->activate();

        try {
            // Semantische Attribute
            $span->setAttribute('webtrees.action', $mapping['action']);
            $span->setAttribute('webtrees.type', $mapping['type']);
            $span->setAttribute('webtrees.route', $this->shortName($routeName));
            $span->setAttribute('http.method', $request->getMethod());

            // Tree und XREF (optional)
            $tree = Validator::attributes($request)->treeOptional();
            if ($tree !== null) {
                $span->setAttribute('webtrees.tree', $tree->name());
            }
            $xref = Validator::attributes($request)->string('xref', '');
            if ($xref !== '') {
                $span->setAttribute('webtrees.xref', $xref);
            }

            // Baggage → Span-Attribute
            $baggage = Baggage::getCurrent();
            $runId = $baggage->getEntry('test.run_id');
            if ($runId !== null) {
                $span->setAttribute('test.run_id', $runId->getValue());
            }
            $caseId = $baggage->getEntry('test.case_id');
            if ($caseId !== null) {
                $span->setAttribute('test.case_id', urldecode($caseId->getValue()));
            }

            $response = $handler->handle($request);

            $span->setAttribute('http.status_code', $response->getStatusCode());
            $span->setStatus(
                $response->getStatusCode() >= 400
                    ? StatusCode::STATUS_ERROR
                    : StatusCode::STATUS_OK
            );

            return $response;
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return str_replace('::class', '', end($parts));
    }
}
```

**Hinweise:**
- Das Modul nutzt ausschliesslich `OpenTelemetry\API\*` (nicht das SDK) — der NoOp-Tracer greift automatisch wenn OTel disabled ist.
- `Validator::attributes($request)->route()` ist das bewiesene Pattern aus `HitCountFooterModule`.
- `Validator::attributes($request)->treeOptional()` und `->string('xref', '')` sind defensiv — kein Fehler bei fehlenden Attributen.
- Die `Baggage::getCurrent()` API gibt `null` fuer nicht vorhandene Eintraege zurueck — Null-Check erforderlich.

---

## Anhang D: OTLP JSON Datenformat (Referenz)

### D.1 OTLP NDJSON Struktur (pro Zeile in traces.json)

```json
{
  "resourceSpans": [
    {
      "resource": {
        "attributes": [
          {"key": "service.name", "value": {"stringValue": "webtrees"}},
          {"key": "telemetry.sdk.language", "value": {"stringValue": "php"}}
        ]
      },
      "scopeSpans": [
        {
          "scope": {
            "name": "io.opentelemetry.contrib.php.pdo",
            "version": "1.0.0"
          },
          "spans": [
            {
              "traceId": "abcdef1234567890abcdef1234567890",
              "spanId": "1234567890abcdef",
              "parentSpanId": "fedcba0987654321",
              "name": "PDO::query",
              "kind": 3,
              "startTimeUnixNano": "1743260400000000000",
              "endTimeUnixNano": "1743260400045000000",
              "attributes": [
                {"key": "db.statement", "value": {"stringValue": "SELECT ..."}},
                {"key": "test.run_id", "value": {"stringValue": "a1b2c3d4-..."}}
              ],
              "status": {"code": 0}
            }
          ]
        }
      ]
    }
  ]
}
```

### D.2 Zeiteinheiten

- **OTLP:** Nanosekunden (als String serialisiert wegen 64-Bit-Integer)
- **PerfSchema:** Picosekunden (Division durch 1e9 = Millisekunden)
- **Jaeger API:** Mikrosekunden
- **Darstellung im Report:** Millisekunden

### D.3 Span-Quellen und Zuordnung

| Quelle | service.name | telemetry.sdk.language | Scope-Name |
|---|---|---|---|
| PHP Auto-PSR15 | `webtrees` | `php` | `io.opentelemetry.contrib.php.psr15` |
| PHP Auto-PDO | `webtrees` | `php` | `io.opentelemetry.contrib.php.pdo` |
| PHP OTel-Spans-Modul | `webtrees` | `php` | `otel-spans` |
| Boomerang Browser | `webtrees-browser` | `webjs` | `@opentelemetry/instrumentation-document-load` |
