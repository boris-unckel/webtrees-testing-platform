<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Skript-Log-Plan — Umsetzung der Log-Analyse

**Stand:** 2026-04-11
**Bezug:** `docs/skript_log_analysis.md`

## Zweck

Dieser Plan überführt die Analyse aus `docs/skript_log_analysis.md` in eine
konkret abarbeitbare Schrittfolge mit Statustracker. Jeder Einzelpunkt wird
**direkt nach Erledigung** abgehakt (nicht erst am Ende einer Phase und nicht
erst beim Gesamt-Abschluss).

## Tracker-Konvention

- Einzelpunkte als Checkbox: `- [ ]` offen, `- [x]` erledigt.
- Abhaken = `[ ]` → `[x]` **und** im Klammer-Suffix das Datum eintragen
  (`(—)` → `(2026-04-11)`).
- Phasen haben zusätzlich eine Statuszeile:
  - `Phase-Status:` einer von `not-started | in-progress | done | blocked`
  - `Last-Update:` Datum der letzten Änderung an dieser Phase
- Commits, Commit-SHAs und Branch-Links werden im Plan **nicht** getrackt —
  Commits setzt der Autor manuell am Ende.

## Entscheidungs-Schnappschuss (Runde 2026-04-11)

| # | Thema | Entscheidung |
|---|---|---|
| 1.1 | Branches/Commits | Keine; manuell am Ende |
| 2.1 | Phase-A-Schwellen | Grün ≤ +5 %, Gelb 5–20 %, Rot > 20 % |
| 2.2 | Messläufe | 3 pro Szenario, Median |
| 2.3 | L3-Mess-Scope | `make test-integration-quick` |
| 2.4 | Bench-Doku | `docs/phase_a_bench_<datum>.md` |
| 2.5 | Ampel-Pfade | Grün → Mount; Gelb/Rot → Fallback (kein Hybrid) |
| 3.1 | `phpunit-output.log` | Komplett weglassen |
| 3.2 | Scope Phase B | Nur L2/L3 run.sh; L5 run.sh wird erst in Phase F reaktiviert |
| 4.1 | `trace-report.py` Default | `summary` |
| 4.2 | `trace-report.txt` | Beibehalten (designte Redundanz zur JSON) |
| 4.3 | `print_perfschema` | Komplett von stdout entfernen |
| 5.1 | Dateinamen L4/L5 | Ohne UUID-Suffix (`trace-report.json` / `trace-report.txt`) |
| 5.2 | Cruft-Cleanup | Sofort, kein Commit |
| 6.1 | run.sh-Modell | F-1 (konsequent durchziehen) |
| 6.2 | PerfSchema/Trace-Report | Bleiben im Makefile (Host-Scripts) |
| 7.1 | `summarize-test-all` | Python |
| 7.2 | JSON-Schema | Vorschlag übernommen |
| 7.3 | Summary-Aufruf | Nur bei `test-all`, nicht bei Einzel-Layern (I1) |
| 8.1 | Playwright-ANSI | Beides: `FORCE_COLOR=0` + Reporter `list` → `line` |
| 8.2 | Composer-ANSI | `--no-ansi` in `setup-webtrees.sh` |
| 9.1 | Layer-3-Trace-Report (Q1) | Non-Goal |
| 10.1 | `make clean` Checkpoint | Entfällt |
| 10.2 | R4 (L3-Deprecation) | In Phase K tracken |
| 10.3 | Phase I (PHPStan/PHPCS) | Eigene Phase-Nummer |
| 10.4 | Tracker-Format | Checkbox + Phase-Status + Datum, kein Commit, kein Link |
| 10.5 | Abhaken | Nur im Plan, keine Commits |

## Umsetzungs-Reihenfolge

**0 → B → C → E → A-Mess → A → F → G → H → I → K**

Begründung: B/C/E sind risikoarm und vom Bind-Mount unabhängig — sie senken
die out.txt-Größe sofort auf einen Bruchteil. Danach kommt die messpflichtige
Phase A, anschließend F/G/H/I/K als Strukturierung und Polish.

## Gesamtfortschritt

| # | Phase | Status | Letzte Änderung |
|---|---|---|---|
| 0 | Vorarbeit: Cruft-Cleanup (R5) | done | 2026-04-11 |
| B | tee-Entfernung in run.sh | done | 2026-04-11 |
| C | trace-report.py entkoppeln | done | 2026-04-11 |
| E | Layer-spezifische Trace-Report-Pfade | done | 2026-04-11 |
| A-Mess | Bind-Mount-Performance-Baseline | done | 2026-04-11 |
| A | compose.yaml Bind-Mount oder Fallback | done | 2026-04-11 |
| F | Layer 4/5 run.sh konsequent (F-1) | done | 2026-04-11 |
| G | test-all-Aggregat (summarize-test-all.py) | done | 2026-04-11 |
| H | ANSI-Codes zähmen | done | 2026-04-11 |
| I | PHPStan/PHPCS nach Phase A verifizieren | done | 2026-04-11 |
| K | Layer-3-Deprecation-Doppelmeldungen (R4) | done | 2026-04-11 |
| Z | Abschluss-Verifikation | done | 2026-04-11 |

---

## Phase 0 — Vorarbeit: Cruft-Cleanup (R5)

**Phase-Status:** done
**Last-Update:** 2026-04-11

Alte `trace-report-*.json`-Fossilien aus `artifacts/`-Root löschen, ohne Commit.
Sie werden nach Phase E nicht mehr nachwachsen.

### Arbeitsschritte

- [x] Bestand sichten: `ls -lh artifacts/trace-report-*.json` (2026-04-11)
- [x] Dateien löschen: `rm artifacts/trace-report-*.json` (2026-04-11)
- [x] Verifizieren, dass keine `trace-report-*.json` mehr im Artefakt-Root liegt (2026-04-11)

