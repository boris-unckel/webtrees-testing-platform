<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S45: Report-Primitive PDF/HTML

**Referenz:** S45 | **SUT:** `app/Report/ReportPdf*.php` + `app/Report/ReportHtml*.php`  
**Aktueller Test:** `ReportPdfObjectsIntegrationTest` + `ReportHtmlObjectsIntegrationTest` (11 Tests)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Die Tests rufen `render()` auf und prüfen nur, dass keine Exception geworfen wird. Es gibt keine Validierung des erzeugten Outputs (HTML-String, TCPDF-Zustand, Positionierungskoordinaten).

---

## SUT-Kernbefunde

Jede `render()`-Methode hat interne Branching-Logik:

### `ReportPdfTextBox::render()` und `ReportHtmlTextBox::render()`

| Branch | Bedingung | Output-Effekt |
|---|---|---|
| Position: CURRENT vs. statisch | `left === CURRENT_POSITION` | Position von tcpdf->GetX() vs. fester Wert |
| Breite: 0.0 → Auto | `width === 0.0` | Seitenbreite minus Margins |
| Breite: > Seitenbreite | `width > getRemainingWidth()` | Gecappt auf Seitenbreite |
| Seitenumbruch | `pagecheck === true` | `checkPageBreakPDF()` aufgerufen |
| Rahmen + Füllfarbe | hex-Parse `#rrggbb` | RGB via `hexdec()` |
| LTR vs. RTL | `!$renderer->tcpdf->getRTL()` | Margin-Reihenfolge umgekehrt |
| `newline=true` | Nach Rendering nächste Zeile | Y-Position erhöht |
| `newline=false` | Nach Rendering gleiche Zeile | X-Position erhöht |

### `ReportPdfCell::render()` und `ReportHtmlCell::render()`

| Branch | Bedingung | Output-Effekt |
|---|---|---|
| `fill=true` + bgcolor | Füllfarbe setzen | Farbiger Hintergrund |
| `fill=false` | Keine Füllfarbe | Transparenter Hintergrund |
| Border: `'1'` | Vollständiger Rahmen | Alle 4 Seiten |
| Border: `'T'` / `'B'` / `'L'` / `'R'` | Einzelne Seiten | Spezifische Rahmenseiten |
| Seitenumbruch | Höhe > verbleibender Platz | Neue Seite vor Zelle |

---

## Äquivalenzklassen (EP)

### Positions-Parameter

| Klasse | `left`-Wert | Erwartung |
|---|---|---|
| EP1 | `CURRENT_POSITION` | X von tcpdf->GetX() |
| EP2 | `0.0` (statisch) | X = 0 + Margin |
| EP3 | `100.0` (statisch) | X = 100 |

### Farb-Parameter (bgcolor, tcolor, bocolor)

