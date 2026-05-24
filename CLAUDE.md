<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# CLAUDE.md — webtrees-testing-platform

Dieses Repo enthält die **Test-Infrastruktur** für webtrees-Module und den webtrees-Core.

**Nicht der Produktivserver.** Nicht das Deployment-Repo. Für Post-Deploy-Tests liegt `smoke-tests/` im Deployment-Repo.

## Kontext

Der Podman-Compose-Stack bringt einen vollständigen webtrees-Stack lokal hoch (Apache, MySQL, optional OpenTelemetry). Die webtrees-Source wird automatisch geklont (`make setup`) oder über `WEBTREES_SOURCE` konfiguriert.

## Kanonischer Testaufruf

```bash
make up                       # Stack starten
make setup                    # webtrees installieren (einmalig nach up)
make test-unit                # Layer 2 — Komponententest (SQLite in-memory)
make test-integration         # Layer 3 — Komponentenintegrationstest (MySQL)
make test-integration-quick   # Layer 3 — Schnelllauf (3 repräsentative Fälle)
make test-e2e-quick           # Layer 4 — Schnelllauf (3 Spec-Dateien, OTel-Korrelation)
make test-all                 # Alle Stufen sequenziell
make down                     # Stack herunterfahren
```

## Make-Targets

Alle Targets mit Kurzbeschreibung: `make help`

### Lifecycle und Abhängigkeiten

**Standard:** `make up` → `make setup` (einmalig) → Tests ausführen → `make down`

**Nach `make clean`:** Volumes, Passwörter und Artefakte sind gelöscht.
Vor dem nächsten Testlauf ist eine vollständige Neueinrichtung nötig: `make up && make setup`.

**Debug-Modus:** `make up-debug` startet zusätzlich Adminer (Port 8081) für DB-Inspektion.

**Security-Tests:** Eigener Lifecycle — `make test-security` baut das Image, startet
Security-Container, testet und räumt auf. Für manuelles Debugging:
`make security-build` → `make security-up` → ... → `make security-down` / `make security-clean`.

### Diagnose

Bei Container-Problemen: `make status` (Health-Check aller Container), `make logs` (Live-Logs).
Für DB-Inspektion: `make mysql-shell`, `make db-dump` (nach `artifacts/`).

### Parametrisierte und versteckte Targets

| Target | Besonderheit |
|---|---|
| `make trace-report` | `RUN_ID=<uuid>` (Pflicht), `LAYER=3\|4\|5` (optional) |
| `make perfschema-extract` | `LAYER=layer3\|layer4\|layer5` |
| `make test-integration-security-<NNN>` | Pattern-Target (z. B. `042`), nicht in `make help` sichtbar, setzt `WEBTREES_SECURITY_TRACE=1` |

### Automatisierung in E2E-/Performance-Targets

`test-e2e`, `test-e2e-quick` und `test-performance` generieren automatisch eine
`TEST_RUN_ID` (UUID) und führen PerfSchema-Truncate, -Extraktion und Trace-Report durch.
Artefakte unter `artifacts/layer<N>/`.

## Konfigurationsvariablen

| Variable | Standardwert | Beschreibung |
|---|---|---|
| `WEBTREES_SOURCE` | `./upstream/webtrees` | Pfad zur webtrees-Source (read-only Mount) |
| `MODULE_PATH` | — | Pfad zum Modul-Repo für optionales Modul-Mounting |
| `MODULE_NAME` | — | Ordnername des Moduls unter `modules_v4/` |
| `OTEL_SDK_DISABLED` | `false` | `true` deaktiviert PHP-SDK und Boomerang-Injection (Zero Overhead) |
| `TRIVY_VERSION` | `0.69.3` | Trivy-Image-Version für `make test-static` |
| `LAYER` | — | Layer-Angabe für `perfschema-extract` (`layer3\|layer4\|layer5`) und `trace-report` (`3\|4\|5`) |
| `RUN_ID` | — | UUID für manuellen `trace-report` (bei E2E-/Performance-Targets automatisch generiert) |

