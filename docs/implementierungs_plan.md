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
PHP (gRPC) --> OTel Collector (:4317, nur gRPC) --> Jaeger (UI :16686)
                                                 --> file (/artifacts/traces.json)
```

### 1.4 Dateien, die geaendert werden

| Datei | Aenderungen |
|---|---|
| `compose.yaml` | MySQL 8.4, Image-Pinning, HTTP-Port 4318, OTel-Spans-Modul-Mount |
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
  |-- Spans --> gRPC --> Collector:4317
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

**Korrelation:** W3C Baggage (`test.run_id`, `test.case_id`). Keine durchgehende traceparent-Kette. Boomerang-Spans nur ueber Zeitfenster korrelierbar.

### 2.2 Ausbaustufe 2 — Playwright Root-Span (kausale Trace-Kette)

```
Playwright (OTel SDK in Node.js)
  |-- Erzeugt Root-Span pro Testfall
  |-- Setzt traceparent + baggage via page.route() (dynamisch pro Request)
  |
  v
Browser (Boomerang + OTel-Plugin)
  |-- Document Load Span liest Server-Timing Header --> gleicher Trace
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
`Playwright-Span --> Boomerang-Span --> PHP-Span --> DB-Spans`

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

#### S1: MySQL 8.0 --> 8.4 LTS `[ ]`

**Datei:** `compose.yaml`

**Teilschritte:**

- [ ] S1.1: `mysql`-Service: Image von `mysql:8.0` auf `mysql:lts` aendern
- [ ] S1.2: `mysql`-Service: PerfSchema Stage-Instrumentierung via `command` aktivieren:
  ```yaml
  command: >
    --character-set-server=utf8mb4
    --collation-server=utf8mb4_bin
    --performance-schema-instrument='stage/%=ON'
    --performance-schema-consumer-events-stages-current=ON
    --performance-schema-consumer-events-stages-history=ON
  ```
- [ ] S1.3: `mysql-security`-Service: Image parallel auf `mysql:lts` aktualisieren
- [ ] S1.4: `make clean` ausfuehren (Volume loeschen — In-Place-Upgrade 8.0->8.4 nicht vorgesehen)
- [ ] S1.5: `make up && make setup` — Stack mit neuem MySQL starten und verifizieren

**Verifizierung V4:** `mysqladmin ping` Healthcheck mit `caching_sha2_password` muss funktionieren. Der bestehende Healthcheck in compose.yaml nutzt bereits `mysqladmin ping` mit Root-Passwort — sollte kompatibel sein.

---

#### S2: Container-Image-Versionen pinnen `[ ]`

**Datei:** `compose.yaml`

**Teilschritte:**

- [ ] S2.1: Aktuelle stabile Version von `otel/opentelemetry-collector-contrib` ermitteln und pinnen (z.B. `0.120.0`)
- [ ] S2.2: Aktuelle stabile Version von `jaegertracing/all-in-one` ermitteln und pinnen (z.B. `1.66`)
- [ ] S2.3: Beide Image-Tags in `compose.yaml` aktualisieren:
  ```yaml
  otel-collector:
    image: docker.io/otel/opentelemetry-collector-contrib:<VERSION>

  jaeger:
    image: docker.io/jaegertracing/all-in-one:<VERSION>
  ```

**Keine Verifizierung noetig** — Standard-Docker-Pull.

---

#### S3: OTel Collector HTTP-Receiver fuer Browser-Traces `[ ]`

**Dateien:** `otel/otel-collector-config.yaml`, `compose.yaml`

**Teilschritte:**

- [ ] S3.1: `otel/otel-collector-config.yaml` — HTTP-Protokoll mit CORS hinzufuegen:
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
- [ ] S3.2: `compose.yaml` — Port 4318 im `otel-collector`-Service exponieren:
  ```yaml
  otel-collector:
    ports:
      - "4317:4317"
      - "4318:4318"
  ```
- [ ] S3.3: Stack neu starten und verifizieren, dass Collector auf Port 4318 horcht

