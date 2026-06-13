<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Coverage-Snapshot: Layer 2 vs Layer 3 — 2026-06-13

> **Datum:** 2026-06-13 (Erhebungs-Tag).
> **Quell-Stände:**
>
> - **L2-Clover-XML:** `artifacts/layer2/coverage.xml` (3,3 MB, erzeugt durch
>   `make test-unit` aus dem `make test-all`-Lauf am 2026-06-13).
> - **L3-Clover-XML:** `/coverage/layer3-coverage.xml` im `webtrees`-Container
>   (3,3 MB, erzeugt durch `make test-integration` am 2026-06-13, Post-Fix-Stand;
>   via `podman cp` geholt). Der Lauf endete mit Exit 2 — das sind die 4 bekannten
>   FAILURE_PINs (AutoComplete, LoginAction ×2, RenumberTree), **0 Errors**;
>   die Coverage wird unabhängig vom Exit-Code erzeugt.
> - **Codebase:** `upstream/webtrees` @ `7ed6f4884983ba4d26aa5564b625b66aff022fbb`
>   (im Testing-Platform-Repo als read-only Mount unter `upstream/webtrees/`).
> - **Erhebungs-Skript:** [`scripts/coverage/clover_aggregate.sh`](../../scripts/coverage/clover_aggregate.sh).
>
> **Self-contained:** Alle Werte sind inline. Die Quell-XMLs sind nicht im Repo
> archiviert (die `docs/test-runs/`-Lineage wurde bewusst entfernt) — dieser
> Report bleibt dennoch vollständig lesbar.

---

## 1 Gesamt

| Metrik                       | Layer 2 (Komponententest, SQLite) | Layer 3 (KIT, MySQL) | Δ |
|---|---:|---:|---:|
| Statements gesamt            | 43 926 | 43 931 | +5 |
| Covered Statements           | 13 232 | 21 864 | +8 632 |
| **Anweisungsüberdeckung**    | **30,12 %** | **49,77 %** | **+19,65 pp** |
| Methods gesamt               | 4 531 | 4 531 | 0 |
| Methods covered              | 1 282 (28,29 %) | 2 035 (44,91 %) | +753 |
| Elements gesamt              | 48 457 | 48 462 | +5 |
| Elements covered             | 14 514 (29,95 %) | 23 899 (49,31 %) | +9 385 |
| Dateien (gezählt)            | 1 388 | 1 388 | 0 |
| Klassen (gezählt)            | 1 180 | 1 180 | 0 |
| Conditionals                 | 0 / 0 | 0 / 0 | (nicht eingesammelt) |

**Grobbild:** Unverändert zum Mai-Snapshot deckt L3 deutlich mehr als L2 — der
Layer-Übergang gewinnt **+19,6 pp Statement-Coverage** und **+16,6 pp
Method-Coverage** hinzu. Die `+5`-Abweichung bei Statements/Elements entsteht
durch leicht unterschiedliche Klassendynamik beim Stack-Setup (`autoload`-
Verzweigungen, die in L2 ohne DB-Setup nicht erreicht werden) und bleibt unter
0,02 % — irrelevant für die Lesart.

---

## 2 Vergleich gegenüber dem direkten Vorgänger (2026-05-24)

| Metrik | 2026-05-24 | 2026-06-13 | Δ |
|---|---:|---:|---:|
| L2 statements covered | 13 203 | **13 232** | **+29** |
| L2 Anweisungsüberdeckung | 29,96 % | **30,12 %** | **+0,16 pp** |
| L3 statements covered | 21 882 | **21 864** | **−18** |
| L3 Anweisungsüberdeckung | 49,65 % | **49,77 %** | **+0,12 pp** |
| Statements gesamt (Nenner) | 44 067 / 44 072 | **43 926 / 43 931** | **−141** |

**Auslesung:** Beide Layer sind ggü. dem Vorgänger **praktisch stabil**. Die
April→Mai-Verschiebung (L2 −9,9 pp durch Nicht-Übernahme des Fork-Branchs
`port-layer2-test-doubles`) ist abgeschlossen; es gibt keine weitere Drift.

