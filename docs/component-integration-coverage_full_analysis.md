<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Coverage-Analyse — Komponentenintegrationstest (Teststufe 2) — Vollständiger Lauf

> Stand: 2026-04-03 (nach AP1–AP4 aus vorherigem Plan)  
> Eingabe: `artifacts/layer3/coverage.xml` aus `make test-integration` (296 Tests, 21 Testklassen)  
> Baseline Voll-Lauf vor AP1–AP4: 17,9% Statement-, 17,3% Methodenüberdeckung (285 Tests)

---

## Vorbemerkung: Stand nach AP1–AP4

Die vorige Iteration schloss folgende APs ab:

| AP | Ziel | CRAP vorher | Ergebnis |
|---|---|---|---|
| AP1 | `RelationshipService::legacyCousinName` (cx=51) | 2.652 | ✅ Cousin-Pfade grün; legacyCousinName jetzt abgedeckt |
| AP2 | `StatisticsChartModule::postCustomChartAction` (cx=118) | 14.042 | ✅ 3 Chart-Typen getestet |
| AP3 | `CalendarService::getAnniversaryEvents` + `RelationshipsChartModule::chart` | 870 + 756 | ✅ beide Methoden abgedeckt |
| AP4 | `BranchesListModule::getDescendantsHtml` | 380 | ✅ AJAX-Branch grün |

Neue Coverage-Basis: **19,8% / 17,7%** (296 Tests, 899 Assertions).

---

## Schritt 1 — Gesamtüberblick

```
Gesamt-Anweisungsüberdeckung:   19,8%  (8.716 / 44.066 Statements)
Gesamt-Methodenüberdeckung:     17,7%  (787 / 4.441 Methoden)
```

Vergleich zur Vorgänger-Baseline:

| Metrik | Vor AP1–AP4 (285 Tests) | Nach AP1–AP4 (296 Tests) | Delta |
|---|---|---|---|
| Anweisungsüberdeckung | 17,9% (7.882 / 44.066) | **19,8% (8.716 / 44.066)** | +1,9 Pp |
| Methodenüberdeckung | 17,3% (767 / 4.441) | **17,7% (787 / 4.441)** | +0,4 Pp |

### Paket-Aufschlüsselung

| Paket | Statements | Cov% | Methoden | MthCov% | Bewertung |
|---|---|---|---|---|---|
| Http\Routes | 370 | **99,7%** | 2 | 50,0% | Sehr gut |
| CustomTags | 825 | **97,2%** | 40 | 47,5% | Sehr gut |
| GedcomFilters | 27 | **81,5%** | 2 | 50,0% | Gut |
| Factories | 714 | **49,4%** | 109 | 56,0% | Partiell |
| Services | 5.727 | **37,7%** | 297 | 22,2% | Partiell |
| (root) | 6.713 | **36,7%** | 919 | 28,5% | Partiell |
| Http | 20 | **25,0%** | 3 | 33,3% | Partiell (Micro-Paket) |
| Encodings | 80 | **26,2%** | 14 | 14,3% | Partiell |
| Date | 735 | **22,3%** | 85 | 25,9% | Gering |
| Module | 10.376 | **20,1%** | 1.357 | 23,6% | Gering |
| (root-direct) | 17 | **70,6%** | 0 | — | Gering (Micro-Dateien) |
| Schema | 622 | **3,9%** | 49 | 6,1% | Marginal |
| Elements | 1.575 | **4,8%** | 201 | 10,4% | Marginal |
| Http\RequestHandlers | 8.083 | **2,0%** | 617 | 1,3% | Marginal |
| Census | 2.552 | **0,0%** | 341 | 0,0% | Keine Coverage |
| Cli\Commands | 906 | **0,0%** | 47 | 0,0% | Keine Coverage |
| CommonMark | 58 | **0,0%** | 14 | 0,0% | Keine Coverage |
| Exceptions | 36 | **0,0%** | 3 | 0,0% | Keine Coverage |
| Http\Exceptions | 13 | **0,0%** | 8 | 0,0% | Keine Coverage |
| Http\Middleware | 545 | **0,0%** | 69 | 0,0% | Keine Coverage |
| Module\InteractiveTree | 155 | **0,0%** | 11 | 0,0% | Keine Coverage |
| Report | 3.137 | **0,0%** | 198 | 0,0% | Keine Coverage |
| Statistics\Service | 504 | **0,0%** | 3 | 0,0% | Keine Coverage |
| SurnameTradition | 255 | **0,0%** | 49 | 0,0% | Keine Coverage |

---

## Schritt 2 — CRAP-Score-Ranking (alle CRAP > 100, 0%-Coverage)

