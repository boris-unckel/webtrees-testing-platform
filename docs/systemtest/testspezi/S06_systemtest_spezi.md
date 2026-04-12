<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S06: Erweiterte Suche (Datum-Modifikatoren)

**Referenz:** S06 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/search-advanced` (GET Page), POST Action → `SearchAdvancedPage`, `SearchAdvancedAction`
**L3-Referenztest:** SearchIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die erweiterte Suche mit Datum-Modifikatoren existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (SearchIntegrationTest) decken partiell Sterbedatum-Suche mit verschiedenen Modifikatoren ab (+-0, +-5, +-20 Jahre). Die bestehende `search-forms.spec.ts` prüft nur Formular-Rendering, nicht die Auswirkung von Datums-Modifikatoren auf die Ergebnismenge.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/search-advanced` | GET | `SearchAdvancedPage` |
| `/tree/{tree}/search-advanced` | POST | `SearchAdvancedAction` |

Gleiche Route wie S05. Die Datum-Modifikatoren sind zusätzliche Formularfelder, die den Datumsbereich erweitern oder einschränken. Der Modifikator-Wert (+-0, +-5, +-20) steuert die Toleranz bei der Datumssuche.

### View-Analyse

Das Datumsfeld im erweiterten Suchformular enthält neben dem Jahreswert ein Select-Feld für den Modifikator (exakt, +-1, +-2, +-5, +-10, +-20 Jahre). Selektoren: Datumsfeld (`input` mit Datumstyp), Modifikator-Select (`select` neben dem Datumsfeld).

### Theme-Abhängigkeit

Formular-Layout variiert zwischen Themes. Funktionale Elemente sind theme-unabhängig. Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**SearchIntegrationTest** — partiell: Sterbedatum-Modifikatoren:

- BVA Grenzwerte: +-0 (exakt), +-5 (mittel), +-20 (weit)
- Testdatenpunkt: Jahr 1997 (Diana Spencer +) als deterministischer Referenzwert
- EP: Exakte Suche (+-0) liefert nur Treffer mit exaktem Sterbejahr
- EP: Erweiterte Suche (+-5) liefert breitere Ergebnismenge
- EP: Maximale Toleranz (+-20) liefert weiteste Ergebnismenge

Die L3-Tests validieren die Ergebnis-Arrays auf Handler-Ebene. Sie prüfen nicht die visuelle Darstellung der Ergebnisse im Browser.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Pattern für Datums-Modifikatoren. Das Such-Ausführungs-Verification-Pattern (Konzept 3) wird angewendet. Diese Spec-Datei wird mit S05 (Feld-Suche) geteilt (Konzept 8 Zusammenlegung).

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Datumssuche mit Modifikator +-0 (exakt) liefert nur exakte Treffer | Admin | Ergebnistabelle enthält nur Personen mit exaktem Sterbejahr 1997 | Ja |
| T2 | Datumssuche mit Modifikator +-5 liefert breitere Ergebnismenge | Admin | Ergebnismenge >= Ergebnismenge von T1 (+-0) | Ja |
| T3 | Datumssuche mit Modifikator +-20 liefert weiteste Ergebnismenge | Admin | Ergebnismenge >= Ergebnismenge von T2 (+-5) | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Such-Ausführungs-Verification (Konzept 3)
**Begründung:** Die Datums-Modifikatoren beeinflussen die Ergebnismenge. Durch Vergleich der Ergebnismengen bei steigender Toleranz wird die korrekte Funktion der Modifikatoren verifiziert. Das Pattern folgt Konzept 3 mit BVA-Grenzwerten aus der L3-Referenz.

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
| **Baum** | demo (Sterbedaten 1997 etc.) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `advanced-search-execution.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## L3-EP/BVA-Referenz

BVA Grenzwerte +-0, +-5, +-20 für Datums-Modifikatoren. Jahr 1997 (Diana Spencer +) als deterministischer Testdatenpunkt. Die Monotonie-Eigenschaft (breiterer Modifikator → mehr Treffer) wird als Invariante geprüft.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
