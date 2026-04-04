<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP C-05 — MediaFileService::uploadFile

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 2 Tests, 7 Assertions, Exit 0

---

## Ziel

| | |
|---|---|
| Klasse | `MediaFileService` |
| Methode | `uploadFile` |
| CRAP | 210 |
| cx | 14 |
| Paket | Services |
| Quellpfad | `upstream/webtrees/app/Services/MediaFileService.php` |

DB-abhängig. Verarbeitet UploadedFile und verschiebt Datei ins webtrees-Datenverzeichnis.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Services/MediaFileService.php`

Konstruktor:
- `PhpService $php_service`

`uploadFile()` braucht:
- `ServerRequestInterface $request`
- `Tree $tree`
- `UploadedFileInterface $uploaded_file`

Schreibt in `$tree->mediaFilesystem()`.

### PHP-Testskelett

Erstelle `layer3-integration/tests/MediaFileServiceUploadIntegrationTest.php`.

Basis: `MysqlTestCase` + `createTreeWithGedcom()`.

Leere Testmethoden:
- `testUploadImageFile`: Bild-Datei als UploadedFile-Mock

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'MediaFileServiceUploadIntegrationTest' \
  /tests/layer3-integration/tests/MediaFileServiceUploadIntegrationTest.php
```

### Verifikation

- Kein Exception bei Upload
- Datei im Tree-Medienverzeichnis vorhanden
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
