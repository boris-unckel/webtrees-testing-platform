<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Deep-Dive Context — SEC-AUDIT-001

- **Started:** 2026-04-08 21:18
- **Task file:** `docs/security-audit/tasks/SEC-AUDIT-001_svg_xss_media_upload.md`
- **Primary source file:** `app/Factories/ImageFactory.php` (Zeile 268–286 `imageResponse()`, Zeile 270 SVG-Filter)
- **Contributing source file:** `app/Services/MediaFileService.php` (Zeile 126–195 `uploadFile()`)
- **Routing entry points:**
  - `POST /tree/{tree}/add-media-file/{xref}` → `AddMediaFileAction` (Editor-Route, `AuthEditor` Middleware)
  - `POST /tree/{tree}/create-media-object` → `CreateMediaObjectAction` (Editor-Route)
- **Serving entry points (SVG-Abruf nach Upload):**
  - `GET /media-download/…` → `MediaFileDownload` → `ImageFactory::mediaFileResponse` → `fileResponse` → `imageResponse`
  - `GET /media-thumbnail/…` → `MediaFileThumbnail` → `mediaFileThumbnailResponse` (SVG: Intervention schlägt fehl → `replacementImageResponse`, kein Angriffsvektor)
  - Admin-Pfad: `AdminMediaFileDownload` → ebenfalls `fileResponse → imageResponse`

## D1 — Context-Extraktion

### 1. Upload-Flow (Plant-In)

Relevanter Code in `app/Services/MediaFileService.php` (Zeilen 126–195):

```php
public function uploadFile(ServerRequestInterface $request): string
{
    $tree          = Validator::attributes($request)->tree();
    $file_location = Validator::parsedBody($request)->string('file_location');

    switch ($file_location) {
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
            } catch (...) { ... }
    }
}
```

**Beobachtungen:**
- **Keine Extension-Blocklist**: `.svg`, `.html`, `.php`, `.htaccess` können alle gespeichert werden.
- **Keine Folder-Allowlist**: `$folder` wird nur von Slashes gestrippt, nicht gegen `allMediaFolders()` validiert.
- **Flysystem-Schutz gegen `..`**: `WhitespacePathNormalizer::normalizePath()` wirft `PathTraversalDetected` bei `..`-Sequenzen, die über den Root hinausgehen → direkter Traversal-Ausbruch blockiert.
- **Inkonsistenz zur sibling `UploadMediaAction`** (`app/Http/RequestHandlers/UploadMediaAction.php:79-97`):
  - Folder-Allowlist: `if (!$all_folders->contains($folder)) { break; }`
  - Extension-Blockliste: `preg_match('/\.(php|pl|cgi|bash|sh|bat|exe|com|htm|html|shtml)$/i', $filename)`
  - Diese Schutzmaßnahmen fehlen im parallelen Flow `AddMediaFileAction` / `CreateMediaObjectAction` → `MediaFileService::uploadFile()`.
  - **Hinweis:** Auch `UploadMediaAction`s Regex deckt `.svg` nicht ab — SVG-Uploads sind überall möglich.

### 2. Download-/Serving-Flow (Trigger-Out)

Relevanter Code in `app/Factories/ImageFactory.php` Zeile 268–286:

```php
protected function imageResponse(string $data, string $mime_type, string $filename): ResponseInterface
{
    if ($mime_type === 'image/svg+xml' && str_contains(haystack: $data, needle: '<script')) {
        return $this->replacementImageResponse(text: 'XSS')
            ->withHeader('x-image-exception', 'SVG image blocked due to XSS.');
    }

    // HTML files may contain javascript and iframes, so use content-security-policy to disable them.
    $response = response($data)
        ->withHeader('content-type', $mime_type)
        ->withHeader('content-security-policy', 'script-src none;frame-src none');

    if ($filename === '') {
        return $response;
    }
    ...
}
```

**Zwei Verteidigungsschichten:**
1. **L1 — Fragile Substring-Filter (Zeile 270):**
   - `str_contains($data, '<script')` ist **case-sensitiv** in PHP.
   - Deckt nur Tag-Eröffnungen ab, nicht Event-Handler (`onload`, `onerror`, …) oder `javascript:`-URLs.
   - Trivially bypassbar durch `<SCRIPT>`, `<svg onload="…">`, `<a xlink:href="javascript:…">`.
