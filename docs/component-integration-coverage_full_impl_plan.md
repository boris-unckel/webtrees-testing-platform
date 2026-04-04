<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Implementierungsplan — Coverage-Erweiterung Teststufe 2 (Iteration 2)

> Basis: `docs/component-integration-coverage_full_analysis.md`
> Ausgangslage: 29,3% Statement-Coverage, 21,6% Methodenüberdeckung (384 Tests, 1.263 Assertions)

---

## Gesamtstatus

| AP | Titel | Status | Ergebnis |
|---|---|---|---|
| AP1 | ReportPdfTextBox::render — Bootstrap-only PDF | ✅ ABGESCHLOSSEN | 7 Tests, 14 Assertions, Exit 0 |
| AP2 | StatisticsData — usersLoggedIn + centuryName (indirekt) | ⬜ OFFEN | — |
| AP3 | UserEdit::execute — CLI + UserService | ⬜ OFFEN | — |
| AP4 | ReportParserGenerate — relativesStartHandler + weitere | ⬜ OFFEN | — |
| AP5 | MapDataImportAction::handle — place_location Import | ⬜ OFFEN | — |
| AP6 | CLI-Batch: UserTreeSetting + TreeSetting + SiteSetting + UserSetting | ⬜ OFFEN | — |
| AP7 | ReportPdfCell::render — Bootstrap-only PDF | ✅ ABGESCHLOSSEN | Abgedeckt durch AP1 |
| AP8 | GedcomLoad::handle — GEDCOM-Chunk-Import | ⬜ OFFEN | — |
| AP9 | ManageMediaData — handle + mediaObjectInfo | ⬜ OFFEN | — |
| AP10 | MergeFactsAction::handle — Record-Merge | ⬜ OFFEN | — |
| AP11 | TreeExport::execute — CLI Tree-Export | ⬜ OFFEN | — |
| AP12 | ReportPdfFootnote::getWidth + ReportPdfText::getWidth | ✅ ABGESCHLOSSEN | Abgedeckt durch AP1 |
| AP13 | MediaFileService::uploadFile | ⬜ OFFEN | — |
| AP14 | EditMediaFileAction::handle | ⬜ OFFEN | — |
| AP15 | BadBotBlocker::process — HTTP-Middleware (UA-Pfade) | ⬜ OFFEN | — |

---

## Ausgangslage

| Metrik | Wert |
|---|---|
| Anweisungsüberdeckung | 29,3% (12.897 / 44.043 Statements) |
| Methodenüberdeckung | 21,6% (958 / 4.433 Methoden) |
| Testklassen | 27 (384 Tests, 1.263 Assertions) |

---

## Stack-Regeln

- `make up` (nie `make _compose-up`) → `make setup`
- Lang laufende Tests: `run_in_background: true`, kein `timeout`-Parameter
- `pgrep -a phpunit` vor jedem neuen Testlauf
- Niemals parallele Testläufe

### Container-Pfade

```bash
# PHPUnit-Konfiguration:
/tests/layer3-integration/phpunit-integration.xml

# Testdateien:
/tests/layer3-integration/tests/MeineTestklasse.php

# Einzeltest-Befehl:
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'MeineTestklasse' \
  /tests/layer3-integration/tests/MeineTestklasse.php
```

### AP-Priorisierung

Gruppe A (AP1) vollständig vor Gruppe B (AP2–AP8).
Gruppe B vollständig vor Gruppe C (AP9–AP15).
Innerhalb einer Gruppe: CRAP absteigend (bereits so angeordnet).

### Konstruktor-Verifikation vor Skelett

Bevor ein PHP-Skelett erstellt wird: Konstruktor-Argumente aus dem webtrees-Source
verifizieren (`upstream/webtrees/app/`). Kein `new Foo()` ohne Konstruktor-Prüfung.

### Keine Zwischencommits

Erst nach Abschluss aller APs + `make test-integration` Exit 0 committen.
