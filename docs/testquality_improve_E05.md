<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — E05: Medienobjekte anlegen & verknüpfen

**Referenz:** E05 | **SUT:** `app/Http/RequestHandlers/CreateMediaObjectModal.php`, `CreateMediaObjectAction.php`, `CreateMediaObjectFromFileAction.php`, `AddMediaFileModal.php`, `AddMediaFileAction.php`, `LinkMediaToRecordAction.php`, `LinkIndividualToMediaModal.php`, `LinkFamilyToMediaModal.php`, `LinkSourceToMediaModal.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test für Medienobjekt-Handler.

---

## SUT-Kernbefunde

Repräsentativer Handler für vollständige EP-Analyse: **CreateMediaObjectModal** (GET) + **LinkMediaToRecordAction** (POST).

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| CreateMediaObjectModal B1 | GET → Modal-View, MediaFileService DI | Nein |
| CreateMediaObjectAction B1 | POST: valide Daten → OBJE-Record in DB | Nein |
| CreateMediaObjectFromFileAction B1 | POST: Datei vorhanden auf Filesystem | Nein |
| LinkMediaToRecordAction B1 | POST: XREF + MEDIA-XREF → OBJE-Fakt hinzugefügt | Nein |
| Alle anderen Modal-Handler | GET → Modal | Smoke |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | CreateMediaObjectModal GET | 200, Modal-HTML |
| EP2 | CreateMediaObjectAction POST: valide Felder | Redirect/200, OBJE in DB |
| EP3 | CreateMediaObjectFromFileAction POST: keine Datei | Guard: tbd bei P1 |
| EP4 | LinkMediaToRecordAction POST: XREF+MEDIA-XREF gültig | DB: OBJE-Fakt bei Record |
| EP5 | LinkIndividualToMediaModal GET | Smoke: 200 |
| EP6 | AddMediaFileModal GET | Smoke: 200 |

---

## Empfohlene Strategie

**Batch-Strategie (~9 Handler):** CreateMediaObjectModal + LinkMediaToRecordAction vollständig. Alle anderen: Smoke. Neue Klasse `MediaObjectIntegrationTest extends MysqlTestCase`. Achtung: Filesystem-Abhängigkeit bei CreateMediaObjectFromFileAction — tbd bei P1.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | CreateMediaObjectModal DI: MediaFileService. LinkMediaToRecordAction: kein DI. LinkMediaToIndividualModal: kein DI. OBJE XREF in demo.ged: X247 |
| P2: Soll-Design | ✅ | EP1 (CreateModal→200), EP4 (LinkMediaToRecord xref=X247+link=X1030→302), EP5 (LinkMediaToIndividualModal xref=X247→200) |
| P3: Test-Coding | ✅ | `MediaObjectIntegrationTest` (3 Tests) |
| P4: Ausführung + Fixing | ✅ | 3/3 grün |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix E05 aktualisiert |
