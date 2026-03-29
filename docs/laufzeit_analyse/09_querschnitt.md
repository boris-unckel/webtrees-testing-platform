<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# A9: Querschnitt — Versionen, Setup, Releasefaehigkeit

## 1. Fakten

### 1.1 Gesicherter Architekturzustand (Synthese A1–A8)

Die Analysen A1 bis A8 haben den technisch erreichbaren Zielzustand vollstaendig geklaert. Drei der fuenf urspruenglich anvisierten Instrumentierungsschichten sind nicht realisierbar:

| Schicht | Urspruengliches Ziel | Ergebnis | Referenz |
|---|---|---|---|
| Browser (RUM) | Boomerang + OTel-Plugin → Collector | **Machbar** | A1, A2 |
| Apache httpd | OTel-Modul fuer Server-Spans | **Nicht machbar** — kein kompatibles Pre-built Binary | A3 |
| PHP Backend | Auto-Instrumentation + Custom Spans | **Machbar** — primaer instrumentierter Service | A7 |
| MySQL Server | Telemetry Trace Plugin → Collector | **Nicht machbar** — Enterprise-only | A4 |
| MySQL PerfSchema | Aggregierte Query-Statistiken | **Machbar** — als separater Extraktionsschritt | A5 |

**Korrelationsmechanismus:** W3C Baggage (`test.run_id`, `test.case_id`) statt vollstaendigem Trace-Context-Propagation ueber alle Schichten. Kein Playwright OTel SDK in der initialen Implementierung (A6, Abschnitt 3.1). Option A (Playwright Root-Span mit vierstufiger Trace-Kette: Playwright-Span → Boomerang-Span → PHP-Span → DB-Spans) ist als systematisch korrekte Ausbaustufe anerkannt (A6, Abschnitt 1.3/2.6). Komplementaer: `BOOMR.addVar()` traegt die Test-Korrelation in den Boomerang-Beacons unabhaengig von der OTel-Trace-Hierarchie (A6, Abschnitt 1.4).

**Auswertung:** Python-basiertes Trace-Report-Script mit OTLP-NDJSON-Parsing und optionaler PerfSchema-Integration (A8, Abschnitt 3.1).

### 1.2 Erreichbare Trace-Architektur

```
Playwright (Layer 4/5)
  |
  |-- setExtraHTTPHeaders({baggage: test.run_id=X, test.case_id=Y})
  |
  v
Browser (Boomerang + OTel-Plugin)
  |-- Document Load, XHR, Fetch Spans → OTLP/HTTP → Collector:4318
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
  |-- OTel-Spans-Modul: Baggage → Span-Attribute (test.run_id, test.case_id)
  |-- Spans → gRPC → Collector:4317
  |
  v
MySQL 8.4 LTS (KEINE Server-Spans)
  |-- Performance Schema: Aggregierte Query-Statistiken (Default ON)
  |-- Extraktion per Bash-Script am Testlauf-Ende
  |
  v
OTel Collector (Contrib)
  |-- Empfaengt: gRPC (PHP, :4317) + HTTP (Boomerang, :4318)
  |-- Exportiert: Jaeger (OTLP) + File (/artifacts/traces.json)
  |
  v
Jaeger (All-in-One)                    traces.json (NDJSON)
  |-- UI: http://localhost:16686         |-- Trace-Report-Script (Python)
  |-- API: /api/traces?tags=...          |-- PerfSchema-Integration
```

### 1.3 Lueckenanalyse gegenueber dem Analyseprompt

Der Analyseprompt (Abschnitt 1.4) definierte als Zielzustand eine durchgehende Korrelation ueber alle fuenf Schichten. Die gesicherten Ergebnisse zeigen folgende Luecken:

| Luecke | Ursache | Auswirkung | Kompensation |
|---|---|---|---|
| Kein Apache-Server-Span | Kein Pre-built Binary fuer Debian Bookworm (A3, Abschnitt 2.1) | Keine Sichtbarkeit von Apache-interner Verarbeitungszeit (mod_rewrite, Output-Filter) | Vernachlaessigbar — Apache-Overhead < 1ms fuer typische Requests (A3, Abschnitt 2.4) |
| Kein MySQL-Server-Span | Enterprise-only Plugin (A4, Abschnitt 2.3.2) | Keine serverseitigen Query-Ausfuehrungsdetails in Traces | PerfSchema liefert aggregierte Statistiken (A5); PDO-Spans liefern Query-Dauer PHP-seitig |
| Keine End-to-End Trace-ID-Propagation | Apache transparent, MySQL nicht propagationsfaehig | Separate Traces statt einer durchgehenden Trace-Kette | Baggage-Korrelation ueber `test.run_id` (A6); Ausbaustufe Option A (Playwright Root-Span) wuerde kausale Trace-Kette ueber Browser und PHP herstellen (A6, Abschnitt 1.3/2.6) |
| Boomerang-Spans nicht testfallkorreliert | Kein `test.run_id`-Attribut in Browser-Spans (A6, Abschnitt 2.5) | Browser-Spans nur ueber Zeitfenster zuordenbar | Temporale Korrelation bei `workers: 1` (A8, Abschnitt 1.3); komplementaer: `BOOMR.addVar()` traegt Test-Korrelation in Beacons (A6, Abschnitt 1.4); Option A wuerde Boomerang-Spans via Server-Timing in den Playwright-Trace einbinden |

**Bewertung:** Die Luecken sind fuer eine Testing-Plattform akzeptabel. Die PHP-Schicht ist der primaere Instrumentierungspunkt — dort fallen > 95% der messbaren Verarbeitungszeit an. Apache und MySQL sind entweder transparent (Apache) oder ueber Hilfsmechanismen (PerfSchema) abgedeckt.

### 1.4 Komponentenversionen (Ist-Zustand und Ziel-Zustand)