---

#### S4: `auto-psr15` installieren `[ ]`

**Datei:** `scripts/setup-webtrees.sh`

**Teilschritte:**

- [ ] S4.1: In der bestehenden `composer require`-Liste (Zeile 52-56 in `setup-webtrees.sh`, bedingt auf `OTEL_SDK_DISABLED != true`) das Paket `open-telemetry/opentelemetry-auto-psr15` hinzufuegen:
  ```bash
  composer require --dev --no-interaction --no-progress \
    open-telemetry/sdk \
    open-telemetry/exporter-otlp \
    open-telemetry/opentelemetry-auto-pdo \
    open-telemetry/opentelemetry-auto-psr18 \
    open-telemetry/opentelemetry-auto-psr15 2>&1
  ```
- [ ] S4.2: `make clean && make up && make setup` — Container neu bauen und Setup ausfuehren
- [ ] S4.3: Verifizieren, dass auto-psr15 installiert wurde (kein Composer-Fehler)

**Verifizierung V2:** auto-psr15 1.2.0 hat Requirement `php: ^8.1`. Beim `composer require` verifizieren, dass keine Inkompatibilitaet mit PHP 8.5 auftritt.

**Ergebnis:** Automatischer Root-Span pro HTTP-Request mit HTTP Method, URL, Status Code. Trace-Hierarchie: Request --> DB-Queries.

---

### Phase 2: PHP-Instrumentierung (S5)

#### S5: OTel-Spans-Modul entwickeln `[ ]`

**Neue Dateien:** `modules/otel-spans/module.php`, `modules/otel-spans/OtelSpansModule.php`
**Geaenderte Datei:** `compose.yaml`

**Teilschritte:**

- [ ] S5.1: Verzeichnis `modules/otel-spans/` anlegen
- [ ] S5.2: `modules/otel-spans/module.php` erstellen:
  ```php
  <?php
  // SPDX-License-Identifier: AGPL-3.0-or-later
  declare(strict_types=1);

  namespace OtelSpans;

  return new OtelSpansModule();
  ```
- [ ] S5.3: `modules/otel-spans/OtelSpansModule.php` erstellen (Details siehe Abschnitt 3.1)
- [ ] S5.4: `compose.yaml` — Volume-Mount fuer OTel-Spans-Modul im `webtrees`-Service hinzufuegen:
  ```yaml
  webtrees:
    volumes:
      # ... bestehende Volumes ...
      - ./modules/otel-spans:/var/www/html/modules_v4/otel_spans:ro,z
  ```
- [ ] S5.5: `make clean && make up && make setup` — Stack mit neuem Modul-Mount starten
- [ ] S5.6: Verifizieren, dass das Modul in der webtrees-Admin-Oberflaeche sichtbar ist
- [ ] S5.7: Verifizieren, dass Spans in Jaeger erscheinen (Span-Name `webtrees.<action>`)

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

**OTel API-Nutzung:** Das Modul nutzt `OpenTelemetry\API\Globals::tracerProvider()`. Wenn OTel disabled ist (`OTEL_SDK_DISABLED=true`), liefert die API einen NoOp-Tracer — kein `class_exists()`-Check noetig.

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

#### S7: Playwright Baggage-Fixture `[ ]`

**Neue Datei:** `layer4-e2e/helpers/otel-fixture.ts`

**Teilschritte:**

- [ ] S7.1: `layer4-e2e/helpers/otel-fixture.ts` erstellen:
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
- [ ] S7.2: Bestehende E2E-Tests umstellen — Import von `@playwright/test` auf `../helpers/otel-fixture` aendern. Betrifft alle `.spec.ts`-Dateien unter `layer4-e2e/tests/` (aktuell 22 Dateien + 6 Security-Tests)
- [ ] S7.3: Bestehende Performance-Tests umstellen — Import in `layer5-performance/tests/` analog aendern (3 Dateien)
- [ ] S7.4: Verifizieren, dass `make test-e2e` weiterhin fehlerfrei laeuft (Import-Aenderung darf keine Regression erzeugen)

