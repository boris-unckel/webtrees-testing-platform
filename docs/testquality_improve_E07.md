<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — E07: Mediendatei-Download & Thumbnail

**Referenz:** E07 | **SUT:** `app/Http/RequestHandlers/MediaFileDownload.php`, `MediaFileThumbnail.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

`ManageMediaDataIntegrationTest` deckt die Datenliste ab, nicht die Datei-Auslieferung. Kein dedizierter Test für MediaFileDownload oder MediaFileThumbnail.

---

## SUT-Kernbefunde

### MediaFileDownload

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → binary stream, Content-Type von Dateierweiterung | Nein |
| B2 | canShow = false → Guard 403 | Nein |
| B3 | fileExists = false → replacementImageResponse(404) | Nein |
| B4 | Datei ist extern (URL) → redirect($url) | Nein |

### MediaFileThumbnail

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Placeholder wenn Datei fehlt → image/* Replacement | Nein |
| B2 | canShow = false → Replacement 403 | Nein |
| B3 | Signatur-Mismatch → Replacement 403 | Nein |
| B4 | Happy Path → image/* Response | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Download: Datei nicht gefunden (fileExists=false) | replacementImageResponse 404 |
| EP2 | Download: externe URL | 302 redirect zur URL |
| EP3 | Thumbnail: Media null | Replacement Image 404 |
| EP4 | Thumbnail: canShow=false (RESN=confidential) | Replacement Image 403 |
| EP5 | Thumbnail: Datei fehlt lokal | Replacement Image |

---

## Empfohlene Strategie

**ISTQB B (spezifikationsbasiert).** Neue Klasse `MediaFileDeliveryIntegrationTest extends MysqlTestCase`. Fixtures: OBJE-Record mit externer URL und mit fehlender lokaler Datei. Happy Path (echte Datei) ist optional — erfordert Datei im Container-Datenverzeichnis.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | SUT gelesen: replacementImageResponse gibt immer HTTP 200 zurück |
| P2: Soll-Design | ✅ | EP1–EP3: null-xref-Thumbnail, fact_id-miss, Download-Exception |
| P3: Test-Coding | ✅ | `MediaFileDeliveryIntegrationTest` (3 Tests) |
| P4: Ausführung + Fixing | ✅ | 3/3 grün |
| P5: Big-Picture | ✅ | testing-bigpicture.md aktualisiert |
