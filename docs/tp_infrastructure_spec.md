<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Infrastruktur-Entscheidungen — webtrees-testing-platform

Dieses Dokument beschreibt die Infrastruktur-Entscheidungen (N1–N7) und die
Container-Stack-Spezifikation der webtrees-testing-platform. Es definiert die
technische Grundlage, auf der alle Teststufen aufbauen.

Verwandte Dokumente:
- [Designentscheidungen](tp_decisions_spec.md) — Architektonische Designentscheidungen
- [Testkonventionen](tp_conventions_spec.md) — Testentwurfsverfahren, Orakel, Namenskonventionen
- [Dokumentationsübersicht](tp_overview_spec.md) — Navigation zu allen Subdokumenten

---

## Getroffene Infrastruktur-Entscheidungen (N1–N7)

> Entschieden am 2026-03-26. Diese Entscheidungen konkretisieren die Designentscheidungen
> oben und bilden die Grundlage für die Implementierung.

---

### N1 — Container-Runtime: Podman + podman-compose

| Aspekt | Entscheidung |
|---|---|
| **Runtime** | Podman 5.8.1 (rootless, Fedora-nativ) |
| **Orchestrierung** | podman-compose 1.5.0 (`/usr/bin/podman-compose`) |
| **Compose-Datei** | `compose.yaml` (nicht `docker-compose.yaml`) |
| **Format** | Standard Compose Specification |

**Begründung:** Podman und podman-compose sind bereits auf dem Entwicklungssystem installiert.
Podman läuft rootless (kein Daemon, keine Root-Rechte), ist auf Fedora das native
Container-Tool und liest das Standard-Compose-Format. Docker ist nicht installiert und
wird nicht benötigt.

**Einschränkung:** podman-compose unterstützt nicht alle `depends_on.condition`-Features.
Stattdessen werden explizite Health-Checks und ein `wait-for-it.sh`-Skript verwendet.

---

### N2 — Verzeichnisstruktur: Eigenständiges Repo `webtrees-testing-platform`