**Baggage-Format:** W3C Baggage Specification. Schluessel duerfen `.` enthalten. UUIDs brauchen kein Encoding. Testtitel mit Sonderzeichen werden per `encodeURIComponent` encoded.

**RUN_ID-Erzeugung:** `TEST_RUN_ID` als Umgebungsvariable (spaeter vom Makefile-Target gesetzt); Fallback: `randomUUID()` pro Testlauf.

---

#### S8: PerfSchema-Extraktion `[ ]`

**Neue Dateien:** `scripts/truncate-perfschema.sh`, `scripts/extract-perfschema.sh`

**Teilschritte:**

- [ ] S8.1: `scripts/truncate-perfschema.sh` erstellen:
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
- [ ] S8.2: `scripts/extract-perfschema.sh` erstellen — Extrahiert JSON fuer 4 PerfSchema-Tabellen + summary.txt (Details siehe Abschnitt 3.2)
- [ ] S8.3: Beide Scripts ausfuehrbar machen (`chmod +x`)
- [ ] S8.4: Manuell testen: `scripts/truncate-perfschema.sh` und `scripts/extract-perfschema.sh layer3`

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

#### S9: Trace-Report-Script `[ ]`

**Neue Dateien:** `scripts/trace-report.py`, `scripts/trace-report.sh`

**Teilschritte:**

- [ ] S9.1: `scripts/trace-report.sh` erstellen (Bash-Wrapper):
  ```bash
  #!/usr/bin/env bash
  # SPDX-License-Identifier: AGPL-3.0-or-later
  set -euo pipefail
  exec python3 "$(dirname "$0")/trace-report.py" "$@"
  ```
- [ ] S9.2: `scripts/trace-report.py` erstellen (Details siehe Abschnitt 3.3)
- [ ] S9.3: Beide Scripts ausfuehrbar machen (`chmod +x`)
- [ ] S9.4: Manuell testen mit vorhandener `artifacts/traces.json`

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

#### S10: Makefile-Integration `[ ]`

**Datei:** `Makefile`

**Teilschritte:**

- [ ] S10.1: `.PHONY`-Zeile um neue Targets erweitern
- [ ] S10.2: Neue Targets hinzufuegen:
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
- [ ] S10.3: Bestehende `test-e2e` und `test-performance` Targets um PerfSchema-Truncate/Extract und Trace-Report erweitern (integrierter Workflow):
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
- [ ] S10.4: Verifizieren, dass `make help` die neuen Targets korrekt anzeigt

---

### Phase 5: Browser-RUM (S6)

#### S6: Boomerang + mod_substitute `[ ]`

**Neue Dateien:** `otel/boomerang-init.js`, `otel/boomerang-apache.conf`
**Geaenderte Datei:** `Containerfile.webtrees`

**Teilschritte:**

- [ ] S6.1: `otel/boomerang-init.js` erstellen:
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
- [ ] S6.2: `otel/boomerang-apache.conf` erstellen:
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
- [ ] S6.3: `Containerfile.webtrees` — Build-Schritte hinzufuegen:
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
- [ ] S6.4: Container-Image neu bauen (`make down && make up`)
- [ ] S6.5: Verifizieren, dass `http://localhost:8080` die Boomerang-Scripts im HTML-Source eingebettet hat (View Source, suche nach `boomerang.js`)
- [ ] S6.6: Verifizieren, dass Browser-Spans in Jaeger unter `webtrees-browser` erscheinen
- [ ] S6.7: Verifizieren, dass bei `OTEL_SDK_DISABLED=true` keine Boomerang-Injection stattfindet

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

### 4.2 Testlauf-Prozedur `[ ]`

- [ ] 4.2.1: Stack sauber aufsetzen:
  ```bash
  make clean && make up && make setup
  ```
- [ ] 4.2.2: Komponentenintegrationstest (Layer 3) ausfuehren:
  ```bash
  make test-integration
  ```
  **Hinweis (CLAUDE.md):** Lang laufende Tests mit `run_in_background: true` starten. Kein `timeout`-Parameter. Exklusive Ausfuehrung — kein paralleler Testlauf.
