<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S43: Report-Generierung HTTP

**Referenz:** S43 | **SUT:** `app/Http/RequestHandlers/ReportGenerate.php` + `ReportSetupPage.php`  
**Aktueller Test:** `ReportIntegrationTest` (5 Tests: Setup-Seite, HTML-Export, 3 direkte Parser-Aufrufe)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Die Tests decken den HTML-Ausgabepfad für 3 Standard-Berichte ab. Der **PDF-Pfad ist komplett ungetestet**. Der `download`-Modus und die Input-Typ-Branches in `ReportSetupPage` sind ungetestet.

---

## SUT-Kernbefunde

### `ReportGenerate::handle()` — Schlüssel-Branches

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| `format='HTML'` → HtmlRenderer | Standard | ✅ |
| `format='PDF'` → PdfRenderer (TCPDF) | Alternativ-Pfad | ❌ |
| `destination='download'` → Content-Disposition-Header | Download-Modus | ❌ |
| `destination='view'` → Inline-Darstellung | View-Modus | ✅ (implizit) |
| Modul nicht gefunden → Redirect | Ungültiger Berichtsname | ❌ |
| `varnames[]` / `vars[]` nicht synchron | Malformed Input | ❌ |

### `ReportSetupPage::handle()` — Input-Typ-Branches

| Branch | Input-`lookup` | Bisher getestet? |
|---|---|---|
| `'INDI'` | Individuums-Auswahl anzeigen | ❌ |
| `'FAM'` | Familien-Auswahl anzeigen | ❌ |
| `'SOUR'` | Quellen-Auswahl anzeigen | ❌ |
| `'DATE'` | Datum mit I18N-Reformatierung | ❌ |
| `type='text'` | Textfeld | ❌ |
| `type='checkbox'` | Checkbox | ❌ |
| `type='select'` + `I18N::number()` | Select mit magischen Strings | ❌ |

---

## Äquivalenzklassen (EP)

### ReportGenerate `format`

| Klasse | Wert | Erwartung |
|---|---|---|
| EP1 | `format='HTML'` | 200, HTML-Ausgabe |
| EP2 | `format='PDF'` | 200, application/pdf |
| EP3 | `format=''` (leer) | Fallback zu HTML |
| EP4 | `format='JSON'` | Ungültig → ggf. Redirect oder Fehler |

### ReportGenerate `destination`

| Klasse | Wert | Erwartung |
|---|---|---|
| EP5 | `destination='view'` | Inline in Browser |
| EP6 | `destination='download'` | `Content-Disposition: attachment` im Header |

### ReportSetupPage Input-Lookup

| Klasse | Wert | Erwartung |
|---|---|---|
| EP7 | `lookup='INDI'` | Individuums-Auswahl-Komponente gerendert |
| EP8 | `lookup='DATE'` | Datum-Feld mit Calendar-Picker |
| EP9 | `type='select'` + `I18N::number(100)` | Zahl korrekt formatiert |

---

## Grenzwerte (BVA)

- `format`: 'HTML' (valider Anfang), 'PDF' (valides Ende), '' (Fallback-Grenze)
- `destination`: 'download' vs. alles andere (Grenze ist String-Gleichheit)

---

## Empfohlene Strategie

**ISTQB B** für Format/Destination-Branches — klar spezifiziert, keine Infrastruktur nötig.  
**Pragmatisch C** für Input-Lookup-Branches in ReportSetupPage — viele Kombinationen, Fokus auf die wichtigsten ('DATE', 'INDI').

Der **PDF-Pfad** ist der wertvollste ungetestete Branch: TCPDF-Initialisierung, PDF-Output-Pufferung und `application/pdf`-Header.

---

## Konkrete Testideen

```
test_report_generate_pdf_format_returns_pdf_content_type()
test_report_generate_download_sets_content_disposition_header()
test_report_generate_unknown_report_returns_redirect()
test_report_setup_page_date_input_renders_calendar_picker()
test_report_setup_page_individual_lookup_renders_selector()
```

---

## Aufwand

**Mittel** — PDF-Test benötigt TCPDF-Initialisierung (bereits im Container vorhanden). Header-Assertion für `Content-Disposition` ist straightforward.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | `switch($format)` hat `default:+case 'HTML':` kombiniert → EP3/EP4 = EP1 (kein eigener Branch); PdfRenderer: `echo $this->tcpdf->Output('S')` → ob-Capture funktioniert; birth_report: lookup='DATE' + type='select' vorhanden; TCPDF im Container verfügbar; isEnabledByDefault()=true; EP3/EP4 aus Spec gestrichen (Klasse = EP1) |
| P2: Soll-Design | ✅ DONE | 3 neue Tests in ReportIntegrationTest: EP2 format='PDF'→application/pdf, EP6 destination='download'→content-disposition:attachment, B1 unbekannter Report→redirect; EP3/EP4 gestrichen (identische Klasse wie EP1); ReportSetupPage-Lookup-Tests aufgeschoben (Pragmatisch C, geringer Erkenntnisgewinn) |
| P3: Test-Coding | ✅ DONE | ReportIntegrationTest.php erweitert: +import PdfRenderer + RequestMethodInterface; 3 neue Tests: EP2 PDF content-type, EP6 download content-disposition, B1 unknown-redirect |
| P4: Ausführung + Fixing | ✅ DONE | 8/8 grün, 33 Assertions; alle 3 neuen Tests + 5 alte grün; kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Äquivalenzklassen-Eintrag S43, CRAP-Zeile korrigiert (S44–S48), Endekriterien, Abdeckungsmatrix, Zusammenfassung 128→129 spec / 11→10 struct, Changelog aktualisiert |