```
webtrees-testing-platform/
├── compose.yaml                    # Podman Compose Stack-Definition
├── Containerfile.webtrees          # PHP 8.5 + Apache mod_php
├── Containerfile.playwright        # Node.js 22 + Playwright + Chromium
├── Containerfile.security          # Distribution-Container (Multi-Stage Build)
├── Makefile                        # make up / down / test-all / test-N / clean
├── .env.example                    # Template: DB-Creds, OTel-Config
├── README.md                       # Deutsch: Strategie + Quickstart
├── CLAUDE.md                       # AI-Kontext: Testaufruf, Layer-Architektur, SELinux
├── docs/
│   └── tp_overview_spec.md          # Dokumentationsübersicht (Einstiegspunkt)
├── scripts/
│   ├── setup-webtrees.sh          # Auto-Installer (config.ini.php, Migration, GEDCOM-Import)
│   ├── generate-privacy-fixture.sh # Template → GEDCOM-Generator (__YEAR_MINUS_N__ ersetzen)
│   ├── build-security-image.sh    # Build-Helper für Distribution-Container (podman build --volume)
│   ├── security-filesystem-checks.sh # 9 Dateisystem-Assertions (pre/post-wizard)
│   ├── analyze-failure.sh         # Artefakt-Sammler → Claude Code CLI
│   ├── export-traces.sh           # OTel-Traces als JSON exportieren
│   ├── truncate-perfschema.sh     # MySQL PerfSchema vor Testlauf leeren
│   ├── extract-perfschema.sh      # PerfSchema-Daten als JSON (4 Tabellen + summary.txt)
│   ├── trace-report.py            # OTLP NDJSON Parser + 4-Stufen-Hierarchie-Report
│   ├── trace-report.sh            # Bash-Wrapper für trace-report.py
│   └── wait-for-it.sh            # TCP-Port-Readiness-Check (vendored)
├── fixtures/
│   ├── demo.ged                   # webtrees Core (72 Individuen, 29 Familien)
│   ├── gedcom-l-muster.ged       # Deutsches Muster (CC BY 4.0, 37 Individuen)
│   ├── privacy-test-template.ged  # Privacy-GEDCOM-Template (30+ Personen, __YEAR_MINUS_N__)
│   ├── invalid-empty.txt          # Leere Datei (0 Bytes) — Upload-Validierung (G21)
│   ├── invalid-text.txt           # Textdatei (kein GEDCOM) — Upload-Validierung (G21)
│   ├── invalid-no-head.ged        # GEDCOM ohne HEAD — Upload-Validierung (G21)
│   └── invalid-binary.bin         # Binärdatei (16 Bytes) — Upload-Validierung (G21)
├── layer1-static/
│   └── run.sh                     # PHPStan + PHPCS im Container
├── layer2-unit/
│   ├── run.sh                     # PHPUnit Unit-Suite
│   └── phpunit-unit.xml           # Config (SQLite in-memory wie webtrees Core)
├── layer3-integration/
│   ├── run.sh                     # PHPUnit Integration-Suite
│   ├── phpunit-integration.xml    # Config (MySQL)
│   ├── bootstrap.php              # Autoloader (webtrees + DombrinksBlagen-Namespace)
│   └── tests/                     # 80 Dateien (2 Basis + 78 Testklassen)
│       ├── MysqlTestCase.php
│       ├── PrivacyTestCase.php    # Basisklasse Privacy-Tests (GEDCOM-Generator, Rollen-Helper)
│       ├── AutoCompleteIntegrationTest.php
│       ├── ChartModuleIntegrationTest.php
│       ├── GedcomImportTest.php
│       ├── GedcomServiceIntegrationTest.php
│       ├── ListModuleIntegrationTest.php
│       ├── RelationshipDbTest.php
│       ├── RelationshipServiceIntegrationTest.php
│       ├── RomanNumeralsIntegrationTest.php
│       ├── SearchIntegrationTest.php
│       ├── TreeOperationsTest.php
│       ├── PrivacySmokeTest.php   # P1 Infrastruktur-Smoke (5 Tests)
│       ├── IsDeadTest.php         # P08–P13 isDead()-Algorithmus (17 Tests)
│       ├── PrivacyVisibilityTest.php # P01–P07, P14–P15 Sichtbarkeit (22 Tests)
│       ├── ResnPrivacyTest.php    # P16–P21 RESN + default_resn (16 Tests)
│       ├── RelationshipPrivacyTest.php # P22–P23 Relationship Privacy (5 Tests)
│       ├── PrivacySearchTest.php  # P24 Privacy in Suchergebnissen (5 Tests)
│       └── AccessControlTest.php  # P27–P29 Zugriffskontrolle (12 Tests)
├── layer4-e2e/
│   ├── playwright.config.ts       # baseURL = http://webtrees:80 (testIgnore: security/)
│   ├── playwright-security.config.ts # Security-Playwright-Config (Distribution-Container)
│   ├── helpers/
│   │   ├── otel-fixture.ts        # Playwright Root-Span + traceparent (page.route()) + Baggage
│   │   ├── theme-switch.ts        # Shared Utility: Theme-Switching (5 Themes)
│   │   └── privacy-roles.ts      # Privacy-Rollen-Login (visitor, member, editor, moderator, manager, relationship)
│   └── tests/
│       ├── security/                  # Sicherheitstests (getrennt von funktionalen E2E)
│       │   ├── wizard-setup.spec.ts   # SEC-WZ01–WZ04 (Setup-Projekt, läuft zuerst)
│       │   ├── data-access.spec.ts    # SEC-H03–H06
│       │   ├── public-access.spec.ts  # SEC-PUB02–PUB04
│       │   ├── setup-lock.spec.ts     # SEC-W01
│       │   ├── media-access.spec.ts   # SEC-M01–M03
│       │   └── security-headers.spec.ts # SEC-HDR01–HDR04
│       ├── login.spec.ts          # S32 (theme-unabhängig)
│       ├── auth.spec.ts           # S33, S34 (theme-unabhängig)
│       ├── navigation.spec.ts     # S23, S20, S09 (× 5 Themes)
│       ├── individual.spec.ts     # S23 (× 5 Themes)
│       ├── family.spec.ts         # S24 (× 5 Themes)
│       ├── records.spec.ts        # S26–S30 (× 5 Themes)
│       ├── calendar.spec.ts       # S31 (× 5 Themes)
│       ├── search-forms.spec.ts   # S38, S39 (× 5 Themes)
│       ├── user-pages.spec.ts     # S35–S37 (× 5 Themes)
│       ├── homepage.spec.ts       # S40 (× 5 Themes)
│       ├── pedigree.spec.ts       # S14 (× 5 Themes)
│       ├── source-list.spec.ts    # S20 (× 5 Themes)
│       ├── upload-validation.spec.ts # G21 (Admin, kein Theme-Loop)
│       ├── search-replace.spec.ts # S13 (× 5 Themes + 1 Visitor)
│       ├── privacy-visibility.spec.ts # P02–P03, P14, P25 (5 Tests)
│       ├── privacy-resn.spec.ts   # P16–P19 (7 Tests)
│       ├── privacy-search.spec.ts # P24 (4 Tests)
│       ├── privacy-charts.spec.ts # P26 (2 Tests)
│       ├── privacy-relationship.spec.ts # P22 (3 Tests)
│       └── access-control.spec.ts # P27–P29 (5 Tests)
├── layer5-performance/
│   ├── playwright.config.ts       # Performance-spezifische Config (timeout 60s, retries 0)
│   ├── helpers/
│   │   └── otel-fixture.ts        # Root-Span + traceparent + Baggage (identisch zu layer4)
│   ├── run.sh                     # Perf-Messung + Baseline-Vergleich
│   ├── baselines/                 # Versionierte Baseline-JSONs (z.B. 2.2.5.json)
│   └── tests/
│       ├── perf-homepage.spec.ts
│       ├── perf-search.spec.ts
│       └── perf-pedigree.spec.ts
├── modules/
│   └── otel-spans/
│       ├── module.php              # Modul-Einstiegspunkt
│       └── OtelSpansModule.php     # Semantische Spans, Server-Timing-Header, Baggage-Korrelation
├── otel/
│   ├── otel-collector-config.yaml  # Collector-Pipeline (OTLP HTTP :4318 → Jaeger + File)
│   ├── boomerang-init.js           # Boomerang OTel-Plugin-Initialisierung (service: webtrees-browser)
│   └── boomerang-apache.conf       # mod_substitute Injection-Config (INFLATE;SUBSTITUTE;DEFLATE)
├── upstream/                      # gitignored — automatisch geklonter webtrees-Checkout
│   └── webtrees/                  # (via scripts/clone-upstream.sh)
├── artifacts/                     # gitignored — Laufzeit-Artefakte
│   ├── layer1/ … layer5/
└── .github/workflows/
    └── webtrees-tests.yaml        # GitHub Actions Workflow (Entwurf)
```

