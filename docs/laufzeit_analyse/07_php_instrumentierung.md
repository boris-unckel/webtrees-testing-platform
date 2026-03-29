<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# A7: PHP-Instrumentierung — Ausbaustufe — Analyse

## 1. Fakten

### 1.1 Verfügbare Auto-Instrumentierungs-Pakete (Packagist, Stand 2026-03-29)

28 Pakete existieren unter dem Prefix `open-telemetry/opentelemetry-auto-*`. Relevanzfilter für webtrees:

| Paket | Beschreibung | Version | Relevanz für webtrees | Status |
|---|---|---|---|---|
| `opentelemetry-auto-pdo` | DB-Queries (PDO) | stabil | **Hoch** — webtrees nutzt PDO/MySQL | Bereits installiert |
| `opentelemetry-auto-psr18` | HTTP-Client (PSR-18) | stabil | **Mittel** — GuzzleHttp implements PSR-18 | Bereits installiert |
| `opentelemetry-auto-psr15` | HTTP-Server-Middleware (PSR-15) | 1.2.0 | **Hoch** — webtrees basiert vollständig auf PSR-15 | Kandidat |
| `opentelemetry-auto-guzzle` | GuzzleHttp-Client | 1.3.0 | **Mittel** — webtrees nutzt Guzzle 7.10.0 für Geocoding, Upgrade-Check, Modul-Updates | Kandidat |
| `opentelemetry-auto-curl` | cURL-Aufrufe | 0.2.0 | **Niedrig** — webtrees nutzt kein raw cURL, nur Guzzle (das intern cURL nutzt, aber auf PSR-18-Ebene bereits abgedeckt) | Nicht empfohlen |
| `opentelemetry-auto-psr3` | PSR-3-Logging | 0.2.0 | **Nicht relevant** — webtrees hat keine PSR-3 LoggerInterface-Dependency, kein Monolog, kein psr/log im composer.json | Nicht anwendbar |
| `opentelemetry-auto-io` | Dateisystem-I/O | 0.2.0 | **Niedrig** — GEDCOM-Import (out of scope), Media-Thumbnails | Optional |
| `opentelemetry-auto-psr14` | Event-Dispatcher (PSR-14) | 0.0.6 | **Nicht relevant** — webtrees nutzt kein PSR-14 Event-System | Nicht anwendbar |
| `opentelemetry-auto-psr16` | Simple-Cache (PSR-16) | 0.0.6 | **Nicht relevant** — webtrees nutzt Symfony Cache (PSR-6), nicht PSR-16 | Nicht anwendbar |
| `opentelemetry-auto-psr6` | Cache (PSR-6) | stabil | **Niedrig** — webtrees nutzt Symfony Contracts CacheInterface | Möglicherweise relevant |
| `opentelemetry-auto-symfony` | Symfony Framework | stabil | **Nicht relevant** — webtrees ist kein Symfony-App | Nicht anwendbar |
| `opentelemetry-auto-laravel` | Laravel Framework | stabil | **Nicht relevant** | Nicht anwendbar |
| `opentelemetry-auto-slim` | Slim Framework | stabil | **Nicht relevant** | Nicht anwendbar |
| `opentelemetry-auto-wordpress` | WordPress | stabil | **Nicht relevant** | Nicht anwendbar |
| `opentelemetry-auto-doctrine` | Doctrine ORM | stabil | **Nicht relevant** — webtrees nutzt kein Doctrine | Nicht anwendbar |
| `opentelemetry-auto-session` | PHP-Sessions | neu | **Niedrig** — möglicherweise nützlich für Session-Debugging | Optional |

**Voraussetzung für alle `auto-*`-Pakete:** Die PHP-Extension `ext-opentelemetry` muss installiert sein. Dies ist bereits der Fall — `Containerfile.webtrees` installiert sie via `pecl install opentelemetry`.

### 1.2 webtrees-Architektur: Modulsystem und Middleware