Vollständige Liste aller 103 Methoden mit `count=0` und CRAP > 100, absteigend.  
Formel: `CRAP = cx² + cx` (bei 0% Coverage vereinfacht).

| Rang | CRAP | Paket | Klasse | Methode | cx | Sichtbarkeit |
|---|---|---|---|---|---|---|
| 1 | 10.100 | Report | RightToLeftSupport | finishCurrentSpan | 100 | private |
| 2 | 7.310 | Report | ReportParserGenerate | listStartHandler | 85 | protected |
| 3 | 6.972 | Report | RightToLeftSupport | spanLtrRtl | 83 | public |
| 4 | 3.660 | Report | ReportPdfTextBox | render | 60 | public |
| 5 | 2.256 | Report | ReportHtmlTextBox | render | 47 | public |
| 6 | 1.722 | Http\RequestHandlers | SearchGeneralPage | handle | 41 | public |
| 7 | 1.406 | Http\RequestHandlers | CalendarEvents | handle | 37 | public |
| 8 | 1.122 | Module\InteractiveTree | TreeView | drawPerson | 33 | private |
| 9 | 992 | Report | ReportHtmlCell | render | 31 | public |
| 10 | 930 | Module | SlideShowModule | getBlock | 30 | public |
| 11 | 870 | Http\Middleware | BadBotBlocker | process | 29 | public |
| 12 | 812 | Module | YahrzeitModule | getBlock | 28 | public |
| 13 | 650 | Http\RequestHandlers | ChangeFamilyMembersAction | handle | 25 | public |
| 14 | 600 | (root) | StatisticsData | centuryName | 24 | private |
| 15 | 600 | (root) | StatisticsFormat | century | 24 | public |
| 16 | 552 | (root) | StatisticsData | ageOfMarriageQuery | 23 | public |
| 17 | 552 | Cli\Commands | UserEdit | execute | 23 | protected |
| 18 | 552 | Report | ReportParserGenerate | relativesStartHandler | 23 | protected |
| 19 | 552 | Report | ReportParserGenerate | getGedcomValue | 23 | private |
| 20 | 462 | Services | RelationshipService | legacyCousinName2 | 21 | private |
| 21 | 420 | (root) | StatisticsData | usersLoggedInQuery | 20 | private |
| 22 | 420 | Http\RequestHandlers | MapDataImportAction | handle | 20 | public |
| 23 | 420 | Services | GedcomEditService | editLinesToGedcom | 20 | public |
| 24 | 380 | Cli\Commands | UserTreeSetting | execute | 19 | protected |
| 25 | 380 | Report | HtmlRenderer | run | 19 | public |
| 26 | 342 | Cli\Commands | TreeSetting | execute | 18 | protected |
| 27 | 342 | Http\RequestHandlers | HelpText | handle | 18 | public |
| 28 | 342 | Module | FanChartModule | chart | 18 | protected |
| 29 | 342 | Report | ReportPdfCell | render | 18 | public |
| 30 | 306 | Cli\Commands | SiteSetting | execute | 17 | protected |
| 31 | 306 | Cli\Commands | UserSetting | execute | 17 | protected |
| 32 | 306 | Http\RequestHandlers | GedcomLoad | handle | 17 | public |
| 33 | 306 | Http\RequestHandlers | MergeRecordsPage | handle | 17 | public |
| 34 | 306 | Module | ReviewChangesModule | getBlock | 17 | public |
| 35 | 306 | Services | CalendarService | getCalendarEvents | 17 | public |
| 36 | 306 | Services | CalendarService | getEventsList | 17 | public |
| 37 | 272 | Http\RequestHandlers | ManageMediaData | handle | 16 | public |
| 38 | 272 | Http\RequestHandlers | MergeFactsPage | handle | 16 | public |
| 39 | 272 | Http\RequestHandlers | ReportSetupPage | handle | 16 | public |
| 40 | 272 | Http\RequestHandlers | TreePrivacyAction | handle | 16 | public |
| 41 | 272 | Module | ClippingsCartModule | postDownloadAction | 16 | public |
| 42 | 272 | Report | ReportParserGenerate | setVarStartHandler | 16 | protected |
| 43 | 272 | Report | ReportParserGenerate | addDescendancy | 16 | private |
| 44 | 240 | Cli\Commands | TreeExport | execute | 15 | protected |
| 45 | 240 | Http\RequestHandlers | MergeFactsAction | handle | 15 | public |
| 46 | 240 | Http\RequestHandlers | RenumberTreeAction | handle | 15 | public |
| 47 | 240 | Http\RequestHandlers | UserEditAction | handle | 15 | public |
| 48 | 240 | Report | ReportParserGenerate | imageStartHandler | 15 | protected |
| 49 | 210 | Report | ReportHtmlFootnote | getWidth | 14 | public |
| 50 | 210 | Report | ReportHtmlText | getWidth | 14 | public |
| 51 | 210 | Report | ReportParserGenerate | repeatTagStartHandler | 14 | protected |
| 52 | 210 | Report | ReportPdfFootnote | getWidth | 14 | public |
| 53 | 210 | Report | ReportPdfText | getWidth | 14 | public |
| 54 | 210 | Services | MediaFileService | uploadFile | 14 | public |
| 55 | 182 | Factories | EncodingFactory | detect | 13 | public |
| 56 | 182 | Http\RequestHandlers | EditMediaFileAction | handle | 13 | public |
| 57 | 182 | Module | ChartsBlockModule | getBlock | 13 | public |
| 58 | 182 | Module | TimelineChartModule | chart | 13 | protected |
| 59 | 182 | Report | ReportHtmlImage | render | 13 | public |
| 60 | 182 | Report | ReportParserGenerate | startElement | 13 | protected |
| 61 | 182 | Report | ReportParserGenerate | endElement | 13 | protected |
| 62 | 182 | Report | ReportParserGenerate | gedcomStartHandler | 13 | protected |
| 63 | 182 | Report | ReportParserGenerate | varStartHandler | 13 | protected |
| 64 | 182 | Report | ReportParserGenerate | factsStartHandler | 13 | protected |
| 65 | 182 | Report | ReportParserGenerate | factsEndHandler | 13 | protected |
| 66 | 182 | Report | ReportParserGenerate | listEndHandler | 13 | protected |
| 67 | 182 | Report | ReportParserGenerate | relativesEndHandler | 13 | protected |
| 68 | 156 | Cli\Commands | TreeEdit | execute | 12 | protected |
| 69 | 156 | Elements | NoteStructure | labelValue | 12 | public |
| 70 | 156 | Exceptions | FileUploadException | __construct | 12 | public |
| 71 | 156 | Http\Middleware | HandleExceptions | process | 12 | public |
| 72 | 156 | Http\Middleware | Router | process | 12 | public |
| 73 | 156 | Http\RequestHandlers | CalendarPage | handle | 12 | public |
| 74 | 156 | Http\RequestHandlers | LinkChildToFamilyAction | handle | 12 | public |
| 75 | 156 | Http\RequestHandlers | LoginPage | handle | 12 | public |
| 76 | 156 | Http\RequestHandlers | MapDataExportCSV | handle | 12 | public |
| 77 | 156 | Module | HitCountFooterModule | process | 12 | public |
| 78 | 156 | Module | ModuleThemeTrait | individualBoxFacts | 12 | public |
| 79 | 156 | Report | ReportParserGenerate | getPersonNameStartHandler | 12 | protected |
| 80 | 156 | Report | ReportParserGenerate | gedcomValueStartHandler | 12 | protected |
| 81 | 156 | Report | ReportParserGenerate | addAncestors | 12 | private |
| 82 | 132 | (root) | StatisticsData | parentsQuery | 11 | public |
| 83 | 132 | (root) | StatisticsData | marriageQuery | 11 | public |
| 84 | 132 | Factories | GedcomRecordFactory | newGedcomRecord | 11 | private |
| 85 | 132 | Http\RequestHandlers | ContactAction | handle | 11 | public |
| 86 | 132 | Http\RequestHandlers | GedcomRecordPage | handle | 11 | public |
| 87 | 132 | Http\RequestHandlers | MapDataSave | handle | 11 | public |
| 88 | 132 | Http\RequestHandlers | SetupWizard | handle | 11 | public |
| 89 | 132 | Http\RequestHandlers | UploadMediaAction | handle | 11 | public |
| 90 | 132 | Module | LanguageFrench | relationships | 11 | public |
| 91 | 132 | Module | ResearchTaskModule | getBlock | 11 | public |
| 92 | 132 | Module\InteractiveTree | TreeView | drawChildren | 11 | private |
| 93 | 132 | Report | ReportParserGenerate | repeatTagEndHandler | 11 | protected |
| 94 | 132 | Report | ReportParserGenerate | ifStartHandler | 11 | protected |
| 95 | 132 | Report | ReportPdfImage | render | 11 | public |
| 96 | 132 | Services | GedcomEditService | insertMissingLevels | 11 | protected |
| 97 | 110 | Census | Census | censusPlaces | 10 | public |
| 98 | 110 | Cli\Commands | TreeImport | execute | 10 | protected |
| 99 | 110 | Http\RequestHandlers | DeleteRecord | handle | 10 | public |
| 100 | 110 | Http\RequestHandlers | ManageMediaData | mediaObjectInfo | 10 | private |
| 101 | 110 | Module | ClippingsCartModule | postAddIndividualAction | 10 | public |
| 102 | 110 | Module | TopSurnamesModule | getBlock | 10 | public |
| 103 | 110 | Module | UpcomingAnniversariesModule | getBlock | 10 | public |