**Begründung:** `webtrees-testing-platform` ist ein eigenständiges Repo, unabhängig vom
Deployment-Repo und `smoke-tests/` (Live-Site-Tests). Die webtrees-Source (Default: `./upstream/webtrees`, konfigurierbar via `WEBTREES_SOURCE`) wird per read-only Bind-Mount in den Container
eingebunden — kein Code wird kopiert oder modifiziert.

`artifacts/` wird in `.gitignore` eingetragen. `layer5-performance/baselines/` ist absichtlich
versioniert — das ist der Kern des Baseline-Vergleichs.

---

### N3 — GEDCOM-Fixture: `demo.ged` (primär) + deutsches Muster (sekundär)

| Fixture | Quelle | Umfang | Zweck |
|---|---|---|---|
| `demo.ged` | `${WEBTREES_SOURCE}/tests/data/demo.ged` | 72 Individuen, 29 Familien (brit. Königshaus) | Primär-Fixture für alle Schichten |
| `gedcom-l-muster.ged` | `github/gedcom_muster/muster_GEDCOM_UTF-8.ged` | 37 Individuen, 18 Familien | i18n / Deutsch-Testing |
| `privacy-test-template.ged` | Eigene Erstellung | 30+ Individuen, 7+ Familien (dynamische Datums-Platzhalter) | Privacy & Zugriffskontrolle (P01–P29) |

