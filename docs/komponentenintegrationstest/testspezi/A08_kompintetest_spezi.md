<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — A08: Medienverwaltung Admin

**Referenz:** A08 | **SUT:** `app/Http/RequestHandlers/AdminMediaFileDownload.php`, `app/Http/RequestHandlers/FixLevel0MediaPage.php`, `app/Http/RequestHandlers/FixLevel0MediaAction.php`, `app/Http/RequestHandlers/ManageMediaPage.php`, `app/Http/RequestHandlers/ManageMediaAction.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Medienverwaltung im Admin-Bereich umfasst fünf Handler:
`AdminMediaFileDownload` (Datei-Download), `FixLevel0MediaPage`/`FixLevel0MediaAction`
(Level-0-Media-Fix), `ManageMediaPage`/`ManageMediaAction` (Media-Übersicht und -Verwaltung).
Die Handler nutzen `MediaFileService` und `TreeService` als Abhängigkeiten. Admin-Login
ist erforderlich. GEDCOM-Import mit Media-Records wird als Fixture benötigt.

---

## SUT-Kernbefunde

**AdminMediaFileDownload:**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `path` innerhalb `media_folder` → 200 + Datei-Download | Nein |
| B2 | `path` nicht in `media_folder` → 400 Bad Request | Nein |

**FixLevel0MediaPage:**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B3 | Immer → 200 OK | Nein |

**FixLevel0MediaAction:**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B4 | Gültige XREFs + Individual + Media existieren → Fact Update | Nein |
| B5 | XREFs gültig, aber Records fehlen → keine Änderung | Nein |
| B6 | Ungültige XREFs → Validator Exception | Nein |

**ManageMediaPage:**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B7 | `files` in `[local, external, unused]`, `subfolders` in `[include, exclude]` → 200 | Nein |
| B8 | `files` ungültig → Exception | Nein |

**ManageMediaAction:**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B9 | POST → Redirect zu ManageMediaPage | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Download: `path` innerhalb `media_folder` | 200 OK + Datei-Inhalt |
| EP2 | Download: `path` außerhalb `media_folder` | 400 Bad Request |
| EP3 | Download: `path` leer | 400 Bad Request |
| EP4 | FixLevel0Page: Normaler Aufruf | 200 OK |
| EP5 | FixLevel0Action: gültige XREFs, Individual + Media existieren | Fact Update durchgeführt |
| EP6 | FixLevel0Action: gültige XREFs, Records fehlen | Keine Änderung |
| EP7 | FixLevel0Action: ungültige XREFs | Validator Exception |
| EP8 | ManageMediaPage: `files=local` | 200 OK, lokale Dateien angezeigt |
| EP9 | ManageMediaPage: `files=invalid` | Exception |
| EP10 | ManageMediaAction: POST-Request | 302 Redirect zu ManageMediaPage |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| `path` Leerstring | `''` | 400 Bad Request |
| `path` gültige Datei | `media/photo.jpg` | 200 OK |
| `path` Traversal-Versuch | `../../etc/passwd` | 400 Bad Request |
| `files` Wert `local` | `local` | 200 OK |
| `files` Wert `external` | `external` | 200 OK |
| `files` Wert `unused` | `unused` | 200 OK |
| `files` Wert ungültig | `invalid` | Exception |

---

## Empfohlene Strategie

- **Testklasse:** `AdminMediaManagementIntegrationTest`
- **Strategie:** EP (Äquivalenzklassen-basiert)
- **Priorität:** Hoch
- **Fixtures:** Admin-User anlegen, Tree mit GEDCOM-Import inkl. Media-Records,
  Media-Dateien im `media_folder` bereitstellen
- **Dependencies:** `MediaFileService`, `TreeService` — real durchlaufen
- **Mocking:** Kein Mocking nötig
- **Besonderheit:** Admin-Login erforderlich — Auth im Test-Setup konfigurieren.
  Path-Traversal-Angriff (`../../etc/passwd`) als Sicherheitstest (EP2/BVA).
  GEDCOM-Import mit Media-Records als Fixture für FixLevel0-Tests.

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `AdminMediaManagementIntegrationTest [EP] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2, 3` enthalten — bereits korrekt) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen (A08 ggf. ergänzen) |
| `docs/tds_methodik_spec.md` | Ggf. Admin-DataTable-Handler-Testing als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ✅ | 5 Tests, 17 Assertions, passed |
| P5: Dokumentation | ✅ | |
