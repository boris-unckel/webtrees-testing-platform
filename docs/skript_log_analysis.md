<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Skript-Log-Analyse — out.txt / artifacts/ / Teststeuerung

**Stand:** 2026-04-11
**Auslöser:** `make test-all > ./out.txt 2>&1` erzeugt eine 100-MB-Datei mit
542 977 Zeilen, darin Trace-Span-Bäume, Performance-Schema-Summaries und
PHPUnit-Klartext-Output, die **alle** in Artefakten persistiert sein sollten.

Dieses Dokument ist **Analyse**, noch nicht Plan. Die konkrete Umsetzung wird
in einem Folgeschritt aus Abschnitt 5 abgeleitet.

---

## 1 — Zielbild

Die Teststeuerung erfüllt das Zielbild, wenn folgende Invarianten gelten:

**I1 — `make test-all` ist ein reiner Aggregator.**
Der Output (stdout+stderr *und* Dateien) eines Einzelaufrufs `make test-<layer>`
ist byte-identisch zu dem Output, den derselbe Layer innerhalb von
`make test-all` erzeugt. Einzige Ausnahme: `make test-all` darf zusätzlich ein
*layer-übergreifendes* Aggregat-Artefakt erzeugen (Summary über alle 5 Layer,
Coverage-Vergleich L2↔L3, …). Dieses Aggregat gehört dann in einen eigenen
Scope (`artifacts/summary/` o. ä.), nicht in einen Layer-Ordner.

**I2 — stdout/stderr ist *Verlauf*, nicht Ergebnis.**
Auf stdout gehören: Container-Bringup-Noise, Setup-Fortschritt,
Phasen-Marker (`=== Layer X ===`), Fortschrittszähler (PHPUnit-Dots,
Playwright-list), Abbruch- und Infrastruktur-Fehler. Testergebnisse (Traces,
SQL-Digests, Perf-Werte, Coverage-Details, JUnit-Detailzeilen,
Span-Hierarchien) gehören **ausschließlich** in Artefakt-Dateien.

**I3 — Artefakte sind layerspezifisch.**
Output einer Layer-Stufe liegt unter `artifacts/layer<N>/`, nicht im
Artifacts-Root. Der Root-Ordner ist reserviert für übergreifendes
Aggregat-Material **und** für *shared* Rohdaten, die bewusst
layer-übergreifend sind und von externen Tools konsumiert werden — konkret
`artifacts/traces.json` als Jaeger-Input-Buffer (§4.5). Auswertungsskripte
hingegen müssen strikt (a) nur Rohdaten für *ihren eigenen* Layer
berücksichtigen und (b) ihre Ausgaben ausschließlich in den zugehörigen
Layer-Ordner schreiben. „Shared Input, layer-lokale Ableitung" ist das
Muster.

**I4 — Persistenz ist vollständig.**
Wenn ein Skript in eine Datei schreibt, erreicht diese Datei den Host. Ein
Artefakt, das nur im Container existiert, gilt als nicht persistiert.

**I5 — Redundanzen sind designt oder nicht vorhanden.**
Legitim sind: PHPUnit-Summary-Zeile auf stdout + strukturierte JUnit-XML im
Artefakt; Layer-übergreifende Summary sowohl auf stdout als auch als
Aggregat-Datei; Fehler-Stack-Traces bei Testabbruch sowohl auf stdout als
auch im Artefakt. *Nicht* legitim: vollständiges PHPUnit-Klartext-Log
gleichzeitig via `tee` auf stdout und in eine Datei, die nie den Host
erreicht.

**I6 — Meta-Output bleibt laut.**
Container-Build-Noise (`podman-compose up --build`), Setup-Wizard-Ablauf,
OTel-Collector-Boot bleiben auf stdout. Ziel ist nicht Stille, sondern
richtige Persistenz.

---

## 2 — Ist-Zustand: out.txt-Anatomie

Strukturkarte von `out.txt` (542 977 Zeilen, ~100 MB), aus einem
vollständigen `make test-all`-Lauf am 2026-04-11:

| Zeilen | Phase | Umfang | Bewertung |
|---|---|---|---|
| 1–5 | `clone-upstream.sh` + `generate-passwords.sh` | 5 Zeilen | Meta, OK |
| 6–119 | `podman-compose up -d --build` | 114 Zeilen (cached) | Meta, OK |
| 120–175 | `setup-webtrees.sh` inkl. Composer-OTel-Install | 56 Zeilen | Meta, OK (ANSI-Codes auffällig, s. §4.4) |
| 176–182 | Layer 1: `run.sh` PHPStan+PHPCS | 7 Zeilen | OK |
| 183–229 | Layer 1: Trivy (2 Aufrufe) | 47 Zeilen | OK |
| 230–7811 | **Layer 2: PHPUnit via `tee`** | **7582 Zeilen** | **Redundant** — vollständiges PHPUnit-Textlog zusätzlich auf stdout |
| 7812–7813 | `podman cp` für `coverage.xml` | 2 Zeilen | OK |
| 7814–7865 | **Layer 3: PHPUnit via `tee`** | **52 Zeilen** | Kompakt, aber: doppelte Deprecation-Warnings (s. §4.3) |
| 7866–7867 | `Testlauf: <uuid>` | 2 Zeilen | Meta, OK |
| 7868–8058 | Layer 4: Playwright-list-Reporter | 191 Zeilen | **Stilistisch schlecht** — ANSI-Escape-Codes `[1A[2K[0G…` wörtlich (s. §4.4) |
| 8059 | `extract-perfschema.sh` Summary-Zeile | 1 Zeile | OK |
| **8060–531469** | **Layer 4: `trace-report.py` Span-Baum** | **523 410 Zeilen** | **Hauptproblem** — 100 % redundant zu `trace-report-*.json` |
| 531470 | `Testlauf: <uuid>` | 1 Zeile | Meta, OK |
| 531471–531492 | Layer 5: Playwright-list | 22 Zeilen | Stilistisch schlecht (ANSI) |
| 531493 | `extract-perfschema.sh` Summary | 1 Zeile | OK |
| **531494–542976** | **Layer 5: `trace-report.py` Span-Baum** | **11 483 Zeilen** | Gleiches Problem wie Layer 4 |

**Zusammenfassung**:
- 99 % der out.txt-Masse stammt aus **zwei** Quellen:
  1. `trace-report.py` rekursive Span-Ausgabe (L4+L5): ≈ 534 893 Zeilen
  2. `tee` in `layer2-unit/run.sh`: ≈ 7 580 Zeilen
- Eigentlicher Testverlauf (Meta + Phasen-Marker + Fortschritt) wäre **unter
  1 000 Zeilen** — alles andere ist falsch platzierter Ergebnis-Output.

---

## 3 — Ist-Zustand: Artefakt-Persistenz-Matrix

Kernentdeckung: **Im `webtrees`-Container werden Artefakte erzeugt, die nie
auf den Host kommen.** Begründung: `compose.yaml` mountet für den
webtrees-Container **keinen** Bind-Mount auf `./artifacts`. Nur ein
schmales Sub-Ziel (`./artifacts/security-trace:/artifacts/security-trace`)
existiert. Der Playwright-Container *hat* `./artifacts:/artifacts:rw,z` (Z. 107).

### 3.1 — Matrix: Artefakt erzeugt vs. Artefakt auf Host

