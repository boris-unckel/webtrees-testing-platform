<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Gemeinsamer Workflow: Test-Iteration

Dieser Leitfaden beschreibt den gemeinsamen Workflow für alle Test-Verbesserungs-Iterationen — unabhängig davon, ob die Testziele über Coverage/CRAP-Analyse oder über Upstream-Code-Analyse identifiziert wurden. Er enthält Methodik, Muster, Infrastrukturbausteine, den 5-Phasen-Arbeitsablauf und die Abschlussschritte.

Die beiden Entry-Guides bestimmen, *was* getestet werden soll:
- [Coverage → Test](wf_coverage-to-test_guide.md): Testziele aus CRAP-Score und Coverage-Lücken
- [Code → Test](wf_code-to-test_guide.md): Testziele aus systematischer Code-Analyse

Dieser Guide beschreibt, *wie* die Tests entworfen, implementiert und abgeschlossen werden.

Verwandte Dokumente:
- [Coverage → Test](wf_coverage-to-test_guide.md) — Entry: Coverage/CRAP-Analyse als Ausgangspunkt
- [Code → Test](wf_code-to-test_guide.md) — Entry: Upstream-Code-Analyse als Ausgangspunkt
- [Testbedingungen](tds_conditions_ref.md) — Feature-Matrizen und Referenz-IDs
- [Abdeckungsmatrix](tds_coverage_ref.md) — Aktueller Teststand

---

## 1 Qualitätsstufen (ISTQB)

Die aktuellen CRAP-Analyse-Tests sind **strukturbasiert** (ISTQB): Testziel-Auswahl via Code-Pfad-Coverage, Abbruchkriterium "kein Exception / kein HTTP 500". Das ist bewusst niedrig -- es deckt nur den minimalen Happy Path ab.

Eine höhere Qualitätsstufe nach ISTQB bedeutet eines der folgenden:

| Stufe | Bezeichnung | Was wird geprüft |
|---|---|---|
| **Strukturbasiert erweitert** | Branch/MC-DC Coverage | Alle Entscheidungspunkte im SUT werden abgedeckt, nicht nur der Happy Path |
| **Spezifikationsbasiert (B)** | Äquivalenzklassen + Grenzwerte | Anforderungen werden aus dem Verhalten des SUT abgeleitet; EP- und BVA-Methodik |
| **Pragmatisch erweitert (C)** | Negative Pfade + Guards | Wichtige Fehlerfälle, Guards und Pre-/Postconditions werden explizit geprüft |

**Faustregel:** Wenn ein SUT klare, ableitbare Invarianten hat (Validierungslogik, Zustandsmaschinen, definierte Enumerationen) → ISTQB B. Wenn das SUT primär HTTP-Handler-Koordination macht → Pragmatisch C (negative Guards).

---

## 2 Methodik

### 2.1 Äquivalenzklassenanalyse (EP)

**Ziel:** Eingaben in Klassen einteilen, bei denen der SUT identisches Verhalten zeigt. Pro Klasse reicht ein Testfall.

**Vorgehen:**

1. Alle Parameter/Attribute des SUT identifizieren.
2. Fur jeden Parameter: gultige und ungultige Partitionen bestimmen.
3. Pro Partition: genau einen Testfall ableiten.

**Beispiel (TreeExport `--format`):**

| Partition | Wert | Erwartung |
|---|---|---|
| EP-valid-1 | `gedcom` | Export erfolgreich, .ged erzeugt |
| EP-valid-2 | `gedzip` | Export erfolgreich, .gdz erzeugt |
| EP-valid-3 | `zip` | Export erfolgreich, .zip erzeugt |
| EP-valid-4 | `zipmedia` | Export erfolgreich, .zip mit Media erzeugt |
| EP-invalid-1 | `xml` | FAILURE, Fehlermeldung |
| EP-invalid-2 | `GEDCOM` (Grossschreibung) | FAILURE (case-sensitive) |
| EP-invalid-3 | `` (leer) | Fallback zu `gedcom` |

**In PHP PHPUnit:** `#[\PHPUnit\Framework\Attributes\DataProvider('...')]`-Annotation fur EP-Matrizen (→ Abschnitt 4).

### 2.2 Grenzwertanalyse (BVA)

**Ziel:** Grenzfalle an den Randern von Partitionen testen (Fehler treten haufig an Grenzen auf).

**Kandidaten suchen bei:**
- Numerischen Bereichen: 0, 1, max-1, max, max+1
- String-Langen: leer, 1 Zeichen, max-Lange, max+1
- Aufzahlungen: erster Wert, letzter Wert, ungultiger Wert
- Boolean-Flags: true/false, '0'/'1' als String
- Array-Langen: leer, 1 Element, viele Elemente

**Beispiel (StatisticsData `$limit`):**
- BV1: `$limit = 0` → leere Collection (Grenzfall unten)
- BV2: `$limit = 1` → genau 1 Ergebnis
- BV3: `$limit = PHP_INT_MAX` → alle Ergebnisse

