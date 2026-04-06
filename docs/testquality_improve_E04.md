<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — E04: Nebenrecords anlegen

**Referenz:** E04 | **SUT:** `app/Http/RequestHandlers/CreateNoteModal.php`, `CreateNoteAction.php`, `EditNotePage.php`, `EditNoteAction.php`, `CreateSourceModal.php`, `CreateSourceAction.php`, `CreateRepositoryModal.php`, `CreateRepositoryAction.php`, `CreateSubmissionModal.php`, `CreateSubmitterModal.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test für Modal- und Action-Handler zum Anlegen von Nebenrecords (NOTE, SOUR, REPO, SUBM).

---

## SUT-Kernbefunde

Repräsentativer Handler für vollständige EP-Analyse: **CreateNoteModal** (GET) + **CreateNoteAction** (POST).

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| CreateNoteModal B1 | GET → Modal-View mit form für NOTE-Text | Nein |
| CreateNoteAction B1 | POST: valider NOTE-Text → neuer NOTE-Record in DB | Nein |
| CreateNoteAction B2 | POST: kein Text → Guard: tbd bei P1 | Nein |
| Alle anderen Modal-Handler | GET → Modal-View | Smoke |
| Alle anderen Action-Handler | POST → Redirect/JSON mit XREF | Smoke |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | CreateNoteModal GET | 200, Modal-HTML |
| EP2 | CreateNoteAction POST: gültiger Text | 200/Redirect, NOTE-Record in DB |
| EP3 | CreateNoteAction POST: leerer Text | Guard: tbd bei P1-Konsistenzcheck |
| EP4 | CreateSourceModal GET | Smoke: 200 |
| EP5 | CreateSourceAction POST | Smoke: 200/Redirect |
| EP6 | CreateRepositoryModal GET | Smoke: 200 |
| EP7 | CreateSubmitterModal GET | Smoke: 200 |

---

## Empfohlene Strategie

**ISTQB B für CreateNote, Smoke für Rest.** Neue Klasse `CreateSubrecordIntegrationTest extends MysqlTestCase`. Manager-Auth, Tree mit Fixture. DataProvider für Smoke-Tests der anderen Modals.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | CreateNoteModal/Source/Repo: kein DI; CreateNoteAction: isNotEmpty() auf 'note'; response enthält JSON mit XREF |
| P2: Soll-Design | ✅ | EP1/EP2 (NoteModal/NoteAction), EP4/EP6 (Source/RepoModal) |
| P3: Test-Coding | ✅ | `CreateSubrecordIntegrationTest` (4 Tests) |
| P4: Ausführung + Fixing | ✅ | 4/4 grün (Fix: DB-count auf JSON-body-check, da createRecord → change-Tabelle) |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix E04 aktualisiert |
