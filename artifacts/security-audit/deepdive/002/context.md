<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-002 — D1 Context

**Task:** Missing extension blocklist / folder allowlist in
`MediaFileService::uploadFile()` — inconsistent with its sibling handler
`UploadMediaAction::handle()`.

**Generated:** 2026-04-09
**Codebase-Stand:** `upstream/webtrees` @ upstream main (volatile scratch clone)
**Spin-off aus:** SEC-AUDIT-001 D1/D2, see
`artifacts/security-audit/deepdive/001/context.md`.

## Gap-Zusammenfassung (TL;DR)

Webtrees bietet **zwei parallele Upload-Pipelines**, die beide in denselben
physikalischen Pfad unter `data/media/<tree>/…` schreiben:

| Pipeline | Handler | Guards |
|---|---|---|
| **A** — "Bulk upload to media folder" | `UploadMediaAction::handle()` | Folder-Allowlist `$all_folders->contains($folder)`, Colon-Blocklist `/([:])/`, **Extension-Blocklist** `/(\.(php\|pl\|cgi\|bash\|sh\|bat\|exe\|com\|htm\|html\|shtml))$/i`, File-exists-Check |
| **B** — "Create/attach media object" | `MediaFileService::uploadFile()` via `CreateMediaObjectAction` und `AddMediaFileAction` | **keiner** — schreibt direkt `$tree->mediaFilesystem()->writeStream($folder . $file, …)` |

Pipeline **B** ist für die Editor-Rolle erreichbar (gleich wie Pipeline A) und
akzeptiert ohne jede Prüfung:
- beliebige Endungen (`.htm`, `.html`, `.shtml`, `.phtml`, `.php`, `.htaccess`, `.exe`, …),
- beliebige, frei wählbare Zielordner innerhalb der Tree-Mediafolder-Wurzel.

## Rollen / Erreichbarkeit

Beide Pipelines sind über Routing-Middleware auf **Editor und höher**
beschränkt (`AuthEditor`). Kein Visitor- oder Member-Zugriff. Damit ist dies
**kein Visitor-Pfad** — der Exploit braucht mindestens Editor-Credentials.

Der Wert eines Editor-Accounts in webtrees ist unterschiedlich:
- In Multi-User-Installationen sind Editoren vertraut (vom Tree-Manager bestätigt).
- In "open community" Trees kann sich jeder registrieren und die Editor-Rolle
  per Manager/Moderator-Override erhalten.
- In "private family" Trees sind Editoren eine kleine Gruppe Familie/Freunde.

**Impact-Einschätzung:** medium-low (defense-in-depth). Kein Visitor-Pfad, kein
Sandbox-Escape. Aber eine klare Symmetrie-Lücke: Pipeline A ist gehärtet,
Pipeline B ist es nicht. Ein Editor, der gegen A blockiert wird, kann via B
dasselbe Ziel erreichen.

## Betroffene Dateien (Quelle)

### `app/Services/MediaFileService.php` (Sink) — lines 126–195

```php
public function uploadFile(ServerRequestInterface $request): string
{
    $tree          = Validator::attributes($request)->tree();
    $file_location = Validator::parsedBody($request)->string('file_location');

    switch ($file_location) {
        case 'url':
            …

        case 'unused':
            …

        case 'upload':
            $folder   = Validator::parsedBody($request)->string('folder');
            $auto     = Validator::parsedBody($request)->string('auto');
            $new_file = Validator::parsedBody($request)->string('new_file');

            $uploaded_file = $request->getUploadedFiles()['file'] ?? null;

            if ($uploaded_file === null || $uploaded_file->getError() !== UPLOAD_ERR_OK) {
                throw new FileUploadException($uploaded_file);
            }

            // The filename
            $new_file = strtr($new_file, ['\\' => '/']);
            if ($new_file !== '' && !str_contains($new_file, '/')) {
                $file = $new_file;
            } else {
                $file = $uploaded_file->getClientFilename();
            }

            // The folder
            $folder = strtr($folder, ['\\' => '/']);
            $folder = trim($folder, '/');
            if ($folder !== '') {
                $folder .= '/';
            }

            // Generate a unique name for the file?
            if ($auto === '1' || $tree->mediaFilesystem()->fileExists($folder . $file)) {
                $folder    = '';
                $extension = pathinfo($uploaded_file->getClientFilename(), PATHINFO_EXTENSION);
                $file      = sha1((string) $uploaded_file->getStream()) . '.' . $extension;
            }

            try {
                $tree->mediaFilesystem()->writeStream($folder . $file, $uploaded_file->getStream()->detach());
                return $folder . $file;
            } catch (RuntimeException | InvalidArgumentException) {
                FlashMessages::addMessage(I18N::translate('There was an error uploading your file.'));
                return '';
            }
    }

    return '';
}
```

**Sink:** `$tree->mediaFilesystem()->writeStream(…)` schreibt direkt auf das
Tree-Media-Filesystem (Flysystem-Local, rooted unter `data/media/<tree>/`).
**Keine** Endungs-, Inhalts-, oder Ordnerprüfung.

Selbst die `$auto === '1'`-Fallback-Logik (die bei Namenskonflikten den Namen
auf `sha1(stream) . '.' . $extension` umschreibt) behält die Originalendung bei
— ein `.htm`-Upload bleibt ein `.htm`-File.

### `app/Http/RequestHandlers/UploadMediaAction.php` (Referenz) — lines 55–125

