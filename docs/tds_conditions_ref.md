<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Testbedingungen — Feature-Matrizen und RE-Methodik

Dieses Dokument enthält alle Testbedingungen (ISTQB: Testbedingungen), organisiert als Feature-Matrizen,
sowie die Reverse-Engineering-Methodik, mit der sie abgeleitet wurden.

**Querverweise:**
- [Abdeckungsmatrix](tds_coverage_ref.md)
- [Testentwurfsverfahren](tds_methodik_spec.md)
- [Designentscheidungen](tp_decisions_spec.md)

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

In den Feature-Matrizen dieses Dokuments wird die Teststufen-Spalte als ISTQB-Nummer (1–3) geführt
(historisch gewachsen, Bezug: `tp_decisions_spec.md`). In der Abdeckungsmatrix
([`tds_coverage_ref.md`](tds_coverage_ref.md)) werden die Spalten per Layer (L2/L3/L4) benannt,
weil die Layer die physische Testinfrastruktur beschreiben, in der die Tests laufen.

---

## Domänen-Navigation

[G](#g) · [S](#s) · [P](#p) · [SEC](#sec) · [E](#e) · [A](#a) · [K](#k) · [U](#u) · [M](#m)

> **Platzhalter:** Die Domäne `M` (Middleware) wird in Plan-Phase 5.1 angelegt; der Anker ist bis dahin leer.

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

> **Aktueller Stand (2026-05-24):** Mess-Basis ist Upstream-`main` (Commit `6966db16a6`)
> — siehe [`tp_ratchet_spec.md`](tp_ratchet_spec.md#layer-2--upstream-unit-tests-teststufe-1-make-test-unit).
> Die ursprüngliche Gap-Analyse vom 2026-03-26 (~95 % Stub-Tests) ist als historischer
> Vergleichspunkt archiviert unter
> [`coverage-runs/historical/2026-03-26_gap-analyse.md`](coverage-runs/historical/2026-03-26_gap-analyse.md);
> der Snapshot vom 2026-04-11
> ([`coverage-runs/2026-04-11_gap-analyse-fork.md`](coverage-runs/2026-04-11_gap-analyse-fork.md))
> bezieht sich auf einen damals untersuchten Branch mit zusätzlichen Test-Doubles und ist
> nicht mehr Mess-Basis.

---

<a id="g"></a>

## Feature-Matrix: GEDCOM Import/Export

> Abgeleitet aus Code-Analyse von `GedcomImportService`, `GedcomExportService`,
> `GedcomEncodingFilter`, `Elements/`, Request-Handlern und dem GEDCOM 5.5.1-Standard.
>
> Teststufen: 1 = Komponententest, 2 = Komponentenintegrationstest, 3 = Systemtest.
> Layer-Zuordnung: siehe [Mapping-Tabelle am Dokumentanfang](#teststufen-und-layer--nomenklatur).

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
| G27 | Mediendatei-Upload URL *(strukturbasiert)* | URL-basierter Upload via MediaFileService → Datei lokal vorhanden und DB-Eintrag erzeugt. Zusätzlich: Extension-Blocklist (`.php`, `.phtml`, `.htaccess` etc.) → Upload wird mit Exception abgewiesen *(L0-strongest-mitigation)*. | 2 | Mittel |
| G28 | OBJE-Metadaten bearbeiten *(spezifikationsbasiert)* | EditMediaFileAction::handle — Happy Path: gültige fact_id + title+type → change-Tabelle enthält pending GEDCOM mit neuem Titel (DB-Postcondition); Fact-not-found-Guard (fact_id='') → Redirect zu TreePage | 2 | Niedrig |
| G29 | GEDCOM-Bearbeitungsservice *(spezifikationsbasiert)* | GedcomEditService: editLinesToGedcom — Mehrzeilenwerte (CONT), Sub-Level-Struktur, Leerstring-Handling; insertMissingLevels — Subtag-Expansion, Level-1/2-Pfade | 2 | Niedrig |
| G30 | Mediendatei-Upload (HTTP-Formular) | UploadMediaPage/UploadMediaAction: Datei-Upload via Web-Formular → Datei gespeichert, OBJE-Record in DB erzeugt (verschieden von G27: URL-basierter Upload via MediaFileService) | 2, 3 | Mittel |
| G31 | GEDCOM-Import via CLI | `TreeImport` CLI-Command (`tree-import <tree-name> <gedcom-file>`): liest GEDCOM-Datei, löscht bestehenden Baum-Inhalt, importiert via `GedcomImportService` mit Optionen `--encoding`/`--keep-media`/`--conc-spaces`/`--gedcom-media-path`; Fail-Fast bei unbekanntem Baum und nicht-existierender Datei → FAILURE. Unterscheidet sich von G25 `GedcomLoad` (HTTP-RequestHandler) und A02 `ImportGedcomPage/Action` (HTTP-Formular). | 2 | Hoch |

---

<a id="s"></a>

## Feature-Matrix: Suche und Navigation

> Abgeleitet aus Code-Analyse von `SearchService` (20 public Methods),
> 9 Search-Handlern, 13 Chart-Modulen, 10 List-Modulen, 16 AutoComplete-Handlern.
>
> Teststufen: 1 = Komponententest, 2 = Komponentenintegrationstest, 3 = Systemtest.
> Layer-Zuordnung: siehe [Mapping-Tabelle am Dokumentanfang](#teststufen-und-layer--nomenklatur).

| # | Feature | Abgeleitete Anforderung | Teststufe | Prio |
|---|---|---|---|---|
| S01 | Allgemeine Suche (Personen) | Suchbegriff → passende Individuen zurückgegeben | 2 | Hoch |
| S02 | Allgemeine Suche (Familien) | Suchbegriff → passende Familien zurückgegeben | 2 | Hoch |
| S03 | Allgemeine Suche (Quellen, Notizen, Repos) | Suchbegriff → passende Records je Typ | 2 | Mittel |
| S04 | Query-Parsing | Anführungszeichen, Mehrwort-Suche, CJK-Splitting korrekt | 1 | Hoch |
| S05 | Erweiterte Suche (Felder) | 75 GEDCOM-Felder → Feld-spezifische Filterung | 2, 3 | Hoch |
| S06 | Erweiterte Suche (Datum-Modifikatoren) | Geburtsdatum ±5 Jahre → korrekte Eingrenzung | 2, 3 | Hoch |
| S07 | Phonetische Suche (Russell) | Russell-Soundex → ähnlich klingende Namen gefunden | 2, 3 | Mittel |
| S08 | Phonetische Suche (Daitch-Mokotoff) | DM-Soundex → osteuropäische Namensvarianten gefunden | 2, 3 | Mittel |
| S09 | Quick-Search (XREF) | "I123" eingeben → direkt zum Record weitergeleitet | 3 | Mittel |
| S10 | Paginierung | Suche mit >50 Ergebnissen → Offset/Limit korrekt | 2, 3 | Mittel |
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
| S25 | Navigation: HEAD-Record-Seite | `HeaderPage`-Handler: HEAD-Record-XREF → sichtbarer Header mit korrektem Slug → 200; abweichender Slug → 301-Redirect; unbekannte XREF → `HttpNotFoundException`. | 2 | Niedrig |
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
| S41 | Statistikdaten-Abfragen *(spezifikationsbasiert)* | StatisticsData: countEventsByMonth whereBetween-Branch (EP5 alle Jahre, EP6 Jahresfilter 1900–2000, EP8 invertierter Bereich=leer); commonSurnames Sort-EP-Matrix (DataProvider alpha/count/rcount, EP13 threshold-Filter); parentsQuery Sex-EP (DataProvider F→WIFE, M→HUSB) | V | 2, 3 | Mittel |
| S42 | Such-HTTP-Handler *(spezifikationsbasiert)* | SearchGeneralPage::handle → Single-Result-Redirect EP2/EP4 (Individual/Family → 302), Default-Fallback EP8, Multi-Result EP1/EP3 (200 OK) | 6 | Mittel |
| S43 | Report-Generierung HTTP *(spezifikationsbasiert)* | ReportSetupPage: Setup-Formular → 200 OK; ReportGenerate: format='PDF'→application/pdf (EP2), destination='download'→content-disposition:attachment (EP6), unbekannter Report→redirect (B1), HTML-Ausgabe (EP1) | 8 | Mittel |
| S44 | Report-Parser Erweitert *(spezifikationsbasiert, Pragmatisch C)* | ReportParserGenerate: Vorfahren-Bericht (EP1 addAncestors→non-empty HTML), Nachkommen-Bericht (EP3 addDescendancy→non-empty HTML), Individual-Bericht mit Fakten+Bild (EP7 factsStartHandler+imageStartHandler→non-empty HTML) | 3 | Mittel |
| S45 | Report-Primitive PDF/HTML *(spezifikationsbasiert+strukturbasiert)* | ReportHtml*: fill/border/newline Assertion-Tests (TextBox: bgcolor, border:solid, X-Pos; Cell: border='1'/'T'/''/ptp/bgcolor); ReportPdfImage: line='N' Y-Advance, statisch, CURRENT_POSITION; CRAP: ReportPdfImage::render eliminiert | 23 | Mittel |
| S46 | Homepage-Block-Module *(spezifikationsbasiert, Pragmatisch C)* | SlideShow: EP1 Standardblock→non-empty; TopSurnames info_style-EP-Matrix via DataProvider (EP4 table, EP5 list, EP6 tagcloud, EP6b array) → assertNotEmpty+HTML; übrige Block-Module: Smoke-String-Tests | 14 | Niedrig |
| S47 | Interaktiver Stammbaum *(spezifikationsbasiert, Pragmatisch C)* | TreeView: getDetails X1030→XREF im Output (EP5 Partner-Validierung); getIndividuals 'p'-Request→assertNotEmpty+HTML (EP1 Person mit Eltern); getIndividuals 'c'-Request→assertNotEmpty+HTML (EP3 Person mit Kindern) | 3 | Mittel |
| S48 | Standortdaten-Import Admin *(spezifikationsbasiert)* | MapDataImportAction: EP1+EP5 option=add korrektes CSV (`;`-Trenner, Level-Format)→DB-Postcondition lat/lng via assertEqualsWithDelta; EP6 Null-Island (0,0) multi-level Ort→gefiltert, place_location leer; 2 Smoke-Tests für malformed CSV (Fehlerresilienz) | 4 | Mittel |
| S49 | Medienverwaltungsliste Admin *(spezifikationsbasiert)* | ManageMediaData: `files`-EP-Matrix (local/external/unused) per Einzeltest, JSON-Struktur `{data, recordsTotal, recordsFiltered}` per Assertion; unused-Branch (handleCollection) gesondert abgedeckt | 3 | Mittel |
| S50 | Hilfetexte *(spezifikationsbasiert)* | HelpText::handle → alle 12 Topic-IDs per DataProvider (200 OK), unbekannte ID → 200 + generischer Hilfetext | 2, 3 | Niedrig |
| S51 | Sprachauswahl-Handler | `SelectLanguage`-Handler: User-facing Sprachwechsel. Sprache-Code aus POST/Query → in Session persistiert (`Session::put('language', …)`) und an der User-Preference des Anfragenden gesetzt; antwortet mit 204 (No Content). Ergänzt M13 (`UseLanguage`-Middleware = Sprachauswahl-Auswertung). | 2 | Mittel |
| S52 | Standortdaten-Verwaltung (CRUD) | MapDataList: Übersicht → 200; MapDataAdd/Edit/Save: Formular + Speichern → DB-Update place_location; MapDataDelete/DeleteUnused: Einträge löschen; MapDataExportCSV → CSV-Download (ergänzt S48 Import) | 2, 3 | Niedrig |
| S53 | Legacy-URL-Weiterleitungen | ~27 Redirect*-Handler (RedirectIndividualPhp, RedirectFanChartPhp, RedirectCalendarPhp usw.) leiten alte webtrees 1.x-URLs auf aktuelle Routen um → HTTP 301/302, kein 404 | 2, 3 | Niedrig |

---

<a id="p"></a>

## Feature-Matrix: Datenschutz & Zugriffskontrolle

> Abgeleitet aus Code-Analyse von `Individual::canShow()`, `Individual::canShowByType()`,
> `Individual::isDead()`, `GedcomRecord::canEdit()`, `Fact::canEdit()`,
> Tree-Preferences (Privacy-Einstellungen), User-Preferences (Relationship Privacy).
>
> Teststufen: 2 = Komponentenintegrationstest, 3 = Systemtest.
> Layer-Zuordnung: siehe [Mapping-Tabelle am Dokumentanfang](#teststufen-und-layer--nomenklatur).
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
| P30 | Datensätze zusammenführen *(spezifikationsbasiert)* | MergeFactsAction: 6 Guard-Branches (record-not-found, same-record, tag-mismatch, pending-deletion) → Redirect zu MergeRecordsPage; Happy Path → change-Eintrag mit new_gedcom='' + Redirect zu ManageTrees | E, V | 2, 3 | Mittel |
| P31 | Familienmitglieder bearbeiten *(spezifikationsbasiert)* | ChangeFamilyMembersAction: Vater-Austausch (B1+B5/EP1), Mutter-Entfernung (B2/EP2), Kind-Hinzufügen (B4/EP3), Kind-Entfernen (B3/EP4) → change-Einträge in DB; kein-Änderung (EP5) → change-count=0 | E, V | 2 | Mittel |
| P32 | Record-Ansicht und -Löschung *(spezifikationsbasiert)* | DeleteRecord: SOUR-Löschung → change-Tabellen-Assert new_gedcom='' (EP1); Familie-Kaskade: 1 Mitglied + keine Fakten → Familie mitgelöscht (EP5). GedcomRecordPage: INDI/FAM/SOUR/REPO → 302-Redirect (EP1×4 DataProvider); Non-Standard-Record → 200+Link-Header (EP2) | E, V | 2 | Mittel |
| P33 | Stammbaum-Privacy-Einstellungen *(spezifikationsbasiert)* | TreePrivacyAction: Mismatched-Arrays → HttpBadRequestException (EP3/EP4); Rule-Typ-Matrix (tag+xref EP5, tag-only EP6, xref-only EP7, beide-leer EP8) → default_resn-Tabellen-Assert; HIDE_LIVE_PEOPLE gespeichert (EP9) | V | 2 | Mittel |
| P34 | Stammbaum-Umnummerierung *(spezifikationsbasiert)* | RenumberTreeAction: keine Cross-Tree-Duplikate → Redirect, kein Umbenennen (B2/EP1); Cross-Tree-INDI-Duplikat → XREF in individuals umbenannt (B3/EP2, DB-Postcondition); Pending-Edits-Guard (B1/EP4) → Redirect, XREF bleibt erhalten. Zusätzlich: xref-Format-Guard — eingehende ungültige XREF-Formate werden abgewiesen, bevor Umbenennungen erfolgen *(verhindert SQL-Injection oder Schema-Korruption über manipulierte XREFs)*. | V | 2 | Niedrig |
| P35 | CLI Benutzer-Verwaltung *(spezifikationsbasiert)* | UserEdit CLI: alle 15 Guard-Branches — Konflikt-Flags (B1–B5), Create-Validierung (B6–B9 inkl. Random-PW), Edit-Validierung (B10–B11), Edit-Felder (B13–B15), Delete → Rückkürcode SUCCESS/FAILURE/INVALID | V | 2 | Mittel |
| P36 | CLI Einstellungs-Verwaltung *(spezifikationsbasiert)* | Settings-Commands (SiteSetting, TreeSetting, UserSetting, UserTreeSetting): --list/--delete-Konflikte (B1/B2), Delete-Branches (B4–B7), Get-Branches (B9–B11), Set-Branches (B12–B14), Entity-not-found (EP11) | V | 2 | Mittel |
| P37 | HTTP Benutzer-Bearbeitung *(spezifikationsbasiert)* | UserEditAction: user-not-found → HttpNotFoundException (B1); Duplikat-Email + Duplikat-Username → Redirect zurück zu UserEditPage (B5/B6, B7/B8); Self-Edit-Admin-Guard → admin-Status bleibt (B4); Passwort-Update/Kein-Update (B3); Path-Length-Reset bei leerem gedcomid (EP12) | V | 2, 3 | Mittel |
| P38 | Account-Selbstverwaltung | AccountEdit: eigenes Profil-Formular → 200; AccountUpdate: Name/E-Mail/Passwort/Theme/Sprache speichern → Redirect; AccountDelete: eigenes Konto löschen → Session beendet, Redirect zu Login | M, E, V | 2, 3 | Mittel |
| P39 | Authentifizierung-Aktionen | LoginAction: korrekte/falsche Credentials → Redirect zu Baum / Fehler; Logout → Session ungültig + Redirect; RegisterAction: neues Konto anlegen → Bestätigungs-E-Mail / Redirect; PasswordRequestAction/ResetAction → Token erzeugt / Passwort gesetzt; VerifyEmail → Account aktiviert (ergänzt S32–S34 Seiten-Smoke) | B, M | 2, 3 | Hoch |
| P40 | Änderungsverwaltung (HTTP-Handler) | PendingChanges: Liste offener Änderungen → 200 + Einträge; PendingChangesAcceptChange/AcceptRecord → DB-Status 'accepted'; PendingChangesRejectChange/RejectRecord → DB-Status 'rejected' oder gelöscht (ergänzt P28 Playwright-Systemtest auf Handler-Ebene) | Mo, V | 2, 3 | Hoch |
| P41 | Datensatz-Zusammenführung (vollständig) | MergeRecordsPage: Vergleichs-Formular zweier Records → 200; MergeRecordsAction: Records zusammenführen → ein Record per change-Tabelle gelöscht, einer aktualisiert (verschieden von P30 Fakten-Merge) | E, V | 2, 3 | Mittel |
| P42 | CLI Benutzer-Listing | `UserList` CLI-Command (`user-list`): gibt alle registrierten Benutzer zeilenweise auf STDOUT aus — Spalten `user_id`, `user_name`, `real_name`, `email`, sowie aggregierte `user_setting`-Werte (Admin/Verified/Approved). Primär nicht-destruktiv, Lesezugriff. Unterscheidet sich von A07 `UserListPage` (HTTP-Admin-Seite) und P35 `UserEdit` (CLI-Bearbeitung). | V | 2 | Niedrig |
| P43 | Logout-Flow | `Logout`-Handler: angemeldeter Benutzer → 302 Redirect zu HomePage + `Auth::id()` ist null (Session-Logout); Gast → 302 Redirect zu HomePage (idempotent, kein Logout-Effekt); Ajax-Request (`X-Requested-With: XMLHttpRequest`) → 204 No Content + Auth::id() ist null. Eigenständiger Flow neben P39 (LoginAction/RegisterAction/PasswordReset). | M, E, V | 2 | Mittel |
| P44 | Login Rate-Limiting | `LoginAction` Rate-Limit-Schutz: zu viele fehlgeschlagene Anmeldeversuche innerhalb eines Zeitfensters → `HttpTooManyRequestsException` (HTTP 429). Verhalts-Definitiv: Rate-Limit-Zähler greift pro IP/User unabhängig vom Erfolg/Misserfolg der Credentials. Eigenständiges Schutzverhalten neben P39 (Auth-Aktionen). | B | 2 | Hoch |

> **Querschnittsanforderung Theme-Abdeckung (Phase 5c):** Jeder Systemtest-Testfall (Teststufe 3) für tree-gebundene Seiten
> MUSS alle 5 Standard-Themes abdecken: `webtrees`, `clouds`, `colors`, `fab`, `xenea`. Theme-Abdeckung ist eine strukturelle
> Eigenschaft jedes Testfalls — keine eigene Testbedingung. Ausnahmen: `auth.spec.ts` (S33, S34) und `login.spec.ts` (S32) —
> nicht tree-gebunden, kein Theme-Loop. *(Hinweis: Die ID `S25` wurde 2026-05-24 für `HeaderPage` neu vergeben, die historisch
> für die Theme-Abdeckungs-Anforderung benutzte und seit Phase 5c aufgelöste Belegung ist damit überschrieben.)*

> **E2E-Gap-Analyse (archiviert):** Die ursprüngliche 2026-03-27-Analyse (8 von ~47
> Nicht-Admin-Routen in Specs abgedeckt, S26–S39 als Lückenschluss) ist wörtlich archiviert
> unter [`coverage-runs/historical/2026-03-27_e2e-gap.md`](coverage-runs/historical/2026-03-27_e2e-gap.md).
> Aktuelle L4-Kennzahlen (Stand 2026-04-11: 26 Specs, `Stub 0 / Smoke 11 / Substantial 15`):
> [`coverage-runs/2026-04-11_gap-analyse-fork.md`](coverage-runs/2026-04-11_gap-analyse-fork.md) §3.1 und §3.6.

---

<a id="sec"></a>

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
| SEC-WZ05 | Wizard Reinstall-Pfad validiert `wtpass` | `SetupWizard::createConfigFile()` Reinstall-Branch: `$_POST['wtpass']` darf nicht direkt verwendet werden; stattdessen muss der per `Validator` geprüfte Wert (`$data['wtpass']`) verwendet werden, sonst kann ein nicht-validierter Klartext (z. B. mit Zeilenumbrüchen) den Admin-Account beim Reinstall korrumpieren. | Hoch | Grün |
| SEC-HDR01 | `X-Content-Type-Options` | Header = `nosniff` | Niedrig | Grün |
| SEC-HDR02 | `X-Frame-Options` | Header = `SAMEORIGIN` oder `DENY` | Niedrig | Grün |
| SEC-HDR03 | `Referrer-Policy` | Header gesetzt (nicht leer) | Niedrig | Grün |
| SEC-HDR04 | Server-Banner | Apache-Versionsstring sichtbar | Niedrig | Rot (Deployment-Empfehlung) |
| SEC-BOT01 | UA-basierte Bot-Blockierung *(spezifikationsbasiert, DNS/ASN ausgeklammert)* | BadBotBlocker: BAD_ROBOTS-Sampling DataProvider (5 Kategorien: SEO, AI, Security → 406); WordPress-Pfade DataProvider (/wp-*, /xmlrpc.php → 406); Cookie-Heuristik EP8/EP9 (mit/ohne Cookies); leerer UA → 406; legitimer UA → 200. DNS-Zweige (B3/B4) dauerhaft ausgeklammert. | 15 | Hoch |
| SEC-UTL01 | Web-Assets & Utility-Endpoints *(spezifikationsbasiert)* | `UtilityEndpointsIntegrationTest` ✅: DataProvider-Batch (FaviconIco/WebmanifestJson/BrowserconfigXml/AppleTouchIconPng/AdsTxt/AppAdsTxt → 200 + Content-Type); RobotsTxt → 200 + text/plain + User-agent + Disallow; Ping → 200 oder 503; Ping-Body = OK/WARNING/ERROR. | Niedrig | Grün |

---

<a id="e"></a>

## Feature-Matrix: Datenpflege / Erfassung (E)

> Alle Handler, die GEDCOM-Datensätze via Web-UI erzeugen oder ändern.
> Abgrenzung: G = Datenformat/Import/Export; S = Ansicht/Navigation; P = Zugriffskontrolle/Auth.
> Rollen: B = Besucher, M = Mitglied, E = Bearbeiter, Mo = Moderator, V = Verwalter.
> Teststufen: 2 = Komponentenintegrationstest, 3 = Systemtest.
> Layer-Zuordnung: siehe [Mapping-Tabelle am Dokumentanfang](#teststufen-und-layer--nomenklatur).

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| E01 | Person/Familie anlegen & verknüpfen | AddChildToIndividual*/Action, AddParentToIndividual*/Action, AddSpouseToIndividual*/Action, LinkSpouseToIndividual*/Action: INDI mit Eltern/Kind/Partner anlegen → pending change; AddChildToFamily*/Action, AddSpouseToFamily*/Action, LinkChildToFamily*/Action: FAM-Mitglieder hinzufügen/verknüpfen | E, V | 2, 3 | Hoch |
| E02 | Fakten bearbeiten | EditFactPage/AddNewFact: Fakt anlegen/bearbeiten → pending change; DeleteFact → GEDCOM ohne Fakt in change-Tabelle; CopyFact/PasteFact: Fakt in Zwischenablage + Einfügen; SelectNewFact: GEDCOM-Tag auswählen | E, V | 2, 3 | Hoch |
| E03 | Rohdaten-Edit (Raw GEDCOM) | EditRawFactPage/Action: einzelner Fakt als GEDCOM-Text → change; EditRawRecordPage/Action: gesamter Record als GEDCOM-Text → change; EditRecordPage/Action: Record via Formular → change | E, V | 2, 3 | Mittel |
| E04 | Nebenrecords anlegen (NOTE / SOUR / REPO / SUBM) | CreateNoteModal/Action → NOTE-XREF; EditNotePage/Action → Notiz change; CreateSourceModal/Action → SOUR-XREF; CreateRepositoryModal/Action → REPO-XREF; CreateSubmissionModal/Action, CreateSubmitterModal/Action → Einreicher-Records | E, V | 2, 3 | Mittel |
| E05 | Medienobjekte anlegen & verknüpfen | CreateMediaObjectModal/Action/FromFile: OBJE-Record anlegen → DB-Eintrag; AddMediaFileModal/Action: Mediendatei zu OBJE hinzufügen → change; LinkMediaToRecordAction/IndividualModal/FamilyModal/SourceModal: OBJE mit anderem Record verknüpfen → change | E, V | 2, 3 | Mittel |
| E06 | Sortierung (Reorder) | ReorderChildrenPage: Kindreihenfolge → change; ReorderNamesPage: Namenreihenfolge → change; ReorderFamiliesPage: Familienreihenfolge → change; ReorderMediaPage/Action, ReorderMediaFilesPage/Action: Medien/Mediendatei-Reihenfolge | E, V | 2, 3 | Niedrig |
| E07 | Mediendatei-Download & Thumbnail | MediaFileDownload: Datei abrufen → 200 + korrekter Content-Type; MediaFileThumbnail: Thumbnail generieren → 200 + image/* | M, E, V | 2, 3 | Mittel |
| E08 | TomSelect & AutoComplete (Edit-Hilfs-APIs) | TomSelectIndividual/MediaObject/Source/Repository/Note/SharedNote: AJAX-Dropdown → JSON mit passenden Records; AutoCompleteCitation: Zitations-Vorschläge → JSON; AutoCompleteFolder: Ordner-Vorschläge für Medienpfad → JSON | E, V | 2, 3 | Niedrig |
| E09 | Sichere Auslieferung gefährlicher Mime-Types | `MediaFileDelivery`-Handler: Härtung der Mediendatei-Auslieferung gegen XSS und ungewollte Script-Ausführung. SVG-Dateien müssen mit Content-Type-Override (z. B. `image/svg+xml; charset=…` mit `Content-Disposition: inline`) ausgeliefert oder durch eine Replacement-Image-Response ersetzt werden; die Replacement-Image-Response muss zusätzlich eine restriktive `Content-Security-Policy` setzen (default-src 'none'). Verschieden von E07 (regulärer Download/Thumbnail). | M, E, V | 2 | Hoch |

---

<a id="a"></a>

## Feature-Matrix: Administration (A)

> Admin-Only-Operationen: Stammbaum-Verwaltung, Modul-Konfiguration, Site-Einstellungen, System-Werkzeuge.
> Getrennt von fachlichen Features (E, G, S, P). Rolle: V = Verwalter / Admin.
> Teststufen: 2 = Komponentenintegrationstest, 3 = Systemtest.
> Layer-Zuordnung: siehe [Mapping-Tabelle am Dokumentanfang](#teststufen-und-layer--nomenklatur).

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
| A12 | CLI Wartungsmodus aktivieren | `SiteOffline` CLI-Command (`site-offline [message]`): erstellt/überschreibt `data/offline.txt` mit optionalem Klartext → alle nachfolgenden HTTP-Requests werden von der `CheckForMaintenanceMode`-Middleware (M22) mit HTTP 503 + Offline-Seite beantwortet (Admin-Session ausgenommen). Destruktiv im Sinne „sperrt alle Nutzer aus". | 2 | Mittel |
| A13 | CLI Wartungsmodus deaktivieren | `SiteOnline` CLI-Command (`site-online`): löscht `data/offline.txt` → Middleware M22 lässt wieder alle Requests passieren. Komplement zu A12. | 2 | Niedrig |
| A14 | CLI initialer Config-Setup | `ConfigIni` CLI-Command (`config-ini`): schreibt `data/config.ini.php` mit DB-Parametern (host, port, user, password, database, tblpfx) aus Command-Optionen; prüft DB-Verbindbarkeit via `PDO` → Early-Exit mit FAILURE bei Verbindungsfehler. Einmalig vor `make setup` genutzt; produktiv riskant, weil falsche Werte die gesamte Plattform unerreichbar machen. | 2 | Hoch |
| A15 | CLI Übersetzung kompilieren | `CompilePoFiles` CLI-Command (`compile-po-files`): liest `.po`-Dateien aus `resources/lang/<locale>/`, konvertiert sie in PHP-Arrays, speichert als `.php` im selben Verzeichnis → Runtime-Translation-Lookup nutzt anschließend die kompilierten `.php`-Dateien. Keine DB-Änderungen; Fail-Silent bei fehlender `.po`-Datei. | 2 | Niedrig |
| A16 | CLI Baum-Listing | `TreeList` CLI-Command (`tree-list`): gibt alle konfigurierten Stammbäume zeilenweise auf STDOUT aus — Spalten `gedcom_id`, `tree_name`, `tree_title`, `imported` (bool). Reines Lesewerkzeug; Unterscheidet sich von A01 `ManageTrees` (HTTP-Admin-Seite). | 2 | Niedrig |
| A17 | Default-Block-Konfiguration TreePage | `TreePageDefaultEdit`/`TreePageDefaultUpdate`-Handler: Admin-Verwaltung der globalen Standard-Block-Konfiguration für TreePage (Hauptseiten neuer Bäume). Edit zeigt main/side-Bloecke des Default-Templates an, Update persistiert via `HomePageService::updateTreeBlocks` mit `tree_id = -1` → Redirect zu Control-Panel. Verschieden von A04 (tree-spezifische Prefs) und S46 (Block-Module-Konfiguration). | 2 | Niedrig |
| A18 | Default-Block-Konfiguration UserPage | `UserPageDefaultEdit`/`UserPageDefaultUpdate`-Handler: Admin-Verwaltung der globalen Standard-Block-Konfiguration für UserPage (persönliches Dashboard neuer Benutzer). Update persistiert via `HomePageService::updateUserBlocks` mit `user_id = -1` → Redirect zu Control-Panel. Komplement zu A17 für User-Seite. | 2 | Niedrig |
| A19 | Modul-Action Runtime-Dispatch | `ModuleAction`-Handler: Runtime-Dispatch für `/module/<name>/<action>`-Routen. Admin-Gate-Enforcement gegen Case-Bypass: Gast/nicht-Admin auf eine nur-Admin-Methode wird per `HttpAccessDeniedException` (geworfen) oder HTTP 403 abgewiesen, auch wenn die `<action>` in beliebiger Casing-Variante angeliefert wird. Verschieden von A05 (Modul-Konfigurationsseite). | 2 | Hoch |

---

<a id="k"></a>

## Feature-Matrix: Kommunikation (K)

> Nutzer-zu-Nutzer- und Nutzer-zu-Admin-Kommunikation.
> S36 deckt ContactPage als Seiten-Smoke — K01 ergänzt die Action-Verarbeitung.

| # | Feature | Abgeleitete Anforderung | Rollen | Teststufe | Prio |
|---|---|---|---|---|---|
| K01 | Kontaktformular | ContactPage: Formular → 200 (S36 Smoke); ContactAction: Nachricht abschicken → E-Mail-Versand / Fehler (kein SMTP im Test-Stack: Response-Status prüfen) | B, M | 2, 3 | Niedrig |
| K02 | Benutzer-Nachrichten | MessagePage: Nachrichtenformular → 200; MessageAction: Nachricht an Nutzer senden → Bestätigung / Redirect; MessageSelect: Empfänger aus Nutzerliste auswählen | M, E, V | 2, 3 | Niedrig |

---

<a id="u"></a>

## Feature-Matrix: Querschnitts-Utilities (U)

> Utility-Klassen ohne Domänenzuordnung — direkt im root-Namespace `Fisharebest\Webtrees`.
> Upstream-Tests vorhanden, aber Layer-3-Lücken durch CRAP-Analyse identifiziert.

| # | Feature | Abgeleitete Anforderung | Teststufe | Prio |
|---|---|---|---|---|
| U01 | Validator (root-Paket) *(spezifikationsbasiert)* | `Validator.float()`: EP/BVA-Matrix (EP1 float-String→float, EP2 integer-String→float, EP3 int-Typ→float, EP4 negativ, EP5 zero-BV, EP-inv1 non-numeric→throw, EP-inv2 non-numeric+default, EP-miss1 fehlt→throw, EP-miss2 fehlt+default); `__construct` UTF-8: key-invalid→throw, value-invalid→throw, serverParams-ASCII→kein-throw; `integer()` negativer-String→-42; `array()` non-array-non-null→throw | 2 | Mittel |
| U02 | CountryService (`Statistics/Service/`) *(SKIP — deprecated)* | `getAllCountries()`, `iso3166()`, `mapTwoLetterToName()`: reine Lookup-Logik ohne DB/Tree-Abhängigkeit; kein Test geplant. Begründung: Klasse ist in webtrees als `@deprecated` markiert und soll in 2.3 entfernt werden — Testaufwand wäre sofort wertlos. | — | — |

---

<a id="m"></a>

## Feature-Matrix: Middleware (M)

> PSR-15 HTTP-Middleware unter `upstream/webtrees/app/Http/Middleware/`. Jede Middleware
> verarbeitet Request und/oder Response in der Kette `ReadConfigIni → BaseUrl → ClientIp →
> UseDatabase → UpdateDatabaseSchema → UseSession → UseLanguage → UseTheme → BootModules →
> LoadRoutes → Router → CheckCsrf → RequestHandler → EmitResponse`. Die 7 Rollen-basierten
> `Auth*`-Klassen sind zu einer logischen Cluster-Einheit (M01) zusammengefasst, weil sie
> denselben Zugriffskontroll-Mechanismus für verschiedene Rollen-Ebenen implementieren.
> **Stand:** 28 IDs für 34 Middleware-Klassen (2026-04-11, Plan-Phase 5.1).

| # | Feature | Abgeleitete Anforderung | Teststufe | Prio |
|---|---|---|---|---|
| M01 | Rollenbasierte Zugriffskontrolle | `AuthLoggedIn`: Session-User ≠ Guest → weiter, sonst 302 Login; `AuthMember`/`AuthEditor`/`AuthModerator`/`AuthManager`/`AuthAdministrator`: Benutzerrolle vs. Ziel-Rolle per Tree → 200/403/302; `AuthNotRobot`: Request-Attribut `robot` ≠ true → weiter. Zusammen bilden sie die Autorisierungs-Stufenleiter der Plattform. | 2, 3 | Hoch |
| M02 | Bad-Bot-Blocker (UA-basiert) | `BadBotBlocker`: User-Agent-Regex-Liste + WordPress-Pfad-Heuristik + DNS-Whois-Reverse-Lookup + Cookie-Heuristik → blockiert bekannte Bot-UAs mit 403. (L3 bereits als SEC-BOT01 getestet, `BadBotBlockerIntegrationTest` 15 Tests.) | 2, 3 | Hoch |
| M03 | Client-IP-Ermittlung (Proxy-Trust) | `ClientIp`: Request → Client-IP aus `X-Forwarded-For`/`Forwarded`/Remote-Addr extrahieren unter Berücksichtigung konfigurierter Proxy-Trust-Liste → Request-Attribut `client-ip`. | 2 | Mittel |
| M04 | CSRF-Token-Validierung | `CheckCsrf`: POST-Request → Vergleich `$_POST['_csrf']` bzw. `X-CSRF-TOKEN` Header mit Session-Token → 403 auf Mismatch, GET passiert durch. | 2 | Hoch |
| M05 | Security-Headers (OWASP) | `SecurityHeaders`: Response ergänzt `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: same-origin`, ggf. CSP. (L4 als SEC-HDR01–HDR04 getestet, `security-headers.spec.ts`.) | 2, 3 | Hoch |
| M06 | Session-Initialisierung | `UseSession`: PHP-Session starten, Session-Cookie-Flags (`HttpOnly`, `SameSite`, `Secure`) setzen, `LAST_ACTIVE_TIMESTAMP` aktualisieren. | 2, 3 | Hoch |
| M07 | Datenbank-Verbindung | `UseDatabase`: Eloquent-Capsule mit Credentials aus `config.ini.php` initialisieren → globaler DB-Singleton. | 2, 3 | Hoch |
| M08 | Datenbank-Schema-Migration | `UpdateDatabaseSchema` (mit `MigrationService`): Schema-Version-Check + Migrations-Chain ausführen bis Ziel-Version erreicht → DB-Struktur aktuell. | 2, 3 | Hoch |
| M09 | Base-URL-Ermittlung | `BaseUrl`: Base-URL aus `config.ini.php` `base_url` lesen oder aus Request `Host`/`Scheme`/`Port` rekonstruieren → Request-Attribut `base_url`. | 2 | Mittel |
| M10 | Routen-Laden | `LoadRoutes` (mit `ApiRoutes`, `WebRoutes`): Core-Routing-Tabellen laden und Router im DI-Container hinterlegen. | 2 | Mittel |
| M11 | URL-Routing | `Router` (mit `ModuleService`, `RouterContainer`, `TreeService`): Request-URL → Routing-Tabelle → RequestHandler-FQCN (ggf. mit Tree/Module-Parameter-Injection). | 2 | Hoch |
| M12 | Request-Handler-Dispatch | `RequestHandler`: Routing-Ergebnis → FQCN aus Container instanziieren + `handle()` aufrufen → Response. | 2, 3 | Hoch |
| M13 | Sprachauswahl | `UseLanguage` (mit `ModuleService`): Sprache aus Session/Cookie/`Accept-Language`-Header/Siteprefs priorisieren, I18N initialisieren (`gettext`/`I18N::init`). | 2 | Mittel |
| M14 | Theme-Auswahl | `UseTheme` (mit `ModuleService`): Theme aus Session/Siteprefs priorisieren → Container-Binding `ModuleThemeInterface`. | 2 | Niedrig |
| M15 | PHP-Error-zu-Exception-Konvertierung | `ErrorHandler`: `set_error_handler()`-Hook konvertiert PHP-Notices/Warnings in `ErrorException`. | 2 | Mittel |
| M16 | Exception-Handling & Error-Page-Rendering | `HandleExceptions` (mit `PhpService`, `TreeService`): Gefangene Exceptions → passende Error-Page (403/404/500) mit Stack-Trace (nur im Debug-Modus). | 2, 3 | Hoch |
| M17 | Debug-Logger (SQL/Perf) | `DebugLogger`: SQL-Query-Zählung, Response-Time, Memory-Peak → Debug-Response-Header oder Log-Datei (nur wenn Debug-Flag aktiv). | 2 | Niedrig |
| M18 | Housekeeping (Thumbnails/Logs/Temp) | `DoHousekeeping` (mit `HousekeepingService`): zufällig 1/1000 Requests → löscht alte Thumbnail-Cache-Einträge, Temp-Dateien, Log-Rotation, Session-Cleanup. | 2 | Niedrig |
| M19 | Response-Kompression | `CompressResponse` (mit `PhpService`, `StreamFactoryInterface`): `Accept-Encoding: gzip/deflate` → Response-Body streamt komprimiert. | 2 | Niedrig |
| M20 | Content-Length-Header | `ContentLength`: Response-Body-Länge berechnen → `Content-Length`-Header setzen falls noch nicht vorhanden. | 2, 3 | Niedrig |
| M21 | Config-Ini-Lesen | `ReadConfigIni` (mit `SetupWizard`): `config.ini.php` parsen → Request-Attribute für DB-Creds, Base-URL, Debug-Flag etc.; wenn nicht vorhanden → Setup-Wizard-Redirect. | 2, 3 | Hoch |
| M22 | Wartungsmodus | `CheckForMaintenanceMode` (mit `MaintenanceModeService`): Wartungs-Marker in `data/offline.txt` → HTTP 503 + Offline-Seite für Nicht-Admins. | 2, 3 | Mittel |
| M23 | Update-Prüfung | `CheckForNewVersion` (mit `UpgradeService`): asynchron verfügbare webtrees-Versionen prüfen (nur bei GET-Requests) → Versions-Info im Container. | 2 | Niedrig |
| M24 | Public-Files-Serving | `PublicFiles`: statische Dateien aus `/public/` direkt serven mit `Cache-Control: public, max-age=N` — umgeht RequestHandler-Chain. | 2, 3 | Mittel |
| M25 | GEDCOM-Tag-Registrierung | `RegisterGedcomTags` (mit `Gedcom`): erweiterte/Custom-GEDCOM-Tags im `ElementFactory` registrieren (z. B. `_WT_USER`, `_FNRL`) → Element-Lookup vollständig. | 2 | Mittel |
| M26 | Modul-Bootstrap | `BootModules` (mit `ModuleService`, `ModuleThemeInterface`): aktive Module aus DB laden, jede `boot()`-Methode aufrufen, pro Modul Theme-Hook ausführen. | 2 | Mittel |
| M27 | DB-Transaktion mit Retry | `UseTransaction`: Request-Handler in `DB::transaction()` wrappen + Deadlock-Retry-Logik bei `SQLSTATE 40001`/`1213`. | 2, 3 | Hoch |
| M28 | Response-Emittierung | `EmitResponse` (mit `PhpService`): finale Response in Chunks via `echo` an Client senden + `fastcgi_finish_request()`-Cleanup. | 2 | Niedrig |
| M29 | 404-Handler | `NotFound`-RequestHandler: Default-Fallback der Routing-Kette für nicht aufgelöste URLs. Robot-Request → schlichte 404-Antwort; GET ohne Robot-Attribut → 302 Redirect zur HomePage; nicht-GET ohne Robot-Attribut → `HttpNotFoundException`. | 2 | Mittel |

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
