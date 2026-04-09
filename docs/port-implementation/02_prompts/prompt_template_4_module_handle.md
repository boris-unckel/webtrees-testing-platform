<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt Template 4: Module mit `handle()`

**Passt auf:** Module (unter `app/Module/`), die `RequestHandlerInterface`
implementieren und eine `handle()`-Methode bereitstellen. Typisch: Chart-Module,
List-Module, Block-Module.

**Muster-Vorlage:** `AncestorsChartModuleTest` (DataProvider für Styles),
`FamilyListModuleTest` (handle + listIsEmpty)

**Achtung:** Module-Tests liegen unter `tests/app/Module/`, nicht unter
`tests/app/Http/RequestHandlers/`.

---

## Prompt

> **Ziel-Repo:** `webtrees-upstream/webtrees` (Fork) — **GPL-3.0-or-later**, **en_GB**
> Lizenz-Header: `// SPDX-License-Identifier: GPL-3.0-or-later` (nach `<?php`)
> Sprache: Alle Code-Kommentare, PHPDoc und BUG-CANDIDATE-Marker in Englisch (en_GB)

Du portierst den Stub-Test `{TEST_FILE}` in einen substanziellen Layer-2-Komponentest.
Das Modul implementiert `RequestHandlerInterface` und hat eine `handle()`-Methode.

### Schritt 1: SUT-Klasse lesen

Lies die SUT-Klasse:
`app/Module/{SUT_CLASS}.php`

Identifiziere:
1. **Konstruktor-Parameter:** Hat das Modul Service-Dependencies?
   (z.B. `ChartService`, `LinkedRecordService`)
2. **`handle()`-Methode:** Welche Request-Attribute werden gelesen?
   (`tree`, `xref`, `style`, `generations`, etc.)
3. **Chart-Styles / DataProvider-Kandidaten:** Hat das Modul verschiedene
   Darstellungs-Styles? (z.B. `individuals`, `families`, `tree`)
4. **Template-Rendering:** Gibt `handle()` eine View-Response zurück?
5. **Methoden jenseits `handle()`:** Gibt es `title()`, `description()`,
   `chartUrl()`, `listIsEmpty()` etc.?

### Schritt 2: Bestehenden Test lesen

Lies `tests/app/Module/{TEST_FILE}`.
`testClass()` bleibt erhalten.

### Schritt 3: Test schreiben

```php
#[CoversClass({SUT_CLASS}::class)]
class {SUT_CLASS}Test extends TestCase
{
    protected static bool $uses_database = true;

    // bestehende testClass() bleibt!

    #[DataProvider('chartStyles')]
    public function testHandleReturnsPage(string $style): void
    {
        // 1. Service-Mock (wenn Konstruktor-Dep)
        $chart_service = $this->createMock(ChartService::class);
        // Stub relevante Methoden, wenn handle() sie aufruft

        // 2. Domain-Stubs
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('tree1');

        $individual = self::createStub(Individual::class);
        $individual->method('canShow')->willReturn(true);
        $individual->method('xref')->willReturn('X123');

        // 3. Registry-Factory (wenn nötig)
        $individual_factory = $this->createMock(IndividualFactory::class);
        $individual_factory->expects($this->once())
            ->method('make')
            ->with('X123', $tree)
            ->willReturn($individual);
        Registry::individualFactory($individual_factory);

        // 4. Modul instanziieren
        $module = new {SUT_CLASS}($chart_service);

        // 5. Request mit Attributen
        $request = self::createRequest()
            ->withAttribute('tree', $tree)
            ->withAttribute('xref', 'X123')
            ->withAttribute('style', $style)
            ->withAttribute('user', new GuestUser());

        // 6. Handle aufrufen
        $response = $module->handle($request);

        // 7. Assertions
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public static function chartStyles(): array
    {
        return [
            ['tree'],
            ['individuals'],
            ['families'],
        ];
    }

    public function testHandleWithInvalidXref(): void
    {
        $chart_service = $this->createMock(ChartService::class);

        $tree = self::createStub(Tree::class);

        $individual_factory = $this->createMock(IndividualFactory::class);
        $individual_factory->expects($this->once())
            ->method('make')
            ->with('INVALID', $tree)
            ->willReturn(null);
        Registry::individualFactory($individual_factory);

        $this->expectException(HttpNotFoundException::class);

        $module = new {SUT_CLASS}($chart_service);
        $request = self::createRequest()
            ->withAttribute('tree', $tree)
            ->withAttribute('xref', 'INVALID');
        $module->handle($request);
    }

    public function testTitle(): void
    {
        $module = new {SUT_CLASS}(...);  // Konstruktor-Deps als Stubs
        self::assertNotEmpty($module->title());
    }
}
```

**DataProvider-Konvention:**
- Chart-Module: DataProvider für alle `chartStyle()` Rückgabewerte
- List-Module: Kein DataProvider nötig (meist nur ein `handle()`-Pfad)

**`listIsEmpty()` ist ein Layer-3-Kandidat:**
Diese Methode fragt die DB ab. Wenn der Test sie prüfen will, gehört
dieser Testfall in Layer 3. Im Layer-2-Test nur `handle()` testen.

### Schritt 4: Bug-Erkennung

1. **Fehlende canShow()-Prüfung:** Prüft `handle()` den Privacy-Check
   `$individual->canShow()` bevor es Chart-Daten rendert?
2. **Null-Individual ohne Exception:** Gibt `Registry::individualFactory()->make()`
   null zurück, aber der Handler greift trotzdem auf Methoden zu?
3. **Style-Validierung:** Akzeptiert der Handler beliebige Style-Werte ohne
   Validierung? Was passiert bei ungültigem Style?
4. **Fehlende Module-Aktivierungsprüfung:** Prüft der Handler, ob das Modul
   für den Tree aktiviert ist?
5. **Integer-Parameter ohne Bounds:** Werden `generations`, `width` etc.
   ohne Min/Max-Check verwendet?

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

- [ ] SUT-Klasse gelesen (unter `app/Module/`)
- [ ] Konstruktor-Dependencies identifiziert
- [ ] `handle()`-Codepfade identifiziert
- [ ] Chart-Styles / DataProvider-Kandidaten identifiziert
- [ ] `testClass()` beibehalten
- [ ] Service-Dependencies als `createMock()`
- [ ] Domain-Objekte als `createStub()`
- [ ] Registry-Factory als `createMock()` (wenn nötig)
- [ ] DataProvider für Styles (wenn Chart-Modul)
- [ ] `testTitle()` und/oder `testDescription()` hinzugefügt
- [ ] Invalid-XREF-Test (Not Found) hinzugefügt
- [ ] `listIsEmpty()` **nicht** als L2-Test (→ Layer 3)
- [ ] Bug-Erkennung durchgeführt
- [ ] Test grün validiert
