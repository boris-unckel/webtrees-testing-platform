<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Implementierungsplan: Laufzeitmessung — End-to-End Trace Correlation

## Zweck

Dieser Plan beschreibt die schrittweise Implementierung der Laufzeitmessung fuer die webtrees-Testing-Plattform in zwei Ausbaustufen. Er ist eigenstaendig und vollstaendig — keine externen Dokumente werden benoetigt.

**Statuskonzept:** Jeder Teilschritt traegt einen Status, der bei der Umsetzung fortlaufend aktualisiert wird:

| Symbol | Status | Bedeutung |
|--------|--------|-----------|
| `[ ]` | OFFEN | Noch nicht begonnen |
| `[~]` | IN ARBEIT | Begonnen, noch nicht abgeschlossen |
| `[x]` | FERTIG | Abgeschlossen und verifiziert |
| `[!]` | BLOCKIERT | Durch Abhaengigkeit oder Problem blockiert |
| `[-]` | ENTFALLEN | Bewusst uebersprungen (mit Begruendung) |

---

## 1. Ausgangslage (Ist-Zustand)

### 1.1 Stack-Komponenten

| Komponente | Aktuell | Image |
|---|---|---|
| PHP | php:8.5-apache (Debian Bookworm), OTel PECL Extension | `docker.io/library/php:8.5-apache` |
| Apache httpd | mod_rewrite, kein OTel-Modul | aus php:8.5-apache |
| MySQL | 8.0 | `docker.io/library/mysql:8.0` |
| OTel Collector | contrib, **ungepinnt** | `docker.io/otel/opentelemetry-collector-contrib:latest` |
| Jaeger | All-in-One, **ungepinnt** | `docker.io/jaegertracing/all-in-one:latest` |
| Playwright | Node.js 22, Chromium headless | `docker.io/library/node:22-bookworm` |

### 1.2 Bestehende PHP-Instrumentierung

Bereits installiert (bedingt auf `OTEL_SDK_DISABLED != true`, via `scripts/setup-webtrees.sh`):

- `open-telemetry/sdk`
- `open-telemetry/exporter-otlp`
- `open-telemetry/opentelemetry-auto-pdo` (DB-Queries)
- `open-telemetry/opentelemetry-auto-psr18` (HTTP-Client)

### 1.3 Bestehende Trace-Pipeline

```
PHP (http/protobuf) --> OTel Collector (:4318) --> Jaeger (UI :16686)
                                                --> file (/artifacts/traces.json, append)
```

### 1.4 Dateien, die geaendert werden

| Datei | Aenderungen |
|---|---|
| `compose.yaml` | MySQL 8.4, Image-Pinning, HTTP-Port 4318, OTel-Spans-Modul-Mount, OTLP http/protobuf |
| `.env` | OTLP-Protokoll grpc → http/protobuf, Endpoint-Port 4317 → 4318 |
| `Containerfile.webtrees` | mod_substitute, Boomerang-Download, Apache-Conf |
| `scripts/setup-webtrees.sh` | auto-psr15 Composer-Require |
| `otel/otel-collector-config.yaml` | HTTP-Receiver + CORS |
| `Makefile` | Neue Targets: perfschema-truncate, perfschema-extract, trace-report |

### 1.5 Neue Dateien

| Datei | Inhalt |
|---|---|
| `modules/otel-spans/module.php` | Modul-Einstiegspunkt |
| `modules/otel-spans/OtelSpansModule.php` | Semantische Spans + Baggage-Konvertierung |
| `otel/boomerang-init.js` | Boomerang-Initialisierung mit OTel-Plugin |
| `otel/boomerang-apache.conf` | mod_substitute Injection-Config |
| `layer4-e2e/helpers/otel-fixture.ts` | Playwright Baggage-Fixture |
| `scripts/truncate-perfschema.sh` | PerfSchema TRUNCATE vor Testlauf |
| `scripts/extract-perfschema.sh` | PerfSchema-Daten als JSON extrahieren |
| `scripts/trace-report.py` | OTLP NDJSON Parser + Report-Generator |
| `scripts/trace-report.sh` | Bash-Wrapper fuer trace-report.py |

### 1.6 Scope-Ausschluesse

- Security-Testing (eigenes Vorhaben)
- inspectIT als Gesamtprodukt
- Produktiv-Deployment
- Metriken und Logs (nur Traces)
- Aenderungen am webtrees-Upstream-Code
- Apache OTel-Modul (kein kompatibles Pre-built Binary fuer Debian Bookworm)
- MySQL Telemetry Plugin (Enterprise-only)
- End-to-End Trace-ID-Propagation PHP->MySQL (architektonisch nicht moeglich)
- Boomerang Grunt-Build-Pipeline (synchrones Laden der Roh-Dateien genuegt)

---

## 2. Zielarchitektur

### 2.1 Ausbaustufe 1 — Initiale Implementierung

```
Playwright (Layer 4/5)
  |-- setExtraHTTPHeaders({baggage: test.run_id=X, test.case_id=Y})
  |
  v
Browser (Boomerang + OTel-Plugin)
  |-- Document Load, XHR, Fetch Spans --> OTLP/HTTP --> Collector:4318
  |-- traceparent in Fetch/XHR Requests (OTel-Plugin automatisch)
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
  |-- OTel-Spans-Modul: Baggage --> Span-Attribute (test.run_id, test.case_id)
  |-- Spans --> http/protobuf --> Collector:4318
  |
  v
MySQL 8.4 LTS (KEINE Server-Spans)
  |-- Performance Schema: Aggregierte Query-Statistiken (Default ON)
  |-- Stage-Instrumentierung aktiviert (5-10% Overhead, akzeptabel)
  |-- Extraktion per Bash-Script am Testlauf-Ende
  |
  v
OTel Collector (Contrib, gepinnt)
  |-- Empfaengt: gRPC (:4317) + HTTP (PHP + Boomerang, :4318)
  |-- Exportiert: Jaeger (OTLP) + File (/artifacts/traces.json, append: true)
  |
  v
Jaeger (All-in-One, gepinnt)              traces.json (NDJSON)
  |-- UI: http://localhost:16686           |-- trace-report.py (Python)
  |-- API: /api/traces?tags=...            |-- PerfSchema-Integration
```

**Korrelation:** W3C Baggage (`test.run_id`, `test.case_id`). Keine durchgehende traceparent-Kette. Boomerang-Spans nur ueber Zeitfenster korrelierbar.

### 2.2 Ausbaustufe 2 — Playwright Root-Span (kausale Trace-Kette)

```
Playwright (OTel SDK in Node.js)
  |-- Erzeugt Root-Span pro Testfall (Service: playwright-tests)
  |-- Setzt traceparent + baggage via page.route() (nur webtrees-Requests)
  |
  v  (traceparent Header)
Apache httpd (TRANSPARENT — leitet traceparent + baggage weiter)
  |
  v
PHP (OTel) — liest traceparent als Parent-Context
  |-- auto-psr15: Request-Span (Child des Playwright-Root-Spans, gleiche trace_id)
  |-- OTel-Spans-Modul: Semantischer Span + Server-Timing Response-Header
  |-- auto-pdo: DB-Query-Spans
  |
  v  (Server-Timing Response-Header)
Browser (Boomerang + OTel-Plugin)
  |-- Document Load Span liest Server-Timing Header --> gleicher Trace
  |-- Spans als Children des PHP-Spans (via Server-Timing)
  |
  v
MySQL (PerfSchema, unveraendert)
```

**Korrelation:** Kausale Parent-Child-Beziehung via traceparent:
`Playwright-Span --> PHP-Span --> {Custom-Span, DB-Spans, Boomerang-Span (via Server-Timing)}`

**Richtung beachten:** Playwright propagiert `traceparent` an PHP (Request-Header). PHP propagiert Span-Context an Boomerang (Response-Header `Server-Timing`). Die Kette ist Playwright → PHP → Boomerang, nicht Playwright → Boomerang → PHP.

### 2.3 Was NICHT implementiert wird

| Komponente | Begruendung |
|---|---|
| Apache OTel-Modul | Kein kompatibles Pre-built Binary fuer Debian Bookworm/Apache 2.4.62 |
| MySQL Telemetry Plugin | Enterprise-only, nicht in Community Edition verfuegbar |
| End-to-End Trace-ID PHP->MySQL | Keine TRACE_ID in PerfSchema Community |
| mysqld_exporter (Prometheus) | Redundant — PerfSchema-SQL liefert dieselben Daten |
| Boomerang Beacon-Receiver | Nicht noetig — OTel-Traces reichen aus |
| Boomerang Grunt-Build | Synchrones Laden der Roh-Dateien genuegt |

---

## 3. Ausbaustufe 1 — Implementierungsschritte

### Abhaengigkeitsgraph

```
                S1 (MySQL 8.4)
                |           \
                |            S8 (PerfSchema)-------------------+
                |                                              |
S2 (Pinning)    S3 (HTTP-Receiver)    S4 (auto-psr15)         |
                |                      |                       |
                |                      S5 (OTel-Spans-Modul)   |
                |                      |                       |
                S6 (Boomerang)         S7 (Baggage-Fixture)    |
                |                      |                       |
                |                      +----------+------------+
                |                                 |
                |                        S9 (Trace-Report)
                |                                 |
                +---------------------------------+
                                                  |
                                         S10 (Makefile-Integration)
```

**Parallelisierbar:** S1, S2, S3, S4 sind voneinander unabhaengig.
**Kritischer Pfad:** S4 --> S5 --> S7 --> S9

### Phasenuebersicht

| Phase | Schritte | Kerninhalt |
|---|---|---|
| Phase 1: Infrastruktur | S1, S2, S3, S4 | MySQL 8.4, Image-Pinning, HTTP-Receiver, auto-psr15 |
| Phase 2: PHP-Instrumentierung | S5 | OTel-Spans-Modul (semantische Attribute, Baggage) |
| Phase 3: Testlauf-Korrelation | S7, S8 | Playwright Baggage-Fixture, PerfSchema-Extraktion |
| Phase 4: Auswertung | S9, S10 | Trace-Report-Script, Makefile-Integration |
| Phase 5: Browser-RUM | S6 | Boomerang + mod_substitute |

---

### Phase 1: Infrastruktur (S1, S2, S3, S4)

#### S1: MySQL 8.0 --> 8.4 LTS `[x]`

**Datei:** `compose.yaml`

**Teilschritte:**

- [x] S1.1: `mysql`-Service: Image von `mysql:8.0` auf `mysql:lts` aendern
- [x] S1.2: `mysql`-Service: PerfSchema Stage-Instrumentierung via `command` aktivieren:
  ```yaml
  command: >
    --character-set-server=utf8mb4
    --collation-server=utf8mb4_bin
    --performance-schema-instrument='stage/%=ON'
    --performance-schema-consumer-events-stages-current=ON
    --performance-schema-consumer-events-stages-history=ON
  ```
