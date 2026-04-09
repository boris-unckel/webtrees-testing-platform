<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# SEC-AUDIT-006 — Latent SQL Injection via Raw Expression() Interpolation in RenumberTreeAction

## Summary

| Field | Value |
|---|---|
| **Affected file** | `app/Http/RequestHandlers/RenumberTreeAction.php` (31 `Expression()` call sites) |
| **Affected version** | webtrees `main` at commit `c338276a5a` (post-2.2.5) and all prior versions |
| **CWE** | CWE-89 (Improper Neutralization of Special Elements used in an SQL Command) |
| **Initial severity estimate** | **Low** (defense-in-depth; not currently exploitable) |
| **CVSS 3.1 estimate** | 2.0 — Low (latent; requires breaking an independent invariant to exploit) |
| **Required privilege** | Manager role (admin track) |
| **Mitigating factor** | The GEDCOM parser validates all xrefs against `Gedcom::REGEX_XREF = '[A-Za-z0-9:_.-]{1,20}'` before database write; this regex excludes all SQL metacharacters |

> **Note:** This is an initial severity estimate by the reporter. Final classification is at the maintainer's discretion.

## Description

`RenumberTreeAction::handle()` iterates over duplicate xrefs and renames them via 31 raw SQL `Expression()` statements that use PHP string interpolation:

```php
new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', '0 @$new_xref@ INDI')")
```

The variable `$old_xref` is interpolated directly into the SQL string without parameter binding. If `$old_xref` contained SQL metacharacters (e.g., a single quote `'`), this would result in SQL injection.

### Why this is not currently exploitable

Safety depends on a single invariant in a different file:

- `app/Gedcom.php:243` defines `REGEX_XREF = '[A-Za-z0-9:_.-]{1,20}'`
- The GEDCOM parser validates all xrefs against this regex before writing to the database
- This regex does not include `'`, `;`, `-`, or any other SQL metacharacter

Therefore, under normal operation, `$old_xref` can never contain SQL injection payloads.

### Why this should still be fixed

This is a defense-in-depth concern:

1. **Cross-file safety dependency:** The security of 31 SQL statements depends entirely on a regex constant in a different file. There is no local validation at the point of use.
2. **Fragility under change:** If `REGEX_XREF` is ever loosened (e.g., to support Unicode identifiers), or if a code path writes xrefs to the database without parser validation, the SQL injection becomes exploitable.
3. **Database schema dependency:** The current `VARCHAR(20)` column limit provides an additional constraint. If the schema is changed to `TEXT`, longer payloads become possible.

## Reproduction Steps

This finding is **not directly reproducible** against a standard webtrees installation because the GEDCOM parser prevents malformed xrefs from reaching the database. The steps below describe the theoretical scenario.

### Theoretical scenario

1. **Precondition:** A malformed xref containing SQL metacharacters exists in the database (e.g., `I1' OR 1=1--`). This would require either:
   - A bug in the GEDCOM parser
   - Direct database manipulation
   - An alternative write path that bypasses the parser

2. **Trigger:** A Manager navigates to **Control panel > Trees > Renumber** and submits the form.

3. **Effect:** `RenumberTreeAction::handle()` reads the malformed xref from the database via `AdminService::duplicateXrefs()` and interpolates it into 31 `Expression()` calls, resulting in SQL injection.

### Verification via unit test

The attached regression test demonstrates the issue by stubbing `AdminService::duplicateXrefs()` to return a malformed xref:

```php
$admin_service->method('duplicateXrefs')
    ->willReturn(["I1' OR 1=1--" => Individual::RECORD_TYPE]);
```

**Before fix:** The malformed xref is interpolated into SQL, causing a `QueryException`.

**After fix:** The `preg_match` guard skips the malformed xref silently.

## Fix

The fix consists of two commits (see attached patches):

1. **`0001-test-add-RenumberTreeActionTest-for-xref-format-vali.patch`** — regression test that seeds a malformed xref via stub and asserts the renumber action handles it gracefully.

2. **`0002-fix-add-xref-format-validation-guard-in-RenumberTree.patch`** — adds a `preg_match('/\A' . Gedcom::REGEX_XREF . '\z/', $old_xref)` guard before the `Expression()` interpolation loop. Malformed xrefs are silently skipped.

This makes the safety contract explicit at the point of use rather than relying on an invariant in a distant file. No performance impact (single regex check per xref, executed only during the infrequent renumber operation).

## Included Files

| File | Description |
|---|---|
| `0001-test-add-RenumberTreeActionTest-for-xref-format-vali.patch` | Regression test |
| `0002-fix-add-xref-format-validation-guard-in-RenumberTree.patch` | Xref validation guard |