**Begründung:** `demo.ged` ist die kanonische Testdatei von webtrees selbst (verwendet in
`ImportGedcomTest`). Sie deckt Mehrgenerationen-Beziehungen, mehrere Ehen, Medien-Referenzen
und Quellen ab. Das deutsche Muster (CC BY 4.0, Verein für Computergenealogie) ergänzt
für Lokalisierungstests.

**Setup:** Alle Fixture-Dateien liegen in `fixtures/`. Das `setup-webtrees.sh`-Skript
importiert sie beim Container-Start als drei separate Bäume (`demo`, `muster`, `privacy`).
Die Privacy-Fixture wird dynamisch aus dem Template generiert (`generate-privacy-fixture.sh`).

---

### N4 — Implementierungsreihenfolge: Bottom-up, Testumgebung → Teststufe 3

| Phase | Stufe / Querschnitt | Kern-Deliverable |
|---|---|---|
| 1 | Querschnitt — Testumgebung | `compose.yaml`, Containerfiles, `setup-webtrees.sh`, `Makefile` |
| 2 | Querschnitt — Statischer Test | `layer1-static/run.sh` (PHPStan + PHPCS im Container) |
| 3 | Teststufe 1 — Komponententest | `phpunit-unit.xml`, SQLite in-memory (wie webtrees Core) |
| 4 | Teststufe 2 — Komponentenintegrationstest | `MysqlTestCase.php`, neue Tests (GEDCOM-Import, Beziehungen, Bäume) |
| 5 | Teststufe 3 — Systemtest | `Containerfile.playwright`, Playwright-Tests (Login, Navigation, Themes) |
| 6 | Querschnitt — Performanztest | Playwright-Metrics, Baseline-JSONs, Vergleichsskript |
| 7 | Querschnitt — CI/CD, OTel, KI-Debug | `analyze-failure.sh`, OTel-Stack, GitHub Actions Workflow |

**Begründung:** Jede höhere Teststufe hängt von der Testumgebung (Container-Stack) ab.
Teststufe 1 nutzt SQLite in-memory wie webtrees Core selbst — das validiert die
Container-Umgebung ohne MySQL-Abhängigkeit. MySQL-spezifische Tests kommen erst in
Teststufe 2 mit einer eigenen `MysqlTestCase`-Basis-Klasse.

---

### N5 — `analyze-failure.sh`: Artefakt-Sammler → Claude Code CLI

**Aufruf:**
```bash
./scripts/analyze-failure.sh        # Alle Teststufen
./scripts/analyze-failure.sh 2      # Nur Teststufe 2
```

**Funktionsweise:**
1. Sammelt Artefakte aus `artifacts/` je nach Teststufe:
   - Statischer Test: PHPStan JSON, PHPCS JSON
   - Teststufe 1: PHPUnit XML + PHP-Fehlerlog (via `podman logs`)
   - Teststufe 2: PHPUnit XML + DB-Dump (`mysqldump`, nur Testschema) + PHP-Log + OTel-Trace-JSON
   - Teststufe 3: Playwright Trace (.zip) + Screenshots + Browser-Konsole-Log + OTel-Trace-JSON
   - Performanztest: Performance-JSON-Diff (Baseline vs. aktuell) + OTel-Trace-Diff
