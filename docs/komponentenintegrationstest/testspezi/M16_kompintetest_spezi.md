<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M16: Exception-Handling & Error-Page-Rendering

**Referenz:** M16 | **SUT:** `app/Http/Middleware/HandleExceptions.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — HttpException | HttpException → httpExceptionResponse (Status = Exception-Code) | Nein |
| B2 — FilesystemException | FilesystemException → thirdPartyExceptionResponse | Nein |
| B3 — Throwable + AJAX GET | Throwable + X-Requested-With: XMLHttpRequest + GET → 200 + layouts/ajax | Nein |
| B4 — Throwable + AJAX POST | Throwable + X-Requested-With: XMLHttpRequest + POST → 500 + layouts/ajax | Nein |
| B5 — Throwable + regulär + Tree | Throwable + regulärer Request + Tree vorhanden → 500 + layouts/default | Nein |
| B6 — Throwable + regulär + kein Tree | Throwable + regulärer Request + kein Tree → Try ohne Tree | Nein |
| B7 — Alle Views fehlgeschlagen | Sämtliche View-Renderings fehlgeschlagen → nl2br Fallback | Nein |
| B8 — Shutdown + displayErrors false | Shutdown-Handler + displayErrors false → Error ausgeben | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | HttpException (z. B. 404 NotFound) | httpExceptionResponse mit passendem Status-Code |
| EP2 | FilesystemException | thirdPartyExceptionResponse |
| EP3 | Throwable + AJAX GET | 200 + layouts/ajax Layout |
| EP4 | Throwable + AJAX POST | 500 + layouts/ajax Layout |
| EP5 | Throwable + regulärer Request + Tree vorhanden | 500 + layouts/default Layout |
| EP6 | Throwable + regulärer Request + kein Tree | Rendering ohne Tree-Kontext |
| EP7 | Alle Views fehlschlagen | nl2br Fallback-Ausgabe |
| EP8 | Shutdown + displayErrors false | Error wird ausgegeben |
| EP9 | Shutdown + displayErrors true | Error wird ausgegeben mit Details |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| Exception-Typ | HttpException / FilesystemException / RuntimeException / Error | Verschiedene Handler-Pfade |
| X-Requested-With Header | vorhanden ('XMLHttpRequest') / absent | AJAX vs. regulär |
| HTTP-Method | GET / POST | Unterschiedlicher Status-Code bei AJAX |
| Tree-Attribut | null / gültiges Tree-Objekt | Mit/ohne Tree-Kontext |

---

## Empfohlene Strategie

- **Strategie:** Spec-C (spezifikationsbasiert + Code-Review)
- **Komplexität:** Hoch
- **Testklasse:** `HandleExceptionsMiddlewareIntegrationTest`
- **Fixtures:** Verschiedene Exception-Typen, Request mit/ohne AJAX-Header, Tree-Mock
- **Mocking:** RequestHandlerInterface mocken (wirft gezielt Exceptions), PhpService mocken (displayErrors), TreeService mocken (Tree-Lookup)

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `<Testklasse> [<Siegel>] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2, 3` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen |
| `docs/tds_methodik_spec.md` | Ggf. Middleware-Pipeline-Testing als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ✅ | 7 Tests, 22 Assertions, passed |
| P5: Dokumentation | ✅ | |
