<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt Template 2: Handler ohne Dependencies

**Passt auf:** RequestHandler ohne Konstruktor-Parameter oder mit nur leichtgewichtigen
Value Objects. Die SUT hat keine injizierbaren Dependencies.

**Muster-Vorlage:** `SelectLanguageTest.php` (Guest-Pfad), `PingTest.php`

---

## Prompt

> **Ziel-Repo:** `webtrees-upstream/webtrees` (Fork) — **GPL-3.0-or-later**, **en_GB**
> Lizenz-Header: `// SPDX-License-Identifier: GPL-3.0-or-later` (nach `<?php`)
> Sprache: Alle Code-Kommentare, PHPDoc und BUG-CANDIDATE-Marker in Englisch (en_GB)

Du portierst den Stub-Test `{TEST_FILE}` in einen substanziellen Layer-2-Komponentest.
Der Handler hat keine oder nur leichtgewichtige Konstruktor-Dependencies.

### Schritt 1: SUT-Klasse lesen

Lies die SUT-Klasse:
`app/Http/RequestHandlers/{SUT_CLASS}.php`

Identifiziere:
1. **Konstruktor:** Leer oder nur Value Objects?
2. **`handle()`-Methode:** Welche Request-Attribute werden gelesen?
3. **Codepfade:** Gibt es Branching basierend auf Request-Daten?
4. **Response:** Statuscode, Body, Headers?
5. **Seiteneffekte:** Werden Preferences gesetzt? Session-Daten geändert?

### Schritt 2: Bestehenden Test lesen

Lies `tests/app/Http/RequestHandlers/{TEST_FILE}`.
`testClass()` bleibt erhalten.

### Schritt 3: Test schreiben

```php
#[CoversClass({SUT_CLASS}::class)]
class {SUT_CLASS}Test extends TestCase
{
    // bestehende testClass() bleibt!

    public function testHandleReturnsExpectedStatus(): void
    {
        $handler = new {SUT_CLASS}();
        $request = self::createRequest()
            ->withAttribute('{key}', '{value}');
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function testHandleWithDifferentInput(): void
    {
        $handler = new {SUT_CLASS}();
        $request = self::createRequest()
            ->withAttribute('{key}', '{other_value}');
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        // Prüfe Response-Body oder Header wenn relevant
    }
}
```

**Besonderheiten Template 2:**
- Kein `createMock()` oder `createStub()` für Services nötig
- Wenn der Handler `User`-Attribute liest: `new GuestUser()` direkt instanziieren
  (Value Object, kein Mock nötig)
- Wenn der Handler `Session`-Daten schreibt: nach `handle()` den Session-State prüfen
- Wenn der Handler Preferences setzt und ein User-Objekt braucht, das `setPreference()`
  auf die DB schreibt: prüfen, ob `$uses_database = true` nötig ist, oder ob ein
  Stub mit `expects()->method('setPreference')` besser passt (wie `SelectThemeTest`)

### Schritt 4: Bug-Erkennung

1. **Fehlende Input-Validierung:** Wird ein Request-Attribut ohne Fallback
   oder Typ-Check verwendet?
2. **Unerwartete Exceptions:** Wirft der Handler bei fehlendem Attribut eine
   unspezifische Exception statt `HttpBadRequestException`?
3. **Seiteneffekt ohne Auth-Check:** Setzt der Handler Preferences/Session-Daten
   ohne zu prüfen, ob der User berechtigt ist?
4. **Response-Typ-Inkonsistenz:** Gibt ein POST-Handler `200 OK` statt
   `204 No Content` oder `302 Redirect` zurück?

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

- [ ] SUT-Klasse gelesen, keine injizierbaren Dependencies bestätigt
- [ ] Alle Codepfade identifiziert
- [ ] `testClass()` beibehalten
- [ ] Mindestens 2 Tests (Success + Edge Case oder alternativer Input)
- [ ] `self::createRequest()` mit relevanten Attributen
- [ ] HTTP-Statuscode-Assertions
- [ ] Response-Body/Header geprüft wenn relevant
- [ ] Bug-Erkennung durchgeführt
- [ ] Test grün validiert
