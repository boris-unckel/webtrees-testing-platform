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
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
