<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S53: Legacy-URL-Weiterleitungen

**Referenz:** S53 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/*.php` (27 Legacy-Endpunkte) → `Redirect*Php`-Handler
**L3-Referenztest:** `LegacyUrlRedirectIntegrationTest` (noch nicht implementiert)
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L4-Tests für Legacy-URL-Weiterleitungen. Keine L3-Tests vorhanden.
Die 27 Redirect-Handler sind komplett ungetestet. Sie leiten alte webtrees-URLs
(z.B. `/individual.php?pid=I1`) auf die neuen Pretty-URLs (z.B. `/tree/demo/individual/I1`)
um.

---

## Upstream-Analyse

### Route und Handler

Die Legacy-URLs werden über das `RedirectLegacyUrlsModule` registriert. Alle
Handler folgen einem einheitlichen Pattern:

1. `ged`-Parameter → Tree-Lookup via TreeService
2. Record-ID-Parameter → Record-Factory → Record-Objekt
3. Gültiger Record → HTTP 301 Redirect + `Location`-Header + `Link: rel="canonical"`
4. Ungültiger Record/Tree → HTTP 410 `HttpGoneException`

**Stichprobe repräsentativer Routen:**

| Legacy-URL | Handler | Parameter | Redirect-Ziel |
|---|---|---|---|
| `/individual.php` | `RedirectIndividualPhp` | `ged`, `pid` | `/tree/{tree}/individual/{xref}` |
| `/family.php` | `RedirectFamilyPhp` | `ged`, `famid` | `/tree/{tree}/family/{xref}` |
| `/source.php` | `RedirectSourcePhp` | `ged`, `sid` | `/tree/{tree}/source/{xref}` |
| `/note.php` | `RedirectNotePhp` | `ged`, `nid` | `/tree/{tree}/note/{xref}` |
| `/repository.php` | `RedirectRepositoryPhp` | `ged`, `rid` | `/tree/{tree}/repository/{xref}` |
| `/calendar.php` | `RedirectCalendarPhp` | `ged`, `view`, `month`, `year` | `/tree/{tree}/calendar/{view}` |
| `/pedigree.php` | `RedirectPedigreePhp` | `ged`, `rootid`, `generations` | Chart-URL via PedigreeChartModule |
| `/medialist.php` | `RedirectMediaListPhp` | `ged`, `filter`, `folder` | Liste-URL via MediaListModule |

**Auth:** Keine — Redirects sind öffentlich. Record-Zugriff wird erst auf der
Ziel-Seite geprüft.

### View-Analyse

**Nicht anwendbar** — Redirect-Handler rendern keine HTML-Seite. Sie antworten
nur mit HTTP 301 + `Location`-Header.

### Theme-Abhängigkeit

**Nein** — Reine HTTP-Redirects ohne Template-Rendering.

---

## L3-Referenz-Analyse

L3-Test noch nicht implementiert. Die Szenarien werden aus dem einheitlichen
Handler-Pattern abgeleitet. Jeder Handler hat dieselben zwei Hauptpfade:
- Gültiger Tree + Record → 301 Redirect
- Ungültiger Tree oder Record → 410 Gone

Spezial-Handler (RedirectModulePhp, RedirectCalendarPhp, RedirectPedigreePhp)
haben zusätzliche Parameter-Mappings.

---

## Bestehende L4-Muster-Analyse

- `security-headers.spec.ts`: API-Only-Pattern mit `request`-Kontext statt `page`-Kontext.
  Direkte HTTP-Status- und Header-Assertions ohne DOM.
- Pattern ist direkt anwendbar für Redirect-Tests.

---

## Testszenarien

| # | Szenario | Route | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Individual Redirect (gültig) | `/individual.php?ged=demo&pid=I1` | HTTP 301, Location → `/tree/demo/individual/I1` | Nein |
| T2 | Individual Redirect (ungültige XREF) | `/individual.php?ged=demo&pid=NONEXIST999` | HTTP 410 Gone | Nein |
| T3 | Family Redirect (gültig) | `/family.php?ged=demo&famid=F1` | HTTP 301, Location enthält `/family/F1` | Nein |
| T4 | Source Redirect (gültig) | `/source.php?ged=demo&sid=S1` | HTTP 301, Location enthält `/source/S1` | Nein |
| T5 | Calendar Redirect (gültig) | `/calendar.php?ged=demo&view=month` | HTTP 301, Location enthält `/calendar/` | Nein |
| T6 | Pedigree Redirect (gültig) | `/pedigree.php?ged=demo&rootid=I1` | HTTP 301, Location enthält Chart-URL | Nein |
| T7 | Tree nicht gefunden | `/individual.php?ged=INVALID_TREE_NAME&pid=I1` | HTTP 410 Gone | Nein |
| T8 | Canonical Link Header | `/individual.php?ged=demo&pid=I1` | Response enthält `Link: <...>; rel="canonical"` | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** API-Only (→ wf_code-to-systemtest_guide.md 4.6)

**Begründung:** Keine HTML-Seiten werden gerendert — nur HTTP-Status und
Header-Assertions. Der `request`-Kontext von Playwright (ohne Browser) ist
ausreichend und performanter als `page.goto()`.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `legacy-url-redirects.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `otel-fixture` (kein MySQL-Zugriff nötig im Test selbst) |
| **Helper** | Keine speziellen Helper |
| **Theme-Loop** | Nein |
| **Login-Strategie** | Kein Login (Visitor) — Redirects sind öffentlich |
| **Baum** | `demo` (muss existieren für gültige Redirects) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `legacy-url-redirects.spec.ts [Spec-C] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `3` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 3 prüfen |
| `docs/tds_methodik_spec.md` | Ggf. „Legacy-Redirect-Stichprobe" als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
