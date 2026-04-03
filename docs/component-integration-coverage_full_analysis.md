<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Coverage-Analyse — Komponentenintegrationstest (Teststufe 2) — Vollständiger Lauf

> Stand: 2026-04-03  
> Eingabe: `artifacts/layer3/coverage.xml` aus `make test-integration` (alle 17 Testklassen)  
> Baseline Quick-Lauf (3 Klassen): 9,0% Statement-, 7,4% Methodenüberdeckung

---

## Vorbemerkung: Scope des Voll-Laufs

`make test-integration` führt **alle 17 Testklassen** aus (vs. 3 im Quick-Lauf):

```
AccessControlTest, AutoCompleteIntegrationTest, ChartModuleIntegrationTest,
CheckTreeIntegrationTest, GedcomImportTest, IndividualFactsIntegrationTest,
IsDeadTest, ListModuleIntegrationTest, PrivacySearchTest, PrivacyVisibilityTest,
RelationshipDbTest, RelationshipPrivacyTest, RelationshipServiceIntegrationTest,
ResnPrivacyTest, RomanNumeralsIntegrationTest, SearchIntegrationTest, TreeOperationsTest
```

Konsequenz: CRAP-Scores auf Basis dieser Coverage sind die echte Baseline.
Methoden, die im Quick-Lauf fälschlicherweise mit 0% erschienen (z.B.
`legacyNameAlgorithm`), sind jetzt korrekt bewertet.

---

## Schritt 1 — Gesamtüberblick

```
Gesamt-Anweisungsüberdeckung:   17,9%  (7.882 / 44.066 Statements)
Gesamt-Methodenüberdeckung:     17,3%  (767 / 4.441 Methoden)
```

Vergleich Quick-Lauf → Voll-Lauf:

| Metrik | Quick-Lauf | Voll-Lauf | Delta |
|---|---|---|---|
| Anweisungsüberdeckung | 9,0% (3.969 / 44.070) | **17,9% (7.882 / 44.066)** | +8,9 Pp |
| Methodenüberdeckung | 7,4% (329 / 4.442) | **17,3% (767 / 4.441)** | +9,9 Pp |

### Paket-Aufschlüsselung

| Paket | Dateien | Statements | Cov% | Methoden | MthCov% | Bewertung |
|---|---|---|---|---|---|---|
| Http/Routes | — | 370 | **99,7%** | 2 | 50,0% | Sehr gut abgedeckt |
| CustomTags | 20 | 825 | **97,2%** | 40 | 47,5% | Sehr gut abgedeckt |
| GedcomFilters | 1 | 27 | **81,5%** | 2 | 50,0% | Gut abgedeckt |
| Factories | 28 | 714 | **49,3%** | 109 | 55,1% | Partiell |
| (root) | 51 | 6.713 | **36,1%** | 919 | 27,9% | Partiell (Individual 76,9%, GedcomRecord, StatisticsData) |
| Services | 37 | 5.727 | **30,9%** | 297 | 22,2% | Partiell (IndividualFactsService 84,4%, GedcomImport 80,5%, RelService 11,1%) |
| Encodings | 16 | 80 | **26,3%** | 14 | 14,3% | Partiell |
| Http | — | 20 | **25,0%** | 3 | 33,3% | Partiell (Micro-Paket) |
| Date | 9 | 735 | **19,9%** | 85 | 18,8% | Gering |
| Module | 259 | 10.376 | **16,4%** | 1.357 | 23,1% | Gering |
| Http/RequestHandlers | 381 | 8.083 | **2,0%** | 617 | 1,3% | Marginal |
| Elements | 216 | 1.575 | **4,8%** | 201 | 10,5% | Marginal |
| Schema | 51 | 622 | **3,9%** | 49 | 6,1% | Marginal |
| Census | 197 | 2.552 | **0,0%** | 341 | 0,0% | Keine Coverage |
| Cli | — | 927 | **0,0%** | 50 | 0,0% | Keine Coverage |
| CommonMark | 7 | 58 | **0,0%** | 14 | 0,0% | Keine Coverage |
| Exceptions | — | 49 | **0,0%** | 11 | 0,0% | Keine Coverage |
| Http/Middleware | — | 545 | **0,0%** | 69 | 0,0% | Keine Coverage |
| Module/InteractiveTree | — | 155 | **0,0%** | 11 | 0,0% | Keine Coverage |
| Report | 28 | 3.137 | **0,0%** | 198 | 0,0% | Keine Coverage |
| Statistics/Service | — | 504 | **0,0%** | 3 | 0,0% | Keine Coverage |
| SurnameTradition | 10 | 255 | **0,0%** | 49 | 0,0% | Keine Coverage |

