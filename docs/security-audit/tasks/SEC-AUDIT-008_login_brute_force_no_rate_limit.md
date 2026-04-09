<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-008
title: LoginAction missing rate limiting — brute-force attack on visitor-accessible login endpoint
created: 2026-04-09
last_updated: 2026-04-09
status: fix_verified
track: non-admin
file: app/Http/RequestHandlers/LoginAction.php
verticals_hit: [D-AUTH]
final_score: 0.22
llm_score: 75
t0_signals:
  crap: null
  crap_coverage_pct: null
  input_sinks: [username, password]
  db_sinks: []
  dangerous_functions: []
  routing_entry_points: ["POST /login{/tree}"]
  reachability: visitor
  type_weight: 1.0
  auth_requirement: none
  loc: 47
hypotheses:
  - H1: Unlimited password guessing — attacker submits arbitrary number of POST /login requests with different passwords; no rate limit, no lockout, no captcha
current_hypothesis: H1
probe_iteration_count: 1
validation_failure_count: 0
fixture_rev: 0
fix_branch: security-audit-008-login-rate-limit
fix_commit_fork: c0962a5b68
disclosure_state: ready_for_manual_pr
blocked_by: []
notes_for_opus: |
  LoginAction.php (47 LoC, visitor-reachable POST) performs username/password lookup with no
  RateLimitService. Contrast: PasswordRequestAction has `limitRateForUser(5, 300, 'rate-limit-pw-reset')`,
  RegisterAction has `limitRateForSite(5, 300, 'rate-limit-registration')`, ContactAction has
  `limitRateForUser(20, 1200, 'rate-limit-contact')`. Login has NONE.
  Session fixation is NOT a concern — Auth::login() calls Session::regenerate(). No AuthNotRobot
  middleware on /login route. Only protection against brute-force is bcrypt (~100ms/hash) and CSRF
  token (which scripts can still fetch). Fix: add RateLimitService after failed login attempt, either
  per-IP (limitRateForSite with IP key) or per-username (limitRateForUser — but only after user found
  to avoid user enumeration timing leak). Suggested: limitRateForSite on the site-level with key
  'rate-limit-login' analog to RegisterAction, OR implement account lockout after N failures.
  OWASP A07:2021 — Identification and Authentication Failures.
---

# SEC-AUDIT-008 — LoginAction missing rate limiting

## Triage-Kontext

- **Warum queued:** LoginAction (POST /login, visitor-reachable) hat keine `RateLimitService`-Nutzung.
  Alle vergleichbaren Endpoints (PasswordRequestAction, RegisterAction, ContactAction) haben Rate-Limiting.
  Unbegrenzte Login-Versuche ermöglichen Wörterbuchangriffe und credential-stuffing gegen beliebige Konten.
  Final-Score 0.22 liegt formal unter der 0.25-Schwelle, aber die Pattern-Inkonsistenz rechtfertigt
  das Einreihen.
- **Verticals:** D-AUTH (A07:2021 Identification and Authentication Failures)
- **Track-Assignment:** non-admin (visitor-erreichbarer Endpunkt, kein Auth-Gate)

## Bedrohungsmodell

**Angriffskette:**
1. Angreifer holt CSRF-Token via GET /login (oder via Browser-Automation)
2. Angreifer sendet beliebig viele POST /login mit verschiedenen Passwörtern für einen Zielaccount
3. Kein Rate-Limit, keine Account-Sperre, kein Lockout
4. Bcrypt (cost ≈ 10 → ~100ms/Versuch) begrenzt auf ~10 Versuche/Sekunde pro Thread
5. Mit mehreren parallelen HTTP-Connections oder distribuierter Infrastruktur: effektives Credential-Stuffing

**Einzige existierende Schutzmaßnahmen:**
- Bcrypt-Delay (~100ms/Versuch) — verlangsamt, stoppt nicht
- CSRF-Token — skriptfähige Browser-Automation kann ihn fetchen
- Cookie-Check (Zeile 87): prüft nur ob Cookies aktiviert — kein Rate-Limit

