<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Analyseprompt: Laufzeitmessung — End-to-End Trace Correlation

## Zweck dieses Dokuments

Dieses Dokument ist ein strukturiertes Analyseprompt. Es definiert die Fragestellungen,
die systematisch bearbeitet werden müssen, bevor ein Implementierungsplan erstellt wird.
Die Analyse soll iterativ erfolgen — jeder Abschnitt kann eigenständig bearbeitet und
die Ergebnisse unter `docs/laufzeit_analyse/` persistiert werden.

---

## 1. Ausgangslage

### 1.1 Bestehender Stack

| Komponente | Ist-Zustand | Version |
|---|---|---|
| **PHP** | `php:8.5-apache` mit OTel PECL Extension | 8.5 |
| **Apache httpd** | Standard `mod_rewrite`, kein OTel-Modul | aus php:8.5-apache |
| **MySQL** | `mysql:8.0`, kein Telemetry-Plugin | 8.0 |
| **OTel Collector** | `otel/opentelemetry-collector-contrib:latest`, gRPC Receiver | latest |
| **Jaeger** | `jaegertracing/all-in-one:latest` | latest |
| **Playwright** | `node:22-bookworm`, Chromium headless | latest |

### 1.2 Bestehende PHP-Instrumentierung

Bereits installiert (via `setup-webtrees.sh`, bedingt auf `OTEL_SDK_DISABLED != true`):

- `open-telemetry/sdk`
- `open-telemetry/exporter-otlp`
- `open-telemetry/opentelemetry-auto-pdo` (DB-Queries)
- `open-telemetry/opentelemetry-auto-psr18` (HTTP-Client)

### 1.3 Bestehende Trace-Pipeline

```
PHP (gRPC) → OTel Collector → Jaeger (UI auf :16686)
                             → file (/artifacts/traces.json)
```

### 1.4 Zielzustand — Korrelierte Traces über alle Layer

```
Playwright (Baggage) → Browser/Boomerang (RUM) → Apache httpd (OTel Modul)
    → PHP Backend (Auto + Custom Spans) → MySQL (Telemetry Plugin)
         │                │                  │               │
         └────────────────┴──────────────────┴───────────────┘
                    Korreliert via W3C Trace Context + Baggage
                    → OTel Collector → Jaeger + File-Export
                    → Automatisierte Auswertung je Testlauf/Testfall
```

> **Analyse-Ergebnis (A9):** Der Zielzustand ist nicht vollstaendig erreichbar.
> Apache httpd OTel-Modul entfaellt (kein Pre-built Binary), MySQL Telemetry
> Plugin entfaellt (Enterprise-only). Erreichbare Architektur:
> `Playwright (Baggage) → Boomerang (RUM) → Apache (transparent) → PHP (OTel) → MySQL (PerfSchema)`
> Korrelation via W3C Baggage (`test.run_id`, `test.case_id`).
> Siehe Abschnitt 7 fuer die vollstaendige Ergebnis-Zusammenfassung.

---

## 2. Analyseabschnitte

### 2.1 Boomerang RUM mit OpenTelemetry Plugin

**Quellen:**
- Boomerang: https://akamai.github.io/boomerang/oss/index.html
- OTel Plugin: https://github.com/inspectIT/boomerang-opentelemetry-plugin

**Scope-Einschränkung:** Nur das Boomerang-OpenTelemetry-Plugin. inspectIT als
Gesamtprodukt ist **nicht** im Scope.

**Analysefragen:**

#### 2.1.1 Boomerang-Distribution

- Welche Version von Boomerang ist aktuell stabil (Release-Tag)?
- Wie wird Boomerang als fertige Distribution (JS-Bundle) bezogen — npm, CDN, GitHub Release?
- Welche Boomerang-Plugins sind für RUM im Testkontext relevant (RT, NavigationTiming, ResourceTiming)?
- Wie wird das OpenTelemetry-Plugin in das Boomerang-Bundle integriert?
  - Ist es ein separates JS-File oder muss ein Custom Build erstellt werden?
  - Falls Custom Build: Wie sieht die Build-Pipeline aus (Grunt, npm)?
- Welche Konfigurationsparameter braucht Boomerang mindestens?
  - `beacon_url` — wohin gehen die Beacons? Direkt an OTel Collector (OTLP/HTTP)?
  - Oder braucht es einen Beacon-Proxy/Receiver?

#### 2.1.2 OTel Collector: Browser-Traces empfangen

- Aktuell nur gRPC auf Port 4317. Boomerang sendet HTTP.
- Welchen Receiver braucht der Collector? `otlp/http` auf Port 4318?
- Oder nutzt das Boomerang-OTel-Plugin ein eigenes Format (Zipkin, proprietär)?
- Welche Collector-Config-Änderungen sind nötig?

#### 2.1.3 Injection-Ansatz A: webtrees-Modul (modules_v4)

- webtrees-Module implementieren das Interface `ModuleInterface`.
- Welches Interface ermöglicht das Einfügen von `<script>`-Tags in jede Seite?
  - `ModuleGlobalInterface` + `headContent()` oder `bodyContent()`?
  - Oder `ModuleFooterInterface`?
- Das Modul muss **synchrones** JavaScript injizieren (kein `async`/`defer`), damit
  Boomerang vor dem ersten User-Event initialisiert ist.
