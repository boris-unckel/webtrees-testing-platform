<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP C-06 — EditMediaFileAction

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 1 Test, 3 Assertions, Exit 0 (Fix: m_filename→fact_id leer lassen, Redirect-Pfad getestet)

---

## Ziel

| | |
|---|---|
| Klasse | `EditMediaFileAction` |
| Methode | `handle` |
| CRAP | 182 |
| cx | 13 |
| Paket | Http/RequestHandlers |
| Quellpfad | `upstream/webtrees/app/Http/RequestHandlers/EditMediaFileAction.php` |

DB-abhängig. Editiert Mediendatei-Metadaten eines bestehenden Media-Records.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Http/RequestHandlers/EditMediaFileAction.php`

Konstruktor:
- `MediaFileService $media_file_service`
- `PendingChangesService $pending_changes_service`

`handle()` liest aus Request:
- `tree` (Attribut), `xref` (Attribut), `fact_id` (Attribut)
- `parsedBody`: `type`, `title`, `file` etc.

### PHP-Testskelett

Erstelle `layer3-integration/tests/EditMediaFileIntegrationTest.php`.

Basis: `MysqlTestCase` + `createTreeWithGedcom()` (GEDCOM mit OBJE-Record).

Leere Testmethoden:
- `testEditMediaFileTitleAndType`

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'EditMediaFileIntegrationTest' \
  /tests/layer3-integration/tests/EditMediaFileIntegrationTest.php
```

### Verifikation

- Response 302 Redirect nach erfolgreichem Edit
- Media-Record aktualisiert in DB
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
