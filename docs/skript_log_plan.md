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
| 0 | Vorarbeit: Cruft-Cleanup (R5) | not-started | — |
| B | tee-Entfernung in run.sh | not-started | — |
| C | trace-report.py entkoppeln | not-started | — |
| E | Layer-spezifische Trace-Report-Pfade | not-started | — |
| A-Mess | Bind-Mount-Performance-Baseline | not-started | — |
| A | compose.yaml Bind-Mount oder Fallback | not-started | — |
| F | Layer 4/5 run.sh konsequent (F-1) | not-started | — |
| G | test-all-Aggregat (summarize-test-all.py) | not-started | — |
| H | ANSI-Codes zähmen | not-started | — |
| I | PHPStan/PHPCS nach Phase A verifizieren | not-started | — |
| K | Layer-3-Deprecation-Doppelmeldungen (R4) | not-started | — |
| Z | Abschluss-Verifikation | not-started | — |

---

## Phase 0 — Vorarbeit: Cruft-Cleanup (R5)

**Phase-Status:** not-started
**Last-Update:** —

Alte `trace-report-*.json`-Fossilien aus `artifacts/`-Root löschen, ohne Commit.
Sie werden nach Phase E nicht mehr nachwachsen.

### Arbeitsschritte

- [ ] Bestand sichten: `ls -lh artifacts/trace-report-*.json` (—)
- [ ] Dateien löschen: `rm artifacts/trace-report-*.json` (—)
- [ ] Verifizieren, dass keine `trace-report-*.json` mehr im Artefakt-Root liegt (—)

**Done-Kriterium:** Kein `trace-report-*.json` mehr im Artefakt-Root.

---

## Phase B — tee-Entfernung in run.sh

**Phase-Status:** not-started
**Last-Update:** —

**Scope:** Nur `layer2-unit/run.sh` und `layer3-integration/run.sh`.
`layer5-performance/run.sh` bleibt vorerst außen vor — es ist aktuell toter
Code und wird erst in Phase F zusammen mit dem neuen `layer4-e2e/run.sh`
reaktiviert (dann ohne `tee`).

**Designentscheidung:** `phpunit-output.log` wird komplett weggelassen. Die
JUnit-XML deckt den Persistenz-Bedarf ab. Wer die Laufzeit-Reihenfolge
braucht, kann sie aus dem JUnit rekonstruieren oder den Testlauf
reproduzieren.

### Arbeitsschritte

- [ ] `layer2-unit/run.sh`: `| tee "${ARTIFACTS}/phpunit-output.log"` entfernen (—)
- [ ] `layer2-unit/run.sh`: `EXIT_CODE=${PIPESTATUS[0]}` auf `EXIT_CODE=$?` umstellen (—)
- [ ] `layer3-integration/run.sh`: gleiche Änderung am PHPUnit-Aufruf (—)
- [ ] `layer3-integration/run.sh`: `PIPESTATUS` → `$?` (—)
- [ ] In beiden Scripten prüfen, ob noch andere Stellen auf `phpunit-output.log` referenzieren (mkdir, Echo, tail) — bei Bedarf entfernen (—)
- [ ] `make test-unit` laufen lassen, Exit 0, stdout zeigt PHPUnit-Progress ohne Duplikate (—)
- [ ] `make test-integration-quick` laufen lassen, Exit 0, stdout zeigt PHPUnit-Progress ohne Duplikate (—)

**Done-Kriterium:** Kein `tee` mehr in `layer2-unit/run.sh` und
`layer3-integration/run.sh`; beide Testläufe grün; stdout-Duplikate
verschwunden.

---

## Phase C — trace-report.py entkoppeln

**Phase-Status:** not-started
**Last-Update:** —

**Designentscheidungen:**
- Neuer Default-Modus `summary` (nur ~200 Zeilen auf stdout).
- `trace-report.txt` wird als menschenlesbare Datei neben `trace-report.json`
  beibehalten (designte Redundanz: TXT zum Grepping, JSON für Maschinen).
