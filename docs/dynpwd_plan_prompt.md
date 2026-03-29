<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Planungsprompt: Dynamische Passwort-Generierung im Test-Stack

## Ziel

Alle statischen Passwörter im Test-Stack eliminieren. Passwörter werden beim
ersten `make up` nach einem `make clean` (oder frischen Clone) generiert.
Kein Fallback (`:-default`, `?: 'fallback'`) mehr — wenn eine Env-Variable
fehlt oder leer ist, bricht der Prozess sofort mit klarer Fehlermeldung ab.

## Entscheidungen

Alle Designentscheidungen sind geklärt:

| # | Frage | Entscheidung | Begründung |
|---|---|---|---|
| E1 | `.env`-Strategie | **Option A: .env selektiv patchen** | Manuelle Anpassungen (OTel, Base-URL) bleiben bei Regenerierung erhalten. Generator ersetzt nur die 6 definierten Passwort-Variablen. |
| E2 | Volume-Konsistenz | **Strategie B: Nur bei clean regenerieren** | Passwörter werden nur generiert, wenn sie in `.env` leer sind (frischer Clone oder nach `make clean`). Existierende nicht-leere Passwörter bleiben unangetastet. |
| E3 | Passwort-Format | **Nur alphanumerisch `[a-zA-Z0-9]`, 24 Zeichen** | Kein Quoting-Risiko in Shell (`-p"$PWD"`), MySQL-CLI, PHP-Strings, YAML. ~143 Bit Entropie — ausreichend für Test-Stack. |
| E4 | Security-Track | **Eigene `MYSQL_SECURITY_*`-Variablen** | Klare Trennung der beiden DB-Instanzen. Kein Risiko, dass sich Test- und Security-Credentials vermischen. |
| E5 | App-Passwörter | **Vollständig dynamisieren** | `WEBTREES_ADMIN_PASSWORD` und `WEBTREES_TEST_USER_PASSWORD` werden generiert. Setup, Tests und E2E lesen aus Env. |

### E1+E2 Konsequenzen: Platzhalter und `make clean`

**Platzhalter = leerer Wert.** In `.env.example` und nach `make clean` stehen die
Passwort-Variablen mit leerem Wert (`MYSQL_ROOT_PASSWORD=`). Keine Pseudo-Tokens
wie `__GENERATED__` — Secret-Scanner in CI/CD oder GitHub-Scanning-Agents würden
solche Strings als potentiellen Leak melden.

**`make clean` setzt exakt 6 benannte Variablen zurück:**

```bash
PASSWORD_KEYS=(
  MYSQL_ROOT_PASSWORD
  MYSQL_PASSWORD
  MYSQL_SECURITY_ROOT_PASSWORD
  MYSQL_SECURITY_PASSWORD
  WEBTREES_ADMIN_PASSWORD
  WEBTREES_TEST_USER_PASSWORD
)
```

Keine Pattern-basierte Erkennung (`*PASSWORD*` o. ä.) — in der `.env` können
projektbezogene Credentials stehen, die nicht zum Test-Stack gehören und nicht
angefasst werden dürfen.

**Abbruchverhalten bei leeren Passwörtern:**
- `compose.yaml`: `${MYSQL_ROOT_PASSWORD}` ohne Fallback → Compose bricht ab
  wenn die Variable leer ist (Compose interpoliert leere Strings, MySQL startet
  nicht mit leerem Root-Passwort).
- Shell-Skripte: `set -euo pipefail` + `${MYSQL_ROOT_PASSWORD}` → `nounset`
  greift nur bei ungesetzten Variablen. Zusätzlicher Guard nötig:
  `[[ -n "${MYSQL_ROOT_PASSWORD}" ]] || { echo "FEHLER: …" >&2; exit 1; }`
- PHP-Code: `getenv('…')` gibt `false` bei fehlender Variable, leeren String bei
  leerem Wert. Beide Fälle abfangen: `getenv('X') ?: throw new \RuntimeException(…)`

## Ist-Zustand

### Bestandsaufnahme statische Passwörter

**Kategorie A — MySQL-Credentials (`webtrees_test` als Default)**

