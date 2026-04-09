<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# webtrees Glossar (Deutsch)

Domänenspezifische Begriffe aus webtrees, dem Testcode und der Test-Infrastruktur dieses Repos.

**Quellen:**
- Upstream-Code: `upstream/webtrees/app/`
- Testcode: `tests/layer3-integration/tests/`
- Dokumentation: `docs/*.md`

---

## A

### Abnahmetest
→ siehe ISTQB-Glossar. Im webtrees-Kontext: Layer 4 (E2E) entspricht dem Systemtest/Abnahmetest.

### Ahne
Vorfahre in einer Stammtafel. GEDCOM-Kontext: Individuen, die über `FAMC`-Einträge mit einer Familie als Kind verknüpft sind, formen die Vorfahrenlinie.  
**Englisch:** Ancestor  
**Siehe auch:** `AncestorsChartModule`, Stammtafel

### Aktion (Action)
HTTP-Handler-Typ in webtrees für zustandsändernde Anfragen (POST/PUT/DELETE). Implementiert `RequestHandlerInterface`.  
**Beispiele:** `ImportGedcomAction`, `DeleteRecordAction`, `LoginAction`, `UploadMediaAction`, `TreePrivacyAction`  
**Testmuster:** `*ActionIntegrationTest.php`  
**Siehe auch:** Seite (Page), RequestHandler

### Anweisungsüberdeckung
Maß für den Anteil ausgeführter Anweisungen während der Tests. Wird mit `pcov` gemessen.  
**Englisch:** Statement Coverage  
**Aktueller Ratchet-Stand:** 29,3 % (12.897 / 44.043 Anweisungen)  
**Werkzeug:** `make crap-report`  
**Siehe auch:** CRAP-Score, Ratchet

### Artefakt
Ausgabedatei eines Testlaufs (Coverage-Report, Log, Trace-Report). Abgelegt unter `artifacts/`.  
**Unterverzeichnisse:** `artifacts/layer3/` (Coverage, PHPUnit-Log), `artifacts/layer4/` (Playwright-Traces)

---

## B

### Bootstrap
Initialisierungsroutine der Testumgebung. Lädt Autoloader, initialisiert webtrees, registriert Routen.  
**Datei:** `tests/layer3-integration/bootstrap.php`

### Baggage
OpenTelemetry-Mechanismus zur Kontextpropagation über Service-Grenzen hinweg.  
**API:** `OpenTelemetry\API\Baggage\Baggage`  
**Verwendung:** Überträgt Test-Metadaten (z. B. Test-ID) von Playwright zum PHP-Backend

---

## C

### CRAP-Score
**Cyclomatic Complexity Risk Analysis Pattern.** Metrik aus Zyklomatischer Komplexität und Testüberdeckung. Hohe Komplexität + geringe Überdeckung = hoher CRAP-Wert.  
**Englisch:** CRAP Score  
**Werkzeug:** `make crap-report` (erzeugt Tabelle aus `artifacts/layer3/coverage.xml`)  
**Schwelle:** CRAP > 100 oder Überdeckung = 0 % wird hervorgehoben

---

## D

### DataProvider
PHPUnit-Mechanismus für parametrisierte Tests. Liefert mehrere Datensätze an eine einzelne Testmethode.  
**Annotation:** `#[\PHPUnit\Framework\Attributes\DataProvider('methodName')]`  
**Verwendung:** Äquivalenzklassen, Grenzwertanalyse

### Datum (GEDCOM)
GEDCOM-Element für Zeitangaben. Unterstützt verschiedene Kalender (Gregorianisch, Julianisch, Hebräisch, Französisch Republikanisch).  
**Tag:** `DATE`  
**Klasse:** `DateElement` in `upstream/webtrees/app/Elements/`  
**Besonderheit:** Ungenaue Angaben (`ABT`, `BEF`, `AFT`, `BET … AND`) sind valide GEDCOM-Daten

---

## E

### Ehepartner
Person in einer Familienrelation als Gatte oder Gattin.  
**Englisch:** Spouse  
**GEDCOM-Tags:** `HUSB` (Ehemann), `WIFE` (Ehefrau)  
**Datenbank:** `f_husb`, `f_wife` in der Tabelle `families`  
**Siehe auch:** Familie (GEDCOM), FAMS