- `print_perfschema` verschwindet komplett von stdout und landet im TXT-Report.
- Die Pfad-Umstellung auf `artifacts/layer<N>/` passiert in Phase E; hier
  werden nur die neuen CLI-Flags eingebaut.

### Arbeitsschritte

- [ ] `scripts/trace-report.py`: argparse um `--output-text <path>` erweitern (—)
- [ ] `scripts/trace-report.py`: argparse um `--stdout-mode {summary,full,silent}`, Default `summary`, erweitern (—)
- [ ] `print_span_tree` so umbauen, dass ein optionales File-Handle-Argument die Ausgabe steuern kann (`print(..., file=fh)`) (—)
- [ ] `print_perfschema` analog umbauen (File-Handle statt fix stdout) (—)
- [ ] `main()`: stdout-Block auf Summary begrenzen (Testlauf-ID, Span-Count, Root-Spans, Testcase-Liste) (—)
- [ ] `main()`: bei `--output-text` Span-Baum + PerfSchema in TXT-File schreiben (—)
- [ ] `main()`: `--stdout-mode full` reaktiviert das alte Verhalten (Debug); `silent` unterdrückt auch die Summary (—)
- [ ] Trockenlauf lokal: `python3 scripts/trace-report.py --run-id <existierende-uuid> --traces-file artifacts/traces.json --output-json /tmp/tr.json --output-text /tmp/tr.txt --stdout-mode summary` — stdout ≤ 250 Zeilen, /tmp/tr.txt enthält Span-Baum + PerfSchema (—)
- [ ] `scripts/trace-report.sh` (Wrapper) inspizieren; falls er die neuen Flags nicht durchreicht, anpassen (—)

**Done-Kriterium:** `trace-report.py` druckt per Default nur Summary; TXT+JSON
enthalten die Details vollständig; der Shell-Wrapper reicht alle neuen Flags
durch.

---

## Phase E — Layer-spezifische Trace-Report-Pfade

**Phase-Status:** not-started
**Last-Update:** —

**Designentscheidungen:**
- Dateinamen ohne UUID-Suffix: `trace-report.json`, `trace-report.txt`.
- Eine Datei pro Layer, vom letzten Lauf überschrieben.
- `artifacts/traces.json` bleibt im Root unverändert (works-as-designed,
  §4.5 der Analyse).

### Arbeitsschritte

- [ ] `Makefile` Target `test-e2e`: `--output-json` auf `artifacts/layer4/trace-report.json` umstellen (—)
- [ ] `Makefile` Target `test-e2e`: `--output-text artifacts/layer4/trace-report.txt` ergänzen (—)
- [ ] `Makefile` Target `test-e2e`: vor dem Aufruf `mkdir -p artifacts/layer4` sicherstellen (—)
- [ ] `Makefile` Target `test-e2e-quick`: dieselben Pfade wie `test-e2e` (—)
- [ ] `Makefile` Target `test-performance`: `--output-json` auf `artifacts/layer5/trace-report.json`, `--output-text artifacts/layer5/trace-report.txt`, `mkdir -p artifacts/layer5` (—)
- [ ] `make test-e2e-quick` laufen lassen; `artifacts/layer4/trace-report.json` und `artifacts/layer4/trace-report.txt` existieren (—)
- [ ] `make test-performance` laufen lassen; `artifacts/layer5/trace-report.json` und `artifacts/layer5/trace-report.txt` existieren (—)
- [ ] Prüfen: kein neuer `artifacts/trace-report-*.json` im Root entstanden (—)
- [ ] Prüfen: stdout-Ausgabe der beiden Läufe ist kurz (Summary statt Baum) (—)

**Done-Kriterium:** Alle Trace-Reports landen im Layer-Ordner, kein
UUID-Suffix, kein Report im Artefakt-Root, out.txt aus `test-e2e` ist auf
wenige hundert Zeilen geschrumpft.

