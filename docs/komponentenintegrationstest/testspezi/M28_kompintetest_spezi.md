<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M28: Response-Emittierung

**Referenz:** M28 | **SUT:** `app/Http/Middleware/EmitResponse.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — Headers bereits gesendet | headers_sent() === true → RuntimeException | Nein |
| B2 — Output-Buffer hat Content | ob_get_level() > 0 + ob_get_length() > 0 → RuntimeException | Nein |
| B3 — Normaler Request | StatusLine + Headers + Body emittieren | Nein |
| B4 — cache-control fehlt | cache-control Header fehlt → 'no-store' setzen | Nein |
| B5 — cache-control vorhanden | cache-control Header vorhanden → nicht überschreiben | Nein |
| B6 — Body seekable | Body isSeekable() → rewind() vor Ausgabe | Nein |
| B7 — Body nicht seekable | Body nicht seekable → direkt read() | Nein |
| B8 — FastCGI verfügbar | fastcgi_finish_request() existiert → aufrufen | Nein |
| B9 — FastCGI nicht verfügbar | fastcgi_finish_request() nicht vorhanden → skip | Nein |
| B10 — Connection abgebrochen | connection_aborted() → Ausgabe ggf. unterbrochen | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Headers bereits gesendet | RuntimeException wird geworfen |
| EP2 | Output-Buffer hat Content | RuntimeException wird geworfen |
| EP3 | Normaler Request (StatusLine + Headers + Body) | Vollständige Response emittiert |
| EP4 | cache-control Header vorhanden | Bestehender Header wird beibehalten |
| EP5 | cache-control Header fehlt | 'no-store' wird als cache-control gesetzt |
| EP6 | Body ist seekable | Body wird zurückgespult (rewind) vor Ausgabe |
| EP7 | Body ist nicht seekable | Body wird direkt gelesen |
| EP8 | FastCGI verfügbar | fastcgi_finish_request() wird aufgerufen |
| EP9 | FastCGI nicht verfügbar | Kein finish_request-Aufruf |
| EP10 | Connection abgebrochen | Ausgabe wird unterbrochen |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| Body-Size | 0 / 1 / CHUNK_SIZE (65536) / größer als CHUNK_SIZE | Korrekte chunk-weise Ausgabe |
| Headers gesendet | true / false | Exception vs. normale Verarbeitung |
| OB-Level | 0 / 1+ | Level 0 → OK; Level 1+ mit Inhalt → Exception |

---

## Empfohlene Strategie

- **Strategie:** Spec-C (spezifikationsbasiert + Code-Review)
- **Komplexität:** Hoch
- **Testklasse:** `EmitResponseMiddlewareIntegrationTest`
- **Fixtures:** Responses mit verschiedenen Headern und Body-Typen, PhpService-Mock
- **Mocking:** PhpService per DI mockbar (headers_sent, ob_get_level, ob_get_length, fastcgi_finish_request, connection_aborted); RequestHandlerInterface mocken; Body-Mock (seekable/nicht seekable)
- **Testbarkeit:** Schwierig — globale PHP-Funktionen (headers_sent, ob_get_level, echo) beeinflussen das Test-Environment. PhpService-Abstraktion ist der Schlüssel zur Testbarkeit. Die eigentliche Ausgabe (echo/print) muss über Output-Buffering im Test abgefangen werden.

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
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ✅ | 5 Tests, 22 Assertions, passed |
| P5: Dokumentation | ✅ | |