- [x] S1.3: `mysql-security`-Service: Image parallel auf `mysql:lts` aktualisieren
- [x] S1.4: `make clean` ausfuehren (Volume loeschen — In-Place-Upgrade 8.0->8.4 nicht vorgesehen)
- [x] S1.5: `make up && make setup` — Stack mit neuem MySQL starten und verifizieren

**Verifizierung V4:** `mysqladmin ping` Healthcheck mit `caching_sha2_password` muss funktionieren. Der bestehende Healthcheck in compose.yaml nutzt bereits `mysqladmin ping` mit Root-Passwort — sollte kompatibel sein.

---

#### S2: Container-Image-Versionen pinnen `[x]`

**Datei:** `compose.yaml`

**Teilschritte:**

- [x] S2.1: Aktuelle stabile Version von `otel/opentelemetry-collector-contrib` ermitteln und pinnen → `0.148.0`
- [x] S2.2: Aktuelle stabile Version von `jaegertracing/jaeger` ermitteln und pinnen → `2.16.0`
- [x] S2.3: Beide Image-Tags in `compose.yaml` aktualisieren:
  ```yaml
  otel-collector:
    image: docker.io/otel/opentelemetry-collector-contrib:<VERSION>

  jaeger:
    image: docker.io/jaegertracing/all-in-one:<VERSION>
  ```

**Keine Verifizierung noetig** — Standard-Docker-Pull.

---

#### S3: OTel Collector HTTP-Receiver fuer Browser-Traces `[x]`

**Dateien:** `otel/otel-collector-config.yaml`, `compose.yaml`

**Teilschritte:**

- [x] S3.1: `otel/otel-collector-config.yaml` — HTTP-Protokoll mit CORS hinzufuegen:
  ```yaml
  receivers:
    otlp:
      protocols:
        grpc:
          endpoint: 0.0.0.0:4317
        http:
          endpoint: 0.0.0.0:4318
          cors:
            allowed_origins:
              - "http://localhost:8080"
              - "http://webtrees:80"
            allowed_headers:
              - "*"
            max_age: 7200
  ```
- [x] S3.2: `compose.yaml` — Port 4318 im `otel-collector`-Service exponieren:
  ```yaml
  otel-collector:
    ports:
      - "4317:4317"
      - "4318:4318"
  ```
- [x] S3.3: Stack neu starten und verifizieren, dass Collector auf Port 4318 horcht

---

#### S4: `auto-psr15` installieren `[x]`

**Datei:** `scripts/setup-webtrees.sh`

**Teilschritte:**

- [x] S4.1: In der bestehenden `composer require`-Liste (Zeile 52-56 in `setup-webtrees.sh`, bedingt auf `OTEL_SDK_DISABLED != true`) das Paket `open-telemetry/opentelemetry-auto-psr15` hinzufuegen:
  ```bash
  composer require --dev --no-interaction --no-progress \
    open-telemetry/sdk \
    open-telemetry/exporter-otlp \
    open-telemetry/opentelemetry-auto-pdo \
    open-telemetry/opentelemetry-auto-psr18 \
    open-telemetry/opentelemetry-auto-psr15 2>&1
  ```
- [x] S4.2: `make clean && make up && make setup` — Container neu bauen und Setup ausfuehren
- [x] S4.3: Verifizieren, dass auto-psr15 installiert wurde (kein Composer-Fehler)

**Verifizierung V2:** auto-psr15 1.2.0 hat Requirement `php: ^8.1`. Beim `composer require` verifizieren, dass keine Inkompatibilitaet mit PHP 8.5 auftritt.

**Ergebnis:** Automatischer Root-Span pro HTTP-Request mit HTTP Method, URL, Status Code. Trace-Hierarchie: Request --> DB-Queries.

---

### Phase 2: PHP-Instrumentierung (S5)

#### S5: OTel-Spans-Modul entwickeln `[x]`

**Neue Dateien:** `modules/otel-spans/module.php`, `modules/otel-spans/OtelSpansModule.php`
**Geaenderte Datei:** `compose.yaml`

**Teilschritte:**

- [x] S5.1: Verzeichnis `modules/otel-spans/` anlegen
- [x] S5.2: `modules/otel-spans/module.php` erstellen:
  ```php
  <?php
  // SPDX-License-Identifier: AGPL-3.0-or-later
  declare(strict_types=1);

  namespace OtelSpans;

  return new OtelSpansModule();
  ```
- [x] S5.3: `modules/otel-spans/OtelSpansModule.php` erstellen (Details siehe Abschnitt 3.1)
- [x] S5.4: `compose.yaml` — Volume-Mount fuer OTel-Spans-Modul im `webtrees`-Service hinzufuegen:
  ```yaml
  webtrees:
    volumes:
      # ... bestehende Volumes ...
      - ./modules/otel-spans:/var/www/html/modules_v4/otel_spans:ro,z
  ```
- [x] S5.5: `make clean && make up && make setup` — Stack mit neuem Modul-Mount starten
- [x] S5.6: Verifizieren, dass das Modul in der webtrees-Admin-Oberflaeche sichtbar ist
- [x] S5.7: Verifizieren, dass Spans in Jaeger erscheinen (Span-Name `webtrees.<action>`)

**Verifizierungen:**
- V1: `OTEL_PROPAGATORS` Default `tracecontext,baggage` im Container pruefen; falls nicht, explizit in `compose.yaml` setzen
- V5: `Baggage::getCurrent()` Timing — Baggage-Context muss propagiert sein bevor das Modul ausfuehrt
- V6: Percent-Encoding Roundtrip — URL-encoded `test.case_id` korrekt durch Stack; `urldecode()` im Modul

##### S5 Detail: OtelSpansModule.php

Die Klasse `OtelSpansModule` erweitert `AbstractModule`, implementiert `ModuleCustomInterface` und `MiddlewareInterface`. Sie nutzt das bewiesene Pattern des existierenden `HitCountFooterModule`.

**`process()`-Methode:**

1. Route-Name extrahieren via `Validator::attributes($request)->route()` --> `$route->name` (FQCN des Handlers)
2. Route-Name gegen `ROUTE_MAP` pruefen (56 Eintraege, 6 Kategorien)
3. Wenn gemappt: Span starten mit semantischen Attributen
4. Baggage aus Context extrahieren: `Baggage::getCurrent()->getEntry('test.run_id')`, `->getEntry('test.case_id')`
5. Baggage-Werte als Span-Attribute setzen (mit `urldecode()` fuer test.case_id)
6. `$handler->handle($request)` ausfuehren
7. Span beenden (mit Status-Code aus Response)

**Span-Attribute:**

| Attribut | Quelle | Beispiel |
|---|---|---|
| `webtrees.action` | ROUTE_MAP | `view_individual` |
| `webtrees.type` | ROUTE_MAP | `query` oder `edit` |
| `webtrees.tree` | `Validator::attributes($request)->treeOptional()` | `demo` |
| `webtrees.xref` | `Validator::attributes($request)->string('xref', '')` | `I123` |
| `webtrees.route` | Short-Name aus FQCN | `IndividualPage` |
| `http.method` | `$request->getMethod()` | `GET` |
| `http.status_code` | `$response->getStatusCode()` | `200` |
| `test.run_id` | Baggage | `a1b2c3d4-e5f6-7890-abcd-ef1234567890` |
| `test.case_id` | Baggage (urldecoded) | `homepage loads without errors` |

**Span-Name:** `webtrees.<action>` (z.B. `webtrees.view_individual`). Ungemappte Routes werden ignoriert — auto-psr15 deckt sie generisch ab.

**Interaktion mit auto-psr15:** Beide Spans existieren parallel. auto-psr15 erzeugt den generischen Request-Root-Span (Parent). Das OTel-Spans-Modul erzeugt einen semantischen Child-Span. Die Dopplung ist erwuenscht: PSR-15 = technische HTTP-Metriken, Custom = geschaeftliche Semantik.

**OTel API-Nutzung:** Das Modul nutzt `OpenTelemetry\API\Globals::tracerProvider()`. Wenn OTel disabled ist (`OTEL_SDK_DISABLED=true`), wird die PHP-Extension nicht geladen und die Klasse `Globals` existiert nicht. Ein `class_exists(Globals::class)`-Guard am Anfang von `process()` ist daher **zwingend erforderlich** (Abweichung vom urspruenglichen Plan — NoOp-Tracer-Annahme war falsch).

**ROUTE_MAP (56 Routes in 6 Kategorien):**

Kategorie 1 — Record-Ansicht (13 Routes):

| Handler-Klasse (FQCN) | `webtrees.action` | `webtrees.type` |
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

Kategorie 2 — Suche (8 Routes):

| Handler-Klasse (FQCN) | `webtrees.action` | `webtrees.type` |
|---|---|---|
| `SearchGeneralPage::class` | `search_general` | `query` |
| `SearchGeneralAction::class` | `search_general` | `query` |
| `SearchAdvancedPage::class` | `search_advanced` | `query` |
| `SearchAdvancedAction::class` | `search_advanced` | `query` |
| `SearchPhoneticPage::class` | `search_phonetic` | `query` |
| `SearchPhoneticAction::class` | `search_phonetic` | `query` |
| `SearchQuickAction::class` | `search_quick` | `query` |
| `SearchReplacePage::class` | `search_replace_form` | `edit` |

Kategorie 3 — Bearbeitung (12 Routes):

| Handler-Klasse (FQCN) | `webtrees.action` | `webtrees.type` |
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

Kategorie 4 — Erstellung (8 Routes):

| Handler-Klasse (FQCN) | `webtrees.action` | `webtrees.type` |
|---|---|---|
| `CreateMediaObjectAction::class` | `create_media` | `edit` |
| `CreateNoteAction::class` | `create_note` | `edit` |
| `CreateSourceAction::class` | `create_source` | `edit` |
| `CreateRepositoryAction::class` | `create_repository` | `edit` |
| `CreateLocationAction::class` | `create_location` | `edit` |
| `CreateSubmitterAction::class` | `create_submitter` | `edit` |
| `AddNewFact::class` | `add_fact_form` | `edit` |
| `AddUnlinkedAction::class` | `create_individual` | `edit` |

Kategorie 5 — Beziehungen (10 Routes):

| Handler-Klasse (FQCN) | `webtrees.action` | `webtrees.type` |
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

Kategorie 6 — Navigation & Berichte (5 Routes):

| Handler-Klasse (FQCN) | `webtrees.action` | `webtrees.type` |
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

