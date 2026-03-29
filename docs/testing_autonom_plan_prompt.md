# Planprompt: webtrees-testing-platform autonom machen

## Ziel

Erstelle einen Implementierungsplan, der die webtrees-testing-platform von ihrer
aktuellen Abhängigkeit von lokalen Verzeichnisstrukturen befreit und zu einem
eigenständig nutzbaren Repository macht.

Ein Anwender soll das Repo klonen und mit `make setup && make test-all` einen
vollständigen Testlauf starten können — ohne vorher manuell ein
webtrees-Upstream-Verzeichnis bereitstellen zu müssen.

---

## Ist-Zustand (Analyse)

### 1. Upstream-Source per relativem Pfad eingebunden

Die webtrees-Source wird als bereits vorhandenes Geschwisterverzeichnis erwartet.
Kein automatischer Clone.

**Betroffene Stellen:**

| Datei | Zeile(n) | Referenz | Typ |
|---|---|---|---|
| `compose.yaml` | 14 | `../webtrees-upstream/webtrees:/var/www/html:ro,z` | Volume-Mount |
| `compose.yaml` | 32 | `../webtrees-upstream/webtrees/tests/data:/webtrees-tests-data-seed:ro,z` | Volume-Mount |
| `scripts/build-security-image.sh` | 12 | `${WEBTREES_SOURCE:-${PROJECT_DIR}/../webtrees-upstream/webtrees}` | Bash-Default |
| `CLAUDE.md` | 9, 52, 59 | `../webtrees-upstream/webtrees/` | Doku |
| `docs/testing-bigpicture.md` | 36, 314, 326, 490, 1206–1254 | `../webtrees-upstream/webtrees/` | Doku |

### 2. Hardcodierter Benutzerpfad in Dokumentation

| Datei | Zeile | Referenz |
|---|---|---|
| `CLAUDE.md` | 27 | `MODULE_PATH=/home/borisunckel/phpprojects/webtrees-db-recaptcha` |
| `docs/testing-bigpicture.md` | 1436 | `/home/borisunckel/phpprojects/webtrees-upstream/webtrees/` |

### 3. Historische Referenz auf Herkunftsrepo

| Datei | Zeile | Referenz |
|---|---|---|
| `CLAUDE.md` | 5 | `dombrinksblagen/` |
| `docs/testing-bigpicture.md` | 1491 | `dombrinksblagen/` (Changelog-Eintrag) |

Die Git-History enthält ebenfalls `dombrinksblagen`-Referenzen (Commit-Messages).
Diese sind unveränderlich und nicht zu bearbeiten.

### 4. CI-Workflow weicht vom lokalen Setup ab

`.github/workflows/webtrees-tests.yaml` klont den Upstream dynamisch:
```yaml
- uses: actions/checkout@v4
  with:
    repository: fisharebest/webtrees
    ref: ${{ github.event.inputs.webtrees_ref || 'main' }}
    path: github/webtrees
```

Aber der `working-directory` ist `webtrees-tests` und `compose.yaml` erwartet
`../webtrees-upstream/webtrees/`. Der CI-Pfad (`github/webtrees`) und der
Compose-Pfad divergieren — das CI-Setup ist derzeit nicht konsistent mit der
lokalen Konfiguration.

### 5. Module-Path-Beispiel umgebungsspezifisch

`CLAUDE.md` enthält ein Beispiel mit absolutem Pfad:
```
MODULE_PATH=/home/borisunckel/phpprojects/webtrees-db-recaptcha
```

---

## Anforderungen an den Plan

### A. Automatischer Upstream-Clone

Entwirf ein Konzept, das den webtrees-Upstream automatisch per `git clone`
bereitstellt, wenn er nicht bereits vorhanden ist.

**Konfigurierbare Parameter (Umgebungsvariablen mit sinnvollen Defaults):**

| Variable | Default | Zweck |
|---|---|---|
| `WEBTREES_REPO` | `https://github.com/fisharebest/webtrees.git` | Repository-URL (ermöglicht Forks) |
| `WEBTREES_REF` | `main` | Branch, Tag oder Commit |
| `WEBTREES_SOURCE` | `./upstream/webtrees` (innerhalb des Repos) | Lokaler Pfad zum Checkout |

**Regeln:**
- Der Default-Pfad soll **innerhalb** des Repo-Verzeichnisses liegen (z. B.
  `./upstream/webtrees`), nicht als Geschwisterverzeichnis.
- `./upstream/` muss in `.gitignore` stehen.
- Wenn `WEBTREES_SOURCE` auf ein existierendes Verzeichnis zeigt, wird kein Clone
  durchgeführt (Rückwärtskompatibilität für Nutzer mit eigenem Checkout).
- Wenn der Pfad nicht existiert, wird automatisch geklont.
- Der Clone-Schritt soll idempotent sein.

### B. compose.yaml dynamisieren

Ersetze die hartkodierten `../webtrees-upstream/webtrees`-Pfade in `compose.yaml`
durch Umgebungsvariablen mit Defaults, die auf den neuen Standard-Pfad zeigen.

### C. Makefile-Target für Upstream-Bereitstellung

Ein `make clone-upstream`-Target (oder vergleichbar) soll:
1. Prüfen, ob `WEBTREES_SOURCE` bereits existiert
2. Bei Bedarf `git clone --branch $WEBTREES_REF $WEBTREES_REPO $WEBTREES_SOURCE`
3. Von `make setup` als Abhängigkeit aufgerufen werden

