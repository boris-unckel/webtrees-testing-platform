<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S41: Statistikdaten-Abfragen

**Referenz:** S41 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/statistics` → `StatisticsPage`
**L3-Referenztest:** StatisticsDataIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Statistikseite existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (StatisticsDataIntegrationTest) decken 8 Tests ab: Century-, Month-, Surnames-, Parents- und Users-Queries. Diese prüfen die Datenabfragen auf Service-Ebene, nicht die visuelle Darstellung der Statistik-Diagramme und -Tabellen im Browser.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/statistics` | GET | `StatisticsPage` |

Der Handler erfordert Viewer-Berechtigung (mindestens). Die Seite rendert verschiedene Statistik-Diagramme und -Tabellen (Geburten/Sterbefälle pro Jahrhundert, pro Monat, häufigste Nachnamen, älteste Eltern, Benutzerstatistiken).

### View-Analyse

Die Statistikseite enthält mehrere Chart- und Tabellen-Bereiche. Diagramme werden als Canvas-, SVG- oder tabellarische Darstellung gerendert (abhängig von der Statistik-Art). Selektoren: Chart-Container (`canvas`, `svg`, `.wt-chart`), Tabellen-Bereiche (`table`, `.wt-stats-table`), Tab-Navigation für verschiedene Statistik-Kategorien.

### Theme-Abhängigkeit

Layout und Farben der Diagramme variieren zwischen Themes. Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**StatisticsDataIntegrationTest** — 8 Tests:

- Century-Query: Geburten/Sterbefälle pro Jahrhundert
- Month-Query: Geburten/Sterbefälle pro Monat
- Surnames-Query: Häufigste Nachnamen
- Parents-Query: Älteste Eltern
- Users-Query: Benutzerstatistiken

Die L3-Tests validieren die Abfrage-Ergebnisse (Daten-Arrays, Anzahlen). Sie prüfen nicht die visuelle Darstellung der Diagramme.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Pattern für Statistik-Seiten. Das Chart-Rendering-Verification-Pattern (Konzept 6) wird auf Smoke-Level angewendet: Seite lädt ohne Serverfehler, Diagramm- und Tabellen-Container sind sichtbar.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Statistik-Seite lädt ohne Fehler (HTTP <500) | Admin | Seite lädt, kein Serverfehler | Ja |
| T2 | Diagramm-Container sichtbar (chart/table/canvas/svg) | Admin | Mindestens ein Diagramm-Container (canvas, svg, .wt-chart oder table) ist sichtbar | Ja |
| T3 | Tabellen-Bereich mit Statistikdaten sichtbar | Admin | Mindestens ein Tabellen-Element mit Statistikdaten ist sichtbar | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Chart-Rendering-Verification (Konzept 6)
**Begründung:** Die Statistikseite rendert komplexe Diagramme und Tabellen. Auf Smoke-Level wird geprüft, dass die Seite fehlerfrei lädt und die wesentlichen visuellen Elemente (Diagramme, Tabellen) im DOM vorhanden sind. Eine tiefere Validierung der Dateninhalte erfolgt auf L3-Ebene.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `statistics-page.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `statistics-page.spec.ts` [Smoke] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Aufwand

Niedrig — Smoke-Level-Tests, die nur die Sichtbarkeit der Diagramm- und Tabellen-Container prüfen.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
