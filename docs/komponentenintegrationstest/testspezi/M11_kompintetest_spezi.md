<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M11: URL-Routing

**Referenz:** M11 | **SUT:** `app/Http/Middleware/Router.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware ist die zentrale Routing-Komponente mit
komplexer Verzweigungslogik: URL-Rewriting, Route-Matching, Tree-Auflösung und
Fehlerbehandlung. Es existiert lediglich ein L2-Stub (`assertTrue(class_exists(...))`),
der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware erhält `ModuleService`, `RouterContainer` und `TreeService` per
Dependency-Injection. Die Logik umfasst URL-Rewriting, FastRoute-Dispatching,
Tree-Parameter-Auflösung und HTTP-Fehlerbehandlung.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `rewrite_urls=true` + `url_route` vorhanden → 301-Redirect | Nein |
| B2 | `rewrite_urls=true` + `url_route` leer → Normal-Routing | Nein |
| B3 | `rewrite_urls=false` → URI-Update auf Query-Parameter | Nein |
| B4 | Route matched → Middleware-Stack aus Route-Definition aufbauen | Nein |
| B5 | Route nicht matched, Method-Not-Allowed → HTTP 405 | Nein |
| B6 | Route nicht matched, Not-Acceptable → HTTP 406 | Nein |
| B7 | Route nicht matched, Default-Fall → Fallback-Verhalten | Nein |
| B8 | Tree-Parameter `null` → Default-Tree verwenden | Nein |
| B9 | Tree-Parameter vorhanden + Tree existiert → Tree im Container setzen | Nein |
| B10 | Andere Route-Attribute → als Request-Attribute übernehmen | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | `rewrite_urls=true`, `url_route` vorhanden | 301-Redirect auf Clean-URL |
| EP2 | `rewrite_urls=true`, `url_route` leer | Normales Routing ohne Redirect |
| EP3 | `rewrite_urls=false` | URI wird auf Query-Parameter-Basis aktualisiert |
| EP4 | Route matched, Handler + Middleware definiert | Middleware-Stack wird aufgebaut, Handler aufgerufen |
| EP5 | Unbekannte Route, erlaubte Methode vorhanden | HTTP 405 Method Not Allowed |
| EP6 | Unbekannte Route, Content-Negotiation fehlgeschlagen | HTTP 406 Not Acceptable |
| EP7 | Unbekannte Route, Default-Fall | Fallback-Response |
| EP8 | Tree existiert in DB | Tree wird im Container registriert |
| EP9 | Tree-Parameter `null` | Default-Tree oder kein Tree |
| EP10 | Route-Attribute (Handler, Middleware, etc.) | Attribute korrekt im Request gesetzt |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| `url_route` leer | `''` | Kein Redirect, normales Routing |
| `url_route` mit Pfad | `some/route` | 301-Redirect (wenn `rewrite_urls=true`) |
| `url_route` nicht existent | `non-existent/path` | 404 oder Default-Fall |
| `rewrite_urls` true | `true` | Rewrite-Logik aktiv |
| `rewrite_urls` false | `false` | Query-Parameter-Logik aktiv |
| Tree `null` | `null` | Default-Tree-Handling |
| Tree gültig | `my-tree` | Tree im Container gesetzt |
| Tree ungültig | `non-existent-tree` | Fehlerbehandlung |

---

## Empfohlene Strategie

- **Testklasse:** `RouterMiddlewareIntegrationTest`
- **Strategie:** Spec-C (spezifikationsbasiert, Conditions-Coverage)
- **Priorität:** Hoch
- **Testbarkeit:** Eingeschränkt wegen `RouterContainer`/`Dispatcher` — die
  FastRoute-Integration erfordert entweder reale Routen oder Mock-Dispatcher
- **Fixtures:** Definiertes Routen-Set (API + Web), mindestens ein Tree in der DB,
  Site-Preference `rewrite_urls`
- **Mocking:** `RouterContainer` und `Dispatcher` ggf. mocken, um Route-Match/Not-Found/
  Method-Not-Allowed gezielt zu steuern. `TreeService` kann real oder gemockt verwendet
  werden.
- **Hinweis:** Dies ist die komplexeste Middleware mit der höchsten Branch-Dichte.
  Priorisierung auf die kritischen Pfade (B1, B4, B5, B8) empfohlen.

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `<Testklasse> [<Siegel>] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen |
| `docs/tds_methodik_spec.md` | Ggf. Middleware-Pipeline-Testing als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