2. Formatiert alles als Markdown-Kontext-Dokument
3. Startet Claude Code CLI mit vorgeladenem Kontext

**In GitHub Actions:** Artefakte werden zusätzlich via `actions/upload-artifact` hochgeladen
(7 Tage Retention), damit Analyse auch ohne lokalen Container-Rebuild möglich ist.

---

### N6 — OTel-Integration: Vollständige Trace-Kette (4 Schichten)

> Entschieden 2026-03-26, implementiert bis 2026-04-01.

| Schicht | Mechanismus | Service-Name in Jaeger | Protokoll |
|---|---|---|---|
| PHP Auto-Instrumentation | PDO + PSR-15 + PSR-18 Auto-Instrumentierung | `webtrees` | OTLP HTTP/Protobuf :4318 |
| PHP OtelSpansModule | Semantische Spans (40+ Routes), Server-Timing-Header, Baggage-Korrelation | `webtrees` (Scope: `otel-spans`) | — (in-process) |
| Browser-RUM | Boomerang + OTel-Plugin via Apache mod_substitute, Server-Timing-Brücke | `webtrees-browser` | OTLP HTTP :4318 |
| Playwright Root-Spans | Ein Root-Span pro Testfall, traceparent-Propagation via page.route() | `playwright-tests` | OTLP HTTP :4318 |

**Gemeinsames Protokoll:** OTLP HTTP/Protobuf auf Port 4318. Alle vier Schichten senden an denselben Endpunkt.
Deaktivierung via `OTEL_SDK_DISABLED=true` → Zero Overhead (PHP-SDK-Guard + Boomerang-Injection-Guard).

---

#### N6a — PHP Auto-Instrumentation (PDO, PSR-15, PSR-18)

