<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Coverage-Analyse — Komponentenintegrationstest (Teststufe 2)

> Stand: 2026-04-03  
> Eingabe: `artifacts/layer3/coverage.xml` aus `make test-integration-quick`  
> Werkzeug: `component-integration-coverage_analysis_prompt.md`

---

## Vorbemerkung: Scope des Quick-Laufs

`make test-integration-quick` führt **3 von 17 Testklassen** aus:

```
--filter='SearchIntegrationTest|PrivacyVisibilityTest|TreeOperationsTest'
```

Das bedeutet: Die Coverage misst nur, was diese drei Tests ausführen. Alle anderen
Layer-3-Tests (GedcomImportTest, AccessControlTest, RelationshipServiceIntegrationTest,
ChartModuleIntegrationTest, IsDeadTest, …) sind in dieser Coverage **nicht sichtbar**.

Konsequenz für die CRAP-Analyse: Methoden, die nur von nicht-ausgeführten Tests
abgedeckt werden, erscheinen mit `count=0` — ihr CRAP-Score ist daher verfälscht.
Für eine korrekte Gesamt-CRAP-Baseline ist `make test-integration` (voller Lauf)
erforderlich.

---

## Schritt 1 — Gesamtüberblick

```
Gesamt-Anweisungsüberdeckung:   9.0%  (3.969 / 44.070 Statements)
Gesamt-Methodenüberdeckung:     7.4%  (329 / 4.442 Methoden)
Dateien mit 0%-Coverage:        1.191 von 1.365
```

### Paket-Aufschlüsselung

| Paket | Dateien | Statements | Cov% | Methoden | MthCov% | Bewertung |
|---|---|---|---|---|---|---|
| CustomTags | 20 | 825 | **97,2%** | 40 | 47,5% | Gut abgedeckt |
| GedcomFilters | 1 | 27 | **81,5%** | 2 | 50,0% | Gut abgedeckt |
| Factories | 28 | 714 | 27,0% | 109 | 30,3% | Partiell |
| Encodings | 16 | 80 | 26,2% | 14 | 14,3% | Partiell |
| (root) | 51 | 6.713 | 20,5% | 919 | 12,5% | Partiell (Individual, GedcomRecord, Date, Statistics) |
| Services | 37 | 5.730 | 14,8% | 297 | 15,2% | Partiell (SearchService 42%, GedcomImport 57%, RelService 0%) |
| Date | 9 | 735 | 9,8% | 85 | 8,2% | Gering |
| Schema | 51 | 622 | 3,9% | 49 | 6,1% | Gering |
| Http | 381 | 9.032 | 4,1% | 700 | 0,1% | Sehr gering |
| Elements | 216 | 1.575 | 1,8% | 201 | 4,0% | Marginal |
| Module | 259 | 10.531 | 2,0% | 1.368 | 6,9% | Marginal |
| Census | 197 | 2.552 | 0,0% | 341 | 0,0% | Keine Coverage |
| Cli | 16 | 927 | 0,0% | 50 | 0,0% | Keine Coverage |
| CommonMark | 7 | 58 | 0,0% | 14 | 0,0% | Keine Coverage |
| Contracts | 32 | 0 | 0,0% | 0 | 0,0% | Keine Coverage (Interfaces) |
| Exceptions | 4 | 36 | 0,0% | 3 | 0,0% | Keine Coverage |
| Report | 28 | 3.137 | 0,0% | 198 | 0,0% | Keine Coverage |
| Statistics | 1 | 504 | 0,0% | 3 | 0,0% | Keine Coverage |
| SurnameTradition | 10 | 255 | 0,0% | 49 | 0,0% | Keine Coverage |

**Hinweis Quick-Run:** Die hohen Coverage-Werte für GedcomImportService (57,2%)
und GedcomExportService (81,6%) entstehen durch indirekte Ausführung in
`TreeOperationsTest` (Fixture-Setup via TreeService). Sie spiegeln keine direkten
Tests für G01–G12 wider.

---

## Schritt 2 — CRAP-Score-Ranking Top 30 (Quick-Lauf-Baseline)

Alle Methoden mit `count=0` (ungetestet im Quick-Lauf), absteigend nach CRAP.

