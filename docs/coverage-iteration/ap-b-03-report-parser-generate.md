<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP B-03 — ReportParserGenerate (mehrere Methoden)

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 3 Tests, 11 Assertions, Exit 0 (Fix: colors-Variable in individualExtReportVars ergänzt)

---

## Ziel

Mehrere Methoden in einer Klasse — gemeinsame Testklasse.

| Methode | CRAP | cx |
|---|---|---|
| `relativesStartHandler` | 552 | 23 |
| `addDescendancy` | 272 | 16 |
| `imageStartHandler` | 240 | 15 |
| `factsStartHandler` | 182 | 13 |
| `factsEndHandler` | 182 | 13 |
| `relativesEndHandler` | 182 | 13 |
| `addAncestors` | 156 | 12 |

| | |
|---|---|
| Klasse | `ReportParserGenerate` |
| Paket | Report |
| Quellpfad | `upstream/webtrees/app/Report/ReportParserGenerate.php` |

Grenzfall: Klasse hat DB-Zugriff in anderen Methoden (`factsStartHandler` kann DB rufen),
aber die meisten SAX-Handler-Methoden sind Bootstrap-only im einfachen Pfad.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Report/ReportParserGenerate.php`

Konstruktor (Zeile ~169):
- `string $report` — Pfad zur Report-XML-Datei
- `AbstractRenderer $renderer` — PdfRenderer oder HtmlRenderer
- `array $vars` — Report-Variablen (mindestens `['sortby' => 'NAME', 'pageSize' => 'A4']`)
- `Tree $tree`

Der Konstruktor startet den SAX-Parser und ruft sofort die Handler auf.
Der Test muss eine minimale Report-XML-Datei bereitstellen oder
eine bestehende Berichtsdatei aus `upstream/webtrees/resources/xml/reports/` nutzen.

Prüfe welche Report-XML-Dateien vorhanden sind:
```bash
ls upstream/webtrees/resources/xml/reports/
```

### PHP-Testskelett

Erstelle `layer3-integration/tests/ReportParserGenerateIntegrationTest.php`.

Basis: `MysqlTestCase` + `createTreeWithGedcom()` (Tree-Instanz nötig).

Leere Testmethoden:
- `testParseAncestorReport`: parst `ancestors.xml`-Bericht mit Demo-Individual
- `testParseDescendantReport`: parst `descendants.xml`-Bericht
- `testParseRelativeReport`: prüft relatives-Handler-Pfad

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'ReportParserGenerateIntegrationTest' \
  /tests/layer3-integration/tests/ReportParserGenerateIntegrationTest.php
```

### Iteratives Fixing

Root Cause aus Fehlerausgabe lesen → gezielt fixen → Einzeltest wiederholen.

Besonderheit: SAX-Parser wird im Konstruktor gestartet. Fehler treten schon bei `new ReportParserGenerate(...)` auf.
Minimale Voraussetzungen: gültige Report-XML + PdfRenderer korrekt initialisiert.

### Verifikation

- Kein Exception bei Konstruktoraufruf
- Renderer enthält nach Parsen Output (nicht-leeres PDF/HTML)
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
