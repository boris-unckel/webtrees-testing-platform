<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M23: Update-Prüfung

**Referenz:** M23 | **SUT:** `app/Http/Middleware/CheckForNewVersion.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — GET ohne XHR | GET + X-Requested-With leer → isUpgradeAvailable() aufrufen | Nein |
| B2 — GET mit XHR | GET + X-Requested-With: XMLHttpRequest → Skip | Nein |
| B3 — POST | POST-Request → Skip | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | GET-Request ohne XHR-Header | UpgradeService::isUpgradeAvailable() wird aufgerufen |
| EP2 | GET-Request mit XHR-Header | Kein Upgrade-Check, Handler direkt aufgerufen |
| EP3 | POST-Request | Kein Upgrade-Check, Handler direkt aufgerufen |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| HTTP-Method | GET / POST / HEAD | Nur GET ohne XHR triggert Check |
| X-Requested-With | '' / 'XMLHttpRequest' | Leer → Check; XMLHttpRequest → Skip |

---

## Empfohlene Strategie

- **Strategie:** Smoke (einfache Durchlauftests)
- **Komplexität:** Niedrig
- **Testklasse:** `CheckForNewVersionMiddlewareIntegrationTest`
- **Fixtures:** GET/POST-Requests mit/ohne XHR-Header
- **Mocking:** UpgradeService per DI mockbar (isUpgradeAvailable()); RequestHandlerInterface mocken

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