| Datei | Erzeugt von | Im Container | Auf Host | Mechanismus |
|---|---|---|---|---|
| `layer1/trivy-report.json` | Makefile `test-static` → `podman run trivy` | — | ✅ | Host-Bind-Mount in Trivy-Container |
| `layer1/trivy-report.txt` | dito | — | ✅ | Host-Bind-Mount |
| `layer1/phpstan.json` | `layer1-static/run.sh` | ✅ (62 B) | ❌ | — keiner |
| `layer1/phpcs.json` | `layer1-static/run.sh` | ✅ (524 KB) | ❌ | — keiner |
| `layer2/phpunit-unit.xml` (JUnit) | `layer2-unit/run.sh` (`--log-junit`) | ✅ (2.4 MB) | ❌ | — keiner |
| `layer2/coverage.xml` (Clover) | `layer2-unit/run.sh` (`--coverage-clover`) | ✅ (3.3 MB) | ✅ | `podman cp` im Makefile |
| `layer2/coverage-html/` | `layer2-unit/run.sh` (phpunit.xml-config?) | ✅ | ❌ | — keiner |
| `layer2/phpunit-output.log` | `layer2-unit/run.sh` (`tee`) | ✅ (1.1 MB) | ❌ | — keiner (und ohnehin redundant, s. §4.1) |
| `layer3/phpunit-integration.xml` (JUnit) | `layer3-integration/run.sh` (`--log-junit`) | ✅ (271 KB) | ❌ | — keiner |
| `layer3/coverage.xml` (Clover) | `layer3-integration/run.sh` (`--coverage-clover` → `/coverage`-Volume) | ✅ | ✅ | `podman cp` im Makefile (aus `coverage-data`-Volume) |
| `layer3/phpunit-output.log` | `layer3-integration/run.sh` (`tee`) | ✅ (3.8 KB) | ❌ | — keiner |
| `layer3/db-dump.sql` | `layer3-integration/run.sh` (conditional on failure) | ✅ | ❌ | — keiner |
| `layer3/php-errors.log` | dito | ✅ | ❌ | — keiner |
| `layer4/playwright-report/` | Playwright HTML-Reporter | — | ✅ | Playwright-Container hat Bind-Mount |
| `layer4/test-results/` | Playwright outputDir | — | ✅ | dito |
| `layer4/perfschema/` | `extract-perfschema.sh` | — | ✅ | Host-Script schreibt direkt |
| `layer5/playwright-report/` | Playwright | — | ✅ | dito |
| `layer5/test-results/` | Playwright | — | ✅ | dito |
| `layer5/performance-results.json` | Playwright JSON-Reporter | — | ✅ | dito |
| `layer5/perf-*.json` | Playwright-Tests selbst (via fixture) | — | ✅ | dito |
| `layer5/perfschema/` | `extract-perfschema.sh` | — | ✅ | dito |
| `traces.json` (Root!) | `otel-collector` (file exporter) | — | ✅ | Host-Bind-Mount am Collector |
| `trace-report-<uuid>.json` (Root!) | `scripts/trace-report.py` | — | ✅ | Host-Script schreibt direkt |

**Summe**:
- **Layer 1, 2, 3**: 8 relevante Artefakte (JUnit, Coverage-HTML, PHPUnit-Log,
  PHPStan, PHPCS, DB-Dump, PHP-Errors, phpunit-output) existieren **nur im
  Container** und sterben mit `podman rm`. Die Tests *tun so*, als würden
  sie persistieren — tatsächlich persistiert nur das, was das Makefile per
  `podman cp` einzeln abholt (zwei `coverage.xml`).
- **Layer 4, 5**: Persistenz funktioniert, weil der Playwright-Container
  `./artifacts:/artifacts:rw,z` hat. Aber: Output landet im Artefakt-Root
  statt im Layer-Ordner (`traces.json`, `trace-report-*.json`).

### 3.2 — Artefakt-Root statt Layer-Ordner

Aktueller Zustand `artifacts/`:

```
artifacts/
├── layer1/                           ← nur Trivy
├── layer2/                           ← nur coverage.xml
├── layer3/                           ← nur coverage.xml
├── layer4/                           ← komplett
├── layer5/                           ← komplett
├── security/                         ← Security-Test-Track (separat)
├── security-audit/                   ← Audit-Trail (git-tracked, separat)
├── security-trace/                   ← Whitebox-Audit (separat)
├── traces.json                       ← 2.77 GB, layer-übergreifend appendet
├── trace-report-066dde77-….json      ← 50 MB, gehört zu Layer 4/5
├── trace-report-3b3a3c22-….json      ← 5 MB
├── trace-report-4d8244d3-….json      ← 74 B (leer)
├── trace-report-6646c6e9-….json      ← 49 MB
├── trace-report-6d7710f4-….json      ← 211 KB
├── trace-report-7e60eaa2-….json      ← 227 MB
├── trace-report-beb3eacb-….json      ← 19 KB
└── trace-report-db2ad093-….json      ← 136 MB
```

**Ein Problem** (Anm.: `traces.json` ist **works-as-designed** als shared
Jaeger-Input-Buffer und fällt unter den qualifizierten Teil von I3 — s. §4.5):