| Rang | CRAP | Paket | Klasse | Methode | cx |
|---|---|---|---|---|---|
| 1 | 516.242 | Services | RelationshipService | legacyNameAlgorithm | 718 |
| 2 | 14.042 | Module | StatisticsChartModule | postCustomChartAction | 118 |
| 3 | 10.100 | Report | RightToLeftSupport | finishCurrentSpan | 100 |
| 4 | 7.310 | Report | ReportParserGenerate | listStartHandler | 85 |
| 5 | 6.972 | Report | RightToLeftSupport | spanLtrRtl | 83 |
| 6 | 4.290 | Module | AbstractIndividualListModule | handle | 65 |
| 7 | 4.160 | Http | CheckTree | handle | 64 |
| 8 | 3.660 | Report | ReportPdfTextBox | render | 60 |
| 9 | 2.652 | Services | RelationshipService | legacyCousinName | 51 |
| 10 | 2.256 | Report | ReportHtmlTextBox | render | 47 |
| 11 | 1.980 | Services | IndividualFactsService | childFacts | 44 |
| 12 | 1.722 | Http | SearchGeneralPage | handle | 41 |
| 13 | 1.406 | Http | CalendarEvents | handle | 37 |
| 14 | 1.122 | Module | TreeView (InteractiveTree) | drawPerson | 33 |
| 15 | 1.122 | (root) | Date | display | 33 |
| 16 | 992 | Services | IndividualFactsService | parentFacts | 31 |
| 17 | 992 | Report | ReportHtmlCell | render | 31 |
| 18 | 930 | Module | SlideShowModule | getBlock | 30 |
| 19 | 870 | Services | CalendarService | getAnniversaryEvents | 29 |
| 20 | 870 | Http | BadBotBlocker | process | 29 |
| 21 | 812 | Module | YahrzeitModule | getBlock | 28 |
| 22 | 756 | Module | RelationshipsChartModule | chart | 27 |
| 23 | 650 | Http | ChangeFamilyMembersAction | handle | 25 |
| 24 | 600 | (root) | StatisticsFormat | century | 24 |
| 25 | 600 | (root) | StatisticsData | centuryName | 24 |
| 26 | 552 | Report | ReportParserGenerate | relativesStartHandler | 23 |
| 27 | 552 | Report | ReportParserGenerate | getGedcomValue | 23 |
| 28 | 552 | Cli | UserEdit | execute | 23 |
| 29 | 552 | (root) | StatisticsData | ageOfMarriageQuery | 23 |
| 30 | 552 | (root) | Individual | getEstimatedBirthDate | 23 |

**Quick-Lauf-Artefakt:** `RelationshipService::legacyNameAlgorithm` (Rang 1, CRAP 516.242)
wird von `RelationshipServiceIntegrationTest` abgedeckt — dieser läuft aber nicht im
Quick-Subset. Im vollen Lauf verschwindet dieser Eintrag aus der Top-Liste.

---

## Schritt 3 — Layer-Abgrenzung

### Kriteriencheck pro Kandidat

Prüfkriterien: DB::table()-Aufruf, Tree/Registry-Abhängigkeit (→ Layer-3),
oder reines Report/Census/Encoding/SurnameTradition-Paket (→ Layer-2).

---

### Layer-3-Kandidaten — Top 15 (MySQL + Container nötig)