| Komponente | Ist-Zustand | Ziel-Zustand | Aenderung |
|---|---|---|---|
| MySQL | `mysql:8.0` | `mysql:lts` (= 8.4.x) | Image-Tag aendern (A4, Abschnitt 3) |
| OTel Collector | `otel/opentelemetry-collector-contrib:latest` | Gepinnte Version (z.B. `0.120.0`) | Image-Tag aendern (A1, Abschnitt 4.1.7) |
| Jaeger | `jaegertracing/all-in-one:latest` | Gepinnte Version (z.B. `1.66`) | Image-Tag aendern |
| Boomerang | nicht vorhanden | `boomerangjs@1.815.1` (npm) | Neu (A1, Abschnitt 3) |
| Boomerang OTel-Plugin | nicht vorhanden | `2.0.0-2` (GitHub Release) | Neu (A1, Abschnitt 3) |
| PHP auto-psr15 | nicht vorhanden | `opentelemetry-auto-psr15` (Composer) | Neu (A7, Abschnitt 3) |
| PHP auto-pdo | installiert | unveraendert | — |
| PHP auto-psr18 | installiert | unveraendert | — |
| PHP OTel-Spans-Modul | nicht vorhanden | Custom Modul in `modules_v4/` | Neu (A7, Abschnitt 2.2) |
| Apache mod_substitute | nicht aktiviert | Aktiviert fuer Boomerang-Injection | Containerfile-Aenderung (A2, Abschnitt 3.2) |

### 1.5 Versionierung und Reproduzierbarkeit (Prompt 3.1)

#### Zentrale Versionsverwaltung

**Empfehlung: Kombinierter Ansatz — `compose.yaml` fuer Container-Images, `Containerfile.webtrees` fuer Build-Artefakte, `setup-webtrees.sh` fuer Composer-Pakete.**

| Versionstyp | Verwaltungsort | Beispiel |
|---|---|---|
| Container-Image-Tags | `compose.yaml` | `mysql:lts`, `otel/opentelemetry-collector-contrib:0.120.0` |
| Boomerang/OTel-Plugin-Version | `Containerfile.webtrees` (als ARG) | `ARG BOOMERANG_VERSION=1.815.1` |
| PHP OTel Composer-Pakete | `setup-webtrees.sh` | `composer require opentelemetry-auto-psr15` |
| Apache-Module | `Containerfile.webtrees` | `a2enmod rewrite substitute` |

Eine separate `versions.env`-Datei waere eine Alternative, erzeugt aber eine zusaetzliche Indirektion. Die Image-Tags direkt in `compose.yaml` und Build-Args direkt im `Containerfile.webtrees` sind transparenter und erfordern keine zusaetzliche Datei.

#### Versionspinning-Strategie

1. **Container-Images:** Auf Minor-Version pinnen (z.B. `mysql:8.4`, `otel/opentelemetry-collector-contrib:0.120.0`). Patch-Updates innerhalb der Minor-Version werden automatisch bei `make up --build` bezogen.

2. **JS-Artefakte (Boomerang):** Auf exakte Version pinnen mit SHA256-Checksum-Verifikation im Containerfile-Build. Kein `latest`-Tag.

3. **Composer-Pakete:** Ueber `composer require` mit automatischer Aufloesung. Die `composer.lock` im Vendor-Volume sperrt die exakten Versionen fuer den Testlauf.

#### Versions-Updates testen

Bevor eine neue Version zum Default wird:

1. In `compose.yaml` / `Containerfile.webtrees` die neue Version setzen
2. `make clean && make up && make setup && make test-all` ausfuehren
3. Bei Erfolg: Commit mit der Versionsaenderung
4. Bei Fehlern: Root-Cause analysieren, ggf. auf bisherige Version zurueckrollen

Kein separater "Staging"-Mechanismus noetig — der gesamte Stack ist lokal und wegwerfbar (`make clean`).

#### Komponentenuebergreifende Kompatibilitaet

| Abhaengigkeit | Anforderung | Quelle |
|---|---|---|
| PHP OTel SDK ↔ ext-opentelemetry | SDK-Version muss zur Extension passen | Composer-Constraints |
| OTel Collector ↔ OTLP-Protokollversion | Collector muss OTLP-Version des PHP-SDK unterstuetzen | Praktisch immer kompatibel (Semantic Versioning) |
| Boomerang OTel-Plugin ↔ OTel Collector | Plugin sendet OTLP/HTTP JSON; Collector muss HTTP-Receiver haben | Collector-Config (A1, Abschnitt 1.3) |
| MySQL 8.4 ↔ PHP PDO/mysqlnd | `caching_sha2_password` Support | PHP 8.x mysqlnd unterstuetzt dies (A4, Abschnitt 2.3.1) |
| Boomerang ↔ Boomerang OTel-Plugin | Plugin 2.0.0-2 ist kompatibel mit Boomerang 1.815.1 | Getestet (A1, Abschnitt 1.2) |

### 1.6 Setup-Integration (Prompt 3.2)

#### Neue Schritte in `setup-webtrees.sh`

```
Bestehend:                              Neu:
[0/4] tests/data seeden                 (unveraendert)
[1/4] composer install                  (unveraendert)
[1b/4] OTel Auto-Instrumentation        → + opentelemetry-auto-psr15 (A7)
[2/4] Warte auf MySQL                   (unveraendert)
[3/4] config.ini.php generieren         (unveraendert)
[4/4] DB-Migration, Admin, Import       (unveraendert)
```

**Einzige Aenderung:** In Schritt `[1b/4]` wird `open-telemetry/opentelemetry-auto-psr15` zur `composer require`-Liste hinzugefuegt. Alle anderen Setup-Schritte bleiben identisch.

Das OTel-Spans-Modul wird NICHT ueber `setup-webtrees.sh` installiert, sondern per Volume-Mount in `compose.yaml` bereitgestellt (analog zum bestehenden `MODULE_PATH`-Mechanismus).

#### Neue Container-Build-Schritte in `Containerfile.webtrees`

```dockerfile
# Bestehend:
RUN a2enmod rewrite

# Neu: mod_substitute fuer Boomerang-Injection (A2)
RUN a2enmod rewrite substitute

# Neu: Boomerang + OTel-Plugin installieren (A1, A2)
ARG BOOMERANG_VERSION=1.815.1
ARG BOOMERANG_OTEL_VERSION=2.0.0-2

RUN mkdir -p /opt/rum/plugins \
    && cd /tmp \
    && npm pack boomerangjs@${BOOMERANG_VERSION} \
    && tar xzf boomerangjs-*.tgz \
    && cp package/boomerang.js /opt/rum/ \
    && cp package/plugins/rt.js /opt/rum/plugins/ \
    && cp package/plugins/navtiming.js /opt/rum/plugins/ \
    && cp package/plugins/restiming.js /opt/rum/plugins/ \
    && cp package/plugins/painttiming.js /opt/rum/plugins/ \
    && cp package/plugins/eventtiming.js /opt/rum/plugins/ \
    && rm -rf /tmp/boomerangjs-* /tmp/package

RUN curl -fSL -o /opt/rum/boomerang-opentelemetry.js \
    "https://github.com/inspectIT/boomerang-opentelemetry-plugin/releases/download/${BOOMERANG_OTEL_VERSION}/boomerang-opentelemetry.js"

# Neu: Boomerang-Initialisierung und Apache-Config (A2)
COPY otel/boomerang-init.js /opt/rum/
COPY otel/boomerang-apache.conf /etc/apache2/conf-available/boomerang.conf
RUN a2enconf boomerang
```