Die nominale L3-Bewegung (−18 covered, aber +0,12 pp) ist ein **Nenner-Effekt**:
Der Upstream-Report-Refactor (PR #5389, `main` @ `7ed6f48`) hat ~141 Statements
aus dem Code entfernt/umstrukturiert. Hinzu kommt die **L3-Testanpassung dieser
Session** an die neue Report-API (Umbenennung `ReportParserGenerate` →
`ParserGenerate`, `RightToLeftSupport` → `RightToLeftFormatter`; Port der
HTML-Objekt-Tests auf Enums/`Style`/`ReportConfig`; Entfernung des nicht mehr
öffentlich testbaren `ReportPdfObjectsIntegrationTest`). Netto nahezu neutral.

---

## 3 Vergleich nach Bereichen (Stand 2026-06-13)

| Bereich | Dts | Stmt | L2 cov | L2 % | L3 cov | L3 % | Δ % | Δ Cov |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| `app/Module`            | 259 | 10 531 | 1 294 | 12,3 %     | 3 410 | 32,4 %     | +20,1 pp     | +2 116 |
| `app/Http`              | 381 |  9 015 | 1 850 | **20,5 %** | 6 568 | **72,9 %** | **+52,3 pp** | +4 718 |
| `app/` (Root, flat)     |  51 |  6 719 | 2 271 | 33,8 %     | 3 336 | 49,7 %     | +15,9 pp     | +1 065 |
| `app/Services`          |  37 |  5 732 |   786 | 13,7 %     | 3 383 | 59,0 %     | +45,3 pp     | +2 597 |
| `app/Report`            |  51 |  2 985 | 2 504 | 83,9 %     | 1 824 | 61,1 %     | −22,8 pp     | −680 |
| `app/Census`            | 197 |  2 552 | 2 546 | 99,8 %     |    55 |  2,2 %     | −97,6 pp     | −2 491 |
| `app/Elements`          | 216 |  1 575 | 1 368 | 86,9 %     |   541 | 34,3 %     | −52,5 pp     | −827 |
| `app/Cli`               |  16 |    927 |     0 | 0,0 %      |   693 | 74,8 %     | +74,8 pp     | +693 |
| `app/CustomTags`        |  20 |    825 |     0 | 0,0 %      |   802 | 97,2 %     | +97,2 pp     | +802 |
| `app/Factories`         |  28 |    736 |   291 | 39,5 %     |   494 | 67,1 %     | +27,6 pp     | +203 |
| `app/Date`              |   9 |    735 |    49 | 6,7 %      |   591 | 80,4 %     | +73,7 pp     | +542 |
| `app/Schema`            |  51 |    625 |     0 | 0,0 %      |    24 |  3,8 %     | +3,8 pp      | +24 |
| `app/Statistics`        |   1 |    504 |     0 | 0,0 %      |     0 |  0,0 %     | 0,0 pp       | 0 |
| `app/SurnameTradition`  |  10 |    255 |   229 | 89,8 %     |    59 | 23,1 %     | −66,7 pp     | −170 |
| `app/Encodings`         |  16 |     80 |    40 | 50,0 %     |    21 | 26,2 %     | −23,8 pp     | −19 |
| `app/CommonMark`        |   7 |     58 |     4 |  6,9 %     |    15 | 25,9 %     | +19,0 pp     | +11 |
| `app/Exceptions`        |   4 |     28 |     0 |  0,0 %     |    10 | 35,7 %     | +35,7 pp     | +10 |
| `app/GedcomFilters`     |   1 |     27 |     0 |  0,0 %     |    22 | 81,5 %     | +81,5 pp     | +22 |
| `app/Helpers`           |   1 |     17 |     0 |  0,0 %     |    16 | 94,1 %     | +94,1 pp     | +16 |
| `app/Contracts`         |  32 |      0 |     — |   —        |     — |   —        |   —          | 0 |

**`app/Report` nach dem Refactor:** Der Bereich ist von 28 auf **51 Dateien**
gewachsen — PR #5389 hat die Renderer-/Element-Klassen aufgespalten (`HtmlCell`,
`PdfCell`, `Style`, `ParserGenerate`, `RightToLeftFormatter` …) und Statements
extrahiert (3 128 → 2 985). L3 deckt jetzt **61,1 %** (vs. 57,8 % im Mai) — die
in dieser Session reparierten Report-Tests greifen wieder; L2 bleibt mit 83,9 %
der Direkttest-Weg für die Element-/Renderer-Klassen.

### 3.1 Http-Untergruppen

| Bereich | Dts | Stmt | L2 cov | L2 % | L3 cov | L3 % | Δ Cov |
|---|---:|---:|---:|---:|---:|---:|---:|
| `Http/RequestHandlers` | 335 | 8 067 | 1 395 | 17,3 % | 5 766 | 71,5 % | +4 371 |
| `Http/Middleware`      |  34 |   545 |    81 | 14,9 % |   404 | 74,1 % | +323 |
| `Http/Routes`          |   2 |   370 |   369 | 99,7 % |   370 | 100,0 % | +1 |
| `Http/` (Root)         |   2 |    20 |     5 | 25,0 % |    19 | 95,0 % | +14 |
| `Http/Exceptions`      |   8 |    13 |     0 |  0,0 % |     9 | 69,2 % | +9 |

