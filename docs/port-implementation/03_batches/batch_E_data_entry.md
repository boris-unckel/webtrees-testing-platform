<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Batch E — Datenpflege / Erfassung

**Priorität:** 3 (Handler mit Dependencies)
**Feature-IDs:** E01–E08

---

## Portierbare Tests

### Handler-Tests (Template 1 — Mock Services)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 1 | `AddChildToFamilyPageTest.php` | `AddChildToFamilyPage` | `GedcomEditService` | `pending` | E01 |
| 2 | `AddChildToFamilyActionTest.php` | `AddChildToFamilyAction` | `GedcomEditService` | `pending` | E01 |
| 3 | `AddChildToIndividualPageTest.php` | `AddChildToIndividualPage` | `GedcomEditService` | `pending` | E01 |
| 4 | `AddChildToIndividualActionTest.php` | `AddChildToIndividualAction` | `GedcomEditService` | `pending` | E01 |
| 5 | `AddParentToIndividualPageTest.php` | `AddParentToIndividualPage` | `GedcomEditService` | `pending` | E01 |
| 6 | `AddParentToIndividualActionTest.php` | `AddParentToIndividualAction` | `GedcomEditService` | `pending` | E01 |
| 7 | `AddSpouseToFamilyPageTest.php` | `AddSpouseToFamilyPage` | `GedcomEditService` | `pending` | E01 |
| 8 | `AddSpouseToFamilyActionTest.php` | `AddSpouseToFamilyAction` | `GedcomEditService` | `pending` | E01 |
| 9 | `AddSpouseToIndividualPageTest.php` | `AddSpouseToIndividualPage` | `GedcomEditService` | `pending` | E01 |
| 10 | `AddSpouseToIndividualActionTest.php` | `AddSpouseToIndividualAction` | `GedcomEditService` | `pending` | E01 |
| 11 | `AddNewFactTest.php` | `AddNewFact` | `GedcomEditService` | `pending` | E01 |
| 12 | `LinkChildToFamilyActionTest.php` | `LinkChildToFamilyAction` | `GedcomEditService` | `pending` | E01 |
| 13 | `EditFactPageTest.php` | `EditFactPage` | `GedcomEditService` | `pending` | E02 |
| 14 | `EditFactActionTest.php` | `EditFactAction` | `GedcomEditService` | `pending` | E02 |
| 15 | `CopyFactTest.php` | `CopyFact` | — | `pending` | E02, ggf. T2 |
| 16 | `DeleteFactTest.php` | `DeleteFact` | — | `pending` | E02, ggf. T2 |
| 17 | `EditRawRecordPageTest.php` | `EditRawRecordPage` | — | `pending` | E03 |
| 18 | `EditRawRecordActionTest.php` | `EditRawRecordAction` | — | `pending` | E03 |
| 19 | `EditRawFactPageTest.php` | `EditRawFactPage` | — | `pending` | E03 |
| 20 | `EditRawFactActionTest.php` | `EditRawFactAction` | — | `pending` | E03 |
| 21 | `CreateNoteObjectPageTest.php` | `CreateNoteObjectPage` | — | `pending` | E04 |
| 22 | `CreateNoteObjectActionTest.php` | `CreateNoteObjectAction` | — | `pending` | E04 |
| 23 | `CreateSourcePageTest.php` | `CreateSourcePage` | — | `pending` | E04 |
| 24 | `CreateSourceActionTest.php` | `CreateSourceAction` | — | `pending` | E04 |
| 25 | `CreateRepositoryPageTest.php` | `CreateRepositoryPage` | — | `pending` | E04 |
| 26 | `CreateRepositoryActionTest.php` | `CreateRepositoryAction` | — | `pending` | E04 |
| 27 | `CreateSubmitterPageTest.php` | `CreateSubmitterPage` | — | `pending` | E04 |
| 28 | `CreateSubmitterActionTest.php` | `CreateSubmitterAction` | — | `pending` | E04 |

### Handler-Tests (Template 3 — Registry-Mock)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 29 | `MediaFileDownloadTest.php` | `MediaFileDownload` | `MediaFileService`, Registry | `pending` | E07 |
| 30 | `MediaFileThumbnailTest.php` | `MediaFileThumbnail` | `MediaFileService`, Registry | `pending` | E07 |

### AutoComplete-Handler (Template 1, Überlapp mit batch_S)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 31–45 | `AutoComplete*Test.php` (15 Stubs) | diverse | `SearchService` | `pending` | E08, Discovery nötig |

### Handler-Tests (Template 1 — Sortierung)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 46 | `ReorderChildrenPageTest.php` | `ReorderChildrenPage` | — | `pending` | E06 |
| 47 | `ReorderChildrenActionTest.php` | `ReorderChildrenAction` | — | `pending` | E06 |
| 48 | `ReorderMediaPageTest.php` | `ReorderMediaPage` | — | `pending` | E06 |
| 49 | `ReorderMediaActionTest.php` | `ReorderMediaAction` | — | `pending` | E06 |
| 50 | `ReorderFamiliesPageTest.php` | `ReorderFamiliesPage` | — | `pending` | E06 |
| 51 | `ReorderFamiliesActionTest.php` | `ReorderFamiliesAction` | — | `pending` | E06 |

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
