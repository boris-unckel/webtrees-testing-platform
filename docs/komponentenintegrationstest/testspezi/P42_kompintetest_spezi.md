<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — P42: CLI Benutzer-Listing

**Referenz:** P42 | **SUT:** `app/Cli/Commands/UserList.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Der CLI-Command `user-list` gibt eine Liste aller Benutzer in
einem konfigurierbaren Format aus. Unterstützte Formate: `table` (Default), `json`, `csv`.
Bei ungültigem Format wird FAILURE zurückgegeben. Die Abhängigkeit `UserService` wird per
Dependency Injection aufgelöst.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `format=table` → Table-Helper-Ausgabe | Nein |
| B2 | `format=csv` → CSV-Rows-Ausgabe | Nein |
| B3 | `format=json` → `json_encode`-Ausgabe | Nein |
| B4 | Default (ungültiges Format) → FAILURE "Invalid format" | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | `format=table` + keine User | Leere Tabelle, SUCCESS |
| EP2 | `format=table` + 1 User | Tabelle mit einer Zeile, SUCCESS |
| EP3 | `format=csv` + keine User | Leere CSV-Ausgabe, SUCCESS |
| EP4 | `format=csv` + Spezialzeichen in Daten | Korrekt escaped, SUCCESS |
| EP5 | `format=json` + Unicode-Zeichen | Korrekt kodiert, SUCCESS |
| EP6 | Ungültiges Format (z. B. `xml`) | FAILURE "Invalid format" |
| EP7 | User mit `Timestamp=0` (Registrierung/Login) | Korrekt dargestellt |
| EP8 | User mit leerer Email | Leeres Feld, kein Fehler |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| Format Leerstring | `''` | FAILURE "Invalid format" |
| Format exakt `table` | `table` | SUCCESS, Tabellenausgabe |
| Format Case-Variation | `Table` | FAILURE (Case-sensitiv) |
| User-Anzahl 0 | Keine User in DB | Leere Ausgabe, SUCCESS |
| User-Anzahl 1 | Genau 1 User | Eine Zeile/Eintrag |
| User-Anzahl 100 | 100 User | 100 Zeilen/Einträge |

---

## Empfohlene Strategie

- **Testklasse:** `UserListCommandIntegrationTest`
- **Strategie:** Spec-C (spezifikationsbasiert, Conditions-Coverage)
- **Priorität:** Niedrig
- **Fixtures:** User in DB anlegen (0, 1, 100 User), Spezialzeichen und Unicode-Daten
- **Dependencies:** `UserService` via DI — real durchlaufen
- **Mocking:** Kein Mocking nötig

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
