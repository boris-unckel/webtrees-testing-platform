<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Abdeckungsmatrix — Testabdeckung nach Feature-Matrix-ID

Dieses Dokument bildet die Testabdeckung pro Feature-Matrix-ID ab. Jedes Feature wird auf Upstream-Tests (SQLite), eigene Infrastruktur-Tests (MySQL-Integration / Playwright-E2E) und den Abdeckungsstatus abgebildet.

**Querverweise:**

- [Feature-Matrizen](tds_conditions_ref.md)
- [Überdeckungsstrategie](tp_ratchet_spec.md)
- [Testentwurfsverfahren](tds_methodik_spec.md)

**Aktueller Stand (2026-04-12):** **173 abgedeckt** (172 spezifikationsbasiert + 1
strukturbasiert), **35 nicht abgedeckt**, **1 SKIP** (U02 deprecated) / **209 Features
gesamt** (Vorgänger-Snapshot 170; Zuwachs: +28 Middleware M01–M28, +7 CLI G31/P42/A12–A16;
Differenz zu 209 durch Korrektur historischer Zählfehler in G/SEC-Domäne).
Historischer Snapshot (vor M-/CLI-Erweiterung):
[`2026-04-11_abdeckung-snapshot.md`](coverage-runs/2026-04-11_abdeckung-snapshot.md)
(165/5/170). Fork-basierter Gap-Analyse-Snapshot:
[`2026-04-11_gap-analyse-fork.md`](coverage-runs/2026-04-11_gap-analyse-fork.md) (L2/L3/L4-Kennzahlen).

---

## Teststufen und Layer — Nomenklatur

Dieses Projekt verwendet zwei Bezugssysteme, die sich gegenseitig ergänzen:

| ISTQB-Teststufe               | Layer (Makefile/Verzeichnis)         | Pfad                    |
|-------------------------------|--------------------------------------|-------------------------|
| —                             | L1 — Statische Analyse               | `layer1-static/`        |
| Teststufe 1 — Komponententest | L2 — `make test-unit`                | `layer2-unit/` (Upstream-Fork-Testbasis) |
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
| `L2:` | `tests/app/` und `tests/feature/` | `webtrees-upstream/webtrees` @ `port-layer2-test-doubles` (Upstream-Fork, read-only) |
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

**Einstufungs-Quelle:** Die Hybrid-Heuristik V2 aus
[`coverage-runs/2026-04-11_gap-analyse-fork.md`](coverage-runs/2026-04-11_gap-analyse-fork.md)
ist die Referenz für die Einstufung — die darin erhobenen Stub/Smoke/Substantial/EP-Zahlen
werden pro Feature-ID auf die obigen Siegel abgebildet (`Stub → Smoke` nur wenn 3+
Assertions, sonst wird das Feature als nicht abgedeckt notiert; `Substantial → Spec-C`;
`EP-complete → EP`; externe Spec vorhanden → `Spec-B`).

---

## Domänen-Navigation

[G](#g) · [S](#s) · [P](#p) · [SEC](#sec) · [E](#e) · [A](#a) · [K](#k) · [U](#u) · [M](#m)

---

### Abdeckungsmatrix: Feature-Matrix → Testabdeckung

<a id="g"></a>

#### GEDCOM Import/Export (G01–G31)

> **Detailkonzept:** [`port-implementation/03_batches/batch_G_gedcom.md`](../port-implementation/03_batches/batch_G_gedcom.md) — 9 portierbare Handler-Tests + 2 Service-Tests, 18 L2-ausgeschlossene Features (DB-Import/Export).
>
> **Befund (Phase 6.2.1):** `GedcomImportServiceTest.php` ist im Fork (`port-layer2-test-doubles` @ `841616f4b5`) nur als **Stub** vorhanden (1 Methode, 1 Assertion — `assertTrue(class_exists(...))`). Die ehemals mit `[Spec-B]` gekennzeichneten L2-Zellen für G01–G12 wurden deshalb in der Migration auf `—` zurückgesetzt; die substanzielle Abdeckung erfolgt ausschließlich über `GedcomImportTest` (L3, 28 Methoden). Konsequenz: L2-Coverage für G-Domäne sinkt; Gesamt-Features ändern sich nicht (nur Spalten-Inhalt).

| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| G01 | Record-Import (INDI) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(28 Tests)* | — | OK | — |
| G02 | Record-Import (FAM) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` + `RelationshipDbTest` [Spec-B] ✅ *(28+3 Tests)* | — | OK | — |
| G03 | Record-Import (Nebenrecords) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(28 Tests)* | — | OK | — |
| G04 | Place-Hierarchie | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `GedcomImportTest` [Spec-B] ✅ *(28 Tests)* | — | OK | — |
| G05 | Date-Parsing | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | — | — | — | L2-Stub ungenügend; L3-Gap |
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
| G27 | Mediendatei-Upload URL | — | `MediaFileServiceUploadIntegrationTest` [CRAP] ✅ *(CRAP-Analyse, 2 Tests)* | — | OK | — |
| G28 | OBJE-Metadaten bearbeiten | — | `EditMediaFileIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 2 Tests: Fact-not-found-Redirect, Happy Path DB-Postcondition change-Tabelle)* | — | OK | — |
| G29 | GEDCOM-Bearbeitungsservice | — | `GedcomEditServiceIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 9 Tests: editLinesToGedcom EP Normal/CONT/Leer/Sub-Level, insertMissingLevels EP Expansion/Tiefe/Tags)* | — | OK | — |
| G30 | Mediendatei-Upload (HTTP-Formular) | — | `UploadMediaActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 3 Tests: UPLOAD_ERR_NO_FILE→302, UPLOAD_ERR_PARTIAL→FileUploadException, gefährliche Extension→FlashMessage+302)* | — | OK | — |
| G31 | GEDCOM-Import via CLI | — | — | — | — | Nicht abgedeckt; Plan-Iteration 2 (analog G25/G26 als `TreeImportCommandIntegrationTest`) |