---

## Phase A-Mess — Bind-Mount-Performance-Baseline

**Phase-Status:** not-started
**Last-Update:** —

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

- [ ] Startzustand verifizieren: `compose.yaml` hat **keinen** `./artifacts:/artifacts:rw,z`-Mount im `webtrees`-Service (—)
- [ ] `make clean && make up && make setup` frisch aufsetzen (—)
- [ ] `time make test-unit` — Lauf 1, Zeit notieren (—)
- [ ] `time make test-unit` — Lauf 2, Zeit notieren (—)
- [ ] `time make test-unit` — Lauf 3, Zeit notieren (—)
- [ ] `time make test-integration-quick` — Lauf 1, Zeit notieren (—)
- [ ] `time make test-integration-quick` — Lauf 2, Zeit notieren (—)
- [ ] `time make test-integration-quick` — Lauf 3, Zeit notieren (—)
- [ ] Baseline-Medianwerte in `docs/phase_a_bench_<datum>.md` festhalten (—)

### Arbeitsschritte — Vergleichslauf (mit Bind-Mount)

- [ ] `compose.yaml` Service `webtrees` Volumes-Liste um `- ./artifacts:/artifacts:rw,z` erweitern (provisorisch — bei Rot-Ergebnis rückgängig machen) (—)
- [ ] `make clean && make up && make setup` (—)
- [ ] `time make test-unit` × 3, Zeiten notieren (—)
- [ ] `time make test-integration-quick` × 3, Zeiten notieren (—)
- [ ] Vergleichs-Medianwerte in `docs/phase_a_bench_<datum>.md` festhalten (—)

### Arbeitsschritte — Auswertung

- [ ] Abweichung pro Layer berechnen: `(vergleich − baseline) / baseline × 100` (—)
- [ ] Schwellen anwenden: Grün / Gelb / Rot pro Layer (—)
- [ ] Gesamtentscheidung treffen: wenn **alle** gemessenen Layer grün → Pfad A-1; sonst → Pfad A-2 (—)
- [ ] Entscheidung in `docs/phase_a_bench_<datum>.md` verankern (Ampel, Zahlen, gewählter Pfad) (—)

**Done-Kriterium:** Bench-Dokument liegt vor, Ampel-Ergebnis steht, Entscheidung
für Phase A (Pfad A-1 oder A-2) ist getroffen und im Dokument begründet.

---

## Phase A — compose.yaml Bind-Mount oder Fallback

**Phase-Status:** not-started
**Last-Update:** —

**Abhängigkeit:** Phase A-Mess abgeschlossen. Einer der beiden Pfade wird
abgearbeitet, der andere bleibt unangetastet.

### Pfad A-1 — Grün: Bind-Mount committen

- [ ] `compose.yaml` Service `webtrees`: `- ./artifacts:/artifacts:rw,z` vor `./artifacts/security-trace`-Zeile einfügen, falls nicht bereits aus A-Mess vorhanden (—)
- [ ] Entscheiden, ob der explizite `./artifacts/security-trace`-Eintrag belassen wird (Klarheit) oder entfernt (redundant) — Entscheidung im Bench-Dokument notieren (—)
- [ ] `make down && make up && make setup` frisch (—)
- [ ] `make test-static`: `artifacts/layer1/phpstan.json` und `phpcs.json` direkt auf Host sichtbar (—)
- [ ] `make test-unit`: `artifacts/layer2/phpunit-unit.xml` und `coverage-html/` direkt auf Host sichtbar (—)
- [ ] `make test-integration-quick`: `artifacts/layer3/phpunit-integration.xml` direkt auf Host sichtbar (—)
- [ ] `Makefile` Target `test-unit`: `podman cp webtrees:/artifacts/layer2/coverage.xml …` entfernen (jetzt redundant) (—)
- [ ] `Makefile` Target `test-integration`: `podman cp webtrees:/coverage/layer3-coverage.xml …` prüfen — L3 schreibt weiter ins Named Volume `coverage-data:/coverage`; der `podman cp` bleibt dort, solange L3 nicht auf `/artifacts/layer3/coverage.xml` umgestellt wird (Entscheidung notieren) (—)
- [ ] Prüfen, dass keine anderen `podman cp`-Aufrufe verwaist sind (—)

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

