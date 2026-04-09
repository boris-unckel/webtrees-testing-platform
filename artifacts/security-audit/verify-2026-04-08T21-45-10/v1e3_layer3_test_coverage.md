<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# V1e.3 — Layer-3 (und Layer-2) Testabdeckung für Security-Sinks

**Verification target**: Für jede in V1a–V1e.2 geprüfte Code-Stelle ermitteln, ob überhaupt Tests existieren und ob diese Tests die *Sicherheitseigenschaft* (nicht nur die funktionale Happy-Path-Eigenschaft) abdecken. Ziel: Scheinsicherheit durch "Test existiert, also ist es sicher" auszuschließen.

## Methodik

1. Grep über `layer3-integration/tests/` + `upstream/webtrees/tests/` nach Klassennamen und typischen Test-Pattern.
2. Für jede Fundstelle: Line-Count + `public function test`-Grep + Stichproben-Read auf die Testkörper.
3. Pro Sink: *Funktionale Coverage* (wird die Happy Path getestet?) und *Security-Coverage* (werden Angriffsvektoren getestet?) getrennt bewertet.
4. Keine neuen Tests geschrieben — V1e.3 ist reine Bestandsaufnahme. Neue Tests wandern als Task-Kandidaten in V3.

## Ergebnismatrix

| Sink (aus V1a–V1e.2) | Layer 2 (upstream) | Layer 3 (eigene) | Security-Coverage? | Gap |
|---|---|---|---|---|
| `HtmlService::sanitize()` (V1c) | 2 Tests (47 LOC) | **0** | **Nein** | → Gap H1 |
| `SearchService::searchX()` (V1b) | 1 Test (75 LOC) | 32 Tests (547 LOC) | **Nein** (nur funktional) | → Gap S1 |
| `SearchService` via HTTP handler (V1b) | — | 6 Tests (163 LOC) | **Nein** | → Gap S1 |
| `ReportParserGenerate` (V1a) | 1 Smoke (`class_exists`) | 9+3 Tests (290+149 LOC) | **Nein** (keine EL-Payload) | → Gap R1 |
| `ReportExpressionLanguageProvider` (V1a) | 1 Smoke (`class_exists`) | 0 | **Nein** | → Gap R2 |
| `RenumberTreeAction` raw `Expression()` (V1b) | — | 3 Tests (164 LOC) | **Nein** (kein REGEX_XREF-Defense-Test) | → Gap RT1 |
| `ModuleAction::handle()` (V1e.2 CRITICAL) | 4 Tests (132 LOC) | **0** | **TEILWEISE** (nur `action='Admin'` mit Großbuchstabe) | → Gap MA1 (der Bypass) |
| `AuthAdministrator` Middleware (V1e.2) | 3 Tests (80 LOC) | 0 | Ja (positiv+negativ+not-logged-in) | ✓ |
| `AuthEditor`/`AuthManager`/`AuthMember`/`AuthModerator` | je 3 Tests (je 89 LOC) | 0 | Ja | ✓ |
| `AuthLoggedIn` Middleware (V1e.2) | **1 Smoke (`class_exists`)** | 0 | **Nein** | → Gap ML1 |
| `CheckCsrf` Middleware (V1e.2) | 1 Test (52 LOC) | 0 | **TEILWEISE** (nur fehlender Token) | → Gap MC1 |
| `PublicFiles` Middleware (V1e.2) | **1 Smoke (`class_exists`)** | 0 | **Nein** | → Gap MP1 |
| `SecurityHeaders` Middleware (V1e.2) | **1 Smoke (`class_exists`)** | 0 | **Nein** | → Gap MS1 |
| `HandleExceptions` Middleware (V1e.2) | 1 Test (56 LOC) | 0 | **TEILWEISE** (nur Status 500, kein HTML-Escape-Test) | → Gap MH1 |
| `MediaFileService::uploadFile()` + `ImageFactory` SVG (SEC-AUDIT-001) | — | **5 Tests Security-Suite** | **Ja** | ✓ |

## Detaillierte Gap-Analyse

### Gap H1 — HtmlService hat nur Smoke-Tests

**Quelle**: `upstream/webtrees/tests/app/Services/HtmlServiceTest.php` (47 LOC, 2 Testmethoden).