- Wie wird das Modul in den Stack integriert?
  - Eigenes Verzeichnis in diesem Repo (z.B. `otel/boomerang-module/`)?
  - Beim Setup nach `modules_v4/` kopiert?
- Vorteil: Nutzt webtrees-API, saubere Integration, kann Tree-Name als Attribut setzen.
- Nachteil: Abhängigkeit vom webtrees-Modul-API, muss bei API-Änderungen angepasst werden.

**Zu prüfen:** Welche `ModuleInterface`-Untertypen existieren in der aktuellen
webtrees-Version, die HTML-Injection in den `<head>` oder vor `</body>` ermöglichen?
Referenz: `upstream/webtrees/app/Module/` — dort liegen die Modul-Interfaces.

#### 2.1.4 Injection-Ansatz B: Apache mod_substitute

- Apache `mod_substitute` kann HTML-Responses on-the-fly modifizieren.
- Regel: `Substitute "s|</head>|<script src="...boomerang..."></script></head>|i"`
- Vorteil: Unabhängig von webtrees-Code, funktioniert bei jeder PHP-App.
- Nachteil: Blind — kein Zugang zu webtrees-Kontext (User, Tree).
- Ist `mod_substitute` im `php:8.5-apache` Image verfügbar oder muss es aktiviert werden?
- Performance-Impact: Jede Response wird gescannt — Auswirkung auf Messungen?
- Interaktion mit `Content-Encoding: gzip` — muss `mod_deflate` nach `mod_substitute` laufen?

#### 2.1.5 Bewertungsmatrix

Die Analyse soll beide Ansätze anhand folgender Kriterien bewerten:

| Kriterium | Modul (A) | mod_substitute (B) |
|---|---|---|
| Implementierungsaufwand | ? | ? |
| Wartbarkeit bei webtrees-Updates | ? | ? |
| Kontextzugang (User, Tree, XREF) | ? | ? |
| Synchrones Laden garantiert | ? | ? |
| Unabhängigkeit von Upstream | ? | ? |
| Performance-Impact | ? | ? |

---

### 2.2 Apache httpd — OpenTelemetry-Modul

**Quelle:** https://github.com/open-telemetry/opentelemetry-cpp-contrib/tree/main/instrumentation/httpd

**Anforderung:** Nur Pre-Built Binaries aus vertrauenswürdigen Original-Quellen.
Kein Kompilieren aus Source im Container-Build.

> **Analyse-Ergebnis (A3):** Kein kompatibles Pre-built Binary fuer `php:8.5-apache`
> (Debian Bookworm). Apache wird als transparenter Proxy ohne OTel-Modul betrieben.
> traceparent/baggage-Header fliessen unveraendert an PHP weiter.

**Analysefragen:**

#### 2.2.1 Binary-Verfügbarkeit

- Stellt das Projekt (`opentelemetry-cpp-contrib`) fertige `.so`-Module als GitHub
  Release oder über einen Paketmanager bereit?
- Falls ja: Für welche Apache/OS-Kombinationen? Passt `php:8.5-apache` (Debian Bookworm)?
- Falls nein: Gibt es alternative Quellen?
  - Docker-Images mit vorinstalliertem Modul (z.B. `ghcr.io/open-telemetry/...`)?
  - OS-Pakete (apt, rpm)?
  - Andere vertrauenswürdige Distributionen?

#### 2.2.2 Apache-Integration

- Welche Apache-Version liefert `php:8.5-apache`? (`apache2 -v`)
- Ist die `.so` ABI-kompatibel mit dieser Apache-Version?
- Welche Apache-Config-Direktiven sind nötig?
  - `LoadModule otel_module modules/mod_opentelemetry.so`
  - `OpenTelemetryExporter` — Ziel: `otel-collector:4317` oder `4318`?
  - Propagation: `OpenTelemetryPropagators` — `tracecontext,baggage`?
- Wird der `traceparent`-Header an PHP weitergereicht?
  - Wenn ja: Die PHP-Auto-Instrumentation erkennt `traceparent` und setzt den
    Trace-Context fort — Korrelation Apache ↔ PHP entsteht automatisch.

#### 2.2.3 Trace-Context-Propagation

- Apache httpd empfängt `traceparent` + `baggage` vom Browser (Boomerang/Fetch).
- Das OTel-Modul muss:
  1. Einen Server-Span erzeugen (parent = Browser-Span)
  2. `traceparent` + `baggage` an PHP weiterreichen (via Request-Header oder Env-Var)
- Unterstützt das Modul W3C Baggage Propagation nativ?
- Falls nicht: Wie wird Baggage (`test.run_id`, `test.case_id`) durchgereicht?

#### 2.2.4 Fallback ohne Binary

Falls kein Pre-Built Binary verfügbar ist:
- Kann die Korrelation Apache ↔ PHP auch ohne Apache-OTel-Modul erreicht werden?
  - Z.B. indem Boomerang den `traceparent`-Header direkt in Fetch-Requests setzt
    und PHP diesen als Parent-Context übernimmt?
- Was geht dabei verloren? (Apache-interne Latenz, Queue-Time, TLS-Handshake)

---

### 2.3 MySQL 9.x — Telemetry Trace Plugin

**Quelle:** https://dev.mysql.com/doc/refman/9.6/en/telemetry-trace-install.html

**Entscheidung:** MySQL-Version wird von 8.0 auf 9.x angehoben.
Showstopper-Warnung, falls bekannt.

