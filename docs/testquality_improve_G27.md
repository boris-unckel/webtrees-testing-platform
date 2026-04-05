<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — G27: Mediendatei-Upload URL

**Referenz:** G27 | **SUT:** `app/Services/MediaFileService.php`  
**Aktueller Test:** `MediaFileServiceUploadIntegrationTest` (2 Tests: URL-Return-Typ, Server-Path-Return-Typ)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Die Tests prüfen nur, dass Upload-Methoden einen nicht-leeren String zurückgeben. Es wird weder der Dateiinhalt, noch die DB-Einträge, noch Fehlerszenarien geprüft.

---

## SUT-Kernbefunde

`MediaFileService` hat mehrere Branches in `uploadFromUrl()` und `uploadFromServerFolder()`:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| URL-Upload | Gültige URL, HTTP 200 → Datei gespeichert | ✅ (Smoke) |
| URL-Upload | Netzwerkfehler / HTTP != 200 → Exception/leer | ❌ |
| URL-Upload | Ungültige URL (kein Protokoll) | ❌ |
| URL-Upload | URL verweist auf Binärdatei (JPEG, PDF) | ❌ |
| Server-Upload | Datei existiert auf Server-Pfad | ✅ (Smoke) |
| Server-Upload | Datei existiert nicht | ❌ |
| Server-Upload | Keine Leserechte | ❌ (Dateirechte, dauerhaft ausgeklammert) |
| Dateiname-Kollision | Gleichnamige Datei bereits vorhanden | ❌ |
| MIME-Validierung | Ungültiger MIME-Typ | ❌ |

**Invarianten:** Hochgeladene Datei muss im konfigurierten `data/media/`-Pfad liegen; DB-Eintrag in `media_file` muss angelegt werden; Dateiname darf keine Pfad-Traversal-Sequenzen enthalten.

---

## Äquivalenzklassen (EP)

| Klasse | Input | Erwartung |
|---|---|---|
| EP1 | Gültige HTTPS-URL, erreichbar | Datei gespeichert, String zurück |
| EP2 | URL mit HTTP 404 | Fehler/Exception |
| EP3 | Nicht-URL (z.B. `ftp://...`) | Fehler oder nicht unterstützt |
| EP4 | Server-Pfad existiert, lesbar | Datei kopiert |
| EP5 | Server-Pfad existiert nicht | Fehler |
| EP6 | Dateiname bereits vorhanden | Umbenennung oder Fehler |
| EP7 | URL mit Query-String | Dateiname aus URL extrahiert |

---

## Grenzwerte (BVA)

- URL-Länge: Leerstring, sehr lange URL
- Dateiname: `../traversal` (Sicherheitsgrenze), `normal.jpg`, `日本語.jpg` (Unicode)
- Dateigröße: 0 Bytes (leere Datei), sehr große Datei

---

## Empfohlene Strategie

**Pragmatisch C** für Netzwerk-Fehlerszenarien (HTTP-Mock benötigt, → Common Abschnitt 5.3). **ISTQB B** für Server-Upload-EP (kein Netzwerk nötig, direkte Filesystem-Tests).

**Hinweis DB-Verifizierung:** Nach erfolgreichem Upload sollte `DB::table('media_file')` auf den neuen Eintrag geprüft werden (Post-Condition-Muster → Common Abschnitt 4.2).

---

## Konkrete Testideen

```
test_upload_from_url_creates_file_in_media_directory()
test_upload_from_server_folder_copies_file()
test_upload_fails_gracefully_on_network_error()     ← HTTP-Mock
test_upload_creates_database_entry()                 ← DB-Postcondition
test_upload_handles_filename_collision()
```

---

## Aufwand

**Mittel** — DB-Postcondition und Filesystem-Verifizierung erfordert erweiterten Setup. HTTP-Mocking ist **Hoch** (neue Dev-Dependency oder Netzwerk-Stub nötig).

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ⬜ OPEN | — |
| P2: Soll-Design | ⬜ OPEN | — |
| P3: Test-Coding | ⬜ OPEN | — |
| P4: Ausführung + Fixing | ⬜ OPEN | — |
| P5: Big-Picture | ⬜ OPEN | — |
