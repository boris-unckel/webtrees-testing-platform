<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Analyseergebnis: Layer-2-Komponentests — IST-Zustand, Maintainer-Feedback, Portierungsstrategie

**Erstellt:** 2026-04-09
**Eingabe:** `docs/port_analysis_start.md`
**Fork:** `/home/borisunckel/phpprojects/webtrees-upstream/webtrees` (Branch `main` + `5349_add_tests`)

---

## A. Begriffliche Klärung: Test Doubles vs. Mocks

### Taxonomie nach Gerard Meszaros ("xUnit Test Patterns")

"Test Double" ist der **Oberbegriff** für jedes Objekt, das in einem Test eine produktive Abhängigkeit ersetzt — analog zum Stunt-Double im Film. Es gibt fünf Untertypen:

| Typ | Definition | Wann verwenden | PHPUnit-Äquivalent |
|-----|-----------|----------------|---------------------|
| **Dummy** | Wird übergeben, aber nie aufgerufen. Füllt eine Konstruktor-Signatur. | Pflicht-Parameter, den der getestete Codepfad nicht berührt | `$this->createStub(Foo::class)` (ohne `method()`-Konfiguration) |
| **Stub** | Liefert vorkonfigurierte Antworten auf Methodenaufrufe. Keine Verhaltensverifikation. | Wenn der Test kontrollierte Eingabewerte braucht, aber nicht prüfen will, *ob* die Methode aufgerufen wurde | `self::createStub(Foo::class)` + `->method('bar')->willReturn(...)` |
| **Mock** | Wie Stub, aber mit **Erwartungen** (Verhaltensverifikation): *wurde* die Methode aufgerufen? Wie oft? Mit welchen Argumenten? | Wenn der Test verifizieren muss, dass die SUT eine bestimmte Interaktion mit der Abhängigkeit durchführt | `$this->createMock(Foo::class)` + `->expects($this->once())->method('bar')->with('arg')->willReturn(...)` |
| **Spy** | Zeichnet Aufrufe auf, die der Test nachträglich prüft. | Wenn die Verifikation nach dem `handle()`-Aufruf erfolgen soll, nicht vorab deklariert | PHPUnit hat keinen nativen Spy — Workaround über Callback-Mocks oder eigene Klassen |
| **Fake** | Funktionierende, aber vereinfachte Implementierung (z.B. In-Memory-Repository statt DB). | Wenn Stub/Mock zu aufwändig wäre, weil die Abhängigkeit ein komplexes Interface hat | Anonyme Klassen: `new class extends AbstractModule { ... }` |

### PHPUnit-Mapping: `createStub()` vs. `createMock()`

```php
// STUB — kein expects(), rein datengetrieben
$tree = self::createStub(Tree::class);        // statischer Aufruf
$tree->method('name')->willReturn('tree1');

// MOCK — mit expects(), verhaltensgetrieben
$tree_service = $this->createMock(TreeService::class);  // Instanz-Aufruf
$tree_service
    ->expects($this->once())
    ->method('all')
    ->willReturn(new Collection(['tree1' => $tree]));
```

**Konvention im webtrees-Codebase:**
- `self::createStub()` = statischer Aufruf, nie mit `expects()`
- `$this->createMock()` = Instanz-Aufruf, immer mit `expects()`
- Domain-Objekte (`Tree`, `Individual`, `Family`, `User`) → **Stubs**
- Services und Factories (`TreeService`, `ModuleService`, `IndividualFactory`) → **Mocks**

### Warum der Maintainer "Test Doubles" sagt, nicht "Mocks"

Der Maintainer wählt bewusst den Oberbegriff, weil nicht alle Abhängigkeiten gemockt (mit Erwartungen versehen) werden müssen. Für manche genügt ein Stub (z.B. ein `Tree`-Objekt, das `name()` zurückgibt), für andere ist ein Fake passender (z.B. die anonyme Modulklasse in `ModuleActionTest`). Die Aussage "should be provided as Test Doubles" schließt alle fünf Typen ein.

---

## B. Analyse der bestehenden substanziellen Tests auf `main`

### Bestandsübersicht

Von 333 RequestHandler-Testdateien unter `tests/app/Http/RequestHandlers/`:

| Kategorie | Anzahl | Beschreibung |
|-----------|--------|--------------|
| Stub-Tests | 292 | Nur `testClass()` → `assertTrue(class_exists(...))` |
| Substanziell mit Test Doubles | 40 | Echte Testmethoden mit `createMock()`/`createStub()` |
| Substanziell ohne Test Doubles | 1 | `SelectLanguageTest` — siehe Sonderfall |
| **Gesamt** | **333** | |

### B.1 Pattern-Katalog

#### Pattern A: Constructor Injection + `createMock()` (Interaktionsverifikation)

Die SUT-Klasse wird mit `new` instanziiert, wobei jede Konstruktor-Abhängigkeit ein Mock ist. `expects($this->once())` verifiziert, dass der Handler die Dependency korrekt aufruft.

```php
// ModuleActionTest.php
$module_service = $this->createMock(ModuleService::class);
$module_service
    ->expects($this->once())
    ->method('findByName')
    ->with('test')
    ->willReturn($this->fooModule());

$handler = new ModuleAction($module_service);
$response = $handler->handle($request);
```

**Verwendung:** Alle 40 substanziellen Tests (außer SelectLanguageTest). Betrifft Services wie `TreeService`, `ModuleService`, `UserService`, `ServerCheckService`, `MessageService`, `UpgradeService`.

#### Pattern B: Constructor Injection + `createStub()` (keine Verifikation)

Wenn eine Dependency zwar im Konstruktor steht, aber im getesteten Codepfad nicht aufgerufen wird (z.B. Exception wird vorher geworfen):

```php
// RedirectModulePhpTest::testNoSuchTree()
$module_service = self::createStub(ModuleService::class);  // nie aufgerufen
$tree_service = $this->createMock(TreeService::class);      // wird aufgerufen
$tree_service->expects($this->once())->method('all')->willReturn(new Collection([]));

$handler = new RedirectModulePhp($module_service, $tree_service);
```

**Verwendung:** In `testNoSuchTree()`/`testMissingParameter()`-Methoden aller Redirect-Tests, sowie `BroadcastPageTest` (`MessageService` als Stub).

#### Pattern C: Registry-Injection (globale Factory-Ersetzung)

Wenn die SUT nicht über den Konstruktor, sondern über das statische `Registry`-Singleton auf eine Factory zugreift:

```php
// RedirectModulePhpTest::testRedirectPedigreeMap()
$individual_factory = $this->createMock(IndividualFactory::class);
$individual_factory
    ->expects($this->once())
    ->method('make')
    ->with('X123', $tree)
    ->willReturn($individual);

Registry::individualFactory($individual_factory);
```

**Factory-Typen via Registry (vollständig):**

| Registry-Methode | Mock-Typ | Verwendende Tests |
|-----------------|----------|-------------------|
| `Registry::individualFactory()` | `IndividualFactory` | 11 Redirect-Tests (Ancestry, Compact, Descendancy, FamilyBook, FanChart, HourGlass, Individual, ModulePhp, Pedigree, Relationship, TimeLine) |
| `Registry::familyFactory()` | `FamilyFactory` | RedirectFamilyPhp |
| `Registry::mediaFactory()` | `MediaFactory` | RedirectMediaViewerPhp |
| `Registry::sourceFactory()` | `SourceFactory` | RedirectSourcePhp |
| `Registry::noteFactory()` | `NoteFactory` | RedirectNotePhp |
| `Registry::repositoryFactory()` | `RepositoryFactory` | RedirectRepositoryPhp |
| `Registry::gedcomRecordFactory()` | `GedcomRecordFactory` | RedirectGedRecordPhp |

**7 Factory-Typen in 18 Testdateien.** Registry-Injection ist immer mit `createMock()` (nicht `createStub()`), da die Factory-Aufrufe verifiziert werden.