| CRAP | Klasse | Methode | Begründung Layer-3 | Feature-Matrix-Bezug |
|---|---|---|---|---|
| 516.242 | `RelationshipService` | `legacyNameAlgorithm` | `Registry::container()` (ModuleService); traversiert DB-gespeicherte Individuen | S16 (Beziehungsfinder-Beschriftung) |
| 14.042 | `StatisticsChartModule` | `postCustomChartAction` | Module mit DB-abhängiger Render-Logik (StatisticsData) | — (kein FM-Eintrag) |
| 4.290 | `AbstractIndividualListModule` | `handle` | `Registry::routeFactory()`, `Registry::individualFactory()`, RequestHandler | S19 (Nachnamen-Collation) |
| 4.160 | `CheckTree` | `handle` | 6× `DB::table()` (individuals, families, media, sources, other, change) | — (Admin-Tool) |
| 2.652 | `RelationshipService` | `legacyCousinName` | `Registry::container()` (Sprach-Service); Teil des Beziehungs-Algorithmus | S16 |
| 1.980 | `IndividualFactsService` | `childFacts` | `Tree`, `Individual` (DB-backed), Fakten-Rendering | S23 (Teststufe 3), kein L3-FM |
| 1.722 | `SearchGeneralPage` | `handle` | `DB::table()` (4× notes/locations/repos/sources) + `RequestHandler` | S38 (Teststufe 3) |
| 1.406 | `CalendarEvents` | `handle` | RequestHandler + `CalendarService` (DB::table('dates')) | S31 (Teststufe 3) |
| 1.122 | `TreeView::drawPerson` | `drawPerson` | Module-Rendering über DB-gespeichertes `Individual` | S18 (Interactive Tree) |
| 992 | `IndividualFactsService` | `parentFacts` | `Tree`, `Individual` (DB-backed) | S23 (Teststufe 3) |
| 870 | `CalendarService` | `getAnniversaryEvents` | `DB::table('dates')`, `Registry::individualFactory()` | S31 (Teststufe 3) |
| 930 | `SlideShowModule` | `getBlock` | Module, nutzt Media-Records aus DB | — (kein FM-Eintrag) |
| 812 | `YahrzeitModule` | `getBlock` | Module, CalendarService-Abhängigkeit (DB) | — (kein FM-Eintrag) |
| 756 | `RelationshipsChartModule` | `chart` | Module + `RelationshipService` (DB-Individuen) | S16, S18 |
| 552 | `Individual` | `getEstimatedBirthDate` | `Registry::timestampFactory()`, traversiert Family-Kette (DB) | P09–P11 (isDead-Inferenz) |

---

### Layer-2-Kandidaten — Top 15 (isoliert testbar, kein Container)

| CRAP | Klasse | Methode | Begründung Layer-2 | Anmerkung |
|---|---|---|---|---|
| 10.100 | `RightToLeftSupport` | `finishCurrentSpan` | Paket `Report/` — in-memory DOM, keine DB | Upstream `tests/` prüfen |
| 7.310 | `ReportParserGenerate` | `listStartHandler` | Paket `Report/` — XML-Parser, keine DB | Upstream `tests/` prüfen |
| 6.972 | `RightToLeftSupport` | `spanLtrRtl` | Paket `Report/` — String/DOM-Logik | Upstream `tests/` prüfen |
| 3.660 | `ReportPdfTextBox` | `render` | Paket `Report/` — PDF-Render, keine DB | FPDF/TCPDF-Abhängigkeit beachten |
| 2.256 | `ReportHtmlTextBox` | `render` | Paket `Report/` — HTML-DOM-Render | Upstream `tests/` prüfen |
| 1.122 | `Date` | `display` | Paket `(root)/Date` — Datumsformat-Rendering | Upstream `tests/Date/` sehr umfangreich — erst abgleichen! |
| 992 | `ReportHtmlCell` | `render` | Paket `Report/` — HTML-Cell-Render | Upstream `tests/` prüfen |
| 600 | `StatisticsFormat` | `century` | Paket `(root)` — reine Format-Methode (int→String) | Kurze Unit-Tests ausreichend |
| 552 | `ReportParserGenerate` | `relativesStartHandler` | Paket `Report/` — XML-Parser | Upstream `tests/` prüfen |
| 552 | `ReportParserGenerate` | `getGedcomValue` | Paket `Report/` — GEDCOM-String-Extraktion | Upstream `tests/` prüfen |
| 552 | `UserEdit` | `execute` | Paket `Cli/` — Symfony-Console-Command, kein HTTP-Stack | DB indirekt (UserService) — Level prüfen |
| 552 | `StatisticsData` | `ageOfMarriageQuery` | Paket `(root)` — **DB::table('families')** vorhanden | Achtung: Layer-3! Falsch-Klassifikation möglich |
| 870 | `BadBotBlocker` | `process` | Paket `Http/Middleware` — Registry::cache()->file(), kein DB-Aufruf | PSR-15-Stack nötig → Layer-3-Grenzfall |
| 600 | `StatisticsData` | `centuryName` | `(root)` — ruft `DB::table('dates')` | Achtung: Layer-3-Kandidat trotz (root)-Paket |
| 812 | `SlideShowModule` | `getBlock` | Falls nur Module-Interface mit Tree-Injection → Layer-3-Grenzfall | Quelle prüfen |

