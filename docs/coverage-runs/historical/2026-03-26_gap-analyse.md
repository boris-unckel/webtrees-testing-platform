<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Gap-Analyse der existierenden webtrees-Tests — 2026-03-26 (archiviert)

> **Archiv-Notiz:** Dieser Befund ist mit Stand 2026-03-26 gegen das damalige
> Upstream-`main` erhoben. Er ist durch den Fork-Branch
> [`port-layer2-test-doubles`](../../../webtrees-upstream/webtrees) in Teilen überholt —
> dort wurden 278 Testdateien von reinen Stubs auf substanzielle Komponententests
> aufgewertet (Commit `841616f4b5`, 2026-04-11). Der aktuelle Stand liegt im Snapshot
> [`../2026-04-11_gap-analyse-fork.md`](../2026-04-11_gap-analyse-fork.md).
>
> **Zweck der Archivierung:** Historische Referenz für Veröffentlichungs- und
> Nachvollziehbarkeits-Zwecke. Wortlaut gegenüber dem Ursprung in
> [`../../tds_conditions_ref.md`](../../tds_conditions_ref.md) unverändert.
>
> **Ursprung:** `tds_conditions_ref.md` (Zeilen 58–129 im Dokumenten-Stand unmittelbar
> vor der Archivierung im Rahmen von Plan-Phase 2).

---

## Befund: Gap-Analyse der existierenden webtrees-Tests

> Stand: webtrees 2.2.6-dev. Analyse vom 2026-03-26.

**Gesamtbild:**
- 1233 Testdateien in `tests/app/`, 5 in `tests/feature/`
- **~95% sind Stub-Tests** (nur `testClass()` — verifiziert, dass die PHP-Klasse existiert)
- **~4% sind triviale Tests** (wenige Assertions, keine fachliche Tiefe)
- **~1% sind substanzielle Tests** (echte fachliche Assertions mit Datenprüfung)

### Domäne: GEDCOM Import/Export

| Komponente | Public Methods | Test-Status | Assertions |
|---|---|---|---|
| `GedcomImportService` | 3 (`importRecord`, `updatePlaces`, `updateRecord`) | Stub | 1 (`testClass`) |
| `GedcomExportService` | 5 (`downloadResponse`, `export`, `createHeader`, `wrapLongLines`, Konstruktor) | Stub | 1 (`testClass`) |
| `ImportGedcomAction` (Handler) | 1 | Stub | 1 |
| `ImportGedcomPage` (Handler) | 1 | Stub | 1 |
| `ExportGedcomClient` (Handler) | 1 | Stub | 1 |
| `ExportGedcomServer` (Handler) | 1 | Stub | 1 |
| `GedcomEncodingFilter` | — | Substanziell | Encoding-Tests vorhanden |
| `ImportGedcomTest` (Feature) | — | Minimal | 1 Test: `demo.ged` importieren (keine Ergebnisprüfung) |
| Element-Klassen | 216 | 212 Tests | Meist Pattern-Validierung (gut) |

**Ungetestete Kernlogik (Import):**
- Record-Import mit Typ-Erkennung (INDI, FAM, SOUR, …)
- Place-Hierarchie-Aufbau beim Import
- Date-Parsing und Index-Aktualisierung
- Name-Extraktion und Soundex-Generierung
- Inline-Media-Konvertierung
- Legacy-Format-Konvertierung (TNG, PLAC_DEFN)

**Ungetestete Kernlogik (Export):**
- 4 Export-Formate: GEDCOM, ZIP, ZIP+Media, GEDZIP
- Privacy-Filterung nach Access-Level (PRIV_NONE, PRIV_USER, PRIV_PRIVATE, PRIV_HIDE)
- Encoding-Konvertierung (UTF-8 → ANSEL, Windows-1252, etc.)
- Zeilenumbrüche (CRLF/LF) und CONC/CONT-Wrapping
- Header-Generierung mit Metadaten
- Media-Datei-Einbettung in ZIP-Export

### Domäne: Suche und Navigation

| Komponente | Public Methods | Test-Status | Assertions |
|---|---|---|---|
| `SearchService` | 20 Suchmethoden | Minimal | 1 Testmethode, prüft nur "Collection nicht leer" |
| `SearchGeneralPage` (Handler) | 1 | Stub | 1 |
| `SearchAdvancedPage` (Handler) | 1 | Stub | 1 |
| `SearchPhoneticPage` (Handler) | 1 | Stub | 1 |
| `SearchQuickAction` (Handler) | 1 | Stub | 1 |
| `SearchReplacePage` (Handler) | 1 | Stub | 1 |
| 13 Chart-Module | je 1–3 | Stub | je 1 (`testClass`) |
| 10 List-Module | je 1–3 | Stub | je 1 (`testClass`) |
| `IndividualListTest` (Feature) | — | **Substanziell** | 7 Testmethoden, ~50 Assertions (Collation, Initialen, Nachnamen) |
| 16 AutoComplete/TomSelect | je 1 | Stub | je 1 |

**Ungetestete Kernlogik (Suche):**
- Allgemeine Suche: Query-Parsing (Anführungszeichen, CJK-Splitting, Leerzeichen)
- Suche über 6 Record-Typen (Individuals, Families, Sources, Notes, Repositories, Locations)
- Erweiterte Suche: 75 GEDCOM-Felder mit Datum-Modifikatoren (±0 bis ±20 Jahre)
- Phonetische Suche: Russell-Soundex und Daitch-Mokotoff-Soundex
- Paginierung, Offset, Limit
- Cross-Tree-Suche (über mehrere Stammbäume)
- Zugriffskontrolle auf Suchergebnisse
- Search-and-Replace (Bulk-Editor, erfordert Edit-Recht)

**Ungetestete Kernlogik (Navigation):**
- 13 Chart-Typen: kein einziger Rendering-Test
- Chart-Parameter und -Optionen (Generationstiefe, Layout, etc.)
- 10 List-Module: nur IndividualList substanziell getestet
- Sortierung und Collation (locale-spezifisch)
- AutoComplete/TomSelect-AJAX-Endpoints (16 Stück)