### 2.3 Wiederkehrende Verbesserungsmuster

#### e.1 Negative Pfade und Guards

Fast alle Request-Handler und CLI-Commands haben **Guard-Clauses** am Anfang, die bei ungultigen Eingaben sofort abbrechen (Redirect, Exception, FAILURE). Diese sind bei strukturbasierten Tests typischerweise nicht abgedeckt.

**Muster:**
```php
// SUT hat Guard:
if ($record === null) {
    return redirect(MergeRecordsPage::class);
}
```

**Test:**
```php
public function test_handle_redirects_when_record1_not_found(): void {
    // Arrange: xref1 verweist auf nicht existierenden Record
    // Act: handle() aufrufen
    // Assert: Response ist 3xx-Redirect, kein 200
}
```

#### e.2 Vor-/Nachbedingungen (Pre/Postconditions)

Strukturbasierte Tests prüfen nur den Response-Code. Spezifikationsbasierte Tests prüfen zusatzlich den **Datenbankzustand** vor und nach der Aktion.

**Muster:**
```php
public function test_delete_record_removes_from_database(): void {
    // Precondition: Record existiert in DB
    self::assertNotNull(Registry::individualFactory()->make('I1', $tree));

    // Action
    $response = $this->handler->handle($request);

    // Postcondition: Record nicht mehr in DB
    self::assertNull(Registry::individualFactory()->make('I1', $tree));
    self::assertSame(204, $response->getStatusCode());
}
```

#### e.3 Zustandsabhangige Tests

Manche SUT-Pfade setzen einen bestimmten DB-Zustand voraus (z.B. `pending_edits`, `keep_media`, `imported`-Flag). Diese Zustande mussen im `setUp()` oder in der Test-Methode explizit hergestellt werden.

**Muster:**
```php
// Zustand setzen:
DB::table('gedcom_chunk')->insert(['imported' => 0, ...]);
$tree->setPreference('keep_media', '1');
```

#### e.4 Kaskadenloschung und Cross-Table-Integritat

Bei Losch- und Merge-Operationen mussen mehrere Tabellen nach der Aktion geprüft werden. Das ist aufwandig, aber fur spezifikationsbasierte Tests notwendig.

```php
// Nach DeleteRecord:
self::assertSame(0, DB::table('individuals')->where('xref', 'I1')->count());
self::assertSame(0, DB::table('link')->where('l_to', 'I1')->count());
```

---

## 3 Mock-Infrastruktur

### f.1 DNS-Mocking

**Problem:** Direkte `gethostbyaddr()`/`gethostbyname()`-Aufrufe im SUT -- kein Interface, kein DI.

**Option A: PHP Function Mocking** (ohne SUT-Anderung)

Das Paket `php-mock/php-mock-phpunit` ermoglicht das Uberschreiben von Built-in-Funktionen im Namespace des SUT:

```php
use phpmock\phpunit\PHPMock;

class BadBotBlockerTest extends TestCase {
    use PHPMock;

    public function test_dns_reverse_lookup_failure(): void {
        $gethostbyaddr = $this->getFunctionMock(
            'Fisharebest\Webtrees\Http\Middleware',
            'gethostbyaddr'
        );
        $gethostbyaddr->expects($this->once())->willReturn(false);

        // ... test DNS-Fehlerpfad
    }
}
```

**Voraussetzung:** `php-mock/php-mock-phpunit` als dev-Dependency. Funktioniert nur im Namespace des SUT.

**Option B: DNS-Service als Interface extrahieren** (SUT-Anderung notig)

Bessere langfristige Losung: `DnsResolverInterface` einfuhren, im SUT per DI injizieren. Dann ist der DNS-Resolver vollstandig mockbar. Dies ist jedoch eine Kern-Anderung am Upstream -- ausserhalb des Scope, solange kein Fork betrieben wird.

**Option C: Lokaler Mock-DNS-Server** (Test-Infrastruktur)

DNSMASQ o.a. im Podman-Compose-Stack konfigurieren, der bestimmte DNS-Antworten simuliert. Aufwand hoch, aber isolierbar.

**Empfehlung:** Option A fur schnelle Verbesserung; Option B als langfristige Ideal-Losung nur wenn geforkt wird.

### f.2 Filesystem-Mocking

Fur Tests, die Dateisystem-Operationen prüfen (Upload, Medienverwaltung), bietet `vfsStream` eine In-Memory-Filesystem-Emulation:

```php
use org\bovigo\vfs\vfsStream;

protected function setUp(): void {
    $this->root = vfsStream::setup('media');
    // MediaFileService mit vfs-Pfad konfigurieren
}
```

**Voraussetzung:** `mikey179/vfsstream` als dev-Dependency.

### f.3 HTTP-Mocking

Fur SUT-Klassen die externe HTTP-Requests ausfuhren, konnen Requests via `guzzlehttp/guzzle` Mock-Handler abgefangen werden:

```php
$mock = new MockHandler([
    new Response(200, [], 'fake-image-data'),
    new RequestException('Network Error', new Request('GET', 'test')),
]);
$client = new Client(['handler' => HandlerStack::create($mock)]);
```