```php
public function testAllowedHtml(): void
{
    $dirty = '<div class="foo">bar</div>';
    $clean = $html_service->sanitize($dirty);
    self::assertSame($dirty, $clean);
}

public function testDisallowedHtml(): void
{
    $dirty = '<div class="foo" onclick="alert(123)">bar</div>';
    $clean = $html_service->sanitize($dirty);
    self::assertSame('<div class="foo">bar</div>', $clean);
}
```

Das sind exakt **zwei** Assertions: eine `<div>` passiert, ein `onclick` wird entfernt. **Fehlend**:

- `<script>alert(1)</script>` (Basis-XSS)
- `<iframe src="javascript:alert(1)">` (iframe-Blocker)
- `<a href="javascript:alert(1)">` (URI-Scheme-Whitelist)
- `<a href="data:text/html,...">` (data:-Scheme)
- `<img src=x onerror=alert(1)>` (Event-Handler)
- `<svg><use xlink:href="..."/></svg>` (SVG-Use-Vektor)
- `<style>body{background:url("javascript:...")}</style>` (CSS-URL)
- `<a id="foo">`-DOM-Clobbering (wegen `Attr.EnableID = true`, siehe V1c-2)
- `<a href="..." target="_blank">` ohne `rel="noopener"` (V1c-1)
- Rekursion/Deeply-nested HTML (HTMLPurifier-Crash-Test)

**Bewertung**: Wenn HTMLPurifier durch einen Bug oder eine falsche Konfiguration plötzlich `<script>` durchließe, würden beide existierenden Tests grün bleiben. Das ist **Scheinsicherheit**: der Test existiert, testet aber nicht die Eigenschaft, die er zu testen vorgibt.

**Task-Kandidat (V3)**: `TEST-V1e3-H1` — HtmlService-XSS-Matrix in Layer 3 ergänzen (MySQL-unabhängig, könnte auch Layer 2 sein). Mindestens 10 OWASP-Payloads aus der XSS-Cheatsheet, jeweils mit `assertStringNotContainsString('alert', $clean)` und `assertStringNotContainsString('javascript:', $clean)`.

### Gap S1 — SearchService hat keine Security-Tests

**Quelle**:
- `upstream/webtrees/tests/app/Services/SearchServiceTest.php` — **1 Test (Smoke, 75 LOC)**: ruft alle `searchX`-Methoden einmal auf und prüft, dass Ergebnisse nicht leer sind.
- `layer3-integration/tests/SearchIntegrationTest.php` — **32 Tests (547 LOC)**: funktional ausgiebig (find/empty/limit/offset/phonetic/cross-tree/advanced-search).

Grep-Ergebnis nach Security-Pattern in den Layer-3-Tests: **keine** Payloads wie `' OR 1=1--`, `%' UNION SELECT`, `%%%%%%%` (LIKE-DoS), oder Newline-Injection.

**Konkreter Gap in Bezug auf V1a (LIKE-DoS)**: V1a hat festgestellt, dass der Pfad `^LIKE /(.+)/$` in `ReportParserGenerate.php:1405` attacker-kontrollierte LIKE-Patterns ohne Wildcard-Escape in die Query schreibt — DoS-Primitive, wenn ein Drittmodul-Template die Regex verwendet. Das ist strenggenommen ein Report-Pfad (Gap R1), aber das allgemeine Pattern "LIKE-Payload ohne Wildcard-Escape" wird auch im SearchService nicht getestet.

**Konkreter Gap in Bezug auf V1b (raw `Expression()` in Services)**: V1b hat 11 `new Expression(...)`-Vorkommen in `app/Services/` gefunden, alle ohne Parameter-Bindung, aber alle mit hardcoded Literals oder mit Variablen, die konstruktiv sicher sind. Kein Test prüft, dass die Werte in diesen Expressions tatsächlich keine Benutzereingaben enthalten — der Schutz ist ausschließlich per Code-Review herleitbar.

**Task-Kandidat (V3)**: `TEST-V1e3-S1` — mindestens ein negativer Test, der `%' OR 1=1--`-Query absetzt und prüft, dass kein Crash auftritt und keine unerwarteten Ergebnisse zurückkommen. Plus ein Wildcard-DoS-Test mit Timeout.

