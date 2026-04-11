<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Coverage-Snapshot: Layer 2 vs Layer 3 — 2026-04-11

**Datum:** 2026-04-11  
**Commit:** `72bb7318fe832ea711ceef47f34c4cc3a981fef9`  
**Artefakte:** `artifacts/layer2/coverage.xml` (erzeugt 00:20), `artifacts/layer3/coverage.xml` (erzeugt 00:40)

---

## Gesamt

| Metrik | Layer 2 (Upstream-Unit) | Layer 3 (Integration) | Δ |
|---|---|---|---|
| Statements gesamt | 44.021 | 44.035 | +14 |
| Covered Statements | 17.527 | 17.540 | +13 |
| **Anweisungsüberdeckung** | **39,82 %** | **39,83 %** | **+0,02 %** |
| Methods covered | 1.598 / 4.432 (36,06 %) | 1.604 / 4.432 (36,19 %) | +6 |

Die nahezu identische Gesamt-Coverage verdeckt fundamentale Unterschiede:
Layer 2 und Layer 3 decken überwiegend **verschiedene Bereiche** ab — sie sind komplementär, nicht redundant.

---

## Vergleich nach Bereichen

| Bereich | Dts | Stmt | L2 % | L3 % | Δ % | ΔCov |
|---|---|---|---|---|---|---|
| app/Module | 259 | 10.531 | 17,6 % | 31,6 % | +14,0 % | +1.471 |
| app/Http | 381 | 9.010 | 62,5 % | 36,1 % | −26,4 % | −2.374 |
| app (root) | 51 | 6.713 | 33,8 % | 46,9 % | +13,0 % | +875 |
| app/Services | 37 | 5.720 | 14,6 % | 54,2 % | +39,7 % | +2.268 |
| app/Report | 28 | 3.128 | 79,1 % | 57,8 % | −21,3 % | −667 |
| app/Census | 197 | 2.552 | 99,8 % | 2,2 % | −97,6 % | −2.491 |
| app/Elements | 216 | 1.575 | 86,9 % | 28,4 % | −58,5 % | −921 |
| app/Cli | 16 | 927 | 0,0 % | 45,2 % | +45,2 % | +419 |
| app/CustomTags | 20 | 825 | 0,0 % | 97,2 % | +97,2 % | +802 |
| app/Date | 9 | 735 | 6,7 % | 77,0 % | +70,3 % | +517 |
| app/Factories | 28 | 714 | 31,7 % | 62,6 % | +31,0 % | +221 |
| app/Schema | 51 | 622 | 0,0 % | 3,9 % | +3,9 % | +24 |
| app/Statistics | 1 | 504 | 0,0 % | 0,0 % | 0,0 % | 0 |
| app/SurnameTradition | 10 | 255 | 89,8 % | 22,7 % | −67,1 % | −171 |
| app/Encodings | 16 | 80 | 50,0 % | 26,2 % | −23,8 % | −19 |
| app/CommonMark | 7 | 58 | 6,9 % | 25,9 % | +19,0 % | +11 |
| app/Exceptions | 4 | 28 | 0,0 % | 35,7 % | +35,7 % | +10 |
| app/GedcomFilters | 1 | 27 | 0,0 % | 81,5 % | +81,5 % | +22 |
| app/Helpers | 1 | 17 | 0,0 % | 94,1 % | +94,1 % | +16 |
| app/Contracts | 32 | 0 | — | — | — | 0 |

### Http-Untergruppen

| Bereich | Dts | Stmt | L2 % | L3 % | Δ % | ΔCov |
|---|---|---|---|---|---|---|
| Http/RequestHandlers | 335 | 8.062 | 63,6 % | 35,0 % | −28,6 % | −2.298 |
| Http/Middleware | 34 | 545 | 24,0 % | 9,2 % | −14,9 % | −81 |
| Http/Routes | 2 | 370 | 99,7 % | 99,7 % | 0,0 % | 0 |
| Http/Exceptions | 8 | 13 | 0,0 % | 38,5 % | +38,5 % | +5 |

### Module-Untergruppen

| Kategorie | N | Stmt | L2 % | L3 % | Δ % | ΔCov |
|---|---|---|---|---|---|---|
| Module (root) | 150 | 7.218 | 14,4 % | 34,0 % | +19,6 % | +1.413 |
| Language | 74 | 1.627 | 0,0 % | 20,8 % | +20,8 % | +339 |
| HistoryData | 10 | 601 | 94,0 % | 3,3 % | −90,7 % | −545 |
| Trait | 22 | 528 | 19,5 % | 35,2 % | +15,7 % | +83 |
| Abstract | 2 | 402 | 36,6 % | 48,3 % | +11,7 % | +47 |
| Subdir/InteractiveTree | 1 | 155 | 0,0 % | 86,5 % | +86,5 % | +134 |

