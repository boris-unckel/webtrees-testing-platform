<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Verification Report — run verify-2026-04-08T21-45-10

**Parent sweep**: 2026-04-08T20-58-28 (run-summary `clean_post_fix`)
**Verification scope**: 6 Teilrunden (V1a–V1e.3) + Konsolidierung (V2)
**Outcome**: **1 CRITICAL Finding, 1 Defense-in-Depth Finding, 11 Testlücken, 3 Sweep-Methodik-Korrekturen**

Dieser Report konsolidiert alle Unterrunden zu einem vollständigen Bild. Für Details jeweils auf die einzelnen `v1x_*.md`-Dateien verwiesen. Alle Findings haben ihre Schweregrad-Einstufung, ihre PoC-Referenz und ihren Fix-Pfad hier.

## Überblick

| Runde | Ziel | Ergebnis | Artefakt |
|---|---|---|---|
| V0 | Pre-Flight | Stack health OK, Run-Dir angelegt | `.run-meta.json` |
| V1a | ExpressionLanguage + ReportParserGenerate Deep-Verify | **1 Claim refuted**, 1 confirmed mit Refinement, kein Live-Exploit | `v1a_reportparser_findings.md` |
| V1b | Raw-SQL Audit across app/Services + Handlers | **1 Defense-in-Depth Gap** (RenumberTreeAction), kein Live-SQLi | `v1b_raw_sql_audit.md` |
| V1c | HtmlService::sanitize() Review | **Confirmed** mit 2 LOW-Nits, keine Tasks | `v1c_htmlservice_review.md` |
| V1e.1 | Handler Coverage Audit (335 files) | 1 MEDIUM (SetupWizard Superglobal), 1 HIGH-but-mitigated (Re-Bestätigung V1b) | `v1e1_handler_coverage.md` |
| V1e.2 | Middleware Coverage Audit (34 files) | **1 CRITICAL Finding (SEC-AUDIT-005)**, 6 MEDIUM Observations | `v1e2_middleware_coverage.md` |
| V1e.3 | Layer-3 Test Coverage Audit | **11 Testlücken**, davon 1 CRITICAL (ModuleAction-Bypass nicht getestet) | `v1e3_layer3_test_coverage.md` |

## Konsolidierte Findings

### F-1 (CRITICAL): SEC-AUDIT-005 — ModuleAction substring-admin-gate bypass

**Quelle**: V1e.2
**Artefakt**: `v1e2_middleware_coverage.md` §F-V1e2-CRITICAL
**Schweregrad**: CRITICAL
**CVSS 3.1 Base**: 8.1 (High) bei Default-Install, 9.4 (Critical) wenn `custom-css-js`-Modul aktiviert
**Vektor**: AV:N / AC:L / PR:N / UI:N / S:U / C:H / I:H / A:L
**Reach**: unauthenticated visitor
**Exploit-Status**: **End-to-end PoC verifiziert**

**Root Cause**:
```php
// app/Http/RequestHandlers/ModuleAction.php:75
if (str_contains($action, 'Admin') && !Auth::isAdmin($user)) {
    throw new HttpAccessDeniedException('Admin only action');
}
// ... later:
if (!method_exists($module, $method)) { ... }
return $module->$method($request);
```

`str_contains` ist **case-sensitive**, aber `method_exists()` und PHP-Method-Dispatch sind **case-insensitive**. Ein Angreifer, der `/module/faq/adminedit` statt `/module/faq/AdminEdit` aufruft:
1. Umgeht den Gate (kein `Admin` im `$action`, weil nur Kleinbuchstaben)
2. PHP löst trotzdem `postadminedit` auf die Methode `postAdminEditAction` auf
3. Die Admin-Methode läuft durch

**Verifizierte Reach-Modulmethoden (Default-Install)**:
- `FrequentlyAskedQuestionsModule::postAdminDeleteAction` → Löscht FAQ-Blocks ohne Auth
- `FrequentlyAskedQuestionsModule::postAdminEditAction` → Erstellt/Ändert FAQ-Blocks
- `StoriesModule::postAdminDeleteAction` → Löscht Stories
- `RelationshipsChartModule::postAdminAction` → Ändert Tree-Preferences aller Trees

