# Implementierungsplan: webtrees-testing-platform autonom machen

## Ziel

Das Repository soll ohne externe Verzeichnisabhängigkeiten nutzbar sein.
Nach `git clone` und `make setup && make test-all` läuft ein vollständiger
Testlauf — ohne manuelles Bereitstellen der webtrees-Source.

---

## Variablen-Übersicht

| Variable | Default | Zweck |
|---|---|---|
| `WEBTREES_SOURCE` | `./upstream/webtrees` (relativ zum Repo-Root) | Lokaler Pfad zum webtrees-Checkout |
| `WEBTREES_REPO` | `https://github.com/fisharebest/webtrees.git` | Repository-URL (ermöglicht Forks) |
| `WEBTREES_REF` | `main` | Branch, Tag oder Commit-Ref |

Alle drei Variablen können per `.env`-Datei oder Shell-Umgebung gesetzt werden.

---

## Abhängigkeitsreihenfolge (Übersicht)

```
AP 1  (.gitignore)
  ↓
AP 2  (scripts/clone-upstream.sh)    ← Neues Script, noch nicht aufgerufen
  ↓
AP 3  (.env.example)                 ← Variablen dokumentiert
  ↓
AP 4  (Makefile: clone-upstream → up) ← Script eingebunden
  ↓
AP 5  (compose.yaml dynamisiert)     ← Pfade nutzen WEBTREES_SOURCE
  ↓
AP 6–10 (parallel möglich)
  ├─ AP 6  (build-security-image.sh)
  ├─ AP 7  (CI-Workflow)
  ├─ AP 8  (CLAUDE.md)
  ├─ AP 9  (testing-bigpicture.md)
  └─ AP 10 (README.md)
  ↓
AP 11 (Gesamtverifikation: make test-all)
```

Nach jedem AP bleibt der Stack funktionsfähig.

---

## Arbeitspakete

### AP 1: `.gitignore` erweitern

**Dateien:** `.gitignore`

**Änderung:** Eintrag `upstream/` hinzufügen (der neue Default-Clone-Pfad).

```gitignore
# Upstream-Clone (automatisch via make setup / scripts/clone-upstream.sh)
upstream/
```

**Verifikation:** `git status` zeigt keine unbeabsichtigten neuen Dateien.

---

### AP 2: `scripts/clone-upstream.sh` erstellen

**Dateien:** `scripts/clone-upstream.sh` (neu)

**Anforderungen:**
- Idempotent: Wenn `WEBTREES_SOURCE` bereits existiert → kein Clone
- Konfigurierbar über `WEBTREES_REPO`, `WEBTREES_REF`, `WEBTREES_SOURCE`
- Sinnvolle Defaults (Tabelle oben)
- Exitcode 0 auch wenn bereits vorhanden (Makefile-kompatibel)

**Code-Skizze:**

```bash
#!/usr/bin/env bash
# Klont webtrees-Upstream, wenn nicht bereits vorhanden.
# Idempotent: existierender Checkout wird nicht angetastet.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

WEBTREES_SOURCE="${WEBTREES_SOURCE:-${PROJECT_DIR}/upstream/webtrees}"
WEBTREES_REPO="${WEBTREES_REPO:-https://github.com/fisharebest/webtrees.git}"
WEBTREES_REF="${WEBTREES_REF:-main}"

if [ -d "${WEBTREES_SOURCE}" ]; then
    echo "webtrees-Source vorhanden: ${WEBTREES_SOURCE} (übersprungen)"
    exit 0
fi

echo "webtrees-Source klonen..."
echo "  Repo: ${WEBTREES_REPO}"
echo "  Ref:  ${WEBTREES_REF}"
echo "  Ziel: ${WEBTREES_SOURCE}"

mkdir -p "$(dirname "${WEBTREES_SOURCE}")"
git clone --branch "${WEBTREES_REF}" "${WEBTREES_REPO}" "${WEBTREES_SOURCE}"

echo "Clone abgeschlossen: ${WEBTREES_SOURCE}"
```

**Hinweis:** `--branch` akzeptiert Branches und Tags. Für Commit-Hashes
muss der Nutzer manuell klonen und `WEBTREES_SOURCE` setzen.

**Verifikation:**
```bash
chmod +x scripts/clone-upstream.sh
WEBTREES_SOURCE=./upstream/webtrees scripts/clone-upstream.sh  # klont
WEBTREES_SOURCE=./upstream/webtrees scripts/clone-upstream.sh  # überspringt
```

---

### AP 3: `.env.example` erweitern

