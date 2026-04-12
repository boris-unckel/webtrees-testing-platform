<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M10: Routen-Laden

**Referenz:** M10 | **SUT:** `app/Http/Middleware/LoadRoutes.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware lädt API- und Web-Routen über injizierte
Route-Provider und registriert sie im Container. Es existiert lediglich ein L2-Stub
(`assertTrue(class_exists(...))`), der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware erhält `ApiRoutes` und `WebRoutes` per Dependency-Injection. Der Ablauf
ist sequenziell ohne echte Branches: Routen laden, im Container registrieren, Handler
aufrufen.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Normal-Fall: API-Routen und Web-Routen werden geladen | Nein |
| B2 | Routen werden im Container registriert | Nein |
| B3 | Handler wird nach Routen-Registration aufgerufen | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Normal-Fall: API- und Web-Routen werden geladen | Routen-Registry im Container enthält Routen, Handler wird aufgerufen |

---

## Grenzwerte (BVA)

Keine sinnvollen Grenzwerte — der Ablauf ist linear ohne parameterabhängige Verzweigungen.

---

## Empfohlene Strategie

- **Testklasse:** `LoadRoutesMiddlewareIntegrationTest`
- **Strategie:** Smoke (minimaler Durchlauftest)
- **Priorität:** Niedrig
- **Testbarkeit:** Sehr gut — `ApiRoutes` und `WebRoutes` werden per DI injiziert
- **Fixtures:** Container mit registrierten Route-Providern; Prüfung, dass nach
  Middleware-Durchlauf Routen im Container vorhanden sind
- **Mocking:** Ggf. Mock-Implementierungen von `ApiRoutes`/`WebRoutes`, um
  spezifische Routen-Sets zu kontrollieren. Alternativ: reale Route-Provider
  nutzen (bevorzugt in L3).
- **Hinweis:** Der Test verifiziert primär, dass die Middleware die Kette
  korrekt aufruft und Routen registriert werden. Die Korrektheit der Routen
  selbst wird in Layer 4 (E2E) geprüft.

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