- [ ] 4.2.3: Systemtest (Layer 4) ausfuehren:
  ```bash
  make test-e2e
  ```
- [ ] 4.2.4: Performanztest (Layer 5) ausfuehren:
  ```bash
  make test-performance
  ```
- [ ] 4.2.5: Graceful Degradation verifizieren — Stack mit `OTEL_SDK_DISABLED=true` testen:
  ```bash
  make clean
  OTEL_SDK_DISABLED=true make up && OTEL_SDK_DISABLED=true make setup
  make test-integration
  make test-e2e
  ```

### 4.3 Abnahmekriterien Ausbaustufe 1

| # | Kriterium | Status |
|---|---|---|
| A1.1 | Jaeger UI zeigt PHP-Spans (auto-psr15, auto-pdo, OTel-Spans-Modul) | `[ ]` |
| A1.2 | Jaeger UI zeigt Browser-Spans (`webtrees-browser` via Boomerang) | `[ ]` |
| A1.3 | PHP-Spans enthalten `test.run_id` und `test.case_id` als Attribute | `[ ]` |
| A1.4 | Span-Hierarchie korrekt: PSR-15 Root --> Custom --> DB-Spans | `[ ]` |
| A1.5 | `trace-report.py` parst OTLP-NDJSON, gruppiert nach Testfall, gibt Layer-Aufschluesselung aus | `[ ]` |
| A1.6 | PerfSchema-JSON vorhanden unter `artifacts/layerN/perfschema/` | `[ ]` |
| A1.7 | `make test-all` fehlerfrei mit `OTEL_SDK_DISABLED=false` (Default) | `[ ]` |
| A1.8 | `make test-all` fehlerfrei mit `OTEL_SDK_DISABLED=true` | `[ ]` |

### 4.4 Artefakt-Archivierung `[ ]`

Einmaliger manueller Schritt — wird NICHT dauerhaft geskriptet oder in Makefile-Targets eingebaut.

- [ ] 4.4.1: Artefakte als ZIP sichern:
  ```bash
  STUFE="ausbaustufe-1"
  TIMESTAMP=$(date +%Y%m%d_%H%M%S)
  ARCHIVE="artifacts_${STUFE}_${TIMESTAMP}.zip"
  zip -r "$ARCHIVE" artifacts/
  mkdir -p docs/laufzeit_analyse/archives
  mv "$ARCHIVE" docs/laufzeit_analyse/archives/
  ```
- [ ] 4.4.2: Aufraeumen fuer Ausbaustufe 2:
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

Ausbaustufe 2 erweitert Ausbaustufe 1 um eine kausale Trace-Kette mittels Playwright OTel SDK. Alle Komponenten aus Ausbaustufe 1 bleiben unveraendert. Baggage-Korrelation bleibt als Fundament.

### 5.2 Implementierungsschritte

#### S11: Playwright OTel SDK installieren `[ ]`

**Geaenderte Datei:** `Containerfile.playwright` (oder `package.json` im Playwright-Container)

**Teilschritte:**

- [ ] S11.1: OTel-Pakete zum Playwright-Container hinzufuegen:
  ```json
  {
    "devDependencies": {
      "@playwright/test": "latest",
      "@opentelemetry/api": "^1.9",
      "@opentelemetry/sdk-node": "^0.57",
      "@opentelemetry/exporter-trace-otlp-http": "^0.57",
      "@opentelemetry/resources": "^1.28"
    }
  }
  ```
- [ ] S11.2: Container-Image neu bauen

---

#### S12: Playwright Baggage-Fixture erweitern `[ ]`

**Geaenderte Datei:** `layer4-e2e/helpers/otel-fixture.ts`

**Teilschritte:**