### Gap R1 — ReportParserGenerate hat keine EL-Payload-Tests

**Quelle**:
- `upstream/webtrees/tests/app/Report/ReportParserGenerateTest.php` — **Smoke-Only**, 32 LOC:

```php
public function testClass(): void
{
    self::assertTrue(class_exists(ReportParserGenerate::class));
}
```

Das ist buchstäblich der schwächst-mögliche Test. **Er prüft, dass PHP die Klasse laden kann.** Nicht mehr.

- `layer3-integration/tests/ReportIntegrationTest.php` — 290 LOC, 9 Tests, alle funktional (birth-report rendert, cemetery-report rendert, pdf rendert, ContentDisposition-Header, unknown-report-redirect).
- `layer3-integration/tests/ReportParserGenerateExtendedIntegrationTest.php` — 149 LOC, 3 Tests, testet spezifisch die `relatives` und `facts+image` Handler-Pfade.

Kein Test aus den bundled reports versucht:
- Einen EL-Trigger zu aktivieren (`<SetVar value="$uservar + 1"/>` mit kontrollierter `$uservar`).
- `constant()` über einen EL-Eval zu erreichen.
- Die `addcslashes`-Escape-Korrektheit der `substituteVars($value, true)`-Pfade zu verifizieren (V1a Claim B).
- Einen `^LIKE /(.+)/$`-Wildcard-DoS-Test gegen den Raw-Pfad bei V1a Claim C.

**Konsequenz**: V1a-Claim "Function-Allowlist enthält nur `stristr`" **war falsch** (enthält auch `constant`, `min`, `max`, `enum`). Hätte es einen Test gegeben, der `constant("DB_PASSWORD")` über ein bundled Template versucht, wäre die Behauptung sofort gefallen — entweder durch unerwarteten Erfolg (Disclosure) oder durch den Nachweis, dass die EL-Funktion aktiv ist. Die Abwesenheit eines solchen Tests war der Grund, warum die Claim-Refutation erst in V1a aufgefallen ist.

**Task-Kandidat (V3)**: `TEST-V1e3-R1` — Regression-Test gegen V1a-Refutation. Ein Report mit `<Input>` + `<SetVar value="$input + 1"/>` + `ifStartHandler`-Substitution, der die Ausgabe verifiziert: wenn ein Angreifer `constant("Webtrees::VERSION")` als Input liefert, darf die Version **nicht** im Output stehen. Plus Test für den `^LIKE /.*/$`-Raw-Pfad (DoS-Probe mit kleinem Timeout).

### Gap R2 — ReportExpressionLanguageProvider ist ungetestet

**Quelle**: `upstream/webtrees/tests/app/Report/ReportExpressionLanguageProviderTest.php` — **Smoke-Only**, 32 LOC, identischer `class_exists`-Test.

**Konkretes Risiko**: Wenn der Provider morgens um eine neue Custom-Funktion erweitert wird (z.B. `strtolower`, `substr`), gibt es keinen Test, der dies aufhält oder die neue Funktion gegen Injection prüft.

**Task-Kandidat (V3)**: `TEST-V1e3-R2` — Explizite Positiv/Negativ-Tests:
- `stristr('abc', 'b')` → `'bc'` (Positiv)
- `stristr(constant("DB_PASSWORD"), 'x')` → Exception oder kontrolliertes Leer-Ergebnis (Negativ, aber auch Regression, weil `constant` aus den Symfony-EL-Defaults *ist* reachable)
- Liste der aktiven Functions sollte per Reflection asserted werden, damit eine Erweiterung der Allowlist einen Test-Fehler auslöst.

### Gap RT1 — RenumberTreeAction testet die REGEX_XREF-Defense nicht

**Quelle**: `layer3-integration/tests/RenumberTreeActionIntegrationTest.php` — 164 LOC, 3 Tests:

1. `test_renumber_tree_no_action_when_no_cross_tree_duplicates` — funktional, keine Duplikate → keine Aktion.
2. `test_renumber_tree_renames_duplicate_individual_xref` — funktional, Cross-Tree-Duplikat wird umbenannt.
3. `test_renumber_tree_blocked_when_pending_edits_and_duplicates` — funktional, Guard feuert bei Pending Edits.