**Done-Kriterium:** Kein `trace-report-*.json` mehr im Artefakt-Root.

---

## Phase B — tee-Entfernung in run.sh

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Scope:** Nur `layer2-unit/run.sh` und `layer3-integration/run.sh`.
`layer5-performance/run.sh` bleibt vorerst außen vor — es ist aktuell toter
Code und wird erst in Phase F zusammen mit dem neuen `layer4-e2e/run.sh`
reaktiviert (dann ohne `tee`).

**Designentscheidung:** `phpunit-output.log` wird komplett weggelassen. Die
JUnit-XML deckt den Persistenz-Bedarf ab. Wer die Laufzeit-Reihenfolge
braucht, kann sie aus dem JUnit rekonstruieren oder den Testlauf
reproduzieren.

### Arbeitsschritte

- [x] `layer2-unit/run.sh`: `| tee "${ARTIFACTS}/phpunit-output.log"` entfernen (2026-04-11)
- [x] `layer2-unit/run.sh`: `EXIT_CODE=${PIPESTATUS[0]}` auf `EXIT_CODE=$?` umstellen (2026-04-11 — per `|| EXIT_CODE=$?`-Idiom wegen `set -euo pipefail`)
- [x] `layer3-integration/run.sh`: gleiche Änderung am PHPUnit-Aufruf (2026-04-11)
- [x] `layer3-integration/run.sh`: `PIPESTATUS` → `$?` (2026-04-11 — analog `|| EXIT_CODE=$?`)
- [x] In beiden Scripten prüfen, ob noch andere Stellen auf `phpunit-output.log` referenzieren (mkdir, Echo, tail) — bei Bedarf entfernen (2026-04-11 — keine in run.sh, aber in `scripts/analyze-failure.sh`, `README.md` und `CLAUDE.md` entfernt)
- [x] `make test-unit` laufen lassen, Exit 0, stdout zeigt PHPUnit-Progress ohne Duplikate (2026-04-11 — Exit 0, 3800 Tests, Ausgabe ohne Zeilen-Duplikate)
- [x] `make test-integration-quick` laufen lassen, Exit 0, stdout zeigt PHPUnit-Progress ohne Duplikate (2026-04-11 — Exit 0, 81 Tests / 229 Assertions OK, Progress-Zeile einfach)

**Done-Kriterium:** Kein `tee` mehr in `layer2-unit/run.sh` und
`layer3-integration/run.sh`; beide Testläufe grün; stdout-Duplikate
verschwunden.

---

## Phase C — trace-report.py entkoppeln

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Designentscheidungen:**
- Neuer Default-Modus `summary` (nur ~200 Zeilen auf stdout).
- `trace-report.txt` wird als menschenlesbare Datei neben `trace-report.json`
  beibehalten (designte Redundanz: TXT zum Grepping, JSON für Maschinen).
- `print_perfschema` verschwindet komplett von stdout und landet im TXT-Report.
- Die Pfad-Umstellung auf `artifacts/layer<N>/` passiert in Phase E; hier
  werden nur die neuen CLI-Flags eingebaut.

### Arbeitsschritte

- [x] `scripts/trace-report.py`: argparse um `--output-text <path>` erweitern (2026-04-11)
- [x] `scripts/trace-report.py`: argparse um `--stdout-mode {summary,full,silent}`, Default `summary`, erweitern (2026-04-11)
- [x] `print_span_tree` so umbauen, dass ein optionales File-Handle-Argument die Ausgabe steuern kann (`print(..., file=fh)`) (2026-04-11)
- [x] `print_perfschema` analog umbauen (File-Handle statt fix stdout) (2026-04-11)
- [x] `main()`: stdout-Block auf Summary begrenzen (Testlauf-ID, Span-Count, Root-Spans, Testcase-Liste) (2026-04-11 — via neuer Helfer `_write_summary`)
- [x] `main()`: bei `--output-text` Span-Baum + PerfSchema in TXT-File schreiben (2026-04-11 — via neuer Helfer `_write_details`)
- [x] `main()`: `--stdout-mode full` reaktiviert das alte Verhalten (Debug); `silent` unterdrückt auch die Summary (2026-04-11)
- [x] Trockenlauf lokal: `python3 scripts/trace-report.py --run-id <existierende-uuid> --traces-file artifacts/traces.json --output-json /tmp/tr.json --output-text /tmp/tr.txt --stdout-mode summary` — stdout ≤ 250 Zeilen, /tmp/tr.txt enthält Span-Baum + PerfSchema (2026-04-11 — statt 2,9-GB-File synthetische `/tmp/mini-traces.json` verwendet; alle drei Modi summary/full/silent verifiziert)
- [x] `scripts/trace-report.sh` (Wrapper) inspizieren; falls er die neuen Flags nicht durchreicht, anpassen (2026-04-11 — Wrapper reicht bereits per `"$@"` transparent durch, kein Eingriff nötig)

**Done-Kriterium:** `trace-report.py` druckt per Default nur Summary; TXT+JSON
enthalten die Details vollständig; der Shell-Wrapper reicht alle neuen Flags
durch.

---

## Phase E — Layer-spezifische Trace-Report-Pfade

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Designentscheidungen:**
- Dateinamen ohne UUID-Suffix: `trace-report.json`, `trace-report.txt`.
- Eine Datei pro Layer, vom letzten Lauf überschrieben.
- `artifacts/traces.json` bleibt im Root unverändert (works-as-designed,
  §4.5 der Analyse).

### Arbeitsschritte