### Einreicher
Person, die genealogische Daten eingereicht hat.  
**Englisch:** Submitter  
**GEDCOM-Tag:** `0 SUBM`  
**Klasse:** `Submitter extends GedcomRecord`

### Einreichung
Metadaten über eine GEDCOM-Dateneinreichung.  
**Englisch:** Submission  
**GEDCOM-Tag:** `0 SUBN`  
**Klasse:** `Submission extends GedcomRecord`

### Einschränkung (RESN)
Datenschutz-Attribut auf Datensatz-Ebene. Kontrolliert Sichtbarkeit eines Eintrags.  
**GEDCOM-Tag:** `RESN`  
**Werte:** `none`, `privacy`, `confidential`, `locked`  
**Datenbank:** Tabelle `default_resn` für Standard-Einschränkungen je Baum  
**Testklasse:** `TreePrivacyActionIntegrationTest`  
**Siehe auch:** Sichtbarkeit, Datenschutz (P-Serie)

### Element (GEDCOM)
Atomare GEDCOM-Struktur für ein Feld oder einen Tag. Jedes Element hat eine Klasse im Verzeichnis `upstream/webtrees/app/Elements/`.  
**Beispiele:** `NoteStructure`, `TextElement`, `DateElement`, `PlaceElement`

### Ereignis (Fact)
Einzelner Eintrag innerhalb eines GEDCOM-Datensatzes. Beschreibt ein Lebensereignis oder Attribut.  
**Englisch:** Fact  
**Klasse:** `Fact` in `upstream/webtrees/app/Fact.php`  
**Beispiele:** `BIRT` (Geburt), `DEAT` (Tod), `MARR` (Heirat), `OCCU` (Beruf)  
**Testklasse:** `IndividualFactsIntegrationTest`

---

## F

### Familie (GEDCOM)
GEDCOM-Datensatz, der eine Familieneinheit mit Ehepartnern und Kindern beschreibt.  
**Englisch:** Family  
**GEDCOM-Tag:** `0 FAM`  
**Klasse:** `Family extends GedcomRecord`  
**Datenbankpfad:** `families`-Tabelle (`f_file`, `f_id`, `f_husb`, `f_wife`, `f_gedcom`)  
**Testklasse:** `RelationshipDbTest`

### FAMC
GEDCOM-Link-Tag: Individuum als Kind in einer Familie.  
**Bedeutung:** Family as Child  
**Datenbank:** `link`-Tabelle mit `l_type='FAMC'`  
**Siehe auch:** FAMS, Familie (GEDCOM)

### FAMS
GEDCOM-Link-Tag: Individuum als Ehepartner in einer Familie.  
**Bedeutung:** Family as Spouse  
**Datenbank:** `link`-Tabelle mit `l_type='FAMS'`  
**Siehe auch:** FAMC, Familie (GEDCOM)

### Fixture
Vorgefertigte GEDCOM-Testdaten für Integrationstests.  
**Dateien:**
- `fixtures/demo.ged` — Hauptfixture: 72 Individuen, 29 Familien
- `fixtures/gedcom-l-muster.ged` — Deutsche GEDCOM-L-Beispieldatei: 37 Individuen (CC BY 4.0)
- `fixtures/privacy-test-template.ged` — Privacy-Tests

---

## G

### GEDCOM
**GEnealogical Data COMmunication.** Standard-Dateiformat für genealogische Daten. Hierarchisch strukturierter Text mit Tags, Verweisen und Werten.  
**Aktuelle Version:** GEDCOM 5.5.1 (Legacy), GEDCOM-L (Erweiterung für Deutschland)  
**Dateiendung:** `.ged`  
**Zeichenkodierung:** UTF-8 (webtrees), ANSEL (Legacy-Import)  
**Importdienst:** `GedcomImportService`

### GEDCOM-L
Deutsche Erweiterung des GEDCOM-5.5.1-Standards. Enthält zusätzliche Tags für deutsche genealogische Besonderheiten.  
**Fixture:** `fixtures/gedcom-l-muster.ged`  
**Lizenz der Beispieldatei:** CC BY 4.0