> **Fußnote (L2-Spalte):** Der in der L2-Spalte angezeigte Testumfang entspricht dem Stand
> des Branch [`port-layer2-test-doubles`](../webtrees-upstream/webtrees) im Upstream-Fork
> (Commit `59548226a4`, 2026-04-12) und ist im Upstream-`main` noch nicht akzeptiert.
> Einzelheiten zur L2-Klassifikation siehe Snapshot
> [`coverage-runs/2026-04-11_gap-analyse-fork.md`](coverage-runs/2026-04-11_gap-analyse-fork.md).

<a id="s"></a>

#### Suche und Navigation (S01–S53)

> **Detailkonzept:** [`port-implementation/03_batches/batch_S_search_navigation.md`](../port-implementation/03_batches/batch_S_search_navigation.md)
>
> **Befund (Phase 6.2.2):** Drei L2-Stub-Korrekturen: (1) `SearchServiceTest` ist im Fork nur ein Stub (1 Methode/7 Assertions) — von `[Spec-C]` auf `[Smoke]` herabgestuft (S01–S04, S10, S12). (2) `GedcomImportServiceTest` ist Stub (1 Methode/1 Assertion) — L2-Zellen S07/S08 auf `—` zurückgesetzt (L3 deckt weiterhin ab). (3) `RelationshipServiceTest` ist Stub (1 Methode/1 Assertion) — L2-Zelle S16 auf `—` zurückgesetzt (L3 deckt weiterhin ab).

| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| S01 | Allg. Suche (Personen) | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions)* | — | — | OK | — |
| S02 | Allg. Suche (Familien) | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions)* | — | — | OK | — |
| S03 | Allg. Suche (SOUR, NOTE, REPO) | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions; Sources, Repos, Submitters)* | — | — | OK | — |
| S04 | Query-Parsing | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions; Multi-word, non-matching)* | — | — | OK | — |
| S05 | Erweiterte Suche (Felder) | — | `SearchIntegrationTest` [Spec-C] ✅ *(5 Tests: Name, Nachname, Sterbedatum, Multi-Feld, leere Felder)* | — | OK | — |
| S06 | Erweiterte Suche (Datum) | — | `SearchIntegrationTest` [Spec-C] ✅ *(3 Tests: ±0, ±5, ±20 Jahre)* | — | OK | — |
| S07 | Phonetische Suche (Russell) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `SearchIntegrationTest` [Smoke] ✅ *(2 Tests: Treffer + kein Treffer)* | — | OK | — |
| S08 | Phonetische Suche (DM) | — *(`GedcomImportServiceTest` nur Stub 1 Test)* | `SearchIntegrationTest` [Smoke] ✅ *(2 Tests: Treffer + kein Treffer)* | — | OK | — |
| S09 | Quick-Search (XREF) | — | — | `navigation.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S10 | Paginierung | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions)* | `SearchIntegrationTest` [Spec-C] ✅ *(3 Tests: Limit, Offset, Offset+Limit)* | — | OK | — |
| S11 | Cross-Tree-Suche | — | `SearchIntegrationTest` [Smoke] ✅ *(2 Tests: Ergebnisse aus beiden Bäumen, Tree-spezifischer Name)* | — | OK | — |
| S12 | Zugriffskontrolle (Suche) | `SearchServiceTest` [Smoke] ✅ *(1 Test/7 Assertions; Guest vs Admin)* | — | — | OK | — |
| S13 | Search-and-Replace | — | — | `search-replace.spec.ts` [Spec-C] ✅ *(3 Tests; 2×5 Themes + 1 Visitor)* | OK | — |
| S14 | Chart: Pedigree | `PedigreeChartModuleTest` [Spec-C] ✅ *(4 Tests)* | — | `pedigree.spec.ts` [Spec-C] ✅ *(2 Tests × 5 Themes)* | OK | — |
| S15 | Chart: Nachkommen | `DescendancyChartModuleTest` [Spec-C] ✅ *(4 Tests)* | — | — | OK | — |
| S16 | Chart: Beziehungsfinder | — *(`RelationshipServiceTest` nur Stub 1 Test)* | `RelationshipServiceIntegrationTest` [Spec-C] ✅ *(16 Tests: direkte Pfade, Onkel/Tante, Großeltern, Ehepartner)* | — | OK | — |
| S17 | Chart: Fächerchart | `FanChartModuleTest` [Spec-C] ✅ *(4 Tests)* | — | — | OK | — |
| S18 | Chart: alle 13 Typen (Smoke) | 6 Chart-Tests [Spec-C] ✅ *(je 4 Tests: Ancestors, CompactTree, Descendancy, Fan, Hourglass, Pedigree)* + `StatisticsChartModuleTest` [Spec-C] ✅ *(3 Tests + 2 DataProvider)* | `ChartModuleIntegrationTest` [Spec-C] ✅ *(17 Tests + 4 DataProvider: Timeline, Lifespan, FamilyBook, Relationships, Branches)* | — | OK (13/13) | — |
| S19 | Liste: Personen (Nachnamen) | `IndividualListModuleTest` [Spec-C] ✅ *(3 Tests: handle, show_all, listIsEmpty)* | `ListModuleIntegrationTest` [Smoke] ✅ *(17 Tests; initial-Filter 'W' via handle())* | `navigation.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S20 | Liste: alle 10 Typen (Smoke) | 7 List-Tests [Spec-C] ✅ *(je 3–4 Tests: Individual, Family, Source, Repository, Note, Media, Submitter)* | `ListModuleIntegrationTest` [Spec-C] ✅ *(3 Tests: Location, PlaceHierarchy, Branches)* | — | OK (10/10) | — |
| S21 | AutoComplete (Personen) | `AutoCompleteSurnameTest` [Spec-C] ✅ *(4 Tests/8 Assertions)* | — | — | OK | — |
| S22 | AutoComplete (Orte) | `AutoCompletePlaceTest` [Spec-C] ✅ *(4 Tests/7 Assertions; match + no-match)* | — | — | OK | — |
| S23 | Navigation: Personenseite | — | — | `individual.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S24 | Navigation: Familienseite | — | — | `family.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| S26 | Navigation: Quellenseite | — | — | `records.spec.ts` [Smoke] ✅ *(5 Tests gesamt; Quellen-Seite)* | OK | — |
| S27 | Navigation: Medienseite | — | — | `records.spec.ts` [Smoke] ✅ *(5 Tests gesamt; Medien-Seite)* | OK | — |
| S28 | Navigation: Notizseite | — | — | `records.spec.ts` [Spec-C] ✅ *(5 Tests gesamt; NOTE-Seite auf `muster`-Tree, 5 Themes)* | OK | — |
| S29 | Navigation: Aufbewahrungsort | — | — | `records.spec.ts` [Smoke] ✅ *(5 Tests gesamt; Aufbewahrungsort-Seite)* | OK | — |
| S30 | Navigation: Einreicherseite | — | — | `records.spec.ts` [Smoke] ✅ *(5 Tests gesamt; Einreicher-Seite)* | OK | — |
| S31 | Kalenderansicht & Kalenderevents-API | — | — | `calendar.spec.ts` [Spec-C] ✅ *(2 Tests: Monat + Jahr; CalendarEvents AJAX implizit)* | OK | — |
| S32 | Anmeldeseite (Login) | — | — | `login.spec.ts` [Spec-C] ✅ *(3 Tests)* | OK | — |
| S33 | Registrierungsseite | — | — | `auth.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S34 | Passwort-Zurücksetzung | — | — | `auth.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S35 | Benutzerseite (Meine Seite) | — | — | `user-pages.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S36 | Kontaktseite | — | — | `user-pages.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S37 | Berichtsliste | — | — | `user-pages.spec.ts` [Smoke] ✅ *(3 Tests)* | OK | — |
| S38 | Erweiterte Suche (Seitenaufruf) | — | — | `search-forms.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S39 | Phonetische Suche (Seitenaufruf) | — | — | `search-forms.spec.ts` [Smoke] ✅ *(2 Tests)* | OK | — |
| S40 | Navigation: Homepage (Baumseite) | — | — | `homepage.spec.ts` [Spec-C] ✅ *(2 Tests × 5 Themes)* | OK | — |
| S41 | Statistikdaten-Abfragen | — | `StatisticsDataIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 13 Tests: 4 alt + EP5/EP6/EP8 whereBetween, DataProvider sort×3, EP13 threshold, DataProvider sex×2)* + `StatisticsIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | — |
| S42 | Such-HTTP-Handler | — | `SearchRequestHandlerIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 6 Tests: Single-Result-Redirect EP2/EP4, Default-Fallback EP8, Multi-Result EP1/EP3)* | — | OK | — |
| S43 | Report-Generierung HTTP | — | `ReportIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 8 Tests: EP2 PDF→application/pdf, EP6 download→content-disposition, B1 unknown-redirect, 5 bisherige HTML/SAX-Tests)* | — | OK | — |
| S44 | Report-Parser Erweitert | — | `ReportParserGenerateExtendedIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert Pragmatisch C, 3 Tests: EP1 Vorfahren+assertNotEmpty+HTML, EP3 Nachkommen+assertNotEmpty+HTML, EP7 Individual+Fakten+Bild+assertNotEmpty+HTML)* | — | OK | — |
| S45 | Report-Primitive PDF/HTML | — | `ReportPdfObjectsIntegrationTest` + `ReportHtmlObjectsIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert+strukturbasiert, 23 Tests: 13 HTML (fill/border/newline Assertions TextBox+Cell) + 10 PDF (3 Image-Branch-Tests + 7 Basis))* | — | OK | — |
| S46 | Homepage-Block-Module | — | `BlockModuleIntegrationTest` [EP] ✅ *(spezifikationsbasiert Pragmatisch C, 14 Tests: 10 alt + DataProvider infoStyles×4 EP4/EP5/EP6/EP6b)* | — | OK | — |
| S47 | Interaktiver Stammbaum | — | `InteractiveTreeIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert Pragmatisch C, 3 Tests: getDetails→XREF im Output, 'p'-Request→non-empty HTML, 'c'-Request→non-empty HTML)* | — | OK | — |
| S48 | Standortdaten-Import Admin | — | `MapDataImportIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 4 Tests: EP1+EP5 add→DB-Postcondition lat/lng, EP6 Null-Island→gefiltert, 2 Smoke-Fehlerresilienz)* | — | OK | — |
| S49 | Medienverwaltungsliste Admin | — | `ManageMediaDataIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 3 Tests: EP1 local + EP2 external + EP3 unused, JSON-Struktur-Assertions)* | — | OK | — |
| S50 | Hilfetexte | — | `HelpTextIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 13 Tests: DataProvider 12 Topics + unknown-Topic)* | — | OK | — |
| S52 | Standortdaten-Verwaltung (CRUD) | — | `MapDataCrudIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 5 Tests: MapDataSave INSERT→DB, UPDATE→DB, MapDataDelete→entfernt, MapDataExportCSV→text/csv, MapDataList GET→200)* | — | OK | — |
| S53 | Legacy-URL-Weiterleitungen | — | — | — | — | — |

