<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP A-01 — ReportPdfTextBox

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 7 Tests, 14 Assertions, Exit 0 (enthält auch AP B-06 + AP C-04 in `ReportPdfObjectsIntegrationTest`)

---

## Ziel

| | |
|---|---|
| Klasse | `ReportPdfTextBox` |
| Methode | `render` |
| CRAP | 3.660 |
| cx | 60 |
| Paket | Report |
| Quellpfad | `upstream/webtrees/app/Report/ReportPdfTextBox.php` |

Bootstrap-only. Kein DB-Zugriff. Höchster CRAP-Wert dieser Iteration.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Report/ReportBaseTextBox.php` (abstrakte Elternklasse)

Konstruktor `ReportBaseTextBox::__construct` (von `ReportPdfTextBox` geerbt):
- `float $width`
- `float $height`
- `bool $border`
- `string $bgcolor`
- `bool $newline` — folgt Text in neuer Zeile
- `float $left`
- `float $top`
- `bool $pagecheck`
- `string $style` — 'D'=Draw, 'F'=Fill, 'DF'=Draw+Fill, 'CEO', 'CNZ'
- `bool $fill`
- `bool $padding`
- `bool $reseth` — setzt Box-Höhe nach Abschluss zurück

`render(PdfRenderer $renderer)` — braucht `PdfRenderer`-Instanz.

Lies: `upstream/webtrees/app/Report/PdfRenderer.php`
- `PdfRenderer` hat öffentliches `TcpdfWrapper $tcpdf` (initialisiert via `output()`-Methode)
- TCPDF (`tecnickcom/tcpdf`) ist im Container verfügbar (`composer.json`)

### PHP-Testskelett

Erstelle `layer3-integration/tests/ReportPdfTextBoxIntegrationTest.php`.

Basis: `MysqlTestCase` (Bootstrap-only, aber Layer-3 wegen webtrees-Bootstrap).
Kein `createTreeWithGedcom()` nötig.

Leere Testmethoden, korrekte Imports, SPDX-Header. Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'ReportPdfTextBoxIntegrationTest' \
  /tests/layer3-integration/tests/ReportPdfTextBoxIntegrationTest.php
```

### Iteratives Fixing

Root Cause aus Fehlerausgabe lesen → gezielt fixen → Einzeltest wiederholen.

Besonderheit: `PdfRenderer` muss vor dem Test korrekt initialisiert sein.
`PdfRenderer::output()` oder direktes Setzen von `$renderer->tcpdf` nötig.
Falls TCPDF-Header-Ausgabe Probleme macht: ob-Start prüfen.

### Verifikation

- Assertion: `render()` wirft keine Exception; Ergebnis ist ein befülltes `$renderer->tcpdf`-Objekt
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
