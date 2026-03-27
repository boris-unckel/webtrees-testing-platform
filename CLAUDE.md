# CLAUDE.md — webtrees-testing-platform

Dieses Repo enthält die **Test-Infrastruktur** für webtrees-Module und den webtrees-Core.

**Nicht der Produktivserver.** Nicht `dombrinksblagen/`. Für Post-Deploy-Tests liegt `smoke-tests/` im Deployment-Repo.

## Kontext

Der Podman-Compose-Stack bringt einen vollständigen webtrees-Stack lokal hoch (Apache, MySQL, optional OpenTelemetry). Die webtrees-Source wird aus `../webtrees-upstream/webtrees/` eingebunden — kein eigener Clone hier.

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
MODULE_PATH=/home/borisunckel/phpprojects/webtrees-db-recaptcha \
  MODULE_NAME=db_recaptcha \
  make test-integration
```

Der Pfad `MODULE_PATH` zeigt auf das Repo-Root des Moduls. `MODULE_NAME` ist der Ordnername unter `modules_v4/`.

## SELinux-Falle (Fedora/rootless Podman)

**Niemals** `podman run -v /pfad/zu/webtrees-upstream/webtrees:/...:Z` (großes `Z`) auf Verzeichnisse, die der Compose-Stack gleichzeitig mountet. Das `:Z`-Flag setzt ein privates SELinux-Label und entzieht dem Compose-Container den Zugriff.

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

| Pfad (außerhalb dieses Repos) | Zweck |
|---|---|
| `../webtrees-upstream/webtrees/` | webtrees-Source (read-only Mount in den Container) |
| `../webtrees-db-*/` | Module unter Test (optional via `MODULE_PATH`) |

## Git

Commits müssen GPG-signiert sein (`commit.gpgsign=true` global gesetzt).
