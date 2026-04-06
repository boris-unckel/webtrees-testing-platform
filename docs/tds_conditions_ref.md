<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Testbedingungen — Feature-Matrizen und RE-Methodik

Dieses Dokument enthält alle Testbedingungen (ISTQB: Testbedingungen), organisiert als Feature-Matrizen,
sowie die Reverse-Engineering-Methodik, mit der sie abgeleitet wurden.

**Querverweise:**
- [Abdeckungsmatrix](tds_coverage_ref.md)
- [Testentwurfsverfahren](tds_methodik_spec.md)
- [Designentscheidungen](tp_decisions_spec.md)

---

## RE-Methodik: 4 Schritte

**Schritt 1 — Code-Topologie erfassen (Feature-Discovery)**

Jedes Feature wird als Call-Chain identifiziert:

```
Route (WebRoutes.php)
  → RequestHandler (Http/RequestHandlers/)
    → Service (Services/)
      → DB / GedcomRecord / Elements
```

Die **öffentlichen Methoden der Service-Klassen** sind die fachlichen Fähigkeiten.
Jede public Method = mindestens ein Testfall. Private Methoden werden indirekt über
die public API getestet.

**Schritt 2 — Gap-Analyse der existierenden Tests**

Nicht die Dateianzahl zählt, sondern die **Assertionsdichte**:
- **Stub-Test** (`testClass()` / 1 Assertion "Klasse existiert") = **ungetestet**
- **Trivialer Test** (2–3 Assertions, keine fachliche Logik) = **minimal getestet**
- **Substanzieller Test** (fachliche Assertions, Fixtures, Datenprüfung) = **getestet**

Ein Code-Analyse-Skript kann diese Klassifizierung automatisieren:
`grep -c 'assert' tests/app/Services/*Test.php` zeigt die Assertionsdichte pro Datei.

**Schritt 3 — GEDCOM-Standard-Abgleich (Domäne Import/Export)**

| Prüfpunkt | Quelle | Methode |
|---|---|---|
| Unterstützte Tags | `app/Elements/` (216 Klassen) vs. GEDCOM 5.5.1 Tag-Liste | Diff |
| Encoding-Varianten | `GedcomEncodingFilter` | Code-Lesen |
| Custom-Tags (Ancestry, FamilySearch, etc.) | `app/Gedcom.php` (13 Custom-Tag-Klassen) | Code-Lesen |
| Zeilenlänge, CONC/CONT | `GedcomExportService::wrapLongLines()` | Komponententest |
| Date-Formate | `app/Date/` Klassen | Vergleich mit GEDCOM-Spec |

**Schritt 4 — Feature-Matrix aufbauen**

Für jede Prioritäts-Domäne: tabellarische Zuordnung
Code-Stelle → abgeleitete Anforderung → Testart → Priorität → Teststufe.

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

---

## Feature-Matrix: GEDCOM Import/Export

> Abgeleitet aus Code-Analyse von `GedcomImportService`, `GedcomExportService`,
> `GedcomEncodingFilter`, `Elements/`, Request-Handlern und dem GEDCOM 5.5.1-Standard.
>
> Teststufen: 1 = Komponententest, 2 = Komponentenintegrationstest, 3 = Systemtest

| # | Feature | Abgeleitete Anforderung | Teststufe | Prio |
|---|---|---|---|---|
| G01 | Record-Import (INDI) | Individuum importieren → korrekte DB-Einträge (name, date, place) | 2 | Hoch |
| G02 | Record-Import (FAM) | Familie importieren → Beziehungen korrekt verknüpft (HUSB, WIFE, CHIL) | 2 | Hoch |
| G03 | Record-Import (SOUR, NOTE, REPO, OBJE) | Nebenrecords importieren → DB-Einträge korrekt | 2 | Mittel |
| G04 | Place-Hierarchie | Import mit PLAC-Tags → Orts-Hierarchie in `place_location` aufgebaut | 2 | Hoch |
| G05 | Date-Parsing | GEDCOM-Datumsformate (exakt, Bereich, vor/nach, ca.) → korrekte date1/date2-Felder | 1 | Hoch |
| G06 | Name-Extraktion | NAME-Tags → Vorname, Nachname, Suffix korrekt gesplittet + Soundex generiert | 1 | Hoch |
| G07 | Encoding (UTF-8) | UTF-8-GEDCOM importieren → keine Zeichenverluste | 2 | Hoch |
| G08 | Encoding (ANSEL, CP1252) | Nicht-UTF-8-GEDCOM importieren → korrekte Konvertierung | 2 | Mittel |
| G09 | Inline-Media | Eingebettete OBJE-Records → separate Media-Objekte erzeugt | 2 | Mittel |
| G10 | Legacy-Formate | TNG-PLAC, _PLAC_DEFN → korrekt konvertiert | 2 | Niedrig |
| G11 | Custom-Tags | Ancestry/FamilySearch/RootsMagic-Tags → erkannt und nicht verworfen | 1 | Mittel |
| G12 | XREF-Vergabe | Neue Records erhalten eindeutige XREFs, keine Kollisionen | 2 | Hoch |
| G13 | Export GEDCOM | Baum exportieren → valide GEDCOM-Datei, importierbar | 2 | Hoch |
| G14 | Export ZIP | Export als ZIP → Datei enthält .ged + korrekte Struktur | 2 | Mittel |
| G15 | Export ZIP+Media | Export mit Mediendateien → Dateien im Archiv vorhanden | 2 | Mittel |
| G16 | Export Privacy | Export mit Access-Level → geschützte Records ausgeblendet/anonymisiert | 2 | Hoch |
| G17 | Export Encoding | Export mit gewähltem Encoding (UTF-8, ANSEL) → korrekte Ausgabe | 1 | Mittel |
| G18 | Export CONC/CONT | Lange Zeilen → korrekt in CONC/CONT aufgeteilt (max. 253 Zeichen) | 1 | Mittel |
| G19 | Export Header | HEAD-Record enthält korrekte Metadaten (Source, Date, GEDC Version) | 1 | Mittel |
| G20 | Import → Export Roundtrip | demo.ged importieren → exportieren → Diff minimal (nur Metadaten) | 3 | Hoch |
| G21 | Upload-Validierung | Ungültige Datei (kein GEDCOM) → Fehlermeldung, kein Import | 3 | Mittel |
| G22 | Element-Validierung | 216 Element-Klassen → Tag-Patterns und erlaubte Kinder korrekt | 1 | Mittel |
| G23 | GEDCOM 5.5.1 Compliance | Unterstützte Tags vs. Standard-Tag-Liste → Abweichungen dokumentiert | 1 | Niedrig |
| G24 | Referenzintegrität (CheckTree) | GEDCOM-Datenbank auf verwaiste XREFs und fehlende Verknüpfungen prüfen → Report-Handler antwortet 200 OK, keine Fehler bei valider demo.ged | 2 | Mittel |
| G25 | GedcomLoad CLI-Import *(spezifikationsbasiert)* | GedcomLoad::handle — keep_media-EP (0 löscht Media, 1 behält), BOM-Strip EP3, fehlendes 0-HEAD EP4 → Fail-View, fehlender Trailer EP5 → Fail-View, Complete-View EP6 | 8 | Mittel |
| G26 | GEDCOM-Export via CLI *(spezifikationsbasiert)* | CLI-Command exportiert Baum — alle 4 Formate (gedcom/gedzip/zip/zipmedia), alle 4 Privacy-Level (none/manager/member/visitor), Fehler bei ungültigem Format/Privacy und unbekanntem Tree → FAILURE | 2 | Mittel |
| G27 | Mediendatei-Upload URL *(strukturbasiert)* | URL-basierter Upload via MediaFileService → Datei lokal vorhanden und DB-Eintrag erzeugt | 2 | Mittel |
| G28 | OBJE-Metadaten bearbeiten *(spezifikationsbasiert)* | EditMediaFileAction::handle — Happy Path: gültige fact_id + title+type → change-Tabelle enthält pending GEDCOM mit neuem Titel (DB-Postcondition); Fact-not-found-Guard (fact_id='') → Redirect zu TreePage | 2 | Niedrig |
| G29 | GEDCOM-Bearbeitungsservice *(spezifikationsbasiert)* | GedcomEditService: editLinesToGedcom — Mehrzeilenwerte (CONT), Sub-Level-Struktur, Leerstring-Handling; insertMissingLevels — Subtag-Expansion, Level-1/2-Pfade | 2 | Niedrig |
| G30 | Mediendatei-Upload (HTTP-Formular) | UploadMediaPage/UploadMediaAction: Datei-Upload via Web-Formular → Datei gespeichert, OBJE-Record in DB erzeugt (verschieden von G27: URL-basierter Upload via MediaFileService) | 2, 3 | Mittel |

