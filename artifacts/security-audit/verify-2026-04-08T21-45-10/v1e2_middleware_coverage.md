<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# V1e.2 — Middleware Coverage Audit (34 files)

**Verification target**: The sweep 2026-04-08T20-58-28 counted middleware scope as 20 files. Actual count under `app/Http/Middleware/` is **34 files**. This round reads every one and audits for HIGH-severity patterns the sweep T1 did not cover. Also chases the follow-up from V1c: verify whether the `ModuleAction::handle()` substring-based admin gate is actually exploitable.

## Scope expansion

- **Sweep scope**: 20 files claimed.
- **V1e.2 actual scope**: 34 files found via `ls app/Http/Middleware/*.php`. Sweep undercounted by 14.

## Files covered (all 34)

| # | File | Class | Purpose | Verdict |
|---|---|---|---|---|
| 1 | AuthAdministrator.php | role gate | Admin only | Safe |
| 2 | AuthManager.php | role gate | Manager | Safe |
| 3 | AuthEditor.php | role gate | Editor | Safe |
| 4 | AuthMember.php | role gate | Member | Safe |
| 5 | AuthModerator.php | role gate | Moderator | Safe |
| 6 | AuthLoggedIn.php | role gate | Any user | Safe |
| 7 | AuthNotRobot.php | robot gate | BadBotBlocker flag | Safe |
| 8 | BadBotBlocker.php | bot filter | UA/DNS/ASN filter | Safe (see notes) |
| 9 | BaseUrl.php | URL init | Normalizes request URL | Safe |
| 10 | BootModules.php | lifecycle | Boots modules | Safe |
| 11 | CheckCsrf.php | CSRF gate | POST CSRF check | Safe (see notes) |
| 12 | CheckForMaintenanceMode.php | gate | Offline mode | Safe |
| 13 | CheckForNewVersion.php | lifecycle | Upgrade check | Safe |
| 14 | ClientIp.php | IP resolver | Trusted proxy | Config-dependent, safe |
| 15 | CompressResponse.php | encoding | gzip/deflate | Safe (BREACH notes) |
| 16 | ContentLength.php | header | content-length | Safe |
| 17 | DebugLogger.php | dev | SQL log in headers | Dev-only (see notes) |
| 18 | DoHousekeeping.php | lifecycle | tmp cleanup | Safe |
| 19 | EmitResponse.php | sink | echos body | Safe |
| 20 | ErrorHandler.php | lifecycle | set_error_handler | Safe |
| 21 | HandleExceptions.php | lifecycle | catch Throwable | Safe (notes on fallback) |
| 22 | LoadRoutes.php | lifecycle | loads routes | Safe |
| 23 | PublicFiles.php | file serve | /public/\* | Safe (defense fragile) |
| 24 | ReadConfigIni.php | lifecycle | config.ini.php | Safe |
| 25 | RegisterGedcomTags.php | lifecycle | tag registration | Safe |
| 26 | RequestHandler.php | sink | dispatches handler | Safe |
| 27 | Router.php | router | Aura match + dispatch | Safe |
| 28 | SecurityHeaders.php | headers | std security hdrs | Safe (CSP gap) |
| 29 | UpdateDatabaseSchema.php | lifecycle | migrations | Safe |
| 30 | UseDatabase.php | lifecycle | DB::connect() | Safe |
| 31 | UseLanguage.php | lifecycle | i18n | Safe |
| 32 | UseSession.php | lifecycle | session + user attr | Safe |
| 33 | UseTheme.php | lifecycle | theme | Safe |
| 34 | UseTransaction.php | lifecycle | DB transaction | Safe |

## Mechanical pattern scan

`grep -E 'whereRaw|selectRaw|orderByRaw|havingRaw|groupByRaw|fromRaw|DB::raw|new Expression|unserialize\(|\beval\(|\bsystem\(|\bexec\(|\bpassthru\(|shell_exec|\bpopen\(|proc_open'` → **0 hits.**

`grep -E 'file_get_contents|file_put_contents|unlink|fopen|\bcopy\(|\brename\(|readfile|include |require '` → 1 hit only: `PublicFiles.php:48` (`file_get_contents($file)`), reviewed below.

`grep -E '\$_GET|\$_POST|\$_COOKIE|\$_REQUEST|\$_FILES|\$_SERVER'` → **0 hits.** All middleware uses the Validator static API or PSR-7 Request methods correctly.

## HIGH severity findings

### F-V1e2-CRITICAL: ModuleAction substring-admin-gate — case-insensitive method dispatch bypass (EXPLOITED in PoC)

