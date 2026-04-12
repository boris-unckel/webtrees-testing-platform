<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M14: Theme-Auswahl

**Referenz:** M14 | **SUT:** `app/Http/Middleware/UseTheme.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware ermittelt das aktive Theme über eine
Kaskade: Session-Theme, Site-Default, Fallback. Es existiert lediglich ein L2-Stub
(`assertTrue(class_exists(...))`), der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware erhält `ModuleService` per Dependency-Injection. Die Theme-Auswahl
folgt einer Prioritätskaskade mit Modul-Aktivitätsprüfung.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Session-Theme vorhanden + zugehöriges Modul aktiv → Session-Theme nutzen | Nein |
| B2 | Session-Theme nicht vorhanden oder Modul inaktiv → Site-Default prüfen | Nein |
| B3 | Site-Default-Theme vorhanden + Modul aktiv → Site-Default nutzen | Nein |
| B4 | Kein gültiges Theme gefunden → Fallback auf `WebtreesTheme` | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Session enthält Theme-Name, zugehöriges Modul aktiv | Session-Theme wird aktiviert |
| EP2 | Kein Session-Theme, Site-Default konfiguriert + Modul aktiv | Site-Default-Theme wird aktiviert |
| EP3 | Kein Session-Theme, kein Site-Default (oder Modul inaktiv) | Fallback auf `WebtreesTheme` |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| Session-Theme `null` | `null` | Site-Default oder Fallback |
| Session-Theme gültig | `custom-theme` (Modul aktiv) | Custom-Theme aktiviert |
| Session-Theme ungültig | `webtrees` (Modul deaktiviert) | Site-Default oder Fallback |
| Site-Default `null` | `null` | Fallback auf `WebtreesTheme` |
| Site-Default gültig | `custom-theme` (Modul aktiv) | Custom-Theme aktiviert |

---

## Empfohlene Strategie

- **Testklasse:** `UseThemeMiddlewareIntegrationTest`
- **Strategie:** EP (Äquivalenzklassenpartitionierung)
- **Priorität:** Niedrig
- **Fixtures:** Session-Einträge mit Theme-Namen; Theme-Module in der DB
  aktiviert/deaktiviert; Site-Preference `THEME_DIR` gesetzt/leer
- **Mocking:** `ModuleService` kann real (gegen DB) oder gemockt verwendet werden.
  Mock empfohlen, um Theme-Module gezielt als aktiv/inaktiv zu steuern.
- **Hinweis:** Die Kaskade (Session → Site-Default → Fallback) ist analog zu
  M13 (Sprachauswahl). Wiederverwendbare Test-Infrastruktur für die
  Kaskaden-Logik kann zwischen M13 und M14 geteilt werden.

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
