<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Test-Run-Snapshot 2026-05-24T19-34 (Layer 4)

Snapshot der Roh-Artefakte aus dem `make test-e2e`-Lauf, gestartet am
**2026-05-24T19:34:05+02:00**, beendet **19:49:03+02:00**. Findings-Protokoll
dazu: [../2026-05-24T19-34_make-test-e2e-findings.md](../2026-05-24T19-34_make-test-e2e-findings.md).

Zweck dieses Ordners: persistente Sicherung der L4-Roh-Ergebnisse, weil
`artifacts/layer4/` beim nächsten Lauf überschrieben wird.

## Quick-Bilanz

| Stufe | Tool                                       | Tests | Passed | Flaky | Failed | Skipped | Suite-Laufzeit | Exit |
|-------|--------------------------------------------|------:|-------:|------:|-------:|--------:|----------------|-----:|
| 4     | Playwright (Chromium headless, 1 worker, retry=1) | 533 |    532 |     1 |      0 |       0 | 14,0 min       | 0    |

Einzelfund: 1 flaky `K01 — Kontaktformular zeigt Pflichtfelder [fab]`,
bestanden im Retry. Details: Findings-Doc oben.

## Vorbedingung dieses Laufs

- Stack vorher mit `make clean && make up && make setup` frisch aufgesetzt
  (außerhalb dieser Session, Stand 19:30+02:00).
- **Kein** vorangehender L3-Lauf in dieser Session — Fixtures (`demo`-Tree,
  `privacy`-Tree) unangetastet.

## Was dieser Snapshot **enthält**

```
docs/test-runs/2026-05-24T19-34_run/
├── README.md                                     (dieses Dokument)
├── MANIFEST.sha256                               (Integritäts-Manifest)
└── layer4/
    ├── playwright-results.json                   656 KB   JSON-Report (alle 533 Tests)
    ├── playwright-report.tar.gz                  1,4 MB   HTML-Report komprimiert
    ├── run-output.log                            113 KB   kompletter `make test-e2e`-Log
    ├── perfschema/
    │   ├── summary.txt                           788 B    PerfSchema-Top-N Zusammenfassung
    │   ├── stages_global.json                    1,9 KB   MySQL-Stages, global
    │   ├── statements_by_digest.json             2,1 KB   Statement-Digest-Stats
    │   ├── table_io_waits.json                   5,9 KB   Tabellen-I/O-Waits
    │   └── transactions_global.json              88 B     Transaktions-Stats
    └── test-results/
        ├── contact-form-Theme-fab-K01-849ca-...chromium/         (1. Anlauf, gefehlt)
        │   ├── error-context.md                  2,8 KB   Playwright-Diagnostik
        │   └── test-failed-1.png                 70 KB    Screenshot Fail-Zeitpunkt
        └── contact-form-Theme-fab-K01-849ca-...chromium-retry1/  (Retry, bestanden)
            └── trace.zip                         426 KB   Vollständiger Playwright-Trace
```

Gesamtgrösse: **≈ 2,6 MB**.

## Was dieser Snapshot **nicht** enthält

- **`trace-report.json`** (419 MB): zu groß für git, ausgelassen. Bei Bedarf
  vor `make clean` aus `artifacts/layer4/trace-report.json` manuell sichern.
- **`trace-report.txt`**: vom `run.sh` seit 2026-04-13 temporär deaktiviert
  (Kommentar im Log). Nicht erzeugt.
- **`perfschema/per-test/`** (525 Dateien, 6,3 MB): sehr granulare
  Per-Test-PerfSchema-Daten, vom Folgelauf reproduzierbar. Nicht ins Snapshot
  übernommen; ggf. bei konkreter Performance-Frage gezielt nachsichern.
- **Layer 1 / 2 / 3 / 5**: nicht Teil dieses Laufs. L2/L3-Snapshot vom selben
  Tag liegt unter [`../2026-05-24T15-54_run/`](../2026-05-24T15-54_run/).

## HTML-Report browsen

```
tar -xzf docs/test-runs/2026-05-24T19-34_run/layer4/playwright-report.tar.gz \
  -C /tmp/l4-report/
xdg-open /tmp/l4-report/playwright-report/index.html
```

Der Report zeigt für die 532 grünen Tests reduzierte Daten (kein Trace bei
passenden Tests) und vollen Trace für den 1 flakigen Test.

## Trace.zip aus dem Retry öffnen

```
podman-compose exec playwright npx playwright show-trace \
  /work/artifacts/layer4/test-results/contact-form-Theme-fab-K01-.../trace.zip
```

Alternativ lokal:
```
npx playwright show-trace \
  docs/test-runs/2026-05-24T19-34_run/layer4/test-results/contact-form-Theme-fab-K01-849ca-ar-zeigt-Pflichtfelder-fab--chromium-retry1/trace.zip
```

## Integritätsprüfung

```
cd docs/test-runs/2026-05-24T19-34_run/
sha256sum -c MANIFEST.sha256
```