#### Pattern D: Anonyme Klasse als Fake

Exakt eine Verwendung im gesamten Codebase:

```php
// ModuleActionTest.php
private function fooModule(): ModuleInterface
{
    return new class () extends AbstractModule {
        public function getTestAction(): ResponseInterface
        {
            return response('It works!');
        }
    };
}
```

Notwendig, weil `ModuleAction` dynamisch via Reflection `get{Action}Action()`-Methoden aufruft — das kann nicht sinnvoll gemockt werden.

#### Pattern E: Request-Attribute als Middleware-Ersatz

Die PSR-15-Middleware-Pipeline setzt Attribute auf den Request (User, Tree, Route-Params). In Unit-Tests wird die Middleware umgangen — Attribute werden manuell gesetzt:

| Attribut-Typ | Code | Dateien |
|-------------|------|---------|
| User-Objekt | `->withAttribute('user', $user)` | ModuleAction, Masquerade, SelectTheme, SelectLanguage |
| Route-Parameter (ID) | `->withAttribute('user_id', 2)` | Masquerade, DeleteUser |
| Token | `->withAttribute('token', '1234')` | PasswordResetPage |
| Module/Action | `->withAttribute('module', 'test')->withAttribute('action', 'Test')` | ModuleAction |
| Sprache/Theme | `->withAttribute('language', 'fr')` / `->withAttribute('theme', 'FOO')` | SelectLanguage, SelectTheme |
| Legacy-Query-Params | `createRequest(GET, ['ged' => 'tree1', 'pid' => 'X123'])` | Alle 29 Redirect-Tests |

**Wichtig:** `createRequest()` aus `TestCase` setzt automatisch `user` (GuestUser), `client-ip`, `base_url` und `route`. Tests überschreiben per `withAttribute()` nur bei Bedarf.

#### Pattern F: Stub + Mock Kombination im selben Test

Standardmuster in den Redirect-Tests — Domain-Objekte sind Stubs, Services/Factories sind Mocks:

```php
$tree = self::createStub(Tree::class);              // STUB: liefert nur 'tree1'
$tree->method('name')->willReturn('tree1');

$tree_service = $this->createMock(TreeService::class);   // MOCK: muss einmal aufgerufen werden
$tree_service->expects($this->once())->method('all')
    ->willReturn(new Collection(['tree1' => $tree]));

$individual = self::createStub(Individual::class);        // STUB: liefert nur URL
$individual->method('url')->willReturn('https://www.example.com');

$individual_factory = $this->createMock(IndividualFactory::class);  // MOCK
$individual_factory->expects($this->once())->method('make')
    ->with('X123', $tree)->willReturn($individual);
```

**Regel:** Domain-Objekte (`Tree`, `Individual`, `Family`, `Source`, `Note`, `Repository`, `Media`, `GedcomRecord`, `User`) sind **immer** Stubs. Services und Factories sind Mocks, wenn Interaktionsverifikation nötig, sonst Stubs.

### B.2 Anforderungsprofil (Template für neue Tests)

Die 41 substanziellen Tests erfüllen gemeinsam folgende Anforderungen:

| # | Anforderung | Beschreibung | Ausnahmen |
|---|-------------|-------------|-----------|
| R1 | **SUT-Isolation** | Nur die Handler-Klasse wird mit `new` instanziiert. Kein Middleware, kein Routing, keine Container-Resolution. | — |
| R2 | **Constructor-Deps sind Test Doubles** | Jeder Konstruktor-Parameter ist Mock, Stub oder leichtgewichtige reale Instanz. | UpgradeWizardStepTest (mischt real + mock, siehe B.4) |
| R3 | **Interaktionsverifikation** | `expects($this->once())` für Services, deren Aufruf getestet wird. Keine `atLeastOnce()` oder `exactly(N>1)`. | — |
| R4 | **Return-Werte steuern Codepfade** | Verschiedene `willReturn()`-Werte testen Success/Failure/Edge-Cases desselben Handlers. | — |
| R5 | **Exception-Pfade explizit** | `expectException()` + `expectExceptionMessage()` *vor* `handle()` platziert. 5 Exception-Typen abgedeckt: `HttpGone`, `HttpNotFound`, `HttpAccessDenied`, `HttpBadRequest`, `HttpServerError`. | — |
| R6 | **Kein `$uses_database` nötig** | Die meisten Tests brauchen keine DB. | 38 von 41 nutzen `$uses_database = true` wegen TestCase-Bootstrap; 3 kommen ohne aus (PingTest, ModuleActionTest, SelectThemeTest) |
| R7 | **Status-Code-Assertions** | Jeder Test assertiert den exakten HTTP-Statuscode: 200, 204, 301, 302, 500, 503. | — |
| R8 | **Response-Body-Assertions** | Wo der Body relevant ist: `assertSame('OK', (string)$response->getBody())`. | Nicht bei Redirects |
| R9 | **Location-Header-Assertions** | Redirect-Tests prüfen den Location-Header: `assertSame('https://...', $response->getHeaderLine('Location'))`. | — |
| R10 | **State-Assertions (Seiteneffekte)** | `MasqueradeTest`: Session-State nach Handle verifizieren (`Auth::id()`, `Session::get('masquerade')`). | Nur Masquerade |
| R11 | **`createRequest()` Factory** | Alle Tests nutzen `self::createRequest()` aus TestCase. Kein manueller ServerRequest-Bau. | — |

### B.3 Sonderfall: SelectLanguageTest

`SelectLanguageTest` verwendet **keine** Mocks/Stubs:

- `testSelectLanguageForGuest()`: `new GuestUser()` (konkretes Value Object), `new SelectLanguage()` (keine Konstruktor-Deps).
- `testSelectLanguageForUser()`: Realer `UserService`, realer User in DB.

**Begründung — kein Pattern-Bruch:**

1. `SelectLanguage` hat **keine Konstruktor-Abhängigkeiten** — es gibt nichts zu injizieren.
2. `GuestUser` ist ein konkretes Value Object (speichert Preferences in einem lokalen Array), kein DB-Entity.
3. Für den registrierten User-Test wird `UserService::create()` benötigt, weil `User::setPreference()` in die DB schreibt — mit `$uses_database = true` verfügbar.

**Kontrast:** `SelectThemeTest` mockt `GuestUser` und `User` mit `expects($this->once())->method('setPreference')` — das ist Verhaltensverifikation. `SelectLanguageTest` prüft stattdessen nur den Statuscode (Zustandsverifikation). Beide Ansätze sind valide.

### B.4 Sonderfall: UpgradeWizardStepTest

Der komplexeste Test (11 Methoden, 251 LoC). Mischt reale Services mit Mocks:

| Abhängigkeit | Real in N Tests | Mock/Stub in N Tests | Warum? |
|-------------|----------------|---------------------|--------|
| `GedcomExportService` | 11 (immer real) | 0 | Rein, keine DB im Konstruktor; `Psr17Factory` leichtgewichtig |
| `MaintenanceModeService` | 11 (immer real) | 0 | Dateisystembasiert (Lock-File), keine DB |
| `PendingChangesService` | 11 (immer real) | 0 | Liest pending Changes aus DB; meiste Tests haben keine |
| `TreeService` | 10 (real) | 1 (Stub in `testStepExport`) | Export-Step braucht kontrollierten Tree |
| `UpgradeService` | 4 (real, nie aufgerufen) | 6 (Stub/Mock) | Methoden machen HTTP-Requests/Zip-Zugriffe |

**Strategie:** Mock an der I/O-Grenze (Netzwerk, Dateisystem), real für zustandslose Logik.

**Sonderfall `testStepPendingExist`:** Einziger Test im gesamten RequestHandler-Corpus, der `Auth::login()` und DB-Zustandsänderungen nutzt. Ist de facto ein Mini-Integrationstest innerhalb der Unit-Test-Suite.

---

## C. Analyse der 23 neuen/geänderten Tests auf `5349_add_tests`

### C.1 Abgleich mit Maintainer-Feedback

