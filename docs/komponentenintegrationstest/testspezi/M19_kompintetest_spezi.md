<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M19: Response-Kompression

**Referenz:** M19 | **SUT:** `app/Http/Middleware/CompressResponse.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — zlib nicht geladen | extension_loaded('zlib') === false → null (keine Kompression) | Nein |
| B2 — gzip in Accept-Encoding | 'gzip' in Accept-Encoding → gzip-Kompression | Nein |
| B3 — deflate in Accept-Encoding | 'deflate' in Accept-Encoding → deflate-Kompression | Nein |
| B4 — Keine Kompression möglich | Weder gzip noch deflate akzeptiert → null | Nein |
| B5 — content-encoding vorhanden | isCompressible: content-encoding Header bereits vorhanden → false | Nein |
| B6 — text/* MIME-Type | isCompressible: Content-Type text/* → true | Nein |
| B7 — MIME_TYPES Whitelist | isCompressible: Content-Type in MIME_TYPES → true | Nein |
| B8 — Nicht komprimierbarer Typ | isCompressible: Content-Type nicht in Liste → false | Nein |
| B9 — Kompression fehlgeschlagen | gzencode/gzdeflate gibt false zurück → Response unverändert | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | zlib-Extension nicht geladen | Keine Kompression, Response unverändert |
| EP2 | gzip + text/html | Gzip-komprimierte Response, Content-Encoding: gzip |
| EP3 | deflate + text/html | Deflate-komprimierte Response, Content-Encoding: deflate |
| EP4 | Keine Kompression akzeptiert | Response unverändert |
| EP5 | application/json (komprimierbar via MIME_TYPES) | Komprimierte Response |
| EP6 | image/png (nicht komprimierbar) | Response unverändert |
| EP7 | Bereits komprimiert (content-encoding vorhanden) | Response unverändert |
| EP8 | Kompression fehlgeschlagen (gzencode gibt false zurück) | Response unverändert |
| EP9 | Accept-Encoding leer | Keine Kompression |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| Accept-Encoding | '' / 'gzip' / 'deflate' / 'gzip, deflate' | Passende Kompression oder keine |
| Content-Type | text/html / application/json / image/png / application/octet-stream | Komprimierbar vs. nicht komprimierbar |

---

## Empfohlene Strategie

- **Strategie:** EP (Äquivalenzklassenbasiert)
- **Komplexität:** Mittel
- **Testklasse:** `CompressResponseMiddlewareIntegrationTest`
- **Fixtures:** Requests mit verschiedenen Accept-Encoding-Headern, Responses mit verschiedenen Content-Types
- **Mocking:** RequestHandlerInterface mocken (liefert Response mit definiertem Content-Type und Body); PhpService mocken (extension_loaded); StreamFactoryInterface per DI

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
