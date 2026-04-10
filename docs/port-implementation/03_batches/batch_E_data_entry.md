<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Batch E — Datenpflege / Erfassung

**Priorität:** 3 (Handler mit Dependencies)
**Feature-IDs:** E01–E08

---

## Portierbare Tests

### Handler-Tests (Template 1 — Mock Services)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 1 | `AddChildToFamilyPageTest.php` | `AddChildToFamilyPage` | `GedcomEditService` | `completed` | E01 |
| 2 | `AddChildToFamilyActionTest.php` | `AddChildToFamilyAction` | `GedcomEditService` | `pending` | E01, nur Stub |
| 3 | `AddChildToIndividualPageTest.php` | `AddChildToIndividualPage` | `GedcomEditService` | `completed` | E01 |
| 4 | `AddChildToIndividualActionTest.php` | `AddChildToIndividualAction` | `GedcomEditService` | `completed` | E01 |
| 5 | `AddParentToIndividualPageTest.php` | `AddParentToIndividualPage` | `GedcomEditService` | `completed` | E01 |
| 6 | `AddParentToIndividualActionTest.php` | `AddParentToIndividualAction` | `GedcomEditService` | `completed` | E01 |
| 7 | `AddSpouseToFamilyPageTest.php` | `AddSpouseToFamilyPage` | `GedcomEditService` | `completed` | E01 |
| 8 | `AddSpouseToFamilyActionTest.php` | `AddSpouseToFamilyAction` | `GedcomEditService` | `pending` | E01, nur Stub |
| 9 | `AddSpouseToIndividualPageTest.php` | `AddSpouseToIndividualPage` | `GedcomEditService` | `completed` | E01 |
| 10 | `AddSpouseToIndividualActionTest.php` | `AddSpouseToIndividualAction` | `GedcomEditService` | `completed` | E01 |
| 11 | `AddNewFactTest.php` | `AddNewFact` | `GedcomEditService` | `completed` | E01 |
| 12 | `LinkChildToFamilyActionTest.php` | `LinkChildToFamilyAction` | `GedcomEditService` | `completed` | E01 |
| 13 | `EditFactPageTest.php` | `EditFactPage` | `GedcomEditService` | `completed` | E02 |
| 14 | `EditFactActionTest.php` | `EditFactAction` | `GedcomEditService` | `pending` | E02, nur Stub |
| 15 | `CopyFactTest.php` | `CopyFact` | — | `completed` | E02, ggf. T2 |
| 16 | `DeleteFactTest.php` | `DeleteFact` | — | `completed` | E02, ggf. T2 |
| 17 | `EditRawRecordPageTest.php` | `EditRawRecordPage` | — | `completed` | E03 |
| 18 | `EditRawRecordActionTest.php` | `EditRawRecordAction` | — | `completed` | E03 |
| 19 | `EditRawFactPageTest.php` | `EditRawFactPage` | — | `completed` | E03 |
| 20 | `EditRawFactActionTest.php` | `EditRawFactAction` | — | `completed` | E03 |
| 21 | `CreateNoteObjectPageTest.php` | `CreateNoteObjectPage` | — | `skipped` | E04, Testdatei existiert nicht |
| 22 | `CreateNoteObjectActionTest.php` | `CreateNoteObjectAction` | — | `skipped` | E04, Testdatei existiert nicht |
| 23 | `CreateSourcePageTest.php` | `CreateSourcePage` | — | `skipped` | E04, Testdatei existiert nicht |
| 24 | `CreateSourceActionTest.php` | `CreateSourceAction` | — | `completed` | E04 |
| 25 | `CreateRepositoryPageTest.php` | `CreateRepositoryPage` | — | `skipped` | E04, Testdatei existiert nicht |
| 26 | `CreateRepositoryActionTest.php` | `CreateRepositoryAction` | — | `completed` | E04 |
| 27 | `CreateSubmitterPageTest.php` | `CreateSubmitterPage` | — | `skipped` | E04, Testdatei existiert nicht |
| 28 | `CreateSubmitterActionTest.php` | `CreateSubmitterAction` | — | `completed` | E04 |

### Handler-Tests (Template 3 — Registry-Mock)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 29 | `MediaFileDownloadTest.php` | `MediaFileDownload` | `MediaFileService`, Registry | `completed` | E07 |
| 30 | `MediaFileThumbnailTest.php` | `MediaFileThumbnail` | `MediaFileService`, Registry | `completed` | E07 |

### AutoComplete-Handler (Template 1, Überlapp mit batch_S)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 31–45 | `AutoComplete*Test.php` (15 Stubs) | diverse | `SearchService` | `completed` | E08, Discovery nötig |

### Handler-Tests (Template 1 — Sortierung)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 46 | `ReorderChildrenPageTest.php` | `ReorderChildrenPage` | — | `completed` | E06 |
| 47 | `ReorderChildrenActionTest.php` | `ReorderChildrenAction` | — | `pending` | E06, nur Stub |
| 48 | `ReorderMediaPageTest.php` | `ReorderMediaPage` | — | `completed` | E06 |
| 49 | `ReorderMediaActionTest.php` | `ReorderMediaAction` | — | `completed` | E06 |
| 50 | `ReorderFamiliesPageTest.php` | `ReorderFamiliesPage` | — | `completed` | E06 |
| 51 | `ReorderFamiliesActionTest.php` | `ReorderFamiliesAction` | — | `completed` | E06 |

## Ausgeschlossen (Layer 3)

Keine Feature-IDs explizit ausgeschlossen. Alle Handler-Tests sind via Test Doubles portierbar.
Die tatsächliche GEDCOM-Bearbeitung (DB-Writes) wird durch Mocks abgefangen.

## Discovery

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
ls tests/app/Http/RequestHandlers/Add*Test.php
ls tests/app/Http/RequestHandlers/Edit*Test.php
ls tests/app/Http/RequestHandlers/Create*Test.php
ls tests/app/Http/RequestHandlers/Reorder*Test.php
ls tests/app/Http/RequestHandlers/Delete*Test.php
ls tests/app/Http/RequestHandlers/Copy*Test.php
ls tests/app/Http/RequestHandlers/Link*Test.php
ls tests/app/Http/RequestHandlers/AutoComplete*Test.php
```

## Statistik

- Portierbar: ~51
- Ausgeschlossen: 0
- Überlapp mit batch_S: AutoComplete-Handler (ggf. dort führend)