#### Konstruktor-Abhängigkeiten der getesteten Klassen

| SUT | Konstruktor-Dependencies |
|-----|--------------------------|
| `AutoCompleteCitation` | `SearchService` (geerbt von `AbstractAutocompleteHandler`) |
| `AutoCompletePlace` | `ModuleService`, `SearchService` (geerbt) |
| `AutoCompleteSurname` | `SearchService` (geerbt) |
| `AncestorsChartModule` | `ChartService` |
| `CompactTreeChartModule` | `ChartService` |
| `DescendancyChartModule` | `ChartService` |
| `FanChartModule` | `ChartService` |
| `PedigreeChartModule` | `ChartService` |
| `MediaListModule` | `LinkedRecordService` |
| `FamilyListModule` | *keine* |
| `HourglassChartModule` | *keine* |
| `IndividualListModule` | *keine* |
| `NoteListModule` | *keine* |
| `RepositoryListModule` | *keine* |
| `SourceListModule` | *keine* |
| `SubmitterListModule` | *keine* |
| `GedcomExportService` | `ResponseFactoryInterface`, `StreamFactoryInterface` |
| `GedcomImportService` | *keine* |
| `GedcomService` | *keine* |
| `RelationshipService` | *keine* |
| `RomanNumeralsService` | *keine* |
| `SearchService` | `TreeService` |
| `TreeService` | `GedcomImportService` |

#### Bewertung pro Datei

**Legende:** SUT = SUT-Prinzip verletzt, RD = Reale Dependencies statt Doubles, DB = `$uses_database`, IT = `importTree`, AUTH = `Auth::login()`

##### 3 RequestHandler-Tests

| Datei | SUT | RD | DB | IT | AUTH | Details |
|-------|-----|----|----|----|----|---------|
| AutoCompleteCitationTest | Ja | `SearchService(TreeService(GedcomImportService))`, `UserService` | Ja | Ja | Ja | Test ist `markTestSkipped` wg. upstream Bug |
| AutoCompletePlaceTest | Ja | `SearchService(TreeService(GedcomImportService))`, `ModuleService` | Ja | Ja | Nein | 2 Tests: match + no-match |
| AutoCompleteSurnameTest | Ja | `SearchService(TreeService(GedcomImportService))` | Ja | Ja | Nein | 2 Tests: match + no-match |

##### 13 Module-Tests

| Datei | SUT | RD | DB | IT | AUTH | Details |
|-------|-----|----|----|----|----|---------|
| AncestorsChartModuleTest | Ja | `ChartService`, `UserService` | Ja | Ja | Ja | 7 Tests: 3 Styles × (Page + Ajax) + chartTitle |
| CompactTreeChartModuleTest | Ja | `ChartService`, `UserService` | Ja | Ja | Ja | 2 Tests: Page + Ajax |
| DescendancyChartModuleTest | Ja | `ChartService`, `UserService` | Ja | Ja | Ja | 3 Tests: 3 Styles via DataProvider |
| FamilyListModuleTest | Teilw. | `UserService` (kein Konstruktor-Dep) | Ja | Ja | Ja | 2 Tests: handle + listIsEmpty |
| FanChartModuleTest | Ja | `ChartService`, `UserService` | Ja | Ja | Ja | 1 Test: handle mit style/generations/width |
| HourglassChartModuleTest | Teilw. | `UserService` (kein Konstruktor-Dep) | Ja | Ja | Ja | 1 Test: handle |
| IndividualListModuleTest | Teilw. | `UserService` (kein Konstruktor-Dep) | Ja | Ja | Ja | 3 Tests: handle, show_all, listIsEmpty |
| MediaListModuleTest | Ja | `LinkedRecordService`, `UserService` | Ja | Ja | Ja | 2 Tests: handle + listIsEmpty |
| NoteListModuleTest | Teilw. | `UserService` (kein Konstruktor-Dep) | Ja | Ja | Ja | 1 Test: handle |
| PedigreeChartModuleTest | Ja | `ChartService`, `UserService` | Ja | Ja | Ja | 4 Tests: 4 Styles via DataProvider |
| RepositoryListModuleTest | Teilw. | `UserService` (kein Konstruktor-Dep) | Ja | Ja | Ja | 2 Tests: handle + listIsEmpty |
| SourceListModuleTest | Teilw. | `UserService` (kein Konstruktor-Dep) | Ja | Ja | Ja | 2 Tests: handle + listIsEmpty |
| SubmitterListModuleTest | Teilw. | `UserService` (kein Konstruktor-Dep) | Ja | Ja | Ja | 1 Test: handle |

##### 7 Service-Tests

| Datei | SUT | RD | DB | IT | AUTH | Details |
|-------|-----|----|----|----|----|---------|
| GedcomExportServiceTest | Ja | `Registry::container()` für Factories, `UserService` | Ja | Ja | Ja | 11 Tests: HEAD/TRLR/INDI/FAM/Sort/Header/Wrap/Download/Privacy |
| GedcomImportServiceTest | Moderat | `TreeService(GedcomImportService)`, `UserService` — SUT selbst hat keine Deps | Ja | Ja | Ja | 15 Tests: Counts, Place, Encoding, XREF, Links, Single-Record, Date, Name, Notes, Media |
| GedcomServiceTest | **Nein** | *keine* | Nein | Nein | Nein | 12 Tests: canonicalTag, readLatitude, readLongitude — **vollständig konform** |
| RelationshipServiceTest | Ja | `Registry::individualFactory()`, `UserService` | Ja | Ja | Ja | 4 Tests: Spouse/ParentChild/SamePerson/ChildToParent |
| RomanNumeralsServiceTest | **Nein** | *keine* | Nein | Nein | Nein | 4 Tests (18 Datenpunkte via DataProvider) — **vollständig konform** |
| SearchServiceTest | Ja | `TreeService(GedcomImportService)`, `UserService` | Ja | Ja | Ja | 12 Tests: Individual/Family/Source/Repo/Media/Submitter/Place + MultiWord + NonMatch + Guest |
| TreeServiceTest | Moderat | `GedcomImportService` (Konstruktor-Dep), `UserService` | Ja | Ja (Delete-Test) | Ja | 6 Tests: create/delete/all/find/titles/uniqueName |

### C.2 Was ist wertvoll?

Trotz der SUT-Verletzungen enthalten die 23 Tests wertvolle **Testszenarien und Assertions**, die in Test-Double-basierte Fassungen übernommen werden sollten:

#### RequestHandler-Tests — portierbare Szenarien

| Test | Szenario | Portierung zu Test-Double |
|------|----------|--------------------------|
| AutoCompletePlace: `testHandleReturnsJsonWithResults` | Query "England" → JSON-Array nicht leer | Mock `SearchService::searchPlaces()` → `willReturn(new Collection(['England, UK']))` |
| AutoCompletePlace: `testHandleReturnsEmptyForNonMatch` | Query "xyznonexistent" → leeres JSON-Array | Mock `SearchService::searchPlaces()` → `willReturn(new Collection([]))` |
| AutoCompleteSurname: match + no-match | Gleiche Logik wie Place | Mock `SearchService::searchSurnames()` |
| AutoCompleteCitation: (skipped) | Gültiger Source-XREF → JSON 200 | Mock `SearchService::searchFamilies()` → canned Collection |

#### Module-Tests — portierbare Szenarien

| Modul-Typ | Szenarien | Portierung |
|-----------|-----------|-----------|
| Chart-Module (5×) | `handle()` mit verschiedenen Styles → HTTP 200 | Mock `ChartService`, Stub `Individual` + `Tree`. DataProvider für Styles beibehalten. |
| List-Module (7×) | `handle()` → HTTP 200, `listIsEmpty()` → false | Mock `Tree` mit Stub-Daten. `listIsEmpty()` intern DB-abhängig — ggf. Layer-3-Kandidat. |
| MediaListModule | Wie List, aber mit `LinkedRecordService` | Mock `LinkedRecordService` |