**Hinweis:** Im Vergleich zum Quick-Lauf haben folgende Pakete deutlich an Coverage gewonnen:
- `Services`: 14,8% → 30,9% (RelationshipService jetzt getestet)
- `Module`: 2,0% → 16,4% (IndividualFactsService, ListModule, ChartModule jetzt aktiv)
- `(root)`: 20,5% → 36,1% (Individual, Privacy-Tests vollständig)
- `Http/RequestHandlers`: 4,1% → 2,0% (Rückgang durch korrektere Zählung nach Voll-Lauf — CheckTree jetzt besser erfasst aber Gesamtpaket sehr groß)

---

## Schritt 2 — CRAP-Score-Ranking Top 30 (Voll-Lauf-Baseline)

Alle Methoden mit `count=0` (ungetestet im Voll-Lauf), absteigend nach CRAP.  
Formel: `CRAP = cx² + cx` (bei 0% Coverage vereinfacht).

| Rang | CRAP | Paket | Klasse | Methode | cx |
|---|---|---|---|---|---|
| 1 | 14.042 | Module | StatisticsChartModule | postCustomChartAction | 118 |
| 2 | 10.100 | Report | RightToLeftSupport | finishCurrentSpan | 100 |
| 3 | 7.310 | Report | ReportParserGenerate | listStartHandler | 85 |
| 4 | 6.972 | Report | RightToLeftSupport | spanLtrRtl | 83 |
| 5 | 3.660 | Report | ReportPdfTextBox | render | 60 |
| 6 | 2.652 | Services | RelationshipService | legacyCousinName | 51 |
| 7 | 2.256 | Report | ReportHtmlTextBox | render | 47 |
| 8 | 1.722 | Http/RequestHandlers | SearchGeneralPage | handle | 41 |
| 9 | 1.406 | Http/RequestHandlers | CalendarEvents | handle | 37 |
| 10 | 1.122 | Module/InteractiveTree | TreeView | drawPerson | 33 |
| 11 | 992 | Report | ReportHtmlCell | render | 31 |
| 12 | 930 | Module | SlideShowModule | getBlock | 30 |
| 13 | 870 | Http/Middleware | BadBotBlocker | process | 29 |
| 14 | 870 | Services | CalendarService | getAnniversaryEvents | 29 |
| 15 | 812 | Module | YahrzeitModule | getBlock | 28 |
| 16 | 756 | Module | RelationshipsChartModule | chart | 27 |
| 17 | 650 | Http/RequestHandlers | ChangeFamilyMembersAction | handle | 25 |
| 18 | 600 | (root) | StatisticsData | centuryName | 24 |
| 19 | 600 | (root) | StatisticsFormat | century | 24 |
| 20 | 552 | (root) | StatisticsData | ageOfMarriageQuery | 23 |
| 21 | 552 | Cli/Commands | UserEdit | execute | 23 |
| 22 | 552 | Report | ReportParserGenerate | relativesStartHandler | 23 |
| 23 | 552 | Report | ReportParserGenerate | getGedcomValue | 23 |
| 24 | 462 | Services | RelationshipService | legacyCousinName2 | 21 |
| 25 | 420 | (root) | StatisticsData | usersLoggedInQuery | 20 |
| 26 | 420 | Http/RequestHandlers | MapDataImportAction | handle | 20 |
| 27 | 420 | Services | GedcomEditService | editLinesToGedcom | 20 |
| 28 | 380 | Cli/Commands | UserTreeSetting | execute | 19 |
| 29 | 380 | Module | BranchesListModule | getDescendantsHtml | 19 |
| 30 | 380 | Report | HtmlRenderer | run | 19 |

**Voll-Lauf-Korrekturen gegenüber Quick-Lauf:**