**Voraussetzung:** SUT verwendet Guzzle oder einen PSR-18 Client; DI-Container muss den HTTP-Client austauschbar machen.

### f.4 WHOIS-Mocking

Wenn der SUT einen `NetworkService` per Konstruktor-DI empfangt, reichen PHPUnit-Mocks:

```php
$networkService = $this->createMock(NetworkService::class);
$networkService->method('findIpRangesForAsn')->willReturn([]);
$blocker = new BadBotBlocker($networkService);
```

Funktioniert **ohne** SUT-Anderung, weil der Service per Konstruktor injiziert wird.

### f.5 Mocking externer Services (E-Mail, Messaging)

**Problem:** Handler nutzen externe Services (E-Mail-Versand, Benachrichtigungsdienste), die im Test-Container nicht verfügbar sind (kein SMTP, kein externer Server).

**Testansatz in vier Stufen:**

1. **Formular-Rendering (GET):** Seite laden, Formularfelder prüfen — kein externer Service nötig.
2. **Validierung (POST):** Fehlende Pflichtfelder, ungültige Eingaben → Redirect mit Fehlermeldung. Kein Service-Aufruf nötig.
3. **Erfolgsfall (POST):** Service-Methode gemockt, gibt Erfolg zurück → Handler redirectet mit Erfolgsmeldung.
4. **Fehlerfall (POST):** Service-Mock gibt Fehler zurück → Handler zeigt Fehlermeldung.

```php
$service = $this->createMock(MessageServiceInterface::class);
$service->method('deliver')->willReturn(true);
// Handler mit gemocktem Service instantiieren
```

**Einschränkung:** Der tatsächliche externe Aufruf (SMTP, HTTP) wird nicht getestet. Die Integrations-Assertion beschränkt sich auf die Handler-Logik (Validierung, Redirect-Verhalten, Flash-Messages).

### f.6 Nicht-mockbare globale PHP-Funktionen

**Problem:** Manche Klassen nutzen globale PHP-Funktionen (`set_error_handler`, `headers_sent`, `ob_get_level`, `gzencode`), die in PHPUnit-Tests nicht direkt mockbar sind.

**Lösungsansatz 1 — PhpService-Kapselung:** Falls das SUT einen `PhpService` per DI empfängt, der globale Funktionen kapselt, kann dieser Service gemockt werden.

**Lösungsansatz 2 — try/finally:** Der Test löst absichtlich eine PHP-Bedingung aus und prüft die erwartete Reaktion. In einem `try/finally`-Block wird der Originalzustand wiederhergestellt:

```php
try {
    // SUT aufrufen, das z.B. set_error_handler() nutzt
    $response = $middleware->process($request, $handler);
    // Assertions auf erwartete Reaktion
} finally {
    // Originalzustand wiederherstellen (Handler, Buffer-Level etc.)
    restore_error_handler();
}
```

---

## 4 Batch-Tests und DataProvider

### Batch-Tests aufsplitten

Bestehende Batch-Testklassen bundeln mehrere Referenz-IDs. Fur spezifikationsbasierte Tests ist eine Aufteilung nach SUT-Klasse sinnvoll. Die bestehenden Batch-Tests konnen als Smoke-Tests (niedrigere Qualitatsstufe, schneller Sanity-Check) erhalten bleiben.

### DataProvider-Muster fur EP-Matrizen

Fur parametrisierte EP-Tests in PHPUnit:

```php
#[\PHPUnit\Framework\Attributes\DataProvider('formatProvider')]
public function test_export_format(string $format, string $expectedExtension): void {
    // ...
}

public static function formatProvider(): array {
    return [
        'gedcom'   => ['gedcom',   '.ged'],
        'gedzip'   => ['gedzip',   '.gdz'],
        'zip'      => ['zip',      '.zip'],
        'zipmedia' => ['zipmedia', '.zip'],
    ];
}
```

Fur Kreuzprodukt-Tests (mehrere Parameter kombiniert):

```php
// Kombinationsmatrix: sex x age_dir x type
public static function statisticsQueryProvider(): array {
    $sexValues    = ['F', 'M', 'ALL'];
    $ageDirValues = ['ASC', 'DESC'];
    $typeValues   = ['full', 'age'];

    $cases = [];
    foreach ($sexValues as $sex) {
        foreach ($ageDirValues as $dir) {
            foreach ($typeValues as $type) {
                $cases["{$sex}-{$dir}-{$type}"] = [$sex, $dir, $type];
            }
        }
    }
    return $cases;
}
```

### Batch-Handler-Strategie fur grosse Feature-Gruppen

Entscheidungsregel: Wann reicht ein Smoke-Test, wann ist ein vollstandiger EP-Test notig?