---

## Top-10 Einzeldateien: Größte Verluste L2→L3

| Datei | Stmt | L2 % | L3 % | ΔCov |
|---|---|---|---|---|
| app/StatisticsData.php | 1.781 | 63,7 % | 14,2 % | −881 |
| app/Module/StatisticsChartModule.php | 474 | 94,7 % | 27,4 % | −319 |
| app/Elements/GovIdType.php | 277 | 100,0 % | 0,0 % | −277 |
| app/Statistics.php | 1.087 | 24,3 % | 0,2 % | −262 |
| app/Report/ReportParserGenerate.php | 1.237 | 85,4 % | 65,0 % | −252 |
| app/Elements/TempleCode.php | 161 | 100,0 % | 0,0 % | −161 |
| app/Http/RequestHandlers/ControlPanel.php | 147 | 100,0 % | 0,0 % | −147 |
| app/Module/CzechMonarchsAndPresidents.php | 104 | 97,1 % | 1,9 % | −99 |
| app/Module/FrenchHistory.php | 100 | 96,0 % | 2,0 % | −94 |
| app/Report/RightToLeftSupport.php | 640 | 59,2 % | 44,7 % | −93 |

## Top-10 Einzeldateien: Größte Gewinne L2→L3

| Datei | Stmt | L2 % | L3 % | ΔCov |
|---|---|---|---|---|
| app/Gedcom.php | 704 | 0,0 % | 90,3 % | +636 |
| app/Services/RelationshipService.php | 1.523 | 0,0 % | 33,8 % | +515 |
| app/Services/IndividualFactsService.php | 550 | 0,0 % | 84,4 % | +464 |
| app/Services/GedcomImportService.php | 467 | 0,0 % | 85,4 % | +399 |
| app/Http/RequestHandlers/RenumberTreeAction.php | 432 | 1,6 % | 83,6 % | +354 |
| app/Individual.php | 381 | 0,0 % | 85,8 % | +327 |
| app/Module/RelationshipsChartModule.php | 333 | 0,0 % | 74,8 % | +249 |
| app/CustomTags/GedcomL.php | 198 | 0,0 % | 99,5 % | +197 |
| app/Fact.php | 213 | 4,7 % | 76,5 % | +153 |
| app/Module/FanChartModule.php | 219 | 22,8 % | 90,9 % | +149 |

---

## Blinde Flecken beider Layers

| Bereich | Problem |
|---|---|
| app/Statistics | 0 % in beiden — `Statistics.php` (1.087 Stmt), `StatisticsData.php` (1.781 Stmt) |
| app/Schema | 0 % L2, 3,9 % L3 — Migrations weitgehend ungetestet |
| Http/Middleware | 24 % L2 → 9,2 % L3 — schwach abgedeckt |
| app/Module/ModuleDataFixTrait | 81 % L2 → 0 % L3 |

---

## Interpretation

**Layer 2 Stärken** (datenbankfreie, deterministisch testbare Logik):
- Census-Definitionen (197 Dateien, 100 % L2 → ~0 % L3) — reine Datentabellen
- GEDCOM-Element-Parser (`app/Elements`, 86,9 % L2)
- Surname-Traditionen (89,8 % L2)
- History-Datenmodule (94 % L2)
- HTTP-RequestHandler via Mocks/Test-Doubles (63,6 % L2)
- Report-Parser (79,1 % L2)

**Layer 3 Stärken** (DB-gebundene, stack-abhängige Logik):
- Services: GedcomImport, RelationshipService, IndividualFactsService, SearchService
- CustomTags (97,2 % L3 — erst durch echten Import-Pfad ausgeführt)
- Date-Verarbeitung mit DB-Kontext (77 % L3)
- CLI-Commands (45,2 % L3)
- Domain-Objekte: `Gedcom.php`, `Individual.php`, `Fact.php`
- Language-Module (20,8 % L3 gegenüber 0 % L2)

**Gesamtbilanz:** +6.645 Statements wo L3 besser, −6.648 wo L2 besser → Netto ≈ 0.
Beide Layers sind strukturell komplementär.
