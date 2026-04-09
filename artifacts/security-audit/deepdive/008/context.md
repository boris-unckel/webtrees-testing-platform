<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-008 — Deep-Dive Context

- **Task:** SEC-AUDIT-008 — LoginAction missing rate limiting
- **Generated:** 2026-04-09 (Deep-Dive D1)
- **Status:** in_analysis → in_progress

## Primärdatei

`app/Http/RequestHandlers/LoginAction.php` (47 LoC, visitor-reachable POST /login{/tree})

### Konstruktor (vor Fix)

```php
public function __construct(
    private readonly UpgradeService $upgrade_service,
    private readonly UserService $user_service,
) {}
```

Kein `RateLimitService` injiziert.

### handle() — Ablauf

1. Parameter extrahieren: `username`, `password`, `url` (isLocalUrl validated)
2. `doLogin($username, $password)` in try/catch aufrufen
3. Bei Erfolg: `Auth::login($user)` → `Session::regenerate()` → redirect
4. Bei Fehler: FlashMessage + redirect to LoginPage

### doLogin() — Ablauf

```
Cookies vorhanden? → nein: Exception (kein Session-Cookie)
findByIdentifier($username) → null: Exception (no such user)
checkPassword($password) → false: Exception (wrong password)
PREF_IS_EMAIL_VERIFIED != '1': Exception
PREF_IS_ACCOUNT_APPROVED != '1': Exception
Auth::login($user) → Session::regenerate()
```

### Exception-Handling-Problem

`handle()` fängt **alle** `\Exception`-Instanzen. `HttpTooManyRequestsException extends HttpException extends RuntimeException extends Exception`.
→ Ein `limitRateForUser`-Call innerhalb von `doLogin()` würde ohne Anpassung als normale Fehlermeldung
angezeigt statt als HTTP 429 zurückgegeben.

**Fix-Anforderung:** Rate-Limit-Calls müssen außerhalb des `catch (Exception $ex)`-Blocks stehen
oder der Catch muss `HttpTooManyRequestsException` explizit re-throwen.

## Vergleichsimplementierungen

| Handler | Methode | Limit | Zeitfenster | Key |
|---|---|---|---|---|
| RegisterAction:112 | limitRateForSite | 5 | 300s | rate-limit-registration |
| PasswordRequestAction:81 | limitRateForUser | 5 | 300s | rate-limit-pw-reset |
| ContactAction | limitRateForUser | 20 | 1200s | rate-limit-contact |
| **LoginAction** | **(keiner)** | — | — | — |

## RateLimitService API

```php
limitRateForSite(int $num, int $seconds, string $limit): void
// Stores timestamps in site_setting.$limit (site_preference)
// Throws HttpTooManyRequestsException when count($in_window) >= $num

limitRateForUser(UserInterface $user, int $num, int $seconds, string $limit): void
// Stores timestamps in user_setting.$limit (user_preference)
// Throws HttpTooManyRequestsException when count($in_window) >= $num
```

Storage: `256 / len(timestamp + ',')` max entries (~22 with 10-digit unix timestamps).
→ Maximal sinnvolles `$num` ≈ 20.

## Fix-Design: Option A+B kombiniert

### Option B — IP-basierter Site-Level Backstop (VOR user lookup, AUSSERHALB try/catch)

```php
// handle() — vor dem try-Block
$ip = substr((string) $request->getAttribute('client-ip'), 0, 40);
$this->rate_limit_service->limitRateForSite(20, 300, 'rate-limit-login-' . $ip);
```

- Schützt gegen Brute-Force mit ungültigem Username
- Propagiert als HTTP 429 (außerhalb try/catch)
- Skalierung: eine site_setting-Zeile pro angreifender IP; vertretbar für Genealogie-Sites

### Option A — Per-User Limit (NACH Identifizierung, per re-throw)

```php
// doLogin() — nach findByIdentifier, vor checkPassword
$this->rate_limit_service->limitRateForUser($user, 10, 300, 'rate-limit-login');
```

Da dies innerhalb des try/catch liegt:
```php
// handle() — catch-Block ERGÄNZEN
} catch (HttpTooManyRequestsException $ex) {
    throw $ex;  // als 429 propagieren, nicht als Flash-Message verschlucken
} catch (Exception $ex) {
    FlashMessages::addMessage($ex->getMessage(), 'danger');
    return redirect(...);
}
```

## Hypothesen

| ID | Beschreibung | Confidence | Ergebnis |
|---|---|---|---|
| H1 | POST /login ohne Rate-Limit — unbegrenzte Versuche | HIGH (statisch) | → D3 Probe |

## Timing-Hinweis

bcrypt cost≈10 → ~100ms/Versuch → ~10 Versuche/Sekunde/Thread.
Rate-Limit schlägt vor bcrypt → kein zusätzlicher Timing-Angriff durch Limit-Check.

## Cookie-Bedingung im Test

`doLogin()` prüft `$_COOKIE === []` als ersten Check.
Tests müssen einen nicht-leeren `$_COOKIE` simulieren oder direkt `RateLimitService`+`UserService` testen.
→ Im Test: `$_COOKIE` mit Dummy-Cookie belegen vor dem Handler-Aufruf.