| Kriterium | Smoke-Test reicht | EP notig |
|---|---|---|
| Handler ist ein reiner GET-View (kein Schreib-Zustand) | ja | nein |
| Handler hat Guards (null-Check, Fehlerformat, Duplikat) | nein | ja |
| Handler schreibt in DB / Filesystem | nein | ja |
| Handler-Logik ist trivial (gibt view() zuruck) | ja | nein |
| Handler-Gruppe hat > 10 Handler | Smoke fur alle; EP fur 2--3 kritische | -- |

**Typische Strategien:**

- **Viele Handler (~14+):** DataProvider-Smoke (GET → 200 / POST → 302) fur alle Handler in einer Klasse. Fur die 2--3 mit der komplexesten Logik oder Guard-Branches: volle EP-Matrix.
- **AJAX-Endpoints:** Smoke-Test: GET → 200, JSON-Ausgabe. EP fur den "XREF direkt" vs. "Namenssuche"-Branch.
- **Modul-Konfigurations-Handler (~46):** Ein DataProvider mit allen Konfigurationsseiten-URLs, ein einzelner `test_module_config_page_returns_200($url)`. Fur die zentrale Action (Aktivieren/Deaktivieren) zusatzlich EP.

### Homogene Handler-Gruppen (Uniform-Interface-Pattern)

Wenn eine Gruppe von Handlern ein identisches Interface implementiert (gleiche Signatur, gleiches Antwort-Pattern), kann ein einzelner DataProvider-Test die gesamte Gruppe abdecken, indem er über Handler-**Klassen** iteriert:

```php
#[\PHPUnit\Framework\Attributes\DataProvider('uniformHandlerProvider')]
public function test_handler_returns_expected_response(
    string $handlerClass,
    array $queryParams,
    int $expectedStatus
): void {
    $handler = Registry::container()->get($handlerClass);
    $request = $this->createRequest(query: $queryParams, attributes: ['tree' => $this->tree]);
    $response = $handler->handle($request);
    self::assertSame($expectedStatus, $response->getStatusCode());
}
```

**Exception-Pfade** werden als separater DataProvider-Test abgebildet:

```php
#[\PHPUnit\Framework\Attributes\DataProvider('invalidParamsProvider')]
public function test_handler_throws_for_invalid_params(
    string $handlerClass,
    array $queryParams,
    string $exceptionClass
): void {
    $this->expectException($exceptionClass);
    $handler = Registry::container()->get($handlerClass);
    $request = $this->createRequest(query: $queryParams, attributes: ['tree' => $this->tree]);
    $handler->handle($request);
}
```

**Abgrenzung:** Für die 2–3 komplexesten Handler der Gruppe (mit zusätzlicher Logik oder Guard-Branches) werden separate, vollständige EP-Tests erstellt. Die DataProvider-Batch-Tests dienen als Basis-Abdeckung.

---

## 5 Spezifische Patterns

### i.1 Auth-Kontext in Komponentenintegrationstests

#### Grundmuster: createAndLoginAdmin()

Das Standardmuster fur alle Handler, die einen authentifizierten Admin benotigen:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->admin = $this->createAndLoginAdmin();
    // Auth::isAdmin() == true, Auth::id() != null ab jetzt
}
```

`createAndLoginAdmin()` (definiert in `MysqlTestCase`) fuhrt folgende Schritte aus:

1. Erzeugt einen neuen User via `$this->userService->create(...)`.
2. Setzt `PREF_IS_ADMINISTRATOR = '1'` via `$user->setPreference(...)`.
3. Ruft `Auth::login($user)` auf -- dies schreibt `Session::put('wt_user', $user->id())`.

Da `Session` in webtrees intern ein einfaches PHP-Array ist (kein Browser-Cookie), ist `Auth::login()` in PHPUnit-Tests voll funktionsfahig. Der Auth-Zustand gilt fur die gesamte Testmethode.

#### Zugriff auf Tree-Kontext

Fur Handler die `$tree = Validator::attributes($request)->tree()` aufrufen, muss der Tree im Request-Attribut gesetzt sein:

```php
$request = $this->createRequest(
    attributes: ['tree' => $this->tree, 'user' => $this->admin],
);
```

`createTreeWithGedcom()` in `MysqlTestCase` erstellt einen echten Baum mit Daten aus einer GEDCOM-Fixture und macht ihn uber `$this->tree` verfugbar.

#### Nicht-Admin-Benutzer fur Tests mit Privileg-Guards

```php
$member = $this->userService->create('member-user', 'Member', 'member@test.local', 'pw');
Auth::login($member);
// Auth::isAdmin() == false, Auth::isMember($tree) variiert je nach canedit-Praferenz
```

### i.2 PSR-7 UploadedFile fur HTTP-Datei-Uploads

Mehrere Handler empfangen Datei-Uploads via POST. In PHPUnit-Tests ist `$_FILES` leer -- es muss ein PSR-7-konformes `UploadedFileInterface`-Objekt in den Request injiziert werden.

**Losung: Laminas\Diactoros\UploadedFile**

webtrees verwendet Laminas Diactoros als PSR-7-Implementierung:

```php
use Laminas\Diactoros\UploadedFile;
use Laminas\Diactoros\Stream;