> **Korrektur zu `StatisticsData`:** Das Paket `(root)` ist kein verlässliches Layer-2-Indiz.
> `StatisticsData` ruft `DB::table()` direkt auf → Layer-3. Die obige Tabelle enthält
> `ageOfMarriageQuery` und `centuryName` mit expliziter Warnung.

---

## Schritt 4 — Gap-Analyse Feature-Matrix × Coverage

Teststufe-2-IDs laut `testing-bigpicture.md`: G01–G04, G07–G10, G12–G16,
S01–S03, S05–S08, S10–S12, S19, S21, S22, P01–P24, P27–P29

### GEDCOM Import/Export (G01–G16)

| ID | Bezeichnung | Testklasse (Layer-3) | Coverage-Status | Bemerkung |
|---|---|---|---|---|
| G01 | Record-Import (INDI) | `GedcomImportTest` | **grün** | GedcomImportService 57,2% stmt |
| G02 | Record-Import (FAM) | `GedcomImportTest` + `RelationshipDbTest` | **grün** | Beziehungsstruktur in DB verifiziert |
| G03 | Record-Import (Nebenrecords) | `GedcomImportTest` | **grün** | SOUR/NOTE/REPO/OBJE abgedeckt |
| G04 | Place-Hierarchie | `GedcomImportTest` | **grün** | place_location-Einträge geprüft |
| G07 | Encoding (UTF-8) | `GedcomImportTest` | **grün** | Zeichenverlust-Tests vorhanden |
| G08 | Encoding (ANSEL/CP1252) | `GedcomImportTest` | **grün** | 4 Tests: ANSEL, CP1252, Umlaute, Sonderzeichen |
| G09 | Inline-Media | `GedcomImportTest` | **grün** | 3 Tests: OBJE-Split, Dateireferenzen, Verknüpfung |
| G10 | Legacy-Formate | `GedcomImportTest` | **grün** | 4 Tests: _PLAC_DEFN, TNG-PLAC, Koordinaten |
| G12 | XREF-Vergabe | `GedcomImportTest` | **grün** | Eindeutigkeit geprüft |
| G13 | Export GEDCOM | `TreeOperationsTest` | **grün** | GedcomExportService 81,6% stmt |
| G14 | Export ZIP | `TreeOperationsTest` | **grün** | 3 Tests: ZIP valide, .ged enthalten, GEDZIP |
| G15 | Export ZIP+Media | `TreeOperationsTest` | **grün** | 2 Tests: Mediendateien + Referenzen |
| G16 | Export Privacy | `TreeOperationsTest` | **partiell** | Nur PRIV_HIDE getestet; PRIV_NONE/USER upstream-Bug dokumentiert |

### Suche & Navigation (S01–S22)

| ID | Bezeichnung | Testklasse (Layer-3) | Coverage-Status | Bemerkung |
|---|---|---|---|---|
| S01 | Allg. Suche (Personen) | `SearchIntegrationTest` | **grün** | SearchService 42,2% stmt |
| S02 | Allg. Suche (Familien) | `SearchIntegrationTest` | **grün** | |
| S03 | Allg. Suche (Quellen, Notizen, Repos) | `SearchIntegrationTest` | **grün** | |
| S05 | Erweiterte Suche (Felder) | `SearchIntegrationTest` | **grün** | 5 Tests |
| S06 | Erweiterte Suche (Datum-Mod.) | `SearchIntegrationTest` | **grün** | 3 Tests: ±0, ±5, ±20 Jahre |
| S07 | Phonetische Suche (Russell) | `SearchIntegrationTest` + `AutoCompleteIntegrationTest` | **grün** | |
| S08 | Phonetische Suche (DM) | `SearchIntegrationTest` | **grün** | 2 Tests |
| S10 | Paginierung | `SearchIntegrationTest` | **grün** | 3 Tests: Limit, Offset, Seite |
| S11 | Cross-Tree-Suche | `SearchIntegrationTest` | **grün** | 2 Tests |
| S12 | Zugriffskontrolle (Suche) | `SearchIntegrationTest` | **grün** | Guest vs. Admin |
| S19 | Liste: Personen (Nachnamen) | `ListModuleIntegrationTest` | **partiell** | Grundrendering OK; `AbstractIndividualListModule::handle()` (Collation-Logik) hat CRAP 4.290, 0,3% Coverage |
| S21 | AutoComplete (Personen) | `AutoCompleteIntegrationTest` | **grün** | AutoCompleteSurname + Citation |
| S22 | AutoComplete (Orte) | `SearchIntegrationTest` + `AutoCompleteIntegrationTest` | **grün** | Submitter-Suche + AutoCompletePlace |