**Reihenfolge im Containerfile:**
1. System-Pakete und PHP-Extensions (bestehend)
2. OTel Extension (bestehend)
3. Apache-Module: `rewrite` + `substitute` (erweitert)
4. Boomerang-Installation (neu)
5. Apache VirtualHost-Config (bestehend)
6. Composer (bestehend)
7. PHP-Config (bestehend)
8. Setup-Script (bestehend)

Die neuen Schritte kommen zwischen die Apache-Modul-Aktivierung und die VirtualHost-Config.

#### Aenderungen in `compose.yaml`

```yaml
# 1. MySQL-Version aendern (A4)
mysql:
  image: docker.io/library/mysql:lts    # war: mysql:8.0

# 2. MySQL PerfSchema Stages aktivieren (A5)
  command: >
    --character-set-server=utf8mb4
    --collation-server=utf8mb4_bin
    --performance-schema-instrument='stage/%=ON'
    --performance-schema-consumer-events-stages-current=ON
    --performance-schema-consumer-events-stages-history=ON

# 3. OTel Collector HTTP-Receiver fuer Browser-Traces (A1)
otel-collector:
  image: docker.io/otel/opentelemetry-collector-contrib:0.120.0  # war: :latest
  ports:
    - "4317:4317"
    - "4318:4318"     # Neu: OTLP/HTTP fuer Boomerang

# 4. Jaeger pinnen
jaeger:
  image: docker.io/jaegertracing/all-in-one:1.66  # war: :latest

# 5. OTel-Spans-Modul mounten (A7)
webtrees:
  volumes:
    # ... bestehende Volumes ...
    - ./modules/otel-spans:/var/www/html/modules_v4/otel_spans:ro,z  # Neu

# 6. mysql-security: Version angleichen (A4)
mysql-security:
  image: docker.io/library/mysql:lts    # war: mysql:8.0
```

#### Collector-Config erweitern (`otel/otel-collector-config.yaml`)

```yaml
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317
      http:                          # Neu: fuer Boomerang Browser-Traces
        endpoint: 0.0.0.0:4318
        cors:
          allowed_origins:
            - "http://localhost:8080"
            - "http://webtrees:80"
          allowed_headers:
            - "*"
          max_age: 7200
```

#### Reihenfolge-Relevanz

Die Reihenfolge der Setup-Schritte ist relevant:

1. **Container-Build** (`Containerfile.webtrees`): Apache-Module und Boomerang muessen vor dem ersten Request verfuegbar sein — das ist durch den Build-Prozess garantiert.

2. **`make up`**: MySQL muss vor `make setup` gesund sein (Healthcheck).

3. **`make setup`**: Composer-Install (inkl. auto-psr15) muss vor dem ersten Test abgeschlossen sein. Das OTel-Spans-Modul ist per Volume-Mount sofort verfuegbar und braucht keinen separaten Setup-Schritt.

4. **OTel Collector**: Muss vor dem ersten PHP-Request laufen (sonst gehen Spans verloren). Ist durch `compose.yaml` Service-Definition garantiert (kein `depends_on` noetig, da gRPC-Exporter Verbindungsfehler toleriert).

### 1.7 Releasefaehigkeit (Prompt 3.3)

#### `make test-all` mit und ohne OTel

**Anforderung:** `make test-all` muss sowohl mit `OTEL_SDK_DISABLED=false` (Default) als auch mit `OTEL_SDK_DISABLED=true` fehlerfrei laufen.

**Bestehende Absicherung:**
- `setup-webtrees.sh` ueberspringt die OTel-Composer-Pakete, wenn `OTEL_SDK_DISABLED=true` (Zeile 46)
- PHP OTel Auto-Instrumentation wird durch `OTEL_PHP_AUTOLOAD_ENABLED` gesteuert

**Neue Komponenten und ihre Deaktivierbarkeit:**

| Komponente | Bei `OTEL_SDK_DISABLED=true` | Mechanismus |
|---|---|---|
| `opentelemetry-auto-psr15` | Nicht installiert (Composer-Skip) | Bestehende Bedingung in setup-webtrees.sh |
| OTel-Spans-Modul (`modules_v4/otel_spans/`) | NoOp-Tracer via OTel API | `Globals::tracerProvider()` liefert NoOp wenn SDK nicht geladen (A7, Abschnitt 2.2.3) |
| Boomerang + OTel-Plugin | Senden an Collector:4318 | Wenn Collector nicht laeuft: Browser-Konsolen-Fehler, aber kein Test-Abbruch |
| mod_substitute | Injiziert immer (statische Apache-Config) | Boomerang-Scripts werden geladen, OTel-Plugin sendet ins Leere |
| PerfSchema-Extraktion | Unabhaengig von OTel | Script laeuft gegen MySQL, nicht gegen Collector |

**Kritischer Punkt: Boomerang bei `OTEL_SDK_DISABLED=true`.**
Wenn der OTel Collector nicht laeuft, schlaegt das `fetch()` an `http://localhost:4318/v1/traces` im Browser fehl. Das erzeugt Konsolen-Fehler, bricht aber weder den Seitenaufbau noch die Tests ab. Die `collectorConfiguration.url` im Boomerang-Init zeigt auf den Collector-Container, der standardmaessig laeuft. Nur wenn der gesamte OTel-Stack deaktiviert waere (Collector + Jaeger nicht gestartet), gaebe es Netzwerkfehler.

**Empfehlung:** Boomerang-Injection per Environment-Variable steuerbar machen. In der `boomerang-apache.conf` eine Bedingung einbauen:

```apache
# Boomerang-Injection nur wenn OTEL_SDK_DISABLED != true
<If "-T env('OTEL_SDK_DISABLED') != true">
    AddOutputFilterByType SUBSTITUTE text/html
    Substitute "s|</head>|...|i"
</If>
```

