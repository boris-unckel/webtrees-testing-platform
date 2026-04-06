<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Upstream-Contribution — Test-Stubs mit echten Tests füllen

> **Separates Vorhaben**, unabhängig von diesem Repo.
> Ziel: PR an `fisharebest/webtrees` — Testabdeckung im Core verbessern.

Referenzen: [Feature-Matrizen](tds_conditions_ref.md) | [Abdeckungsmatrix](tds_coverage_ref.md)

## Abgrenzung

| Aspekt | `webtrees-testing-platform/` (dieses Repo) | Upstream-Branch (`${WEBTREES_SOURCE}`) |
|---|---|---|
| **Ort** | `webtrees-testing-platform/` (dieses Repo) | `${WEBTREES_SOURCE}` (Default `./upstream/webtrees`) |
| **Abhängigkeit** | Bindet `${WEBTREES_SOURCE}` nur lesend ein | Ändert webtrees-Code direkt (nur `tests/`) |
| **Zweck** | Eigene Testinfrastruktur (Container, OTel, Playwright) | Bestehende Stubs → echte Tests |
| **Zielgruppe** | Eigenbedarf (Regressionstests vor Updates) | Upstream-Community (PR) |
| **Redundanz** | Zunächst bewusst redundant | Nach Upstream-Akzeptanz: dieses Repo nutzt Core-Tests statt eigener |
| **Testframework** | PHPUnit + Playwright (eigene Infra) | PHPUnit (webtrees-eigene Infra: `TestCase.php`, SQLite in-memory) |

## Vorgehen

1. **Branch erstellen** im lokalen webtrees-Checkout (`${WEBTREES_SOURCE}`, z. B. `fill-test-stubs`)
2. **Stubs identifizieren** — alle Testdateien mit nur `testClass()`-Methode (siehe Gap-Analyse: ~95%)
3. **Priorisierung** — Feature-Matrizen G01–G23 und S01–S24, S26–S40 als Leitfaden:
   - Zuerst Komponententest-Stubs (Teststufe 1): `GedcomExportServiceTest`, `SearchServiceTest` etc.
   - Dann Komponentenintegrationstest-Stubs (Teststufe 2): Handler-Tests für Import/Export, Suche
4. **Tests schreiben** — innerhalb der bestehenden webtrees-Test-Infrastruktur:
   - `TestCase.php` als Basisklasse (SQLite in-memory, `importTree()`)
   - PHPUnit 12.x Assertions
   - `demo.ged` als Fixture (bereits in `tests/data/`)
   - Bestehende Coding-Standards (PSR-12, PHPStan Level 2)
5. **PR vorbereiten** — saubere Commit-Historie, ein Commit pro Service/Domäne

## Scope der Stub-Befüllung

| Domäne | Stubs → echte Tests | Orientierung |
|---|---|---|
| GEDCOM Import | `GedcomImportServiceTest` | G01–G04, G07–G12 |
| GEDCOM Export | `GedcomExportServiceTest` | G13–G19 |
| Suche | `SearchServiceTest` | S01–S08, S10–S12 |
| Handler (Import) | `ImportGedcomActionTest`, `ImportGedcomPageTest` | G20, G21 |
| Handler (Export) | `ExportGedcomClientTest`, `ExportGedcomServerTest` | G13 |
| Handler (Suche) | `SearchGeneralPageTest`, `SearchAdvancedPageTest`, `SearchPhoneticPageTest` | S01, S05, S07 |
| Charts | 13 Chart-Modul-Tests | S14–S18 (Rendering-Smoke) |
| Lists | 10 List-Modul-Tests | S19, S20 |
| AutoComplete | 16 TomSelect-Handler-Tests | S21, S22 |

## Abgrenzung zu diesem Repo

- **Kein Container-Stack nötig** — webtrees Core-Tests laufen mit SQLite in-memory
- **Kein Playwright** — nur PHPUnit, Handler-Tests über `RequestHandler`-Interface
- **Kein OTel** — reine Assert-basierte Tests
- **Bestehende CI nutzen** — webtrees hat `.github/workflows/phpunit.yaml`

## Redundanz und Rückbau

Zunächst entstehen ähnliche Tests an zwei Stellen:
- Dieses Repo: Teststufe 1 und 2 → eigene Testfälle
- `${WEBTREES_SOURCE}/tests/app/` → gefüllte Stubs

**Nach Upstream-Akzeptanz:**
- Dieses Repo entfernt redundante Komponenten- und Komponentenintegrationstests
- Dieses Repo konzentriert sich auf Bereiche, die Upstream nicht abdeckt: Testumgebung (Container-Stack), Systemtest mit Playwright (Teststufe 3), Performance-Baselines (Performanztest), OTel-Tracing
- Die Feature-Matrizen G01–G23 und S01–S24, S26–S40 bleiben als Referenz erhalten

## Status

| Schritt | Status | Ergebnis |
|---|---|---|
| Branch erstellen | Geplant | — |
| Stub-Inventur automatisieren | **Erledigt** | 202 Stubs identifiziert (26 Service, 176 Module). |
| Prio 1: Basis-Service-Stubs | **Erledigt** | 3 Service-Stubs gefüllt: `GedcomImportServiceTest`, `GedcomExportServiceTest`, `TreeServiceTest`. |
| Prio 2a: Service-Tests vertiefen | **Erledigt** | 5 Service-Tests erweitert: `GedcomImportServiceTest` (15→), `GedcomExportServiceTest` (11→), `GedcomServiceTest` (11→), `RelationshipServiceTest` (5→), `SearchServiceTest` (12→). |
| Prio 2b: Chart/List-Smoke | **Erledigt** | 11 Module-Tests von Stubs gefüllt: 6 Chart-Module (Ancestors, Pedigree, Descendancy, CompactTree, Fan, Hourglass), 7 List-Module (Individual, Family, Source, Repository, Note, Media, Submitter). 27 Tests. |
| Prio 3a: AutoComplete/Suche | **Erledigt** | 3 AutoComplete-Handler-Tests gefüllt (Place, Surname, Citation). 4 neue SearchService-Tests (Place, Media, Submitter). 1 Test übersprungen (upstream Bug). |
| Prio 3b: Encoding/Media | **Erledigt** | 3 neue GedcomImportService-Tests (multi-line CONT/CONC, empty fields, media objects). FamilyList + MediaList Module-Tests. |
| Prio 4: Restliche Stubs | **Erledigt** | `RomanNumeralsServiceTest` vollständig gefüllt (38 Tests via DataProvider). |
| Upstream-Bug dokumentiert | **Erledigt** | `FamilyFactory::mapper()` TypeError bei Privat-Familien (betrifft PRIV_NONE/PRIV_USER Export + Citation AutoComplete). |
| PR vorbereiten und einreichen | Geplant | — |
| **Gesamt** | **137 Tests** | **450 Assertions, 1 Skipped (upstream Bug), 0 Failures** |
