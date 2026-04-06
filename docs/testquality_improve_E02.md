<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — E02: Fakten bearbeiten

**Referenz:** E02 | **SUT:** `app/Http/RequestHandlers/EditFactPage.php`, `AddNewFact.php`, `DeleteFact.php`, `CopyFact.php`, `PasteFact.php`, `SelectNewFact.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test für die HTTP-Handler der Faktbearbeitung.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| EditFactPage B1 | GET: Fakt nicht gefunden → Redirect | Nein |
| EditFactPage B2 | GET: can_edit_raw-Branch → zusätzliche View-Option | Nein |
| AddNewFact B1 | GET: subtag = 'OBJE' + kein canUploadMedia → AccessDenied | Nein |
| AddNewFact B2 | GET: include_hidden = true → erweiterte Felder | Nein |
| AddNewFact B3 | GET: gedcom == hidden → hidden_url = '' | Nein |
| DeleteFact B1 | POST: Fakt gefunden + canEdit → deleteFact() | Nein |
| DeleteFact B2 | POST: kein canEdit → kein Delete (Schleife bricht nicht) | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | EditFactPage GET: gültiger XREF + fact_id | 200, View |
| EP2 | EditFactPage GET: fact_id nicht gefunden | Redirect |
| EP3 | AddNewFact GET: normaler subtag | 200, View, hidden_url leer oder gesetzt |
| EP4 | AddNewFact GET: subtag OBJE ohne Berechtigung | HttpAccessDeniedException |
| EP5 | DeleteFact POST: Fakt mit canEdit=true | 200 (leer), DB-Eintrag entfernt |
| EP6 | DeleteFact POST: Fakt mit canEdit=false | 200 (leer), DB-Eintrag unverändert |

---

## Empfohlene Strategie

**ISTQB B (spezifikationsbasiert).** Neue Klasse `EditFactIntegrationTest extends MysqlTestCase`. Fixtures: INDI mit einem BIRT-Fakt, Manager-Auth. DeleteFact: DB-Postcondition prüfen.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | EditFactPage DI: GedcomEditService; AddNewFact: `fact` in attributes (isTag()); DeleteFact kein DI |
| P2: Soll-Design | ✅ | EP2 (redirect ungültige fact_id), EP5 (DeleteFact 204), EP3 (AddNewFact 200) |
| P3: Test-Coding | ✅ | `EditFactIntegrationTest` (3 Tests) |
| P4: Ausführung + Fixing | ✅ | 3/3 grün (Fix: `fact` in attributes statt query) |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix E02 aktualisiert |
