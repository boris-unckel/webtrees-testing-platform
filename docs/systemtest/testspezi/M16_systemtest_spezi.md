<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — M16: Exception-Handling & Error-Page-Rendering

**Referenz:** M16 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** Implizit — Error-Pages werden durch Exception-Handling in `HandleExceptions` generiert
**L3-Referenztest:** `HandleExceptionsMiddlewareIntegrationTest` (implementiert, 7 Tests, 22 Assertions)
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine dedizierten L4-Tests für Error-Pages. Bestehende Tests prüfen generisch
`expect(response?.status()).toBeLessThan(500)` — das Gegenteil (absichtlich
Fehler provozieren) wurde bisher nicht getestet. Error-Page-Rendering ist
nutzersichtbar und damit L4-relevant.

---

## Upstream-Analyse

### Route und Handler

Die `HandleExceptions`-Middleware fängt alle Exceptions in der Pipeline ab:

| Exception-Typ | HTTP-Status | Response-Layout |
|---|---|---|
| `HttpNotFoundException` | 404 | `layouts/default` + `components/alert-danger` |
| `HttpAccessDeniedException` | 403 | `layouts/default` + `components/alert-danger` |
| `HttpGoneException` | 410 | `layouts/default` + `components/alert-danger` |
| `FilesystemException` | 500 | `layouts/default` + `components/alert-danger` |
| Sonstige `Throwable` | 500 | `layouts/default` → Fallback `layouts/error` |
| AJAX-Request mit Exception | 200 | `layouts/ajax` + `components/alert-danger` |

**Auth:** Keine spezifische Anforderung — Error-Pages sind öffentlich zugänglich.

### View-Analyse

- `components/alert-danger.phtml`: `<div class="alert alert-danger" role="alert">`
- `errors/unhandled-exception.phtml`: `<pre class="alert alert-danger">` (Stacktrace)
- `layouts/error.phtml`: Minimal-Layout (kein Menü, kein Theme-CSS)

### Theme-Abhängigkeit

**Nein** — Error-Pages nutzen Bootstrap-basierte Alert-Komponenten. Das Layout
`layouts/default` enthält Theme-CSS, aber der Alert-Container selbst ist
theme-unabhängig. Ein Theme-Loop ist nicht erforderlich.

---

## L3-Referenz-Analyse

`HandleExceptionsMiddlewareIntegrationTest` (7 Tests, 22 Assertions):
HttpException→Statuscode, FilesystemException→500, AJAX GET→200+Layout,
Throwable→500+Fehlermeldung, OB-Stack-Restoration. Die L3-Tests prüfen
Response-Objekte; L4 prüft die tatsächliche DOM-Darstellung im Browser.

---

## Bestehende L4-Muster-Analyse

- `access-control.spec.ts`: Prüft Zugriffsverweigerungen für verschiedene Rollen —
  ähnliches Pattern (HTTP-Status-Prüfung), aber fokussiert auf Privacy, nicht Error-Pages.
- `security-headers.spec.ts`: API-Only-Pattern mit Status-Code-Assertions.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Nicht existierende XREF → Client-Error | Visitor | HTTP 4xx + `.alert.alert-danger` sichtbar | Nein |
| T2 | Admin-Seite ohne Login → Redirect | Visitor | Redirect weg von `/admin/` | Nein |
| T3 | Error-Page ARIA-Rolle | Visitor | `.alert.alert-danger[role="alert"]` sichtbar | Nein |
| T4 | Fehlermeldungstext vorhanden | Visitor | Alert-Text nicht leer | Nein |
| T5 | Ungültiger Tree-Name → Client-Error | Visitor | HTTP 4xx | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Spec-C (provozierte Fehler) — kein Standard-Pattern,
Kombination aus API-Only (Status-Prüfung) und DOM-Assertion (Alert-Container).

**Begründung:** Error-Pages sind weder theme-abhängig noch rollenabhängig.
Die Tests provozieren gezielt verschiedene HTTP-Fehler und prüfen sowohl den
Status-Code als auch die DOM-Darstellung der Fehlermeldung.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `error-handling.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | Keine speziellen Helper nötig |
| **Theme-Loop** | Nein |
| **Login-Strategie** | Kein Login (Visitor) — Error-Pages sind öffentlich |
| **Baum** | `demo` |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `error-handling.spec.ts [Spec-C] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2, 3` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 3 prüfen |
| `docs/tds_methodik_spec.md` | Ggf. neues Testentwurfsverfahren „Error-Provokation" ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | Upstream-Analyse abgeschlossen |
| P2: Soll-Design | ✅ | 5 Szenarien definiert |
| P3: Test-Coding | ✅ | `error-handling.spec.ts` (5 Tests) |
| P4: Ausführung + Fixing | ✅ | Alle 5 Tests grün |
| P5: Dokumentation | ✅ | tds_coverage/conditions/ratchet/methodik aktualisiert |
