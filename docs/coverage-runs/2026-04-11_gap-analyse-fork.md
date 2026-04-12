<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Gap-Analyse-Snapshot — Fork-Stand 2026-04-11

> **Scope:** Neuerhebung der Teststand-Gesamtsicht nach der Methodik aus
> [`coverage_doc_improvement_analysis.md`](../coverage_doc_improvement_analysis.md) §4.3.
> Erzeugt im Rahmen von Phase 1 des Umsetzungsplans
> [`coverage_doc_improvement_plan.md`](../coverage_doc_improvement_plan.md).
>
> **Charakter:** Frozen Snapshot. Dieses Dokument wird **nicht** fortgeschrieben — spätere
> Neuerhebungen werden als neuer, datierter Snapshot angelegt und in der Coverage-Runs-Navigation
> referenziert.

## 1 Basis — Commit-SHAs der Quellen

| Quelle | Repo / Branch | Commit | Datum | Kopfzeile |
|---|---|---|---|---|
| L2 (Upstream-Fork, Komponententest) | `webtrees-upstream/webtrees` @ `port-layer2-test-doubles` | `841616f4b56c07ae81b146310d6131dd019b76f0` | 2026-04-11 11:28 +0200 | *Add substantive component tests for 278 test files* |
| L3 (Testing-Platform, KIT) | `webtrees-testing-platform` @ `main` → `layer3-integration/tests/` | `698479661f24036558b502108124d641a8311fb1` | 2026-04-11 22:35 +0200 | *docs: Umsetzungsplan coverage_doc_improvement_plan.md* |
| L4 (Testing-Platform, Systemtest) | `webtrees-testing-platform` @ `main` → `layer4-e2e/tests/` | `698479661f24036558b502108124d641a8311fb1` | 2026-04-11 22:35 +0200 | *docs: Umsetzungsplan coverage_doc_improvement_plan.md* |
| Anwendungscode (Services/Handler/Middleware/CLI) | `webtrees-testing-platform` @ `upstream/webtrees/` (`security-audit-consolidated`) | `9c7bdfd95a4c4689d8791d5c025fd7034da62b57` | 2026-04-11 11:26 +0200 | *security: fix SEC-AUDIT-008 — add rate limiting to login endpoint* |

## 2 Methodik

Kurzform — Langfassung siehe [`coverage_doc_improvement_analysis.md`](../coverage_doc_improvement_analysis.md) §4.3.

**Erhebungs-Schritt 1 — Inventar pro Test-Schicht.** Liste aller Testdateien pro Layer via
`find -name "*.php"` bzw. `-name "*.spec.ts"`. L2-Scope ist `tests/app/` + `tests/feature/` —
identisch zu den in `phpunit.xml.dist` registrierten Testsuites *Unit tests* und *Feature tests*.
`tests/views/` (2 Dateien) ist auf Disk vorhanden, aber **nicht** im PHPUnit-Scope und bleibt aus
der Zählung. L3-Scope schließt alle Basis-Klassen (`*TestCase.php`) aus. L4-Scope umfasst
`*.spec.ts` rekursiv inkl. `security/`.

**Erhebungs-Schritt 2 — Metriken pro Datei.** Pro Testdatei werden erhoben:
`lines` (Zeilen), `methods` (Testmethoden; PHP: `public function test*` ∪ `#[Test]`; TS:
`test('…')`-Aufrufe ohne `describe`/`beforeEach`/`afterEach`), `providers` (`#[DataProvider]` bzw.
`test.each`), `assertions`/`expects` (`$this->assert*`, `self::assert*`, `static::assert*`,
`expect(`), sowie PHPDoc-Marker `@ep`, `@substantial`. Die Erhebungsskripte liegen während der
Erhebung unter `/tmp/gap_inventory_{php,ts}.sh` und sind reproduzierbar gegen die oben genannten
Commit-SHAs.

**Erhebungs-Schritt 3 — Hybrid-Qualitätsklassifikation pro Testdatei (V2):**

