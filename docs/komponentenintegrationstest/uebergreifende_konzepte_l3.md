<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Übergreifende Konzepte — Komponentenintegrationstest-Iteration L3

**Erstellt:** 2026-04-12
**Basis:** [`testcov_komponentenintegration_systemtests_delta.md`](../testcov_komponentenintegration_systemtests_delta.md),
[`wf_code-to-test_guide.md`](../wf_code-to-test_guide.md)

Dieses Dokument beschreibt **neue, iterationsspezifische Konzepte** für die
L3-Komponentenintegrationstest-Erweiterung (31 Features). Bereits dokumentierte
Basis-Methodiken (EP/BVA, Mock-Infrastruktur, Auth-Kontext, DataProvider) sind in
[wf_test-iteration_guide.md](../wf_test-iteration_guide.md) beschrieben und werden
hier nur referenziert, nicht wiederholt.

---

## 1 Middleware-Pipeline-Testing

**Betroffene Features:** M03, M06–M21, M23, M25–M28 (20 Features)

**Problem:** Middleware-Klassen implementieren das PSR-15 `MiddlewareInterface` mit
`process(ServerRequestInterface $request, RequestHandlerInterface $handler)`. Sie sind
Pipeline-Stufen ohne eigene Route — der Test muss die Middleware isoliert mit einem
Mock-Handler aufrufen.

### 1.1 Grundmuster: Middleware isoliert testen

```php
use Psr\Http\Server\RequestHandlerInterface;

// Mock-Handler, der eine definierte Response zurückgibt
$handler = $this->createMock(RequestHandlerInterface::class);
$handler->method('handle')
    ->willReturn(new Response(200, [], 'OK'));

// Middleware instantiieren (ggf. mit gemockten Dependencies)
$middleware = new ClientIp();

// process() aufrufen und Response prüfen
$response = $middleware->process($request, $handler);
self::assertSame(200, $response->getStatusCode());
```

**Verifikation:** Prüfe (a) dass `$handler->handle()` aufgerufen wurde, (b) dass
Request-Attribute korrekt gesetzt sind, (c) dass Response-Header korrekt sind.

### 1.2 Request-Attribut-Verifikation

Viele Middleware-Klassen reichern den Request mit Attributen an (z.B. `base_url`,
`client-ip`, `tree`). Da der Handler einen Mock ist, muss der Request **vor dem
Handler-Aufruf** geprüft werden:

```php
$handler = $this->createMock(RequestHandlerInterface::class);
$handler->expects($this->once())
    ->method('handle')
    ->with($this->callback(function (ServerRequestInterface $request) {
        // Request-Attribute prüfen
        return $request->getAttribute('base_url') === 'https://example.com';
    }))
    ->willReturn(new Response(200));
```

### 1.3 Handler-Nicht-Aufgerufen-Assertion

Einige Middleware-Klassen (z.B. ReadConfigIni bei fehlender Config) rufen den
ursprünglichen Handler nicht auf, sondern leiten auf einen Ersatz-Handler um:

```php
$handler = $this->createMock(RequestHandlerInterface::class);
$handler->expects($this->never())->method('handle');

// Middleware sollte den SetupWizard statt des Handlers aufrufen
$response = $middleware->process($request, $handler);
```

### 1.4 Abhängigkeitskategorien

| Kategorie | Middleware-Klassen | Testansatz |
|---|---|---|
| **Nur DI-Dependencies** | M08, M10, M18, M23, M25, M26 | Constructor-Mock, direkt testbar |
| **DI + Validator/Registry** | M07, M09, M11, M12, M13, M14, M21 | DI-Mock + Request-Attribute setzen |
| **DI + Statische Facades** | M06, M17, M27 | DI-Mock + DB/Session-Setup in MysqlTestCase |
| **Globale PHP-Funktionen** | M15, M16, M19, M28 | PhpService-Mock oder php-mock |
| **Externe Klassen-Vererbung** | M03 | Parent-Mock (erbt von `\Middlewares\ClientIp`) |

---

## 2 CLI-Command-Testing

**Betroffene Features:** G31, P42, A12–A16 (7 Features)

**Problem:** CLI-Commands werden über Symfony Console ausgeführt. In L3-Tests
nutzen wir `CommandTester` zur isolierten Ausführung.

### 2.1 Grundmuster: CommandTester

```php
use Symfony\Component\Console\Tester\CommandTester;

$command = new TreeList($this->treeService);
$tester = new CommandTester($command);
$tester->execute(['--format' => 'table']);

self::assertSame(Command::SUCCESS, $tester->getStatusCode());
self::assertStringContainsString('tree-name', $tester->getDisplay());
```