### 3.2 Module-Untergruppen (Name-Pattern)

| Kategorie | N | Stmt | L2 cov | L2 % | L3 cov | L3 % | Δ Cov |
|---|---:|---:|---:|---:|---:|---:|---:|
| `Module/<sonstige flat>`      | 157 | 7 505 | 889 | 11,8 % | 2 542 | 33,9 % | +1 653 |
| `Module/Language*`            |  73 | 1 627 |   0 |  0,0 % |   339 | 20,8 % | +339 |
| `Module/*Trait`               |  22 |   528 |  93 | 17,6 % |   197 | 37,3 % | +104 |
| `Module/Abstract*`            |   2 |   402 |  13 |  3,2 % |   194 | 48,3 % | +181 |
| `Module/*History*` u. Royal   |   4 |   314 | 299 | 95,2 % |     4 |  1,3 % | −295 |
| `Module/InteractiveTree/`     |   1 |   155 |   0 |  0,0 % |   134 | 86,5 % | +134 |

**Lesehinweis:** Die History-Module (`BritishSocialHistory`, `FrenchHistory`,
`CzechMonarchsAndPresidents`, `BritishPrimeMinisters`, `EnglishMonarchs`) sind
reine Daten-Module — durch direkte Instanzierung in L2 abgedeckt, in L3 nicht
erreicht (zur Laufzeit nur über Modul-Discovery angetippt). Eine systematische
L3-Abdeckung wäre möglich, hat aber keinen Mehrwert über den L2-Direkttest.

---

## 4 Top-10 Einzeldateien: Größte Verluste L2→L3

| Datei | Stmt | L2 cov | L3 cov | ΔCov |
|---|---:|---:|---:|---:|
| `app/StatisticsData.php`                         | 1 781 | 1 134 |   269 | −865 |
| `app/Module/StatisticsChartModule.php`           |   474 |   449 |   130 | −319 |
| `app/Elements/GovIdType.php`                     |   277 |   277 |     0 | −277 |
| `app/Statistics.php`                             | 1 087 |   264 |     3 | −261 |
| `app/Elements/TempleCode.php`                    |   161 |   161 |     0 | −161 |
| `app/Report/ReportListBuilder.php`               |   268 |   201 |    52 | −149 |
| `app/Report/ParserGenerate.php`                  |   812 |   727 |   613 | −114 |
| `app/Module/CzechMonarchsAndPresidents.php`      |   104 |   101 |     1 |  −100 |
| `app/Module/FrenchHistory.php`                   |   100 |    96 |     1 |  −95 |
| `app/Report/RightToLeftFormatter.php`            |   526 |   361 |   276 |  −85 |