### GedcomRecord
Basisklasse aller GEDCOM-Datensatztypen in webtrees.  
**Datei:** `upstream/webtrees/app/GedcomRecord.php`  
**Felder:** `xref`, GEDCOM-Text, Tree-Referenz  
**Unterklassen:** `Individual`, `Family`, `Source`, `Repository`, `Media`, `Note`, `SharedNote`, `Location`, `Submitter`, `Submission`, `Header`

### GedcomImportService
Dienst für den GEDCOM-Dateiimport in einen webtrees-Baum.  
**Methoden:** `importRecord()`, `importString()`  
**Testklassen:** `ImportGedcomActionIntegrationTest`, `RelationshipDbTest`

### Gemeinsame Notiz (SharedNote)
Wiederverwendbare Notiz, die in mehreren Datensätzen referenziert werden kann.  
**Englisch:** Shared Note  
**Klasse:** `SharedNote extends Note`  
**GEDCOM-Tag:** `0 NOTE` mit ID

### Geschwister
Kind-Relation zwischen zwei Individuen mit gemeinsamen Eltern.  
**Englisch:** Sibling  
**GEDCOM:** Gleicher `FAMC`-Eintrag in beiden Individuen

---

## H

### Header (GEDCOM)
Kopfeintrag einer GEDCOM-Datei mit Metadaten über Datei, Software und Zeichenkodierung.  
**GEDCOM-Tag:** `0 HEAD`  
**Klasse:** `Header extends GedcomRecord`

---

## I

### Individuum
Einzelne Person in der genealogischen Datenbank.  
**Englisch:** Individual  
**GEDCOM-Tag:** `0 INDI`  
**Klasse:** `Individual extends GedcomRecord`  
**Datenbankpfad:** `individuals`-Tabelle (`i_file`, `i_id`, `i_gedcom`)

---

## J

### Jaeger
Visualisierungs-UI für OpenTelemetry-Traces.  
**Version:** 2.16.0  
**URL (lokal):** http://localhost:16686  
**Verwendung:** Analyse von Test-Traces (Layer 4 + Layer 5)

---

## K

### Kind
Individuum als Kind in einer Familie.  
**Englisch:** Child  
**GEDCOM-Tag:** `CHIL` in einem `FAM`-Datensatz  
**Datenbanklink:** `FAMC`-Eintrag in `link`-Tabelle

### Komponentenintegrationstest
→ Layer 3. Test, der mehrere Komponenten gemeinsam mit einer echten MySQL-Datenbank testet.  
**Englisch:** Component Integration Test  
**ISTQB-Begriff:** Komponentenintegrationstest  
**Werkzeug:** PHPUnit mit MySQL-Container  
**Basisklasse:** `MysqlTestCase`

### Kopfzeile
→ Header (GEDCOM)

---

## L

### Layer-Architektur
Hierarchische Teststruktur dieses Projekts mit 5 Ebenen.

| Layer | Deutsch | Werkzeug |
|-------|---------|---------|
| Layer 1 | Statischer Test | PHPStan, PHPCS, Trivy |
| Layer 2 | Komponententest / Unit-Test | PHPUnit + SQLite |
| Layer 3 | Komponentenintegrationstest | PHPUnit + MySQL |
| Layer 4 | Systemtest / E2E-Test | Playwright |
| Layer 5 | Performanztest | Playwright + Tracing |

### Location
Geografischer Standort mit Koordinateninformationen.  
**Klasse:** `Location extends GedcomRecord`  
**GEDCOM:** Erweiterter Ortstyp (nicht im GEDCOM-Standard, webtrees-spezifisch)

---

## M

### Medien (Media)
Multimediale Objekte (Fotos, Dokumente, Audiodateien).  
**Englisch:** Media  
**GEDCOM-Tag:** `0 OBJE`  
**Klasse:** `Media extends GedcomRecord`  
**Datenbankpfad:** `media`-Tabelle + `media_file`-Tabelle  
**Testklasse:** `EditMediaFileIntegrationTest`