$stream = new Stream('php://temp', 'rw');
$stream->write('0 HEAD\n1 SOUR Test\n0 TRLR');
$stream->rewind();

$uploadedFile = new UploadedFile(
    $stream,
    $stream->getSize(),
    UPLOAD_ERR_OK,
    'test.ged',
    'text/plain'
);

$request = $this->createRequest(
    method: 'POST',
    attributes: ['tree' => $this->tree],
)->withUploadedFiles(['client_file' => $uploadedFile]);
```

**Fehlerfall: UPLOAD_ERR_NO_FILE**

```php
$uploadedFile = new UploadedFile(
    new Stream('php://temp', 'rw'),
    0,
    UPLOAD_ERR_NO_FILE,
    '',
    ''
);
```

### i.3 Session-State-Einschrankungen

#### LoginAction: $_COOKIE-Problem

`LoginAction::doLogin()` pruft als erste Guard: `if ($_COOKIE === []) { throw Exception('cookies disabled'); }`. In PHP CLI-Tests ist `$_COOKIE` immer ein leeres Array -- diese Prüfung schlagt daher immer fehl.

**Testbare Pfade (Fehlerpfade):**
- User nicht gefunden → Exception
- Falsches Passwort → Exception
- E-Mail nicht verifiziert → Exception
- Account nicht genehmigt → Exception

Diese Pfade werden alle aufgerufen, bevor `Auth::login()` erreicht wird, und sind damit unabhangig vom `$_COOKIE`-Problem.

**Nicht testbar (ohne SUT-Anderung):** Der Happy-Path von `LoginAction` (erfolgreicher Login).

#### Masquerade: Auth::login() schreibt Session

`Masquerade::handle()` ruft `Auth::login($user)` auf -- dies ist in Tests testbar (Session ist PHP-Array). Ausserdem schreibt es `Session::put('masquerade', '1')`. Beide Schritte sind verifizierbar.

Guards:
- User-ID nicht gefunden → `HttpNotFoundException`
- Gleiche User-ID wie current user → kein `Auth::login()` Aufruf (Kurzschluss)

### i.4 Middleware-Pipeline-Testing (PSR-15)

Middleware-Klassen implementieren `MiddlewareInterface::process()`. Der Test ruft die Middleware isoliert mit einem Mock-Handler auf:

```php
use Psr\Http\Server\RequestHandlerInterface;

$handler = $this->createMock(RequestHandlerInterface::class);
$handler->method('handle')->willReturn(new Response(200, [], 'OK'));

$middleware = new SomeMiddleware(/* gemockte Dependencies */);
$response = $middleware->process($request, $handler);
self::assertSame(200, $response->getStatusCode());
```

**Request-Attribut-Verifikation:** Middleware reichert oft den Request mit Attributen an. Da der Handler ein Mock ist, wird der modifizierte Request über einen Callback geprüft:

```php
$handler->expects($this->once())
    ->method('handle')
    ->with($this->callback(function (ServerRequestInterface $request) {
        return $request->getAttribute('some_attribute') === 'expected_value';
    }))
    ->willReturn(new Response(200));
```

**Handler-Nicht-Aufgerufen-Assertion:** Wenn die Middleware unter bestimmten Bedingungen den Handler nicht aufruft (z. B. Redirect auf Alternativ-Seite, Fehler-Response):

```php
$handler->expects($this->never())->method('handle');
$response = $middleware->process($request, $handler);
// Middleware antwortet selbst, ohne den nachgelagerten Handler zu rufen
```

### i.5 CLI-Command-Testing (Symfony Console)

**Grundmuster — CommandTester:**

```php
use Symfony\Component\Console\Tester\CommandTester;

$command = new SomeCommand(/* Dependencies */);
$tester = new CommandTester($command);
$tester->execute(['--option' => 'value']);

self::assertSame(Command::SUCCESS, $tester->getStatusCode());
self::assertStringContainsString('erwartete ausgabe', $tester->getDisplay());
```

**Commands ohne Constructor-DI:** Einige Commands haben keine Konstruktor-Injection und nutzen statische Klassen oder globale Konfiguration direkt. Diese müssen als Integration-Tests im Container ausgeführt werden, da Mocking nicht möglich ist.

**Format-Output-Verification** für Commands mit `--format`-Option:

```php
// Table-Format
$tester->execute(['--format' => 'table']);
self::assertStringContainsString('| ID |', $tester->getDisplay());

// JSON-Format
$tester->execute(['--format' => 'json']);
$data = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
self::assertIsArray($data);

// CSV-Format
$tester->execute(['--format' => 'csv']);
$lines = explode("\n", trim($tester->getDisplay()));
self::assertStringContainsString(',', $lines[0]);
```

**Datei-I/O:** Commands, die Dateien schreiben, benötigen Aufräumlogik in `tearDown()`, um Seiteneffekte auf andere Tests zu vermeiden.

### i.6 Path-Security-Testing (Directory-Traversal-Schutz)

Handler, die Dateipfade aus User-Input verarbeiten, müssen gegen Directory-Traversal geschützt sein. Der Test prüft, dass Pfade außerhalb des erlaubten Verzeichnisses eine Exception auslösen:

```php
// Pfad innerhalb des erlaubten Verzeichnisses → OK
$request = $this->createRequest(query: ['path' => 'subdir/file.txt'], ...);
$response = $handler->handle($request);
self::assertSame(200, $response->getStatusCode());

