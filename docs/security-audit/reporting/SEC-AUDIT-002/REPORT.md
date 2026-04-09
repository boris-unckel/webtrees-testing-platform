<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# SEC-AUDIT-002 — Missing Dangerous-Extension Blocklist in MediaFileService::uploadFile()

## Summary

| Field | Value |
|---|---|
| **Affected file** | `app/Services/MediaFileService.php` (method `uploadFile()`, ~line 166) |
| **Affected version** | webtrees `main` at commit `c338276a5a` (post-2.2.5) and all prior versions |
| **CWE** | CWE-434 (Unrestricted Upload of File with Dangerous Type) |
| **Initial severity estimate** | **Medium** (defense-in-depth gap; stored HTML injection, JS blocked by CSP) |
| **CVSS 3.1 estimate** | 5.4 — Medium (AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N) |
| **Required privilege** | Editor role |
| **Mitigating factor** | CSP headers on image responses block script execution; `.php` uploads are unlikely to be executed directly by Apache in the media directory |

> **Note:** This is an initial severity estimate by the reporter. Final classification is at the maintainer's discretion.

## Description

webtrees has two code paths for media file uploads:

1. **`UploadMediaAction::handle()`** — enforces a dangerous-extension blocklist via regex:
   ```php
   preg_match('/(\.(php|pl|cgi|bash|sh|bat|exe|com|htm|html|shtml))$/i', ...)
   ```

2. **`MediaFileService::uploadFile()`** — used by `CreateMediaObjectAction` and `AddMediaFileAction` — has **no** extension blocklist.

An editor can upload files with dangerous extensions (`.php`, `.htm`, `.html`, `.shtml`, etc.) through the second path. This is an inconsistency: the same user-facing action ("upload a media file") applies different validation depending on which internal code path is taken.

### Concrete impact

- **`.htm` / `.html` uploads** are stored on the media filesystem and served to visitors. While `ImageFactory::imageResponse()` sets CSP headers for image types, HTML files served directly would render in the browser. The actual exploitability depends on the web server configuration and whether HTML files from the media directory are served with their native MIME type.
- **`.php` uploads** are less likely to execute because the media directory is typically not configured to execute PHP, but this depends on the server configuration.

### Auto-rename bypass

An additional subtlety: even after adding the blocklist, the `auto=1` parameter triggers an auto-rename path that derives the file extension from `getClientFilename()` rather than `new_file`. This means an attacker could set `new_file=safe.jpg` with `auto=1` and still get a `.htm` extension from the original upload filename. The fix addresses both paths.

## Reproduction Steps

### Prerequisites

- A webtrees instance at `$BASE_URL`
- An account with **Editor** role
- A valid CSRF token and session cookie

### Step 1: Upload an HTML file via CreateMediaObjectAction

```bash
# Log in
curl -c cookies.txt -b cookies.txt \
  "$BASE_URL/index.php?route=/login" \
  -d "username=editor_user&password=editor_pass&_csrf=$CSRF_TOKEN"

# Upload an HTML file as a media object
curl -b cookies.txt \
  "$BASE_URL/index.php?route=/tree/tree1/create-media-object" \
  -F "file=@evil.htm;type=text/html" \
  -F "file_location=upload" \
  -F "folder=" \
  -F "auto=0" \
  -F "new_file=evil.htm" \
  -F "_csrf=$CSRF_TOKEN"
```

The file `evil.htm` can contain any HTML, e.g.:
```html
<html><body><h1>Stored HTML injection</h1><script>alert(document.cookie)</script></body></html>
```

### Step 2: Verify the file was accepted

**Before fix:** `uploadFile()` returns the stored path (e.g., `evil.htm`). The file is written to the media filesystem.

**After fix:** `uploadFile()` returns an empty string and a flash message indicates the extension is not allowed.

### Step 3: Auto-rename bypass variant

```bash
# Upload with auto=1 — the server picks extension from client filename
curl -b cookies.txt \
  "$BASE_URL/index.php?route=/tree/tree1/create-media-object" \
  -F "file=@evil.htm;type=text/html" \
  -F "file_location=upload" \
  -F "folder=" \
  -F "auto=1" \
  -F "new_file=safe.jpg" \
  -F "_csrf=$CSRF_TOKEN"
```

**Before fix:** The auto-rename path generates a SHA1-based filename with the `.htm` extension from the client filename, bypassing any filename-based check.

**After fix:** The blocklist check runs after the auto-rename logic, catching both paths.

## Fix

The fix consists of three commits (see attached patches):

1. **`0001-test-add-MediaFileServiceTest-for-dangerous-extensio.patch`** — regression tests (6 test methods: 4 dangerous extensions, 1 safe extension, 1 auto-rename bypass). Tests are RED against unfixed code.

2. **`0002-fix-add-dangerous-extension-blocklist-to-MediaFileSe.patch`** — adds the `preg_match` blocklist check to `MediaFileService::uploadFile()`, mirroring `UploadMediaAction::handle()`.

3. **`0003-fix-move-extension-blocklist-after-auto-rename-to-cl.patch`** — moves the blocklist check to run after the auto-rename block, closing the `auto=1` bypass. Adds a dedicated test for this scenario.

Blocked extensions: `.php`, `.pl`, `.cgi`, `.bash`, `.sh`, `.bat`, `.exe`, `.com`, `.htm`, `.html`, `.shtml` (case-insensitive).

## Included Files

| File | Description |
|---|---|
| `0001-test-add-MediaFileServiceTest-for-dangerous-extensio.patch` | Regression test (RED-first) |
| `0002-fix-add-dangerous-extension-blocklist-to-MediaFileSe.patch` | Extension blocklist addition |
| `0003-fix-move-extension-blocklist-after-auto-rename-to-cl.patch` | Auto-rename bypass fix + test |
