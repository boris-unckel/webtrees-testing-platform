<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Coverage-Analyse — Komponentenintegrationstest (Teststufe 2)

> Stand: 2026-04-03 (Iteration 2 — Ausgangslage nach AP1–AP15)
> Eingabe: `artifacts/layer3/coverage.xml` aus `make test-integration` (384 Tests, 1.263 Assertions)
> Baseline: 29,3% Statement-, 21,6% Methodenüberdeckung (Stand AP1–AP15)

---

## 2.1 — Gesamtüberblick

```
Gesamt-Anweisungsüberdeckung:   29,3%  (12.897 / 44.043 Statements)
Gesamt-Methodenüberdeckung:     21,6%  (958 / 4.433 Methoden)
```

### Paket-Aufschlüsselung

| Paket | Statements | Cov% | Methoden | MthCov% | Bewertung |
|---|---|---|---|---|---|
| Module | 10.531 | 27,8% | 1.368 | 25,5% | Partiell |
| Http | 9.025 | 16,1% | 699 | 3,0% | Partiell |
| (root) | 6.713 | 42,2% | 919 | 30,1% | Partiell |
| Services | 5.727 | 42,1% | 297 | 24,9% | Partiell |
| Report | 3.128 | 39,4% | 190 | 38,4% | Partiell |
| Census | 2.552 | 2,2% | 341 | 0,0% | Gering |
| Elements | 1.575 | 7,4% | 201 | 12,4% | Gering |
| Cli | 927 | 0,0% | 50 | 0,0% | **Keine** |
| CustomTags | 825 | 97,2% | 40 | 47,5% | Sehr gut |
| Date | 735 | 77,0% | 85 | 52,9% | Gut |
| Factories | 714 | 56,2% | 109 | 57,8% | Gut |
| Schema | 622 | 3,9% | 49 | 6,1% | Gering |
| Statistics | 504 | 0,0% | 3 | 0,0% | **Keine** |
| SurnameTradition | 255 | 0,0% | 49 | 0,0% | **Keine** |
| Encodings | 80 | 26,2% | 14 | 14,3% | Partiell |
| CommonMark | 58 | 25,9% | 14 | 42,9% | Partiell |
| Exceptions | 28 | 10,7% | 3 | 0,0% | Partiell |
| GedcomFilters | 27 | 81,5% | 2 | 50,0% | Sehr gut |

**Auffällig:** `Cli` (0%, 50 Methoden) und `Statistics` (0%, 3 Methoden) komplett ohne Coverage.

---

## 2.2 — CRAP-Score-Ranking (alle CRAP > 100, 0%-Coverage)

Gesamt: 43 Methoden.

