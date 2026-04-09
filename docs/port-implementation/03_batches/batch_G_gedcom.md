<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Batch G — GEDCOM Import/Export

**Priorität:** 3 (Handler mit Dependencies)
**Feature-IDs:** G01–G30

**Hinweis:** Die meisten GEDCOM-Features sind inhärent DB-abhängig (Import schreibt
in 15+ Tabellen, Export liest Records aus DB). Nur Handler-Dispatch-Logik und
zustandslose Hilfsmethoden sind L2-portierbar.

---

## Portierbare Tests

### Handler-Tests (Template 1)

| # | Test-Datei | SUT-Klasse | Dependencies | Status | Bemerkung |
|---|-----------|------------|-------------|--------|-----------|
| 1 | `UploadMediaActionTest.php` | `UploadMediaAction` | `MediaFileService` | `pending` | G21, G30 |
| 2 | `EditMediaFileActionTest.php` | `EditMediaFileAction` | `MediaFileService` | `pending` | G28 |
| 3 | `EditMediaFileModalTest.php` | `EditMediaFileModal` | `MediaFileService` | `pending` | G28 |
| 4 | `CheckTreeTest.php` | `CheckTree` | `TreeService` | `pending` | G24 |
| 5 | `GedcomLoadTest.php` | `GedcomLoad` | `TreeService`, `GedcomImportService` | `pending` | G25 (Handler-Teil, CLI→L3) |
| 6 | `MediaFileServiceTest.php`* | `MediaFileService` | diverse | `pending` | G27 |
| 7 | `ImportGedcomActionTest.php` | `ImportGedcomAction` | `GedcomImportService` | `pending` | A02 overlap |
| 8 | `ExportGedcomPageTest.php` | `ExportGedcomPage` | `GedcomExportService` | `pending` | A03 overlap |
| 9 | `ExportGedcomActionTest.php` | `ExportGedcomAction` | `GedcomExportService` | `pending` | A03 overlap |

### Service-Tests (L2-fähige Methoden)

| # | Test-Datei | SUT-Klasse | Template | Status | Bemerkung |
|---|-----------|------------|----------|--------|-----------|
| 10 | `GedcomServiceTest.php` | `GedcomService` | T2 | `pending` | **Ship as-is** von 5349: 12 pure Tests (canonicalTag, readLatitude/Longitude) |
| 11 | `GedcomExportServiceTest.php` (partiell) | `GedcomExportService` | T1 | `pending` | G18–G19: nur `wrapLongLines()`, `createHeader()` → pure Funktionen, kein Mock nötig |

### Bestehende substanzielle Tests (Verbesserung in P2)

| Test-Datei | Methoden | Verbesserungspotenzial |
|-----------|----------|----------------------|
| `GedcomEditServiceTest.php` | 3 | G29: substanziell, mehr Pfade prüfen |
| 212 Element-Tests | je 7 | G22: substanziell, XSS/canonical/pattern |

## Ausgeschlossen (Layer 3 — DB-Import/Export)

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| G01–G06 | Record-Import (INDI, FAM, Nebenrecords, Places, Dates, Names) | importRecord() → 15+ DB-Tabellen |
| G09 | Inline-Media-Import | DB-Tabelle media |
| G10 | Legacy-Formate | Import-Pipeline |
| G12 | XREF-Eindeutigkeit | DB-Constraint |
| G14–G17 | Export ZIP, ZIP+Media, Privacy, Encoding | Tree-Daten aus DB |
| G20 | Import→Export Roundtrip | Nur mit DB sinnvoll |
| G23 | GEDCOM 5.5.1 Compliance | Import-Pipeline |
| G25–G26 | GedcomLoad/Export CLI (Kern) | CLI+DB |

## Discovery

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
ls tests/app/Http/RequestHandlers/*Gedcom*Test.php tests/app/Http/RequestHandlers/*Media*Test.php
ls tests/app/Services/Gedcom*Test.php
# 5349-Branch Tests
git show 5349_add_tests:tests/app/Services/GedcomServiceTest.php | head -30
```

## Statistik

- Portierbar: ~11
- Ausgeschlossen: 18 Feature-IDs (DB-Import/Export)
- Ship as-is: GedcomServiceTest (12 Tests)
- Bereits substanziell: GedcomEditServiceTest (3), 212 Element-Tests