Variablen können via `.env`-Datei oder als Kommandozeilen-Prefix gesetzt werden (z. B. `LAYER=layer3 make perfschema-extract`).

## Optionales Modul-Mounting

Ein Modul aus einem anderen Repo (z. B. `webtrees-db-recaptcha`) kann in den laufenden Stack eingehängt werden:

```bash
MODULE_PATH=/pfad/zum/modul-repo/webtrees-db-recaptcha \
  MODULE_NAME=db_recaptcha \
  make test-integration
```

Der Pfad `MODULE_PATH` zeigt auf das Repo-Root des Moduls. `MODULE_NAME` ist der Ordnername unter `modules_v4/`. Das Modul wird per Bind-Mount in den webtrees-Container eingehängt und steht allen Targets zur Verfügung, die den webtrees-Container nutzen.

## SELinux-Falle (Fedora/rootless Podman)

**Niemals** `podman run -v /pfad/zum/webtrees-source:/...:Z` (großes `Z`) auf Verzeichnisse, die der Compose-Stack gleichzeitig mountet. Das `:Z`-Flag setzt ein privates SELinux-Label und entzieht dem Compose-Container den Zugriff.

Für ad-hoc-Befehle immer `podman-compose exec webtrees php ...` statt `podman run`.

Recovery nach SELinux-Desaster:
```bash
make down && make up && make setup
```

## Layer-Architektur

| Layer | Inhalt | Tool |
|---|---|---|
| Layer 1 | Statische Analyse (PHPStan, PHPCS, Trivy) | `make test-static` |
| Layer 2 | Komponententest — PHPUnit (SQLite in-memory) | `make test-unit` |
| Layer 3 | Komponentenintegrationstest — PHPUnit (MySQL) | `make test-integration` |
| Layer 4 | Systemtest — Playwright (Chromium headless) | `make test-e2e` |
| Layer 5 | Performanztest — Playwright-Metrics + Baseline | `make test-performance` |

## Abhängigkeiten

| Pfad | Zweck |
|---|---|
| `./upstream/webtrees` (Default) oder `${WEBTREES_SOURCE}` | webtrees-Source (read-only Mount in den Container, automatisch geklont) |
| `../webtrees-db-*/` | Module unter Test (optional via `MODULE_PATH`) |

## Testausführung — Parallelitäts- und Timeout-Regeln

**Exklusive Ausführung:** Es darf immer nur genau eine Teststufe gleichzeitig laufen und von einer Teststufe nur genau ein Lauf gleichzeitig. Die Container teilen sich Zustand (MySQL, webtrees-Daten) — parallele Läufe erzeugen Race-Conditions und unvorhersehbare Ergebnisse.

**Keine Timeout-Limits auf lang laufende Tests:** Die Komponentenintegrationstests (Layer 3) und Systemtests (Layer 4) können deutlich länger als 10 Minuten dauern. Das Bash-Tool hat ein Maximum von 600 s — das reicht für diese Tests nicht aus. Deshalb:

- Lang laufende Tests (`make test-integration`, `make test-integration-quick`, `make test-e2e`, `make test-e2e-quick`, `make test-performance`, `make test-security`, `make test-all`) immer mit `run_in_background: true` starten und auf die Fertigmeldung warten.
- **Kein** `timeout`-Parameter setzen, der die Laufzeit künstlich beschränkt.

**Iteratives Test-/Fixing-Vorgehen:** Vor dem Start eines neuen Testlaufs sicherstellen, dass kein vorheriger Lauf noch aktiv ist. Wenn ein vorheriger Lauf noch läuft:

1. Entweder auf dessen Abschluss warten, oder
2. den laufenden Prozess gezielt beenden — PID **im Container** ermitteln und dort killen:
   ```bash
   podman-compose exec webtrees pgrep -a -f phpunit   # PID ermitteln
   podman-compose exec webtrees kill <PID>             # Prozess beenden
   ```

Niemals einen neuen Testlauf starten, während ein alter noch im Container aktiv ist.

## Einzeltest-Ausführung (Layer 3)

Alle PHPUnit-Prozesse laufen **im Container** (`webtrees`) — niemals auf dem Host-System direkt starten oder prüfen.

