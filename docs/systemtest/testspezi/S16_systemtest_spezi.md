<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S16: Chart: Beziehungsfinder

**Referenz:** S16 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/relationships` (GET) → `RelationshipsChartPage`
**L3-Referenztest:** RelationshipServiceIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für den Beziehungsfinder-Chart existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (RelationshipServiceIntegrationTest) decken 12 Tests ab: Ehepaare, Eltern-Kind, Onkel, Cousin etc. Diese prüfen die Beziehungsberechnung auf Service-Ebene, nicht die visuelle Darstellung im Browser.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/relationships` | GET | `RelationshipsChartPage` |

Der Handler erfordert Viewer-Berechtigung (mindestens). Die Seite rendert ein Formular zur Auswahl von zwei Personen und zeigt nach Auswahl den Beziehungspfad als visuelles Chart an.

### View-Analyse

Das Formular enthält zwei Personen-Auswahlfelder (TomSelect/AutoComplete), einen Submit-Button und den Chart-Bereich für den Beziehungspfad. Nach Auswahl und Submit wird der Beziehungspfad als Grafik oder Text dargestellt. Selektoren: Formular (`form`), Personen-Selects, Chart-Container, Beziehungslabel.

### Theme-Abhängigkeit

Chart-Rendering und Formular-Layout variieren zwischen Themes. Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**RelationshipServiceIntegrationTest** — 12 Tests:

- Ehepaare: Beziehungspfad "husband"/"wife"
- Eltern-Kind: Beziehungspfad "son"/"daughter"/"father"/"mother"
- Onkel/Tante: Beziehungspfad "uncle"/"aunt"
- Cousin: Beziehungspfad "cousin"
- Weitere Verwandtschaftsgrade

Die L3-Tests validieren den berechneten Beziehungspfad als String. Sie prüfen nicht die visuelle Darstellung des Pfades im Browser.

---

## Bestehende L4-Muster-Analyse

`pedigree.spec.ts` dient als Referenz für Chart-Rendering-Pattern (Konzept 6). Der Beziehungsfinder erweitert dieses Pattern um Formular-Submit (Konzept 1): Zwei Personen auswählen, Submit, Beziehungspfad im Chart verifizieren.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Beziehungs-Chart-Seite lädt korrekt (Formular für Person-Auswahl sichtbar) | Admin | Seite lädt, Formular mit zwei Personen-Auswahlfeldern sichtbar | Ja |
| T2 | Zwei Personen auswählen (X1030 + X1041), Beziehungspfad "husband" angezeigt | Admin | Chart zeigt Beziehungspfad, Text enthält "husband" | Ja |
| T3 | Eltern-Kind-Beziehung (X1030 + X1052), Pfad "son" angezeigt | Admin | Chart zeigt Beziehungspfad, Text enthält "son" | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Chart-Rendering-Verification (Konzept 6) + Formular-Submit (Konzept 1)
**Begründung:** Der Beziehungsfinder kombiniert zwei Patterns: Formular-Interaktion (Personen auswählen, Submit) und Chart-Rendering (Beziehungspfad visuell verifizieren). T1 ist Smoke-Level (Formular lädt), T2 und T3 sind Spec-C (fachlicher Beziehungspfad korrekt).

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `relationship-chart.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo (X1030 Elizabeth II, X1041 Philip, X1052 Charles) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `relationship-chart.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Referenz-Spec

`pedigree.spec.ts` (Chart-Rendering-Pattern) dient als Vorlage für die Chart-Container-Verifikation und Theme-Loop-Struktur.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