**Source**: `app/Http/RequestHandlers/ModuleAction.php:75`
```php
// Actions with "Admin" in the name are for administrators only.
if (str_contains($action, 'Admin') && !Auth::isAdmin($user)) {
    throw new HttpAccessDeniedException('Admin only action');
}

if (!method_exists($module, $method)) {
    throw new HttpNotFoundException(...);
}

return $module->$method($request);
```

`$action` comes from the URL path: `/module/{module}/{action}`. The gate uses `str_contains` which is **case-sensitive**. PHP's `method_exists()` and method dispatch (`$module->$method()`) are **case-INSENSITIVE**. This is a direct asymmetry.

**Exploit path (confirmed end-to-end in the running stack)**:

Step 1 — visitor GET with lowercase action:
```
GET /module/faq/adminedit HTTP/1.1
→ HTTP 200 OK
```
Response body contains `<title>Add an FAQ</title>` and a fresh CSRF token `<input type="hidden" name="_csrf" value="5444wzW2..."/>`. This proves `FrequentlyAskedQuestionsModule::getAdminEditAction()` was dispatched to an **unauthenticated visitor**.

Verification matrix (all on stock install, no login):
```
/module/faq/Admin       → HTTP 403  (gate fires: str_contains matches)
/module/faq/admin       → HTTP 302  (bypass: redirect to tree-specific admin URL)
/module/faq/adminedit   → HTTP 200  (bypass: full admin edit page rendered + CSRF)
/module/faq/AdminEdit   → HTTP 403  (gate fires correctly)
/module/faq/AdminDelete → HTTP 403  (gate fires correctly)
/module/faq/admindelete → HTTP 404  (GET to POST-only method → HttpNotFound — no bypass via GET)
/module/faq/aDmIn       → HTTP 302  (bypass: mixed case also bypasses)
```

Step 2 — POST with extracted CSRF and session cookie:
```
POST /module/faq/admindelete?block_id=999999 HTTP/1.1
Cookie: WT2_SESSION=<from step 1>
Content-Type: application/x-www-form-urlencoded

_csrf=<from step 1>
```
Response:
```
HTTP/1.1 302 Found
location: http://webtrees:80/module/faq/Admin
```

The redirect target is the one defined at `FrequentlyAskedQuestionsModule.php:186-191`:
```php
$url = route('module', ['module' => $this->name(), 'action' => 'Admin']);
return redirect($url);
```

A CSRF failure would redirect to `(string) $request->getUri()` per `CheckCsrf.php:66`, i.e., back to `/module/faq/admindelete?block_id=999999`. The observed redirect target is **different** from the POST URL, which proves CheckCsrf passed and `postAdminDeleteAction()` ran to completion.

**What the method does** (`FrequentlyAskedQuestionsModule.php:178-192`):
```php
public function postAdminDeleteAction(ServerRequestInterface $request): ResponseInterface
{
    $block_id = Validator::queryParams($request)->integer('block_id');
    DB::table('block_setting')->where('block_id', '=', $block_id)->delete();
    DB::table('block')->where('block_id', '=', $block_id)->delete();
    ...
}
```

No internal auth check. An unauthenticated visitor can delete arbitrary FAQ blocks by supplying the block_id. (In this test the table was empty — `SELECT COUNT(*) FROM wt_block WHERE module_name='faq'` returned 0 — so no actual records were harmed during verification.)

**PHP case-insensitivity proof** (run inside the container):
```php
class Foo { public function postAdminAction() { return "called"; } }
$foo = new Foo();
method_exists($foo, 'postAdminAction');    // true
method_exists($foo, 'postadminAction');    // true ← lowercase 'a'
method_exists($foo, 'postadminaction');    // true ← fully lowercase
$m = 'postadminaction'; $foo->$m();         // returns "called"
```

All three return true; dispatch resolves case-insensitively.

**Impact scope — default install** (3 enabled modules with admin methods on this verification stack):
- **FrequentlyAskedQuestionsModule** (`faq`): reachable via bypass — read/write/delete FAQ content for all trees.
- **StoriesModule** (`stories`): reachable via bypass — verified `/module/stories/adminedit` returns HTTP 400 WITH a CSRF token (method dispatched, then failed on missing param). Same destructive surface as FAQ.
- **RelationshipsChartModule** (`relationships_chart`): reachable — `postAdminAction` iterates ALL trees and writes `RELATIONSHIP_RECURSION` / `RELATIONSHIP_ANCESTORS` preferences globally without any internal auth check (line 439-445).