**Phase-Status:** not-started
**Last-Update:** —

**Designentscheidungen:**
- run.sh-Modell konsequent durchziehen.
- `PerfSchema-Extract` und `trace-report` bleiben **im Makefile** (Host-Scripts;
  sie laufen nicht im Container).
- Konsistente Phasen-Header/End-Marker in allen 5 Layern.

### Arbeitsschritte

- [ ] `layer4-e2e/run.sh` neu anlegen: Phasen-Header `=== Teststufe 3 — Systemtest ===`, Playwright-Aufruf, Exit-Code-Capture, End-Marker (—)
- [ ] `layer4-e2e/run.sh`: `TEST_RUN_ID` und weitere Env-Variablen aus dem Container-Env übernehmen (kein Neu-Setzen) (—)
- [ ] `layer5-performance/run.sh` reaktivieren: bestehende Datei prüfen, `tee` entfernen, Phasen-Header setzen, Exit-Code-Capture über `$?` (—)
- [ ] `compose.yaml` prüfen: `playwright`-Container sieht `/tests/e2e/run.sh` und `/tests/performance/run.sh` (aktuelle Mounts `./layer4-e2e:/tests/e2e:ro,z` und `./layer5-performance:/tests/performance:ro,z` passen) (—)
- [ ] `Makefile` Target `test-e2e`: Playwright-Aufruf durch `$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright /bin/bash /tests/e2e/run.sh` ersetzen (—)
- [ ] `Makefile` Target `test-e2e-quick`: analog — Filter/Spec-Files an run.sh als Parameter übergeben oder als Env-Variable (Design-Entscheidung dokumentieren) (—)
- [ ] `Makefile` Target `test-performance`: analog für `/tests/performance/run.sh` (—)
- [ ] Makefile-Phasen-Marker `@echo "=== Layer 4 ==="` / `=== Layer 5 ===` ergänzen, falls nicht schon im run.sh (—)
- [ ] `make test-e2e-quick` grün, stdout hat saubere Phase-Klammer (—)
- [ ] `make test-performance` grün, stdout hat saubere Phase-Klammer (—)

**Done-Kriterium:** Alle 5 Layer haben ein `run.sh` mit konsistentem Phasen-
Header und End-Marker. Makefile-Targets rufen immer `run.sh` auf; PerfSchema-
Extract und Trace-Report laufen weiterhin Host-seitig nach dem Container-Aufruf.

---

## Phase G — test-all-Aggregat (summarize-test-all.py)

**Phase-Status:** not-started
**Last-Update:** —

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