| Rang | CRAP | Paket | Klasse | Methode | cx |
|---|---|---|---|---|---|
| 1 | 3.660 | Report | ReportPdfTextBox | render | 60 |
| 2 | 870 | Http/Middleware | BadBotBlocker | process | 29 |
| 3 | 600 | (root) | StatisticsData | centuryName | 24 |
| 4 | 552 | Cli/Commands | UserEdit | execute | 23 |
| 5 | 552 | Report | ReportParserGenerate | relativesStartHandler | 23 |
| 6 | 420 | (root) | StatisticsData | usersLoggedInQuery | 20 |
| 7 | 420 | Http/RequestHandlers | MapDataImportAction | handle | 20 |
| 8 | 380 | Cli/Commands | UserTreeSetting | execute | 19 |
| 9 | 342 | Cli/Commands | TreeSetting | execute | 18 |
| 10 | 342 | Report | ReportPdfCell | render | 18 |
| 11 | 306 | Cli/Commands | SiteSetting | execute | 17 |
| 12 | 306 | Cli/Commands | UserSetting | execute | 17 |
| 13 | 306 | Http/RequestHandlers | GedcomLoad | handle | 17 |
| 14 | 272 | Http/RequestHandlers | ManageMediaData | handle | 16 |
| 15 | 272 | Report | ReportParserGenerate | addDescendancy | 16 |
| 16 | 240 | Cli/Commands | TreeExport | execute | 15 |
| 17 | 240 | Http/RequestHandlers | MergeFactsAction | handle | 15 |
| 18 | 240 | Report | ReportParserGenerate | imageStartHandler | 15 |
| 19 | 210 | Report | ReportPdfFootnote | getWidth | 14 |
| 20 | 210 | Report | ReportPdfText | getWidth | 14 |
| 21 | 210 | Services | MediaFileService | uploadFile | 14 |
| 22 | 182 | Http/RequestHandlers | EditMediaFileAction | handle | 13 |
| 23 | 182 | Report | ReportParserGenerate | factsStartHandler | 13 |
| 24 | 182 | Report | ReportParserGenerate | factsEndHandler | 13 |
| 25 | 182 | Report | ReportParserGenerate | relativesEndHandler | 13 |
| 26 | 156 | Cli/Commands | TreeEdit | execute | 12 |
| 27 | 156 | Http/Middleware | HandleExceptions | process | 12 |
| 28 | 156 | Http/Middleware | Router | process | 12 |
| 29 | 156 | Http/RequestHandlers | CalendarPage | handle | 12 |
| 30 | 156 | Http/RequestHandlers | LinkChildToFamilyAction | handle | 12 |
| 31 | 156 | Http/RequestHandlers | LoginPage | handle | 12 |
| 32 | 156 | Http/RequestHandlers | MapDataExportCSV | handle | 12 |
| 33 | 156 | Module | HitCountFooterModule | process | 12 |
| 34 | 156 | Module | ModuleThemeTrait | individualBoxFacts | 12 |
| 35 | 156 | Report | ReportParserGenerate | addAncestors | 12 |
| 36 | 132 | Factories | GedcomRecordFactory | newGedcomRecord | 11 |
| 37 | 132 | Http/RequestHandlers | ContactAction | handle | 11 |
| 38 | 132 | Http/RequestHandlers | MapDataSave | handle | 11 |
| 39 | 132 | Http/RequestHandlers | SetupWizard | handle | 11 |
| 40 | 132 | Http/RequestHandlers | UploadMediaAction | handle | 11 |
| 41 | 132 | Report | ReportPdfImage | render | 11 |
| 42 | 110 | Cli/Commands | TreeImport | execute | 10 |
| 43 | 110 | Http/RequestHandlers | ManageMediaData | mediaObjectInfo | 10 |

---

## 2.3 — Klassifikation: DB-abhängig vs. Bootstrap-only

### Bootstrap-only (kein DB-Aufruf im getesteten Pfad)

| CRAP | Klasse | Methode | Begründung | Testbarkeit |
|---|---|---|---|---|
| 3.660 | ReportPdfTextBox | render | PDF-Rendering, kein DB-Zugriff | Hoch — braucht TCPDF-Initialisierung |
| 342 | ReportPdfCell | render | PDF-Rendering, kein DB-Zugriff | Hoch |
| 210 | ReportPdfFootnote | getWidth | Kein DB-Zugriff | Hoch |
| 210 | ReportPdfText | getWidth | Kein DB-Zugriff | Hoch |
| 132 | ReportPdfImage | render | Kein DB-Zugriff | Mittel (Bildpfad nötig) |

### Privat — nur indirekt erreichbar

| CRAP | Klasse | Methode | Erreichbar via | Sichtbarkeit |
|---|---|---|---|---|
| 600 | StatisticsData | centuryName | Aufruf einer Statistik-Methode, die intern `centuryName` ruft | `private` |
| 420 | StatisticsData | usersLoggedInQuery | `usersLoggedIn()` oder `usersLoggedInList()` | `private` |