#### Service-Tests — Differenzierung

| Kategorie | Services | Portierungs-Empfehlung |
|-----------|----------|----------------------|
| **Vollständig konform (ship as-is)** | GedcomServiceTest, RomanNumeralsServiceTest | Keine Änderung nötig. Pure Unit-Tests ohne DB. |
| **Konstruktor-Deps mockbar, aber DB-inhärent** | SearchServiceTest, TreeServiceTest, GedcomExportServiceTest | Konstruktor-Deps mocken (TreeService, GedcomImportService, Factories). Methoden bauen intern DB-Queries → Tests bleiben teilweise auf `$uses_database` angewiesen. Alternative: Nur die nicht-DB-abhängigen Methoden als Unit-Test (z.B. `wrapLongLines`, `createHeader`), Rest in Layer 3. |
| **Keine Konstruktor-Deps, aber DB-inhärent** | GedcomImportServiceTest, RelationshipServiceTest | SUT selbst hat keine Dependencies zum Mocken. `importRecord()` schreibt direkt in DB-Tabellen. Diese Tests sind inhärent Integrationstests → **Layer-3-Kandidaten**, nicht Layer-2. |

### C.3 Die fundamentale Spannung bei Service-Tests

Der Maintainer-Grundsatz "Dependencies should be Test Doubles" ist klar für **Konstruktor-injizierte** Abhängigkeiten. Aber viele webtrees-Services haben eine zusätzliche **implizite Abhängigkeit**: die Datenbank (via `DB::table()` innerhalb ihrer Methoden).

| Ansatz | Beschreibung | Bewertung |
|--------|-------------|-----------|
| Konstruktor-Deps mocken, DB akzeptieren | Mock `TreeService` in `SearchServiceTest`, aber `$uses_database = true` beibehalten | Halbmaßnahme — entspricht dem UpgradeWizardStepTest-Pattern |
| DB-Facade mocken | `DB::shouldReceive('table')...` | Extrem brüchig, nicht Standard-PHPUnit |
| Als Integrationstests akzeptieren | In Layer 3 belassen, nicht nach Layer 2 portieren | Sauberste Trennung |
| Repository-Pattern einführen | DB-Queries in mockbare Repository-Interfaces extrahieren | Major Refactor des webtrees-Core — außerhalb unseres Scope |

**Empfehlung:** Services, deren Kernlogik in `DB::table()`-Queries besteht (`SearchService`, `TreeService`, `GedcomImportService`), gehören primär in **Layer 3**. Aus ihren Tests können nur die nicht-DB-abhängigen Methoden als Layer-2-Tests portiert werden.

---

## D. Portierungsstrategie (analytisch)

### D.1 Priorisierungskriterien

| Priorität | Kriterium | Begründung |
|-----------|----------|-----------|
| **1 (höchste)** | Sicherheitsrelevante Handler | SEC-BOT01, Auth-Middleware, RESN-Handler — Fehler haben externe Auswirkungen |
| **2** | Handler mit hoher Codepfad-Komplexität | Viele Branches = viele Edge Cases, die mit Test Doubles trivial testbar sind |
| **3** | Handler mit Konstruktor-Dependencies | Test-Double-Pattern direkt anwendbar; maximaler Isolation-Gewinn |
| **4** | Handler mit hohem CRAP-Score | Kombination aus Komplexität und fehlender Coverage |
| **5** | Handler ohne Dependencies | Einfachstes Pattern, schneller Durchsatz für Coverage-Gewinn |

### D.2 Template-Ableitung

Basierend auf den Patterns der 41 bestehenden Tests ergeben sich vier Templates:

#### Template 1: Handler mit Service-Dependencies (Redirect-Pattern)

Passt auf: Handler, deren Konstruktor einen oder mehrere Services erwartet (z.B. `TreeService`, `ModuleService`, `SearchService`).

```php
#[CoversClass(SomeHandler::class)]
class SomeHandlerTest extends TestCase
{
    protected static bool $uses_database = true;  // nur wenn TestCase-Bootstrap es erfordert

    public function testHandleSuccess(): void
    {
        $some_service = $this->createMock(SomeService::class);
        $some_service->expects($this->once())
            ->method('doSomething')
            ->willReturn($expected_result);

        $handler = new SomeHandler($some_service);
        $request = self::createRequest()->withAttribute('key', 'value');
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function testHandleNotFound(): void
    {
        $some_service = $this->createMock(SomeService::class);
        $some_service->expects($this->once())
            ->method('doSomething')
            ->willReturn(null);

        $this->expectException(HttpNotFoundException::class);

        $handler = new SomeHandler($some_service);
        $handler->handle(self::createRequest());
    }
}
```

**Zuordnung:** ~150 Stubs mit Service-Dependencies.

#### Template 2: Handler ohne Dependencies (Einfaches Pattern)

Passt auf: Handler ohne Konstruktor-Parameter oder mit nur leichtgewichtigen Value Objects.

```php
#[CoversClass(SimpleHandler::class)]
class SimpleHandlerTest extends TestCase
{
    public function testHandleReturnsExpectedStatus(): void
    {
        $handler = new SimpleHandler();
        $request = self::createRequest()->withAttribute('param', 'value');
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
```

**Zuordnung:** ~40 Stubs ohne Konstruktor-Dependencies (z.B. SelectLanguage-artige Handler).

#### Template 3: Handler mit Registry-Dependencies (Registry-Mock-Pattern)

Passt auf: Handler, die `Registry::individualFactory()`, `Registry::familyFactory()` etc. nutzen.

```php
#[CoversClass(RecordHandler::class)]
class RecordHandlerTest extends TestCase
{
    protected static bool $uses_database = true;

    public function testHandleWithValidRecord(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('tree1');

        $record = self::createStub(Individual::class);
        $record->method('url')->willReturn('https://example.com/indi/X123');

        $individual_factory = $this->createMock(IndividualFactory::class);
        $individual_factory->expects($this->once())
            ->method('make')->with('X123', $tree)->willReturn($record);
        Registry::individualFactory($individual_factory);

        $handler = new RecordHandler($some_service);
        // ...
    }
}
```

**Zuordnung:** ~60 Stubs, die Record-Typen verarbeiten (Individual/Family/Source/Note/etc.).

#### Template 4: Module mit `handle()` (Module-Handle-Pattern)

Passt auf: Module, die `RequestHandlerInterface` implementieren und `handle()` bereitstellen.

```php
#[CoversClass(SomeChartModule::class)]
class SomeChartModuleTest extends TestCase
{
    protected static bool $uses_database = true;

    #[DataProvider('chartStyles')]
    public function testHandleReturnsPage(string $style): void
    {
        $chart_service = $this->createMock(ChartService::class);
        // Stub return values for chart rendering

        $tree = self::createStub(Tree::class);
        $individual = self::createStub(Individual::class);
        $individual->method('canShow')->willReturn(true);

        $module = new SomeChartModule($chart_service);
        $request = self::createRequest()
            ->withAttribute('tree', $tree)
            ->withAttribute('xref', 'X123')
            ->withAttribute('style', $style);
        $response = $module->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public static function chartStyles(): array
    {
        return [['tree'], ['individuals'], ['families']];
    }
}
```

**Zuordnung:** ~40 Stubs unter `tests/app/Module/`.

### D.3 Bestandsverbesserung der 41 substanziellen Tests

