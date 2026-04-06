<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A09: Datenpflege-Werkzeuge

**Referenz:** A09 | **SUT:** `app/Http/RequestHandlers/DataFixPage.php`, `DataFixChoose.php`, `DataFixSelect.php`, `DataFixUpdate.php`, `CleanDataFolder.php`, `FindDuplicateRecords.php`, `AddUnlinkedPage.php`, `AddUnlinkedAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test. FindDuplicateRecords: GET → AdminService.duplicateRecords(). DataFixPage: GET mit Branch für leere Modulliste.

---

## SUT-Kernbefunde

### FindDuplicateRecords (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → duplicateRecords($tree) aus AdminService → View | Nein |

### DataFixPage (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `$data_fixes->isEmpty()` → redirect('control-panel') | Nein |
| B2 | Kein spezifisches Modul → Auswahl-Seite | Nein |
| B3 | Spezifisches Modul → DataFix-Seite | Nein |

### AddUnlinkedAction (POST)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Unverknüpfte Records einem Tree zuordnen | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | FindDuplicateRecords GET: mit demo.ged-Baum | 200, Duplikate-View |
| EP2 | DataFixPage GET: B2 (Auswahl) | 200, Modul-Auswahl |
| EP3 | DataFixPage GET: B3 (spezifisches Modul) | 200, DataFix-Seite |
| EP4 | DataFixChoose GET | Smoke: 200 |
| EP5 | DataFixSelect GET | Smoke: 200 |
| EP6 | AddUnlinkedPage GET | Smoke: 200 |

---

## Empfohlene Strategie

**ISTQB B für DataFixPage-Branches, Smoke für Rest.** Neue Klasse `DataMaintenanceIntegrationTest extends MysqlTestCase`. Admin-Auth + demo.ged-Baum. DataFixUpdate (POST mit AJAX) und CleanDataFolder: tbd bei P1 (Filesystem-Abhängigkeit).

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | FindDuplicateRecords DI: AdminService. DataFixPage DI: ModuleService; data_fix='' → Auswahl; data_fix='fix-place-names' → Modul-Seite. DataFixChoose DI: ModuleService. AddUnlinkedPage DI: GedcomEditService |
| P2: Soll-Design | ✅ | EP1 (FindDuplicateRecords→200), EP2 (DataFixPage leer→200), EP3 (DataFixPage fix-place-names→200), EP4 (DataFixChoose→200) |
| P3: Test-Coding | ✅ | `DataMaintenanceIntegrationTest` (4 Tests) |
| P4: Ausführung + Fixing | ✅ | 4/4 grün |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix A09 aktualisiert |
