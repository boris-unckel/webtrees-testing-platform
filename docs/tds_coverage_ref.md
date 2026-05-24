<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Abdeckungsmatrix — Testabdeckung nach Feature-Matrix-ID

Dieses Dokument bildet die Testabdeckung pro Feature-Matrix-ID ab. Jedes Feature wird auf Upstream-Tests (SQLite), eigene Infrastruktur-Tests (MySQL-Integration / Playwright-E2E) und den Abdeckungsstatus abgebildet.

**Querverweise:**

- [Feature-Matrizen](tds_conditions_ref.md)
- [Überdeckungsstrategie](tp_ratchet_spec.md)
- [Testentwurfsverfahren](tds_methodik_spec.md)

**Stand 2026-05-24:** **216 abgedeckt** (215 spezifikationsbasiert + 1 strukturbasiert),
**2 nicht abgedeckt** (G05, G06), **1 SKIP** (U02 deprecated) / **219 Features gesamt**.
Coverage-Kennzahlen siehe [`tp_ratchet_spec.md`](tp_ratchet_spec.md#ist-stand-stand-2026-05-24).
Historische Snapshots liegen unter [`coverage-runs/`](coverage-runs/).

---

## Teststufen und Layer — Nomenklatur

Dieses Projekt verwendet zwei Bezugssysteme, die sich gegenseitig ergänzen:

| ISTQB-Teststufe               | Layer (Makefile/Verzeichnis)         | Pfad                    |
|-------------------------------|--------------------------------------|-------------------------|
| —                             | L1 — Statische Analyse               | `layer1-static/`        |
| Teststufe 1 — Komponententest | L2 — `make test-unit`                | `layer2-unit/` (Upstream-`main`-Testbasis) |
| Teststufe 2 — KIT             | L3 — `make test-integration`         | `layer3-integration/`   |
| Teststufe 3 — Systemtest      | L4 — `make test-e2e`                 | `layer4-e2e/`           |
| — (Querschnitt)               | L5 — `make test-performance`         | `layer5-performance/`   |

In den Feature-Matrizen ([`tds_conditions_ref.md`](tds_conditions_ref.md)) wird die
Teststufen-Spalte als ISTQB-Nummer (1–3) geführt (historisch gewachsen, Bezug:
`tp_decisions_spec.md`). In der Abdeckungsmatrix dieses Dokuments werden die Spalten per
Layer (L2/L3/L4) benannt, weil die Layer die physische Testinfrastruktur beschreiben, in
der die Tests laufen.

---

## Pfad-Legende für Testklassen

Die Zellen der Abdeckungsmatrizen enthalten **Dateinamen ohne Pfad**. Die zugehörigen
Verzeichnisse sind über ein L-Präfix eindeutig adressiert:

| Präfix | Pfad | Repo / Branch |
|---|---|---|
| `L2:` | `tests/app/` und `tests/feature/` | `upstream/webtrees` @ Upstream-`main` (im Testing-Platform-Repo, read-only) |
| `L3:` | `layer3-integration/tests/` | `webtrees-testing-platform` @ `main` |
| `L4:` | `layer4-e2e/tests/` | `webtrees-testing-platform` @ `main` |

**Konvention:** In den Zellen werden Dateinamen ohne Pfad, aber mit L-Präfix notiert
(z. B. `L3: GedcomImportTest.php`). Das L-Präfix wird nur dort eingesetzt, wo die Zelle
ohne Präfix mehrdeutig wäre — z. B. wenn derselbe Dateiname in mehreren Layern existiert
oder wenn die Spaltenzugehörigkeit aus dem Kontext nicht eindeutig ist. Existierende Einträge
ohne Präfix werden sukzessive in Phase 6 (Inhalts-Migration der Zellen) auf die Konvention
nachgezogen.

---

## Qualitätssiegel-Katalog

Jede **abgedeckte** Zelle in den nachfolgenden Abdeckungsmatrizen trägt genau ein
Qualitätssiegel. Leere Zellen (nicht abgedeckt, `—`) erhalten kein Siegel. Das Siegel
qualifiziert die Testtiefe der zugeordneten Testklasse bzw. Spec.

| Siegel      | Bedeutung                              | Kriterium                                                                     |
|-------------|----------------------------------------|-------------------------------------------------------------------------------|
| `[EP]`      | EP-complete                            | DataProvider mit ≥3 Partitionen oder explizite EP-Markierung in der Klasse    |
| `[Spec-B]`  | Spezifikationsbasiert, strikt          | Testmethoden 1:1 einer externen Spezifikation folgend (GEDCOM, RFC, W3C)      |
| `[Spec-C]`  | Spezifikationsbasiert, pragmatisch     | Fachliche Assertions, aber ohne strikte Spec-Ableitung                        |
| `[Smoke]`   | Smoke                                  | 3–5 Assertions, kein fachlicher Pfad                                          |
| `[CRAP]`    | Strukturbasiert                        | Aus CRAP-Report abgeleitete Tests (CRAP > 100 oder 0 %-Branch)                |

**Lesart:** Das Siegel folgt dem Testklassen-/Spec-Namen in der Zelle, gefolgt vom
Abdeckungs-Check (`✅`). Die vollständige Zellen-Syntax (inkl. Testmethoden-Zahl und
Detailkonzept-Link) wird in Plan-Phase 6 durchgängig hergestellt. Bis dahin steht das
Siegel unmittelbar hinter dem Klassennamen, z. B. `GedcomImportServiceTest [EP] ✅`.

**Einstufungs-Quelle:** Die Hybrid-Heuristik V2 ist erstmals dokumentiert in
[`coverage-runs/historical/2026-04-11_gap-analyse-fork.md`](coverage-runs/historical/2026-04-11_gap-analyse-fork.md) §2
und kommt in der aktuellen Erhebung
[`coverage-runs/2026-05-24_gap-analyse.md`](coverage-runs/2026-05-24_gap-analyse.md) §1.3
in unveränderter Form zum Einsatz. Sie ist die Referenz für die Einstufung — die darin
erhobenen Stub/Smoke/Substantial/EP-Zahlen werden pro Feature-ID auf die obigen Siegel
abgebildet (`Stub → Smoke` nur wenn 3+ Assertions, sonst wird das Feature als nicht
abgedeckt notiert; `Substantial → Spec-C`; `EP-complete → EP`; externe Spec vorhanden → `Spec-B`).

---

## Domänen-Navigation

[G](#g) · [S](#s) · [P](#p) · [SEC](#sec) · [E](#e) · [A](#a) · [K](#k) · [U](#u) · [M](#m)

---

### Abdeckungsmatrix: Feature-Matrix → Testabdeckung

<a id="g"></a>

#### GEDCOM Import/Export (G01–G31)

> **Befund (Upstream-`main`):** `GedcomImportServiceTest.php` ist im Upstream-`main` nur als **Stub** vorhanden (1 Methode, 1 Assertion — `assertTrue(class_exists(...))`). Die ehemals mit `[Spec-B]` gekennzeichneten L2-Zellen für G01–G12 wurden deshalb auf `—` zurückgesetzt; die substanzielle Abdeckung erfolgt ausschließlich über `GedcomImportTest` (L3, 28 Methoden). Konsequenz: L2-Coverage für G-Domäne sinkt; Gesamt-Features ändern sich nicht (nur Spalten-Inhalt).

| # | Feature | L2 — Komponententest (Upstream-`main`) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| G01 | Record-Import (INDI) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(28 Tests)* | — | OK | — |
| G02 | Record-Import (FAM) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` + `RelationshipDbTest` [Spec-B] ✅ *(28+3 Tests)* | — | OK | — |
| G03 | Record-Import (Nebenrecords) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(28 Tests)* | — | OK | — |
| G04 | Place-Hierarchie | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(28 Tests)* | — | OK | — |
| G05 | Date-Parsing | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomServiceIntegrationTest` [CRAP] ✅ *(GedcomService-Utility-Methoden: canonicalTag-Synonyme + readLatitude/readLongitude; CRAP-Anteil — Date-Parsing selbst weiterhin nicht direkt geprüft)* | — | — | L2-Stub ungenügend; Date-Parsing-Lücke besteht weiter (nur Utility-CRAP abgedeckt) |
| G06 | Name-Extraktion + Soundex | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | — | — | — | L2-Stub ungenügend; L3-Gap |
| G07 | Encoding (UTF-8) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(28 Tests)* | — | OK | — |
| G08 | Encoding (ANSEL, CP1252) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(4 Tests: ANSEL/CP1252 Post-Konvertierung)* | — | OK | — |
| G09 | Inline-Media | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(3 Tests: OBJE-Split, Dateireferenzen, Verknüpfung)* | — | OK | — |
| G10 | Legacy-Formate | — | `GedcomImportTest` [Spec-B] ✅ *(4 Tests: _PLAC_DEFN, _PLAC, Koordinaten)* | — | OK | — |
| G11 | Custom-Tags | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(3 Tests: Ancestry, FamilySearch, RootsMagic)* | — | OK | — |
| G12 | XREF-Eindeutigkeit | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(28 Tests)* | — | OK | — |
| G13 | Export GEDCOM | `GedcomExportServiceTest` [Spec-B] ✅ *(4 Tests/9 Assertions, Substantial)* | `TreeOperationsTest` [Spec-B] ✅ *(23 Tests/43 Assertions)* | — | OK | — |
| G14 | Export ZIP | — *(upstream-Tests decken Sort by XREF ab, nicht ZIP-Format)* | `TreeOperationsTest` [Spec-B] ✅ *(3 Tests: ZIP valide, .ged enthalten, GEDZIP)* | — | OK | — |
| G15 | Export ZIP+Media | — *(upstream-Tests decken Download-Response ab, nicht ZIP+Media)* | `TreeOperationsTest` [Spec-C] ✅ *(2 Tests: Mediendateien im ZIP, Referenzen)* | — | OK | — |
| G16 | Export Privacy | `GedcomExportServiceTest` [Spec-B] ✅ *(4 Tests; PRIV_HIDE; PRIV_NONE/USER → upstream Bug)* | `TreeOperationsTest` [Spec-B] ✅ *(PRIV_NONE + PRIV_USER Regressions-Guard)* | — | OK | Upstream-Bug |
| G17 | Export Encoding | `GedcomExportServiceTest` [Spec-B] ✅ *(4 Tests; CONC)* | `TreeOperationsTest` [Spec-B] ✅ *(3 Tests: UTF-8, ANSEL, CP1252)* | — | OK | — |
| G18 | Export CONC/CONT | `GedcomExportServiceTest` [Spec-B] ✅ *(4 Tests/9 Assertions)* | — | — | OK | — |
| G19 | Export Header | `GedcomExportServiceTest` [Spec-B] ✅ *(4 Tests/9 Assertions)* | — | — | OK | — |
| G20 | Import→Export Roundtrip | `GedcomExportServiceTest` [Spec-B] ✅ *(4 Tests; INDI/FAM-Counts nach Export)* | — | — | OK | — |
| G21 | Upload-Validierung | `UploadMediaActionTest` [Spec-C] ✅ *(6 Tests, Substantial)* | — | `upload-validation.spec.ts` [Spec-C] ✅ *(4 Tests: leere/Text/NoHead/Binär-Datei)* | OK | — |
| G22 | Element-Validierung | 212 Element-Tests [Spec-B] ✅ *(~1484 Methoden gesamt, je Element 7 Methoden: XSS/canonical/pattern)* | — | — | OK | — |
| G23 | GEDCOM 5.5.1 Compliance | — | `GedcomImportTest` [Smoke] ✅ *(1 Test: Standard-Tags OCCU/RELI/NATI nicht verworfen)* | — | OK | — |
| G24 | Referenzintegrität | `CheckTreeTest` [Spec-C] ✅ *(4 Tests, Substantial)* | `CheckTreeIntegrationTest` [Smoke] ✅ *(2 Tests: 200 OK + nicht-leerer Body auf demo.ged)* | — | OK | — |
| G25 | GedcomLoad CLI-Import | — | `GedcomLoadIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 8 Tests: EP1 keep_media=0, EP2 keep_media=1, EP3 BOM-Strip, EP4 kein-HEAD→Fail, EP5 kein-Trailer→Fail, EP6 Complete-View)* | — | OK | — |
| G26 | GEDCOM-Export via CLI | — | `TreeExportCommandIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 13 Tests: EP Format×4+1 invalid, EP Privacy×4+1 invalid, Tree-not-found)* | — | OK | — |
| G27 | Mediendatei-Upload URL | — | `MediaFileServiceUploadIntegrationTest` [CRAP + Spec-C] ✅ *(CRAP-Analyse + Extension-Blocklist gefährlicher Extensions → Upload via Exception abgewiesen, L0-strongest-mitigation)* | — | OK | — |
| G28 | OBJE-Metadaten bearbeiten | — | `EditMediaFileIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 2 Tests: Fact-not-found-Redirect, Happy Path DB-Postcondition change-Tabelle)* | — | OK | — |
| G29 | GEDCOM-Bearbeitungsservice | — | `GedcomEditServiceIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 9 Tests: editLinesToGedcom EP Normal/CONT/Leer/Sub-Level, insertMissingLevels EP Expansion/Tiefe/Tags)* | — | OK | — |
| G30 | Mediendatei-Upload (HTTP-Formular) | — | `UploadMediaActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 3 Tests: UPLOAD_ERR_NO_FILE→302, UPLOAD_ERR_PARTIAL→FileUploadException, gefährliche Extension→FlashMessage+302)* + `AddMediaFileActionIntegrationTest` [Spec-C] ✅ *(AddMediaFileAction/Modal: Upload-Fehler-Pfade gegen echtes Media-Objekt aus demo.ged)* | — | OK | — |
| G31 | GEDCOM-Import via CLI | — | `TreeImportCommandIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 4 Tests: gültiger Import→SUCCESS+DB-Count, Baum-not-found→FAILURE, Datei-not-found→FAILURE, keep-media-Option→SUCCESS)* | — | OK | — |

