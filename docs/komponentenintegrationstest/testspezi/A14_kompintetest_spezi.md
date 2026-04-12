<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — A14: CLI initialer Config-Setup

**Referenz:** A14 | **SUT:** `app/Cli/Commands/ConfigIni.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Der CLI-Command `config-ini` erstellt die webtrees-Konfigurationsdatei
(`data/config.ini.php`) aus 14 Optionen: `--dbtype`, `--dbhost`, `--dbport`, `--dbuser`,
`--dbpass`, `--dbname`, `--dbkey`, `--dbcert`, `--dbca`, `--dbverify`, `--tblpfx`, `--base-url`,
`--rewrite-urls`, `--block-asn`. Der Command baut einen INI-String zusammen, schreibt ihn in
die Datei und testet die DB-Verbindung via `DB::connect()`. Testbarkeit ist eingeschränkt
(Mittel), da keine Dependency Injection vorhanden ist und `DB::connect()` statisch aufgerufen wird.

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | INI-String zusammenbauen → Datei schreiben | Nein |
| B2 | `base_url` leer → WARNING-Ausgabe | Nein |
| B3 | `DB::connect()` Erfolg → SUCCESS | Nein |
| B4 | `DB::connect()` Exception → FAILURE | Nein |
| B5 | `base_url` mit Trailing Slashes → `rtrim` | Nein |
| B6 | `dbverify=true` → `'1'` in INI | Nein |
| B7 | `dbpass` mit Spezialzeichen → korrekt escaped | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Alle Defaults + `base-url` gesetzt | SUCCESS, Config-Datei erstellt |
| EP2 | Alle Optionen explizit gesetzt | SUCCESS, alle Werte in Config |
| EP3 | `base-url` leer | WARNING + SUCCESS |
| EP4 | DB-Credentials gültig | SUCCESS, Verbindungstest bestanden |
| EP5 | DB-Credentials ungültig | FAILURE |
| EP6 | `dbpass` mit Spezialzeichen (`"`, `\`, `'`) | Korrekt escaped in INI-Datei |
| EP7 | `base-url` mit Trailing Slashes (`http://example.com///`) | Slashes entfernt via `rtrim` |
| EP8 | `dbverify=true` | Wert `'1'` in INI-Datei |
| EP9 | Config-Datei existiert bereits | Datei wird überschrieben |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| `base-url` Leerstring | `''` | WARNING + SUCCESS |
| `base-url` mit Slashes | `http://example.com/` | Trailing Slash entfernt |
| `dbport` Leerstring | `''` | Default-Port verwendet |
| `dbport` Standard | `3306` | Korrekt in INI |
| `dbport` Maximum | `65535` | Korrekt in INI |
| `dbpass` Leerstring | `''` | Leeres Passwort in INI |
| `dbpass` mit Anführungszeichen | `with"quote` | Korrekt escaped |
| `dbpass` mit Backslash | `with\\backslash` | Korrekt escaped |

---

## Empfohlene Strategie

- **Testklasse:** `ConfigIniCommandIntegrationTest`
- **Strategie:** Spec-C (spezifikationsbasiert, Conditions-Coverage)
- **Priorität:** Hoch
- **Testbarkeit:** Mittel — keine DI, statische `DB::connect()`, reale Datei-I/O
- **Fixtures:** Temporäres Verzeichnis für Config-Datei, gültige/ungültige DB-Credentials
- **Dependencies:** Keine DI — statischer `DB::connect()`-Aufruf
- **Mocking:** `DB::connect()` muss ggf. über Prozess-Isolation oder Testdatenbank abgedeckt werden
- **Besonderheit:** Die generierte INI-Datei muss nach dem Test aufgeräumt werden, um den
  Stack-Zustand nicht zu korrumpieren. Spezialzeichen im `dbpass` sind ein bekannter
  Fehlervektor in INI-Dateien.

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