---

## Schritt 3 — Klassifikation: DB-abhängig vs. Bootstrap-only

Alle Kandidaten landen unabhängig vom DB-Bedarf in `layer3-integration/tests/` (MysqlTestCase).  
Unterschied: ob `createTreeWithGedcom()` im Test benötigt wird.

### Bootstrap-only (kein `createTreeWithGedcom()` nötig)

| CRAP | Klasse | Methode | Begründung | Testbarkeit |
|---|---|---|---|---|
| 10.100 | RightToLeftSupport | finishCurrentSpan | private static — erreichbar via `spanLtrRtl` | Via public spanLtrRtl |
| 7.310 | ReportParserGenerate | listStartHandler | protected SAX-Handler — kein DB direkt, aber Tree für Daten | Via Subklasse/Reflection oder Report-Lauf |
| 6.972 | RightToLeftSupport | spanLtrRtl | public static — reine String/DOM-Logik | `RightToLeftSupport::spanLtrRtl(string)` direkt |
| 2.256 | ReportHtmlTextBox | render | HTML-DOM-Render — kein DB | Bootstrap mit HTML-Renderer |
| 992 | ReportHtmlCell | render | HTML-Cell-Render — kein DB | Bootstrap mit HTML-Renderer |
| 600 | StatisticsFormat | century | public — reine I18N-Format-Funktion | `new StatisticsFormat(); $f->century(21)` |
| 552 | ReportParserGenerate | relativesStartHandler | protected SAX-Handler | Via Report-Lauf |
| 552 | ReportParserGenerate | getGedcomValue | private SAX-Hilfsmethode | Via Report-Lauf |
| 380 | HtmlRenderer | run | Report-HTML-Renderer — AbstractRenderer | Bootstrap mit Report-Objekten |
| 272 | ReportParserGenerate | setVarStartHandler | protected SAX-Handler | Via Report-Lauf |
| 272 | ReportParserGenerate | addDescendancy | private Hilfsmethod | Via Report-Lauf |
| 240 | ReportParserGenerate | imageStartHandler | protected SAX-Handler | Via Report-Lauf |
| 210 | ReportHtmlFootnote | getWidth | public — Breiten-Berechnung | Bootstrap direkt |
| 210 | ReportHtmlText | getWidth | public — Breiten-Berechnung | Bootstrap direkt |
| 210 | ReportParserGenerate | repeatTagStartHandler | protected SAX-Handler | Via Report-Lauf |
| 182 | EncodingFactory | detect | public — Encoding-Erkennung aus Byte-Sequenz | `new EncodingFactory(); $f->detect(string)` |
| 182 | ReportHtmlImage | render | public — HTML-Bild-Render | Bootstrap mit HTML-Renderer |
| 182 | ReportParserGenerate | startElement/endElement/… (9×) | protected/private SAX-Handler | Via Report-Lauf |
| 156 | NoteStructure | labelValue | public — GEDCOM-Element-Rendering | Bootstrap via `new NoteStructure` |
| 156 | FileUploadException | __construct | public — Exception-Konstruktor | Bootstrap direkt |
| 156 | ReportParserGenerate | getPersonNameStartHandler / gedcomValueStartHandler (2×) | protected SAX-Handler | Via Report-Lauf |
| 132 | ReportParserGenerate | repeatTagEndHandler / ifStartHandler (2×) | protected SAX-Handler | Via Report-Lauf |
| 132 | LanguageFrench | relationships | public — Beziehungsnamen-Array | Bootstrap direkt |
| 110 | Census | censusPlaces | public static — Konfigurations-Array | `Census::censusPlaces('en')` direkt |

