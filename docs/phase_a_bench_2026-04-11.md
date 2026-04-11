<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Phase-A-Bench — Bind-Mount-Performance-Baseline

**Datum:** 2026-04-11
**Bezug:** `docs/skript_log_plan.md` Phase A-Mess
**Zweck:** Entscheidungsgrundlage für Phase A — Bind-Mount `./artifacts:/artifacts:rw,z`
im `webtrees`-Service oder Fallback über `podman cp`.

## Rahmen

- **Szenarien:** 2 (Baseline ohne Mount, Vergleich mit Mount)
- **Messkommandos:**
  - Layer 2: `time make test-unit` (volle Suite)
  - Layer 3: `time make test-integration-quick` (3 Cases Smoke-Subset)
- **Läufe:** 3 pro Szenario, Median
- **Schwellen:** Grün ≤ +5 %, Gelb 5–20 %, Rot > 20 %
- **Entscheidung:** Alle gemessenen Layer grün → Pfad A-1 (Mount committen); sonst → Pfad A-2 (Fallback)

## Methode — Zeitmessung

Jeder Lauf wird mit `time make <target>` aus der Host-Shell gestartet.
Aus der `time`-Ausgabe wird die `real`-Zeit (Wall-Clock) entnommen und in
Sekunden notiert. Median über 3 Läufe dient als Vergleichswert.

`make clean && make up && make setup` wird **einmal vor** jedem
Szenario-Block ausgeführt, damit der Container-Stack frisch startet und
Caches gleichen Stand haben.

## Baseline — Status quo (ohne `./artifacts`-Mount im webtrees-Service)

**Compose-Stand:** `webtrees`-Service hat nur `./artifacts/security-trace` im
Volumes-Block (keine vollen `/artifacts`-Bind-Mounts).

### Layer 2 — `make test-unit`

| Lauf | real-Zeit (s) | Anmerkung |
|---|---|---|
| 1 | 287,693 | exit 0, volle Suite (3800 Tests) |
| 2 | 278,943 | exit 0 |
| 3 | 281,103 | exit 0 |
| **Median** | **281,103** | |

### Layer 3 — `make test-integration-quick`

| Lauf | real-Zeit (s) | Anmerkung |
|---|---|---|
| 1 | 110,965 | exit 0, PHPUnit 01:49.960 |
| 2 | 291,469 | exit 0, PHPUnit 04:50.432 (deutlich langsamer) |
| 3 | 219,401 | exit 0, PHPUnit 03:38.334 |
| **Median** | **219,401** | Hohe Streuung: min 110 s, max 291 s (Faktor ~2,6) |

**Anmerkung:** Die drei Baseline-L3-Läufe zeigen eine hohe Streuung
(Faktor ~2,6 zwischen Min und Max). Ursache vermutlich PHPUnit +
pcov + MySQL in Kombination — die Coverage-Generierung scheint
nicht-deterministisch. Die +5%-Grenze für den Grün-Schwellwert ist
unter diesen Bedingungen nicht aussagekräftig — bei der Auswertung
wird zusätzlich auf Plausibilität vs. Streubreite geprüft.

## Vergleichslauf — mit `./artifacts:/artifacts:rw,z`-Bind-Mount

**Compose-Stand:** `webtrees`-Service hat zusätzlich
`- ./artifacts:/artifacts:rw,z` im Volumes-Block.

### Layer 2 — `make test-unit`

| Lauf | real-Zeit (s) | Anmerkung |
|---|---|---|
| 1 | 285,422 | exit 0 |
| 2 | 277,338 | exit 0 |
| 3 | 273,131 | exit 0 |
| **Median** | **277,338** | |

### Layer 3 — `make test-integration-quick`

| Lauf | real-Zeit (s) | Anmerkung |
|---|---|---|
| 1 | 111,572 | exit 0, PHPUnit 01:50.568 |
| 2 | 212,847 | exit 0, PHPUnit 03:31.843 |
| 3 | 220,847 | exit 0, PHPUnit 03:39.817 |
| **Median** | **212,847** | |

## Auswertung

### Abweichung

| Layer | Baseline-Median (s) | Vergleichs-Median (s) | Abweichung | Ampel |
|---|---|---|---|---|
| 2 (test-unit) | 281,103 | 277,338 | **−1,34 %** | Grün |
| 3 (test-integration-quick) | 219,401 | 212,847 | **−2,99 %** | Grün |

Rechnung: `(Vergleich − Baseline) / Baseline × 100`.
Negative Werte = Vergleichslauf ist schneller als Baseline.

### Schwellen-Anwendung

- Grün ≤ +5 %
- Gelb 5–20 %
- Rot > 20 %

**Beide Layer liegen im Grün-Bereich.** Der Bind-Mount verursacht keine
messbare Verlangsamung. Tatsächlich liegen beide Median-Werte **unter**
der Baseline — was angesichts der beobachteten L3-Streubreite
(Baseline 110–291 s; Vergleich 111–221 s) als Rauschen zu werten ist
und nicht als reale Beschleunigung.

## Entscheidung

**Ergebnis:** Grün auf beiden gemessenen Layern.

**Gewählter Pfad:** **Pfad A-1** — `./artifacts:/artifacts:rw,z`-Bind-Mount
im `webtrees`-Service **committen**.

**Begründung:**
1. Beide Medianwerte zeigen keinen negativen Trend (beide im Grün).
2. Die L3-Streuung ist in beiden Szenarien vergleichbar groß — die
   Non-Determinismus kommt aus PHPUnit + pcov + MySQL, nicht aus dem
   Mount. Der Mount erhöht die Streuung nicht.
3. Live-Monitoring (`tail -f artifacts/layer3/phpunit-integration.xml`)
   wird mit Pfad A-1 möglich; Pfad A-2 hätte dieses Feature bewusst
   verworfen.
4. Mehrere `podman cp`-Aufrufe können entfallen (Makefile-Targets
   `test-unit`, `test-integration` — siehe Phase A Pfad A-1).

## Anmerkungen

- Die Messungen laufen auf derselben Hardware, während sonst kein anderer
  Testlauf aktiv ist (Exklusivität gemäß CLAUDE.md).
- `artifacts/traces.json` wächst während der Läufe (OTel-Collector
  schreibt unabhängig). Das ist für Layer 2/3 irrelevant (SQLite/MySQL,
  kein OTel-Export auf PHP-CLI).
- Die L3-Streubreite (Faktor ~2,6) taucht in **beiden** Szenarien auf
  (Baseline wie Vergleich). Beide haben einen "schnellen Erstlauf"
  (~110 s), danach Werte im Bereich 200–300 s. Wahrscheinlich
  Container-Cache-Effekt (opcache, Filesystem-Cache) oder MySQL-Warmup.

## Anmerkungen

- Die Messungen laufen auf derselben Hardware, während sonst kein anderer
  Testlauf aktiv ist (Exklusivität gemäß CLAUDE.md).
- `artifacts/traces.json` wächst während der Läufe (OTel-Collector
  schreibt unabhängig). Das ist für Layer 2/3 irrelevant (SQLite/MySQL,
  kein OTel-Export auf PHP-CLI).