**Referenz:** Bestehende CLI-Tests `TreeExportCommandIntegrationTest.php` und
`UserEditCommandIntegrationTest.php`.

### 2.2 Commands ohne Constructor-DI

Einige Commands (A14/ConfigIni, A15/CompilePoFiles) haben **keine Constructor-Injection**.
Sie verwenden statische Klassen (`DB::connect()`, `Webtrees::CONFIG_FILE`) direkt.

**Testansatz:** Integration-Test im Container — die Commands werden mit echten
Services ausgeführt, da Mocking nicht möglich ist. Die Test-Umgebung (MySQL-Container)
stellt die notwendigen Ressourcen bereit.

### 2.3 Format-Output-Verification

Für Commands mit `--format`-Option (P42/UserList, A16/TreeList):

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
self::assertStringContainsString(',', $lines[0]); // Header-Zeile
```

### 2.4 Datei-I/O in Commands

Commands wie A12/SiteOffline, A13/SiteOnline und A14/ConfigIni schreiben Dateien.
Die Dateipfade sind über `Webtrees::CONFIG_FILE` bzw. `MaintenanceModeService`
definiert.

**Aufräumen:** In `tearDown()` sicherstellen, dass geschriebene Dateien entfernt
werden, um Seiteneffekte auf andere Tests zu vermeiden.

---

## 3 Redirect-Handler-Batch-Testing

**Betroffenes Feature:** S53 (27 Legacy-URL-Redirect-Handler)

**Problem:** ~27 Handler folgen einem einheitlichen Pattern (Query-Param → Record-Lookup
→ 301-Redirect oder 410-Gone). Individuelle Testklassen wären unverhältnismäßig.

### 3.1 DataProvider-Batch-Strategie

```php
#[\PHPUnit\Framework\Attributes\DataProvider('redirectHandlerProvider')]
public function test_legacy_redirect_returns_301(string $handlerClass, array $queryParams): void
{
    // Handler instantiieren und Request bauen
    $handler = Registry::container()->get($handlerClass);
    $request = $this->createRequest(query: $queryParams, attributes: ['tree' => $this->tree]);
    $response = $handler->handle($request);
    self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
    self::assertNotEmpty($response->getHeaderLine('Location'));
}