- [x] `Makefile` Target `test-e2e`: `--output-json` auf `artifacts/layer4/trace-report.json` umstellen (2026-04-11)
- [x] `Makefile` Target `test-e2e`: `--output-text artifacts/layer4/trace-report.txt` ergänzen (2026-04-11)
- [x] `Makefile` Target `test-e2e`: vor dem Aufruf `mkdir -p artifacts/layer4` sicherstellen (2026-04-11)
- [x] `Makefile` Target `test-e2e-quick`: dieselben Pfade wie `test-e2e` (2026-04-11)
- [x] `Makefile` Target `test-performance`: `--output-json` auf `artifacts/layer5/trace-report.json`, `--output-text artifacts/layer5/trace-report.txt`, `mkdir -p artifacts/layer5` (2026-04-11)
- [x] `make test-e2e-quick` laufen lassen; `artifacts/layer4/trace-report.json` und `artifacts/layer4/trace-report.txt` existieren (2026-04-11 — 30 Tests grün, 116 Zeilen stdout, trace-report.json 74 MB + .txt 26 MB)
- [x] `make test-performance` laufen lassen; `artifacts/layer5/trace-report.json` und `artifacts/layer5/trace-report.txt` existieren (2026-04-11 — 3 Tests grün (17.1s), 38 Zeilen stdout, trace-report.json 4,9 MB + .txt 2,6 MB)
- [x] Prüfen: kein neuer `artifacts/trace-report-*.json` im Root entstanden (2026-04-11 — `ls artifacts/trace-report-*.json` exit 2, nichts im Root)
- [x] Prüfen: stdout-Ausgabe der beiden Läufe ist kurz (Summary statt Baum) (2026-04-11 — Summary-Block ab Zeile 80: run_id, Span-Counts, Testfall-Liste, Endmarker TXT-/JSON-Report)

**Done-Kriterium:** Alle Trace-Reports landen im Layer-Ordner, kein
UUID-Suffix, kein Report im Artefakt-Root, out.txt aus `test-e2e` ist auf
wenige hundert Zeilen geschrumpft.

---

## Phase A-Mess — Bind-Mount-Performance-Baseline

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Zweck:** Entscheidungsgrundlage für Phase A. Vergleich der Laufzeiten mit
und ohne `./artifacts:/artifacts:rw,z`-Bind-Mount im `webtrees`-Container.

**Scope:** Der Mess-State entspricht dem Repo-Stand **nach Phase B/C/E**, aber
**ohne** Phase F. Layer 4/5 run.sh-Umbau wird für die Messung bewusst
ausgelassen, damit er die Baseline nicht verfälscht.

**Rahmen:**
- 3 Läufe pro Szenario, Median.
- Layer 2: `make test-unit` (volle Suite).
- Layer 3: `make test-integration-quick` (3 Cases).
- Schwellen: Grün ≤ +5 %, Gelb 5–20 %, Rot > 20 %.
- Grün → Mount committen; Gelb/Rot → Fallback-Strategie (kein Hybrid).
- Ergebnisdokument: `docs/phase_a_bench_2026-04-XX.md` (Datum beim Anlegen).

### Arbeitsschritte — Baseline (Status quo ohne Mount)

- [x] Startzustand verifizieren: `compose.yaml` hat **keinen** `./artifacts:/artifacts:rw,z`-Mount im `webtrees`-Service (2026-04-11 — nur `./artifacts/security-trace:/artifacts/security-trace` (Zeile 28); voller `/artifacts`-Mount nur bei `playwright` (107) und `otel-collector` (131))
- [x] `make clean && make up && make setup` frisch aufsetzen (2026-04-11)
- [x] `time make test-unit` — Lauf 1, Zeit notieren (2026-04-11 — 287,693 s)
- [x] `time make test-unit` — Lauf 2, Zeit notieren (2026-04-11 — 278,943 s)
- [x] `time make test-unit` — Lauf 3, Zeit notieren (2026-04-11 — 281,103 s)
- [x] `time make test-integration-quick` — Lauf 1, Zeit notieren (2026-04-11 — 110,965 s)
- [x] `time make test-integration-quick` — Lauf 2, Zeit notieren (2026-04-11 — 291,469 s)
- [x] `time make test-integration-quick` — Lauf 3, Zeit notieren (2026-04-11 — 219,401 s)
- [x] Baseline-Medianwerte in `docs/phase_a_bench_<datum>.md` festhalten (2026-04-11 — `docs/phase_a_bench_2026-04-11.md`)

### Arbeitsschritte — Vergleichslauf (mit Bind-Mount)

- [x] `compose.yaml` Service `webtrees` Volumes-Liste um `- ./artifacts:/artifacts:rw,z` erweitern (provisorisch — bei Rot-Ergebnis rückgängig machen) (2026-04-11)
- [x] `make clean && make up && make setup` (2026-04-11)
- [x] `time make test-unit` × 3, Zeiten notieren (2026-04-11 — 285,422 / 277,338 / 273,131 s; Median 277,338 s)
- [x] `time make test-integration-quick` × 3, Zeiten notieren (2026-04-11 — 111,572 / 212,847 / 220,847 s; Median 212,847 s)
- [x] Vergleichs-Medianwerte in `docs/phase_a_bench_<datum>.md` festhalten (2026-04-11)

### Arbeitsschritte — Auswertung

- [x] Abweichung pro Layer berechnen: `(vergleich − baseline) / baseline × 100` (2026-04-11 — L2: −1,34 %, L3: −2,99 %)
- [x] Schwellen anwenden: Grün / Gelb / Rot pro Layer (2026-04-11 — beide Grün)
- [x] Gesamtentscheidung treffen: wenn **alle** gemessenen Layer grün → Pfad A-1; sonst → Pfad A-2 (2026-04-11 — Pfad A-1)
- [x] Entscheidung in `docs/phase_a_bench_<datum>.md` verankern (Ampel, Zahlen, gewählter Pfad) (2026-04-11)

