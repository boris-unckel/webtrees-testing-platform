<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S47: Interaktiver Stammbaum

**Referenz:** S47 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/tree` (InteractiveTree-Modul) → `InteractiveTreeModule` (JS-basiertes Widget mit AJAX-Endpoints)
**L3-Referenztest:** InteractiveTreeIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für den interaktiven Stammbaum existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (InteractiveTreeIntegrationTest) decken 3 Tests ab: getDetails liefert XREF-HTML, 'p'-Request liefert drawPerson, 'c'-Request liefert drawChildren. Diese prüfen die AJAX-Endpoints auf Handler-Ebene, nicht die JavaScript-Widget-Interaktion im Browser.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/tree` | GET | `InteractiveTreeModule` |

Der Handler erfordert Viewer-Berechtigung (mindestens). Die Seite rendert ein JavaScript-basiertes interaktives Stammbaum-Widget, das über AJAX-Endpoints Personendaten und Kindknoten nachlädt.

### View-Analyse

Das Widget rendert entweder ein Canvas- oder SVG-Element (muss in P3 aus dem Upstream-JavaScript-Code ermittelt werden). Personenknoten zeigen Name und Lebensdaten. Klick auf einen Knoten öffnet ein Detail-Panel oder navigiert zur Personenseite. Selektor-Fallback: `canvas, svg, .wt-chart`. AJAX-Endpoints: `?request=p` (drawPerson), `?request=c` (drawChildren).

### Theme-Abhängigkeit

Widget-Rendering kann theme-abhängig sein (Farben, Schriftgrößen innerhalb des Canvas/SVG). Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**InteractiveTreeIntegrationTest** — 3 Tests:

1. `getDetails` mit gültigem XREF liefert HTML mit Personendaten
2. `p`-Request liefert drawPerson-Daten (JSON/HTML für Personenknoten)
3. `c`-Request liefert drawChildren-Daten (JSON/HTML für Kindknoten)

Die L3-Tests validieren die AJAX-Response-Inhalte. Sie prüfen nicht die JavaScript-Widget-Initialisierung, das Rendering im Canvas/SVG und die Knoten-Interaktion.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Pattern für JavaScript-Widget-Interaktion. Konzept 2.2 (Canvas/SVG-Widget) aus den übergreifenden Konzepten beschreibt das Pattern: Element auf Sichtbarkeit prüfen, Knoten-Interaktion, Detail-Panel verifizieren. Ob Canvas oder SVG gerendert wird, muss in P3 aus dem JS-Quellcode ermittelt werden.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Interaktiver Stammbaum lädt (Canvas/SVG-Element sichtbar) | Admin | Widget-Container (canvas, svg oder .wt-chart) ist sichtbar | Ja |
| T2 | Widget zeigt Personendaten (Name, Lebensdaten im Widget) | Admin | Personenname und/oder Lebensdaten sind im Widget-Bereich sichtbar | Ja |
| T3 | Knoten-Klick öffnet Detail-Panel oder navigiert | Admin | Nach Klick auf einen Knoten: Detail-Panel sichtbar oder Navigation zur Personenseite | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + JS-Widget-Interaktion (Konzept 2.2)
**Begründung:** Das interaktive Stammbaum-Widget ist ein komplexes JavaScript-Widget, das Canvas oder SVG rendert und AJAX-Endpoints für Daten verwendet. T1 ist Smoke-Level (Widget lädt), T2 prüft Datenanzeige, T3 testet die Knoten-Interaktion. Der Aufwand ist hoch wegen der Widget-Komplexität.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `interactive-tree.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo (XREF X1030) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `interactive-tree.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Aufwand

Hoch — JavaScript-Widget-Interaktion erfordert:
- Ermittlung des Render-Typs (Canvas vs. SVG) aus dem Upstream-JS-Code
- Widget-spezifische Selektoren und Interaktions-API
- AJAX-Ladezeiten abwarten (`waitForResponse` oder `waitForLoadState`)

---

## Hinweis

Ob Canvas oder SVG gerendert wird, muss in Phase P3 aus dem JS-Quellcode ermittelt werden. Selektor-Fallback: `canvas, svg, .wt-chart`. Die Interaktions-API unterscheidet sich grundlegend zwischen Canvas (Koordinaten-basiert) und SVG (DOM-basiert).

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