**PoC-Transkript** (aus `v1e2_middleware_coverage.md`):
```
GET /module/faq/adminedit              → HTTP 200 + CSRF token (Admin-Edit-Form sichtbar)
POST /module/faq/admindelete?block_id=999999
    + CSRF + Session-Cookie            → HTTP 302 Location: /module/faq/Admin
                                          (postAdminDeleteAction completion redirect)
```

**Fix**:
- **Minimum** (1 Zeile): `str_contains($action, 'Admin')` → `stripos($action, 'Admin') !== false` bei ModuleAction.php:75
- **Defense-in-Depth**: jede `post*Admin*Action`/`get*Admin*Action`-Methode soll `Auth::isAdmin($user)` im eigenen Methodenrumpf prüfen

**Task-Empfehlung**: Als `SEC-AUDIT-005` in `priorities.md` einbauen (V3). Regression-Test-First: `TEST-V1e3-MA1` (siehe Gap MA1 unten) muss **vor** dem Fix geschrieben werden und fail-then-pass sein.

**Zusätzlicher Befund (V1e.3)**: `upstream/webtrees/tests/app/Http/RequestHandlers/ModuleActionTest.php::testAdminAction` existiert und testet `action='Admin'` (Großbuchstabe). **Er testet die Casing-Lücke nicht.** Das ist das deutlichste Scheinsicherheits-Beispiel des gesamten Audits — ein Test mit passendem Namen, der die eigentliche Eigenschaft nicht prüft.

---

### F-2 (MEDIUM/queued): RenumberTreeAction raw `Expression()` — Defense-in-Depth Gap

**Quelle**: V1b (verifiziert in V1e.1)
**Artefakte**: `v1b_raw_sql_audit.md`, `v1e1_handler_coverage.md`
**Schweregrad**: MEDIUM (defense-in-depth), **nicht live exploitable**
**Reach**: Manager-Rolle (authenticated)

**Root Cause**: `RenumberTreeAction.php` verwendet 31× `new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', '0 @$new_xref@ INDI')")` mit PHP-String-Interpolation statt Parameter-Bindung. Die Sicherheit hängt ausschließlich von `Gedcom::REGEX_XREF = '[A-Za-z0-9:_.-]{1,20}'` in einer **anderen Datei** (`app/Gedcom.php:243`) ab.

**Aktueller Status**:
- **Nicht exploitable heute**: GEDCOM-Parser erzwingt REGEX_XREF, xref kann keine SQL-Metachars enthalten
- **Fragile**: Jede zukünftige Änderung an REGEX_XREF oder jede andere Schreib-Operation auf `i_id`/`f_id`/`s_id`/`m_id`/`o_id` ohne REGEX-Validierung macht das zum Live-SQLi-Vektor
- **Manager-Reach**: Manager ist eine nicht-triviale Privilegierungsstufe, aber kein Admin

**Fix-Empfehlung**: ersetze `REPLACE(...)`-Expressions durch Code-Side-Stringmanipulation (Gedcom laden, `str_replace` in PHP, per bound parameter zurückspeichern).

**Score-Wirkung (V1b)**: `final_score ≈ 0.263`, marginal über 0.25 Cutoff.

**V3-Entscheidung angefragt**: Soll dies als `SEC-AUDIT-006` geführt werden oder als Observation-Only geschlossen werden? V1b-Empfehlung: queued/track=admin mit Label `defense-in-depth`.

---

### F-3 (MEDIUM): SetupWizard.php:327 — Superglobal Bypass

**Quelle**: V1e.1
**Artefakt**: `v1e1_handler_coverage.md` §1
**Schweregrad**: MEDIUM (Code-Qualität), **nicht exploitable**
**Reach**: Setup-Phase (vor Installation oder Reinstall)

**Root Cause**:
```php
// app/Http/RequestHandlers/SetupWizard.php
// Line 180: $data['wtpass'] = Validator::parsedBody($request)->string('wtpass', $default);  // richtig
// Line 323: $admin->setPassword($data['wtpass']);  // richtig
// Line 327: $admin->setPassword($_POST['wtpass']);  // ← Superglobal statt $data
```

Im Reinstall-Zweig wird der raw `$_POST['wtpass']` verwendet statt dem validierten `$data['wtpass']`. Keine direkte Exploitation (das Password wird von `setPassword()` gehasht), aber API-Inkonsistenz.