> **Fußnote (L2-Spalte):** Der in der L2-Spalte angezeigte Testumfang entspricht dem Stand
> Upstream-`main` (Commit `6966db16a6`, gemessen 2026-05-24).
> Einzelne Zellen können noch auf historische Stände referenzieren, die per
> Spuernasen-Sweep nachgezogen werden. Aktueller Inventar-Snapshot:
> [`coverage-runs/2026-05-24_gap-analyse.md`](coverage-runs/2026-05-24_gap-analyse.md).
> Der historische Snapshot
> [`coverage-runs/historical/2026-04-11_gap-analyse-fork.md`](coverage-runs/historical/2026-04-11_gap-analyse-fork.md)
> ist als Vergleichspunkt eines damals untersuchten Branches archiviert.

<a id="s"></a>

#### Suche und Navigation (S01–S53)

> **Befund (Upstream-`main`):** Drei L2-Stub-Korrekturen: (1) `SearchServiceTest` ist im Upstream nur ein Stub (1 Methode/7 Assertions) — von `[Spec-C]` auf `[Smoke]` herabgestuft (S01–S04, S10, S12). (2) `GedcomImportServiceTest` ist Stub (1 Methode/1 Assertion) — L2-Zellen S07/S08 auf `—` zurückgesetzt (L3 deckt weiterhin ab). (3) `RelationshipServiceTest` ist Stub (1 Methode/1 Assertion) — L2-Zelle S16 auf `—` zurückgesetzt (L3 deckt weiterhin ab).

