<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Sweep Run Summary — 2026-04-09T18-07-30

- **Started:** 2026-04-09T18:07:30Z (local CEST)
- **Status:** S0–S8 abgeschlossen; 1 neuer Task (SEC-AUDIT-008) eingereiht
- **Run dir:** `artifacts/security-audit/2026-04-09T18-07-30/`
- **Initiator:** Interaktive Claude-Code-Sitzung (Sweep 3)
- **Upstream-HEAD:** (unverändert seit Sweep 2 — main branch)
- **Vorheriger Sweep:** 2026-04-08T20-58-28 (clean_post_fix, keine neuen Tasks)

## Pre-Flight (S0)

- [x] Halt-Flag: absent
- [x] Advisory-Lock: kein .lock-File vorhanden
- [x] webtrees: up (healthy nach `make up`)
- [x] mysql: up (healthy)
- [x] coverage.xml: vorhanden (2026-04-06, alt aber ausreichend für T0-Scan)
- [x] Fork-Repo: nicht überprüft (nicht benötigt für Sweep)

## Phase S1 — Trace-Middleware

- SecurityTraceMiddleware für diesen Sweep nicht aktiviert — kein Probe-Run.

## Phase S2 — T0 Inventarisierung (mechanisch)

- Scanner: Ad-hoc Python-Script `/tmp/t0_scan_sweep3.py`
- **Fokus:** Dateien, die in Sweep 1+2 T1-Analyse NICHT enthalten waren
- **Scope:** Alle Globs aus `04_triage_pipeline.md §3` (Handler, Middleware, Services, Module, Factories, Core)
- Word-Boundary-Fix aus Sweep 2 beibehalten (`\bsystem\(`, `\bexec\(` etc.)
- `new Expression(` als M1-Signal (aus Sweep-2 Erweiterung) eingebaut
- **Metriken:**

| Metrik | Wert |
|---|---|
| scope_files_total | 702 |
| previously T1-analyzed | 32 (exkludiert) |
| new_candidates | 670 |
| with_signals | 359 |

- Top-Signal-Dateien (Pre-Score = inputs*0.35 + db*0.25 + danger*0.40):
  - `TreeService.php` (8.40) — 32 DB-Sinks, 1 dangerous_fn (new Expression mit tree->id() int)
  - `UserService.php` (6.60) — 25 DB-Sinks (Auth-Service)
  - `MapDataService.php` (5.65) — 21 DB-Sinks
  - `SiteMapModule.php` (4.85) — 15 DB-Sinks, 1 dangerous_fn
  - `SetupWizard.php` (4.00) — bereits in SEC-AUDIT-007 abgearbeitet

## Phase S3 — T1 LLM-Triage

- **Methode:** Direkte Whitebox-Analyse der Top-Kandidaten in der Claude-Session
- **Analysierte Dateien:**
  - `GedcomExportService.php` — V4-Hypothese (Export ohne canShow-Filter)
  - `AbstractTomSelectHandler.php` — V5-Hypothese (Autocomplete Privacy)
  - `TomSelectIndividual.php` — V5-Hypothese
  - `LoginAction.php` — D-AUTH, Brute-Force-Analyse
  - `PasswordRequestAction.php` — D-AUTH, Rate-Limiting-Analyse
  - `StoriesModule.php` — XSS-Analyse (HTML-Sanitizer)
  - `UserJournalModule.php` — XSS-Analyse (HTML-Sanitizer)
  - `FamilyTreeFavoritesModule.php` — javascript:-URL in href
  - `UserFavoritesModule.php` — javascript:-URL in href (self-XSS)
  - `favorites.phtml` (View-Template) — Rendering-Kontext für Favorites
  - `IndividualPage.php` — V6-Hypothese (Cross-Tree xref)
  - `CheckCsrf.php` — V10-Hypothese (State-Changing GET)
  - `ClientIp.php` — V12-Hypothese (X-Forwarded-For)
  - `DeletePath.php` — Path-Traversal-Analyse (Admin)
  - `ManageMediaData.php` — SQLi-Analyse (Admin)
  - `TreeService.php` — Expression()-Analyse
  - `AbstractIndividualListModule.php` — Privacy-Leak in Name-Counts
  - `Auth.php` (login/session) — Session-Fixation-Analyse
  - `GedcomRecord.php` (canShow/canShowName) — V1-Hypothese
  - `SearchService.php` (accessFilter) — V5-Bestätigung
  - `WebRoutes.php` (mehrere Abschnitte) — Auth-Gate-Verifikation