**Fix**: 1-Zeilen-Änderung — `$_POST['wtpass']` → `$data['wtpass']`.

**Task-Empfehlung**: LOW-Priority Refactoring-Task in V3, **nicht** als SEC-AUDIT-Task (keine Sicherheitslücke, nur Code-Konsistenz).

---

### F-4 (OBSERVATION): V1a-Claim "Allowlist = [stristr]" refuted

**Quelle**: V1a
**Artefakt**: `v1a_reportparser_findings.md` §Claim A
**Schweregrad**: OBSERVATION (Dokumentation, keine Vuln)

**Befund**: Die `priorities.md`-Zeile 50 des Parent-Sweeps behauptet "Der Function-Allowlist ist *absichtlich* auf [stristr] beschränkt". Das ist falsch. Symfony ExpressionLanguage registriert unconditional die Defaults `constant, min, max, enum` vor der Provider-Erweiterung. Der tatsächliche Allowlist ist `[constant, min, max, enum, stristr]`.

**Live-Exploit-Status**: `constant()` ist zwar reachable im Prinzip, aber kein attacker-kontrollierter String erreicht in den 16 bundled Reports einen EL-Eval. **Kein Exploit heute.**

**Latent Footgun**: Drittmodul-Autoren, die in ihrem XML-Template `<SetVar value="$user_input + 1"/>` verwenden, öffnen den `constant()`-Zugang. Nicht unser Scope.

**Task-Empfehlung**: **Keine neue Task**, aber `priorities.md`-Korrektur in V3.

---

### F-5 (OBSERVATION): V1a LIKE-DoS im Raw-Pfad

**Quelle**: V1a
**Artefakt**: `v1a_reportparser_findings.md` §Claim C
**Schweregrad**: OBSERVATION (below cutoff)

**Befund**: `ReportParserGenerate.php:1405` — der `^LIKE /(.+)/$`-Zweig übergibt attacker-kontrollierte LIKE-Patterns direkt an `->where('i_gedcom', 'LIKE', $match[1])`. Gebundener Parameter (kein SQLi), aber **ohne Wildcard-Escape**. `%%%%%%`-Pattern würde katastrophalen Full-Table-LIKE-Scan auslösen.

**Reachability**: Kein bundled Report verwendet dieses Pattern. Drittmodule könnten es einführen.

**Task-Empfehlung**: **Keine neue Task**.

---

### F-6 (OBSERVATION): V1e.2 Middleware-Observations

**Quelle**: V1e.2
**Artefakt**: `v1e2_middleware_coverage.md`
**Schweregrad**: alle MEDIUM/LOW (keine neuen Tasks)

1. **CSP-Header fehlt** in `SecurityHeaders.php` — nur `Permissions-Policy`, `Referrer-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`. Defense-in-depth Gap. SEC-AUDIT-003 deckt den image-response-CSP; globaler CSP ist weiter gefasst.
2. **PublicFiles.php** — Substring-Check `!str_contains($path, '..')` statt echte Kanonisierung. Heute sicher (PSR-7 raw percent-encoded + PHP file-funcs dekodieren nicht), aber fragil.
3. **DebugLogger.php** — `x-debug-sql`-Response-Header mit SQL+Bindings wenn `debug=true`. Dev-only.
4. **CompressResponse.php** — BREACH-Attacke theoretisch möglich bei gzip/deflate, aber niedrige Reach ohne reflektierte Secrets im Body.
5. **CheckCsrf.php:59** — `$client_token !== $session_token` ist non-constant-time. Side-Channel-Leak möglich, praktisch nicht exploitbar.
6. **HandleExceptions.php:134** — Fallback-Pfad `response(nl2br((string) $exception), 500)` escapet nicht HTML. Fallback hat extrem niedrige Reachability (nur wenn View-Rendering selbst wirft).

**Keine neuen Tasks**, alle als Observations in V3 dokumentieren.

---

### F-7 (TESTLÜCKEN): 11 Gaps aus V1e.3

**Quelle**: V1e.3
**Artefakt**: `v1e3_layer3_test_coverage.md`
**Schweregrad**: 1 CRITICAL, 2 HOCH, 6 MEDIUM, 2 NIEDRIG

