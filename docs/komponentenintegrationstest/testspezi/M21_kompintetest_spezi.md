<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M21: Config-Ini-Lesen

**Referenz:** M21 | **SUT:** `app/Http/Middleware/ReadConfigIni.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — Config vorhanden | CONFIG_FILE existiert → parse_ini_file, Loop über Keys, Request-Attribute setzen, Handler aufrufen | Nein |
| B2 — Config fehlt | CONFIG_FILE existiert nicht → SetupWizard als Handler verwenden | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Config-Datei vorhanden und gültig | Alle Keys als Request-Attribute gesetzt, Handler aufgerufen |
| EP2 | Config-Datei vorhanden aber leer/ungültig | Leere/fehlerhafte Attribute, Handler aufgerufen |
| EP3 | Config-Datei fehlt | SetupWizard wird als Handler verwendet |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| Config-Optionen Anzahl | 0 / 1 / viele Optionen | Alle korrekt als Attribute gesetzt |

---

## Empfohlene Strategie

- **Strategie:** Spec-C (spezifikationsbasiert + Code-Review)
- **Komplexität:** Mittel
- **Testklasse:** `ReadConfigIniMiddlewareIntegrationTest`
- **Fixtures:** Temporäre INI-Dateien (gültig, leer, fehlend), SetupWizard-Mock
- **Mocking:** SetupWizard per DI mockbar; RequestHandlerInterface mocken
- **Testbarkeit:** file_exists() und parse_ini_file() mit Temp-Dateien im Container testbar. CONFIG_FILE-Konstante muss ggf. über Reflection oder Dateisystem-Setup gesteuert werden.

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `<Testklasse> [<Siegel>] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2, 3` enthalten) |
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