**Impact scope — non-default modules** (activated in some production installs):
- **CustomCssJsModule** (`custom-css-js`): The `body` / `head` preferences are injected raw into every page's `<body>` and `<head>` without sanitization (by design, for admin-only custom CSS/JS). An unauthenticated bypass allows **stored XSS on every page**, which escalates to **session hijacking of any admin/manager who views any page** → **full server compromise**.
- **GoogleMaps / MapBox / HereMaps / OpenRouteService / Geonames**: store API keys as tree/site preferences. `getAdminAction` renders these keys into the admin form → **API credential disclosure**.
- **SiteMapModule** / others: tree preference modification, sitemap regeneration → filesystem writes.

**CVSS 3.1 estimate**:
- AV:N / AC:L / PR:N / UI:N / S:U / C:H / I:H / A:L
- Base score: **9.4 (Critical)** when custom-css-js is enabled, **8.1 (High)** on default install (only FAQ/Stories/RelationshipsChart reachable → admin content modification but no XSS escalation).

**Root cause**: The gate is naming-convention–based and relies on case-sensitive substring matching, while PHP method dispatch is case-insensitive. Two solutions:

1. **Minimum fix** (1 line): Change `str_contains($action, 'Admin')` at ModuleAction.php:75 to `stripos($action, 'Admin') !== false`. This makes the gate case-insensitive and closes the bypass.
2. **Proper fix**: Normalize `$action` to a canonical form BEFORE constructing `$method` AND BEFORE the gate check. E.g., reject any `$action` whose lowercase form starts with `admin` unless the user is admin.
3. **Architectural fix**: Replace the string-convention gate with an explicit attribute/annotation check (PHP 8 attributes like `#[RequiresAdmin]` on the method). This is cleanest but requires touching every admin method.

**Methodology gap in the sweep**: The sweep's T1 triage read `ModuleAction.php` but classified the gate as correct because the reader did not consider PHP's method-dispatch case-insensitivity. V1c noted the substring-match concern as a "methodology pattern" but deferred actual verification to V1e.2. V1e.2 executed the PoC and confirmed the exploit. **The sweep was not wrong, but it under-investigated.** The anti-Scheinsicherheit methodology of the verification round caught this exactly.

## MEDIUM severity observations (no tasks)

### M-V1e2-1: SecurityHeaders lacks Content-Security-Policy (defense-in-depth gap)