| # | Feature | L2 — Komponententest (Upstream-`main`) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| S01 | Allg. Suche (Personen) | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions)* | — | — | OK | — |
| S02 | Allg. Suche (Familien) | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions)* | — | — | OK | — |
| S03 | Allg. Suche (SOUR, NOTE, REPO) | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions; Sources, Repos, Submitters)* | — | — | OK | — |
| S04 | Query-Parsing | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions; Multi-word, non-matching)* | — | — | OK | — |
| S05 | Erweiterte Suche (Felder) | — | `SearchIntegrationTest` [Spec-C] ✅ *(5 Tests: Name, Nachname, Sterbedatum, Multi-Feld, leere Felder)* + `SearchAdvancedActionIntegrationTest` [Spec-C] ✅ *(Action-Handler: POST → 302 mit ergänzten Feldern)* | `advanced-search-execution.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| S06 | Erweiterte Suche (Datum) | — | `SearchIntegrationTest` [Spec-C] ✅ *(3 Tests: ±0, ±5, ±20 Jahre)* | `advanced-search-execution.spec.ts` [Spec-C] ✅ *(1 Test × 5 Themes)* | OK | — |
| S07 | Phonetische Suche (Russell) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `SearchIntegrationTest` [Smoke] ✅ *(2 Tests: Treffer + kein Treffer)* + `SearchPhoneticActionIntegrationTest` [Spec-C] ✅ *(Russell-Soundex POST → 302)* | `phonetic-search-execution.spec.ts` [Spec-C] ✅ *(2 Tests × 5 Themes)* | OK | — |
| S08 | Phonetische Suche (DM) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `SearchIntegrationTest` [Smoke] ✅ *(2 Tests: Treffer + kein Treffer)* + `SearchPhoneticActionIntegrationTest` [Spec-C] ✅ *(Daitch-Mokotoff-Soundex POST → 302)* | `phonetic-search-execution.spec.ts` [Spec-C] ✅ *(1 Test × 5 Themes)* | OK | — |
| S09 | Quick-Search (XREF) | — | `SearchQuickActionIntegrationTest` [Spec-C] ✅ *(XREF-Lookup → direkter Redirect zum Record bzw. Fallback auf allgemeine Suchseite)* | `navigation.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S10 | Paginierung | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions)* | `SearchIntegrationTest` [Spec-C] ✅ *(3 Tests: Limit, Offset, Offset+Limit)* | `search-pagination.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| S11 | Cross-Tree-Suche | — | `SearchIntegrationTest` [Smoke] ✅ *(2 Tests: Ergebnisse aus beiden Bäumen, Tree-spezifischer Name)* | — | OK | — |
| S12 | Zugriffskontrolle (Suche) | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions; Guest vs Admin)* | — | — | OK | — |
| S13 | Search-and-Replace | — | `SearchReplaceActionIntegrationTest`, `SearchReplacePageIntegrationTest` [Spec-C] ✅ *(Action: Context-Branches all/name/place → SearchService-Delegationen; Page: Formular-Render mit/ohne Query-Params)* | `search-replace.spec.ts` [Spec-C] ✅ *(3 Tests; 2×5 Themes + 1 Visitor)* | OK | — |
| S14 | Chart: Pedigree | `PedigreeChartModuleTest` [Spec-C] ✅ *(4 Tests)* | — | `pedigree.spec.ts` [Spec-C] ✅ *(2 Tests × 5 Themes)* | OK | — |
| S15 | Chart: Nachkommen | `DescendancyChartModuleTest` [Spec-C] ✅ *(4 Tests)* | — | — | OK | — |
| S16 | Chart: Beziehungsfinder | — *(`RelationshipServiceTest` nur Stub 1 Test)* | `RelationshipServiceIntegrationTest` [Spec-C] ✅ *(16 Tests: direkte Pfade, Onkel/Tante, Großeltern, Ehepartner)* | `relationship-chart.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| S17 | Chart: Fächerchart | `FanChartModuleTest` [Spec-C] ✅ *(4 Tests)* | — | — | OK | — |
| S18 | Chart: alle 13 Typen (Smoke) | 6 Chart-Tests [Spec-C] ✅ *(je 4 Tests: Ancestors, CompactTree, Descendancy, Fan, Hourglass, Pedigree)* + `StatisticsChartModuleTest` [Spec-C] ✅ *(3 Tests + 2 DataProvider)* | `ChartModuleIntegrationTest` [Spec-C] ✅ *(17 Tests + 4 DataProvider: Timeline, Lifespan, FamilyBook, Relationships, Branches)* | `chart-types.spec.ts` [Spec-C] ✅ *(5 Tests × 5 Themes)* | OK (13/13) | — |
| S19 | Liste: Personen (Nachnamen) | `IndividualListModuleTest` [Spec-C] ✅ *(3 Tests: handle, show_all, listIsEmpty)* | `ListModuleIntegrationTest` [Smoke] ✅ *(17 Tests; initial-Filter 'W' via handle())* | `navigation.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S20 | Liste: alle 10 Typen (Smoke) | 7 List-Tests [Spec-C] ✅ *(je 3–4 Tests: Individual, Family, Source, Repository, Note, Media, Submitter)* | `ListModuleIntegrationTest` [Spec-C] ✅ *(3 Tests: Location, PlaceHierarchy, Branches)* | — | OK (10/10) | — |
| S21 | AutoComplete (Personen) | `AutoCompleteSurnameTest` [Spec-C] ✅ *(4 Tests/8 Assertions)* | — | — | OK | — |
| S22 | AutoComplete (Orte) | `AutoCompletePlaceTest` [Spec-C] ✅ *(4 Tests/7 Assertions; match + no-match)* | — | — | OK | — |
| S23 | Navigation: Personenseite | — | `IndividualPageIntegrationTest` [Spec-C] ✅ *(Slug-Match → 200, Slug-Redirect → 301, unbekannte XREF → HttpNotFoundException)* | `individual.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S24 | Navigation: Familienseite | — | `FamilyPageIntegrationTest` [Spec-C] ✅ *(Slug-Match → 200, Slug-Redirect → 301, unbekannte XREF → HttpNotFoundException)* | `family.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| S25 | Navigation: HEAD-Record-Seite | — | `HeaderPageIntegrationTest` [Spec-C] ✅ *(3 Tests: Slug-Match → 200, Slug-Redirect → 301, unbekannte XREF → HttpNotFoundException)* | — | OK | — |
| S26 | Navigation: Quellenseite | — | `SourcePageIntegrationTest` [Spec-C] ✅ *(Slug-Match → 200, Slug-Redirect → 301, unbekannte XREF → HttpNotFoundException)* | `records.spec.ts` [Smoke] ✅ *(5 Tests gesamt; Quellen-Seite)* | OK | — |
| S27 | Navigation: Medienseite | — | `MediaPageIntegrationTest` [Spec-C] ✅ *(Slug-Match → 200, Slug-Redirect → 301, unbekannte XREF → HttpNotFoundException)* | `records.spec.ts` [Smoke] ✅ *(5 Tests gesamt; Medien-Seite)* | OK | — |
| S28 | Navigation: Notizseite | — | `NotePageIntegrationTest` [Spec-C] ✅ *(Slug-Match → 200, Slug-Redirect → 301, unbekannte XREF → HttpNotFoundException)* | `records.spec.ts` [Spec-C] ✅ *(5 Tests gesamt; NOTE-Seite auf `muster`-Tree, 5 Themes)* | OK | — |
| S29 | Navigation: Aufbewahrungsort | — | `RepositoryPageIntegrationTest` [Spec-C] ✅ *(Slug-Match → 200, Slug-Redirect → 301, unbekannte XREF → HttpNotFoundException)* | `records.spec.ts` [Smoke] ✅ *(5 Tests gesamt; Aufbewahrungsort-Seite)* | OK | — |
| S30 | Navigation: Einreicherseite | — | `SubmitterPageIntegrationTest` [Spec-C] ✅ *(Slug-Match → 200, Slug-Redirect → 301, unbekannte XREF → HttpNotFoundException)* | `records.spec.ts` [Smoke] ✅ *(5 Tests gesamt; Einreicher-Seite)* | OK | — |
| S31 | Kalenderansicht & Kalenderevents-API | — | `CalendarChartIntegrationTest` [CRAP] ✅ *(CalendarService::getAnniversaryEvents CRAP 870/cx=29 + CalendarAction/CalendarPage/CalendarEvents Smoke)* | `calendar.spec.ts` [Spec-C] ✅ *(2 Tests: Monat + Jahr; CalendarEvents AJAX implizit)* | OK | — |
| S32 | Anmeldeseite (Login) | — | `LoginPageIntegrationTest` [Spec-C] ✅ *(4 Tests: Default 200, bereits-eingeloggt 302, gewählter Baum 200, TreeService-Default-Redirect 302)* | `login.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| S33 | Registrierungsseite | — | `UserRegistrationIntegrationTest` [Spec-C] ✅ *(RegisterAction Pre-Condition-Gate, RegisterPage GET → 200, VerifyEmail Token-Flow)* | `auth.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S34 | Passwort-Zurücksetzung | — | `PasswordResetActionIntegrationTest` [Spec-C] ✅ *(2 Tests: valid-token → Passwort gesetzt + 302, expired-token → 302 + Flash-Message)* | `auth.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S35 | Benutzerseite (Meine Seite) | — | `UserPageIntegrationTest`, `UserPageEditIntegrationTest`, `UserPageUpdateIntegrationTest` [Spec-C] ✅ *(GET 200, Edit-Phase Block-Übersicht, Update-Phase Persistenz via HomePageService)* | `user-pages.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S36 | Kontaktseite | — | — | `user-pages.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S37 | Berichtsliste | — | — | `user-pages.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S38 | Erweiterte Suche (Seitenaufruf) | — | `SearchAdvancedPageIntegrationTest`, `SearchAdvancedActionIntegrationTest` [Spec-C] ✅ *(Page: Render + Such-Auswertung; Action: 302-Redirect zu Page mit ergänzten POST-Feldern)* | `search-forms.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S39 | Phonetische Suche (Seitenaufruf) | — | `SearchPhoneticPageIntegrationTest`, `SearchPhoneticActionIntegrationTest` [Spec-C] ✅ *(Page: Default/Russell/Daitch-Mokotoff; Action: Soundex-Wahl + 302-Redirect)* | `search-forms.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S40 | Navigation: Homepage (Baumseite) | — | `HomePageIntegrationTest`, `TreePageIntegrationTest` [Spec-C] ✅ *(HomePage: keine-Bäume → 302 Login; TreePage: Default-Insert + Skip-Pfad bei vorhandenen Blöcken)* | `homepage.spec.ts` [Spec-C] ✅ *(2 Tests × 5 Themes)* | OK | — |
| S41 | Statistikdaten-Abfragen | — | `StatisticsDataIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 13 Tests: 4 alt + EP5/EP6/EP8 whereBetween, DataProvider sort×3, EP13 threshold, DataProvider sex×2)* + `StatisticsIntegrationTest` ✅ *(CRAP-Smoke)* + `StatisticsChartIntegrationTest` [CRAP] ✅ *(postCustomChartAction CRAP 14.042/cx=118)* | `statistics-page.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| S42 | Such-HTTP-Handler | — | `SearchRequestHandlerIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 6 Tests: Single-Result-Redirect EP2/EP4, Default-Fallback EP8, Multi-Result EP1/EP3)* | — | OK | — |
| S43 | Report-Generierung HTTP | — | `ReportIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 8 Tests: EP2 PDF→application/pdf, EP6 download→content-disposition, B1 unknown-redirect, 5 bisherige HTML/SAX-Tests)* | — | OK | — |
| S44 | Report-Parser Erweitert | — | `ReportParserGenerateExtendedIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert Pragmatisch C, 3 Tests: EP1 Vorfahren+assertNotEmpty+HTML, EP3 Nachkommen+assertNotEmpty+HTML, EP7 Individual+Fakten+Bild+assertNotEmpty+HTML)* + `RightToLeftSupportIntegrationTest` [CRAP] ✅ *(RTL-Span CRAP-Anteil)* | — | OK | — |
| S45 | Report-Primitive PDF/HTML | — | `ReportPdfObjectsIntegrationTest` + `ReportHtmlObjectsIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert+strukturbasiert, 23 Tests: 13 HTML (fill/border/newline Assertions TextBox+Cell) + 10 PDF (3 Image-Branch-Tests + 7 Basis))* + `RomanNumeralsIntegrationTest` [CRAP] ✅ *(RomanNumeralsService numeric/roman Konversion)* | — | OK | — |
| S46 | Homepage-Block-Module | — | `BlockModuleIntegrationTest` [EP] ✅ *(spezifikationsbasiert Pragmatisch C, 14 Tests: 10 alt + DataProvider infoStyles×4 EP4/EP5/EP6/EP6b)* + `PageBlockDisplayIntegrationTest`, `PageBlockEditIntegrationTest`, `PageBlockUpdateIntegrationTest`, `TreePageEditIntegrationTest`, `TreePageUpdateIntegrationTest`, `UserPageEditIntegrationTest`, `UserPageUpdateIntegrationTest` [Spec-C] ✅ *(TreePage/UserPage-Block-Display/Edit/Update-Handler — Block-Konfiguration des Tree- und User-Dashboards)* | `homepage-blocks.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| S47 | Interaktiver Stammbaum | — | `InteractiveTreeIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert Pragmatisch C, 3 Tests: getDetails→XREF im Output, 'p'-Request→non-empty HTML, 'c'-Request→non-empty HTML)* | `interactive-tree.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| S48 | Standortdaten-Import Admin | — | `MapDataImportIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 4 Tests: EP1+EP5 add→DB-Postcondition lat/lng, EP6 Null-Island→gefiltert, 2 Smoke-Fehlerresilienz)* | — | OK | — |
| S49 | Medienverwaltungsliste Admin | — | `ManageMediaDataIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 3 Tests: EP1 local + EP2 external + EP3 unused, JSON-Struktur-Assertions)* | — | OK | — |
| S50 | Hilfetexte | — | `HelpTextIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 13 Tests: DataProvider 12 Topics + unknown-Topic)* | `help-texts.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| S51 | Sprachauswahl-Handler | — | `SelectLanguageIntegrationTest` [Spec-C] ✅ *(2 Tests: Gast und registrierter Benutzer — Sprachcode in Session + User-Preference persistiert, 204 No Content)* | — | OK | — |
| S52 | Standortdaten-Verwaltung (CRUD) | — | `MapDataCrudIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 5 Tests: MapDataSave INSERT→DB, UPDATE→DB, MapDataDelete→entfernt, MapDataExportCSV→text/csv, MapDataList GET→200)* | — | OK | — |
| S53 | Legacy-URL-Weiterleitungen | — | `LegacyUrlRedirectIntegrationTest` [Batch-Smoke + EP] ✅ *(spezifikationsbasiert, 13 Tests, 49 Assertions: Individual/Family/Source/Note/Repository/GedRecord→301, invalid tree→410, invalid record→410, Calendar/ReportEngine, DEFAULT_GEDCOM fallback, Pedigree style mapping)* | `legacy-url-redirects.spec.ts` [API-Only] ✅ *(8 Tests)* | OK | — |

<a id="p"></a>

#### Datenschutz & Zugriffskontrolle (P01–P44)

| # | Feature | L2 — Komponententest (Upstream-`main`) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| P01 | Stammbaum-Sichtbarkeit | — | `PrivacyVisibilityTest` [Spec-C] ✅ *(22 Tests)* | `privacy-visibility.spec.ts` [Spec-C] ✅ *(5 Tests)* | OK | — |
| P02 | Verstorbene Personen zeigen | — | `PrivacyVisibilityTest` [Spec-C] ✅ *(22 Tests)* | `privacy-visibility.spec.ts` [Spec-C] ✅ *(5 Tests)* | OK | — |
| P03 | Lebende Personen zeigen (Override) | — | `PrivacyVisibilityTest` [Spec-C] ✅ *(22 Tests)* | `privacy-visibility.spec.ts` [Spec-C] ✅ *(5 Tests)* | OK | — |
| P04 | MAX_ALIVE_AGE — Altersgrenze | — | `IsDeadTest` + `PrivacyVisibilityTest` [Spec-C] ✅ *(17+22 Tests)* | — | OK | — |
| P05 | KEEP_ALIVE_YEARS_BIRTH | — | `PrivacyVisibilityTest` [Spec-C] ✅ *(22 Tests)* | — | OK | — |
| P06 | KEEP_ALIVE_YEARS_DEATH | — | `PrivacyVisibilityTest` [Spec-C] ✅ *(22 Tests)* | — | OK | — |
| P07 | KEEP_ALIVE kombiniert | — | `PrivacyVisibilityTest` [Spec-C] ✅ *(22 Tests)* | — | OK | — |
| P08 | isDead(): Expliziter Tod | — | `IsDeadTest` [Spec-C] ✅ *(17 Tests)* | — | OK | — |
| P09 | isDead(): Datiertes Event > MAX_ALIVE_AGE | — | `IsDeadTest` [Spec-C] ✅ *(17 Tests)* | — | OK | — |
| P10 | isDead(): Geburt vorhanden + jung | — | `IsDeadTest` [Spec-C] ✅ *(17 Tests)* | — | OK | — |
| P11 | isDead(): Inferenz Eltern | — | `IsDeadTest` [Spec-C] ✅ *(17 Tests)* | — | OK | — |
| P12 | isDead(): Inferenz Ehepartner | — | `IsDeadTest` [Spec-C] ✅ *(17 Tests)* | — | OK | — |
| P13 | isDead(): Inferenz Kinder/Enkel | — | `IsDeadTest` [Spec-C] ✅ *(17 Tests)* + `IndividualFactsIntegrationTest` [CRAP] ✅ *(IndividualFactsService::relativeFacts → childFacts CRAP 1.980/cx=44, parentFacts CRAP 992/cx=31; via öffentliche API exercised)* | — | OK | — |
| P14 | Namen vertraulicher Personen | — | `PrivacyVisibilityTest` [Spec-C] ✅ *(22 Tests)* | `privacy-visibility.spec.ts` [Spec-C] ✅ *(5 Tests)* | OK | — |
| P15 | Vertrauliche Beziehungen | — | `PrivacyVisibilityTest` [Spec-C] ✅ *(22 Tests)* | — | OK | — |
| P16 | RESN none (Record) | — | `ResnPrivacyTest` [Spec-B] ✅ *(16 Tests)* | `privacy-resn.spec.ts` [Spec-C] ✅ *(7 Tests)* | OK | — |
| P17 | RESN privacy (Record) | — | `ResnPrivacyTest` [Spec-B] ✅ *(16 Tests)* | `privacy-resn.spec.ts` [Spec-C] ✅ *(7 Tests)* | OK | — |
| P18 | RESN confidential (Record) | — | `ResnPrivacyTest` [Spec-B] ✅ *(16 Tests)* | `privacy-resn.spec.ts` [Spec-C] ✅ *(7 Tests)* | OK | — |
| P19 | RESN auf Fakten-Ebene | — | `ResnPrivacyTest` [Spec-B] ✅ *(16 Tests)* | `privacy-resn.spec.ts` [Spec-C] ✅ *(7 Tests)* | OK | — |
| P20 | default_resn (Individuum) | — | `ResnPrivacyTest` [Spec-B] ✅ *(16 Tests)* | — | OK | — |
| P21 | default_resn (Faktentyp) | — | `ResnPrivacyTest` [Spec-B] ✅ *(16 Tests)* | — | OK | — |
| P22 | Relationship Privacy (Pfadlänge) | — | `RelationshipPrivacyTest` [Spec-C] ✅ *(5 Tests)* | `privacy-relationship.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| P23 | Relationship Privacy (kein XREF) | — | `RelationshipPrivacyTest` [Spec-C] ✅ *(5 Tests)* | — | OK | — |
| P24 | Privacy in Suchergebnissen | — | `PrivacySearchTest` [Spec-C] ✅ *(5 Tests)* | `privacy-search.spec.ts` [Spec-C] ✅ *(4 Tests)* | OK | — |
| P25 | Personenseite: Vertraulich-Platzhalter | — | — | `privacy-visibility.spec.ts` [Spec-C] ✅ *(5 Tests)* | OK | — |
| P26 | Charts: Vertrauliche Boxen | — | — | `privacy-charts.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| P27 | Bearbeiter: Datensatz bearbeiten | — | `AccessControlTest` [Spec-C] ✅ *(12 Tests)* | `access-control.spec.ts` [Spec-C] ✅ *(5 Tests)* | OK | — |
| P28 | Moderator: Änderungen akzeptieren | — | `AccessControlTest` [Spec-C] ✅ *(12 Tests)* | `access-control.spec.ts` [Spec-C] ✅ *(5 Tests)* | OK | — |
| P29 | RESN locked / Zugriffsverbot | — | `AccessControlTest` [Spec-C] ✅ *(12 Tests)* | `access-control.spec.ts` [Spec-C] ✅ *(5 Tests)* | OK | — |
| P30 | Datensätze zusammenführen | — | `MergeFactsActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 5 Tests: B1/EP2 not-found, B3/EP4 same-record, B4/EP5 tag-mismatch, B5/EP6 pending-deletion, EP1 change-DB-Assert)* + `MergeFactsIntegrationTest` ✅ *(CRAP-Smoke)* + `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke)* | `merge-records.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| P31 | Familienmitglieder bearbeiten | — | `ChangeFamilyMembersActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 5 Tests: EP1 replace-husband, EP2 remove-wife, EP3 add-child, EP4 remove-child, EP5 no-change)* + `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | — |
| P32 | Record-Ansicht und -Löschung | — | `DeleteRecordIntegrationTest` + `GedcomRecordPageIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 2+5 Tests: EP1 SOUR-Löschung, EP5 Familie-Kaskade; DataProvider INDI/FAM/SOUR/REPO→Redirect, EP2 _CUST→200+Link)* + `RequestHandlerBatchAIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | — |
| P33 | Stammbaum-Privacy-Einstellungen | — | `TreePrivacyActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 6 Tests: EP3/EP4 mismatched-arrays, EP5 tag+xref, EP6 tag-only, EP7 xref-only, EP8 beide-leer count-gleich, EP9 HIDE_LIVE_PEOPLE)* + `RequestHandlerBatchAIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | — |
| P34 | Stammbaum-Umnummerierung | — | `RenumberTreeActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 6 Tests: B2/EP1 keine-Duplikate, B3/EP2 INDI-Rename-Postcondition, B1/EP4 Pending-Edits-Guard, Page GET → 200 / leerer Baum → 200, sowie `malformed_xref_is_skipped_not_renamed` als FAILURE_PIN nach `wf_test-iteration_guide.md` §i.7: Upstream-`main` filtert ungültige XREF-Formate nicht, der Test bleibt rot bis Upstream-Fix)* + `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | Upstream-Bug (xref-Format-Guard) |
| P35 | CLI Benutzer-Verwaltung | — | `UserEditCommandIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 16 Tests: B1–B11 Guards, DataProvider B3/B4/B5, B13–B15 Edit-Felder)* | — | OK | — |
| P36 | CLI Einstellungs-Verwaltung | — | `CliSettingsBatchIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 17 Tests: --list/--delete-Konflikte, Delete nonexistent, Get nonexistent, same-value Warn, Update, EP11 Tree/User/UserTree not found)* | — | OK | — |
| P37 | HTTP Benutzer-Bearbeitung | — | `UserEditActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 7 Tests: B1 not-found, B5/B6 Duplikat-Email, B7/B8 Duplikat-Username, B4 Self-Edit-Admin, B3 Passwort, EP12 Path-Reset); `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke, 1 Test)* | `user-edit-admin.spec.ts` [Spec-C] ✅ *(4 Tests)* | OK | — |
| P38 | Account-Selbstverwaltung | — | `AccountSelfManagementIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: Edit GET 200, Update POST E-Mail, Delete admin-Guard, Delete non-admin gelöscht)* | `account-self-management.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| P39 | Authentifizierung-Aktionen | — | `LoginActionIntegrationTest` [Spec-C] ✅ *(6 Tests gegen Handler aus DI-Container: CLI-Kontext $_COOKIE=[]→302, gültige Credentials→Auth gesetzt, falsches Passwort, unbekannter User, unverifizierte E-Mail, nicht-freigegebener Account — jeweils erwarteter Status- und Flash-Pfad)* | — | OK | — |
| P40 | Änderungsverwaltung (HTTP-Handler) | — | `PendingChangesIntegrationTest` [Spec-C] ✅ *(25 Tests / 143 Assertions: AcceptRecord/RejectRecord Smoke-Routen, PendingChanges-Page GET, Accept/Reject-Delegationen an `PendingChangesService` mit aktualisierter 3-Arg-Signatur sowie zwei **DB-Postcondition-Tests** gegen Upstream-Regression `f24e5c62fe`: fully-pending INDI → nach Accept `wt_change.status='accepted'` + Reihe in `wt_individuals` vorhanden, nach Reject `wt_change.status='rejected'` ohne Kanonisierung. Hinzu Log-Handler: List/Page/Data/Delete/Download inkl. CSV-Escaping)* | `pending-changes.spec.ts` [Spec-C] ✅ *(6 Tests: 5 Rollen-/Smoke-Pfade + P40-Klickpfad „Moderator akzeptiert fully-pending Add-Child Change → Reload-Verschwund" als L4-Regression-Pin auf `f24e5c62fe`)* | OK | — |
| P41 | Datensatz-Zusammenführung (vollständig) | — | `MergeRecordsIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: Page GET valid/empty XREFs, Action POST matching INDIs→302)* | `merge-records.spec.ts` [Spec-C] ✅ *(1 Test)* | OK | — |
| P42 | CLI Benutzer-Listing | — | `UserListCommandIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 6 Tests, 34 Assertions)* | — | OK | — |
| P43 | Logout-Flow | — | `LogoutIntegrationTest` [Spec-C] ✅ *(3 Tests: angemeldeter Benutzer → 302 + Auth::id() null, Gast → 302 idempotent, Ajax → 204 + Auth::id() null)* | — | OK | — |
| P44 | Login Rate-Limiting | — | `LoginActionIntegrationTest` [Spec-C] ✅ *(2 Tests, FAILURE_PIN nach `wf_test-iteration_guide.md` §i.7: `per_user_rate_limit_fires_after_threshold` — 10 Fehlversuche pro Account → `HttpTooManyRequestsException` / HTTP 429; `site_wide_rate_limit_fires_for_unknown_users` — 20 Fehlversuche gegen unbekannte User → 429 (Schutz gegen User-Enumeration). Beide Tests bleiben rot, weil Upstream-`main` keine `RateLimitService`-Implementierung enthält — Soll-Verhalten verhalts-definitiv gepinnt)* | — | OK | Upstream-Bug |