// Pfad mit Directory-Traversal → Exception
$this->expectException(HttpBadRequestException::class);
$request = $this->createRequest(query: ['path' => '../../../etc/passwd'], ...);
$handler->handle($request);
```

### i.7 FAILURE_PIN — Default-Pattern für dokumentierte Upstream-Bugs

Default für dieses Repo: testbar reproduzierbare Upstream-Bugs werden weder per `markTestSkipped` versteckt noch invertiert gepinnt (Test grün solange Bug aktiv), sondern als echtes Failure gepinnt — Test asserted das **Soll-Verhalten** und ist rot, solange Upstream nicht fixt.

```php
$this->assertSame(
    $expected,
    $actual,
    '<SUT::method>: <Soll-Verhalten>. Aktuell <Ist-Verhalten> — Upstream-Bug in <Ort> (<nötiger Fix>).'
);
```

Failure-Message benennt SUT, Defekt-Ort und nötigen Fix. Konsequenz: `make test-integration` exitet ≠ 0 — CI-Gate auf erwarteten Failure-Count kalibrieren, nicht auf "0 Failures". Abgrenzung zu §11: nicht reproduzierbare Defekte (DNS, Race, Filesystem-Permissions) bleiben dort.

---

## 6 Aufwandskategorien

| Kategorie | Kriterien | Typische Massnahme |
|---|---|---|
| **Niedrig** | Neue Testmethoden im bestehenden Test; kein neuer Setup-Aufwand; keine neuen Dependencies | 1--3 Testmethoden erganzen |
| **Mittel** | Neue Test-Klasse notig; Setup-Code erweitern; ggf. neue Fixtures | Neue Klasse + erweitertes Fixture |
| **Hoch** | Neue Dev-Dependencies; Test-Infrastruktur (VFS, Mock-DNS); SUT-Anderung wunschenswert | Neue Bibliothek evaluieren + integrieren |

---

## 7 Arbeitsablauf je Feature (5 Phasen)

Jedes Feature (identifiziert durch eine Referenz-ID) durchlauft die folgenden Phasen -- sequenziell pro Feature. P3 (Coding) kann fur **unterschiedliche Features** parallel laufen, solange die Testklassen sich nicht uberschneiden.

| Phase | Inhalt | Abnahmekriterium |
|---|---|---|
| **P1: Konsistenzcheck** | (a) Upstream-SUT lesen (`./upstream/webtrees/app/...`); (b) aktuellen Test-Code lesen; (c) Abgleich mit Feature-Detailkonzept | Konzept stimmt mit Code-Ist uberein. Falls Diskrepanz: Konzept wird korrigiert, nicht der SUT. |
| **P2: Soll-Design** | EP/BVA-Matrix aus Detailkonzept finalisieren; konkrete Testmethoden-Namen und DataProvider-Struktur festlegen; Fixture-Bedarf identifizieren; Mocking-Strategie festlegen | Testmethoden-Liste vollstandig, Implementierungsreihenfolge bekannt |
| **P3: Test-Coding** | Testmethoden schreiben; ggf. neue Testklasse anlegen; DataProvider implementieren; Fixtures vorbereiten | Code syntaktisch korrekt; alle geplanten Testmethoden vorhanden; ggf. neue Klasse in phpunit.xml eingetragen |
| **P4: Ausfuhrung + Fixing** | Einzelnen Test/Klasse isoliert ausfuhren; Fehler analysieren und im **Testcode** beheben (nicht im SUT); iterieren bis grun | Alle neuen Tests grun; keine Regressionen in der gesamten Test-Suite |
| **P5: Dokumentation** | Dokumentations-Diff-Vorschlage umsetzen: Feature-Matrix (`docs/tds_conditions_ref.md`), Abdeckungsmatrix (`docs/tds_coverage_ref.md`), Ratchet-Werte (`docs/tp_ratchet_spec.md`), Changelog | Dokumentation konsistent mit neuem Test-Zustand |

**Statusaktualisierung:** Unmittelbar nach Abschluss jeder Phase im Feature-Detailkonzept eintragen -- nicht akkumuliert am Ende.

### P1: Konsistenzcheck -- Checkliste

Pro Feature vor Beginn von P2:

1. **SUT lesen:** `./upstream/webtrees/app/<Pfad>` -- hat sich die Klasse seit der Analyse verandert? Neue Branches? Geloschte Branches?
2. **Test-Ist lesen:** Aktuelle Testklasse lesen -- welche Methoden existieren bereits?
3. **Detailkonzept prüfen:** Stimmt der Branch-Katalog mit dem aktuellen SUT uberein?
4. **Ggf. Detailkonzept korrigieren:** Falls Diskrepanz → Konzept aktualisieren, dann P1 als erledigt markieren.
5. **Fixture-Check:** Welche Demo-Daten sind verfugbar? XREF-IDs aus Fixture-Datei ableiten.

### P5: Dokumentation -- Was zu aktualisieren ist

Nach Abschluss jedes Features:

| Abschnitt | Anderung |
|---|---|
| **Feature-Matrix** (`docs/tds_conditions_ref.md`) | Test-Anzahl aktualisieren; Qualitatsstufe von *(strukturbasiert)* auf *(spezifikationsbasiert)* oder *(spezifikationsbasiert+strukturbasiert)* anpassen |
| **Testentwurfsverfahren** (in Feature-Matrix-Dokument) | Zeile fur strukturbasiertes CRAP-Testen: Verweis auf neue EP/BVA-Tests erganzen, oder neue Zeile fur betroffene Referenz-IDs |
| **Abdeckungsmatrix** (`docs/tds_coverage_ref.md`) | Testklassen-Namen, Test-Anzahl, ggf. neue Klassen-Namen nach Aufsplittung |
| **Ratchet-Werte** (`docs/tp_ratchet_spec.md`) | Neuen Coverage-Stand eintragen (falls vorhanden) |
| **Changelog** | Neuen Eintrag mit Datum und Kurzbeschreibung anhangen |

---

## 8 Parallelisierungsstrategie

```
Analyse → Implementierungsplan
              |
  Feature/AP-1 [Skelett/P3]   Feature/AP-2 [Skelett/P3]   ...parallel...
              |
  Feature/AP-1 [Ausführung/P4] → Feature/AP-2 [Ausführung/P4] → ...sequenziell...
              |
  Abschluss (Voll-Lauf, Ratchet, Commit)
