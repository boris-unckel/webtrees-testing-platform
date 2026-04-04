<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP C-04 — ReportPdfFootnote + ReportPdfText (getWidth)

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** Abgedeckt durch `ReportPdfObjectsIntegrationTest` (AP A-01)

---

## Ziel

Zwei Bootstrap-only PDF-Klassen in einer Testklasse.

| Klasse | Methode | CRAP | cx |
|---|---|---|---|
| `ReportPdfFootnote` | `getWidth` | 210 | 14 |
| `ReportPdfText` | `getWidth` | 210 | 14 |

| | |
|---|---|
| Paket | Report |
| Quellpfade | `upstream/webtrees/app/Report/ReportPdfFootnote.php`, `ReportPdfText.php` |

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

`ReportPdfFootnote` (erbt `ReportBaseFootnote`):
- `string $styleName` — falls leer: `'footnote'`

`ReportPdfText` (erbt `ReportBaseText`):
- `string $styleName`
- `string $color`

`getWidth(PdfRenderer $renderer)` — braucht initialisierten `PdfRenderer`.

### PHP-Testskelett

Erstelle `layer3-integration/tests/ReportPdfWidthIntegrationTest.php`.

Basis: `MysqlTestCase` (Bootstrap-only).

Leere Testmethoden:
- `testFootnoteGetWidth`
- `testTextGetWidth`

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'ReportPdfWidthIntegrationTest' \
  /tests/layer3-integration/tests/ReportPdfWidthIntegrationTest.php
```

### Verifikation

- `getWidth()` gibt `float` zurück (>= 0)
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
