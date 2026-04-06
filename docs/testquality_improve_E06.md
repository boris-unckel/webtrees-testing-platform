<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — E06: Sortierung (Reorder)

**Referenz:** E06 | **SUT:** `app/Http/RequestHandlers/ReorderChildrenPage.php`, `ReorderNamesPage.php`, `ReorderFamiliesPage.php`, `ReorderMediaPage.php`, `ReorderMediaAction.php`, `ReorderMediaFilesPage.php`, `ReorderMediaFilesAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test für Reorder-Handler. ReorderChildrenPage GET zeigt Kinderliste für FAM.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| ReorderChildrenPage B1 | GET: FAM-XREF vorhanden + isManager → 200, View | Nein |
| ReorderChildrenPage B2 | GET: kein Manager → AccessDenied | Nein |
| ReorderMediaAction B1 | POST: neue Reihenfolge → GEDCOM-Update | Nein |
| ReorderMediaFilesAction B1 | POST: neue Dateienreihenfolge → GEDCOM-Update | Nein |
| Alle anderen Page-Handler | GET → View | Smoke |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | ReorderChildrenPage GET: Manager + gültige FAM-XREF | 200 |
| EP2 | ReorderNamesPage GET | Smoke: 200 |
| EP3 | ReorderFamiliesPage GET | Smoke: 200 |
| EP4 | ReorderMediaPage GET | Smoke: 200 |
| EP5 | ReorderMediaAction POST: neue Reihenfolge | 200/Redirect, DB-Update |
| EP6 | ReorderMediaFilesAction POST | 200/Redirect |

---

## Empfohlene Strategie

**Niedrig-Aufwand: DataProvider-Smoke für alle Page-Handler.** Neue Klasse `ReorderIntegrationTest extends MysqlTestCase`. Fixtures: FAM + INDI + OBJE in Tree, Manager-Auth. ReorderMediaAction: POST mit neuer Reihenfolge, DB-Postcondition optional.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