- [ ] `scripts/summarize-test-all.py` anlegen mit SPDX-Header + argparse + Main-Skelett (Eingabe: `artifacts/`) (—)
- [ ] Layer 1: `artifacts/layer1/phpstan.json` parsen, Error-Count extrahieren (—)
- [ ] Layer 1: `artifacts/layer1/phpcs.json` parsen, Error-Count extrahieren (—)
- [ ] Layer 1: `artifacts/layer1/trivy-report.json` parsen, Findings-Count extrahieren (—)
- [ ] Layer 2: `artifacts/layer2/phpunit-unit.xml` (JUnit) parsen — `tests`, `assertions`, `failures` aus dem Root-Element (—)
- [ ] Layer 2: `artifacts/layer2/coverage.xml` (Clover) parsen — Coverage-% berechnen (covered / total lines) (—)
- [ ] Layer 3: analog zu Layer 2 (phpunit-integration.xml + coverage.xml) (—)
- [ ] Layer 4: Playwright-JUnit bzw. `playwright-report/results.json` (je nach Reporter-Setup) parsen (—)
- [ ] Layer 5: Playwright-JUnit + `performance-results.json` parsen; `p95_ms` aus vorhandenen Metriken ableiten (—)
- [ ] Gesamt-`duration_seconds` berechnen (aus JUnit-Timestamps oder einfacher Summe) (—)
- [ ] `artifacts/summary/test-all.json` schreiben (mkdir -p vorher) (—)
- [ ] `artifacts/summary/test-all.txt` schreiben (menschenlesbare Variante, tabellarisch) (—)
- [ ] Kurz-Zusammenfassung auf stdout drucken (designte Redundanz, <20 Zeilen) (—)
- [ ] `Makefile` Target `test-all`: am Ende `@python3 scripts/summarize-test-all.py` als Nachbearbeitungs-Schritt anfügen (—)
- [ ] Volltest: `make test-all` komplett — `artifacts/summary/test-all.json` und `.txt` werden erzeugt (—)
- [ ] Invarianten-Test: `make test-unit` allein — erzeugt **kein** Summary-Artefakt (I1) (—)
- [ ] Invarianten-Test: `make test-integration-quick` allein — dito (—)

**Done-Kriterium:** `make test-all` produziert `artifacts/summary/test-all.{json,txt}`
plus stdout-Zusammenfassung; Einzel-Layer-Targets bleiben byte-identisch zu
vorher und erzeugen kein Summary.

---

## Phase H — ANSI-Codes zähmen

**Phase-Status:** not-started
**Last-Update:** —

**Designentscheidungen:**
- Playwright: **beides** — `FORCE_COLOR=0` in `compose.yaml` und Reporter
  `list` → `line` in beiden `playwright.config.ts`.
- Composer: `--no-ansi` in `setup-webtrees.sh`.

### Arbeitsschritte

- [ ] `compose.yaml` Service `playwright`: `FORCE_COLOR: "0"` in Environment ergänzen (—)
- [ ] `layer4-e2e/playwright.config.ts`: Reporter von `['list']` auf `['line']` ändern (—)
- [ ] `layer5-performance/playwright.config.ts`: Reporter auf `['line']` ändern (falls heute auf `list`) (—)
- [ ] `scripts/setup-webtrees.sh`: Composer-Aufrufe um `--no-ansi` erweitern (Zeilen 45 und 59 der Analyse) (—)
- [ ] `make down && make up && make setup > /tmp/setup.txt 2>&1` — in `/tmp/setup.txt` nach ANSI-Escapes (`\x1b\[`) suchen, Treffer minimiert (—)
- [ ] `make test-e2e-quick > /tmp/e2e.txt 2>&1` — analog prüfen (—)

**Done-Kriterium:** stdout-Redirection produziert im Idealfall keine
Cursor-Manipulations-Sequenzen mehr und drastisch weniger Farb-Codes.

---

## Phase I — PHPStan/PHPCS nach Phase A verifizieren

**Phase-Status:** not-started
**Last-Update:** —

**Zweck:** Bestätigung, dass die Layer-1-Artefakte nach Phase A (oder
Fallback) auf dem Host landen und die bestehende stdout-Summary-Zeile
weiterhin korrekt einen Count aus der jeweiligen JSON zieht.

### Arbeitsschritte

- [ ] `make test-static` laufen lassen (—)
- [ ] `artifacts/layer1/phpstan.json` existiert und ist nicht leer (—)
- [ ] `artifacts/layer1/phpcs.json` existiert und ist nicht leer (—)
- [ ] stdout enthält eine klare PHPStan-Status-Zeile (Fehlerzahl oder "OK") (—)
- [ ] stdout enthält eine klare PHPCS-Status-Zeile (—)
- [ ] `layer1-static/run.sh` Zeilen ~26-29 und ~41-46 prüfen: Count wird aus der Datei gezogen, keine Code-Änderung nötig (—)

