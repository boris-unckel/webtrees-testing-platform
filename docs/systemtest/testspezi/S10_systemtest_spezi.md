<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S10: Paginierung (Suchergebnisse)

**Referenz:** S10 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/search-advanced` (mit Limit/Offset-Parametern) → `SearchAdvancedAction`
**L3-Referenztest:** SearchIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Paginierung von Suchergebnissen existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (SearchIntegrationTest) decken partiell Limit, Offset und Offset+Limit ab (3 Tests). Die bestehenden L4-Tests prüfen weder Paginierungs-Controls noch Seitenwechsel-Verhalten.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/search-advanced` | POST | `SearchAdvancedAction` |

Der Handler unterstützt Limit- und Offset-Parameter zur Paginierung der Suchergebnisse. Bei vielen Treffern werden Paginierungs-Controls gerendert.

### View-Analyse

Die Paginierungs-Controls verwenden Bootstrap-Pagination-Komponenten. Selektoren: `.pagination` für den Paginierungs-Container, `.page-item` für einzelne Seitenlinks, `.page-link` für klickbare Links. Die Ergebnisanzahl pro Seite ist serverseitig begrenzt.

### Theme-Abhängigkeit

Paginierungs-Styling variiert zwischen Themes (Farben, Abstände). Die funktionalen Elemente (`.pagination`, `.page-link`) sind theme-unabhängig. Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**SearchIntegrationTest** — partiell: Paginierung (3 Tests):

- BVA Limit (5): Ergebnismenge auf 5 begrenzt
- BVA Offset (5): Erste 5 Ergebnisse übersprungen
- BVA Offset+Limit Kombination (offset=2, limit=3): Kombination beider Parameter

Die L3-Tests validieren die Ergebnis-Arrays auf Handler-Ebene (Array-Länge, Start-Index). Sie prüfen nicht die visuelle Darstellung der Paginierungs-Controls im Browser.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Pattern für Paginierung. Das Such-Ausführungs-Verification-Pattern (Konzept 3) wird angewendet, erweitert um Konzept 3.2 (Paginierungs-Verification). Die Paginierung erfordert eine Suche, die genügend Treffer liefert, um mehrere Seiten zu erzeugen (demo-Baum mit 72 Individuen).

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Suche mit vielen Treffern zeigt Paginierungs-Controls (.pagination sichtbar) | Admin | Paginierungs-Container ist sichtbar, mindestens 2 Seitenlinks vorhanden | Ja |
| T2 | Seitenwechsel (Klick auf Seite 2) zeigt andere Ergebnisse | Admin | Nach Klick auf Seite 2: Ergebnistabelle zeigt andere Einträge als Seite 1 | Ja |
| T3 | Ergebnisanzahl pro Seite ist begrenzt | Admin | Anzahl der Tabellenzeilen auf Seite 1 ist kleiner oder gleich dem Limit | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Such-Ausführungs-Verification (Konzept 3, 3.2)
**Begründung:** Paginierung ist ein visuelles UI-Feature, das nur im Browser vollständig testbar ist (Controls-Sichtbarkeit, Seitenwechsel-Interaktion, Ergebniswechsel). Die L3-BVA-Grenzwerte (Limit=5, Offset=5) liefern die Testparameter.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `search-pagination.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo (72 Individuen — genügend für Paginierung) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `search-pagination.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## L3-EP/BVA-Referenz

BVA Limit (5), Offset (5), Offset+Limit Kombination (offset=2, limit=3). Der demo-Baum enthält 72 Individuen, was bei einer breiten Suche (z.B. alle Individuen) genügend Treffer für mehrere Seiten liefert.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