| # | Datei | Zeile(n) | Muster |
|---|---|---|---|
| A1 | `.env` | 5, 8 | `MYSQL_ROOT_PASSWORD=webtrees_test`, `MYSQL_PASSWORD=webtrees_test` |
| A2 | `.env.example` | 5, 8 | identisch zu `.env` |
| A3 | `compose.yaml` | 42, 70, 73, 83 | `${MYSQL_PASSWORD:-webtrees_test}`, `${MYSQL_ROOT_PASSWORD:-webtrees_test}` |
| A4 | `scripts/setup-webtrees.sh` | 16 | `MYSQL_PASSWORD="${MYSQL_PASSWORD:-webtrees_test}"` |
| A5 | `scripts/setup-webtrees.sh` | 154 | `getenv('MYSQL_PASSWORD') ?: 'webtrees_test'` (PHP-Inline) |
| A6 | `scripts/truncate-perfschema.sh` | 6 | `${MYSQL_ROOT_PASSWORD:-webtrees_test}` |
| A7 | `scripts/extract-perfschema.sh` | 9 | `${MYSQL_ROOT_PASSWORD:-webtrees_test}` |
| A8 | `layer3-integration/run.sh` | 28 | `${MYSQL_PASSWORD:-webtrees_test}` |
| A9 | `layer3-integration/tests/MysqlTestCase.php` | 110 | `getenv('MYSQL_PASSWORD') ?: 'webtrees_test'` |
| A10 | `Makefile` | 113 | `mysql-shell: … -pwebtrees_test webtrees_test` |
| A11 | `Makefile` | 119 | `db-dump: … -pwebtrees_test webtrees_test` |

**Kategorie B — Security-Test-DB (`security_test` hardcoded)**

| # | Datei | Zeile(n) | Muster |
|---|---|---|---|
| B1 | `compose.yaml` | 165 | `MYSQL_ROOT_PASSWORD: security_test` |
| B2 | `compose.yaml` | 168 | `MYSQL_PASSWORD: security_test` |

**Kategorie C — Applikations-Passwörter (webtrees Admin/User: `'password'`, `'admin'`)**

| # | Datei | Zeile(n) | Muster |
|---|---|---|---|
| C1 | `scripts/setup-webtrees.sh` | 184 | `$userService->create('admin', …, 'admin')` |
| C2 | `scripts/setup-webtrees.sh` | 282, 313 | `'password'` als User-Passwort |
| C3 | `layer3-integration/tests/MysqlTestCase.php` | 192 | `'password'` |
| C4 | `layer3-integration/tests/PrivacyTestCase.php` | 91 | `'password'` |

### Aktuelle Architektur

- `.env` ist gitignored, `.env.example` ist committed (Template).
- `compose.yaml` und alle Shell-Skripte verwenden `${VAR:-webtrees_test}`-Fallbacks.
- PHP-Code im Setup und in Tests verwendet `getenv() ?: 'webtrees_test'`.
- Der Security-Track hat komplett eigene hardcoded Credentials.
- `Makefile`-Targets `mysql-shell` und `db-dump` haben Passwörter inline.

## Soll-Zustand

1. **Generierung:** `scripts/generate-passwords.sh` erzeugt zufällige Passwörter
   (alphanumerisch, 24 Zeichen) und patcht sie selektiv in `.env`.
2. **Keine Fallbacks:** Nirgends im Code steht `:-webtrees_test` oder `?: 'webtrees_test'`.
   Wenn die Env-Variable fehlt oder leer ist, Abbruch mit klarer Fehlermeldung.
3. **Single Source of Truth:** Genau eine Stelle schreibt die Passwörter (Generator),
   alle Konsumenten lesen sie aus Env-Variablen.
4. **Compose-Kompatibilität:** `podman-compose` liest `.env` automatisch.
   Passwörter stehen dort vor `podman-compose up`.
5. **Idempotenz:** Wiederholte `make up`-Läufe überspringen die Generierung,
   wenn Passwörter bereits nicht-leer sind. Erst nach `make clean` wird regeneriert.
6. **Kein Perl:** Nur Bash-native Mittel, `openssl`, `tr`, `head`, `sed`.

### Die 6 verwalteten Variablen