```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    $data_filesystem = Registry::filesystem()->data();
    $all_folders     = $this->media_file_service->allMediaFolders($data_filesystem);

    foreach ($request->getUploadedFiles() as $key => $uploaded_file) {
        …
        // Validate the folder
        if (!$all_folders->contains($folder)) {
            break;
        }

        // Validate the filename.
        $filename = str_replace('\\', '/', $filename);
        $filename = trim($filename, '/');

        if (preg_match('/([:])/', $filename, $match)) {
            // Local media files cannot contain certain special characters, especially on MS Windows
            FlashMessages::addMessage(I18N::translate('Filenames are not allowed to contain the character "%s".', $match[1]));
            continue;
        }

        if (preg_match('/(\.(php|pl|cgi|bash|sh|bat|exe|com|htm|html|shtml))$/i', $filename, $match)) {
            // Do not allow obvious script files.
            FlashMessages::addMessage(I18N::translate('Filenames are not allowed to have the extension "%s".', $match[1]));
            continue;
        }
        …
```

**Referenzimplementation.** Dies sind die Checks, die Pipeline A enforciert und
die in Pipeline B fehlen. Der Regex ist trailing-anchored (`$`), matcht also
**nicht** Doppelextensionen vom Stil `payload.php.jpg` — diese Limitierung gilt
aber bereits für Pipeline A und ist nicht Scope dieser Task.

### `app/Http/RequestHandlers/CreateMediaObjectAction.php` (Caller B) — line 58

```php
try {
    $file = $this->media_file_service->uploadFile($request);
} catch (FileUploadException $exception) {
    …
}
```

Kein eigener Check; delegiert komplett an die ungeprüfte `uploadFile()`.

### `app/Http/RequestHandlers/AddMediaFileAction.php` (Caller B) — line 55

```php
$file = $this->media_file_service->uploadFile($request);
```

Ebenfalls ohne Checks. `Auth::checkMediaAccess($media, true)` prüft nur, ob der
User das Medienobjekt bearbeiten darf, nicht den Dateiinhalt.

## Serve-Pfad (für H1)

Nach erfolgreicher Pipeline-B-Upload wird die Datei via `MediaFileDownload::handle()`
an den Browser geliefert:

```
GET /media-{filename} (Visitor-erreichbar, wenn Tree public)
 → ImageFactory::mediaFileResponse($media_file, $watermark, $download)
 → (no watermark / not isImage) → fileResponse(filesystem, path, download)
 → $filesystem->mimeType($path) → returned as Content-Type
 → imageResponse(data, mime_type, filename)
```

`ImageFactory::imageResponse()` setzt **unabhängig vom MIME-Typ** den CSP-Header
`script-src none;frame-src none` (siehe `app/Factories/ImageFactory.php:280-282`):

```php
$response = response($data)
    ->withHeader('content-type', $mime_type)
    ->withHeader('content-security-policy', 'script-src none;frame-src none');
```

**Wichtige Folge:** Ein erfolgreich hochgeladenes `evil.htm` wird mit
`Content-Type: text/html` **und** `CSP: script-src none;frame-src none`
ausgeliefert. Inline-Scripts und Inline-Event-Handler (`onerror=`,
`onclick=`, …) sind damit **blockiert**.

Was **nicht** blockiert ist:
- Rendern des HTML-Body (der Browser rendert `<form>`, `<a>`, `<img>`, Text, …)
- CSS-Injection-Varianten (z.B. `@import url(…)`)
- Redirects via `<meta http-equiv="refresh">`
- Phishing via eingebettete Formulare, Links, Fake-Login-UIs
- Arbitrary HTML-Inhalte unter dem vertrauten Origin der webtrees-Instanz

Damit reduziert sich der primäre XSS-Vektor (JS-Execution) auf
**HTML-Injection / Stored Phishing** — immer noch ein legitimer Befund, aber
nicht mehr "Stored XSS with JS execution".

## Sibling-Routen, die ebenfalls `text/html` ausliefern

Zur Vollständigkeit: `isImage()` (in `MediaFile.php`) entscheidet, ob ein
File als Bild behandelt wird (und Watermarking durchläuft). Für `.htm` gibt
`isImage()` false zurück, also führt `mediaFileResponse` den direkten
`fileResponse`-Pfad aus — und dort steht der CSP-Header unbedingt.

## Beziehung zu SEC-AUDIT-001

SEC-AUDIT-001 hat den spezifischen SVG-XSS-Vektor im **Lese**-Pfad
(`imageResponse()` → `svgContainsActiveContent()`-DOM-Walker) geschlossen.
SEC-AUDIT-002 adressiert den **Schreib**-Pfad: warum werden gefährliche
Endungen überhaupt akzeptiert? SEC-AUDIT-001 und SEC-AUDIT-002 sind
komplementär — SEC-AUDIT-001 härtet den Guard am Ausgang, SEC-AUDIT-002 härtet
den Guard am Eingang.

## Driver-Kontext-Felder

```yaml
entry_points:
  - POST /create-media-object -> CreateMediaObjectAction (Editor)
  - POST /tree/{tree}/add-media-file/{xref} -> AddMediaFileAction (Editor)
sink:
  function: Fisharebest\Webtrees\Services\MediaFileService::uploadFile
  file: app/Services/MediaFileService.php
  lines: 126-195
  sink_call: $tree->mediaFilesystem()->writeStream($folder . $file, $uploaded_file->getStream()->detach())
reference_implementation:
  function: Fisharebest\Webtrees\Http\RequestHandlers\UploadMediaAction::handle
  file: app/Http/RequestHandlers/UploadMediaAction.php
  lines: 55-125
auth_requirement: editor
reachability: authenticated (editor+)
verticals:
  primary: V4_xss (HTML injection / stored phishing, JS blocked by CSP)
  secondary: V9_arbitrary_file_write (innerhalb Tree-Media-Sandbox)
severity_estimate: medium-low (defense-in-depth inconsistency)
```