<a id="sec"></a>

#### Sicherheit (SEC-H01–SEC-UTL01)

> **Hinweis (Phase 3.5):** Shell-Assertions (`security-filesystem-checks.sh`) sind keine
> eigene Teststufe. Sie laufen im Rahmen der Systemtests gegen die installierte
> Webtrees-Instanz und werden deshalb in der L4-Spalte geführt. Wo Shell-Skript und
> Playwright-Spec dieselbe Kontrollpunktfrage beantworten, werden beide in der L4-Zelle
> nebeneinander geführt (`spec.ts ✅ + shell-checks.sh ✅`).

| # | Feature | L2 — Komponententest (Upstream-`main`) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| SEC-H01 | `.htaccess` Existenz | — | — | `security-filesystem-checks.sh` [Smoke] ✅ *(6 Prüfungen gesamt)* | OK | — |
| SEC-H02 | `.htaccess` Inhalt | — | — | `security-filesystem-checks.sh` [Smoke] ✅ *(6 Prüfungen gesamt)* | OK | — |
| SEC-H03 | HTTP-Zugriff `data/` blockiert | — | — | `data-access.spec.ts` [Spec-C] ✅ *(4 Tests/8 Assertions)* | OK | — |
| SEC-H04 | HTTP-Zugriff `config.ini.php` blockiert | — | — | `data-access.spec.ts` [Spec-C] ✅ *(4 Tests/8 Assertions)* | OK | — |
| SEC-H05 | HTTP-Zugriff `data/media/` blockiert | — | — | `data-access.spec.ts` [Spec-C] ✅ *(4 Tests/8 Assertions)* | OK | — |
| SEC-H06 | URL-Encoding umgeht `.htaccess` nicht | — | — | `data-access.spec.ts` [Spec-C] ✅ *(4 Tests/8 Assertions)* | OK | — |
| SEC-D01 | `data/index.php` Existenz | — | — | `security-filesystem-checks.sh` [Smoke] ✅ *(6 Prüfungen gesamt)* | OK | — |
| SEC-D02 | `data/index.php` Redirect-Logik | — | — | `security-filesystem-checks.sh` [Smoke] ✅ *(6 Prüfungen gesamt)* | OK | — |
| SEC-C01 | Config PHP-Guard | — | — | `security-filesystem-checks.sh` [Smoke] ✅ *(6 Prüfungen gesamt)* | OK | — |
| SEC-C02 | Config DB-Credentials | — | — | `security-filesystem-checks.sh` [Smoke] ✅ *(6 Prüfungen gesamt)* | OK | — |
| SEC-C03 | Config Datei-Permissions | — | — | `security-filesystem-checks.sh` [Smoke] ⚠ *(6 Prüfungen gesamt)* | OK | Upstream-Bug |
| SEC-M01 | Direkter Media-Zugriff blockiert | — | — | `media-access.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| SEC-M02 | Media-Route ohne Auth | — | — | `media-access.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| SEC-M03 | Media-Route mit Auth | — | — | `media-access.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| SEC-PUB01 | `public/index.php` Existenz | — | — | `security-filesystem-checks.sh` [Smoke] ✅ *(6 Prüfungen gesamt)* | OK | — |
| SEC-PUB02 | `public/index.php` keine PHP-Execution | — | — | `public-access.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| SEC-PUB03 | Kein Directory Listing `/public/` | — | — | `public-access.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| SEC-PUB04 | Path-Traversal blockiert | — | — | `public-access.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| SEC-W01 | Wizard nach Setup gesperrt | — | — | `setup-lock.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| SEC-WZ01 | Wizard erscheint bei Erstaufruf | — | — | `wizard-setup.spec.ts` [Spec-C] ✅ *(4 Tests)* | OK | — |
| SEC-WZ02 | Wizard prüft Schreibrechte | — | — | `wizard-setup.spec.ts` [Spec-C] ✅ *(4 Tests)* | OK | — |
| SEC-WZ03 | Wizard erzeugt `config.ini.php` | — | — | `wizard-setup.spec.ts` [Spec-C] ✅ *(4 Tests)* + `security-filesystem-checks.sh` ✅ | OK | — |
| SEC-WZ04 | Wizard sperrt sich selbst | — | — | `wizard-setup.spec.ts` [Spec-C] ✅ *(4 Tests)* | OK | — |
| SEC-WZ05 | Wizard Reinstall-Pfad validiert `wtpass` | — | `SetupWizardReinstallIntegrationTest` [Spec-C] ✅ *(SetupWizard::createConfigFile Reinstall-Branch: validierter `$data['wtpass']` wird verwendet, nicht der rohe Superglobal — verhindert Klartext-Korruption beim Reinstall)* | — | OK | — |
| SEC-HDR01 | `X-Content-Type-Options` | — | — | `security-headers.spec.ts` [Spec-B] ✅ *(4 Tests)* | OK | — |
| SEC-HDR02 | `X-Frame-Options` | — | — | `security-headers.spec.ts` [Spec-B] ✅ *(4 Tests)* | OK | — |
| SEC-HDR03 | `Referrer-Policy` | — | — | `security-headers.spec.ts` [Spec-B] ✅ *(4 Tests)* | OK | — |
| SEC-HDR04 | Server-Banner | — | — | `security-headers.spec.ts` [Spec-C] ⚠ *(4 Tests)* | OK | Deployment-Empfehlung |
| SEC-BOT01 | UA-basierte Bot-Blockierung | — | `BadBotBlockerIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 15 Tests: BAD_ROBOTS-DataProvider×5 + WP-Pfade-DataProvider×4 + Cookie-Heuristik EP8/EP9 + 4 Basis; DNS ausgeklammert)* | — | OK | — |
| SEC-UTL01 | Web-Assets & Utility-Endpoints | — | `UtilityEndpointsIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 10 Tests)* | — | OK | — |

<a id="e"></a>

#### Datenpflege / Erfassung (E01–E09)

| # | Feature | L2 — Komponententest (Upstream-`main`) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| E01 | Person/Familie anlegen & verknüpfen | — | `AddRelationIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 6 Tests: AddChildToIndividualPage GET→200, Action POST→302, DataProvider AddParent/AddSpouseToIndi/AddChild/AddSpouseToFam→200)* | `person-family-create.spec.ts` [Spec-C] ✅ *(4 Tests × 5 Themes)* | OK | — |
| E02 | Fakten bearbeiten | — | `EditFactIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: EditFactPage unknown fact_id→redirect, DeleteFact unknown fact_id→204, AddNewFact GET→200)* + `CopyFactIntegrationTest`, `PasteFactIntegrationTest`, `EditRecordIntegrationTest` [Spec-C] ✅ *(Clipboard-Fluss: Copy → pasteFact-Delegation + 302-Redirect; EditRecord: editLinesToGedcom-Aufruf + 302)* | `fact-edit.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| E03 | Rohdaten-Edit (Raw GEDCOM) | — | `EditRawGedcomIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: EditRawFactPage unknown fact_id→redirect, EditRawRecordPage GET→200, EditRawFactAction unknown fact_id→redirect)* | `raw-gedcom-edit.spec.ts` [Spec-C] ✅ *(2 Tests)* | OK | — |
| E04 | Nebenrecords anlegen | — | `CreateSubrecordIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: CreateNoteModal GET→200, CreateNoteAction POST→JSON-XREF, CreateSourceModal GET→200, CreateRepositoryModal GET→200)* | `subrecord-create.spec.ts` [Spec-C] ✅ *(4 Tests × 5 Themes)* | OK | — |
| E05 | Medienobjekte anlegen & verknüpfen | — | `MediaObjectIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: CreateMediaObjectModal GET→200, LinkMediaToRecordAction POST→302, LinkMediaToIndividualModal GET→200)* | `media-object.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| E06 | Sortierung (Reorder) | — | `ReorderIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: ReorderChildren/Names/Families GET→200, unknown FAM→404)* | `reorder.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| E07 | Mediendatei-Download & Thumbnail | — | `MediaFileDeliveryIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: Thumbnail unknown XREF→200, Thumbnail known XREF no fact_id→200, Download unknown XREF→HttpNotFoundException)* | — | OK | — |
| E08 | TomSelect & AutoComplete | — | `TomSelectIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 5 Tests: TomSelectIndividual leer/XREF/Name, TomSelectSource leer, AutoCompleteFolder)* | `tomselect-autocomplete.spec.ts` [Spec-C] ✅ *(4 Tests × 5 Themes)* | OK | — |
| E09 | Sichere Auslieferung gefährlicher Mime-Types | — | `MediaFileDeliveryIntegrationTest` [Spec-C] ✅ *(SVG-XSS Härtung: SVG→non-SVG Content-Type-Override bzw. Replacement-Image-Response; Replacement-Image setzt restriktive Content-Security-Policy. CSP wird **semantisch** geprüft (script-src bzw. default-src-Fallback nach CSP-Spec via `cspBlocksScriptExecution`-Helper) statt per String-Match auf Literalwerte; deckt H1–H5 Bypass-Vektoren ab (Mixed-Case `<Script>`, `onload`-Handler, `javascript:`-URLs, lowercase `<script>`, legitime SVGs))* | — | OK | — |