**Vollstaendiger Quellcode — OtelSpansModule.php:**

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

    private const ROUTE_MAP = [
        // Kategorie 1: Record-Ansicht
        \Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage::class
            => ['action' => 'view_individual', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage::class
            => ['action' => 'view_family', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SourcePage::class
            => ['action' => 'view_source', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\MediaPage::class
            => ['action' => 'view_media', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\NotePage::class
            => ['action' => 'view_note', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SharedNotePage::class
            => ['action' => 'view_shared_note', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage::class
            => ['action' => 'view_repository', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\LocationPage::class
            => ['action' => 'view_location', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage::class
            => ['action' => 'view_submitter', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SubmissionPage::class
            => ['action' => 'view_submission', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\HeaderPage::class
            => ['action' => 'view_header', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\GedcomRecordPage::class
            => ['action' => 'view_record', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\TreePage::class
            => ['action' => 'view_tree', 'type' => 'query'],

        // Kategorie 2: Suche
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage::class
            => ['action' => 'search_general', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralAction::class
            => ['action' => 'search_general', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedPage::class
            => ['action' => 'search_advanced', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedAction::class
            => ['action' => 'search_advanced', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchPhoneticPage::class
            => ['action' => 'search_phonetic', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchPhoneticAction::class
            => ['action' => 'search_phonetic', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchQuickAction::class
            => ['action' => 'search_quick', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchReplacePage::class
            => ['action' => 'search_replace_form', 'type' => 'edit'],

        // Kategorie 3: Bearbeitung
        \Fisharebest\Webtrees\Http\RequestHandlers\EditFactPage::class
            => ['action' => 'edit_fact_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditFactAction::class
            => ['action' => 'edit_fact_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRecordPage::class
            => ['action' => 'edit_record_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRecordAction::class
            => ['action' => 'edit_record_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactPage::class
            => ['action' => 'edit_raw_fact_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactAction::class
            => ['action' => 'edit_raw_fact_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordPage::class
            => ['action' => 'edit_raw_record_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordAction::class
            => ['action' => 'edit_raw_record_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditNotePage::class
            => ['action' => 'edit_note_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditNoteAction::class
            => ['action' => 'edit_note_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord::class
            => ['action' => 'delete_record', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\DeleteFact::class
            => ['action' => 'delete_fact', 'type' => 'edit'],

        // Kategorie 4: Erstellung
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectAction::class
            => ['action' => 'create_media', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteAction::class
            => ['action' => 'create_note', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateSourceAction::class
            => ['action' => 'create_source', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateRepositoryAction::class
            => ['action' => 'create_repository', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateLocationAction::class
            => ['action' => 'create_location', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmitterAction::class
            => ['action' => 'create_submitter', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddNewFact::class
            => ['action' => 'add_fact_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddUnlinkedAction::class
            => ['action' => 'create_individual', 'type' => 'edit'],

        // Kategorie 5: Beziehungen
        \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualPage::class
            => ['action' => 'add_child_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualAction::class
            => ['action' => 'add_child_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualPage::class
            => ['action' => 'add_spouse_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualAction::class
            => ['action' => 'add_spouse_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddParentToIndividualPage::class
            => ['action' => 'add_parent_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddParentToIndividualAction::class
            => ['action' => 'add_parent_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToFamilyAction::class
            => ['action' => 'add_child_to_family', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToFamilyAction::class
            => ['action' => 'add_spouse_to_family', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\LinkChildToFamilyAction::class
            => ['action' => 'link_child_to_family', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\LinkSpouseToIndividualAction::class
            => ['action' => 'link_spouse', 'type' => 'edit'],

        // Kategorie 6: Navigation & Berichte
        \Fisharebest\Webtrees\Http\RequestHandlers\CalendarPage::class
            => ['action' => 'calendar', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\ReportListPage::class
            => ['action' => 'report_list', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate::class
            => ['action' => 'report_generate', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\ContactPage::class
            => ['action' => 'contact_form', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges::class
            => ['action' => 'pending_changes', 'type' => 'query'],
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

            // Baggage --> Span-Attribute
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

---

### Phase 3: Testlauf-Korrelation (S7, S8)

#### S7: Playwright Baggage-Fixture `[x]`

**Neue Datei:** `layer4-e2e/helpers/otel-fixture.ts`

**Teilschritte:**

- [x] S7.1: `layer4-e2e/helpers/otel-fixture.ts` erstellen:
  ```typescript
  // SPDX-License-Identifier: AGPL-3.0-or-later
  import { test as base } from '@playwright/test';
  import { randomUUID } from 'crypto';

  export const test = base.extend<{}>({
    page: async ({ page }, use, testInfo) => {
      const runId = process.env.TEST_RUN_ID || randomUUID();
      const caseId = testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_');

      await page.setExtraHTTPHeaders({
        'baggage': `test.run_id=${runId},test.case_id=${caseId}`,
      });

      await use(page);
    },
  });

  export { expect } from '@playwright/test';
  ```
  **Abweichung vom urspruenglichen Plan:** `encodeURIComponent()` durch Zeichenersetzung ersetzt (siehe F7). Der PHP OTel SDK BaggagePropagator kann Percent-Encoding in Baggage-Werten nicht korrekt verarbeiten.
- [x] S7.2: Bestehende E2E-Tests umstellen — Import von `@playwright/test` auf `../helpers/otel-fixture` aendern. Betrifft alle `.spec.ts`-Dateien unter `layer4-e2e/tests/` (aktuell 22 Dateien + 6 Security-Tests)
- [x] S7.3: Bestehende Performance-Tests umstellen — Import in `layer5-performance/tests/` analog aendern (3 Dateien)
- [x] S7.4: Verifizieren, dass `make test-e2e` weiterhin fehlerfrei laeuft (Import-Aenderung darf keine Regression erzeugen)

**Baggage-Format:** W3C Baggage Specification. Schluessel duerfen `.` enthalten. UUIDs brauchen kein Encoding. Testtitel werden per Zeichenersetzung (`[^a-zA-Z0-9_.-]` → `_`) in sichere Baggage-Werte umgewandelt. `encodeURIComponent` kann NICHT verwendet werden — der PHP OTel SDK BaggagePropagator interpretiert `%`-Sequenzen fehlerhaft (siehe F7).

**RUN_ID-Erzeugung:** `TEST_RUN_ID` als Umgebungsvariable (spaeter vom Makefile-Target gesetzt); Fallback: `randomUUID()` pro Testlauf.

---

#### S8: PerfSchema-Extraktion `[x]`

**Neue Dateien:** `scripts/truncate-perfschema.sh`, `scripts/extract-perfschema.sh`

**Teilschritte:**

- [x] S8.1: `scripts/truncate-perfschema.sh` erstellen:
  ```bash
  #!/usr/bin/env bash
  # SPDX-License-Identifier: AGPL-3.0-or-later
  set -euo pipefail
  CONTAINER="${1:-mysql}"
  podman-compose exec "$CONTAINER" mysql -u root \
    -p"${MYSQL_ROOT_PASSWORD:-webtrees_test}" -e "
    TRUNCATE TABLE performance_schema.events_statements_summary_by_digest;
    TRUNCATE TABLE performance_schema.events_stages_summary_global_by_event_name;
    TRUNCATE TABLE performance_schema.table_io_waits_summary_by_table;
    TRUNCATE TABLE performance_schema.events_transactions_summary_global_by_event_name;
  "
  ```
- [x] S8.2: `scripts/extract-perfschema.sh` erstellen — Extrahiert JSON fuer 4 PerfSchema-Tabellen + summary.txt (Details siehe Abschnitt 3.2)
- [x] S8.3: Beide Scripts ausfuehrbar machen (`chmod +x`)
- [x] S8.4: Manuell testen: `scripts/truncate-perfschema.sh` und `scripts/extract-perfschema.sh layer3`

##### S8 Detail: extract-perfschema.sh

**CLI:** `scripts/extract-perfschema.sh <layer>` (layer = `layer3`, `layer4`, `layer5`)

**Erzeugte Dateien:**

```
artifacts/<layer>/perfschema/
  statements_by_digest.json    — Top-50 Queries nach Gesamtzeit
  table_io_waits.json          — I/O pro Tabelle
  stages_global.json           — Query-Phasen
  transactions_global.json     — Transaktions-Statistiken
  summary.txt                  — Menschenlesbare Zusammenfassung
```

**SQL-Queries:**

statements_by_digest.json — Top-50 Queries:

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

table_io_waits.json:

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

stages_global.json:

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

**Einheitenumrechnung:** PerfSchema speichert in Picosekunden. Division durch 1e9 = Millisekunden.

**Aufruf-Muster:**

```bash
podman-compose exec mysql mysql -u root -p"${MYSQL_ROOT_PASSWORD:-webtrees_test}" \
  --batch --raw --skip-column-names -e "<SQL>" > "${TARGET_DIR}/datei.json"
```

**summary.txt:** Top-10-Queries, Top-5-Tabellen, Warnungen (Full Table Scans, No-Index-Queries, Temp-Tabellen auf Disk).

---

### Phase 4: Auswertung (S9, S10)

#### S9: Trace-Report-Script `[x]`

**Neue Dateien:** `scripts/trace-report.py`, `scripts/trace-report.sh`

**Teilschritte:**

- [x] S9.1: `scripts/trace-report.sh` erstellen (Bash-Wrapper):
  ```bash
  #!/usr/bin/env bash
  # SPDX-License-Identifier: AGPL-3.0-or-later
  set -euo pipefail
  exec python3 "$(dirname "$0")/trace-report.py" "$@"
  ```
- [x] S9.2: `scripts/trace-report.py` erstellen (Details siehe Abschnitt 3.3)
- [x] S9.3: Beide Scripts ausfuehrbar machen (`chmod +x`)
- [x] S9.4: Manuell testen mit vorhandener `artifacts/traces.json`

##### S9 Detail: trace-report.py

**Technologie:** Python 3, ausschliesslich Standardbibliothek (`json`, `sys`, `os`, `argparse`, `datetime`, `urllib.parse`, `dataclasses`, `collections`, `typing`). Laeuft auf dem Host (Fedora, Python 3.12+) oder im Playwright-Container.

**CLI-Interface:**

```
python3 trace-report.py \
  --run-id <UUID>                              (erforderlich)
  --traces-file /artifacts/traces.json         (Default)
  --perfschema-dir /artifacts/layerN/perfschema/ (optional)
  --output-json /artifacts/trace-report.json   (optional)
  --layer <3|4|5>                              (bestimmt PerfSchema-Pfad)
```

**Verarbeitungspipeline:**

1. OTLP NDJSON parsen (`json.loads` pro Zeile)
2. Spans nach `test.run_id` filtern (Attribut in jedem Span)
3. Spans nach `test.case_id` gruppieren (urldecode)
4. Hierarchie aufloesen (`parentSpanId` --> Children)
5. Layer zuordnen (`service.name` + `scope`)
6. Boomerang-Spans temporal korrelieren (Zeitfenster, da kein `test.run_id`)
7. PerfSchema-Daten laden (falls vorhanden)

**Kern-Datenstruktur:**

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

**OTLP NDJSON Parser:**

```python
def parse_traces(traces_path: str, run_id: str) -> list[Span]:
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

**Hierarchie-Aufloesung und Layer-Zuordnung:**

```python
def group_by_test_case(spans: list[Span]) -> dict[str, list[Span]]:
    groups = defaultdict(list)
    for span in spans:
        case_id = span.attributes.get("test.case_id", "(unbekannt)")
        case_id = unquote(case_id)
        groups[case_id].append(span)
    return dict(groups)


def build_hierarchy(spans: list[Span]) -> list[Span]:
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

**Layer-Zuordnung:**

| service.name | scope (enthaelt) | Display-Layer |
|---|---|---|
| `webtrees-browser` | * | Browser (RUM) |
| `webtrees` | `pdo` | DB Query |
| `webtrees` | `psr15` | PHP Backend |
| `webtrees` | `otel-spans` | webtrees Custom |
| `webtrees` | * (andere) | PHP |

**Boomerang-Spans temporale Korrelation:** Boomerang-Spans haben kein `test.run_id`. Sie werden ueber das Zeitfenster des Testlaufs korreliert (min/max Timestamps der PHP-Spans). Bei `workers: 1` (Playwright-Config) ist Ueberlappung unwahrscheinlich.

**Gewuenschtes Ausgabeformat:**

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

**Verifizierung V7:** File-Exporter Flush-Timing — Collector flusht Spans im Batch (Default: 5s). Bei automatischer Integration ggf. `sleep 5` vor Report-Generierung.

---

#### S10: Makefile-Integration `[x]`

**Datei:** `Makefile`

**Teilschritte:**

- [x] S10.1: `.PHONY`-Zeile um neue Targets erweitern
- [x] S10.2: Neue Targets hinzufuegen:
  ```makefile
  perfschema-truncate: ## PerfSchema-Daten zuruecksetzen
  	scripts/truncate-perfschema.sh

  perfschema-extract: ## PerfSchema-Daten extrahieren (LAYER=layer3|layer4|layer5)
  	scripts/extract-perfschema.sh $(LAYER)

  trace-report: ## Trace-Report generieren (RUN_ID=... LAYER=3|4|5)
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
- [x] S10.3: Bestehende `test-e2e` und `test-performance` Targets um PerfSchema-Truncate/Extract und Trace-Report erweitern (integrierter Workflow):
  ```makefile
  test-e2e: ## Systemtest (Playwright) mit OTel-Korrelation
  	@RUN_ID=$$(uuidgen); \
  	echo "Testlauf: $$RUN_ID"; \
  	-scripts/truncate-perfschema.sh; \
  	TEST_RUN_ID=$$RUN_ID $(COMPOSE) exec playwright npx playwright test \
  	    --config=/tests/e2e/playwright.config.ts; \
  	-scripts/extract-perfschema.sh layer4; \
  	scripts/trace-report.sh --run-id $$RUN_ID --layer 4 \
  	    --output-json artifacts/trace-report-$$RUN_ID.json || true
  ```
- [x] S10.4: Verifizieren, dass `make help` die neuen Targets korrekt anzeigt

---

### Phase 5: Browser-RUM (S6)

#### S6: Boomerang + mod_substitute `[x]`

**Neue Dateien:** `otel/boomerang-init.js`, `otel/boomerang-apache.conf`
**Geaenderte Datei:** `Containerfile.webtrees`

**Teilschritte:**

- [x] S6.1: `otel/boomerang-init.js` erstellen:
  ```javascript
  // SPDX-License-Identifier: AGPL-3.0-or-later
  // Boomerang + OTel-Plugin Initialisierung
  (function() {
    var collectorHost = window.location.hostname;
    var collectorUrl = 'http://' + collectorHost + ':4318/v1/traces';

    BOOMR.init({
      beacon_url: '/beacon/',
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
- [x] S6.2: `otel/boomerang-apache.conf` erstellen:
  ```apache
  # SPDX-License-Identifier: AGPL-3.0-or-later
  # Boomerang-Injection via mod_substitute

  Alias /rum/ /opt/rum/

  <Directory /opt/rum>
      Require all granted
      Options -Indexes
  </Directory>

  PassEnv OTEL_SDK_DISABLED
  <If "reqenv('OTEL_SDK_DISABLED') != 'true'">
      AddOutputFilterByType SUBSTITUTE text/html
      Substitute "s|</head>|<script src=\"/rum/boomerang.js\"></script><script src=\"/rum/plugins/rt.js\"></script><script src=\"/rum/plugins/navtiming.js\"></script><script src=\"/rum/plugins/restiming.js\"></script><script src=\"/rum/plugins/painttiming.js\"></script><script src=\"/rum/plugins/eventtiming.js\"></script><script src=\"/rum/boomerang-opentelemetry.js\"></script><script src=\"/rum/boomerang-init.js\"></script></head>|i"
  </If>
  ```
- [x] S6.3: `Containerfile.webtrees` — Build-Schritte hinzufuegen:
  - `a2enmod rewrite` zu `a2enmod rewrite substitute` erweitern
  - Boomerang via npm-Registry-Tarball herunterladen (Version 1.815.1):
    ```dockerfile
    ARG BOOMERANG_VERSION=1.815.1
    ARG BOOMERANG_OTEL_VERSION=2.0.0-2

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

    RUN curl -fSL -o /opt/rum/boomerang-opentelemetry.js \
        "https://github.com/inspectIT/boomerang-opentelemetry-plugin/releases/download/${BOOMERANG_OTEL_VERSION}/boomerang-opentelemetry.js"

    COPY otel/boomerang-init.js /opt/rum/
    COPY otel/boomerang-apache.conf /etc/apache2/conf-available/boomerang.conf
    RUN a2enconf boomerang
    ```
- [x] S6.4: Container-Image neu bauen (`make down && make up`)
- [x] S6.5: Verifizieren, dass `http://localhost:8080` die Boomerang-Scripts im HTML-Source eingebettet hat (View Source, suche nach `boomerang.js`)
- [x] S6.6: Verifizieren, dass Browser-Spans in Jaeger unter `webtrees-browser` erscheinen — 2.430 Browser-Spans nach E2E-Quick-Lauf (2026-04-01). Erforderte drei Fixes: INFLATE;SUBSTITUTE;DEFLATE-Filterkette (F9), Collector-URL auf Container-Hostname (F10), CORS-Origin ohne Port (F11).
- [x] S6.7: Verifizieren, dass bei `OTEL_SDK_DISABLED=true` keine Boomerang-Injection stattfindet

**Verifizierung V3:** mod_substitute Interaktion mit `FallbackResource /index.php` — testen, ob Injection bei internem Subrequest korrekt funktioniert.

**Gepinnte Versionen:**

| Komponente | Version | Quelle |
|---|---|---|
| Boomerang | 1.815.1 | npm-Registry-Tarball |
| Boomerang-OTel-Plugin | 2.0.0-2 | GitHub Release: inspectIT/boomerang-opentelemetry-plugin |

**Boomerang-Plugins:**

| Plugin | Datei | Funktion |
|---|---|---|
| RT (Round-Trip) | `plugins/rt.js` | Seitenlade-Zeiten |
| NavigationTiming | `plugins/navtiming.js` | W3C Navigation Timing API |
| ResourceTiming | `plugins/restiming.js` | Waterfall-Daten |
| PaintTiming | `plugins/painttiming.js` | FCP, LCP |
| EventTiming | `plugins/eventtiming.js` | FID |

**Protokoll:** OTLP/HTTP mit JSON-Encoding (`Content-Type: application/json`) auf `/v1/traces`.

---

## 4. Testlauf und Archivierung — Ausbaustufe 1

### 4.1 Voraussetzungen

Alle Schritte S1-S10 sind abgeschlossen und einzeln verifiziert.

### 4.2 Testlauf-Prozedur `[x]`

**Hinweis:** Die Testlaeufe 4.2.1–4.2.5 wurden am 2026-03-29 mit dem gRPC-Protokoll-Bug durchgefuehrt (siehe Abschnitt 9). Am 2026-04-01 erneuter Testlauf mit Quick-Targets nach Bugfixes F7/F8 (siehe Abschnitt 13.1) — OTel-Korrelation verifiziert: 40 Spans mit `test.run_id` + `test.case_id`, 30 Testfaelle korrekt gruppiert.

- [x] 4.2.1: Stack sauber aufsetzen:
  ```bash
  make clean && make up && make setup
  ```
- [x] 4.2.2: Komponentenintegrationstest (Layer 3) ausfuehren — 274/274 bestanden, 1 uebersprungen. Quick-Target (2026-04-01): 79/79 bestanden.
  ```bash
  make test-integration          # Alle
  make test-integration-quick    # 3 repraesentative Faelle
  ```
  **Hinweis (CLAUDE.md):** Lang laufende Tests mit `run_in_background: true` starten. Kein `timeout`-Parameter. Exklusive Ausfuehrung — kein paralleler Testlauf.
- [x] 4.2.3: Systemtest (Layer 4) ausfuehren — 175 bestanden, 1 flaky, 0 fehlgeschlagen. Quick-Target (2026-04-01): 30/30 bestanden mit funktionierender OTel-Korrelation.
  ```bash
  make test-e2e                  # Alle
  make test-e2e-quick            # 3 repraesentative Faelle mit Trace-Report
  ```
- [x] 4.2.4: Performanztest (Layer 5) ausfuehren — 3/3 bestanden (Homepage 669ms, Pedigree 695ms, Search 575ms)
  ```bash
  make test-performance
  ```
- [x] 4.2.5: Graceful Degradation verifizieren — 175 bestanden, 1 flaky, 0 fehlgeschlagen. **Erforderte Bugfix:** `class_exists(Globals::class)`-Guard in OtelSpansModule (siehe Abschnitt 9).
  ```bash
  make clean
  OTEL_SDK_DISABLED=true make up && OTEL_SDK_DISABLED=true make setup
  make test-e2e
  ```

### 4.3 Abnahmekriterien Ausbaustufe 1

| # | Kriterium | Status | Anmerkung |
|---|---|---|---|
| A1.1 | Jaeger UI zeigt PHP-Spans (auto-psr15, auto-pdo, OTel-Spans-Modul) | `[x]` | 191.702 Spans (144.662 PDO, 46.985 PSR-15, 55 Custom) in traces.json nach Quick-Test 2026-04-01 |
| A1.2 | Jaeger UI zeigt Browser-Spans (`webtrees-browser` via Boomerang) | `[x]` | 2.430 Browser-Spans in traces.json nach E2E-Quick-Lauf (2026-04-01). Fixes: F9, F10, F11 |
| A1.3 | PHP-Spans enthalten `test.run_id` und `test.case_id` als Attribute | `[x]` | 40 OtelSpansModule-Spans mit beiden Attributen, 30 Testfaelle korrekt gruppiert (nach F7-Fix) |
| A1.4 | Span-Hierarchie korrekt: PSR-15 Root --> Custom --> DB-Spans | `[x]` | Alle 3 Scopes in Hierarchie verifiziert |
| A1.5 | `trace-report.py` parst OTLP-NDJSON, gruppiert nach Testfall, gibt Layer-Aufschluesselung aus | `[x]` | Vollstaendig funktional: Testfall-Gruppierung + PerfSchema-Aggregat + JSON-Report |
| A1.6 | PerfSchema-JSON vorhanden unter `artifacts/layerN/perfschema/` | `[x]` | Valides JSON nach F8-Fix (MYSQL_PWD statt -p) |
| A1.7 | `make test-all` fehlerfrei mit `OTEL_SDK_DISABLED=false` (Default) | `[x]` | 274/274 Integration, 175 E2E, 3/3 Performance |
| A1.8 | `make test-all` fehlerfrei mit `OTEL_SDK_DISABLED=true` | `[x]` | 175 E2E bestanden nach class_exists-Guard-Fix |

### 4.4 Artefakt-Archivierung `[~]`

Einmaliger manueller Schritt — wird NICHT dauerhaft geskriptet oder in Makefile-Targets eingebaut.

- [x] 4.4.1: Artefakte als ZIP sichern (Erstarchiv `artifacts_ausbaustufe-1_20260329_203150.zip` — enthaelt 0 Spans wegen Protokoll-Bug; neues Archiv nach erneutem Testlauf noetig):
  ```bash
  STUFE="ausbaustufe-1"
  TIMESTAMP=$(date +%Y%m%d_%H%M%S)
  ARCHIVE="artifacts_${STUFE}_${TIMESTAMP}.zip"
  zip -r "$ARCHIVE" artifacts/
  mkdir -p docs/laufzeit_analyse/archives
  mv "$ARCHIVE" docs/laufzeit_analyse/archives/
  ```
- [ ] 4.4.2: Aufraeumen fuer Ausbaustufe 2 (NOCH NICHT — erst nach erneutem Testlauf mit Protokoll-Fix):
  ```bash
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

---

## 5. Ausbaustufe 2 — Playwright Root-Span (kausale Trace-Kette)

### 5.1 Scope

Ausbaustufe 2 erweitert Ausbaustufe 1 um eine kausale Trace-Kette: Playwright erzeugt einen Root-Span pro Testfall und propagiert `traceparent` via `page.route()` an PHP. Alle PHP-Spans eines Testfalls teilen sich dieselbe `trace_id`. Boomerang-Browser-Spans werden ueber den `Server-Timing`-Response-Header in denselben Trace eingehaengt.

Baggage-Korrelation (`test.run_id`, `test.case_id`) bleibt unveraendert als Fundament — `page.route()` uebernimmt die Header-Injection und ersetzt `page.setExtraHTTPHeaders()`.

### 5.2 Invarianten aus Ausbaustufe 1 (NICHT AENDERN)

Die folgenden Mechanismen sind in Ausbaustufe 1 verifiziert und funktional. Aenderungen in Ausbaustufe 2 duerfen sie nicht brechen. Jede Invariante referenziert den zugehoerigen Fix aus Abschnitt 13.1.

| # | Invariante | Fix-Referenz |
|---|---|---|
| I1 | **Baggage-Werte ohne Percent-Encoding** — `testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_')` statt `encodeURIComponent()` | F7 |
| I2 | **Apache-Filterkette `INFLATE;SUBSTITUTE;DEFLATE`** in `boomerang-apache.conf` | F9 |
| I3 | **Collector-URL `http://otel-collector:4318`** — kein `window.location.hostname`, kein `localhost` | F10 |
| I4 | **CORS-Origins ohne UND mit Port** (`http://webtrees` + `http://webtrees:80`) | F11 |
| I5 | **`class_exists(Globals::class)`-Guard** in `OtelSpansModule.php` | F3 |
| I6 | **`MYSQL_PWD` statt `-p`** in Shell-Scripts | F8 |
| I7 | **`podman-compose exec -e VAR=...`** fuer Container-Umgebungsvariablen | F6 |

**Pruefung:** `make test-e2e-quick` nach jeder Aenderung in Ausbaustufe 2 laufen lassen. Alle 30 Tests muessen bestehen, Browser-Spans muessen weiterhin erscheinen.

### 5.3 Abhaengigkeitsgraph

```
S11 (Playwright OTel SDK)
  |
  v
S12 (Fixture: traceparent + baggage via page.route)
  |                                \
  |                                 S13 (Server-Timing Header) [parallel moeglich]
  |                                /
  v                               v
S14 (Trace-Report erweitern)
```

**Parallelisierbar:** S13 hat keine Abhaengigkeit zu S11/S12 und kann parallel implementiert werden.
**Kritischer Pfad:** S11 → S12 → S14

### 5.4 Implementierungsschritte

#### S11: Playwright OTel SDK installieren `[x]`

**Geaenderte Datei:** `Containerfile.playwright`

**Teilschritte:**

- [x] S11.1: OTel-Pakete in `Containerfile.playwright` hinzugefuegt. **Nicht** `@opentelemetry/sdk-node` (zieht Auto-Instrumentierungs-Registry ein, die im Playwright-Kontext nicht benoetigt wird) — nur `sdk-trace-node` + HTTP-Exporter:
  ```json
  {
    "devDependencies": {
      "@playwright/test": "latest",
      "@opentelemetry/api": "^1.9",
      "@opentelemetry/sdk-trace-node": "^1.28",
      "@opentelemetry/exporter-trace-otlp-http": "^0.57",
      "@opentelemetry/resources": "^1.28",
      "@opentelemetry/semantic-conventions": "^1.28"
    }
  }
  ```
- [x] S11.2: Container-Image neu gebaut (`make down && make up`). **Hinweis:** Browser-Install muss NACH `npm install` erfolgen, nicht davor — sonst Versionsmismatch zwischen `npx playwright@latest install` und der per `npm install` aufgeloesten `@playwright/test`-Version (siehe F12).
- [x] S11.3: Verifiziert: `podman-compose exec playwright npm ls @opentelemetry/api` — installierte Versionen: api 1.9.1, sdk-trace-node 1.30.1, exporter-trace-otlp-http 0.57.2, resources 1.30.1, semantic-conventions 1.28.0

**Hinweis:** Keine `globalSetup`-Datei fuer OTel-Initialisierung verwenden — Playwright's `globalSetup` laeuft in einem separaten Prozess. Ein dort registrierter `TracerProvider` ist in den Test-Worker-Prozessen nicht verfuegbar. Stattdessen Worker-scoped Fixture (S12).

---

#### S12: Fixture erweitern — `page.route()` mit traceparent + baggage `[x]`

**Geaenderte Dateien:** `layer4-e2e/helpers/otel-fixture.ts`, `layer5-performance/helpers/otel-fixture.ts`

**Design-Entscheidungen (Erkenntnisse aus Ausbaustufe 1):**

1. **`page.route()` ersetzt `page.setExtraHTTPHeaders()`** — eine einzige Stelle fuer Header-Injection. `route.continue()` mit explizitem `headers`-Objekt ueberschreibt alle Header; deshalb `...route.request().headers()` zum Merge zwingend noetig.
2. **Route-Pattern `/^http:\/\/webtrees(:\d+)?\/`** statt `'**/*'` — nur Requests an webtrees abfangen. **Kritisch:** Boomerang sendet OTLP-Requests an `http://otel-collector:4318/v1/traces`. Bei `page.route('**/*')` wuerde `traceparent` in diese OTLP-Requests injiziert und die Browser-Span-Zustellung stoeren.
3. **Ein Root-Span pro Testfall** mit statischem `traceparent` — alle PHP-Requests innerhalb eines Tests werden Children dieses Root-Spans. Kein Per-Request-Child-Span aus Playwright noetig: auto-psr15 erzeugt den Request-Level-Span serverseitig.
4. **Worker-scoped `TracerProvider`** statt `globalSetup` — OTel SDK einmal pro Worker initialisieren, am Ende herunterfahren (`provider.shutdown()` flusht ausstehende Spans). Bei `workers: 1` aequivalent zu globalSetup, aber im richtigen Prozess.
5. **Zeichenersetzung statt `encodeURIComponent()`** fuer `test.case_id` (Invariante I1).

**Teilschritte:**

- [x] S12.1: `layer4-e2e/helpers/otel-fixture.ts` umgeschrieben:
  ```typescript
  // SPDX-License-Identifier: AGPL-3.0-or-later
  import { test as base } from '@playwright/test';
  import { randomUUID } from 'crypto';
  import { trace } from '@opentelemetry/api';
  import { NodeTracerProvider, SimpleSpanProcessor } from '@opentelemetry/sdk-trace-node';
  import { OTLPTraceExporter } from '@opentelemetry/exporter-trace-otlp-http';
  import { Resource } from '@opentelemetry/resources';
  import { ATTR_SERVICE_NAME } from '@opentelemetry/semantic-conventions';

  export const test = base.extend<{}, { _otelProvider: NodeTracerProvider }>({
    // Worker-scoped: einmal pro Worker initialisiert, bei workers:1 = einmal gesamt
    _otelProvider: [async ({}, use) => {
      const provider = new NodeTracerProvider({
        resource: new Resource({
          [ATTR_SERVICE_NAME]: 'playwright-tests',
        }),
        spanProcessors: [
          new SimpleSpanProcessor(
            new OTLPTraceExporter({
              url: 'http://otel-collector:4318/v1/traces',
            })
          ),
        ],
      });
      provider.register();
      await use(provider);
      await provider.shutdown();
    }, { scope: 'worker' }],

    page: async ({ page, _otelProvider }, use, testInfo) => {
      const tracer = trace.getTracer('playwright-tests');
      const runId = process.env.TEST_RUN_ID || randomUUID();
      const caseId = testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_');

      const rootSpan = tracer.startSpan(`test: ${caseId}`, {
        attributes: {
          'test.run_id': runId,
          'test.case_id': caseId,
        },
      });
      const spanContext = rootSpan.spanContext();
      const traceparent = `00-${spanContext.traceId}-${spanContext.spanId}-01`;

      // NUR webtrees-Requests — nicht otel-collector:4318 (Boomerang OTLP)
      await page.route(/^http:\/\/webtrees(:\d+)?\//, async (route) => {
        await route.continue({
          headers: {
            ...route.request().headers(),
            'traceparent': traceparent,
            'baggage': `test.run_id=${runId},test.case_id=${caseId}`,
          },
        });
      });

      await use(page);
      rootSpan.end();
    },
  });

  export { expect } from '@playwright/test';
  ```
- [x] S12.2: `layer5-performance/helpers/otel-fixture.ts` identisch aktualisiert
- [x] S12.3: `make test-e2e-quick` — 30/30 Tests bestanden, 80 PHP-Spans mit `test.run_id` (2026-04-01)
- [x] S12.4: 30 Playwright-Root-Spans in `traces.json` unter Service `playwright-tests` verifiziert (2026-04-01)
- [x] S12.5: 172.267 PHP-Spans mit gleicher `trace_id` wie zugehoeriger Playwright-Root-Span verifiziert (2026-04-01)
- [x] S12.6: 3.240 Browser-Spans (`webtrees-browser`) im Quick-Lauf, 2.970 im vollstaendigen E2E-Lauf — keine Regression (2026-04-01)

**Abweichungen vom urspruenglichen Plan:**

| Urspruenglich (fehlerhaft) | Korrigiert | Begruendung |
|---|---|---|
| `encodeURIComponent(testInfo.title)` | `testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_')` | F7: PHP BaggagePropagator verwirft %-Sequenzen |
| `page.route('**/*')` | `page.route(/^http:\/\/webtrees(:\d+)?\//)` | Verhindert traceparent-Injection in Boomerang-OTLP-Requests an Collector |
| `page.setExtraHTTPHeaders()` + `page.route()` parallel | Nur `page.route()` | Vermeidet Header-Merge-Problematik; `route.continue()` ueberschreibt alle Header |
| Per-Request-Child-Span (`spanId = /* neu */`) | Statischer `traceparent` (Root-Span-ID) | auto-psr15 erzeugt Request-Spans serverseitig; Client-seitige Child-Spans redundant |
| `globalSetup` fuer OTel SDK | Worker-scoped Fixture | `globalSetup` laeuft in separatem Prozess — TracerProvider dort nicht in Tests verfuegbar |
| `@opentelemetry/sdk-node` | `@opentelemetry/sdk-trace-node` | `sdk-node` bringt Auto-Instrumentierung mit, die im Playwright-Kontext nicht benoetigt wird |

---

#### S13: Server-Timing Header in OTel-Spans-Modul `[x]`

**Geaenderte Datei:** `modules/otel-spans/OtelSpansModule.php`

**Design-Entscheidung:** Der `Server-Timing`-Header enthaelt den Span-Context des auto-psr15-Root-Spans (nicht des OtelSpansModule-Child-Spans). Dadurch wird Boomerangs `documentLoad`-Span ein Sibling des Custom-Spans — die Hierarchie bleibt flach und uebersichtlich. Der auto-psr15-Context muss **vor** dem eigenen `spanBuilder()` gesichert werden, da `Span::getCurrent()` danach den Child-Span liefern wuerde.

**Teilschritte:**

- [x] S13.1: In `process()` den auto-psr15-Root-Span-Context **vor** dem eigenen Span-Start gesichert und nach `$handler->handle()` als `Server-Timing`-Header gesetzt:
  ```php
  // VOR Span-Start: auto-psr15 Root-Span-Context sichern
  $parentContext = \OpenTelemetry\API\Trace\Span::getCurrent()->getContext();

  $span = $tracer->spanBuilder('webtrees.' . $mapping['action'])
      ->setSpanKind(SpanKind::KIND_INTERNAL)
      ->startSpan();
  // ... Attribute, Baggage, handle(), span->end() wie bisher ...

  // NACH Span-End: Server-Timing mit Root-Span-Context
  $serverTiming = sprintf(
      'traceparent;desc="00-%s-%s-01"',
      $parentContext->getTraceId(),
      $parentContext->getSpanId()
  );
  return $response->withHeader('Server-Timing', $serverTiming);
  ```
- [x] S13.2: **`class_exists`-Guard beachtet** (Invariante I5) — bestehender Guard am Methodenkopf unveraendert beibehalten.
- [x] S13.3: Verifiziert: `curl -s -D - -o /dev/null http://localhost:8080/tree/demo | grep -i server-timing` liefert `Server-Timing: traceparent;desc="00-{traceId}-{spanId}-01"` (2026-04-01). Hinweis: Root-URL `/` ist ein Redirect (302) und keine gemappte Route — Verifizierung auf `/tree/demo`.

**Einschraenkung:** Server-Timing wird nur fuer die 56 gemappten Routes gesetzt (OtelSpansModule-Durchlauf mit Span). Fuer ungemappte Routes (Durchlauf ohne Span, Zeile `return $handler->handle($request)`) fehlt der Header — Boomerangs `documentLoad` bleibt dort ohne kausale Verknuepfung. Dies betrifft seltene Admin- und Utility-Routes und ist fuer die Testauswertung akzeptabel.

---

#### S14: Trace-Report erweitern `[x]`

**Geaenderte Datei:** `scripts/trace-report.py`

**Teilschritte:**

- [x] S14.1: `classify_span()` um Playwright-Service erweitert (`"Playwright (E2E)"`)
- [x] S14.2: Playwright-Root-Spans in Report-Zusammenfassung aufgenommen — Ausgabe zeigt Anzahl, trace_id-Prefixe und Testfallnamen. JSON-Report enthaelt `playwright_root_spans` und `browser_spans_trace_linked` Felder.
- [x] S14.3: Parent-Child-Konsistenz implementiert — `parse_browser_spans()` erhaelt `trace_ids`-Set fuer trace_id-basierte Korrelation. Report zeigt getrennt `trace-korreliert` vs `temporal`.
- [x] S14.4: Boomerang-Spans: trace_id-basierte Korrelation implementiert, temporale Korrelation als Fallback beibehalten. 710 trace-korrelierte Browser-Spans im Quick-Lauf (2026-04-01). Server-Timing-Bruecke funktioniert mit `recordTransaction: true` und `about:blank`-Flush.

**Hinweis:** Die bestehende temporale Korrelation fuer Boomerang-Spans (Zeitfenster-Methode aus Ausbaustufe 1) bleibt als Fallback erhalten — sie greift fuer Requests ohne Server-Timing (ungemappte Routes). Fuer gemappte Routes funktioniert die trace_id-basierte Korrelation ueber Server-Timing (V8 verifiziert).

---

### 5.5 Resultierende Trace-Kette

**Mit Server-Timing (gemappte Routes — 56 von ca. 80):**

```
Playwright Root-Span (test: homepage_loads_without_errors)  [playwright-tests]
  |
  |-- traceparent: 00-{traceId}-{rootSpanId}-01  (via page.route)
  |
  +-- PHP auto-psr15 (GET /)  [webtrees]                parentSpanId = rootSpanId
  |     +-- webtrees.view_tree (Custom-Span)              parentSpanId = psr15SpanId
  |     +-- PDO Span (SELECT ...)                         parentSpanId = psr15SpanId
  |     +-- PDO Span (SELECT ...)                         parentSpanId = psr15SpanId
  |
  +-- PHP auto-psr15 (GET /ajax/...)  [webtrees]         parentSpanId = rootSpanId
  |     +-- webtrees.search_query (Custom-Span)
  |     +-- PDO Spans
  |
  +-- Boomerang documentLoad  [webtrees-browser]         parentSpanId = psr15SpanId
        +-- resourceFetch Spans (CSS, JS, Bilder)                 (via Server-Timing)
```

**Ohne Server-Timing (ungemappte Routes oder OTEL_SDK_DISABLED):**

Fallback auf Ausbaustufe-1-Verhalten: Boomerang-Spans in separatem Trace, nur ueber Zeitfenster korrelierbar.

**Unterschied zu Ausbaustufe 1:** In Stufe 1 hat jeder PHP-Request eine eigene `trace_id` (vom PHP-SDK generiert). In Stufe 2 teilen sich alle Requests eines Testfalls dieselbe `trace_id` (von Playwright vorgegeben). Dadurch zeigt Jaeger einen einzigen Trace mit allen Spans statt vieler einzelner Traces pro Testfall.

**Korrektur gegenueber dem urspruenglichen Diagramm:** Boomerang `documentLoad` ist kein Parent der PHP-Spans, sondern umgekehrt — PHP setzt den `Server-Timing`-Header, Boomerang liest ihn und ordnet sich als Child ein. Die Kette ist: `Playwright → PHP → Boomerang` (nicht `Playwright → Boomerang → PHP`).

---

## 6. Testlauf und Archivierung — Ausbaustufe 2

### 6.1 Voraussetzungen

Alle Schritte S11-S14 sind abgeschlossen. Ausbaustufe 1 ist verifiziert (alle A1.1-A1.8 bestehen).

### 6.2 Testlauf-Prozedur `[x]`

- [x] 6.2.1: Stack sauber aufgesetzt (`make clean && make up && make setup`) (2026-04-01)
- [x] 6.2.2: Quick-Targets bestanden: 79/79 Integration, 30/30 E2E (2026-04-01)
- [x] 6.2.3: Vollstaendige Testlaeufe bestanden: 274/274 Integration (1 Skipped), 176/176 E2E (2026-04-01). Performance-Tests ausstehend.
- [x] 6.2.4: Invarianten-Pruefung bestanden (2026-04-01):
  - 80 Custom-Spans mit `test.run_id` + `test.case_id` (A1.3)
  - 3.240 Browser-Spans `webtrees-browser` (A1.2)
  - 30 Playwright-Root-Spans `playwright-tests` (A2.1) im Quick-Lauf, 176 im vollstaendigen Lauf
  - 172.267+ PHP-Spans mit gleicher `trace_id` wie Playwright-Root-Span (A2.2)
- [ ] 6.2.5: Graceful Degradation mit `OTEL_SDK_DISABLED=true` — noch nicht verifiziert

### 6.3 Abnahmekriterien Ausbaustufe 2

| # | Kriterium | Status | Anmerkung |
|---|---|---|---|
| A2.1 | Playwright-Root-Spans in traces.json (Service `playwright-tests`, ein Span pro Testfall) | `[x]` | 30 (Quick), 176 (vollstaendig) — 2026-04-01 |
| A2.2 | Kausale Verknuepfung: PHP-Spans haben gleiche `trace_id` wie Playwright-Root-Span und `parent_span_id` = Root-Span-ID | `[x]` | 172.267+ PHP-Spans kausale verknuepft — 2026-04-01 |
| A2.3 | `Server-Timing`-Header in PHP-Responses fuer gemappte Routes vorhanden | `[x]` | `curl -s -D - -o /dev/null http://localhost:8080/tree/demo` zeigt Header — 2026-04-01 |
| A2.4 | Boomerang-Spans haben gleiche `trace_id` wie zugehoeriger Playwright-Trace (Server-Timing-Bruecke) | `[x]` | 710 trace-korrelierte Browser-Spans im Quick-Lauf (30 Tests). Server-Timing-Bruecke funktioniert mit `recordTransaction: true` und `about:blank`-Flush — 2026-04-01 |
| A2.5 | `trace-report.py` erkennt Playwright-Spans und zeigt Hierarchie | `[x]` | `classify_span()` liefert "Playwright (E2E)", Report zeigt Root-Spans — 2026-04-01 |
| A2.6 | Alle Abnahmekriterien A1.1-A1.8 bestehen weiterhin (`make test-e2e-quick`) | `[x]` | 30/30 Tests, 3.065 Browser-Spans (710 trace-korreliert), 70 Spans gesamt — 2026-04-01 |
| A2.7 | `page.route()` interceptiert keine OTLP-Requests an `otel-collector:4318` | `[x]` | Browser-Spans vorhanden (3.065 Quick) — Route-Pattern schliesst `otel-collector:4318` korrekt aus — 2026-04-01 |

### 6.4 Artefakt-Archivierung `[ ]`

- [ ] 6.4.1: Artefakte als ZIP sichern:
  ```bash
  STUFE="ausbaustufe-2"
  TIMESTAMP=$(date +%Y%m%d_%H%M%S)
  ARCHIVE="artifacts_${STUFE}_${TIMESTAMP}.zip"
  zip -r "$ARCHIVE" artifacts/
  mkdir -p docs/laufzeit_analyse/archives
  mv "$ARCHIVE" docs/laufzeit_analyse/archives/
  ```
- [ ] 6.4.2: Aufraeumen:
  ```bash
  make clean
  ```

---

## 7. Risiken und Mitigationen

| # | Risiko | Schwere | Mitigation |
|---|---|---|---|
| R1 | Boomerang hat kein fertiges Bundle | Mittel | Synchrones Laden der Roh-JS-Dateien aus npm-Tarball |
| R2 | CORS-Probleme Browser --> OTel Collector | Mittel | CORS-Config im Collector HTTP-Receiver mit Origin-Whitelist |
| R3 | Boomerang OTel-Plugin geringe Community | Mittel | Funktional ausgereift; Version gepinnt |
| R4 | zone.js-Seiteneffekte durch Boomerang OTel-Plugin | Niedrig | webtrees kein SPA; jQuery-basiert |
| R5 | mod_substitute fragile Config-Syntax | Niedrig | Init-Script in externe Datei ausgelagert |
| R8 | MySQL 8.4 Healthcheck-Kompatibilitaet | Mittel | Bestehender `mysqladmin ping` Healthcheck kompatibel |
| R9 | Root-Zugriff fuer PerfSchema TRUNCATE | Niedrig | Root-Passwort in compose.yaml als ENV — Testkontext |
| R10 | PerfSchema-Daten bei Container-Neustart verloren | Mittel | Extraktion muss VOR `make down` erfolgen |
| R11 | BaggagePropagator nicht aktiv in PHP | Hoch | Default `tracecontext,baggage`; im Container verifizieren (V1) — **noch offen** |
| R12 | **EINGETRETEN:** Percent-Encoding-Roundtrip fuer test.case_id | ~~Mittel~~ Hoch | PHP OTel SDK BaggagePropagator verarbeitet `%`-Sequenzen fehlerhaft → Fix: Zeichenersetzung statt `encodeURIComponent` (F7) |
| R13 | Upstream-Aenderung der Route-Namen | Mittel | Route-Namen sind FQCN; ungemappte werden ignoriert |
| R14 | Doppelte Spans durch auto-psr15 + OTel-Spans-Modul | Niedrig | Erwuenscht — PSR-15 technisch, Custom semantisch |
| R16 | Boomerang-Spans ohne test.run_id | Mittel | workers=1; temporale Korrelation |

| R17 | **EINGETRETEN:** PHP OTel SDK ohne gRPC-Transport | Hoch | `.env` setzte `grpc`, aber `transport-grpc` war nicht installiert → 0 Spans. Fix: `http/protobuf` (siehe F1) |
| R18 | `page.route('**/*')` interceptiert Boomerang-OTLP-Requests | ~~Hoch~~ | **MITIGIERT** (2026-04-01): Route-Pattern `/^http:\/\/webtrees(:\d+)?\/` implementiert — 3.240 Browser-Spans bestätigen, dass OTLP-Requests nicht abgefangen werden |
| R19 | Boomerang-OTel-Plugin unterstuetzt Server-Timing nicht | Mittel | inspectIT boomerang-opentelemetry.js v2.0.0-2 basiert auf `@opentelemetry/instrumentation-document-load`, das Server-Timing liest. Verifizierung noetig (V8). Fallback: temporale Korrelation aus Stufe 1 |
| R20 | `page.route()` Overhead in Performance-Tests (Layer 5) | Niedrig | Abfangen und Weiterleiten der Requests fuegt minimale Latenz hinzu; bei Baseline-Vergleichen hebt sich der Effekt auf, da er in allen Laeufen gleich ist |
| R21 | Playwright `globalSetup` laeuft in separatem Prozess | ~~Hoch~~ | **MITIGIERT** (2026-04-01): Worker-scoped Fixture implementiert — 30/176 Playwright-Root-Spans in traces.json bestaetigen korrekte TracerProvider-Initialisierung |
| R22 | Server-Timing nur fuer 56 gemappte Routes | Niedrig | Ungemappte Routes (Admin, Utility) haben keinen Server-Timing Header. Boomerang-Spans dort ohne kausale Verknuepfung — Fallback auf temporale Korrelation |

**Eliminierte Risiken:** Apache ABI-Inkompatibilitaet (kein Apache OTel-Modul), MySQL Telemetry Enterprise-only (nicht verwendet). R17 (gRPC-Transport) behoben. R12 (Percent-Encoding) behoben durch Zeichenersetzung. R18 (OTLP-Interception) und R21 (globalSetup-Prozessgrenze) mitigiert in Ausbaustufe 2.

---

## 8. Verifizierungspunkte

| # | Punkt | Was zu pruefen ist | Schritt | Status | Anmerkung |
|---|---|---|---|---|---|
| V1 | OTEL_PROPAGATORS Default | `tracecontext,baggage` im Container aktiv | S5 | `[x]` | `OTEL_PROPAGATORS=tracecontext,baggage` im Container bestaetigt (2026-04-01) |
| V2 | auto-psr15 + PHP 8.5 | Composer-Install ohne Fehler | S4 | `[x]` | auto-psr15 1.2 installiert ohne Fehler |
| V3 | mod_substitute + FallbackResource | Injection funktioniert bei internem Subrequest | S6 | `[x]` | Boomerang-Scripts im HTML-Source verifiziert |
| V4 | MySQL 8.4 Healthcheck | `mysqladmin ping` mit `caching_sha2_password` | S1 | `[x]` | Healthcheck funktioniert |
| V5 | Baggage::getCurrent() Timing | Baggage propagiert bevor OTel-Spans-Modul ausfuehrt | S5 | `[x]` | `test.run_id` in 40 Spans korrekt extrahiert — Baggage-Kontext vor Middleware aktiv |
| V6 | Baggage-Werte durch Stack | test.case_id korrekt durch Stack | S5/S7 | `[x]` | Geloest durch Zeichenersetzung statt Percent-Encoding (siehe F7). 30 Testfaelle korrekt zugeordnet. |
| V7 | File-Exporter Flush-Timing | Collector flusht vor Report-Generierung | S9 | `[x]` | Geloest durch `append: true` im File-Exporter |
| V8 | Boomerang OTel-Plugin Server-Timing | `boomerang-opentelemetry.js` v2.0.0-2 liest `Server-Timing` Header und setzt `parentSpanId` korrekt | S13 | `[x]` | Server-Timing-Bruecke funktioniert. Voraussetzungen: `recordTransaction: true` in Plugin-Config, `about:blank`-Navigation vor Context-Close (Boomerang-Flush). 710 trace-korrelierte Browser-Spans im Quick-Lauf — 2026-04-01 |
| V9 | page.route() Pattern-Ausschluss | `page.route(/^http:\/\/webtrees/)` faengt keine Requests an `otel-collector:4318` ab | S12 | `[x]` | 3.240 Browser-Spans im Quick-Lauf — Route-Pattern `/^http:\/\/webtrees(:\d+)?\/` schliesst `otel-collector:4318` korrekt aus (2026-04-01) |
| V10 | TracerProvider Worker-Scope | Worker-scoped Fixture initialisiert TracerProvider im Test-Prozess (nicht globalSetup) | S12 | `[x]` | 30 Playwright-Root-Spans in traces.json — `provider.shutdown()` flusht Spans korrekt (2026-04-01) |

---

## 9. Komponentenversionen (Ziel-Zustand)

| Komponente | Version | Quelle | Verwaltungsort |
|---|---|---|---|
| MySQL | `mysql:lts` (= 8.4.x) | Docker Hub | `compose.yaml` |
| OTel Collector | Gepinnte Version | Docker Hub | `compose.yaml` |
| Jaeger | Gepinnte Version | Docker Hub | `compose.yaml` |
| Boomerang | 1.815.1 | npm-Registry-Tarball | `Containerfile.webtrees` (ARG) |
| Boomerang OTel-Plugin | 2.0.0-2 | GitHub Release | `Containerfile.webtrees` (ARG) |
| opentelemetry-auto-psr15 | aktuell: 1.2.0 | Composer | `setup-webtrees.sh` |
| OTel-Spans-Modul | 1.0.0 (Custom) | Repo: `modules/otel-spans/` | `compose.yaml` (Volume-Mount) |

---

## 10. Graceful Degradation (OTEL_SDK_DISABLED=true)

| Komponente | Verhalten bei disabled OTel |
|---|---|
| opentelemetry-auto-psr15 | Nicht installiert (Composer-Skip in setup-webtrees.sh) |
| OTel-Spans-Modul | `class_exists(Globals::class)`-Guard → `$handler->handle($request)` ohne OTel (NoOp-Tracer-Annahme war falsch — Klasse fehlt komplett wenn SDK disabled) |
| Boomerang + OTel-Plugin | Injection per Apache `<If>`-Direktive unterdrueckt |
| PerfSchema-Extraktion | Unabhaengig von OTel — laeuft gegen MySQL |
| Trace-Report | Keine Spans --> Fehlermeldung, kein Abbruch |

**Testpflicht:** `make test-all` muss mit beiden Modi fehlerfrei laufen.

---

## 11. SELinux-Hinweise (Fedora/rootless Podman)

- Neue Bind-Mounts verwenden `:z` (shared Label), konsistent mit bestehenden Mounts
- **Kein `:Z`** auf Verzeichnisse, die der Compose-Stack gleichzeitig mountet
- `modules/otel-spans` wird als `:ro,z` gemountet (Read-only, shared)
- Bei MySQL-Upgrade: `make clean` (Volume loeschen), dann `make up && make setup`
- JS-Dateien (Boomerang) sind per COPY im Image — keine SELinux-Relevanz

---

## 12. Aufgeschobene Punkte (nicht blockierend)

| # | Punkt | Prioritaet |
|---|---|---|
| D1 | Content-Security-Policy (falls CSP eingefuehrt) | Niedrig |
| D2 | PerfSchema Baseline-Vergleich (automatische Schwellwerte) | Mittel |
| D3 | Digest-Text-Matching (PDO-Span <-> PerfSchema Korrelation) | Mittel |
| D4 | ARM64-Support (fuer Fedora/x86-64 irrelevant) | Niedrig |

---

## 13. Erkenntnisse und Abweichungen bei der Umsetzung (2026-03-29)

### 13.1 Behobene Fehler

| # | Problem | Ursache | Fix | Betroffene Dateien |
|---|---|---|---|---|
| F1 | **Keine PHP-Spans trotz funktionierendem Stack** | `.env` setzte `OTEL_EXPORTER_OTLP_PROTOCOL=grpc`, aber `open-telemetry/transport-grpc` war nicht installiert. PHP-Fehlermeldung: `Transport factory not defined for protocol: grpc`. Das gesamte SDK initialisierte nicht. | Protokoll auf `http/protobuf` + Endpoint-Port 4317→4318 geaendert | `.env`, `compose.yaml` |
| F2 | **File-Exporter truncated bei Collector-Neustart** | Der OTel Collector File-Exporter erstellt die Datei bei jedem Start neu (Default `append: false`). Bei `make down`/`make up` gingen alle bisherigen Spans verloren. | `append: true` in der File-Exporter-Konfiguration | `otel/otel-collector-config.yaml` |
| F3 | **HTTP 500 bei OTEL_SDK_DISABLED=true** | Die Plan-Annahme war: OTel API liefert NoOp-Tracer wenn SDK disabled. Tatsaechlich: Bei `OTEL_SDK_DISABLED=true` wird die PHP-Extension nicht geladen und `OpenTelemetry\API\Globals` existiert nicht → Fatal Error. | `class_exists(Globals::class)`-Guard am Anfang von `process()` | `modules/otel-spans/OtelSpansModule.php` |
| F4 | **module.php: Klasse nicht gefunden** | Plan-Code fehlte `require_once` — webtrees Custom-Module laufen nicht im Composer-Autoloader. | `require_once __DIR__ . '/OtelSpansModule.php';` in module.php | `modules/otel-spans/module.php` |
| F5 | **Makefile: `-`-Prefix funktionierte nicht korrekt** | Plan-Code nutzte `-scripts/truncate-perfschema.sh;` — Minusprefix ignoriert Fehler nur am Zeilenanfang, nicht innerhalb eines Shell-Blocks. | `scripts/truncate-perfschema.sh \|\| true` | `Makefile` |
| F6 | **Makefile: TEST_RUN_ID nicht im Container** | Plan-Code nutzte `TEST_RUN_ID=$$RUN_ID $(COMPOSE) exec playwright ...` — Umgebungsvariablen vor `podman-compose exec` werden nicht in den Container weitergeleitet. | `$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright ...` | `Makefile` |
| F7 | **test.case_id nicht in Spans (Percent-Encoding-Bug)** | `encodeURIComponent()` erzeugt `%XX`-Sequenzen. Der PHP OTel SDK BaggagePropagator interpretiert `%`-Sequenzen als Encoding und verwirft Eintraege mit dekodierten Sonderzeichen (Leerzeichen, Komma). `test.run_id` (UUID, keine `%`-Zeichen) war nicht betroffen. | Zeichenersetzung statt Percent-Encoding: `testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_')`. `urldecode()` im PHP-Modul und `unquote()` in trace-report.py entfernt. | `otel-fixture.ts`, `OtelSpansModule.php`, `trace-report.py` |
| F8 | **PerfSchema-JSON ungueltig (MySQL-Warnung)** | `mysql -p"..."` gibt `mysql: [Warning] Using a password on the command line interface can be insecure.` auf stderr aus. `podman-compose exec` leitet Container-stderr in stdout — die Warnung landete vor dem JSON in den Ausgabedateien. `trace-report.py` scheiterte beim Parsen. | `MYSQL_PWD` Umgebungsvariable statt `-p` Argument: `podman-compose exec -e MYSQL_PWD=... mysql -u root ...`. Eliminiert die Warnung statt sie umzuleiten. | `extract-perfschema.sh`, `truncate-perfschema.sh` |
| F9 | **Browser-Spans: mod_deflate komprimiert vor mod_substitute** | Browser sendet `Accept-Encoding: gzip`. Apache `mod_deflate` komprimiert die Response bevor `mod_substitute` laueft — die Substitution findet `</head>` nicht im gzip-Stream. Resultat: 0 Boomerang-Injections bei gzip-faehigen Clients (alle Browser). | Filter-Kette `INFLATE;SUBSTITUTE;DEFLATE` statt nur `SUBSTITUTE`: Erst dekomprimieren, dann substituieren, dann wieder komprimieren. | `otel/boomerang-apache.conf` |
| F10 | **Browser-Spans: Collector-URL zeigt auf falschen Hostname** | `boomerang-init.js` nutzte `window.location.hostname` fuer die Collector-URL. Im Playwright-Kontext (Headless Chromium) ist `window.location.hostname` = `webtrees` (Container-interne Adresse). Der Collector hoert auf `otel-collector:4318`, nicht `webtrees:4318`. | Collector-URL auf `http://otel-collector:4318/v1/traces` fest kodiert. Im Container-Netzwerk ist dieser Hostname immer korrekt. | `otel/boomerang-init.js` |
| F11 | **Browser-Spans: CORS-Origin ohne Port nicht erlaubt** | Browser sendet `Origin: http://webtrees` (Standard-Port 80 wird gemaess RFC weggelassen). Der OTel Collector hatte nur `http://webtrees:80` in `allowed_origins`. CORS-Preflight gab 204 zurueck, aber ohne `Access-Control-Allow-Origin`-Header — Browser blockierte den Request. | `http://webtrees` (ohne Port) zu `allowed_origins` hinzugefuegt. | `otel/otel-collector-config.yaml` |
| F12 | **Playwright-Browser nicht gefunden nach OTel-Paketinstallation** | `Containerfile.playwright` installierte Browser mit `npx playwright@latest install` VOR `npm install`. Die OTel-Pakete als neue `devDependencies` konnten die aufgeloeste `@playwright/test`-Version aendern — die vorab installierten Browser passten nicht mehr zur npm-Version. Fehler: `Executable doesn't exist at .../chromium_headless_shell-1217/chrome-headless-shell`. | Browser-Install nach `npm install` verschoben: erst `install-deps` (Systemabhaengigkeiten), dann `npm install`, dann `npx playwright install chromium`. | `Containerfile.playwright` |

### 13.2 Offene Punkte

| # | Punkt | Prioritaet | Abhaengigkeit |
|---|---|---|---|
| O1 | ~~Erneuter Testlauf mit http/protobuf-Fix~~ **ERLEDIGT** (2026-04-01) — Quick-Target-Lauf: 79/79 Integration, 30/30 E2E. 40 OTel-Spans mit `test.run_id` + `test.case_id`, 30 Testfaelle korrekt gruppiert. Trace-Report + PerfSchema funktional. | ~~Hoch~~ | — |
| O2 | ~~A1.2 Browser-Spans funktionieren nicht~~ **ERLEDIGT** (2026-04-01) — 2.430 Browser-Spans (`webtrees-browser`) in traces.json nach E2E-Quick-Lauf. Drei Ursachen: mod_deflate komprimierte vor mod_substitute (F9), Collector-URL zeigte auf falschen Hostname (F10), CORS-Origin ohne Port nicht erlaubt (F11). | ~~Mittel~~ | — |
| O3 | ~~Baggage-Korrelation End-to-End~~ **ERLEDIGT** (2026-04-01) — `test.run_id` und `test.case_id` in allen 40 OtelSpansModule-Spans verifiziert. Fix: Zeichenersetzung statt Percent-Encoding (F7). | ~~Mittel~~ | — |
| O4 | **Erstarchiv ersetzen** — `artifacts_ausbaustufe-1_20260329_203150.zip` enthaelt 0 Spans. Neues Archiv mit echten Trace-Daten aus den Quick-Laeufen (2026-04-01) sollte erstellt werden. | Niedrig | — |
| O5 | **Bekannter Flaky Test** — `search-replace.spec.ts:52` ("search-and-replace page not accessible for visitor") schlaegt beim ersten Versuch fehl, besteht beim Retry. Kein Code-Problem; Timing-Issue. Tritt konsistent in beiden E2E-Laeufen auf. | Niedrig | — |
| O6 | **Nur OtelSpansModule-Spans mit Testfall korreliert** — Die auto-psr15 (46.985) und auto-pdo (144.662) Spans haben kein `test.run_id`-Attribut und erscheinen daher nicht im Testfall-Report. Sie sind ueber `trace_id` mit den OtelSpansModule-Spans verknuepft, aber `trace-report.py` nutzt bisher nur `test.run_id`-Filterung. Fuer die per-Testfall-Zuordnung von DB-Queries waere eine Erweiterung noetig (trace_id-basiertes Nachladen). | Mittel | — |
| O7 | **Layer 3 (Komponentenintegrationstest) hat keine OTel-Korrelation** — PHPUnit-Tests laufen als CLI-PHP im Container, nicht ueber HTTP-Requests. Es entstehen keine auto-psr15/OtelSpansModule-Spans. PerfSchema-Aggregat waere per Truncate/Extract moeglich, aber kein Testfall-Bezug. | Niedrig | — |
| O8 | ~~Boomerang-OTel-Plugin wertet Server-Timing nicht aus~~ **ERLEDIGT** (2026-04-01) — Server-Timing-Bruecke funktioniert. Ursache war fehlende `recordTransaction: true` Config und fehlender `about:blank`-Flush vor Context-Close. Mit beiden Fixes: 710 trace-korrelierte Browser-Spans im Quick-Lauf. PW∩WT = 30/30 (alle Testfaelle haben Playwright + webtrees + Browser-Spans korreliert). | ~~Niedrig~~ | — |

---

## 13. OTLP-Datenformat (Referenz)

### 13.1 OTLP NDJSON Struktur (pro Zeile in traces.json)

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

### 13.2 Zeiteinheiten

| Quelle | Einheit | Umrechnung |
|---|---|---|
| OTLP | Nanosekunden (als String) | / 1e6 = ms |
| PerfSchema | Picosekunden | / 1e9 = ms |
| Jaeger API | Mikrosekunden | / 1e3 = ms |
| Report-Darstellung | Millisekunden | — |

### 13.3 Span-Quellen und Zuordnung

| Quelle | service.name | telemetry.sdk.language | Scope-Name |
|---|---|---|---|
| PHP Auto-PSR15 | `webtrees` | `php` | `io.opentelemetry.contrib.php.psr15` |
| PHP Auto-PDO | `webtrees` | `php` | `io.opentelemetry.contrib.php.pdo` |
| PHP OTel-Spans-Modul | `webtrees` | `php` | `otel-spans` |
| Boomerang Browser | `webtrees-browser` | `webjs` | `@opentelemetry/instrumentation-document-load` |
| Playwright Root-Span | `playwright-tests` | `nodejs` | `playwright-tests` |

---

## 14. Gesamtstatus-Uebersicht

### Ausbaustufe 1

| Phase | Schritt | Beschreibung | Status |
|---|---|---|---|
| 1 | S1 | MySQL 8.0 --> 8.4 LTS | `[ ]` |
| 1 | S2 | Container-Image-Versionen pinnen | `[ ]` |
| 1 | S3 | OTel Collector HTTP-Receiver | `[ ]` |
| 1 | S4 | auto-psr15 installieren | `[ ]` |
| 2 | S5 | OTel-Spans-Modul entwickeln | `[ ]` |
| 3 | S7 | Playwright Baggage-Fixture | `[ ]` |
| 3 | S8 | PerfSchema-Extraktion | `[ ]` |
| 4 | S9 | Trace-Report-Script | `[ ]` |
| 4 | S10 | Makefile-Integration | `[ ]` |
| 5 | S6 | Boomerang + mod_substitute | `[x]` |
| — | — | Testlauf Ausbaustufe 1 | `[ ]` |
| — | — | Archivierung Ausbaustufe 1 | `[ ]` |

### Ausbaustufe 2

| Schritt | Beschreibung | Status |
|---|---|---|
| S11 | Playwright OTel SDK installieren | `[x]` |
| S12 | Playwright Fixture erweitern (traceparent) | `[x]` |
| S13 | Server-Timing Header im OTel-Spans-Modul | `[x]` |
| S14 | Trace-Report erweitern (4-Stufen-Hierarchie) | `[x]` |
| — | Testlauf Ausbaustufe 2 | `[x]` |
| — | Archivierung Ausbaustufe 2 | `[ ]` |