> **Analyse-Ergebnis (A4):** Telemetry Trace Plugin ist **Enterprise-only** (Showstopper).
> MySQL-Version-Entscheidung: **8.4 LTS** statt 9.x — kein Vorteil durch Innovation-Release,
> da das Plugin in keiner Community-Version verfuegbar ist.

**Analysefragen:**

#### 2.3.1 Versionsauswahl

- Welche MySQL 9.x-Version ist aktuell als `docker.io/library/mysql:9.x` verfügbar?
- Ist `mysql:9.2` oder `mysql:9.6` als offizielles Docker-Image vorhanden?
- Kompatibilität mit webtrees: Unterstützt die aktuelle webtrees-Version MySQL 9.x?
  - Bekannte Inkompatibilitäten (z.B. geändertes SQL-Verhalten, entfernte Features)?
  - Wie wird webtrees `composer.json` → `require` → `ext-pdo_mysql` gehandhabt?
- **Showstopper-Check:** Ist ein harter Blocker für MySQL 9.x mit webtrees bekannt?

#### 2.3.2 Telemetry Trace Plugin

- Name: `component_telemetry` (8.0.33+) oder `telemetry_trace` (9.x)?
- Wie wird das Plugin installiert?
  - `INSTALL COMPONENT 'file://component_telemetry'`?
  - Oder als Server-Startparameter: `--early-plugin-load`?
- Ist das Plugin im Standard-MySQL-Docker-Image enthalten oder muss es separat bezogen werden?
- Welches Protokoll nutzt das Plugin für den Export?
  - OTLP/gRPC direkt an den OTel Collector?
  - Oder ein proprietäres Format, das einen Zwischenschritt braucht?

#### 2.3.3 Trace-Propagation MySQL ↔ PHP

- PDO (PHP) → MySQL: Wie wird der Trace-Context an MySQL übergeben?
  - MySQL 9.x Telemetry unterstützt `traceparent` als Connection-Attribut?
  - Oder muss der Client den Context als SQL-Kommentar (`/*traceparent=...*/`) mitgeben?
  - Unterstützt `opentelemetry-auto-pdo` diese Propagation automatisch?
- MySQL → OTel Collector: Sendet das Plugin eigenständig an den Collector?
  - Konfiguration: Endpoint, Protokoll, Service-Name?
  - Muss MySQL Netzwerkzugriff auf `otel-collector:4317` haben?

#### 2.3.4 compose.yaml-Änderungen

- Image-Wechsel: `mysql:8.0` → `mysql:9.x`
- Neue `command`-Parameter für Telemetry?
- Neue Umgebungsvariablen?
- Netzwerk: MySQL muss `otel-collector` erreichen können (bereits im selben Netzwerk).
- Volume-Kompatibilität: Kann ein mit 8.0 erzeugtes Volume von 9.x gelesen werden?
  Oder muss `mysql-data` bei Upgrade neu initialisiert werden (`make clean`)?

---

### 2.4 W3C Baggage — Testlauf- und Testfall-Korrelation

**Entscheidung:** W3C Baggage Header als Propagation-Mechanismus.

**Analysefragen:**

#### 2.4.1 Playwright → HTTP Request

- Playwright kann Custom-Header pro Request setzen: `page.setExtraHTTPHeaders()`.
- Baggage-Format: `baggage: test.run_id=<uuid>,test.case_id=<test-title>`
- Soll `test.run_id` pro `test.describe`-Block oder pro Playwright-Prozess gesetzt werden?
- Soll `test.case_id` der Playwright-Test-Titel sein oder eine eigene ID?
- Wie wird `traceparent` initial erzeugt?
  - Option A: Playwright erzeugt selbst einen Root-Span (erfordert OTel-SDK in Node.js)
  - Option B: Boomerang erzeugt den Root-Span im Browser
  - Option C: Apache httpd erzeugt den Root-Span (kein Browser-Span)

#### 2.4.2 Boomerang ↔ Baggage

- Empfängt Boomerang den `baggage`-Header aus der Initial-Response?
- Oder muss Baggage im JavaScript-Kontext gesetzt werden?
- Kann Boomerang Baggage-Attribute in die eigenen Beacons/Spans aufnehmen?
- Wie interagiert das OpenTelemetry-Plugin mit dem W3C Baggage-Standard?

#### 2.4.3 Propagation durch den Stack

Für jeden Hop prüfen: Wird `baggage` automatisch weitergeleitet?

| Hop | Propagation | Automatisch? |
|---|---|---|
| Playwright → Browser (Page Request) | `setExtraHTTPHeaders` | manuell |
| Browser → Apache httpd | HTTP Header | ja (Browser sendet mit) |
| Apache httpd → PHP | OTel-Modul propagiert | zu prüfen |
| PHP → MySQL | PDO Connection | zu prüfen |
| Boomerang → OTel Collector | Beacon/OTLP | zu prüfen |

#### 2.4.4 Auswertbarkeit

- Wird `test.run_id` als Span-Attribut in Jaeger sichtbar?
- Kann Jaeger nach Baggage-Attributen filtern?
- Oder muss der OTel Collector Baggage → Span-Attribute konvertieren?
  - Processor: `baggage` → `attributes` im Collector-Pipeline?

---

### 2.5 PHP-Instrumentierung — Ausbaustufe

**Anforderung:** Upstream-Code nicht anfassen. Kein GEDCOM-Import.
Fokus: Nutzerinteraktion — Datenabfrage und Datenedit.

