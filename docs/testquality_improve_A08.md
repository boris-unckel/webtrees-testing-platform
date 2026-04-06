<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A08: Medienverwaltung Admin

**Referenz:** A08 | **SUT:** `app/Http/RequestHandlers/AdminMediaFileDownload.php`, `AdminMediaFileThumbnail.php`, `FixLevel0MediaPage.php`, `FixLevel0MediaAction.php`, `ManageMediaPage.php`, `ManageMediaAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test. ManageMediaAction: POST → redirect(ManageMediaPage) mit übernommenen Query-Params.

---

## SUT-Kernbefunde

### ManageMediaAction (POST)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | POST → liest media_folders aus Filesystem, redirect(ManageMediaPage) | Nein |
| B2 | Ungültiger media_folder → Validator schlägt fehl | Nein |

### AdminMediaFileDownload (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Pfad innerhalb media_folder → Datei-Response | Nein |
| B2 | Pfad außerhalb media_folder → HttpBadRequestException | Nein |

### FixLevel0MediaPage (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → View mit Level-0-Media-Records | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | ManageMediaPage GET | Smoke: 200 |
| EP2 | ManageMediaAction POST: valider media_folder | 302 zu ManageMediaPage |
| EP3 | AdminMediaFileDownload: ungültiger Pfad | HttpBadRequestException |
| EP4 | FixLevel0MediaPage GET | Smoke: 200 |
| EP5 | FixLevel0MediaAction POST | 302/Redirect |

---

## Empfohlene Strategie

**ISTQB B für AdminMediaFileDownload Guard (EP3), Smoke für Rest.** Neue Klasse `AdminMediaIntegrationTest extends MysqlTestCase`. Admin-Auth, kein Tree für ManageMedia nötig. Für EP3: Pfad traversal-Versuch.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
