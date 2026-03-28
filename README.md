# webtrees Teststrategie

Vollständige Teststrategie für das Open-Source-Projekt [webtrees](https://github.com/fisharebest/webtrees) — PHP-Genealogie-Webapplikation.

## Schnellstart

```bash
# 1. Umgebungsvariablen
cp .env.example .env

# 2. Stack starten + webtrees einrichten
make setup

# 3. Alle Tests ausführen
make test-all
```

## Teststufen

| Stufe | Befehl | Framework | Beschreibung |
|---|---|---|---|
| Statischer Test | `make test-static` | PHPStan + PHPCS | Typfehler, Coding Standard (PSR-12) |
| Komponententest | `make test-unit` | PHPUnit (SQLite) | Isolierte Klassen, Coverage via pcov |
| Komponentenintegrationstest | `make test-integration` | PHPUnit (MySQL) | Echte DB, GEDCOM-Import, Beziehungen |
| Systemtest | `make test-e2e` | Playwright | Login, Navigation, Theme-Matrix |
| Performanztest | `make test-performance` | Playwright | Baseline-Vergleich (+20% Schwellwert) |

## Container-Stack

6 Container im Podman Compose Stack:

- **webtrees** — PHP 8.5 + Apache + OTel SDK (Port 8080)
- **mysql** — MySQL 8.0 (Port 3306)
- **playwright** — Node.js 22 + Chromium
- **otel-collector** — OpenTelemetry Sidecar (Port 4317)
- **jaeger** — Trace-UI (Port 16686)
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
make logs          # Container-Logs
make status        # Container-Status
make mysql-shell   # MySQL-Shell
make db-dump       # DB nach artifacts/ exportieren
make clean         # Stack + Volumes löschen
```

## Dokumentation

- **Teststrategie**: `docs/testing-bigpicture.md`
- **Feature-Matrizen**: G01–G23 (GEDCOM), S01–S25 (Suche/Navigation)
- **Architektur**: `docs/webtrees-architecture.md`
