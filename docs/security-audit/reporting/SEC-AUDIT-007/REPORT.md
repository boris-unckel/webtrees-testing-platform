<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# SEC-AUDIT-007 — Raw `$_POST` Superglobal in SetupWizard Reinstall Path

## Summary

| Field | Value |
|---|---|
| **Affected file** | `app/Http/RequestHandlers/SetupWizard.php` (line 327) |
| **Affected version** | webtrees `main` at commit `c338276a5a` (post-2.2.5) and all prior versions |
| **CWE** | N/A (code quality; no exploitable security primitive) |
| **Initial severity estimate** | **Informational / Low** (code quality issue, not a vulnerability) |
| **CVSS 3.1 estimate** | 0.0 (no security impact) |
| **Required privilege** | Setup installer access (before installation is complete, or during explicit reinstall) |
| **Mitigating factor** | The value is immediately passed to `password_hash()`, never echoed, interpolated into SQL, or stored verbatim |

> **Note:** This is an initial severity estimate by the reporter. Final classification is at the maintainer's discretion.

## Description

In `SetupWizard.php`, the fresh-install and reinstall branches handle the admin password differently:

```php
// Line 323 — fresh install (uses validated input):
$admin->setPassword($data['wtpass']);

// ...
} else {
    // Line 327 — reinstall (uses raw superglobal):
    $admin->setPassword($_POST['wtpass']);
}
```

The `$data['wtpass']` value is populated at line 180 via the `Validator::parsedBody()` pipeline:

```php
$data[$key] = Validator::parsedBody($request)->string($key, $default);
```

The reinstall branch bypasses this validated value and reads directly from `$_POST`. This is an API inconsistency — all other request input in this handler flows through the Validator.

### Why this is not exploitable

- `User::setPassword()` immediately hashes the value via `password_hash()`. The raw password is never echoed to the response, interpolated into SQL, stored in logs, or used in any other context.
- There is no SQL injection, XSS, or information disclosure primitive.
- The only concrete side effect is a potential `Undefined Index` PHP notice if the `wtpass` POST parameter is missing (the Validator path returns a default value instead).

### Why it is still worth fixing

- **API consistency:** All request input should flow through the Validator pipeline. Mixing raw `$_POST` access with the Validator creates confusion and makes auditing harder.
- **Error handling:** The Validator provides a default fallback when the parameter is missing; `$_POST['wtpass']` throws a warning.
- **Audit hygiene:** Eliminates a `$_POST[` grep hit from security scans, reducing false positives in future audits.

## Reproduction Steps

This is a code-quality observation, not a directly exploitable vulnerability. The inconsistency can be observed by reading the source code.

### Code inspection

```bash
# Show the inconsistency:
grep -n 'wtpass' app/Http/RequestHandlers/SetupWizard.php
```

Expected output (before fix):
```
180:        foreach (['lang', 'dbtype', 'dbhost', 'dbport', 'dbuser', 'dbpass', 'dbname', 'tblpfx', 'wtname', 'wtuser', 'wtpass', 'wtemail'] as $key) {
323:            $admin->setPassword($data['wtpass']);
327:            $admin->setPassword($_POST['wtpass']);
```

Line 323 uses `$data['wtpass']` (validated). Line 327 uses `$_POST['wtpass']` (raw).

## Fix

The fix is a single-line change (see attached patch):

**`0001-SEC-AUDIT-007-use-validated-data-wtpass-in-reinstall.patch`** — replaces `$_POST['wtpass']` with `$data['wtpass']` on line 327.

No behavior change for the happy path. The only observable difference is that missing `wtpass` parameters now return the Validator's default value instead of an `Undefined Index` warning.

## Included Files

| File | Description |
|---|---|
| `0001-SEC-AUDIT-007-use-validated-data-wtpass-in-reinstall.patch` | 1-line fix |