<a id="a"></a>

#### Administration (A01–A19)

| # | Feature | L2 — Komponententest (Upstream-`main`) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| A01 | Stammbaum-Management | — | `TreeManagementIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: CreateTree Duplikat→302, CreateTree Neu→DB, DeleteTree→204, ManageTrees GET→200)* | `tree-management.spec.ts` [Spec-C] ✅ *(4 Tests)* | OK | — |
| A02 | Stammbaum-Import (HTTP-Formular) | — | `ImportGedcomActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 4 Tests: UPLOAD_ERR_NO_FILE→302, UPLOAD_ERR_PARTIAL→Exception, leerer server_file→302, ImportGedcomPage GET→200)* | — | OK | — |
| A03 | Stammbaum-Export (HTTP-Formular) | — | `ExportGedcomIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: Client format=gedcom→attachment, format=zip→application/zip, ExportGedcomServer→302, ExportGedcomPage GET→200)* | — | OK | — |
| A04 | Stammbaum-Präferenzen | — | `TreePreferencesIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: Page GET→200, Action POST→302+preference saved, Action POST→meta_description saved)* | `tree-preferences.spec.ts` [Spec-C] ✅ *(2 Tests)* | OK | — |
| A05 | Modul-Konfiguration | — | `ModuleConfigIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 7 Tests: ModulesAllPage GET→200, ModulesAllAction POST→302, DataProvider Analytics/Blocks/Charts/Menus/Reports→200)* | `module-configuration.spec.ts` [Spec-C] ✅ *(6 Tests)* | OK | — |
| A06 | Site-Präferenzen | — | `SitePreferencesIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: Page GET→200, Action POST valid→302, POST saves LANGUAGE, POST invalid directory→302)* + `SiteRegistrationIntegrationTest` [Spec-C] ✅ *(Site-weite Registrierungs-Prefs: Welcome-Modus, Welcome-Text, Modul-Aktivierung, Caution-Hinweis)* | — | OK | — |
| A07 | Benutzerverwaltung Admin | — | `UserAdminIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: UserListPage GET→200, mit filter, UsersCleanupPage GET→200)* | `user-admin.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| A08 | Medienverwaltung Admin | — | `AdminMediaManagementIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 5 Tests, 17 Assertions: ManageMediaPage render, invalid path, path traversal security, nonexistent records, FixLevel0MediaPage render)* | `media-admin.spec.ts` [Admin-Only] ✅ *(6 Tests)* | OK | — |
| A09 | Datenpflege-Werkzeuge | — | `DataMaintenanceIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: FindDuplicateRecords GET→200, DataFixPage leer→200, DataFixPage fix-place-names→200, DataFixChoose GET→200)* | — | OK | — |
| A10 | Protokolle & Monitoring | — | `LogsMonitoringIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: PendingChangesLogPage GET→200, SiteLogsDownload→CSV, Disposition attachment, PhpInformation→200)* | — | OK | — |
| A11 | System & Upgrade | — | `SystemAdminIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 5 Tests: Masquerade not-found→HttpNotFoundException, self→204, other→204+Auth, BroadcastPage GET→200, EmailPreferencesPage GET→200)* | — | OK | — |
| A12 | CLI Wartungsmodus aktivieren | — | `SiteOfflineCommandIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 4 Tests: ohne Message→SUCCESS+Datei, Custom-Message→gespeichert, Spezialzeichen→korrekt, Überschreiben→SUCCESS)* | — | OK | — |
| A13 | CLI Wartungsmodus deaktivieren | — | `SiteOnlineCommandIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 3 Tests: Offline→Online+Datei-gelöscht, bereits-online→idempotent, Sequenz offline→online)* | — | OK | — |
| A14 | CLI initialer Config-Setup | — | `ConfigIniCommandIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 5 Tests: gültige Credentials→SUCCESS+Config-Datei, leere base-url→WARNING, Trailing-Slash→getrimmt, dbverify-Flag→'1', ungültige Credentials→FAILURE)* | — | OK | — |
| A15 | CLI Übersetzung kompilieren | — | `CompilePoFilesCommandIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 2 Tests, 6 Assertions)* | — | OK | — |
| A16 | CLI Baum-Listing | — | `TreeListCommandIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 6 Tests, 45 Assertions)* | — | OK | — |
| A17 | Default-Block-Konfiguration TreePage | — | `TreePageDefaultEditIntegrationTest`, `TreePageDefaultUpdateIntegrationTest` [Spec-C] ✅ *(Edit: Default-Block-Übersicht via HomePageService; Update: Persistenz mit tree_id = -1 → Redirect zu Control-Panel)* | — | OK | — |
| A18 | Default-Block-Konfiguration UserPage | — | `UserPageDefaultEditIntegrationTest`, `UserPageDefaultUpdateIntegrationTest` [Spec-C] ✅ *(Edit: Default-User-Block-Übersicht; Update: Persistenz mit user_id = -1 → Redirect zu Control-Panel)* | — | OK | — |
| A19 | Modul-Action Runtime-Dispatch | — | `ModuleActionIntegrationTest` [Spec-C] ✅ *(DataProvider: Admin-Gate-Enforcement gegen Case-Bypass — Guest/nicht-Admin auf admin-Methoden wird auch bei beliebiger `<action>`-Casing per HttpAccessDeniedException oder HTTP 403 abgewiesen)* | — | OK | — |

