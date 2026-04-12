<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M15: PHP-Error-zu-Exception-Konvertierung

**Referenz:** M15 | **SUT:** `app/Http/Middleware/ErrorHandler.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — ErrorException werfen | error_reporting() & errno !== 0 → throw ErrorException | Nein |
| B2 — Fehler ignorieren | error_reporting() & errno === 0 → return true (unterdrückt via @) | Nein |
| B3 — Normaler Request | Handler registrieren → Request verarbeiten → restore_error_handler() | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Fehler mit error_reporting aktiv (E_ALL) | ErrorException wird geworfen |
| EP2 | Fehler mit @ unterdrückt (error_reporting & errno === 0) | Fehler wird ignoriert (return true) |
| EP3 | Kein Fehler während Request | Request wird normal verarbeitet, Handler restored |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| error_reporting Stufe | E_ALL / E_USER_NOTICE / 0 | E_ALL und E_USER_NOTICE → Exception; 0 → ignoriert |
| errno Typ | E_NOTICE / E_WARNING / E_ERROR | Verschiedene ErrorException-Severity-Stufen |

---

## Empfohlene Strategie

- **Strategie:** Spec-C (spezifikationsbasiert + Code-Review)
- **Komplexität:** Mittel
- **Testklasse:** `ErrorHandlerMiddlewareIntegrationTest`
- **Fixtures:** Standard-Request/Response, Handler-Mock
- **Mocking:** RequestHandlerInterface mocken; innere Handler-Logik triggert gezielt PHP-Fehler (trigger_error)
- **Testbarkeit:** Schwierig — globale Error-Handler (set_error_handler/restore_error_handler) beeinflussen das Test-Environment. Test muss sicherstellen, dass der ursprüngliche Handler nach dem Test wiederhergestellt ist. Isolation über try/finally empfohlen.

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
