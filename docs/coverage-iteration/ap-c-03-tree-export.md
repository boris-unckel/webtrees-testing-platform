<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP C-03 — TreeExport (CLI)

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 2 Tests, 5 Assertions, Exit 0 (Fix: chdir('/tmp') vor execute — Container-Rootfs read-only)

---

## Ziel

| | |
|---|---|
| Klasse | `TreeExport` |
| Methode | `execute` |
| CRAP | 240 |
| cx | 15 |
| Paket | Cli/Commands |
| Quellpfad | `upstream/webtrees/app/Cli/Commands/TreeExport.php` |

CLI, DB-abhängig. Ergänzt AP B-02/B-05 CLI-Infrastruktur. Exportiert Tree als GEDCOM.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Cli/Commands/TreeExport.php`

Klasse erbt von `AbstractCommand` — kein eigener Konstruktor.
Testbar via `CommandTester`.

Argumente: `tree-name`, Optionen für Dateiformat/Ausgabepfad (aus `configure()` entnehmen).

### PHP-Testskelett

Erstelle `layer3-integration/tests/TreeExportCommandIntegrationTest.php`.

Basis: `MysqlTestCase` (Tree `demo` aus Setup vorhanden).

Leere Testmethoden:
- `testExportTreeToStdout`
- `testExportTreeToFile`

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'TreeExportCommandIntegrationTest' \
  /tests/layer3-integration/tests/TreeExportCommandIntegrationTest.php
```

### Verifikation

- `CommandTester::getStatusCode()` === 0
- Ausgabe enthält `0 HEAD` oder `0 TRLR` (GEDCOM-Marker)
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
