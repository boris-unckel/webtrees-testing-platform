<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M13: Sprachauswahl

**Referenz:** M13 | **SUT:** `app/Http/Middleware/UseLanguage.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware ermittelt die aktive Sprache über eine
Kaskade: Session-Language, Browser-Negotiation, Fallback. Es existiert lediglich
ein L2-Stub (`assertTrue(class_exists(...))`), der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware erhält `ModuleService` per Dependency-Injection. Die Sprachauswahl
folgt einer Prioritätskaskade mit Modul-Aktivitätsprüfung.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Session-Language vorhanden + zugehöriges Modul aktiv → Session-Sprache nutzen | Nein |
| B2 | Session-Language vorhanden + Modul inaktiv → Browser-Negotiation | Nein |
| B3 | Keine Session-Language + Browser-Header vorhanden → Browser-Negotiation | Nein |
| B4 | Kein Match → Fallback auf en-US | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Session enthält Language-Code (`de`), Modul aktiv | Deutsch wird als Sprache gesetzt |
| EP2 | Session enthält Language-Code (`fr`), Modul inaktiv | Browser-Negotiation wird durchgeführt |
| EP3 | Keine Session-Language, Browser sendet `Accept-Language: de` | Deutsch wird per Browser-Header gesetzt |
| EP4 | Keine Session-Language, kein Browser-Header | Fallback auf en-US |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| Session-Language `null` | `null` | Browser-Negotiation oder Fallback |
| Session-Language gültig | `de` | Sprache direkt gesetzt (wenn Modul aktiv) |
| Session-Language ungültig | `fr` (Modul deaktiviert) | Fallback auf Browser-Negotiation |
| Accept-Language leer | `''` | Fallback auf en-US |
| Accept-Language einzeln | `de` | Deutsch |
| Accept-Language mit q-values | `de;q=0.8, en;q=0.5, fr;q=0.9` | Französisch (höchster q-Wert) |

---

## Empfohlene Strategie

- **Testklasse:** `UseLanguageMiddlewareIntegrationTest`
- **Strategie:** EP (Äquivalenzklassenpartitionierung)
- **Priorität:** Mittel
- **Fixtures:** Session-Einträge mit Language-Codes; Sprach-Module in der DB
  aktiviert/deaktiviert; Requests mit verschiedenen `Accept-Language`-Headern
- **Mocking:** `ModuleService` kann real (gegen DB) oder gemockt verwendet werden.
  Für deterministische Tests empfiehlt sich ein Mock, der gezielt bestimmte
  Sprach-Module als aktiv/inaktiv meldet.
- **Hinweis:** Die Browser-Negotiation (`Accept-Language`-Parsing) ist ein
  wichtiger Edge-Case-Bereich. Multiple q-values und Sprach-Tags mit Subtags
  (`de-DE`, `en-GB`) sollten berücksichtigt werden.

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