**Fehlend**: ein Test, der direkt einen präparierten `i_id`-Wert mit SQL-Metacharactern in die DB schreibt (unter Umgehung der GEDCOM-Import-Regex) und dann `RenumberTreeAction` ausführt. Erwartetes Verhalten: entweder ein klar dokumentierter Fehler oder eine nachweisbar korrekte Escape-Sequenz.

**Das ist der direkte Test für die V1b-Feststellung**: Das raw `new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', ...)")`-Muster hängt von einem Invariant ab, das in einer *anderen Datei* (`app/Gedcom.php:243`, `REGEX_XREF`) enforced wird. Ein Regressionstest sollte diesen Invariant explizit prüfen.

**Task-Kandidat (V3)**: `TEST-V1e3-RT1` — direkter DB-Insert einer präparierten `i_id = "DUPXREF'; DROP TABLE users;--"` (oder ähnlich) mit anschließendem RenumberTreeAction-Run. Erwartet wird entweder eine SQL-Exception (wenn Expression-Escape fehlt) oder ein success ohne Table-Drop (defense-in-depth OK). Der Test dient als Alarm, wenn jemand `REGEX_XREF` in der Zukunft lockert.

### Gap MA1 — ModuleAction-Bypass ist in den existierenden Tests nicht abgedeckt

**Quelle**: `upstream/webtrees/tests/app/Http/RequestHandlers/ModuleActionTest.php` — 132 LOC, 4 Tests:

```php
public function testAdminAction(): void
{
    $this->expectException(HttpAccessDeniedException::class);
    $request = self::createRequest()
        ->withAttribute('module', 'test')
        ->withAttribute('action', 'Admin')        // ← GROSSBUCHSTABE
        ->withAttribute('user', $user);
    $handler->handle($request);
}
```

**Dies ist genau das Test-Scheinsicherheits-Muster**: Der Test existiert, heißt `testAdminAction`, ruft `str_contains($action, 'Admin')` mit korrekt großgeschriebenem `'Admin'` auf und bestätigt, dass der Gate feuert. Die **case-sensitivity-Lücke** wird nicht getestet, weil alle Test-Inputs ausschließlich mit dem korrekten Casing generiert werden.

Ein `testAdminActionLowercase` mit `->withAttribute('action', 'admin')` hätte:
- **Erwartet**: `HttpAccessDeniedException` (weil PHP-Method-Dispatch case-insensitive ist und `postadminAction` trotzdem auf `postAdminAction` auflöst)
- **Tatsächlich (pre-fix)**: Der Gate wurde *nicht* ausgelöst, und die Admin-Methode lief durch. Der Test hätte die Vulnerability bei der ersten Ausführung gefangen.

Ein `testAdminActionMixedCase` mit `->withAttribute('action', 'ADMIN')` oder `'AdMiN'` hätte das gleiche gezeigt.

Dies ist **das klarste Beispiel von Scheinsicherheit im gesamten bisherigen Audit**. Layer 2 hat das Test-Framework, Layer 2 kennt das Pattern, Layer 2 hat einen Test namens `testAdminAction` — und trotzdem hat niemand den Test mit Kleinbuchstaben ausgeführt.

**Task-Kandidat (V3)**: `TEST-V1e3-MA1` — Regression für SEC-AUDIT-005. Muss **vor** dem Fix in ModuleAction.php geschrieben werden, muss fail-then-pass sein:

```php
#[DataProvider('caseBypassProvider')]
public function testAdminActionCaseBypass(string $actionAttr): void
{
    $this->expectException(HttpAccessDeniedException::class);
    $request = self::createRequest()
        ->withAttribute('module', 'test')
        ->withAttribute('action', $actionAttr)
        ->withAttribute('user', new GuestUser());
    (new ModuleAction($module_service))->handle($request);
}

public static function caseBypassProvider(): array
{
    return [
        ['Admin'],        // baseline (bereits getestet)
        ['admin'],        // V1e.2 bypass (all lowercase)
        ['ADMIN'],        // uppercase
        ['AdMiN'],        // mixed
        ['admin-edit'],   // V1e.2 PoC
        ['admindelete'],  // V1e.2 PoC
    ];
}
```

