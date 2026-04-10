<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Batch S — Suche & Navigation

**Priorität:** 2+3 (Codepfad-Komplexität + Dependencies)
**Feature-IDs:** S01–S53

---

## Portierbare Tests

### AutoComplete-Handler (Template 1 — Mock SearchService)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 1 | `AutoCompleteCitationTest.php` | `AutoCompleteCitation` | `SearchService` | `completed` | E08, 5349-Szenario: match/no-match |
| 2 | `AutoCompletePlaceTest.php` | `AutoCompletePlace` | `SearchService`, `ModuleService` | `completed` | S22, 5349-Szenario: match/no-match |
| 3 | `AutoCompleteSurnameTest.php` | `AutoCompleteSurname` | `SearchService` | `completed` | S21, 5349-Szenario: match/no-match |
| 4 | `AutoCompleteFolder.php`* | diverse AutoComplete | `SearchService` | `completed` | E08 |

*Discovery nötig — alle AutoComplete-Handler unter `app/Http/RequestHandlers/AutoComplete*`

### Such-Handler (Template 1 — Mock SearchService)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 5 | `SearchAdvancedActionTest.php` | `SearchAdvancedAction` | `SearchService` | `pending` | S05–S06, nur Stub |
| 6 | `SearchAdvancedPageTest.php` | `SearchAdvancedPage` | `SearchService` | `completed` | S05 |
| 7 | `SearchQuickActionTest.php` | `SearchQuickAction` | `SearchService` | `pending` | S09, nur Stub |
| 8 | `SearchGeneralPageTest.php` | `SearchGeneralPage` | `SearchService` | `completed` | S05 |
| 9 | `SearchPhoneticActionTest.php` | `SearchPhoneticAction` | `SearchService` | `pending` | S07–S08, nur Stub |
| 10 | `SearchPhoneticPageTest.php` | `SearchPhoneticPage` | `SearchService` | `completed` | S07 |
| 11 | `SearchReplaceActionTest.php` | `SearchReplaceAction` | `SearchService` | `pending` | S13, nur Stub |
| 12 | `SearchReplacePageTest.php` | `SearchReplacePage` | `SearchService` | `completed` | S13 |

### Navigations-Handler (Template 3 — Registry-Mock)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 13 | `IndividualPageTest.php` | `IndividualPage` | `TreeService`, Registry | `completed` | S23 |
| 14 | `FamilyPageTest.php` | `FamilyPage` | `TreeService`, Registry | `completed` | S24 |
| 15 | `SourcePageTest.php` | `SourcePage` | `TreeService`, Registry | `completed` | S25 |
| 16 | `NotePageTest.php` | `NotePage` | `TreeService`, Registry | `completed` | S26 |
| 17 | `RepositoryPageTest.php` | `RepositoryPage` | `TreeService`, Registry | `completed` | S27 |
| 18 | `MediaPageTest.php` | `MediaPage` | `TreeService`, Registry | `completed` | S28 |
| 19 | `SubmitterPageTest.php` | `SubmitterPage` | `TreeService`, Registry | `completed` | S29 |
| 20 | `HeaderPageTest.php` | `HeaderPage` | `TreeService`, Registry | `completed` | S30 |

### Kalender-Handler (Template 1)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 21 | `CalendarPageTest.php` | `CalendarPage` | `CalendarService` | `completed` | S31 |
| 22 | `CalendarEventsTest.php` | `CalendarEvents` | `CalendarService` | `pending` | S31, nur Stub |
| 23 | `CalendarActionTest.php` | `CalendarAction` | `CalendarService` | `pending` | S31, nur Stub |

### Weitere Seiten (Template 1)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 24 | `RegisterPageTest.php` | `RegisterPage` | `CaptchaService` | `completed` | S33 |
| 25 | `RegisterActionTest.php` | `RegisterAction` | `UserService` | `pending` | S33, nur Stub |
| 26 | `HelpTextTest.php` | `HelpText` | — | `completed` | S50, T2 (kein Konstruktor) |
| 27 | `MapDataImportPageTest.php` | `MapDataImportPage` | `MapDataService` | `completed` | S48 |
| 28 | `MapDataImportActionTest.php` | `MapDataImportAction` | `MapDataService` | `completed` | S48 |

