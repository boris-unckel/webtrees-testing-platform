<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Test-Run-Snapshot 2026-05-24T15-54

Snapshot der Roh-Artefakte aus dem `make test-all`-Lauf, gestartet am
**2026-05-24T15:54:23+02:00**, beendet ≈ **16:45:59+02:00**. Findings-Protokoll
dazu: [../2026-05-24T15-54_make-test-all-findings.md](../2026-05-24T15-54_make-test-all-findings.md).

Zweck dieses Ordners: persistente Sicherung der Test-Roh-Ergebnisse, weil
`artifacts/` zwischen Läufen überschrieben wird.

## Quick-Bilanz pro Layer

| Layer | Tool                          | Tests | Assertions | Failures | Warnings | Suite-Laufzeit       | Exit |
|-------|-------------------------------|------:|-----------:|---------:|---------:|----------------------|-----:|
| 2     | PHPUnit `layer2-unit` (SQLite) | 3 289 |    150 424 |        0 |       70 | 259,28 s ≈ 4 min 19 s | 0    |
| 3     | PHPUnit `layer3-integration` (MySQL) | 1 327 |  5 358 |        4 |      —   | 2 751,59 s ≈ 45 min 52 s | 1    |

L3 hatte zusätzlich 30 PHPUnit-Notices und 1 Deprecation
(`CheckTree.php:209`). L4 und L5 sind in diesem Lauf nicht gestartet
(Abbruch durch L3-Exit-Code) — separate L4-Sicherung steht aus.

## Coverage (Roh-Werte aus Clover XML)

| Metrik              | L2 (Unit)            | L3 (Integration)     |
|---------------------|----------------------|----------------------|
| Tests gesamt        | 3 289                | 1 327                |
| `statements`        | 13 203 / 44 067 = **29,96 %** | 21 882 / 44 072 = **49,65 %** |
| `methods`           | 1 198 /  4 434 = **27,02 %** | 2 013 /  4 434 = **45,40 %** |
| `elements` (total)  | 14 401 / 48 501 = **29,69 %** | 23 895 / 48 506 = **49,26 %** |
| Klassen             | 1 165                | 1 165                |
| `loc` / `ncloc`     | 152 189 / 110 223    | 152 189 / 110 223    |
| `conditionals`      | 0 / 0                | 0 / 0                |

**Vergleich zur Baseline aus Memory-Stand 2026-04-11 (Commit 72bb731):**
L2 39,82 % → **29,69 %** (−10,1 pp), L3 39,83 % → **49,26 %** (+9,4 pp).
Verschiebung passt zur „improve-layer3"-Arbeit der letzten Wochen
(Tests in L3 migriert / dort neu angelegt) zzgl. Upstream-Refresh, der
ein paar Elemente verändert hat (48 506 vs. 48 501 — bei identischen
Klassenzahlen ist die Codebase-Größe quasi unverändert).

**HTML-Report L2:** liegt entpackbar in `layer2/coverage-html.tar.gz`
(5,4 MB komprimiert ≈ 101 MB entpackt).

**HTML-Report L3:** vom L3-Runner nicht erzeugt — `run.sh` ruft nur
`--coverage-clover`, kein `--coverage-html`. Ggf. nachträglich via PHPUnit
mit hinterlegtem Coverage-Cache rekonstruierbar.

## Was dieser Snapshot **enthält**

```
docs/test-runs/2026-05-24T15-54_run/
├── README.md                          (dieses Dokument)
├── MANIFEST.sha256                    (Integritäts-Manifest, 8 Dateien)
├── layer2/
│   ├── coverage.xml                   3,3 MB   Clover-XML L2 (29,69 % elements)
│   ├── phpunit-unit.xml               1,7 MB   JUnit-XML L2 (3 289 Tests)
│   ├── coverage-html.tar.gz           5,4 MB   PHPUnit-HTML-Report L2 (komprimiert)
│   └── run-output-excerpt.log         524 KB   L2-Sektion aus Test-all-Log (Zeilen 227–5236)
└── layer3/
    ├── phpunit-integration.xml        520 KB   JUnit XML L3 (1 327 Tests, 4 Failures)
    ├── layer3-coverage.xml            3,4 MB   Clover XML L3 (49,26 % elements)
    ├── silent-tests-sweep.txt         1,9 KB   13 Treffer aus Spürnasen-Sweep
    └── run-output-excerpt.log         8,3 KB   L3-Sektion aus Test-all-Log (Zeilen 5238–5327)
```

Gesamtgrösse: **≈ 15 MB**.

## Was dieser Snapshot **nicht** enthält

- **Layer 1**: PHPStan/PHPCS/Trivy-Reports unter `artifacts/layer1/`. Statisch
  bestanden (0 PHPStan-Errors, 2 148 PHPCS-Verstöße im Upstream-Code
  informell). Nicht hier gesichert, da `make test-all` für L1 nur Pass/Fail-
  Status meldet und keine Hauptartefakte produziert, die L2/L3-spezifisch wären.