| Methode | CRAP Quick | CRAP Voll | Erklärung |
|---|---|---|---|
| `RelationshipService::legacyNameAlgorithm` | 516.242 | **nicht in Top 30** | count=3, jetzt abgedeckt |
| `CheckTree::handle` | 4.160 | **nicht in Top 30** | count=2, Klasse 65,8% abgedeckt |
| `IndividualFactsService::childFacts` | 1.980 | **nicht in Top 30** | count=3, Klasse 84,4% abgedeckt |
| `IndividualFactsService::parentFacts` | 992 | **nicht in Top 30** | count=3, Klasse 84,4% abgedeckt |
| `AbstractIndividualListModule::handle` | 4.290 | **nicht in Top 30** | count=4, Klasse 47,9% abgedeckt |

---

## Schritt 3 — Layer-Abgrenzung

### Layer-3-Kandidaten — Top 15 (MySQL + Container nötig)

| CRAP | Klasse | Methode | Begründung Layer-3 | Feature-Matrix-Bezug |
|---|---|---|---|---|
| 14.042 | `StatisticsChartModule` | `postCustomChartAction` | Module mit `StatisticsData`-Aufruf; `StatisticsData` ruft `DB::table()` auf | — (kein FM-Eintrag) |
| 2.652 | `RelationshipService` | `legacyCousinName` | `private static` — Aufruf via `legacyNameAlgorithm`; traversiert DB-gespeicherte Cousin-Pfade | S16 |
| 1.722 | `SearchGeneralPage` | `handle` | `DB::table()` (4×: notes, locations, repos, sources) + `RequestHandler` | S38 (Teststufe 3) |
| 1.406 | `CalendarEvents` | `handle` | `RequestHandler` + `CalendarService` → `DB::table('dates')` | S31 (Teststufe 3) |
| 1.122 | `TreeView` | `drawPerson` | Module/InteractiveTree — rendert DB-gespeichertes `Individual` | S18 (Teststufe 3) |
| 930 | `SlideShowModule` | `getBlock` | Module — nutzt Media-Records aus DB | — (kein FM-Eintrag) |
| 870 | `CalendarService` | `getAnniversaryEvents` | `DB::table('dates')`, `Registry::individualFactory()` | S31 (Teststufe 3) |
| 812 | `YahrzeitModule` | `getBlock` | Module — `CalendarService`-Abhängigkeit (DB) | — (kein FM-Eintrag) |
| 756 | `RelationshipsChartModule` | `chart` | Module + `RelationshipService` (DB-Individuen); `Registry::routeFactory()` | S16, S18 |
| 650 | `ChangeFamilyMembersAction` | `handle` | `RequestHandler` + `DB::table('link')` | — (Admin-Funktion) |
| 600 | `StatisticsData` | `centuryName` | `DB::table('dates')` trotz Paket `(root)` | — (Statistiken) |
| 552 | `StatisticsData` | `ageOfMarriageQuery` | `DB::table('families')` | — (Statistiken) |
| 462 | `RelationshipService` | `legacyCousinName2` | `private static` — Aufruf via `legacyNameAlgorithm`; erweiterte Cousin-Grade | S16 |
| 420 | `StatisticsData` | `usersLoggedInQuery` | `DB::table('session')` | — (Statistiken) |
| 420 | `GedcomEditService` | `editLinesToGedcom` | `Registry::gedcomRecordFactory()`, `Tree`-Abhängigkeit | — (Edit-Funktion) |

### Layer-2-Kandidaten — Top 10 (isoliert testbar, kein Container)