Alternativ (einfacher): Die Apache-`<If>`-Direktive erfordert `mod_expr`, das standardmaessig geladen ist. Die Umgebungsvariable `OTEL_SDK_DISABLED` muss per `PassEnv` im Apache-Config verfuegbar gemacht werden.

#### Graceful Degradation bei nicht erreichbarem Collector

| Situation | Verhalten |
|---|---|
| Collector nicht erreichbar (PHP gRPC) | gRPC-Exporter loggt Warning, Spans gehen verloren, Tests laufen weiter |
| Collector nicht erreichbar (Boomerang HTTP) | `fetch()` schlaegt fehl, Browser-Konsolen-Fehler, Seite funktioniert |
| Jaeger nicht erreichbar | Collector kann Spans nicht exportieren, loggt Warning, Tests laufen weiter |
| MySQL PerfSchema nicht extrahierbar | Extraktions-Script liefert Fehler, Tests sind nicht betroffen |

**Keine harten Abhaengigkeiten von externen Services zur Testlaufzeit.** Alle OTel-Komponenten sind fire-and-forget. Testausfuehrung und Testergebnisse haengen nicht von der Trace-Pipeline ab.

### 1.8 Containerfile-Aenderungen und Image-Groesse (Prompt 3.4)

| Neue Komponente | Geschaetzte Groesse | Caching-Impact |
|---|---|---|
| `a2enmod substitute` | < 100 KB (.so bereits im Base-Image) | Aendert bestehenden Layer |
| Boomerang JS (boomerang.js + 5 Plugins) | ~200 KB (unminified) | Neuer Layer (ARG-basiert, nur bei Version-Update rebuilt) |
| Boomerang OTel-Plugin (minified) | 639 KB | Neuer Layer |
| `boomerang-init.js` | < 1 KB | Neuer Layer (COPY) |
| `boomerang-apache.conf` | < 1 KB | Neuer Layer (COPY) |
| **Gesamt** | **~850 KB** | Minimal |

**MySQL-Image-Groesse:** `mysql:8.0` und `mysql:lts` (8.4) sind praktisch gleich gross (~600 MB). Kein signifikanter Unterschied.

**Caching-Strategie:**
- Boomerang und OTel-Plugin werden per `ARG` versioniert. Bei einem Versions-Update aendert sich nur der betroffene Layer.
- Die groessten Layer (apt-get, PHP-Extensions, Composer-Basis-Install) aendern sich nicht bei OTel-Updates.
- `npm pack` erfordert keine npm-Installation im finalen Image — es wird nur im Build verwendet. **Alternativ:** `curl` statt `npm pack` fuer den Boomerang-Download, um die npm-Runtime-Abhaengigkeit im Build zu vermeiden. Das npm-Registry-Tarball-URL-Format ist stabil: `https://registry.npmjs.org/boomerangjs/-/boomerangjs-${VERSION}.tgz`.

### 1.9 SELinux (Fedora/rootless Podman) (Prompt 3.5)

#### Neue Volumes

| Volume/Mount | Typ | SELinux-Label | Begruendung |
|---|---|---|---|
| `./modules/otel-spans:/var/www/html/modules_v4/otel_spans:ro,z` | Bind-Mount | `:z` (shared) | Read-only, analog zum bestehenden `MODULE_PATH`-Pattern. Shared Label (`:z`), weil der webtrees-Source-Mount ebenfalls `:z` verwendet |
| `./otel/boomerang-init.js` | COPY im Containerfile | Kein Volume | Im Image enthalten, kein SELinux-Relevanz |
| `./otel/boomerang-apache.conf` | COPY im Containerfile | Kein Volume | Im Image enthalten |
| `/opt/rum/` | Im Containerfile erstellt | Kein Volume | Im Image enthalten |

**Keine neuen `:Z`-Mounts.** Alle neuen Bind-Mounts verwenden `:z` (shared Label), konsistent mit den bestehenden Mounts in `compose.yaml`. Die SELinux-Warnung aus `CLAUDE.md` (kein `:Z` auf Verzeichnisse, die der Compose-Stack gleichzeitig mountet) bleibt relevant.

#### MySQL-Upgrade Volume-Migration

Bei einem Wechsel von `mysql:8.0` auf `mysql:lts` (8.4) muss das `mysql-data`-Volume geloescht und neu erstellt werden:

```bash
make clean    # Loescht Volumes (inkl. mysql-data)
make up       # Erstellt neues Volume mit MySQL 8.4
make setup    # Initialisiert DB neu
```

**SELinux-spezifisch:** Kein Unterschied — `mysql-data` ist ein Named Volume (kein Bind-Mount), daher kein SELinux-Label-Konflikt. `make clean` (`podman-compose down -v`) entfernt das Volume vollstaendig.

Fuer `mysql-security-data` gilt dasselbe: `security-clean` Target loescht das Volume.

---

## 2. Gesamtbewertung

### 2.1 Technische Machbarkeit

Von den fuenf urspruenglich anvisierten Instrumentierungsschichten sind **drei realisierbar** (Browser-RUM, PHP-Backend, PerfSchema-Extraktion) und **zwei nicht realisierbar** (Apache OTel-Modul, MySQL Telemetry Plugin).

**Die drei machbaren Schichten decken den wesentlichen Observability-Bedarf ab.** Die PHP-Schicht ist der primaere Instrumentierungspunkt, in dem > 95% der messbaren Server-Verarbeitungszeit anfallen. Browser-RUM liefert Client-seitige Metriken (Navigation Timing, Resource Timing, Paint Timing). PerfSchema liefert MySQL-seitige Aggregatstatistiken (Query-Profile, Table I/O, Full Scans).

### 2.2 Vereinfachte Architektur gegenueber dem Zielzustand

**Urspruenglich anvisiert (Prompt 1.4):**
```
Playwright → Boomerang → Apache (OTel) → PHP (OTel) → MySQL (Telemetry)
    korreliert via W3C Trace Context + Baggage
```

**Tatsaechlich erreichbar (initiale Implementierung):**
```
Playwright → Boomerang → Apache (transparent) → PHP (OTel) → MySQL (PerfSchema)
    korreliert via W3C Baggage (test.run_id, test.case_id)
    + BOOMR.addVar() fuer Beacon-Korrelation (komplementaerer Kanal)
    PerfSchema: Aggregat-Korrelation (Zeitfenster + Digest-Text)
```

