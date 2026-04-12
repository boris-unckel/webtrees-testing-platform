<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — A12: CLI Wartungsmodus aktivieren

**Referenz:** A12 | **SUT:** `app/Cli/Commands/SiteOffline.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Der CLI-Command `site-offline` versetzt die webtrees-Instanz in
den Wartungsmodus, indem eine Offline-Datei erstellt wird. Ein optionales Argument `message`
erlaubt das Setzen einer benutzerdefinierten Wartungsnachricht. Die Abhängigkeit
`MaintenanceModeService` wird per Dependency Injection aufgelöst.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Erfolg → Offline-Datei erstellt, SUCCESS | Nein |
| B2 | Exception beim Schreiben → FAILURE "Failed to write file" | Nein |
| B3 | Message leer → Standard-Nachricht | Nein |
| B4 | Message gesetzt → Benutzerdefinierte Nachricht | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Message leer (kein Argument) | SUCCESS, Offline-Datei mit Default-Nachricht |
| EP2 | Message mit Text (z. B. „Wartung bis 18:00") | SUCCESS, Offline-Datei mit benutzerdefinierter Nachricht |
| EP3 | Message mit Spezialzeichen (`<script>`, `"`, `&`) | SUCCESS, Nachricht korrekt gespeichert |
| EP4 | Schreib-Permission fehlt | FAILURE "Failed to write file" |
| EP5 | Offline-Datei existiert bereits | Datei wird überschrieben, SUCCESS |

---

## Grenzwerte (BVA)

Keine signifikanten Grenzwerte — die Funktionalität ist binär (Datei schreiben/nicht schreiben).

---

## Empfohlene Strategie

- **Testklasse:** `SiteOfflineCommandIntegrationTest`
- **Strategie:** Smoke (grundlegende Funktionsfähigkeit)
- **Priorität:** Niedrig
- **Fixtures:** Keine besonderen Fixtures nötig
- **Dependencies:** `MaintenanceModeService` via DI — real durchlaufen
- **Mocking:** Kein Mocking nötig
- **Besonderheit:** Aufräumen nach Test (Offline-Datei entfernen), damit nachfolgende Tests
  nicht im Wartungsmodus laufen

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `<Testklasse> [<Siegel>] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen |
| `docs/tds_methodik_spec.md` | Ggf. CLI-Command-Testing als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
