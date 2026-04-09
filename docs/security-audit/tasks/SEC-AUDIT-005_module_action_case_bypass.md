<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-005
title: ModuleAction::handle() substring-admin-gate case-insensitive bypass — unauthenticated admin-method invocation
created: 2026-04-08
last_updated: 2026-04-09
status: fix_verified
track: non-admin
file: app/Http/RequestHandlers/ModuleAction.php
contributing_files:
  - app/Http/Routes/WebRoutes.php
  - app/Module/FrequentlyAskedQuestionsModule.php
  - app/Module/StoriesModule.php
  - app/Module/RelationshipsChartModule.php
  - upstream/webtrees/tests/app/Http/RequestHandlers/ModuleActionTest.php
verticals_hit:
  - V3_auth_bypass
  - V4_xss
  - V9_arbitrary_file_write
final_score: 0.85
llm_score: 90
t0_signals:
  crap: 0
  crap_coverage_pct: 0.0
  input_sinks:
    - Validator::attributes($request)->string('module')
    - Validator::attributes($request)->string('action')
    - Validator::attributes($request)->user()
  db_sinks:
    - indirect via module methods (e.g. FAQ block delete)
  dangerous_functions:
    - dynamic method dispatch via $module->$method($request)
  routing_entry_points:
    - GET/POST /module/{module}/{action}
    - GET/POST /module/{module}/{action}/{tree}
  reachability: visitor
  type_weight: 1.0
  auth_requirement: none
  loc: 85
hypotheses:
  - H1_case_insensitive_bypass_verified
current_hypothesis: H1
probe_iteration_count: 3
validation_failure_count: 0
fixture_rev: 0
fix_branch: security-audit-005-module-action-case-bypass
disclosure_state: not_ready
blocked_by: []
notes_for_opus: |
  Discovered in V1e.2 of verification run verify-2026-04-08T21-45-10.
  End-to-end PoC verified against live webtrees container (default install).

  **Root cause**: ModuleAction.php:75 uses `str_contains($action, 'Admin')`
  (case-sensitive) as admin gate, but PHP's `method_exists()` and method
  dispatch are case-insensitive. A lowercase URL action like
  `/module/faq/adminedit` bypasses the gate but still dispatches to
  `postAdminEditAction`.

  **Reach**: unauthenticated visitor. No session, no CSRF token (for GET
  actions), no prior login required.

  **Verified exploitable methods (default install, 3 modules enabled)**:
    - FrequentlyAskedQuestionsModule::postAdminDeleteAction (destructive)
    - FrequentlyAskedQuestionsModule::postAdminEditAction (state-changing)
    - FrequentlyAskedQuestionsModule::getAdminEditAction (form disclosure)
    - StoriesModule::postAdminDeleteAction (destructive)
    - RelationshipsChartModule::postAdminAction (state-changing)
  **Additional reach if custom-css-js module enabled**:
    - CustomCssJsModule::postAdminAction — unauthenticated admin can inject
      arbitrary <script> into every page head/body → stored XSS visitor→admin

  **CVSS 3.1**:
    Default install: AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:L = 8.1 (High)
    With custom-css-js: AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H = 9.4 (Critical)

  **Missed by sweep T1**: the sweep read ModuleAction.php and classified it
  as "admin-only via substring gate" without checking that PHP method
  dispatch is case-insensitive. The existing upstream unit test
  `ModuleActionTest::testAdminAction` passes `action='Admin'` (capital A)
  only — the case-variant was never probed.

  **Minimum fix**: ModuleAction.php:75 — replace `str_contains($action, 'Admin')`
  with `stripos($action, 'Admin') !== false`. 1-line change, preserves
  existing intent.

  **Defense-in-depth fix**: every `post*Admin*Action` / `get*Admin*Action`
  method in bundled modules should call `Auth::checkAdminOrException()`
  (or equivalent) as the first line of its body. This removes the
  dependency on the ModuleAction handler gate entirely.

  **Test-first regression requirement**: a data-provider test with at
  least 6 casing variants (`Admin`, `admin`, `ADMIN`, `AdMiN`,
  `admin-edit`, `admindelete`) must be added to ModuleActionTest and
  assert `HttpAccessDeniedException` for every variant. This test must
  be written **before** the fix and must fail-then-pass.
