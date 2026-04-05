<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S46: Homepage-Block-Module

**Referenz:** S46 | **SUT:** `ChartsBlockModule`, `SlideShowModule`, `YahrzeitModule`, `ReviewChangesModule`, `UpcomingAnniversariesModule`, `TopSurnamesModule`, `ResearchTaskModule`, `ClippingsCartModule`  
**Aktueller Test:** `BlockModuleIntegrationTest` (10 Tests: alle geben String/Redirect zurück)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Smoke-Tests: `getBlock()` gibt nicht-null String zurück; `postAddIndividualAction` gibt Redirect zurück. Keine Inhaltsprüfung, keine Edge-Case-Szenarien.

---

## SUT-Kernbefunde

Jedes Block-Modul hat spezifische Branches in `getBlock()`:

| Modul | Schlüssel-Branch | Bisher getestet? |
|---|---|---|
| `SlideShowModule` | Filter nach Medientyp (photo/audio/video) | ❌ |
| `SlideShowModule` | AJAX-Flag → JSON statt HTML | ❌ |
| `YahrzeitModule` | Hebräischer Kalender — Datum-Berechnung | ❌ |
| `YahrzeitModule` | Datumsbereich (wie viele Tage voraus?) | ❌ |
| `ChartsBlockModule` | Chart-Typ-Auswahl (familytree/hourglass/statistics) | ❌ |
| `ChartsBlockModule` | Chart-Modul nicht verfügbar | ❌ |
| `TopSurnamesModule` | Anzeige-Stil (table/list/cloud) | ❌ |
| `TopSurnamesModule` | Leere Namensliste | ❌ |
| `ClippingsCartModule` | `ancestors`-Option (rekursiv) vs. `record` | ✅ (je 1 Test) |
| `ClippingsCartModule` | Download → Content-Disposition | ✅ (1 Test) |
| `ReviewChangesModule` | Keine ausstehenden Änderungen | ❌ |

---

## Äquivalenzklassen (EP)

### `SlideShowModule`

| Klasse | Config | Erwartung |
|---|---|---|
| EP1 | Standardmäßig (alle Medientypen) | HTML mit Bild oder Platzhalter |
| EP2 | AJAX=true | JSON-Antwort |
| EP3 | Kein Bild im Baum | „no media" Ausgabe oder leerer String |

### `TopSurnamesModule`

| Klasse | Config | Erwartung |
|---|---|---|
| EP4 | `style='table'` | HTML-Tabelle in Output |
| EP5 | `style='list'` | HTML-Liste |
| EP6 | `style='cloud'` | HTML-Tag-Cloud |
| EP7 | Keine Nachnamen im Baum | Kein Fehler, leerer Block |

### `ChartsBlockModule`

| Klasse | Config | Erwartung |
|---|---|---|
| EP8 | Chart-Modul verfügbar | Chart-HTML eingebettet |
| EP9 | Chart-Modul deaktiviert | Fallback-Text |

---

## Grenzwerte (BVA)

- `SlideShowModule` AJAX-Flag: `true` vs. `false` (binäre Grenze)
- `TopSurnamesModule` Limit: 0 Namen (leere Liste), 1, 10, viele
- `YahrzeitModule` Datumsbereich: 0 Tage (nur heute), 1, 7, 365

---

## Empfohlene Strategie

**Pragmatisch C** für die meisten Module — kein klares GEDCOM-Standard-Spezifikation für Blocks. Wichtigster Gewinn: **AJAX vs. HTML-Branch** (SlideShow) und **leere Datenlage** (Baum ohne Medien, ohne Nachnamen).  
**ISTQB B** für `TopSurnamesModule` Anzeige-Stile — 3 klar definierte Ausgabe-Formate.

---

## Konkrete Testideen

```
test_slideshow_block_ajax_mode_returns_json()
test_top_surnames_block_table_style_contains_html_table()
test_top_surnames_block_list_style_contains_html_list()
test_top_surnames_block_handles_empty_surname_list()
test_charts_block_handles_unavailable_chart_module()
```

---

## Aufwand

**Mittel** — `getBlock()` nimmt Config-Parameter entgegen; Block-Config in Test setzen und Output per String-Assertion prüfen.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | EP2 (SlideShow AJAX→JSON) FALSCH — loadAjax()=true ist nur Host-Page-Flag, getBlock() gibt immer HTML zurück; TopSurnamesModule: extract($config) überschreibt $info_style → DataProvider testbar; Styles: 'tagcloud' (nicht 'cloud'!), 'list', 'array', 'table'; EP2 gestrichen; EP6 Korrektur 'cloud'→'tagcloud' |
| P2: Soll-Design | ✅ DONE | 1 neuer DataProvider-Test: infoStyles DataProvider (tagcloud/list/array/table) → TopSurnamesModule.getBlock(config=['info_style'=>$style]) assertNotEmpty; SlideShow-Test upgrade assertIsString→assertNotEmpty; EP7 (leere Surnames) aufgeschoben (Pragmatisch C) |
| P3: Test-Coding | ✅ DONE | BlockModuleIntegrationTest.php: +DataProvider import; SlideShow-Test assertIsString→assertNotEmpty; neuer DataProvider infoStyles (4 Styles) + test_top_surnames_block_all_info_styles_return_non_empty_string (assertNotEmpty + HTML-Assertion) |
| P4: Ausführung + Fixing | ✅ DONE | 14/14 grün (10 alt + 4 neue DataProvider-Cases), 41 Assertions; kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix Pragmatisch C, Äquivalenzklassen-Eintrag S46 (EP2-Korrektur: kein AJAX-JSON-Branch), CRAP-Zeile korrigiert (S46 entfernt → S45, S47–S48), Endekriterien, Abdeckungsmatrix, Zusammenfassung 130→131 spec / 9→8 struct, Changelog aktualisiert |