- [ ] S12.1: Fixture um OTel SDK Root-Span und dynamischen `traceparent` via `page.route()` erweitern:
  ```typescript
  // Erweiterte Fixture (Ausbaustufe 2)
  import { trace, context } from '@opentelemetry/api';

  page: async ({ page }, use, testInfo) => {
    const tracer = trace.getTracer('playwright-tests');
    const span = tracer.startSpan(`test: ${testInfo.title}`);
    const ctx = trace.setSpan(context.active(), span);

    const runId = process.env.TEST_RUN_ID || randomUUID();
    const caseId = encodeURIComponent(testInfo.title);

    // Dynamischer traceparent pro Request
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
- [ ] S12.2: OTel SDK Initialisierung als Playwright globalSetup oder in der Fixture konfigurieren (Exporter auf `http://otel-collector:4318/v1/traces`)
- [ ] S12.3: Verifizieren, dass Playwright-Root-Spans in Jaeger erscheinen

---

#### S13: Server-Timing Header in OTel-Spans-Modul `[ ]`

**Geaenderte Datei:** `modules/otel-spans/OtelSpansModule.php`

**Teilschritte:**

- [ ] S13.1: Nach `$handler->handle($request)` den aktuellen Span-Context als `Server-Timing`-Header in die Response schreiben:
  ```php
  $response = $handler->handle($request);
  $spanContext = \OpenTelemetry\API\Trace\Span::getCurrent()->getContext();
  $serverTiming = sprintf(
      'traceparent;desc="00-%s-%s-01"',
      $spanContext->getTraceId(),
      $spanContext->getSpanId()
  );
  return $response->withHeader('Server-Timing', $serverTiming);
  ```
- [ ] S13.2: Verifizieren, dass `Server-Timing`-Header in HTTP-Responses vorhanden ist (Browser DevTools oder `curl -I`)

---

#### S14: Trace-Report erweitern `[ ]`

**Geaenderte Datei:** `scripts/trace-report.py`

**Teilschritte:**

- [ ] S14.1: Erkennung von Playwright-Spans (`service.name` fuer den Playwright-Service)
- [ ] S14.2: Darstellung der vierstufigen Hierarchie (Playwright --> Boomerang --> PHP --> DB)
- [ ] S14.3: Validierung der Parent-Child-Beziehungen (traceparent-Konsistenz)

---

### 5.3 Resultierende Trace-Kette (4 Stufen)

```
Playwright Root-Span (test: "homepage loads without errors")
  +-- Boomerang documentFetch (Browser --> Server, via traceparent)
  |     +-- PHP Request-Span (Server, Child des propagierten traceparent)
  |           +-- webtrees.view_tree (Custom-Span)
  |           +-- PDO Span (SELECT users ...)
  |           +-- PDO Span (SELECT trees ...)
  |           +-- PDO Span (SELECT modules ...)
  +-- Boomerang documentLoad (Browser-seitiges Timing)
  |     +-- resourceFetch Spans (CSS, JS, Bilder)
  +-- Boomerang XHR/Fetch Spans (AJAX-Requests nach Page-Load)
        +-- PHP Request-Span (nachfolgende Requests)
              +-- PDO Spans
```

---

## 6. Testlauf und Archivierung — Ausbaustufe 2

### 6.1 Voraussetzungen

Alle Schritte S11-S14 sind abgeschlossen. Ausbaustufe 1 ist archiviert.

### 6.2 Testlauf-Prozedur `[ ]`

- [ ] 6.2.1: Stack sauber aufsetzen:
  ```bash
  make clean && make up && make setup
  ```
- [ ] 6.2.2: Layer 3 ausfuehren: `make test-integration`
- [ ] 6.2.3: Layer 4 ausfuehren: `make test-e2e`
- [ ] 6.2.4: Layer 5 ausfuehren: `make test-performance`
- [ ] 6.2.5: Graceful Degradation mit `OTEL_SDK_DISABLED=true` verifizieren

### 6.3 Abnahmekriterien Ausbaustufe 2