```

Alle Features/APs arbeiten auf demselben initialen Test-Snapshot.
P3 (Test-Coding) kann für unterschiedliche Features parallel laufen, solange die Testklassen sich nicht überschneiden.
P4 (Ausführung) ist strikt sequenziell — immer nur ein PHPUnit-Prozess gleichzeitig.

---

## 9 Pflicht-Constraints

| Constraint | Quelle |
|---|---|
| `pgrep -a phpunit` vor jedem neuen Testlauf | CLAUDE.md |
| Lang laufende Tests: `run_in_background: true`, kein `timeout` | CLAUDE.md |
| `make up` (nie `make _compose-up`) | CLAUDE.md |
| Alle neuen Tests in `layer3-integration/tests/` | CLAUDE.md |
| Konstruktor-Verifikation vor jedem PHP-Skelett | Prompt-Erfahrung |
| Kein Commit vor allen Features/APs + `make test-integration` Exit 0 | Prompt-Erfahrung |
| GPG-signierte Commits | CLAUDE.md |
| Kein SUT-Code ändern | Prompt-Erfahrung |
| Bestehende Batch-Tests erhalten (als Smoke-Tests) | Prompt-Erfahrung |
| Neue Dev-Dependencies erst evaluieren und Benutzer-Zustimmung einholen | Prompt-Erfahrung |

Vollständige Stack-Regeln: `CLAUDE.md`

### Container-Pfade

```bash
# PHPUnit-Konfiguration im Container:
/tests/layer3-integration/phpunit-integration.xml

# Testdateien im Container:
/tests/layer3-integration/tests/MeineTestklasse.php

# Einzeltest-Befehl:
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'MeineTestklasse' \
  /tests/layer3-integration/tests/MeineTestklasse.php
```

---

## 10 Abschluss (Voll-Lauf, Ratchet, Konsistenzprüfung, Commit)

Voraussetzung: Alle Features/APs im Implementierungsplan sind abgeschlossen.

### 10.1 Voll-Lauf

```bash
# Sicherstellen, dass kein Testprozess laeuft
pgrep -a phpunit && echo "Warten oder per kill beenden"