**Erreichbar als Ausbaustufe (Option A, A6 Abschnitt 1.3/2.6):**
```
Playwright (OTel SDK) → Boomerang (via Server-Timing) → PHP (OTel) → DB-Spans
    korreliert via traceparent (kausale Parent-Child-Beziehung)
    + W3C Baggage (test.run_id, test.case_id) bleibt als Fundament
    + BOOMR.addVar() fuer Beacon-Korrelation (unabhaengig von OTel)
```

Die initiale Vereinfachung reduziert die Komplexitaet erheblich: kein Apache-Binary-Management, kein Enterprise-Lizenz-Problem, kein Playwright-OTel-SDK. Die Korrelation ueber Baggage statt vollstaendiger Trace-Hierarchie ist fuer den initialen Einsatz als Testing-Plattform ausreichend.

Option A (Playwright Root-Span) ist als systematisch korrekte Ausbaustufe anerkannt: Die vierstufige Trace-Kette (Playwright-Span → Boomerang-Span → PHP-Span → DB-Spans) bildet die tatsaechliche Kausalitaet ab und wird relevant, wenn die Trace-Analyse request-uebergreifende Latenz-Attribution erfordert (A6, Abschnitt 2.6). Die Baggage-Infrastruktur ist so konzipiert, dass ein spaeterer Umstieg auf Option A keine bestehende Funktionalitaet umbaut — die `traceparent`-Kette kommt als zusaetzliche Schicht hinzu.

### 2.3 Aufwand-Nutzen-Bewertung

| Komponente | Aufwand | Nutzen | Bewertung |
|---|---|---|---|
| MySQL 8.0 → 8.4 LTS | 0.25 PT | LTS-Support bis 2032, Security-Patches | **Empfohlen** — triviale Aenderung, hoher Langzeitnutzen |
| `auto-psr15` installieren | 0.1 PT | Request-Root-Spans, vollstaendige Trace-Hierarchie | **Empfohlen** — minimaler Aufwand, hoher Nutzen |
| Boomerang + mod_substitute | 1–2 PT | Browser-RUM-Daten, Client-seitige Performance-Sichtbarkeit | **Empfohlen** — mittlerer Aufwand, mittlerer Nutzen |
| OTel-Spans-Modul | 0.5–1 PT | Semantische Attribute (action, tree, xref), Baggage-Konvertierung | **Empfohlen** — moderater Aufwand, hoher Nutzen fuer Auswertung |
| PerfSchema-Extraktion | 0.5 PT | MySQL-Query-Profile, Full-Scan-Warnungen | **Empfohlen** — geringer Aufwand, ergaenzender Nutzen |
| Trace-Report-Script | 1–2 PT | Automatisierte Testlauf-Auswertung | **Empfohlen** — kernziel der gesamten Initiative |
| Collector/Jaeger Version-Pinning | 0.1 PT | Reproduzierbarkeit | **Empfohlen** — trivial |
| Playwright Baggage-Fixture | 0.25 PT | Testlauf/Testfall-Korrelation | **Empfohlen** — Voraussetzung fuer Trace-Report |

**Gesamtaufwand:** ~4–7 Personentage fuer die vollstaendige Implementierung aller empfohlenen Komponenten.

---

## 3. Implementierungsreihenfolge

### 3.1 Geordnete Implementierungsschritte

| Schritt | Beschreibung | Aenderungen | Aufwand | Risiko | Abhaengigkeit |
|---|---|---|---|---|---|
| **S1** | MySQL 8.0 → 8.4 LTS | `compose.yaml`: Image-Tag | 0.25 PT | Niedrig | Keine |
| **S2** | Container-Image-Versionen pinnen | `compose.yaml`: Collector + Jaeger Tags | 0.1 PT | Niedrig | Keine |
| **S3** | OTel Collector HTTP-Receiver | `otel-collector-config.yaml`: HTTP + CORS; `compose.yaml`: Port 4318 | 0.25 PT | Niedrig | Keine |
| **S4** | `auto-psr15` installieren | `setup-webtrees.sh`: Composer-require erweitern | 0.1 PT | Niedrig | Keine |
| **S5** | OTel-Spans-Modul entwickeln | `modules/otel-spans/`: PHP-Modul mit MiddlewareInterface | 0.5–1 PT | Mittel | S4 (auto-psr15 als Parent-Span) |
| **S6** | Boomerang + mod_substitute | `Containerfile.webtrees`: Build-Schritte; `otel/`: Config-Dateien | 1–2 PT | Mittel | S3 (HTTP-Receiver) |
| **S7** | Playwright Baggage-Fixture | `layer4-e2e/helpers/otel-fixture.ts` | 0.25 PT | Niedrig | S5 (OTel-Spans-Modul fuer Baggage-zu-Attribute) |
| **S8** | PerfSchema-Extraktion | `scripts/extract-perfschema.sh`, `scripts/truncate-perfschema.sh`; `Makefile`: Targets | 0.5 PT | Niedrig | S1 (MySQL 8.4) |
| **S9** | Trace-Report-Script | `scripts/trace-report.py`, `scripts/trace-report.sh`; `Makefile`: Target | 1–2 PT | Niedrig | S5, S7 (Spans mit Attributen) |
| **S10** | Makefile-Integration | Test-Targets um RUN_ID, PerfSchema, Report erweitern | 0.25 PT | Niedrig | S8, S9 |

### 3.2 Kritischer Pfad

Der kritische Pfad bestimmt die minimale Durchlaufzeit:

```
S4 (auto-psr15) → S5 (OTel-Spans-Modul) → S7 (Baggage-Fixture) → S9 (Trace-Report)
```

Alle anderen Schritte koennen parallel oder unabhaengig erfolgen:
- S1 (MySQL), S2 (Pinning), S3 (HTTP-Receiver) sind voneinander unabhaengig
- S6 (Boomerang) benoetigt S3, kann aber parallel zu S5 laufen
- S8 (PerfSchema) benoetigt S1, kann parallel zu allem anderen laufen

### 3.3 Empfohlene Phasen

**Phase 1 — Infrastruktur-Basis (S1, S2, S3, S4):**
Alle vier Schritte sind unabhaengig und koennen in einem einzigen Commit umgesetzt werden. Danach ist die Grundlage fuer alle weiteren Schritte gelegt. Aufwand: ~0.5 PT.