<a id="k"></a>

#### Kommunikation (K01–K02)

| # | Feature | L2 — Komponententest (Upstream-`main`) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| K01 | Kontaktformular | — | `ContactFormIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 11 Tests, 34 Assertions)* | `contact-form.spec.ts` [Spec-C] ✅ *(3 Tests × 5 Themes)* | OK | — |
| K02 | Benutzer-Nachrichten | — | `UserMessageIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 9 Tests, 31 Assertions)* | `user-messages.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |

<a id="u"></a>

#### Querschnitts-Utilities (U01–U02)

| # | Feature | L2 — Komponententest (Upstream-`main`) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| U01 | Validator (root-Paket) | `ValidatorTest` [EP] ✅ *(24 Tests/52 Assertions, EP-complete)* | `ValidatorIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 15 Tests: float() EP1–EP5+BV+Inv+Miss, __construct UTF-8 key/value/ASCII, integer() neg-String, array() non-array-throw)* | — | OK | — |
| U02 | CountryService (`Statistics/Service/`) | — | — | — | SKIP | Deprecated (`@deprecated`, Entfernung in webtrees 2.3; kein Test geplant) |

<a id="m"></a>

#### Middleware (M01–M29)

> **Hinweis:** Die M-Domäne wurde in Plan-Phase 5.1 neu angelegt. Die L2-Zellen spiegeln den
> Stand Upstream-`main`. Aktuelles L2-Inventar als CSV:
> [`coverage-runs/2026-05-24_gap-analyse_l2.csv`](coverage-runs/2026-05-24_gap-analyse_l2.csv)
> (Einträge unter `Http/Middleware/`). Der historische Vergleichs-Snapshot eines anderen
> Branches liegt unter
> [`coverage-runs/historical/2026-04-11_gap-analyse-fork_l2.csv`](coverage-runs/historical/2026-04-11_gap-analyse-fork_l2.csv)
> (Einträge `app/Http/Middleware/*`). L3/L4-Zellen sind überwiegend leer, weil die vorhandenen
> Integrationstests (z. B. `BadBotBlockerIntegrationTest` unter SEC-BOT01,
> `security-headers.spec.ts` unter SEC-HDR01–HDR04, `AccessControlTest` / `access-control.spec.ts` unter P27–P29) primär unter anderen
> Feature-IDs geführt werden und hier als Querverweis ausgewiesen sind. Viele L2-Einträge sind im Upstream nur als **Stub** vorhanden
> (`testClass` + `assertTrue(class_exists(...))`) — diese Zellen bleiben `—` ohne Siegel, weil sie die Regel „Stub → Smoke nur bei ≥ 3
> Assertions" nicht erfüllen.

