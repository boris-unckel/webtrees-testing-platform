<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Analyse: Layer-2-Komponentests — IST-Zustand, Maintainer-Feedback, Portierungsstrategie

## Kontext

Im webtrees-Fork (Pfad: /home/borisunckel/phpprojects/webtrees-upstream/webtrees)
existiert Branch `5349_add_tests` mit 23 geänderten Testdateien. Diese wurden aus
Komponentenintegrationstests (Layer 3, MySQL) nach Layer 2 (Komponentests, SQLite
in-memory) portiert. Der Upstream-Maintainer hat den PR mit folgendem Feedback abgelehnt:

> With unit tests, it is best practice for each unit test to test only one class.
> This is often refered to as the "SUT" - "System under test".
> So, when testing the request handlers, we only need to test the request handlers.
> Dependencies (such as SearchService) should be provided as "Test Doubles".
> In fact, the design of the code ("Services" as constructor dependencies to the
> request handlers) was designed to allow exactly this type of separation.
> So, could you perhaps ask your AI tool to rewrite the unit tests for the request
> handlers with this in mind.
> The "test double" approach makes it trivially easy to provide different values to
> the request handler (e.g. no data, etc.) to allow every code path and edge-case
> value to be covered by the test.

## Aufgaben

### A. Begriffliche Klärung: Test Doubles vs. Mocks

Erläutere die Taxonomie nach Gerard Meszaros ("xUnit Test Patterns") und ordne
die PHPUnit-Methoden zu:

- Dummy, Stub, Spy, Mock, Fake — Definition, Abgrenzung, wann welches verwenden
- PHPUnit-Mapping: `createStub()` vs. `createMock()` + `expects()` — was erzeugt was?
- Warum der Maintainer bewusst "Test Doubles" (Oberbegriff) und nicht "Mocks" sagt

### B. Analyse der bestehenden substanziellen Tests auf `main`

Auf `main` existieren 333 RequestHandler-Testdateien unter
`tests/app/Http/RequestHandlers/`. Davon:
- 292 Stub-Tests (nur `testClass()` → `class_exists()`)
- 41 mit echten Testmethoden

Die 41 substanziellen Tests sind:

| Datei | Test-Methoden | Mocks? | LoC |
|-------|---------------|--------|-----|
| UpgradeWizardStepTest.php | 11 | ja | 251 |
| RedirectModulePhpTest.php | 6 | ja | 282 |
| RedirectMediaViewerPhpTest.php | 5 | ja | 138 |
| ModuleActionTest.php | 4 | ja | 131 |
| RedirectIndividualPhpTest.php | 4 | ja | 125 |
| MasqueradeTest.php | 3 | ja | 96 |
| DeleteUserTest.php | 3 | ja | 83 |
| PingTest.php | 3 | ja | 71 |
| 26× Redirect*PhpTest.php | je 3 | ja | 96–139 |
| RedirectReportEnginePhpTest.php | 2 | ja | 87 |
| RedirectCalendarPhpTest.php | 2 | ja | 84 |
| PasswordResetPageTest.php | 2 | ja | 69 |
| BroadcastPageTest.php | 2 | ja | 62 |
| SelectThemeTest.php | 2 | ja | 60 |
| LoginPageTest.php | 2 | ja | 56 |
| PasswordRequestPageTest.php | 2 | ja | 50 |
| SelectLanguageTest.php | 2 | nein | 57 |

Analysiere für diese 41 Tests:

1. **Pattern-Katalog**: Welche Test-Double-Patterns werden konkret eingesetzt?
   Kategorisiere (mit Codebeispielen aus den Dateien):
   - Dependency Injection via Constructor + createMock/createStub
   - Registry-Injection (z.B. `Registry::individualFactory($mock)`)
   - Anonyme Klassen als Fakes (z.B. `fooModule()` in ModuleActionTest)
   - Request-Attribute als Ersatz für Middleware-Kontext
   - Kombination Stub + Mock im selben Test