#### 1.2.1 Middleware-Pipeline (Hauptpipeline)

Die globale Middleware-Kette ist in `Webtrees.php` definiert (Zeile 150–176):

```
ErrorHandler -> EmitResponse -> ReadConfigIni -> BaseUrl -> SecurityHeaders ->
HandleExceptions -> PublicFiles -> ClientIp -> ContentLength -> CompressResponse ->
BadBotBlocker -> UseDatabase -> DebugLogger -> UpdateDatabaseSchema -> UseSession ->
UseLanguage -> CheckForMaintenanceMode -> UseTheme -> DoHousekeeping ->
UseTransaction -> CheckForNewVersion -> LoadRoutes -> RegisterGedcomTags ->
BootModules -> Router
```

Der **Router** (`app/Http/Middleware/Router.php`) ist die letzte Station der Hauptpipeline. Er matcht die Route und baut eine **zweite, innere Pipeline** auf (Zeile 101–109):

```php
$route_middleware = $route->extras['middleware'] ?? [];
$module_middleware = $this->module_service->findByInterface(MiddlewareInterface::class)->all();
$middleware = [
    ...$route_middleware,        // Auth-Middleware (z.B. AuthEditor, AuthManager)
    CheckCsrf::class,
    ...$module_middleware,       // <-- HIER: Alle Module die MiddlewareInterface implementieren
    RequestHandler::class,       // Führt den eigentlichen Handler aus
];
```

**Entscheidend:** Module, die `MiddlewareInterface` implementieren, werden automatisch in die innere Pipeline eingefügt — **nach** den Auth-Middlewares und **vor** dem `RequestHandler`. Das ist der perfekte Injektionspunkt für Custom Spans.

#### 1.2.2 Modul-Erkennung

Custom Modules werden aus `modules_v4/*/module.php` geladen (`ModuleService::customModules()`). Der Mechanismus:

1. `glob(Webtrees::MODULES_DIR . '*/module.php')` findet alle Module
2. `module.php` muss eine Instanz von `ModuleCustomInterface` zurückgeben
3. Modulname wird aus dem Verzeichnisnamen abgeleitet, mit `_` Prefix/Suffix: `_modulname_`
4. Maximale Namenslänge: 30 Zeichen
5. Keine Punkte, Leerzeichen, eckige Klammern im Verzeichnisnamen

#### 1.2.3 Vorhandene Modul-Middleware-Beispiele

Zwei Core-Module implementieren bereits `MiddlewareInterface`:

1. **`HitCountFooterModule`** (`implements ModuleFooterInterface, MiddlewareInterface`):
   - Intercepts Requests in `process()`, erkennt die Route via `Validator::attributes($request)->route()`
   - Liest `$route->name` (das ist der FQCN des RequestHandlers, z.B. `IndividualPage::class`)
   - Liest `xref` aus Request-Attributen: `Validator::attributes($request)->isXref()->string('xref')`
   - Liest `tree` optional
   - **Genau dieses Muster ist die Vorlage für Custom OTel-Spans**

2. **`CheckForNewVersion`** (`implements MiddlewareInterface`):
   - Einfachere Middleware ohne Route-Analyse

#### 1.2.4 Verfügbare Request-Attribute im Middleware-Kontext

Durch die Position in der Pipeline (nach Router, vor RequestHandler) stehen folgende Attribute zur Verfügung:

| Attribut | Typ | Quelle |
|---|---|---|
| `route` | `Aura\Router\Route` | Router-Middleware extrahiert die gematchte Route |
| `route->name` | `string` | FQCN des RequestHandlers (z.B. `IndividualPage::class`) |
| `tree` | `Tree\|null` | Aus Route-Parametern, falls `{tree}` im Pfad |
| `xref` | `string\|null` | Aus Route-Parametern, falls `{xref}` im Pfad |
| `base_url` | `string` | Von BaseUrl-Middleware gesetzt |

#### 1.2.5 Route-Mapping: User-Interaktionen zu RequestHandler-Klassen