| Verbesserungspotenzial | Betroffene Tests | Maßnahme |
|----------------------|-----------------|----------|
| **Fehlende Negativ-Tests** | `LoginPageTest`, `BroadcastPageTest` (je nur 2 Tests) | Weitere Codepfade mit Mock-Variationen testen (ungültige Inputs, Exception-Pfade) |
| **UpgradeWizardStepTest:: testStepPendingExist** | 1 Test | Ist de facto ein Integrationstest (Auth::login, DB-State) — ggf. nach Layer 3 verschieben oder als bewusste Ausnahme dokumentieren |
| **SelectLanguageTest** | 1 Test | Korrekt ohne Mocks, aber könnte für den User-Pfad auch `setPreference`-Interaktion verifizieren (wie SelectThemeTest) |
| **$uses_database bei Nicht-DB-Tests** | PingTest, ModuleActionTest, SelectThemeTest haben es nicht; einige andere könnten theoretisch auch ohne auskommen | Niedrige Priorität — TestCase-Bootstrap erfordert es oft implizit |
| **Pattern-Inkonsistenz** | LoginPageTest nutzt reale `TreeService` statt Mock | Refactoring auf Mocks wäre konsistenter |

### D.4 Abgrenzung zu Layer 3

**Kriterium:** Ein Test gehört in Layer 3 (Komponentenintegrationstest), wenn er **nur mit realer Datenbank und realen Testdaten sinnvoll ist** — d.h. wenn die SUT-Kernlogik `DB::table()`-Queries baut und das Testergebnis von importierten GEDCOM-Daten abhängt.

| Definitiv Layer 3 | Begründung |
|-------------------|-----------|
| Privacy-Logik (P01–P23) | `isDead()`, RESN, Relationship-Privacy traversieren Individual/Family-Graphen aus der DB |
| SearchService-Tests mit demo.ged | `searchIndividuals()` baut SQL-Queries; Ergebnis hängt von importierten Daten ab |
| TreeService CRUD-Tests | `create()`, `delete()`, `all()`, `find()` sind direkte DB-Operationen |
| GedcomImportService-Tests | `importRecord()` schreibt in 15+ DB-Tabellen |
| GedcomExportService-Tests mit Tree-Daten | `export()` liest Records aus DB; reine Hilfsmethoden (`wrapLongLines`, `createHeader`) sind Layer-2-fähig |
| Module-`listIsEmpty()`-Tests | Methode fragt DB ab; kein sinnvoller Mock möglich |
| RelationshipService mit Individual-Traversierung | `getCloseRelationshipName()` traversiert FAMS/FAMC-Links in der DB |

| Kandidat für Layer 2 | Begründung |
|---------------------|-----------|
| Module-`handle()`-Tests mit gemocktem Chart-/List-Output | Wenn die interne Rendering-Logik gemockt wird, prüft der Test nur die HTTP-Dispatch-Logik |
| AutoComplete-Handler mit gemocktem SearchService | Handler delegiert an SearchService; Test prüft JSON-Serialisierung + HTTP-Status |
| GedcomExportService: `wrapLongLines()`, `createHeader()` | Pure Funktionen ohne DB-Zugriff |
| GedcomService: alle Methoden | Zustandslose Tag-/Koordinaten-Logik |
| RomanNumeralsService: alle Methoden | Zustandslose Konvertierung |

---

## E. Testabdeckungsmatrix Layer 2

### GEDCOM Import/Export (G01–G30)

| # | Feature | Upstream Unit-Test (IST) | Substanziell? | Portierungs-Kandidat (SOLL) | Bemerkung |
|---|---------|--------------------------|---------------|----------------------------|-----------|
| G01 | Record-Import (INDI) | `GedcomImportServiceTest` | Nein (Stub) | `5349`: `testImportIndiRecords` / L3: `GedcomImportTest` | Inhärent DB-abhängig → Layer 3 |
| G02 | Record-Import (FAM) | `GedcomImportServiceTest` | Nein (Stub) | `5349`: `testImportFamRecords` / L3: `GedcomImportTest` | Inhärent DB-abhängig → Layer 3 |
| G03 | Record-Import (Nebenrecords) | `GedcomImportServiceTest` | Nein (Stub) | `5349`: `testImportSecondaryRecords` / L3: `GedcomImportTest` | Inhärent DB-abhängig → Layer 3 |
| G04 | Place-Hierarchie | `GedcomImportServiceTest` | Nein (Stub) | `5349`: `testImportPreservesPlaceHierarchy` / L3: `GedcomImportTest` | DB-Tabelle placelinks → Layer 3 |
| G05 | Date-Parsing | `GedcomImportServiceTest` | Nein (Stub) | `5349`: `testImportParsesDateFields`, `testImportComputesJulianDays` | DB-dates-Tabelle → Layer 3; Date-Klassen separat L2-testbar |
| G06 | Name-Extraktion + Soundex | `GedcomImportServiceTest` | Nein (Stub) | `5349`: `testImportExtractsNameComponents` | DB-name-Tabelle → Layer 3 |
| G07 | Encoding (UTF-8) | `UTF8Test` (5 Methoden) | Ja | — | Encoding-Konvertierung auf L2 abgedeckt |
| G08 | Encoding (ANSEL, CP1252) | `AnselTest` + `Windows1252Test` | Ja | L3: `GedcomImportTest` (4 Tests) | Encoding-Klassen L2-substanziell; Import-Pfad → L3 |
| G09 | Inline-Media | `GedcomImportServiceTest` | Nein (Stub) | `5349`: `testImportMediaObjects` / L3: `GedcomImportTest` | DB-abhängig → Layer 3 |
| G10 | Legacy-Formate | — | — | L3: `GedcomImportTest` (4 Tests) | Kein L2-Kandidat (braucht Import-Pipeline) |
| G11 | Custom-Tags | `CustomTags/*Test` (20 Klassen) | Ja | L3: `GedcomImportTest` (3 Tests) | Tag-Registrierung L2-substanziell |
| G12 | XREF-Eindeutigkeit | `GedcomImportServiceTest` | Nein (Stub) | `5349`: `testImportedXrefsAreUnique` | DB-abhängig → Layer 3 |
| G13 | Export GEDCOM | `GedcomExportServiceTest` | Nein (Stub) | `5349`: 5 Export-Tests / L3: `TreeOperationsTest` | `wrapLongLines`, `createHeader` → L2; `export()` mit Tree → L3 |
| G14 | Export ZIP | `GedcomExportServiceTest` | Nein (Stub) | L3: `TreeOperationsTest` (3 Tests) | ZIP-Erzeugung braucht Tree → Layer 3 |
| G15 | Export ZIP+Media | `GedcomExportServiceTest` | Nein (Stub) | L3: `TreeOperationsTest` (2 Tests) | Mediendateisystem nötig → Layer 3 |
| G16 | Export Privacy | `GedcomExportServiceTest` | Nein (Stub) | `5349`: 2 Privacy-Export-Tests / L3: `TreeOperationsTest` | DB+Tree → Layer 3 |
| G17 | Export Encoding | `GedcomExportServiceTest` | Nein (Stub) | L3: `TreeOperationsTest` (3 Tests) | Encoding-Konvertierung → Layer 3 |
| G18 | Export CONC/CONT | `GedcomExportServiceTest` | Nein (Stub) | `5349`: `testWrapLongLines` | **Pure Funktion → L2-Kandidat** (Template 1, kein Mock nötig) |
| G19 | Export Header | `GedcomExportServiceTest` | Nein (Stub) | `5349`: `testCreateHeaderProducesValidHeader` | **Teilweise L2-Kandidat** (Header-Erzeugung ohne Tree) |
| G20 | Import→Export Roundtrip | — | — | `5349`: implizit | Nur mit DB sinnvoll → Layer 3 |
| G21 | Upload-Validierung | `UploadMediaActionTest` | Nein (Stub) | L4: `upload-validation.spec.ts` | Handler-Stub → **Template 1** |
| G22 | Element-Validierung | 212 Element-Tests (7 Methoden je Klasse) | Ja | — | XSS, canonical, pattern — substanziell |
| G23 | GEDCOM 5.5.1 Compliance | — | — | L3: `GedcomImportTest` | Braucht Import-Pipeline → Layer 3 |
| G24 | Referenzintegrität | `CheckTreeTest` | Nein (Stub) | L3: `CheckTreeIntegrationTest` | Handler-Stub → **Template 1** |
| G25 | GedcomLoad CLI | `GedcomLoadTest` | Nein (Stub) | L3: `GedcomLoadIntegrationTest` (8 Tests) | CLI+DB → Layer 3 |
| G26 | GEDCOM-Export via CLI | — | — | L3: `TreeExportCommandIntegrationTest` (13 Tests) | CLI+DB → Layer 3 |
| G27 | Mediendatei-Upload URL | `MediaFileServiceTest` | Nein (Stub) | L3: `MediaFileServiceUploadIntegrationTest` | Service-Stub → **Template 1** |
| G28 | OBJE-Metadaten | `EditMediaFileActionTest` + `EditMediaFileModalTest` | Nein (Stubs) | L3: `EditMediaFileIntegrationTest` | Handler-Stubs → **Template 1** |
| G29 | GEDCOM-Bearbeitungsservice | `GedcomEditServiceTest` (3 Methoden) | Ja | L3: `GedcomEditServiceIntegrationTest` (9 Tests) | L2-substanziell; L3 deckt mehr Pfade |
| G30 | Mediendatei-Upload (HTTP) | `UploadMediaActionTest` | Nein (Stub) | L3: `UploadMediaActionIntegrationTest` (3 Tests) | Handler-Stub → **Template 1** |