<a id="p"></a>

#### Datenschutz & Zugriffskontrolle (P01–P42)

> **Detailkonzept:** [`port-implementation/03_batches/batch_P_privacy_access.md`](../port-implementation/03_batches/batch_P_privacy_access.md)

| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
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
| P13 | isDead(): Inferenz Kinder/Enkel | — | `IsDeadTest` [Spec-C] ✅ *(17 Tests)* | — | OK | — |
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
| P30 | Datensätze zusammenführen | — | `MergeFactsActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 5 Tests: B1/EP2 not-found, B3/EP4 same-record, B4/EP5 tag-mismatch, B5/EP6 pending-deletion, EP1 change-DB-Assert)* + `MergeFactsIntegrationTest` ✅ *(CRAP-Smoke)* + `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | — |
| P31 | Familienmitglieder bearbeiten | — | `ChangeFamilyMembersActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 5 Tests: EP1 replace-husband, EP2 remove-wife, EP3 add-child, EP4 remove-child, EP5 no-change)* + `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | — |
| P32 | Record-Ansicht und -Löschung | — | `DeleteRecordIntegrationTest` + `GedcomRecordPageIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 2+5 Tests: EP1 SOUR-Löschung, EP5 Familie-Kaskade; DataProvider INDI/FAM/SOUR/REPO→Redirect, EP2 _CUST→200+Link)* + `RequestHandlerBatchAIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | — |
| P33 | Stammbaum-Privacy-Einstellungen | — | `TreePrivacyActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 6 Tests: EP3/EP4 mismatched-arrays, EP5 tag+xref, EP6 tag-only, EP7 xref-only, EP8 beide-leer count-gleich, EP9 HIDE_LIVE_PEOPLE)* + `RequestHandlerBatchAIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | — |
| P34 | Stammbaum-Umnummerierung | — | `RenumberTreeActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 3 Tests: B2/EP1 keine-Duplikate, B3/EP2 INDI-Rename-Postcondition, B1/EP4 Pending-Edits-Guard)* + `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke)* | — | OK | — |
| P35 | CLI Benutzer-Verwaltung | — | `UserEditCommandIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 16 Tests: B1–B11 Guards, DataProvider B3/B4/B5, B13–B15 Edit-Felder)* | — | OK | — |
| P36 | CLI Einstellungs-Verwaltung | — | `CliSettingsBatchIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 17 Tests: --list/--delete-Konflikte, Delete nonexistent, Get nonexistent, same-value Warn, Update, EP11 Tree/User/UserTree not found)* | — | OK | — |
| P37 | HTTP Benutzer-Bearbeitung | — | `UserEditActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 7 Tests: B1 not-found, B5/B6 Duplikat-Email, B7/B8 Duplikat-Username, B4 Self-Edit-Admin, B3 Passwort, EP12 Path-Reset); `RequestHandlerBatchBIntegrationTest` ✅ *(CRAP-Smoke, 1 Test)* | — | OK | — |
| P38 | Account-Selbstverwaltung | — | `AccountSelfManagementIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: Edit GET 200, Update POST E-Mail, Delete admin-Guard, Delete non-admin gelöscht)* | — | OK | — |
| P39 | Authentifizierung-Aktionen | — | `LoginActionIntegrationTest` [Smoke] ✅ *(spezifikationsbasiert, 1 Test: EP1 CLI-Kontext $_COOKIE=[]→doLogin wirft→handler fängt→302)* | — | OK | — |
| P40 | Änderungsverwaltung (HTTP-Handler) | — | `PendingChangesIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: AcceptRecord ungültig→204, RejectRecord ungültig→204, PendingChanges GET→200)* | — | OK | — |
| P41 | Datensatz-Zusammenführung (vollständig) | — | `MergeRecordsIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: Page GET valid/empty XREFs, Action POST matching INDIs→302)* | — | OK | — |
| P42 | CLI Benutzer-Listing | — | — | — | — | Nicht abgedeckt; Plan-Iteration 2 (analog P35/P36 als `UserListCommandIntegrationTest`) |