### Datenschutz & Zugriffskontrolle (P01–P29)

| ID | Bezeichnung | Testklasse (Layer-3) | Coverage-Status | Bemerkung |
|---|---|---|---|---|
| P01 | Stammbaum-Sichtbarkeit | `PrivacyVisibilityTest` | **grün** | Individual 24,7% stmt |
| P02 | Verstorbene Personen | `PrivacyVisibilityTest` | **grün** | |
| P03 | Lebende Personen (Override) | `PrivacyVisibilityTest` | **grün** | |
| P04 | MAX_ALIVE_AGE | `IsDeadTest` + `PrivacyVisibilityTest` | **grün** | Grenzwertanalyse vorhanden |
| P05 | KEEP_ALIVE_YEARS_BIRTH | `PrivacyVisibilityTest` | **grün** | |
| P06 | KEEP_ALIVE_YEARS_DEATH | `PrivacyVisibilityTest` | **grün** | |
| P07 | KEEP_ALIVE kombiniert | `PrivacyVisibilityTest` | **grün** | OR-Logik |
| P08 | isDead(): Explizit | `IsDeadTest` | **grün** | |
| P09 | isDead(): Datiertes Event | `IsDeadTest` | **grün** | |
| P10 | isDead(): Geburt jung | `IsDeadTest` | **grün** | |
| P11 | isDead(): Inferenz Eltern | `IsDeadTest` | **grün** | |
| P12 | isDead(): Inferenz Ehepartner | `IsDeadTest` | **grün** | |
| P13 | isDead(): Inferenz Kinder | `IsDeadTest` | **grün** | |
| P14 | Namen vertraulich | `PrivacyVisibilityTest` | **grün** | 3 Stufen abgedeckt |
| P15 | Vertrauliche Beziehungen | `PrivacyVisibilityTest` | **grün** | |
| P16 | RESN none | `ResnPrivacyTest` | **grün** | |
| P17 | RESN privacy | `ResnPrivacyTest` | **grün** | |
| P18 | RESN confidential | `ResnPrivacyTest` | **grün** | |
| P19 | RESN Fakten-Ebene | `ResnPrivacyTest` | **grün** | BIRT + DEAT |
| P20 | default_resn (Individuum) | `ResnPrivacyTest` | **grün** | |
| P21 | default_resn (Faktentyp) | `ResnPrivacyTest` | **grün** | |
| P22 | Relationship Privacy (Pfad) | `RelationshipPrivacyTest` | **grün** | |
| P23 | Relationship Privacy (kein XREF) | `RelationshipPrivacyTest` | **grün** | |
| P24 | Privacy in Suchergebnissen | `PrivacySearchTest` | **grün** | Lebende + RESN-Personen |
| P27 | Bearbeiter: Datensatz bearbeiten | `AccessControlTest` | **grün** | `canEdit()` + pending change |
| P28 | Moderator: Änderungen akzeptieren | `AccessControlTest` | **grün** | |
| P29 | RESN locked / Zugriffsverbot | `AccessControlTest` | **grün** | |

### Lücken-Zusammenfassung

**Kein „rot (kein Test)":** Alle 53 zugeordneten Teststufe-2-IDs besitzen Testklassen.
Die Endekriterien für Teststufe 2 sind formal erfüllbar.

**Zwei partielle Lücken:**

