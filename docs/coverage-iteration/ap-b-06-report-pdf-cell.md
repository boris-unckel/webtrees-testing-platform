<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP B-06 — ReportPdfCell

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** Abgedeckt durch `ReportPdfObjectsIntegrationTest` (AP A-01)

---

## Ziel

| | |
|---|---|
| Klasse | `ReportPdfCell` |
| Methode | `render` |
| CRAP | 342 |
| cx | 18 |
| Paket | Report |
| Quellpfad | `upstream/webtrees/app/Report/ReportPdfCell.php` |

Bootstrap-only. Ähnlich AP A-01 (ReportPdfTextBox). Kann parallel zu AP B-02..B-05 als Skelett erstellt werden.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Report/ReportBaseCell.php` (abstrakte Elternklasse)

Konstruktor `ReportBaseCell::__construct` (von `ReportPdfCell` geerbt):
- `float $width` — 0: nutzt verfügbaren Platz bis rechten Rand
- `float $height`
- `string $border` — '': keine, '1': alle, [LRTB]: Kombination
- `string $align` — left/center/right/justify
- `string $bgcolor`
- `string $styleName`
- `int $newline` — 0: rechts, 1: Zeilenanfang, 2: darunter
- `float $top`
- `float $left`
- `bool $fill`
- `int $stretch` — 0–4
- `string $bocolor` — Rahmenfarbe
- `string $tcolor` — Textfarbe
- `bool $reseth`

`render(PdfRenderer $renderer)` — braucht initialisierten `PdfRenderer`.

### PHP-Testskelett

Erstelle `layer3-integration/tests/ReportPdfCellIntegrationTest.php`.

Basis: `MysqlTestCase` (Bootstrap-only, kein createTreeWithGedcom()).

Leere Testmethoden:
- `testRenderSimpleCell`
- `testRenderWithBorderAndFill`

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'ReportPdfCellIntegrationTest' \
  /tests/layer3-integration/tests/ReportPdfCellIntegrationTest.php
```

### Iteratives Fixing

Wie AP A-01: `PdfRenderer`-Initialisierung prüfen. TCPDF muss bereit sein.
`render()` schreibt in `PdfRenderer::$tcpdf`.

### Verifikation

- `render()` wirft keine Exception
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