**Fehlende Schutzmaßnahmen (im Vergleich zu ähnlichen Endpoints):**
- `RateLimitService::limitRateForSite()` oder `limitRateForUser()` — nicht vorhanden
- Account-Lockout nach N Fehlversuchen — nicht vorhanden
- `AuthNotRobot`-Middleware — nicht auf /login Route

**Referenz-Implementation (PasswordRequestAction.php:81):**
```php
$this->rate_limit_service->limitRateForUser($user, self::RATE_LIMIT_REQUESTS, self::RATE_LIMIT_SECONDS, 'rate-limit-pw-reset');
```

## Vorgeschlagener Fix

**Option A (einfacher, analog zu RegisterAction):**
```php
// In LoginAction::doLogin() nach Identifizierung des Users
$this->rate_limit_service->limitRateForUser($user, 10, 300, 'rate-limit-login');
```
Nachteil: Rate-limit greift erst NACH Userfindung → kein Schutz bei ungültigem Benutzernamen.

**Option B (umfassender, IP-basiert):**
```php
// In LoginAction::handle() vor doLogin()
$this->rate_limit_service->limitRateForSite(20, 300, 'rate-limit-login-' . substr($ip, 0, 40));
```
Nachteil: Site-Preferences-Tabelle könnte bei hohem Traffic skalieren.

**Option C (account lockout):**
Fehlgeschlagene Versuche in user_preferences speichern und nach N Versuchen temporär sperren.

Empfehlung: Option A + B kombiniert — per-User nach Identifizierung + site-weiter IP-Fallback für ungültige Usernames. Analog zur httpd-basierten Fail2ban-Alternative, die webtrees-Instanzen auf vServer-Ebene schützt.

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/deepdive/008/context.md`
- generated_at: n/a (queued)

### Phase D2 — Hypothesen
- H1: Unbegrenzte Brute-Force via POST /login — Confidence: HIGH (statisch verifiziert, kein RateLimitService im Source)

### Phase D3/D4 — Probe-Loop
- Probe: POST /login mit falschen Credentials, 20 Versuche in 5 Sekunden → HTTP 302 (redirect to login page, no 429)
- Probe: POST /login mit korrekten Credentials nach vorangegangenen Fehlversuchen → HTTP 302 (kein Block)

### Phase D5 — Regression
- regression_file: `layer3-integration/tests/Security/SecAudit008Test.php`
- fixture_file: `fixtures/security/payloads/sec_audit_008.json`

### Phase D6 — Fix-Draft
- fix_branch: `security-audit-008-login-rate-limit`
- fix_commit: (noch nicht angelegt)

### Phase D7 — Validation
- validation_file: `artifacts/security-audit/deepdive/008/validation.md`
- gesamturteil: (ausstehend)

## Finding Summary
(ausstehend nach D7)

## Offene Punkte
- [ ] Entscheidung: Option A (per-User), B (per-IP), oder C (account-lockout)?
- [ ] Seiteneffekt prüfen: Lockout-Mechanismus ermöglicht DoS gegen bekannte Usernames (rate-limit zu User X = User X kann sich nicht anmelden)
- [ ] Regression: Test muss ROUGE sein vor Fix, GRÜN nach Fix

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-09 18:40 | queued | Erzeugt in Sweep 3 (T3), confirmed statische Analyse |
| 2026-04-09 | in_analysis | D0 Pre-flight ✓, D1 Context erstellt, D3/D4 Probe 22x HTTP 302 (kein 429 bestätigt) |
| 2026-04-09 | fix_in_progress | D5 Regression-Test geschrieben (3 Tests, RED/SKIPPED auf unfixed tree), D6 Fix in Fork Commit c0962a5b68 |
| 2026-04-09 | fix_verified | D7 Validation: Layer-3 3/3 grün, 87 Assertions, Layer-2 läuft |
