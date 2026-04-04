<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP B-04 — MapDataImportAction

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 2 Tests, 6 Assertions, Exit 0 (Fix: Nyholm-UploadedFile-Param error→errorStatus)

---

## Ziel

| | |
|---|---|
| Klasse | `MapDataImportAction` |
| Methode | `handle` |
| CRAP | 420 |
| cx | 20 |
| Paket | Http/RequestHandlers |
| Quellpfad | `upstream/webtrees/app/Http/RequestHandlers/MapDataImportAction.php` |

DB-abhängig. Importiert Ortsdaten in `place_location`-Tabelle via CSV oder Server-Datei.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Http/RequestHandlers/MapDataImportAction.php`

Konstruktor:
- `StreamFactoryInterface $stream_factory`

`handle()` liest aus `parsedBody`:
- `source`: `'client'` oder `'server'`
- `options`: `'add'`, `'addupdate'` oder `'update'`
- Bei `source === 'client'`: `UploadedFile` in `$_FILES['client_file']`
- Bei `source === 'server'`: Pfad zu Server-Datei

### PHP-Testskelett

Erstelle `layer3-integration/tests/MapDataImportIntegrationTest.php`.

Basis: `MysqlTestCase` (place_location-Tabelle via DB::).

Leere Testmethoden:
- `testImportFromClientCsvAdd`: minimale CSV-Datei als UploadedFile, `source=client, options=add`
- `testImportFromClientCsvUpdate`: `options=addupdate`

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'MapDataImportIntegrationTest' \
  /tests/layer3-integration/tests/MapDataImportIntegrationTest.php
```

### Iteratives Fixing

Root Cause aus Fehlerausgabe lesen → gezielt fixen → Einzeltest wiederholen.

Besonderheit: `StreamFactoryInterface` aus PSR-17 — webtrees nutzt `Nyholm\Psr7\Factory\Psr17Factory`.
Request-Bau mit `parsedBody` + `UploadedFile` nötig.

### Verifikation

- Response mit 3xx (Redirect zu MapDataList) nach erfolgreichem Import
- `DB::table('place_location')->count()` > 0 nach Import
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
