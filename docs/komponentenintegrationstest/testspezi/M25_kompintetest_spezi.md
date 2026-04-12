<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M25: GEDCOM-Tag-Registrierung

**Referenz:** M25 | **SUT:** `app/Http/Middleware/RegisterGedcomTags.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — Sequenzieller Ablauf | Keine Verzweigung — registerTags() → Handler aufrufen | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Normal-Fall (Tags registriert) | Gedcom::registerTags() wird aufgerufen, Handler verarbeitet Request |

---

## Grenzwerte (BVA)

[Nicht sinnvoll — keine variablen Eingabegrößen oder Grenzfälle]

---

## Empfohlene Strategie

- **Strategie:** Smoke (einfacher Durchlauftest)
- **Komplexität:** Niedrig
- **Testklasse:** `RegisterGedcomTagsMiddlewareIntegrationTest`
- **Fixtures:** Standard-Request, Gedcom-Mock
- **Mocking:** Gedcom per DI mockbar (registerTags()-Aufruf verifizieren); RequestHandlerInterface mocken

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