| Variable | Kategorie | Konsumenten |
|---|---|---|
| `MYSQL_ROOT_PASSWORD` | A | compose.yaml (mysql), Makefile, PerfSchema-Skripte |
| `MYSQL_PASSWORD` | A | compose.yaml (webtrees, mysql), Setup-Skript, Layer-3-Tests, run.sh |
| `MYSQL_SECURITY_ROOT_PASSWORD` | B | compose.yaml (mysql-security) |
| `MYSQL_SECURITY_PASSWORD` | B | compose.yaml (mysql-security) |
| `WEBTREES_ADMIN_PASSWORD` | C | Setup-Skript, MysqlTestCase.php, Playwright (E2E-Login) |
| `WEBTREES_TEST_USER_PASSWORD` | C | Setup-Skript, MysqlTestCase.php, PrivacyTestCase.php, Playwright |

## Randbedingungen

### R1: Zeitpunkt der Generierung

Der Generator muss **vor** `podman-compose up` laufen, da Compose `.env` beim
Start liest. Im Makefile: `up` hängt von `generate-passwords` ab.

### R2: `.env` selektiv patchen (Entscheidung E1)

Der Generator:
1. Falls `.env` nicht existiert: `cp .env.example .env`
2. Iteriert über die 6 `PASSWORD_KEYS`
3. Für jeden Key: prüft ob der Wert leer ist (`^KEY=$`)
4. Falls leer: generiert Passwort, ersetzt per `sed -i`
5. Falls nicht-leer: überspringt (existierendes Passwort bleibt)

Manuelle Anpassungen an anderen Feldern (OTel, Base-URL etc.) bleiben unberührt.

### R3: Compose-Healthcheck

Aktuell (Zeile 83):
```yaml
test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p${MYSQL_ROOT_PASSWORD:-webtrees_test}"]
```

Lösung: Auf passwortlosen Ping umstellen (wie bereits bei `mysql-security`):
```yaml
test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
```
`mysqladmin ping` prüft nur ob der Server antwortet, nicht die Authentifizierung.

### R4: Security-Track (Entscheidung E4)

Eigene Variablen `MYSQL_SECURITY_ROOT_PASSWORD` und `MYSQL_SECURITY_PASSWORD`
in `.env.example` (leer) und `.env` (generiert). `compose.yaml` referenziert
sie im `mysql-security`-Service.

### R5: App-Passwörter (Entscheidung E5)

Neue Variablen `WEBTREES_ADMIN_PASSWORD` und `WEBTREES_TEST_USER_PASSWORD`.

Konsumenten im Container brauchen die Variablen in ihrer `environment:`-Sektion:
- `webtrees`-Service: für `setup-webtrees.sh` und Layer-3-Tests
- `playwright`-Service: für E2E-Login-Tests

### R6: Makefile-Targets mit Inline-Credentials

`mysql-shell` und `db-dump` verwenden aktuell hardcoded Passwörter.
Lösung: `include .env` im Makefile-Header, dann `$(MYSQL_PASSWORD)` etc.
in den Rezepten. `include` erfordert, dass `.env` existiert — passt zum
Ablauf, da `mysql-shell` und `db-dump` nur nach `make up` sinnvoll sind.

Variante: `-include .env` (mit Dash) um Fehler bei fehlendem File zu
unterdrücken, da nicht alle Targets `.env` brauchen (z. B. `make help`).

### R7: Volume-Konsistenz (Entscheidung E2)

`make clean`:
1. Setzt die 6 Passwort-Variablen in `.env` auf leer (explizite `sed`-Liste)
2. Löscht Volumes (`podman-compose down -v`)

Danach: `make up` → Generator erkennt leere Werte → generiert frische Passwörter
→ MySQL startet mit frischen Volumes + frischen Passwörtern → konsistent.

Ohne `make clean`: `make up` → Passwörter nicht leer → Generator überspringt
→ gleiche Passwörter, gleiche Volumes → konsistent.

## Betroffene Dateien (vollständige Liste)

