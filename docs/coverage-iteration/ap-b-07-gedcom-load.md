<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP B-07 — GedcomLoad

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 2 Tests, 6 Assertions, Exit 0 (Fix: tree via treeService::create in setUp, imported via direktem DB-Update)

---

## Ziel

| | |
|---|---|
| Klasse | `GedcomLoad` |
| Methode | `handle` |
| CRAP | 306 |
| cx | 17 |
| Paket | Http/RequestHandlers |
| Quellpfad | `upstream/webtrees/app/Http/RequestHandlers/GedcomLoad.php` |

DB-abhängig. Chunk-basierter GEDCOM-Import — liest aus `gedcom_chunk`-Tabelle.
FM-Bezug: G21 (Upload-Validierung — bisher nur E2E).

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Http/RequestHandlers/GedcomLoad.php`

Konstruktor:
- `GedcomImportService $gedcom_import_service`
- `TimeoutService $timeout_service`

`handle()` liest aus Request-Attributen:
- `tree` — Tree-Instanz (via Validator::attributes)
- Liest `gedcom_chunk`-Tabelle für `$tree->id()` (via `DB::table('gedcom_chunk')`)

Vor dem Test: GEDCOM-Daten in `gedcom_chunk`-Tabelle einfügen (oder
`TreeService::importGedcomFile()` aufrufen, das die Chunks anlegt).

### PHP-Testskelett

Erstelle `layer3-integration/tests/GedcomLoadIntegrationTest.php`.

Basis: `MysqlTestCase` + `createTreeWithGedcom()` für Tree-Fixture.

Leere Testmethoden:
- `testHandleLoadsChunks` — Tree mit vorhandenen gedcom_chunk-Einträgen

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'GedcomLoadIntegrationTest' \
  /tests/layer3-integration/tests/GedcomLoadIntegrationTest.php
```

### Iteratives Fixing

Root Cause aus Fehlerausgabe lesen → gezielt fixen → Einzeltest wiederholen.

Besonderheit: `gedcom_chunk`-Tabelle muss Daten enthalten, damit `handle()` Coverage bekommt.
Alternative: `TreeService::importGedcom()` als Setup-Schritt, der Chunks anlegt.
`TimeoutService` könnte nach kurzer Zeit abbrechen — ggf. mit großem Timeout initialisieren.

### Verifikation

- Response mit 200 + JSON-Fortschrittsdaten
- Individuals/Families in Tree-DB nach Import
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