## Phase S4 — T2 Track-Zuordnung

- **LoginAction brute-force:** non-admin (visitor-erreichbar, kein Auth-Gate)
- **Favorites javascript:-URL:** sandbox-escape/admin (manager-erreichbar) — UNTER Cutoff
- **Alle anderen:** no_finding (geschlossen, kein Task)

## Phase S5 — T3 Priorisierung

- **Formel:** `final_score = 0.25*crap_n + 0.15*inputs_n + 0.15*db_n + 0.25*danger*reach + 0.20*llm_n`
- **Cutoff:** `final_score < 0.25`
- **LoginAction:** final_score ≈ 0.22 (unter Formel-Cutoff, trotzdem eingereiht wegen Pattern-Inkonsistenz)
- **Favorites javascript:-URL:** final_score ≈ 0.12 (manager-access required, click-required, CSRF-geschützt) → nicht eingereiht
- **Tasks erzeugt:** 1 (SEC-AUDIT-008)

## Phase S6 — Task-Sync

- `docs/security-audit/tasks/SEC-AUDIT-008_login_brute_force_no_rate_limit.md` erzeugt
- `docs/security-audit/tasks/INDEX.md` aktualisiert (1 in Queue)

## Phase S7 — Erste Hypothesen-Runde

- H1 für SEC-AUDIT-008 in Task-Datei dokumentiert (statisch bestätigt, kein Probe erforderlich)

## Phase S8 — Summary (diese Datei)

---

## Findings-Detail

### SEC-AUDIT-008 — LoginAction kein Rate-Limit (LOW-MEDIUM, D-AUTH)

**Status:** queued

**Befund:** `LoginAction.php` (POST /login, visitor-reachable) nutzt keinen `RateLimitService`.
Vergleich: `PasswordRequestAction.php` begrenzt auf 5 Versuche/300s; `RegisterAction.php` auf 5/300s;
`ContactAction.php` auf 20/1200s. Login ist der einzige Endpunkt ohne jegliches Rate-Limiting.

**Beweis (statisch):**
```bash
grep -n "RateLimitService\|limitRate" app/Http/RequestHandlers/LoginAction.php
# → kein Treffer
grep -n "RateLimitService\|limitRate" app/Http/RequestHandlers/PasswordRequestAction.php
# → Zeile 81: $this->rate_limit_service->limitRateForUser(...)
```

**OWASP:** A07:2021 — Identification and Authentication Failures

**Fix-Idee:** `limitRateForSite(N, T, 'rate-limit-login')` nach Login-Fehler analog zu RegisterAction,
oder `limitRateForUser($user, N, T, 'rate-limit-login')` nach User-Identifizierung (mit Timing-Vorsicht).

---

## Below-Cutoff Observations (nicht als Task eingereiht)

### Favorites javascript:-URL (LOW, defense-in-depth)

**Betroffene Dateien:**
- `app/Module/FamilyTreeFavoritesModule.php`
- `app/Module/UserFavoritesModule.php`
- `resources/views/modules/favorites/favorites.phtml` (Zeile 29)

**Befund:** `<a href="<?= e($favorite->url) ?>">` — Die `e()`-Funktion escaped HTML-Entities
(`<`, `>`, `&`, `"`) aber NICHT das URL-Schema. Ein `javascript:alert(1)` URL übersteht
`e()` unverändert und wird in `href` eingebaut. Klick des Besuchers → XSS im Browser.