**Phase 2 — PHP-Instrumentierung (S5):**
Das OTel-Spans-Modul ist die wertvollste Einzelkomponente. Es liefert semantische Attribute und die Baggage-zu-Span-Attribut-Konvertierung. Aufwand: 0.5–1 PT.

**Phase 3 — Testlauf-Korrelation (S7, S8):**
Playwright Baggage-Fixture und PerfSchema-Extraktion. Beide sind Voraussetzung fuer den vollstaendigen Trace-Report. Aufwand: ~0.75 PT.

**Phase 4 — Auswertung (S9, S10):**
Trace-Report-Script und Makefile-Integration. Das Kernziel der gesamten Initiative. Aufwand: 1–2 PT.

**Phase 5 — Browser-RUM (S6):**
Boomerang-Integration. Kann unabhaengig von Phase 2–4 erfolgen, liefert aber den meisten Wert in Kombination mit dem Trace-Report (Phase 3 des Report-Scripts, A8 Abschnitt 3.7). Aufwand: 1–2 PT.

---

## 4. Abhaengigkeitsgraph

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

**Legende:**
- Vertikale Linien: direkte Abhaengigkeit (oberer Schritt muss vor unterem abgeschlossen sein)
- `\`: abzweigende Abhaengigkeit

**Parallelisierbare Schritte:**
- S1, S2, S3, S4 koennen alle gleichzeitig umgesetzt werden
- S5 und S6 koennen parallel laufen (S5 braucht S4, S6 braucht S3)
- S7 und S8 koennen parallel laufen (S7 braucht S5, S8 braucht S1)
- S9 braucht S7 und S8 (beide muessen abgeschlossen sein)
- S10 braucht S9 (und optional S8 fuer PerfSchema-Targets)

**Blockierende Schritte:**
- **S5 (OTel-Spans-Modul)** blockiert S7 und S9 — das Modul ist die zentrale Komponente fuer die Baggage-zu-Span-Attribut-Konvertierung
- **S4 (auto-psr15)** blockiert S5 — ohne Request-Root-Span fehlt die Trace-Hierarchie

---

## 5. Risikomatrix

### 5.1 Konsolidierte Risiken aus A1–A8

| # | Risiko | Quelle | Schwere | Wahrscheinlichkeit | Mitigation |
|---|---|---|---|---|---|
| R1 | **Boomerang hat kein fertiges Bundle** — Grunt-Build oder synchrones Multi-File-Loading noetig | A1, 2.3 | Mittel | Sicher | Synchrones Laden der Roh-JS-Dateien aus npm (kein Build noetig); oder `npm pack`-Tarball im Containerfile (A1, Abschnitt 3) |
| R2 | **CORS-Probleme** Browser → OTel Collector fuer OTLP/HTTP | A1, 2.3 | Mittel | Hoch | CORS-Config im Collector HTTP-Receiver; Origin-Whitelist fuer `localhost:8080` und `webtrees:80` (A1, Abschnitt 1.3) |
| R3 | **Boomerang OTel-Plugin geringe Community** (10 Sterne) | A1, 2.3 | Mittel | Moeglich | Funktional ausgereift; OTel JS SDK dahinter ist stabil; Plugin-Version gepinnt |
| R4 | **zone.js-Seiteneffekte** durch Boomerang OTel-Plugin | A1, 2.3 | Niedrig | Niedrig | webtrees ist kein SPA; jQuery-basiert; zone.js-Patches sollten harmlos sein |
| R5 | **mod_substitute fragile Config-Syntax** — 8 Script-Tags in einer Apache-Directive | A2, 2.3 | Niedrig | Moeglich | Init-Script in externe Datei auslagern; reduziert auf einen Script-Tag fuer `boomerang-init.js` |
| R6 | **Apache ABI-Inkompatibilitaet** (falls Apache OTel-Modul doch versucht wuerde) | A3, 2.1 | Hoch | Hoch | Apache OTel-Modul wird NICHT verwendet — Risiko eliminiert durch Architekturentscheidung |
| R7 | **MySQL Telemetry Enterprise-only** | A4, 2.3.2 | Hoch (Showstopper) | Sicher | MySQL Telemetry wird NICHT verwendet; PerfSchema als Alternative (A5) |
| R8 | **MySQL 8.4 Healthcheck-Kompatibilitaet** mit `caching_sha2_password` | A4, 4.1 | Mittel | Niedrig | Bestehender Healthcheck verwendet `mysqladmin ping` mit Passwort — kompatibel mit 8.4 |
| R9 | **Root-Zugriff fuer PerfSchema TRUNCATE** | A5, 2 | Niedrig | Sicher | Root-Passwort in compose.yaml als ENV — im Testkontext akzeptabel |
| R10 | **PerfSchema-Daten gehen bei Container-Neustart verloren** | A5, 2 | Mittel | Sicher | Extraktion muss VOR `make down` erfolgen; Dokumentation in Makefile-Targets |
| R11 | **BaggagePropagator nicht aktiv in PHP** | A6, 2.5 | Hoch | Niedrig | Default ist `tracecontext,baggage`; im Container verifizieren; bei Bedarf explizit `OTEL_PROPAGATORS` setzen |
| R12 | **Percent-Encoding-Roundtrip fuer test.case_id** | A6, 4.1 | Mittel | Mittel | OTel-Spans-Modul muss `urldecode()` anwenden; Tests mit Sonderzeichen in Testfall-Titeln |
| R13 | **Upstream-Aenderung der Route-Namen** in webtrees | A7, 2.2.3 | Mittel | Niedrig | Route-Namen sind FQCN — aendern sich nur bei Klassen-Rename; ungemappte Routen werden ignoriert |
| R14 | **Doppelte Spans durch auto-psr15 + OTel-Spans-Modul** | A7, 4.1 | Niedrig | Sicher | Akzeptabel — auto-psr15 liefert generischen Request-Span, OTel-Modul liefert semantischen Span als Child |
| R15 | **Grosse traces.json bei vielen Testlaeufen** | A8, 2.3 | Niedrig | Moeglich | Filterung nach test.run_id; `make clean` loescht die Datei; keine Rotation noetig |
| R16 | **Boomerang-Spans ohne test.run_id** — nur temporale Korrelation | A6, 2.5 / A8, 2.3 | Mittel | Sicher | Workers=1 minimiert Ueberlappung; akzeptable Ungenauigkeit fuer Browser-Spans |
| R17 | **npm/curl-Abhaengigkeit im Containerfile-Build** fuer Boomerang-Download | Neu | Niedrig | Niedrig | curl ist im Base-Image vorhanden; npm ist optional (curl auf npm-Registry-Tarball als Alternative) |

### 5.2 Risikobewertung nach Schwere

**Hoch (eliminiert durch Architekturentscheidung):**
- R6 (Apache ABI): Eliminiert — Apache OTel-Modul wird nicht verwendet
- R7 (MySQL Enterprise): Eliminiert — MySQL Telemetry wird nicht verwendet

**Mittel (mit Mitigation beherrschbar):**
- R2 (CORS): CORS-Config im Collector
- R8 (MySQL Healthcheck): Verifizierung bei Umstellung
- R10 (PerfSchema-Verlust): Extraktions-Timing dokumentieren
- R12 (Encoding-Roundtrip): Testen bei Implementierung
- R13 (Route-Namen): Defensive Programmierung
- R16 (Boomerang-Korrelation): Workers=1

**Niedrig (akzeptabel):**
- R1, R3, R4, R5, R9, R14, R15, R17

---

## 6. Bewertung

### 6.1 Technische Machbarkeit: HOCH

Alle empfohlenen Implementierungsschritte basieren auf bewaehrten Technologien und gesicherten Analyseergebnissen:

- **PHP OTel Auto-Instrumentation** ist eine reife Technologie mit stabiler API
- **Boomerang + OTel-Plugin** ist funktional ausgereift (A1, Abschnitt 2.1)
- **mod_substitute** ist ein Standard-Apache-Modul ohne Kompilierungsabhaengigkeit
- **MySQL 8.4 LTS** ist die offizielle Langzeit-Support-Version
- **Performance Schema** ist Default-maessig aktiviert und erfordert minimale Konfiguration

### 6.2 Komplexitaet: MODERAT

Die Gesamtarchitektur ist durch die Entscheidungen in A3 (kein Apache-Modul) und A4 (kein MySQL-Plugin) deutlich vereinfacht. Die verbleibende Komplexitaet liegt in:

1. **Boomerang-Integration** (A1/A2): Multi-Datei-JS-Injection via Apache-Config — funktional, aber fragil in der Config-Syntax
2. **OTel-Spans-Modul** (A7): PHP-Modul mit ~150 Zeilen — ueberschaubar, aber erfordert Kenntnis der webtrees-Modul-API
3. **Trace-Report-Script** (A8): Python-Script mit ~300 Zeilen — die komplexeste Einzelkomponente, aber mit klarem Design

### 6.3 Wartbarkeit: GUT

- Keine Abhaengigkeit von Pre-built Binaries fuer exotische Plattform-Kombinationen
- Alle Versionen gepinnt und reproduzierbar
- Keine Upstream-Aenderungen an webtrees noetig
- OTel-Spans-Modul robust gegenueber webtrees-Updates (nutzt stabile Module-API)
- Trace-Report-Script nutzt nur Python-Standardbibliothek

---

## 7. Empfehlung

### 7.1 Konkreter Implementierungsplan

**Phase 1 (0.5 PT): Infrastruktur-Basis**

Alle vier Schritte in einem Commit:

1. `compose.yaml`: MySQL auf `mysql:lts`, Collector auf gepinnte Version, Jaeger auf gepinnte Version, Port 4318 exponieren
2. `otel/otel-collector-config.yaml`: HTTP-Receiver mit CORS hinzufuegen
3. `setup-webtrees.sh`: `opentelemetry-auto-psr15` zur Composer-require-Liste hinzufuegen
4. `compose.yaml` (mysql-security): Parallel auf `mysql:lts` aktualisieren

**Validierung:** `make clean && make up && make setup && make test-all` — alle bestehenden Tests muessen gruen sein.

**Phase 2 (0.5–1 PT): OTel-Spans-Modul**

1. `modules/otel-spans/module.php` und `modules/otel-spans/OtelSpansModule.php` erstellen
2. `compose.yaml`: Volume-Mount fuer das Modul hinzufuegen
3. Route-Map fuer die wichtigsten Interaktionen (View Individual, View Family, Search, Edit — ~20 Routes initial)
4. Baggage-zu-Span-Attribut-Konvertierung (`test.run_id`, `test.case_id`)
5. Graceful Degradation bei deaktiviertem OTel SDK (NoOp-Tracer)

**Validierung:** Jaeger UI zeigt Spans mit `webtrees.action`-Attributen nach manuellem Browsen.

**Phase 3 (0.75 PT): Testlauf-Korrelation**

1. `layer4-e2e/helpers/otel-fixture.ts`: Playwright Baggage-Fixture
2. `scripts/extract-perfschema.sh` und `scripts/truncate-perfschema.sh`
3. `Makefile`: Targets `perfschema-extract` und `perfschema-report`

**Validierung:** Nach `make test-e2e` sind Spans in Jaeger nach `test.run_id` filterbar; PerfSchema-JSON unter `artifacts/` vorhanden.

**Phase 4 (1–2 PT): Auswertung**

1. `scripts/trace-report.py`: OTLP-NDJSON-Parsing, Hierarchie-Aufloesung, Text-Report
2. `scripts/trace-report.sh`: Bash-Wrapper
3. `Makefile`: Target `trace-report`
4. PerfSchema-Integration im Report (Phase 2 des Scripts, A8 Abschnitt 3.7)

**Validierung:** `make trace-report RUN_ID=<uuid> LAYER=4` erzeugt lesbaren Report auf stdout und JSON unter `artifacts/`.

**Phase 5 (1–2 PT): Browser-RUM**

1. `Containerfile.webtrees`: Boomerang-Installation, mod_substitute-Aktivierung
2. `otel/boomerang-init.js`: Boomerang-Konfiguration
3. `otel/boomerang-apache.conf`: Apache-Substitute-Regeln
4. Boomerang-Injection bei OTEL_SDK_DISABLED steuerbar machen

**Validierung:** Jaeger UI zeigt `webtrees-browser`-Spans nach Seitenaufruf; Trace-Report zeigt Browser-RUM-Zeiten.

### 7.2 Was NICHT implementiert wird

| Komponente | Begruendung |
|---|---|
| Apache OTel-Modul | Kein kompatibles Pre-built Binary (A3) |
| MySQL Telemetry Plugin | Enterprise-only (A4) |
| Playwright OTel SDK (Node.js) | Initial aufgeschoben — Baggage-Korrelation reicht fuer den initialen Einsatz; als systematisch korrekte Ausbaustufe (Option A) anerkannt fuer kausale Trace-Kette (A6, Abschnitt 1.3/2.6) |
| End-to-End Trace-ID-Propagation PHP→MySQL | Architektonisch nicht moeglich (A4, Abschnitt 2.3.3) |
| mysqld_exporter (Prometheus) | Redundant — PerfSchema-SQL liefert dieselben Daten (A5) |
| Boomerang Beacon-Receiver | Nicht noetig — OTel-Traces reichen aus (A1, Abschnitt 3) |
| Boomerang Grunt-Build-Pipeline | Unnoetig — synchrones Laden der Roh-Dateien genuegt (A1, Abschnitt 3) |

---

## 8. Offene Punkte

### 8.1 Vor Beginn der Implementierung zu klaeren

1. **OTel Collector Version pinnen:** Die konkrete Version des OTel Collector Contrib-Images muss gewaehlt werden. Empfehlung: Die zum Zeitpunkt der Implementierung aktuelle stabile Version verwenden und dokumentieren.

2. **Jaeger Version pinnen:** Analog zum Collector. Empfehlung: Aktuelle stabile Version.

3. **Boomerang npm-Download vs. curl:** Entscheidung, ob `npm pack` (erfordert npm im Build) oder `curl` auf die npm-Registry-Tarball-URL (kein npm noetig) verwendet wird. curl ist einfacher und hat weniger Abhaengigkeiten.

4. **Boomerang `beacon_url`:** Verifizieren, dass Boomerang mit `beacon_url: '/dev/null'` (oder einer nicht-existierenden URL) keine blockierenden Fehler erzeugt (A1, Abschnitt 4.1.2).

5. **OTEL_PROPAGATORS Default:** Im laufenden Container pruefen, ob der Default `tracecontext,baggage` gilt, oder ob `OTEL_PROPAGATORS` explizit in `compose.yaml` gesetzt werden muss (A6, Abschnitt 4.1.1).

6. **Span-Name-Konvention:** Entscheidung, ob das OTel-Spans-Modul die OpenTelemetry HTTP Semantic Convention (`HTTP GET /tree/{tree}/individual/{xref}`) oder eigene Benennung (`webtrees.view_individual`) verwendet (A7, Abschnitt 4.1.2).

7. **Route-Map-Granularitaet:** Entscheidung, ob initial alle ~80 Routes oder nur die im Scope genannten (~20 Routes) gemappt werden (A7, Abschnitt 4.1.3).

8. **OTel-Spans-Modul Platzierung:** Entscheidung ueber das Verzeichnis: `modules/otel-spans/` (im Repo-Root) oder `otel/otel-spans-module/` (im otel-Verzeichnis). Empfehlung: `modules/otel-spans/`, da es ein webtrees-Modul ist.

9. **Boomerang-Deaktivierung:** Entscheidung, ob Boomerang-Injection per `OTEL_SDK_DISABLED` oder per eigener Environment-Variable (`BOOMERANG_ENABLED`) gesteuert wird.

### 8.2 Waehrend der Implementierung zu verifizieren

10. **ext-opentelemetry Kompatibilitaet mit PHP 8.5:** Pruefen, ob `auto-psr15` 1.2.0 mit PHP 8.5 kompatibel ist (A7, Abschnitt 4.2.6).

11. **mod_substitute und FallbackResource:** Pruefen, ob mod_substitute korrekt mit `FallbackResource /index.php` interagiert (A2, Abschnitt 4.1.3).

12. **Collector-URL im Container-Netzwerk vs. Host:** Boomerang im Browser verwendet `http://localhost:4318/v1/traces` (Host-Zugriff) vs. Playwright-Browser im Container verwendet `http://otel-collector:4318/v1/traces` (Container-Netzwerk). Die `boomerang-init.js` muss ggf. dynamisch konfigurierbar sein (A2, Abschnitt 4.1.2).