---

## Feature-Matrix: Suche und Navigation

> Abgeleitet aus Code-Analyse von `SearchService` (20 public Methods),
> 9 Search-Handlern, 13 Chart-Modulen, 10 List-Modulen, 16 AutoComplete-Handlern.
>
> Teststufen: 1 = Komponententest, 2 = Komponentenintegrationstest, 3 = Systemtest

| # | Feature | Abgeleitete Anforderung | Teststufe | Prio |
|---|---|---|---|---|
| S01 | Allgemeine Suche (Personen) | Suchbegriff → passende Individuen zurückgegeben | 2 | Hoch |
| S02 | Allgemeine Suche (Familien) | Suchbegriff → passende Familien zurückgegeben | 2 | Hoch |
| S03 | Allgemeine Suche (Quellen, Notizen, Repos) | Suchbegriff → passende Records je Typ | 2 | Mittel |
| S04 | Query-Parsing | Anführungszeichen, Mehrwort-Suche, CJK-Splitting korrekt | 1 | Hoch |
| S05 | Erweiterte Suche (Felder) | 75 GEDCOM-Felder → Feld-spezifische Filterung | 2 | Hoch |
| S06 | Erweiterte Suche (Datum-Modifikatoren) | Geburtsdatum ±5 Jahre → korrekte Eingrenzung | 2 | Hoch |
| S07 | Phonetische Suche (Russell) | Russell-Soundex → ähnlich klingende Namen gefunden | 2 | Mittel |
| S08 | Phonetische Suche (Daitch-Mokotoff) | DM-Soundex → osteuropäische Namensvarianten gefunden | 2 | Mittel |
| S09 | Quick-Search (XREF) | "I123" eingeben → direkt zum Record weitergeleitet | 3 | Mittel |
| S10 | Paginierung | Suche mit >50 Ergebnissen → Offset/Limit korrekt | 2 | Mittel |
| S11 | Cross-Tree-Suche | Suche über 2+ Bäume → Ergebnisse aus allen Bäumen | 2 | Mittel |
| S12 | Zugriffskontrolle (Suche) | Eingeschränkte Records → nicht in Suchergebnissen für Visitor | 2 | Hoch |
| S13 | Search-and-Replace | Bulk-Ersetzung in GEDCOM → nur bei Edit-Recht möglich | 3 | Mittel |
| S14 | Chart: Stammbaum (Pedigree) | Person mit 3+ Generationen → Chart rendert korrekt | 3 | Hoch |
| S15 | Chart: Nachkommen | Person mit Kindern/Enkeln → Descendancy-Chart korrekt | 3 | Mittel |
| S16 | Chart: Beziehungsfinder | 2 Personen → Verwandtschaftspfad gefunden und dargestellt | 3 | Hoch |
| S17 | Chart: Fächerchart (Fan) | Person → Kreisförmige Ahnentafel gerendert | 3 | Niedrig |
| S18 | Chart: alle 13 Typen | Jeder Chart-Typ → rendert ohne Fehler (Smoke) | 3 | Mittel |
| S19 | Liste: Personen (Nachnamen) | Nachnamen-Initialen → korrekte Filterung, Collation | 2 | Hoch |
| S20 | Liste: alle 10 Typen | Jeder List-Typ → rendert ohne Fehler, zeigt Einträge | 3 | Mittel |
| S21 | AutoComplete (Personen) | Tipp-Vorschläge → passende Individuen per AJAX | 2 | Mittel |
| S22 | AutoComplete (Orte) | Ort eintippen → Ortsvorschläge korrekt | 2 | Mittel |
| S23 | Navigation: Personenseite | XREF aufrufen → Fakten, Familien, Events korrekt dargestellt | 3 | Hoch |
| S24 | Navigation: Familienseite | Familien-XREF → Ehepartner, Kinder, Events korrekt | 3 | Hoch |
| S26 | Navigation: Quellenseite | Quellen-XREF aufrufen → Titel, Zitate, verknüpfte Records dargestellt | 3 | Hoch |
| S27 | Navigation: Medienseite | Medien-XREF aufrufen → Bild/Datei-Info, verknüpfte Records dargestellt | 3 | Mittel |
| S28 | Navigation: Notizseite | Notiz-XREF aufrufen → Notiztext dargestellt | 3 | Mittel |
| S29 | Navigation: Aufbewahrungsort-Seite | Repository-XREF aufrufen → Name, Adresse, verknüpfte Quellen | 3 | Mittel |
| S30 | Navigation: Einreicherseite | Submitter-XREF aufrufen → Name dargestellt | 3 | Niedrig |
| S31 | Kalenderansicht & Kalenderevents-API | CalendarPage/CalendarAction: Monats-/Jahresansicht aufrufen → rendert, Events sichtbar; CalendarEvents (AJAX-Endpoint): Ereignisdaten für Kalender-View → JSON mit Events des gewählten Zeitraums | 2, 3 | Hoch |
| S32 | Anmeldeseite (Login) | /login aufrufen → Formular sichtbar, Login/Fehler funktional | 3 | Hoch |
| S33 | Registrierungsseite | /register aufrufen → Formular sichtbar, keine HTTP-Fehler | 3 | Mittel |
| S34 | Passwort-Zurücksetzung | /password-request aufrufen → Formular sichtbar | 3 | Mittel |
| S35 | Benutzerseite (Meine Seite) | /my-page aufrufen → Benutzer-Blöcke gerendert, keine HTTP-Fehler | 3 | Hoch |
| S36 | Kontaktseite | /contact aufrufen → Kontaktformular sichtbar | 3 | Mittel |
| S37 | Berichtsliste | /report aufrufen → verfügbare Berichte gelistet | 3 | Mittel |
| S38 | Erweiterte Suche (Seitenaufruf) | /search-advanced aufrufen → Formular mit Feldfiltern sichtbar | 3 | Hoch |
| S39 | Phonetische Suche (Seitenaufruf) | /search-phonetic aufrufen → Formular sichtbar | 3 | Mittel |
| S40 | Navigation: Homepage (Baumseite) | Homepage/Baumseite aufrufen → Baumstatistik oder Willkommensblock dargestellt, keine HTTP-Fehler | 3 | Hoch |
| S41 | Statistikdaten-Abfragen *(spezifikationsbasiert)* | StatisticsData: countEventsByMonth whereBetween-Branch (EP5 alle Jahre, EP6 Jahresfilter 1900–2000, EP8 invertierter Bereich=leer); commonSurnames Sort-EP-Matrix (DataProvider alpha/count/rcount, EP13 threshold-Filter); parentsQuery Sex-EP (DataProvider F→WIFE, M→HUSB) | V | 2 | Mittel |
| S42 | Such-HTTP-Handler *(spezifikationsbasiert)* | SearchGeneralPage::handle → Single-Result-Redirect EP2/EP4 (Individual/Family → 302), Default-Fallback EP8, Multi-Result EP1/EP3 (200 OK) | 6 | Mittel |
| S43 | Report-Generierung HTTP *(spezifikationsbasiert)* | ReportSetupPage: Setup-Formular → 200 OK; ReportGenerate: format='PDF'→application/pdf (EP2), destination='download'→content-disposition:attachment (EP6), unbekannter Report→redirect (B1), HTML-Ausgabe (EP1) | 8 | Mittel |
| S44 | Report-Parser Erweitert *(spezifikationsbasiert, Pragmatisch C)* | ReportParserGenerate: Vorfahren-Bericht (EP1 addAncestors→non-empty HTML), Nachkommen-Bericht (EP3 addDescendancy→non-empty HTML), Individual-Bericht mit Fakten+Bild (EP7 factsStartHandler+imageStartHandler→non-empty HTML) | 3 | Mittel |
| S45 | Report-Primitive PDF/HTML *(spezifikationsbasiert+strukturbasiert)* | ReportHtml*: fill/border/newline Assertion-Tests (TextBox: bgcolor, border:solid, X-Pos; Cell: border='1'/'T'/''/ptp/bgcolor); ReportPdfImage: line='N' Y-Advance, statisch, CURRENT_POSITION; CRAP: ReportPdfImage::render eliminiert | 23 | Mittel |
| S46 | Homepage-Block-Module *(spezifikationsbasiert, Pragmatisch C)* | SlideShow: EP1 Standardblock→non-empty; TopSurnames info_style-EP-Matrix via DataProvider (EP4 table, EP5 list, EP6 tagcloud, EP6b array) → assertNotEmpty+HTML; übrige Block-Module: Smoke-String-Tests | 14 | Niedrig |
| S47 | Interaktiver Stammbaum *(spezifikationsbasiert, Pragmatisch C)* | TreeView: getDetails X1030→XREF im Output (EP5 Partner-Validierung); getIndividuals 'p'-Request→assertNotEmpty+HTML (EP1 Person mit Eltern); getIndividuals 'c'-Request→assertNotEmpty+HTML (EP3 Person mit Kindern) | 3 | Mittel |
| S48 | Standortdaten-Import Admin *(spezifikationsbasiert)* | MapDataImportAction: EP1+EP5 option=add korrektes CSV (`;`-Trenner, Level-Format)→DB-Postcondition lat/lng via assertEqualsWithDelta; EP6 Null-Island (0,0) multi-level Ort→gefiltert, place_location leer; 2 Smoke-Tests für malformed CSV (Fehlerresilienz) | 4 | Mittel |
| S49 | Medienverwaltungsliste Admin *(spezifikationsbasiert)* | ManageMediaData: `files`-EP-Matrix (local/external/unused) per Einzeltest, JSON-Struktur `{data, recordsTotal, recordsFiltered}` per Assertion; unused-Branch (handleCollection) gesondert abgedeckt | 3 | Mittel |
| S50 | Hilfetexte *(spezifikationsbasiert)* | HelpText::handle → alle 12 Topic-IDs per DataProvider (200 OK), unbekannte ID → 200 + generischer Hilfetext | 2 | Niedrig |
| S52 | Standortdaten-Verwaltung (CRUD) | MapDataList: Übersicht → 200; MapDataAdd/Edit/Save: Formular + Speichern → DB-Update place_location; MapDataDelete/DeleteUnused: Einträge löschen; MapDataExportCSV → CSV-Download (ergänzt S48 Import) | 2, 3 | Niedrig |
| S53 | Legacy-URL-Weiterleitungen | ~27 Redirect*-Handler (RedirectIndividualPhp, RedirectFanChartPhp, RedirectCalendarPhp usw.) leiten alte webtrees 1.x-URLs auf aktuelle Routen um → HTTP 301/302, kein 404 | 3 | Niedrig |