Aus `WebRoutes.php` (Zeilen 660–705):

**Daten-Abfrage (View):**

| Interaktion | HTTP-Methode | Pfad | Route-Name (Handler-Klasse) |
|---|---|---|---|
| Person ansehen | GET | `/tree/{tree}/individual/{xref}{/slug}` | `IndividualPage::class` |
| Familie ansehen | GET | `/tree/{tree}/family/{xref}{/slug}` | `FamilyPage::class` |
| Quelle ansehen | GET | `/tree/{tree}/source/{xref}{/slug}` | `SourcePage::class` |
| Medien ansehen | GET | `/tree/{tree}/media/{xref}{/slug}` | `MediaPage::class` |
| Notiz ansehen | GET | `/tree/{tree}/note/{xref}{/slug}` | `NotePage::class` |
| Repository ansehen | GET | `/tree/{tree}/repository/{xref}{/slug}` | `RepositoryPage::class` |
| Baum-Startseite | GET | `/tree/{tree}` | `TreePage::class` |
| Suche allgemein | GET/POST | `/tree/{tree}/search-general` | `SearchGeneralPage::class` / `SearchGeneralAction::class` |
| Suche erweitert | GET/POST | `/tree/{tree}/search-advanced` | `SearchAdvancedPage::class` / `SearchAdvancedAction::class` |
| Suche phonetisch | GET/POST | `/tree/{tree}/search-phonetic` | `SearchPhoneticPage::class` / `SearchPhoneticAction::class` |

**Daten-Bearbeitung (Edit):**

| Interaktion | HTTP-Methode | Pfad | Route-Name (Handler-Klasse) |
|---|---|---|---|
| Fakt bearbeiten (Seite) | GET | `/tree/{tree}/edit-fact/{xref}/{fact_id}` | `EditFactPage::class` |
| Fakt speichern | POST | `/tree/{tree}/update-fact/{xref}{/fact_id}` | `EditFactAction::class` |
| Record bearbeiten | GET | `/tree/{tree}/edit-record/{xref}` | `EditRecordPage::class` |
| Record speichern | POST | `/tree/{tree}/update-record/{xref}` | `EditRecordAction::class` |
| Kind hinzufügen | POST | `/tree/{tree}/add-child-to-individual/{xref}` | `AddChildToIndividualAction::class` |
| Ehepartner hinzufügen | POST | `/tree/{tree}/add-spouse-to-individual/{xref}` | `AddSpouseToIndividualAction::class` |
| Record löschen | POST | `/tree/{tree}/delete/{xref}` | `DeleteRecord::class` |
| Fakt löschen | POST | `/tree/{tree}/delete/{xref}/{fact_id}` | `DeleteFact::class` |

### 1.3 Bestehende OTel-Infrastruktur

- **Container:** `ext-opentelemetry` installiert (pecl), `protobuf` und `grpc` Extensions vorhanden
- **Composer:** `open-telemetry/sdk`, `open-telemetry/exporter-otlp`, `auto-pdo`, `auto-psr18` installiert
- **Konfiguration:** Über Umgebungsvariablen in `compose.yaml`:
  - `OTEL_PHP_AUTOLOAD_ENABLED=true` (automatische Instrumentierung aktiv)
  - `OTEL_SERVICE_NAME=webtrees`
  - `OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4317` (gRPC)
  - Traces enabled, Metrics/Logs disabled
- **Backend:** otel-collector (Contrib-Image) exportiert an Jaeger (Port 16686)

---

## 2. Bewertung

### 2.1 Zusätzliche Auto-Instrumentierung

#### 2.1.1 `opentelemetry-auto-psr15` — EMPFOHLEN

**Machbarkeit: Hoch.** webtrees ist eine vollständig PSR-15-basierte Anwendung. Jeder Request durchläuft mindestens 25 Middleware-Layer. Das Paket würde automatisch:

- Einen Root-Span pro HTTP-Request erzeugen (mit HTTP-Method, Status-Code, URL)
- Spans für jede Middleware in der Pipeline erzeugen
- Spans für den finalen RequestHandler erzeugen

**Aufwand:** 1 Zeile in `setup-webtrees.sh` (Composer-require).

**Risiko:** Gering. Das Paket hookt auf Interface-Ebene (PSR-15), nicht auf Framework-Ebene. Da webtrees PSR-15 sauber implementiert, ist Kompatibilität sehr wahrscheinlich.

**Wert:** Hoch. Ohne dieses Paket gibt es keinen übergeordneten Request-Span, der die PDO-Spans als Children einordnet. Mit `auto-psr15` bekommt man eine vollständige Trace-Hierarchie: Request → Middleware → Handler → DB-Queries.

#### 2.1.2 `opentelemetry-auto-guzzle` — OPTIONAL

**Machbarkeit: Hoch.** webtrees nutzt Guzzle 7.10.0 für:
- Upgrade-Check (`UpgradeService`)
- Geocoding-Autocomplete (`GeonamesAutocomplete`, `OpenRouteServiceAutocomplete`)
- Modul-Update-Checks (`ModuleCustomTrait`)

**Aufwand:** 1 Zeile in `setup-webtrees.sh`.

**Risiko:** Gering. Beachten: `auto-psr18` ist bereits installiert und Guzzle implementiert PSR-18. Es gibt möglicherweise doppelte Spans (PSR-18-Span und Guzzle-Span für denselben HTTP-Call). In der Praxis ist das akzeptabel, da die Guzzle-Instrumentierung mehr Details liefert (Guzzle-Middleware-Stack).

**Wert:** Niedrig bis mittel. Die externen HTTP-Calls sind selten (einmal pro Session/Upgrade-Check). Für User-Interaktionen (Scope dieses Tickets) irrelevant.

#### 2.1.3 `opentelemetry-auto-curl`, `auto-io`, `auto-psr3`, `auto-psr14`, `auto-psr16` — NICHT EMPFOHLEN

- **curl:** Redundant zu psr18/guzzle. webtrees nutzt kein raw cURL.
- **io:** Potentiell nützlich für Media-File-Zugriff, aber GEDCOM-Import ist out of scope. Kein Mehrwert für User-Interaktionen.
- **psr3:** webtrees hat keine PSR-3-Logger-Dependency (`psr/log` nicht im `composer.json`).
- **psr14:** webtrees nutzt kein PSR-14 Event-Dispatching.
- **psr16:** webtrees nutzt Symfony Cache Contracts (PSR-6-kompatibel), nicht PSR-16 SimpleCache.

### 2.2 Custom Spans via webtrees-Modul

#### 2.2.1 Machbarkeit: BESTÄTIGT — Funktioniert ohne Upstream-Änderung

Der Mechanismus ist bewiesen durch das existierende `HitCountFooterModule`:

1. Ein Custom Module kann `MiddlewareInterface` implementieren
2. Es wird automatisch in die innere Request-Pipeline eingefügt (Router.php, Zeile 103)
3. Es hat Zugriff auf die gematchte Route und alle Request-Attribute
4. Es kann `$handler->handle($request)` wrappen (Before/After-Pattern)

**Konkreter Ansatz:** Ein Modul `otel-spans` in `modules_v4/otel-spans/module.php` mit:

```
modules_v4/otel-spans/
  module.php            — Return new OtelSpansModule()
  OtelSpansModule.php   — extends AbstractModule implements ModuleCustomInterface, MiddlewareInterface
```

Das Modul würde:

1. In `process()` den Route-Namen extrahieren (`$route->name`)
2. Einen Span starten mit semantischen Attributen
3. `$handler->handle($request)` ausführen
4. Den Span beenden (mit Status-Code aus der Response)

**Route-Name-zu-Aktion-Mapping** (Beispiel):