- **PHPDoc-Override (höchste Priorität):** `@ep` → `EP-complete`, `@substantial` → `Substantial`.
- **Metrik-basiert (V2-Heuristik):** Assertionsdichte (`assertions/methods`) + strukturelle Dimension
  `lines/methods` (Setup-Proxy — fängt Mock-intensive Komponententests mit wenigen
  End-Assertions ein, die V1 fälschlich als Smoke eingestuft hätte).

| Klasse | Kriterium (in Prüfreihenfolge) |
|---|---|
| `EP-complete` | `providers ≥ 3` **oder** (`methods ≥ 10` **und** `density ≥ 2.0`) |
| `Substantial` | (`methods ≥ 3` **und** `density ≥ 2.0`) **oder** (`methods ≥ 3` **und** `lines/methods ≥ 15` **und** `density ≥ 1.0`) |
| `Smoke`       | `methods ≥ 2` **und** (`density ≥ 1.0` **oder** `lines/methods ≥ 10`) |
| `Stub`        | Rest (`methods == 0`, Boilerplate, `class_exists`-Smoke) |

**Erhebungs-Schritt 4 — Domänen-Zuordnung:**

- **L2:** Die Fork-Tests enthalten **keine** `@see`-Annotationen zu Feature-IDs. Die Zuordnung
  erfolgt daher auf **Aggregat-Ebene** per Top-Level-Verzeichnis unter `tests/app/` (siehe §3.3).
  Eine Datei-für-Datei-Ermittlung der SUT-Klasse ist in der CSV-Rohdatei (`_l2.csv`) über
  die `file`-Spalte möglich, wird aber nicht als Tabelle im Snapshot ausgerollt.
- **L3/L4:** Die Tests nutzen `@see docs/testing-bigpicture.md <Feature-ID>`-Annotationen (Pfad
  wird in Plan-Phase 7 aktualisiert). Zuordnung pro Datei per `grep`-Extraktion (siehe §3.4).
- **Mehrfachzuordnung:** Im Skript zugelassen — eine Testdatei kann mehrere Feature-IDs tragen.

**Darstellungs-Entscheidung:** Aggregate + Top/Tails im Dokument selbst. Rohdaten pro Datei
als CSV-Anhänge neben diesem Dokument: `2026-04-11_gap-analyse-fork_l2.csv` (1238 Zeilen + Header),
`_l3.csv` (82 + Header), `_l4.csv` (26 + Header). Begründung: 1238 L2-Dateien sind als
Zeilen-für-Zeile-Tabelle unnavigierbar.

---

## 3 Ergebnisse

### 3.1 Gesamtkennzahlen pro Layer

| Kennzahl | L2 (Komponententest, Fork) | L3 (KIT, MySQL) | L4 (Systemtest, Playwright) |
|---|---:|---:|---:|
| Testdateien | **1238** | **82** | **26** |
| Quell-Zeilen gesamt | 82 668 | 15 643 | 1 524 |
| Testmethoden gesamt | 2 443 | 579 | 85 |
| DataProvider / `test.each` | 21 | 20 | 0 |
| Assertions / `expect(` gesamt | 9 025 | 1 006 | 143 |
| Gesamt-Assertionsdichte | 3.69 | 1.74 | 1.68 |
| Mittlere Datei-Länge (Zeilen) | 66.8 | 190.8 | 58.6 |
| Mittlere Testmethoden-Zahl | 1.97 | 7.06 | 3.27 |

**Lesehinweise:**
- L2 hat trotz der höchsten Gesamt-Assertionsdichte (3.69) die niedrigste Methoden-Zahl pro Datei (1.97) — weil die Census-Submatrizen künstlich hohe Assertion-Zahlen pro Methode produzieren (siehe Top-20 unten).
- L3 ist pro Datei deutlich umfangreicher und assertionsdichter als typische L2-Dateien (190 Zeilen / 7 Methoden / 12.3 Assertions pro Datei im Schnitt).
- L4 hat keine einzige `test.each`-Parametrisierung; Parametrisierung erfolgt über `for (const theme of themes)`-Schleifen (nicht im DataProvider-Count erfasst).

