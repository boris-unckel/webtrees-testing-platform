<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP B-02 — UserEdit (CLI)

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 3 Tests, 8 Assertions, Exit 0

---

## Ziel

| | |
|---|---|
| Klasse | `UserEdit` |
| Methode | `execute` |
| CRAP | 552 |
| cx | 23 |
| Paket | Cli/Commands |
| Quellpfad | `upstream/webtrees/app/Cli/Commands/UserEdit.php` |

DB-abhängig. Erster AP der CLI-Gruppe — legt `CommandTester`-Muster für AP B-04 und AP C-03 fest.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Cli/Commands/UserEdit.php`

Konstruktor:
- `UserService $user_service`
- `parent::__construct()` (erbt `AbstractCommand` → `Symfony\Component\Console\Command\Command`)

Testbarkeit: via `Symfony\Component\Console\Tester\CommandTester`.
Kein HTTP-Stack nötig — nur webtrees-Bootstrap + DB.

### PHP-Testskelett

Erstelle `layer3-integration/tests/UserEditCommandIntegrationTest.php`.

Basis: `MysqlTestCase` (DB für UserService benötigt).

Leere Testmethoden:
- `testCreateUser`: `--create` mit neuen User-Daten
- `testDeleteUser`: `--delete` auf existierenden User
- `testEditUserRealName`: `--real-name` auf existierenden User

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'UserEditCommandIntegrationTest' \
  /tests/layer3-integration/tests/UserEditCommandIntegrationTest.php
```

### Iteratives Fixing

Root Cause aus Fehlerausgabe lesen → gezielt fixen → Einzeltest wiederholen.

Besonderheit: `CommandTester` aus `symfony/console` ist im webtrees-Container verfügbar
(Composer-Abhängigkeit). DB-Verbindung via `MysqlTestCase::setUp()` aktiv.

### Verifikation

- `CommandTester::getStatusCode()` === 0 für erfolgreiche Kommandos
- User existiert / wurde gelöscht nach Ausführung (DB-Assertion via `UserService`)
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
