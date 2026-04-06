<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — E01: Person/Familie anlegen & verknüpfen

**Referenz:** E01 | **SUT:** `app/Http/RequestHandlers/AddChildToIndividualPage.php` (+ Action), `AddParentToIndividualPage.php` (+ Action), `AddSpouseToIndividualPage.php` (+ Action), `LinkSpouseToIndividualPage.php` (+ Action), `AddChildToFamilyPage.php` (+ Action), `AddSpouseToFamilyPage.php` (+ Action), `LinkChildToFamilyPage.php` (+ Action)
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test. ~14 Handler für die Erstellung und Verknüpfung von Personen und Familien.

---

## SUT-Kernbefunde

Repräsentativer Handler für vollständige EP-Analyse: **AddChildToIndividualPage** (GET) + **AddChildToIndividualAction** (POST).

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| AddChildToIndividualPage B1 | GET: XREF vorhanden + isManager → 200, View | Nein |
| AddChildToIndividualPage B2 | GET: kein Manager → AccessDenied | Nein |
| AddChildToIndividualAction B1 | POST: valide Daten → GEDCOM erstellt, Redirect | Nein |
| AddChildToIndividualAction B2 | POST: XREF nicht gefunden → Guard | Nein |
| Alle anderen Handler | GET/POST | Smoke-Test (GET → 200 / POST → 302) |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | AddChildToIndividualPage GET: Manager mit gültiger XREF | 200 |
| EP2 | AddChildToIndividualPage GET: Non-Manager | AccessDenied |
| EP3 | AddChildToIndividualAction POST: valide GEDCOM-Daten | Redirect, neuer INDI in DB |
| EP4 | AddChildToIndividualAction POST: fehlendes Pflichtfeld | Guard: tbd bei P1-Konsistenzcheck |
| EP5 | Alle anderen Page-Handler | Smoke: GET → 200 |
| EP6 | Alle anderen Action-Handler | Smoke: POST → 302 |

---

## Empfohlene Strategie

**Batch-Strategie (Hoch, ~14 Handler):** AddChildToIndividualPage/Action vollständig mit EP-Matrix. Alle anderen Handler: DataProvider-Smoke (GET → 200 / POST → 302). Neue Klasse `AddRelationIntegrationTest extends MysqlTestCase`. Fixtures: INDI + FAM in Tree, Manager-Auth.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | Alle Handler DI: GedcomEditService. Page-Handler: xref aus attributes, ggf. sex. Action: ilevels/itags/ivalues Arrays aus parsedBody. FAM-XREFs in demo.ged: f1/f2… (lowercase). INDI: X1030+ |
| P2: Soll-Design | ✅ | EP1 (AddChildToIndividualPage→200), EP3 (AddChildToIndividualAction→302), DataProvider EP5 (AddParent/AddSpouseToIndi, AddChild/AddSpouseToFam→200) |
| P3: Test-Coding | ✅ | `AddRelationIntegrationTest` (6 Tests: 2 direkt + 4 DataProvider) |
| P4: Ausführung + Fixing | ✅ | 6/6 grün |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix E01 aktualisiert |