### 3.2 Assertionsdichte-Verteilung

| Metrik | L2 | L3 | L4 |
|---|---:|---:|---:|
| Assertions pro Datei (min / Q1 / Median / Q3 / max) | 0 / 1 / 2 / 4 / **156** | 1 / 4 / 8 / 15 / **54** | 2 / 4 / 4 / 6 / **15** |
| Testmethoden pro Datei (min / Q1 / Median / Q3 / max) | 0 / 1 / 1 / 2 / **24** | — | — |
| Assertionsdichte (min / Q1 / Median / Q3 / max) bei `methods > 0` | 0.75 / 1.00 / 1.00 / 1.75 / 147.00 | 1.00 / 1.00 / 1.50 / 2.00 / 6.20 | — |

Die L2-Medianwerte (`assertions = 2`, `methods = 1`, `density = 1.00`) sind der stärkste Indikator
dafür, dass ein sehr großer Teil der L2-Basis aus 1-Methoden-Boilerplate-Tests mit einem einzigen
`class_exists`-artigen Assert besteht. Der Fork-Branch `port-layer2-test-doubles` hat das für 278
Dateien aufgelöst — diese fallen in `Substantial` oder `EP-complete` (siehe §3.3).

### 3.3 Qualitätsklassifikation (Hybrid V2)

| Klasse | L2 | L3 | L4 |
|---|---:|---:|---:|
| `Stub`         | 682 (55.1 %) | 2 (2.4 %)  | 0 (0.0 %)  |
| `Smoke`        | 285 (23.0 %) | 9 (11.0 %) | 11 (42.3 %) |
| `Substantial`  | 263 (21.2 %) | 65 (79.3 %) | 15 (57.7 %) |
| `EP-complete`  |   8 (0.6 %)  | 6 (7.3 %)  | 0 (0.0 %)  |
| **Gesamt**     | **1238**    | **82**    | **26**    |

**Abgleich mit Commit `841616f4b5`:** Der Fork-Branch-Commit kündigt *„substantive component tests
for 278 test files"* an. V2 klassifiziert **271** L2-Dateien als `Substantial`/`EP-complete`
(263 + 8) — die Abweichung von 7 ist im Rahmen der Heuristik erwartet (Dateien mit leichter
Erweiterung, die die strukturellen Schwellen verfehlen).

**Historischer Vergleich:** Die Gap-Analyse vom 2026-03-26 (in `tds_conditions_ref.md` archiviert)
sprach von ~95 % Stub-Tests. V2-Neuerhebung im Fork: 55.1 % Stub — **ca. 40 Prozentpunkte
Reduktion** durch den `port-layer2-test-doubles`-Branch. Das bestätigt qualitativ, dass der
historische Befund überholt ist.

### 3.4 L2-Verteilung pro Top-Level-Verzeichnis (Aggregat-Domänen-Zuordnung)

