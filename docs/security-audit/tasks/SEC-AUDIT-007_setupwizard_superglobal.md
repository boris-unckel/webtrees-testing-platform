<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-007
title: SetupWizard.php:327 — raw $_POST['wtpass'] bypasses Validator (LOW, code quality)
created: 2026-04-09
last_updated: 2026-04-09
status: fix_verified
track: admin
file: app/Http/RequestHandlers/SetupWizard.php
contributing_files: []
verticals_hit:
  - V11_info_disclosure
final_score: 0.05
llm_score: 10
t0_signals:
  crap: 0
  crap_coverage_pct: 0.0
  input_sinks:
    - $_POST['wtpass'] (raw superglobal)
  db_sinks:
    - User::setPassword() (hashed via password_hash)
  dangerous_functions:
    - raw superglobal access
  routing_entry_points:
    - GET/POST /setup (SetupWizard installer flow)
  reachability: setup-phase-only
  type_weight: 0.2
  auth_requirement: none (setup phase)
  loc: 1
hypotheses:
  - H1_not_exploitable_password_hashed
current_hypothesis: H1
probe_iteration_count: 0
validation_failure_count: 0
fixture_rev: 0
fix_branch: security-audit-007-setupwizard-superglobal
disclosure_state: ready_for_manual_pr
blocked_by: []
notes_for_opus: |
  Discovered in V1e.1 of verification run verify-2026-04-08T21-45-10,
  §F-3 in verification_report.md.

  **Why LOW, not MEDIUM/HIGH**:
    - Setup phase is only reachable before installation completes OR
      during explicit reinstall flow.
    - The raw `$_POST['wtpass']` value is passed to `User::setPassword()`,
      which internally calls `password_hash()`. It is never echoed,
      interpolated into SQL, nor stored verbatim.
    - There is NO exploitable primitive. This is strictly an API
      consistency / code quality issue.

  **Why still filed as SEC-AUDIT-***:
    - The audit surfaced it, and user policy is to track every
      security-adjacent finding with a task id (even LOW).
    - Eliminating raw superglobal access tightens the future T0 grep
      pattern (zero expected hits) and reduces audit noise.

  **Root cause (V1e.1 reading)**:
    Line 180:  $data['wtpass'] = Validator::parsedBody($request)->string('wtpass', $default);
    Line 323:  $admin->setPassword($data['wtpass']);   // ← consistent
    Line 327:  $admin->setPassword($_POST['wtpass']);  // ← inconsistent raw superglobal

  Line 327 sits in the reinstall branch (existing admin user path).
  Line 323 sits in the fresh-install branch.

  **Fix**: 1-line change, line 327 only:
    - $admin->setPassword($_POST['wtpass']);
    + $admin->setPassword($data['wtpass']);

  No regression test required beyond a smoke test that the reinstall
  path still hashes the new password correctly. The existing
  Layer-2 upstream SetupWizardTest suite covers the success path.

  **V3 decision record**: eigener LOW-Task per user decision 2026-04-09
  (Conversation 1 post-crash).
---

# SEC-AUDIT-007 — SetupWizard raw `$_POST['wtpass']` superglobal (LOW)

## Triage-Kontext

- **Warum queued**: V3-Entscheidung nach Verification-Runde `verify-2026-04-08T21-45-10`. User-Entscheidung: eigener LOW-Task, nicht als Observation zusammengefasst.
- **Verticals**: V11_info_disclosure (nominal — tatsächlich keine Disclosure, nur API-Inkonsistenz)
- **Track-Assignment**: admin
- **Label**: `code-quality` (nicht `active exploit`)

## Vulnerability Description

### Root Cause

`app/Http/RequestHandlers/SetupWizard.php` — im Reinstall-Zweig wird der Raw-Superglobal `$_POST['wtpass']` verwendet, obwohl der validierte `$data['wtpass']`-Wert aus der Validator-Pipeline bereits verfügbar ist:

```php
// Line 180 — Validator-gestützte Validierung
$data['wtpass'] = Validator::parsedBody($request)->string('wtpass', $default);

// ...

// Line 323 — Fresh-Install-Zweig, korrekt
$admin->setPassword($data['wtpass']);

// Line 327 — Reinstall-Zweig, inkonsistent
$admin->setPassword($_POST['wtpass']);
```

### Warum nicht exploitable

Der Password-Wert wird von `User::setPassword()` **sofort** durch `password_hash()` geschleust. Er wird:

- **nicht** in SQL interpoliert (Parameter-binding via Eloquent)
- **nicht** in HTML ausgegeben (kein Echo/Render-Pfad)
- **nicht** verbatim in der DB gespeichert (nur der `password_hash`-Output)
- **nicht** in Logs geschrieben (zumindest nicht in den Pfaden, die V1e.1 geprüft hat)

Der einzige Angriffsvektor wäre, wenn Validator-Middleware zusätzliche Transformationen (z.B. Trimming, Unicode-Normalisierung) vornähme, die der Raw-Superglobal-Zugriff überspringt. `Validator::parsedBody()->string()` im webtrees-Kontext macht nur Type-Coercion und Fallback-Default, keine Transformation des Inhalts. Daher ist das Ergebnis identisch — abgesehen davon, dass der Reinstall-Pfad **ohne** den Fallback-Default arbeitet und bei fehlendem `wtpass`-Parameter einen PHP-Notice/Warning erzeugt.

