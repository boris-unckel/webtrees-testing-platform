<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt Template 3: Handler mit Registry-Dependencies

**Passt auf:** RequestHandler, die auf `Registry::individualFactory()`,
`Registry::familyFactory()`, `Registry::mediaFactory()` etc. zugreifen.
Oft in Kombination mit Service-Dependencies im Konstruktor.

**Muster-Vorlage:** `RedirectModulePhpTest.php` (6 Methoden), `RedirectIndividualPhpTest.php`

---

## Prompt

> **Ziel-Repo:** `webtrees-upstream/webtrees` (Fork) — **GPL-3.0-or-later**, **en_GB**
> Lizenz-Header: `// SPDX-License-Identifier: GPL-3.0-or-later` (nach `<?php`)
> Sprache: Alle Code-Kommentare, PHPDoc und BUG-CANDIDATE-Marker in Englisch (en_GB)

Du portierst den Stub-Test `{TEST_FILE}` in einen substanziellen Layer-2-Komponentest.
Der Handler greift neben Konstruktor-Dependencies auch auf das statische
`Registry`-Singleton für Record-Factories zu.

### Schritt 1: SUT-Klasse lesen

Lies die SUT-Klasse:
`app/Http/RequestHandlers/{SUT_CLASS}.php`

Identifiziere:
1. **Konstruktor-Parameter:** Welche Services?
2. **Registry-Zugriffe:** Welche `Registry::*Factory()`-Aufrufe gibt es?
   Vollständige Liste der Registry-Factories im Codebase:
   - `Registry::individualFactory()` → `IndividualFactory`
   - `Registry::familyFactory()` → `FamilyFactory`
   - `Registry::mediaFactory()` → `MediaFactory`
   - `Registry::sourceFactory()` → `SourceFactory`
   - `Registry::noteFactory()` → `NoteFactory`
   - `Registry::repositoryFactory()` → `RepositoryFactory`
   - `Registry::submitterFactory()` → `SubmitterFactory`
   - `Registry::headerFactory()` → `HeaderFactory`
   - `Registry::gedcomRecordFactory()` → `GedcomRecordFactory`
3. **Record-Typ:** Welcher Record-Typ wird via Factory erzeugt?
4. **Codepfade:** Record gefunden, Record nicht gefunden, Record ohne Berechtigung?
5. **Legacy-Parameter:** Liest der Handler `$request->getQueryParams()` für
   Legacy-URL-Params (`ged`, `pid`, `famid`, etc.)?

### Schritt 2: Bestehenden Test lesen

Lies `tests/app/Http/RequestHandlers/{TEST_FILE}`.
`testClass()` bleibt erhalten.

### Schritt 3: Test schreiben

```php
#[CoversClass({SUT_CLASS}::class)]
class {SUT_CLASS}Test extends TestCase
{
    protected static bool $uses_database = true;

    // bestehende testClass() bleibt!

    public function testHandleWithValidRecord(): void
    {
        // 1. Tree-Stub
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('tree1');

        // 2. TreeService-Mock (wenn Konstruktor-Dep)
        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects($this->once())
            ->method('all')
            ->willReturn(new Collection(['tree1' => $tree]));

        // 3. Record-Stub
        $individual = self::createStub(Individual::class);
        $individual->method('url')->willReturn('https://www.example.com/indi/X123');

        // 4. Registry-Factory-Mock
        $individual_factory = $this->createMock(IndividualFactory::class);
        $individual_factory->expects($this->once())
            ->method('make')
            ->with('X123', $tree)
            ->willReturn($individual);
        Registry::individualFactory($individual_factory);

        // 5. Request mit Legacy-Params
        $request = self::createRequest(RequestMethodInterface::METHOD_GET, [
            'ged' => 'tree1',
            'pid' => 'X123',
        ]);

        // 6. Handler instanziieren und aufrufen
        $handler = new {SUT_CLASS}($tree_service);  // oder weitere Service-Deps
        $response = $handler->handle($request);

        // 7. Redirect-Assertions
        self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
        self::assertSame('https://www.example.com/indi/X123', $response->getHeaderLine('Location'));
    }

    public function testHandleWithRecordNotFound(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('tree1');

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects($this->once())
            ->method('all')
            ->willReturn(new Collection(['tree1' => $tree]));

        $individual_factory = $this->createMock(IndividualFactory::class);
        $individual_factory->expects($this->once())
            ->method('make')
            ->with('X123', $tree)
            ->willReturn(null);  // Record nicht gefunden
        Registry::individualFactory($individual_factory);

        $this->expectException(HttpGoneException::class);

        $handler = new {SUT_CLASS}($tree_service);
        $handler->handle(self::createRequest(RequestMethodInterface::METHOD_GET, [
            'ged' => 'tree1',
            'pid' => 'X123',
        ]));
    }

    public function testHandleWithNoSuchTree(): void
    {
        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects($this->once())
            ->method('all')
            ->willReturn(new Collection([]));  // kein Tree

        $this->expectException(HttpGoneException::class);

        $handler = new {SUT_CLASS}($tree_service);
        $handler->handle(self::createRequest(RequestMethodInterface::METHOD_GET, [
            'ged' => 'nonexistent',
        ]));
    }
}
```

