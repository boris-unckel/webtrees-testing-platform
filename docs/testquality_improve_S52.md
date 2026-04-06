<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S52: Standortdaten-Verwaltung (CRUD)

**Referenz:** S52 | **SUT:** `app/Http/RequestHandlers/MapDataList.php`, `MapDataAdd.php`, `MapDataEdit.php`, `MapDataSave.php`, `MapDataDelete.php`, `MapDataDeleteUnused.php`, `MapDataExportCSV.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test für MapData-Handler. Die `place_location`-Tabelle wird nicht per HTTP-Handler getestet.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| MapDataSave B1 | POST mit vorhandener ID → UPDATE place_location | Nein |
| MapDataSave B2 | POST ohne ID (neu) → INSERT place_location | Nein |
| MapDataDelete B1 | POST → Eintrag gelöscht aus place_location | Nein |
| MapDataDeleteUnused B1 | POST → alle place_location ohne Referenz gelöscht | Nein |
| MapDataExportCSV B1 | GET → CSV-Stream mit Content-Type text/csv | Nein |
| MapDataList B1 | GET → View mit Standortliste | Nein |
| MapDataAdd B1 | GET → Modal-View für neuen Standort | Nein |
| MapDataEdit B1 | GET → Modal-View mit vorhandenem Standort | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | MapDataSave: Insert (keine ID) | 200/Redirect, DB-Eintrag neu angelegt |
| EP2 | MapDataSave: Update (vorhandene ID) | 200/Redirect, DB-Eintrag aktualisiert |
| EP3 | MapDataSave: Ungültige Koordinaten | Guard: tbd bei P1-Konsistenzcheck |
| EP4 | MapDataDelete: Gültige ID | 200, Eintrag aus DB entfernt |
| EP5 | MapDataDelete: Ungültige ID | Guard: tbd bei P1-Konsistenzcheck |
| EP6 | MapDataExportCSV | GET → 200, Content-Type text/csv |
| EP7 | MapDataList | GET → 200 |

---

## Empfohlene Strategie

**ISTQB B (spezifikationsbasiert) + DB-Postconditions.**
Neue Klasse `MapDataCrudIntegrationTest extends MysqlTestCase`.
Fokus: Save Insert/Update mit DB-Verifikation, Delete-Postcondition, CSV-Export Content-Type.
Admin-Auth über `createAndLoginAdmin()`.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
