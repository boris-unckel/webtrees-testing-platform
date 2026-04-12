<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Testentwurfsverfahren und Testorakel

Dieses Dokument beschreibt die systematische Zuordnung von ISTQB-Testentwurfsverfahren
und Testorakeln zu den einzelnen Domänen und Feature-Matrix-IDs. Die Verfahren bestimmen,
**wie** Testbedingungen und Testfälle abgeleitet werden; die Orakel liefern die
Informationsquelle zur Ermittlung erwarteter Ergebnisse.

Verwandte Dokumente:
- [Feature-Matrizen](tds_conditions_ref.md) — Testbedingungen und Feature-IDs
- [Abdeckungsmatrix](tds_coverage_ref.md) — Zuordnung Features → Testklassen
- [Überdeckungsstrategie](tp_ratchet_spec.md) — Ratchet-Mechanismus und Schwellenwerte

---

## Testfall-Verteilung nach Teststufe

| Teststufe | GEDCOM (G01–G31) | Suche/Nav (S01–S53) | Privacy (P01–P42) | Sicherheit (SEC) | E/A/K | Gesamt |
|---|---|---|---|---|---|---|
| Teststufe 1 — Komponententest | G05, G06, G11, G17, G18, G19, G22, G23 (8) | S04 (1) | — | — | — | **9** |
| Teststufe 2 — Komponentenintegrationstest (Dateisystem) | G01–G04, G07–G10, G12–G16, G24 (14) | S01–S03, S05–S08, S10–S12, S19, S21, S22 (13) | P01–P24, P27–P29, P30, P37, P38 (30) | SEC-H01–H02, SEC-D01–D02, SEC-C01–C03, SEC-PUB01, SEC-WZ03 (9) | E01–E08 (8), A01–A07 (7) | **81** |
| Teststufe 3 — Systemtest (HTTP/Playwright) | G20, G21 (2) | S05–S10, S13–S18, S20, S23–S24, S26–S41, S46, S47, S50 (34) | P01–P03, P14–P19, P22, P24–P30, P37, P38, P40, P41 (21) | SEC-H03–H06, SEC-M01–M03, SEC-PUB02–PUB04, SEC-W01, SEC-WZ01–WZ04, SEC-HDR01–HDR04 (18) | E01–E06, E08 (7), A01, A04, A05, A07 (4), K01, K02 (2) | **88** |
| **Nur Teststufe 2** | — | — | P04–P13, P20–P21, P23 (13) | SEC-H01–H02, SEC-D01–D02, SEC-C01–C02, SEC-PUB01 (7) | — |
| **Nur Teststufe 3** | — | — | P25, P26 (2) | SEC-H03–H06, SEC-M01–M03, SEC-PUB02–PUB04, SEC-W01, SEC-WZ01–WZ02, SEC-WZ04, SEC-HDR01–HDR04 (17) | — |
| **Beide Teststufen** | — | — | 14 Features (P01–P03, P14–P19, P22, P24, P27–P29) | SEC-C03, SEC-WZ03 (2) | — |
| **Summe** | **24** | **39** | **29** | **26** | **118** |

## Prioritätsverteilung

| Priorität | G+S | P | SEC | Gesamt | Anteil |
|---|---|---|---|---|---|
| Hoch | 26 | 19 | 14 | **59** | 50% |
| Mittel | 32 | 10 | 8 | **50** | 43% |
| Niedrig | 4 | 0 | 4 | **8** | 7% |

---

## Testorakel — Orakelquellen pro Domäne

> Ein **Testorakel** (ISTQB) ist die Informationsquelle zur Ermittlung erwarteter Ergebnisse.
> Konkrete erwartete Werte werden im Testcode definiert, nicht in diesem Dokument.

