<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — P30: Datensätze zusammenführen (Auswahl)

**Referenz:** P30 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/merge-records` (GET Page + POST Action) → `MergeRecordsPage`, `MergeRecordsAction`
**L3-Referenztest:** MergeRecordsIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Datensatz-Zusammenführung (Auswahl-Schritt) existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (MergeRecordsIntegrationTest) decken die Handler-Ebene ab (3 Tests: gültige XREFs→200, leere XREFs→200, POST mit passenden Records→302 Redirect zu MergeFactsPage), prüfen aber nicht die tatsächliche Formular-Interaktion und den Workflow-Übergang im Browser. P30 bildet den ersten Schritt des zweistufigen Merge-Workflows (P30 → P41), der in der gemeinsamen Spec-Datei `merge-records.spec.ts` abgebildet wird (→ Konzept 8 Zusammenlegung).

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/merge-records` | GET | `MergeRecordsPage` |
| `/tree/{tree}/merge-records` | POST | `MergeRecordsAction` |

Beide Handler erfordern Admin-Berechtigung. Der GET-Handler rendert ein Formular mit zwei XREF-Eingabefeldern zur Auswahl der zusammenzuführenden Records. Der POST-Handler validiert die eingegebenen XREFs und leitet bei Erfolg (passende Record-Typen) auf die MergeFactsPage weiter (302), wo die eigentliche Zusammenführung stattfindet (P41).

### View-Analyse

Das Formular enthält zwei XREF-Eingabefelder (Autocomplete-fähig) und einen Submit-Button. Die Eingabefelder nutzen webtrees-Standard-XREF-Selektoren. Bei leeren XREFs wird die Seite ohne Fehler neu geladen (Null-Records-Handling). Die Vorschau-Seite nach erfolgreichem Submit zeigt beide Records im Vergleich.

### Theme-Abhängigkeit

Kein Theme-Loop erforderlich. Die Merge-Funktionalität ist eine reine Admin-Funktion mit standardisiertem Admin-Layout, das nicht theme-abhängig variiert.

---

## L3-Referenz-Analyse

**MergeRecordsIntegrationTest** — 3 Tests:

1. GET mit gültigen XREFs (xref1, xref2 als Query-Parameter) → 200 (Formular wird mit vorausgefüllten XREFs gerendert)
2. GET mit leeren XREFs → 200 (Formular wird ohne Fehler gerendert, keine Records vorausgewählt)
3. POST mit zwei passenden Individual-XREFs → 302 (Redirect zu MergeFactsPage mit xref1/xref2 als Query-Parameter)

Die L3-Tests validieren die HTTP-Ebene (Statuscodes, Redirects). Sie prüfen nicht das DOM-Rendering des Formulars, die XREF-Eingabe-Interaktion und die korrekte Anzeige der Record-Vorschau nach Submit.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Test-Pattern für Merge-Workflows. Das Mehrstufiger-Workflow-Pattern (Konzept 4.1) aus den übergreifenden Konzepten definiert den Ablauf: Schritt 1 (P30) — Merge-Seite laden, zwei XREFs eingeben → Schritt 2 (P30) — Vorschau prüfen → Schritt 3 (P41) — Merge bestätigen → Schritt 4 (P41) — Ergebnis verifizieren. Die Admin-Login-Strategie folgt dem bestehenden `loginAsAdmin`-Pattern.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Merge-Seite lädt korrekt (Formular für XREF-Eingabe sichtbar) | Admin | Seite lädt (200), zwei XREF-Eingabefelder und Submit-Button sichtbar | Nein |
| T2 | Zwei XREFs eingeben (X1030, X1031), Submit → Vorschau-Seite mit beiden Records | Admin | POST→302, Redirect zu MergeFactsPage, beide Records in Vergleichsansicht sichtbar | Nein |
| T3 | Leere XREFs → Seite lädt ohne Fehler (null-Records) | Admin | Seite wird ohne Fehlermeldung neu geladen, Formular bleibt funktional | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only + Mehrstufiger-Workflow (Konzept 4.1)
**Begründung:** Die Merge-Auswahl ist der erste Schritt eines mehrstufigen Admin-Workflows. T1 ist Smoke-Level (Formular lädt), T2 ist Spec-C (Workflow-Übergang mit fachlich sichtbarem Effekt — Vorschau-Seite zeigt beide Records), T3 prüft den Null-Records-Edge-Case. Kein Theme-Loop, da Admin-Funktionalität nicht theme-abhängig ist. Die Spec-Datei `merge-records.spec.ts` wird mit P41 geteilt (Konzept 8).

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `merge-records.spec.ts` (shared mit P41, → Konzept 8 Zusammenlegung) |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein — Admin-Funktionalität, nicht theme-abhängig |
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

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
