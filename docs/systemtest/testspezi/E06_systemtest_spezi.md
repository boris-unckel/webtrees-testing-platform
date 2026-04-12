<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — E06: Sortierung (Reorder)

**Referenz:** E06 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/reorder-children/{xref}` (GET), `/tree/{tree}/reorder-names/{xref}` (GET), `/tree/{tree}/reorder-families/{xref}` (GET) → `ReorderChildrenPage`/`ReorderChildrenAction`, `ReorderNamesPage`/`ReorderNamesAction`, `ReorderFamiliesPage`/`ReorderFamiliesAction`
**L3-Referenztest:** ReorderIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Sortierungsfunktionen (Reorder Children, Names, Families) existieren bisher keine L4-Systemtests. Die L3-Tests (ReorderIntegrationTest) decken die GET-Seiten-Rendering und Guard-Verhalten ab. Die tatsächliche Drag-and-Drop- oder Button-basierte Sortier-Interaktion im Browser ist nicht getestet.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/reorder-children/{xref}` | GET | `ReorderChildrenPage` |
| `/tree/{tree}/reorder-children/{xref}` | POST | `ReorderChildrenAction` |
| `/tree/{tree}/reorder-names/{xref}` | GET | `ReorderNamesPage` |
| `/tree/{tree}/reorder-names/{xref}` | POST | `ReorderNamesAction` |
| `/tree/{tree}/reorder-families/{xref}` | GET | `ReorderFamiliesPage` |
| `/tree/{tree}/reorder-families/{xref}` | POST | `ReorderFamiliesAction` |

Alle Handler erfordern Editor-Berechtigung (effektiv Admin für diese Verwaltungsfunktion). Die GET-Handler zeigen eine sortierbare Liste der Kinder/Namen/Familien. Die POST-Handler speichern die neue Reihenfolge.

### View-Analyse

Die Seiten zeigen sortierbare Listen mit Drag-and-Drop-Unterstützung oder Auf/Ab-Buttons. Die Elemente enthalten Hidden-Inputs mit der aktuellen Reihenfolge. Das Layout ist minimalistisch und primär funktional.

### Theme-Abhängigkeit

Die Reorder-Seiten sind Admin-/Editor-Funktionen mit minimalem Theme-Einfluss. Die Listen und Buttons sind in allen Themes funktional identisch. Kein Theme-Loop erforderlich.

---

## L3-Referenz-Analyse

**ReorderIntegrationTest** — 4 Tests:

1. ReorderChildrenPage GET mit gültigem XREF (Familie f1) → 200
2. ReorderNamesPage GET mit gültigem XREF (Person) → 200
3. ReorderFamiliesPage GET mit gültigem XREF (Person) → 200
4. ReorderChildrenPage GET mit unbekanntem XREF → HttpNotFoundException

Die L3-Tests validieren das Seiten-Rendering und Guard-Verhalten. Die Smoke-Level-Prüfung (Seite lädt korrekt) ist für L4 ausreichend, da die Sortier-Interaktion (Drag-and-Drop) komplex zu automatisieren wäre und der fachliche Mehrwert gering ist.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Test-Pattern für Reorder-Seiten. Das Admin-Only-Pattern (wf_code-to-systemtest_guide.md 4.5) ist direkt anwendbar: Seite laden, DOM-Elemente prüfen, kein Theme-Loop.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Reorder-Children-Seite lädt für Familie f1 | Admin | Seite lädt (200), sortierbare Liste mit Kindern sichtbar | Nein |
| T2 | Reorder-Names-Seite lädt für Person X1030 | Admin | Seite lädt (200), sortierbare Liste mit Namen sichtbar | Nein |
| T3 | Reorder-Families-Seite lädt für Person X1030 | Admin | Seite lädt (200), sortierbare Liste mit Familien sichtbar | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only (wf_code-to-systemtest_guide.md 4.5)
**Begründung:** Die Reorder-Seiten sind reine Admin-Funktionen ohne signifikante Theme-Abhängigkeit. Smoke-Level-Tests (Seite lädt korrekt, Liste sichtbar) sind ausreichend, da die Sortier-Interaktion (Drag-and-Drop) in L3 indirekt über POST-Handler abgedeckt ist.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `reorder.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein (Admin-Funktionalität) |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo (XREF X1030 Person, f1 Familie) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `reorder.spec.ts` [Smoke] ✅ *(3 Tests)* |
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