**Dateien:** `.env.example`

**Änderung:** Neue Variablen mit Kommentaren und Defaults am Ende einfügen:

```env
# --- Upstream-Source ---
# Pfad zum webtrees-Checkout (Default: ./upstream/webtrees, automatisch geklont)
# WEBTREES_SOURCE=./upstream/webtrees
# Repository-URL (für Forks)
# WEBTREES_REPO=https://github.com/fisharebest/webtrees.git
# Branch, Tag oder Ref
# WEBTREES_REF=main
```

Auskommentiert, damit die Defaults greifen. Nutzer mit eigenem Checkout
setzen `WEBTREES_SOURCE=/pfad/zum/checkout` in ihrer `.env`.

**Verifikation:** `diff .env.example .env` — nur die neuen Kommentare als Differenz.

---

### AP 4: Makefile erweitern

**Dateien:** `Makefile`

**Änderungen:**

1. Neues Target `clone-upstream` hinzufügen
2. `up` und `up-debug` als abhängig von `clone-upstream` deklarieren
3. `.PHONY`-Liste erweitern

**Code-Skizze (Diff):**

```makefile
# Bestehend:
# .PHONY: help up down clean setup ...

# Neu:
.PHONY: help clone-upstream up down clean setup ...

clone-upstream: ## webtrees-Source klonen (falls nicht vorhanden)
	scripts/clone-upstream.sh

up: clone-upstream ## Stack starten (alle Container)
	$(COMPOSE) up -d --build
	@echo "Stack gestartet. webtrees: http://localhost:8080 | Jaeger: http://localhost:16686"

up-debug: clone-upstream ## Stack starten inkl. Adminer (Debug-Profil)
	$(COMPOSE_DEBUG) up -d --build
	@echo "..."
```

**Begründung:** `up` → `clone-upstream` stellt sicher, dass die Source vor
dem ersten `podman-compose up` existiert. Wiederholte Aufrufe sind
idempotent (Script prüft Existenz).

**Verifikation:** `make -n up` zeigt `scripts/clone-upstream.sh` als
ersten Schritt.

---

### AP 5: `compose.yaml` dynamisieren

**Dateien:** `compose.yaml`

**Änderungen:** 2 Volume-Mount-Pfade ersetzen.

| Zeile | Alt | Neu |
|---|---|---|
| 14 | `../webtrees-upstream/webtrees:/var/www/html:ro,z` | `${WEBTREES_SOURCE:-./upstream/webtrees}:/var/www/html:ro,z` |
| 32 | `../webtrees-upstream/webtrees/tests/data:/webtrees-tests-data-seed:ro,z` | `${WEBTREES_SOURCE:-./upstream/webtrees}/tests/data:/webtrees-tests-data-seed:ro,z` |

**Muster-Referenz:** Zeile 20 nutzt bereits `${MODULE_PATH:-./.empty-module}` —
dasselbe Pattern wird für `WEBTREES_SOURCE` übernommen.

**Kommentar-Update (Zeile 13):**
```yaml
# webtrees-Source read-only einbinden (WEBTREES_SOURCE, Default: ./upstream/webtrees)
```

**Verifikation:**
```bash
# Stack neu starten (clone-upstream läuft automatisch)
make down && make up && make setup
# Prüfen, dass webtrees erreichbar ist
curl -f http://localhost:8080/
```

---

### AP 6: `scripts/build-security-image.sh` — Default-Pfad anpassen

**Dateien:** `scripts/build-security-image.sh`

**Änderung (Zeile 12):**

```bash
# Alt:
WEBTREES_SOURCE="${WEBTREES_SOURCE:-${PROJECT_DIR}/../webtrees-upstream/webtrees}"

# Neu:
WEBTREES_SOURCE="${WEBTREES_SOURCE:-${PROJECT_DIR}/upstream/webtrees}"
```

**Begründung:** Gleicher Default wie compose.yaml und clone-upstream.sh.
Das Script unterstützt bereits `WEBTREES_SOURCE` als Override — nur der
Fallback-Pfad ändert sich.

**Verifikation:** `make security-build` (wenn Stack läuft).

---

### AP 7: GitHub-Actions-Workflow konsolidieren

**Dateien:** `.github/workflows/webtrees-tests.yaml`

**Probleme im Ist-Zustand:**