```php
private const ROUTE_MAP = [
    IndividualPage::class       => ['action' => 'view_individual',  'type' => 'query'],
    FamilyPage::class           => ['action' => 'view_family',      'type' => 'query'],
    SourcePage::class           => ['action' => 'view_source',      'type' => 'query'],
    SearchGeneralPage::class    => ['action' => 'search_general',   'type' => 'query'],
    SearchGeneralAction::class  => ['action' => 'search_general',   'type' => 'query'],
    EditFactPage::class         => ['action' => 'edit_fact_form',   'type' => 'edit'],
    EditFactAction::class       => ['action' => 'edit_fact_save',   'type' => 'edit'],
    DeleteRecord::class         => ['action' => 'delete_record',    'type' => 'edit'],
    // ...
];
```

**Semantische Attribute pro Span:**

| Attribut | Quelle | Beispiel |
|---|---|---|
| `webtrees.action` | Route-Map | `view_individual` |
| `webtrees.type` | Route-Map | `query` oder `edit` |
| `webtrees.tree` | `Validator::attributes($request)->treeOptional()` | `demo` |
| `webtrees.xref` | `Validator::attributes($request)->string('xref', '')` | `I123` |
| `webtrees.route` | `$route->name` | `IndividualPage` |
| `http.method` | `$request->getMethod()` | `GET` |
| `http.status_code` | `$response->getStatusCode()` | `200` |

#### 2.2.2 Aufwand

- **Modul-Entwicklung:** ca. 1 PHP-Datei, ~100–150 Zeilen
- **Integration:** Mounting via `compose.yaml` Volume (oder direktes Anlegen in `modules_v4/`)
- **Aktivierung:** Automatisch — neue Module sind per Default enabled (Zeile 44 in `AbstractModule.php`: `private bool $enabled = true`)
- **Abhängigkeit:** Nur `open-telemetry/api` (bereits als transitive Dependency des SDK vorhanden)

#### 2.2.3 Risiken

| Risiko | Bewertung | Mitigation |
|---|---|---|
| Modul wird nicht geladen, wenn DB nicht verfügbar | Niedrig | Module werden erst nach `UseDatabase` geladen |
| Performance-Overhead durch Span-Erzeugung | Vernachlässigbar | Ein Span-Start dauert <0.1ms, gegenüber ~50–500ms Request-Verarbeitung |
| Upstream-Änderung der Route-Namen | Mittel | Route-Namen sind FQCN — ändern sich nur bei Klassen-Rename. Ungemappte Routen werden einfach ignoriert. |
| `$route`-Attribut fehlt (404-Requests) | Niedrig | Null-Check auf Route-Attribut, kein Span für nicht-gematchte Requests |
| OTel SDK nicht installiert (OTEL_SDK_DISABLED=true) | Gering | Modul prüft `class_exists()` für OpenTelemetry-Klassen vor Nutzung; oder NoOp-Tracer wird automatisch verwendet, wenn SDK disabled |

### 2.3 Wertanalyse: Custom Spans vs. reine Auto-Instrumentierung

#### Was Auto-Instrumentierung (PDO + PSR-15 + PSR-18) allein liefert:

1. **Request-Trace:** HTTP Method + URL + Status Code + Dauer
2. **Middleware-Spans:** Welche Middleware wie lange läuft
3. **DB-Query-Spans:** Jede SQL-Query mit Statement und Dauer
4. **HTTP-Client-Spans:** Ausgehende HTTP-Requests

**Fehlt ohne Custom Spans:**

1. **Semantische Einordnung:** Ein Trace zeigt "GET /tree/demo/individual/I123/max-mustermann" — aber nicht "view_individual". Ein Dashboard müsste URL-Pattern parsen, um Aktionstypen zu aggregieren.
2. **XREF-Korrelation:** Welcher GEDCOM-Record wurde angefragt? Ohne Custom Spans nur durch URL-Parsing möglich.
3. **Baum-Korrelation:** Welcher Baum war betroffen? Wieder nur durch URL-Parsing.
4. **Query/Edit-Klassifizierung:** Ob eine Aktion lesend oder schreibend war, ist nur aus HTTP-Method ableitbar (unzuverlässig, da GET-Requests auch Daten ändern können).
5. **Geschäftslogik-Metriken:** "Wie lange dauert das Anzeigen einer Person im Durchschnitt?" erfordert eine gezielte Span-Gruppierung nach `webtrees.action`.

