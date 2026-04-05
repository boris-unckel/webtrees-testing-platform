<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S41: Statistikdaten-Abfragen

**Referenz:** S41 | **SUT:** `app/StatisticsData.php` (+ `app/Statistics.php`)  
**Aktueller Test:** `StatisticsDataIntegrationTest` + `StatisticsIntegrationTest` (10 Tests)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Die Tests prüfen bereits Return-Typen und einige Parameter-Kombinationen (`ageOfMarriageQuery`, `parentsQuery`, `marriageQuery` mit je 2 Permutationen). Strukturell besser als reiner Smoke-Test. Verbesserungspotenzial liegt in der **systematischen EP-Abdeckung der Parameter-Enumerationen** und **Grenzwert-Abdeckung für Jahresranges und Limits**.

---

## SUT-Kernbefunde

`StatisticsData` hat über 20 öffentliche Methoden mit klar definierten Parametern:

| Parameter | Aufzählung / Bereich | Bisher getestet? |
|---|---|---|
| `$sex` (string) | `'F'`, `'M'`, `'ALL'` | ❌ (nur Kreuzprodukte via Statistics) |
| `$age_dir` (string) | `'ASC'`, `'DESC'` | ✅ (je 1 Test) |
| `$type` in parentsQuery | `'full'`, `'age'` | ✅ |
| `$type` in ageOfMarriageQuery | `'name'`, `'age'` | ✅ |
| `$sort` in commonSurnames | `'alpha'`, `'count'`, `'rcount'` | ❌ |
| `$limit` | 1, 10, 1000 | ❌ |
| `$threshold` | 0, 1, 100 | ❌ |
| `$year1`, `$year2` | (0,0), (Y,Y), (Y1>Y2) | ❌ |
| `$event` in countEventsByCentury | `'BIRT'`, `'DEAT'`, `'MARR'`, unbekannt | ✅ (BIRT, DEAT) |

**Schlüssel-Branch:** In `countEventsByCentury` und `countEventsByMonth` gibt es eine bedingte `whereBetween`-Klausel: Wenn `$year1 !== 0 && $year2 !== 0` → Jahresfilter, sonst alle Jahre. Diese ist bisher nicht explizit getestet.

**Sex-Filtering-Branch:** In `commonGivenNames` und `birthAndDeathQuery` gibt es `if ($sex !== 'ALL')` → JOIN auf Individuals-Tabelle. Dieser Zweig ist bisher nicht direkt getestet.

---

## Äquivalenzklassen (EP)

### `parentsQuery($type, $age_dir, $sex, $show_years)`

| Klasse | Werte | Besonderheit |
|---|---|---|
| EP1 | `('full', 'ASC', 'F', false)` | Valide Kombination |
| EP2 | `('full', 'ASC', 'M', false)` | Anderer Sex-Zweig |
| EP3 | `('age', 'DESC', 'F', true)` | show_years = true → formatierte Ausgabe |
| EP4 | Sex='ALL' (falls definiert) | Ggf. ohne Sex-Filter |

### `countEventsByMonth($event, $year1, $year2)`

| Klasse | Werte | Erwartung |
|---|---|---|
| EP5 | `('BIRT', 0, 0)` | Alle Jahre, kein Filter |
| EP6 | `('BIRT', 1900, 2000)` | Gefiltert, whereBetween |
| EP7 | `('BIRT', 1900, 1900)` | Ein Jahr, Grenzfall |
| EP8 | `('BIRT', 2000, 1900)` | Invertierter Bereich → leeres Ergebnis |
| EP9 | `('XXXX', 0, 0)` | Unbekannter Event-Tag → leeres Ergebnis |

### `commonSurnames($limit, $threshold, $sort)`

| Klasse | Werte | Erwartung |
|---|---|---|
| EP10 | `(10, 0, 'alpha')` | Alphabetisch sortiert |
| EP11 | `(10, 0, 'count')` | Nach Häufigkeit |
| EP12 | `(10, 0, 'rcount')` | Umgekehrt nach Häufigkeit |
| EP13 | `(1, 10, 'count')` | Threshold filtert seltene Namen |

---

## Grenzwerte (BVA)

- `$limit`: 0 (leere Collection), 1, PHP_INT_MAX
- `$threshold`: 0 (kein Filter), 1 (mindestens 2 Vorkommen nötig)
- `$year1/$year2`: (0, 0), (1900, 1900), (1900, 2000), (2000, 1900)
- Event-Tag: `'BIRT'` (existiert in Demo), `'XXXX'` (existiert nicht)

---

## Empfohlene Strategie

**ISTQB B** — klare Enum-Parameter, GEDCOM-Standard als Spezifikation. DataProvider-Matrix für Sex × age_dir × type-Kombinationen direkt umsetzbar. Die `countEventsByMonth`-Year-Range-Tests sind besonders wertvoll (bisher komplett fehlend).

---

## Konkrete Testideen

```
test_parents_query_all_sex_combinations(string $sex, ...)  ← DataProvider
test_count_events_by_month_with_year_range_filter()
test_count_events_by_month_inverted_range_returns_empty()
test_count_events_by_month_all_years_when_zero_range()
test_common_surnames_sorted_by_count()
test_common_surnames_sorted_by_rcount()
test_common_surnames_threshold_filters_rare_names()
test_count_events_by_century_unknown_event_returns_empty()
```

---

## Aufwand

**Mittel** — Demo-Fixture enthält Testdaten; DataProvider ergänzen, Year-Range-Tests mit spezifischen Assertionen auf Array-Größe.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt mit Spec überein; EP4 (sex='ALL') → HUSB (gleich wie 'M'), kein eigener Branch; EP8 (inverted range year1>year2) → MySQL BETWEEN leer; commonSurnames 'count'=no-op (bereits DESC-sortiert), 'rcount'=array_reverse; vorhandene Klasse StatisticsDataIntegrationTest erweitern (kein Split) |
| P2: Soll-Design | ✅ DONE | 7 neue Tests in bestehende Klasse: EP5 countEventsByMonth(BIRT,0,0)→non-empty, EP6 countEventsByMonth(BIRT,1900,2000)→array, EP8 countEventsByMonth(BIRT,2100,1900)→empty, commonSurnames DataProvider alpha/count/rcount→array, commonSurnames(threshold=999)→leerer Array oder kleiner als threshold=0, parentsQuery DataProvider F/M→string |
| P3: Test-Coding | ✅ DONE | StatisticsDataIntegrationTest.php erweitert: DataProvider-Import + 7 neue Tests (EP5/EP6/EP8 countEventsByMonth, DataProvider sortTypes EP10-EP12, EP13 high-threshold, DataProvider sexValues EP1/EP2 parentsQuery) |
| P4: Ausführung + Fixing | ✅ DONE | 13/13 grün, 35 Assertions; alle 7 neuen Tests + 4 alte grün (4 BIRT/DEAT/users-logged-in + 3 DataProvider-Erweiterungen) |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Äquivalenzklassen-Eintrag, CRAP-Zeile (S41 entfernt), Endekriterien, Abdeckungsmatrix, Zusammenfassung 127→128 spec / 12→11 struct, Changelog aktualisiert |