| Datei | Änderungsart |
|---|---|
| `.env.example` | Passwort-Felder auf leer setzen, neue Variablen hinzufügen, Kommentar |
| `.env` (gitignored) | Wird durch Generator gepatcht, nicht manuell gepflegt |
| `compose.yaml` | Fallbacks entfernen (`${VAR}` statt `${VAR:-default}`), Healthcheck umstellen, Security-Track auf eigene Variablen, App-Passwörter an webtrees+playwright durchreichen |
| `scripts/setup-webtrees.sh` | Fallbacks entfernen, Guards einbauen, App-Passwörter aus `WEBTREES_ADMIN_PASSWORD` / `WEBTREES_TEST_USER_PASSWORD` lesen |
| `scripts/truncate-perfschema.sh` | Fallback `:-webtrees_test` entfernen |
| `scripts/extract-perfschema.sh` | Fallback `:-webtrees_test` entfernen |
| `layer3-integration/run.sh` | Fallback `:-webtrees_test` entfernen |
| `layer3-integration/tests/MysqlTestCase.php` | `?: 'webtrees_test'` und `'password'` entfernen, Env-Variable ohne Fallback |
| `layer3-integration/tests/PrivacyTestCase.php` | `'password'` durch Env-Variable ersetzen |
| `Makefile` | `-include .env`, Generator-Target als Dep von `up`, `clean` setzt Passwörter zurück, Inline-Credentials in `mysql-shell`/`db-dump` ersetzen |
| **NEU:** `scripts/generate-passwords.sh` | Passwort-Generator (prüft+patcht `.env`) |

## Umsetzungsschritte

### Schritt 1: `scripts/generate-passwords.sh` erstellen

```bash
#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

ENV_FILE="${1:-.env}"
ENV_EXAMPLE=".env.example"

# Falls .env nicht existiert: aus .env.example erzeugen
if [[ ! -f "${ENV_FILE}" ]]; then
    cp "${ENV_EXAMPLE}" "${ENV_FILE}"
    echo ".env aus .env.example erzeugt."
fi

PASSWORD_KEYS=(
    MYSQL_ROOT_PASSWORD
    MYSQL_PASSWORD
    MYSQL_SECURITY_ROOT_PASSWORD
    MYSQL_SECURITY_PASSWORD
    WEBTREES_ADMIN_PASSWORD
    WEBTREES_TEST_USER_PASSWORD
)

generate_password() {
    openssl rand -base64 32 | tr -dc 'a-zA-Z0-9' | head -c 24
}

GENERATED=0
for key in "${PASSWORD_KEYS[@]}"; do
    # Prüfe ob Key existiert und leer ist
    if grep -q "^${key}=$" "${ENV_FILE}"; then
        pw="$(generate_password)"
        sed -i "s/^${key}=.*/${key}=${pw}/" "${ENV_FILE}"
        echo "  ${key} generiert."
        GENERATED=$((GENERATED + 1))
    fi
done

if [[ "${GENERATED}" -eq 0 ]]; then
    echo "Alle Passwörter bereits gesetzt — keine Änderung."
else
    echo "${GENERATED} Passwörter generiert."
fi
```

### Schritt 2: `.env.example` anpassen

Passwort-Felder auf leeren Wert setzen, neue Variablen hinzufügen:
```
# MySQL (Werte werden automatisch durch make up generiert)
MYSQL_ROOT_PASSWORD=
MYSQL_PASSWORD=

# Security-Track MySQL
MYSQL_SECURITY_ROOT_PASSWORD=
MYSQL_SECURITY_PASSWORD=

# webtrees Applikation (Werte werden automatisch durch make up generiert)
WEBTREES_ADMIN_PASSWORD=
WEBTREES_TEST_USER_PASSWORD=
```

### Schritt 3: Makefile erweitern