**Analysefragen:**

#### 2.5.1 Zusätzliche Auto-Instrumentierungen

Welche weiteren `opentelemetry-auto-*` Pakete sind verfügbar und relevant?

| Paket | Zweck | Relevanz |
|---|---|---|
| `opentelemetry-auto-pdo` | DB-Queries | bereits installiert |
| `opentelemetry-auto-psr18` | HTTP-Client | bereits installiert |
| `opentelemetry-auto-curl` | cURL-Aufrufe | ? |
| `opentelemetry-auto-psr15` | HTTP-Middleware | ? |
| `opentelemetry-auto-psr3` | Logging | ? |
| `opentelemetry-auto-laravel` | Laravel Framework | nicht relevant |
| `opentelemetry-auto-slim` | Slim Framework | nicht relevant |
| `opentelemetry-auto-io` | I/O-Operationen | ? |
| `opentelemetry-auto-wordpress` | WordPress | nicht relevant |

- Welche Pakete existieren tatsächlich (aktuelle Composer-Registry prüfen)?
- Nutzt webtrees PSR-15 Middleware? Falls ja: `auto-psr15` wäre relevant.

#### 2.5.2 Custom Spans — webtrees-Modul

Für Nutzerinteraktion (Datenabfrage, Datenedit) ohne Upstream-Änderungen:

- Kann ein webtrees-Modul Middleware registrieren, die um bestimmte Routes Custom Spans erzeugt?
- webtrees nutzt Laravel-artige Request-Handling: Welche Hooks sind verfügbar?
  - `RequestHandlerInterface` (PSR-15)?
  - Event-System?
  - Middleware-Stack?
- Welche Nutzerinteraktionen erzeugen identifizierbare HTTP-Requests?
  - Personenansicht: `GET /tree/{tree}/individual/{xref}`
  - Suche: `GET /tree/{tree}/search/general?query=...`
  - Bearbeitung: `POST /tree/{tree}/edit/...`
  - Familienansicht: `GET /tree/{tree}/family/{xref}`
- Kann ein Modul diese Requests abfangen und Spans mit semantischen Attributen erzeugen?
  - `webtrees.action = "view_individual"`
  - `webtrees.tree = "demo"`
  - `webtrees.xref = "I123"`

#### 2.5.3 Mehrwert-Analyse

Was gewinnt man durch Custom Spans gegenüber reiner Auto-Instrumentation?

| Aspekt | Auto-Only | Auto + Custom |
|---|---|---|
| HTTP-Request-Latenz | ja | ja |
| DB-Query-Latenz | ja | ja |
| Semantik (welche Aktion?) | nein | ja |
| Entity-Bezug (welcher XREF?) | nein | ja |
| Baum-Kontext | nein | ja |
| Nutzer-Rolle | nein | ja |

---

### 2.6 MySQL Performance Schema — Auswertung und OTel-Korrelation

**Quelle:** https://dev.mysql.com/doc/refman/9.6/en/performance-schema.html

**Kontext:** MySQL läuft im Container. Performance-Schema-Daten sind nur innerhalb
des laufenden MySQL-Prozesses verfügbar und gehen beim Container-Neustart verloren.
Deshalb muss eine Strategie existieren, die relevanten Daten zum Abschluss jedes
Testlaufs zu extrahieren und unter `artifacts/` zu persistieren.

**Analysefragen:**

#### 2.6.1 Performance Schema im Container-Kontext

- Ist Performance Schema im `mysql:9.x` Docker-Image standardmäßig aktiviert?
  - Falls nicht: Welche `command`-Parameter aktivieren es (`--performance-schema=ON`)?
  - Speicher-Overhead: Wie viel zusätzlichen RAM verbraucht Performance Schema?
  - Auswirkung auf die Testlaufzeiten selbst (Observer-Effekt)?
- Welche `performance_schema`-Tabellen sind für Laufzeitmessung relevant?
  - `events_statements_summary_by_digest` — SQL-Statement-Profile (Häufigkeit, Latenz, Rows)
  - `events_stages_summary_global_by_event_name` — Query-Phasen (Parsing, Optimizing, Executing)
  - `events_waits_summary_global_by_event_name` — I/O-Waits, Lock-Waits
  - `events_transactions_summary_by_account_by_event_name` — Transaktions-Latenz
  - `table_io_waits_summary_by_table` — I/O pro Tabelle
  - `file_summary_by_instance` — Datei-I/O (relevant für InnoDB)
- Welche Instruments und Consumers müssen explizit aktiviert werden?
  - Default-Konfiguration vs. benötigte Konfiguration für Testkontext?
  - `UPDATE performance_schema.setup_instruments SET ENABLED = 'YES' WHERE ...`?
  - Oder besser als Server-Startparameter in `compose.yaml` → `command`?

#### 2.6.2 Korrelation Performance Schema ↔ OpenTelemetry

- **Trace-ID-Propagation:** Kann MySQL 9.x die `traceparent`-Information (Trace-ID,
  Span-ID) in Performance-Schema-Events abbilden?
  - `events_statements_current.TRACE_ID` — existiert dieses Feld in 9.x?
  - Oder muss die Korrelation indirekt erfolgen (Zeitfenster, Thread-ID)?