**Profil:** Statics, History-Module und Element-Datentabellen — L2-
`@dataProvider`-getrieben (Census-/History-Tabellen) oder direkter Methodenaufruf
in `*Test.php`. In L3 nur indirekt über Modul-Discovery / Report-Rendering
angekratzt. Die Report-Klassen (`ParserGenerate`, `RightToLeftFormatter`,
`ReportListBuilder`) tragen hier die alte `ReportParserGenerate`/
`RightToLeftSupport`-Last unter neuen Namen (PR #5389).

## 5 Top-10 Einzeldateien: Größte Gewinne L2→L3

| Datei | Stmt | L2 cov | L3 cov | ΔCov |
|---|---:|---:|---:|---:|
| `app/Gedcom.php`                                      |   704 |   0 |   636 | +636 |
| `app/Services/RelationshipService.php`                | 1 523 |   0 |   515 | +515 |
| `app/Services/IndividualFactsService.php`             |   550 |   0 |   464 | +464 |
| `app/Services/GedcomImportService.php`                |   467 |   0 |   396 | +396 |
| `app/Http/RequestHandlers/RenumberTreeAction.php`     |   432 |   0 |   361 | +361 |
| `app/Individual.php`                                  |   381 |   0 |   327 | +327 |
| `app/Module/RelationshipsChartModule.php`             |   333 |   0 |   249 | +249 |
| `app/Module/FanChartModule.php`                       |   219 |   0 |   199 | +199 |
| `app/CustomTags/GedcomL.php`                          |   198 |   0 |   197 | +197 |
| `app/Module/AbstractIndividualListModule.php`         |   363 |   0 |   174 | +174 |

**Profil:** Services, Chart-Module und Domain-Objekte (`Gedcom.php`,
`Individual.php`) — ohne MySQL-Tree-Kontext kaum sinnvoll testbar; die
L3-MySQL-Integration ist hier struktureller Pflichtweg.

---

## 6 Blinde Flecken beider Layer

| Bereich / Datei | L2 % | L3 % | Anmerkung |
|---|---:|---:|---|
| `app/Statistics/Service/CountryService.php` (504 stmt) | 0,0 % | 0,0 % | Country-Statistik-Service vollständig blind |
| `app/Schema/Migration0.php` (298 stmt)                  | 0,0 % | 0,0 % | DB-Bootstrap-Migration, nicht aus PHPUnit erreicht |
| `app/Schema/Migration44.php` (105 stmt)                 | 0,0 % | 0,0 % | aktuelle DB-Migration |
| `app/Statistics.php` (1 087 stmt)                       | 24,3 % | 0,3 % | Quasi blind in L3, mäßig in L2 |
| `app/Module/SiteMapModule.php` (180 stmt)               | 0,0 % | 1,7 % | Theme-/Service-Modul ohne gezielte Tests |
| `app/Module/FamilyTreeStatisticsModule.php` (172 stmt)  | 0,0 % | 2,9 % | Statistik-Block, nur Discovery-Antippen |
| `app/Module/StoriesModule.php` (159 stmt)               | 0,0 % | 3,1 % | Story-Modul ohne gezielte Tests |
| `app/Module/RecentChangesModule.php` (153 stmt)         | 0,0 % | 3,3 % | Block-Modul ohne gezielte Tests |

**Schwerpunkte für die nächste Schließung:**

- `Schema/Migration*`: keine Tests existieren — Migration könnte über einen
  Fresh-Install-Smoke (Wizard-Lauf in L4) indirekt verifiziert werden.
- `app/Statistics/Service/CountryService.php`: 504 stmt vollständig ungetestet
  — als `EP`-Test direkt instanzierbar.
- `app/StatisticsData.php` / `app/Statistics.php`: zusammen die größten
  L3-Lücken; aktuell überwiegend L2-`@dataProvider`-getragen.

---

## 7 Methodik

### 7.1 Aggregat-Reproduktion

```bash
# L3-Coverage aus dem Container holen (make test-integration endet wegen der
# FAILURE_PINs mit Exit 2, vor dem podman-cp-Schritt → manuell holen):
podman cp webtrees:/coverage/layer3-coverage.xml /tmp/l3-current.xml

# Wurzel-Metriken (key=value)
scripts/coverage/clover_aggregate.sh totals artifacts/layer2/coverage.xml
scripts/coverage/clover_aggregate.sh totals /tmp/l3-current.xml

# Per-Verzeichnis (Prefix muss absoluter Container-Pfad sein)
scripts/coverage/clover_aggregate.sh by-prefix artifacts/layer2/coverage.xml \
  /var/www/html/app/Http/

# Per-Datei CSV (file, statements, coveredstatements, methods, coveredmethods, elements, coveredelements)
scripts/coverage/clover_aggregate.sh files-csv artifacts/layer2/coverage.xml
```

### 7.2 Top-10-Listen-Reproduktion

```bash
scripts/coverage/clover_aggregate.sh files-csv artifacts/layer2/coverage.xml | tail -n +2 > /tmp/l2.csv
scripts/coverage/clover_aggregate.sh files-csv /tmp/l3-current.xml          | tail -n +2 > /tmp/l3.csv
awk -F, 'FNR==NR{l2[$1]=$3; stmt[$1]=$2; next}
         { if ($1 in l2) printf "%d\t%d\t%d\t%d\t%s\n", stmt[$1], l2[$1], $3, $3-l2[$1], $1 }' \
    /tmp/l2.csv /tmp/l3.csv | sort -k4 -n -r | head -10  # Gewinne (für Verluste: sort -k4 -n)
```

### 7.3 Stand der Conditional-Coverage

Beide Clover-XMLs zeigen `conditionals="0"` und `coveredconditionals="0"`.
PHPUnit erfasst Branch-Conditionals in der Standard-Konfiguration nicht — die
Spalte ist Platzhalter ohne Aussage. Branch-Daten kämen aus einer separaten
Path-Coverage-Erhebung (`pcov` mit `coverage.directives` oder Xdebug Path
Coverage), die in diesem Repo nicht aktiviert ist.

---

## 8 Anschlussverweise

- [`2026-05-24_abdeckung-snapshot.md`](2026-05-24_abdeckung-snapshot.md) —
  Feature-Matrix-Stand (jüngster verfügbarer, 2026-05-24).
- [`2026-05-24_gap-analyse.md`](2026-05-24_gap-analyse.md) —
  Test-Inventar mit Hybrid-V2-Klassifikation (2026-05-24).
- [`historical/2026-05-24_layer2-vs-layer3.md`](historical/2026-05-24_layer2-vs-layer3.md)
  — direkter Vorgänger (Mai-Werte).
