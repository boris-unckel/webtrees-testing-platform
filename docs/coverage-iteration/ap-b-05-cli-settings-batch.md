<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP B-05 — CLI-Settings-Batch (UserTreeSetting / TreeSetting / SiteSetting / UserSetting)

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 9 Tests, 18 Assertions, Exit 0 (Fix: Symfony Application-Wrapper für globale --quiet Option)

---

## Ziel

Vier ähnliche CLI-Commands in einer Testklasse — gemeinsame `CommandTester`-Infrastruktur.

| Klasse | Methode | CRAP | cx |
|---|---|---|---|
| `UserTreeSetting` | `execute` | 380 | 19 |
| `TreeSetting` | `execute` | 342 | 18 |
| `SiteSetting` | `execute` | 306 | 17 |
| `UserSetting` | `execute` | 306 | 17 |

| | |
|---|---|
| Paket | Cli/Commands |
| Quellpfade | `upstream/webtrees/app/Cli/Commands/{Klasse}.php` |

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Alle vier Klassen erben von `AbstractCommand` (kein eigener Konstruktor).
`AbstractCommand` erweitert `Symfony\Component\Console\Command\Command`.

Keine Konstruktor-Argumente — direkt instanziierbar mit `new UserTreeSetting()` etc.

Testbar via `Symfony\Component\Console\Tester\CommandTester`.

Operationen (alle vier Commands haben ähnliche Flags):
- `--list` / `-l`: Einstellungen anzeigen
- `--delete` / `-d`: Einstellung löschen
- Argument `setting-name` + `setting-value`: Einstellung setzen

### PHP-Testskelett

Erstelle `layer3-integration/tests/CliSettingsBatchIntegrationTest.php`.

Basis: `MysqlTestCase` (DB für Settings-Tabellen).

Leere Testmethoden:
- `testSiteSettingSetAndList`
- `testTreeSettingSetAndList` (braucht Tree)
- `testUserSettingSetAndList` (braucht User)
- `testUserTreeSettingSetAndList` (braucht User + Tree)
- `testSiteSettingDelete`

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'CliSettingsBatchIntegrationTest' \
  /tests/layer3-integration/tests/CliSettingsBatchIntegrationTest.php
```

### Iteratives Fixing

Root Cause aus Fehlerausgabe lesen → gezielt fixen → Einzeltest wiederholen.

Besonderheit: `TreeSetting`/`UserTreeSetting` brauchen Tree-Name als Argument.
`setup-webtrees.sh` legt Tree `demo` und `muster` an — diese als Fixture nutzen.

### Verifikation

- `CommandTester::getStatusCode()` === 0
- Settings in DB-Tabellen korrekt gesetzt/gelöscht (optional prüfen)
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