- **Thread-basierte Korrelation:**
  - `PROCESSLIST_ID` / `THREAD_ID` in Performance Schema ↔ PDO-Connection-ID in PHP
  - Kann `opentelemetry-auto-pdo` die MySQL Thread-ID als Span-Attribut setzen?
  - Reicht temporale Korrelation (Statement-Timestamp ↔ Span-Timestamp)?
- **Telemetry Trace Plugin ↔ Performance Schema:**
  - Nutzt das Telemetry Trace Plugin (Abschnitt 2.3) intern Performance-Schema-Daten?
  - Gibt es Redundanzen oder ergänzen sich beide Quellen?
  - Empfehlung: Beide aktivieren oder eins von beiden ausreichend?

#### 2.6.3 Datenextraktion zum Testlauf-Ende

Strategie: Am Ende jedes Testlaufs (Layer 3 Integration, Layer 4 E2E, Layer 5 Performance)
werden die Performance-Schema-Daten aus dem laufenden MySQL-Container extrahiert.

- **Extraktionsmethode:**
  - Option A: `podman-compose exec mysql mysqldump --tab` auf `performance_schema`-Views
    (nicht möglich — Performance Schema ist nicht dumpbar)
  - Option B: SQL-Queries via `podman-compose exec mysql mysql -e "SELECT ... FROM performance_schema.…"`
    → Output als CSV/JSON in `/artifacts/`
  - Option C: PHP-Script im webtrees-Container, das via PDO die Performance-Schema-Tabellen
    abfragt und als JSON exportiert
  - Option D: Eigenes Extraktions-Script (bash + mysql-client), das nach jedem Testlauf aufgerufen wird
- **Welche Option passt am besten zum bestehenden `layer*/run.sh`-Muster?**
- **Timing:** Muss die Extraktion _vor_ dem Reset/Truncate der Performance-Schema-Daten
  erfolgen? Wie werden die Daten zwischen Testläufen getrennt?
  - `TRUNCATE TABLE performance_schema.events_statements_summary_by_digest` vor jedem Lauf?
  - Oder Snapshot-Differenz (vorher/nachher)?

#### 2.6.4 Artefakt-Struktur und Ausgabeformat

Vorschlag für die Ablage unter `artifacts/`:

```
artifacts/
├── layer3/
│   └── perfschema/
│       ├── statements_by_digest.json      # Top-N SQL-Statements nach Latenz
│       ├── table_io_waits.json            # I/O pro webtrees-Tabelle
│       └── summary.txt                    # Menschenlesbare Zusammenfassung
├── layer4/
│   └── perfschema/
│       ├── statements_by_digest.json
│       ├── table_io_waits.json
│       └── summary.txt
└── layer5/
    └── perfschema/
        ├── statements_by_digest.json
        ├── table_io_waits.json
        └── summary.txt
```

**Analysefragen:**
- Welche Spalten/Metriken sind pro Tabelle im Export relevant?
  - `events_statements_summary_by_digest`: `DIGEST_TEXT`, `COUNT_STAR`, `SUM_TIMER_WAIT`,
    `AVG_TIMER_WAIT`, `SUM_ROWS_EXAMINED`, `SUM_ROWS_SENT`
  - `table_io_waits_summary_by_table`: `OBJECT_NAME`, `COUNT_FETCH`, `SUM_TIMER_FETCH`,
    `COUNT_INSERT`, `SUM_TIMER_INSERT`, `COUNT_UPDATE`, `SUM_TIMER_UPDATE`
- JSON-Format: Flach (ein Array von Rows) oder hierarchisch (gruppiert nach Tabelle)?
- Summary-Format: Top-10-Queries, langsamste Tabellen, Gesamtstatistik?
- Soll die Summary auch in die Konsolenausgabe des Testlaufs integriert werden
  (analog zu den Testergebnissen)?

#### 2.6.5 Baseline-Vergleich und Regression

Analog zum bestehenden Performance-Baseline-Mechanismus (`layer5-performance/baselines/`):

- Soll es Performance-Schema-Baselines geben?
  - Z.B. "Query X darf nicht mehr als Y ms im Durchschnitt brauchen"
  - Oder "Tabelle Z darf nicht mehr als N Full-Table-Scans pro Testlauf haben"
- Schwellwert-Definition: Absolut (ms) oder relativ (+20% wie bei Ladezeiten)?
- Integration mit dem Auswertungs-Script (Abschnitt 2.7): Können Performance-Schema-Daten
  den Trace-basierten Layer-Report ergänzen?
  - Z.B. unter dem MySQL-Layer zusätzlich: "Top 3 Queries: ..."

#### 2.6.6 Makefile-Integration

- Neues Target `make perfschema-report`? Oder integriert in bestehende `run.sh`-Scripts?
- Soll der Export automatisch am Ende von `make test-integration`, `make test-e2e`,
  `make test-performance` laufen?
- Oder als optionaler Post-Step (z.B. `make test-integration && make perfschema-report`)?

---

### 2.7 Automatisierte Auswertung

**Anforderung:** Script, das Traces eines Testlaufs je Testfall mit Laufzeit pro Layer anzeigt.
Ergänzend: Performance-Schema-Daten (Abschnitt 2.6) in den Report integrieren.

**Analysefragen:**

#### 2.7.1 Datenquellen