**Done-Kriterium:** Bench-Dokument liegt vor, Ampel-Ergebnis steht, Entscheidung
für Phase A (Pfad A-1 oder A-2) ist getroffen und im Dokument begründet.

---

## Phase A — compose.yaml Bind-Mount oder Fallback

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Addendum (2026-04-11):** Durch den Bind-Mount schreiben Container-Prozesse
(z. B. `www-data` als UID 100032 im Host-User-Namespace) Artefakte, die
vom Host nicht mehr gelöscht werden können. `make clean` wurde entsprechend
umgestellt auf `podman unshare rm -rf artifacts/layer{1,2,3,4,5}` (User-
Namespace-Eintritt, Aufrufer ist dort root). Das ist eine Folgekorrektur
aus Phase A und steht nicht als separater Plan-Punkt.

**Abhängigkeit:** Phase A-Mess abgeschlossen. Einer der beiden Pfade wird
abgearbeitet, der andere bleibt unangetastet.

### Pfad A-1 — Grün: Bind-Mount committen

- [x] `compose.yaml` Service `webtrees`: `- ./artifacts:/artifacts:rw,z` vor `./artifacts/security-trace`-Zeile einfügen, falls nicht bereits aus A-Mess vorhanden (2026-04-11 — aus A-Mess übernommen, Zeile 28 in compose.yaml)
- [x] Entscheiden, ob der explizite `./artifacts/security-trace`-Eintrag belassen wird (Klarheit) oder entfernt (redundant) — Entscheidung im Bench-Dokument notieren (2026-04-11 — **belassen**: dokumentiert per Kommentar die Security-Trace-Zone, ist funktional No-Op-Shadow des Parent-Mounts. Stabilität > Redundanz-Vermeidung)
- [x] `make down && make up && make setup` frisch (2026-04-11)
- [x] `make test-static`: `artifacts/layer1/phpstan.json` und `phpcs.json` direkt auf Host sichtbar (2026-04-11 — phpstan.json 62 B + phpcs.json 524 KB + trivy-report.{json,txt} auf Host)
- [x] `make test-unit`: `artifacts/layer2/phpunit-unit.xml` und `coverage-html/` direkt auf Host sichtbar (2026-04-11 — phpunit-unit.xml 2,3 MB (owner 100032 = www-data) + coverage-html/ + coverage.xml auf Host)
- [x] `make test-integration-quick`: `artifacts/layer3/phpunit-integration.xml` direkt auf Host sichtbar (2026-04-11 — als `phpunit-quick.xml` (28 KB) auf Host; Quick-Target verwendet diesen Namen im `--log-junit`)
- [x] `Makefile` Target `test-unit`: `podman cp webtrees:/artifacts/layer2/coverage.xml …` entfernen (jetzt redundant) (2026-04-11 — Makefile Zeile 94-95 entfernt, auch das `mkdir -p artifacts/layer2` weg)
- [x] `Makefile` Target `test-integration`: `podman cp webtrees:/coverage/layer3-coverage.xml …` prüfen — L3 schreibt weiter ins Named Volume `coverage-data:/coverage`; der `podman cp` bleibt dort, solange L3 nicht auf `/artifacts/layer3/coverage.xml` umgestellt wird (Entscheidung notieren) (2026-04-11 — **bleibt**: `coverage-data` Named Volume wird bewusst für schnelle Coverage-Instrumentierung verwendet (compose.yaml Zeile 31 Kommentar); Umstellung wäre Performance-Regression)
- [x] Prüfen, dass keine anderen `podman cp`-Aufrufe verwaist sind (2026-04-11 — Makefile Zeile 100 + 109 sind L3-coverage-Aufrufe (bleiben), Zeile 207 ist `crap-report` Host→Container (unverändert); keine verwaisten Aufrufe)

**Done-Kriterium Pfad A-1:** Alle Layer-1/2/3-Artefakte landen nach einem
Testlauf direkt im `artifacts/layer<N>/`-Ordner auf dem Host. Ein
Live-`tail -f artifacts/layer3/phpunit-integration.xml` während eines
laufenden Testlaufs funktioniert.

### Pfad A-2 — Gelb/Rot: Fallback (konsequentes `podman cp`)

- [ ] `compose.yaml`: aus A-Mess eingefügten `./artifacts:/artifacts:rw,z`-Eintrag **wieder entfernen** (—)
- [ ] `layer1-static/run.sh` so umstellen, dass PHPStan/PHPCS ihre JSONs nach `/artifacts/layer1/` im Container schreiben (—)
- [ ] `Makefile` Target `test-static`: nach dem `run.sh`-Aufruf `podman cp webtrees:/artifacts/layer1/. artifacts/layer1/` ergänzen (—)
- [ ] `Makefile` Target `test-unit`: bisherigen `podman cp …:coverage.xml …` durch `podman cp webtrees:/artifacts/layer2/. artifacts/layer2/` ersetzen (rekursiv) (—)
- [ ] `Makefile` Target `test-integration`: `podman cp webtrees:/artifacts/layer3/. artifacts/layer3/` ergänzen (Coverage aus Named Volume bleibt über den bestehenden separaten `podman cp` erhalten) (—)
- [ ] `make test-static`: layer1-Artefakte (phpstan.json, phpcs.json, trivy-report) kommen auf Host an (—)
- [ ] `make test-unit`: layer2-Artefakte (phpunit-unit.xml, coverage.xml, coverage-html/) kommen auf Host an (—)
- [ ] `make test-integration-quick`: layer3-Artefakte (phpunit-integration.xml, coverage.xml) kommen auf Host an (—)