# run_in_background: true -- kein timeout-Parameter
make test-integration
```

Auf die Fertigmeldung warten.

**Erwartetes Ergebnis:** Exit 0, alle Tests gruen.

Falls Tests rot: Fehler analysieren, gezielt fixen, Voll-Lauf wiederholen.
Kein Commit bei rotem Voll-Lauf.

### 10.2 Ratchet-Werte aktualisieren

Aus der frischen `artifacts/layer3/coverage.xml` die aktuellen Werte lesen:
- Anweisungsueberdeckung: X.X% (covered / total)
- Methodenueberdeckung: X.X% (covered / total)
- Testanzahl: N Tests, M Assertions, N Testklassen

In `docs/tp_ratchet_spec.md` aktualisieren:

- Datum auf heute setzen
- Baseline-Verweis auf vorherigen Stand aktualisieren
- Neue Werte eintragen (Anweisungsueberdeckung, Methodenueberdeckung, Testanzahl)
- Pakete mit >50%-Coverage und 0%-Coverage pruefen -- wenn neue Pakete hinzugekommen

Falls FM-Tabelle oder Abdeckungsmatrix durch neue Tests erweitert wurde:
entsprechende Zeilen aktualisieren (Diff-Vorschlaege aus der Analyse
als Vorlage):
- Feature-Matrix-IDs: `docs/tds_conditions_ref.md`
- Abdeckungsmatrix: `docs/tds_coverage_ref.md`

### 10.3 Dokumenten-Konsistenzpruefung

#### CLAUDE.md

Pruefen: Sind Stack-Regeln, Make-Targets und die Layer-Tabelle noch aktuell?
Neues Target `crap-report` ist bereits eingetragen -- kein weiterer Handlungsbedarf
sofern kein anderer Stack-Befehl geaendert wurde.

#### README.md

Pruefen: Einstieg, Schnellstart, Teststufen-Tabelle, Container-Liste.
Typisch: kein Handlungsbedarf (keine strukturellen Aenderungen pro Coverage-Iteration).

#### docs/tp_ratchet_spec.md

Bereits in Abschnitt 10.2 aktualisiert. Abschliessend pruefen:
- Endekriterien (Ratchet-Basis) korrekt?
- Versions-Footer am Ende der Datei auf heute gesetzt?

#### docs/tds_conditions_ref.md und docs/tds_coverage_ref.md

Pruefen:
- FM-Abdeckungsmatrix vollstaendig?
- Neue Feature-IDs korrekt eingetragen?

### 10.4 Commit

```bash
# Aenderungen pruefen
git status
git diff --staged

# Commit (GPG-signiert)
# Commit-Nachricht enthaelt alle umgesetzten Features/APs
# und die neuen Coverage-Werte
```

Commit-Inhalt:
- `layer3-integration/tests/*.php` -- neue/erweiterte Testdateien
- `docs/coverage-runs/*_full_analysis.md` -- neue Analyse
- `docs/tp_ratchet_spec.md` -- aktualisierter Ratchet-Stand
- `docs/coverage-runs/ap-*.md` -- AP-Dateien dieser Iteration (Status abgeschlossen)

Beispiel-Commit-Nachricht:

```
test(layer3): Coverage-Erweiterung Voll-Lauf -- AP1-APn abgeschlossen

N Tests, M Assertions (vorher: N' Tests, M' Assertions).
Anweisungsueberdeckung: X.X% (vorher: Y.Y%).
Methodenueberdeckung: X.X% (vorher: Y.Y%).

AP1: KlasseA -- methodA
AP2: KlasseB -- methodB
...

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
```

---

## 11 Nicht verbesserbar (dauerhaft ausgeklammert)

Diese Liste enthält Branches, deren Defekt-Verhalten ohne SUT-Änderung oder externe Infrastruktur **nicht einmal reproduzierbar** ist — daher weder per FAILURE_PIN (§5 i.7) noch per Standard-Test abdeckbar. Für dokumentierte Upstream-Bugs, die testbar reproduzierbar sind, gilt FAILURE_PIN als Default und nicht dieser Abschnitt.

Die folgenden Branches konnen mit vertretbarem Aufwand nicht auf eine hohere Qualitatsstufe angehoben werden, ohne externe Infrastruktur oder SUT-Anderungen:

| SUT | Branch | Grund |
|---|---|---|
| `BadBotBlocker` | DNS Reverse+Forward-Lookup (ROBOT_REV_FWD_DNS) | Direkte `gethostbyaddr()`/`gethostbyname()` Aufrufe; kein DI |
| `BadBotBlocker` | DNS Reverse-Only-Lookup (ROBOT_REV_ONLY_DNS) | Gleicher Grund |
| `GedcomLoad` | Race Condition / `$n===0` (Branch C1b) | Echte Parallelitat im MySQL-Container nicht reproduzierbar |
| `TreeExport` | `stream_get_contents()` Fehler (Branch D1) | PHP-Ressource-Fehler kaum injizierbar ohne SUT-Anderung |
| `MediaFileService` | Dateirechte-Fehler | Filesystem-Permissions in Podman-Container kaum steuerbar |
| `RenumberTreeAction` | Timeout mid-import (B4) | TimeoutService muss mockbar sein; aktuell nicht per DI austauschbar |
| `LoginAction::doLogin()` | Happy-Path (erfolgreicher Login) | `$_COOKIE === []` Guard in PHP CLI immer true; kein DI; SUT-Anderung notig |
| `UpgradeWizardPage` | Download/Unzip-Schritte | Netzwerkzugriff (latestVersion() ruft GitHub API auf); kein Mock ohne SUT-Anderung |
| `RegisterAction` | E-Mail-Versand (EmailService) | SMTP nicht konfiguriert in Test-Container; EmailService kein Mock per DI |
| `AdminMediaFileDownload` | Filesystem-Fehler (unreadable file) | Dateisystem-Permissions im Container nicht steuerbar |
| `SitePreferencesAction` | `is_dir()` / `is_writable()` Fehlerfall | Dateisystem-State im Container nicht steuerbar ohne SUT-Anderung |
