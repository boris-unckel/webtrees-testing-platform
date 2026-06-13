<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Coverage-Snapshot: Layer 2 vs Layer 3 — 2026-05-24

> **Datum:** 2026-05-24 (Erhebungs-Tag).
> **Quell-Stände:**
>
> - **L2-Clover-XML:** `docs/test-runs/2026-05-24T15-54_run/layer2/coverage.xml`
>   (3,3 MB, erzeugt durch `make test-unit` aus dem `make test-all`-Lauf
>   gestartet 2026-05-24T15:54:23+02:00).
> - **L3-Clover-XML:** `docs/test-runs/2026-05-24T15-54_run/layer3/layer3-coverage.xml`
>   (3,4 MB, erzeugt durch `make test-integration`, gleicher Lauf).
> - **Codebase:** `upstream/webtrees` @ `d123a1b789e29872d6736ece1d9d47cb0a038e8c`
>   (im Testing-Platform-Repo als read-only Mount unter `upstream/webtrees/`).
> - **Erhebungs-Skript:** [`scripts/coverage/clover_aggregate.sh`](../../../scripts/coverage/clover_aggregate.sh).
>
> **Self-contained:** Alle Werte sind inline. Eine spätere Löschung von
> `docs/test-runs/` ändert nichts an der Lesbarkeit dieses Reports.

---

## 1 Gesamt

| Metrik                       | Layer 2 (Komponententest, SQLite) | Layer 3 (KIT, MySQL) | Δ |
|---|---:|---:|---:|
| Statements gesamt            | 44 067 | 44 072 | +5 |
| Covered Statements           | 13 203 | 21 882 | +8 679 |
| **Anweisungsüberdeckung**    | **29,96 %** | **49,65 %** | **+19,69 pp** |
| Methods gesamt               | 4 434 | 4 434 | 0 |
| Methods covered              | 1 198 (27,02 %) | 2 013 (45,40 %) | +815 |
| Elements gesamt              | 48 501 | 48 506 | +5 |
| Elements covered             | 14 401 (29,69 %) | 23 895 (49,26 %) | +9 494 |
| Dateien (gezählt)            | 1 365 | 1 365 | 0 |
| Klassen (gezählt)            | 1 165 | 1 165 | 0 |
| Conditionals                 | 0 / 0 | 0 / 0 | (nicht eingesammelt) |

**Grobbild:** L3 deckt deutlich mehr als L2 — der Layer-Übergang gewinnt
ca. **19,7 Prozentpunkte Statement-Coverage** und **18,4 Prozentpunkte
Method-Coverage** hinzu. Die Abweichung von `+5` bei Statements/Elements
zwischen L2 und L3 entsteht durch leicht unterschiedliche Klassendynamik beim
Stack-Setup (`autoload`-Verzweigungen, die in L2 ohne DB-Setup nicht erreicht
werden) und bleibt unter 0,02 % — irrelevant für die Lesart.

Die Diskussion „Layer 2 und Layer 3 sind komplementär" aus dem April-Snapshot
gilt nicht mehr in derselben Form: L3 deckt heute viele frühere L2-Stärken
**mit** ab (siehe §3, insbesondere `Http/`). Die starke Komplementarität von
April resultierte aus dem Fork-Branch-`port-layer2-test-doubles`, der nicht
weiterverfolgt wird.

---

## 2 Vergleich gegenüber April 2026-04-11

| Metrik | April | Heute | Δ |
|---|---:|---:|---:|
| L2 statements covered | 17 527 | **13 203** | **−4 324** |
| L2 Anweisungsüberdeckung | 39,82 % | **29,96 %** | **−9,86 pp** |
| L3 statements covered | 17 540 | **21 882** | **+4 342** |
| L3 Anweisungsüberdeckung | 39,83 % | **49,65 %** | **+9,82 pp** |
| L2 + L3 unique (Schätzung) | ≈ 23 000 | ≈ 24 000 | ≈ +1 000 |

**Auslesung:** L2 hat **9,9 pp Coverage verloren**, L3 hat **9,8 pp gewonnen**
— in absoluten Zahlen nahezu symmetrisch (−4324 vs +4342). Die L2-Verluste
sind direkt auf die Nicht-Übernahme des Fork-Branchs `port-layer2-test-doubles`
zurückzuführen. Die L3-Gewinne kommen aus der Wachstumsphase seit April
(82 → 156 L3-Test-Dateien, +90 %) — siehe
[`2026-05-24_gap-analyse.md`](../2026-05-24_gap-analyse.md) §2.1.