| Top-Level | Dateien | Zeilen | Methoden | Assertions | Subst+EP | Fachliche Zuordnung |
|---|---:|---:|---:|---:|---:|---|
| `app/Http/`              | 373 | 29 608 | 942 | 1 189 | 156 | **alle Domänen** (RequestHandler pro Feature) |
| `app/Elements/`          | 212 |  8 514 | 127 |   245 |  10 | **G** (GEDCOM-Record-Elemente) |
| `app/Module/`            | 210 | 10 354 | 254 |   399 |  15 | **S** (Chart-/List-Module), **A** (Admin-Module), **P** (Privacy-Module) |
| `app/Census/`            | 192 | 19 661 | 616 | 5 815 |  51 | **G** (Census-Daten-Spalten) |
| `app/Schema/`            |  48 |  1 536 |  48 |    48 |   0 | *(infra — DB-Migration, keine Feature-Zuordnung)* |
| `app/` (Root)            |  46 |  2 943 | 135 |   408 |  11 | gemischt |
| `app/Services/`          |  36 |  2 167 |  86 |   206 |  12 | **G** + **P** + **E** (Service-Schicht) |
| `app/Report/`            |  27 |    864 |  27 |    27 |   0 | **S** (Bericht-Rendering) |
| `app/Factories/`         |  27 |  1 311 |  58 |   136 |   4 | **G** / **E** (Entity-Factories) |
| `app/CustomTags/`        |  20 |    640 |  20 |    20 |   0 | **G** (GEDCOM Custom Tags) |
| `app/Encodings/`         |  13 |    970 |  19 |    80 |   1 | **G** (GEDCOM-Encoding) |
| `app/SurnameTradition/`  |   9 |  1 759 |  73 |   123 |   9 | **S** (Namensgebung pro Kultur) |
| `app/Date/`              |   8 |    338 |  10 |    26 |   1 | **G** (Kalender/Datum) |
| `app/CommonMark/`        |   7 |    224 |   7 |     7 |   0 | *(infra — Markdown-Renderer)* |
| `feature/`               |   5 |  1 284 |   7 |   226 |   0 | **E** (Integrations-Features) |
| `app/Exceptions/`        |   3 |     96 |   3 |     3 |   0 | *(infra)* |
| `app/Reports/`           |   1 |    367 |  10 |    66 |   1 | **S** |
| `app/GedcomFilters/`     |   1 |     32 |   1 |     1 |   0 | **G** |

Summe: 1238 Dateien (konsistent mit §3.1). Die `Http/`-Gruppe dominiert die L2-Menge und wird durch
den `port-layer2-test-doubles`-Commit primär aufgewertet — `Substantial`-Quote hier allein 156 / 373 = 41.8 %.

### 3.5 L3-Feature-ID-Abdeckung (aus `@see`-Annotationen)

**Dateien mit `@see`-Annotation:** 47 von 82 (57.3 %).
**Dateien ohne `@see`-Annotation:** 35 (42.7 %) — diese müssen in Plan-Phase 7 entweder nachträglich
annotiert oder als Gap dokumentiert werden.

**Referenzierte IDs pro Domäne (Anzahl referenzierender Testdateien):**

| Domäne | IDs + Häufigkeit | Abdeckungs-Summe |
|---|---|---:|
| **G** (GEDCOM) | G01, G02, G05, G13, G16, G24, G25, G26, G27, G28, G29 (je 1) | 11 |
| **S** (Sichten) | S01, S07, S14, S15, S19, S41 (×2), S42, S43, S44, S45 (×2), S46, S47, S48, S49, S50 (×2) | 17 |
| **P** (Privacy/User) | P01 (×2), P08, P16, P22, P24, P27, P30 (×3), P31, P32 (×2), P33, P34, P35, P36, P37 | 20 |
| **SEC** | SEC-BOT01 (×1) | 1 |
| **E / A / K / U / M** | — | 0 |

**Hinweis:** `MysqlTestCase.php` enthält `@see docs/testing-bigpicture.md N4 (Phase 4)` —
dies ist **keine** Feature-ID, sondern ein Workflow-Verweis auf eine Phase im alten Big-Picture-Dokument.
MysqlTestCase ist ohnehin kein eigenständiger Test und aus dem L3-Scope ausgeschlossen. Der Verweis
wird in Plan-Phase 7 gesondert behandelt (Rewrite oder Entfernung).

### 3.6 L4-Feature-ID-Abdeckung (aus `@see`-Annotationen)

**Specs mit `@see`-Annotation:** 14 von 26 (53.8 %).
**Specs ohne `@see`-Annotation:** 12 (46.2 %) — darunter alle Security-Specs (`access-control`,
`security-headers`, `media-access`, `data-access`, `public-access`, `setup-lock`, `wizard-setup`),
sowie diverse Privacy-Specs (`privacy-search`, `privacy-relationship`, `privacy-resn`, `privacy-charts`,
`privacy-visibility`).

**Referenzierte IDs pro Domäne:**