---

## Feature-Matrix: Datenschutz & Zugriffskontrolle

> Abgeleitet aus Code-Analyse von `Individual::canShow()`, `Individual::canShowByType()`,
> `Individual::isDead()`, `GedcomRecord::canEdit()`, `Fact::canEdit()`,
> Tree-Preferences (Privacy-Einstellungen), User-Preferences (Relationship Privacy).
>
> Teststufen: 2 = Komponentenintegrationstest, 3 = Systemtest.
> Rollen: B = Besucher, M = Mitglied, E = Bearbeiter, Mo = Moderator, V = Verwalter.

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| P01 | Stammbaum-Sichtbarkeit | `REQUIRE_AUTHENTICATION=1`: Besucher sieht keine Daten. `=0`: Besucher sieht öffentliche Daten. | B, M | 2, 3 | Hoch |
| P02 | Verstorbene Personen zeigen | `SHOW_DEAD_PEOPLE=PRIV_PRIVATE`: Besucher sieht Verstorbene. `=PRIV_USER`: Nur Mitglieder+. | B, M, V | 2, 3 | Hoch |
| P03 | Lebende Personen zeigen (Override) | `HIDE_LIVE_PEOPLE=0`: Privacy deaktiviert. `=1`: Privacy aktiv. | B, M, V | 2, 3 | Hoch |
| P04 | MAX_ALIVE_AGE — Altersgrenze | Grenzwertanalyse: Person geboren vor genau 120 Jahren (Grenze), ±1 Jahr. | B, M | 2 | Hoch |
| P05 | KEEP_ALIVE_YEARS_BIRTH | Verstorbene mit Geburt innerhalb N Jahren bleibt geschützt. Grenzwert: ==N, ==N+1. | B, M | 2 | Hoch |
| P06 | KEEP_ALIVE_YEARS_DEATH | Verstorbene mit Tod innerhalb N Jahren bleibt geschützt. Grenzwert: ==N, ==N+1. | B, M | 2 | Hoch |
| P07 | KEEP_ALIVE kombiniert | Beide KEEP_ALIVE gesetzt — OR-Logik. | B, M | 2 | Mittel |
| P08 | isDead(): Expliziter Tod | `1 DEAT Y` / `1 DEAT\n2 DATE` / `1 DEAT\n2 PLAC` → `isDead()=true`. | — | 2 | Hoch |
| P09 | isDead(): Datiertes Event > MAX_ALIVE_AGE | Irgendein Event älter als MAX_ALIVE_AGE → tot. Grenzwert ±1. | — | 2 | Hoch |
| P10 | isDead(): Geburt vorhanden + jung | Geburtsdatum < MAX_ALIVE_AGE, kein DEAT → `isDead()=false`. | — | 2 | Hoch |
| P11 | isDead(): Inferenz Eltern | Eltern-Events > MAX_ALIVE_AGE+45 → tot. Grenzwert. | — | 2 | Hoch |
| P12 | isDead(): Inferenz Ehepartner | Heirat > MAX_ALIVE_AGE−10 oder Ehepartner-Event > MAX_ALIVE_AGE+40 → tot. | — | 2 | Mittel |
| P13 | isDead(): Inferenz Kinder/Enkel | Kinder-Event > MAX_ALIVE_AGE−15, Enkel-Event > MAX_ALIVE_AGE−30 → tot. | — | 2 | Mittel |
| P14 | Namen vertraulicher Personen | `SHOW_LIVING_NAMES` × 3 Stufen (PRIV_PRIVATE, PRIV_USER, PRIV_NONE). | B, M, V | 2, 3 | Hoch |
| P15 | Vertrauliche Beziehungen | `SHOW_PRIVATE_RELATIONSHIPS=1`: leere Boxen in Charts. `=0`: komplett ausgeblendet. | B, M | 2, 3 | Mittel |
| P16 | RESN none (Record) | `1 RESN none` → für alle sichtbar, überschreibt isDead()-Logik. | B, M, V | 2, 3 | Hoch |
| P17 | RESN privacy (Record) | `1 RESN privacy` → nur Mitglieder+ sehen Record. | B, M, V | 2, 3 | Hoch |
| P18 | RESN confidential (Record) | `1 RESN confidential` → nur Verwalter/Admin sehen Record. | B, M, V | 2, 3 | Hoch |
| P19 | RESN auf Fakten-Ebene | `2 RESN privacy` auf BIRT → Person sichtbar, Fakt nur für M+. `2 RESN confidential` auf DEAT → nur für V+. | B, M, V | 2, 3 | Hoch |
| P20 | default_resn (Individuum) | DB-Eintrag `xref=..., tag_type=NULL` → gesamter Record eingeschränkt. | B, M, V | 2 | Mittel |
| P21 | default_resn (Faktentyp) | DB-Eintrag `tag_type=BIRT` → alle BIRT eingeschränkt. Kombiniert: `xref+tag_type`. | B, M, V | 2 | Mittel |
| P22 | Relationship Privacy (Pfadlänge) | `PREF_TREE_PATH_LENGTH=2`: nahe Verwandte sichtbar, entfernte/unverwandte nicht. `=0`: deaktiviert. | M | 2, 3 | Mittel |
| P23 | Relationship Privacy (kein XREF) | Pfadlänge > 0, aber kein `PREF_TREE_ACCOUNT_XREF` → Fallback: alles sichtbar. | M | 2 | Mittel |
| P24 | Privacy in Suchergebnissen | Geschützte Person nicht in Suchergebnissen für Besucher. Für Mitglieder+: enthalten. | B, M, V | 2, 3 | Hoch |
| P25 | Personenseite: Vertraulich-Platzhalter | Besucher → „Vertraulich"/„Private". Name ggf. sichtbar (SHOW_LIVING_NAMES). | B, M, V | 3 | Hoch |
| P26 | Charts: Vertrauliche Boxen | Ahnentafel mit vertraulichen Personen → leere Boxen oder ausgeblendet. | B, M | 3 | Mittel |
| P27 | Bearbeiter: Datensatz bearbeiten | Fakt hinzufügen → pending change in DB. `auto_accept` → sofort akzeptiert. | E | 2, 3 | Hoch |
| P28 | Moderator: Änderungen akzeptieren | Moderator akzeptiert/verwirft Pending Change → DB-Status aktualisiert. | Mo | 2, 3 | Hoch |
| P29 | RESN locked / Zugriffsverbot | B/M: kein Edit. E auf RESN-locked: kein Edit. V: Edit erlaubt. `privacy, locked`: additiv. | B, M, E, V | 2, 3 | Hoch |
| P30 | Datensätze zusammenführen *(spezifikationsbasiert)* | MergeFactsAction: 6 Guard-Branches (record-not-found, same-record, tag-mismatch, pending-deletion) → Redirect zu MergeRecordsPage; Happy Path → change-Eintrag mit new_gedcom='' + Redirect zu ManageTrees | E, V | 2 | Mittel |
| P31 | Familienmitglieder bearbeiten *(spezifikationsbasiert)* | ChangeFamilyMembersAction: Vater-Austausch (B1+B5/EP1), Mutter-Entfernung (B2/EP2), Kind-Hinzufügen (B4/EP3), Kind-Entfernen (B3/EP4) → change-Einträge in DB; kein-Änderung (EP5) → change-count=0 | E, V | 2 | Mittel |
| P32 | Record-Ansicht und -Löschung *(spezifikationsbasiert)* | DeleteRecord: SOUR-Löschung → change-Tabellen-Assert new_gedcom='' (EP1); Familie-Kaskade: 1 Mitglied + keine Fakten → Familie mitgelöscht (EP5). GedcomRecordPage: INDI/FAM/SOUR/REPO → 302-Redirect (EP1×4 DataProvider); Non-Standard-Record → 200+Link-Header (EP2) | E, V | 2 | Mittel |
| P33 | Stammbaum-Privacy-Einstellungen *(spezifikationsbasiert)* | TreePrivacyAction: Mismatched-Arrays → HttpBadRequestException (EP3/EP4); Rule-Typ-Matrix (tag+xref EP5, tag-only EP6, xref-only EP7, beide-leer EP8) → default_resn-Tabellen-Assert; HIDE_LIVE_PEOPLE gespeichert (EP9) | V | 2 | Mittel |
| P34 | Stammbaum-Umnummerierung *(spezifikationsbasiert)* | RenumberTreeAction: keine Cross-Tree-Duplikate → Redirect, kein Umbenennen (B2/EP1); Cross-Tree-INDI-Duplikat → XREF in individuals umbenannt (B3/EP2, DB-Postcondition); Pending-Edits-Guard (B1/EP4) → Redirect, XREF bleibt erhalten | V | 2 | Niedrig |
| P35 | CLI Benutzer-Verwaltung *(spezifikationsbasiert)* | UserEdit CLI: alle 15 Guard-Branches — Konflikt-Flags (B1–B5), Create-Validierung (B6–B9 inkl. Random-PW), Edit-Validierung (B10–B11), Edit-Felder (B13–B15), Delete → Rückkürcode SUCCESS/FAILURE/INVALID | V | 2 | Mittel |
| P36 | CLI Einstellungs-Verwaltung *(spezifikationsbasiert)* | Settings-Commands (SiteSetting, TreeSetting, UserSetting, UserTreeSetting): --list/--delete-Konflikte (B1/B2), Delete-Branches (B4–B7), Get-Branches (B9–B11), Set-Branches (B12–B14), Entity-not-found (EP11) | V | 2 | Mittel |
| P37 | HTTP Benutzer-Bearbeitung *(spezifikationsbasiert)* | UserEditAction: user-not-found → HttpNotFoundException (B1); Duplikat-Email + Duplikat-Username → Redirect zurück zu UserEditPage (B5/B6, B7/B8); Self-Edit-Admin-Guard → admin-Status bleibt (B4); Passwort-Update/Kein-Update (B3); Path-Length-Reset bei leerem gedcomid (EP12) | V | 2 | Mittel |
| P38 | Account-Selbstverwaltung | AccountEdit: eigenes Profil-Formular → 200; AccountUpdate: Name/E-Mail/Passwort/Theme/Sprache speichern → Redirect; AccountDelete: eigenes Konto löschen → Session beendet, Redirect zu Login | M, E, V | 2, 3 | Mittel |
| P39 | Authentifizierung-Aktionen | LoginAction: korrekte/falsche Credentials → Redirect zu Baum / Fehler; Logout → Session ungültig + Redirect; RegisterAction: neues Konto anlegen → Bestätigungs-E-Mail / Redirect; PasswordRequestAction/ResetAction → Token erzeugt / Passwort gesetzt; VerifyEmail → Account aktiviert (ergänzt S32–S34 Seiten-Smoke) | B, M | 2, 3 | Hoch |
| P40 | Änderungsverwaltung (HTTP-Handler) | PendingChanges: Liste offener Änderungen → 200 + Einträge; PendingChangesAcceptChange/AcceptRecord → DB-Status 'accepted'; PendingChangesRejectChange/RejectRecord → DB-Status 'rejected' oder gelöscht (ergänzt P28 Playwright-Systemtest auf Handler-Ebene) | Mo, V | 2 | Hoch |
| P41 | Datensatz-Zusammenführung (vollständig) | MergeRecordsPage: Vergleichs-Formular zweier Records → 200; MergeRecordsAction: Records zusammenführen → ein Record per change-Tabelle gelöscht, einer aktualisiert (verschieden von P30 Fakten-Merge) | E, V | 2 | Mittel |