Beide Methoden sind `private`. Coverage nur durch Aufruf der öffentlichen Wrapper-Methoden.
Testaufwand: gering (Bootstrap-Layer), aber Testtiefe begrenzt auf Smoke.

### Bootstrap-only mit Besonderheit

| CRAP | Klasse | Methode | Begründung | Besonderheit |
|---|---|---|---|---|
| 870 | BadBotBlocker | process | Kein DB-Zugriff, reine HTTP-Middleware | DNS-Lookup in `checkRobotDNS` — Branches mit DNS-Verification schwer testbar. User-Agent-Pfade ohne DNS sind isoliert testbar. |
| 552 | ReportParserGenerate | relativesStartHandler | Grenzfall: Konstruktor braucht Tree, andere Methoden haben DB | Methode selbst ohne DB-Zugriff; Konstruktor braucht Tree-Instanz |
| 156 | HandleExceptions | process | Kein DB-Zugriff | Braucht PhpService + TreeService im Konstruktor |
| 156 | Router | process | HTTP-Routing | Braucht vollständigen Router-Bootstrap |

### DB-abhängig (braucht `createTreeWithGedcom()` oder direkte DB-Interaktion)

| CRAP | Klasse | Methode | DB-Zugriff | FM-Bezug |
|---|---|---|---|---|
| 552 | UserEdit | execute | UserService → DB | — |
| 420 | MapDataImportAction | handle | `DB::table('place_location')` | Karte/Orte |
| 380 | UserTreeSetting | execute | `DB::table('user_gedcom_setting')` | — |
| 342 | TreeSetting | execute | `DB::table('gedcom_setting')` | — |
| 306 | SiteSetting | execute | `DB::table('site_setting')` | — |
| 306 | UserSetting | execute | `DB::table('user_setting')` (erwartet) | — |
| 306 | GedcomLoad | handle | `DB::table('gedcom_chunk')` | G21 (Upload) |
| 272 | ManageMediaData | handle | Erwartet DB (Media-Verwaltung) | — |
| 240 | TreeExport | execute | Tree-Export via GedcomExportService | G13–G17 |
| 240 | MergeFactsAction | handle | Zugriff auf GEDCOM-Records | Daten-Merge |
| 210 | MediaFileService | uploadFile | DB-Operationen bei Upload | — |
| 182 | EditMediaFileAction | handle | Media-Record-Mutation | — |
| 156 | CalendarPage | handle | Kalender-Events aus DB | S31 |
| 156 | LinkChildToFamilyAction | handle | Family-Record-Mutation | — |
| 156 | LoginPage | handle | Auth-Logik mit DB-Lookup | S32 |
| 156 | MapDataExportCSV | handle | `place_location`-Tabelle | — |
| 156 | HitCountFooterModule | process | `DB::table('hit_counter')` | — |
| 156 | ModuleThemeTrait | individualBoxFacts | Individual-Record aus DB | — |
| 132 | GedcomRecordFactory | newGedcomRecord | `DB::table('other')` | — |
| 132 | ContactAction | handle | E-Mail via UserService | S36 |
| 132 | MapDataSave | handle | `place_location`-Upsert | — |
| 132 | SetupWizard | handle | Wizard-Status, DB-Setup | — |
| 132 | UploadMediaAction | handle | Media-Upload + DB | — |
| 110 | TreeImport | execute | Tree-Import mit GEDCOM-Parser | G01–G12 |
| 110 | ManageMediaData | mediaObjectInfo | Media-Record-Lookup | — |

**CLI-Klassen (Gruppe Cli — 0% Paket-Coverage):**

| CRAP | Klasse | Methode | Typ |
|---|---|---|---|
| 552 | UserEdit | execute | CLI, DB |
| 380 | UserTreeSetting | execute | CLI, DB |
| 342 | TreeSetting | execute | CLI, DB |
| 306 | SiteSetting | execute | CLI, DB |
| 306 | UserSetting | execute | CLI, DB |
| 240 | TreeExport | execute | CLI, DB |
| 156 | TreeEdit | execute | CLI, DB |
| 110 | TreeImport | execute | CLI, DB |

