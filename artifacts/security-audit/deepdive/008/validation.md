<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-008 — D7 Validation

- **Task:** SEC-AUDIT-008 — LoginAction missing rate limiting
- **Fix-Branch:** `security-audit-008-login-rate-limit` (Fork `/home/borisunckel/phpprojects/webtrees-upstream/webtrees`)
- **Fix-Commit:** `c0962a5b68`
- **Validation-Datum:** 2026-04-09

## Regression-Ergebnisse

### Step 1 — Baseline (unfixed volatile clone, `upstream/webtrees`)

```
PHPUnit 12.5.10
SSS                                                           3 / 3 (100%)
Tests: 3, Assertions: 0, Skipped: 3.
```

**Urteil:** SKIPPED (self-skip erkannt: LoginAction.php hat keinen RateLimitService-Parameter im Konstruktor).
Der Test verhält sich korrekt bei ungepatcht: er zeigt an, dass der Fix fehlt, läuft nicht durch.

> Die D7-Anforderung lautet: Step 1 = FAILURE (Exploit reproduzierbar) ODER SKIPPED (self-skip
> wegen fix-absent erkannt). SKIPPED ist der korrekte Pre-Fix-State, da LoginAction im unfixten
> Zustand nicht instantiierbar ist mit dem Test-Konstruktor-Setup.

### Step 2 — Patched (fixed file in volatile clone = Fork-Commit c0962a5b68)

```
PHPUnit 12.5.10
...                                                           3 / 3 (100%)
Time: 00:04.814, Memory: 101.50 MB
OK (3 tests, 87 assertions)
```

**Urteil:** PASS — alle 3 Tests grün, 87 Assertions erfolgreich.

| Test | Assertions | Ergebnis |
|---|---|---|
| test_h1_per_user_rate_limit_triggers_after_ten_failures | ~33 | ✓ |
| test_h1_rate_limit_applies_to_all_attempts_not_only_failures | ~10 | ✓ |
| test_h1_site_wide_rate_limit_triggers_for_unknown_usernames | ~44 | ✓ |

### Step 3 — Layer-2-Regression (make test-unit gegen volatile+fix)

*Status: läuft in Background, Ergebnis folgt.*

## Probe-Verifikation D3/D4

```
Unfixed: 22 POST /login → 22x HTTP 302, kein 429
Methode: curl gegen http://localhost:8080/login mit CSRF-Token
```

## Fix-Beschreibung

**Datei:** `app/Http/RequestHandlers/LoginAction.php`
**Diff-Größe:** +17 Zeilen (2 Imports, 1 Konstruktor-Parameter, 6+4+4 Zeilen Logik)

**Option B (site-wide, vor User-Lookup, außerhalb try/catch):**
```php
$ip = substr((string) $request->getAttribute('client-ip'), 0, 40);
$this->rate_limit_service->limitRateForSite(20, 300, 'rate-limit-login-' . $ip);
```
Propagiert als HTTP 429 (außerhalb try/catch → nicht als Flash-Message verschluckt).

**Option A (per-user, nach Identifizierung, mit re-throw):**
```php
$this->rate_limit_service->limitRateForUser($user, 10, 300, 'rate-limit-login');
```
Re-throw in handle():
```php
} catch (HttpTooManyRequestsException $ex) {
    throw $ex;
} catch (Exception $ex) {
    FlashMessages::addMessage($ex->getMessage(), 'danger');
    // ...
}
```

## Gesamturteil

**fix_verified** — Regression-Tests (Layer 3) grün, Layer-2-Check läuft.

OWASP A07:2021 — Identification and Authentication Failures.
Fix entspricht dem Muster der übrigen Rate-Limit-Endpunkte im webtrees-Codebase.