**Einzelne Testklasse ausführen (ohne Coverage):**
```bash
podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter='BadBotBlockerIntegrationTest'
```

**Einzelne Testmethode:**
```bash
podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter='BadBotBlockerIntegrationTest::test_blocked_user_agent_returns_403'
```

Kein `make`-Target für Einzeltests — `make test-integration` führt immer die Voll-Suite inkl. Coverage aus. PHPUnit schreibt keine Live-Log-Datei; Live-Output ist nur über die aufrufende Shell sichtbar, persistiert bleibt die JUnit-XML nach Testende.

## OTel-Stack

Der Stack enthält OTel-Infrastruktur für Distributed Tracing (optional, Standard: aktiv).

- `OTEL_SDK_DISABLED=true` deaktiviert PHP-SDK und Boomerang-Injection vollständig — Zero Overhead.
- Traces: Jaeger UI unter http://localhost:16686
- Protokoll: OTLP HTTP/Protobuf auf Port 4318

## Coverage-Iteration

Einstiegspunkt: `docs/wf_coverage-to-test_guide.md` — CRAP-Score-Analyse via `make crap-report`.

## Teststrategie-Dokumentation

Einstiegspunkt: `docs/tp_overview_spec.md` — Navigation zu allen Subdokumenten.

## Sprache

Jedes Repo hat eine feste Sprache. Code, Kommentare, Dokumentation und Commit-Messages richten sich danach:

| Repo | Locale | Geltungsbereich |
|---|---|---|
| `webtrees-testing-platform` (dieses Repo) | **de_DE** | Dokumentation, Kommentare, CLAUDE.md, Commit-Messages |
| `webtrees-upstream/webtrees` (Fork) | **en_GB** | Code, Tests, PHPDoc, Code-Kommentare, BUG-CANDIDATE-Marker |

## Lizenz-Header

Jede neue Sourcecode-Datei muss einen SPDX-Header erhalten:

- **Dieses Repo:** `SPDX-License-Identifier: AGPL-3.0-or-later`
- **Fork-Repo (`webtrees-upstream/webtrees`):** `SPDX-License-Identifier: GPL-3.0-or-later`

**Platzierung:** Erste Zeile der Datei. Ausnahmen: `.php` nach `<?php`, `.sh` nach Shebang, `.xml` nach `<?xml?>`.

**Kommentar-Syntax:** Richtet sich nach Dateityp — `//` für `.php`/`.ts`, `#` für `.sh`/`.yaml`/`Makefile`, `<!-- -->` für `.md`/`.xml`.

**Fork-Ausnahme:** Die meisten Upstream-Dateien enthalten bereits einen mehrzeiligen GPL-Boilerplate-Header. Diesen **nicht** durch SPDX ersetzen. SPDX-Header nur in Dateien ohne bestehenden Lizenz-Header einfügen.

## Kein Perl

Perl darf in diesem Projekt nicht verwendet werden — auch nicht als Einzeiler in Shell-Skripten. Textersetzungen und Templating in Bash mit nativen Mitteln (`BASH_REMATCH`, Parameter-Expansion, `sed`) lösen.

## Code-Style für neue Skripte

**Verbindlich** für neu erstellte Skripte. Bestehende Old-World-Skripte werden bei fachlichen Anpassungen refactored.

- **Bash** (`*.sh`): Google Shell Style Guide — https://google.github.io/styleguide/shellguide.html.
  Pflicht-Marker: `#!/usr/bin/env bash`, `set -euo pipefail`, `lower_snake_case`-Funktionen, doppelte Quotes um `"$variable"` außer in `$(...)`-Inneren, `[[ ]]` statt `[ ]`, Zwei-Space-Indent, `main "$@"` als Entry-Point, `readonly` für Konstanten in `UPPER_SNAKE_CASE`. Lokal-Verify: `shellcheck <skript>` (Paket `ShellCheck` aus `dnf`, Stand 2026-05-08: 0.11.0 installiert).

## Git

Commits müssen GPG-signiert sein (`commit.gpgsign=true` global gesetzt).
