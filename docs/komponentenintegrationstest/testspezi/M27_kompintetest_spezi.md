<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M27: DB-Transaktion mit Retry

**Referenz:** M27 | **SUT:** `app/Http/Middleware/UseTransaction.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — Handler erfolgreich | DB::connection()->transaction() Callback erfolgreich → Commit | Nein |
| B2 — Handler Exception | Callback wirft Exception → Rollback | Nein |
| B3 — Deadlock + Retry + Erfolg | Deadlock bei Versuch 1–3 → Retry, dann Erfolg | Nein |
| B4 — Alle Retries fehlgeschlagen | Deadlock auf allen 3 Versuchen → Exception propagiert | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Handler-Callback erfolgreich | Transaktion wird committed, Response zurückgegeben |
| EP2 | Handler-Callback wirft Exception | Transaktion wird zurückgerollt, Exception propagiert |
| EP3 | Deadlock beim 1. Versuch, Erfolg beim 2. | Retry erfolgreich, Response zurückgegeben |
| EP4 | Deadlock auf allen 3 Versuchen | Exception wird nach letztem Versuch propagiert |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| Retry-Versuche | 1 (Erfolg) / 2 (Retry+Erfolg) / 3 (Retry+Retry+Erfolg) / 4+ (alle fehlgeschlagen) | Grenze bei 3 Versuchen: ab 4 → Exception |

---

## Empfohlene Strategie

- **Strategie:** Spec-C (spezifikationsbasiert + Code-Review)
- **Komplexität:** Mittel
- **Testklasse:** `UseTransactionMiddlewareIntegrationTest`
- **Fixtures:** Standard-Request, Handler-Mock der Response oder Exception liefert
- **Mocking:** RequestHandlerInterface mocken (liefert Response oder wirft Exception); DB::connection()->transaction() wird intern verwendet — Deadlock-Szenarien schwierig nachzustellen
- **Testbarkeit:** Eingeschränkt — DB::transaction() kapselt die Retry-Logik intern. Deadlock-Szenarien erfordern entweder echte Deadlocks (zwei parallele Transaktionen) oder Mocking der DB-Verbindung. Der Erfolgs- und Exception-Pfad sind einfacher testbar.

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