public static function redirectHandlerProvider(): array
{
    return [
        'individual' => [RedirectIndividualPhp::class, ['pid' => 'I1', 'ged' => 'test']],
        'family'     => [RedirectFamilyPhp::class, ['famid' => 'F1', 'ged' => 'test']],
        // ... weitere Handler
    ];
}
```

### 3.2 Gone-Exception-Tests

Für ungültige XREFs oder nicht existierende Bäume:

```php
#[\PHPUnit\Framework\Attributes\DataProvider('redirectGoneProvider')]
public function test_legacy_redirect_returns_gone_for_invalid_record(
    string $handlerClass, array $queryParams
): void {
    $this->expectException(HttpGoneException::class);
    $handler = Registry::container()->get($handlerClass);
    $request = $this->createRequest(query: $queryParams, attributes: ['tree' => $this->tree]);
    $handler->handle($request);
}
```

### 3.3 Repräsentative Detail-Tests

Für 3–4 komplexere Handler (RedirectModulePhp, RedirectPedigreePhp,
RedirectReportEnginePhp, RedirectCalendarPhp) werden zusätzlich EP-Tests mit
vollständiger Parameter-Analyse erstellt.

---

## 4 Kontakt-/Nachrichten-Handler ohne SMTP

**Betroffene Features:** K01 (Kontaktformular), K02 (Benutzer-Nachrichten)

**Problem:** Die Handler nutzen `EmailService` bzw. `MessageService` für
E-Mail-Versand. Im Test-Container ist kein SMTP konfiguriert.

**Testansatz:**

1. **Formular-Rendering (GET):** Seite laden, Formularfelder prüfen — kein SMTP nötig.
2. **Validierung (POST):** Fehlende Pflichtfelder, ungültige E-Mail, externe Links
   → Redirect mit Fehlermeldung. Kein E-Mail-Versand nötig.
3. **Erfolgsfall (POST):** `MessageService::deliverMessage()` wird gemockt.
   Der Mock gibt `true` zurück → Handler redirectet mit Erfolgsmeldung.
4. **Fehlerfall (POST):** Mock gibt `false` zurück → Handler redirectet mit
   Fehlermeldung.

```php
$messageService = $this->createMock(MessageService::class);
$messageService->method('deliverMessage')->willReturn(true);
// Handler mit gemocktem Service instantiieren
```

**Einschränkung:** Der tatsächliche E-Mail-Versand wird nicht getestet. Die
Integrations-Assertion beschränkt sich auf die Handler-Logik (Validierung,
Redirect-Verhalten, Flash-Messages).

---

## 5 Admin-Media-Handler und Filesystem

**Betroffenes Feature:** A08 (Medienverwaltung Admin)

**Problem:** `AdminMediaFileDownload` prüft Dateipfade gegen `media_folder`.
`FixLevel0MediaAction` manipuliert GEDCOM-Daten (Fact-Updates). `ManageMediaPage`
listet Media-Ordner auf.

### 5.1 Media-Folder-Setup

Die Admin-Media-Handler benötigen:
- Einen Baum mit importierten Media-Records (OBJE-Tags in GEDCOM)
- Existierende Media-Dateien im Container-Filesystem

**Testansatz:** GEDCOM-Fixture mit Media-Referenzen importieren. Die
eigentlichen Mediendateien werden nicht benötigt, da die Handler nur
GEDCOM-Daten und Ordnerstrukturen prüfen (nicht die Dateien selbst lesen).

### 5.2 Path-Security-Tests

`AdminMediaFileDownload` validiert den angeforderten Pfad gegen den
konfigurierten `media_folder`. Directory-Traversal-Versuche müssen
eine `HttpBadRequestException` auslösen:

```php
// Pfad innerhalb des Media-Ordners → OK
$request = $this->createRequest(query: ['path' => 'media/photo.jpg'], ...);
// Pfad außerhalb → Exception
$request = $this->createRequest(query: ['path' => '../../../etc/passwd'], ...);
```

---

## 6 Testbarkeits-Einschränkungen — Middleware

### 6.1 Globale PHP-Funktionen

Middleware-Klassen M15 (ErrorHandler), M19 (CompressResponse) und M28 (EmitResponse)
nutzen globale PHP-Funktionen (`set_error_handler`, `headers_sent`, `ob_get_level`,
`gzencode`). Diese sind in PHPUnit-Tests nicht direkt mockbar.

**Lösungsansatz 1:** `PhpService`-Klasse (bereits in webtrees vorhanden) kapselt
einige dieser Funktionen. Middleware-Klassen, die `PhpService` per DI empfangen
(M19, M28), können den Service mocken.

**Lösungsansatz 2:** Für M15 (ErrorHandler) kann `set_error_handler()` in einem
try/finally-Block getestet werden — der Test löst absichtlich einen PHP-Fehler
aus und prüft, ob die erwartete `ErrorException` geworfen wird.

### 6.2 Session-/Auth-Facades

M06 (UseSession) nutzt statische `Session::start()`, `Auth::user()` und
`Session::get()`. In `MysqlTestCase` ist die Session als PHP-Array initialisiert.
Der Test kann Session-Werte direkt über `Session::put()` setzen.

### 6.3 DB-Transaktionen

M27 (UseTransaction) delegiert an `DB::connection()->transaction()`. Die
Retry-Logik bei Deadlocks liegt innerhalb der DB-Abstraktionsschicht. Ein
echter Deadlock-Test wäre nur mit parallelen Transaktionen möglich.

**Pragmatischer Ansatz:** Testen, dass (a) der Handler innerhalb einer
Transaktion aufgerufen wird, (b) bei Exception ein Rollback stattfindet.
Die Deadlock-Retry-Logik wird als Framework-Verhalten akzeptiert und nicht
explizit getestet.

---

## 7 Empfehlung: Test-Datei-Benennung

| Feature-Gruppe | Testklassen-Name | Grund |
|---|---|---|
| M03 | `ClientIpMiddlewareIntegrationTest` | Einzelne Middleware |
| M06 | `UseSessionMiddlewareIntegrationTest` | Einzelne Middleware |
| M07 | `UseDatabaseMiddlewareIntegrationTest` | Einzelne Middleware |
| ... | `{ClassName}MiddlewareIntegrationTest` | Pattern für alle M-Features |
| G31 | `TreeImportCommandIntegrationTest` | CLI-Command |
| P42 | `UserListCommandIntegrationTest` | CLI-Command |
| A12–A16 | `{CommandName}CommandIntegrationTest` | CLI-Commands |
| K01 | `ContactFormIntegrationTest` | Handler-Gruppe |
| K02 | `UserMessageIntegrationTest` | Handler-Gruppe |
| A08 | `AdminMediaManagementIntegrationTest` | Handler-Gruppe |
| S53 | `LegacyUrlRedirectIntegrationTest` | Batch-Test (DataProvider) |

**Konvention:** Jedes Feature erhält eine eigene Testklasse. Ausnahme: S53
(27 Handler) wird als Batch-Test mit DataProvider in einer Klasse zusammengefasst.