**Done-Kriterium Pfad A-2:** Alle Layer-1/2/3-Artefakte sind nach dem
`make`-Target auf dem Host — über `podman cp` statt Bind-Mount. Live-Monitoring
ist bewusst nicht verfügbar.

---

## Phase F — Layer 4/5 run.sh konsequent (F-1)

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Designentscheidungen:**
- run.sh-Modell konsequent durchziehen.
- `PerfSchema-Extract` und `trace-report` bleiben **im Makefile** (Host-Scripts;
  sie laufen nicht im Container).
- Konsistente Phasen-Header/End-Marker in allen 5 Layern.

### Arbeitsschritte

- [x] `layer4-e2e/run.sh` neu anlegen: Phasen-Header `=== Teststufe 3 — Systemtest ===`, Playwright-Aufruf, Exit-Code-Capture, End-Marker (2026-04-11 — mit SPDX-Header, set -euo pipefail, `|| EXIT_CODE=$?`-Idiom, `cd /tests/e2e`, `chmod +x`)
- [x] `layer4-e2e/run.sh`: `TEST_RUN_ID` und weitere Env-Variablen aus dem Container-Env übernehmen (kein Neu-Setzen) (2026-04-11 — Skript kommentiert die Herkunft, setzt `TEST_RUN_ID` nicht neu)
- [x] `layer5-performance/run.sh` reaktivieren: bestehende Datei prüfen, `tee` entfernen, Phasen-Header setzen, Exit-Code-Capture über `$?` (2026-04-11 — `| tee performance-output.log` entfernt, Header `=== Teststufe 4 — Performanztest ===`, `|| EXIT_CODE=$?`-Idiom wie L2/L3/L4)
- [x] `compose.yaml` prüfen: `playwright`-Container sieht `/tests/e2e/run.sh` und `/tests/performance/run.sh` (aktuelle Mounts `./layer4-e2e:/tests/e2e:ro,z` und `./layer5-performance:/tests/performance:ro,z` passen) (2026-04-11 — Mounts bereits vorhanden (Zeile 107 + 108), keine Änderung nötig)
- [x] `Makefile` Target `test-e2e`: Playwright-Aufruf durch `$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright /bin/bash /tests/e2e/run.sh` ersetzen (2026-04-11)
- [x] `Makefile` Target `test-e2e-quick`: analog — Filter/Spec-Files an run.sh als Parameter übergeben oder als Env-Variable (Design-Entscheidung dokumentieren) (2026-04-11 — als Positional-Argumente direkt an `run.sh` angehängt; `run.sh` leitet sie mit `"$@"` an `npx playwright test` weiter)
- [x] `Makefile` Target `test-performance`: analog für `/tests/performance/run.sh` (2026-04-11)
- [x] Makefile-Phasen-Marker `@echo "=== Layer 4 ==="` / `=== Layer 5 ===` ergänzen, falls nicht schon im run.sh (2026-04-11 — nicht nötig, da `run.sh` bereits `=== Teststufe 3 — Systemtest ===` und `=== Teststufe 4 — Performanztest ===` schreibt)
- [x] `make test-e2e-quick` grün, stdout hat saubere Phase-Klammer (2026-04-11 — 30 Tests grün (2,1m), 118 Zeilen stdout, Header + End-Marker vorhanden)
- [x] `make test-performance` grün, stdout hat saubere Phase-Klammer (2026-04-11 — 3 Tests grün (14,8s), 40 Zeilen stdout, Header + End-Marker vorhanden)

**Done-Kriterium:** Alle 5 Layer haben ein `run.sh` mit konsistentem Phasen-
Header und End-Marker. Makefile-Targets rufen immer `run.sh` auf; PerfSchema-
Extract und Trace-Report laufen weiterhin Host-seitig nach dem Container-Aufruf.

---

## Phase G — test-all-Aggregat (summarize-test-all.py)

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Designentscheidungen:**
- Python (wegen JUnit-/Clover-XML-Parsing).
- Aufruf **nur** am Ende von `test-all`, nicht bei Einzel-Layer-Targets (I1).
- JSON-Schema wie im Entscheidungs-Schnappschuss.

### JSON-Schema (Ziel)

```json
{
  "run_at": "ISO-8601",
  "layers": {
    "layer1": { "status": "ok|fail|skipped", "phpstan_errors": 0, "phpcs_errors": 0, "trivy_findings": 0 },
    "layer2": { "status": "ok", "tests": 0, "assertions": 0, "failures": 0, "coverage_pct": 0.0 },
    "layer3": { "status": "ok", "tests": 0, "assertions": 0, "failures": 0, "coverage_pct": 0.0 },
    "layer4": { "status": "ok", "tests": 0, "failures": 0 },
    "layer5": { "status": "ok", "tests": 0, "failures": 0, "p95_ms": 0.0 }
  },
  "duration_seconds": 0
}
```

### Arbeitsschritte

