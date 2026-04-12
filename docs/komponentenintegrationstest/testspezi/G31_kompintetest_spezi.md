<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — G31: GEDCOM-Import via CLI

**Referenz:** G31 | **SUT:** `app/Cli/Commands/TreeImport.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Der CLI-Command `tree-import` importiert eine GEDCOM-Datei in einen
bestehenden Baum. Er erwartet zwei Pflicht-Argumente (`tree-name`, `gedcom-file`) und vier
optionale Optionen (`--encoding`, `--keep-media`, `--conc-spaces`, `--gedcom-media-path`).
Die Kernlogik delegiert an `GedcomImportService` und `TreeService` via Dependency Injection.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Baum nicht gefunden → FAILURE | Nein |
| B2 | Datei nicht vorhanden → FAILURE | Nein |
| B3 | `keep-media=true` → alte Media erhalten | Nein |
| B4 | `keep-media=false` → alle Daten löschen vor Import | Nein |
| B5 | Import-Exception → FAILURE + Rollback | Nein |
| B6 | Erfolgreicher Import → SUCCESS | Nein |
| B7 | `--encoding` gesetzt → Encoding-Option weitergereicht | Nein |
| B8 | `--conc-spaces=true` → Leerzeichen bei CONC eingefügt | Nein |
| B9 | `--gedcom-media-path` gesetzt → Preference gesetzt | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Gültiger Baum + gültige Datei + `keep-media=false` | SUCCESS, Daten importiert |
| EP2 | Gültiger Baum + gültige Datei + `keep-media=true` | SUCCESS, alte Media erhalten |
| EP3 | Baum nicht gefunden | FAILURE |
| EP4 | Datei nicht vorhanden | FAILURE |
| EP5 | Encoding-Option gesetzt (z. B. `UTF-8`) | SUCCESS, Encoding korrekt angewendet |
| EP6 | Import-Exception (korrupte GEDCOM) | FAILURE + Rollback |
| EP7 | `conc-spaces=true` | Leerzeichen bei CONC-Zeilen eingefügt |
| EP8 | `gedcom-media-path` gesetzt (z. B. `media/`) | Preference gesetzt |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| Dateigröße 0 Byte | Leere Datei | Import ohne Records |
| Dateigröße 8192 Byte (Buffer-Grenze) | Exakt 8192 Byte | Korrekt importiert, kein Split-Problem |
| Dateigröße 8193 Byte | 8193 Byte | Buffer-Splitting korrekt behandelt |

---

## Empfohlene Strategie

- **Testklasse:** `TreeImportCommandIntegrationTest`
- **Strategie:** EP (Äquivalenzklassen-basiert)
- **Priorität:** Hoch
- **Referenz:** `TreeExportCommandIntegrationTest.php` als Muster für CLI-Command-Tests
- **Fixtures:** Baum in DB anlegen, GEDCOM-Testdateien in verschiedenen Größen/Formaten bereitstellen
- **Dependencies:** `GedcomImportService`, `TreeService` via DI — real durchlaufen
- **Mocking:** Kein Mocking nötig, da Dependency Injection vorhanden
- **Besonderheit:** Buffer-Splitting an 8192-Byte-Grenze testen (BVA)

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `<Testklasse> [<Siegel>] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen |
| `docs/tds_methodik_spec.md` | Ggf. CLI-Command-Testing als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