13. **MySQL 8.4 Healthcheck:** Verifizieren, dass der bestehende `mysqladmin ping`-Healthcheck mit MySQL 8.4 und `caching_sha2_password` funktioniert (A4, Abschnitt 4.1).

### 8.3 Nicht blockierend (spaeter)

14. **Server-Timing Header:** PHP OTel SDK emittiert standardmaessig keinen `Server-Timing`-Response-Header. Fuer Browser↔Server-Trace-Verknuepfung waere dies nachzuruesten. **Wird Voraussetzung fuer Option A** (Playwright Root-Span): Boomerangs `instrumentation-document-load` benoetigt den Server-Timing-Rueckkanal, um Browser-Spans mit dem Server-Span im selben Trace-Baum zu verknuepfen (A6, Abschnitt 1.3). Prioritaet: Niedrig (initial), steigt bei Umstieg auf Option A.

15. **Content-Security-Policy:** Aktuell kein CSP-Header gesetzt. Falls spaeter eingefuehrt, muessen `/rum/`-Script-Quellen erlaubt werden (A2, Abschnitt 4.2.9).

16. **Baseline-Vergleich (PerfSchema):** Automatischer Schwellwert-Vergleich mit konfigurierbaren Toleranzen. Phase 3 des PerfSchema-Plans (A5, Abschnitt 3). Aufwand: ~8 Stunden.

17. **Digest-Text-Matching (PDO ↔ PerfSchema):** Korrelation zwischen PDO-Span `db.statement` und PerfSchema `DIGEST_TEXT`. Phase 4 des Trace-Report-Plans (A8, Abschnitt 3.7). Aufwand: ~6 Stunden.

18. **ARM64-Support:** Keines der analysierten OTel-Binaries unterstuetzt ARM64 (A3, Abschnitt 4.2.5). Falls die Testing-Plattform auf Apple Silicon laufen soll, muesste dies beruecksichtigt werden. Fuer den aktuellen Fedora/x86-64-Einsatz irrelevant.

---

*Synthese basiert auf den Analysen A1–A8, erstellt am 2026-03-29. Alle Referenzen beziehen sich auf die Dokumente unter `docs/laufzeit_analyse/01_boomerang_rum.md` bis `08_auswertung.md` sowie den Analyseprompt `docs/laufzeit_analyse_prompt.md`.*
