<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M06: Session-Initialisierung

**Referenz:** M06 | **SUT:** `app/Http/Middleware/UseSession.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware verwaltet Session-Lifecycle (Start, Destroy,
Timestamp-Aktualisierung) und Masquerade-Erkennung. Es existiert lediglich ein L2-Stub
(`assertTrue(class_exists(...))`), der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware nutzt keine Dependency-Injection, sondern statische Facades (`Session`,
`Auth`, `Registry`). Das erschwert die Testbarkeit erheblich.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `session_status()` aktiv → `session_destroy()` vor Neustart | Nein |
| B2 | `session_status()` inaktiv → direkter Start | Nein |
| B3 | Masquerade `null` + Zeit-Delta >= 60s → Timestamp aktualisieren | Nein |
| B4 | Masquerade `null` + Zeit-Delta < 60s → Timestamp-Update überspringen | Nein |
| B5 | Masquerade `!== null` → Timestamp-Update überspringen | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Session bereits aktiv vor Middleware-Aufruf | Session wird zerstört und neu gestartet |
| EP2 | Session inaktiv vor Middleware-Aufruf | Session wird direkt gestartet |
| EP3 | Masquerade `null`, letzter Timestamp >= 60s her | Session-Timestamp wird aktualisiert |
| EP4 | Masquerade `null`, letzter Timestamp < 60s her | Session-Timestamp wird nicht aktualisiert |
| EP5 | Masquerade aktiv (anderer User) | Session-Timestamp wird nicht aktualisiert |

---

## Grenzwerte (BVA)

| Grenzwert | Wert (Delta in Sekunden) | Erwartung |
|---|---|---|
| Unter Schwelle | 0 | Kein Timestamp-Update |
| Knapp unter Schwelle | 59 | Kein Timestamp-Update |
| Exakt an Schwelle | 60 | Timestamp-Update |
| Knapp über Schwelle | 61 | Timestamp-Update |

---

## Empfohlene Strategie

- **Testklasse:** `UseSessionMiddlewareIntegrationTest`
- **Strategie:** Spec-C (spezifikationsbasiert, Conditions-Coverage)
- **Priorität:** Mittel
- **Testbarkeit:** Eingeschränkt wegen statischer Facades (`Session`, `Auth`, `Registry`).
  Erfordert entweder:
  - Integration über den realen HTTP-Stack (bevorzugt für L3)
  - Oder Refactoring-Vorschlag für bessere Testbarkeit
- **Fixtures:** Authentifizierter User in der DB, Session-Einträge mit definierten Timestamps
- **Mocking:** Ggf. Clock-Mocking für deterministische Timestamp-Prüfungen

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