| Klasse | Wert | Erwartung |
|---|---|---|
| EP4 | `'ffffff'` (valide hex) | Weiß gesetzt |
| EP5 | `'000000'` (valide hex) | Schwarz gesetzt |
| EP6 | `''` (leer) | Keine Farbe gesetzt |
| EP7 | `'red'` (ungültig) | Regex-Miss, kein Fehler (graceful) |
| EP8 | `'#ffffff'` (mit #) | Regex parst # optional |

### Breite

| Klasse | Wert | Erwartung |
|---|---|---|
| EP9 | `0.0` | Auto → Seitenbreite |
| EP10 | `10.0` | Exakt 10 |
| EP11 | `10000.0` (> Seite) | Gecappt auf Seitenbreite |

### Border (PDF)

| Klasse | Wert | Erwartung |
|---|---|---|
| EP12 | `''` | Kein Rahmen |
| EP13 | `'1'` | Vollrahmen |
| EP14 | `'T'` | Nur oben |
| EP15 | `'TLBR'` | Alle Seiten einzeln |

---

## Grenzwerte (BVA)

- Breite: 0.0 (auto), 0.1 (minimum), remaining_width (Grenze), remaining_width+1 (gecappt)
- Farbe: `'000000'` (Schwarz), `'ffffff'` (Weiß), `'000001'` (ein off-from-Schwarz)
- Text: '' (leer), 1 Zeichen, langer String der Umbruch erzwingt

---

## Empfohlene Strategie

**ISTQB B** für die klar definierten Branches (Position, Farbe, Border) — diese haben eindeutige Spezifikationen.  
**Pragmatisch C** für RTL und Seitenumbruch — Infrastruktur-Komplexität (TCPDF-Zustand-Verifizierung).

**Schlüsselproblem:** Aktuell keine Möglichkeit, TCPDF-internen Zustand nach `render()` zu inspizieren ohne weitere Test-Infrastruktur. Für HTML-Renderer ist die Ausgabe einfacher zu prüfen (String-Assertionen auf Output-HTML).

**Empfehlung:** Zuerst `ReportHtmlTextBox`/`ReportHtmlCell` verbessern (Output als String prüfbar), dann als Referenz für PDF-Äquivalent.

---

## Konkrete Testideen

```
// HTML-Renderer (direkt prüfbar)
test_html_textbox_render_applies_background_color()
test_html_cell_render_border_one_generates_all_borders()
test_html_cell_render_with_empty_border_has_no_border()

// PDF-Renderer (Smoke + Zustand)
test_pdf_textbox_render_with_pagecheck_does_not_exceed_page()
test_pdf_cell_render_with_fill_color_sets_background()
test_pdf_textbox_auto_width_uses_remaining_page_width()
```

---

## Aufwand

**Hoch** — TCPDF-Zustandsverifizierung (Seitenbreite, aktuelle Position) erfordert Zugriff auf `$renderer->tcpdf->GetX()`/`GetY()` und tiefe Kenntnis der TCPDF-API. HTML-Tests sind **Mittel**.

---

## ReportPdfImage::render() — Branches (Haupt-CRAP-Target, ergänzt P1)

| Branch | Bedingung | Output-Effekt |
|---|---|---|
| PB1 | `checkPageBreakPDF(height+5)` → Seitenumbruch | `$this->y = GetY()` auf neuer Seite |
| X1 | `$this->x === CURRENT_POSITION` | x von tcpdf->GetX() |
| X2 | `$this->x !== CURRENT_POSITION` (statisch) | x = addMarginX($this->x); tcpdf->setX($curx) |
| Y1 | `$this->y === CURRENT_POSITION` | y von tcpdf->GetY() (mit Kollisions-Check) |
| Y1a | Y1 + `lastpicbottom !== null && same page && Kollision` | Y auf lastpicbottom+5 |
| Y2 | `$this->y !== CURRENT_POSITION` (statisch) | tcpdf->setY($this->y) |
| RTL | `getRTL()` | Image an gespiegelter x-Position (pageWidth - x) |
| LTR | `!getRTL()` (Standard) | Image an direkter x-Position |
| N1 | `$this->line === 'N'` | tcpdf->setY(lastpicbottom) — Y rückt auf Unterkante |
| N2 | `$this->line !== 'N'` | kein setY — Y bleibt nach Image() |

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | TextBox/Cell-Spec ↔ Code bestätigt; ReportPdfImage-Branches ergänzt (fehlten in ursprünglicher Spec) |
| P2: Soll-Design | ✅ DONE | HTML: 7 Assertion-Tests (fill/border/newline für TextBox; border='1'/'T'/''/ptp/bgcolor für Cell); PDF: 3 Image-Tests (line='N', statisch, CURRENT) |
| P3: Test-Coding | ✅ DONE | 7 neue HTML-Tests in ReportHtmlObjectsIntegrationTest.php; 3 neue PDF-Image-Tests in ReportPdfObjectsIntegrationTest.php |
| P4: Ausführung + Fixing | ✅ DONE | Voll-Lauf: 552/552 grün, 1804 Assertions. ReportPdfImage::render aus CRAP-Liste eliminiert. |
| P5: Big-Picture | ✅ DONE | Feature-Matrix S45 auf spezifikationsbasiert+strukturbasiert, 23 Tests; Abdeckungsmatrix, Endekriterien, CRAP-Zeile aktualisiert |
