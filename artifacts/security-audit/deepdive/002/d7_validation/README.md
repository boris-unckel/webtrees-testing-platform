<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-002 — D7 Validation

**Scope:** defense-in-depth — extension blocklist symmetry between
`MediaFileService::uploadFile()` and `UploadMediaAction::handle()`.

## Validation Matrix

| Check | Result | Artifacts |
|---|---|---|
| Layer 1 `php -l` (MediaFileService.php) | OK | `php_lint.txt` |
| Layer 1 `php -l` (MediaFileServiceTest.php) | OK | `php_lint.txt` |
| Layer 2 `MediaFileServiceTest` (6 tests, 36 assertions) | OK | `layer2_green.txt` |
| Code-read: blocklist regex identical to UploadMediaAction | OK | see below |
| Code-read: check position covers auto-rename path | OK | see below |
| Code-read: callers handle `return ''` correctly | OK | see below |

## Code-Read Review

### Fix placement (lines 184–190 in fixed file)

The blocklist check was placed **after** the auto-rename block (lines 184–189)
which derives the extension from `$uploaded_file->getClientFilename()`. This
ensures that both upload paths are covered:

- **Explicit name path:** `new_file=evil.htm`, `auto=0` → `$file = 'evil.htm'` → blocked
- **Auto-rename path:** `new_file=safe.jpg`, `auto=1`, client file `evil.htm` → `$file = sha1.htm` → blocked

### Regex

```
/(\.(php|pl|cgi|bash|sh|bat|exe|com|htm|html|shtml))$/i
```

String-identical to `UploadMediaAction::handle()` line 93.

### Caller handling of `return ''`

- `CreateMediaObjectAction::handle()` line 69–70: checks `$file === ''` →
  returns JSON with `error_message` and `STATUS_NOT_ACCEPTABLE`
- `AddMediaFileAction::handle()` line 57–60: checks `$file === ''` →
  FlashMessage + redirect

Both callers correctly handle the empty-string return.

### FlashMessage

Uses the same I18N key as UploadMediaAction:
`I18N::translate('Filenames are not allowed to have the extension "%s".', $match[1])`

## Commands

```bash
# Layer 1
podman-compose exec -T webtrees php -l app/Services/MediaFileService.php
podman-compose exec -T webtrees php -l tests/app/Services/MediaFileServiceTest.php

# Layer 2
podman-compose exec -T webtrees php vendor/bin/phpunit \
    --filter='MediaFileServiceTest' --no-coverage
```

## Gesamturteil

**fix_verified** — extension blocklist closes the defense-in-depth gap between
the two upload pipelines. The auto-rename bypass scenario is covered by test
and by check positioning.