| CRAP | Klasse | Methode | Begründung Layer-2 | Anmerkung |
|---|---|---|---|---|
| 10.100 | `RightToLeftSupport` | `finishCurrentSpan` | Paket `Report/` — in-memory DOM, keine DB | Upstream `tests/` prüfen |
| 7.310 | `ReportParserGenerate` | `listStartHandler` | Paket `Report/` — XML-Parser, keine DB | Upstream `tests/` prüfen |
| 6.972 | `RightToLeftSupport` | `spanLtrRtl` | Paket `Report/` — String/DOM-Logik | Upstream `tests/` prüfen |
| 3.660 | `ReportPdfTextBox` | `render` | Paket `Report/` — PDF-Render, keine DB | FPDF/TCPDF-Abhängigkeit beachten |
| 2.256 | `ReportHtmlTextBox` | `render` | Paket `Report/` — HTML-DOM-Render | Upstream `tests/` prüfen |
| 992 | `ReportHtmlCell` | `render` | Paket `Report/` — HTML-Cell-Render | Upstream `tests/` prüfen |
| 600 | `StatisticsFormat` | `century` | Paket `(root)` — reine Format-Methode (int→String) | Guter Kandidat für kurze Unit-Tests |
| 552 | `ReportParserGenerate` | `relativesStartHandler` | Paket `Report/` — XML-Parser | Upstream `tests/` prüfen |
| 552 | `ReportParserGenerate` | `getGedcomValue` | Paket `Report/` — GEDCOM-String-Extraktion | Upstream `tests/` prüfen |
| 380 | `HtmlRenderer` | `run` | Paket `Report/` — HTML-Ausgabe-Renderer | Upstream `tests/` prüfen |

> **Cli/Commands-Grenzfall:** `UserEdit::execute` (CRAP 552) ist ein Symfony-Console-Command.
> Der Code ruft intern `UserService` auf (kein direktes `DB::table()`), aber `UserService`
> nutzt Eloquent/DB. → Layer-3-Grenzfall; primär durch E2E oder dedizierte CLI-Tests abzudecken.

---

## Schritt 4 — Gap-Analyse Feature-Matrix × Coverage

Teststufe-2-IDs laut `testing-bigpicture.md`:
G01–G04, G07–G10, G12–G16, G24, S01–S03, S05–S08, S10–S12, S19, S21, S22, P01–P24, P27–P29

### GEDCOM Import/Export (G01–G24)

| ID | Bezeichnung | Testklasse (Layer-3) | Coverage-Status | Bemerkung |
|---|---|---|---|---|
| G01 | Record-Import (INDI) | `GedcomImportTest` | **grün** | GedcomImportService 80,5% stmt |
| G02 | Record-Import (FAM) | `GedcomImportTest` + `RelationshipDbTest` | **grün** | Beziehungsstruktur in DB verifiziert |
| G03 | Record-Import (Nebenrecords) | `GedcomImportTest` | **grün** | SOUR/NOTE/REPO/OBJE abgedeckt |
| G04 | Place-Hierarchie | `GedcomImportTest` | **grün** | place_location-Einträge geprüft |
| G07 | Encoding (UTF-8) | `GedcomImportTest` | **grün** | Zeichenverlust-Tests vorhanden |
| G08 | Encoding (ANSEL/CP1252) | `GedcomImportTest` | **grün** | 4 Tests: ANSEL, CP1252, Umlaute, Sonderzeichen |
| G09 | Inline-Media | `GedcomImportTest` | **grün** | 3 Tests: OBJE-Split, Dateireferenzen, Verknüpfung |
| G10 | Legacy-Formate | `GedcomImportTest` | **grün** | 4 Tests: _PLAC_DEFN, TNG-PLAC, Koordinaten |
| G12 | XREF-Vergabe | `GedcomImportTest` | **grün** | Eindeutigkeit geprüft |
| G13 | Export GEDCOM | `TreeOperationsTest` | **grün** | GedcomExportService 88,7% stmt |
| G14 | Export ZIP | `TreeOperationsTest` | **grün** | 3 Tests: ZIP valide, .ged enthalten, GEDZIP |
| G15 | Export ZIP+Media | `TreeOperationsTest` | **grün** | 2 Tests: Mediendateien + Referenzen |
| G16 | Export Privacy | `TreeOperationsTest` | **grün** | PRIV_HIDE + PRIV_NONE + PRIV_USER Regressions-Guard |
| G24 | Referenzintegrität (CheckTree) | `CheckTreeIntegrationTest` | **grün** | CheckTree 65,8% stmt; 200 OK + Body nicht leer |

### Suche & Navigation (S01–S22)

