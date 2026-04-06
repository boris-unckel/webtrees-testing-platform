<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — G30: UploadMediaAction (Mediendatei-Upload HTTP)

**Status:** ⬜ OPEN  
**Aufwand:** Mittel  
**Qualitätsziel:** Spezifikationsbasiert (ISTQB B) — EP-Matrix für Guard-Branches

---

## Status quo

Kein dedizierter Test für `UploadMediaAction`. Möglicherweise in `MediaFileServiceUploadIntegrationTest` partiell abgedeckt (Service-Ebene). Der HTTP-Handler selbst ist nicht direkt getestet.

**Bestehende Tests:**
- `MediaFileServiceUploadIntegrationTest.php` — testet Service-Ebene, nicht den HTTP-Handler

---

## SUT-Kernbefunde

**Handler:** `Fisharebest\Webtrees\Http\RequestHandlers\UploadMediaAction`  
**Konstruktor-DI:** `MediaFileService $media_file_service` (testbar)

Die `handle()`-Methode iteriert über `$request->getUploadedFiles()` und für jeden Upload:

| Branch | Bedingung | Ergebnis |
|---|---|---|
| B1 | `UPLOAD_ERR_NO_FILE` | `continue` (überspringen) |
| B2 | Anderer Upload-Fehler (`!== UPLOAD_ERR_OK`) | `FileUploadException` |
| B3 | Ordner nicht in `allMediaFolders()` | `break` (Schleife abbrechen) |
| B4 | Dateiname enthält `:` | FlashMessage 'danger' + `continue` |
| B5 | Dateiname mit gefährlicher Extension (.php, .pl, .cgi etc.) | FlashMessage 'danger' + `continue` |
| B6 | Datei existiert bereits im Filesystem | FlashMessage 'danger' + `continue` |
| B7 (Happy Path) | Alle Guards passiert | `writeStream()` + FlashMessage 'success' + redirect(UploadMediaPage) |

**Filesystem-Problem:** `Registry::filesystem()->data()` ist nicht per DI austauschbar. Im Test-Container schreibt der Handler in das echte Daten-Verzeichnis des Containers — das ist der einzige testbare Weg ohne vfsStream.

---

## EP-Matrix

| EP | Partition | Eingabe | Erwartetes Ergebnis |
|---|---|---|---|
| EP1 | B1: Kein File | `UPLOAD_ERR_NO_FILE` | Response 302, kein Filesystem-Write |
| EP2 | B2: Upload-Fehler | `UPLOAD_ERR_PARTIAL` | `FileUploadException` |
| EP3 | B4: Doppelpunkt im Namen | `Datei:name.jpg` | FlashMessage 'danger', 302 redirect |
| EP4 | B5: Gefährliche Extension | `script.php` | FlashMessage 'danger', 302 redirect |
| EP5 | B7: Happy Path | Valide JPEG-Datei | FlashMessage 'success', 302 redirect, Datei in Filesystem |

EP3 (Ordner nicht in allMediaFolders) ist schwer testbar ohne Filesystem-Mock — als EXCLUDED markieren.

---

## Strategie

**Neue Testklasse:** `UploadMediaActionIntegrationTest extends MysqlTestCase`

- `setUp()`: `createAndLoginAdmin()`, `createTreeWithGedcom('demo', 'Demo', '/fixtures/demo.ged')`
- PSR-7 `UploadedFile` via `Laminas\Diactoros\UploadedFile` erzeugen (siehe `testquality_improve_common2.md`, Abschnitt 2)
- Für EP5 (Happy Path): Prüfen ob Datei im Container-Datenverzeichnis erstellt wurde; `tearDown()` bereinigt
- EP2 (FileUploadException): `expectException(FileUploadException::class)`

**Abhängigkeit:** Container muss laufen, Datenverzeichnis beschreibbar.

---

## Phasenstatus

| Phase | Status |
|---|---|
| P1: Konsistenzcheck | ⬜ |
| P2: Soll-Design | ⬜ |
| P3: Test-Coding | ⬜ |
| P4: Ausführung + Fixing | ⬜ |
| P5: Big-Picture | ⬜ |