| Problem | Ursache |
|---|---|
| `working-directory: webtrees-tests` existiert nicht | Repo heißt `webtrees-testing-platform`, Checkout liegt im Workspace-Root |
| `path: github/webtrees` passt nicht zu `compose.yaml` | Separater Checkout divergiert vom lokalen Mechanismus |
| `paths: ['webtrees-tests/**']` triggert nie | Kein `webtrees-tests/`-Verzeichnis im Repo |
| Jeder Job wiederholt den webtrees-Checkout identisch | Keine zentrale Lösung |

**Strukturelle Änderungen:**

1. `defaults.run.working-directory` entfernen
2. `on.push.paths` und `on.pull_request.paths` entfernen (oder auf `**` setzen)
3. Separaten `actions/checkout` für webtrees entfernen
4. Stattdessen `WEBTREES_REF` als Umgebungsvariable setzen und `make setup`
   den Clone überlassen
5. Jobs vereinfachen — jeder Job:
   - `actions/checkout@v4` (dieses Repo)
   - `pip install podman-compose`
   - `make setup` (klont Upstream automatisch, startet Stack, richtet ein)
   - `make test-<stufe>`

**Code-Skizze (ein Job als Beispiel):**

```yaml
name: webtrees Tests

on:
  push:
  pull_request:
  workflow_dispatch:
    inputs:
      webtrees_ref:
        description: 'webtrees git ref (branch, tag)'
        default: 'main'

jobs:
  statischer-test:
    name: Statischer Test
    runs-on: ubuntu-latest
    env:
      WEBTREES_REF: ${{ github.event.inputs.webtrees_ref || 'main' }}
    steps:
      - uses: actions/checkout@v4
      - name: Install podman-compose
        run: pip install podman-compose
      - name: Setup
        run: make setup
      - name: Run static analysis
        run: make test-static
      - name: Upload artifacts
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: static-analysis
          path: artifacts/layer1/
          retention-days: 7
```

**Änderungen an jedem Job:**
- `needs:`-Kette bleibt erhalten (sequenzielle Ausführung)
- `env.WEBTREES_REF` auf Job- oder Workflow-Ebene
- Kein separater webtrees-Checkout-Step
- `make setup` statt manueller `podman-compose`-Befehle
- Artifact-Pfade: `webtrees-tests/artifacts/` → `artifacts/` (kein Subdirectory)

**Verifikation:** Workflow-Syntax prüfen:
```bash
# Nur Syntax, kein Push nötig:
gh workflow view webtrees-tests.yaml  # oder: yamllint
```

---

### AP 8: `CLAUDE.md` neutralisieren

**Dateien:** `CLAUDE.md`

**Änderungen im Detail:**

| Zeile | Alt | Neu | Begründung |
|---|---|---|---|
| 5 | `Nicht \`dombrinksblagen/\`.` | Satz entfernen oder reformulieren: `Nicht das Deployment-Repo.` | Referenz auf nicht mehr relevantes Repo |
| 9 | `Die webtrees-Source wird aus \`../webtrees-upstream/webtrees/\` eingebunden — kein eigener Clone hier.` | `Die webtrees-Source wird automatisch geklont (\`make setup\`) oder über \`WEBTREES_SOURCE\` konfiguriert.` | Spiegelt neuen autonomen Mechanismus |
| 27 | `MODULE_PATH=/home/borisunckel/phpprojects/webtrees-db-recaptcha` | `MODULE_PATH=/pfad/zum/modul-repo/webtrees-db-recaptcha` | Generischer Platzhalter |
| 36 | `/pfad/zu/webtrees-upstream/webtrees` | `/pfad/zum/webtrees-source` | Konsistenter Platzhalter |
| 52–60 | Abhängigkeiten-Tabelle: `../webtrees-upstream/webtrees/` | `./upstream/webtrees` (Default) oder `${WEBTREES_SOURCE}` | Neuer Standard-Pfad |

**Zusätzlich:** Abschnitt „Kanonischer Testaufruf" bleibt unverändert — `make setup`
klont automatisch, das ist der gewünschte Workflow.

**Verifikation:** Manuelles Review des gerenderten Markdown.

---

### AP 9: `docs/testing-bigpicture.md` neutralisieren

**Dateien:** `docs/testing-bigpicture.md`

Dies ist das umfangreichste Dokumentations-AP. Der Prompt definiert Regeln
(Abschnitt E), aber keine Einzelstellen-Auflistung. Die folgende Tabelle
ergänzt die vollständige Enumeration:

| Zeile | Kontext | Alt | Neu | Typ |
|---|---|---|---|---|
| 36 | Designentscheidungen-Tabelle | `Separater Branch in \`../webtrees-upstream/webtrees/\`` | `Separater Branch im lokalen webtrees-Checkout (\`${WEBTREES_SOURCE}\`)` | Pfad → Variable |
| 314 | N2 Begründung | `Die webtrees-Source aus \`../webtrees-upstream/webtrees/\` wird per read-only Bind-Mount…` | `Die webtrees-Source (Default: \`./upstream/webtrees\`, konfigurierbar via \`WEBTREES_SOURCE\`) wird per read-only Bind-Mount…` | Pfad → neuer Default |
| 326 | N3 Fixture-Tabelle | `../webtrees-upstream/webtrees/tests/data/demo.ged` | `${WEBTREES_SOURCE}/tests/data/demo.ged` (oder `./upstream/webtrees/tests/data/demo.ged`) | Pfad → Variable |
| 490 | Container-Tabelle | `../webtrees-upstream/webtrees/` → `/var/www/html` (ro) | `${WEBTREES_SOURCE}` → `/var/www/html` (ro) | Pfad → Variable |
| 1206–1209 | Upstream-Contribution Abgrenzung | 4× `../webtrees-upstream/webtrees/` | `${WEBTREES_SOURCE}` (Default `./upstream/webtrees`) | Pfad → Variable |
| 1217 | Upstream-Contribution Vorgehen | `Branch erstellen in \`../webtrees-upstream/webtrees/\`` | `Branch erstellen im lokalen webtrees-Checkout (\`${WEBTREES_SOURCE}\`)` | Pfad → Variable |
| 1254 | Upstream-Contribution Redundanz | `../webtrees-upstream/webtrees/tests/app/` | `${WEBTREES_SOURCE}/tests/app/` | Pfad → Variable |
| 1436 | SELinux Recovery | `chcon -R -l s0 /home/borisunckel/phpprojects/webtrees-upstream/webtrees/` | `chcon -R -l s0 /pfad/zum/webtrees-checkout/` (generisch) | Hardcoded User-Pfad → Platzhalter |
| 1491 | Änderungshistorie | `dombrinksblagen/` | Bleibt stehen (historischer Changelog-Eintrag) | Keine Änderung |

**Zusätzlich:** N2-Verzeichnisbaum um `upstream/` (gitignored) ergänzen.

**Verifikation:** Manuelles Review. `grep -n 'webtrees-upstream\|dombrinksblagen\|/home/borisunckel' docs/testing-bigpicture.md` darf nur die Changelog-Zeile finden.

---

### AP 10: `README.md` aktualisieren

**Dateien:** `README.md`

**Änderung:** Schnellstart-Abschnitt anpassen:

```markdown
## Schnellstart

```bash
git clone <dieses-repo>
cd webtrees-testing-platform
cp .env.example .env       # Defaults anpassen (optional)
make setup                 # klont webtrees-Upstream, startet Stack, richtet ein
make test-all              # Alle Teststufen ausführen
```

Hinweis hinzufügen:

```markdown
### Eigener webtrees-Checkout

Wer einen vorhandenen webtrees-Checkout nutzen möchte, setzt in `.env`:

```env
WEBTREES_SOURCE=/pfad/zum/vorhandenen/checkout
```
```

**Verifikation:** Gerenderte README prüfen (z. B. via `gh repo view --web`).

---

### AP 11: Gesamtverifikation

**Vorgehen:**

1. **Clean-State-Test** (simuliert neuen Nutzer):
   ```bash
   rm -rf upstream/          # Falls vorhanden
   make clean                # Volumes entfernen
   make setup                # Muss Upstream klonen + Stack starten + einrichten
   ```

2. **Testlauf:**
   ```bash
   make test-all             # Alle Stufen: static, unit, integration, e2e, performance
   ```
   Gemäß CLAUDE.md mit `run_in_background: true` ausführen (> 10 min möglich).

3. **Override-Test** (simuliert bestehenden Nutzer):
   ```bash
   WEBTREES_SOURCE=../webtrees-upstream/webtrees make down
   WEBTREES_SOURCE=../webtrees-upstream/webtrees make setup
   WEBTREES_SOURCE=../webtrees-upstream/webtrees make test-unit
   ```

4. **Grep-Prüfung** (keine alten Pfade in Code/Doku):
   ```bash
   grep -rn 'webtrees-upstream' --include='*.yaml' --include='*.sh' --include='*.md' \
     --exclude-dir=upstream --exclude='testing_autonom_plan*.md'
   # Erlaubt: nur Changelog-Einträge in testing-bigpicture.md
   ```

5. **Security-Track:**
   ```bash
   make test-security
   ```

**Erfolgskriterium:** `make test-all` und `make test-security` grün.
Keine hartkodierten Pfade in Code- und Konfigurationsdateien.

---

## Risiken und Fallstricke

### R1: SELinux-Relabeling bei neuem Clone-Pfad

**Risiko:** Der neue Pfad `./upstream/webtrees` bekommt beim ersten
Bind-Mount ein SELinux-Label (`system_u:object_r:container_file_t:s0:c…`).
Bei Podman rootless + SELinux (Fedora) kann ein nachfolgender
`podman-compose down && up` zu MCS-Label-Konflikten führen.

**Mitigation:** Das `:z`-Flag (kleines z) in compose.yaml sorgt für
shared relabeling. Das funktioniert identisch zum bisherigen Mount.
Die bestehende SELinux-Recovery-Dokumentation (CLAUDE.md) gilt auch für
den neuen Pfad.

### R2: Podman-Compose-Variable-Expansion in Volume-Pfaden

**Risiko:** `${WEBTREES_SOURCE:-./upstream/webtrees}/tests/data` wird
von einigen Compose-Implementierungen möglicherweise nicht korrekt
expandiert (Variable + Literal-Suffix).

**Mitigation:** Die bestehende compose.yaml nutzt bereits dasselbe
Pattern für MODULE_PATH (Zeile 20). Getestet mit podman-compose 1.5.0.
Fallback: Separate Variable `WEBTREES_TESTS_DATA` einführen.

### R3: `git clone --branch` akzeptiert keine Commit-Hashes

**Risiko:** Nutzer, die einen bestimmten Commit testen wollen, können
`WEBTREES_REF=abc123` nicht verwenden.

**Mitigation:** Dokumentation in `.env.example`: „Für Commit-Hashes
manuell klonen und `WEBTREES_SOURCE` setzen." Betrifft < 5% der
Anwendungsfälle.

### R4: `.gitignore` schließt `upstream/` aus — aber nicht `./upstream/webtrees/.git`

**Risiko:** Nutzer könnten versehentlich versuchen, Änderungen im
geklonten Upstream zu committen (verschachtelte Git-Repos).

**Mitigation:** `upstream/` in `.gitignore` verhindert, dass das
äußere Repo Dateien aus `upstream/` trackt. Das innere `.git` bleibt
eigenständig. Kein Risiko für das Testing-Repo.

### R5: CI-Laufzeit durch `git clone` statt `actions/checkout`

**Risiko:** `git clone` im CI ist langsamer als `actions/checkout@v4`
(kein Token-Auth, kein Shallow-Clone-Default).

**Mitigation:** `clone-upstream.sh` kann mit `--depth 1` erweitert
werden (eigene CI-Variable). Akzeptabler Trade-off für
Konsistenz zwischen lokal und CI. Der Clone dauert ~30s für webtrees
(~150 MB).

### R6: Bestehende `.env`-Dateien setzen kein `WEBTREES_SOURCE`

**Risiko:** Nach AP 5 nutzt compose.yaml den Default `./upstream/webtrees`.
Nutzer mit bestehendem Setup unter `../webtrees-upstream/webtrees/`
müssen ihre `.env` anpassen oder `make setup` laufen lassen (klont neu).

**Mitigation:** Migrationsleitfaden (siehe unten). `make setup` klont
automatisch — funktioniert auch ohne `.env`-Änderung, nutzt dann den
neuen Default-Pfad.

---

## Migrationsleitfaden (bestehende Nutzer)

### Option A: Neuen Default-Pfad nutzen (empfohlen)

```bash
git pull                   # Neue Makefile/Compose-Targets holen
make clean                 # Alten Stack entfernen
make setup                 # Klont Upstream nach ./upstream/webtrees
make test-all              # Verifizieren
```

Der alte Checkout unter `../webtrees-upstream/webtrees/` bleibt
unangetastet und kann weiterhin für Upstream-Contribution genutzt werden.

### Option B: Bestehenden Checkout weiterverwenden

In `.env` einfügen:

```env
WEBTREES_SOURCE=../webtrees-upstream/webtrees
```

Dann:

```bash
git pull
make clean && make setup && make test-all
```

---

## Änderungshistorie

*Erstellt: 2026-03-29 — Initialer Implementierungsplan. 11 Arbeitspakete,
Abhängigkeitsreihenfolge, Code-Skizzen, Risiko-Analyse, Migrationsleitfaden.
Basiert auf Analyseprompt `docs/testing_autonom_plan_prompt.md`.*