2. **Anforderungsprofil**: Welche konkreten Anforderungen erfüllen die
   bestehenden Tests, die als Vorgabe für künftige Implementierungen dienen?
   Z.B.:
   - SUT-Isolation: nur die Handler-Klasse wird instanziiert
   - Alle Constructor-Dependencies sind Test Doubles
   - `expects($this->once())` verifiziert Interaktionen
   - Verschiedene Return-Werte testen verschiedene Codepfade
   - Exception-Pfade werden explizit getestet
   - Kein `$uses_database = true` nötig
   - Kein `importTree()` nötig

3. **SelectLanguageTest als Sonderfall**: Warum verwendet dieser Test keine
   Mocks? Liegt hier ein Pattern-Bruch vor oder ist die Klasse zustandslos
   genug?

### C. Analyse der 23 neuen/geänderten Tests auf `5349_add_tests`

Der Branch ändert 23 Testdateien (alle Modifikationen bestehender Stubs):

**3 RequestHandler-Tests:**
- AutoCompleteCitationTest.php (1 Test, markTestSkipped wg. upstream Bug)
- AutoCompletePlaceTest.php (2 Tests: match + no-match)
- AutoCompleteSurnameTest.php (2 Tests: match + no-match)

**13 Module-Tests (implementieren RequestHandlerInterface):**
- 5 Chart-Module: AncestorsChartModuleTest, CompactTreeChartModuleTest,
  DescendancyChartModuleTest, FanChartModuleTest, PedigreeChartModuleTest
  (je DataProvider für Chart-Styles, Konstruktor-Dependency: ChartService)
- 8 List-Module: FamilyListModuleTest, HourglassChartModuleTest,
  IndividualListModuleTest, MediaListModuleTest, NoteListModuleTest,
  RepositoryListModuleTest, SourceListModuleTest, SubmitterListModuleTest
  (teils ohne Konstruktor, teils mit LinkedRecordService)

**7 Service-Tests:**
- GedcomExportServiceTest (10+ Tests, Deps: ResponseFactory, StreamFactory)
- GedcomImportServiceTest (7+ Tests, keine Konstruktor-Deps)
- GedcomServiceTest (minimal)
- RelationshipServiceTest (minimal)
- RomanNumeralsServiceTest (mehrere Tests, zustandslos)
- SearchServiceTest (10+ Tests, Dep: TreeService)
- TreeServiceTest (5+ Tests, Dep: GedcomImportService)

Analysiere für alle 23 Dateien:

1. **Abgleich mit Maintainer-Feedback**: Für jede Datei konkret bewerten:
   - Wird das SUT-Prinzip verletzt? (mehrere reale Klassen im Spiel?)
   - Werden Abhängigkeiten als reale Instanzen statt Test Doubles injiziert?
   - Wird `$uses_database = true` verwendet? (= implizite Abhängigkeit)
   - Wird `importTree('demo.ged')` verwendet? (= Testdaten-Kopplung)
   - Wird `Auth::login()` / `loginAsAdmin()` verwendet? (= globaler Zustand)

2. **Was ist trotzdem wertvoll?** — Identifiziere, welche Testlogik, welche
   Testszenarien und welche Assertions aus den 23 Tests in eine Test-Double-
   basierte Fassung übernommen werden können/sollten. Die Portierung ist keine
   Neuentwicklung von Null, sondern soll die Testszenarien bewahren und die
   Implementierung an das Maintainer-Pattern anpassen.