| # | Feature | L2 — Komponententest (Upstream-`main`) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| M01 | Rollenbasierte Zugriffskontrolle | `AuthAdministratorTest` + `AuthEditorTest` + `AuthManagerTest` + `AuthMemberTest` + `AuthModeratorTest` [Spec-C] ✅ *(5× Substantial, je 3 Tests/5 Assertions; `AuthLoggedInTest` Stub; `AuthNotRobot` ohne L2-Test)* | `AccessControlTest` [EP] ✅ *(Querverweis zu P27–P29)* | `access-control.spec.ts` [Spec-C] ✅ *(Querverweis zu P27–P29)* | OK | Cluster (7 Klassen); Detail unter P27–P29 |
| M02 | Bad-Bot-Blocker (UA-basiert) | `BadBotBlockerTest` [Spec-C] ✅ *(Substantial, 4 Tests/6 Assertions)* | `BadBotBlockerIntegrationTest` [EP] ✅ *(Querverweis zu SEC-BOT01)* | — | OK | Detail unter SEC-BOT01 |
| M03 | Client-IP-Ermittlung (Proxy-Trust) | — *(`ClientIpTest` Stub, 1 Assertion)* | `ClientIpMiddlewareIntegrationTest` [Spec-C] ✅ *(5 Tests, 16 Assertions)* | — | OK | — |
| M04 | CSRF-Token-Validierung | `CheckCsrfTest` [Smoke] ✅ *(Stub, 3 Assertions → Smoke-Schwelle)* | — | — | OK | — |
| M05 | Security-Headers (OWASP) | `SecurityHeadersTest` [Spec-C] ✅ *(Substantial, 3 Tests/9 Assertions)* | `SecurityHeadersMiddlewareIntegrationTest` [Spec-C] ✅ *(Permissions-Policy, Referrer-Policy, X-Content-Type-Options, X-Frame-Options, X-XSS-Protection + HSTS bei HTTPS-Base-URL)* | `security-headers.spec.ts` [Spec-B] ✅ *(Querverweis zu SEC-HDR01–HDR04)* | OK | Detail unter SEC-HDR01–HDR04 |
| M06 | Session-Initialisierung | — *(`UseSessionTest` Stub, 1 Assertion)* | `UseSessionMiddlewareIntegrationTest` [Spec-C] ✅ *(5 Tests)* | — | OK | — |
| M07 | Datenbank-Verbindung | — *(`UseDatabaseTest` Stub, 1 Assertion)* | `UseDatabaseMiddlewareIntegrationTest` [EP] ✅ *(3 Tests)* | — | OK | — |
| M08 | Datenbank-Schema-Migration | — *(`UpdateDatabaseSchemaTest` Stub, 1 Assertion)* | `UpdateDatabaseSchemaMiddlewareIntegrationTest` [Smoke] ✅ *(2 Tests)* | — | OK | — |
| M09 | Base-URL-Ermittlung | — *(`BaseUrlTest` Stub, 1 Assertion)* | `BaseUrlMiddlewareIntegrationTest` [Spec-C] ✅ *(4 Tests, 24 Assertions)* | — | OK | — |
| M10 | Routen-Laden | — *(`LoadRoutesTest` Stub, 1 Assertion)* | `LoadRoutesMiddlewareIntegrationTest` [Smoke] ✅ *(3 Tests, 10 Assertions)* | — | OK | — |
| M11 | URL-Routing | — *(`RouterTest` Stub, 1 Assertion)* | `RouterMiddlewareIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 7 Tests: rewrite_urls→308-Redirect, Method-Not-Allowed→405+Allow-Header, Not-Acceptable→406, Fallback-Handler, Route-Match→Handler, Tree-Auflösung aus DB, rewrite_urls=false→URI-Update)* | — | OK | — |
| M12 | Request-Handler-Dispatch | — *(`RequestHandlerTest` Stub, 1 Assertion)* | `RequestHandlerMiddlewareIntegrationTest` [Spec-C] ✅ *(2 Tests)* | — | OK | — |
| M13 | Sprachauswahl | — *(`UseLanguageTest` Stub, 1 Assertion)* | `UseLanguageMiddlewareIntegrationTest` [EP] ✅ *(4 Tests, 15 Assertions)* | — | OK | — |
| M14 | Theme-Auswahl | — *(`UseThemeTest` Stub, 1 Assertion)* | `UseThemeMiddlewareIntegrationTest` [EP] ✅ *(4 Tests, 17 Assertions)* | — | OK | — |
| M15 | PHP-Error-zu-Exception-Konvertierung | — *(kein L2-Test im Fork)* | `ErrorHandlerMiddlewareIntegrationTest` [Spec-C] ✅ *(4 Tests, 16 Assertions)* | — | OK | — |
| M16 | Exception-Handling & Error-Page-Rendering | — *(`HandleExceptionsTest` Stub, 1 Assertion)* | `HandleExceptionsMiddlewareIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 7 Tests, 22 Assertions: HttpException→status code, AJAX GET→200, FilesystemException→500, Throwable→500, error messages)* | `error-handling.spec.ts` [Spec-C] ✅ *(5 Tests)* | OK | — |
| M17 | Debug-Logger (SQL/Perf) | — *(kein L2-Test im Fork)* | `DebugLoggerMiddlewareIntegrationTest` [EP] ✅ *(4 Tests, 19 Assertions)* | — | OK | — |
| M18 | Housekeeping (Thumbnails/Logs/Temp) | — *(`DoHousekeepingTest` Stub, 1 Assertion)* | `DoHousekeepingMiddlewareIntegrationTest` [Spec-C] ✅ *(3 Tests, 11 Assertions)* | — | OK | — |
| M19 | Response-Kompression | — *(`CompressResponseTest` Stub, 1 Assertion)* | `CompressResponseMiddlewareIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 9 Tests, 34 Assertions: gzip/deflate compression, zlib check, MIME type filtering, already-encoded skip, text/* rule)* | — | OK | — |
| M20 | Content-Length-Header | — *(`ContentLengthTest` Stub, 1 Assertion)* | `ContentLengthMiddlewareIntegrationTest` [Smoke] ✅ *(4 Tests)* | — | OK | — |
| M21 | Config-Ini-Lesen | — *(`ReadConfigIniTest` Stub, 1 Assertion)* | `ReadConfigIniMiddlewareIntegrationTest` [Spec-C] ✅ *(3 Tests)* | — | OK | — |
| M22 | Wartungsmodus | `CheckForMaintenanceModeTest` [Smoke] ✅ *(Smoke, 2 Tests/3 Assertions)* | — | — | OK | — |
| M23 | Update-Prüfung | — *(`CheckForNewVersionTest` Stub, 1 Assertion)* | `CheckForNewVersionMiddlewareIntegrationTest` [Smoke] ✅ *(4 Tests, 15 Assertions)* | — | OK | — |
| M24 | Public-Files-Serving | `PublicFilesTest` [Spec-C] ✅ *(Substantial, 4 Tests/4 Assertions)* | `PublicFilesMiddlewareIntegrationTest` [Spec-C] ✅ *(Delegation an inneren Handler für Pfade außerhalb `/public/`, Path-Traversal-Marker, nicht-existierende `/public/*`-Dateien)* | — | OK | — |
| M25 | GEDCOM-Tag-Registrierung | — *(`RegisterGedcomTagsTest` Stub, 1 Assertion)* | `RegisterGedcomTagsMiddlewareIntegrationTest` [Smoke] ✅ *(2 Tests, 10 Assertions)* | — | OK | — |
| M26 | Modul-Bootstrap | — *(`BootModulesTest` Stub, 2 Assertions)* | `BootModulesMiddlewareIntegrationTest` [Smoke] ✅ *(2 Tests, 9 Assertions)* | — | OK | — |
| M27 | DB-Transaktion mit Retry | — *(`UseTransactionTest` Stub, 1 Assertion)* | `UseTransactionMiddlewareIntegrationTest` [Spec-C] ✅ *(3 Tests)* | — | OK | — |
| M28 | Response-Emittierung | — *(`EmitResponseTest` Stub, 1 Assertion)* | `EmitResponseMiddlewareIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 5 Tests, 22 Assertions: body emission, cache-control add/preserve, FastCGI check, empty body)* | — | OK | — |
| M29 | 404-Handler | — | `NotFoundIntegrationTest` [Spec-C] ✅ *(3 Tests: Robot-Request → 404, GET ohne Robot-Attribut → 302 HomePage, nicht-GET ohne Robot → HttpNotFoundException)* | — | OK | — |

#### Zusammenfassung Abdeckung

**Stand 2026-05-24:** 216 abgedeckt / 2 nicht abgedeckt / 1 SKIP / 219 gesamt.

Nicht abgedeckte IDs: G05 (Date-Parsing direkt — `GedcomServiceIntegrationTest` deckt nur Utility-CRAP ab), G06 (Name-Extraktion). SKIP: U02 (deprecated, Entfernung in webtrees 2.3).

Coverage-Kennzahlen siehe [`tp_ratchet_spec.md`](tp_ratchet_spec.md#ist-stand-stand-2026-05-24).

**Test-Inhalts-Updates 2026-05-24 (Cluster-A/B nach Upstream-Refresh):**
- **P39** `LoginActionIntegrationTest`: 1 Smoke-Test → 6 Spec-C-Tests (DI-Container-Auflösung, Credentials-Pfade, Verifizierungs-/Freigabe-Pfade).
- **P40** `PendingChangesIntegrationTest`: 3 → 25 Tests (143 Assertions); zwei neue DB-Postcondition-Tests pinnen Upstream-Regression `f24e5c62fe` (fully-pending INDI Accept/Reject).
- **P40** `pending-changes.spec.ts`: 4 → 6 Tests; neuer L4-Klick-Pfad Editor → Moderator Accept → Reload-Verschwund pinnt denselben Regressions-Fall im UI.
- **P44** `LoginActionIntegrationTest` Rate-Limit-Block: alleinstehender Test → 2 verhalts-definitive FAILURE_PINs (per-user + site-wide gegen User-Enumeration), bleiben rot bis Upstream-Implementierung.
- **P34** `RenumberTreeActionIntegrationTest`: malformed-XREF-Guard von Error → FAILURE_PIN nach `wf_test-iteration_guide.md` §i.7.
- **E09** `MediaFileDeliveryIntegrationTest`: CSP-Check von String-Literal-Match auf semantische Parsing-Helfer (`cspBlocksScriptExecution`, script-src/default-src nach CSP-Spec); Methodennamen ohne SEC-AUDIT-Präfix.

Zuwachs durch Feature-Matrix-Konsolidierung (2026-05-24): +10 neue Feature-IDs
(S25 HEAD-Record-Page, S51 Sprachauswahl-Handler, P43 Logout-Flow, P44 Login Rate-Limiting,
A17 Default-Blocks TreePage, A18 Default-Blocks UserPage, A19 Modul-Action Runtime-Dispatch,
M29 404-Handler, SEC-WZ05 SetupWizard Reinstall-Pfad, E09 Sichere Auslieferung gefährlicher
Mime-Types). L3-Zelle erstmalig befüllt für 28 bestehende IDs (S05/S07/S08/S09/S13
Such-Actions+Pages, S23/S24/S26–S30 Page-Display-Handler, S31 Kalender, S32 LoginPage,
S33 UserRegistration, S34 PasswordReset, S35 UserPage+Edit+Update, S38/S39 Such-Page-Forms,
S40 HomePage/TreePage, S41 StatisticsChart-CRAP, S46 PageBlock+TreePage/UserPage Edit/Update,
G05 GedcomService-Utility-CRAP, G30 AddMediaFileAction, E02 Copy/Paste/EditRecord,
A06 SiteRegistration, M05 SecurityHeaders, M24 PublicFiles); G27 um Extension-Blocklist
(L0-strongest-mitigation) und P34 um xref-Format-Guard erweitert; P08–P13 erhalten
`IndividualFactsIntegrationTest` als CRAP-Anteil; S44/S45 ergänzt um
`RightToLeftSupportIntegrationTest`/`RomanNumeralsIntegrationTest`-CRAP.

Historische Snapshots liegen unter [`coverage-runs/`](coverage-runs/) (jüngster zuerst).
