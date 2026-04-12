<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M12: Request-Handler-Dispatch

**Referenz:** M12 | **SUT:** `app/Http/Middleware/RequestHandler.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware löst den Request-Handler auf und ruft ihn auf.
Die Auflösung unterscheidet zwischen String-Klassennamen (Container-Lookup) und
Objekt-Instanzen (direkter Aufruf). Es existiert lediglich ein L2-Stub
(`assertTrue(class_exists(...))`), der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware nutzt keine Dependency-Injection, sondern `Registry`/`Validator` zur
Handler-Extraktion aus Request-Attributen. Die Kernlogik ist die Typunterscheidung
des Handlers.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Handler ist String (Klassenname) → `Container::get()` zur Instanziierung | Nein |
| B2 | Handler ist bereits ein Objekt → direkt `handle()` aufrufen | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Handler als String-Klassenname im Request-Attribut | Container löst Klasse auf, `handle()` wird aufgerufen, Response zurückgegeben |
| EP2 | Handler als Objekt-Instanz im Request-Attribut | `handle()` wird direkt aufgerufen, Response zurückgegeben |

---

## Grenzwerte (BVA)

Keine sinnvollen Grenzwerte — die Unterscheidung ist binär (String vs. Objekt).

---

## Empfohlene Strategie

- **Testklasse:** `RequestHandlerMiddlewareIntegrationTest`
- **Strategie:** Spec-C (spezifikationsbasiert, Conditions-Coverage)
- **Priorität:** Niedrig
- **Fixtures:** Request-Objekte mit Handler-Attribut (einmal als FQCN-String,
  einmal als vorinstanziiertes Handler-Objekt)
- **Mocking:** Einfacher Test-Handler (`RequestHandlerInterface`-Implementierung),
  der eine definierte Response zurückgibt. Für EP1: Handler-Klasse muss im
  Container registriert sein.
- **Hinweis:** Der Test ist kompakt (2 Testmethoden). Die Hauptkomplexität liegt
  im korrekten Container-Setup für EP1.

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
