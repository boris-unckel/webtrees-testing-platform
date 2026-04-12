<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — A16: CLI Baum-Listing

**Referenz:** A16 | **SUT:** `app/Cli/Commands/TreeList.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Der CLI-Command `tree-list` gibt eine Liste aller Stammbäume in
einem konfigurierbaren Format aus. Unterstützte Formate: `table` (Default), `json`, `csv`.
Die Logik ist identisch zum `user-list`-Command (Format-Switch), ergänzt um die Felder
`imported` (boolean → `'yes'`/`'no'`). Die Abhängigkeit `TreeService` wird per Dependency
Injection aufgelöst.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `format=table` → Table-Helper-Ausgabe | Nein |
| B2 | `format=csv` → CSV-Rows-Ausgabe | Nein |
| B3 | `format=json` → `json_encode`-Ausgabe | Nein |
| B4 | Default (ungültiges Format) → FAILURE "Invalid format" | Nein |
| B5 | `imported=true` → `'yes'` in Ausgabe | Nein |
| B6 | `imported=false` → `'no'` in Ausgabe | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | `format=table` + keine Bäume | Leere Tabelle, SUCCESS |
| EP2 | `format=table` + 1 Baum | Tabelle mit einer Zeile, SUCCESS |
| EP3 | `format=csv` + Spezialzeichen in Title | Korrekt escaped, SUCCESS |
| EP4 | `format=json` + Unicode-Zeichen im Baum-Title | Korrekt kodiert, SUCCESS |
| EP5 | Ungültiges Format (z. B. `xml`) | FAILURE "Invalid format" |
| EP6 | Baum mit `imported=true` | Spalte/Feld zeigt `'yes'` |
| EP7 | Baum mit `imported=false` | Spalte/Feld zeigt `'no'` |

---

## Grenzwerte (BVA)

Keine signifikanten Grenzwerte jenseits der EP-Abdeckung — Format-Validierung ist identisch
zu `user-list` (siehe P42).

---

## Empfohlene Strategie

- **Testklasse:** `TreeListCommandIntegrationTest`
- **Strategie:** Spec-C (spezifikationsbasiert, Conditions-Coverage)
- **Priorität:** Niedrig
- **Fixtures:** Bäume in DB anlegen (0, 1, mehrere), mit/ohne GEDCOM-Import
- **Dependencies:** `TreeService` via DI — real durchlaufen
- **Mocking:** Kein Mocking nötig
- **Besonderheit:** `imported`-Flag testen (boolean → String-Mapping `yes`/`no`)

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