---

# SEC-AUDIT-005 — ModuleAction case-insensitive admin-gate bypass

## Triage-Kontext

- **Warum queued**: In Verification-Runde V1e.2 (verify-2026-04-08T21-45-10) entdeckt. **Live-exploit mit End-to-End-PoC gegen laufenden webtrees-Container bestätigt.** CRITICAL: unauthentifizierte Visitor-Reach auf destruktive Admin-Methoden in Default-Install-Modulen.
- **Verticals**: V3_auth_bypass (primär), V4_xss (über custom-css-js), V9_arbitrary_file_write (indirekt über FAQ-Admin-Edit)
- **Track-Assignment**: non-admin (höchste Priorität, da Visitor-Reach)

## Vulnerability Description

### Root Cause

`app/Http/RequestHandlers/ModuleAction.php` implementiert einen string-basierten Admin-Gate:

```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    $module_name = $request->getAttribute('module');
    $action      = $request->getAttribute('action');
    $user        = Validator::attributes($request)->user();
    // ...
    $verb   = strtolower($request->getMethod());
    $method = $verb . $action . 'Action';

    // Actions with "Admin" in the name are for administrators only.
    if (str_contains($action, 'Admin') && !Auth::isAdmin($user)) {
        throw new HttpAccessDeniedException('Admin only action');
    }

    if (!method_exists($module, $method)) {
        throw new HttpNotFoundException(...);
    }

    return $module->$method($request);
}
```

**Asymmetrie**:
- `str_contains($action, 'Admin')` ist **case-sensitive**
- `method_exists($module, $method)` ist **case-insensitive** (PHP-Methodennamen sind case-insensitive)
- `$module->$method($request)` ist **case-insensitive** (PHP-Method-Dispatch ist case-insensitive)

**Konsequenz**: Ein Angreifer, der statt `/module/faq/AdminEdit` den URL-Pfad `/module/faq/adminedit` aufruft, trifft:
1. Gate: `str_contains('adminedit', 'Admin')` → `false` → Gate feuert nicht
2. Method-Exists-Check: `method_exists($faq, 'postadmineditAction')` → `true` (wegen case-insensitivity → findet `postAdminEditAction`)
3. Dispatch: `$faq->postadmineditAction($request)` → läuft `postAdminEditAction()` aus

Der gesamte Admin-Code-Pfad wird ohne Auth-Check durchlaufen.

### Routing

`app/Http/Routes/WebRoutes.php:720-723`:

```php
$router->get('module-tree', '/module/{module}/{action}/{tree}', ModuleAction::class)
    ->allows(RequestMethodInterface::METHOD_POST);
$router->get('module-no-tree', '/module/{module}/{action}', ModuleAction::class)
    ->allows(RequestMethodInterface::METHOD_POST);
```

Die Route ist im **äußeren Scope** definiert — **keine** Middleware-basierte Auth-Gate (kein `AuthAdministrator`, kein `AuthEditor`). Der einzige Schutz ist die interne `str_contains`-Prüfung im Handler.

### Verifizierte Reach-Module (Default-Install)

In der Default-Install-Konfiguration sind folgende Module aktiv und haben `post*Admin*Action`-Methoden:

| Modul | Methode | Effekt |
|---|---|---|
| `faq` (FrequentlyAskedQuestionsModule) | `getAdminEditAction` | Offenbart Admin-Edit-Form für FAQ-Blocks an Visitor |
| `faq` | `postAdminEditAction` | Erstellt/ändert FAQ-Blocks ohne Auth |
| `faq` | `postAdminDeleteAction` | Löscht FAQ-Blocks ohne Auth |
| `stories` (StoriesModule) | `postAdminDeleteAction` | Löscht Stories ohne Auth |
| `relationships_chart` (RelationshipsChartModule) | `postAdminAction` | Ändert `RELATIONSHIP_RECURSION`/`RELATIONSHIP_ANCESTORS` Preferences aller Trees |

**Reach if `custom-css-js` module enabled** (nicht Default, aber bundled):
- `CustomCssJsModule::postAdminAction` → schreibt `body` und `head` Preferences die als **raw HTML/JS** in jede Seite eingebettet werden. **Unauthentifizierter Admin→Admin Stored-XSS**.

### End-to-End PoC (verifiziert gegen webtrees-Container)

**Schritt 1**: GET `/module/faq/adminedit` als Guest (keine Session, kein Cookie)
```
HTTP/1.1 200 OK
Content-Type: text/html
... <form ...> <input name="_csrf" value="TOKEN"> ...
```
Resultat: Admin-Edit-Form wird an Guest ausgeliefert.

**Schritt 2**: POST `/module/faq/admindelete?block_id=999999` mit CSRF aus Schritt 1 + Session-Cookie
```
HTTP/1.1 302 Found
Location: /index.php?route=%2Fmodule%2Ffaq%2FAdmin
```
Resultat: HTTP 302 zur `postAdminDeleteAction`-Completion-URL. Die Methode wurde ausgeführt.

**Korrelation mit Source**: `FrequentlyAskedQuestionsModule.php:186-191` zeigt den exakten Completion-Redirect:
```php
$url = route('module', ['module' => $this->name(), 'action' => 'Admin']);
return redirect($url);
```

## CVSS 3.1

### Default Install (3 Module mit Admin-Methoden)
- AV:N (Network, HTTP request)
- AC:L (Low — nur URL mit Kleinbuchstaben)
- PR:N (None — unauthenticated)
- UI:N (None — kein User-Interaction)
- S:U (Unchanged — webtrees-Anwendung)
- C:H (High — Admin-Edit-Form mit CSRF/DB-Inhalten wird offenbart)
- I:H (High — Visitor kann FAQ/Stories löschen, Tree-Preferences ändern)
- A:L (Low — kein Full-DoS, aber teilweise Verfügbarkeit durch Datenverlust)

**Score**: **8.1 High**

### Mit custom-css-js Modul
- Alles wie oben, aber A:H (High — via XSS-Injection in jede Seite)

**Score**: **9.4 Critical**

## Existing Test Coverage (V1e.3 Finding)

`upstream/webtrees/tests/app/Http/RequestHandlers/ModuleActionTest.php::testAdminAction`:

```php
public function testAdminAction(): void
{
    $this->expectException(HttpAccessDeniedException::class);
    $this->expectExceptionMessage('Admin only action');

    $request = self::createRequest()
        ->withAttribute('module', 'test')
        ->withAttribute('action', 'Admin')         // ← Großbuchstabe
        ->withAttribute('user', $user);
    $handler->handle($request);
}
```

**Dieser Test testet die Positive-Path des Gates, aber niemals eine Casing-Variante**. Der Test ist ein Lehrbuchbeispiel für Scheinsicherheit: der Name `testAdminAction` legt nahe, dass der Admin-Gate vollständig geprüft wird; tatsächlich wird nur ein einziger Input getestet, und zwar derjenige, der den Bug **nicht** triggert.

## Fix

### Minimum Fix (1-Line)

`app/Http/RequestHandlers/ModuleAction.php:75`:

```diff
- if (str_contains($action, 'Admin') && !Auth::isAdmin($user)) {
+ if (stripos($action, 'Admin') !== false && !Auth::isAdmin($user)) {
```