| Domäne | IDs | Abdeckungs-Summe |
|---|---|---:|
| **G** | G21 | 1 |
| **S** | S13, S14, S20, S23 (×3), S24, S26, S31, S33, S35, S38, S40 | 13 |

### 3.7 Lückenanalyse — Feature-IDs ohne Testabdeckung im Neu-Stand

Die vollständige Liste der Feature-IDs aus `tds_conditions_ref.md` wird in Plan-Phase 3/4
(Struktur- und Siegel-Migration) neu gegen die L2/L3/L4-Abdeckung verprobt. Für den Snapshot
hier reicht die **Abdeckungs-Obergrenze** der `@see`-Annotationen:

- **G-Reihe:** 11 IDs mit L3-Annotation, 1 ID mit L4-Annotation (G21). L2 ohne `@see` —
  Zuordnung nur auf Aggregat-Ebene (Elements/Census/CustomTags/Encodings/Factories/Date).
- **S-Reihe:** 11 IDs mit L3-Annotation, 12 IDs mit L4-Annotation. L2 via `app/Module/`
  (210 Dateien) und `app/Report/` (27) nur aggregiert zuordbar.
- **P-Reihe:** 15 IDs mit L3-Annotation, 0 in L4. L2 hat keine eigene Privacy-Verzeichnis-Aggregation
  (Privacy-Tests sind in `app/` Root und `app/Http/RequestHandlers/`, nicht separiert).
- **SEC-Reihe:** nur SEC-BOT01 mit L3-Annotation; alle SEC-AUDIT-*-Tests ohne `@see`.
  Die L3-`Security/`-Tests (SecAudit001/005/008) tragen keine Annotation.
- **E-Reihe:** keine `@see`-Annotation in irgendeinem Layer.
- **A-Reihe:** keine `@see`-Annotation in irgendeinem Layer — obwohl CLI-Commands L3-Tests haben
  (`CliSettingsBatchIntegrationTest`, `UserEditCommandIntegrationTest`, `TreeExportCommandIntegrationTest`).
- **K-Reihe:** keine Annotation.
- **U-Reihe:** keine Annotation.

**Gap-Kernbefund:**

1. `@see`-Annotationen decken im Fork-Stand **nur** Teile der G/S/P/SEC-Reihen ab. Für E/A/K/U gibt
   es **keine einzige** Annotation — die bestehende Testabdeckung muss dort manuell zugeordnet
   werden (Plan-Phase 6 Zellen-Migration).
2. In L2 existiert **keinerlei** Annotation — die gesamte L2-Coverage-Matrix-Füllung muss auf
   Aggregat- oder Dateinamens-Heuristik basieren.
3. **Middleware** ist weder in den Feature-IDs noch in den Tests repräsentiert — die M-Reihe
   aus Plan-Phase 5.1 startet auf der grünen Wiese.
4. **CLI-Commands** sind in der A-Reihe teilweise erfasst (G25, G26, P35, P36), aber 11 Commands
   fehlen — siehe Plan-Phase 5.2.

### 3.8 Top-/Bottom-Listen (für Review-Kontextualisierung)

#### L2 Top-10 nach Assertions (höchste textuelle Assertion-Dichte)

| Datei | Methoden | Assertions | Klasse |
|---|---:|---:|---|
| `app/Census/CensusOfUnitedStates1830Test.php` | 2 | 156 | Smoke |
| `feature/RelationshipNamesTest.php` | 1 | 147 | Stub |
| `app/Census/CensusTest.php` | 10 | 120 | EP-complete |
| `app/Census/CensusOfUnitedStates1840Test.php` | 2 | 120 | Smoke |
| `app/Census/CensusOfCanada1931Test.php` | 2 | 117 | Smoke |
| `app/TimestampTest.php` | 19 | 116 | EP-complete |
| `app/Census/CensusOfCanada1911Test.php` | 2 | 108 | Smoke |
| `app/Census/CensusOfUnitedStates1820Test.php` | 2 | 99 | Smoke |
| `app/Census/CensusOfCanada1921Test.php` | 2 | 99 | Smoke |
| `app/Census/CensusOfUnitedStates1940Test.php` | 2 | 96 | Smoke |

