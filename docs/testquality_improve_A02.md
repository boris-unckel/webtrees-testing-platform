<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A02: Stammbaum-Import (HTTP-Formular)

**Referenz:** A02 | **SUT:** `app/Http/RequestHandlers/ImportGedcomPage.php`, `ImportGedcomAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

`GedcomImportTest` und `GedcomLoadIntegrationTest` testen den Import via Service und CLI. Der HTTP-Handler `ImportGedcomAction` (POST) ist nicht direkt getestet.

---

## SUT-Kernbefunde

### ImportGedcomPage (GET)

Liest GEDCOM-Dateien aus Filesystem (`admin_service->gedcomFiles()`). Gibt Admin-View zurück.

### ImportGedcomAction (POST)

PSR-7 UploadedFile nötig (`getUploadedFiles()`). Startet Import-Queue (asynchron).

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | source='client', kein File / UPLOAD_ERR_NO_FILE → FlashMessage 'danger' + redirect | Nein |
| B2 | source='client', Upload-Fehler → FileUploadException | Nein |
| B3 | source='client', valides File → importGedcomFile(stream) + redirect(ManageTrees) | Nein |
| B4 | source='server', leerer Dateiname → FlashMessage 'danger' + redirect | Nein |
| B5 | source='server', valider Dateiname → importGedcomFile(serverFile) + redirect | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | source=client, UPLOAD_ERR_NO_FILE | 302 zu ImportGedcomPage |
| EP2 | source=client, UPLOAD_ERR_PARTIAL | FileUploadException |
| EP3 | source=client, valider .ged-Stream | 302 zu ManageTrees |
| EP4 | source=server, server_file='' | 302 zu ImportGedcomPage |
| EP5 | ImportGedcomPage GET | 200 |

---

## Empfohlene Strategie

**ISTQB B (spezifikationsbasiert).** Neue Klasse `ImportGedcomActionIntegrationTest extends MysqlTestCase`. PSR-7 `UploadedFile` für Client-Upload-Tests. Für EP3: Minimales GEDCOM im Memory (`0 HEAD\n1 SOUR Test\n0 TRLR`).

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