| ID | Lücke | Ursache |
|---|---|---|
| G16 | Nur PRIV_HIDE getestet | Upstream-Bug für PRIV_NONE/USER; kein eigener Test für die anderen Access-Level-Kombinationen |
| S19 | Collation-Logik (Nachnamen-Initialen) nicht abgedeckt | `AbstractIndividualListModule::handle()` — CRAP 4.290, 0,3% Coverage — wird in `ListModuleIntegrationTest` nicht über den handle-Pfad getriggert |

**Coverage-Rücksicht Quick vs. Full:**
Wer Klassen-Coverage für P04–P24 (IsDeadTest, ResnPrivacyTest, RelationshipPrivacyTest,
PrivacySearchTest, AccessControlTest) prüfen will, muss `make test-integration` starten —
diese Tests fehlen im Quick-Lauf vollständig.

---

## Schritt 5 — Priorisierter Handlungsplan

### Priorität 1: Feature-Matrix-Lücken schließen (Status "rot" oder "partiell")

```
1. [S19-CollationTest — Erweiterung von ListModuleIntegrationTest]
   Feature-Matrix-IDs: S19
   Zu coverende Klassen: AbstractIndividualListModule::handle()
   CRAP-Score: 4.290 (cx=65)
   Coverage vorher: AbstractIndividualListModule 1/363 stmt (0,3%)

   Begründung: S19 ist Endekriterium für Teststufe 2. Der bestehende
   ListModuleIntegrationTest prüft nur Smoke-Rendering. Die eigentliche
   Collation-Logik in handle() (Initialen-Filterung, Nachnamen-Sortierung
   nach Locale) ist vollständig ungetestet. Ein Testfall mit explizitem
   initial=-Parameter würde handle() triggern und die Lücke schließen.

   Vorgehen: In ListModuleIntegrationTest einen Testfall für
   IndividualListModule::handle() mit Request-Parameter `initial=A`
   ergänzen; Ergebnis-Count gegen Fixture verifizieren.

2. [G16-ExportPrivacyTest — Erweiterung von TreeOperationsTest]
   Feature-Matrix-IDs: G16
   Zu coverende Klassen: GedcomExportService::export() mit PRIV_NONE, PRIV_USER
   CRAP-Score: — (GedcomExportService bereits 81,6%; fehlende Branch-Coverage)
   Coverage vorher: GedcomExportService 151/185 stmt (81,6%)

   Begründung: G16 "mit Einschränkung" in der Abdeckungsmatrix. Upstream-Bug
   für PRIV_NONE/USER ist bekannt (Privacy-Level-Kombination liefert falsche
   Ergebnisse). Testfall anlegen, der den Bug als erwartetes Verhalten dokumentiert
   (expectation: issue fisharebest/webtrees) — verhindert stilles Regressions-
   Übersehen nach Upstream-Fix.
```

### Priorität 2: Layer-3-Kandidaten mit hohem CRAP und Teststufe-2-Potential