- Primär (Traces): `/artifacts/traces.json` (OTel Collector File-Exporter)
- Ergänzend (DB-Profiling): `/artifacts/layer{3,4,5}/perfschema/*.json` (Performance Schema Export, siehe 2.6)
- Alternativ (Traces): Jaeger-API (`http://jaeger:16686/api/traces?...`)
- Welche Quelle ist für die Auswertung besser geeignet?
  - File: Sofort verfügbar, offline, aber Rohformat (OTLP JSON)
  - Jaeger-API: Strukturiert, filterbar, aber erfordert laufenden Jaeger-Container

#### 2.7.2 Auswertungslogik

Gewünschte Ausgabe (Beispiel):

```
=== Testlauf: a1b2c3d4 (2026-03-29T14:30:00Z) ===

Testfall: homepage load time within threshold
  Browser (RUM):     120ms  [Boomerang NavigationTiming]
  Apache httpd:       15ms  [mod_opentelemetry Server-Span]
  PHP Backend:       280ms  [opentelemetry-auto-psr15]
    └─ DB Query:      45ms  [opentelemetry-auto-pdo]
    └─ DB Query:      12ms  [opentelemetry-auto-pdo]
  MySQL Server:       38ms  [telemetry_trace Plugin]
  Total (E2E):       415ms

Testfall: search results load time
  Browser (RUM):      85ms
  Apache httpd:       12ms
  PHP Backend:       520ms
    └─ webtrees.action: search_general
    └─ DB Query:     180ms
    └─ DB Query:      95ms
    └─ DB Query:      42ms
  MySQL Server:      290ms
  Total (E2E):       617ms

--- Performance Schema (Testlauf-Aggregat) ---
  Top SQL by Latenz:
    1. SELECT ... FROM wt_individuals WHERE ...  avg=12ms  calls=847  rows_examined=4230
    2. SELECT ... FROM wt_name WHERE ...         avg=8ms   calls=1203 rows_examined=2406
    3. SELECT ... FROM wt_places WHERE ...       avg=6ms   calls=312  rows_examined=1560
  Table I/O:
    wt_individuals:  fetches=4230  insert=0  update=0  total_wait=9.8s
    wt_name:         fetches=2406  insert=0  update=0  total_wait=5.2s
    wt_dates:        fetches=1890  insert=0  update=0  total_wait=3.1s
```

#### 2.7.3 Korrelation im Script

- Wie wird `test.run_id` aus dem Baggage-Attribut extrahiert?
- Wie werden Spans dem richtigen Layer zugeordnet?
  - `service.name = "webtrees"` → PHP
  - `service.name = "apache-httpd"` → Apache (falls OTel-Modul eigenen Service-Name setzt)
  - `service.name = "mysql"` → MySQL
  - Browser-Spans: `telemetry.sdk.language = "webjs"` oder Boomerang-spezifisch?
- Wie wird die hierarchische Darstellung (Parent-Child) aufgelöst?

#### 2.7.4 Ausgabeformat und Integration

- Shell-Script (bash + jq) oder Python?
- Output: Konsole + JSON-Datei unter `/artifacts/`?
- Integration in Makefile als eigenes Target (z.B. `make trace-report`)?
- Soll der Report auch in den Playwright-HTML-Report eingebettet werden?

#### 2.7.5 Performance-Schema-Daten im Trace-Report

- Wie werden Performance-Schema-Daten (2.6) in den Trace-Report integriert?
- Vorschlag: Unter dem MySQL-Layer im hierarchischen Report zusätzlich:
  ```
  MySQL Server:       38ms  [telemetry_trace Plugin]
    └─ Top Query:     25ms  SELECT ... FROM wt_individuals  [perfschema digest]
    └─ Table I/O:     12ms  wt_individuals (42 fetches)     [perfschema table_io]
  ```
- Wie wird die Zuordnung Performance-Schema ↔ Trace hergestellt?
  - Temporale Korrelation (Zeitfenster des Testfalls)?
  - Thread-ID-Korrelation (falls aus PDO-Span extrahierbar)?
  - Oder nur aggregierte Darstellung (Performance Schema = Gesamtstatistik des Testlaufs,
    nicht pro Testfall aufgeschlüsselt)?

---

## 3. Querschnittsaspekte

### 3.1 Versionierung und Reproduzierbarkeit

**Anforderung:** Module/Frameworks/APIs müssen mit Versionen referenziert und
dynamisch je Testlauf im Setup berücksichtigt werden. Keine Repo-Kopien.

| Komponente | Versionierung | Analyse-Ergebnis |
|---|---|---|
| Boomerang JS | npm-Registry-Tarball, Pin auf Version | `boomerangjs@1.815.1` (A1) |
| Boomerang OTel Plugin | GitHub Release, Pin auf Version | `2.0.0-2` (A1) |
| ~~Apache OTel Modul (.so)~~ | ~~GitHub Release, Pin auf Version~~ | **Entfaellt** — kein kompatibles Binary (A3) |
| MySQL Docker Image | `mysql:lts` (= 8.4.x) | Geaendert: 8.4 LTS statt 9.x (A4) |
| OTel Collector | Pin auf Version statt `:latest` | Zum Implementierungszeitpunkt pinnen (A9) |
| Jaeger | Pin auf Version statt `:latest` | Zum Implementierungszeitpunkt pinnen (A9) |
| PHP OTel Composer-Pakete | Bereits via Composer versioniert | + `auto-psr15` neu (A7) |