| ID | Sink | Schweregrad | V3-Task |
|---|---|---|---|
| H1 | HtmlService XSS-Matrix fehlt | HOCH | TEST-V1e3-H1 |
| S1 | SearchService Security-Tests fehlen | MEDIUM | TEST-V1e3-S1 |
| R1 | ReportParserGenerate EL-Payload-Tests fehlen | HOCH | TEST-V1e3-R1 |
| R2 | ReportExpressionLanguageProvider nur `class_exists`-Smoke | MEDIUM | TEST-V1e3-R2 |
| RT1 | RenumberTreeAction REGEX_XREF-Defense untested | MEDIUM | TEST-V1e3-RT1 |
| **MA1** | **ModuleAction case-insensitive bypass untested** | **CRITICAL** | **TEST-V1e3-MA1** |
| ML1 | AuthLoggedIn Middleware nur `class_exists`-Smoke | NIEDRIG | TEST-V1e3-ML1 |
| MC1 | CheckCsrf nur 1 Test (POST ohne Token) | MEDIUM | TEST-V1e3-MC1 |
| MP1 | PublicFiles nur `class_exists`-Smoke | MEDIUM | TEST-V1e3-MP1 |
| MS1 | SecurityHeaders nur `class_exists`-Smoke | NIEDRIG | TEST-V1e3-MS1 |
| MH1 | HandleExceptions HTML-Escape untested | MEDIUM | TEST-V1e3-MH1 |

**Meta-Beobachtung**: 20 von 31 Middleware-Tests in Layer 2 sind reine `class_exists`-Smoke-Tests (32 LOC Boilerplate). Diese Tests sind Dead Code und inflationieren den Test-Count ohne Informationsgehalt.

---

## Sweep-Methodik-Korrekturen

| # | Korrektur | Betroffen | Aufnahme in |
|---|---|---|---|
| M1 | `new Expression(` zur T0-Grep-Liste hinzufügen (V1b/V1e.1 Finding) | Zukünftige Sweeps | `priorities.md` Methodology-Abschnitt |
| M2 | `priorities.md` Zeile 50: Allowlist-Text korrigieren (`[stristr]` → `[constant, min, max, enum, stristr]`) | parent sweep | `priorities.md` |
| M3 | Case-Sensitivity-Probe: jede String-basierte Gate-Prüfung auf Input-Methoden-Name muss beide Casings testen | Zukünftige Sweeps | `priorities.md` Methodology-Abschnitt |
| M4 | Handler-Count: Sweep zählte 313, tatsächlich 335 (V1e.1) | parent sweep | `priorities.md` Scope-Abschnitt |
| M5 | Middleware-Count: Sweep zählte 20, tatsächlich 34 (V1e.2) | parent sweep | `priorities.md` Scope-Abschnitt |

---

## Claim-Verifikation gegen Parent Sweep

| Parent-Claim | Verification | Artefakt |
|---|---|---|
| "ReportGenerate: no exploit today" | **CONFIRMED** mit refined reasoning (constant() reachable aber kein attacker-controlled input) | V1a |
| "ExpressionLanguage-Allowlist = [stristr]" | **REFUTED** (actual: `[constant, min, max, enum, stristr]`) | V1a |
| "`addcslashes($val, \"'\")` is correct EL-literal escape" | **CONFIRMED** (Lexer grammar analysis) | V1a |
| "SearchService bound-parameter DB-Zugriffe durchgängig" | **CONFIRMED** mit Caveat: `new Expression(` war nicht in der Grep-Liste | V1b |
| "RenumberTreeAction uses query builder" | **REFINED** (raw Expression() mit REGEX_XREF-Dependency) | V1b, V1e.1 |
| "HTML-Sanitization für alle Admin-Rich-Text via HtmlService" | **CONFIRMED** (8/8 Module-Sinks, CustomCssJs intentional bypass admin-gated) | V1c |
| "Handler scope gedeckt (313 files via T1)" | **REFUTED quantitativ** (actual: 335, ~20 T1-reads) + **mitigiert** durch V1e.1 (315 weitere Files mechanisch gescannt) | V1e.1 |
| "Middleware scope gedeckt (20 files)" | **REFUTED quantitativ** (actual: 34) + **ergänzt** durch V1e.2 | V1e.2 |
| "Keine weiteren exploitable findings in Handler/Middleware" | **REFUTED** — SEC-AUDIT-005 (CRITICAL, unauthenticated bypass) | V1e.2 |