**Mehrwert der Custom Spans:**

| Capability | Nur Auto-Instr. | Mit Custom Spans |
|---|---|---|
| Request-Dauer messen | Ja | Ja |
| DB-Queries pro Request | Ja | Ja |
| Aktionstyp erkennen | Nein (URL-Parsing nötig) | Ja (`webtrees.action`) |
| Baum-Name im Trace | Nein | Ja (`webtrees.tree`) |
| XREF im Trace | Nein | Ja (`webtrees.xref`) |
| Dashboard: "Langsamste Aktionstypen" | Schwierig | Trivial (Group by `webtrees.action`) |
| Dashboard: "Queries pro Baum" | Unmöglich | Trivial (Group by `webtrees.tree`) |
| Alerting auf Edit-Aktionen | Schwierig | Trivial (Filter `webtrees.type=edit`) |

**Fazit:** Auto-Instrumentierung liefert die technische Basis (Request, DB, HTTP). Custom Spans liefern die **geschäftliche Semantik** (was tut der User, in welchem Kontext). Für eine Testing-Plattform, die User-Interaktionen testen will, sind die Custom Spans der eigentliche Mehrwert.

---

## 3. Empfehlung

### Schritt 1: `opentelemetry-auto-psr15` installieren (Quick Win)

**In `setup-webtrees.sh`** die Composer-require-Zeile erweitern:

```
open-telemetry/opentelemetry-auto-psr15
```

Dies liefert sofort einen vollständigen Request-Span als Parent für alle PDO-Spans und ergibt eine saubere Trace-Hierarchie. Aufwand: minimal (1 Zeile). Kein Code nötig.

### Schritt 2: Custom-Span-Modul entwickeln

Ein webtrees-Modul `otel-spans` erstellen, das:

1. `ModuleCustomInterface` und `MiddlewareInterface` implementiert
2. In `process()` die Route analysiert und semantische Spans erzeugt
3. Das HitCountFooterModule als bewiesene Vorlage nutzt (identisches Pattern: Route-Name lesen, Attribute extrahieren, Aktion ableiten)

**Technische Besonderheit:** Das Modul muss die OpenTelemetry-API nutzen, nicht das SDK. Die API (`OpenTelemetry\API\Globals::tracerProvider()`) holt sich den konfigurierten Tracer automatisch. Wenn OTel disabled ist (`OTEL_SDK_DISABLED=true`), liefert die API einen NoOp-Tracer — das Modul muss sich nicht um diesen Fall kümmern.

**Platzierung:** Das Modul gehört in die Testing-Plattform (dieses Repo), nicht upstream. Es wird via Podman-Volume in den Container gemountet, analog zum bestehenden Modul-Mounting-Mechanismus.

### Schritt 3 (optional): `opentelemetry-auto-guzzle` installieren

Nur sinnvoll, wenn ausgehende HTTP-Calls (Geocoding, Upgrade-Check) beobachtet werden sollen. Für den Scope "User-Interaktionen" nicht erforderlich.

---

## 4. Offene Punkte

### 4.1 Vor Implementierung zu klären

1. **Modul-Platzierung:** Soll das `otel-spans`-Modul als eigenes Verzeichnis in der Testing-Plattform leben (z.B. `modules/otel-spans/`) und per Volume-Mount eingebunden werden? Oder direkt in einem `modules_v4/`-Verzeichnis innerhalb der Plattform, das per Volume überlagert wird?