```makefile
-include .env
export

generate-passwords: ## Passwörter generieren (falls leer)
	scripts/generate-passwords.sh

up: clone-upstream generate-passwords ## Stack starten
	$(COMPOSE) up -d --build

clean: ## Stack stoppen, Volumes und Passwörter löschen
	$(COMPOSE) down -v
	rm -rf artifacts/layer*/*
	@if [ -f .env ]; then \
	    for key in MYSQL_ROOT_PASSWORD MYSQL_PASSWORD \
	        MYSQL_SECURITY_ROOT_PASSWORD MYSQL_SECURITY_PASSWORD \
	        WEBTREES_ADMIN_PASSWORD WEBTREES_TEST_USER_PASSWORD; do \
	        sed -i "s/^$${key}=.*/$${key}=/" .env; \
	    done; \
	    echo "Passwörter in .env zurückgesetzt."; \
	fi

mysql-shell: ## MySQL-Shell öffnen
	$(COMPOSE) exec mysql mysql -u $(MYSQL_USER) -p"$(MYSQL_PASSWORD)" $(MYSQL_DATABASE)

db-dump: ## Testdatenbank dumpen
	$(COMPOSE) exec mysql mysqldump -u $(MYSQL_USER) -p"$(MYSQL_PASSWORD)" $(MYSQL_DATABASE) > artifacts/db-dump.sql
```

### Schritt 4: `compose.yaml` bereinigen

- Alle `${VAR:-webtrees_test}`-Fallbacks → `${VAR}` (bare)
- Healthcheck: `mysqladmin ping -h localhost` (ohne Passwort)
- `mysql-security`: `MYSQL_ROOT_PASSWORD: ${MYSQL_SECURITY_ROOT_PASSWORD}`,
  `MYSQL_PASSWORD: ${MYSQL_SECURITY_PASSWORD}`
- `webtrees`-Service: `WEBTREES_ADMIN_PASSWORD` und `WEBTREES_TEST_USER_PASSWORD`
  in `environment:` durchreichen
- `playwright`-Service: `WEBTREES_ADMIN_PASSWORD` und `WEBTREES_TEST_USER_PASSWORD`
  in `environment:` durchreichen (für E2E-Login)

### Schritt 5: Shell-Skripte bereinigen

Für jedes Skript:
1. Fallback-Ausdruck `${VAR:-webtrees_test}` → `${VAR}` ersetzen
2. Guard am Anfang: `[[ -n "${VAR}" ]] || { echo "FEHLER: VAR nicht gesetzt" >&2; exit 1; }`

Betroffene Dateien:
- `scripts/setup-webtrees.sh` (Zeile 16 + PHP-Block Zeile 154)
- `scripts/truncate-perfschema.sh` (Zeile 6)
- `scripts/extract-perfschema.sh` (Zeile 9)
- `layer3-integration/run.sh` (Zeile 28)

`setup-webtrees.sh` zusätzlich: PHP-Inline-Block (ab Zeile 123) muss
`WEBTREES_ADMIN_PASSWORD` und `WEBTREES_TEST_USER_PASSWORD` aus Env lesen.
Zeile 184: `getenv('WEBTREES_ADMIN_PASSWORD')` statt `'admin'`.
Zeile 282, 313: `getenv('WEBTREES_TEST_USER_PASSWORD')` statt `'password'`.

### Schritt 6: PHP-Test-Code bereinigen

`layer3-integration/tests/MysqlTestCase.php`:
- Zeile 110: `getenv('MYSQL_PASSWORD') ?: 'webtrees_test'`
  → `getenv('MYSQL_PASSWORD') ?: throw new \RuntimeException('MYSQL_PASSWORD nicht gesetzt')`
- Analog für `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER`
- Zeile 192: `'password'`
  → `getenv('WEBTREES_TEST_USER_PASSWORD') ?: throw new \RuntimeException(…)`

`layer3-integration/tests/PrivacyTestCase.php`:
- Zeile 91: `'password'`
  → `getenv('WEBTREES_TEST_USER_PASSWORD') ?: throw new \RuntimeException(…)`

### Schritt 7: Testen

Verifizierung des kompletten Flows:
1. `rm -f .env` → frischer Zustand
2. `make up` → `.env` aus `.env.example` erzeugt, 6 Passwörter generiert, Stack startet
3. `make setup` → webtrees installiert mit generierten Credentials
4. `make test-integration` → Tests bestehen mit generierten Credentials
5. `make clean` → Volumes gelöscht, 6 Passwörter in `.env` auf leer gesetzt
6. `make up` → neue Passwörter generiert, Stack startet konsistent
7. Manueller Test: Passwort-Feld in `.env` leeren → `make up` generiert nur dieses neu
8. Manueller Test: `.env` löschen + `make setup` ohne `make up` → Abbruch (`.env` fehlt)