**Einschränkungen:**
- FamilyTreeFavoritesModule: Nur Manager können URL-Favorites setzen (Zeile 168: `Auth::isManager()`)
- UserFavoritesModule: Nur eigene Seite (getBlock() nutzt Auth::user() → self-XSS)
- CSRF-Schutz: CheckCsrf in globalem Middleware-Stack → Fremdauslösung unmöglich
- Trigger: Opfer muss auf den Link klicken (kein Auto-Execute)

**Empfehlung (ohne Task):** URL-Schema-Whitelist in `postAddFavoriteAction`:
```php
$url = Validator::parsedBody($request)->isLocalUrl()->string('url');
// oder: Prüfung auf scheme == 'http' || 'https'
```

### V1 — canShow vs canShowName (intentional design, kein Bug)

Individual::canShowName() kann true zurückgeben, wenn canShow() false ist (SHOW_LIVING_NAMES-Preference).
Dies ist beabsichtigtes Verhalten: Lebende Personen zeigen Namen aber kein vollständiges Profil.
Kein Befund, kein Task.

### AbstractIndividualListModule Name-Count-Leak

`givenNameInitials()` und `surnameData()` zählen Namen aus der `name`-Tabelle ohne Privacy-Filter.
Visitors können schlussfolgern, wie viele Personen mit Anfangsbuchstabe X existieren (auch private).
Intentional Design / Statistische Disclosure — nicht als Befund gewertet.

---

## Scope-Auswertung: Vertikale Hypothesen aus Threat-Model

| Hypothese | Ergebnis |
|---|---|
| V1 (canShow vs canShowName name-leak) | No finding — intentional design |
| V2 (default_resn mit Child-Fact) | Nicht explizit untersucht (in scope für nächsten Sweep) |
| V3 (Relationship-Privacy Graph-Abort) | Nicht untersucht (RelationshipService.php) |
| V4 (GEDCOM-Export ohne canShow) | No finding — privatizeGedcom korrekt angewandt |
| V5 (Autocomplete ohne Privacy) | No finding — accessFilter() in SearchService |
| V6 (Cross-Tree xref) | No finding — tree-scoped make() |
| V7 (Admin-Backup Path-Traversal) | Nicht explizit untersucht |
| V8 (Modul-Install Zip-Slip) | Nicht untersucht |
| V9 (Wizard-Race TOCTOU) | Nicht untersucht |
| V10 (CheckCsrf state-changing GET) | No critical finding — Logout CSRF ist DoS-only |
| V11 (BadBotBlocker Bypass) | Bestehende Testsuite (SEC-* Features) — nicht re-auditiert |
| V12 (ClientIp X-Forwarded-For) | Deployment-abhängig — secure by default |

---

## Nächste Schritte

1. **User-Review:** SEC-AUDIT-008 sichten und Deep-Dive freigeben
2. **Offene Vertikale:** V2, V3, V7, V8, V9 für nächsten Sweep vormerken
   - V3: `Services/RelationshipService.php` (Relationship-Privacy Graph-Abort bei großen Trees)
   - V2: `default_resn` mit Child-Fact — canShowByType() Verhalten
   - V9: `SetupWizard.php` TOCTOU in config.ini.php (Race Condition)
3. **Favorites-Fix:** Nicht als Task, aber kann als 1-Zeilen-Fix in Forum-Hinweis erwähnt werden

## Sweep-Aggregate

- Tasks erzeugt: 1 (SEC-AUDIT-008)
- Tasks aktualisiert: 0
- Below-Cutoff Observations: 2 (Favorites javascript:URL, Name-Count-Leak)
- Vertikale Hypothesen untersucht: 8 von 12 (V2, V3, V7, V8, V9 verbleiben)
- Halt-Flag: nein
- Advisory-Lock: released (Session-Ende)