CLI-Befehle teilen ein Muster: Symfony Console + DB-Interaktion.
Testbar via `CommandTester` aus `symfony/console`, Bootstrap + DB.
Als Gruppe sinnvoll zu batchen (gemeinsame Infrastruktur).

---

## 2.4 — Gap-Analyse Feature-Matrix × Coverage

| FM-ID | Testklasse | Status | Bezug zu CRAP-Kandidaten |
|---|---|---|---|
| G21 (Upload-Validierung) | `upload-validation.spec.ts` (E2E) | Layer-3 offen | `GedcomLoad::handle` (Rang 13, CRAP 306) |
| G13–G17 (Export) | `TreeOperationsTest` ✅ | Partiell | `TreeExport::execute` nur via CLI — nicht via HTTP-Layer getestet |
| S18 (Chart: Statistiken) | `ChartModuleIntegrationTest` ✅ | Partiell | `StatisticsData::centuryName` + `usersLoggedInQuery` (privat) nicht abgedeckt |
| S31 (Kalenderansicht) | E2E (`calendar.spec.ts`) ✅ | Layer-3 offen | `CalendarPage::handle` (Rang 29, CRAP 156) |
| S32 (Anmeldeseite) | E2E (`login.spec.ts`) ✅ | Layer-3 offen | `LoginPage::handle` (Rang 31, CRAP 156) |
| S36 (Kontaktseite) | E2E (`user-pages.spec.ts`) ✅ | Layer-3 offen | `ContactAction::handle` (Rang 37, CRAP 132) |

---

## 2.5 — Priorisierter Handlungsplan

### Gruppe A: CRAP > 1.000

| AP | Klasse | Methode | CRAP/cx | Begründung | Aufwand |
|---|---|---|---|---|---|
| AP1 | ReportPdfTextBox | render | 3660/60 | Bootstrap-only, höchster CRAP-Wert, TCPDF-Bootstrap im Container verfügbar | mittel |

### Gruppe B: CRAP 300–1.000

| AP | Klasse | Methode | CRAP/cx | Begründung | Aufwand |
|---|---|---|---|---|---|
| AP2 | StatisticsData | usersLoggedIn / centuryName (indirekt) | 600+420/24+20 | Privat — über `usersLoggedIn()` + `usersLoggedInList()` erreichbar; `centuryName` via Statistik-Aufruf der Elternmethode. Bootstrap + DB. | niedrig–mittel |
| AP3 | UserEdit | execute | 552/23 | CLI via CommandTester, UserService + DB. Starter-AP für CLI-Batch. | mittel |
| AP4 | ReportParserGenerate | relativesStartHandler + weitere | 552+272+240+182+182+182+156/23+…| Mehrere Methoden in einer Klasse — gemeinsame Testkklasse sinnvoll. Konstruktor braucht Tree. | hoch |
| AP5 | MapDataImportAction | handle | 420/20 | DB, place_location. Standalone HTTP-Request-Handler. | mittel |
| AP6 | UserTreeSetting + TreeSetting + SiteSetting + UserSetting | execute | 380+342+306+306/19+18+17+17 | CLI-Batch — 4 ähnliche CLI-Commands, gemeinsame CommandTester-Infrastruktur. | mittel |
| AP7 | ReportPdfCell | render | 342/18 | Bootstrap-only, ähnlich AP1. Kann parallel zu AP3–AP6. | mittel |
| AP8 | GedcomLoad | handle | 306/17 | DB, gedcom_chunk — GEDCOM-Upload-Handler. FM G21-Bezug. | hoch |

### Gruppe C: CRAP 100–300