### Middleware
PSR-15-Komponente in der Anfrageverarbeitungskette. Filtert oder transformiert HTTP-Anfragen und -Antworten.  
**Interface:** `MiddlewareInterface`  
**Beispiel:** `OtelSpansModule` (implementiert `ModuleCustomInterface` + `MiddlewareInterface`)

### Mitglied
Angemeldeter Benutzer ohne Bearbeitungsrechte. Kann eingeschränkte Informationen sehen.  
**Englisch:** Member  
**Rolle:** Mehr Sichtbarkeit als Besucher, weniger als Editor

### Modul
Erweiterungseinheit in webtrees. Implementiert ein oder mehrere `Module*Interface`-Interfaces.  
**Englisch:** Module  
**Basisklasse:** `AbstractModule`  
**Verzeichnis:** `modules_v4/` im webtrees-Verzeichnis  
**Ladedienst:** `ModuleService`

### Moderator
Benutzerrolle mit Rechten zum Genehmigen oder Ablehnen ausstehender Änderungen.  
**Englisch:** Moderator  
**Workflow:** Ausstehende Änderungen → `PendingChangesService`

---

## N

### Nachkomme
Individuum, das von einem anderen abstammt.  
**Englisch:** Descendant  
**Diagramm:** `DescendancyChartModule`

### Notiz (Note)
Freitextnotiz, die einem GEDCOM-Datensatz beigefügt ist.  
**Englisch:** Note  
**GEDCOM-Tag:** `0 NOTE`  
**Klasse:** `Note extends GedcomRecord`  
**Datenbankpfad:** `notes`-Tabelle  
**Siehe auch:** Gemeinsame Notiz (SharedNote)

---

## O