> **Querschnittsanforderung Theme-Abdeckung (Phase 5c):** Jeder Systemtest-Testfall (Teststufe 3) für tree-gebundene Seiten
> MUSS alle 5 Standard-Themes abdecken: `webtrees`, `clouds`, `colors`, `fab`, `xenea`. Theme-Abdeckung ist keine eigene
> Testbedingung mehr (S25 aufgelöst), sondern eine strukturelle Eigenschaft jedes Testfalls. Ausnahmen: `auth.spec.ts` (S33, S34)
> und `login.spec.ts` (S32) — nicht tree-gebunden, kein Theme-Loop.

> **E2E-Gap-Analyse (2026-03-27):** Abgleich der vorhandenen Playwright-Specs (`layer4-e2e/tests/`)
> mit den 170 GET-Routen in `WebRoutes.php` (webtrees Upstream). Von ~47 für eingeloggte
> Nicht-Admin-Nutzer erreichbaren Seiten-Routen werden 8 URLs in den bestehenden Specs
> abgedeckt. S26–S39 schließen die wichtigsten Lücken. Nicht aufgenommen: Editor-Formulare
> (Add/Edit-Seiten, erfordern Schreibrechte), Admin-Panel-Seiten, AJAX-Endpoints (TomSelect),
> Asset-Routen. Korrektur: S24 (Familienseite) war fehlzugeordnet — `navigation.spec.ts`
> testet `/tree/demo/family-list` (→ S20), nicht `/tree/demo/family/{xref}`.