3. **Service-Tests separat betrachten**: Die 7 Service-Tests testen Services,
   nicht Handler. Der Maintainer-Kommentar betrifft Handler-Tests explizit.
   Dennoch: Bewerte, ob die Service-Tests ebenfalls vom Test-Double-Ansatz
   profitieren würden, oder ob bei Services (die selbst die "tiefste" Schicht
   sind) reale Instanziierung vertretbar ist. Differenziere nach:
   - Services mit Konstruktor-Dependencies (SearchService, TreeService,
     GedcomExportService) → Test Doubles sinnvoll?
   - Services ohne Dependencies (GedcomService, RelationshipService,
     RomanNumeralsService) → Isolation ohnehin gegeben?

### D. Portierungsstrategie (analytisch, keine Implementierung)

Formuliere eine Strategie, wie die Gesamtmenge der 292 Stub-Tests in
substanzielle Komponentests überführt werden kann, mit Maximalverbesserung als
Ziel. Dabei:

1. **Priorisierung**: Nach welchen Kriterien sollte priorisiert werden?
   (Codepfad-Komplexität, Sicherheitsrelevanz, Dependency-Anzahl,
   CRAP-Score, etc.)

2. **Template-Ableitung**: Definiere ein oder mehrere Test-Templates basierend
   auf den Patterns der 41 bestehenden substanziellen Tests. Kategorisiere
   die 292 Stubs nach dem jeweils passenden Template:
   - Handler mit Service-Dependencies → Redirect-Pattern
   - Handler ohne Dependencies → Einfaches Pattern
   - Handler mit Registry-Dependencies → Registry-Mock-Pattern
   - Module mit `handle()` → Module-Handle-Pattern

3. **Bestandsverbesserung der 41**: Die 41 existierenden substanziellen Tests
   sind separat zu betrachten — gibt es Verbesserungspotenzial?
   (z.B. fehlende Negativ-Tests, unvollständige Pfadabdeckung,
   inkonsistente Patterns)

4. **Abgrenzung zu Layer 3**: Welche Testszenarien gehören definitiv NICHT in
   Layer 2 (Komponentest), sondern in Layer 3 (Komponentenintegrationstest)?
   Kriterium: Wenn der Test nur mit realer Datenbank und realen Testdaten
   sinnvoll ist, gehört er in Layer 3.

### E. Testabdeckungsmatrix Layer 2 (Format: docs/tds_coverage_ref.md)

Erstelle eine Abdeckungsmatrix im Format von `docs/tds_coverage_ref.md`, aber
für Layer-2-Komponentests. Verwende die Feature-Matrix-IDs aus der bestehenden
Referenz. Pro Feature-ID:

| # | Feature | Upstream Unit-Test (IST) | Substanziell? | Portierungs-Kandidat (SOLL) | Bemerkung |
|---|---------|--------------------------|---------------|----------------------------|-----------|

Spalten-Semantik:
- **Upstream Unit-Test (IST)**: Testklasse auf `main`, die das Feature abdeckt
  (oder "Stub" wenn nur testClass())
- **Substanziell?**: Ja/Nein — hat der Test echte Testlogik mit Assertions
  jenseits von class_exists()?
- **Portierungs-Kandidat (SOLL)**: Welcher Test aus Layer 3 oder aus
  `5349_add_tests` liefert Testszenarien, die in ein Test-Double-basiertes
  Layer-2-Format portiert werden können?
- **Bemerkung**: Test-Double-Pattern, Komplexitätshinweis, Blocker

Decke mindestens folgende Feature-Bereiche ab:
- GEDCOM Import/Export (G01–G30)
- Suche und Navigation (S01–S53)
- Datenschutz & Zugriffskontrolle (P01–P41)
- Sicherheit (SEC-*)
- Datenpflege (E01–E08)
- Administration (A01–A11)

### F. Zusammenfassung

Fasse die Kernerkenntnisse in maximal 10 Bullet Points zusammen:
- Taxonomie-Einordnung (Test Double / Mock / Stub)
- Maintainer-Anforderungen destilliert
- Stärken/Schwächen der 41 bestehenden Tests
- Stärken/Schwächen der 23 neuen Tests
- Strategische Empfehlung für die Portierung