| ID | Bezeichnung | Testklasse (Layer-3) | Coverage-Status | Bemerkung |
|---|---|---|---|---|
| S01 | Allg. Suche (Personen) | `SearchIntegrationTest` | **grün** | SearchService 43,6% stmt |
| S02 | Allg. Suche (Familien) | `SearchIntegrationTest` | **grün** | |
| S03 | Allg. Suche (Quellen, Notizen, Repos) | `SearchIntegrationTest` | **grün** | |
| S05 | Erweiterte Suche (Felder) | `SearchIntegrationTest` | **grün** | 5 Tests |
| S06 | Erweiterte Suche (Datum-Mod.) | `SearchIntegrationTest` | **grün** | 3 Tests: ±0, ±5, ±20 Jahre |
| S07 | Phonetische Suche (Russell) | `SearchIntegrationTest` + `AutoCompleteIntegrationTest` | **grün** | |
| S08 | Phonetische Suche (DM) | `SearchIntegrationTest` | **grün** | 2 Tests |
| S10 | Paginierung | `SearchIntegrationTest` | **grün** | 3 Tests: Limit, Offset, Seite |
| S11 | Cross-Tree-Suche | `SearchIntegrationTest` | **grün** | 2 Tests |
| S12 | Zugriffskontrolle (Suche) | `SearchIntegrationTest` | **grün** | Guest vs. Admin |
| S16 | Chart: Beziehungsfinder | `RelationshipServiceIntegrationTest` | **grün** | legacyNameAlgorithm count=3; Klasse 11,1% — Cousin-Paths (S16-Erweiterung offen, s.u.) |
| S19 | Liste: Personen (Nachnamen) | `ListModuleIntegrationTest` | **grün** | handle count=4, Klasse 47,9% stmt |
| S21 | AutoComplete (Personen) | `AutoCompleteIntegrationTest` | **grün** | AutoCompleteSurname + Citation |
| S22 | AutoComplete (Orte) | `SearchIntegrationTest` + `AutoCompleteIntegrationTest` | **grün** | Submitter-Suche + AutoCompletePlace |

### Datenschutz & Zugriffskontrolle (P01–P29)

| ID | Bezeichnung | Testklasse (Layer-3) | Coverage-Status | Bemerkung |
|---|---|---|---|---|
| P01–P03 | Stammbaum-/Personen-Sichtbarkeit | `PrivacyVisibilityTest` | **grün** | Individual 76,9% stmt |
| P04–P13 | isDead()-Inferenz (8 Fälle) | `IsDeadTest` + `PrivacyVisibilityTest` | **grün** | Grenzwertanalyse vorhanden |
| P14–P15 | Vertrauliche Namen/Beziehungen | `PrivacyVisibilityTest` | **grün** | |
| P16–P21 | RESN-Ebenen (6 Typen) | `ResnPrivacyTest` | **grün** | none/privacy/confidential/fact/individuum/faktentyp |
| P22–P23 | Relationship Privacy | `RelationshipPrivacyTest` | **grün** | |
| P24 | Privacy in Suchergebnissen | `PrivacySearchTest` | **grün** | Lebende + RESN-Personen |
| P27–P29 | Zugriffssteuerung (edit/moderate/locked) | `AccessControlTest` | **grün** | |

### Lücken-Zusammenfassung

**Kein „rot":** Alle 54 zugeordneten Teststufe-2-IDs besitzen Testklassen.
Die Endekriterien für Teststufe 2 sind formal erfüllt.

**Residuelle Coverage-Lücken innerhalb abgedeckter IDs:**

| ID | Lücke | Ursache | Risiko |
|---|---|---|---|
| S16 | `legacyCousinName` (CRAP 2.652, cx=51) + `legacyCousinName2` (CRAP 462, cx=21) ungetestet | `private static` — Cousin-Grade-Erkennung nicht durch bisherige Pfad-Tests erreichbar | Mittel (bekannte Logik, hohe Komplexität) |
| S16 | RelationshipService Gesamtklasse nur 11,1% Coverage | Sehr große Klasse (1.523 Stmt) mit komplexem Algorithmus | Mittel |
| G24 | CheckTree 34,2% der Klasse ungetestet | Error-Paths und fehlende-XREF-Szenarien nicht abgedeckt | Niedrig |

---

## Schritt 5 — Priorisierter Handlungsplan

### Priorität 1: S16 Cousin-Pfade (legacyCousinName)