Plus Layer-3-Test (kein Unit-Level-Mock), der einen echten HTTP-Request gegen `/module/faq/adminedit` absetzt und 403 erwartet.

### Gap ML1 — AuthLoggedIn hat nur einen `class_exists`-Test

**Quelle**: `upstream/webtrees/tests/app/Http/Middleware/AuthLoggedInTest.php` — 32 LOC, identisches `class_exists`-Pattern wie PublicFilesTest und SecurityHeadersTest.

Die anderen Auth-Middleware (Administrator/Editor/Manager/Member/Moderator) haben ordentliche 80–89-Zeilen-Tests mit drei Fällen pro Middleware (allowed/not-allowed/not-logged-in). **Nur AuthLoggedIn fällt heraus.** Das Muster legt nahe, dass der Autor den Test nie geschrieben und nur den Skelett-Platzhalter committed hat.

**Konkretes Risiko**: Wenn `AuthLoggedIn::process()` durch einen Bug oder Refactoring kaputtgeht, gibt es keinen Test, der es aufhält.

**Task-Kandidat (V3)**: `TEST-V1e3-ML1` — AuthLoggedIn-Test analog zu AuthAdministrator schreiben: allowed (angemeldeter User), not-logged-in (Guest → Redirect auf Login). Triviale 60-Zeilen-Aufgabe, die bei Änderungen eine sofortige Warnung liefert.

### Gap MC1 — CheckCsrf-Test ist unvollständig

**Quelle**: `upstream/webtrees/tests/app/Http/Middleware/CheckCsrfTest.php` — 52 LOC, 1 Testmethode:

```php
public function testMiddleware(): void
{
    // POST ohne Token → erwartet: Redirect (302)
    $request = self::createRequest(RequestMethodInterface::METHOD_POST)
        ->withUri($uri_factory->createUri('https://example.com'));
    $middleware = new CheckCsrf();
    $response = $middleware->process($request, $handler);
    self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
}
```

**Getestet**: POST ohne Token wird geblockt.
**Nicht getestet**:
1. POST mit **korrektem** Token passiert (regression-kritisch — wenn hier jemand den Vergleich invertiert, passiert **nichts**, Tests bleiben grün).
2. POST mit **falschem** Token wird geblockt (V1e.2 Observation: non-constant-time-comparison).
3. `EXCLUDE_ROUTES` (Logout, SelectLanguage, SelectTheme) tatsächlich ausgenommen sind.
4. GET-Requests (read-only) passieren auch ohne Token.

**Task-Kandidat (V3)**: `TEST-V1e3-MC1` — CheckCsrf-Test um 4 Fälle erweitern. Triviale Ergänzung, die den V1e.2-Observation "non-constant-time comparison" auf eine Test-Existenz hebt, auch wenn der Constant-Time-Aspekt selbst nicht testbar ist.

### Gap MP1 — PublicFiles hat nur `class_exists`

**Quelle**: `upstream/webtrees/tests/app/Http/Middleware/PublicFilesTest.php` — 32 LOC, `class_exists`.

**Direkter Bezug zu V1e.2**: V1e.2 hat festgestellt, dass die Substring-Prüfung `str_contains($path, '..')` fragil ist und nur funktioniert, weil PSR-7 den raw percent-encoded Path liefert und PHP-File-Funktionen nicht dekodieren. Ein Test, der die Defense explizit verifiziert:

1. `/public/%2e%2e/config.ini.php` → 404 oder 403 (heute: 404, aber nicht verifiziert)
2. `/public/..%2fconfig.ini.php` → 404
3. `/public/legit-asset.svg` → 200 (Happy Path)
4. `/public/../app/Webtrees.php` → 404 (direktes `..` im Path)

**Task-Kandidat (V3)**: `TEST-V1e3-MP1` — Path-Traversal-Matrix für PublicFiles. 4 Payloads minimum, alle mit `self::createRequest()->withUri(...)`.

### Gap MS1 — SecurityHeaders hat nur `class_exists`

**Quelle**: `upstream/webtrees/tests/app/Http/Middleware/SecurityHeadersTest.php` — 32 LOC.