**Lesehinweis:** Census-Tests haben extrem hohe Assertion-Zahlen in 2 Methoden — das ist ein
Muster, bei dem ein einziger Test alle Census-Spalten in einer Schleife prüft. Die Heuristik
stuft sie korrekt als `Smoke` ein (wenige Methoden, aber hohe Dichte), obwohl die *Abdeckung*
fachlich substanziell ist. Das ist ein bekannter Edge-Case der Klassifikations-Heuristik.

#### L2 Top-10 nach Zeilenumfang (strukturelle Substanz)

| Datei | Zeilen | Methoden | Assertions | Klasse |
|---|---:|---:|---:|---|
| `feature/EmbeddedVariablesTest.php` | 483 | 2 | 4 | Smoke |
| `feature/RelationshipNamesTest.php` | 400 | 1 | 147 | Stub |
| `app/ValidatorTest.php` | 391 | 24 | 52 | EP-complete |
| `app/Reports/RightToLeftSupportTest.php` | 367 | 10 | 66 | EP-complete |
| `app/Census/CensusColumnConditionUsTest.php` | 346 | 14 | 14 | Substantial |
| `app/Census/CensusColumnConditionCanadaTest.php` | 346 | 14 | 14 | Substantial |
| `app/TreeTest.php` | 320 | 15 | 36 | EP-complete |
| `app/SurnameTradition/LithuanianSurnameTraditionTest.php` | 319 | 13 | 21 | Substantial |
| `app/Module/MissingFactsReportModuleTest.php` | 301 | 1 | 9 | Stub |
| `app/SurnameTradition/PolishSurnameTraditionTest.php` | 297 | 11 | 19 | Substantial |

#### L3 Top-10 nach Assertions

| Datei | Methoden | Assertions | Klasse |
|---|---:|---:|---|
| `GedcomImportTest.php` | 28 | 54 | Substantial |
| `ResnPrivacyTest.php` | 16 | 51 | EP-complete |
| `PrivacyVisibilityTest.php` | 22 | 50 | EP-complete |
| `TreeOperationsTest.php` | 23 | 43 | Substantial |
| `RelationshipServiceIntegrationTest.php` | 16 | 41 | EP-complete |
| `IsDeadTest.php` | 17 | 34 | EP-complete |
| `AccessControlTest.php` | 12 | 32 | EP-complete |
| `Security/SecAudit001Test.php` | 5 | 31 | Substantial |
| `SearchIntegrationTest.php` | 29 | 31 | Substantial |
| `UserEditCommandIntegrationTest.php` | 14 | 26 | Substantial |

#### L4 Top-10 nach Expects

| Spec | Tests | Expects | Klasse |
|---|---:|---:|---|
| `records.spec.ts` | 5 | 15 | Substantial |
| `security/wizard-setup.spec.ts` | 4 | 10 | Substantial |
| `privacy-resn.spec.ts` | 7 | 9 | Smoke |
| `user-pages.spec.ts` | 3 | 8 | Substantial |
| `security/data-access.spec.ts` | 4 | 8 | Substantial |
| `access-control.spec.ts` | 5 | 7 | Substantial |
| `upload-validation.spec.ts` | 4 | 6 | Substantial |
| `security/security-headers.spec.ts` | 4 | 6 | Smoke |
| `search-replace.spec.ts` | 3 | 6 | Substantial |
| `search-forms.spec.ts` | 2 | 6 | Smoke |

---

## 4 Anhang

### 4.1 Rohdaten

- `2026-04-11_gap-analyse-fork_l2.csv` — 1238 Datenzeilen. Spalten:
  `file,lines,methods,providers,assertions,density,phpdoc_ep,phpdoc_sub,classification`.
- `2026-04-11_gap-analyse-fork_l3.csv` — 82 Datenzeilen (identisches Schema).
- `2026-04-11_gap-analyse-fork_l4.csv` — 26 Datenzeilen. Spalten:
  `file,lines,tests,each_loops,expects,density,phpdoc_ep,phpdoc_sub,classification`.