```
ID: S16-Cousin
Ziel: RelationshipService::legacyCousinName (CRAP 2.652, cx=51)
      RelationshipService::legacyCousinName2 (CRAP 462, cx=21)
Datei: layer3-integration/tests/RelationshipServiceIntegrationTest.php

Begründung: legacyCousinName ist private static, aber via legacyNameAlgorithm()
(öffentlich, jetzt getestet) erreichbar. Cousin-Pfade in legacyNameAlgorithm:
"fatbrofat", "fatbromotfat" etc. → triggern legacyCousinName. Mit CRAP 2.652
zweithöchster Layer-3-Score nach postCustomChartAction.

Umsetzung: Neue Testgruppe in RelationshipServiceIntegrationTest mit expliziten
Cousin-Pfad-Strings (erstes/zweites Cousin, entfernte Cousins).
```

### Priorität 2: StatisticsChartModule (postCustomChartAction)

```
ID: —
Ziel: StatisticsChartModule::postCustomChartAction (CRAP 14.042, cx=118)
Datei: layer3-integration/tests/ (neue Datei StatisticsChartIntegrationTest.php)

Begründung: Höchster CRAP-Score im Layer-3-Bereich. RequestHandler-Methode
(Http\RequestHandlers), aber Module-Aufruf via StatisticsData (DB-backed).
Smoke-Test: POST mit tree-Attribut und gültigem chart-Typ → 200 OK.

Umsetzung: Neue Testklasse. StatisticsChartModule als Handler instanziieren,
Request mit POST-Parameter für chart-type erstellen.
```

### Priorität 3: CalendarService + CalendarEvents

```
ID: S31 (Teststufe 3 primär, aber Layer-3-Komponente)
Ziel: CalendarService::getAnniversaryEvents (CRAP 870, cx=29)
      CalendarEvents::handle (CRAP 1.406, cx=37)
Datei: layer3-integration/tests/ (neue Datei CalendarIntegrationTest.php)

Begründung: CalendarService::getAnniversaryEvents ruft DB::table('dates') direkt auf.
Smoke-Test reicht: CalendarService mit Tree und Datum aufrufen → Collection zurück.
CalendarEvents::handle via Request → 200 OK.

Umsetzung: Neue Testklasse. CalendarService direkt instanziieren.
```

### Priorität 4: RelationshipsChartModule (chart)

```
ID: S16/S18
Ziel: RelationshipsChartModule::chart (CRAP 756, cx=27)
Datei: layer3-integration/tests/ChartModuleIntegrationTest.php (erweitern)

Begründung: Chart-Rendering mit zwei bekannten Individuen aus demo.ged.
ChartModuleIntegrationTest existiert bereits (ChartModuleIntegrationTest.php).
RelationshipsChartModule::chart liefert Response.

Umsetzung: Neue Testmethode in ChartModuleIntegrationTest.
```

### Priorität 5: BranchesListModule (getDescendantsHtml)

```
ID: —
Ziel: BranchesListModule::getDescendantsHtml (CRAP 380, cx=19)
Datei: layer3-integration/tests/ListModuleIntegrationTest.php (erweitern)

Begründung: ListModuleIntegrationTest existiert bereits. BranchesListModule::getDescendantsHtml
ist eine Layer-3-Methode (navigiert DB-gespeicherte Individual-Descendants).
Smoke-Test: Methode mit bekannter Person aus demo.ged aufrufen → String zurück.

Umsetzung: Neue Testmethode in ListModuleIntegrationTest.
```

---

## Schritt 6 — testing-bigpicture.md Diff-Vorschläge

### 6.1 — Ratchet Ist-Stand aktualisieren

Abschnitt `### Ist-Stand (Teststufe 2, Stand: 2026-04-03)`:

```diff
-### Ist-Stand (Teststufe 2, Stand: 2026-04-03)
+### Ist-Stand (Teststufe 2, Stand: 2026-04-03, aktualisiert Voll-Lauf)

-> Basis: `make test-integration-quick` (3 Testklassen: SearchIntegrationTest,
-> PrivacyVisibilityTest, TreeOperationsTest) — vor diesem Implementierungsplan.
-> Voller Lauf (`make test-integration`) ergibt höhere Werte.
+> Basis: `make test-integration` (alle 17 Testklassen, 285 Tests).

-| Anweisungsüberdeckung | 9,0% (3.969 / 44.070 Statements) |
-| Methodenüberdeckung | 7,4% (329 / 4.442 Methoden) |
-| Dateien mit 0%-Coverage | 1.191 von 1.365 |
-| Pakete mit >50%-Coverage | CustomTags (97,2%), GedcomFilters (81,5%) |
-| Pakete mit 0%-Coverage | Census, Cli, CommonMark, Exceptions, Report, Statistics, SurnameTradition |
-| Größte unabgedeckte Pakete | Module (10.531 Stmt), Http (9.032 Stmt), Report (3.137 Stmt), Census (2.552 Stmt) |
+| Anweisungsüberdeckung | 17,9% (7.882 / 44.066 Statements) |
+| Methodenüberdeckung | 17,3% (767 / 4.441 Methoden) |
+| Pakete mit >50%-Coverage | Http/Routes (99,7%), CustomTags (97,2%), GedcomFilters (81,5%) |
+| Pakete mit 0%-Coverage | Census, Cli, CommonMark, Exceptions, Http/Middleware, Module/InteractiveTree, Report, Statistics/Service, SurnameTradition |
+| Größte unabgedeckte Pakete | Http/RequestHandlers (8.083 Stmt), Module (10.376 Stmt), Report (3.137 Stmt), Census (2.552 Stmt) |
```

---

## Schritt 7 — Einschränkungen und Messartefakte

| Einschränkung | Auswirkung | Empfehlung |
|---|---|---|
| pcov Statement-Level (keine Branch-Coverage) | Methoden mit count>0 können intern ungeteStete Branches haben; CheckTree 65,8% Klassen-Coverage ≠ alle Error-Pfade getestet | Branch-Coverage-Tool (Xdebug) für kritische Methoden ergänzend verwenden |
| I18N-Kontext bei legacyNameAlgorithm | `count=3` bedeutet: 3 Testaufrufe; die 718 Branches nicht vollständig abgedeckt | Explizite Pfad-Tests für alle relevanten Familiengradtypen |
| Voll-Lauf ≠ alle Code-Pfade | 17,9% Statement-Coverage bedeutet 82,1% des Codes ist ungetestet | Ratchet-Strategie: keine absolute Zielgröße, aber gezielte CRAP-Reduktion |
| private/static Methoden | `legacyCousinName` und `legacyCousinName2` nur über `legacyNameAlgorithm` erreichbar | Cousin-Pfad-Strings als Indirektion nutzen |
| Http/RequestHandlers: 617 Methoden, 2% Coverage | 99% aller HTTP-Handler sind ungetestet — die meisten brauchen Session/Auth/Request-Kontext | Priorität auf Handler mit hohem CRAP-Score; E2E (Layer 4) deckt HTTP-Kontext besser ab |
| Module-Paket: 16,4% Coverage | Viele Module-Klassen haben komplexe Render-Logik die Template-Infrastruktur benötigt | Smoke-Tests (200 OK) als pragmatischer Einstieg |

---

## Status-Fazit

**Voll-Lauf (make test-integration, 285 Tests):**

- Statement-Coverage **17,9%** (+8,9 Pp gegenüber Quick-Lauf-Baseline 9,0%)
- Methoden-Coverage **17,3%** (+9,9 Pp gegenüber 7,4%)
- Alle 54 Teststufe-2-FM-IDs (G01–G16, G24, S01–S22, P01–P29) haben Testklassen
- Alle Quick-Lauf-Artefakte (legacyNameAlgorithm, CheckTree, IndividualFacts, ListModule) verschwunden

**Offene CRAP-Risiken Layer-3:**

| Rang | CRAP | Klasse | Methode | Priorität |
|---|---|---|---|---|
| 1 | 14.042 | StatisticsChartModule | postCustomChartAction | 2 (kein FM-Eintrag) |
| 2 | 2.652 | RelationshipService | legacyCousinName | 1 (S16-Erweiterung) |
| 3 | 1.722 | SearchGeneralPage | handle | 3 (S38, Teststufe-3-primär) |
| 4 | 1.406 | CalendarEvents | handle | 3 (S31, Teststufe-3-primär) |
| 5 | 462 | RelationshipService | legacyCousinName2 | 1 (S16-Erweiterung) |