V1e.2 Finding: **Kein CSP-Header gesetzt**. Nur `Permissions-Policy`, `Referrer-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`.

Ein korrekter Test würde die Header-Liste explizit assertieren:

```php
public function testSecurityHeadersPresent(): void
{
    $middleware = new SecurityHeaders();
    $response = $middleware->process($request, $handler);
    self::assertNotEmpty($response->getHeaderLine('X-Content-Type-Options'));
    self::assertNotEmpty($response->getHeaderLine('X-Frame-Options'));
    // NB: CSP is NOT currently set — this assertion would DOCUMENT the gap
    self::assertEmpty($response->getHeaderLine('Content-Security-Policy'),
        'When CSP is added, this assertion should be inverted to assertNotEmpty');
}
```

**Task-Kandidat (V3)**: `TEST-V1e3-MS1` — SecurityHeaders-Assertions explizit machen. Dient als Dokumentation der aktuellen Header-Konfiguration und als Regression-Schutz.

### Gap MH1 — HandleExceptions testet HTML-Escape nicht

**Quelle**: `upstream/webtrees/tests/app/Http/Middleware/HandleExceptionsTest.php` — 56 LOC, 1 Test:

```php
public function testMiddleware(): void
{
    $handler->method('handle')->willThrowException(new HttpServerErrorException('eek'));
    // ...
    self::assertSame(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR, $response->getStatusCode());
}
```

**Nicht getestet**:
1. Exception-Message mit HTML-Special-Chars (`<script>alert(1)</script>` als Exception-Message) wird escaped ausgegeben.
2. Der Base-Path-Redacter (`str_replace($base_path, '…', $trace)` bei `HandleExceptions.php:207`) entfernt wirklich alle Vorkommen.
3. Der Fallback-Pfad bei `HandleExceptions.php:134` (wenn `view('errors/500')` selbst wirft).
4. Stack-Traces enthalten kein `DB_PASSWORD` / keine Konfigurationswerte.

**Task-Kandidat (V3)**: `TEST-V1e3-MH1` — HTML-Escape-Test und Path-Redaction-Test für HandleExceptions.

## Zusammenfassung

| Gap | Betroffener Sink | Schweregrad (Testlücke) | Empfohlener Task |
|---|---|---|---|
| H1 | HtmlService | **HOCH** (Scheinsicherheit) | TEST-V1e3-H1 |
| S1 | SearchService | Mittel (funktional komplett, Security fehlt) | TEST-V1e3-S1 |
| R1 | ReportParserGenerate | **HOCH** (direkt mit V1a-Refutation verbunden) | TEST-V1e3-R1 |
| R2 | ReportExpressionLanguageProvider | Mittel | TEST-V1e3-R2 |
| RT1 | RenumberTreeAction raw Expression | Mittel (defense-in-depth, V1b-Finding) | TEST-V1e3-RT1 |
| **MA1** | **ModuleAction Bypass** | **CRITICAL** (Regression für SEC-AUDIT-005) | **TEST-V1e3-MA1** |
| ML1 | AuthLoggedIn Middleware | Niedrig (Vollständigkeit) | TEST-V1e3-ML1 |
| MC1 | CheckCsrf Middleware | Mittel | TEST-V1e3-MC1 |
| MP1 | PublicFiles Middleware | Mittel (defense-in-depth, V1e.2-Finding) | TEST-V1e3-MP1 |
| MS1 | SecurityHeaders Middleware | Niedrig (Dokumentation) | TEST-V1e3-MS1 |
| MH1 | HandleExceptions Middleware | Mittel | TEST-V1e3-MH1 |

**Gesamt: 11 Testlücken identifiziert.** Davon:
- **1 CRITICAL** (MA1, verbunden mit SEC-AUDIT-005)
- **2 HOCH** (H1, R1 — beides Scheinsicherheit im engeren Sinn)
- **5 Mittel** (S1, R2, RT1, MC1, MP1, MH1)
- **2 Niedrig** (ML1, MS1)

## Meta-Beobachtungen zur Test-Strategie