| AP | Klasse | Methode | CRAP/cx | Begründung | Aufwand |
|---|---|---|---|---|---|
| AP9 | ManageMediaData | handle + mediaObjectInfo | 272+110/16+10 | DB, Media-Verwaltung. Beide Methoden in einer Klasse. | mittel |
| AP10 | MergeFactsAction | handle | 240/15 | DB, GEDCOM-Record-Merge. | mittel |
| AP11 | TreeExport | execute | 240/15 | CLI, Tree-Export. Ergänzt AP3/AP6 CLI-Batch. | mittel |
| AP12 | ReportPdfFootnote + ReportPdfText | getWidth | 210+210/14+14 | Bootstrap-only, PDF-Klassen. Einfach. | niedrig |
| AP13 | MediaFileService | uploadFile | 210/14 | DB, Upload-Service. | mittel |
| AP14 | EditMediaFileAction | handle | 182/13 | DB, Media-Mutation. | mittel |
| AP15 | BadBotBlocker | process | 870→156er-Block/29 | Bootstrap, HTTP-Middleware. DNS-Branches ausklammern; UA-String-Pfade testbar. | mittel |

---

## 2.6 — testing-bigpicture.md Diff-Vorschläge

Die Ratchet-Werte in `testing-bigpicture.md` sind bereits aktuell (29,3%/21,6%, Stand AP1–AP15).
Nach Abschluss dieser Iteration:

```diff
 ### Ist-Stand (Teststufe 2, Stand: 2026-04-03, nach AP1–AP15)
+### Ist-Stand (Teststufe 2, Stand: YYYY-MM-DD, nach AP1–APn)
 
-| Anweisungsüberdeckung | 29,3% (12.897 / 44.043 Statements) — Ratchet-Basis |
+| Anweisungsüberdeckung | X,X% (N / 44.043 Statements) — Ratchet-Basis |
-| Methodenüberdeckung | 21,6% (958 / 4.433 Methoden) |
+| Methodenüberdeckung | X,X% (N / 4.433 Methoden) |
-| Testklassen | 27 Testklassen (384 Tests, 1.263 Assertions) |
+| Testklassen | N Testklassen (N Tests, N Assertions) |
```

Falls `Cli`-Tests hinzukommen: neuen Paket-Eintrag in Paket-Aufschlüsselung ergänzen.
Falls `CalendarPage::handle` getestet wird: FM S31 Layer-3-Status auf ✅ setzen.

---

## 2.7 — Einschränkungen und Messartefakte

| Einschränkung | Auswirkung | Empfehlung |
|---|---|---|
| pcov statement-level | Keine Branch-Coverage | BadBotBlocker DNS-Branches nicht messbar — explizit im Testkommentar vermerken |
| Private Methoden (`centuryName`, `usersLoggedInQuery`) | Coverage nur durch öffentliche Wrapper | Als Indirekt-Test kennzeichnen; Smoke-Level reicht |
| CLI-Tests via CommandTester | Symfony Console-Bootstrap nötig | `MysqlTestCase` als Basis; DB-Verbindung ist vorhanden |
| Report-PDF-Klassen (TCPDF) | TCPDF-Renderer im Container verfügbar? | Vor Skelett-Erstellung: `upstream/webtrees/app/Report/ReportPdfRenderer.php` Konstruktor prüfen |
| `BadBotBlocker` DNS-Lookups | `gethostbyaddr`/`gethostbyname` → externe DNS-Abhängigkeit | Nur User-Agent-Pfade ohne DNS testen; DNS-Branches als bekannte Lücke dokumentieren |
| `GedcomLoad::handle` — Chunk-basierter Import | Aufwändig: GEDCOM-Chunk-Tabelle füllen nötig | Alternativ: `gedcom_chunk`-Vorbefüllung via SQL-Fixture |
| `SetupWizard::handle` — Einmal-Wizard | Testet Wizard-Fluss, der nur auf frischem System läuft | Schwer in bestehendem Test-Setup testbar; als "E2E only" markieren |
