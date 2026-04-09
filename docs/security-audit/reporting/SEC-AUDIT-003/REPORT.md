<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# SEC-AUDIT-003 — Missing Content-Security-Policy Header on replacementImageResponse()

## Summary

| Field | Value |
|---|---|
| **Affected file** | `app/Factories/ImageFactory.php` (method `replacementImageResponse()`) |
| **Affected version** | webtrees `main` at commit `c338276a5a` (post-2.2.5) and all prior versions |
| **CWE** | CWE-693 (Protection Mechanism Failure) |
| **Initial severity estimate** | **Low** (defense-in-depth; no current exploit primitive) |
| **CVSS 3.1 estimate** | 2.0 — Low (theoretical only; no active vulnerability) |
| **Required privilege** | Visitor (the placeholder is served on error conditions reachable without authentication) |
| **Mitigating factor** | The placeholder SVG body is static and contains no user-controlled content today |

> **Note:** This is an initial severity estimate by the reporter. Final classification is at the maintainer's discretion.

## Description

`ImageFactory` has two response constructors for SVG content:

1. **`imageResponse()`** — serves the actual media file. Sets `content-security-policy: script-src none;frame-src none` as a defense-in-depth header against SVG-based XSS.

2. **`replacementImageResponse()`** — serves a placeholder SVG when the original file cannot be displayed (file not found, XSS blocked, internal error, etc.). This method does **not** set any CSP header.

Both methods return `Content-Type: image/svg+xml`. The asymmetry means that `replacementImageResponse()` lacks the same defense-in-depth protection as the regular image path.

### Why this matters

While the placeholder body is currently a static SVG template (`resources/views/errors/image-svg.phtml`) with no user-controlled interpolation, this creates a latent risk:

- If a future change adds user-controlled text to the placeholder (e.g., the filename, an error message, or an exception detail), there would be no CSP to block inline script execution.
- The 13 call sites throughout `ImageFactory` that call `replacementImageResponse()` would all inherit this gap.

## Reproduction Steps

### Prerequisites

- A webtrees instance at `$BASE_URL`

### Step 1: Trigger a placeholder response

Request a media file that does not exist or triggers an error:

```bash
curl -v "$BASE_URL/index.php?route=/media-thumbnail/nonexistent-file.svg"
```

### Step 2: Inspect headers

**Before fix:**
```
HTTP/1.1 200 OK
Content-Type: image/svg+xml
```
No `Content-Security-Policy` header is present.

**After fix:**
```
HTTP/1.1 200 OK
Content-Type: image/svg+xml
Content-Security-Policy: script-src none;frame-src none
```

The CSP header now matches the one set by `imageResponse()`.

## Fix

The fix consists of two commits (see attached patches):

1. **`0001-SEC-AUDIT-003-test-that-replacementImageResponse-set.patch`** — regression test asserting CSP header presence and value on `replacementImageResponse()`. Fails on unfixed code.

2. **`0002-SEC-AUDIT-003-add-CSP-header-to-replacementImageResp.patch`** — adds the `content-security-policy` header directly in the `replacementImageResponse()` method. All 13 call sites inherit the fix automatically.

The change is a single-point modification (5 lines added to one method). No call-site changes required.

## Included Files

| File | Description |
|---|---|
| `0001-SEC-AUDIT-003-test-that-replacementImageResponse-set.patch` | Regression test |
| `0002-SEC-AUDIT-003-add-CSP-header-to-replacementImageResp.patch` | CSP header fix |
