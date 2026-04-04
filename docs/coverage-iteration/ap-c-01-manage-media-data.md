<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP C-01 — ManageMediaData

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 2 Tests, 6 Assertions, Exit 0 (Fix: media_folder via allMediaFolders() dynamisch bestimmt)

---

## Ziel

Beide Methoden in einer Klasse — eine Testklasse.

| Methode | CRAP | cx |
|---|---|---|
| `handle` | 272 | 16 |
| `mediaObjectInfo` | 110 | 10 |

| | |
|---|---|
| Klasse | `ManageMediaData` |
| Paket | Http/RequestHandlers |
| Quellpfad | `upstream/webtrees/app/Http/RequestHandlers/ManageMediaData.php` |

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Http/RequestHandlers/ManageMediaData.php`

Konstruktor:
- `DatatablesService $datatables_service`
- `LinkedRecordService $linked_record_service`
- `MediaFileService $media_file_service`
- `TreeService $tree_service`

`handle()` liefert Datatable-JSON (AJAX-Handler für Media-Verwaltungsseite).
`mediaObjectInfo()` gibt HTML-Fragment für ein Media-Objekt zurück.

### PHP-Testskelett

Erstelle `layer3-integration/tests/ManageMediaDataIntegrationTest.php`.

Basis: `MysqlTestCase` + `createTreeWithGedcom()` (Media-Records im GEDCOM).

Leere Testmethoden:
- `testHandleReturnsDatatableJson`
- `testMediaObjectInfo`

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'ManageMediaDataIntegrationTest' \
  /tests/layer3-integration/tests/ManageMediaDataIntegrationTest.php
```

### Verifikation

- `handle()` gibt 200 + JSON zurück
- `mediaObjectInfo()` gibt nicht-leeren HTML-String zurück
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