| Orakel | Gilt für Feature-Matrix-IDs | Methode |
|---|---|---|
| `demo.ged` (bekannte Inhalte: 72 Individuen, 29 Familien) | G01–G04, G07–G12, S01–S03, S19 | DB-Count, Feldwerte prüfen, Beziehungsstruktur verifizieren |
| GEDCOM 5.5.1-Standard (Kapitel 2–4) | G05, G17–G19, G22, G23 | Spec-Abgleich: Tag-Liste, Datumsformate, Encoding-Regeln, CONC/CONT |
| webtrees-DB-Schema (`DB::MYSQL` Constraints) | G12, G13, S10 | XREF-Eindeutigkeit, Fremdschlüssel, Collation-Verhalten |
| Erwartetes DOM (Playwright-Selektoren) | S09, S13–S18, S20, S23–S24, S26–S40, P25–P29 | Element-Existenz, Struktur, Textinhalt; kein Screenshot-Vergleich |
| Vorversion (Baseline-Traces) | Performanztest | Trace-Diff: Ladezeit ≤+20%, Query-Count ≤+2 |
| `privacy-test-template.ged` (30+ Personen, dynamische Daten) | P01–P24, P27–P29 | DB-Sichtbarkeit per `canShow()`/`canEdit()`, Rollen × Einstellungen × Personenzustand |
| webtrees Privacy-Quellcode (`Individual::canShowByType()`, `isDead()`, `GedcomRecord::canEdit()`) | P01–P29 | Code-Analyse: Rollenmatrix, Grenzwerte, Inferenz-Logik als Orakel |
| Upstream-Quellcode: `data/.htaccess` (statische Datei) | SEC-H01, SEC-H02 | Dateiinhalt als Referenz: `Require all denied` |
| Upstream-Quellcode: `data/index.php` (statische Datei) | SEC-D01, SEC-D02 | Dateiinhalt als Referenz: `header('Location: ../index.php')` |
| Upstream-Quellcode: `resources/views/setup/config.ini.phtml` | SEC-C01, SEC-C02 | Template definiert erwartetes Format (PHP-Guard, INI-Keys) |
| Apache HTTP-Spezifikation (RFC 7231, Status 403) | SEC-H03–SEC-H06, SEC-M01 | HTTP 403 = Zugriff verboten; Body darf keine Credentials enthalten |
| Upstream-Quellcode: `ReadConfigIni.php`, `SetupWizard.php` | SEC-W01, SEC-WZ01–SEC-WZ04 | Middleware-Logik: `file_exists()` → Lock; Wizard-HTML-Selektoren |
| Upstream-Quellcode: `PublicFiles.php` | SEC-PUB02–SEC-PUB04 | `file_get_contents()` statt PHP-Execution; `!str_contains($path, '..')` |
| Upstream-Quellcode: `SecurityHeaders.php` | SEC-HDR01–SEC-HDR03 | Middleware setzt Header-Werte direkt im Code |
| Upstream-Quellcode: `Auth::checkMediaAccess()`, `MediaFileDownload` | SEC-M02, SEC-M03 | Rollenbasierte Zugriffskontrolle: Visitor → kein Zugriff, Member → Zugriff |
| Dateisystem-Semantik: `stat()` Permissions | SEC-C03 | umask-Default des PHP-Prozesses; world-readable = potenzielle Schwäche |
| Apache-Konfiguration: `ServerTokens` Default | SEC-HDR04 | Default `ServerTokens Full` → Versionsinfo; gehärtete Config → `Prod` |

---

## Testentwurfsverfahren pro Domäne

> ISTQB-Testentwurfsverfahren (Testverfahren) beschreiben, **wie** Testbedingungen und
> Testfälle systematisch abgeleitet werden. Zuordnung pro Domäne, nicht pro Einzeleintrag.