**Registry-Cleanup:** PHPUnit's `tearDown()` in `TestCase` resettet das Registry
automatisch. Kein manuelles Cleanup nötig.

**Konventionen (Pattern F aus Analyse):**
- `Tree`, `Individual`, `Family`, `Source`, `Note`, `Repository`, `Media`,
  `GedcomRecord` → **immer `self::createStub()`**
- `TreeService`, `ModuleService` → **`$this->createMock()` mit `expects()`**
- `IndividualFactory`, `FamilyFactory`, etc. → **`$this->createMock()` mit `expects()`**
  (über `Registry::*Factory()` gesetzt)

### Schritt 4: Bug-Erkennung

1. **Fehlender Tree-Lookup-Schutz:** Greift der Handler auf `$trees[$ged]` zu
   ohne vorher zu prüfen, ob `$ged` in der Collection existiert?
2. **Null-Record ohne Exception:** Gibt `Factory::make()` null zurück,
   aber der Handler prüft nicht darauf?
3. **Falscher Exception-Typ:** `HttpNotFoundException` statt `HttpGoneException`
   für Legacy-Redirects (301 → Gone statt Not Found)?
4. **Fehlende Parameter-Behandlung:** Was passiert, wenn ein Legacy-Parameter
   (`pid`, `famid`) im Request fehlt?
5. **Privacy-Bypass:** Prüft der Handler `$record->canShow()` bevor er
   die URL zurückgibt?

**Wenn ein Bug gefunden wird:** Test mit `// BUG-CANDIDATE:` markieren.

### Schritt 5: Validierung

```bash
cd /home/borisunckel/phpprojects/webtrees-testing-platform
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees \
  podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/var/www/html/phpunit.xml.dist \
    --filter='{SUT_CLASS}Test'
```

---

## Checkliste

- [ ] SUT-Klasse gelesen, alle Konstruktor-Dependencies identifiziert
- [ ] Registry-Factory-Zugriffe identifiziert (welche Factory, welche Methode)
- [ ] Alle Codepfade identifiziert (Record found, not found, Tree missing, etc.)
- [ ] `testClass()` beibehalten
- [ ] Tree als `createStub()`, Record als `createStub()`
- [ ] TreeService als `createMock()` mit `expects()`
- [ ] Factory als `createMock()` mit `expects()`, via `Registry::*Factory()` gesetzt
- [ ] Legacy-Query-Params via `self::createRequest(GET, ['ged' => ..., 'pid' => ...])`
- [ ] Redirect-Assertions: Statuscode 301 + Location-Header
- [ ] Exception-Tests für Not-Found/Gone-Pfade
- [ ] Bug-Erkennung durchgeführt
- [ ] Test grün validiert