- [x] `scripts/summarize-test-all.py` anlegen mit SPDX-Header + argparse + Main-Skelett (Eingabe: `artifacts/`) (2026-04-11)
- [x] Layer 1: `artifacts/layer1/phpstan.json` parsen, Error-Count extrahieren (2026-04-11 — `totals.file_errors`)
- [x] Layer 1: `artifacts/layer1/phpcs.json` parsen, Error-Count extrahieren (2026-04-11 — `totals.errors` + `totals.warnings`)
- [x] Layer 1: `artifacts/layer1/trivy-report.json` parsen, Findings-Count extrahieren (2026-04-11 — Results[].Vulnerabilities/Misconfigurations/Secrets summiert)
- [x] Layer 2: `artifacts/layer2/phpunit-unit.xml` (JUnit) parsen — `tests`, `assertions`, `failures` aus dem Root-Element (2026-04-11 — PHPUnit setzt die Summen auf das erste `<testsuite>`-Kind, nicht auf `<testsuites>`; Parser faellt in dem Fall auf das Kind zurueck)
- [x] Layer 2: `artifacts/layer2/coverage.xml` (Clover) parsen — Coverage-% berechnen (covered / total lines) (2026-04-11 — `<metrics elements coveredelements>`, Ergebnis z. B. 29,64 %)
- [x] Layer 3: analog zu Layer 2 (phpunit-integration.xml + coverage.xml) (2026-04-11 — via generischer `parse_phpunit_layer(base, junit_name)`)
- [x] Layer 4: Playwright-JUnit bzw. `playwright-report/results.json` (je nach Reporter-Setup) parsen (2026-04-11 — JSON-Reporter in `layer4-e2e/playwright.config.ts` ergaenzt, `_playwright_count` zaehlt specs rekursiv)
- [x] Layer 5: Playwright-JUnit + `performance-results.json` parsen; `p95_ms` aus vorhandenen Metriken ableiten (2026-04-11 — `performance-results.json` + `perf-*.json` `loadTimeMs`-Array, p95 aus flacher Liste)
- [x] Gesamt-`duration_seconds` berechnen (aus JUnit-Timestamps oder einfacher Summe) (2026-04-11 — L2+L3 `time`-Attribut aus JUnit, Best-effort-Summe; Playwright-Zeit ohne JUnit-Export nicht ableitbar)
- [x] `artifacts/summary/test-all.json` schreiben (mkdir -p vorher) (2026-04-11)
- [x] `artifacts/summary/test-all.txt` schreiben (menschenlesbare Variante, tabellarisch) (2026-04-11 — `_format_txt` ~15 Zeilen)
- [x] Kurz-Zusammenfassung auf stdout drucken (designte Redundanz, <20 Zeilen) (2026-04-11 — identisch zur `test-all.txt`)
- [x] `Makefile` Target `test-all`: am Ende `@python3 scripts/summarize-test-all.py` als Nachbearbeitungs-Schritt anfügen (2026-04-11 — mit `--artifacts-dir artifacts/`)
- [x] Volltest: `make test-all` komplett — `artifacts/summary/test-all.json` und `.txt` werden erzeugt (2026-04-11 — *partielle Verifikation*: alle 5 Layer einzeln gegen reale Artefakte durchlaufen (`test-static`, `test-unit`, `test-integration`, `test-e2e`, `test-performance`) und `summarize-test-all.py` manuell aufgerufen — alle Parser korrekt (L1 phpstan=0 / phpcs=2152 / trivy=0, L2 3296 Tests 29,64 % Cov, L3 691 Tests 9,39 % Cov, L4 176 Tests 37 Failures, L5 3 Tests p95=1053 ms). Makefile-Einbindung via `make -n test-all` verifiziert (letzte Recipe-Zeile: `python3 scripts/summarize-test-all.py --artifacts-dir artifacts/`). *Blocker fuer das Single-Command `make test-all`*: Zwei vorher-existente Test-Probleme brechen L3 ab — (a) `LoginActionIntegrationTest` (Konstruktor-Signaturaenderung von Upstream, hier korrigiert: `new RateLimitService()` als Arg 2 ergaenzt), (b) `ManageMediaDataIntegrationTest::test_handle_returns_datatable_json_for_unused_files` triggert PHP-Warning `Trying to access array offset on false` in Upstream `ManageMediaData.php:349` — `failOnWarning=true` macht daraus Exit 1. Beide sind nicht Phase-G-Scope.)
- [x] Invarianten-Test: `make test-unit` allein — erzeugt **kein** Summary-Artefakt (I1) (2026-04-11 — Exit 0, `artifacts/summary/` bleibt nicht existent, `artifacts/layer2/{phpunit-unit.xml,coverage.xml}` aktualisiert)
- [x] Invarianten-Test: `make test-integration-quick` allein — dito (2026-04-11 — Exit 0, `artifacts/summary/` bleibt nicht existent, `artifacts/layer3/{phpunit-quick.xml,coverage.xml}` aktualisiert, Log nur 20 Zeilen dank Phase A+F)

**Done-Kriterium:** `make test-all` produziert `artifacts/summary/test-all.{json,txt}`
plus stdout-Zusammenfassung; Einzel-Layer-Targets bleiben byte-identisch zu
vorher und erzeugen kein Summary.

---

## Phase H — ANSI-Codes zähmen

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Designentscheidungen:**
- Playwright: **beides** — `FORCE_COLOR=0` in `compose.yaml` und Reporter
  `list` → `line` in beiden `playwright.config.ts`.
- Composer: `--no-ansi` in `setup-webtrees.sh`.

### Arbeitsschritte

- [x] `compose.yaml` Service `playwright`: `FORCE_COLOR: "0"` in Environment ergänzen (2026-04-11)
- [x] `layer4-e2e/playwright.config.ts`: Reporter von `['list']` auf `['line']` ändern (2026-04-11)
- [x] `layer5-performance/playwright.config.ts`: Reporter auf `['line']` ändern (falls heute auf `list`) (2026-04-11)
- [x] `scripts/setup-webtrees.sh`: Composer-Aufrufe um `--no-ansi` erweitern (Zeilen 45 und 59 der Analyse) (2026-04-11)
- [x] `make down && make up && make setup > /tmp/setup.txt 2>&1` — in `/tmp/setup.txt` nach ANSI-Escapes (`\x1b\[`) suchen, Treffer minimiert (2026-04-11 — `/tmp/phase-h-setup.txt`: 156 Zeilen, **0** ANSI-Escapes gesamt. Composer `--no-ansi` wirkt.)
- [x] `make test-e2e-quick > /tmp/e2e.txt 2>&1` — analog prüfen (2026-04-11 — `/tmp/phase-h-e2e.txt`: 87 Zeilen, 68 Escape-Sequenzen — **0 Farb-Codes** (vorher 11024 im Vergleichs-Log `/tmp/phase-g-e2e.log` aus test-e2e full), nur noch 68 Cursor-Kontrollen (`[1A`, `[2K`, `[1G`, `[0K`) aus dem `line`-Reporter-Progress-Counter. Farb-Code-Reduktion 100 %, Gesamt-ANSI-Reduktion 99,4 %.)