**Analysefragen:**
- Wie werden Versionen zentral verwaltet? `.env`? `versions.env`? Makefile-Variablen?
- Wie wird ein Versions-Update getestet, bevor es im Default landet?
- Welche Versionen haben gegenseitige Kompatibilitätsanforderungen?

### 3.2 Setup-Integration

**Anforderung:** `make setup` muss alle neuen Komponenten automatisch einrichten.
`git clone && make setup && make test-all` muss weiterhin funktionieren.

**Analysefragen:**
- Welche neuen Schritte kommen in `setup-webtrees.sh`?
  - Boomerang-Download?
  - Modul-Deployment nach `modules_v4/`?
- Welche neuen Container-Build-Schritte kommen in `Containerfile.webtrees`?
  - Apache-OTel-Modul installieren?
  - Apache-Config anpassen?
- Welche compose.yaml-Änderungen sind nötig?
  - MySQL-Image-Wechsel?
  - Collector-Config für HTTP-Receiver?
  - Neue Umgebungsvariablen?
- Ist die Reihenfolge der Setup-Schritte relevant?
  - MySQL muss vor Telemetry-Plugin-Aktivierung laufen
  - Apache-OTel-Modul muss vor dem ersten Request geladen sein

### 3.3 Releasefähigkeit

**Anforderung:** Die gegenwärtig erreichte Releasefähigkeit darf nicht abgeschwächt werden.

- `make test-all` muss mit und ohne OTel-Instrumentierung funktionieren
  (`OTEL_SDK_DISABLED=true` schaltet PHP-OTel ab — gilt das auch für Apache/MySQL/Boomerang?)
- Graceful Degradation: Wenn Collector nicht erreichbar → Tests laufen trotzdem?
- Keine harten Abhängigkeiten von externen Services zur Testlaufzeit.

### 3.4 Containerfile-Änderungen und Image-Größe

- Apache-OTel-Modul: Wie groß ist die `.so`-Datei?
- MySQL 9.x: Signifikant größeres Image als 8.0?
- Playwright-Container: Braucht er OTel-SDK für Node.js (Baggage-Erzeugung)?
- Caching-Strategie: Welche Layer ändern sich bei Versions-Updates?

### 3.5 SELinux (Fedora/rootless Podman)

- Neue Volumes für Boomerang-Assets, OTel-Config?
- `:z` vs `:Z` — bestehende Regeln gelten weiter.
- MySQL-Upgrade: Volume-Migration unter SELinux-Kontext?

---

## 4. Analyse-Ablauf

Die Analyse soll in folgender Reihenfolge bearbeitet werden. Jeder Abschnitt wird
nach Fertigstellung unter `docs/laufzeit_analyse/` persistiert.

| Nr. | Abschnitt | Datei | Abhängigkeiten |
|---|---|---|---|
| A1 | Boomerang + OTel Plugin: Distribution, Build, Config | `01_boomerang_rum.md` | keine |
| A2 | Boomerang Injection: Modul vs. mod_substitute | `02_injection_bewertung.md` | A1 |
| A3 | Apache httpd OTel Modul: Binary, Config, Propagation | `03_apache_otel.md` | keine |
| A4 | MySQL 9.x: Version, Telemetry Plugin, Kompatibilität | `04_mysql_telemetry.md` | keine |
| A5 | MySQL Performance Schema: Korrelation, Extraktion, Artefakte | `05_mysql_perfschema.md` | A4 |
| A6 | W3C Baggage: Playwright → Stack Propagation | `06_baggage_propagation.md` | A1, A3, A4 |
| A7 | PHP Ausbaustufe: Auto + Custom Spans | `07_php_instrumentierung.md` | keine |
| A8 | Automatisierte Auswertung: Script-Design + PerfSchema-Integration | `08_auswertung.md` | A5, A6 |
| A9 | Querschnitt: Versionen, Setup, Releasefähigkeit | `09_querschnitt.md` | A1–A8 |

**Parallelisierbar:** A1, A3, A4, A7 können unabhängig voneinander bearbeitet werden.
A2 baut auf A1 auf. A5 baut auf A4 auf. A6 integriert A1, A3, A4.
A8 integriert A5 (PerfSchema-Daten) und A6 (Baggage-Korrelation). A9 integriert alles.

---

## 5. Erwartetes Ergebnis der Analyse

Pro Abschnitt:

1. **Fakten:** Versionen, Verfügbarkeit, API-Dokumentation, bekannte Einschränkungen
2. **Bewertung:** Machbarkeit, Aufwand, Risiken
3. **Empfehlung:** Konkreter Ansatz mit Begründung
4. **Offene Punkte:** Was muss vor der Implementierung noch geklärt werden?

Nach Abschluss aller Abschnitte:
- Gesamtbewertung und Implementierungsreihenfolge
- Abhängigkeitsgraph der Implementierungsschritte
- Risikomatrix mit Mitigationsstrategien

---

## 6. Nicht im Scope

- Security-Testing-Setup (eigenes Vorhaben)
- inspectIT als Gesamtprodukt
- Produktiv-Deployment
- Metriken und Logs (nur Traces)
- Änderungen am webtrees-Upstream-Code

---

## 7. Ergebnis der Analyse (Stand 2026-03-29)

**Status: Alle 9 Analyseabschnitte (A1–A9) abgeschlossen.**

Die Analysedokumente liegen unter `docs/laufzeit_analyse/01_boomerang_rum.md` bis `09_querschnitt.md`.

### 7.1 Architekturentscheidungen — Abweichungen vom Zielzustand