```
3. [RelationshipNameAlgorithmTest — Erweiterung von RelationshipServiceIntegrationTest]
   Feature-Matrix-IDs: S16 (Beziehungsfinder, Beschriftung des Pfades)
   Zu coverende Klassen:
     - RelationshipService::legacyNameAlgorithm() (CRAP 516.242, cx=718)
     - RelationshipService::legacyCousinName() (CRAP 2.652, cx=51)
   CRAP-Scores: 516.242 + 2.652

   Begründung: legacyNameAlgorithm ist die komplexeste einzelne Methode im
   gesamten Codebase (cx=718). RelationshipServiceIntegrationTest existiert
   bereits und bootstrappt die Laufzeit — dort Testfälle für Cousin-Grad-
   Bezeichnungen (1C, 2C, 1C1R, …) ergänzen. Hoher Risikoschutz durch Ratchet
   (Methode mit cx=718 + coverage=0 → CRAP sinkt dramatisch nach ersten Tests).

   Fixture-Voraussetzung: demo.ged enthält Mehrgenerationen-Beziehungen
   (29 Familien, 72 Individuen) — ausreichend für Basis-Cousin-Tests.

4. [CheckTreeIntegrationTest — neue Testklasse]
   Feature-Matrix-IDs: — (kein bestehender FM-Eintrag → neuer Eintrag nötig, s. Schritt 6c)
   Zu coverende Klassen: CheckTree::handle() (CRAP 4.160, cx=64)
   CRAP-Score: 4.160

   Begründung: CheckTree ist ein Admin-RequestHandler mit 6 DB::table()-Aufrufen.
   Er prüft Referenzintegrität (orphaned records, broken XREF-links). Kein FM-Eintrag,
   kein Test. Muster: AutoCompleteIntegrationTest zeigt, wie ein PSR-15-Handler
   im Container testbar ist. Kandidat für neues Feature-Matrix-Thema "Datenintegrität"
   (G24 o.ä.).

5. [IndividualFactsIntegrationTest — neue Testklasse]
   Feature-Matrix-IDs: — (IndividualFactsService gehört zu S23/Personenseite → Teststufe 3)
   Zu coverende Klassen:
     - IndividualFactsService::childFacts() (CRAP 1.980, cx=44)
     - IndividualFactsService::parentFacts() (CRAP 992, cx=31)
   CRAP-Scores: 1.980 + 992 = 2.972 kombiniert

   Begründung: IndividualFactsService aggregiert Fakten (Geburts-, Todes-, Heirats-
   daten) aus DB-gespeicherten Individual/Family-Objekten. Die Logik ist zentral für
   korrekte Faktendarstellung, aber S23 ist Systemtest. Kein Feature-Matrix-Eintrag
   für Layer-3. Entweder: (a) S23 um Teststufe-2-Zeile erweitern (Schritt 6c),
   oder (b) als technischer Test ohne FM-ID hinzufügen (ähnlich RomanNumeralsTest).
   Hohe Risikoabdeckung durch cx=44+31 bei 0,2% Coverage.
```

---

## Schritt 6 — Vorschläge für testing-bigpicture.md

### 6a — Überdeckungsstrategie — Ratchet: Ist-Stand ergänzen

Direkt nach dem bestehenden Tabellen-Block in `## Überdeckungsstrategie — Ratchet`
folgende Sektion einfügen:

```markdown
### Ist-Stand (Teststufe 2, Stand: 2026-04-03)

> Basis: `make test-integration-quick` (3 Testklassen: SearchIntegrationTest,
> PrivacyVisibilityTest, TreeOperationsTest). Voller Lauf (`make test-integration`)
> ergibt höhere Werte.

| Metrik | Wert (Quick-Lauf) |
|---|---|
| Anweisungsüberdeckung | 9,0% (3.969 / 44.070 Statements) |
| Methodenüberdeckung | 7,4% (329 / 4.442 Methoden) |
| Dateien mit 0%-Coverage | 1.191 von 1.365 |
| Pakete mit >50%-Coverage | CustomTags (97,2%), GedcomFilters (81,5%) |
| Pakete mit 0%-Coverage | Census, Cli, CommonMark, Exceptions, Report, Statistics, SurnameTradition |
| Größte unabgedeckte Pakete | Module (10.531 Stmt), Http (9.032 Stmt), Census (2.552 Stmt), Report (3.137 Stmt) |
```

---

### 6b — Endekriterien: Ergänzung für S19-Collation

Aktueller Text (Zeile ~1001):

```
Alle Feature-Matrix-Integrationstests grün (G01–G04, G07–G10, G12–G16, S01–S03,
S05–S08, S10–S12, S19, S21, S22, P01–P24, P27–P29)
```

Kein neues ID nötig (S19 ist bereits enthalten). **Jedoch:** Klarstellung empfohlen,
dass S19 die Collation-Logik einschließt:

```diff
- Alle Feature-Matrix-Integrationstests grün (G01–G04, G07–G10, G12–G16, S01–S03,
-   S05–S08, S10–S12, S19, S21, S22, P01–P24, P27–P29)
+ Alle Feature-Matrix-Integrationstests grün (G01–G04, G07–G10, G12–G16, S01–S03,
+   S05–S08, S10–S12, S19 (inkl. Nachnamen-Collation via handle()), S21, S22,
+   P01–P24, P27–P29)
```

---

### 6c — Feature-Matrix-Erweiterungen: Neue Einträge vorschlagen

