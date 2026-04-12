<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S50: Hilfetexte

**Referenz:** S50 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/help/{topic}` (AJAX-Endpoint für Hilfe-Tooltips) → `HelpText`
**L3-Referenztest:** HelpTextIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Hilfetexte existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (HelpTextIntegrationTest) decken 13 Tests ab: 12 gültige Topics und 1 unbekanntes Topic. Diese prüfen die HTTP-Responses der AJAX-Endpoints, nicht die Integration der Hilfe-Tooltips in die Seiten-UI.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/help/{topic}` | GET | `HelpText` |

Der Handler erfordert keine besondere Berechtigung (AJAX-Endpoint). Er liefert HTML-Fragmente für Hilfe-Tooltips zurück. Bei bekannten Topics wird der Hilfetext zurückgegeben, bei unbekannten Topics eine Fallback-Meldung.

### View-Analyse

Die Hilfetexte werden als Popover/Tooltip in der Seiten-UI angezeigt. Hilfe-Icons (z.B. Fragezeichen-Symbol) neben Formularfeldern lösen den AJAX-Request aus. Der zurückgegebene HTML-Inhalt wird im Popover/Tooltip dargestellt. Selektoren: Hilfe-Icon (`.wt-help-icon`, `[data-help]`, `.help-link`), Popover-Container (`.popover`, `.tooltip`).

### Theme-Abhängigkeit

Hilfe-Icon-Darstellung und Popover-Styling variieren zwischen Themes. Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**HelpTextIntegrationTest** — 13 Tests:

12 gültige Topics: DATE, NAME, SURN, OBJE, PLAC, RESN, ROMN, _HEB, data-fixes, edit_SOUR_EVEN, pending_changes, relationship-privacy

1 ungültiges Topic: Unbekannter Topic liefert Fallback-Meldung ("not been written")

- EP: Gültiger Topic liefert Hilfetext-Inhalt
- EP: Ungültiger Topic liefert Fallback-Meldung
- BVA: Alle 12 bekannten Topics als Äquivalenzklassen-Vertreter

Die L3-Tests validieren HTTP-Response-Inhalte. Sie prüfen nicht die UI-Integration (Hilfe-Icon-Klick, Popover-Darstellung).

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Pattern für AJAX-Tooltip-Interaktion. Das Pattern kombiniert Theme-Loop (wf_code-to-systemtest_guide.md 4.3) mit AJAX-Response-Verification: Hilfe-Icon klicken, Popover auf Sichtbarkeit warten, Inhalt verifizieren.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Hilfe-Icon auf Personenseite klicken, Tooltip/Popover erscheint mit Text | Admin | Nach Klick auf Hilfe-Icon: Popover/Tooltip wird sichtbar, enthält Text | Ja |
| T2 | Hilfe-Endpunkt liefert für bekannten Topic Inhalt (nicht "not been written") | Admin | AJAX-Response enthält Hilfetext, der nicht die Fallback-Meldung ist | Ja |
| T3 | Hilfe-Endpunkt für unbekannten Topic liefert Fallback-Meldung | Admin | AJAX-Response enthält "not been written" oder ähnliche Fallback-Meldung | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop (wf_code-to-systemtest_guide.md 4.3)
**Begründung:** Die Hilfe-Tooltips sind ein UI-Feature, das über AJAX-Endpoints geladen wird. T1 testet die vollständige UI-Integration (Icon-Klick → Popover), T2 und T3 können alternativ direkt den AJAX-Endpoint testen (`page.request.get`). Theme-Loop ist sinnvoll, da Popover-Styling theme-abhängig ist.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `help-texts.spec.ts` |
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
| `docs/tds_coverage_ref.md` | L4-Spalte: `help-texts.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Topics-Referenz

Die 12 bekannten Topics: DATE, NAME, SURN, OBJE, PLAC, RESN, ROMN, _HEB, data-fixes, edit_SOUR_EVEN, pending_changes, relationship-privacy. Für T2 wird ein repräsentativer Topic gewählt (z.B. DATE). Für T3 wird ein beliebiger ungültiger Topic-String verwendet.

---

## Aufwand

Niedrig — AJAX-Endpoint-Tests sind strukturell einfach. Die UI-Integration (T1) erfordert die Identifikation des Hilfe-Icons auf einer geeigneten Seite (z.B. Personen-Editierseite).

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