### 4.2 Reproduktionskommandos

**L2 (Upstream-Fork):**
```bash
/tmp/gap_inventory_php.sh \
    /home/borisunckel/phpprojects/webtrees-upstream/webtrees/tests \
    /tmp/l2_metrics.csv
grep -v '^views/' /tmp/l2_metrics.csv > /tmp/l2_metrics_filtered.csv
```

**L3 (Testing-Platform):**
```bash
/tmp/gap_inventory_php.sh \
    /home/borisunckel/phpprojects/webtrees-testing-platform/layer3-integration/tests \
    /tmp/l3_metrics.csv
grep -vE '^(PrivacyTestCase|Security/SecurityAuditTestCase)\.php,' /tmp/l3_metrics.csv \
    > /tmp/l3_metrics_filtered.csv
```

**L4 (Testing-Platform):**
```bash
/tmp/gap_inventory_ts.sh \
    /home/borisunckel/phpprojects/webtrees-testing-platform/layer4-e2e/tests \
    /tmp/l4_metrics.csv
```

Die Skripte selbst sind während der Erhebung generiert und nicht committed. Sie sind deterministisch
und können bei Bedarf regeneriert werden (Kern-Metriken: Zeilenzahl, `grep -cE` für Methoden,
Provider und Assertions, `awk` für Density und Klassifikation).

### 4.3 Offene Lesson-Learned-Notizen (Rückmeldung an den Plan)

1. **Analyse §1.2 L3-Zahl überholt:** Analyse nennt 84 L3-Dateien (exkl. `MysqlTestCase.php`),
   tatsächlich sind es **82** echte Tests — weil `PrivacyTestCase.php` und
   `Security/SecurityAuditTestCase.php` zusätzlich als Basisklassen existieren. Plan-Phase 8
   sollte diese Zahl aus dem Plan ableiten oder korrigieren.
2. **`tests/views/` außerhalb des L2-Scopes:** 2 Dateien existieren auf Disk, aber nicht in
   `phpunit.xml.dist`. Sie werden nicht ausgeführt und bleiben aus dem L2-Inventar.
3. **V1→V2-Heuristik-Revision:** Die Assertionsdichte allein klassifiziert Mock-intensive
   Komponententests (viel Setup, 1 Abschluss-Assertion) zu pessimistisch als `Smoke`. V2 ergänzt
   `lines/methods ≥ 15` als strukturelle Dimension und liefert mit 271 Substantial/EP-complete
   Treffern ein Ergebnis, das der Commit-Kopfzeile von `841616f4b5` („278 substantive component
   tests") quantitativ entspricht.
4. **`N4`-Referenz in `MysqlTestCase.php`:** `@see docs/testing-bigpicture.md N4 (Phase 4)` ist
   keine Feature-ID, sondern ein Workflow-Verweis. Phase 7 (`@see`-Pfad-Update) muss diesen
   Sonderfall ausklammern oder explizit neu schreiben — ein reines `sed`-Replace würde eine
   stale Referenz auf einen nicht-existenten Anker in `tds_conditions_ref.md` hinterlassen.
5. **Nur 57 % L3 und 54 % L4 haben `@see`-Annotationen.** Phase 7 trifft also nur die Hälfte
   der Tests. Die verbleibenden ~47 Dateien / Specs müssen im Rahmen der Zellen-Migration
   (Plan-Phase 6) heuristisch oder manuell einer Feature-ID zugeordnet werden.
6. **Census-Edge-Case:** Einige Census-Tests haben 1–2 Methoden mit 50–150 Assertions in
   Schleifen-Assertions — die Heuristik stuft sie als `Smoke` ein, fachlich sind sie aber
   vollständige Spezifikations-Tests für Census-Spalten. Bei der Siegel-Vergabe in Phase 4
   sollte das per manueller Korrektur auf `Spec-B` oder `Spec-C` hochgesetzt werden.