- **Layer 4 / Layer 5**: in diesem `make test-all`-Lauf nicht gestartet
  (Abbruch durch L3-Exit-Code). L4 wurde separat um 19:34 auf frisch
  aufgesetztem Stack gefahren — Findings:
  [`../2026-05-24T19-34_make-test-e2e-findings.md`](../2026-05-24T19-34_make-test-e2e-findings.md),
  Snapshot: [`../2026-05-24T19-34_run/`](../2026-05-24T19-34_run/).
- **Volle Test-all-Log-Datei** (`artifacts/test-all-2026-05-24T15-54-23.log`,
  557 KB): bleibt unter `artifacts/`, nur die L2- und L3-Sektionen sind hier
  extrahiert. Beim nächsten `make clean` wird das Original entsorgt — bei
  Bedarf vorher manuell sichern.
- **DB-Dump nach L3-Fail**: Der L3-Runner versucht bei Failure einen
  `mysqldump` nach `artifacts/layer3/db-dump.sql`. In diesem Lauf war die
  Datei 0 Bytes (`${MYSQL_PASSWORD}` im Runner-Env war vermutlich nicht gesetzt
  oder `mysqldump` schweigt mit `|| true`). Daher kein DB-Snapshot mit dabei.

## Failure-Übersicht Layer 3 (verlinkt zum Findings-Doc)

| # | Test                                                                                              | Kategorie                  |
|--:|---------------------------------------------------------------------------------------------------|----------------------------|
| 1 | `AutoCompleteIntegrationTest::test_autocomplete_citation_returns_json_for_valid_source`           | **NEU** — Upstream-Bug, honest red |
| 2 | `LoginActionIntegrationTest::test_per_user_rate_limit_fires_after_threshold`                      | FAILURE_PIN §i.7 (SEC-AUDIT-008) |
| 3 | `LoginActionIntegrationTest::test_site_wide_rate_limit_fires_for_unknown_users`                   | FAILURE_PIN §i.7 (SEC-AUDIT-008) |
| 4 | `RenumberTreeActionIntegrationTest::test_malformed_xref_is_skipped_not_renamed`                   | FAILURE_PIN §i.7 (SEC-AUDIT-006) |

Vollständige Befundlage:
[../2026-05-24T15-54_make-test-all-findings.md](../2026-05-24T15-54_make-test-all-findings.md).

## L2-Auffälligkeit: 70 Warnings (Lang-Include-Failures)

Über den L2-Lauf hinweg ≈ 70 gleiche Warnings dieser Form:

```
Warning: include(/var/www/html/app/../resources/lang/<locale>/messages.php):
Failed to open stream: No such file or directory in
/var/www/html/vendor/fisharebest/localization/src/Translation.php on line 60
```

Locales betroffen (Auszug): `hu`, `nl`, `pl`, `pt`, `sk`, `fi`, `sv`, `vi`,
`tr`, `el`, `bg`, `kk`, `ru`, `uk`, `ka`, `he`, `ar`, `hi`, `zh-Hans`,
`zh-Hant`. Test-Status: alle 3 289 Tests bestanden — die `localization`-Lib
hat einen Fallback. Hintergrund-Vermutung im Findings-Doc.

Komplette Liste ist in `layer2/run-output-excerpt.log` erhalten.

## Reproduktion

Aus diesen Roh-Artefakten lassen sich nachträglich erzeugen:

- **CRAP-Report L3** (`make crap-report`): operiert auf
  `${COVERAGE_DIR}/layer3-coverage.xml`. Soll der Report gegen diesen
  konkreten Snapshot laufen, vorher XML ins `coverage-data`-Volume schieben:
  ```
  podman-compose cp \
    docs/test-runs/2026-05-24T15-54_run/layer3/layer3-coverage.xml \
    webtrees:/coverage/layer3-coverage.xml
  ```
- **Coverage-Diff** (L2 vs. L3, oder gegen frühere Snapshots): direkt aus
  den Clover-XML-Header-Zeilen (`<metrics .../>`).
- **L2-HTML-Report browsen**:
  ```
  tar -xzf docs/test-runs/2026-05-24T15-54_run/layer2/coverage-html.tar.gz \
    -C /tmp/l2-coverage/
  xdg-open /tmp/l2-coverage/coverage-html/index.html
  ```
- **JUnit-Auswertung** (z. B. Per-Klasse-Laufzeiten): direkt mit
  `xmllint --xpath` über `phpunit-unit.xml` bzw. `phpunit-integration.xml`.

## Integritätsprüfung

```
cd docs/test-runs/2026-05-24T15-54_run/
sha256sum -c MANIFEST.sha256
```