---

## 3 Vergleich nach Bereichen (Stand 2026-05-24)

| Bereich | Dts | Stmt | L2 cov | L2 % | L3 cov | L3 % | Δ % | Δ Cov |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| `app/Http`              | 381 |  9 013 | 1 850 | **20,5 %** | 6 576 | **72,9 %** | **+52,4 pp** | +4 726 |
| `app/Module`            | 259 | 10 531 | 1 294 | 12,3 %     | 3 438 | 32,6 %     | +20,3 pp     | +2 144 |
| `app/` (Root, flat)     |  50 |  6 612 | 2 208 | 33,4 %     | 3 243 | 49,0 %     | +15,6 pp     | +1 035 |
| `app/Services`          |  37 |  5 732 |   786 | 13,7 %     | 3 390 | 59,1 %     | +45,4 pp     | +2 604 |
| `app/Report`            |  28 |  3 128 | 2 475 | 79,1 %     | 1 808 | 57,8 %     | −21,3 pp     | −667 |
| `app/Census`            | 197 |  2 552 | 2 546 | 99,8 %     |    55 |  2,2 %     | −97,6 pp     | −2 491 |
| `app/Elements`          | 216 |  1 575 | 1 368 | 86,9 %     |   541 | 34,3 %     | −52,5 pp     | −827 |
| `app/Cli`               |  16 |    927 |     0 | 0,0 %      |   700 | 75,5 %     | +75,5 pp     | +700 |
| `app/CustomTags`        |  20 |    825 |     0 | 0,0 %      |   802 | 97,2 %     | +97,2 pp     | +802 |
| `app/Date`              |   9 |    735 |    49 | 6,7 %      |   591 | 80,4 %     | +73,8 pp     | +542 |
| `app/Factories`         |  28 |    736 |   291 | 39,5 %     |   494 | 67,1 %     | +27,6 pp     | +203 |
| `app/Schema`            |  51 |    625 |     0 | 0,0 %      |    24 |  3,8 %     | +3,8 pp      | +24 |
| `app/Statistics`        |   1 |    504 |     0 | 0,0 %      |     0 |  0,0 %     | 0,0 pp       | 0 |
| `app/SurnameTradition`  |  10 |    255 |   229 | 89,8 %     |    59 | 23,1 %     | −66,7 pp     | −170 |
| `app/Encodings`         |  16 |     80 |    40 | 50,0 %     |    21 | 26,2 %     | −23,7 pp     | −19 |
| `app/CommonMark`        |   7 |     58 |     4 |  6,9 %     |    15 | 25,9 %     | +19,0 pp     | +11 |
| `app/Exceptions`        |   4 |     28 |     0 |  0,0 %     |    10 | 35,7 %     | +35,7 pp     | +10 |
| `app/GedcomFilters`     |   1 |     27 |     0 |  0,0 %     |    22 | 81,5 %     | +81,5 pp     | +22 |
| `app/Helpers`           |   1 |     17 |     0 |  0,0 %     |    16 | 94,1 %     | +94,1 pp     | +16 |
| `app/Contracts`         |  32 |      0 |     — |   —        |     — |   —        |   —          | 0 |

### 3.1 Http-Untergruppen

| Bereich | Dts | Stmt | L2 cov | L2 % | L3 cov | L3 % | Δ Cov |
|---|---:|---:|---:|---:|---:|---:|---:|
| `Http/RequestHandlers` | 335 | 8 065 | 1 395 | 17,3 % | 5 774 | 71,6 % | +4 379 |
| `Http/Middleware`      |  34 |   545 |    81 | 14,9 % |   404 | 74,1 % | +323 |
| `Http/Routes`          |   2 |   370 |   369 | 99,7 % |   370 | 100,0 % | +1 |
| `Http/Exceptions`      |   8 |    13 |     0 |  0,0 % |     9 | 69,2 % | +9 |