> **Hinweis:** ReportParserGenerate-Methoden (protected/private) sind nur über den XML-Parser-Mechanismus
> erreichbar. Der effiziente Weg ist, einen vollständigen Report-Lauf via `ReportSetupPage::handle()`
> auszuführen — das triggert die gesamte SAX-Kette auf einmal.

### DB-abhängig (braucht `createTreeWithGedcom()`)

| CRAP | Klasse | Methode | DB-Zugriff | Feature-Matrix-Bezug |
|---|---|---|---|---|
| 3.660 | ReportPdfTextBox | render | Indirekt via Report-Daten | — (PDF → Skip-Kandidat) |
| 1.722 | SearchGeneralPage | handle | `SearchService` → `DB::table()` 4× | S26 (Teststufe 3 primär) |
| 1.406 | CalendarEvents | handle | `CalendarService` → `DB::table('dates')` | S31 (Teststufe 3) |
| 1.122 | TreeView | drawPerson | `Individual` aus DB (private — via `getIndividuals`) | S18 |
| 930 | SlideShowModule | getBlock | Media-Records aus DB | — |
| 870 | BadBotBlocker | process | Session/IP-Check → DB | — (Middleware → Skip) |
| 812 | YahrzeitModule | getBlock | `CalendarService` → DB | — |
| 650 | ChangeFamilyMembersAction | handle | `DB::table('link')` | — (Edit-Funktion) |
| 600 | StatisticsData | centuryName | `DB::table('dates')` (private — via Statistics-Methode) | — |
| 552 | StatisticsData | ageOfMarriageQuery | `DB::table('families')` | — |
| 462 | RelationshipService | legacyCousinName2 | private — via `legacyNameAlgorithm` (kein DB) | S16 |
| 420 | StatisticsData | usersLoggedInQuery | `DB::table('session')` (private) | — |
| 420 | MapDataImportAction | handle | Kartenobjekte → DB | — (Map-Modul) |
| 420 | GedcomEditService | editLinesToGedcom | `Registry::gedcomRecordFactory()`, Tree | — |
| 380 | (Cli-Klassen) | execute | UserService via DB | — (Symfony-Console → Skip) |
| 342 | HelpText | handle | Kein DB — nur Template-Render | Bootstrap-fähig |
| 342 | FanChartModule | chart | `Individual` aus DB (protected) | S18 |
| 306 | ReviewChangesModule | getBlock | Pending-Changes aus DB | — |
| 306 | CalendarService | getCalendarEvents | `DB::table('dates')` | S31 |
| 306 | CalendarService | getEventsList | `DB::table('dates')` | S31 |
| 272 | MergeFactsPage | handle | `GedcomRecord` aus DB | — |
| 272 | ReportSetupPage | handle | Triggert Report-Kette (DB für Daten) | — |
| 272 | TreePrivacyAction | handle | Baum-Privacy-Einstellungen | — |
| 272 | ClippingsCartModule | postDownloadAction | Clippings via DB | — |
| 272 | ManageMediaData | handle | Media-Objekte via DB | — |
| 240 | MergeFactsAction | handle | Schreibt GEDCOM-Records via DB | — |
| 240 | RenumberTreeAction | handle | Umnummeriert alle XREFs | — |
| 240 | UserEditAction | handle | UserService via DB | — |
| 210 | MediaFileService | uploadFile | Datei-Upload + DB | — (Upload-Kontext nötig) |
| 182 | ChartsBlockModule | getBlock | ModuleService + Charts | — |
| 182 | TimelineChartModule | chart | `Individual` aus DB (protected) | S18 |
| 182 | EditMediaFileAction | handle | Media via DB | — |
| 156 | HitCountFooterModule | process | `DB::table('hit_counter')` | — |
| 156 | ModuleThemeTrait | individualBoxFacts | `Individual`-Fakten aus DB | — |
| 156 | CalendarPage | handle | Kalenderseite mit DB | S31 |
| 156 | LoginPage | handle | Auth + Session (kein Redirect-Test nötig) | — |
| 156 | LinkChildToFamilyAction | handle | Verknüpft Kind mit Familie | — |
| 132 | StatisticsData | parentsQuery | `DB::table('families')` | — |
| 132 | StatisticsData | marriageQuery | `DB::table('families')` | — |
| 132 | GedcomRecordFactory | newGedcomRecord | private — via Factory-Aufruf | — |
| 132 | GedcomRecordPage | handle | Rendert GEDCOM-Record | — |
| 132 | ResearchTaskModule | getBlock | Research-Tasks via DB | — |
| 132 | TreeView | drawChildren | private — via `getIndividuals` | S18 |
| 132 | GedcomEditService | insertMissingLevels | `Fact`-Objekte aus DB | — |
| 110 | DeleteRecord | handle | Löscht GEDCOM-Record | — |
| 110 | ManageMediaData | mediaObjectInfo | private — via handle() | — |
| 110 | ClippingsCartModule | postAddIndividualAction | Individual aus DB | — |
| 110 | TopSurnamesModule | getBlock | `DB::table('name')` | — |
| 110 | UpcomingAnniversariesModule | getBlock | `CalendarService` → DB | — |
| 110 | ResearchTaskModule | getBlock | Research-Tasks via DB | — |

