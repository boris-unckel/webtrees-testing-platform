<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S48: Standortdaten-Import Admin

**Referenz:** S48 | **SUT:** `app/Http/RequestHandlers/MapDataImportAction.php`  
**Aktueller Test:** `MapDataImportIntegrationTest` (2 Tests: `add`- und `addupdate`-Option)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Zwei Smoke-Tests prüfen nur den Response-Code (< 500). Es wird nicht geprüft, ob Koordinaten tatsächlich in der DB landen oder ob CSV-Parserfehler korrekt behandelt werden.

---

## SUT-Kernbefunde

`MapDataImportAction::handle()` verarbeitet hochgeladene CSV-Dateien mit Geo-Koordinaten:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| `source='client'` | Upload vom Browser | ❌ (Smoke deckt HTTP-Pfad) |
| `source='server'` | Datei liegt auf Server | ❌ |
| `option='add'` | Nur neue Orte hinzufügen | ✅ (Smoke) |
| `option='update'` | Nur bestehende aktualisieren | ❌ |
| `option='addupdate'` | Hinzufügen + aktualisieren | ✅ (Smoke) |
| CSV mit Koordinaten `(0.0, 0.0)` | Null-Island-Filter | ❌ |
| Ort bereits in DB | Duplikat-Behandlung | ❌ |
| Ungültige CSV | Fehlende Pflichtfelder | ❌ |
| Koordinaten außerhalb Bereich | > 90 lat, > 180 lng | ❌ |

**DB-Postcondition:** Koordinaten werden in `placelocation`-Tabelle geschrieben. Bisher keine Verifizierung.

---

## Äquivalenzklassen (EP)

### `option`-Parameter

| Klasse | Wert | Erwartung |
|---|---|---|
| EP1 | `'add'` | Neuer Ort → in DB; bestehender → übersprungen |
| EP2 | `'update'` | Bestehender Ort → aktualisiert; neuer → übersprungen |
| EP3 | `'addupdate'` | Beides |
| EP4 | Ungültiger Wert | Fehler oder Default |

### CSV-Inhalt

| Klasse | Inhalt | Erwartung |
|---|---|---|
| EP5 | Valides CSV (Ort + Lat + Lng) | Koordinaten in DB |
| EP6 | CSV mit `(0.0, 0.0)` (Null-Island) | Gefiltert / nicht importiert |
| EP7 | CSV ohne Koordinaten-Spalten | Fehler |
| EP8 | Leeres CSV (nur Header) | 0 Einträge importiert, kein Fehler |
| EP9 | CSV mit Duplikaten | Duplikat-Verhalten je nach `option` |

---

## Grenzwerte (BVA)

- Koordinaten: lat=0.0 / lng=0.0 (Null-Island-Grenze), lat=90.0 (Pol), lat=-90.0, lat=90.01 (ungültig)
- Orts-Hierarchie: 1 Level, 5 Level (Stadt → Land)
- CSV-Zeilen: 0 (nur Header), 1, viele

---

## Empfohlene Strategie

**ISTQB B** für `option`-EP-Matrix und CSV-Inhalts-Klassen — klare Spezifikation.  
**Schlüssel-Verbesserung:** Post-Condition-Verifikation (Koordinaten in DB nach Import) — dies ist der wichtigste fehlende Test.

---

## Konkrete Testideen

```
test_import_add_option_creates_new_location_in_db()       ← DB-Postcondition
test_import_update_option_only_updates_existing()
test_import_skips_null_island_coordinates()
test_import_empty_csv_imports_zero_records()
test_import_handles_invalid_csv_gracefully()
```

---

## Aufwand

**Mittel** — CSV-Fixture erstellen, `DB::table('placelocation')` nach Import prüfen.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | KRITISCH: Bestehende Tests verwenden falsches CSV-Format (`,`-Separator statt `;` aus MapDataService::CSV_SEPARATOR)→importieren 0 Zeilen, bestehen nur weil kein Fehler; Korrektes Format: `0;Canada;;;W106;N56;3;`; PlaceLocation::id() erstellt DB-Zeile automat.; Null-Island-Filter: multi-level Orte mit (0,0)→gefiltert; Bestehende Tests bleiben als Fehler-resilience-Test, neue Tests nutzen korrektes Format |
| P2: Soll-Design | ✅ DONE | 2 neue Tests: EP1+EP5 test_import_add_creates_location_in_db (richtiges CSV, option=add → DB-Postcondition lat/lng); EP6 test_import_null_island_filtered (level=1, 0,0 coords → place nicht in DB); Bestehende Tests unverändert (Fehlerresilienz-Test für malformed input) |
| P3: Test-Coding | ✅ DONE | MapDataImportIntegrationTest.php: 2 neue Tests test_import_add_creates_location_in_db (EP1+EP5, DB-Postcondition assertEqualsWithDelta) + test_import_null_island_filtered_for_sublocation (EP6, assertFalse exists); Bestehende Tests unverändert |
| P4: Ausführung + Fixing | ✅ DONE | 4/4 grün, 16 Assertions; kein Fixing nötig; DB-Postcondition für TestCountry99 lat/lng bestätigt |
| P5: Big-Picture | ✅ DONE | Feature-Matrix spezifikationsbasiert, Äquivalenzklassen-Eintrag S48 (CSV-Format-Befund dokumentiert), CRAP-Zeile korrigiert (S48 entfernt → S45), Endekriterien, Abdeckungsmatrix, Zusammenfassung 132→133 spec / 7→6 struct, Changelog aktualisiert |
