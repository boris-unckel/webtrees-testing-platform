<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — P41: Datensatz-Zusammenführung (vollständig)

**Referenz:** P41 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/merge-facts` (GET Page + POST Action) → `MergeFactsPage`, `MergeFactsAction`
**L3-Referenztest:** MergeFactsActionIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Datensatz-Zusammenführung (Ausführungs-Schritt) existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (MergeFactsActionIntegrationTest) decken die Handler-Ebene umfassend ab (5 Tests: unknown xref1→302 Guard, same xref→302 Guard, different tags→302 Guard, pending deletion→302 Guard, happy path→302+DB pending deletion für xref2), prüfen aber nicht die tatsächliche Merge-Interaktion im Browser (Vergleichsansicht, Fakten-Auswahl, Merge-Bestätigung). P41 bildet den zweiten Schritt des zweistufigen Merge-Workflows (P30 → P41), der in der gemeinsamen Spec-Datei `merge-records.spec.ts` abgebildet wird (→ Konzept 8 Zusammenlegung).

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/merge-facts` | GET | `MergeFactsPage` |
| `/tree/{tree}/merge-facts` | POST | `MergeFactsAction` |

Beide Handler erfordern Admin-Berechtigung. Der GET-Handler rendert eine Vergleichsansicht beider Records (Fakten nebeneinander) mit Auswahlmöglichkeit, welche Fakten übernommen werden. Der POST-Handler führt die Zusammenführung durch: Der erste Record erhält die ausgewählten Fakten, der zweite Record wird zur Löschung markiert (pending deletion). Bei Erfolg leitet der Handler auf ManageTrees weiter (302).

### View-Analyse

Die MergeFactsPage zeigt eine zweispaltige Vergleichsansicht: Links Record 1 (xref1), rechts Record 2 (xref2). Jeder Fakt hat eine Checkbox zur Auswahl. Ein Merge-Button bestätigt die Zusammenführung. Die Seite enthält Guard-Logik: Bei ungültigen Eingaben (gleiche XREF, verschiedene Record-Typen, bereits gelöschter Record) erfolgt ein Redirect zurück zu MergeRecordsPage.

### Theme-Abhängigkeit

Kein Theme-Loop erforderlich. Die Merge-Funktionalität ist eine reine Admin-Funktion mit standardisiertem Admin-Layout, das nicht theme-abhängig variiert.

---

## L3-Referenz-Analyse

**MergeFactsActionIntegrationTest** — 5 Tests:

1. Unknown xref1 → 302 Guard (Redirect zurück zu MergeRecordsPage mit xref1/xref2 als Query-Params)
2. Same xref (xref1 == xref2) → 302 Guard (Redirect zurück zu MergeRecordsPage)
3. Different tags (z.B. INDI + FAM) → 302 Guard (Redirect zurück zu MergeRecordsPage)
4. Pending deletion (xref2 bereits gelöscht) → 302 Guard (Redirect zurück zu MergeRecordsPage)
5. Happy path (zwei gültige INDI-Records) → 302 (Redirect zu ManageTrees) + DB: xref2 hat pending deletion

Die L3-Tests validieren die HTTP-Ebene und DB-Zustand (Statuscodes, Redirects, pending deletion). Sie prüfen nicht das DOM-Rendering der Vergleichsansicht, die Fakten-Auswahl per Checkbox und die visuelle Bestätigung des Merge-Ergebnisses.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Test-Pattern für Merge-Workflows. Das Mehrstufiger-Workflow-Pattern (Konzept 4.1) aus den übergreifenden Konzepten definiert den Ablauf: Schritt 3 (P41) — Merge bestätigen und ausführen → Schritt 4 (P41) — Ergebnis verifizieren (ein Record bleibt, einer gelöscht). Die gemeinsame Spec-Datei `merge-records.spec.ts` bildet den gesamten Workflow (P30 → P41) als zusammenhängenden Test ab.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Fakten-Merge-Seite zeigt Vergleichsansicht beider Records (X1030, X1031) | Admin | Seite lädt (200), zweispaltige Vergleichsansicht mit Fakten beider Records sichtbar | Nein |
| T2 | Merge bestätigen → ein Record bleibt, anderer gelöscht | Admin | POST→302, Redirect zu ManageTrees, xref1-Record vorhanden, xref2-Record zur Löschung markiert | Nein |
| T3 | (Guard) Gleiche XREF für beide Records → Redirect zurück zu Merge-Auswahl | Admin | POST→302, Redirect zu MergeRecordsPage mit xref1/xref2 als Query-Params | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only + Mehrstufiger-Workflow (Konzept 4.1)
**Begründung:** Die Fakten-Zusammenführung ist der zweite Schritt des Merge-Workflows (P30 → P41). T1 ist Smoke-Level (Vergleichsansicht lädt korrekt), T2 ist Spec-C (fachlicher Effekt — ein Record bleibt, einer wird gelöscht), T3 prüft den Guard-Branch (gleiche XREF wird abgefangen). Kein Theme-Loop, da Admin-Workflow. Die Spec-Datei `merge-records.spec.ts` wird mit P30 geteilt (Konzept 8) und bildet den gesamten Workflow ab.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `merge-records.spec.ts` (shared mit P30, → Konzept 8 Zusammenlegung) |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein — Admin-Workflow, nicht theme-abhängig |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo (XREF X1030, X1031 — zwei Individuen) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `merge-records.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Workflow-Kette mit P30

P30 (Datensätze zusammenführen — Auswahl) und P41 (Datensatz-Zusammenführung — vollständig) bilden einen sequenziellen Workflow:

```
P30: MergeRecordsPage → XREFs eingeben → Submit → Redirect zu MergeFactsPage
P41: MergeFactsPage → Vergleichsansicht → Merge bestätigen → Redirect zu ManageTrees
```

Die gemeinsame Spec-Datei `merge-records.spec.ts` enthält beide Teile als zusammenhängende Testszenarien. Der End-to-End-Workflow-Test durchläuft alle Schritte sequenziell.

---

## L3-Guard-Pattern

MergeFactsAction enthält 4 Guard-Branches, die alle auf MergeRecordsPage zurückleiten:

| Guard | Bedingung | Redirect-Ziel |
|---|---|---|
| Unknown xref1 | xref1 existiert nicht in der Datenbank | MergeRecordsPage |
| Same xref | xref1 == xref2 | MergeRecordsPage (mit xref1/xref2) |
| Different tags | Record-Typen unterschiedlich (z.B. INDI + FAM) | MergeRecordsPage (mit xref1/xref2) |
| Pending deletion | xref2 bereits zur Löschung markiert | MergeRecordsPage (mit xref1/xref2) |

Der Happy-Path leitet zu ManageTrees weiter. T3 prüft den Same-XREF-Guard als repräsentativen Guard-Test im L4.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