### Skip-Kandidaten

| Grund | Klassen | Empfehlung |
|---|---|---|
| **PDF/FPDF-Abhängigkeit** | ReportPdfTextBox, ReportPdfCell, ReportPdfFootnote, ReportPdfText, ReportPdfImage | Kein Layer-3-Test — FPDF/TCPDF-Setup komplex, kein Layer-3-Mehrwert |
| **Symfony Console (protected execute)** | UserEdit, UserTreeSetting, TreeSetting, SiteSetting, UserSetting, TreeExport, TreeEdit, TreeImport | Niedrigere Priorität — CLI-Tests erfordern eigene Console-Test-Infrastruktur |
| **HTTP-Middleware** | BadBotBlocker, HandleExceptions, Router | Router::process braucht vollständige Dispatch-Kette; dedizierter Middleware-Test sinnvoller |
| **Datei-Upload-Kontext** | MediaFileService::uploadFile, UploadMediaAction | Benötigen echten Multipart-Upload — E2E-Territory |
| **Komplex/Setup-Wizard** | SetupWizard, MapDataImportAction, MapDataSave, MapDataExportCSV | Zu viel Setup-Overhead für Layer-3; ContactAction braucht SMTP |

---

## Schritt 4 — Gap-Analyse Feature-Matrix × Coverage

