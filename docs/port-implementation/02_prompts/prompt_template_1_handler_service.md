<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt Template 1: Handler mit Service-Dependencies

**Passt auf:** RequestHandler, deren Konstruktor einen oder mehrere Services erwartet
(z.B. `TreeService`, `ModuleService`, `SearchService`, `UserService`, `MessageService`).

**Muster-Vorlage:** `RedirectModulePhpTest.php`, `ModuleActionTest.php`, `MasqueradeTest.php`

---

## Prompt

Du portierst den Stub-Test `{TEST_FILE}` in einen substanziellen Layer-2-Komponentest
mit Test Doubles. Der Test liegt im webtrees-Fork unter
`/home/borisunckel/phpprojects/webtrees-upstream/webtrees/tests/app/Http/RequestHandlers/`.

### Schritt 1: SUT-Klasse lesen

Lies die SUT-Klasse (System Under Test):
`app/Http/RequestHandlers/{SUT_CLASS}.php`

Identifiziere:
1. **Konstruktor-Parameter:** Welche Services werden injiziert?
2. **`handle()`-Methode:** Welche Codepfade gibt es? (Success, Not Found, Access Denied, Bad Request, etc.)
3. **Request-Attribute:** Welche `$request->getAttribute()` / `$request->getQueryParams()` / `$request->getParsedBody()` werden gelesen?
4. **Return-Typ:** Welcher HTTP-Statuscode und Response-Typ wird in jedem Pfad zurückgegeben?
5. **Exception-Pfade:** Welche Exceptions werden geworfen? (`HttpNotFoundException`, `HttpAccessDeniedException`, etc.)

### Schritt 2: Bestehenden Test lesen

Lies den bestehenden Stub-Test:
`tests/app/Http/RequestHandlers/{TEST_FILE}`

Der Test hat aktuell nur eine `testClass()`-Methode mit `assertTrue(class_exists(...))`.
Diese Methode **bleibt erhalten**. Neue Testmethoden werden hinzugefügt.

### Schritt 3: Test schreiben

Erweitere den Test nach folgendem Pattern:

```php
#[CoversClass({SUT_CLASS}::class)]
class {SUT_CLASS}Test extends TestCase
{
    // bestehende testClass() bleibt!

    public function testHandle{SuccessCase}(): void
    {
        // 1. Mock für jeden Konstruktor-Service
        $service = $this->createMock(SomeService::class);
        $service->expects($this->once())
            ->method('{methodName}')
            ->with({expectedArgs})
            ->willReturn({successResult});

        // 2. Request mit Attributen
        $request = self::createRequest()
            ->withAttribute('{key}', '{value}');

        // 3. SUT instanziieren und aufrufen
        $handler = new {SUT_CLASS}($service);
        $response = $handler->handle($request);

        // 4. Assertions
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function testHandle{ErrorCase}(): void
    {
        $this->expectException(HttpNotFoundException::class);

        $service = $this->createMock(SomeService::class);
        $service->expects($this->once())
            ->method('{methodName}')
            ->willReturn(null);  // oder leere Collection

        $handler = new {SUT_CLASS}($service);
        $handler->handle(self::createRequest());
    }
}
```

**Regeln (R1–R11 aus Analyse):**
- R1: Nur die SUT-Klasse mit `new` instanziieren
- R2: Alle Konstruktor-Dependencies als Mock oder Stub
- R3: `expects($this->once())` für Service-Methoden, deren Aufruf getestet wird
- R4: Verschiedene `willReturn()`-Werte für verschiedene Codepfade
- R5: `expectException()` + `expectExceptionMessage()` VOR `handle()`
- R7: Exakten HTTP-Statuscode assertieren
- R11: `self::createRequest()` verwenden, nie manuell ServerRequest bauen

**Konventionen:**
- Domain-Objekte (`Tree`, `Individual`, `Family`, `User`) → `self::createStub()`
- Services/Factories → `$this->createMock()` mit `expects()`
- `GuestUser` ist ein konkretes Value Object — darf direkt instanziiert werden

### Schritt 4: Bug-Erkennung

**Prüfe die SUT-Implementierung auf potenzielle Bugs:**

1. **Exception-Typ-Konsistenz:** Wirft der Handler `\RuntimeException` oder
   `\InvalidArgumentException` wo `HttpNotFoundException` oder
   `HttpBadRequestException` korrekt wäre?

2. **Fehlende Input-Validierung:** Wird `$request->getAttribute('xref')` ohne
   Null-Check verwendet? Fehlt Typ-Validierung für Query-Parameter?

3. **Auth-Check-Lücken:** Hat ein Admin-Only-Handler keinen
   `Auth::checkIsAdmin()` oder equivalenten Check?

4. **Unreachable Code:** Gibt es Branches in `handle()`, die durch keinen
   Test-Double-Setup erreichbar sind? (Dead Code)

5. **Inkonsistenz mit verwandten Handlern:** Vergleiche mit ähnlichen Handlern
   derselben Kategorie — weicht die Fehlerbehandlung ab?

**Wenn ein potenzieller Bug gefunden wird:**
- Schreibe den Test trotzdem so, dass er **das erwartete korrekte Verhalten** testet
- Markiere den Test mit Kommentar: `// BUG-CANDIDATE: {Beschreibung}`
- Dokumentiere den Befund im Batch-Status als `bug_candidate`

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
- [ ] Alle Codepfade in `handle()` identifiziert (Success, Error, Edge Cases)
- [ ] `testClass()` beibehalten
- [ ] Je ein Test pro Codepfad geschrieben
- [ ] Alle Services als `createMock()` mit `expects()`
- [ ] Domain-Objekte als `createStub()`
- [ ] `self::createRequest()` verwendet
- [ ] Exception-Tests mit `expectException()` + `expectExceptionMessage()`
- [ ] HTTP-Statuscode-Assertions
- [ ] Bug-Erkennung durchgeführt
- [ ] Test grün validiert