---

## Feature-Matrix: Sicherheit (SEC)

> Sicherheitstests prüfen, ob die Schutzmechanismen des webtrees-Upstream-Codes in einer
> produktionsidentischen Distribution-Instanz greifen. Eigener Container-Build, eigene
> Datenbank, Setup-Wizard via Playwright. Zwei Testverfahren: Shell-Assertions (Dateisystem)
> und Playwright-HTTP-Tests (Zugriffskontrolle, Header).

| # | Feature | Abgeleitete Anforderung | Prio | Status |
|---|---------|-------------------------|------|--------|
| SEC-H01 | `.htaccess` Existenz | `data/.htaccess` in Distribution vorhanden | Hoch | Grün |
| SEC-H02 | `.htaccess` Inhalt | Enthält `Require all denied` (Apache 2.4) | Hoch | Grün |
| SEC-H03 | HTTP-Zugriff `data/` blockiert | `GET /data/` → HTTP 403 | Hoch | Grün |
| SEC-H04 | HTTP-Zugriff `config.ini.php` blockiert | `GET /data/config.ini.php` → 403 | Hoch | Grün |
| SEC-H05 | HTTP-Zugriff `data/media/` blockiert | `GET /data/media/` → 403 | Hoch | Grün |
| SEC-H06 | URL-Encoding umgeht `.htaccess` nicht | Encoding-Varianten → jeweils 403 | Hoch | Grün |
| SEC-D01 | `data/index.php` Existenz | Datei in Distribution vorhanden | Mittel | Grün |
| SEC-D02 | `data/index.php` Redirect-Logik | Enthält `header('Location: ../index.php')` | Mittel | Grün |
| SEC-C01 | Config PHP-Guard | `config.ini.php` hat `; <?php return; ?>` als erste Zeile | Hoch | Grün |
| SEC-C02 | Config DB-Credentials | `config.ini.php` enthält dbhost, dbuser, dbpass, dbname | Hoch | Grün |
| SEC-C03 | Config Datei-Permissions | world-readable (644) — kein `chmod` im Wizard | Hoch | Rot (Upstream-Befund) |
| SEC-M01 | Direkter Media-Zugriff blockiert | `GET /data/media/<datei>` → 403 | Mittel | Grün |
| SEC-M02 | Media-Route ohne Auth | App-Route als Visitor → 302 (Redirect zu Login) | Mittel | Grün |
| SEC-M03 | Media-Route mit Auth | App-Route als Member → 200 | Mittel | Grün |
| SEC-PUB01 | `public/index.php` Existenz | Datei in Distribution vorhanden | Mittel | Grün |
| SEC-PUB02 | `public/index.php` keine PHP-Execution | Statischer Inhalt (Source sichtbar, nicht ausgeführt) | Mittel | Grün |
| SEC-PUB03 | Kein Directory Listing `/public/` | `GET /public/` → kein Datei-Listing | Mittel | Grün |
| SEC-PUB04 | Path-Traversal blockiert | `GET /public/../data/config.ini.php` → kein Dateiinhalt | Mittel | Grün |
| SEC-W01 | Wizard nach Setup gesperrt | Setup-URL → kein Setup-Formular | Hoch | Grün |
| SEC-WZ01 | Wizard erscheint bei Erstaufruf | Frische Instanz → Setup-Formular | Hoch | Grün |
| SEC-WZ02 | Wizard prüft Schreibrechte | Schritt 2: data/ beschreibbar | Hoch | Grün |
| SEC-WZ03 | Wizard erzeugt `config.ini.php` | Datei existiert nach Wizard-Abschluss | Hoch | Grün |
| SEC-WZ04 | Wizard sperrt sich selbst | Kein erneuter Setup nach Abschluss | Hoch | Grün |
| SEC-HDR01 | `X-Content-Type-Options` | Header = `nosniff` | Niedrig | Grün |
| SEC-HDR02 | `X-Frame-Options` | Header = `SAMEORIGIN` oder `DENY` | Niedrig | Grün |
| SEC-HDR03 | `Referrer-Policy` | Header gesetzt (nicht leer) | Niedrig | Grün |
| SEC-HDR04 | Server-Banner | Apache-Versionsstring sichtbar | Niedrig | Rot (Deployment-Empfehlung) |
| SEC-BOT01 | UA-basierte Bot-Blockierung *(spezifikationsbasiert, DNS/ASN ausgeklammert)* | BadBotBlocker: BAD_ROBOTS-Sampling DataProvider (5 Kategorien: SEO, AI, Security → 406); WordPress-Pfade DataProvider (/wp-*, /xmlrpc.php → 406); Cookie-Heuristik EP8/EP9 (mit/ohne Cookies); leerer UA → 406; legitimer UA → 200. DNS-Zweige (B3/B4) dauerhaft ausgeklammert. | 15 | Hoch |
| SEC-UTL01 | Web-Assets & Utility-Endpoints *(spezifikationsbasiert)* | `UtilityEndpointsIntegrationTest` ✅: DataProvider-Batch (FaviconIco/WebmanifestJson/BrowserconfigXml/AppleTouchIconPng/AdsTxt/AppAdsTxt → 200 + Content-Type); RobotsTxt → 200 + text/plain + User-agent + Disallow; Ping → 200 oder 503; Ping-Body = OK/WARNING/ERROR. | Niedrig | Grün |