FM-IDs für Teststufe 2: G01–G04, G07–G10, G12–G16, G24, S01–S03, S05–S08, S10–S12, S19, S21, S22, P01–P24, P27–P29.

### GEDCOM Import/Export (G01–G24) — vollständig grün

Alle G-IDs (G01–G04, G07–G10, G12–G16, G24) haben Testklassen und zeigen grüne Coverage.
Keine neuen Lücken durch AP1–AP4.

### Suche & Navigation (S01–S22)

| ID | Testklasse | Coverage-Status | Bemerkung |
|---|---|---|---|
| S01–S03 | SearchIntegrationTest | **grün** | SearchService 43,6% stmt |
| S05–S08 | SearchIntegrationTest | **grün** | Erweiterte + phonetische Suche |
| S10–S12 | SearchIntegrationTest | **grün** | Paginierung, Cross-Tree, Zugriff |
| S16 | RelationshipServiceIntegrationTest | **grün** | legacyCousinName jetzt abgedeckt; legacyCousinName2 noch 0% (AP9) |
| S19 | ListModuleIntegrationTest | **grün** | AbstractIndividualListModule 47,9% |
| S21 | AutoCompleteIntegrationTest | **grün** | |
| S22 | SearchIntegrationTest + AutoCompleteIntegrationTest | **grün** | |

### Datenschutz & Zugriffskontrolle (P01–P29) — vollständig grün

Alle P-IDs haben Testklassen. Keine Lücken durch AP1–AP4.

### Residuelle Coverage-Lücken

| ID | Lücke | Ursache | Risiko |
|---|---|---|---|
| S16 | `legacyCousinName2` (CRAP 462, cx=21) — count=0 | private static; AP1 deckt legacyCousinName ab, aber legacyCousinName2 braucht spezifischere Pfade | Mittel |
| S18 | `TreeView::drawPerson` + `drawChildren` (CRAP 1.122 + 132) | private — via getIndividuals() erreichbar | Mittel |
| S31 | `CalendarService::getCalendarEvents` + `getEventsList` (CRAP 306 je) | CalendarChartIntegrationTest deckt nur getAnniversaryEvents | Niedrig |

---

## Schritt 5 — Priorisierter Handlungsplan (ohne Code)

### Gruppe A: CRAP > 1.000 (höchste Priorität)

| CRAP | Klasse | Methode | cx | Umsetzungsidee | Aufwand |
|---|---|---|---|---|---|
| 10.100 | RightToLeftSupport | finishCurrentSpan | 100 | **Indirekt via spanLtrRtl** — private static, wird intern von spanLtrRtl aufgerufen. Ein Test mit RTL-String deckt beide Methoden ab. | niedrig |
| 7.310 | ReportParserGenerate | listStartHandler | 85 | **Via ReportSetupPage::handle()** — HTTP-Request mit vorhandenem Report-XML triggert die SAX-Kette und damit alle protected/private Handler-Methoden auf einmal. | hoch |
| 6.972 | RightToLeftSupport | spanLtrRtl | 83 | **Direkter static-Aufruf** — Bootstrap-only, `RightToLeftSupport::spanLtrRtl($rtlString)`. | niedrig |
| 3.660 | ReportPdfTextBox | render | 60 | **Skip** — PDF/FPDF-Abhängigkeit; kein Layer-3-Mehrwert. | — |
| 2.256 | ReportHtmlTextBox | render | 47 | **Bootstrap-Test** — `new ReportHtmlTextBox(...)` instanziieren, `render()` aufrufen, Output-Buffer prüfen. | mittel |
| 1.722 | SearchGeneralPage | handle | 41 | **DB-Test** — `new SearchGeneralPage(new SearchService(), $treeService); handle($request)` mit query-Parametern. | mittel |
| 1.406 | CalendarEvents | handle | 37 | **DB-Test** — `new CalendarEvents(new CalendarService()); handle($request)` mit view=day, cal, day, month, year. | mittel |
| 1.122 | TreeView | drawPerson | 33 | **Via getIndividuals** — TreeView::getIndividuals() ist public und ruft drawPerson intern auf. Request mit XREF und generations. | mittel |

