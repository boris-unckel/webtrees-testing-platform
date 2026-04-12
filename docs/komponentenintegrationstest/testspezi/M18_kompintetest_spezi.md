<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M18: Housekeeping

**Referenz:** M18 | **SUT:** `app/Http/Middleware/DoHousekeeping.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — Nicht-GET Request | Request Method !== GET → Skip (kein Housekeeping) | Nein |
| B2 — GET + random !== 1 | Method GET + random_int(1, 250) !== 1 → Skip | Nein |
| B3 — GET + random === 1 | Method GET + random_int(1, 250) === 1 → 4 Cleanup-Services aufrufen | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | POST-Request | Kein Housekeeping, Handler direkt aufgerufen |
| EP2 | GET-Request + random_int !== 1 | Kein Housekeeping, Handler direkt aufgerufen |
| EP3 | GET-Request + random_int === 1 | Alle 4 Cleanup-Services werden aufgerufen |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| HTTP-Method | GET / POST / HEAD | Nur GET triggert potentiell Housekeeping |
| random_int Ergebnis | 1 / 2–250 | Nur 1 löst Housekeeping aus |

---

## Empfohlene Strategie

- **Strategie:** Spec-C (spezifikationsbasiert + Code-Review)
- **Komplexität:** Niedrig
- **Testklasse:** `DoHousekeepingMiddlewareIntegrationTest`
- **Fixtures:** GET/POST-Requests, HousekeepingService-Mock
- **Mocking:** HousekeepingService per DI mockbar; random_int() ist schwierig zu testen — entweder über wiederholte Ausführung statistisch validieren oder HousekeepingService-Aufrufe indirekt verifizieren
- **Hinweis:** random_int() kann nicht direkt gemockt werden. Testansatz: HousekeepingService mocken und bei GET-Requests prüfen, ob die Cleanup-Methoden aufgerufen werden (bei genügend Wiederholungen) oder den Nicht-GET-Pfad deterministisch testen.

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