---

## Feature-Matrix: Datenpflege / Erfassung (E)

> Alle Handler, die GEDCOM-Datensätze via Web-UI erzeugen oder ändern.
> Abgrenzung: G = Datenformat/Import/Export; S = Ansicht/Navigation; P = Zugriffskontrolle/Auth.
> Rollen: B = Besucher, M = Mitglied, E = Bearbeiter, Mo = Moderator, V = Verwalter.
> Teststufen: 2 = Komponentenintegrationstest, 3 = Systemtest.

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| E01 | Person/Familie anlegen & verknüpfen | AddChildToIndividual*/Action, AddParentToIndividual*/Action, AddSpouseToIndividual*/Action, LinkSpouseToIndividual*/Action: INDI mit Eltern/Kind/Partner anlegen → pending change; AddChildToFamily*/Action, AddSpouseToFamily*/Action, LinkChildToFamily*/Action: FAM-Mitglieder hinzufügen/verknüpfen | E, V | 2, 3 | Hoch |
| E02 | Fakten bearbeiten | EditFactPage/AddNewFact: Fakt anlegen/bearbeiten → pending change; DeleteFact → GEDCOM ohne Fakt in change-Tabelle; CopyFact/PasteFact: Fakt in Zwischenablage + Einfügen; SelectNewFact: GEDCOM-Tag auswählen | E, V | 2, 3 | Hoch |
| E03 | Rohdaten-Edit (Raw GEDCOM) | EditRawFactPage/Action: einzelner Fakt als GEDCOM-Text → change; EditRawRecordPage/Action: gesamter Record als GEDCOM-Text → change; EditRecordPage/Action: Record via Formular → change | E, V | 2, 3 | Mittel |
| E04 | Nebenrecords anlegen (NOTE / SOUR / REPO / SUBM) | CreateNoteModal/Action → NOTE-XREF; EditNotePage/Action → Notiz change; CreateSourceModal/Action → SOUR-XREF; CreateRepositoryModal/Action → REPO-XREF; CreateSubmissionModal/Action, CreateSubmitterModal/Action → Einreicher-Records | E, V | 2, 3 | Mittel |
| E05 | Medienobjekte anlegen & verknüpfen | CreateMediaObjectModal/Action/FromFile: OBJE-Record anlegen → DB-Eintrag; AddMediaFileModal/Action: Mediendatei zu OBJE hinzufügen → change; LinkMediaToRecordAction/IndividualModal/FamilyModal/SourceModal: OBJE mit anderem Record verknüpfen → change | E, V | 2, 3 | Mittel |
| E06 | Sortierung (Reorder) | ReorderChildrenPage: Kindreihenfolge → change; ReorderNamesPage: Namenreihenfolge → change; ReorderFamiliesPage: Familienreihenfolge → change; ReorderMediaPage/Action, ReorderMediaFilesPage/Action: Medien/Mediendatei-Reihenfolge | E, V | 2, 3 | Niedrig |
| E07 | Mediendatei-Download & Thumbnail | MediaFileDownload: Datei abrufen → 200 + korrekter Content-Type; MediaFileThumbnail: Thumbnail generieren → 200 + image/* | M, E, V | 2, 3 | Mittel |
| E08 | TomSelect & AutoComplete (Edit-Hilfs-APIs) | TomSelectIndividual/MediaObject/Source/Repository/Note/SharedNote: AJAX-Dropdown → JSON mit passenden Records; AutoCompleteCitation: Zitations-Vorschläge → JSON; AutoCompleteFolder: Ordner-Vorschläge für Medienpfad → JSON | E, V | 2 | Niedrig |

---

## Feature-Matrix: Administration (A)

> Admin-Only-Operationen: Stammbaum-Verwaltung, Modul-Konfiguration, Site-Einstellungen, System-Werkzeuge.
> Getrennt von fachlichen Features (E, G, S, P). Rolle: V = Verwalter / Admin.
> Teststufen: 2 = Komponentenintegrationstest, 3 = Systemtest.

| # | Feature | Abgeleitete Anforderung | Teststufe | Prio |
|---|---|---|---|---|
| A01 | Stammbaum-Management | CreateTreePage/Action: neuen Baum anlegen → gedcom_id erzeugt; DeleteTreeAction: Baum + alle Records gelöscht; ManageTrees → Übersicht 200; MergeTreesPage/Action: Records aus Baum 2 nach Baum 1 verschoben | 2, 3 | Hoch |
| A02 | Stammbaum-Import (HTTP-Formular) | ImportGedcomPage: Upload-Formular → 200; ImportGedcomAction: GEDCOM-Datei hochladen → Import angestoßen (verschieden von CLI GedcomLoad G25) | 2, 3 | Hoch |
| A03 | Stammbaum-Export (HTTP-Formular) | ExportGedcomPage: Export-Formular → 200; ExportGedcomClient: Browser-Download → GEDCOM/ZIP-Response; ExportGedcomServer: serverseitige Datei gespeichert (verschieden von CLI Export G26) | 2, 3 | Mittel |
| A04 | Stammbaum-Präferenzen | TreePreferencesPage: Einstellungsformular → 200; TreePreferencesAction: Preference-Werte (HIDE_LIVE_PEOPLE, REQUIRE_AUTHENTICATION usw.) speichern → DB-Update gedcom_setting | 2, 3 | Mittel |
| A05 | Modul-Konfiguration | ModulesAllPage/Action: Module aktivieren/deaktivieren/sortieren; alle Modules*Page/Action-Handler (~46): Charts/Maps/Reports/Blocks/Themes konfigurieren → module_setting-Tabelle | 2, 3 | Niedrig |
| A06 | Site-Präferenzen | SitePreferencesPage/Action: globale Einstellungen (Standardbaum, Zeitzone, E-Mail-Config, Registrierung, Theme) → site_setting-Tabelle | 2, 3 | Mittel |
| A07 | Benutzerverwaltung Admin | UserListPage: Benutzerliste → 200 + alle Nutzer sichtbar; UsersCleanupPage/Action: inaktive Nutzer ohne Zuordnung → Übersicht + Batch-Löschen | 2, 3 | Mittel |
| A08 | Medienverwaltung Admin | AdminMediaFileDownload/Thumbnail: Admin-Zugriff auf Mediendateien; FixLevel0MediaPage/Action: Level-0-Medien-Referenzen korrigieren → DB-Update; ManageMediaPage/Action: Admin-Medienliste (Backend-Seite, verschieden von ManageMediaData-API S49) | 2, 3 | Niedrig |
| A09 | Datenpflege-Werkzeuge | DataFixPage/Choose/Select/Update: Datenpflege-Script auswählen + anwenden → DB-Änderungen; CleanDataFolder: temporäre Dateien bereinigen; FindDuplicateRecords → XREFs mit Duplikaten gelistet; AddUnlinkedPage/Action → neues INDI ohne FAM anlegen | 2, 3 | Niedrig |
| A10 | Protokolle & Monitoring | PendingChangesLogPage/Data/Action/Delete/Download: Change-Log abrufen/filtern/löschen/exportieren; SiteLogsDownload: Site-Log als CSV; PhpInformation: phpinfo() → 200 | 2, 3 | Niedrig |
| A11 | System & Upgrade | UpgradeWizardPage/Confirm: Update-Wizard Schritte → Versions-Check + Download; CheckForNewVersionNow → Versions-Check-Response; Masquerade: Admin übernimmt Nutzer-Session → SessionUser geändert; BroadcastPage/Action: Nachricht an alle Nutzer; EmailPreferencesPage/Action: SMTP-Konfiguration testen | 2, 3 | Niedrig |

---

## Feature-Matrix: Kommunikation (K)

> Nutzer-zu-Nutzer- und Nutzer-zu-Admin-Kommunikation.
> S36 deckt ContactPage als Seiten-Smoke — K01 ergänzt die Action-Verarbeitung.

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| K01 | Kontaktformular | ContactPage: Formular → 200 (S36 Smoke); ContactAction: Nachricht abschicken → E-Mail-Versand / Fehler (kein SMTP im Test-Stack: Response-Status prüfen) | B, M | 3 | Niedrig |
| K02 | Benutzer-Nachrichten | MessagePage: Nachrichtenformular → 200; MessageAction: Nachricht an Nutzer senden → Bestätigung / Redirect; MessageSelect: Empfänger aus Nutzerliste auswählen | M, E, V | 3 | Niedrig |

---

## Feature-Matrix: Querschnitts-Utilities (U)

> Utility-Klassen ohne Domänenzuordnung — direkt im root-Namespace `Fisharebest\Webtrees`.
> Upstream-Tests vorhanden, aber Layer-3-Lücken durch CRAP-Analyse identifiziert.

| # | Feature | Abgeleitete Anforderung | Teststufe | Prio |
|---|---|---|---|---|
| U01 | Validator (root-Paket) *(spezifikationsbasiert)* | `Validator.float()`: EP/BVA-Matrix (EP1 float-String→float, EP2 integer-String→float, EP3 int-Typ→float, EP4 negativ, EP5 zero-BV, EP-inv1 non-numeric→throw, EP-inv2 non-numeric+default, EP-miss1 fehlt→throw, EP-miss2 fehlt+default); `__construct` UTF-8: key-invalid→throw, value-invalid→throw, serverParams-ASCII→kein-throw; `integer()` negativer-String→-42; `array()` non-array-non-null→throw | 2 | Mittel |

---

## Entscheidung: Reverse-Engineering-Quellen

| Quelle | Einsatz | Methode |
|---|---|---|
| **Code-first** | Primär — alle Anforderungen werden aus dem Code abgeleitet | Service-API → Feature, Route → Handler → Testbedingung |
| **Gap-Analyse existierende Tests** | Priorisierung — Stub-Tests = ungetestet = hohe Prio | Assertionsdichte messen, Stubs identifizieren |
| **GEDCOM 5.5.1 Standard** | Compliance — Tag-Abdeckung, Encoding, Date-Formate | Element-Klassen vs. Standard-Tags abgleichen |

Die Domäne **Beziehungsberechnung** ist bewusst als niedrigere Priorität eingestuft.
**Privacy/Zugriffskontrolle** wurde in Phase 11 vollständig umgesetzt (P01–P29, siehe
Feature-Matrix oben).
