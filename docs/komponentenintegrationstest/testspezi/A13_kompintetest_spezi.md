<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — A13: CLI Wartungsmodus deaktivieren

**Referenz:** A13 | **SUT:** `app/Cli/Commands/SiteOnline.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Der CLI-Command `site-online` deaktiviert den Wartungsmodus, indem
die Offline-Datei gelöscht wird. Der Command hat keine Argumente und keine Optionen. Besonderes
Verhalten: Der Command gibt **immer** SUCCESS zurück, selbst wenn das Löschen der Datei fehlschlägt
(Exception wird gefangen und als ERROR-Nachricht ausgegeben, aber Exit-Code bleibt SUCCESS).

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Offline-Datei existiert → löschen, SUCCESS | Nein |
| B2 | Offline-Datei existiert nicht → "already online", SUCCESS | Nein |
| B3 | Lösch-Permission fehlt → ERROR-Nachricht, aber trotzdem SUCCESS (!) | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Offline-Datei existiert | Datei gelöscht, SUCCESS |
| EP2 | Offline-Datei fehlt (idempotent) | SUCCESS, Meldung "already online" |
| EP3 | Permission-Fehler beim Löschen | SUCCESS + ERROR-Nachricht in Ausgabe |
| EP4 | Sequenz `site-offline` → `site-online` | Wartungsmodus korrekt aktiviert und deaktiviert |

---

## Grenzwerte (BVA)

Keine signifikanten Grenzwerte — die Funktionalität ist binär (Datei löschen/nicht vorhanden).

---

## Empfohlene Strategie

- **Testklasse:** `SiteOnlineCommandIntegrationTest`
- **Strategie:** Smoke (grundlegende Funktionsfähigkeit)
- **Priorität:** Niedrig
- **Fixtures:** Für EP1/EP3 muss die Offline-Datei vorab erstellt werden (z. B. via `site-offline`-Command)
- **Dependencies:** Keine DI-Abhängigkeiten — direkte Dateioperationen
- **Mocking:** Kein Mocking nötig
- **Besonderheit:** Der Command gibt **immer** SUCCESS zurück (Exit-Code 0), auch bei Exceptions —
  dies muss explizit getestet werden (EP3). Test-Reihenfolge beachten: EP4 testet die
  Sequenz `offline → online` als Integrationstest der beiden Commands zusammen.

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