<a id="sec"></a>

#### Sicherheit (SEC-H01–SEC-UTL01)

> **Detailkonzept:** [`port-implementation/03_batches/batch_SEC_security.md`](../port-implementation/03_batches/batch_SEC_security.md)
>
> **Hinweis (Phase 3.5):** Shell-Assertions (`security-filesystem-checks.sh`) sind keine
> eigene Teststufe. Sie laufen im Rahmen der Systemtests gegen die installierte
> Webtrees-Instanz und werden deshalb in der L4-Spalte geführt. Wo Shell-Skript und
> Playwright-Spec dieselbe Kontrollpunktfrage beantworten, werden beide in der L4-Zelle
> nebeneinander geführt (`spec.ts ✅ + shell-checks.sh ✅`).

| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
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
| SEC-HDR01 | `X-Content-Type-Options` | — | — | `security-headers.spec.ts` [Spec-B] ✅ *(4 Tests)* | OK | — |
| SEC-HDR02 | `X-Frame-Options` | — | — | `security-headers.spec.ts` [Spec-B] ✅ *(4 Tests)* | OK | — |
| SEC-HDR03 | `Referrer-Policy` | — | — | `security-headers.spec.ts` [Spec-B] ✅ *(4 Tests)* | OK | — |
| SEC-HDR04 | Server-Banner | — | — | `security-headers.spec.ts` [Spec-C] ⚠ *(4 Tests)* | OK | Deployment-Empfehlung |
| SEC-BOT01 | UA-basierte Bot-Blockierung | — | `BadBotBlockerIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 15 Tests: BAD_ROBOTS-DataProvider×5 + WP-Pfade-DataProvider×4 + Cookie-Heuristik EP8/EP9 + 4 Basis; DNS ausgeklammert)* | — | OK | — |
| SEC-UTL01 | Web-Assets & Utility-Endpoints | — | `UtilityEndpointsIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 10 Tests)* | — | OK | — |

