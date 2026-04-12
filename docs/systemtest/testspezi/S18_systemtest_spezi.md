<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S18: Charts: 5 fehlende Typen

**Referenz:** S18 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/timeline`, `/tree/{tree}/lifespans`, `/tree/{tree}/family-book`, `/tree/{tree}/descendants`, `/tree/{tree}/branches` → `TimelineChartPage`, `LifespansChartPage`, `FamilyBookChartPage`, `DescendancyChartPage`, `BranchesListPage`
**L3-Referenztest:** ChartModuleIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die fünf Chart-Typen Timeline, Lifespans, FamilyBook, Descendants und Branches existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (ChartModuleIntegrationTest) decken 22 Tests ab, einschließlich Style-Varianten. Die bestehenden L4-Tests decken andere Chart-Typen ab (z.B. Pedigree, FanChart), aber nicht diese fünf.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/timeline` | GET | `TimelineChartPage` |
| `/tree/{tree}/lifespans` | GET | `LifespansChartPage` |
| `/tree/{tree}/family-book` | GET | `FamilyBookChartPage` |
| `/tree/{tree}/descendants` | GET | `DescendancyChartPage` |
| `/tree/{tree}/branches` | GET | `BranchesListPage` |

Alle Handler erfordern Viewer-Berechtigung (mindestens). Jeder Handler rendert einen spezifischen Chart-Typ mit individuellen Parametern (XREF, Style, Generationen etc.).

### View-Analyse

Jeder Chart-Typ hat ein eigenes Layout. Timeline und Lifespans zeigen zeitbasierte Darstellungen. FamilyBook und Descendants zeigen hierarchische Baumstrukturen. Branches zeigt eine Namensliste. Gemeinsamer Selektor: Chart-Container (`.wt-chart`, `canvas`, `svg` oder tabellarisch). XREF-Parameter: X1030 für personenbasierte Charts, Nachname "Windsor" für Branches.

### Theme-Abhängigkeit

Chart-Rendering variiert zwischen Themes (Farben, Schriftgrößen, Layout-Abstände). Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**ChartModuleIntegrationTest** — 22 Tests:

- Timeline: Chart-Seite lädt (HTTP 200)
- Lifespans: Chart-Seite lädt (HTTP 200)
- FamilyBook: Chart-Seite lädt (HTTP 200) + Style-Varianten
- Descendants: Chart-Seite lädt (HTTP 200) + Style-Varianten
- Branches: Liste lädt (HTTP 200) + Nachnamen-Filterung

Die L3-Tests validieren HTTP-Statuscodes. Sie prüfen nicht das DOM-Rendering der Chart-Container.

---

## Bestehende L4-Muster-Analyse

`pedigree.spec.ts` und andere Chart-Specs dienen als Referenz für Chart-Rendering-Verification (Konzept 6). Die fünf neuen Chart-Typen folgen dem gleichen Smoke-Pattern: Seite laden, Chart-Container auf Sichtbarkeit prüfen.

---

## Testszenarien (DataProvider-artig über Array)

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Timeline-Chart lädt ohne Fehler (chart-Container sichtbar) | Admin | Seite lädt (HTTP <500), Chart-Container sichtbar | Ja |
| T2 | Lifespans-Chart lädt ohne Fehler | Admin | Seite lädt (HTTP <500), Chart-Container sichtbar | Ja |
| T3 | FamilyBook-Chart lädt ohne Fehler | Admin | Seite lädt (HTTP <500), Chart-Container sichtbar | Ja |
| T4 | Descendancy-Chart lädt ohne Fehler | Admin | Seite lädt (HTTP <500), Chart-Container sichtbar | Ja |
| T5 | Branches-Liste lädt ohne Fehler | Admin | Seite lädt (HTTP <500), Listen-Container sichtbar | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Chart-Rendering-Verification (Konzept 6) — Smoke-Level
**Begründung:** Alle fünf Charts folgen dem gleichen Smoke-Pattern. Die Testszenarien werden DataProvider-artig über ein Array iteriert (Route + erwarteter Container-Selektor). Niedriger Aufwand durch identische Struktur — nur die Routen und ggf. Parameter unterscheiden sich.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `chart-types.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo (XREF X1030, Nachname "Windsor" für Branches) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `chart-types.spec.ts` [Smoke] ✅ *(5 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Aufwand

Niedrig — gleiche Struktur für alle fünf Chart-Typen. DataProvider-Array mit Routen und Selektoren. Ein `describe`-Block mit Schleife über das Array.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