`SecurityHeaders.php:30-36` sets `Permissions-Policy`, `Referrer-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, and conditional `Strict-Transport-Security`. **No `Content-Security-Policy` header.**

This means the only defense against XSS is Twig auto-escaping + `HtmlService::sanitize()` (verified in V1c). There is **no CSP backstop**. SEC-AUDIT-003 already flags the image-response CSP gap; the global lack of CSP is a broader architectural observation.

**Why not a task**: Adding CSP to a mature application requires per-page nonce/hash policy or extensive inline-script audit, which is architectural-level work and out of scope for this sweep. Documented for the upstream-webtrees-maintainer.

### M-V1e2-2: PublicFiles path-traversal defense is substring-based, not canonicalization-based

`PublicFiles.php:43-45`:
```php
$path = $request->getUri()->getPath();
if (str_starts_with($path, '/public/') && !str_contains($path, '..')) {
    $file = Webtrees::ROOT_DIR . $path;
```

**Analysis**: PSR-7 (`nyholm/psr7`) stores the path in **percent-encoded form** per the spec. `str_contains($path, '..')` does not match `%2E%2E`. BUT PHP's `file_get_contents()` does NOT decode percent-encoded characters in filenames, so `file_exists('/public/%2E%2E/config.ini.php')` returns false because the literal filename does not exist. Attack fails at the filesystem layer.

**Residual concern**: The defense is fragile. Any future change that decodes the URL path before the check (e.g., a new middleware added before PublicFiles) would turn the substring check into a bypass vector. Defense-in-depth recommendation: use `realpath()` canonicalization:
```php
$real = realpath(Webtrees::ROOT_DIR . $path);
$public_real = realpath(Webtrees::ROOT_DIR . '/public/');
if ($real !== false && str_starts_with($real, $public_real . DIRECTORY_SEPARATOR)) {
    // safe
}
```

**Why not a task**: Not currently exploitable. Recommendation only.

### M-V1e2-3: DebugLogger leaks SQL query values as response headers when debug=1 (dev-only)

`DebugLogger.php:52,79` adds `x-debug-sql` response headers with full SQL + bindings. Truncates string bindings to 27 chars. Binding values (including passwords, tokens) are exposed to any browser or proxy that sees the response. **Dev-only** (only runs when `debug=true` in config.ini.php), so not a production concern. Documented as a reminder: do not enable `debug=1` in production.

### M-V1e2-4: CompressResponse enables BREACH attack surface (HTTPS only)

Standard concern: HTTPS + gzip + user-reflected data in response + secret in response = BREACH-attack conditions. Theoretical; hard to exploit. Not actionable without CSRF-per-request rotation or response padding. Documented only.

### M-V1e2-5: CheckCsrf uses non-constant-time comparison (`!==`)

`CheckCsrf.php:59`: `if ($client_token !== $session_token)`. PHP string `!==` is not constant-time. In theory, timing side-channel can leak CSRF token byte-by-byte. **In practice**: CSRF tokens are 32-byte random values, and network jitter + webtrees' request-latency dominate any timing signal. Not exploitable without local network control AND millions of requests. Documented only. Preferred: `hash_equals($session_token, $client_token)`.

### M-V1e2-6: HandleExceptions fallback path emits raw exception text without HTML escape

`HandleExceptions.php:134`:
```php
return response(nl2br((string) $exception), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
```

This is a **last-resort fallback** reached only if all three view-rendering attempts (lines 108-131) themselves throw. In that emergency path, `(string) $exception` may contain exception messages that could include user-supplied data (e.g., `throw new Exception("Bad value: $user_input")`). Output is `nl2br`-wrapped but not `e()`-escaped.

**Reachability**: Extremely low (requires template engine itself to be broken).

**Defense-in-depth**: wrap with `e(...)` anyway. Documented only.

## Auth-middleware symmetry check

All 7 Auth\* middlewares follow the identical pattern:
1. Get user/tree from request attributes (via Validator::attributes) 
2. Check role → if OK, forward
3. Else if user logged in OR method POST → throw 403
4. Else redirect to LoginPage with `url` param = raw `$request->getUri()`

**Open-redirect follow-up**: The redirect includes the raw target URL as a query parameter. `LoginAction.php:53` validates this via `Validator::parsedBody($request)->isLocalUrl()->string('url', $default_url)` before post-login `redirect($url)`. **Verified safe**; no open-redirect via login flow.

## Sweep claim verification

| Sweep claim | Verified? | Refinement |
|---|---|---|
| "Middleware scope covered (20 files)" | **REFUTED count** | Actual count is 34, sweep undercounted by 14. None of the 14 additional files contain HIGH-severity patterns, but the headcount was wrong. |
| "No raw SQL / no dangerous functions in middleware" | **CONFIRMED** | 0 hits across all patterns (`*Raw`, `new Expression`, eval/system/exec, `$_GET`/`$_POST`/etc.) |
| "Auth middleware is symmetric and secure" | **CONFIRMED** | 7/7 Auth\* files follow identical pattern; post-login url validated via isLocalUrl() |
| "ModuleAction substring gate correct" (implied by V1c) | **REFUTED** | **CRITICAL bypass** via case-insensitive method dispatch, PoC executed |

## New task candidates (for V3 triage)

### SEC-AUDIT-005 — ModuleAction substring-admin-gate bypass (CRITICAL)

- **Track**: non-admin (unauthenticated reach)
- **Source files**: `app/Http/RequestHandlers/ModuleAction.php:75`
- **Sinks exposed**: all `get/postAdmin*Action()` methods in all enabled modules
- **Status proposal**: `queued` (not `fix_verified`, no patch exists)
- **Minimum fix**: `str_contains` → `stripos !== false` at ModuleAction.php:75
- **Verification PoC**: see F-V1e2-CRITICAL section above
- **CVSS 3.1 Base**: 8.1 (High) default install, 9.4 (Critical) if custom-css-js enabled
- **Defense-in-depth ask**: FAQ/Stories/RelationshipsChart and other `post*Admin*Action` methods should perform their own `Auth::isAdmin($user)` check inside the method body, not rely only on the ModuleAction gate. This is a dual-defense recommendation for the upstream maintainer.

### No other new task candidates from this round.

## Recommendations for sweep methodology

1. **Glob count discipline**: the sweep declared 20 middleware files, actual was 34. Sweep T0 scan should `wc -l` by directory and report actual counts, not estimates. Already noted as methodology fix in V1b.

2. **Case-sensitivity asymmetry pattern**: add a T1 probe rule — "any string-based gate check on user input that constructs a method/function name should test both cases of the input". This was the root cause of F-V1e2-CRITICAL and should become a grep pattern in future sweeps.

3. **PoC-in-runtime culture**: the sweep T1 read `ModuleAction.php` but did not attempt runtime verification of its gate. V1e.2 caught the bug only because it sent real HTTP requests to the running stack. Future sweeps should include a "one runtime probe per suspicious pattern" step for auth-gate verification.

4. **Method-exists case-insensitivity awareness**: PHP's case-insensitive method dispatch is a well-known footgun. Document this explicitly in the webtrees developer docs; add it to the sweep T1 checklist.