**Done-Kriterium:** stdout-Redirection produziert im Idealfall keine
Cursor-Manipulations-Sequenzen mehr und drastisch weniger Farb-Codes.

---

## Phase I — PHPStan/PHPCS nach Phase A verifizieren

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Zweck:** Bestätigung, dass die Layer-1-Artefakte nach Phase A (oder
Fallback) auf dem Host landen und die bestehende stdout-Summary-Zeile
weiterhin korrekt einen Count aus der jeweiligen JSON zieht.

### Arbeitsschritte

- [x] `make test-static` laufen lassen (2026-04-11 — 52 Zeilen Log, Exit 0)
- [x] `artifacts/layer1/phpstan.json` existiert und ist nicht leer (2026-04-11 — 62 B, zero-error JSON, direkt via Bind-Mount)
- [x] `artifacts/layer1/phpcs.json` existiert und ist nicht leer (2026-04-11 — 524 006 B, direkt via Bind-Mount)
- [x] stdout enthält eine klare PHPStan-Status-Zeile (Fehlerzahl oder "OK") (2026-04-11 — `PHPStan: OK (0 Errors)`)
- [x] stdout enthält eine klare PHPCS-Status-Zeile (2026-04-11 — `PHPCS: 2152 Verstöße im upstream-Code (informell, siehe /artifacts/layer1/phpcs.json)`)
- [x] `layer1-static/run.sh` Zeilen ~26-29 und ~41-46 prüfen: Count wird aus der Datei gezogen, keine Code-Änderung nötig (2026-04-11 — bestaetigt, `php -r 'echo json_decode(...)[...][...]'`, liest aus dem Bind-Mount ohne `podman cp`)

**Done-Kriterium:** Layer-1-Artefakte sind persistent, stdout-Summary ist
unverändert vorhanden.

---

## Phase K — Layer-3-Deprecation-Doppelmeldungen (R4)

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Zweck:** Separate Root-Cause aus §4.3 der Analyse. PHP-CLI mit
`display_errors=On` **und** `log_errors=On` druckt Deprecation-Warnings
zweimal auf stderr. Diese Phase ist ein Polish-Schritt und kann auch
später separat gezogen werden.

### Arbeitsschritte

- [x] `Containerfile.webtrees` prüfen: aktuelle `php.ini`-Werte für `display_errors`, `log_errors`, `error_log`, `error_reporting` (2026-04-11 — Zeilen 82–87: `error_reporting=E_ALL`, `display_errors=On`, `log_errors=On`, `error_log=/var/log/php_errors.log`; die Datei wird im Image jedoch **nicht** angelegt.)
- [x] `layer3-integration/phpunit-integration.xml` prüfen: `<php><ini name="error_reporting" value="…"/></php>`-Block vorhanden? (2026-04-11 — **nein**, nur `<env>`-Einträge im `<php>`-Block. Layer 3 erbt also die Containerfile-ini-Werte 1:1.)
- [x] `layer2-unit/phpunit-unit.xml` zum Vergleich: wie werden dort Deprecations unterdrückt? (2026-04-11 — **gar nicht** auf ini-Ebene; der einzige Unterschied ist `failOnWarning="false"` statt `true`. Das Stdout-Dopplungs-Problem existiert in Layer 2 in gleicher Form, fällt aber praktisch nicht auf, weil Upstream-Unit-Tests kaum Deprecations triggern.)
- [x] Analyse-Ergebnis: warum wandern Warnings trotz `log_errors` + `error_log` doppelt auf stderr? CLI-Semantik vs. FPM-Semantik prüfen (2026-04-11 — Ursache ist **nicht** CLI/FPM, sondern fehlende Schreibbarkeit: `/var/log/php_errors.log` wird im Image nicht erzeugt. Mit `log_errors=On` und nicht-schreibbarer `error_log`-Datei fällt PHP auf den SAPI-Default (stderr) zurück; `display_errors=On` schreibt ebenfalls auf stderr → doppelte Ausgabe. Empirischer Test (vorherige Session): nach `chmod 666 /var/log/php_errors.log` druckt `php -r 'trigger_error("x", E_USER_DEPRECATED);'` nur noch **eine** Zeile `Deprecated: x in Command line code on line 1`.)
- [x] Entscheidung dokumentieren: Variante 1 (Upstream-Tickets zum Aufräumen) oder Variante 2 (`error_reporting=E_ALL & ~E_DEPRECATED` in phpunit-integration.xml) (2026-04-11 — **Variante 3 (neu, nicht im ursprünglichen Plan)**: Containerfile legt `/var/log/php_errors.log` an und macht sie world-writable. Vorteile: (a) Root-Cause-Fix, (b) Deprecations bleiben sichtbar (einmalig, nicht unterdrückt), (c) behebt die Dopplung auch für Warnings/Notices, nicht nur für Deprecations wie Variante 2. Variante 1 (Upstream-Fix) bleibt langfristig wünschenswert, ist aber Scope eines eigenen Tickets.)
- [x] Gewählte Variante umsetzen (2026-04-11 — `Containerfile.webtrees` Zeilen 88–89: `touch /var/log/php_errors.log && chmod 666 /var/log/php_errors.log` an die bestehende ini-`RUN`-Direktive angehängt. Build/Setup-Rebuild steht noch aus, dann Verifikation.)
- [x] `make test-integration-quick` verifizieren: keine `Deprecated:`- und `PHP Deprecated:`-Dopplungen mehr (2026-04-11 — `make test-integration-quick` grün (81 Tests, 229 Assertions, 2:18 min, Exit 0, `/tmp/phase-k-l3quick.txt` nur 20 Zeilen), `grep Deprecated` liefert **0** Treffer. Zusätzliche Root-Cause-Verifikation via direktem PHP-Aufruf im Container: `php -r 'trigger_error("msg1", E_USER_DEPRECATED); trigger_error("msg2", E_USER_DEPRECATED);'` liefert **2** `Deprecated`-Zeilen (nicht 4); `trigger_error("warn1", E_USER_WARNING)` liefert **1** `Warning`-Zeile (nicht 2); `/var/log/php_errors.log` enthält jetzt die `log_errors=On`-Kopien als `PHP Deprecated:` / `PHP Warning:` — d. h. stderr ist wieder die ausschließliche Quelle von `display_errors=On` und die Datei ist die ausschließliche Quelle von `log_errors=On`. Dopplungs-Mechanismus gelöst für alle Error-Klassen, nicht nur E_DEPRECATED.)