L3-Middleware-Coverage springt **von 9 % (April) auf 74 %** — die Middleware-
Reihe `M01–M29` (siehe [Abdeckungs-Snapshot](../2026-05-24_abdeckung-snapshot.md))
ist seit April systematisch durch L3-Tests aufgewertet worden.

### 3.2 Module-Untergruppen (Name-Pattern)

| Kategorie | N | Stmt | L2 cov | L2 % | L3 cov | L3 % | Δ Cov |
|---|---:|---:|---:|---:|---:|---:|---:|
| `Module/Language*`            | 73  | 1 627 |   0 |  0,0 % |   339 | 20,8 % | +339 |
| `Module/*Trait`               | 22  |   528 |  93 | 17,6 % |   197 | 37,3 % | +104 |
| `Module/Abstract*`            |  2  |   402 |  13 |  3,2 % |   194 | 48,3 % | +181 |
| `Module/*History*` u. Royal   |  5  |   301 | 284 | 94,4 % |    10 |  3,3 % | −274 |
| `Module/InteractiveTree/`     |  1  |   155 |   0 |  0,0 % |   134 | 86,5 % | +134 |
| `Module/<sonstige flat>`      | 156 | 7 518 | 904 | 12,0 % | 2 564 | 34,1 % | +1 660 |

**Lesehinweis:** Die History-Module (`BritishSocialHistory`, `FrenchHistory`,
`CzechMonarchsAndPresidents`, `BritishPrimeMinisters`, `EnglishMonarchs`) sind
reine Daten-Module — sie sind durch direkte Instanzierung in L2 abgedeckt, aber
in L3 nicht erreicht, weil sie zur Laufzeit nur über Modul-Discovery angetippt
werden. Eine systematische L3-Abdeckung wäre möglich, hat aber keinen
Mehrwert über den L2-Direkttest hinaus.

---

## 4 Top-10 Einzeldateien: Größte Verluste L2→L3

| Datei | Stmt | L2 cov | L3 cov | ΔCov |
|---|---:|---:|---:|---:|
| `app/StatisticsData.php`                         | 1 781 | 1 134 |   253 | −881 |
| `app/Module/StatisticsChartModule.php`           |   474 |   449 |   130 | −319 |
| `app/Elements/GovIdType.php`                     |   277 |   277 |     0 | −277 |
| `app/Statistics.php`                             | 1 087 |   264 |     3 | −261 |
| `app/Report/ReportParserGenerate.php`            | 1 237 | 1 056 |   804 | −252 |
| `app/Elements/TempleCode.php`                    |   161 |   161 |     0 | −161 |
| `app/Module/CzechMonarchsAndPresidents.php`      |   104 |   101 |     2 |  −99 |
| `app/Module/FrenchHistory.php`                   |   100 |    96 |     2 |  −94 |
| `app/Report/RightToLeftSupport.php`              |   640 |   379 |   286 |  −93 |
| `app/Module/BritishPrimeMinisters.php`           |    85 |    81 |     2 |  −79 |

**Profil:** Statics, History-Module und Element-Datentabellen. Diese Dateien
sind L2-`@dataProvider`-getrieben (Census-/History-Tabellen) oder direkter
Methodenaufruf in `*Test.php`. In L3 werden sie nur indirekt über Modul-
Discovery / Report-Rendering angekratzt.

## 5 Top-10 Einzeldateien: Größte Gewinne L2→L3

| Datei | Stmt | L2 cov | L3 cov | ΔCov |
|---|---:|---:|---:|---:|
| `app/Gedcom.php`                                      |   704 |   0 |   636 | +636 |
| `app/Services/RelationshipService.php`                | 1 523 |   0 |   515 | +515 |
| `app/Services/IndividualFactsService.php`             |   550 |   0 |   464 | +464 |
| `app/Services/GedcomImportService.php`                |   467 |   0 |   408 | +408 |
| `app/Http/RequestHandlers/RenumberTreeAction.php`     |   432 |   0 |   361 | +361 |
| `app/Individual.php`                                  |   381 |   0 |   327 | +327 |
| `app/Module/RelationshipsChartModule.php`             |   333 |   0 |   249 | +249 |
| `app/Module/FanChartModule.php`                       |   219 |   0 |   199 | +199 |
| `app/CustomTags/GedcomL.php`                          |   198 |   0 |   197 | +197 |
| `app/Module/AbstractIndividualListModule.php`         |   363 |   0 |   174 | +174 |