### Suche und Navigation (S01–S53)

| # | Feature | Upstream Unit-Test (IST) | Substanziell? | Portierungs-Kandidat (SOLL) | Bemerkung |
|---|---------|--------------------------|---------------|----------------------------|-----------|
| S01 | Allg. Suche (Personen) | `SearchServiceTest` (8 Assertions) | Ja | `5349`: `testSearchIndividuals...` (12 Tests) | IST: 1 Methode, 8 Inline-Assertions; DB-abhängig |
| S02 | Allg. Suche (Familien) | `SearchServiceTest` | Ja | `5349`: `testSearchFamilies...` | Wie S01 |
| S03 | Allg. Suche (SOUR, NOTE, REPO) | `SearchServiceTest` | Ja | `5349`: 3 weitere Such-Tests | Wie S01 |
| S04 | Query-Parsing | `SearchServiceTest` | Ja | `5349`: MultiWord + NonMatch | Wie S01 |
| S05 | Erweiterte Suche (Felder) | `SearchAdvancedActionTest` | Nein (Stub) | L3: `SearchIntegrationTest` (5 Tests) | Handler → **Template 1** (Mock SearchService) |
| S06 | Erweiterte Suche (Datum) | `SearchAdvancedActionTest` | Nein (Stub) | L3: `SearchIntegrationTest` (3 Tests) | Handler → **Template 1** |
| S07 | Phonetische Suche (Russell) | `SoundexTest` | Nein (Stub) | L3: `SearchIntegrationTest` | Soundex-Klasse → **L2-Kandidat** (pure Logik) |
| S08 | Phonetische Suche (DM) | `SoundexTest` | Nein (Stub) | L3: `SearchIntegrationTest` | Wie S07 |
| S09 | Quick-Search (XREF) | `SearchQuickActionTest` | Nein (Stub) | L4: `navigation.spec.ts` | Handler → **Template 1** |
| S10 | Paginierung | `SearchServiceTest` | Ja | L3: `SearchIntegrationTest` (3 Tests) | IST: implizit via Place-Search |
| S11 | Cross-Tree-Suche | — | — | L3: `SearchIntegrationTest` (2 Tests) | Multi-Tree → Layer 3 |
| S12 | Zugriffskontrolle (Suche) | `SearchServiceTest` | Ja | `5349`: `testSearchAsGuest...` | IST: Guest-vs-Admin implizit |
| S13 | Search-and-Replace | `SearchReplaceActionTest` | Nein (Stub) | L4: `search-replace.spec.ts` | Handler → **Template 1** |
| S14 | Chart: Pedigree | `PedigreeChartModuleTest` | Nein (Stub) | `5349`: 4 Styles via DataProvider | Module → **Template 4** (Mock ChartService) |
| S15 | Chart: Nachkommen | `DescendancyChartModuleTest` | Nein (Stub) | `5349`: 3 Styles via DataProvider | Module → **Template 4** |
| S16 | Chart: Beziehungsfinder | `RelationshipServiceTest` | Nein (Stub) | `5349`: 4 Tests / L3: `RelationshipServiceIntegrationTest` | Individual-Graph → Layer 3 |
| S17 | Chart: Fächerchart | `FanChartModuleTest` | Nein (Stub) | `5349`: handle-Test | Module → **Template 4** |
| S18 | Chart: alle 13 (Smoke) | 6 Chart-Stubs + `StatisticsChartModuleTest` (3 Methoden) | Teilweise | `5349`: 6 Module + L3: `ChartModuleIntegrationTest` | Statistics substanziell; Rest → **Template 4** |
| S19 | Liste: Personen | `IndividualListModuleTest` | Nein (Stub) | `5349`: handle + show_all + listIsEmpty | `handle()` → **Template 4**; `listIsEmpty()` → Layer 3 |
| S20 | Liste: alle 10 (Smoke) | 7 List-Stubs | Nein (7 Stubs) | `5349`: 7 Module-Tests + L3: `ListModuleIntegrationTest` | Module → **Template 4** |
| S21 | AutoComplete (Personen) | `AutoCompleteSurnameTest` | Nein (Stub) | `5349`: 2 Tests (match + no-match) | Handler → **Template 1** (Mock SearchService) |
| S22 | AutoComplete (Orte) | `AutoCompletePlaceTest` | Nein (Stub) | `5349`: 2 Tests (match + no-match) | Handler → **Template 1** (Mock SearchService + ModuleService) |
| S23–S30 | Navigation-Seiten | 8 Handler-Stubs | Nein (8 Stubs) | L4: Playwright-Tests | Handler → **Template 3** (Registry-Mock) |
| S31 | Kalenderansicht | 3 Handler-Stubs | Nein (3 Stubs) | L4: `calendar.spec.ts` | Handler → **Template 1** |
| S32 | Anmeldeseite | `LoginPageTest` (2 Methoden) | Ja | L4: `login.spec.ts` | IST substanziell |
| S33 | Registrierung | 2 Handler-Stubs | Nein (2 Stubs) | L4: `auth.spec.ts` | Handler → **Template 1** |
| S34 | Passwort-Reset | `PasswordRequestPageTest` (2 Methoden) | Ja | L4: `auth.spec.ts` | IST substanziell |
| S35–S40 | Weitere Seiten | 6+ Handler-Stubs | Nein (Stubs) | L4: diverse Specs | Handler → **Templates 1–3** |
| S41 | Statistikdaten | `StatisticsTest` | Nein (Stub) | L3: `StatisticsDataIntegrationTest` (13 Tests) | DB+Tree → Layer 3 |
| S42 | Such-HTTP-Handler | 2 Handler-Stubs | Nein (2 Stubs) | L3: `SearchRequestHandlerIntegrationTest` (6 Tests) | Handler → **Template 1** |
| S43 | Report-Generierung | 2 Handler-Stubs | Nein (2 Stubs) | L3: `ReportIntegrationTest` (8 Tests) | Report-Engine komplex → Teilw. Layer 3 |
| S44 | Report-Parser | 3 Stubs | Nein (3 Stubs) | L3: `ReportParserGenerateExtendedIntegrationTest` | Parser+DB → Layer 3 |
| S45 | Report-Primitive | 18 Report-Stubs | Nein (18 Stubs) | L3: Report*ObjectsIntegrationTest (23 Tests) | Primitive → **L2-Kandidat** (pure Rendering-Logik) |
| S46 | Homepage-Block-Module | Block-Module-Stubs | Nein (Stubs) | L3: `BlockModuleIntegrationTest` (14 Tests) | Module → **Template 4** |
| S47 | Interaktiver Stammbaum | 2 Stubs | Nein (2 Stubs) | L3: `InteractiveTreeIntegrationTest` | TreeView+DB → Layer 3 |
| S48 | Standortdaten-Import | 2 Handler-Stubs | Nein (2 Stubs) | L3: `MapDataImportIntegrationTest` (4 Tests) | Handler → **Template 1** |
| S49 | Medienverwaltungsliste | `ManageMediaDataTest` (3 Methoden) | Ja | L3: `ManageMediaDataIntegrationTest` | IST substanziell mit DB+JSON |
| S50 | Hilfetexte | `HelpTextTest` | Nein (Stub) | L3: `HelpTextIntegrationTest` (13 Tests) | Handler → **Template 1** (Mock HelpService/statischer Content) |
| S52 | Standortdaten CRUD | 4 Handler-Stubs | Nein (4 Stubs) | L3: `MapDataCrudIntegrationTest` (5 Tests) | Handler → **Template 1** |
| S53 | Legacy-URL-Redirects | 29 Redirect-Tests (91 Methoden) | Ja | — | **Bereits substanziell** — Mustervorlage für weitere Tests |