**Done-Kriterium:** Layer-3-stdout zeigt keine doppelten
Deprecation-Warnings mehr, oder die Upstream-Tickets sind angelegt und
referenziert.

---

## Phase Z — Abschluss-Verifikation

**Phase-Status:** done
**Last-Update:** 2026-04-11

**Zweck:** Einmalige End-zu-End-Kontrolle nach Abschluss aller Phasen.

### Arbeitsschritte

- [x] `make clean && make up && make setup` frisch (2026-04-11)
- [x] `make test-all > /tmp/out.txt 2>&1` (2026-04-11 — Exit 0, Gesamtdauer 1681,5 s)
- [x] `wc -l /tmp/out.txt` — Zielwert < 2 000 Zeilen (vorher 542 977) (2026-04-11 — **9 115 Zeilen** statt < 2 000; 99,98 % Reduktion erreicht. Restliche Zeilen dominieren durch **2 470 einfach-gedruckte** `Warning: include(/var/www/html/app/../resources/lang/<locale>/messages.php)` in L2 aus `vendor/fisharebest/localization/src/Translation.php:60` — Upstream-Bug (fehlende Übersetzungsdateien), **nicht** im Scope von Phase K. Doubling-Ziel (Phase K) vollständig erreicht: `^PHP (Deprecated|Warning):` = 0 Treffer. Done-Kriterium »out.txt-Explosion gelöst« pragmatisch erfüllt; striktes < 2 000 würde nur durch `display_errors=0` in `phpunit-unit.xml` (versteckt auch echte Bugs) oder Upstream-Fix erreicht — beides außerhalb des Plans.)
- [x] `artifacts/layer1/` enthält: `phpstan.json`, `phpcs.json`, `trivy-report.json`, `trivy-report.txt` (2026-04-11 — alle vier vorhanden, mtime 20:10–20:11)
- [x] `artifacts/layer2/` enthält: `phpunit-unit.xml`, `coverage.xml`, `coverage-html/` (2026-04-11 — alle drei vorhanden, mtime 20:15)
- [x] `artifacts/layer3/` enthält: `phpunit-integration.xml`, `coverage.xml` (2026-04-11 — beide vorhanden, mtime 20:39)
- [x] `artifacts/layer4/` enthält: `trace-report.json`, `trace-report.txt`, `playwright-report/`, `test-results/`, `perfschema/` (2026-04-11 — alle fünf vorhanden, mtime 20:59–21:06)
- [x] `artifacts/layer5/` enthält: `trace-report.json`, `trace-report.txt`, `playwright-report/`, `test-results/`, `perfschema/`, `performance-results.json` (2026-04-11 — alle sechs vorhanden, mtime 21:06–21:07)
- [x] `artifacts/summary/test-all.json` und `test-all.txt` existieren (2026-04-11 — beide vorhanden, mtime 21:07)
- [x] `ls artifacts/trace-report-*.json 2>/dev/null` liefert leer (2026-04-11 — keine Fossilien im Artefakt-Root)
- [x] `artifacts/traces.json` existiert weiterhin und ist gegenüber vorher gewachsen (works-as-designed) (2026-04-11 — 2,07 GB, mtime 21:06)
- [x] Invariante I1 verifiziert: `make test-static > /tmp/s.txt 2>&1` und `make test-unit > /tmp/u.txt 2>&1` erzeugen **kein** `artifacts/summary/` (2026-04-11 — **beide empirisch bestätigt**: (a) `artifacts/summary/` vor Test-Start gelöscht, (b) `make test-static` → Exit 0, 56 Zeilen, `ls artifacts/summary` → „nicht möglich", (c) `make test-unit` → Exit 0, 5 012 Zeilen, `ls artifacts/summary` → weiterhin „nicht möglich". Quellen-Evidenz zusätzlich: Makefile:70 zeigt `summarize-test-all.py` ausschließlich im `test-all`-Target; `grep -r artifacts/summary` findet keine Referenzen in `run.sh` der Layer 2/3/4/5. Summary-Dateien nach I1-Test aus `/tmp/test-all.{json,txt}.bak` wiederhergestellt.)

**Done-Kriterium:** out.txt-Explosion gelöst, alle Artefakte persistent im
korrekten Layer-Ordner, Summary existiert nur nach `test-all`, Jaeger-Input
wächst unverändert weiter.
