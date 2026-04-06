<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — E03: Rohdaten-Edit (Raw GEDCOM)

**Referenz:** E03 | **SUT:** `app/Http/RequestHandlers/EditRawFactPage.php`, `EditRawFactAction.php`, `EditRawRecordPage.php`, `EditRawRecordAction.php`, `EditRecordPage.php`, `EditRecordAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test für Raw-GEDCOM-Edit-Handler.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| EditRawFactAction B1 | POST: valider GEDCOM-String → change-Tabelle | Nein |
| EditRawFactAction B2 | POST: ungültiger GEDCOM → Validation-Guard | Nein |
| EditRawRecordAction B1 | POST: gültiger GEDCOM-Record → update | Nein |
| EditRecordAction B1 | POST: Standard-Edit → DB-Update | Nein |
| EditRawFactPage B1 | GET: Fakt vorhanden → View | Nein |
| EditRawRecordPage B1 | GET: Record vorhanden → View | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | EditRawFactPage GET: gültiger XREF + fact_id | 200, View mit GEDCOM-Textarea |
| EP2 | EditRawFactAction POST: valider GEDCOM | Redirect, change-Tabelle aktualisiert |
| EP3 | EditRawFactAction POST: leerer GEDCOM-String | Guard: tbd bei P1-Konsistenzcheck |
| EP4 | EditRawRecordPage GET: gültiger XREF | 200, View |
| EP5 | EditRawRecordAction POST: gültiger GEDCOM | Redirect, DB-Update |
| EP6 | EditRecordAction POST: Standard-Felder | Redirect, DB-Update |

---

## Empfohlene Strategie

**ISTQB B (spezifikationsbasiert).** Neue Klasse `EditRawGedcomIntegrationTest extends MysqlTestCase`. Fixtures: INDI mit BIRT-Fakt. Fokus auf EditRawFactAction (Validierungslogik) und DB-Postcondition nach POST.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
