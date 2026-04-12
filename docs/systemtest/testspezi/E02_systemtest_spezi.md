<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — E02: Fakten bearbeiten

**Referenz:** E02 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/edit-fact/{xref}` (GET), `/tree/{tree}/edit-fact-action/{xref}` (POST), `/tree/{tree}/add-fact/{xref}` (GET) → `EditFactPage`, `EditFactAction`, `AddNewFact`
**L3-Referenztest:** EditFactIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für das Bearbeiten und Hinzufügen von Fakten (Events, Attribute) zu INDI-/FAM-Records existieren bisher keine L4-Systemtests. Die L3-Tests (EditFactIntegrationTest) decken die Handler-Ebene ab, prüfen aber weder das Formular-Rendering noch die fachliche Sichtbarkeit der Änderung auf der Personenseite.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/edit-fact/{xref}` | GET | `EditFactPage` |
| `/tree/{tree}/edit-fact-action/{xref}` | POST | `EditFactAction` |
| `/tree/{tree}/add-fact/{xref}` | GET | `AddNewFact` |

Die Handler erfordern Editor-Berechtigung. `AddNewFact` zeigt ein Formular zur Auswahl des Fakt-Typs (BIRT, DEAT, OCCU etc.) und zur Eingabe der Fakt-Daten. `EditFactPage` zeigt ein Bearbeitungsformular für einen bestehenden Fakt. `EditFactAction` speichert die Änderung und leitet auf die Personenseite weiter.

### View-Analyse

Die Formulare nutzen GEDCOM-spezifische Eingabefelder: Datum-Picker, Ort-AutoComplete, Fakt-Typ-Dropdown. Die Fakten-Tabelle auf der Personenseite zeigt alle Fakten als tabellarische Liste. Relevante Selektoren: `select[name="fact"]` (Fakt-Typ), Datumsfelder, Ortsfelder.

### Theme-Abhängigkeit

Die Fakten-Tabelle und Formulare werden theme-abhängig gerendert. Die funktionalen Elemente (Formularfelder, Submit-Button, Fakten-Anzeige) verwenden konsistente `name`-Attribute über alle Themes hinweg.

---

## L3-Referenz-Analyse

**EditFactIntegrationTest** — 3 Tests:

1. EditFactPage mit unbekanntem Fakt → 302 (Redirect, Fakt nicht gefunden)
2. DeleteFact mit unbekanntem Fakt → 204 (No Content, Guard-Verhalten)
3. AddNewFact GET mit gültigem XREF → 200 (Formular wird gerendert)

Die L3-Tests validieren primär Guard-Verhalten (ungültige Eingaben) und das grundlegende Rendering. Es fehlt die Prüfung des Formulars mit gültigen Daten und die Verification des gespeicherten Fakts.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Test-Pattern für Fakten-Bearbeitung. Das Formular-Submit-Verification-Pattern (Konzept 1) aus den übergreifenden Konzepten ist direkt anwendbar. Die Theme-Loop-Iteration folgt dem etablierten Muster aus bestehenden Specs.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | "Fakt hinzufügen"-Seite rendert korrekt (GET→200, Formular sichtbar) | Admin | Seite lädt, Fakt-Typ-Dropdown und Eingabefelder sichtbar | Ja |
| T2 | Neuen Fakt hinzufügen via Submit, Fakt auf Personenseite sichtbar | Admin | POST→302, Redirect auf Personenseite, neuer Fakt in Fakten-Tabelle sichtbar | Ja |
| T3 | Personenseite zeigt bestehende Fakten in Faktentabelle | Admin | Fakten-Tabelle mit mindestens einem Eintrag sichtbar | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Formular-Submit-Verification (Konzept 1)
**Begründung:** Fakten-Formulare und die Fakten-Tabelle auf der Personenseite sind theme-abhängig im Layout. Die funktionale Interaktion (Fakt-Typ wählen, Daten eingeben, Submit, Redirect, Fakten-Anzeige) folgt dem Formular-Submit-Verification-Pattern. T1/T3 sind Smoke-Level, T2 ist Spec-C.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `fact-edit.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login (Editor-Berechtigung erforderlich) |
| **Baum** | demo (XREF X1030) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `fact-edit.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