**Done-Kriterium:** Layer-1-Artefakte sind persistent, stdout-Summary ist
unverändert vorhanden.

---

## Phase K — Layer-3-Deprecation-Doppelmeldungen (R4)

**Phase-Status:** not-started
**Last-Update:** —

**Zweck:** Separate Root-Cause aus §4.3 der Analyse. PHP-CLI mit
`display_errors=On` **und** `log_errors=On` druckt Deprecation-Warnings
zweimal auf stderr. Diese Phase ist ein Polish-Schritt und kann auch
später separat gezogen werden.

### Arbeitsschritte

- [ ] `Containerfile.webtrees` prüfen: aktuelle `php.ini`-Werte für `display_errors`, `log_errors`, `error_log`, `error_reporting` (—)
- [ ] `layer3-integration/phpunit-integration.xml` prüfen: `<php><ini name="error_reporting" value="…"/></php>`-Block vorhanden? (—)
- [ ] `layer2-unit/phpunit-unit.xml` zum Vergleich: wie werden dort Deprecations unterdrückt? (—)
- [ ] Analyse-Ergebnis: warum wandern Warnings trotz `log_errors` + `error_log` doppelt auf stderr? CLI-Semantik vs. FPM-Semantik prüfen (—)
- [ ] Entscheidung dokumentieren: Variante 1 (Upstream-Tickets zum Aufräumen) oder Variante 2 (`error_reporting=E_ALL & ~E_DEPRECATED` in phpunit-integration.xml) (—)
- [ ] Gewählte Variante umsetzen (—)
- [ ] `make test-integration-quick` verifizieren: keine `Deprecated:`- und `PHP Deprecated:`-Dopplungen mehr (—)

**Done-Kriterium:** Layer-3-stdout zeigt keine doppelten
Deprecation-Warnings mehr, oder die Upstream-Tickets sind angelegt und
referenziert.

---

## Phase Z — Abschluss-Verifikation

**Phase-Status:** not-started
**Last-Update:** —

**Zweck:** Einmalige End-zu-End-Kontrolle nach Abschluss aller Phasen.

### Arbeitsschritte

- [ ] `make clean && make up && make setup` frisch (—)
- [ ] `make test-all > /tmp/out.txt 2>&1` (—)
- [ ] `wc -l /tmp/out.txt` — Zielwert < 2 000 Zeilen (vorher 542 977) (—)
- [ ] `artifacts/layer1/` enthält: `phpstan.json`, `phpcs.json`, `trivy-report.json`, `trivy-report.txt` (—)
- [ ] `artifacts/layer2/` enthält: `phpunit-unit.xml`, `coverage.xml`, `coverage-html/` (—)
- [ ] `artifacts/layer3/` enthält: `phpunit-integration.xml`, `coverage.xml` (—)
- [ ] `artifacts/layer4/` enthält: `trace-report.json`, `trace-report.txt`, `playwright-report/`, `test-results/`, `perfschema/` (—)
- [ ] `artifacts/layer5/` enthält: `trace-report.json`, `trace-report.txt`, `playwright-report/`, `test-results/`, `perfschema/`, `performance-results.json` (—)
- [ ] `artifacts/summary/test-all.json` und `test-all.txt` existieren (—)
- [ ] `ls artifacts/trace-report-*.json 2>/dev/null` liefert leer (—)
- [ ] `artifacts/traces.json` existiert weiterhin und ist gegenüber vorher gewachsen (works-as-designed) (—)
- [ ] Invariante I1 verifiziert: `make test-static > /tmp/s.txt 2>&1` und `make test-unit > /tmp/u.txt 2>&1` erzeugen **kein** `artifacts/summary/` (—)

**Done-Kriterium:** out.txt-Explosion gelöst, alle Artefakte persistent im
korrekten Layer-Ordner, Summary existiert nur nach `test-all`, Jaeger-Input
wächst unverändert weiter.