1. **Das `class_exists`-Pattern ist endemisch**. Von 31 Middleware-Testdateien sind **20 ausschließlich `class_exists`-Smoke-Tests** (32 LOC, identisches Boilerplate). Das gleiche Pattern trifft auf `ReportParserGenerateTest`, `ReportExpressionLanguageProviderTest` und andere Report-Klassen zu. Diese Tests tragen **null Informationsgehalt** — sie prüfen, ob die Composer-Autoloader die Klasse finden, was bei einem korrekt gesetzten `composer dump-autoload` immer der Fall ist. Sie geben falsche Test-Count-Zahlen in die CI zurück.

2. **Das "Test-Name ≠ Test-Content"-Anti-Pattern**. `ModuleActionTest::testAdminAction` **klingt**, als würde er den Admin-Gate testen. Er testet aber nur einen Codepfad mit einem harmlos gewählten Input. Ein sicherheitsbewusster Test hätte einen `@dataProvider` mit fünf Casing-Varianten.

3. **Layer-3 deckt funktional, Layer-2 deckt gar nichts**. Die Layer-3-Suite (79 Tests, 20k+ LOC) ist **deutlich umfangreicher** als die Layer-2-Upstream-Suite im Security-relevanten Teil. Die *einzigen* Layer-2-Tests, die substanziell mehr als `class_exists` machen, sind die 5 Auth-Middleware (Administrator/Editor/Manager/Member/Moderator). Alle anderen Security-kritischen Codepfade hängen an der Layer-3-Suite.

4. **Es gibt genau einen dedizierten Security-Test-Ordner in Layer 3**: `layer3-integration/tests/Security/` mit `SecAudit001Test.php` (5 Tests). Das ist die einzige Stelle im gesamten Projekt, an der Tests **explizit** die Sicherheitseigenschaft (nicht nur die funktionale Eigenschaft) prüfen. **V1e.3 empfiehlt, dieses Pattern auf alle weiteren Gaps auszudehnen**.

## Score-Impact

V1e.3 erzeugt keine neuen Expoit-Findings (die Lücken sind Test-Lücken, keine Code-Lücken). Aber V1e.3 rechtfertigt **retrospektiv die Schweregradeinstufung von V1e.2**:

- Die Tatsache, dass `ModuleActionTest::testAdminAction` existiert, aber case-sensitive-only ist, verstärkt das **Scheinsicherheits-Argument** für SEC-AUDIT-005. Ein Code-Reviewer, der "grüner Test mit passendem Namen" sieht, glaubt, dass der Admin-Gate funktioniert. Er funktioniert nicht. Der Test ist Teil der Täuschung.

- Die Tatsache, dass `HtmlServiceTest` nur 2 Tests enthält, rechtfertigt die V1c-Empfehlung, HtmlService-Härtung in eine defensive Hardening-PR einzuschließen — es gibt kein "aber wir haben ja Tests, die würden das fangen"-Argument, weil die Tests es nicht fangen würden.

## Neue Follow-up-Observations (keine Tasks durch V1e.3 selbst, aber Task-Kandidaten für V3)

1. **`class_exists`-Tests sind Dead Code**. V3 könnte empfehlen, sie zu *löschen* (nicht zu erweitern), damit die CI nicht mehr 20 wertlose grüne Marker ausgibt. Alternative: diese Slots mit echten Tests füllen (siehe ML1, MP1, MS1).

2. **Test-Pattern-Dokumentation**. Der Ordner `layer3-integration/tests/Security/` sollte eine `README.md` bekommen, die das SecAudit001Test-Pattern als Template für alle zukünftigen Security-Tests empfiehlt. Hypothesis-Mapping, `assertSvgWasBlockedByFilter`-style Helfer, Fixture-JSON-Struktur — das alles ist wiederverwendbar.

3. **Regression-First-Workflow für SEC-AUDIT-005**. Der Task sollte explizit fordern, dass `TEST-V1e3-MA1` **vor** dem Fix in `ModuleAction.php` geschrieben wird und zunächst **fehlschlägt**. Dann Fix. Dann grün. Das ist das einzige Test-First-Vorgehen, das den Scheinsicherheits-Schwanz am Kopf packt.

**V1e.3 schließt damit ab.** Keine neuen Code-Tasks, aber 11 Test-Task-Kandidaten, die in V3 triagiert werden.