### Service-Tests (L2-fähige Methoden)

| # | Test-Datei | SUT-Klasse | Template | Status | Bemerkung |
|---|-----------|------------|----------|--------|-----------|
| 29 | `SoundexTest.php` | `Soundex` | T2 | `skipped` | S07–S08, Testdatei existiert nicht im Upstream |

### Bestehende substanzielle Tests (Verbesserung in P2)

| Test-Datei | Methoden | Verbesserungspotenzial |
|-----------|----------|----------------------|
| `LoginPageTest.php` | 2 | Reale TreeService → Mock |
| `SelectLanguageTest.php` | 2 | Ggf. `setPreference`-Verifikation wie SelectThemeTest |
| `SelectThemeTest.php` | 2 | Bereits substanziell, prüfen |
| 29 Redirect-Tests | 91 | Mustervorlage — prüfen auf fehlende Negativ-Tests |

## Module-Tests (Template 4)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 30 | `PedigreeChartModuleTest.php` | `PedigreeChartModule` | `ChartService` | `completed` | S14, 5349-Szenario: 4 Styles |
| 31 | `DescendancyChartModuleTest.php` | `DescendancyChartModule` | `ChartService` | `completed` | S15, 5349-Szenario: 3 Styles |
| 32 | `FanChartModuleTest.php` | `FanChartModule` | `ChartService` | `completed` | S17, 5349-Szenario: handle |
| 33 | `AncestorsChartModuleTest.php` | `AncestorsChartModule` | `ChartService` | `completed` | 5349-Szenario: 7 Tests |
| 34 | `CompactTreeChartModuleTest.php` | `CompactTreeChartModule` | `ChartService` | `completed` | 5349-Szenario: 2 Tests |
| 35 | `IndividualListModuleTest.php` | `IndividualListModule` | — | `completed` | S19, handle only (listIsEmpty → L3) |
| 36 | `FamilyListModuleTest.php` | `FamilyListModule` | — | `completed` | S20 |
| 37 | `MediaListModuleTest.php` | `MediaListModule` | `LinkedRecordService` | `completed` | S20 |
| 38 | `NoteListModuleTest.php` | `NoteListModule` | — | `completed` | S20 |
| 39 | `RepositoryListModuleTest.php` | `RepositoryListModule` | — | `completed` | S20 |
| 40 | `SourceListModuleTest.php` | `SourceListModule` | — | `completed` | S20 |
| 41 | `SubmitterListModuleTest.php` | `SubmitterListModule` | — | `completed` | S20 |
| 42 | `HourglassChartModuleTest.php` | `HourglassChartModule` | — | `completed` | S18 |

## Ausgeschlossen (Layer 3)

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| S01–S04 | Allg. Suche (Personen, Familien, SOUR/NOTE/REPO, Query-Parsing) | SearchService baut SQL-Queries |
| S10 | Paginierung | DB-LIMIT/OFFSET |
| S11 | Cross-Tree-Suche | Multi-DB-Query |
| S41 | Statistikdaten | DB-Aggregationen |
| S47 | Interaktiver Stammbaum | TreeView+DB |

## Discovery

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
# AutoComplete-Handler
ls tests/app/Http/RequestHandlers/AutoComplete*Test.php
# Such-Handler
ls tests/app/Http/RequestHandlers/Search*Test.php
# Chart-Module
ls tests/app/Module/*Chart*Test.php
# List-Module
ls tests/app/Module/*List*Test.php
```

## Statistik

- Portierbar: ~42
- Ausgeschlossen: 8 Feature-IDs (DB-Queries)
- Bereits substanziell: 34 Tests (29 Redirect + SelectLanguage + SelectTheme + LoginPage + 2 weitere)