- `trace-report-*.json` verletzen **I3** (Layer-Isolation). Diese Dateien
  sind *abgeleitete, layer-spezifische* Analysen eines einzelnen Testlaufs
  und gehören in den Layer-Ordner ihres Erzeugers (Layer 4 bzw. Layer 5).
  Namen mit UUID-Suffix widersprechen zusätzlich der Exklusiv-Regel („ein
  Lauf gleichzeitig") — ein stabiler Pfad `artifacts/layerN/trace-report.json`
  ist ausreichend.

---

## 4 — Root-Cause-Analyse: Warum landet was wo?

### 4.1 — `tee` in run.sh: Redundanz ohne Persistenz

Layer 2, Layer 3 und (totes) Layer 5 verwenden alle dasselbe Muster:

```bash
# layer2-unit/run.sh:34-38
su -s /bin/bash www-data -c "... vendor/bin/phpunit ..." \
    2>&1 | tee "${ARTIFACTS}/phpunit-output.log"
```

**Absicht**: PHPUnit-Output soll sowohl live auf stdout sichtbar sein (Fortschritt)
*als auch* in einer Log-Datei persistieren (Nachlesen, Post-mortem-Analyse).

**Warum das schief geht**:
1. `tee` dupliziert aktiv — der gesamte PHPUnit-Text wandert **zweimal** durch
   das Ausgabepipeline (Dateihandle + stdout). Bei Layer 2 sind das 7 580
   Zeilen, die in out.txt ankommen.
2. Die Log-Datei `${ARTIFACTS}/phpunit-output.log` wird im **webtrees-Container**
   erzeugt. `compose.yaml` hat dort **keinen** Bind-Mount auf `./artifacts`.
   Die Datei existiert nur bis `podman rm`. Das Makefile ruft `podman cp`
   nur für `coverage.xml` auf — die `.log`-Datei bleibt zurück.
3. **Konsequenz**: Das `tee` erfüllt seinen Persistenz-Zweck **nie**.
   Es erzeugt die Datei an einem unerreichbaren Ort *und* sprengt gleichzeitig
   stdout. Schlechtestmögliches Ergebnis beider Welten.

**Nebenbefund**: Die JUnit-XML-Datei (`--log-junit`) wird ebenfalls nach
`${ARTIFACTS}` geschrieben — sie hat exakt dasselbe Persistenz-Problem. Sie
enthält aber die strukturierten Ergebnisse, die man bei einem Test-Post-mortem
eigentlich braucht (Test-Namen, Zeit, Assertion-Texte bei Failures, Stacktraces).
**Die JUnit-XML ist die richtige Persistenz-Form; `phpunit-output.log` ist
redundant zu stdout + JUnit.**

### 4.2 — `trace-report.py`: Span-Baum auf stdout + JSON-Datei

Das Python-Script `scripts/trace-report.py` ist der Haupt-Treiber der
out.txt-Explosion (~535 000 Zeilen). Seine Output-Semantik:

```python
# scripts/trace-report.py
# ca. Z. 308-367 (Kurzform)
print(f"=== Testlauf: {run_id[:8]} ({now}) ===")
print(f"Gefundene Spans: {len(spans)}")
print(f"Playwright-Root-Spans: {len(playwright_spans)}")
for ps in playwright_spans:
    print(f"  test: {ps.attributes['test.case_id']}  trace_id=…")
print(f"Browser-Spans: {…}")
for case_id, case_spans in sorted(cases.items()):
    print(f"\nTestfall: {case_id}")
    for root in roots:
        print(f"  {layer}: {root.duration_ms}ms  [{root.name}]")
        for child in root.children:
            print_span_tree(child, indent=4)       # ← REKURSIV, jeder Span eine Zeile
# PerfSchema
print_perfschema(perfschema)                       # ← Top-10 SQL, Table I/O, Warnungen
# JSON
if args.output_json:
    with open(args.output_json, "w") as f:
        json.dump(generate_json_report(...), f, indent=2)
```

**Zwei unabhängige Probleme**:

**4.2 a — `print_span_tree` ist rekursiv und unbegrenzt.**
Bei dem protokollierten Layer-4-Lauf wurden **493 889 Spans** für 176
Playwright-Testfälle gesammelt (Z. 8061: `Gefundene Spans: 493889`). Jeder
PDO-Span ist eine Zeile, jeder PSR-15-Span eine Zeile, jeder
`resourceFetch`-Browser-Span eine Zeile. Die Rekursion ist unbegrenzt in
Tiefe und Breite. Ergebnis: ~523 000 Zeilen auf stdout.

**4.2 b — `generate_json_report` enthält denselben Inhalt noch einmal.**
Zeilen 251-281 der Datei erzeugen eine strukturierte JSON mit *allen* Spans
(Name, Dauer, Layer-Klassifikation, Attribute) gruppiert nach `test_cases`.
Die JSON wird per `--output-json artifacts/trace-report-<uuid>.json`
gespeichert. **100 % Informationsüberlappung mit der stdout-Ausgabe** —
bloß strukturiert statt menschenlesbar.

**Konsequenz**: Die menschenlesbare Ausgabe gehört entweder in eine
**Datei** (`artifacts/layer<N>/trace-report.txt`), oder sie ist vollständig
überflüssig, weil die JSON alles enthält.

Zusätzlich: Die `print_perfschema`-Ausgabe (Z. 213-248) druckt die Top-10
Queries, Table-I/O und Warnungen auf stdout — Inhalte, die **identisch**
bereits in `artifacts/layer<N>/perfschema/summary.txt` persistiert wurden
(siehe `extract-perfschema.sh:97-148`). Doppelte Persistenz, keine echte
Redundanz.

### 4.3 — Layer 3: Deprecation-Warnings doppelt gedruckt

Im Layer-3-Bereich (Z. 7823-7850) erscheinen Deprecation-Warnings **zweifach**:

```
Deprecated: Using null as an array offset is deprecated ...  ← display_errors
PHP Deprecated:  Using null as an array offset is deprecated ...  ← error_log
```

**Ursache**: `Containerfile.webtrees` (STEP 15/22) setzt:
```
error_reporting = E_ALL
display_errors = On
log_errors = On
error_log = /var/log/php_errors.log
```

Im CLI-Mode (PHPUnit) druckt PHP Warnings bei `display_errors=On` auf
stderr, bei `log_errors=On` **zusätzlich** auf stderr mit `PHP `-Präfix
(wenn `error_log` leer oder syslog wäre — beim File-Log sollte es in die
Datei gehen, im CLI-Mode greift die Datei-Umleitung aber nicht immer).

**Bewertung**: Kein Kerngrund für die out.txt-Explosion, aber ein kleiner
Rausch-Faktor. In Layer 2 tritt das Problem nicht so sichtbar auf, weil
dort die Upstream-Tests mit `error_reporting = E_ALL & ~E_DEPRECATED`
konfiguriert sein dürften (zu prüfen — vermutlich in `phpunit-unit.xml`).

**Lösungsoption**: Deprecation-Warnings in Tests entweder aufräumen (Upstream-
Ticket) oder über `error_reporting=E_ALL & ~E_DEPRECATED` in einer test-
spezifischen php.ini ausblenden. Nicht Teil dieser Log-Analyse.

### 4.4 — ANSI-Escape-Codes in stdout-Datei

Zwei Stellen sichtbar:
- **Setup Composer-Output** (Z. 125-159): `[30;43m...[39;49m`, `[32m...[39m` —
  Composer färbt seine Ausgabe mit ANSI-Codes.
- **Layer 4/5 Playwright-list** (Z. 7868ff): `[1A[2K[0G` (Cursor hoch, Zeile
  löschen, an Spalte 0), `[32m✓[39m`, `[2m...[22m` (dim/undim).

**Ursache**: Beide Tools erkennen nicht, dass ihre Ausgabe in eine Datei
redirectet wird. Sie sehen `isatty(stdout) == false`, verwenden aber
dennoch Farben — entweder per Default, per Umgebungsvariable oder per
`FORCE_COLOR`.

**Bewertung**: Stilistisches Problem, kein Persistenz-Problem. Für interaktive
Terminal-Nutzung sind die Codes korrekt. Nur die Redirection nach `out.txt`
macht sie sichtbar. Da out.txt ohnehin eine Verlaufsdatei ist (nicht Artefakt),
ist die Dringlichkeit niedrig.

**Gegenmaßnahme**:
- Composer: `--no-ansi` im `setup-webtrees.sh:45,59`.
- Playwright: `FORCE_COLOR=0` als env auf dem `playwright`-Container, oder
  `--reporter=line` statt `list` (ruhiger, kein Cursor-Hopping), oder
  zusätzlicher `['line']`-Reporter statt `['list']`.

### 4.5 — `traces.json` als shared Jaeger-Input (works-as-designed)

**Mechanik**:
- `otel/otel-collector-config.yaml` konfiguriert den `file`-Exporter auf
  `/artifacts/traces.json` mit `append: true`.
- Der Collector läuft als Dauerservice (Stack), schreibt bei jeder Testphase
  kontinuierlich NDJSON an die Datei.
- `make clean` enthält in Zeile 48 die einzige Truncate-Stelle:
  `: > artifacts/traces.json && chmod 666 artifacts/traces.json`.
- Weder `make test-all` noch `make test-e2e` noch `make test-performance`
  truncaten die Datei zwischen Läufen.

**Begründung (works-as-designed)**: Das Wachstum ist **bewusst so gebaut**,
nicht übersehen. Jaeger (UI unter `:16686`) liest aus demselben Collector-
Pipeline-Output, und der menschliche Leser kann in Jaeger interaktiv **über
alle Läufe hinweg** durch Traces navigieren. Besonders im Systemtest
(Layer 4) wurde erheblicher Aufwand investiert, um den Testfall als
obersten Span-Knoten eines Trace sichtbar zu machen — `TEST_RUN_ID` +
`test.case_id`-Attribute korrelieren Playwright-Testfälle mit
PHP-Backend-Spans, PDO-Spans und Browser-RUM-Spans. Diese navigierbare
Historie über mehrere Läufe ist ein expliziter Feature-Wert.

**Folge**:
- `artifacts/traces.json` wächst monoton und wird **nur** durch `make clean`
  zurückgesetzt. Auf dem Host der protokollierten Sitzung waren das 2.77 GB
  — das ist **kein** Fehler, sondern der Preis der Feature.
- `scripts/trace-report.py` filtert per `test.run_id` aus dem shared Buffer
  genau die Spans des aktuellen Laufs heraus. Disk-Space und Lesezeit
  wachsen linear mit der Anzahl seit `make clean` akkumulierter Läufe;
  akzeptiert.

**Konsequenz für die Reparatur**: `traces.json` bleibt **unverändert** im
Artefakt-Root. Keine Rotation, kein Verschieben, kein Truncate zwischen
Layern. Nur die **abgeleiteten** Reports (`trace-report.json`,
`trace-report.txt`) müssen in den jeweiligen Layer-Ordner umziehen
(§6 Phase E). Sie dürfen ausschließlich Spans ihres eigenen Layers
auswerten — das ist heute bereits korrekt umgesetzt, weil `--run-id`
implizit layer-lokal filtert (ein `RUN_ID` wird pro Layer-Target frisch
erzeugt und an genau einen Playwright-Lauf gebunden).

**Offene Frage — Trace-Auswertung für Layer 3?**
Die OTel-PDO-Auto-Instrumentation (`open-telemetry/opentelemetry-auto-pdo`)
ist bereits im webtrees-Container aktiv, d. h. die PDO-Spans aus
Layer-3-Läufen landen schon heute in `traces.json`. Ausgewertet werden sie
nicht, weil im PHPUnit-Kontext weder `TEST_RUN_ID` noch `test.case_id`
gesetzt sind und damit keine Testfall-Korrelation hergestellt wird. Ob
Layer 3 einen eigenen `trace-report.{json,txt}` bekommt, ist eine
**separate Design-Entscheidung** — diese Log-Analyse notiert sie als
offene Frage (s. §7 Q1). Für Layer 1 (statisch, kein Runtime) und
Layer 2 (SQLite in-memory ohne OTel-Export) entfällt die Frage
strukturell.

### 4.6 — Toter Code: `layer5-performance/run.sh`

Die Datei existiert, enthält dasselbe `tee`-Muster wie Layer 2/3, wird aber
von **keinem Makefile-Target** aufgerufen. `make test-performance`
(Zeile 139-147) startet Playwright direkt via `podman-compose exec` und
ruft danach `extract-perfschema.sh` + `trace-report.sh`. Die `run.sh` ist
aktuell Fossil.

**Bewertung**: Inkonsistenz gegenüber Layer 1-3. Entweder wird das
`run.sh`-Modell konsequent durchgezogen (dann auch für Layer 4 einführen),
oder das `run.sh`-Modell wird verworfen und für alle Layer die Makefile-
Direktaufrufe konsolidiert.

### 4.7 — `layer4-e2e/` hat kein `run.sh`

Analog: Das Makefile-Target `test-e2e` ruft Playwright direkt. Es gibt
keine Skript-Zwischenschicht. Folge: Das `echo "Testlauf: $$RUN_ID"` aus
dem Makefile landet roh auf stdout, kein Phasen-Header `=== Layer 4 ===`,
kein definiertes Abschluss-Signal.

### 4.8 — Fehlender `=== Teststufe N ===`-Marker bei Layer 4+5

Layer 1-3 schreiben über `run.sh` einen konsistenten Phasen-Marker:

```
=== Statischer Test ===
=== Teststufe 1 — Komponententest ===
=== Teststufe 2 — Komponentenintegrationstest ===
...
=== Komponententest abgeschlossen (Exit: 0) ===
```

Layer 4 beginnt nur mit `Testlauf: <uuid>` (Z. 7866) und endet implizit mit
der PerfSchema-Extract-Zeile. Layer 5 gleich. Für einen sauberen Verlauf
im `out.txt`-Lese-Szenario fehlen die Klammer-Marker.

---

## 5 — Bewertung: Soll-Ort jedes Artefakt-Typs

### 5.1 — Was gehört auf stdout (I2+I6)

- `clone-upstream.sh`/`generate-passwords.sh`-Status
- `podman-compose up --build`-Output (Container-Build)
- `setup-webtrees.sh`-Fortschritt (`[N/4] ...`)
- `=== Layer X: <Kurzname> ===` Phasen-Anfang
- PHPStan/PHPCS-Summary-Zeile (`PHPStan: OK` oder `N Fehler`)
- Trivy-Info-Meldungen (Progress)
- PHPUnit-Progress-Dots + Summary-Zeile (`Tests: 691, Assertions: 2213, Deprecations: 3`)
- Playwright-Progress (ruhiger Reporter, nicht `list` mit Cursor-Hopping)
- `extract-perfschema.sh`-Fortschritt (`  statements_by_digest.json...`)
- Trace-Report **Summary** (Z. 308-324: Testlauf-ID, Spans-Count,
  Root-Spans-Count, Testcase-Liste mit trace_id — OHNE den Span-Baum)
- `=== Layer X abgeschlossen (Exit: 0) ===` Phasen-Ende
- Fehler/Abbrüche (Test-Crashes, Container-Bringup-Fehler, SELinux-Probleme)
- **`make test-all` Aggregat-Summary** am Ende: „Layer 1 OK | Layer 2 OK
  (3800 Tests) | Layer 3 OK (691 Tests) | Layer 4 OK (176 Tests) | Layer 5 OK“

### 5.2 — Was gehört in `artifacts/layer<N>/`

**Layer 1**:
- `phpstan.json`, `phpcs.json`, `trivy-report.json`, `trivy-report.txt`

**Layer 2**:
- `phpunit-unit.xml` (JUnit)
- `coverage.xml` (Clover)
- `coverage-html/` (optional, wenn HTML gewünscht; groß)

**Layer 3**:
- `phpunit-integration.xml` (JUnit)
- `coverage.xml` (Clover)
- `db-dump.sql` (konditional bei Failure)
- `php-errors.log` (konditional bei Failure)
- `perfschema/` (wenn Layer 3 PerfSchema extrahiert — derzeit nicht Standard)

**Layer 4**:
- `playwright-report/` (HTML)
- `test-results/` (Screenshots, Traces-Zip, JUnit-Detail)
- `perfschema/`
- `trace-report.json` (statt `artifacts/trace-report-<uuid>.json`)
- `trace-report.txt` (menschenlesbarer Span-Baum — als *designte* Redundanz
  zur JSON, weil ein Text-Report für interaktives Grepping praktischer ist)

**Layer 5**:
- `playwright-report/`, `test-results/`, `perfschema/`
- `performance-results.json`, `perf-*.json`
- `trace-report.json`, `trace-report.txt`

**Bewusst nicht pro Layer gespiegelt**: Die Rohdatei `artifacts/traces.json`
bleibt als shared Jaeger-Input-Buffer im Artefakt-Root (§4.5). Jeder
Layer-Report (`trace-report.*`) extrahiert daraus *lesend* nur die eigenen
Spans per `test.run_id`; die Rohdaten werden **nicht** in einen Layer-Ordner
kopiert oder verschoben.

### 5.3 — Was gehört in `artifacts/summary/` (nur bei `make test-all`)

- `test-all.json` — maschinenlesbare Layer-Übersicht (PASS/FAIL-Counts,
  Coverage-%-pro-Layer, Laufzeit)
- `test-all.txt` — menschenlesbare Variante (identischer Inhalt, anderer
  Konsum)
- `coverage-compare.txt` — L2-vs-L3-Coverage-Diff (aktuell händisch in
  `docs/coverage-runs/` erzeugt — automatisierbar)

### 5.4 — Legitime (designte) Redundanzen

Explizit zulässig (keine Aufräumarbeit nötig):
- **PHPUnit-Summary** sowohl auf stdout (`Tests: 691, Assertions: 2213`)
  als auch in JUnit-XML. Verschiedene Konsumenten.
- **PHPStan/PHPCS-Count** sowohl auf stdout als auch in den JSON-Reports.
- **`trace-report.json` ↔ `trace-report.txt`** gleicher Inhalt, strukturiert
  vs. human-readable. Beides *im Artefakt*, nicht auf stdout.
- **Test-Abbruch-Stacktrace** auf stdout *und* im JUnit/Log-Artefakt.
- **Aggregat-Summary** auf stdout *und* als `artifacts/summary/test-all.txt`.

Nicht zulässig:
- **`phpunit-output.log`** als vollständiger Klartext-Mirror des stdout-
  Fortschritts. Der JUnit-XML ist der strukturierte Ersatz. Wer die
  Laufzeit-Reihenfolge braucht, kann sie aus dem JUnit oder dem Testlauf
  reproduzieren.
- **Trace-Span-Baum auf stdout** parallel zur JSON. Eine Quelle reicht.
- **PerfSchema-Top-Tabelle auf stdout** parallel zu `perfschema/summary.txt`.

---

## 6 — Umsetzungsplan (Phasenskizze)

Die folgenden Phasen sind inkrementell und einzeln testbar. Sie ergeben
zusammen die Reparatur, überführen aber weder Commits noch Patches — das
geschieht im Folgeschritt (Plan).

### Phase A — Bind-Mount `./artifacts:/artifacts:rw,z` im webtrees-Container

**Änderung**: `compose.yaml`, Service `webtrees`, Volumes-Liste um einen
Eintrag erweitern:

```diff
     volumes:
       - ${WEBTREES_SOURCE:-./upstream/webtrees}:/var/www/html:ro,z
       - webtrees-data:/var/www/html/data
       - webtrees-vendor:/var/www/html/vendor
       - ${MODULE_PATH:-./.empty-module}:/var/www/html/modules_v4/${MODULE_NAME:-_none}:ro,z
       - ./modules/otel-spans:/var/www/html/modules_v4/otel_spans:ro,z
       - ./modules/security-trace:/var/www/html/modules_v4/security_trace:ro,z
+      - ./artifacts:/artifacts:rw,z
       - ./artifacts/security-trace:/artifacts/security-trace:rw,z
       - ./fixtures:/fixtures:ro,z
       ...
```

**Effekt**:
- `run.sh`-Scripts schreiben direkt auf Host.
- `podman cp webtrees:/artifacts/layer2/coverage.xml artifacts/layer2/coverage.xml`
  im Makefile wird obsolet (aber bleibt harmlos, kann parallel weglaufen).
- Bestehende `artifacts/security-trace`-Zeile bleibt als expliziter Unterpfad-
  Mount drin (oder kann entfallen — das oberste Mount deckt sie ab).
- **Zusatznutzen**: Während des Laufs sind Zwischenartefakte live auf dem
  Host sichtbar (`tail -f artifacts/layer3/phpunit-integration.xml`,
  `ls artifacts/layer2/coverage-html/`). Bei langen Läufen (Layer 3 kann
  mehr als 10 Min dauern) ist das heute nur über `podman-compose exec …
  tail` möglich; nach Phase A direkt.

#### Historisches Risiko: Performance bei Bind-Mounts in den webtrees-Container

Das Muster *„im Container schreiben, nach Abschluss per `podman cp` holen“*
ist **nicht** durch Zufall entstanden. Es existiert, weil ein früherer
Bind-Mount-Ansatz Performance-Probleme ausgelöst hat — vermutlich im
Zusammenspiel mit SELinux-Relabeling auf Fedora + rootless Podman, möglicherweise
auch durch fuse-overlayfs- oder kernel-overlayfs-Overhead bei
schreibintensiven Operationen. Die genauen Ursachen sind nicht mehr
dokumentiert; die Konsequenz ist im Repo sichtbar:

- `coverage-data:/coverage` ist als **Named Volume** deklariert
  (`compose.yaml:32`, Kommentar: *„Coverage-Daten auf schnellem Named
  Volume (kein Bind-Mount-I/O bei Instrumentierung)“*).
- Layer 2 schreibt nach `/artifacts/layer2/` im Container und wird per
  `podman cp` extrahiert (`Makefile:95`).
- Layer 3 schreibt Coverage nach `/coverage/layer3-coverage.xml` (Named
  Volume) und wird ebenfalls per `podman cp` extrahiert (`Makefile:100`).

Die Schreib-Last an PHPUnit/PCOV-Schreibpfaden ist hoch (Coverage-Daten,
JUnit-XML, PCOV-Writes bei jeder gedeckten Zeile). Ein Bind-Mount mit
SELinux-relabeling-Overhead pro Syscall kann hier messbar langsamer sein
als ein Named Volume auf demselben Storage-Backend.

#### Mess-Pflicht vor dem Commit

Phase A darf **nicht** blind committed werden. Vor der Umstellung sind
zwei Referenzläufe nötig:

| Messung | Befehl | Anzahl |
|---|---|---|
| **Baseline** (Status quo, ohne Mount) | `time make test-unit` und `time make test-integration` | je 3 Läufe, Median |
| **Vergleich** (mit Bind-Mount) | dito nach Änderung der `compose.yaml` | je 3 Läufe, Median |

**Entscheidungskriterien**:

- **Grün (Mount committen)**: Median-Abweichung ≤ +5 % gegenüber Baseline.
- **Gelb (Teil-Mount)**: Abweichung 5–20 %. Dann Bind-Mount nur für
  schreib-unkritische Layer-Ordner einsetzen (z. B. `./artifacts/layer1`,
  `./artifacts/layer4`, `./artifacts/layer5`), nicht für `layer2/` und
  `layer3/`, wo Coverage + PCOV-Last am höchsten ist. Die betroffenen
  Layer behalten das „Schreiben-im-Container + `podman cp`“-Muster (siehe
  Fallback-Strategie unten).
- **Rot (Mount verwerfen)**: Abweichung > 20 % in einem der Schreib-heißen
  Layer. Dann Phase A komplett **nicht** umsetzen, stattdessen Fallback-
  Strategie konsequent durchziehen.

Ergebnisse dieser Messung wandern in die `docs/`-Doku als `phase_a_bench_<datum>.md`
und in die Commit-Message.

#### Fallback-Strategie: konsequentes `podman cp` für alle Artefakte

Wenn Phase A aus Performance-Gründen (Gelb / Rot) nicht oder nur teilweise
umgesetzt werden kann, bleibt das bisherige Muster — dann aber **konsequent**
und **vollständig**, nicht punktuell wie heute. Aktuell extrahiert das
Makefile nur `coverage.xml` (L2 und L3), lässt aber JUnit-XMLs,
`coverage-html/`, `phpunit-output.log` und die PHPStan/PHPCS-JSONs im
Container zurück. Das ist der Kern des ursprünglichen Problems und muss
gelöst werden:

```diff
 test-unit:
 	$(COMPOSE) exec webtrees /bin/bash /tests/layer2-unit/run.sh
 	mkdir -p artifacts/layer2
-	podman cp webtrees:/artifacts/layer2/coverage.xml artifacts/layer2/coverage.xml
+	podman cp webtrees:/artifacts/layer2/. artifacts/layer2/
```

Der Punkt hinter `/artifacts/layer2` extrahiert rekursiv alle Inhalte
(inkl. `coverage-html/`, `phpunit-unit.xml`, `coverage.xml`). Das gleiche
Muster für Layer 1 (`layer1-static/run.sh` muss dafür nach `/artifacts/layer1/`
schreiben statt direkt auf Host-Pfaden), Layer 3, optional auch für Layer 4/5.

**Nachteil der Fallback-Strategie**: Live-Monitoring während des Laufs
bleibt nur über `podman-compose exec webtrees tail -f …` möglich, nicht
vom Host aus. Der Bonus „Zwischenartefakte parallel zum Lauf sichten“
fällt weg. Gerade bei Layer 3 (lange Laufzeit) ist das spürbar.

**Risiken Phase A (allgemein)**:
- SELinux (`,z` vs. `,Z`): `,z` ist korrekt (shared), kein Konflikt mit
  Playwright-Container, der dieselbe `./artifacts` shared mountet.
- `.gitignore` ignoriert `artifacts/*` — keine git-Tracking-Probleme.
- Alte, im Container verbliebene Artefakte aus früheren Läufen verschwinden
  beim Restart (erwünscht).
- **Performance — siehe Historisches Risiko + Mess-Pflicht oben.** Dies
  ist das einzige technisch ernstzunehmende Risiko dieser Phase.

### Phase B — `tee`-Entfernung in `run.sh`

**Änderung**: `layer2-unit/run.sh:34-38`, `layer3-integration/run.sh:19-23`:

```diff
-su -s /bin/bash www-data -c "... vendor/bin/phpunit ..." \
-    2>&1 | tee "${ARTIFACTS}/phpunit-output.log"
+su -s /bin/bash www-data -c "... vendor/bin/phpunit ..."

-EXIT_CODE=${PIPESTATUS[0]}
+EXIT_CODE=$?
```

Analog für `layer5-performance/run.sh` (falls es reaktiviert wird, s. §Phase F).

**Effekt**:
- PHPUnit-Text-Output nur noch auf stdout → out.txt enthält Progress-Dots
  und Summary, keine Persistenz-Duplikate.
- JUnit-XML (`--log-junit`) bleibt unverändert und ist nach Phase A auf dem
  Host sichtbar.
- `phpunit-output.log` wird nicht mehr erzeugt.

**Alternative falls `phpunit-output.log` beibehalten werden muss**: nur
stderr loggen, stdout bleibt sauber:

```bash
... 2> "${ARTIFACTS}/phpunit-errors.log"
```

Empfehlung: komplett weglassen. JUnit-XML deckt den Persistenz-Bedarf ab.

### Phase C — `trace-report.py` entkoppeln: Summary auf stdout, Details in Datei

**Änderung**: `scripts/trace-report.py`.

Zwei neue Kommandozeilen-Parameter:

```python
parser.add_argument("--output-text", help="Menschenlesbarer Report (txt)")
parser.add_argument("--stdout-mode", choices=["summary", "full", "silent"],
                    default="summary")
```

Die `print()`-Aufrufe in `main()` nach stdout-Mode gruppieren:

- **`summary`** (Default): nur Z. 308-324 (Testlauf-ID, Spans-Count,
  Root-Count, Testcase-Liste) + `print_perfschema` entfällt von stdout.
- **`full`**: aktuelle Semantik, nur für Debugging.
- **`silent`**: komplett still.

Die Span-Baum-Ausgabe (Z. 346-359) und `print_perfschema` landen in einem
optionalen Text-Report:

```python
if args.output_text:
    with open(args.output_text, "w") as f:
        # Kopie von print_span_tree, die in die Datei schreibt statt stdout
        # Sauber via io.StringIO + tee oder per file=f-Argument bei print()
```

**Makefile-Anpassung** (`test-e2e`, `test-performance`):

```diff
-scripts/trace-report.sh --run-id $$RUN_ID --layer 4 \
-    --output-json artifacts/trace-report-$$RUN_ID.json || true
+scripts/trace-report.sh --run-id $$RUN_ID --layer 4 \
+    --output-json artifacts/layer4/trace-report.json \
+    --output-text artifacts/layer4/trace-report.txt \
+    --stdout-mode summary || true
```

**Effekt**:
- stdout schrumpft von ~535 000 Zeilen auf ~200 Zeilen Summary pro Layer.
- Informationsverlust: **null** — JSON + TXT haben alles.

### Phase D — ~~`traces.json` pro Layer rotieren~~ (entfällt)

**Status**: entfällt.

Die ursprüngliche Einschätzung, `artifacts/traces.json` müsse rotiert oder
pro Layer kopiert werden, war falsch. Wie in §4.5 ausführlich dokumentiert,
ist der unbegrenzte Akkumulator **works-as-designed**:

- Jaeger (UI unter `http://localhost:16686`) liest aus derselben
  Collector-Pipeline und erlaubt Navigation über *alle* Traces *aller*
  Läufe — das ist der Zweck der Datei.
- Insbesondere im Layer-4-Systemtest wurde erheblicher Aufwand investiert,
  den Testfall als oberste Span-Ebene sichtbar zu machen, damit man in
  Jaeger über mehrere Läufe hinweg nach Testfällen filtern kann.
- Eine Rotation oder layer-weise Aufsplittung würde diesen Navigations-Use-Case
  brechen und wäre eine Regression, kein Fix.

Die Invariante I3 („Layer-getrennte Persistenz“) wird für Trace-Rohdaten
*nicht* durch Aufsplittung der Rohdatei erreicht, sondern dadurch, dass
alle **abgeleiteten** Reports (`trace-report.json`, `trace-report.txt`) in
den jeweiligen Layer-Ordner wandern und *lesend* nur ihre eigenen Spans
aus der gemeinsamen Rohdatei herausfiltern. Siehe §4.5 und Phase E.

**Folge**: Der Collector-Config-Block (`otel/otel-collector-config.yaml`,
`exporters.file.path: /artifacts/traces.json`) bleibt **unverändert**.
Kein `rotation`-Block, kein `append: false`, keine Truncate-Logik im
Makefile. Der Preis (heute ca. 2,77 GB) ist akzeptiert und hängt an der
Lebensdauer des lokalen Test-Stacks — bereinigt wird er ausschließlich
über `make clean` (das bereits `: > artifacts/traces.json` enthält,
`Makefile:48`).

### Phase E — Layer-spezifische `trace-report.json`-Pfade

**Änderung**: Makefile `test-e2e` und `test-performance`:

```diff
 test-e2e:
 	@RUN_ID=$$(uuidgen); \
 	echo "Testlauf: $$RUN_ID"; \
 	scripts/truncate-perfschema.sh || true; \
 	$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright npx playwright test \
 	    --config=/tests/e2e/playwright.config.ts; \
 	scripts/extract-perfschema.sh layer4 || true; \
 	scripts/trace-report.sh --run-id $$RUN_ID --layer 4 \
-	    --output-json artifacts/trace-report-$$RUN_ID.json || true
+	    --output-json artifacts/layer4/trace-report.json \
+	    --output-text artifacts/layer4/trace-report.txt || true
```

Analog Layer 5 (nach `artifacts/layer5/trace-report.{json,txt}`).

**Die Rohdatei bleibt im Artefakt-Root**: `artifacts/traces.json` wird
**nicht** kopiert und **nicht** truncated. Der Report liest sie nur und
filtert intern per `test.run_id` auf die eigenen Spans. Rationale siehe
§4.5 und Phase D.

**Ausgabepfade enthalten keine RUN_ID mehr**: Der Dateiname `trace-report.json`
(ohne UUID-Suffix) ist bewusst so gewählt — pro Layer gibt es genau einen
aktuellen Report, der vom jeweils letzten Lauf überschrieben wird. Wer
historische Reports braucht, kann Jaeger bemühen (die Rohdaten sind dort
vollständig) oder den Layer-Ordner vor dem nächsten Lauf wegsichern.

**Effekt**:
- `artifacts/trace-report-*.json` verschwinden aus dem Artefakt-Root.
- Alte Dateien (die 8 `trace-report-*.json` im Root, 600 MB kumuliert)
  sind Cruft und sollten einmalig gelöscht werden (außerhalb von `make clean`,
  weil das Target layer-fokussiert bleibt).
- `make clean` bleibt unverändert und deckt die neuen Pfade über
  `rm -rf artifacts/layer*/*` ab (`Makefile:47`).

### Phase F — Layer 4/5 `run.sh` konsolidieren

**Option F-1 — run.sh-Modell konsequent durchziehen**:
- `layer4-e2e/run.sh` neu anlegen (analog layer3):
  ```bash
  #!/usr/bin/env bash
  set -euo pipefail
  ARTIFACTS="/artifacts/layer4"
  mkdir -p "${ARTIFACTS}"
  echo "=== Teststufe 3 — Systemtest ==="
  cd /tests/e2e
  npx playwright test --config=playwright.config.ts
  EXIT_CODE=$?
  echo "=== Systemtest abgeschlossen (Exit: ${EXIT_CODE}) ==="
  exit "${EXIT_CODE}"
  ```
- `layer5-performance/run.sh` reaktivieren (ohne `tee`).
- Makefile umstellen auf `$(COMPOSE) exec playwright /bin/bash /tests/e2e/run.sh`.
- Die PerfSchema-Extract- und Trace-Report-Aufrufe bleiben im Makefile
  (weil sie auf dem Host laufen, nicht im Container).

**Option F-2 — run.sh-Modell verwerfen**:
- `layer*-*/run.sh` alle löschen.
- Makefile-Targets enthalten die Logik direkt.
- Phasen-Marker als `@echo "=== Layer N ==="` im Makefile.

**Bewertung**: Option F-1 ist konsistenter, weil run.sh Layer-interne Orchestrierung
kapselt (Env-Setup, tests/data-Bereinigung, chown für www-data, DB-Dump
bei Failure). F-2 ist einfacher, aber verlagert Logik ins Makefile und
verhindert, dass man einen Layer einzeln aus dem Container starten kann.

**Empfehlung**: F-1.

### Phase G — Aggregat-Report `artifacts/summary/test-all.*`

**Neu**: `scripts/summarize-test-all.sh` oder `.py`.

Ablauf:
1. Prüft, welche Layer artefakte vorliegen (letzter Lauf).
2. Parst JUnit-XMLs (L2, L3, L4), Clover-XMLs (L2, L3), JSON-Reports (L4, L5).
3. Schreibt `artifacts/summary/test-all.json` (maschinenlesbar) und
   `artifacts/summary/test-all.txt` (menschenlesbar).
4. Druckt Kurz-Zusammenfassung auf stdout (designte Redundanz).

Aufruf am Ende des `test-all`-Targets:

```diff
-test-all: setup test-static test-unit test-integration test-e2e test-performance ## Alle Teststufen ausführen
+test-all: setup test-static test-unit test-integration test-e2e test-performance ## Alle Teststufen ausführen
+	@scripts/summarize-test-all.sh
```

**Wichtig für I1**: Der Summary-Schritt darf **nur** laufen, wenn das Target
`test-all` aufgerufen wurde — nicht im Einzel-Layer-Lauf. Ein als
Abhängigkeit eingebundenes `test-all-summary: test-static test-unit ...`-
Target wäre eine Verletzung der Invariante.

### Phase H — Optional: ANSI-Codes zähmen

**Composer**: in `setup-webtrees.sh` die Composer-Aufrufe mit `--no-ansi`
erweitern (Z. 45, Z. 59). Niedrige Priorität.

**Playwright**: in `compose.yaml` für den `playwright`-Service
`FORCE_COLOR: "0"` als Env setzen, oder in beiden `playwright.config.ts`
den Reporter von `['list']` auf `['line']` umstellen (ruhigerer Output,
keine Cursor-Manipulation). Mittlere Priorität — betrifft Ästhetik der
stdout-Zeilen, nicht Persistenz.

### Phase I — PHPStan/PHPCS-Summary nach Phase A verifizieren

**Check**: Nach Phase A sind `phpstan.json` und `phpcs.json` auf dem Host
sichtbar. Damit kann der Status-Echo in `layer1-static/run.sh:26-29, 41-46`
einen Count aus der bereits existierenden Datei ziehen (tut er schon
korrekt — keine Änderung nötig). Verifizieren, dass der User-Wunsch
„PHPStan: N Fehler“ auf stdout bleibt und `phpstan.json` die Details hat.

---

## 7 — Risiken und offene Fragen

**R1 — Bind-Mount-Performance (Phase A, Haupt-Risiko)**:
Das ist das einzige technisch ernsthafte Risiko dieser Reparatur. In der
Vergangenheit hat ein Bind-Mount für Coverage-Pfade Performance-Probleme
verursacht (vermutlich SELinux-Relabeling + fuse-overlayfs-Overhead unter
rootless Podman). Die genaue Ursache ist nicht dokumentiert, aber die
Gegenmaßnahme steht im Repo: `coverage-data` ist ein Named Volume, Layer 2
schreibt nach `/artifacts/layer2/` im Container und wird per `podman cp`
extrahiert. Vor dem Commit von Phase A sind deshalb Referenzläufe nötig
(§6 Phase A, „Mess-Pflicht“). Bei Rot-Ergebnis wird Phase A durch die
Fallback-Strategie (`podman cp webtrees:/artifacts/<layer>/.`) ersetzt.

**R2 — SELinux-Mount-Label (Phase A, Nebenrisiko)**:
`./artifacts:/artifacts:rw,z` im webtrees-Container könnte mit dem
Playwright-Container-Mount `./artifacts:/artifacts:rw,z` ins Gehege
kommen, wenn beide Container gleichzeitig schreiben. Laut CLAUDE.md-Regel
(„parallele Läufe erzeugen Race-Conditions“) läuft nur ein Layer
gleichzeitig — Risiko de-facto null. `,z` (shared) behalten, **nicht**
`,Z`.

**R3 — `coverage-data` Named-Volume für Layer 3**:
Aktuell schreibt Layer 3 die Coverage nach `/coverage/layer3-coverage.xml`
(Named Volume) und das Makefile kopiert sie danach per `podman cp`. Nach
Phase A könnte man direkt nach `/artifacts/layer3/coverage.xml` schreiben.
Aber: Das Named Volume existiert aus denselben Performance-Gründen wie R1
(siehe Kommentar in `compose.yaml:32`: *„kein Bind-Mount-I/O bei
Instrumentierung“*). Möglicherweise ist der Clover-Schreibpfad heiß genug,
dass Phase A für Layer 3 fällt ins Gelb-Band. In diesem Fall **nur für
Layer 3** beim Named-Volume-Muster bleiben, die übrigen Layer auf
Bind-Mount umstellen. Hybrid ist akzeptabel.

**R4 — Layer-3-Deprecation-Doppelmeldungen**:
Separate Root-Cause (PHP CLI + `display_errors` + `log_errors`, §4.3),
gehört nicht in dieselbe Reparatur-Welle. Als Folge-Ticket.

**R5 — Alter `trace-report-*.json`-Cruft im Root**:
Die 8 Dateien (~600 MB) aus alten Läufen sind „Fossils“. Nach Phase E
werden sie nicht mehr nachwachsen, aber der Alt-Bestand sollte einmalig
gelöscht werden. Kein Teil der Reparatur, aber in der Umsetzung
erwähnen.

**R6 — `make clean` Verhalten nach Phase A**:
Aktuell löscht `make clean` per `rm -rf artifacts/layer*/*` (Host-Pfad,
Zeile 47). Nach Phase A werden dieselben Pfade auch im Container als
Bind-Mount sichtbar, und das Host-`rm` reicht weiterhin. Kein Konflikt.
`: > artifacts/traces.json` (Zeile 48) bleibt der einzige Reset-Punkt
für die shared Trace-Datei — bewusst so (§4.5).

**R7 — Invariante I1 bei Phase G**:
Wenn `summarize-test-all.sh` nur beim `test-all`-Target läuft, und ein
User ruft stattdessen `make test-static test-unit test-integration`
manuell der Reihe nach, entsteht kein Summary. Das ist erwünscht und
dokumentationspflichtig (CLAUDE.md-Hinweis).

### Offene Fragen

**Q1 — Trace-Analyse jenseits von Layer 4**:
Der OTel-PDO- und -PSR-15-Auto-Instrumentation läuft bei **allen** Layern,
in denen der webtrees-Container Requests verarbeitet — also auch bei
Layer 3 (Integration-Tests) und in Ausnahmefällen bei Layer 2 (wenn
Upstream-Tests PDO treffen). Die Spans landen ebenfalls in
`artifacts/traces.json`. Aktuell wird davon nur Layer 4/5 ausgewertet,
weil nur dort ein Testfall mit `test.run_id`-Attribut per `otel-spans`-Modul
und Playwright-Hooks als oberste Span-Ebene existiert.

Für Layer 3 wäre eine analoge Korrelation denkbar (PHPUnit-TestCase-Name
→ Baggage → Span-Attribut `test.case_id`), ist aber nicht umgesetzt. Die
Frage bleibt offen:
- Ist die Investition sinnvoll? Layer 3 läuft PHPUnit-intern, ohne HTTP-Hops.
  Die PDO-Spans sind da, aber der Test-Context fehlt.
- Wenn ja: Layer 3 bekäme einen eigenen `artifacts/layer3/trace-report.{json,txt}`
  nach gleichem Muster wie Layer 4/5. Invariante I3 bliebe gewahrt.
- Wenn nein: Der PDO-Rausch in `traces.json` aus Layer 3 bleibt im Jaeger,
  aber ohne Testfall-Zuordnung — reiner Debug-Kontext.

Diese Frage ist **nicht Teil** dieser Reparatur und wird als Folgediskussion
geführt.

---

## 8 — Anhang: Ist → Soll pro Artefakt

| Artefakt | Ist-Ort | Soll-Ort | Änderung |
|---|---|---|---|
| `phpstan.json` | nur Container | `artifacts/layer1/phpstan.json` | Phase A |
| `phpcs.json` | nur Container | `artifacts/layer1/phpcs.json` | Phase A |
| `trivy-report.json` | `artifacts/layer1/` | unverändert | — |
| `trivy-report.txt` | `artifacts/layer1/` | unverändert | — |
| PHPStan-Status-Zeile | stdout | unverändert | — |
| PHPCS-Status-Zeile | stdout | unverändert | — |
| `phpunit-unit.xml` (JUnit) | nur Container | `artifacts/layer2/phpunit-unit.xml` | Phase A |
| `coverage.xml` L2 | `artifacts/layer2/` (via `podman cp`) | `artifacts/layer2/coverage.xml` direkt | Phase A + Makefile-Cleanup |
| `coverage-html/` L2 | nur Container | `artifacts/layer2/coverage-html/` | Phase A |
| `phpunit-output.log` L2 | nur Container (1.1 MB) | **entfällt** (redundant zu JUnit) | Phase B |
| PHPUnit-Progress L2 | stdout (via `tee`) | stdout (direkt) | Phase B |
| PHPUnit-Summary L2 | stdout | stdout | — |
| `phpunit-integration.xml` (JUnit) | nur Container | `artifacts/layer3/phpunit-integration.xml` | Phase A |
| `coverage.xml` L3 | `artifacts/layer3/` (via `podman cp` aus Named Volume) | ggf. unverändert (R2) | R2 bewerten |
| `phpunit-output.log` L3 | nur Container | **entfällt** | Phase B |
| `db-dump.sql` L3 (conditional) | nur Container | `artifacts/layer3/db-dump.sql` | Phase A |
| `php-errors.log` L3 (conditional) | nur Container | `artifacts/layer3/php-errors.log` | Phase A |
| `playwright-report/` L4 | `artifacts/layer4/` | unverändert | — |
| `test-results/` L4 | `artifacts/layer4/` | unverändert | — |
| `perfschema/` L4 | `artifacts/layer4/` | unverändert | — |
| Playwright-list-Output L4 | stdout (mit ANSI) | stdout (`line`-Reporter, ohne Cursor-Hopping) | Phase H (optional) |
| Trace-Span-Baum L4 | stdout (523 410 Zeilen) | `artifacts/layer4/trace-report.txt` | Phase C |
| Trace-Report-Summary L4 | stdout (~200 Z.) | stdout | — (explizit behalten) |
| `trace-report.json` L4 | `artifacts/trace-report-<uuid>.json` | `artifacts/layer4/trace-report.json` | Phase E |
| `playwright-report/` L5 | `artifacts/layer5/` | unverändert | — |
| `test-results/` L5 | `artifacts/layer5/` | unverändert | — |
| `performance-results.json` L5 | `artifacts/layer5/` | unverändert | — |
| `perf-*.json` L5 | `artifacts/layer5/` | unverändert | — |
| `perfschema/` L5 | `artifacts/layer5/` | unverändert | — |
| Playwright-list-Output L5 | stdout | stdout (ggf. `line`) | Phase H |
| Trace-Span-Baum L5 | stdout (11 483 Zeilen) | `artifacts/layer5/trace-report.txt` | Phase C |
| `trace-report.json` L5 | `artifacts/trace-report-<uuid>.json` | `artifacts/layer5/trace-report.json` | Phase E |
| `artifacts/traces.json` (shared) | Root, wächst unbegrenzt | **unverändert** (works-as-designed, §4.5 — Jaeger-Input) | — |
| Aggregat-Summary | — | `artifacts/summary/test-all.{json,txt}` + stdout | Phase G |

---

## 9 — Zusammenfassung

Die out.txt-Explosion hat **drei klar getrennte Root-Causes**, die sich in
der Reparatur auch klar trennen lassen:

1. **`tee` in `layer2-unit/run.sh` und `layer3-integration/run.sh`**
   erzeugt eine Klartext-Log-Datei im Container (die nie den Host erreicht)
   und spiegelt den vollen PHPUnit-Output auf stdout. → Phase B, ein
   einzeiliger Patch pro Datei. **Risikoarm, unabhängig von Phase A.**

2. **`scripts/trace-report.py` druckt den vollständigen Span-Baum auf
   stdout**, während die identische Information in `trace-report.json`
   persistiert wird. → Phase C, CLI-Schalter + Refaktor der Print-Pfade.
   **Risikoarm, unabhängig von Phase A** (Playwright-Container hat den
   `./artifacts`-Bind-Mount bereits, siehe `compose.yaml:107`).

3. **`compose.yaml` mountet `./artifacts` nicht in den `webtrees`-Container.**
   Deshalb stirbt jedes Artefakt aus Layer 1/2/3, das nicht per `podman cp`
   abgeholt wird, mit dem Container-Restart. → Phase A, ein zusätzlicher
   Volume-Eintrag. **Einziges Risiko mit Mess-Pflicht** (historische
   Performance-Probleme, §6 Phase A). Fallback-Strategie: `podman cp`
   konsequent rekursiv durchziehen (derselbe Layer-Effekt, ohne
   Live-Monitoring).

Daneben bleiben **zwei** kleinere Layoutprobleme: Trace-Artefakte im
Artefakt-Root statt im Layer-Ordner (Phase E) und inkonsistente
`run.sh`-Existenz zwischen Layer 1–3 und Layer 4–5 (Phase F). Zusätzlich
fehlt bei `make test-all` das Aggregat-Reporting (Phase G). Der unbegrenzt
wachsende `artifacts/traces.json` ist **kein** Problem, sondern das
gewünschte Verhalten (§4.5: shared Jaeger-Input über alle Läufe hinweg).

### Phasen-Abhängigkeiten

Die Reihenfolge der Phasen ist *nicht* strikt linear:

- **Phase B** (tee-Entfernung) und **Phase C** (trace-report Summary) sind
  von Phase A **unabhängig** und können zuerst gemerged werden. Sie
  senken die out.txt-Größe sofort auf < 1 % des aktuellen Volumens und
  liefern den größten Hebel der ganzen Reparatur.
- **Phase E** (Layer-Pfade für Trace-Reports) ist ebenfalls unabhängig —
  der Playwright-Container hat bereits Zugriff auf `./artifacts`.
- **Phase A** ist **Voraussetzung** für vollständige L1/L2/L3-Persistenz
  auf dem Host (PHPStan-JSON, PHPCS-JSON, JUnit-XMLs, HTML-Coverage-Bericht
  etc.). Sie ist die kritische Phase, weil sie die Mess-Pflicht trägt. Bei
  Rot-Ergebnis wird sie durch die Fallback-Variante (`podman cp -r`)
  ersetzt — der End-Zustand bleibt derselbe, nur das Live-Monitoring fällt
  weg.
- **Phase D ist gestrichen** (§4.5 / §6 Phase D).
- **Phase F** (run.sh-Konsolidierung) und **Phase G** (test-all-Aggregat)
  sind kosmetische Verbesserungen ohne Abhängigkeiten und können als
  Follow-up kommen.
- **Phase H** (ANSI-Codes) und **Phase I** (PHPStan/PHPCS-Verify) sind
  Polish und optional.

Empfohlene Reihenfolge der Umsetzung: **B → C → E → (Messung für Phase A)
→ A oder Fallback → F → G → H → I**.
