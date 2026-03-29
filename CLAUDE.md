<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# CLAUDE.md — webtrees-testing-platform

Dieses Repo enthält die **Test-Infrastruktur** für webtrees-Module und den webtrees-Core.

**Nicht der Produktivserver.** Nicht das Deployment-Repo. Für Post-Deploy-Tests liegt `smoke-tests/` im Deployment-Repo.

## Kontext

Der Podman-Compose-Stack bringt einen vollständigen webtrees-Stack lokal hoch (Apache, MySQL, optional OpenTelemetry). Die webtrees-Source wird automatisch geklont (`make setup`) oder über `WEBTREES_SOURCE` konfiguriert.

## Kanonischer Testaufruf

```bash
make up          # Stack starten
make setup       # webtrees installieren (einmalig nach up)
make test-unit          # Layer 2 — Upstream-Tests (SQLite in-memory)
make test-integration   # Layer 3 — Eigene Integrationstests (MySQL)
make test-all           # Alle Stufen sequenziell
make down               # Stack herunterfahren
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
| Layer 1 | Statische Analyse (phpstan, phpcs) | `make test-static` |
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

- Lang laufende Tests (`make test-integration`, `make test-e2e`, `make test-all`) immer mit `run_in_background: true` starten und auf die Fertigmeldung warten.
- **Kein** `timeout`-Parameter setzen, der die Laufzeit künstlich beschränkt.

**Iteratives Test-/Fixing-Vorgehen:** Vor dem Start eines neuen Testlaufs sicherstellen, dass kein vorheriger Lauf noch aktiv ist. Wenn ein vorheriger Lauf noch läuft:

1. Entweder auf dessen Abschluss warten, oder
2. den laufenden Prozess gezielt per `kill` beenden (PID über `pgrep -f` ermitteln), bevor der nächste Lauf gestartet wird.

Niemals einen neuen Testlauf starten, während ein alter noch im Container aktiv ist.

## Lizenz-Header

Jede neue Sourcecode-Datei (.php, .ts, .sh, .yaml, .xml, Makefile) und jede neue `.md`-Datei muss einen SPDX-Header erhalten:

| Dateityp | Kommentar-Syntax | Platzierung |
|---|---|---|
| `.md` | `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->` | Erste Zeile |
| `.php` | `// SPDX-License-Identifier: AGPL-3.0-or-later` | Nach `<?php` |
| `.ts` | `// SPDX-License-Identifier: AGPL-3.0-or-later` | Erste Zeile |
| `.sh` | `# SPDX-License-Identifier: AGPL-3.0-or-later` | Nach Shebang |
| `.yaml`/`.yml` | `# SPDX-License-Identifier: AGPL-3.0-or-later` | Erste Zeile |
| `.xml` | `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->` | Nach `<?xml?>` |
| `Makefile` | `# SPDX-License-Identifier: AGPL-3.0-or-later` | Erste Zeile |

## Git

Commits müssen GPG-signiert sein (`commit.gpgsign=true` global gesetzt).