| # | Kriterium | Status |
|---|---|---|
| A2.1 | Jaeger UI zeigt Playwright-Root-Spans (ein Span pro Testfall) | `[ ]` |
| A2.2 | Kausale Trace-Kette: Playwright --> Boomerang --> PHP --> DB (Parent-Child via traceparent) | `[ ]` |
| A2.3 | `Server-Timing`-Header in PHP-Responses vorhanden | `[ ]` |
| A2.4 | Boomerang `instrumentation-document-load` liest Server-Timing und ordnet Span in Trace ein | `[ ]` |
| A2.5 | trace-report.py zeigt vierstufige Hierarchie korrekt an | `[ ]` |
| A2.6 | Alle Abnahmekriterien aus Ausbaustufe 1 (A1.1-A1.8) bestehen weiterhin | `[ ]` |

### 6.4 Artefakt-Archivierung `[ ]`

- [ ] 6.4.1: Artefakte als ZIP sichern:
  ```bash
  STUFE="ausbaustufe-2"
  TIMESTAMP=$(date +%Y%m%d_%H%M%S)
  ARCHIVE="artifacts_${STUFE}_${TIMESTAMP}.zip"
  zip -r "$ARCHIVE" artifacts/
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
| R11 | BaggagePropagator nicht aktiv in PHP | Hoch | Default `tracecontext,baggage`; im Container verifizieren (V1) |
| R12 | Percent-Encoding-Roundtrip fuer test.case_id | Mittel | `urldecode()` im Modul; Tests mit Sonderzeichen |
| R13 | Upstream-Aenderung der Route-Namen | Mittel | Route-Namen sind FQCN; ungemappte werden ignoriert |
| R14 | Doppelte Spans durch auto-psr15 + OTel-Spans-Modul | Niedrig | Erwuenscht — PSR-15 technisch, Custom semantisch |
| R16 | Boomerang-Spans ohne test.run_id | Mittel | workers=1; temporale Korrelation |

**Eliminierte Risiken:** Apache ABI-Inkompatibilitaet (kein Apache OTel-Modul), MySQL Telemetry Enterprise-only (nicht verwendet).

---

## 8. Verifizierungspunkte

| # | Punkt | Was zu pruefen ist | Schritt | Status |
|---|---|---|---|---|
| V1 | OTEL_PROPAGATORS Default | `tracecontext,baggage` im Container aktiv | S5 | `[ ]` |
| V2 | auto-psr15 + PHP 8.5 | Composer-Install ohne Fehler | S4 | `[ ]` |
| V3 | mod_substitute + FallbackResource | Injection funktioniert bei internem Subrequest | S6 | `[ ]` |
| V4 | MySQL 8.4 Healthcheck | `mysqladmin ping` mit `caching_sha2_password` | S1 | `[ ]` |
| V5 | Baggage::getCurrent() Timing | Baggage propagiert bevor OTel-Spans-Modul ausfuehrt | S5 | `[ ]` |
| V6 | Percent-Encoding Roundtrip | URL-encoded test.case_id korrekt durch Stack | S5/S7 | `[ ]` |
| V7 | File-Exporter Flush-Timing | Collector flusht vor Report-Generierung | S9 | `[ ]` |

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
| OTel-Spans-Modul | NoOp-Tracer via OTel API — keine Spans, kein Overhead |
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
| 5 | S6 | Boomerang + mod_substitute | `[ ]` |
| — | — | Testlauf Ausbaustufe 1 | `[ ]` |
| — | — | Archivierung Ausbaustufe 1 | `[ ]` |

### Ausbaustufe 2

| Schritt | Beschreibung | Status |
|---|---|---|
| S11 | Playwright OTel SDK installieren | `[ ]` |
| S12 | Playwright Fixture erweitern (traceparent) | `[ ]` |
| S13 | Server-Timing Header im OTel-Spans-Modul | `[ ]` |
| S14 | Trace-Report erweitern (4-Stufen-Hierarchie) | `[ ]` |
| — | Testlauf Ausbaustufe 2 | `[ ]` |
| — | Archivierung Ausbaustufe 2 | `[ ]` |
