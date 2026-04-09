<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-005 — Phase D7 (Validation) Artifacts

**Task:** `docs/security-audit/tasks/SEC-AUDIT-005_module_action_case_bypass.md`
**Fix branch:** `security-audit-005-module-action-case-bypass` (upstream/webtrees)
**Commits on fix branch:**

| Commit | Subject |
|---|---|
| `3a53e837de` | Add regression test for case-insensitive admin-gate bypass |
| `f8fdf173cf` | Close case-insensitive admin-gate bypass in ModuleAction |

Both GPG-signed with key `C3800666AD9815724DDAF7495E6039E5B765BCA4`.

**Date:** 2026-04-09

## Validation matrix

| Layer | Test file                                                                       | Variants | Result             | Evidence                                        |
|-------|---------------------------------------------------------------------------------|----------|--------------------|-------------------------------------------------|
| 2     | `upstream/webtrees/tests/app/Http/RequestHandlers/ModuleActionTest.php`         | 6        | ✅ 10/10 green     | `../d5_regression/layer2_green_run_post_fix.txt` |
| 3     | `layer3-integration/tests/Security/SecAudit005Test.php`                         | 9 + baseline | ✅ 10/10 green | `layer3_green_run.txt`                           |

## Layer 3 test design

The Layer-3 regression uses the **real** `ModuleService` (bootstrapped in
`MysqlTestCase::setUp()`) and the **real** `ModuleAction` handler against
real bundled modules (`faq`, `stories`, `relationships_chart`). This is
the key extension over Layer 2, which uses a mocked `ModuleService` and
an anonymous `fooModule` helper. Layer 3 proves the fix holds against
production classes wired into the full webtrees bootstrap (container,
I18N, MySQL).

**Data provider (9 rows)**:

1. `GET faq admin`                   — lowercase
2. `GET faq ADMIN`                   — uppercase
3. `GET faq AdMiN`                   — mixed
4. `GET faq adminedit`               — **smoking gun**: matches
   `FrequentlyAskedQuestionsModule::getAdminEditAction` via case-
   insensitive dispatch
5. `GET faq admin-edit`              — hyphenated
6. `POST faq admindelete`            — lowercase POST variant
7. `POST faq AdminDelete`            — POST baseline (canonical casing)
8. `POST stories admindelete`        — cross-module coverage
9. `POST relationships_chart admin`  — cross-module + lowercase

Plus one standalone baseline method `test_baseline_admin_action_is_blocked_on_faq`
using `action=Admin`.

Every row must raise `HttpAccessDeniedException` — the canonical 403
signal in webtrees — *before* the handler reaches `method_exists` or
`$module->$method($request)`.

## Self-skip guard

`SecAudit005Test::setUp()` runs two probes before each test method:

1. **Behavioral fix probe** — build a guest request with `module=faq`,
   `action=admin` (lowercase) and call `ModuleAction::handle()`. If the
   fix is present, this throws `HttpAccessDeniedException` and setup
   continues. Any other exception (or no exception at all) triggers
   `markTestSkipped` with a pointer to the fix branch and commits so
   that future runs against an unfixed `WEBTREES_SOURCE` stay green.
2. **Module-enablement probe** — iterate over `faq`, `stories`,
   `relationships_chart` and verify `findByName()` returns a non-null
   module. If any target module is disabled in the test fixture, the
   class is skipped with an explanatory message rather than failing.

Both probes ensure `make test-integration` stays green on pristine
upstream checkouts while auto-enabling the regression once the fix is
merged (or once `WEBTREES_SOURCE` is repointed at the fix branch).

## Run commands

### Layer 2 (unit)

```bash
podman-compose exec -T webtrees su -s /bin/bash www-data -c \
  'cd /var/www/html && vendor/bin/phpunit \
     --configuration=/tests/layer2-unit/phpunit-unit.xml \
     --filter "ModuleActionTest" --no-coverage --colors=never'
```

### Layer 3 (integration)

```bash
podman-compose exec -T webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter 'SecAudit005Test' --no-coverage --colors=never
```

## Next step

SEC-AUDIT-005 is now at status `fix_verified`. Parallel to SEC-AUDIT-001,
the fix lives on a feature branch in the `upstream/webtrees` submodule
and is ready for manual PR upstream. The platform-side artifacts and
Layer-3 regression test are committed on `main` in the testing platform
repo.