2. **L2 — Content-Security-Policy (Zeile 278):**
   - `script-src 'none'; frame-src 'none'` — blockiert laut CSP Level 2/3 Spec:
     - `<script>`-Tags (inline + extern)
     - Inline Event-Handler (`onload=""`, etc.)
     - `javascript:`-URLs bei Navigation
   - Gilt für Response-Body unabhängig von L1.
   - **Fehlende Direktiven** (Risiko): kein `default-src`, kein `object-src` (default `*`), kein `base-uri`. Keine dieser Lücken ermöglicht laut Recherche Skript-Ausführung in modernen Browsern.

### 3. Rendering-Pfade im Browser

`MediaFile::displayImage()` (`app/MediaFile.php:176-214`) entscheidet anhand `isImage()`:

```php
private const array SUPPORTED_IMAGE_MIME_TYPES = [
    'image/gif',
    'image/jpeg',
    'image/png',
    'image/webp',
];
```

**Wichtige Beobachtung:** `image/svg+xml` ist **NICHT** in `SUPPORTED_IMAGE_MIME_TYPES`. Daher:
- `isImage()` returniert `false` für SVG.
- `displayImage()` rendert SVG **nicht** als `<img src="…svg">`, sondern als `<a href="…" type="image/svg+xml">` mit Icon-Placeholder.
- Browser-Pfad: User klickt den Link → direkte Navigation zu `/media-download/…` → Browser parst die Response als Top-Level-Dokument mit `image/svg+xml` Content-Type → CSP der Response gilt.
- Alternative: Im Gallery-/Lightbox-Kontext könnte der Link durch JavaScript in einen Modal geöffnet werden. Je nach Modal-Implementation (direkter Link oder iframe) gilt weiterhin die CSP der Response.

**Folge:** SVG-Inhalt wird nie als `<img>` im Sandbox-Modus gerendert, sondern als **Top-Level-Dokument mit CSP** → Browser-eigene `<img>`-Sandbox entfällt als Schutzschicht. L2 (CSP) ist **die** Schutzschicht.

### 4. Auth-Kontext

| Rolle | Zugriff auf Upload (Plant-In) | Zugriff auf Download (Trigger-Out) |
|---|---|---|
| Visitor | nein (AuthEditor) | ja wenn `$media->canShow()` |
| Member | nein (AuthEditor) | ja wenn `$media->canShow()` |
| Editor | **ja** (Plant-In via AddMediaFileAction/CreateMediaObjectAction) | ja |
| Manager | ja | ja |
| Admin | ja | ja |

**Angriffsprofil:**
- Angreifer: Editor (erforderliche Rolle zum Upload).
- Opfer: jeder User, der das Media-Record ansieht (potenziell Visitor, Member, Editor, Manager, Admin).
- Bei Admin-Viewing → Session-Hijack → vollständige Privilege-Escalation **wenn** CSP umgangen wird.

### 5. Trace-Instrumentierung

Kein Trace-Log-Eintrag im Upload-/Serving-Pfad bisher. Für Probe-Runs setze ich gezielt:

```bash
podman-compose exec -e WEBTREES_SECURITY_TRACE=1 webtrees <curl>
```

mit Header `X-Audit-Probe: SEC-AUDIT-001-H1` (bzw. H2/H3). Ziel-Artefakt: `/artifacts/security-trace/SEC-AUDIT-001/*.json`.

### 6. Fazit D1

- **Primäre Verteidigung gegen SVG-XSS ist die CSP `script-src 'none'`** — nicht der L1 Substring-Filter.
- Der L1 Filter ist **fragil und irreführend**: er vermittelt Sicherheit, die nicht existiert. Drei triviale Bypässe existieren (Case, Event-Handler, javascript:-URL).
- **Die Exploit-Frage** lautet daher: Existiert ein Code-Pfad, auf dem SVG-Inhalt **ohne** die CSP ausgeliefert wird, oder eine CSP-Schwäche die Script-Ausführung erlaubt?
- Falls nein → Finding ist **Defense-in-Depth-Gap** (niedrige Severity, Code-Hardening-Fix)
- Falls ja → Finding ist **Stored XSS** (hohe Severity)

## D2 — Hypothesen

Siehe `hypotheses.md` im selben Verzeichnis. Drei Hypothesen (H1 Case-Bypass, H2 Event-Handler, H3 javascript:-URL) + H4 als CSP-Kontrolltest (leere SVG ohne Script → erwartete Erfolgsausgabe).

## Probe-Log

<Wird in D3 pro Iteration angehängt.>
