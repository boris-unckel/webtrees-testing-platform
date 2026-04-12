<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S05: Erweiterte Suche (Felder)

**Referenz:** S05 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/search-advanced` (GET Page), POST Action → `SearchAdvancedPage`, `SearchAdvancedAction`
**L3-Referenztest:** SearchIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die erweiterte Suche mit Feldfiltern existieren bisher keine L4-Systemtests. Die bestehende `search-forms.spec.ts` prüft nur das Rendering der Suchformulare (GET → DOM sichtbar), nicht die eigentliche Suchergebnis-Qualität. Die L3-Komponentenintegrationstests (SearchIntegrationTest) decken partiell Name, Nachname und Multi-Feld-Suche ab (je ca. 8 Tests), prüfen aber nicht die End-to-End-Interaktion im Browser.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/search-advanced` | GET | `SearchAdvancedPage` |
| `/tree/{tree}/search-advanced` | POST | `SearchAdvancedAction` |

Beide Handler erfordern Viewer-Berechtigung (mindestens). Der GET-Handler rendert das Suchformular mit mehreren Filterfeldern (Vorname, Nachname, Geburtsort, Geburtsdatum etc.). Der POST-Handler führt die Suche aus und gibt die Ergebnistabelle zurück.

### View-Analyse

Das erweiterte Suchformular nutzt Bootstrap-basierte Eingabefelder mit `name`-Attributen für die GEDCOM-Tags (z.B. `GIVN`, `SURN`). Die Ergebnisse werden in einer Tabelle dargestellt. Selektoren: `form` für das Suchformular, `table` bzw. `.wt-table` für die Ergebnistabelle.

### Theme-Abhängigkeit

Das Formular-Layout (Labels, Feldanordnung, Abstände) variiert zwischen Themes. Die funktionalen Elemente (`name`-Attribute, Submit-Button) sind theme-unabhängig. Theme-Loop ist sinnvoll.

---

## L3-Referenz-Analyse

**SearchIntegrationTest** — partiell ca. 8 Tests für Feld-Suche:

- Suche nach Vorname liefert Treffer (EP: gültige Eingabe)
- Suche nach Nachname liefert Treffer (EP: gültige Eingabe)
- Multi-Feld-Suche (Vorname + Nachname) schränkt Ergebnisse ein (BVA: Kombination)
- Guard-Tests: Leere Suche, nicht existierende Namen

Die L3-Tests validieren die Handler-Ebene (HTTP-Response, Ergebnis-Arrays). Sie prüfen nicht das DOM-Rendering der Ergebnistabelle und nicht die visuelle Darstellung im Browser.

---

## Bestehende L4-Muster-Analyse

`search-forms.spec.ts` testet Formular-Rendering (Smoke-Level). Die Such-Ausführungs-Verification (Eingabe → Submit → Ergebnisverifikation) ist ein neues Pattern, beschrieben in Konzept 3 der übergreifenden Konzepte. Dieses Feature teilt sich die Spec-Datei mit S06 (Datum-Modifikatoren) gemäß Konzept 8 (Zusammenlegung).

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Erweiterte Suchseite rendert Formular (GET→200, form sichtbar) | Admin | Seite lädt, Suchformular mit Eingabefeldern sichtbar | Ja |
| T2 | Suche nach Vorname "Elizabeth" liefert Treffer in Ergebnistabelle | Admin | Ergebnistabelle enthält mindestens einen Treffer mit "Elizabeth" | Ja |
| T3 | Suche nach Nachname "Windsor" liefert Treffer | Admin | Ergebnistabelle enthält mindestens einen Treffer mit "Windsor" | Ja |
| T4 | Multi-Feld-Suche (Vorname+Nachname) schränkt Ergebnisse ein | Admin | Ergebnismenge ist kleiner als bei Einzelfeld-Suche | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Such-Ausführungs-Verification (Konzept 3)
**Begründung:** Die Suchformulare sind theme-abhängig im Rendering. Die funktionale Interaktion (Felder ausfüllen, Submit, Ergebnistabelle verifizieren) folgt dem Konzept 3 aus den übergreifenden Konzepten. Alle Tests prüfen fachliche Suchergebnis-Qualität, nicht nur Rendering.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `advanced-search-execution.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo (Elizabeth, Windsor, etc.) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `advanced-search-execution.spec.ts` [Spec-C] ✅ *(4 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Abgrenzung

`search-forms.spec.ts` testet nur das Formular-Rendering (Smoke-Level). Dieses Feature testet die Suchergebnis-Qualität nach Formular-Submit. Die Spec-Datei `advanced-search-execution.spec.ts` wird mit S06 (Datum-Modifikatoren) geteilt (Konzept 8 Zusammenlegung).

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
