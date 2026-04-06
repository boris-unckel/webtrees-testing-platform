<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A03: Stammbaum-Export (HTTP-Formular)

**Referenz:** A03 | **SUT:** `app/Http/RequestHandlers/ExportGedcomPage.php`, `ExportGedcomClient.php`, `ExportGedcomServer.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

`TreeOperationsTest` und `TreeExportCommandIntegrationTest` testen den GEDCOM-Export via Service und CLI. Die HTTP-Handler `ExportGedcomClient` (POST → Download-Response) und `ExportGedcomServer` (POST → Datei auf Server) sind nicht direkt getestet.

---

## SUT-Kernbefunde

### ExportGedcomPage (GET)

Trivial — gibt Admin-View zurück. Branch: `zip_available` via `PhpService::extensionLoaded('zip')`.

### ExportGedcomClient (POST)

**DI:** `GedcomExportService`

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | format='gedcom' → .ged Download, text/plain | Nein |
| B2 | format='zip' → .zip Download, application/zip | Nein |
| B3 | format='zipmedia' → .zip mit Media | Nein |
| B4 | format='gedzip' → .gdz Download | Nein |
| B5 | privacy='none' → alle Records exportiert | Nein |
| B6 | Ungültiges format → Validator-Exception | Nein |

### ExportGedcomServer (POST)

**DI:** `GedcomExportService`

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | filename ohne .ged-Suffix → .ged wird angehängt | Nein |
| B2 | writeStream() erfolgreich → FlashMessage 'success' + redirect | Nein |
| B3 | writeStream() fehlgeschlagen → FlashMessage 'danger' + redirect | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | ExportGedcomClient POST: format=gedcom | 200, Content-Disposition attachment, .ged |
| EP2 | ExportGedcomClient POST: format=zip | 200, application/zip |
| EP3 | ExportGedcomClient POST: privacy=none | 200, Response |
| EP4 | ExportGedcomServer POST: Happy Path | 302 redirect(ManageTrees), Flash 'success' |
| EP5 | ExportGedcomServer POST: filename ohne .ged | filename += '.ged' (tbd bei P1) |
| EP6 | ExportGedcomPage GET | 200 |

---

## Empfohlene Strategie

**ISTQB B.** Neue Klasse `ExportGedcomIntegrationTest extends MysqlTestCase`. Fixtures: demo.ged-Baum. ExportGedcomClient: Response-Stream auf Content-Type prüfen. ExportGedcomServer: Flash-Message + Redirect prüfen, Filesystem-Postcondition optional.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | ExportGedcomClient DI: GedcomExportService; ExportGedcomServer: schreibt in data-FS, gibt immer 302 zurück; ExportGedcomPage DI: PhpService |
| P2: Soll-Design | ✅ | EP1 (gedcom attachment), EP2 (zip content-type), EP4 (Server 302), EP6 (Page GET) |
| P3: Test-Coding | ✅ | `ExportGedcomIntegrationTest` (4 Tests) |
| P4: Ausführung + Fixing | ✅ | 4/4 grün |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix A03 aktualisiert |
