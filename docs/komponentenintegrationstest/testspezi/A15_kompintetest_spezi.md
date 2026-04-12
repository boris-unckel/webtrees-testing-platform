<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — A15: CLI Übersetzung kompilieren

**Referenz:** A15 | **SUT:** `app/Cli/Commands/CompilePoFiles.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Der CLI-Command `compile-po-files` kompiliert alle PO-Dateien
(Gettext-Übersetzungen) in PHP-Dateien. Er sucht per `glob()` nach PO-Dateien, parst jede
mit einer externen Translation-Library und schreibt das Ergebnis als PHP-Array in eine
gleichnamige PHP-Datei. Testbarkeit ist eingeschränkt (Mittel), da keine Dependency Injection
vorhanden ist und externe File-I/O sowie eine Translation-Library verwendet werden.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `glob()` leer oder `false` → FAILURE "no PO files" | Nein |
| B2 | PO-Datei gültig → PHP-Datei erzeugt | Nein |
| B3 | `file_put_contents` gibt `false` zurück → `error=true` | Nein |
| B4 | Alle PO-Dateien erfolgreich → SUCCESS | Nein |
| B5 | Mindestens ein Fehler → FAILURE | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | 0 PO-Dateien im Verzeichnis | FAILURE "no PO files" |
| EP2 | 1 gültige PO-Datei | SUCCESS, 1 PHP-Datei erzeugt |
| EP3 | 5 gültige PO-Dateien | SUCCESS, 5 PHP-Dateien erzeugt |
| EP4 | PO-Datei mit 0 Translations (leere PO) | SUCCESS, leeres Array in PHP-Datei |
| EP5 | Zielverzeichnis nicht writable | FAILURE |
| EP6 | Mehrere PO-Dateien, eine fehlgeschlagen | FAILURE (mindestens ein Fehler) |

---

## Grenzwerte (BVA)

Keine signifikanten Grenzwerte jenseits der EP-Abdeckung — die Logik ist dateibasiert
(vorhanden/nicht vorhanden, schreibbar/nicht schreibbar).

---

## Empfohlene Strategie

- **Testklasse:** `CompilePoFilesCommandIntegrationTest`
- **Strategie:** Spec-C (spezifikationsbasiert, Conditions-Coverage)
- **Priorität:** Mittel
- **Testbarkeit:** Mittel — keine DI, externe Translation-Library, File-I/O
- **Fixtures:** Temporäres Verzeichnis mit PO-Testdateien (0, 1, 5 Dateien; gültig/leer)
- **Dependencies:** Externe Translation-Library (real durchlaufen)
- **Mocking:** Kein Mocking nötig, aber temporäre Verzeichnisse für isolierte File-I/O
- **Besonderheit:** Aufräumen der erzeugten PHP-Dateien nach dem Test. PO-Dateien müssen
  syntaktisch korrekt sein, damit die Translation-Library sie parsen kann.

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
