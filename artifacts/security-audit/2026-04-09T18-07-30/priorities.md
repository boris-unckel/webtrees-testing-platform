<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Audit-Priorisierung — Run 2026-04-09T18-07-30

| Rank | Score | Datei | Track | Impact | Reach | Dangerous-Fn | Rationale |
|---|---|---|---|---|---|---|---|
| 1 | 0.22* | app/Http/RequestHandlers/LoginAction.php | non_admin_owasp | auth-bypass (brute-force) | visitor | 0 | Keine RateLimitService-Nutzung, während PasswordRequestAction/RegisterAction/ContactAction alle rate-limitiert sind. Unbegrenzte Brute-Force-Versuche gegen jedes Konto. Bcrypt verlangsamt, reicht aber nicht als alleiniger Schutz. |

*Score unter formaler 0.25-Cutoff-Schwelle, wegen bestätigter Pattern-Inkonsistenz (alle ähnlichen Endpoints haben Rate-Limiting, Login hat keines) trotzdem eingereiht.

## Below-Cutoff Observations (nicht eingereiht, aber dokumentiert)

| Datei | Beschreibung | Begründung für Nicht-Einreihung |
|---|---|---|
| app/Module/FamilyTreeFavoritesModule.php | javascript:-URL in `href="<?= e($favorite->url) ?>"` — Manager kann XSS-Link für alle Visitor auf Tree-Home-Page speichern | Manager-Zugang (trusted) + Klick erforderlich + CSRF-geschützt → Score ~0.12, weit unter Cutoff |
| app/Module/UserFavoritesModule.php | javascript:-URL in User-Favorites — logged-in member → self-XSS nur auf eigener Seite | Self-XSS, keine Fremdwirkung → nicht sicherheitsrelevant |
| app/Module/AbstractIndividualListModule.php | givenNameInitials/surnameData: Name-Tabelle ohne Privacy-Filter → count-Leakage | Intentional Design (SHOW_LIVING_NAMES), Existenzinformation aber keine Namensoffenbarung |

## Nicht bestätigt / No Finding

| Hypothese | Ergebnis |
|---|---|
| V4 (GEDCOM-Export ohne canShow) | No finding: GedcomExportService wendet privatizeGedcom korrekt an; 'none'-Modus ist intentional admin-only full-dump |
| V6 (Cross-Tree xref-Leak) | No finding: individualFactory()->make($xref, $tree) ist tree-scope; Cross-Tree xref gibt null zurück |
| V1 (canShow vs canShowName) | No finding: Intentional Design (SHOW_LIVING_NAMES-Preference steuert), kein Bug |
| V5 (Autocomplete ohne canShow) | No finding: SearchService::searchIndividualNames() wendet GedcomRecord::accessFilter() an |
| V10 (CheckCsrf state-changing GET) | No finding: Logout ist POST + in EXCLUDE_ROUTES → forced-logout CSRF möglich aber DoS-only, kein session-hijack |
| V12 (ClientIp X-Forwarded-For) | Configuration-only: sicher by default (leere trusted_proxies), Deployment-Risiko wenn falsch konfiguriert |
| Brute-Force Session-Fixation | No finding: Auth::login() ruft Session::regenerate() → kein session-fixation möglich |
| StoriesModule/UserJournalModule XSS | No finding: HTMLPurifier-Sanitizer aktiv |
| FamilyTreeNewsModule XSS | No finding: HTMLPurifier-Sanitizer aktiv |
| TreeService SQLi | No finding: Expression() nur mit tree->id() (int), kein User-Input |
| DeletePath traversal | No finding: Admin-only + Flysystem-PathTraversalDetected-Protection |
| ManageMediaData SQLi | No finding: Admin-only + Expression() mit static Concat, $media_folder in bound-param LIKE |