| Verfahren (ISTQB) | Domäne / Feature-Matrix-IDs | Begründung |
|---|---|---|
| **Äquivalenzklassenbildung** | G05, G08, G17, S04, S07–S08 | Eingaben mit klar abgrenzbaren Klassen: 5 GEDCOM-Datumstypen, 4 Encoding-Varianten, Suchsyntax-Varianten, 2 Soundex-Algorithmen |
| **Grenzwertanalyse** | G18, S06, S10 | Numerische Grenzen: Zeilenlänge exakt 253/254 Zeichen (CONC/CONT), Datumstoleranz ±0/±1/±20 Jahre, Paginierung 0/1/50/51 Ergebnisse |
| **Entscheidungstabellentest** | G16, S12 | Kombinatorik: 4 Access-Levels × 6 Record-Typen = 24 Privacy-Kombinationen; Rolle × Record-Sichtbarkeit |
| **Anwendungsfall-Test** | G20, G21, S05–S10, S13–S18, S23–S24, S26–S41, S46, S47, S50, E01–E06, E08, K01, K02 | Systemtest-Szenarien mit Nutzerinteraktion: Import-Export-Roundtrip, Chart-Rendering, Seitennavigation, Record-Seiten, Auth-Formulare, Kalender, Suche (Felder/Datum/Phonetisch/Paginierung), Statistik, Homepage-Blöcke, Interaktiver Stammbaum, Hilfetexte, Datenerfassung (Personen/Fakten/Raw-GEDCOM/Nebenrecords/Medien/Reorder/TomSelect), Kontaktformular, Nachrichten |
| **Erfahrungsbasierter Test** | G10, G11, S17 | Keine formale Spezifikation verfügbar: Legacy-Formate (TNG), Custom-Tags (Ancestry, FamilySearch), Nischen-Charts |
| **Grenzwertanalyse** | P04–P06, P08–P13 | Datumsgrenzen: MAX_ALIVE_AGE ±1, KEEP_ALIVE ±1, isDead()-Inferenz-Offsets (Eltern +45, Ehepartner −10/+40, Kinder −15, Enkel −30) |
| **Äquivalenzklassenbildung** | P16–P19, P20–P21 | RESN-Werte (none, privacy, confidential) × Rollen; default_resn-Typen (xref, tag_type, xref+tag_type) |
| **Entscheidungstabellentest** | P14–P15, P24 | SHOW_LIVING_NAMES (3 Stufen) × Rollen; Suche × Privacy-Zustand × Rolle |
| **Anwendungsfall-Test** | P25–P30, P37, P38, P40, P41, A01, A04, A05, A07 | End-to-End-Szenarien: Seitenaufruf → Sichtbarkeitsprüfung → Edit → Pending Change → DB-Persistenz; Merge-Workflow, Benutzer-Admin, Account-Selbstverwaltung, Pending-Changes-Workflow, Stammbaum-Management, Präferenzen, Modul-Konfiguration, Benutzerverwaltung |
| **Paarweiser Test** | P01–P03 | Kombinatorik: REQUIRE_AUTHENTICATION × HIDE_LIVE_PEOPLE × SHOW_DEAD_PEOPLE × Rolle — paarweise statt volles Produkt |
| **Entscheidungstabellentest** | SEC-H03–SEC-H06, SEC-M01–SEC-M03 | Kombination URL-Pfad × HTTP-Methode × erwarteter Status (403/200/302). Entscheidungstabelle: `.htaccess` greift ja/nein × Auth vorhanden ja/nein |
| **Erfahrungsbasierter Test** | SEC-H06, SEC-PUB04 | URL-Encoding-Varianten und Path-Traversal-Muster aus OWASP Testing Guide. Keine formale Spezifikation für Umgehungsversuche |
| **Anwendungsfall-Test** | SEC-WZ01–SEC-WZ04 | End-to-End-Szenario: Frische Distribution → Wizard durchlaufen → lauffähige Instanz (6 Wizard-Schritte) |
| **Äquivalenzklassenbildung** | SEC-HDR01–SEC-HDR04, SEC-PUB02–SEC-PUB03 | Header: vorhanden/korrekt vs. fehlend/falsch. `public/`-Zugriff: Datei vs. Verzeichnis vs. Traversal |
| **Grenzwertanalyse** | SEC-C03 | Datei-Permissions: Grenze bei world-readable-Bit (0644 vs. 0640 vs. 0600) |
| **Äquivalenzklassenbildung** | G26 | Format-Partitionen (gedcom/gedzip/zip/zipmedia/ungültig) und Privacy-Partitionen (none/manager/member/visitor/ungültig) per DataProvider; Tree-not-found als eigene Fehler-EP |
| **Äquivalenzklassenbildung** | G29 | editLinesToGedcom: Wert-Klassen (normal, mehrzeilig→CONT, leer, Sub-Level); insertMissingLevels: Input mit/ohne fehlende Subtags; EP10-Korrektur: leerer Input expandiert für Tags mit Subtags |
| **Entscheidungstabellentest** | P35 | 15 Guard-Branches: Konflikt-Flag-Kombinationen (B2–B5), Create-Bedingungen (B6–B9), Edit-Bedingungen (B10–B11, B13–B15) — DataProvider für gleichförmige Branches |
| **Zustandsbasiertes Testen** | P36 | Zustandsautomat für alle 4 Setting-Commands (SiteSetting, TreeSetting, UserSetting, UserTreeSetting): list→get→set→delete-Zustände; Konflikt-Branches, Warn-/Error-Paths, Entity-not-found-Branches |
| **Äquivalenzklassenbildung** | S42 | SearchGeneralPage Suchergebnis-Kardinalität: EP2 genau 1 Individual → Redirect 302; EP4 genau 1 Family → Redirect 302; EP8 keine Typ-Flags → Fallback individuals+families → 200; Redirect-Grenze (1 vs. 2+ Treffer) als BVA |
| **Äquivalenzklassenbildung** | S49 | ManageMediaData `files`-Parameter: EP1 local / EP2 external / EP3 unused — vollständige Typ-EP-Matrix; JSON-Struktur-Assertion ({data, recordsTotal, recordsFiltered}) als Vertragsprüfung |
| **Äquivalenzklassenbildung** | S50 | 12 bekannte Topic-IDs als EP-Partitionen per DataProvider (switch-Cases); default-Case (unbekannte ID) als eigene Fehler-EP |
| **Äquivalenzklassenbildung** | SEC-BOT01 | BadBotBlocker UA-Partitionen: BAD_ROBOTS-Sampling per DataProvider (5 Kategorien SEO/AI/Security); WordPress-Pfad-EP per DataProvider (4 Pfade); Cookie-Heuristik EP8/EP9; DNS-Branches dauerhaft ausgeklammert |
| **Äquivalenzklassenbildung** | P37 | UserEditAction Validierungs-Branches: user-not-found (B1/EP1), Duplikat-Email/Username (B5/B6, B7/B8/EP2/EP4), Self-Edit-Admin-Guard (B4/EP6), Passwort-Update/Kein-Update (B3/EP9/EP10), Path-Length-Reset (EP12); Redirect-Ziel unterscheidet Happy-Path (UserListPage) von Error-Path (UserEditPage mit user_id) |
| **Äquivalenzklassenbildung** | P30 | MergeFactsAction Guard-Branches: record-not-found (B1/EP2), same-record (B3/EP4), tag-mismatch INDI+SOUR (B4/EP5), pending-deletion via DB-Insert (B5/EP6); Happy Path: change-Tabellen-Assert für deleteRecord() (EP1); Redirect-Ziel unterscheidet Guard (MergeRecordsPage mit xref1) von Happy-Path (ManageTrees ohne xref1) |
| **Äquivalenzklassenbildung** | P31 | ChangeFamilyMembersAction Member-Branches: Vater-Austausch (B1+B5/EP1), Mutter-Entfernung WIFE='' (B2/EP2), Kind-Hinzufügen (B4/EP3), Kind-Entfernen (B3/EP4); kein-Änderung (EP5): change-count=0; Assertion via change-Tabelle (exists() je betroffenem xref); B7/B8 Datumsreihenfolge ausgeklammert (Pragmatisch C) |
| **Äquivalenzklassenbildung** | P33 | TreePrivacyAction Array-Validierung: Mismatched-Arrays → HttpBadRequestException (EP3/EP4); Rule-Typ-Matrix: tag+xref (EP5), tag-only/xref=NULL (EP6), xref-only/tag=NULL (EP7), beide-leer/kein-Insert (EP8, countBefore=countAfter wg. TreeService-Defaults); Privacy-Setting HIDE_LIVE_PEOPLE=1 → tree.getPreference (EP9) |
| **Äquivalenzklassenbildung** | S41 | StatisticsData whereBetween-Branch: EP5 (year1=0,year2=0→kein Filter), EP6 (1900–2000→gefiltert), EP8 (2100→1900→invertiert leer); Sort-EP-Matrix via DataProvider (alpha/count/rcount); parentsQuery Sex-EP via DataProvider (F→WIFE-Feld, M→HUSB-Feld); EP13 threshold=999→filter |
| **Äquivalenzklassenbildung** | S43 | ReportGenerate Format-EP: EP1 HTML→200+HTML-Body, EP2 PDF→content-type:application/pdf; Destination-EP: EP5 view→kein attachment-Header, EP6 download→content-disposition:attachment+Dateiname; Guard-Branch: B1 unbekannter Report→Redirect; switch($format) default==HTML (EP3/EP4 = EP1, kein eigener Branch) |
| **Äquivalenzklassenbildung (Pragmatisch C)** | S44 | ReportParserGenerate Output-Validierung: EP1 Vorfahren-Report→assertNotEmpty+HTML (addAncestors ausgeführt), EP3 Nachkommen-Report→assertNotEmpty+HTML (addDescendancy ausgeführt), EP7 Individual+Fakten+Bild→assertNotEmpty+HTML (factsStartHandler+imageStartHandler ausgeführt); Rekursionsabbruch EP2/EP4 durch Demo-Fixture implizit |
| **Äquivalenzklassenbildung (Pragmatisch C)** | S46 | TopSurnamesModule info_style-EP-Matrix via DataProvider: EP4 'table'→HTML-Tabelle, EP5 'list'→HTML-Liste, EP6 'tagcloud'→HTML-Tag-Cloud, EP6b 'array'→kompakte-Liste; alle 4: assertNotEmpty+HTML; EP2 (AJAX→JSON) gestrichen (loadAjax()=true ist Host-Page-Flag, kein JSON-Branch in getBlock()) |
| **Äquivalenzklassenbildung (Pragmatisch C)** | S47 | TreeView HTML-Strukturvalidierung: getDetails X1030→assertStringContainsString('X1030') (EP5 XREF im Output); getIndividuals 'p'-Request→assertNotEmpty+HTML (EP1 Eltern-Ansicht via drawPerson); getIndividuals 'c'-Request→assertNotEmpty+HTML (EP3 Kinder-Ansicht via drawChildren); EP7 (unbekannte XREF) ausgeklammert (Individual-Factory gibt null zurück, kein Aufruf möglich) |
| **Äquivalenzklassenbildung** | S48 | MapDataImportAction option-EP: EP1+EP5 option=add + korrektes CSV (`;`-Trenner, Level-Format) → DB-Postcondition place_location mit lat/lng assertEqualsWithDelta; EP6 Null-Island-Filter: level=1 Ort mit (0,0) → array_filter-Callback gefiltert, assertFalse exists(); Befund: bisherige Tests nutzten falsches `,`-Format → keine echten Imports; Smoke-Tests als Fehlerresilienz-Tests behalten |
| **Äquivalenzklassenbildung** | P32 | DeleteRecord Lösch-Branches: Standard-Löschung (EP1): SOUR X1102 → change-Eintrag new_gedcom=''; Familie-Kaskade (EP5): P1 löschen aus 2-Mitglieder-Familie ohne Fakten → F1 ebenfalls in change-Tabelle. GedcomRecordPage EP-Matrix: STANDARD_RECORDS (INDI/FAM/SOUR/REPO) → 302-Redirect via DataProvider (EP1×4); _CUST-Record via DB-Insert → 200+canonical-Link-Header (EP2). Befund: Smoke-Tests prüften nur < 400, verdeckten Redirect-Verhalten |
| **Strukturbasiertes Testen (CRAP-Score-Analyse)** | G27 (EXCLUDED), S45 | Testziel-Auswahl via CRAP-Score (cx² + cx bei 0 % Coverage) aus PHPUnit Clover-XML (`make crap-report`). Schwelle: CRAP > 100 (cx ≥ 10). Tests zielen auf Code-Pfad-Abdeckung (strukturbasiertes Testen, ISTQB), nicht auf fachliche Äquivalenzklassen. Explizit **niedrigere Qualitätsstufe** als spezifikationsbasierte Tests: i. d. R. Smoke-Pfad (200 OK / Redirect) oder Basis-Verhalten ohne vollständige Grenzwert- und Äquivalenzklassen-Abdeckung. Ausgeklammert: DNS-abhängige Zweige (BadBotBlocker), nicht-CLI-Umgebungsprüfungen, externe Dateirechte-Abhängigkeiten. Drei Gruppen: A (CRAP > 1.000), B (300–1.000), C (100–300). Stand 2026-04-05: 43 → 15 Methoden nach Runde 1–3 (28 eliminiert). |
| **Äquivalenzklassenbildung** | G30 | UploadMediaAction Upload-Fehler-Codes: EP1 UPLOAD_ERR_NO_FILE → 302 ohne Filesystem-Write; EP2 UPLOAD_ERR_PARTIAL → FileUploadException; EP4 gefährliche Extension (.php/.pl/.cgi) → FlashMessage 'danger' + 302; PSR-7 UploadedFile via `Laminas\Diactoros\UploadedFile` in PHPUnit-Kontext nötig (kein $_FILES in CLI) |
| **Äquivalenzklassenbildung** | S52 | MapDataSave INSERT/UPDATE-Zustandsunterschied: EP1 POST ohne place_location-ID → INSERT + DB-Postcondition; EP2 POST mit vorhandener ID → UPDATE bestehender Eintrag; EP4 MapDataDelete → assertFalse DB-exists(); EP6 MapDataExportCSV → Content-Type text/csv |
| **Äquivalenzklassenbildung** | P39 | LoginAction $_COOKIE-Constraint: B0 (`$_COOKIE === []`) ist erster Guard in `doLogin()` — alle nachfolgenden Guards B1–B4 (user-not-found, wrong-password, email-not-verified, account-not-approved) nicht erreichbar im PHP-CLI-Kontext; einzig testbarer Pfad: `handle()` fängt Cookie-Exception → 302-Redirect; Happy Path dauerhaft EXCLUDED |
| **Äquivalenzklassenbildung** | SEC-UTL01 | Web-Asset DataProvider-Batch: Content-Type-EP-Matrix für 6 statische Asset-Handler (image/x-icon, application/json, application/xml, image/png, text/plain) per DataProvider; RobotsTxt mit Tree-Kontext (User-agent + Disallow-Inhalt); Ping Health-Endpoint: Status 200 oder 503, Body = OK/WARNING/ERROR |
| **Äquivalenzklassenbildung** | E07 | MediaFileThumbnail replacementImageResponse: Befund — Handler gibt immer HTTP 200 zurück, auch wenn XREF ungültig oder Datei fehlt (nicht HTTP 404 wie initial angenommen); MediaFileDownload bei ungültiger XREF → HttpNotFoundException (keine replacementImageResponse) |
| **Äquivalenzklassenbildung** | P40 | PendingChanges HTTP-Handler: AcceptRecord/RejectRecord mit ungültiger XREF → 200 + kein DB-Write (B1-Guard); `REQUIRE_APPROVAL='1'` als Baum-Präferenz-Voraussetzung für pending changes im Test-Setup; PendingChanges GET → View 200 ohne Branch |
| **Äquivalenzklassenbildung** | A11 | Masquerade Auth-Branches (sicherheitsrelevant): EP1 user_id not-found → HttpNotFoundException; EP2 self (gleiche user_id wie current) → 204 ohne `Auth::login()`-Aufruf; EP3 other → 204 + `Auth::user()` wechselt zu Ziel-User (Session-Verifikation via `Auth::id()`) |