### Datenschutz & Zugriffskontrolle (P01–P41)

| # | Feature | Upstream Unit-Test (IST) | Substanziell? | Portierungs-Kandidat (SOLL) | Bemerkung |
|---|---------|--------------------------|---------------|----------------------------|-----------|
| P01–P07 | Stammbaum-Sichtbarkeit / Altersregeln | — | — | L3: `PrivacyVisibilityTest` | Privacy-Logik → Layer 3 (DB+Individual-Graph) |
| P08–P13 | isDead()-Inferenzen | `IndividualTest` | Nein (Stub) | L3: `IsDeadTest` | Individual-Traversierung → Layer 3 |
| P14–P15 | Vertrauliche Namen/Beziehungen | — | — | L3: `PrivacyVisibilityTest` | Privacy → Layer 3 |
| P16–P21 | RESN-Regeln | — | — | L3: `ResnPrivacyTest` + L4 | RESN+DB → Layer 3 |
| P22–P23 | Relationship Privacy | — | — | L3: `RelationshipPrivacyTest` | Graph-Traversierung → Layer 3 |
| P24 | Privacy in Suche | `SearchServiceTest` | Ja | L3: `PrivacySearchTest` | IST: Guest-vs-Admin implizit |
| P25–P26 | Vertraulich-Platzhalter / Charts | Handler-Stubs | Nein (Stubs) | L4: Playwright-Tests | L4 vorrangig; Handler → Template 3 |
| P27 | Bearbeiter-Zugriff | `AuthEditorTest` (3 Methoden) | Ja | L3: `AccessControlTest` + L4 | **IST substanziell** — Auth-Middleware |
| P28 | Moderator-Zugriff | `AuthModeratorTest` (3 Methoden) | Ja | L3 + L4 | **IST substanziell** |
| P29 | RESN locked / Admin-Zugriff | `AuthAdministratorTest` + `AuthManagerTest` | Ja | L3 + L4 | **IST substanziell** |
| P30–P34 | Merge/Edit/Privacy-Settings/Reorder | 10+ Handler-Stubs | Nein (Stubs) | L3: diverse Tests | Handler → **Template 1** |
| P35–P36 | CLI User/Settings | — | — | L3: diverse Tests | CLI+DB → Layer 3 |
| P37 | HTTP User-Edit | `DeleteUserTest` (3 Methoden) + Stubs | Ja (Delete) | L3: `UserEditActionIntegrationTest` | DeleteUser IST substanziell |
| P38 | Account-Selbstverwaltung | 3 Handler-Stubs | Nein (3 Stubs) | L3: `AccountSelfManagementIntegrationTest` | Handler → **Template 1** |
| P39 | Auth-Aktionen | `LoginActionTest` | Nein (Stub) | L3: `LoginActionIntegrationTest` | Handler → **Template 1** |
| P40 | Änderungsverwaltung | 4 Handler-Stubs | Nein (4 Stubs) | L3: `PendingChangesIntegrationTest` | Handler → **Template 1** |
| P41 | Zusammenführung | 4 Handler-Stubs | Nein (4 Stubs) | L3: `MergeRecordsIntegrationTest` | Handler → **Template 1** |

### Sicherheit (SEC-H01–SEC-UTL01)

| # | Feature | Upstream Unit-Test (IST) | Substanziell? | Portierungs-Kandidat (SOLL) | Bemerkung |
|---|---------|--------------------------|---------------|----------------------------|-----------|
| SEC-H01–H06 | .htaccess / HTTP-Zugriff | — | — | L3/L4 Shell/Playwright | Deployment-Level, nicht unit-testbar |
| SEC-D01–D02 | data/index.php | — | — | L3 Shell | Filesystem-Level |
| SEC-C01–C03 | Config Guards | — | — | L3 Shell | Filesystem-Level |
| SEC-M01–M03 | Media-Zugriff | 2 Handler-Stubs | Nein | L4: `media-access.spec.ts` | Handler → **Template 3** |
| SEC-PUB01–04 | public/ Zugriff | `PublicFilesTest` | Nein (Stub) | L4: `public-access.spec.ts` | Middleware-Stub |
| SEC-W01 | Wizard-Sperre | `SetupWizardTest` | Nein (Stub) | L4: `setup-lock.spec.ts` | Handler-Stub |
| SEC-WZ01–04 | Wizard-Setup | `SetupWizardTest` | Nein (Stub) | L4: `wizard-setup.spec.ts` | Wizard → Teilw. L2 (Mock DB-Check) |
| SEC-HDR01–04 | Security Headers | `SecurityHeadersTest` | Nein (Stub) | L4: `security-headers.spec.ts` | Middleware → **L2-Kandidat** (Mock Request/Response) |
| SEC-BOT01 | Bot-Blockierung | `BadBotBlockerTest` | Nein (Stub) | L3: `BadBotBlockerIntegrationTest` (15 Tests) | Middleware → **L2-Kandidat** (Mock Request mit UA) |
| SEC-UTL01 | Utility-Endpoints | 8 Handler-Stubs | Nein (8 Stubs) | L3: `UtilityEndpointsIntegrationTest` | Handler → **Template 2** (zustandslos) |

### Datenpflege / Erfassung (E01–E08)

| # | Feature | Upstream Unit-Test (IST) | Substanziell? | Portierungs-Kandidat (SOLL) | Bemerkung |
|---|---------|--------------------------|---------------|----------------------------|-----------|
| E01 | Person/Familie anlegen | 12 Handler-Stubs | Nein (12 Stubs) | L3: `AddRelationIntegrationTest` (6 Tests) | Handler → **Template 1** |
| E02 | Fakten bearbeiten | 4 Handler-Stubs | Nein (4 Stubs) | L3: `EditFactIntegrationTest` (3 Tests) | Handler → **Template 1** |
| E03 | Rohdaten-Edit | 4 Handler-Stubs | Nein (4 Stubs) | L3: `EditRawGedcomIntegrationTest` (3 Tests) | Handler → **Template 1** |
| E04 | Nebenrecords anlegen | 6 Handler-Stubs | Nein (6 Stubs) | L3: `CreateSubrecordIntegrationTest` (4 Tests) | Handler → **Template 1** |
| E05 | Medienobjekte | 4 Handler-Stubs | Nein (4 Stubs) | L3: `MediaObjectIntegrationTest` (3 Tests) | Handler → **Template 1** |
| E06 | Sortierung | 6 Handler-Stubs | Nein (6 Stubs) | L3: `ReorderIntegrationTest` (4 Tests) | Handler → **Template 1** |
| E07 | Download/Thumbnail | 4 Handler-Stubs | Nein (4 Stubs) | L3: `MediaFileDeliveryIntegrationTest` (3 Tests) | Handler → **Template 3** (Registry-Mock für MediaFactory) |
| E08 | TomSelect/AutoComplete | 15 Stubs | Nein (15 Stubs) | `5349`: 3 AutoComplete-Tests / L3: `TomSelectIntegrationTest` | Handler → **Template 1** (Mock SearchService) |

### Administration (A01–A11)

