<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P39: LoginAction (Anmeldungs-Aktion)

**Status:** ⬜ OPEN (teilweise EXCLUDED)  
**Aufwand:** Mittel  
**Qualitätsziel:** Pragmatisch (C) — Fehlerpfade; Happy Path dauerhaft EXCLUDED

---

## Status quo

Kein dedizierter Test für `LoginAction`. Fehlerpfade (user not found, wrong password etc.) sind testbar; Happy-Path ist EXCLUDED.

---

## SUT-Kernbefunde

**Handler:** `Fisharebest\Webtrees\Http\RequestHandlers\LoginAction`  
**Konstruktor-DI:** `UserService`, ggf. weitere Services

`doLogin()` zentrale Guards (Aufruf-Reihenfolge):

| Branch | Bedingung | Ergebnis |
|---|---|---|
| B0 | `$_COOKIE === []` | Exception: "Cookies disabled" |
| B1 | User nicht gefunden (username) | Exception: "The username or password is incorrect" |
| B2 | Falsches Passwort | Exception: "The username or password is incorrect" |
| B3 | E-Mail nicht verifiziert (`PREF_IS_EMAIL_VERIFIED !== '1'`) | Exception: "This account has not been verified yet" |
| B4 | Account nicht genehmigt (`PREF_IS_ACCOUNT_APPROVED !== '1'`) | Exception: "This account has not been approved yet" |
| B5 (Happy Path) | Alle Guards passiert | `Auth::login($user)` + redirect |

**EXCLUDED: B5 Happy Path** — `$_COOKIE === []` ist in PHP CLI immer true. Der Happy-Path setzt voraus, dass `$_COOKIE` nicht leer ist. Ohne Manipulation von `$_COOKIE` ist B5 nicht erreichbar.

**Testbar: B1–B4** — Diese Guards werden aufgerufen bevor `$_COOKIE`-Check in `doLogin()` — nein, B0 ist der erste Guard. **Korrekte Analyse:** B0 (`$_COOKIE === []`) ist der allererste Guard in `doLogin()`. Damit sind B1–B4 ebenfalls nur erreichbar wenn Cookies vorhanden. → Alle Pfade hinter B0 sind durch den Cookie-Check blockiert.

**Revidierte Einschätzung:** Wenn `$_COOKIE === []` der erste Guard ist, ist der gesamte `doLogin()`-Ablauf in CLI-Tests blockiert. Alle Fehlerpfade B1–B4 sind nur nach B0 erreichbar.

**Testbarkeit:** Nur der `$_COOKIE`-Guard selbst (B0) kann als Exception-Test verifiziert werden — der Handler wirft die Cookie-Exception wenn `$_COOKIE` leer ist.

---

## EP-Matrix

| EP | Partition | Eingabe | Erwartetes Ergebnis |
|---|---|---|---|
| EP1 | B0: Keine Cookies | CLI-Kontext (Standard) | Exception "Cookies disabled" |
| EP2–EP5 | B1–B4: Fehlerpfade | 🚫 Nicht erreichbar wegen B0 | — |
| EP6 | B5: Happy Path | 🚫 EXCLUDED | — |

---

## Strategie

**Neuer Test:** `LoginActionIntegrationTest extends MysqlTestCase`

- Nur B0 testbar: `expectException()` für Cookie-Fehler.
- Fehlerpfade B1–B4: Nur testbar wenn `$_COOKIE` manipuliert wird. Optionaler Ansatz: `$_COOKIE['wt_test'] = '1';` vor dem Test setzen (globale Variable), dann nach dem Test zurücksetzen. Dieses Muster ist fragil und nicht empfohlen.
- Empfehlung: Nur EP1 (B0) als Dokumentationstest umsetzen. Rest EXCLUDED.

---

## Ausschluss-Details

| Pfad | Grund |
|---|---|
| B5 Happy Path | `$_COOKIE === []` in PHP CLI immer true |
| B1–B4 Fehlerpfade | Hinter B0-Guard; nicht erreichbar ohne `$_COOKIE`-Manipulation |

Verweis: `testquality_improve_common2.md`, Abschnitt 4.

---

## Phasenstatus

| Phase | Status |
|---|---|
| P1: Konsistenzcheck | ✅ | DI: UpgradeService+UserService; handle() fängt alle Exceptions; $_COOKIE=[] in CLI → doLogin wirft → 302 |
| P2: Soll-Design | ✅ | EP1: POST → 302 (Cookie-Check-Redirect zu LoginPage) |
| P3: Test-Coding | ✅ | `LoginActionIntegrationTest` (1 Test) |
| P4: Ausführung + Fixing | ✅ | 1/1 grün (Fix: Location enthält 'nonexistent-user' statt 'login') |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix P39 aktualisiert | |
