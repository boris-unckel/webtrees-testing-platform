<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — E01: Person/Familie anlegen & verknüpfen

**Referenz:** E01 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/add-child-to-individual`, `/tree/{tree}/add-parent-to-individual`, `/tree/{tree}/add-spouse-to-individual` (GET+POST) → `AddChildToIndividualPage`/`AddChildToIndividualAction`, `AddParentToIndividualPage`, `AddSpouseToIndividualPage`, `AddChildToFamilyPage`, `AddSpouseToFamilyPage`
**L3-Referenztest:** AddRelationIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für das Anlegen und Verknüpfen von Personen und Familien existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (AddRelationIntegrationTest) decken die Handler-Ebene ab (3 Tests: AddChild POST→302, AddParent/AddSpouse/AddChild GET→200), prüfen aber nicht das vollständige Formular-Rendering und die End-to-End-Interaktion im Browser.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/add-child-to-individual` | GET | `AddChildToIndividualPage` |
| `/tree/{tree}/add-child-to-individual` | POST | `AddChildToIndividualAction` |
| `/tree/{tree}/add-parent-to-individual` | GET | `AddParentToIndividualPage` |
| `/tree/{tree}/add-spouse-to-individual` | GET | `AddSpouseToIndividualPage` |
| `/tree/{tree}/add-child-to-family` | GET | `AddChildToFamilyPage` |
| `/tree/{tree}/add-spouse-to-family` | GET | `AddSpouseToFamilyPage` |

Alle Handler erfordern Editor-Berechtigung (mindestens). Die GET-Handler rendern Formulare mit GEDCOM-Eingabefeldern (Name, Geschlecht, Geburtsdatum). Die POST-Handler erzeugen neue INDI/FAM-Records und leiten auf die Personenseite weiter (302).

### View-Analyse

Die Formulare nutzen webtrees-Standard-Formularfelder (Bootstrap-basiert). Felder: `input[name="GIVN"]`, `input[name="SURN"]`, `select[name="SEX"]`, Datumsfelder. Das Layout ist theme-abhängig (unterschiedliche CSS-Klassen, aber gleiche `name`-Attribute).

### Theme-Abhängigkeit

Formular-Rendering (Labels, Layouts, Abstände) variiert zwischen Themes. Die funktionalen Elemente (Input-Namen, Submit-Button) sind theme-unabhängig. Theme-Loop ist sinnvoll für visuelle Regression, funktional ausreichend wäre ein einzelnes Theme.

---

## L3-Referenz-Analyse

**AddRelationIntegrationTest** — 3 Tests:

1. AddChild POST mit gültigem XREF → 302 (Redirect auf neue Person)
2. AddParent/AddSpouse/AddChild GET mit gültigem XREF → 200 (Formular wird gerendert)
3. Guard-Tests: Ungültiger XREF → 302 (Redirect auf Fehlermeldung)

Die L3-Tests validieren die HTTP-Ebene (Statuscodes, Redirects). Sie prüfen nicht das DOM-Rendering der Formulare und nicht die fachliche Korrektheit des angelegten Records (z.B. ob der Name auf der Personenseite sichtbar ist).

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Test-Pattern für Formular-basiertes Personen-/Familien-Anlegen. Als Referenz dienen die bestehenden Theme-Loop-Tests (z.B. `pedigree.spec.ts`) für die Iteration über Themes und die Login-Helper-Nutzung.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Formular "Kind hinzufügen" öffnet sich korrekt (GET→200, Formular sichtbar) | Admin | Seite lädt, Formularfelder (GIVN, SURN, SEX) sichtbar | Ja |
| T2 | Formular "Elternteil hinzufügen" öffnet sich korrekt | Admin | Seite lädt, Formularfelder sichtbar | Ja |
| T3 | Formular "Ehepartner hinzufügen" öffnet sich korrekt | Admin | Seite lädt, Formularfelder sichtbar | Ja |
| T4 | Kind hinzufügen via Formular-Submit, neuer Record sichtbar | Admin | POST→302, Redirect auf Personenseite, Name des neuen Kindes sichtbar | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Formular-Submit-Verification (Konzept 1)
**Begründung:** Die Formulare sind theme-abhängig im Rendering (Bootstrap-Layout variiert). Die funktionale Interaktion (Felder ausfüllen, Submit, Redirect-Verification) folgt dem Konzept 1 aus den übergreifenden Konzepten. T1–T3 sind Smoke-Level (Formular lädt), T4 ist Spec-C (fachlicher Effekt nach Submit).

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `person-family-create.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login (Editor-Berechtigung erforderlich) |
| **Baum** | demo (XREF X1030 für Person, f1 für Familie) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `person-family-create.spec.ts` [Spec-C] ✅ *(4 Tests)* |
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
