# webtrees Teststrategie

Vollständige Teststrategie für das Open-Source-Projekt [webtrees](https://github.com/fisharebest/webtrees) — PHP-Genealogie-Webapplikation.

**English documentation:** [Reference Manual](REFERENCE_en.md) · [Features & Capabilities](FEATURES_en.md)

## Lizenz

    webtrees-testing-platform — Testinfrastruktur für die webtrees-Genealogie-Webapplikation.
    Copyright (C) 2026  Boris Unckel

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.

## Schnellstart

```bash
git clone https://github.com/<OWNER>/webtrees-testing-platform.git
cd webtrees-testing-platform
make setup                 # klont Upstream, generiert Passwoerter, startet Stack, richtet ein
make test-all              # Alle Teststufen ausfuehren
```

### Passwoerter

Alle Passwoerter (MySQL, webtrees Admin, Test-User) werden beim ersten
`make up` automatisch generiert und in `.env` geschrieben. Manuelle
Anpassungen bleiben bei erneutem `make up` erhalten. `make clean` setzt
alle Passwoerter zurueck, sodass der naechste Start frische Werte erzeugt.

Siehe `scripts/generate-passwords.sh` und `.env.example` fuer Details.

### Eigener webtrees-Checkout

Wer einen vorhandenen webtrees-Checkout nutzen möchte, setzt in `.env`:

```env
WEBTREES_SOURCE=/pfad/zum/vorhandenen/checkout
```

## Teststufen

| Stufe | Befehl | Framework | Beschreibung |
|---|---|---|---|
| Statischer Test | `make test-static` | PHPStan + PHPCS | Typfehler, Coding Standard (PSR-12) |
| Komponententest | `make test-unit` | PHPUnit (SQLite) | Isolierte Klassen, Coverage via pcov |
| Komponentenintegrationstest | `make test-integration` | PHPUnit (MySQL) | Echte DB, GEDCOM-Import, Beziehungen |
| Komponentenintegrationstest (Quick) | `make test-integration-quick` | PHPUnit (MySQL) | Schnelllauf — Smoke-Subset |
| Systemtest | `make test-e2e` | Playwright | Login, Navigation, Theme-Matrix |
| Systemtest (Quick) | `make test-e2e-quick` | Playwright | 30 Tests, OTel-Korrelation verifiziert |
| Performanztest | `make test-performance` | Playwright | Baseline-Vergleich (+20% Schwellwert) |

## Bekannte Abweichungen gegen webtrees `main`

Die Plattform testet bewusst gegen das aktuelle webtrees-`main` (kein Tag-Pin), um Module/Extensions gegen Latest zu prüfen. Dabei können webtrees-**eigene** Tests umgebungsabhängig fehlschlagen, ohne dass ein Defekt der Plattform oder des getesteten Moduls vorliegt:

- **`ReportRegressionTest::testReportHtmlOutputMatchesSnapshot`** (`@individual_report`, `@individual_ext_report`), Layer 2 — *Stand 2026-06-13, beobachtet auf webtrees `main` @ [`7ed6f48`](https://github.com/fisharebest/webtrees/commit/7ed6f4884983ba4d26aa5564b625b66aff022fbb).*
  Der byte-genaue Snapshot-Vergleich scheitert allein am eingebetteten JPEG: GD/libjpeg in diesem Container schreibt das Kommentarsegment `CREATOR: gd-jpeg v1.0 (using IJG JPEG v62), quality = 70`, das der gespeicherte Snapshot nicht enthält. Reiner libjpeg-Toolchain-Unterschied gegenüber der webtrees-Snapshot-Umgebung — kein Inhalts- oder Layout-Fehler. Test Upstream eingeführt in [`f8617fc`](https://github.com/fisharebest/webtrees/commit/f8617fc1bf947c33865145b4d8afab79c8aac490) (2026-06-01).

## Container-Stack

6 Container im Podman Compose Stack:

- **webtrees** — PHP 8.5 + Apache + OTel SDK (Port 8080)
- **mysql** — MySQL LTS (8.4) (Port 3306)
- **playwright** — Node.js 22 + Chromium
- **otel-collector** — OpenTelemetry Sidecar 0.154.0 (Inbound OTLP HTTP :4318; intern gRPC an Jaeger)
- **jaeger** — Trace-UI 2.19.0 (Port 16686)
- **adminer** — DB-Admin, nur Debug-Profil (Port 8081)

## Fehleranalyse

```bash
# Artefakte sammeln + Claude Code CLI starten
./scripts/analyze-failure.sh      # Alle Teststufen
./scripts/analyze-failure.sh 3    # Nur Komponentenintegrationstest
```

## Weitere Befehle

```bash
make help          # Alle Targets anzeigen
make logs          # Container-Logs (webtrees + alle)
make status        # Container-Status: Up/Down + Health
make mysql-shell   # MySQL-Shell
make db-dump       # DB nach artifacts/ exportieren
make clean         # Stack + Volumes löschen
make test-e2e-quick         # Systemtest-Schnelllauf (30 Tests)
make trace-report           # OTel-Trace-Auswertung (artifacts/)
make perfschema-extract     # MySQL PerfSchema-Daten extrahieren
```

### Einzelnen Integrationstest ausführen

Kein `make`-Target für Einzeltests — direkt per `podman-compose exec` im Container:

```bash
# Testklasse (ohne Coverage)
podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter='MeineTestklasse'

# Testmethode
podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter='MeineTestklasse::test_methode'
```

### Testlauf-Status im Container prüfen

Alle PHPUnit-Prozesse laufen im Container `webtrees`, nicht auf dem Host:

```bash
podman-compose exec webtrees pgrep -a -f phpunit                           # Läuft ein Testlauf?
podman-compose exec webtrees kill <PID>                                    # Abbrechen
```

## Dokumentation

- **Teststrategie**: `docs/tds_conditions_ref.md`
- **Feature-Matrizen**: Siehe in Teststrategie

## webtrees & Lizenzkontext

Diese Plattform testet [webtrees](https://github.com/fisharebest/webtrees) (GPL-3.0-or-later), enthält aber **keinen** webtrees-Quellcode: Der Upstream wird zur Laufzeit per `make setup` geklont (read-only Mount; `upstream/` ist `.gitignore`d). Die Test-Infrastruktur selbst steht unter AGPL-3.0-or-later (siehe `LICENSE.md`).

Sicherheitsbefunde, die das Audit-Framework dieses Repos im webtrees-Core aufgedeckt hat, wurden an den Upstream gemeldet und in **webtrees 2.2.6** (2026-04-29) adressiert — Details: `docs/security-audit/10_fixing_and_disclosure.md`.

## Mitwirken & Sicherheit

- Beitragshinweise: `CONTRIBUTING.md`
- Sicherheitsmeldungen: `SECURITY.md`

