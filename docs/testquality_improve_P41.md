<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P41: Datensatz-Zusammenführung

**Referenz:** P41 | **SUT:** `app/Http/RequestHandlers/MergeRecordsPage.php`, `MergeRecordsAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test für MergeRecords-Handler.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| MergeRecordsPage B1 | GET: beide XREFs existieren im selben Tree, gleicher Typ | Nein |
| MergeRecordsPage B2 | GET: XREF1 nicht vorhanden → Exception/Redirect | Nein |
| MergeRecordsPage B3 | GET: unterschiedliche Record-Typen → Guard | Nein |
| MergeRecordsAction B1 | POST: Typ-Match → ein Record gelöscht, einer aktualisiert | Nein |
| MergeRecordsAction B2 | POST: Typ-Mismatch → Guard schlägt fehl | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | GET: zwei gültige INDIs gleichen Baums | 200, Vergleichs-View |
| EP2 | GET: XREF existiert nicht | Guard: tbd bei P1-Konsistenzcheck |
| EP3 | GET: unterschiedliche Typen (INDI + FAM) | Guard: tbd bei P1-Konsistenzcheck |
| EP4 | POST: Merge-Happy-Path (INDI + INDI) | Redirect, DB: einer gelöscht, einer aktualisiert |
| EP5 | POST: Typ-Mismatch | Guard: tbd bei P1-Konsistenzcheck |

---

## Empfohlene Strategie

**ISTQB B (spezifikationsbasiert) + DB-Postconditions.**
Neue Klasse `MergeRecordsIntegrationTest extends MysqlTestCase`.
Fixtures: zwei INDI-Records im selben Tree. DB-Postcondition nach POST: einer der XREFs nicht mehr in `gedcom_record`.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | SUT gelesen: Page kein DI, Action kein DI; null-Record-Guard → redirect zu MergeRecordsPage |
| P2: Soll-Design | ✅ | EP1–EP4: Page GET 200, Page leer 200, Action matching INDIs → 302 zu MergeFactsPage |
| P3: Test-Coding | ✅ | `MergeRecordsIntegrationTest` (3 Tests) |
| P4: Ausführung + Fixing | ✅ | 3/3 grün |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix P41 aktualisiert |
