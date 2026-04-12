<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — A04: Stammbaum-Präferenzen

**Referenz:** A04 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/preferences` (GET Page), `/tree/{tree}/preferences` (POST Action) → `TreePreferencesPage`, `TreePreferencesAction`
**L3-Referenztest:** TreePreferencesIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Stammbaum-Präferenzen existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (TreePreferencesIntegrationTest) decken die Handler-Ebene ab (3 Tests: GET→200, POST→302 mit SHOW_COUNTER gespeichert, POST→302 mit META_DESCRIPTION gespeichert), prüfen aber nicht das vollständige Formular-Rendering und nicht, ob gespeicherte Einstellungen nach Reload im Formular sichtbar sind.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/preferences` | GET | `TreePreferencesPage` |
| `/tree/{tree}/preferences` | POST | `TreePreferencesAction` |

Beide Handler erfordern Administrator-Berechtigung für den jeweiligen Baum. `TreePreferencesPage` rendert das Einstellungsformular mit allen konfigurierbaren Optionen (Meta-Beschreibung, Besucherzähler, Kalenderformat etc.). `TreePreferencesAction` validiert und speichert die Einstellungen, dann Redirect zurück auf die Präferenzen-Seite (302).

### View-Analyse

Das Präferenzen-Formular enthält zahlreiche Einstellungsfelder: `input[name="META_DESCRIPTION"]` (Meta-Beschreibung), Checkboxen/Selects für Anzeige-Optionen (z.B. SHOW_COUNTER). Die Felder sind in thematischen Gruppen (Tabs oder Akkordeons) organisiert. Nach dem Speichern wird auf dieselbe Seite zurückgeleitet, die gespeicherten Werte sind im Formular sichtbar.

### Theme-Abhängigkeit

Admin-/Einstellungs-Seiten nutzen ein festes Layout. Kein Theme-Loop erforderlich.

---

## L3-Referenz-Analyse

**TreePreferencesIntegrationTest** — 3 Tests:

1. GET `/tree/{tree}/preferences` → 200 (Einstellungsformular wird gerendert)
2. POST mit SHOW_COUNTER (leerer String) → 302 (Redirect, SHOW_COUNTER-Einstellung in DB gespeichert)
3. POST mit META_DESCRIPTION ('Test Description A04') → 302 (Redirect, META_DESCRIPTION in DB gespeichert)

Die L3-Tests validieren die HTTP-Ebene und den DB-Zustand. Sie prüfen nicht, ob die gespeicherte Einstellung nach Reload im Formular korrekt angezeigt wird und ob sie sich fachlich auf die Seitenausgabe auswirkt.

**EP/BVA-Analyse:**

- EP1: GET→200 — Lesefall (Formular-Rendering)
- EP2: POST speichert SHOW_COUNTER (leerer String) — Spezialwert
- EP3: POST speichert META_DESCRIPTION ('Test Description A04') — Standardwert

---

## Bestehende L4-Muster-Analyse

**Referenz-Spec:** `upload-validation.spec.ts` — enthält das Admin-Pattern (Login als Admin, Admin-Seite aufrufen). Für die Formular-Submit-Verification (Einstellung ändern → speichern → Reload → Wert prüfen) wird das Konzept 1 aus den übergreifenden Konzepten angewendet.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Präferenzen-Seite lädt korrekt (Formular mit Einstellungsfeldern sichtbar) | Admin | Seite lädt, Formularfelder (META_DESCRIPTION, weitere Optionen) sichtbar | Nein |
| T2 | Einstellung ändern (META_DESCRIPTION) und speichern → Redirect, Einstellung wirkt | Admin | POST→302, Redirect auf Präferenzen-Seite, neuer Wert im Formular sichtbar | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only + Formular-Submit-Verification (Konzept 1)
**Begründung:** Die Stammbaum-Präferenzen sind eine reine Admin-Funktion ohne Theme-Abhängigkeit. T1 ist Smoke-Level (Formular lädt korrekt), T2 ist Spec-C (Einstellung ändern, speichern, Wirksamkeit verifizieren). Das Pattern folgt dem Standard-Formular-Submit-Ablauf: Seite laden, Feld ändern, Submit, Redirect abwarten, gespeicherten Wert im DOM prüfen.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `tree-preferences.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein (Admin-Seite) |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `tree-preferences.spec.ts` [Spec-C] ✅ *(2 Tests)* |
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