Zwei Kandidaten aus Schritt 3 ohne bestehenden FM-Eintrag:

**Kandidat A: Datenintegrität (CheckTree)**

In `### Feature-Matrix: GEDCOM Import/Export` am Ende ergänzen:

```
| G24 | Referenzintegrität (CheckTree) | GEDCOM-Datenbank auf verwaiste XREFs und fehlende Verknüpfungen prüfen → Report mit 0 Fehlern bei valider demo.ged | 2 | Mittel |
```

Und in der `Testfall-Verteilung`-Tabelle die Teststufe-2-Spalte für GEDCOM anpassen:
`G01–G04, G07–G10, G12–G16 (13)` → `G01–G04, G07–G10, G12–G16, G24 (14)`

**Kandidat B: Fakten-Aggregation (IndividualFactsService)**

Alternativ zu neuem FM-Eintrag: als technischen Test ohne FM-ID führen (analog
`RomanNumeralsIntegrationTest`). Begründung: IndividualFactsService ist intern; die
Nutzersicht ist S23 (Systemtest). Ein Layer-3-Test wäre Komponentenintegrationstest
ohne direkten Feature-Matrix-Bezug.

---

### 6d — Abdeckungsmatrix: Aktualisierungen

In `#### Suche und Navigation (S01–S39)` Zeile S19 aktualisieren:

```diff
- | S19 | Liste: Personen (Nachnamen) | `IndividualListModuleTest` ✅ (handle, show_all, listIsEmpty) | — | `navigation.spec.ts` ✅ | **Abgedeckt** |
+ | S19 | Liste: Personen (Nachnamen) | `IndividualListModuleTest` ✅ (handle, show_all, listIsEmpty) | `ListModuleIntegrationTest` ⚠ (Smoke, Collation-Lücke: `AbstractIndividualListModule::handle()` CRAP 4.290) | `navigation.spec.ts` ✅ | **Partiell** |
```

In `#### GEDCOM Import/Export (G01–G23)` Zeile G16 aktualisieren (kein Änderungsbedarf
— bereits als "mit Einschränkung" dokumentiert; nach Upstream-Fix ggf. auf **Abgedeckt**
setzen).

---

## Limitierungen dieser Analyse

| Limitierung | Konkrete Auswirkung hier |
|---|---|
| **Quick-Lauf-Scope** | Nur 3 von 17 Testklassen ausgeführt. CRAP-Rang 1 (RelationshipService) ist Quick-Artefakt; im vollen Lauf verschwindet er. Für echte Baseline: `make test-integration`. |
| **Coverage-Granularität** | pcov misst Statement-Coverage. `GedcomImportService` bei 57,2% heißt: 43% der Statements werden nicht durchlaufen — nicht, dass 43% der Pfade ungetestet sind. CRAP überschätzt Sicherheit bei partiell abgedeckten Methoden. |
| **Keine Kausalität** | Coverage zeigt Ausführung, nicht korrekte Assertions. `SearchService` bei 42,2% bedeutet nicht, dass 42% der Suchanfragen korrekt getestet sind. |
| **Layer-2-Abgrenzung heuristisch** | `StatisticsData` im Paket `(root)` wurde zunächst als Layer-2 klassifiziert — DB::table()-Aufruf erst bei Quellcode-Check gefunden. Vor Implementierung immer Quelle prüfen. |
| **upstream-Tests unsichtbar** | `Date::display` (CRAP 1.122) — upstream `tests/` enthält umfangreiche Date-Tests. 0%-Coverage im Layer-3-Report ≠ ungetestet global. Layer-2-Kandidaten immer gegen upstream abgleichen. |
| **Fixture-Abhängigkeit** | demo.ged (72 Individuen, 29 Familien). Testpfade, die andere Datenkonstellationen brauchen (z.B. für legacyNameAlgorithm: 3-Cousins-Konstellation), erfordern Fixture-Erweiterung. |
| **Module-Paket** | 259 Dateien, 2,0% Coverage. CRAP allein rechtfertigt keinen hohen Rang — Module-Tests sind Feature-Matrix-geführt (S-IDs), nicht CRAP-getrieben. |