### Gruppe B: CRAP 300–1.000

| CRAP | Klasse | Methode | cx | Umsetzungsidee | Aufwand |
|---|---|---|---|---|---|
| 992 | ReportHtmlCell | render | 31 | Bootstrap-Test — direkt instanziieren, render() | niedrig |
| 930 | SlideShowModule | getBlock | 30 | DB-Test — `new SlideShowModule(new LinkedRecordService()); getBlock($tree, $blockId, $context)` | niedrig |
| 812 | YahrzeitModule | getBlock | 28 | DB-Test — `new YahrzeitModule(new CalendarService()); getBlock(...)` | niedrig |
| 650 | ChangeFamilyMembersAction | handle | 25 | DB-Test — Handle mit leerer Familie | hoch |
| 600 | StatisticsFormat | century | 24 | Bootstrap-Test — `(new StatisticsFormat())->century(21)` | niedrig |
| 600 | StatisticsData | centuryName | 24 | **Skip direkt** (private) — via Statistics-Methode erreichbar, aber kein publischer Einstiegspunkt für centuryName | — |
| 552 | StatisticsData | ageOfMarriageQuery | 23 | DB-Test — `new StatisticsData($tree, $userService)->ageOfMarriageQuery(...)` | mittel |
| 462 | RelationshipService | legacyCousinName2 | 21 | **via legacyNameAlgorithm** — spezifische Pfade die legacyCousinName2 triggern (fernere Grad-Stufen) | mittel |
| 420 | GedcomEditService | editLinesToGedcom | 20 | DB-Test — Instanz + Record-Typen | mittel |
| 380 | HtmlRenderer | run | 19 | Bootstrap-Test — ReportHtml-Objekte + HtmlRenderer | mittel |
| 342 | HelpText | handle | 18 | Bootstrap-Test — `new HelpText(); handle($request)` mit topic-Param | niedrig |
| 342 | FanChartModule | chart | 18 | DB-Test — `new FanChartModule(new ChartService())->chart($individual, ...)` | niedrig |
| 306 | ReviewChangesModule | getBlock | 17 | DB-Test — EmailService + TreeService + UserService | mittel |
| 306 | CalendarService | getCalendarEvents | 17 | DB-Test — Extend CalendarChartIntegrationTest | niedrig |
| 306 | CalendarService | getEventsList | 17 | DB-Test — Extend CalendarChartIntegrationTest | niedrig |
| 306 | GedcomLoad | handle | 17 | DB-Test — POST-Request mit Datei-Quelle (komplex) | hoch |
| 306 | MergeRecordsPage | handle | 17 | DB-Test — zwei bekannte Records | hoch |

### Gruppe C: CRAP 100–300

Sammlung der wichtigsten actionable Kandidaten:

| CRAP | Klasse | Methode | Umsetzungsidee | Aufwand |
|---|---|---|---|---|
| 272 | ReportSetupPage | handle | DB-Test — triggert ReportParserGenerate SAX-Kette | hoch (deckt 20+ ReportParser-Methoden) |
| 272 | TreePrivacyAction | handle | DB-Test — setze Privacy-Flag auf Tree | niedrig |
| 272 | ClippingsCartModule | postDownloadAction | DB-Test — Download-Action mit Clippings-Cart | mittel |
| 272 | ManageMediaData | handle | DB-Test — Media-Listing | niedrig |
| 272 | MergeFactsPage | handle | DB-Test — zwei Facts anzeigen | mittel |
| 240 | MergeFactsAction | handle | DB-Test — Facts zusammenführen | hoch |
| 240 | RenumberTreeAction | handle | DB-Test — Baum umnummerieren | niedrig |
| 240 | UserEditAction | handle | DB-Test — User bearbeiten | mittel |
| 210 | ReportHtmlFootnote | getWidth | Bootstrap | niedrig |
| 210 | ReportHtmlText | getWidth | Bootstrap | niedrig |
| 182 | EncodingFactory | detect | Bootstrap — detect(byteString) | niedrig |
| 182 | ChartsBlockModule | getBlock | DB-Test — ModuleService | niedrig |
| 182 | TimelineChartModule | chart | DB-Test — protected, extend ChartModuleIntegrationTest | niedrig |
| 182 | ReportHtmlImage | render | Bootstrap | niedrig |
| 156 | NoteStructure | labelValue | Bootstrap | niedrig |
| 156 | FileUploadException | __construct | Bootstrap | niedrig |
| 156 | CalendarPage | handle | DB-Test | niedrig |
| 156 | ModuleThemeTrait | individualBoxFacts | DB-Test | mittel |
| 132 | StatisticsData | parentsQuery | DB-Test | niedrig |
| 132 | StatisticsData | marriageQuery | DB-Test | niedrig |
| 132 | GedcomRecordPage | handle | DB-Test | niedrig |
| 132 | GedcomEditService | insertMissingLevels | DB-Test | mittel |
| 132 | LanguageFrench | relationships | Bootstrap | niedrig |
| 132 | ResearchTaskModule | getBlock | DB-Test | niedrig |
| 110 | Census | censusPlaces | Bootstrap — static call | niedrig |
| 110 | DeleteRecord | handle | DB-Test | niedrig |
| 110 | ClippingsCartModule | postAddIndividualAction | DB-Test | niedrig |
| 110 | TopSurnamesModule | getBlock | DB-Test | niedrig |
| 110 | UpcomingAnniversariesModule | getBlock | DB-Test | niedrig |

---

## Schritt 6 — testing-bigpicture.md Diff-Vorschläge

### 6.1 — Ratchet Ist-Stand aktualisieren

Abschnitt `### Ist-Stand (Teststufe 2, Stand: 2026-04-03)` (ca. Zeile 1115):

```diff
-### Ist-Stand (Teststufe 2, Stand: 2026-04-03)
+### Ist-Stand (Teststufe 2, Stand: 2026-04-03, aktualisiert nach AP1–AP4)
 
-> Coverage-Erweiterung (Voll-Lauf-Baseline) waren es 285 Tests / 17,9%.
+> Basis: `make test-integration` (alle 21 Testklassen, 296 Tests, 899 Assertions).
+> Vorherige Baseline (285 Tests, vor AP1–AP4): 17,9% / 17,3%.
 
-| Anweisungsüberdeckung | 17,9% (7.882 / 44.066 Statements) — Ratchet-Basis |
-| Methodenüberdeckung | 17,3% (767 / 4.441 Methoden) |
+| Anweisungsüberdeckung | 19,8% (8.716 / 44.066 Statements) — Ratchet-Basis |
+| Methodenüberdeckung | 17,7% (787 / 4.441 Methoden) |
```

---

## Schritt 7 — Einschränkungen und Messartefakte

| Einschränkung | Auswirkung | Empfehlung |
|---|---|---|
| pcov Statement-Level (keine Branch-Coverage) | Methoden mit count>0 können intern untestete Branches haben | Xdebug ergänzend für kritische Methoden |
| Private Methoden im CRAP-Ranking | finishCurrentSpan, centuryName, usersLoggedInQuery, drawPerson, drawChildren, getGedcomValue u.a. nicht direkt testbar | Indirektion via öffentliche Interface-Methoden |
| PDF-Klassen (FPDF/TCPDF) | ReportPdfTextBox, ReportPdfCell, ReportPdfFootnote, ReportPdfText, ReportPdfImage brauchen FPDF-Setup | Skip für Layer-3; ggf. eigenständiger FPDF-Test |
| Symfony-Console-Commands | UserEdit, TreeSetting etc. nur über Console-Infrastruktur testbar | Niedrigere Priorität; CLI-Tests als eigene Layer-3-Erweiterung denkbar |
| ReportParserGenerate 20+ SAX-Handler | Alle protected/private — einzeln nicht aufrufbar | Alle via ReportSetupPage::handle() auf einmal erreichbar |
| StatisticsData private Methoden | centuryName, usersLoggedInQuery nur über Statistics-Statistik-Methoden erreichbar | Öffentliche Methoden auf Statistics-Klasse direkt aufrufen |
| Middleware-Klassen | BadBotBlocker, HandleExceptions, Router brauchen vollständige Request-Dispatch-Kette | Skip für Layer-3; E2E oder eigene Middleware-Tests sinnvoller |
| CalendarEvents::handle benötigt gültigen `view`-Parameter | Validator prüft view ∈ {day, month, year} — falscher Wert → 400 | Request-Parameter exakt nach Validator-Anforderung setzen |
