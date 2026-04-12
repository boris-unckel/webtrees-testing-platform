<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — E08: TomSelect & AutoComplete (Edit-Hilfs-APIs)

**Referenz:** E08 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/autocomplete/individuals` (GET AJAX), `/tree/{tree}/autocomplete/sources` (GET AJAX), `/tree/{tree}/autocomplete/folder` (GET AJAX) → `TomSelectIndividual`, `TomSelectSource`, `AutoCompleteFolder` (AJAX-Endpoints)
**L3-Referenztest:** TomSelectIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die TomSelect- und AutoComplete-Widgets existieren bisher keine L4-Systemtests. Die L3-Tests (TomSelectIntegrationTest) decken die AJAX-Endpoints auf HTTP-Ebene ab (JSON-Antworten mit Suchtreffern). Die tatsächliche JavaScript-Widget-Interaktion im Browser (Dropdown öffnen, Eintrag auswählen, Hidden-Input-Wert) ist nicht getestet.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/autocomplete/individuals` | GET | `TomSelectIndividual` |
| `/tree/{tree}/autocomplete/sources` | GET | `TomSelectSource` |
| `/tree/{tree}/autocomplete/folder` | GET | `AutoCompleteFolder` |

Die Handler erfordern Editor-Berechtigung. Sie liefern JSON-Antworten mit Suchtreffern, die vom TomSelect-JavaScript-Widget im Frontend konsumiert werden. Die Endpoints akzeptieren Query-Parameter (`query`, `page`).

### View-Analyse

Die TomSelect-Widgets werden auf verschiedenen Edit-Seiten eingebunden (z.B. AddChildToIndividual, EditFact, LinkMediaToIndividual). Das Widget ersetzt ein Standard-`<select>` oder `<input>` durch ein interaktives Dropdown mit Suchfunktion. Relevante Selektoren:

- `.ts-control` — TomSelect-Container
- `.ts-control input` — Eingabefeld für Suche
- `.ts-dropdown` — Dropdown-Container
- `.ts-dropdown .option` — Einzelner Treffer im Dropdown
- Hidden-Input (Original-`<input>` oder `<select>`) — enthält die gewählte XREF

### Theme-Abhängigkeit

TomSelect-Selektoren (`.ts-control`, `.ts-dropdown`, `.ts-input`) können zwischen Themes variieren, da Themes eigene CSS-Überschreibungen für TomSelect laden können. Theme-Loop ist empfohlen, um sicherzustellen, dass die Selektoren in allen Themes funktionieren.

---

## L3-Referenz-Analyse

**TomSelectIntegrationTest** — 5 Tests:

1. TomSelectIndividual mit leerer Query → JSON mit allen Personen (paginiert)
2. TomSelectIndividual mit XREF-Query → JSON mit exaktem Treffer
3. TomSelectIndividual mit Name-Query → JSON mit passenden Treffern
4. TomSelectSource → JSON mit Quellen-Treffern
5. AutoCompleteFolder → JSON mit Ordner-Treffern

Die L3-Tests validieren die JSON-API-Ebene. Es fehlt die Prüfung der Widget-Interaktion im Browser: Dropdown-Sichtbarkeit, Treffer-Auswahl, Hidden-Input-Wert-Übernahme.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Test-Pattern für TomSelect-Widgets. Das JS-Widget-Interaktions-Pattern (Konzept 2.1) aus den übergreifenden Konzepten definiert den Ablauf: Input in `.ts-control input` tippen → `.ts-dropdown .option` auf `visible` warten → Eintrag per Klick wählen → Hidden-Input-Wert verifizieren.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | TomSelect-Widget auf Edit-Seite: Tippen öffnet Dropdown (`.ts-dropdown` visible) | Admin | Eingabe von Text in `.ts-control input`, Dropdown mit Treffern wird sichtbar | Ja |
| T2 | Eintrag aus Dropdown wählen, Wert in Hidden-Input übernommen | Admin | Klick auf `.ts-dropdown .option`, Hidden-Input enthält gewählte XREF | Ja |
| T3 | Leere Eingabe zeigt leeres/kein Dropdown | Admin | Keine Eingabe oder leere Eingabe, kein Dropdown sichtbar oder leeres Dropdown | Ja |
| T4 | XREF-Eingabe zeigt passenden Treffer | Admin | Eingabe einer bekannten XREF (z.B. X1030), genau ein Treffer im Dropdown | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + JS-Widget-Interaktion (Konzept 2.1)
**Begründung:** TomSelect-Widgets sind JavaScript-basierte UI-Komponenten, deren Selektoren und Verhalten zwischen Themes variieren können. Die Interaktion erfordert spezifische Playwright-Schritte (Tippen in `.ts-control input`, Warten auf `.ts-dropdown`, Klick auf `.option`), die über einfaches `fill`/`click` hinausgehen. Theme-Loop sichert die Kompatibilität aller Themes ab.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `tomselect-autocomplete.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login (Editor-Berechtigung erforderlich) |
| **Baum** | demo (XREF X1030) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `tomselect-autocomplete.spec.ts` [Spec-C] ✅ *(4 Tests)* |
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