---

## Abgeleitete V3-Aktionen (Vorbereitung)

Zusammenfassung der V3-Tasks, die in der nächsten Runde angelegt werden:

**Neue SEC-AUDIT-Tasks**:
- `SEC-AUDIT-005` — ModuleAction substring-admin-gate bypass (CRITICAL, aktive PoC, priorisiert)

**Neue TEST-V1e3-Tasks** (11 Stück, siehe F-7):
- `TEST-V1e3-MA1` (CRITICAL) — Regression für SEC-AUDIT-005
- `TEST-V1e3-H1` (HOCH) — HtmlService XSS-Matrix
- `TEST-V1e3-R1` (HOCH) — ReportParserGenerate EL-Payload-Tests
- `TEST-V1e3-S1`, `TEST-V1e3-R2`, `TEST-V1e3-RT1`, `TEST-V1e3-MC1`, `TEST-V1e3-MP1`, `TEST-V1e3-MH1` (MEDIUM)
- `TEST-V1e3-ML1`, `TEST-V1e3-MS1` (NIEDRIG)

**Offene Entscheidungen für V3**:
- Soll die RenumberTreeAction-Defense-in-Depth-Lücke (F-2) als `SEC-AUDIT-006` geführt werden?
- Soll SetupWizard-Superglobal (F-3) als eigener Task geführt werden oder als "Observation only"?
- Sollen die `class_exists`-Smoke-Tests gelöscht oder durch echte Tests ersetzt werden?

**Nicht-Task Observationsn** (nur `priorities.md`-Update):
- V1a-Allowlist-Korrektur (M2)
- V1a LIKE-DoS Raw-Pfad (F-5)
- V1e.2 CSP, PublicFiles, DebugLogger, BREACH, CheckCsrf non-constant-time, HandleExceptions fallback (F-6)

**Sweep-Methodik-Verbesserungen** (M1, M3, M4, M5): als Abschnitt in `priorities.md` oder als eigene `sweep_methodology_followup.md`.

---

## Gesamtbewertung gegen "Anti-Scheinsicherheit"

Die Verification-Runde hat ihre Kernaufgabe erfüllt: **"jede ungeprüfte Claim ist jetzt geprüft".** Ergebnis:

- **4 der 7 ungeprüften Parent-Claims waren so korrekt wie behauptet** (mit präzisierten Begründungen)
- **2 waren quantitativ falsch** (Handler-Count, Middleware-Count) — bietergereinigt durch V1e.1/V1e.2
- **1 war qualitativ falsch** (Allowlist-Text) — dokumentiert, kein Exploit
- **1 zuvor nicht explizit behauptete Eigenschaft wurde widerlegt**: die Annahme, dass die 20 Layer-2-Middleware-Tests ausreichen, um Änderungen am Admin-Gate zu fangen — sie tun es nicht, wie TEST-V1e3-MA1 zeigt

**Die wichtigste Lehre**: Das einzige CRITICAL-Finding (SEC-AUDIT-005) wurde **nicht** durch neue Code-Analyse gefunden, sondern durch **systematisches Ausprobieren gegen den Code, den der Sweep bereits gelesen hatte**. Der Sweep hat `ModuleAction.php` korrekt als "Admin-only gate" gelesen, aber nicht gegen PHP's case-insensitive-method-dispatch cross-referenziert. Die Lücke war mit dem Wissen "substring-check auf Input, das in Method-Dispatch fließt" in jedem PHP-Lehrbuch zu finden — sie ist nur übersehen worden.

**Methodologie-Schluss**: Zukünftige Sweeps brauchen eine T1-Regel "für jeden String-basierten Gate: konstruiere einen bypassenden Input und probiere ihn". Das wird als M3 in die Methodologie-Liste aufgenommen.

---

## Nächste Schritte

1. **V3**: `priorities.md` mit den oben aufgeführten Änderungen aktualisieren, Task-Kandidaten anlegen
2. **V4**: Verification-Artefakte committen (SPDX-Header sind bereits gesetzt, GPG-signed)
3. **Post-Verification**: SEC-AUDIT-002, -003, -004 Deep-Dives können weiter, aber SEC-AUDIT-005 priorisiert (CRITICAL, unauthenticated)