<a id="e"></a>

#### Datenpflege / Erfassung (E01–E08)

> **Detailkonzept:** [`port-implementation/03_batches/batch_E_data_entry.md`](../port-implementation/03_batches/batch_E_data_entry.md)

| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| E01 | Person/Familie anlegen & verknüpfen | — | `AddRelationIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 6 Tests: AddChildToIndividualPage GET→200, Action POST→302, DataProvider AddParent/AddSpouseToIndi/AddChild/AddSpouseToFam→200)* | — | OK | — |
| E02 | Fakten bearbeiten | — | `EditFactIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: EditFactPage unknown fact_id→redirect, DeleteFact unknown fact_id→204, AddNewFact GET→200)* | — | OK | — |
| E03 | Rohdaten-Edit (Raw GEDCOM) | — | `EditRawGedcomIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: EditRawFactPage unknown fact_id→redirect, EditRawRecordPage GET→200, EditRawFactAction unknown fact_id→redirect)* | — | OK | — |
| E04 | Nebenrecords anlegen | — | `CreateSubrecordIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: CreateNoteModal GET→200, CreateNoteAction POST→JSON-XREF, CreateSourceModal GET→200, CreateRepositoryModal GET→200)* | — | OK | — |
| E05 | Medienobjekte anlegen & verknüpfen | — | `MediaObjectIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: CreateMediaObjectModal GET→200, LinkMediaToRecordAction POST→302, LinkMediaToIndividualModal GET→200)* | — | OK | — |
| E06 | Sortierung (Reorder) | — | `ReorderIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: ReorderChildren/Names/Families GET→200, unknown FAM→404)* | — | OK | — |
| E07 | Mediendatei-Download & Thumbnail | — | `MediaFileDeliveryIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: Thumbnail unknown XREF→200, Thumbnail known XREF no fact_id→200, Download unknown XREF→HttpNotFoundException)* | — | OK | — |
| E08 | TomSelect & AutoComplete | — | `TomSelectIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 5 Tests: TomSelectIndividual leer/XREF/Name, TomSelectSource leer, AutoCompleteFolder)* | — | OK | — |

<a id="a"></a>

#### Administration (A01–A16)

> **Detailkonzept:** [`port-implementation/03_batches/batch_A_admin.md`](../port-implementation/03_batches/batch_A_admin.md)

| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| A01 | Stammbaum-Management | — | `TreeManagementIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: CreateTree Duplikat→302, CreateTree Neu→DB, DeleteTree→204, ManageTrees GET→200)* | — | OK | — |
| A02 | Stammbaum-Import (HTTP-Formular) | — | `ImportGedcomActionIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 4 Tests: UPLOAD_ERR_NO_FILE→302, UPLOAD_ERR_PARTIAL→Exception, leerer server_file→302, ImportGedcomPage GET→200)* | — | OK | — |
| A03 | Stammbaum-Export (HTTP-Formular) | — | `ExportGedcomIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: Client format=gedcom→attachment, format=zip→application/zip, ExportGedcomServer→302, ExportGedcomPage GET→200)* | — | OK | — |
| A04 | Stammbaum-Präferenzen | — | `TreePreferencesIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: Page GET→200, Action POST→302+preference saved, Action POST→meta_description saved)* | — | OK | — |
| A05 | Modul-Konfiguration | — | `ModuleConfigIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 7 Tests: ModulesAllPage GET→200, ModulesAllAction POST→302, DataProvider Analytics/Blocks/Charts/Menus/Reports→200)* | — | OK | — |
| A06 | Site-Präferenzen | — | `SitePreferencesIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: Page GET→200, Action POST valid→302, POST saves LANGUAGE, POST invalid directory→302)* | — | OK | — |
| A07 | Benutzerverwaltung Admin | — | `UserAdminIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 3 Tests: UserListPage GET→200, mit filter, UsersCleanupPage GET→200)* | — | OK | — |
| A08 | Medienverwaltung Admin | — | — | — | — | — |
| A09 | Datenpflege-Werkzeuge | — | `DataMaintenanceIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: FindDuplicateRecords GET→200, DataFixPage leer→200, DataFixPage fix-place-names→200, DataFixChoose GET→200)* | — | OK | — |
| A10 | Protokolle & Monitoring | — | `LogsMonitoringIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 4 Tests: PendingChangesLogPage GET→200, SiteLogsDownload→CSV, Disposition attachment, PhpInformation→200)* | — | OK | — |
| A11 | System & Upgrade | — | `SystemAdminIntegrationTest` [Spec-C] ✅ *(spezifikationsbasiert, 5 Tests: Masquerade not-found→HttpNotFoundException, self→204, other→204+Auth, BroadcastPage GET→200, EmailPreferencesPage GET→200)* | — | OK | — |
| A12 | CLI Wartungsmodus aktivieren | — | — | — | — | Nicht abgedeckt; Plan-Iteration 2 (analog P36 als `SiteOfflineCommandIntegrationTest`) |
| A13 | CLI Wartungsmodus deaktivieren | — | — | — | — | Nicht abgedeckt; Plan-Iteration 2 (analog P36 als `SiteOnlineCommandIntegrationTest`) |
| A14 | CLI initialer Config-Setup | — | — | — | — | Nicht abgedeckt; Plan-Iteration 2 (`ConfigIniCommandIntegrationTest`) |
| A15 | CLI Übersetzung kompilieren | — | — | — | — | Nicht abgedeckt; Plan-Iteration 2 (`CompilePoFilesCommandIntegrationTest`) |
| A16 | CLI Baum-Listing | — | — | — | — | Nicht abgedeckt; Plan-Iteration 2 (`TreeListCommandIntegrationTest`) |

<a id="k"></a>

#### Kommunikation (K01–K02)

> **Detailkonzept:** [`port-implementation/03_batches/batch_K_communication.md`](../port-implementation/03_batches/batch_K_communication.md)

| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| K01 | Kontaktformular | — | — | — | — | — |
| K02 | Benutzer-Nachrichten | — | — | — | — | — |

<a id="u"></a>

#### Querschnitts-Utilities (U01–U02)

> **Detailkonzept:** [`port-implementation/03_batches/batch_U_utilities.md`](../port-implementation/03_batches/batch_U_utilities.md)

| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| U01 | Validator (root-Paket) | `ValidatorTest` [EP] ✅ *(24 Tests/52 Assertions, EP-complete)* | `ValidatorIntegrationTest` [EP] ✅ *(spezifikationsbasiert, 15 Tests: float() EP1–EP5+BV+Inv+Miss, __construct UTF-8 key/value/ASCII, integer() neg-String, array() non-array-throw)* | — | OK | — |
| U02 | CountryService (`Statistics/Service/`) | — | — | — | SKIP | Deprecated (`@deprecated`, Entfernung in webtrees 2.3; kein Test geplant) |

<a id="m"></a>

#### Middleware (M01–M28)

> **Hinweis:** Die M-Domäne wurde in Plan-Phase 5.1 neu angelegt. Die L2-Zellen spiegeln die Fork-Gap-Analyse vom 2026-04-11
> (siehe [`coverage-runs/2026-04-11_gap-analyse-fork_l2.csv`](coverage-runs/2026-04-11_gap-analyse-fork_l2.csv), Einträge `app/Http/Middleware/*`).
> L3/L4-Zellen sind überwiegend leer, weil die vorhandenen Integrationstests (z. B. `BadBotBlockerIntegrationTest` unter SEC-BOT01,
> `security-headers.spec.ts` unter SEC-HDR01–HDR04, `AccessControlTest` / `access-control.spec.ts` unter P27–P29) primär unter anderen
> Feature-IDs geführt werden und hier als Querverweis ausgewiesen sind. Viele L2-Einträge sind im Fork nur als **Stub** vorhanden
> (`testClass` + `assertTrue(class_exists(...))`) — diese Zellen bleiben `—` ohne Siegel, weil sie die Regel „Stub → Smoke nur bei ≥ 3
> Assertions" nicht erfüllen. Aufarbeitung dieser Stubs ist als Plan-Iteration 2 adressiert.

| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Abdeckung | Befund |
|---|---|---|---|---|---|---|
| M01 | Rollenbasierte Zugriffskontrolle | `AuthAdministratorTest` + `AuthEditorTest` + `AuthManagerTest` + `AuthMemberTest` + `AuthModeratorTest` [Spec-C] ✅ *(5× Substantial, je 3 Tests/5 Assertions; `AuthLoggedInTest` Stub; `AuthNotRobot` ohne L2-Test)* | `AccessControlTest` [EP] ✅ *(Querverweis zu P27–P29)* | `access-control.spec.ts` [Spec-C] ✅ *(Querverweis zu P27–P29)* | OK | Cluster (7 Klassen); Detail unter P27–P29 |
| M02 | Bad-Bot-Blocker (UA-basiert) | `BadBotBlockerTest` [Spec-C] ✅ *(Substantial, 4 Tests/6 Assertions)* | `BadBotBlockerIntegrationTest` [EP] ✅ *(Querverweis zu SEC-BOT01)* | — | OK | Detail unter SEC-BOT01 |
| M03 | Client-IP-Ermittlung (Proxy-Trust) | — *(`ClientIpTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M04 | CSRF-Token-Validierung | `CheckCsrfTest` [Smoke] ✅ *(Stub, 3 Assertions → Smoke-Schwelle)* | — | — | OK | — |
| M05 | Security-Headers (OWASP) | `SecurityHeadersTest` [Spec-C] ✅ *(Substantial, 3 Tests/9 Assertions)* | — | `security-headers.spec.ts` [Spec-B] ✅ *(Querverweis zu SEC-HDR01–HDR04)* | OK | Detail unter SEC-HDR01–HDR04 |
| M06 | Session-Initialisierung | — *(`UseSessionTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M07 | Datenbank-Verbindung | — *(`UseDatabaseTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M08 | Datenbank-Schema-Migration | — *(`UpdateDatabaseSchemaTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M09 | Base-URL-Ermittlung | — *(`BaseUrlTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M10 | Routen-Laden | — *(`LoadRoutesTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M11 | URL-Routing | — *(`RouterTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M12 | Request-Handler-Dispatch | — *(`RequestHandlerTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M13 | Sprachauswahl | — *(`UseLanguageTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M14 | Theme-Auswahl | — *(`UseThemeTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M15 | PHP-Error-zu-Exception-Konvertierung | — *(kein L2-Test im Fork)* | — | — | — | Kein Test vorhanden; Plan-Iteration 2 |
| M16 | Exception-Handling & Error-Page-Rendering | — *(`HandleExceptionsTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M17 | Debug-Logger (SQL/Perf) | — *(kein L2-Test im Fork)* | — | — | — | Kein Test vorhanden; Plan-Iteration 2 |
| M18 | Housekeeping (Thumbnails/Logs/Temp) | — *(`DoHousekeepingTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M19 | Response-Kompression | — *(`CompressResponseTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M20 | Content-Length-Header | — *(`ContentLengthTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M21 | Config-Ini-Lesen | — *(`ReadConfigIniTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M22 | Wartungsmodus | `CheckForMaintenanceModeTest` [Smoke] ✅ *(Smoke, 2 Tests/3 Assertions)* | — | — | OK | — |
| M23 | Update-Prüfung | — *(`CheckForNewVersionTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M24 | Public-Files-Serving | `PublicFilesTest` [Spec-C] ✅ *(Substantial, 4 Tests/4 Assertions)* | — | — | OK | — |
| M25 | GEDCOM-Tag-Registrierung | — *(`RegisterGedcomTagsTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M26 | Modul-Bootstrap | — *(`BootModulesTest` Stub, 2 Assertions)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M27 | DB-Transaktion mit Retry | — *(`UseTransactionTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |
| M28 | Response-Emittierung | — *(`EmitResponseTest` Stub, 1 Assertion)* | — | — | — | Stub ungenügend; Plan-Iteration 2 |

#### Zusammenfassung Abdeckung

**Aktueller Stand (2026-04-12):** 173 abgedeckt / 35 nicht abgedeckt / 1 SKIP / 209 gesamt.

Nicht abgedeckte IDs: G05, G06, G31, S53, P42, A08, A12–A16, K01, K02, M03, M06–M21,
M23, M25–M28 (22 M-Stubs, 7 neue CLI-IDs, 3 historische Lücken, 3 bestehende Lücken).

Der Vorgänger-Stand (165 / 5 / 170) ist als datierter Snapshot archiviert:
[`coverage-runs/2026-04-11_abdeckung-snapshot.md`](coverage-runs/2026-04-11_abdeckung-snapshot.md).
Spätere Stände werden als neuer, datierter Snapshot angelegt (jüngster zuerst in der
Dateiliste unter [`coverage-runs/`](coverage-runs/)).