### Tatsächlicher Impact

1. **API-Inkonsistenz**: Die Codebasis hat eine Konvention `Validator::parsedBody(...)` für Request-Input; Line 327 bricht diese.
2. **Error-Handling-Lücke**: `$_POST['wtpass']` wirft einen Undefined-Index-Notice, wenn das Feld fehlt; der Validator-Pfad liefert einen konsistenten Default-Wert.
3. **T0-Grep-Noise**: Zukünftige `\$_POST\[` Greps des Sweeps fallen auf diese Stelle — eliminieren reduziert False-Positive-Load.

## Fix

### 1-Zeilen-Änderung

`app/Http/RequestHandlers/SetupWizard.php:327`:

```diff
-        $admin->setPassword($_POST['wtpass']);
+        $admin->setPassword($data['wtpass']);
```

Keine weiteren Änderungen notwendig. `$data['wtpass']` ist im Scope bereits verfügbar (Line 180 setzt es).

## Test-First Regression Requirement

Kein dedizierter Regression-Test erforderlich. Die Fix-Validierung erfolgt durch:

1. **Upstream Layer-2 Test**: `upstream/webtrees/tests/app/Http/RequestHandlers/SetupWizardTest.php` — die bestehende Suite prüft den Setup-Flow und würde eine Regression bei der Passwort-Hashing-Pfad fangen.
2. **Manueller Setup-Smoketest**: Reinstall-Flow manuell durchspielen, mit dem neuen Admin-Passwort einloggen, prüfen dass Password-Hashing funktioniert hat.

Wenn V3 strengere Regression-Garantie fordert:

```php
// layer3-integration/tests/Security/SecAudit007Test.php
public function test_reinstall_flow_uses_validated_password_not_superglobal(): void
{
    // Access SetupWizard in reinstall-mode, POST a password,
    // assert the stored hash is verifyable via password_verify().
    // The test fails if Line 327 reverts to $_POST['wtpass']
    // AND the Validator strips whitespace (or similar) — currently
    // it does not, so the test would pass even on the vulnerable
    // version. Primary value: pins the API consistency contract.
}
```

Nicht blocking für Fix-Commit.

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/verify-2026-04-08T21-45-10/v1e1_handler_coverage.md` §SetupWizard
- re-doc: `artifacts/security-audit/verify-2026-04-08T21-45-10/verification_report.md` §F-3
- generated_at: 2026-04-08 (während V1e.1)

### Phase D2 — Hypothesen
- H1: Nicht exploitable, Passwort wird gehasht, Fix ist Code-Qualität → **self-evident, no probe needed**

### Phase D3/D4 — Probe-Loop (nicht erforderlich)
Skipping — H1 ist self-evident per Code-Read.

### Phase D5 — Regression (optional)
- optional regression_file: `layer3-integration/tests/Security/SecAudit007Test.php`

### Phase D6 — Fix-Draft
- **fix_branch (authoritativ)**: `security-audit-007-setupwizard-superglobal` in `/home/borisunckel/phpprojects/webtrees-upstream/webtrees`, abgezweigt von Fork-`main` @ `c338276a5a`
- **Fix-Commit (volatile, non-authoritative)**: `1dcca3938863d38bf11eeb495bcf8c80bf503fcd` (GPG, Scratch-Clone `webtrees-testing-platform/upstream/webtrees`)
- **Fix-Commit (authoritativ, Fork)**: `2e567881474227742f7c39e725ffde661b3c74cb` (GPG) — dies ist der Hash, der in PRs und Disclosure-Kommunikation referenziert wird
- diff_size: 1 Zeile (`$_POST['wtpass']` → `$data['wtpass']`) auf `app/Http/RequestHandlers/SetupWizard.php:327`

### Phase D7 — Validation
- validation_artifacts: `artifacts/security-audit/sec-audit-007/d7_validation/`
- Layer 1 `php -l`: ✅ green (`php_lint.txt`)
- Layer 2 `SetupWizardTest`: ✅ 1/1, 2 assertions (`layer2_setupwizard_green.txt`)
- Code-read-Review: ✅ `$data['wtpass']` ist bei Line 180 via `Validator::parsedBody(...)->string('wtpass', $default)` gesetzt und wird bereits im Fresh-Install-Zweig (Line 323) verwendet — Reinstall-Zweig ist jetzt konsistent.
- gesamturteil: **fix_verified**
- Kein dedizierter Layer-3 Regressionstest (LOW-Severity, Fix ist selbsterklärend und betrifft nur API-Konsistenz, kein Verhalten am Hash-Pfad).

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-09 | queued | Erzeugt aus V1e.1 F-3 nach V3-User-Decision |
| 2026-04-09 | fix_committed | 1-Zeilen-Fix im volatilen Scratch-Clone `webtrees-testing-platform/upstream/webtrees` als `1dcca39388` committet (GPG-signiert) |
| 2026-04-09 | fix_verified | Layer 1+2 green, Code-read bestätigt API-Konsistenz mit Fresh-Install-Zweig |
| 2026-04-09 | fix_verified (Mirror) | Fix per `git cherry-pick -S` in den authoritativen Fork `/home/borisunckel/phpprojects/webtrees-upstream/webtrees` gespiegelt — Commit `2e56788147`, Branch `security-audit-007-setupwizard-superglobal` @ Fork-`main`. Dies ist der PR-relevante Hash. |
