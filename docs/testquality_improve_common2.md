<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — Übergreifende Konzepte (Serie 2)

Dieses Dokument ergänzt `testquality_improve_common.md` und beschreibt übergreifende Methoden und Muster speziell für die Features der zweiten Serie (E01–E08, A01–A11, G30, S52–S53, P38–P41, SEC-UTL01, K01–K02).

---

## 1. Auth-Kontext in Komponentenintegrationstests (E, A, P38–P41)

### Grundmuster: createAndLoginAdmin()

Das Muster stammt aus `UserEditActionIntegrationTest` und `TreeOperationsTest` — es ist die Standardlösung für alle Handler, die einen authentifizierten Admin benötigen:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->admin = $this->createAndLoginAdmin();
    // Auth::isAdmin() == true, Auth::id() != null ab jetzt
}
```

`createAndLoginAdmin()` (definiert in `MysqlTestCase`) führt folgende Schritte aus:

1. Erzeugt einen neuen User via `$this->userService->create(...)`.
2. Setzt `PREF_IS_ADMINISTRATOR = '1'` via `$user->setPreference(...)`.
3. Ruft `Auth::login($user)` auf — dies schreibt `Session::put('wt_user', $user->id())`.

Da `Session` in webtrees intern ein einfaches PHP-Array ist (kein Browser-Cookie), ist `Auth::login()` in PHPUnit-Tests voll funktionsfähig. Der Auth-Zustand gilt für die gesamte Testmethode.

### Zugriff auf Tree-Kontext

Für Handler die `$tree = Validator::attributes($request)->tree()` aufrufen, muss der Tree im Request-Attribut gesetzt sein:

```php
$request = $this->createRequest(
    attributes: ['tree' => $this->tree, 'user' => $this->admin],
);
```

`createTreeWithGedcom()` in `MysqlTestCase` erstellt einen echten Baum mit Daten aus einer GEDCOM-Fixture und macht ihn über `$this->tree` verfügbar.

### Nicht-Admin-Benutzer für Tests mit Privileg-Guards

```php
$member = $this->userService->create('member-user', 'Member', 'member@test.local', 'pw');
Auth::login($member);
// Auth::isAdmin() == false, Auth::isMember($tree) variiert je nach canedit-Präferenz
```

---

## 2. PSR-7 UploadedFile für HTTP-Datei-Uploads (G30, A02)

### Das Problem

Mehrere Handler (z. B. `ImportGedcomAction`, `UploadMediaAction`) lesen Upload-Dateien über `$request->getUploadedFiles()`. In PHPUnit-Tests ist `$_FILES` leer — es muss ein PSR-7-konformes `UploadedFileInterface`-Objekt in den Request injiziert werden.

### Lösung: Laminas\Diactoros\UploadedFile

webtrees verwendet Laminas Diactoros als PSR-7-Implementierung. Ein Upload kann so simuliert werden:

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

### Fehlerfall: UPLOAD_ERR_NO_FILE

```php
$uploadedFile = new UploadedFile(
    new Stream('php://temp', 'rw'),
    0,
    UPLOAD_ERR_NO_FILE,
    '',
    ''
);
```

### Wichtig für G30 (UploadMediaAction)

`UploadMediaAction` schreibt das hochgeladene File via `$data_filesystem->writeStream()` (League Flysystem). Das Filesystem-Objekt kommt aus `Registry::filesystem()->data()` — es ist **nicht per DI injizierbar**. Im Test-Stack ist es das echte Filesystem des Containers. Für die Upload-Tests muss daher der Container laufen und das Datenverzeichnis beschreibbar sein.

---

## 3. Batch-Handler-Strategie für große Feature-Gruppen (E01, E05, A05)

### Entscheidungsregel: Smoke-Test vs. EP-Test

| Kriterium | Smoke-Test reicht | EP nötig |
|---|---|---|
| Handler ist ein reiner GET-View (kein Schreib-Zustand) | ja | nein |
| Handler hat Guards (null-Check, Fehlerformat, Duplikat) | nein | ja |
| Handler schreibt in DB / Filesystem | nein | ja |
| Handler-Logik ist trivial (gibt view() zurück) | ja | nein |
| Handler-Gruppe hat > 10 Handler | Smoke für alle; EP für 2–3 kritische | — |

### E01: Individuum-/Familien-/Record-Ansichts-Handler (~14 Handler)

Strategie: Batch-Smoke (GET → 200) für alle Ansichts-Handler in einer neuen Klasse `IndividualViewHandlerBatchTest`. Für die kritischen 2–3 (die Auth-Guards haben oder Redirect-Logik) volle EP-Matrix.

### E05: Autocomplete-/TomSelect-Handler (~9 Handler)

`TomSelectIndividual`, `TomSelectFamily` etc. sind AJAX-Endpoints. Strategie: `AutoCompleteIntegrationTest` erweitern um TomSelect-Handler. Smoke-Test: GET → 200, JSON-Ausgabe. EP für den "XREF direkt" vs. "Namenssuche"-Branch.

### A05: Admin-Handler-Gruppe (~46 Handler)

Sehr groß. Strategie:
1. Bestehende `RequestHandlerBatchA/B` als Basis-Smoke-Tests behalten.
2. Für die 5–8 wichtigsten Admin-Handler (Create/Delete/Merge Trees, Masquerade, Import/Export GEDCOM) eigene EP-Tests in neuen Klassen.
3. Reine View-Handler (ManageTrees GET, SitePreferencesPage GET, ModulesAllPage GET) bleiben im Batch.

---

## 4. Session-State-Einschränkungen (P39, A11 Masquerade)

### LoginAction: $_COOKIE-Problem

`LoginAction::doLogin()` prüft als erste Guard: `if ($_COOKIE === []) { throw Exception('cookies disabled'); }`. In PHP CLI-Tests ist `$_COOKIE` immer ein leeres Array — diese Prüfung schlägt daher immer fehl.

**Konsequenz:** Der Happy-Path von `LoginAction` (erfolgreicher Login) ist in PHPUnit ohne Modifikation von `$_COOKIE` **nicht testbar**.

**Testbare Pfade (Fehlerpfade):**
- User nicht gefunden → Exception
- Falsches Passwort → Exception
- E-Mail nicht verifiziert → Exception
- Account nicht genehmigt → Exception

Diese Pfade werden alle aufgerufen, bevor `Auth::login()` erreicht wird, und sind damit unabhängig vom `$_COOKIE`-Problem.

**Begründung für EXCLUDED (P39 Happy-Path):** Kein vertretbarer Weg, `$_COOKIE` in PHPUnit-CLI-Kontext zu manipulieren ohne SUT-Änderung. Die Fehlerpfade können getestet werden und liefern ausreichend Coverage für die Guard-Logik.

### Masquerade: Auth::login() schreibt Session

`Masquerade::handle()` ruft `Auth::login($user)` auf — dies ist in Tests testbar (Session ist PHP-Array). Außerdem schreibt es `Session::put('masquerade', '1')`. Beide Schritte sind verifizierbar.

Guards:
- User-ID nicht gefunden → `HttpNotFoundException`
- Gleiche User-ID wie current user → kein `Auth::login()` Aufruf (Kurzschluss)

---

## 5. Neue dauerhafte Ausschluss-Kandidaten

| SUT | Branch | Grund |
|---|---|---|
| `LoginAction::doLogin()` | Happy-Path (erfolgreicher Login) | `$_COOKIE === []` Guard in PHP CLI immer true; kein DI; SUT-Änderung nötig |
| `UpgradeWizardPage` | Download/Unzip-Schritte | Netzwerkzugriff (latestVersion() ruft GitHub API auf); kein Mock ohne SUT-Änderung |
| `RegisterAction` | E-Mail-Versand (EmailService) | SMTP nicht konfiguriert in Test-Container; EmailService kein Mock per DI |
| `AdminMediaFileDownload` | Filesystem-Fehler (unreadable file) | Dateisystem-Permissions im Container nicht steuerbar |
| `SitePreferencesAction` | `is_dir()` / `is_writable()` Fehlerfall | Dateisystem-State im Container nicht steuerbar ohne SUT-Änderung |