2. **Span-Name-Konvention:** Soll der Span-Name dem OpenTelemetry HTTP Semantic Convention folgen (`HTTP GET /tree/{tree}/individual/{xref}`) oder eine eigene Benennung verwenden (`webtrees.view_individual`)?

3. **Granularität der Route-Map:** Sollen alle ~80 Routes gemappt werden, oder nur die im Scope genannten (View Individual, View Family, Search, Edit)?

4. **Interaktion mit `auto-psr15`:** Wenn sowohl `auto-psr15` als auch das Custom-Modul Spans erzeugen, entstehen zwei Spans pro Request (einer generisch, einer semantisch). Ist das gewünscht, oder soll das Custom-Modul den PSR-15-Span als Parent verwenden und ihn mit Attributen anreichern statt einen eigenen zu erzeugen?

5. **Query-Parameter in Spans:** Sollen Suchbegriffe (`query=...`) als Span-Attribute erfasst werden? Datenschutz-Implikation: Suchbegriffe könnten Personennamen enthalten. In einer reinen Testing-Plattform mit Testdaten ist das unproblematisch, sollte aber dokumentiert werden.

### 4.2 Technische Validierung nötig

6. **ext-opentelemetry Kompatibilität mit PHP 8.5:** Der Container nutzt `php:8.5-apache`. Die `pecl install opentelemetry` im Containerfile bestätigt, dass die Extension gebaut wird. Ob `auto-psr15` 1.2.0 mit PHP 8.5 kompatibel ist, muss getestet werden (Requirement: `php: ^8.1`).

7. **Aura Router Route-Objekt in Attributen:** Die Route wird als Request-Attribut `route` gespeichert, aber der Validator erwartet ein `Route`-Objekt. Ob das Custom-Modul direkt `$request->getAttribute('route')` oder `Validator::attributes($request)->route()` verwenden soll, hängt davon ab, ob das Modul die webtrees Validator-Klasse nutzen darf (Autoload-Abhängigkeit vorhanden, da das Modul im webtrees-Kontext läuft).

---

## Anhang: Architektur-Diagramm (vereinfacht)

```
HTTP Request
    |
    v
[Webtrees::MIDDLEWARE — Hauptpipeline]
    ErrorHandler -> ... -> UseDatabase -> ... -> BootModules -> Router
                                                                   |
                                                                   v
                                                    [Innere Pipeline]
                                                    AuthEditor/AuthManager/...
                                                    CheckCsrf
                                                    HitCountFooterModule::process()   <-- bestehend
                                                    OtelSpansModule::process()         <-- NEU
                                                    RequestHandler::process()
                                                        |
                                                        v
                                                    IndividualPage::handle()
                                                    FamilyPage::handle()
                                                    SearchGeneralAction::handle()
                                                    EditFactAction::handle()
                                                    ...
```

---

## Anhang: Quelldateien (Referenzen)

| Datei | Relevanz |
|---|---|
| `upstream/webtrees/app/Webtrees.php` (Zeile 150–176) | Middleware-Pipeline-Definition |
| `upstream/webtrees/app/Http/Middleware/Router.php` (Zeile 101–109) | Module-Middleware-Injection in innere Pipeline |
| `upstream/webtrees/app/Module/HitCountFooterModule.php` | Bewiesenes Muster: Modul als Middleware mit Route-Analyse |
| `upstream/webtrees/app/Http/Routes/WebRoutes.php` (Zeile 660–717) | Route-Definitionen für User-Interaktionen |
| `upstream/webtrees/app/Services/ModuleService.php` (Zeile 682–713) | Custom-Module-Ladung aus `modules_v4/` |
| `upstream/webtrees/app/Http/Dispatcher.php` | Middleware-Pipeline-Ausführung |
| `scripts/setup-webtrees.sh` (Zeile 43–57) | Bestehende OTel-Paketinstallation |
| `Containerfile.webtrees` (Zeile 32–34) | ext-opentelemetry Installation |
| `compose.yaml` (Zeile 41–49) | OTel-Umgebungsvariablen |
