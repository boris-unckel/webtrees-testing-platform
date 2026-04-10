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
make test-unit                # Layer 2 — Upstream-Tests (SQLite in-memory)
make test-integration         # Layer 3 — Eigene Integrationstests (MySQL)
make test-integration-quick   # Layer 3 — Schnelllauf (Smoke-Subset)
make test-e2e-quick           # Layer 4 — Schnelllauf (30 Tests, OTel-Korrelation)
make test-all                 # Alle Stufen sequenziell
make down                     # Stack herunterfahren
```

## Optionales Modul-Mounting

Ein Modul aus einem anderen Repo (z. B. `webtrees-db-recaptcha`) kann in den laufenden Stack eingehängt werden:

```bash
MODULE_PATH=/pfad/zum/modul-repo/webtrees-db-recaptcha \
  MODULE_NAME=db_recaptcha \
  make test-integration
```

Der Pfad `MODULE_PATH` zeigt auf das Repo-Root des Moduls. `MODULE_NAME` ist der Ordnername unter `modules_v4/`.

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
| Layer 2 | Upstream-Unit-Tests (SQLite in-memory) | `make test-unit` |
| Layer 3 | Eigene Integrationstests (MySQL) | `make test-integration` |
| Layer 4 | E2E-Tests (Playwright) | `make test-e2e` |
| Layer 5 | Performance-Tests | `make test-performance` |

## Abhängigkeiten

| Pfad | Zweck |
|---|---|
| `./upstream/webtrees` (Default) oder `${WEBTREES_SOURCE}` | webtrees-Source (read-only Mount in den Container, automatisch geklont) |
| `../webtrees-db-*/` | Module unter Test (optional via `MODULE_PATH`) |

## Testausführung — Parallelitäts- und Timeout-Regeln

**Exklusive Ausführung:** Es darf immer nur genau eine Teststufe gleichzeitig laufen und von einer Teststufe nur genau ein Lauf gleichzeitig. Die Container teilen sich Zustand (MySQL, webtrees-Daten) — parallele Läufe erzeugen Race-Conditions und unvorhersehbare Ergebnisse.

**Keine Timeout-Limits auf lang laufende Tests:** Die Komponentenintegrationstests (Layer 3) und Systemtests (Layer 4) können deutlich länger als 10 Minuten dauern. Das Bash-Tool hat ein Maximum von 600 s — das reicht für diese Tests nicht aus. Deshalb:

- Lang laufende Tests (`make test-integration`, `make test-integration-quick`, `make test-e2e`, `make test-e2e-quick`, `make test-all`) immer mit `run_in_background: true` starten und auf die Fertigmeldung warten.
- **Kein** `timeout`-Parameter setzen, der die Laufzeit künstlich beschränkt.

**Iteratives Test-/Fixing-Vorgehen:** Vor dem Start eines neuen Testlaufs sicherstellen, dass kein vorheriger Lauf noch aktiv ist. Wenn ein vorheriger Lauf noch läuft:

1. Entweder auf dessen Abschluss warten, oder
2. den laufenden Prozess gezielt beenden — PID **im Container** ermitteln und dort killen:
   ```bash
   podman-compose exec webtrees pgrep -a -f phpunit   # PID ermitteln
   podman-compose exec webtrees kill <PID>             # Prozess beenden
   ```

Niemals einen neuen Testlauf starten, während ein alter noch im Container aktiv ist.

## Status-Diagnose und Einzeltest (Layer 3)

Alle PHPUnit-Prozesse laufen **im Container** (`webtrees`) — niemals auf dem Host-System direkt starten oder prüfen.

**Container-Status prüfen:**
```bash
make status                              # Alle Container: Up/Down + Health (= podman-compose ps)
podman-compose logs -f webtrees         # Live-Log des webtrees-Containers
```

**Testlauf-Status im Container:**
```bash
# Läuft gerade ein PHPUnit-Prozess?
podman-compose exec webtrees pgrep -a -f phpunit