Der in Abschnitt 1.4 definierte Zielzustand ist **nicht vollstaendig erreichbar**. Drei der fuenf Instrumentierungsschichten mussten angepasst werden:

| Schicht | Zielzustand (Prompt 1.4) | Ergebnis | Begruendung |
|---|---|---|---|
| Browser (RUM) | Boomerang + OTel-Plugin → Collector | **Machbar** — via mod_substitute injiziert | A1, A2 |
| Apache httpd | OTel-Modul fuer Server-Spans | **Entfaellt** — kein kompatibles Pre-built Binary fuer Debian Bookworm | A3 |
| PHP Backend | Auto + Custom Spans | **Machbar** — auto-psr15 + Custom OTel-Spans-Modul | A7 |
| MySQL Server | Telemetry Trace Plugin → Collector | **Entfaellt** — Enterprise-only, nicht in Community Edition | A4 |
| MySQL PerfSchema | (nicht im Prompt) | **Ergaenzt** — aggregierte Query-Statistiken als Kompensation | A5 |

| Aspekt | Zielzustand (Prompt) | Ergebnis |
|---|---|---|
| MySQL-Version | 9.x (Innovation) | **8.4 LTS** — Telemetry-Plugin ohnehin nicht verfuegbar, LTS bietet 8-Jahres-Support (A4) |
| Korrelation | W3C Trace Context + Baggage (durchgehend) | **W3C Baggage only** — kein durchgehender traceparent; Korrelation ueber test.run_id/test.case_id Attribute (A6) |
| Trace-Kette | Playwright → Boomerang → Apache → PHP → MySQL | **Playwright → Boomerang → Apache (transparent) → PHP → MySQL (PerfSchema)** — Apache und MySQL ohne OTel-Spans (A9) |
| Auswertung | Script mit Layer-Aufschluesselung | **Python-Script** — angepasst an tatsaechlich verfuegbare Span-Quellen (kein Apache/MySQL Layer) (A8) |

### 7.2 Implementierungsplan (Zusammenfassung aus A9)

10 Schritte in 5 Phasen, Gesamtaufwand: ~4–7 Personentage.

| Phase | Schritte | Aufwand | Kerninhalt |
|---|---|---|---|
| 1 Infrastruktur | S1–S4 | 0.5 PT | MySQL 8.4, Image-Pinning, HTTP-Receiver, auto-psr15 |
| 2 PHP-Instrumentierung | S5 | 0.5–1 PT | OTel-Spans-Modul (semantische Attribute, Baggage-Konvertierung) |
| 3 Testlauf-Korrelation | S7–S8 | 0.75 PT | Playwright Baggage-Fixture, PerfSchema-Extraktion |
| 4 Auswertung | S9–S10 | 1–2 PT | Trace-Report-Script, Makefile-Integration |
| 5 Browser-RUM | S6 | 1–2 PT | Boomerang + mod_substitute |

Kritischer Pfad: S4 → S5 → S7 → S9

### 7.3 Offene Punkte — Status

- **14 Punkte entschieden** (E1–E14, siehe A9 Abschnitt 8.1)
- **7 Punkte bei Implementierung zu verifizieren** (V1–V7, siehe A9 Abschnitt 8.2)
- **5 Punkte aufgeschoben** (S1–S5, siehe A9 Abschnitt 8.3)
- **0 Punkte blockierend offen**

### 7.4 Abnahmekriterien

Die Implementierung gilt als abgeschlossen, wenn folgende Kriterien erfuellt sind:

1. **Jaeger UI zeigt Spans:** PHP-Spans (auto-psr15, auto-pdo, Custom OTel-Spans-Modul) und Browser-Spans (Boomerang) sind in der Jaeger-Oberflaeche sichtbar und inspizierbar.
2. **Korrelationskette funktioniert:** Browser-Span und PHP-Spans gehoeren zur selben Trace (ueber W3C Baggage `test.run_id` korreliert). Die Span-Hierarchie ist korrekt: Request-Root-Span → Custom-Span → DB-Spans.
3. **Testfallzuordnung funktioniert:** Jeder Playwright-Testfall setzt `test.run_id` und `test.case_id` als Baggage-Header. Diese Werte erscheinen als Span-Attribute in Jaeger und ermoeglichen die Filterung nach Testfall.
4. **Auswertung moeglich:** Das Trace-Report-Script (`trace-report.py`) kann die OTLP-NDJSON-Daten parsen, Spans nach Testfall gruppieren und eine Layer-Aufschluesselung (Browser, PHP, DB) ausgeben. PerfSchema-Daten ergaenzen den Report um MySQL-seitige Statistiken.

### 7.5 Scope

Alle 5 Phasen des Implementierungsplans (S1–S10) sind im Scope:
- Phase 1: Infrastruktur (S1–S4)
- Phase 2: PHP-Instrumentierung (S5)
- Phase 3: Testlauf-Korrelation (S7–S8)
- Phase 4: Auswertung (S9–S10)
- Phase 5: Browser-RUM (S6)

Der Planprompt darf keine Git-Strategie (Commits, Feature-Branches) vorschreiben.

### 7.6 Naechster Schritt

Aus diesem Analyseprompt und den Ergebnissen in A1–A9 kann ein **Implementierungs-Planprompt** erstellt werden. Alle Architekturentscheidungen sind getroffen, alle blockierenden Fragen beantwortet.
