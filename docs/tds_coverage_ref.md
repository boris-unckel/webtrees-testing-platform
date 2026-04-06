<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Abdeckungsmatrix — Testabdeckung nach Feature-Matrix-ID

Dieses Dokument bildet die Testabdeckung pro Feature-Matrix-ID ab. Jedes Feature wird auf Upstream-Tests (SQLite), eigene Infrastruktur-Tests (MySQL-Integration / Playwright-E2E) und den Abdeckungsstatus abgebildet.

**Querverweise:**

- [Feature-Matrizen](tds_conditions_ref.md)
- [Überdeckungsstrategie](tp_ratchet_spec.md)
- [Testentwurfsverfahren](tds_methodik_spec.md)

**Aktueller Stand:** 165 abgedeckt (164 spezifikationsbasiert + 1 strukturbasiert), 4 nicht abgedeckt.

### Abdeckungsmatrix: Feature-Matrix → Testabdeckung

#### GEDCOM Import/Export (G01–G23)

| # | Feature | Upstream (SQLite) | Eigene Infra (MySQL) | Eigene Infra (Playwright) | Status |
|---|---|---|---|---|---|
| G01 | Record-Import (INDI) | `GedcomImportServiceTest` ✅ | `GedcomImportTest` ✅ | — | **Abgedeckt** |
| G02 | Record-Import (FAM) | `GedcomImportServiceTest` ✅ | `GedcomImportTest` + `RelationshipDbTest` ✅ | — | **Abgedeckt** |
| G03 | Record-Import (Nebenrecords) | `GedcomImportServiceTest` ✅ | `GedcomImportTest` ✅ | — | **Abgedeckt** |
| G04 | Place-Hierarchie | `GedcomImportServiceTest` ✅ | `GedcomImportTest` ✅ | — | **Abgedeckt** |
| G05 | Date-Parsing | `GedcomImportServiceTest` ✅ | — | — | **Abgedeckt** |
| G06 | Name-Extraktion + Soundex | `GedcomImportServiceTest` ✅ | — | — | **Abgedeckt** |
| G07 | Encoding (UTF-8) | `GedcomImportServiceTest` ✅ | `GedcomImportTest` ✅ | — | **Abgedeckt** |
| G08 | Encoding (ANSEL, CP1252) | `GedcomImportServiceTest` (CONT/CONC, empty fields) ✅ | `GedcomImportTest` ✅ (4 Tests: ANSEL/CP1252 Post-Konvertierung) | — | **Abgedeckt** |
| G09 | Inline-Media | `GedcomImportServiceTest` (media objects) ✅ | `GedcomImportTest` ✅ (3 Tests: OBJE-Split, Dateireferenzen, Verknüpfung) | — | **Abgedeckt** |
| G10 | Legacy-Formate | — | `GedcomImportTest` ✅ (4 Tests: _PLAC_DEFN, _PLAC, Koordinaten) | — | **Abgedeckt** |
| G11 | Custom-Tags | `GedcomImportServiceTest` (media files) ✅ | `GedcomImportTest` ✅ (3 Tests: Ancestry, FamilySearch, RootsMagic) | — | **Abgedeckt** |
| G12 | XREF-Eindeutigkeit | `GedcomImportServiceTest` ✅ | `GedcomImportTest` ✅ | — | **Abgedeckt** |
| G13 | Export GEDCOM | `GedcomExportServiceTest` ✅ | `TreeOperationsTest` ✅ | — | **Abgedeckt** |
| G14 | Export ZIP | — (upstream-Tests decken Sort by XREF ab, nicht ZIP-Format) | `TreeOperationsTest` ✅ (3 Tests: ZIP valide, .ged enthalten, GEDZIP) | — | **Abgedeckt** |
| G15 | Export ZIP+Media | — (upstream-Tests decken Download-Response ab, nicht ZIP+Media) | `TreeOperationsTest` ✅ (2 Tests: Mediendateien im ZIP, Referenzen) | — | **Abgedeckt** |
| G16 | Export Privacy | `GedcomExportServiceTest` ✅ (PRIV_HIDE; PRIV_NONE/USER → upstream Bug) | `TreeOperationsTest` ✅ (PRIV_NONE + PRIV_USER Regressions-Guard) | — | **Abgedeckt** |
| G17 | Export Encoding | `GedcomExportServiceTest` (CONC) ✅ | `TreeOperationsTest` ✅ (3 Tests: UTF-8, ANSEL, CP1252) | — | **Abgedeckt** |
| G18 | Export CONC/CONT | `GedcomExportServiceTest` ✅ | — | — | **Abgedeckt** |
| G19 | Export Header | `GedcomExportServiceTest` ✅ | — | — | **Abgedeckt** |
| G20 | Import→Export Roundtrip | `GedcomExportServiceTest` (INDI/FAM-Counts nach Export) ✅ | — | — | **Abgedeckt** |
| G21 | Upload-Validierung | — | — | `upload-validation.spec.ts` ✅ (4 Tests: leere/Text/NoHead/Binär-Datei) | **Abgedeckt** |
| G22 | Element-Validierung | 212 Element-Tests (substanziell, Pattern-Validierung) ✅ | — | — | **Abgedeckt** |
| G23 | GEDCOM 5.5.1 Compliance | — | `GedcomImportTest` ✅ (1 Test: Standard-Tags OCCU/RELI/NATI nicht verworfen) | — | **Abgedeckt** |
| G24 | Referenzintegrität | — | `CheckTreeIntegrationTest` ✅ (200 OK + nicht-leerer Body auf demo.ged) | — | **Abgedeckt** |
| G25 | GedcomLoad CLI-Import | — | `GedcomLoadIntegrationTest` ✅ *(spezifikationsbasiert, 8 Tests: EP1 keep_media=0, EP2 keep_media=1, EP3 BOM-Strip, EP4 kein-HEAD→Fail, EP5 kein-Trailer→Fail, EP6 Complete-View)* | — | **Abgedeckt** |
| G26 | GEDCOM-Export via CLI | — | `TreeExportCommandIntegrationTest` ✅ *(spezifikationsbasiert, 13 Tests: EP Format×4+1 invalid, EP Privacy×4+1 invalid, Tree-not-found)* | — | **Abgedeckt** |
| G27 | Mediendatei-Upload URL | — | `MediaFileServiceUploadIntegrationTest` ✅ *(CRAP-Analyse, 2 Tests)* | — | **Abgedeckt** |
| G28 | OBJE-Metadaten bearbeiten | — | `EditMediaFileIntegrationTest` ✅ *(spezifikationsbasiert, 2 Tests: Fact-not-found-Redirect, Happy Path DB-Postcondition change-Tabelle)* | — | **Abgedeckt** |
| G29 | GEDCOM-Bearbeitungsservice | — | `GedcomEditServiceIntegrationTest` ✅ *(spezifikationsbasiert, 9 Tests: editLinesToGedcom EP Normal/CONT/Leer/Sub-Level, insertMissingLevels EP Expansion/Tiefe/Tags)* | — | **Abgedeckt** |
| G30 | Mediendatei-Upload (HTTP-Formular) | — | `UploadMediaActionIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: UPLOAD_ERR_NO_FILE→302, UPLOAD_ERR_PARTIAL→FileUploadException, gefährliche Extension→FlashMessage+302)* | — | **Abgedeckt** |

#### Suche und Navigation (S01–S53)

| # | Feature | Upstream (SQLite) | Eigene Infra (MySQL) | Eigene Infra (Playwright) | Status |
|---|---|---|---|---|---|
| S01 | Allg. Suche (Personen) | `SearchServiceTest` ✅ (8 Tests) | — | — | **Abgedeckt** |
| S02 | Allg. Suche (Familien) | `SearchServiceTest` ✅ | — | — | **Abgedeckt** |
| S03 | Allg. Suche (SOUR, NOTE, REPO) | `SearchServiceTest` ✅ (Sources, Repos, Submitters) | — | — | **Abgedeckt** |
| S04 | Query-Parsing | `SearchServiceTest` ✅ (Multi-word, non-matching) | — | — | **Abgedeckt** |
| S05 | Erweiterte Suche (Felder) | — | `SearchIntegrationTest` ✅ (5 Tests: Name, Nachname, Sterbedatum, Multi-Feld, leere Felder) | — | **Abgedeckt** |
| S06 | Erweiterte Suche (Datum) | — | `SearchIntegrationTest` ✅ (3 Tests: ±0, ±5, ±20 Jahre) | — | **Abgedeckt** |
| S07 | Phonetische Suche (Russell) | `GedcomImportServiceTest` (Soundex generation) ✅ | `SearchIntegrationTest` ✅ (2 Tests: Treffer + kein Treffer) | — | **Abgedeckt** |
| S08 | Phonetische Suche (DM) | `GedcomImportServiceTest` (DM Soundex generation) ✅ | `SearchIntegrationTest` ✅ (2 Tests: Treffer + kein Treffer) | — | **Abgedeckt** |
| S09 | Quick-Search (XREF) | — | — | `navigation.spec.ts` ✅ | **Abgedeckt** |
| S10 | Paginierung | `SearchServiceTest` (Place search with limits) ✅ | `SearchIntegrationTest` ✅ (3 Tests: Limit, Offset, Offset+Limit) | — | **Abgedeckt** |
| S11 | Cross-Tree-Suche | — | `SearchIntegrationTest` ✅ (2 Tests: Ergebnisse aus beiden Bäumen, Tree-spezifischer Name) | — | **Abgedeckt** |
| S12 | Zugriffskontrolle (Suche) | `SearchServiceTest` ✅ (Guest vs Admin) | — | — | **Abgedeckt** |
| S13 | Search-and-Replace | — | — | `search-replace.spec.ts` ✅ (2×5 Themes + 1 Visitor) | **Abgedeckt** |
| S14 | Chart: Pedigree | `PedigreeChartModuleTest` ✅ (4 Styles) | — | `pedigree.spec.ts` ✅ (5 Themes × 2 Tests) | **Abgedeckt** |
| S15 | Chart: Nachkommen | `DescendancyChartModuleTest` ✅ (3 Styles) | — | — | **Abgedeckt** |
| S16 | Chart: Beziehungsfinder | `RelationshipServiceTest` ✅ (nameFromPath) | `RelationshipServiceIntegrationTest` ✅ (legacyNameAlgorithm: direkte Pfade, Onkel/Tante, Großeltern, Ehepartner) | — | **Abgedeckt** |
| S17 | Chart: Fächerchart | `FanChartModuleTest` ✅ | — | — | **Abgedeckt** |
| S18 | Chart: alle 13 Typen (Smoke) | 6 Chart-Tests ✅ + `StatisticsChartModuleTest` ✅ | `ChartModuleIntegrationTest` ✅ (5 Tests: Timeline, Lifespan, FamilyBook, Relationships, Branches) | — | **Abgedeckt** (13/13) |
| S19 | Liste: Personen (Nachnamen) | `IndividualListModuleTest` ✅ (handle, show_all, listIsEmpty) | `ListModuleIntegrationTest` ✅ (initial-Filter 'W' via handle()) | `navigation.spec.ts` ✅ | **Abgedeckt** |
| S20 | Liste: alle 10 Typen (Smoke) | 7 List-Tests ✅ (Individual, Family, Source, Repository, Note, Media, Submitter) | `ListModuleIntegrationTest` ✅ (3 Tests: Location, PlaceHierarchy, Branches) | — | **Abgedeckt** (10/10) |
| S21 | AutoComplete (Personen) | `AutoCompleteSurnameTest` ✅ | — | — | **Abgedeckt** |
| S22 | AutoComplete (Orte) | `AutoCompletePlaceTest` ✅ (match + no-match) | — | — | **Abgedeckt** |
| S23 | Navigation: Personenseite | — | — | `individual.spec.ts` ✅ | **Abgedeckt** |
| S24 | Navigation: Familienseite | — | — | `family.spec.ts` ✅ (3 Tests) | **Abgedeckt** |
| S26 | Navigation: Quellenseite | — | — | `records.spec.ts` ✅ | **Abgedeckt** |
| S27 | Navigation: Medienseite | — | — | `records.spec.ts` ✅ | **Abgedeckt** |
| S28 | Navigation: Notizseite | — | — | `records.spec.ts` ✅ (NOTE-Seite auf `muster`-Tree, 5 Themes) | **Abgedeckt** |
| S29 | Navigation: Aufbewahrungsort | — | — | `records.spec.ts` ✅ | **Abgedeckt** |
| S30 | Navigation: Einreicherseite | — | — | `records.spec.ts` ✅ | **Abgedeckt** |
| S31 | Kalenderansicht & Kalenderevents-API | — | — | `calendar.spec.ts` ✅ (Monat + Jahr; CalendarEvents AJAX implizit via Seitenaufruf) | **Abgedeckt** |
| S32 | Anmeldeseite (Login) | — | — | `login.spec.ts` ✅ | **Abgedeckt** |
| S33 | Registrierungsseite | — | — | `auth.spec.ts` ✅ | **Abgedeckt** |
| S34 | Passwort-Zurücksetzung | — | — | `auth.spec.ts` ✅ | **Abgedeckt** |
| S35 | Benutzerseite (Meine Seite) | — | — | `user-pages.spec.ts` ✅ | **Abgedeckt** |
| S36 | Kontaktseite | — | — | `user-pages.spec.ts` ✅ | **Abgedeckt** |
| S37 | Berichtsliste | — | — | `user-pages.spec.ts` ✅ | **Abgedeckt** |
| S38 | Erweiterte Suche (Seitenaufruf) | — | — | `search-forms.spec.ts` ✅ | **Abgedeckt** |
| S39 | Phonetische Suche (Seitenaufruf) | — | — | `search-forms.spec.ts` ✅ | **Abgedeckt** |
| S40 | Navigation: Homepage (Baumseite) | — | — | `homepage.spec.ts` ✅ (5 Themes × 2 Tests) | **Abgedeckt** |
| S41 | Statistikdaten-Abfragen | — | `StatisticsDataIntegrationTest` ✅ *(spezifikationsbasiert, 13 Tests: 4 alt + EP5/EP6/EP8 whereBetween, DataProvider sort×3, EP13 threshold, DataProvider sex×2)* + `StatisticsIntegrationTest` ✅ *(CRAP-Smoke)* | — | **Abgedeckt** |
| S42 | Such-HTTP-Handler | — | `SearchRequestHandlerIntegrationTest` ✅ *(spezifikationsbasiert, 6 Tests: Single-Result-Redirect EP2/EP4, Default-Fallback EP8, Multi-Result EP1/EP3)* | — | **Abgedeckt** |
| S43 | Report-Generierung HTTP | — | `ReportIntegrationTest` ✅ *(spezifikationsbasiert, 8 Tests: EP2 PDF→application/pdf, EP6 download→content-disposition, B1 unknown-redirect, 5 bisherige HTML/SAX-Tests)* | — | **Abgedeckt** |
| S44 | Report-Parser Erweitert | — | `ReportParserGenerateExtendedIntegrationTest` ✅ *(spezifikationsbasiert Pragmatisch C, 3 Tests: EP1 Vorfahren+assertNotEmpty+HTML, EP3 Nachkommen+assertNotEmpty+HTML, EP7 Individual+Fakten+Bild+assertNotEmpty+HTML)* | — | **Abgedeckt** |
| S45 | Report-Primitive PDF/HTML | — | `ReportPdfObjectsIntegrationTest` + `ReportHtmlObjectsIntegrationTest` ✅ *(spezifikationsbasiert+strukturbasiert, 23 Tests: 13 HTML (fill/border/newline Assertions TextBox+Cell) + 10 PDF (3 Image-Branch-Tests + 7 Basis))* | — | **Abgedeckt** |
| S46 | Homepage-Block-Module | — | `BlockModuleIntegrationTest` ✅ *(spezifikationsbasiert Pragmatisch C, 14 Tests: 10 alt + DataProvider infoStyles×4 EP4/EP5/EP6/EP6b)* | — | **Abgedeckt** |
| S47 | Interaktiver Stammbaum | — | `InteractiveTreeIntegrationTest` ✅ *(spezifikationsbasiert Pragmatisch C, 3 Tests: getDetails→XREF im Output, 'p'-Request→non-empty HTML, 'c'-Request→non-empty HTML)* | — | **Abgedeckt** |
| S48 | Standortdaten-Import Admin | — | `MapDataImportIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: EP1+EP5 add→DB-Postcondition lat/lng, EP6 Null-Island→gefiltert, 2 Smoke-Fehlerresilienz)* | — | **Abgedeckt** |
| S49 | Medienverwaltungsliste Admin | — | `ManageMediaDataIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: EP1 local + EP2 external + EP3 unused, JSON-Struktur-Assertions)* | — | **Abgedeckt** |
| S50 | Hilfetexte | — | `HelpTextIntegrationTest` ✅ *(spezifikationsbasiert, 13 Tests: DataProvider 12 Topics + unknown-Topic)* | — | **Abgedeckt** |
| S52 | Standortdaten-Verwaltung (CRUD) | — | `MapDataCrudIntegrationTest` ✅ *(spezifikationsbasiert, 5 Tests: MapDataSave INSERT→DB, UPDATE→DB, MapDataDelete→entfernt, MapDataExportCSV→text/csv, MapDataList GET→200)* | — | **Abgedeckt** |
| S53 | Legacy-URL-Weiterleitungen | — | — | — | **Nicht abgedeckt** |

#### Datenschutz & Zugriffskontrolle (P01–P41)

| # | Feature | Eigene Infra (MySQL) | Eigene Infra (Playwright) | Status |
|---|---|---|---|---|
| P01 | Stammbaum-Sichtbarkeit | `PrivacyVisibilityTest` ✅ | `privacy-visibility.spec.ts` ✅ | **Abgedeckt** |
| P02 | Verstorbene Personen zeigen | `PrivacyVisibilityTest` ✅ | `privacy-visibility.spec.ts` ✅ | **Abgedeckt** |
| P03 | Lebende Personen zeigen (Override) | `PrivacyVisibilityTest` ✅ | `privacy-visibility.spec.ts` ✅ | **Abgedeckt** |
| P04 | MAX_ALIVE_AGE — Altersgrenze | `IsDeadTest` + `PrivacyVisibilityTest` ✅ | — | **Abgedeckt** |
| P05 | KEEP_ALIVE_YEARS_BIRTH | `PrivacyVisibilityTest` ✅ | — | **Abgedeckt** |
| P06 | KEEP_ALIVE_YEARS_DEATH | `PrivacyVisibilityTest` ✅ | — | **Abgedeckt** |
| P07 | KEEP_ALIVE kombiniert | `PrivacyVisibilityTest` ✅ | — | **Abgedeckt** |
| P08 | isDead(): Expliziter Tod | `IsDeadTest` ✅ | — | **Abgedeckt** |
| P09 | isDead(): Datiertes Event > MAX_ALIVE_AGE | `IsDeadTest` ✅ | — | **Abgedeckt** |
| P10 | isDead(): Geburt vorhanden + jung | `IsDeadTest` ✅ | — | **Abgedeckt** |
| P11 | isDead(): Inferenz Eltern | `IsDeadTest` ✅ | — | **Abgedeckt** |
| P12 | isDead(): Inferenz Ehepartner | `IsDeadTest` ✅ | — | **Abgedeckt** |
| P13 | isDead(): Inferenz Kinder/Enkel | `IsDeadTest` ✅ | — | **Abgedeckt** |
| P14 | Namen vertraulicher Personen | `PrivacyVisibilityTest` ✅ | `privacy-visibility.spec.ts` ✅ | **Abgedeckt** |
| P15 | Vertrauliche Beziehungen | `PrivacyVisibilityTest` ✅ | — | **Abgedeckt** |
| P16 | RESN none (Record) | `ResnPrivacyTest` ✅ | `privacy-resn.spec.ts` ✅ | **Abgedeckt** |
| P17 | RESN privacy (Record) | `ResnPrivacyTest` ✅ | `privacy-resn.spec.ts` ✅ | **Abgedeckt** |
| P18 | RESN confidential (Record) | `ResnPrivacyTest` ✅ | `privacy-resn.spec.ts` ✅ | **Abgedeckt** |
| P19 | RESN auf Fakten-Ebene | `ResnPrivacyTest` ✅ | `privacy-resn.spec.ts` ✅ | **Abgedeckt** |
| P20 | default_resn (Individuum) | `ResnPrivacyTest` ✅ | — | **Abgedeckt** |
| P21 | default_resn (Faktentyp) | `ResnPrivacyTest` ✅ | — | **Abgedeckt** |
| P22 | Relationship Privacy (Pfadlänge) | `RelationshipPrivacyTest` ✅ | `privacy-relationship.spec.ts` ✅ | **Abgedeckt** |
| P23 | Relationship Privacy (kein XREF) | `RelationshipPrivacyTest` ✅ | — | **Abgedeckt** |
| P24 | Privacy in Suchergebnissen | `PrivacySearchTest` ✅ | `privacy-search.spec.ts` ✅ | **Abgedeckt** |
| P25 | Personenseite: Vertraulich-Platzhalter | — | `privacy-visibility.spec.ts` ✅ | **Abgedeckt** |
| P26 | Charts: Vertrauliche Boxen | — | `privacy-charts.spec.ts` ✅ | **Abgedeckt** |
| P27 | Bearbeiter: Datensatz bearbeiten | `AccessControlTest` ✅ | `access-control.spec.ts` ✅ | **Abgedeckt** |
| P28 | Moderator: Änderungen akzeptieren | `AccessControlTest` ✅ | `access-control.spec.ts` ✅ | **Abgedeckt** |
| P29 | RESN locked / Zugriffsverbot | `AccessControlTest` ✅ | `access-control.spec.ts` ✅ | **Abgedeckt** |
| P30 | Datensätze zusammenführen | `MergeFactsActionIntegrationTest` ✅ *(spezifikationsbasiert, 5 Tests: B1/EP2 not-found, B3/EP4 same-record, B4/EP5 tag-mismatch, B5/EP6 pending-deletion, EP1 change-DB-Assert)* + `MergeFactsIntegrationTest` ✅ *(CRAP-Smoke)* + `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke)* | — | **Abgedeckt** |
| P31 | Familienmitglieder bearbeiten | `ChangeFamilyMembersActionIntegrationTest` ✅ *(spezifikationsbasiert, 5 Tests: EP1 replace-husband, EP2 remove-wife, EP3 add-child, EP4 remove-child, EP5 no-change)* + `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke)* | — | **Abgedeckt** |
| P32 | Record-Ansicht und -Löschung | `DeleteRecordIntegrationTest` ✅ *(spezifikationsbasiert, 2 Tests: EP1 SOUR-Löschung change-Assert, EP5 Familie-Kaskade change-Assert)* + `GedcomRecordPageIntegrationTest` ✅ *(spezifikationsbasiert, 5 Tests: EP1×4 DataProvider INDI/FAM/SOUR/REPO→Redirect, EP2 _CUST→200+Link)* + `RequestHandlerBatchAIntegrationTest` ✅ *(CRAP-Smoke)* | — | **Abgedeckt** |
| P33 | Stammbaum-Privacy-Einstellungen | `TreePrivacyActionIntegrationTest` ✅ *(spezifikationsbasiert, 6 Tests: EP3/EP4 mismatched-arrays, EP5 tag+xref, EP6 tag-only, EP7 xref-only, EP8 beide-leer count-gleich, EP9 HIDE_LIVE_PEOPLE)* + `RequestHandlerBatchAIntegrationTest` ✅ *(CRAP-Smoke)* | — | **Abgedeckt** |
| P34 | Stammbaum-Umnummerierung | `RenumberTreeActionIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: B2/EP1 keine-Duplikate, B3/EP2 INDI-Rename-Postcondition, B1/EP4 Pending-Edits-Guard)* + `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke)* | — | **Abgedeckt** |
| P35 | CLI Benutzer-Verwaltung | `UserEditCommandIntegrationTest` ✅ *(spezifikationsbasiert, 16 Tests: B1–B11 Guards, DataProvider B3/B4/B5, B13–B15 Edit-Felder)* | — | **Abgedeckt** |
| P36 | CLI Einstellungs-Verwaltung | `CliSettingsBatchIntegrationTest` ✅ *(spezifikationsbasiert, 17 Tests: --list/--delete-Konflikte, Delete nonexistent, Get nonexistent, same-value Warn, Update, EP11 Tree/User/UserTree not found)* | — | **Abgedeckt** |
| P37 | HTTP Benutzer-Bearbeitung | `UserEditActionIntegrationTest` ✅ *(spezifikationsbasiert, 7 Tests: B1 not-found, B5/B6 Duplikat-Email, B7/B8 Duplikat-Username, B4 Self-Edit-Admin, B3 Passwort, EP12 Path-Reset); `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke, 1 Test)* | — | **Abgedeckt** |
| P38 | Account-Selbstverwaltung | `AccountSelfManagementIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: Edit GET 200, Update POST E-Mail, Delete admin-Guard, Delete non-admin gelöscht)* | — | **Abgedeckt** |
| P39 | Authentifizierung-Aktionen | — | `LoginActionIntegrationTest` ✅ *(spezifikationsbasiert, 1 Test: EP1 CLI-Kontext $_COOKIE=[]→doLogin wirft→handler fängt→302)* | — | **Abgedeckt** |
| P40 | Änderungsverwaltung (HTTP-Handler) | `PendingChangesIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: AcceptRecord ungültig→204, RejectRecord ungültig→204, PendingChanges GET→200)* | — | **Abgedeckt** |
| P41 | Datensatz-Zusammenführung (vollständig) | `MergeRecordsIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: Page GET valid/empty XREFs, Action POST matching INDIs→302)* | — | **Abgedeckt** |

#### Sicherheit (SEC-H01–SEC-UTL01)

| # | Feature | Shell-Assertions | Playwright-Security | Status |
|---|---------|-----------------|---------------------|--------|
| SEC-H01 | `.htaccess` Existenz | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-H02 | `.htaccess` Inhalt | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-H03 | HTTP-Zugriff `data/` blockiert | — | `data-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-H04 | HTTP-Zugriff `config.ini.php` blockiert | — | `data-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-H05 | HTTP-Zugriff `data/media/` blockiert | — | `data-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-H06 | URL-Encoding umgeht `.htaccess` nicht | — | `data-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-D01 | `data/index.php` Existenz | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-D02 | `data/index.php` Redirect-Logik | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-C01 | Config PHP-Guard | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-C02 | Config DB-Credentials | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-C03 | Config Datei-Permissions | `security-filesystem-checks.sh` ⚠ | — | **Upstream-Befund** |
| SEC-M01 | Direkter Media-Zugriff blockiert | — | `media-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-M02 | Media-Route ohne Auth | — | `media-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-M03 | Media-Route mit Auth | — | `media-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-PUB01 | `public/index.php` Existenz | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-PUB02 | `public/index.php` keine PHP-Execution | — | `public-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-PUB03 | Kein Directory Listing `/public/` | — | `public-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-PUB04 | Path-Traversal blockiert | — | `public-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-W01 | Wizard nach Setup gesperrt | — | `setup-lock.spec.ts` ✅ | **Abgedeckt** |
| SEC-WZ01 | Wizard erscheint bei Erstaufruf | — | `wizard-setup.spec.ts` ✅ | **Abgedeckt** |
| SEC-WZ02 | Wizard prüft Schreibrechte | — | `wizard-setup.spec.ts` ✅ | **Abgedeckt** |
| SEC-WZ03 | Wizard erzeugt `config.ini.php` | `security-filesystem-checks.sh` ✅ | `wizard-setup.spec.ts` ✅ | **Abgedeckt** |
| SEC-WZ04 | Wizard sperrt sich selbst | — | `wizard-setup.spec.ts` ✅ | **Abgedeckt** |
| SEC-HDR01 | `X-Content-Type-Options` | — | `security-headers.spec.ts` ✅ | **Abgedeckt** |
| SEC-HDR02 | `X-Frame-Options` | — | `security-headers.spec.ts` ✅ | **Abgedeckt** |
| SEC-HDR03 | `Referrer-Policy` | — | `security-headers.spec.ts` ✅ | **Abgedeckt** |
| SEC-HDR04 | Server-Banner | — | `security-headers.spec.ts` ⚠ | **Deployment-Empfehlung** |
| SEC-BOT01 | UA-basierte Bot-Blockierung | `BadBotBlockerIntegrationTest` ✅ *(spezifikationsbasiert, 15 Tests: BAD_ROBOTS-DataProvider×5 + WP-Pfade-DataProvider×4 + Cookie-Heuristik EP8/EP9 + 4 Basis; DNS ausgeklammert)* | — | **Abgedeckt** |
| SEC-UTL01 | Web-Assets & Utility-Endpoints | `UtilityEndpointsIntegrationTest` ✅ *(spezifikationsbasiert, 10 Tests)* | — | **Abgedeckt** |

#### Datenpflege / Erfassung (E01–E08)

| # | Feature | Eigene Infra (MySQL) | Eigene Infra (Playwright) | Status |
|---|---|---|---|---|
| E01 | Person/Familie anlegen & verknüpfen | — | `AddRelationIntegrationTest` ✅ *(spezifikationsbasiert, 6 Tests: AddChildToIndividualPage GET→200, Action POST→302, DataProvider AddParent/AddSpouseToIndi/AddChild/AddSpouseToFam→200)* | — | **Abgedeckt** |
| E02 | Fakten bearbeiten | — | `EditFactIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: EditFactPage unknown fact_id→redirect, DeleteFact unknown fact_id→204, AddNewFact GET→200)* | — | **Abgedeckt** |
| E03 | Rohdaten-Edit (Raw GEDCOM) | — | `EditRawGedcomIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: EditRawFactPage unknown fact_id→redirect, EditRawRecordPage GET→200, EditRawFactAction unknown fact_id→redirect)* | — | **Abgedeckt** |
| E04 | Nebenrecords anlegen | — | `CreateSubrecordIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: CreateNoteModal GET→200, CreateNoteAction POST→JSON-XREF, CreateSourceModal GET→200, CreateRepositoryModal GET→200)* | — | **Abgedeckt** |
| E05 | Medienobjekte anlegen & verknüpfen | — | `MediaObjectIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: CreateMediaObjectModal GET→200, LinkMediaToRecordAction POST→302, LinkMediaToIndividualModal GET→200)* | — | **Abgedeckt** |
| E06 | Sortierung (Reorder) | `ReorderIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: ReorderChildren/Names/Families GET→200, unknown FAM→404)* | — | **Abgedeckt** |
| E07 | Mediendatei-Download & Thumbnail | `MediaFileDeliveryIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: Thumbnail unknown XREF→200, Thumbnail known XREF no fact_id→200, Download unknown XREF→HttpNotFoundException)* | — | **Abgedeckt** |
| E08 | TomSelect & AutoComplete | `TomSelectIntegrationTest` ✅ *(spezifikationsbasiert, 5 Tests: TomSelectIndividual leer/XREF/Name, TomSelectSource leer, AutoCompleteFolder)* | — | **Abgedeckt** |

#### Administration (A01–A11)

| # | Feature | Eigene Infra (MySQL) | Eigene Infra (Playwright) | Status |
|---|---|---|---|---|
| A01 | Stammbaum-Management | — | `TreeManagementIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: CreateTree Duplikat→302, CreateTree Neu→DB, DeleteTree→204, ManageTrees GET→200)* | — | **Abgedeckt** |
| A02 | Stammbaum-Import (HTTP-Formular) | — | `ImportGedcomActionIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: UPLOAD_ERR_NO_FILE→302, UPLOAD_ERR_PARTIAL→Exception, leerer server_file→302, ImportGedcomPage GET→200)* | — | **Abgedeckt** |
| A03 | Stammbaum-Export (HTTP-Formular) | — | `ExportGedcomIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: Client format=gedcom→attachment, format=zip→application/zip, ExportGedcomServer→302, ExportGedcomPage GET→200)* | — | **Abgedeckt** |
| A04 | Stammbaum-Präferenzen | `TreePreferencesIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: Page GET→200, Action POST→302+preference saved, Action POST→meta_description saved)* | — | **Abgedeckt** |
| A05 | Modul-Konfiguration | — | `ModuleConfigIntegrationTest` ✅ *(spezifikationsbasiert, 7 Tests: ModulesAllPage GET→200, ModulesAllAction POST→302, DataProvider Analytics/Blocks/Charts/Menus/Reports→200)* | — | **Abgedeckt** |
| A06 | Site-Präferenzen | `SitePreferencesIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: Page GET→200, Action POST valid→302, POST saves LANGUAGE, POST invalid directory→302)* | — | **Abgedeckt** |
| A07 | Benutzerverwaltung Admin | `UserAdminIntegrationTest` ✅ *(spezifikationsbasiert, 3 Tests: UserListPage GET→200, mit filter, UsersCleanupPage GET→200)* | — | **Abgedeckt** |
| A08 | Medienverwaltung Admin | — | — | **Nicht abgedeckt** |
| A09 | Datenpflege-Werkzeuge | — | `DataMaintenanceIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: FindDuplicateRecords GET→200, DataFixPage leer→200, DataFixPage fix-place-names→200, DataFixChoose GET→200)* | — | **Abgedeckt** |
| A10 | Protokolle & Monitoring | `LogsMonitoringIntegrationTest` ✅ *(spezifikationsbasiert, 4 Tests: PendingChangesLogPage GET→200, SiteLogsDownload→CSV, Disposition attachment, PhpInformation→200)* | — | **Abgedeckt** |
| A11 | System & Upgrade | — | `SystemAdminIntegrationTest` ✅ *(spezifikationsbasiert, 5 Tests: Masquerade not-found→HttpNotFoundException, self→204, other→204+Auth, BroadcastPage GET→200, EmailPreferencesPage GET→200)* | — | **Abgedeckt** |

#### Kommunikation (K01–K02)

| # | Feature | Eigene Infra (MySQL) | Eigene Infra (Playwright) | Status |
|---|---|---|---|---|
| K01 | Kontaktformular | — | — | **Nicht abgedeckt** |
| K02 | Benutzer-Nachrichten | — | — | **Nicht abgedeckt** |

#### Querschnitts-Utilities (U01)

| # | Feature | Upstream (SQLite) | Eigene Infra (MySQL) | Status |
|---|---|---|---|---|
| U01 | Validator (root-Paket) | `ValidatorTest` ✅ (substanziell, alle Methoden) | `ValidatorIntegrationTest` ✅ *(spezifikationsbasiert, 15 Tests: float() EP1–EP5+BV+Inv+Miss, __construct UTF-8 key/value/ASCII, integer() neg-String, array() non-array-throw)* | **Abgedeckt** |

#### Zusammenfassung Abdeckung

| Status | G (G01–G30) | S (S01–S53) | P (P01–P41) | SEC (inkl. UTL01) | E (E01–E08) | A (A01–A11) | K (K01–K02) | U (U01) | Gesamt |
|---|---|---|---|---|---|---|---|---|---|
| **Abgedeckt** (spezifikationsbasiert) | 28 (G01–G26, G28–G30) | 50 (S01–S50, S52) | 41 (P01–P41) | 26 (SEC-UTL01 inkl.) | 8 (E01–E08) | 10 (A01–A07, A09–A11) | 0 | 1 (U01) | **164** |
| Davon mit Einschränkung (Upstream-Bug) | 1 (G16) | 0 | 0 | 1 (SEC-C03) | — | — | — | — | **2** |
| Deployment-Empfehlung | 0 | 0 | 0 | 1 (SEC-HDR04) | — | — | — | — | **1** |
| **Abgedeckt** (strukturbasiert, CRAP-Analyse, niedrigere Qualitätsstufe) | 1 (G27) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | **1** |
| **Nicht abgedeckt** | 0 | 1 (S53) | 0 | 0 | 0 | 1 (A08) | 2 | 0 | **4** |
| **Gesamt** | **29** | **51** | **41** | **26** | **8** | **11** | **2** | **1** | **169** |