# Live-Output des laufenden Testlaufs verfolgen
podman-compose exec webtrees tail -f /artifacts/layer3/phpunit-output.log
```

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

Kein `make`-Target für Einzeltests — `make test-integration` führt immer die Voll-Suite inkl. Coverage aus.

## Sprache

Jedes Repo hat eine feste Sprache. Code, Kommentare, Dokumentation und Commit-Messages richten sich danach:

| Repo | Locale | Geltungsbereich |
|---|---|---|
| `webtrees-testing-platform` (dieses Repo) | **de_DE** | Dokumentation, Kommentare, CLAUDE.md, Commit-Messages |
| `webtrees-upstream/webtrees` (Fork) | **en_GB** | Code, Tests, PHPDoc, Code-Kommentare, BUG-CANDIDATE-Marker |

## Lizenz-Header

Jede neue Sourcecode-Datei muss einen SPDX-Header erhalten. **Die Lizenz hängt vom Ziel-Repo ab:**

| Repo | SPDX-Identifier |
|---|---|
| `webtrees-testing-platform` (dieses Repo) | `AGPL-3.0-or-later` |
| `webtrees-upstream/webtrees` (Fork) | `GPL-3.0-or-later` |

### Dieses Repo (AGPL-3.0-or-later)

| Dateityp | Kommentar-Syntax | Platzierung |
|---|---|---|
| `.md` | `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->` | Erste Zeile |
| `.php` | `// SPDX-License-Identifier: AGPL-3.0-or-later` | Nach `<?php` |
| `.ts` | `// SPDX-License-Identifier: AGPL-3.0-or-later` | Erste Zeile |
| `.sh` | `# SPDX-License-Identifier: AGPL-3.0-or-later` | Nach Shebang |
| `.yaml`/`.yml` | `# SPDX-License-Identifier: AGPL-3.0-or-later` | Erste Zeile |
| `.xml` | `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->` | Nach `<?xml?>` |
| `Makefile` | `# SPDX-License-Identifier: AGPL-3.0-or-later` | Erste Zeile |

### Fork-Repo (GPL-3.0-or-later)

**Wichtig:** Die meisten Upstream-Dateien enthalten bereits einen ausführlichen GPL-Lizenz-Header-Kommentar (mehrzeiliger Boilerplate-Block). In diesem Fall **keinen** SPDX-Identifier ergänzen und den bestehenden Header **nicht** durch SPDX ersetzen. SPDX-Header nur in Dateien einfügen, die **keinen** bestehenden Lizenz-Header haben (neue Dateien oder headerlose Source-Dateien).

| Dateityp | Kommentar-Syntax | Platzierung | Bedingung |
|---|---|---|---|
| `.php` | `// SPDX-License-Identifier: GPL-3.0-or-later` | Nach `<?php` | Nur wenn kein Lizenz-Header vorhanden |
| `.xml` | `<!-- SPDX-License-Identifier: GPL-3.0-or-later -->` | Nach `<?xml?>` | Nur wenn kein Lizenz-Header vorhanden |
| `.md` | `<!-- SPDX-License-Identifier: GPL-3.0-or-later -->` | Erste Zeile | Nur wenn kein Lizenz-Header vorhanden |

## Kein Perl

Perl darf in diesem Projekt nicht verwendet werden — auch nicht als Einzeiler in Shell-Skripten. Textersetzungen und Templating in Bash mit nativen Mitteln (`BASH_REMATCH`, Parameter-Expansion, `sed`) lösen.

## OTel-Stack

Der Stack enthält OTel-Infrastruktur für Distributed Tracing (optional, Standard: aktiv).

- `OTEL_SDK_DISABLED=true` deaktiviert PHP-SDK und Boomerang-Injection vollständig — Zero Overhead.
- Traces: Jaeger UI unter http://localhost:16686
- Protokoll: OTLP HTTP/Protobuf auf Port 4318

`make test-e2e` und `make test-performance` starten automatisch PerfSchema-Truncate,
-Extraktion und Trace-Report (Artefakte unter `artifacts/`).

## Coverage-Iteration (Teststufe 2)

Einstiegspunkt für Coverage-Erweiterungsiterationen: `docs/wf_coverage-to-test_guide.md`

```bash
make crap-report   # CRAP-Score-Tabelle aus artifacts/layer3/coverage.xml (CRAP > 100, 0% Cov.)
```

## Teststrategie-Dokumentation

Einstiegspunkt: `docs/tp_overview_spec.md` — Navigation zu allen Subdokumenten.

## Git

Commits müssen GPG-signiert sein (`commit.gpgsign=true` global gesetzt).