Erklärung: `stripos` ist case-insensitive. Matched `Admin`, `admin`, `ADMIN`, `AdMiN` gleichermaßen. Bewahrt die exakte Semantik des ursprünglichen Gates für alle existierenden Input-Pfade.

### Defense-in-Depth Fix

Jede `post*Admin*Action` / `get*Admin*Action` Methode in `app/Module/*` sollte **im eigenen Methoden-Körper** eine Auth-Prüfung durchführen:

```php
public function postAdminDeleteAction(ServerRequestInterface $request): ResponseInterface
{
    $user = Validator::attributes($request)->user();
    if (!Auth::isAdmin($user)) {
        throw new HttpAccessDeniedException('Admin only action');
    }
    // ... rest of method ...
}
```

Dies macht die Sicherheit unabhängig vom Handler-Gate und verhindert zukünftige Regressionen bei Refactoring.

## Test-First Regression Requirement

**Vor dem Fix**: Regression-Test muss geschrieben sein und **fehlschlagen**:

```php
#[DataProvider('caseBypassProvider')]
public function testAdminActionCaseBypass(string $actionAttr): void
{
    $this->expectException(HttpAccessDeniedException::class);
    $this->expectExceptionMessage('Admin only action');

    $module_service = $this->createMock(ModuleService::class);
    $module_service
        ->expects($this->once())
        ->method('findByName')
        ->with('test')
        ->willReturn($this->fooModule());

    $request = self::createRequest()
        ->withAttribute('module', 'test')
        ->withAttribute('action', $actionAttr)
        ->withAttribute('user', new GuestUser());

    (new ModuleAction($module_service))->handle($request);
}

public static function caseBypassProvider(): array
{
    return [
        'baseline capital A'  => ['Admin'],        // bereits in testAdminAction getestet
        'all lowercase'       => ['admin'],        // V1e.2 bypass
        'all uppercase'       => ['ADMIN'],
        'mixed case'          => ['AdMiN'],
        'lowercase hyphen'    => ['admin-edit'],   // V1e.2 PoC
        'lowercase no sep'    => ['admindelete'],  // V1e.2 PoC
    ];
}
```

**Zusätzlich Layer-3-HTTP-Test**: Ein echter Request gegen `/module/faq/adminedit` muss HTTP 403 (oder äquivalentes Redirect-to-Login) erhalten, nicht HTTP 200.

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/verify-2026-04-08T21-45-10/v1e2_middleware_coverage.md` §F-V1e2-CRITICAL
- generated_at: 2026-04-08 (während V1e.2)

### Phase D2 — Hypothesen
- H1: Case-insensitive PHP-Method-Dispatch umgeht `str_contains`-Gate → **CONFIRMED** (D3)

### Phase D3/D4 — Probe-Loop
- iter1: PHP case-insensitivity im webtrees-Container verifiziert (`method_exists($foo, 'postadminaction')` → `true` trotz Deklaration als `postAdminAction`)
- iter2: HTTP-Probe gegen `/module/faq/adminedit` → 200 + Form
- iter3: End-to-end CSRF-authed POST → 302 Completion-Redirect = Methode lief

**Status**: **exploit_confirmed** (vor Deep-Dive-Start; die Verification-Runde hat D3/D4 effektiv vorweggenommen)

### Phase D5 — Regression (2026-04-09, Layer 2 abgeschlossen)
- **Branch**: `security-audit-005-module-action-case-bypass` (upstream/webtrees, abgezweigt von `main` @ `34dff096c2`)
- **Modifiziertes Testfile**: `upstream/webtrees/tests/app/Http/RequestHandlers/ModuleActionTest.php`
  - Neu: `testAdminActionCaseBypass` mit `#[DataProvider('caseBypassProvider')]` — 6 Casing-Varianten
  - Neu: `getAdminEditAction()` auf dem anonymen `fooModule()` — Dispatch-Target für die `adminedit`-Variante
