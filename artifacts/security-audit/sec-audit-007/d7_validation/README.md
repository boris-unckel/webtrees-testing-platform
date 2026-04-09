<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-007 — Phase D7 (Validation) Artifacts

**Task:** `docs/security-audit/tasks/SEC-AUDIT-007_setupwizard_superglobal.md`
**Fix branch:** `security-audit-007-setupwizard-superglobal` (upstream/webtrees)
**Fix commit:** `1dcca3938863d38bf11eeb495bcf8c80bf503fcd`
  * GPG-signed with key `C3800666AD9815724DDAF7495E6039E5B765BCA4`.
**Date:** 2026-04-09

## Scope

This task is classified **LOW / code-quality**: the raw `$_POST['wtpass']`
read in `SetupWizard.php:327` is not an exploitable primitive — the value
is pushed straight through `password_hash()` via `User::setPassword()`
and is never echoed, logged, interpolated into SQL, or stored verbatim.
The audit surfaced it because the adjacent fresh-install branch
(line 323, now 327 after fix) already uses the validated `$data['wtpass']`,
so the reinstall branch was the sole inconsistency.

## Fix

One-line change (`app/Http/RequestHandlers/SetupWizard.php:327`):

```diff
-            $admin->setPassword($_POST['wtpass']);
+            $admin->setPassword($data['wtpass']);
```

`$data['wtpass']` is populated at line 180 by

```php
$data[$key] = Validator::parsedBody($request)->string($key, $default);
```

which is the same value already consumed by `user_service->create()` on
the fresh-install branch one line above.

## Validation matrix

| Layer | Check                                       | Result  | Evidence                            |
|-------|---------------------------------------------|---------|-------------------------------------|
| 1     | `php -l SetupWizard.php`                    | ✅ pass | `php_lint.txt`                      |
| 2     | Upstream `SetupWizardTest` (1 test)         | ✅ pass | `layer2_setupwizard_green.txt`      |
| —     | Code-read review                            | ✅ ok   | see "Code-read review" below        |

### Code-read review

The fix is self-evident and does not require a dedicated regression test
(per task file §"Test-First Regression Requirement"). The audit accepts:

1. `$data['wtpass']` is defined on line 180 (before line 327 in
   control flow: `createConfigFile()` is called from `handle()` →
   `step6Install()`, both of which receive the same `$data` array that
   was populated by `userData()`).
2. `$data['wtpass']` is the same value the **fresh-install** branch
   consumes on line 323 — so the two sibling branches now handle the
   password identically.
3. No other call site in `SetupWizard.php` uses `$_POST` directly
   (grep confirms only the one hit was on line 327).

### Layer 2 run

```bash
podman-compose exec -T webtrees su -s /bin/bash www-data -c \
  'cd /var/www/html && vendor/bin/phpunit \
     --configuration=/tests/layer2-unit/phpunit-unit.xml \
     --filter="SetupWizardTest" --no-coverage --colors=never'
```

Result: `OK (1 test, 2 assertions)` — see `layer2_setupwizard_green.txt`.

The upstream `SetupWizardTest` is a smoke test (`class_exists`) and does
not exercise the reinstall branch. A targeted integration test was
weighed against the LOW severity and rejected: the reinstall path
requires a full SetupWizard bootstrap with an existing admin user, and
the fix is a straight literal substitution with no behavioural delta.

## Not pushed

The fix lives on the local `security-audit-007-setupwizard-superglobal`
branch in the `upstream/webtrees` clone. Manual upstream PR will be
filed when the user is ready (same pattern as SEC-AUDIT-001 / 005).