### D. CI-Workflow konsolidieren

Der GitHub-Actions-Workflow soll denselben Mechanismus nutzen wie das lokale Setup:
- Dieselbe `WEBTREES_SOURCE`-Variable
- Dieselben Defaults
- Kein separater `path: github/webtrees`-Checkout, der an `compose.yaml` vorbeigeht

### E. Dokumentation neutralisieren

Alle umgebungsspezifischen Referenzen in CLAUDE.md und docs/ ersetzen:

- `../webtrees-upstream/webtrees/` → neuen Standard-Pfad verwenden
- `/home/borisunckel/...`-Pfade → generische Platzhalter (`/pfad/zum/modul`)
- `dombrinksblagen/`-Bezüge → entfernen oder neutral formulieren
  (außer in Changelog-Einträgen, wo die historische Referenz stehen bleiben kann)

### F. .env.example erweitern

Neue Variablen mit Defaults und Kommentaren in `.env.example` dokumentieren.

### G. README.md aktualisieren

Das Quick-Start-Setup soll den neuen, autonomen Workflow widerspiegeln:
```
git clone <dieses-repo>
cd webtrees-testing-platform
make setup    # klont Upstream automatisch, startet Stack, richtet ein
make test-all
```

---

## Vergleichbare Muster (Referenz für den Plan)

### Muster 1: Eigener CI-Workflow (bereits im Repo)

`.github/workflows/webtrees-tests.yaml` klont den Upstream bereits dynamisch mit
konfigurierbarem `ref`. Dieses Muster soll als Vorlage für das lokale Setup dienen.

### Muster 2: build-security-image.sh (bereits im Repo)

`scripts/build-security-image.sh` unterstützt bereits `WEBTREES_SOURCE` als
Override-Variable mit Fallback. Dieses Muster soll auf compose.yaml und Makefile
übertragen werden.

### Muster 3: MODULE_PATH-Mechanismus (bereits im Repo)

Das optionale Modul-Mounting in `compose.yaml` (Zeile 20) zeigt, wie
Umgebungsvariablen mit Fallback-Default für Volume-Mounts funktionieren:
```yaml
- ${MODULE_PATH:-./.empty-module}:/var/www/html/modules_v4/${MODULE_NAME:-_none}:ro,z
```

---

## Abgrenzung

- **Kein** Rewrite der Test-Layer oder Test-Logik.
- **Kein** Wechsel von Podman auf Docker.
- **Kein** Umbenennen des Repositories.
- **Keine** Änderung an der Git-History (Commit-Messages bleiben wie sie sind).
- Die bestehende Funktionalität (`make test-all`, Module-Mounting, Security-Track,
  OTel-Integration) muss unverändert funktionieren.

---

## Erwartetes Ergebnis

Ein Implementierungsplan (`docs/testing_autonom_plan.md`) mit:
1. Arbeitspaketen (nummeriert, abhängigkeitsgeordnet)
2. Für jedes AP: betroffene Dateien, Art der Änderung, ggf. Code-Skizze
3. Reihenfolge, die jederzeit einen funktionierenden Stand gewährleistet
4. Verifikationsschritt je AP (z. B. `make test-all` nach Compose-Änderung)
5. Risiken und Fallstricke (insb. SELinux, Podman-Bind-Mounts, .gitignore)

---

## Status / Fazit

**Plan erstellt:** `docs/testing_autonom_plan.md`

**11 Arbeitspakete** in abhängigkeitsgeordneter Reihenfolge:

| AP | Datei(en) | Kern der Änderung |
|---|---|---|
| 1 | `.gitignore` | `upstream/` eintragen |
| 2 | `scripts/clone-upstream.sh` | Neues Script (idempotent, 3 Variablen) |
| 3 | `.env.example` | Neue Variablen dokumentiert |
| 4 | `Makefile` | Target `clone-upstream`, `up` hängt davon ab |
| 5 | `compose.yaml` | 2 Pfade → `${WEBTREES_SOURCE:-./upstream/webtrees}` |
| 6 | `scripts/build-security-image.sh` | Default-Fallback anpassen |
| 7 | `.github/workflows/webtrees-tests.yaml` | `working-directory` + separaten Checkout entfernen, `make setup` nutzen |
| 8 | `CLAUDE.md` | 5 Stellen neutralisieren (Pfade, dombrinksblagen, User-Pfad) |
| 9 | `docs/testing-bigpicture.md` | 8 Stellen neutralisieren (+ N2-Baum ergänzen), 1 Changelog bleibt |
| 10 | `README.md` | Schnellstart auf autonomen Workflow umstellen |
| 11 | — | Gesamtverifikation: `make test-all` + Grep-Prüfung |

**Zur Dokumentationsabdeckung:** Prompt-Abschnitt E gibt korrekte Regeln, aber keine
Einzelstellen-Enumeration. Der Plan ergänzt dies: **8 Fundstellen** in
`testing-bigpicture.md` und **5 in CLAUDE.md** sind tabellarisch mit Alt/Neu/Begründung
aufgelistet. Die Changelog-Zeile 1491 (dombrinksblagen) bleibt als historische Referenz
stehen, wie vom Prompt vorgesehen.