| Aspekt | Entscheidung |
|---|---|
| **Tiefe** | Nur Auto-Instrumentation — kein webtrees-Core-Change |
| **Installation** | `composer require` bedingt in `setup-webtrees.sh` (nur wenn `OTEL_SDK_DISABLED != true`) |
| **Aktivierung** | ENV-Variablen in `compose.yaml` |
| **Deaktivierung** | `OTEL_SDK_DISABLED=true` → Zero Overhead |
| **Export lokal** | Jaeger UI (http://localhost:16686) |
| **Export CI** | File-Exporter → `artifacts/traces.json` |

**Composer-Pakete:**

- `open-telemetry/sdk`
- `open-telemetry/exporter-otlp`
- `open-telemetry/opentelemetry-auto-pdo`
- `open-telemetry/opentelemetry-auto-psr18`
- `open-telemetry/opentelemetry-auto-psr15`

PHP-Extensions `protobuf` + `grpc` im `Containerfile.webtrees` (via `pecl install`).

**ENV-Variablen (in `compose.yaml`):**
```yaml
OTEL_PHP_AUTOLOAD_ENABLED: "true"
OTEL_SERVICE_NAME: "webtrees"
OTEL_EXPORTER_OTLP_ENDPOINT: "http://otel-collector:4318"
OTEL_EXPORTER_OTLP_PROTOCOL: "http/protobuf"
OTEL_SDK_DISABLED: "false"
OTEL_TRACES_EXPORTER: "otlp"
OTEL_METRICS_EXPORTER: "none"
OTEL_LOGS_EXPORTER: "none"
```

**OTel Collector Config (`otel/otel-collector-config.yaml`):**
```yaml
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317
      http:
        endpoint: 0.0.0.0:4318
        cors:
          allowed_origins: ["http://localhost:8080", "http://webtrees:80", "http://webtrees"]
          allowed_headers: ["*"]
          max_age: 7200
exporters:
  otlp/jaeger:
    endpoint: jaeger:4317
    tls:
      insecure: true
  file:
    path: /artifacts/traces.json
    append: true
service:
  pipelines:
    traces:
      receivers: [otlp]
      exporters: [otlp/jaeger, file]
```

**Begründung:** Auto-Instrumentation fängt alle PDO-Queries und HTTP-Calls automatisch ab —
ohne eine Zeile webtrees-Code zu ändern. Protokollwechsel gRPC → HTTP/Protobuf war notwendig,
damit Browser-Spans (Boomerang) denselben Endpunkt nutzen können.

---

#### N6b — OtelSpansModule (semantische Spans)

| Aspekt | Entscheidung |
|---|---|
| **Implementierung** | webtrees-Modul unter `modules/otel-spans/` — kein Core-Change |
| **Mount** | Bind-Mount in `compose.yaml` → `modules_v4/otel_spans` |
| **Span-Klassifikation** | 40+ ROUTE_MAP-Einträge, Span-Name `webtrees.<action>` (z.B. `webtrees.individual.show`) |
| **Server-Timing-Header** | `traceparent;desc="00-{traceId}-{spanId}-01"` in PHP-Responses für gemappte Routes |
| **Baggage-Korrelation** | `test.run_id` + `test.case_id` aus eingehenden Baggage-Headern als Span-Attribute |
| **Guard** | `class_exists(Globals::class)` — Graceful Degradation bei deaktiviertem SDK |

**Begründung:** Auto-Instrumentation liefert keine semantischen Span-Namen — `webtrees.individual.show`
ist aussagekräftiger als `GET /index.php`. Der Server-Timing-Header ist der einzige standardkonforme
Kanal, um `traceparent` vom PHP-Server in den Browser zu propagieren, ohne webtrees-Code zu ändern.

---

#### N6c — Browser-RUM: Boomerang + OTel-Plugin

| Aspekt | Entscheidung |
|---|---|
| **RUM-Bibliothek** | Boomerang 1.815.1 + `@opentelemetry/instrumentation-document-load` |
| **Injection** | Apache `mod_substitute` mit `INFLATE;SUBSTITUTE;DEFLATE`-Filterkette — kein webtrees-Core-Change |
| **Konfiguration** | `otel/boomerang-init.js` + `otel/boomerang-apache.conf` |
| **Collector-URL** | `http://otel-collector:4318/v1/traces` (Container-Hostname, nicht localhost) |
| **Deaktivierung** | Injection nur wenn `OTEL_SDK_DISABLED != true` |
| **Trace-Korrelation** | Boomerang liest `traceparent` aus Server-Timing-Header → Browser-Spans im selben Trace |

**Begründung:** Browser-RUM ohne Build-Pipeline (kein Grunt, kein Webpack), kein webtrees-Core-Change.
Die `INFLATE;SUBSTITUTE;DEFLATE`-Filterkette ist notwendig, da webtrees Responses komprimiert ausliefert.

---

#### N6d — Playwright Root-Spans (Testfall-Korrelation)

| Aspekt | Entscheidung |
|---|---|
| **SDK** | `@opentelemetry/api`, `sdk-trace-node`, `exporter-trace-otlp-http`, `resources`, `semantic-conventions` im Playwright-Container |
| **Fixture** | `layer4-e2e/helpers/otel-fixture.ts`, `layer5-performance/helpers/otel-fixture.ts` |
| **Mechanismus** | Root-Span pro Testfall; `traceparent` via `page.route()` in alle webtrees-Requests injiziert |
| **Baggage** | `test.run_id` (UUID pro `make`-Aufruf) + `test.case_id` (Testfall-Name, alphanumerisch bereinigt) |
| **Ausnahme** | OTLP-Requests an `otel-collector:4318` werden von `page.route()` ausgenommen |
| **Ergebnis** | Alle PHP-Spans eines Testfalls teilen dieselbe `trace_id` wie der Playwright-Root-Span |

**Begründung:** `page.route()` ersetzt `page.setExtraHTTPHeaders()`, weil Header selektiv
pro Request-URL gesetzt werden können — OTLP-Requests dürfen kein `traceparent` erhalten.

---

#### N6e — PerfSchema-Extraktion und Trace-Report

| Aspekt | Entscheidung |
|---|---|
| **Scripts** | `truncate-perfschema.sh`, `extract-perfschema.sh`, `trace-report.py`, `trace-report.sh` |
| **Makefile-Targets** | `perfschema-truncate`, `perfschema-extract`, `trace-report` |
| **Automatisierung** | `make test-e2e` und `make test-performance` rufen truncate/extract/report automatisch auf |
| **Span-Hierarchie** | `trace-report.py` klassifiziert in 4 Stufen: Playwright, Browser (RUM), PHP Custom, PHP Auto |

---

### N7 — GitHub Actions Workflow

| Aspekt | Entscheidung |
|---|---|
| **Datei** | `.github/workflows/webtrees-tests.yaml` |
| **Trigger** | `push` + `pull_request`, `workflow_dispatch` |
| **Matrix** | PHP 8.5 (Latest Stable, keine Vorgängerversionen) |
| **Runner** | `ubuntu-latest` (Podman vorinstalliert) |
| **Job-Kette** | `testumgebung` → `statischer-test` → `komponententest` → `komponentenintegrationstest` → `systemtest` → `performanztest` |
| **Artefakte** | Coverage-HTML, Playwright-Report, Performance-Diff, OTel-Traces (7 Tage Retention) |

**Setup-Schritt (in jedem Job):**
```yaml
- name: Install podman-compose
  run: pip install podman-compose
```

**`workflow_dispatch` Input (für manuelles Testen vor Version-Update):**
```yaml
inputs:
  webtrees_ref:
    description: 'webtrees git ref to test (branch, tag, commit)'
    default: 'main'
```

**Begründung:** Die Job-Kette spiegelt die Stufenhierarchie wider — bei einem Fehler in einer
niedrigeren Teststufe brechen die höheren ab. Die PHP-Matrix testet ausschließlich mit PHP 8.5
(Latest Stable) — Vorgängerversionen werden bewusst nicht getestet, unabhängig davon welche
Versionen webtrees Core offiziell unterstützt. `workflow_dispatch` erlaubt manuelles Testen
eines spezifischen webtrees-Refs vor einem Versions-Update.

---

## Container-Stack-Spezifikation

### 6+2 Container, 1 Netzwerk

> Der Fachtest-Stack umfasst 6 Container in einem gemeinsamen Netzwerk (`webtrees-test-net`).
> Für Sicherheitstests (`make test-security`) kommen 2 weitere Container hinzu, die über
> ein separates Compose-Profil (`--profile security`) gestartet werden. Die Security-Container
> nutzen dasselbe `webtrees-test-net`-Netzwerk.

| Container | Image | Zweck | Host-Port | Volume-Mounts |
|---|---|---|---|---|
| `webtrees` | `Containerfile.webtrees` | PHP 8.5 + Apache mod_php + webtrees | 8080:80 | `${WEBTREES_SOURCE}` → `/var/www/html` (ro), Named Vol → `/var/www/html/data/` (rw), `fixtures/` → `/fixtures` (ro) |
| `mysql` | `docker.io/library/mysql:lts` | Datenbank (MySQL LTS 8.4) | 3306:3306 | Named Vol → `/var/lib/mysql` |
| `playwright` | `Containerfile.playwright` | Node.js 22 + Chromium (headless) + OTel SDK | — | `layer4-e2e/` + `layer5-performance/` → `/tests` (ro), `artifacts/` → `/artifacts` (rw) |
| `otel-collector` | `docker.io/otel/opentelemetry-collector-contrib:0.148.0` | OTel Sidecar (OTLP HTTP :4318, gRPC :4317) | 4317:4317, 4318:4318 | `otel/otel-collector-config.yaml` → `/etc/otelcol-contrib/config.yaml` (ro), `artifacts/` → `/artifacts` (rw) |
| `jaeger` | `docker.io/jaegertracing/jaeger:2.16.0` | Trace-Visualisierung | 16686:16686 | — |
| `adminer` | `docker.io/library/adminer` | DB-Admin (optional, nur Debug) | 8081:8080 | — |
| `webtrees-security` | `Containerfile.security` | Distribution-Build (ZIP entpackt) + Apache (Profil: security) | 8082:80 | — |
| `mysql-security` | `docker.io/library/mysql:lts` | Datenbank — Security-Track (Profil: security) | — | Named Vol → `/var/lib/mysql` |

### Netzwerk-Topologie

```
webtrees-test-net (Bridge)
├── webtrees    ←→  mysql           (PDO, Port 3306)
├── webtrees    →   otel-collector  (OTLP HTTP, Port 4318)
├── playwright  →   webtrees        (HTTP + traceparent, Port 80)
├── playwright  →   otel-collector  (OTLP HTTP, Port 4318)
├── browser     →   otel-collector  (OTLP HTTP, Port 4318, via otel-collector-Hostname)
├── otel-collector → jaeger         (OTLP gRPC, Port 4317 intern)
└── adminer     →   mysql           (Port 3306)

Security-Container (Profil: security, gleiches Netzwerk)
├── webtrees-security ←→ mysql-security  (PDO, Port 3306)
└── playwright        →  webtrees-security (HTTP, Port 80)
```

### MySQL-Konfiguration

```yaml
environment:
  MYSQL_ROOT_PASSWORD: webtrees_test
  MYSQL_DATABASE: webtrees_test
  MYSQL_USER: webtrees
  MYSQL_PASSWORD: webtrees_test
command: >
  --character-set-server=utf8mb4
  --collation-server=utf8mb4_bin
  --performance-schema-instrument='stage/%=ON'
  --performance-schema-consumer-events-stages-current=ON
  --performance-schema-consumer-events-stages-history=ON
```

Collation `utf8mb4_bin` entspricht `DB::COLLATION_UTF8[DB::MYSQL]` in webtrees Core.
PerfSchema-Flags aktivieren Stage-Instrumentierung für `make perfschema-extract`.

### setup-webtrees.sh — Automatischer Installer

1. `composer install` + `composer require` OTel-Pakete im Container (bedingt auf `OTEL_SDK_DISABLED != true`)
2. `data/config.ini.php` generieren (MySQL-Credentials des Containers)
3. DB-Migration via `MigrationService::updateSchema()`
4. Privacy-Fixture generieren (Template-Substitution inline via Bash, `__YEAR_MINUS_N__`-Platzhalter)
5. GEDCOM-Import: `demo.ged` → Baum `demo`, `gedcom-l-muster.ged` → Baum `muster`, `privacy-test.ged` → Baum `privacy`
6. Test-User anlegen: Admin (`admin`), 4 rollenbasierte User (member, editor, moderator, manager), Relationship-Privacy-User (`test-relationship`)
7. OtelSpansModule bereitstellen: Das Modul aus `modules/otel-spans/` wird per Bind-Mount in `compose.yaml` als `modules_v4/otel_spans` im Container verfügbar gemacht (Zeile: `./modules/otel-spans:/var/www/html/modules_v4/otel_spans:ro,z`). webtrees erkennt das Modul automatisch beim nächsten Request. Das OtelSpansModule ist eine Voraussetzung für die Komponentenintegrationstests (Layer 3), da es semantische Spans und den Server-Timing-Header erzeugt, die von der OTel-Trace-Kette (N6) benötigt werden.

Der Setup-Wizard wird vollständig umgangen (programmatischer Install).