| # | Feature | Upstream Unit-Test (IST) | Substanziell? | Portierungs-Kandidat (SOLL) | Bemerkung |
|---|---------|--------------------------|---------------|----------------------------|-----------|
| A01 | Stammbaum-Management | 5 Stubs + `TreeServiceTest` (Stub) | Nein | `5349`: TreeServiceTest (6 Tests) / L3: `TreeManagementIntegrationTest` | TreeService → Layer 3; Handler → **Template 1** |
| A02 | Import (HTTP) | 2 Handler-Stubs | Nein | L3: `ImportGedcomActionIntegrationTest` | Handler → **Template 1** |
| A03 | Export (HTTP) | 3 Handler-Stubs | Nein | `5349`: GedcomExportServiceTest / L3: `ExportGedcomIntegrationTest` | Handler → **Template 1**; Service → Layer 3 |
| A04 | Stammbaum-Präferenzen | 2 Handler-Stubs | Nein | L3: `TreePreferencesIntegrationTest` | Handler → **Template 1** |
| A05 | Modul-Konfiguration | 30+ Stubs + `ModuleServiceTest` (5 Methoden) | Ja (Service) | L3: `ModuleConfigIntegrationTest` | ModuleServiceTest substanziell |
| A06 | Site-Präferenzen | 2 Handler-Stubs | Nein | L3: `SitePreferencesIntegrationTest` | Handler → **Template 1** |
| A07 | Benutzerverwaltung | 6 Stubs + `UserServiceTest` (13 Methoden) | Ja (Service) | L3: `UserAdminIntegrationTest` | UserServiceTest substanziell |
| A08 | Medienverwaltung | 2 Stubs + `ManageMediaDataTest` (3 Methoden) | Ja (Data) | — | **Nicht abgedeckt** in L3/L4 |
| A09 | Datenpflege-Werkzeuge | 8+ Handler-Stubs | Nein | L3: `DataMaintenanceIntegrationTest` | Handler → **Template 1** |
| A10 | Protokolle/Monitoring | 8 Stubs | Nein | L3: `LogsMonitoringIntegrationTest` | Handler → **Template 1** |
| A11 | System/Upgrade | `UpgradeWizardStepTest` (11), `BroadcastPageTest` (2), `MasqueradeTest` (3) + 5 Stubs | Ja (16 Tests) | L3: `SystemAdminIntegrationTest` | **Substanziellster Bereich auf L2** |

### Kommunikation (K01–K02)

| # | Feature | Upstream Unit-Test (IST) | Substanziell? | Portierungs-Kandidat (SOLL) | Bemerkung |
|---|---------|--------------------------|---------------|----------------------------|-----------|
| K01 | Kontaktformular | 2 Handler-Stubs | Nein | — | Handler → **Template 1** (Mock EmailService) |
| K02 | Benutzer-Nachrichten | 4 Stubs | Nein | — | Handler → **Template 1** (Mock MessageService) |

### Querschnitts-Utilities (U01–U02)

| # | Feature | Upstream Unit-Test (IST) | Substanziell? | Portierungs-Kandidat (SOLL) | Bemerkung |
|---|---------|--------------------------|---------------|----------------------------|-----------|
| U01 | Validator | `ValidatorTest` (24 Methoden, 391 LoC) | Ja | L3: `ValidatorIntegrationTest` | **Umfangreichster Unit-Test** — vollständig |
| U02 | CountryService | — | — | — | SKIP — deprecated |

### Zusammenfassung der Matrix

| Kategorie | Feature-IDs | Substanziell auf main | Stub-only | Kein L2-Test | Auf 5349_add_tests |
|-----------|------------|----------------------|-----------|-------------|-------------------|
| G (G01–G30) | 30 | 4 (G07, G08, G22, G29) | 23 | 3 (G10, G23, G26) | 17 Tests |
| S (S01–S53) | 51 | 10 (S01–S04, S10, S12, S32, S34, S49, S53) | 37 | 4 | 14 Tests |
| P (P01–P41) | 41 | 5 (P24, P27–P29, P37) | 20 | 16 | 0 |
| SEC | 26 | 0 | 14 | 12 | 0 |
| E (E01–E08) | 8 | 0 | 8 | 0 | 3 |
| A (A01–A11) | 11 | 5 (A05, A07, A08, A11) | 6 | 0 | 2 |
| K (K01–K02) | 2 | 0 | 2 | 0 | 0 |
| U (U01–U02) | 2 | 1 (U01) | 0 | 1 | 0 |
| **Gesamt** | **171** | **25** | **110** | **36** | **36 Szenarien** |

---

## F. Zusammenfassung

1. **Test Double ≠ Mock.** "Test Double" ist der Oberbegriff (Meszaros-Taxonomie) für Dummy, Stub, Mock, Spy, Fake. PHPUnit bildet Stub (`createStub()`) und Mock (`createMock()` + `expects()`) direkt ab. Der Maintainer wählt bewusst den Oberbegriff, weil verschiedene Untertypen situationsabhängig passend sind.

2. **Maintainer-Anforderung destilliert:** Nur die SUT instanziieren. Alle Konstruktor-Dependencies als Test Doubles injizieren. Verschiedene Return-Werte nutzen, um jeden Codepfad und Edge Case abzudecken. Keine reale Datenbank, kein `importTree()`.

3. **41 substanzielle Tests auf `main` sind Mustervorlagen.** Sie verwenden konsistent 6 Patterns (Constructor-Mock, Constructor-Stub, Registry-Injection, Fake, Request-Attribute, Stub+Mock-Kombination). Sie decken 25 von 171 Feature-IDs substanziell ab.

4. **292 Stubs sind der zentrale Hebel.** Jeder Stub prüft nur `class_exists()` — null Verhaltensabdeckung. Die Überführung in substanzielle Tests mit Test Doubles ist die größte Verbesserungsmöglichkeit.

5. **23 Tests auf `5349_add_tests` liefern wertvolle Szenarien, aber falsche Implementierung.** Alle (bis auf GedcomServiceTest und RomanNumeralsServiceTest) verwenden reale Services + DB + demo.ged — das entspricht Integrationstests, nicht Komponentests. Die Testszenarien (match/no-match, Style-DataProvider, Status-Assertions) sind portierbar.

6. **2 Tests auf `5349_add_tests` sind sofort upstream-fähig:** GedcomServiceTest (12 pure Tests) und RomanNumeralsServiceTest (4 Tests, 18 Datenpunkte) — keine DB, keine Mocks nötig, zustandslose Services.

7. **Service-Tests mit DB-Kernlogik gehören in Layer 3.** SearchService, TreeService, GedcomImportService bauen intern `DB::table()`-Queries. Ihre Tests können Konstruktor-Dependencies mocken, bleiben aber DB-abhängig. Die saubere Trennung: Handler-Dispatch-Logik → Layer 2, Service-DB-Logik → Layer 3.

8. **Privacy-Features (P01–P23) haben keine L2-Abdeckung.** isDead(), RESN, Relationship-Privacy traversieren Individual/Family-Graphen — das ist architekturbedingt Layer-3-Territorium. Auth-Middleware (P27–P29) ist die einzige Privacy-Kategorie mit substanziellen L2-Tests.

9. **4 Templates decken 95% der 292 Stubs ab:** (1) Handler+Service-Deps → Redirect-Pattern, (2) Handler ohne Deps → Einfach, (3) Handler+Registry → Registry-Mock, (4) Module+handle() → DataProvider-Pattern. Priorisierung nach Sicherheitsrelevanz → Codepfad-Komplexität → CRAP-Score.

10. **Die 41 bestehenden substanziellen Tests haben punktuelles Verbesserungspotenzial:** LoginPageTest nutzt reale TreeService statt Mock (Pattern-Inkonsistenz), UpgradeWizardStepTest::testStepPendingExist ist de facto ein Integrationstest, und einige Tests (BroadcastPage, LoginPage) haben nur 2 Methoden bei mehr als 2 Codepfaden.