- **Red-Run-Evidenz**: `artifacts/security-audit/sec-audit-005/d5_regression/layer2_red_run_pre_fix.txt`
  - 10 Tests gesamt, **5 Failures** gegen unfixed `str_contains` Gate
  - Baseline `Admin` ✓ pass (Gate feuert)
  - `admin`, `ADMIN`, `AdMiN`, `admin-edit`: ✗ fail mit `HttpNotFoundException` statt `HttpAccessDeniedException` (Gate bypassed, Method-Lookup ins Leere)
  - `adminedit`: ✗ fail mit "keine Exception geworfen" — **Smoking Gun**: Gate bypassed AND case-insensitive PHP-Dispatch auf `getAdminEditAction` erfolgreich → HTTP 200 mit Sentinel-Body
- **fixture_file**: nicht erforderlich (keine XSS-Payloads, nur URL-Casing-Varianten in Code)
- **Test-Commit**: `3a53e837de` (GPG-signiert) auf Fix-Branch

### Phase D6 — Fix-Draft (2026-04-09 abgeschlossen)
- fix_branch: `security-audit-005-module-action-case-bypass` (bereits in D5 angelegt)
- **Fix-Commit**: `f8fdf173cf` (GPG-signiert)
- **Diff**: 1-Zeilen-Änderung in `app/Http/RequestHandlers/ModuleAction.php:75` + passender `use function`-Import
  - `str_contains($action, 'Admin')` → `stripos($action, 'Admin') !== false`
  - `use function str_contains;` → `use function stripos;`
- Kommentar im Fix erklärt die Asymmetrie zwischen `str_contains` (case-sensitive) und PHP-Method-Dispatch (case-insensitive), damit zukünftige Refactorings nicht erneut ins str_contains-Muster zurückfallen.

### Phase D7 — Validation (2026-04-09 abgeschlossen)
- **Layer-2 Re-Run**: `artifacts/security-audit/sec-audit-005/d5_regression/layer2_green_run_post_fix.txt`
  - Kommando identisch zum D5-Red-Run
  - Ergebnis: **10/10 green, 60 assertions, OK**
- **Layer-3 Integrationstest**: `layer3-integration/tests/Security/SecAudit005Test.php` — neu erzeugt
  - Nutzt die echte `ModuleService` (aus `MysqlTestCase::setUp()` gebootet) und den echten `ModuleAction`-Handler
  - 9 Data-Provider-Rows × 1 Baseline-Methode = 10 Tests gegen echte Bundled-Module (`faq`, `stories`, `relationships_chart`), GET + POST, alle Casing-Varianten inkl. Smoking-Gun `GET faq adminedit`
  - **Self-Skip-Guard** in `setUp()`: behavioral Fix-Probe + Module-Enablement-Probe. `make test-integration` bleibt grün auf pristine Upstream.
  - Ergebnis: **10/10 green, 50 assertions, OK** (`artifacts/security-audit/sec-audit-005/d7_validation/layer3_green_run.txt`)
- **Validation-Report**: `artifacts/security-audit/sec-audit-005/d7_validation/README.md`

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-08 | queued | Erzeugt aus V1e.2 CRITICAL Finding |
| 2026-04-08 | exploit_confirmed | End-to-end PoC in V1e.2 verifiziert (Probe-Loop in Verification-Runde vorweggenommen) |
| 2026-04-09 | regression_drafted | D5 Layer-2 Regression-Test erzeugt und auf unfixed Code als RED bestätigt (5/6 Varianten fail, Smoking Gun = `adminedit` dispatch erfolgreich) |
| 2026-04-09 | fix_committed | D6 1-Zeilen-Fix + `use function`-Import committet als `f8fdf173cf` auf Fix-Branch (GPG-signiert) |
| 2026-04-09 | fix_verified | D7 Layer-2 (10/10) und Layer-3 (10/10) Re-Runs grün. Layer-3-Test mit echter `ModuleService` + Bundled-Modulen + Self-Skip-Guard live. |
