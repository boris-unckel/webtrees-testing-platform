<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-005 — Phase D5 (Regression) Artifacts

**Task:** `docs/security-audit/tasks/SEC-AUDIT-005_module_action_case_bypass.md`
**Fix branch:** `security-audit-005-module-action-case-bypass`
  (lives in `upstream/webtrees` submodule, branched from `main` at
  upstream commit `34dff096c2`)
**Date:** 2026-04-09

## Regression test location

- **Layer 2 (upstream unit):**
  `upstream/webtrees/tests/app/Http/RequestHandlers/ModuleActionTest.php`
  - Added `testAdminActionCaseBypass` with `#[DataProvider('caseBypassProvider')]`
  - Added `getAdminEditAction()` to the anonymous `fooModule()` — provides a
    real dispatch target so the `adminedit` variant exercises the full
    case-insensitive PHP method lookup path.
  - Data provider: 6 casing variants — `Admin`, `admin`, `ADMIN`,
    `AdMiN`, `admin-edit`, `adminedit`.

## Red-state evidence (pre-fix run)

`layer2_red_run_pre_fix.txt` captures the PHPUnit run against **unfixed**
`app/Http/RequestHandlers/ModuleAction.php` (line 75 still uses
`str_contains($action, 'Admin')`).

### Expected and observed results per data row

| Row | Action value | Expected (post-fix) | Observed (pre-fix)                                                  | Meaning |
|-----|--------------|---------------------|---------------------------------------------------------------------|---------|
| 0   | `Admin`      | `HttpAccessDeniedException` | `HttpAccessDeniedException` ✓ pass                        | Baseline, gate fires correctly on capital A |
| 1   | `admin`      | `HttpAccessDeniedException` | `HttpNotFoundException` at `ModuleAction.php:80` ✗ fail   | Gate missed, method lookup failed |
| 2   | `ADMIN`      | `HttpAccessDeniedException` | `HttpNotFoundException` at `ModuleAction.php:80` ✗ fail   | Gate missed, method lookup failed |
| 3   | `AdMiN`      | `HttpAccessDeniedException` | `HttpNotFoundException` at `ModuleAction.php:80` ✗ fail   | Gate missed, method lookup failed |
| 4   | `admin-edit` | `HttpAccessDeniedException` | `HttpNotFoundException` at `ModuleAction.php:80` ✗ fail   | Gate missed, hyphenated method name unresolvable |
| 5   | `adminedit`  | `HttpAccessDeniedException` | **no exception thrown** ✗ fail                             | **SMOKING GUN**: gate missed AND case-insensitive PHP dispatch resolved `getadmineditAction` to `getAdminEditAction`, the admin method executed without auth |

### Interpretation

Rows 1–4 demonstrate the gate bypass: `str_contains($action, 'Admin')`
returns `false` for any lowercased variant, so the admin check is
skipped and the handler proceeds to `method_exists`. In rows 1–4 the
method lookup happens to miss (because fooModule has no
`getadminAction` / `getADMINAction` / etc.) and the handler throws
`HttpNotFoundException` instead of the expected
`HttpAccessDeniedException`. The test still fails — but the failure
mode already proves the gate was bypassed: a secure handler would have
thrown the access-denied exception *before* reaching the method lookup.

Row 5 (`adminedit`) is the end-to-end proof of the live exploit:
- The gate misses: `str_contains('adminedit', 'Admin')` → `false`
- The method lookup hits: `method_exists($module, 'getadmineditAction')`
  → `true` (PHP method dispatch is case-insensitive, so the camelCase
  declaration `getAdminEditAction` matches)
- Dispatch runs: `$module->getadmineditAction($request)` executes
  `getAdminEditAction()` and returns a 200 response with the sentinel
  body `SEC-AUDIT-005: admin method reached without auth gate`
- The test fails with "expected HttpAccessDeniedException, no
  exception thrown" — proving the guest reached the admin code path.

This matches the end-to-end PoC in the verification run
`verify-2026-04-08T21-45-10` V1e.2 against the live webtrees container.

## Run command (inside the webtrees container)

```bash
podman-compose exec -T webtrees su -s /bin/bash www-data -c \
  'cd /var/www/html && vendor/bin/phpunit \
     --configuration=/tests/layer2-unit/phpunit-unit.xml \
     --filter "ModuleActionTest" --no-coverage --colors=never'
```

Matches the Layer 2 test runner (`layer2-unit/run.sh`) conventions.

## Next step

Phase D6 — apply the minimum fix on this same feature branch
(`security-audit-005-module-action-case-bypass`):

```diff
--- a/app/Http/RequestHandlers/ModuleAction.php
+++ b/app/Http/RequestHandlers/ModuleAction.php
@@ -72,7 +72,7 @@ final class ModuleAction implements RequestHandlerInterface
         $method = $verb . $action . 'Action';
 
         // Actions with "Admin" in the name are for administrators only.
-        if (str_contains($action, 'Admin') && !Auth::isAdmin($user)) {
+        if (stripos($action, 'Admin') !== false && !Auth::isAdmin($user)) {
             throw new HttpAccessDeniedException('Admin only action');
         }
```

Re-run the same filter after the fix — expect **all 10 tests green**.