**Profil:** Services, Chart-Module und Domain-Objekte (`Gedcom.php`,
`Individual.php`). Diese Klassen lassen sich ohne MySQL-Tree-Kontext kaum
sinnvoll testen — die L3-MySQL-Integration ist hier struktureller Pflichtweg.

---

## 6 Blinde Flecken beider Layer

| Bereich / Datei | L2 % | L3 % | Anmerkung |
|---|---:|---:|---|
| `app/Statistics/Service/CountryService.php` (504 stmt) | 0,0 % | 0,0 % | Country-Statistik-Service vollständig blind |
| `app/Schema/Migration0.php` (298 stmt)                  | 0,0 % | 0,0 % | DB-Bootstrap-Migration, nicht aus PHPUnit erreicht |
| `app/Schema/Migration44.php` (105 stmt)                 | 0,0 % | 0,0 % | aktuelle DB-Migration |
| `app/Statistics.php` (1 087 stmt)                       | 24,3 % | 0,3 % | Quasi blind in L3, mäßig in L2 |
| `app/Module/<sonstige>` (z. T. 0/0)                     | wechselnd | wechselnd | Spezielle Theme-/Sidebar-Module ohne Tests |

**Schwerpunkte für die nächste Schliessung:**

- `Schema/Migration*`: keine Tests existieren — Migration könnte über einen
  Fresh-Install-Smoke (Wizard-Lauf in L4) indirekt verifiziert werden.
- `app/Statistics/Service/CountryService.php`: 504 stmt vollständig ungetestet
  — als `EP`-Test direkt instanzierbar.

---

## 7 Methodik

### 7.1 Aggregat-Reproduktion

```bash
# Wurzel-Metriken (key=value)
scripts/coverage/clover_aggregate.sh totals \
  docs/test-runs/2026-05-24T15-54_run/layer2/coverage.xml

# Per-Verzeichnis (Prefix muss absoluter Container-Pfad sein)
scripts/coverage/clover_aggregate.sh by-prefix \
  docs/test-runs/2026-05-24T15-54_run/layer2/coverage.xml \
  /var/www/html/app/Http/

# Per-Datei CSV (file, statements, coveredstatements, methods, coveredmethods, elements, coveredelements)
scripts/coverage/clover_aggregate.sh files-csv \
  docs/test-runs/2026-05-24T15-54_run/layer2/coverage.xml
```

### 7.2 Top-10-Listen-Reproduktion

```bash
# Join L2 vs L3 per Datei und sortieren nach ΔCov
scripts/coverage/clover_aggregate.sh files-csv "$L2_XML" | tail -n +2 > /tmp/l2.csv
scripts/coverage/clover_aggregate.sh files-csv "$L3_XML" | tail -n +2 > /tmp/l3.csv
awk -F, 'FNR==NR{l2[$1]=$3; stmt[$1]=$2; next}
         { if ($1 in l2) printf "%d\t%d\t%d\t%d\t%s\n", stmt[$1], l2[$1], $3, $3-l2[$1], $1 }' \
    /tmp/l2.csv /tmp/l3.csv | sort -k4 -n -r | head -10  # Gewinne
```

### 7.3 Stand der Conditional-Coverage

Beide Clover-XMLs zeigen `conditionals="0"` und `coveredconditionals="0"`.
PHPUnit erfasst Branch-Conditionals in der Standard-Konfiguration nicht — die
Spalte ist Platzhalter ohne Aussage. Branch-Daten kämen aus einer separaten
Path-Coverage-Erhebung (`pcov` mit `coverage.directives` oder Xdebug Path
Coverage), die in diesem Repo nicht aktiviert ist.

---

## 8 Anschlussverweise

- [`2026-05-24_abdeckung-snapshot.md`](../2026-05-24_abdeckung-snapshot.md) —
  Feature-Matrix-Stand (216 / 2 / 1 / 219).
- [`2026-05-24_gap-analyse.md`](../2026-05-24_gap-analyse.md) —
  Test-Inventar mit Hybrid-V2-Klassifikation.
- [`historical/2026-04-11_layer2-vs-layer3.md`](2026-04-11_layer2-vs-layer3.md)
  — historischer Vorgänger (April-Werte, Fork-Branch-basiert).
