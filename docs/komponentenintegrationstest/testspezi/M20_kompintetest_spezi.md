<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M20: Content-Length-Header

**Referenz:** M20 | **SUT:** `app/Http/Middleware/ContentLength.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — Header existiert bereits | content-length Header bereits vorhanden → Response unverändert | Nein |
| B2 — Body-Size null | Body getSize() gibt null zurück → Response unverändert | Nein |
| B3 — Body-Size bekannt | Body getSize() gibt Zahl zurück → content-length Header setzen | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | content-length Header bereits vorhanden | Response unverändert, kein zweiter Header |
| EP2 | Body-Size ist null (unbekannte Größe) | Response unverändert, kein Header gesetzt |
| EP3 | Body-Size ist bekannt (Zahl) | content-length Header mit korrektem Wert gesetzt |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| Body-Size | 0 / 1 / 100 / 1000000 | Header mit jeweiligem Wert gesetzt |
| Header vorhanden | vorhanden / nicht vorhanden | Kein Überschreiben vs. Setzen |

---

## Empfohlene Strategie

- **Strategie:** Smoke (einfache Durchlauftests)
- **Komplexität:** Niedrig
- **Testklasse:** `ContentLengthMiddlewareIntegrationTest`
- **Fixtures:** Responses mit/ohne content-length Header, Bodies mit verschiedenen Größen
- **Mocking:** RequestHandlerInterface mocken (liefert Response mit definiertem Body); Body-Mock mit getSize() → null / Zahl

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