### Ort (Place)
Geografische Ortsangabe in einem GEDCOM-Datensatz.  
**Englisch:** Place  
**GEDCOM-Tag:** `PLAC`  
**Klasse:** `Place` in `upstream/webtrees/app/Place.php`  
**Datenbankpfad:** `places`-Tabelle (hierarchische Struktur), `placelinks`-Tabelle  
**Besonderheit:** Orte sind hierarchisch (z. B. „Berlin, Deutschland")

### OTel (OpenTelemetry)
Observability-Framework für Distributed Tracing im Test-Stack.  
**Status:** Optional, standardmäßig aktiv  
**Deaktivierung:** `OTEL_SDK_DISABLED=true`  
**Protokoll:** OTLP HTTP/Protobuf auf Port 4318  
**Siehe auch:** Jaeger, Span, Trace, Traceparent

### OTel-Collector
Sidecar-Komponente, die Telemetriedaten empfängt, verarbeitet und weiterleitet.  
**Version:** 0.148.0  
**Intern:** gRPC auf Port 4317  
**Extern:** OTLP HTTP auf Port 4318

### OTLP
**OpenTelemetry Protocol.** Standardprotokoll für Telemetriedaten.  
**Format:** HTTP/Protobuf  
**Exporter:** `OTLPTraceExporter`

---

## P

### Peddigree
→ Stammtafel, Ahnenbaum. GEDCOM-Tag `PEDI` beschreibt die Art der Eltern-Kind-Beziehung (biological, adopted, foster).

### Ausstehende Änderung (Pending Change)
Bearbeitungsvorgang, der noch nicht durch einen Moderator genehmigt wurde.  
**Englisch:** Pending Change  
**Datenbank:** `change`-Tabelle (Felder: `change_type`, `change_time`, `change_user`, `pending_changes`)  
**Dienst:** `PendingChangesService`  
**Testklasse:** `PendingChangesIntegrationTest`

---

## Q

### Quelle (Source)
Beleg oder Quelldokument, das in genealogischen Datensätzen referenziert wird.  
**Englisch:** Source  
**GEDCOM-Tag:** `0 SOUR`  
**Klasse:** `Source extends GedcomRecord`  
**Datenbankpfad:** `sources`-Tabelle

### Querverweis (XREF)
Eindeutige Identifikationsreferenz für einen Datensatz innerhalb einer GEDCOM-Datei.  
**Englisch:** Cross-Reference  
**Format:** `@X1030@` (Individuum), `@f1@` (Familie)  
**Verwendung im Test:** `'sour_xref' => 'X1102'` in `DeleteRecordIntegrationTest`  
**Datenbankfelder:** `i_id`, `f_id`, `s_id`, etc.

---

## R

### Ratchet
Mechanismus, der sicherstellt, dass die Testüberdeckung nur steigen, nie fallen darf.  
**Aktueller Stand:** 29,3 % Anweisungsüberdeckung (12.897 / 44.043 Anweisungen)  
**Workflow:** Nach jedem Commit wird der Schwellwert angehoben  
**Überprüfung:** `make test-integration` + `make crap-report`

### Redakteur (Editor)
Benutzerrolle mit Rechten zum Hinzufügen und Bearbeiten von Datensätzen.  
**Englisch:** Editor  
**Besonderheit:** Änderungen werden als ausstehende Änderungen gespeichert, bis ein Moderator sie genehmigt

### Repositorium (Repository)
Institution oder Archiv, das Quellendokumente aufbewahrt.  
**Englisch:** Repository  
**GEDCOM-Tag:** `0 REPO`  
**Klasse:** `Repository extends GedcomRecord`  
**Datenbankpfad:** `other`-Tabelle (zusammen mit Submission und Submitter)

### RequestHandler
PSR-15-kompatibler HTTP-Anfrage-Handler in webtrees.  
**Interface:** `RequestHandlerInterface`  
**Verzeichnis:** `upstream/webtrees/app/Http/RequestHandlers/`  
**Untertypen:** Aktion (Action), Seite (Page)

### Route
URL-zu-Handler-Zuordnung in webtrees.  
**Registry:** `upstream/webtrees/app/Http/Routes/WebRoutes.php`  
**Router:** `Aura\Router\RouterContainer`

---

## S

### Seite (Page)
HTTP-Handler-Typ für GET-Anfragen, die Ansichten zurückgeben.  
**Englisch:** Page  
**Beispiele:** `HomePage`, `IndividualPage`, `FamilyPage`, `SearchPage`, `CalendarPage`  
**Testmuster:** `*PageIntegrationTest.php`  
**Siehe auch:** Aktion (Action), RequestHandler

### Span
Operative Einheit in einem OpenTelemetry-Trace. Beschreibt eine einzelne Operation mit Start- und Endzeitpunkt.  
**Verwendung:** PHP-Backend (Datenbank-Queries, HTTP-Handler), Playwright (Testoperationen)  
**Klasse:** `OtelSpansModule` erzeugt semantische Spans

### Stammtafel
Diagramm, das die Vorfahren einer Person zeigt.  
**Englisch:** Pedigree Chart / Ancestor Chart  
**Modul:** `PedigreeChartModule`, `AncestorsChartModule`  
**Testklasse:** `ChartModuleIntegrationTest`

---

## T

### Trace
Vollständige Aufzeichnung einer Anfrage durch alle Service-Schichten.  
**Hierarchie:** PDO (Datenbankabfragen) → PSR-15 (Middleware) → Browser → Playwright  
**Werkzeug:** `make trace-report` → `scripts/trace-report.py`  
**Visualisierung:** Jaeger UI

### Traceparent
W3C-Trace-Context-Header für die Weitergabe von Trace-Kontext über HTTP.  
**Format:** `00-{traceId}-{spanId}-01`  
**Verwendung:** Von Playwright in HTTP-Anfragen und `page.route()`-Interceptor injiziert  
**Datei:** `tests/layer4-e2e/helpers/otel-fixture.ts`

### Baum (Tree)
Eine GEDCOM-Dateiinstanz in der webtrees-Datenbank. Ein webtrees-System kann mehrere Bäume verwalten.  
**Englisch:** Tree  
**Klasse:** `Tree` in `upstream/webtrees/app/Tree.php`  
**Methoden:** `id()`, `name()`, `title()`  
**Dienst:** `TreeService` (erstellen, löschen, aktualisieren)

---

## U

### Upstream
Der offizielle webtrees-Quellcode als Git-Submodul oder Klon.  
**Pfad:** `upstream/webtrees/` (Standard) oder `${WEBTREES_SOURCE}` (konfigurierbar)  
**Einbindung:** Read-only Mount im Podman-Container

---

## V

### Verwalter (Manager)
Benutzerrolle mit Rechten zur Verwaltung eines Baums (Einstellungen, Datenschutz).  
**Englisch:** Manager  
**Berechtigung:** Kann Datenschutzregeln, Baumeinstellungen und Benutzerzuordnungen ändern

### Vorfahre
→ Ahne

---

## W

### webtrees
Open-Source-Genealogiesoftware (PHP/MySQL), deren Tests dieses Repository testet.  
**Lizenz:** GPL-3.0-or-later  
**Quelle:** https://webtrees.net  
**Upstream-Repo:** `upstream/webtrees/`

---

## X

### XREF
→ Querverweis (XREF)

---

## Z

### Zeige-als-tot (isDead)
Algorithmus in webtrees, der ermittelt, ob ein Individuum als verstorben gilt.  
**Logik:** Explizites `DEAT`-Ereignis, oder Alter > Schwellwert (konfigurierbar), oder Geburt > 120 Jahre  
**Methode:** `Individual::isDead()`  
**Testklasse:** `IsDeadTest`  
**Relevanz:** Steuert Datenschutz für lebende Personen (P-Serie)

---

## Modul-Interfaces (Übersicht)

| Interface | Deutsch | Beschreibung |
|-----------|---------|--------------|
| `ModuleBlockInterface` | Dashboard-Block | Widget auf der Startseite |
| `ModuleTabInterface` | Datensatz-Tab | Tab in Individual-/Familienansicht |
| `ModuleChartInterface` | Diagramm | Stammbaum-Visualisierung |
| `ModuleListInterface` | Listenansicht | Datensatzliste (Individuen, Quellen, …) |
| `ModuleReportInterface` | Bericht | Druckbarer Bericht |
| `ModuleSidebarInterface` | Seitenleiste | Seitenleistenpanel |
| `ModuleThemeInterface` | Thema | UI-Erscheinungsbild |
| `ModuleLanguageInterface` | Sprache | Lokalisierungsmodul |
| `ModuleFooterInterface` | Fußzeile | Fußzeileninhalt |
| `ModuleMenuInterface` | Menüpunkt | Navigationseintrag |
| `ModuleAnalyticsInterface` | Analyse | Tracking-Integration |
| `ModuleMapProviderInterface` | Kartenanbieter | Kartendienst-Integration |
| `ModuleDataFixInterface` | Datenpflege | Datenbereinigungswerkzeug |
| `ModuleConfigInterface` | Konfiguration | Modulkonfiguration |
| `ModuleCustomInterface` | Benutzerdefiniert | Erweiterungsmarkierung |

---

## Testfeature-Serien (Übersicht)

Interne Bezeichnungen aus der Coverage-Iteration für getestete Feature-Gruppen:

| Serie | Bereich |
|-------|---------|
| G-Serie (G02, G07, G08, G21, G24, G30) | GEDCOM-Import/Export |
| S-Serie (S05–S18, S52, S53) | Suche, Diagramme, Charts |
| P-Serie (P01–P41) | Datenschutz, Sichtbarkeit, isDead |
| E-Serie (E01–E08) | Bearbeitung (Edit) |
| A-Serie (A01–A11) | Administration |
| K-Serie (K01–K02) | Kalender |
| SEC-Serie (SEC-WZ, SEC-H, SEC-PUB, SEC-M, SEC-HDR, SEC-UTL) | Sicherheit |

---

## Datenbankschema-Übersicht

| Tabelle | Inhalt |
|---------|--------|
| `individuals` | Individuen (`i_file`, `i_id`, `i_gedcom`) |
| `families` | Familien (`f_file`, `f_id`, `f_husb`, `f_wife`, `f_gedcom`) |
| `sources` | Quellen |
| `media` | Medienobjekte |
| `media_file` | Mediendatei-Referenzen |
| `notes` | Notizen |
| `other` | Repositorium, Submission, Submitter |
| `places` | Ortsangaben |
| `placelinks` | Ortshierarchie |
| `name` | Namensindex |
| `dates` | Datumsindex |
| `link` | Datensatzbeziehungen (FAMS, FAMC, …) |
| `change` | Ausstehende Änderungen / Audit-Log |
| `default_resn` | Standard-Datenschutzregeln je Baum |
