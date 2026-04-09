<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# T3 Priorisierung — Run 2026-04-08T20-58-28

## Formel

```
final_score = 0.25 * crap_norm
            + 0.15 * inputs_norm
            + 0.15 * db_norm
            + 0.25 * danger * reach
            + 0.20 * llm_norm
```

Cutoff: `final_score < 0.25` → kein Task.

Normierung: `*_norm = value / max_value_in_run`. `danger * reach` ist bereits 0..1 skaliert (dangerous_count gewichtet mit reachability: visitor=1.0, member=0.7, editor=0.6, manager=0.4, admin=0.2).

## ⚠️ VERIFICATION UPDATE (2026-04-08, Run verify-2026-04-08T21-45-10) ⚠️

**Der `clean_post_fix`-Status dieses Sweeps ist durch die Verification-Runde REVIDIERT.**

Zusammenfassung der Korrekturen (siehe `artifacts/security-audit/verify-2026-04-08T21-45-10/verification_report.md` für Details):

1. **1 CRITICAL Finding neu entdeckt**: **SEC-AUDIT-005** — ModuleAction substring-admin-gate bypass. Unauthenticated visitor kann `post*Admin*Action`-Methoden von FAQ/Stories/RelationshipsChart aufrufen via lowercase URL (z.B. `/module/faq/admindelete`). **End-to-end PoC verifiziert.** CVSS 3.1 = 8.1 (High) default, 9.4 (Critical) mit custom-css-js. Diese Lücke wurde vom Sweep T1 übersehen, weil `ModuleAction.php` gelesen wurde, aber die Case-Sensitivity-Asymmetrie zwischen `str_contains` (case-sensitive) und PHP-Method-Dispatch (case-insensitive) nicht geprüft wurde. Status: `exploit_confirmed`. Task: `docs/security-audit/tasks/SEC-AUDIT-005_module_action_case_bypass.md`.

2. **Scope-Korrekturen**:
   - Handler-Count: Sweep zählte **313**, tatsächlich **335** Files unter `app/Http/RequestHandlers/` (V1e.1). T1 hatte nur ~20 gelesen. V1e.1 hat alle 335 mechanisch gescannt.
   - Middleware-Count: Sweep zählte **20**, tatsächlich **34** Files unter `app/Http/Middleware/` (V1e.2).

3. **Allowlist-Text-Korrektur** (Zeile 50 dieser Datei): "Der Function-Allowlist ist *absichtlich* auf `[stristr]` beschränkt" ist **faktisch falsch**. Symfony ExpressionLanguage::__construct() registriert unconditional die Defaults `[constant, min, max, enum]` vor dem Provider. Der tatsächliche Allowlist ist `[constant, min, max, enum, stristr]`. Kein Live-Exploit, weil keine attacker-kontrollierte Input-Eingabe in den 16 bundled Reports einen EL-Eval trifft. Aber: **Drittmodul-Autoren, die `<SetVar value="$uservar + 1"/>` verwenden, exponieren `constant()`**. Siehe `verify-2026-04-08T21-45-10/v1a_reportparser_findings.md` §Claim A.

4. **RenumberTreeAction-Defense-in-Depth** → **SEC-AUDIT-006 queued** (V3-User-Decision 2026-04-09): Zeile 98 dieser Datei `"Mass xref-renumber via query builder"` ist **irreführend**. Die Methode verwendet 31× `new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', ...)")` mit PHP-String-Interpolation statt Parameter-Bindung. Sicherheit hängt ausschließlich von `Gedcom::REGEX_XREF` in einer anderen Datei ab. Nicht exploitable heute, aber defense-in-depth Gap. `final_score ≈ 0.263` (marginal über Cutoff). Track: `admin`, Label: `defense-in-depth`. Task: `docs/security-audit/tasks/SEC-AUDIT-006_renumber_tree_raw_expression.md`.

5. **SetupWizard.php:327 (V1e.1)** → **SEC-AUDIT-007 queued** (V3-User-Decision 2026-04-09): Raw `$_POST['wtpass']` statt `$data['wtpass']`. Code-Qualität, nicht exploitable (Password wird sofort gehasht). 1-Zeilen-Fix. Track: `admin`, Label: `code-quality`. Task: `docs/security-audit/tasks/SEC-AUDIT-007_setupwizard_superglobal.md`.

6. **V1e.2 Middleware-Observations (6 MEDIUM)**: kein CSP-Header in SecurityHeaders; PublicFiles-Substring-Traversal-Check ist fragil; DebugLogger dev-only SQL-Header-Leak; BREACH bei CompressResponse; CheckCsrf non-constant-time compare; HandleExceptions fallback-Pfad ohne HTML-Escape. Alle als Observations dokumentiert, keine neuen Tasks.

7. **V1e.3 Testlücken und `class_exists`-Smoke-Tests** → **OUT-OF-SCOPE FÜR SECURITY-TRACK** (V3-User-Decision 2026-04-09): Die 11 Testlücken aus `verify-2026-04-08T21-45-10/v1e3_layer3_test_coverage.md` (1 CRITICAL TEST-V1e3-MA1, 2 HOCH, 6 MEDIUM, 2 NIEDRIG) und die Meta-Beobachtung „20 von 31 Middleware-Tests im Upstream sind reine `class_exists`-Smoke-Tests" werden **nicht** als SEC-AUDIT-Tasks geführt. Das ist bekannter Sachverhalt und gehört in den **regulären Testqualität-Track** (siehe Memory `project_testquality_status.md`), nicht in den Security-Audit. **Ausnahme**: TEST-V1e3-MA1 (Regression für SEC-AUDIT-005) bleibt als Regression-Test-Pflicht direkt im Deep-Dive D5 von SEC-AUDIT-005 verankert (siehe Task-File).

8. **Sweep-Methodik-Verbesserungen für zukünftige Runs** (V1e.1 + V1e.2 + V1e.3 Feedback):
   - M1: `new Expression(` zur T0-Grep-Liste hinzufügen (Laravel raw-SQL-Primitive, die der aktuelle Grep übersieht).
   - M3: Case-Sensitivity-Probe — jede String-basierte Gate-Prüfung auf Input-Methoden-Name muss beide Casings testen. Das wäre die einzige Methodik-Änderung, die SEC-AUDIT-005 beim nächsten Mal automatisch fangen würde.
   - M4/M5: Scope-Counts (Handler 313→335, Middleware 20→34) in zukünftigen Runs mit `ls app/Http/RequestHandlers/*.php | wc -l` gegenprüfen statt auf T0-Output zu vertrauen.

### Revidierter Sweep-Status

Der ursprüngliche Status `clean_post_fix` wird revidiert auf:

**`one_critical_missed_by_t1`** — Der Sweep T1 hat eine CRITICAL-Lücke in einer Datei übersehen, die T1 gelesen hatte. Die Methodik-Verbesserungen (M1, M3) sind notwendig, bevor zukünftige Sweeps wieder als `clean_post_fix` klassifiziert werden können.

Der unten stehende ursprüngliche Sweep-Text bleibt unverändert als historisches Artefakt erhalten. Die Verification-Runde hat ihn als teilweise überholt markiert.

---

## Sweep-Ergebnis: `clean_post_fix` *(ORIGINAL — siehe VERIFICATION UPDATE oben für Revision)*

Dieser Sweep findet **keinen** neuen queueable Kandidaten. Der Codebase befindet sich nach SEC-AUDIT-001 in einem post-hardened Zustand für die klassisch OWASP-sichtbare Visitor/Member/Editor-Angriffsfläche:

- **Konsistente `Validator`-Nutzung** für alle parsedBody/queryParams/attributes.
- **Bound-Parameter-DB-Zugriffe** durchgängig: `grep -E 'whereRaw|orderByRaw|DB::raw|->raw\('` in `app/Services/SearchService.php` (dem Top-DB-Service) → 0 Treffer.
- **Open-Redirect-Schutz** via `Validator::parsedBody($request)->isLocalUrl()` in allen URL-Redirect-Pfaden (MessageAction, EditFactAction, AddSpouseToFamilyAction, EditMediaFileAction, ContactAction).
- **HTML-Sanitization** für alle Admin-eingegebenen Rich-Text-Felder via `HtmlService::sanitize()` (FAQ-Modul, Stories-Modul, etc.).
- **Access-Checks pro Komponente** via `Auth::checkComponentAccess(...)` und `Auth::checkRecordAccess(...)`.
- **CSP-Header** (`script-src none; frame-src none`) auf Image-Responses (SEC-AUDIT-001 D7 bestätigt).

## Top Candidates (nach T1-Triage)

### 1. ReportGenerate + ReportParserGenerate — Defense-in-Depth-Gap

**Primär-Datei:** `app/Http/RequestHandlers/ReportGenerate.php`
**Contributing-Dateien:** `app/Report/ReportParserGenerate.php`, `app/Report/ReportExpressionLanguageProvider.php`, `app/Http/RequestHandlers/ReportSetupAction.php`

| Metrik | Rohwert | Normiert (Max im Run) | Gewichtet |
|---|---|---|---|
| crap | n/a | 0.00 | 0.000 |
| inputs | 7 | 7/48 = 0.146 | 0.022 |
| db | 0 | 0.00 | 0.000 |
| danger * reach | 0 * 1.0 | 0.00 | 0.000 |
| llm | 45 | 0.45 | 0.090 |
| **final_score** | | | **0.112** |

**Status:** Unter Cutoff (0.112 < 0.25) → **nicht queued**.

**Warum dennoch dokumentiert:** `ReportParserGenerate::substituteVars()` erlaubt zwei Pfade in denen benutzer-kontrollierte `$vars[...]` in weitere Verarbeitung fließen:

1. **`substituteVars($expr, true)`** (ReportParserGenerate.php:1098): `addcslashes($val, "'")` wrapt den Wert als String-Literal in eine Expression, die per `Symfony\ExpressionLanguage` evaluiert wird. Der Function-Allowlist ist *absichtlich* auf `[stristr]` beschränkt (ReportExpressionLanguageProvider.php:36). Die Quote-Escape ist für ExpressionLanguage-String-Literale korrekt. **Heute kein Exploit.**
2. **`substituteVars($value, false)`** (ReportParserGenerate.php:1366, 1452): Roh-Substitution in Filter-Strings, die per `preg_match` in Laravel-Query-Builder `where(column, 'LIKE', value)` fließen. **Bound-Parameter → keine SQLi.**

**Nicht-anchored Arithmetic-Regex** bei Zeile 1071 (`preg_match("/(\d+)\s*([-+*\/])\s*(\d+)/", $value)`) macht den ExpressionLanguage-Pfad für jeden XML-Template-Wert erreichbar, der eine Ziffer enthält. Das ist ein architektonisches Design-Smell, aber aktuell ohne exploit-fähige Senke.

**Follow-Up-Empfehlung (keine Task):** Bei Refactoring der Report-Engine sollte der Allowlist-Check in `ReportExpressionLanguageProvider` einen Linter-Kommentar bekommen, dass jede Funktionsaddition durch ein Security-Review laufen muss.

### 2. ClippingsCartModule — Höchste Input-Dichte

**Datei:** `app/Module/ClippingsCartModule.php`

| Metrik | Rohwert | Normiert | Gewichtet |
|---|---|---|---|
| inputs | 48 | 1.00 | 0.150 |
| db | 0 | 0.00 | 0.000 |
| danger * reach | 0 | 0.00 | 0.000 |
| llm | 22 | 0.22 | 0.044 |
| **final_score** | | | **0.194** |

**Status:** Unter Cutoff → **nicht queued**.

**Grund:** Grep für gefährliche File-Operationen (`file_put_contents|fopen|ZipArchive|tempnam|->move|unlink|fwrite|move_uploaded`) liefert 0 Treffer. Der T0 `assert(`-Hit ist ein Runtime-Type-Guard (FP).

**Follow-Up-Empfehlung:** Wenn das Sweep-Budget es im nächsten Lauf erlaubt, strukturierter Audit aller 48 `Validator::parsedBody(...)`-Call-Sites.

### 3. EditMediaFileAction — Pfad-Konstruktion mit Flysystem

**Datei:** `app/Http/RequestHandlers/EditMediaFileAction.php`

| Metrik | Rohwert | Normiert | Gewichtet |
|---|---|---|---|
| inputs | 8 | 0.167 | 0.025 |
| db | 0 | 0.00 | 0.000 |
| danger * reach | 0 | 0.00 | 0.000 |
| llm | 20 | 0.20 | 0.040 |
| **final_score** | | | **0.065** |

**Status:** Unter Cutoff → **nicht queued**.

**Grund:** Editor-reachable. `$file = $folder . '/' . $new_file` wird an `$filesystem->move($old, $new)` geschickt — Flysystem LocalFilesystemAdapter blockt `../` Traversal. `$remote` ist nur ein in GEDCOM gespeicherter URL-String, wird hier nicht dereferenziert (kein SSRF).

**Querverweis:** Verwandt mit SEC-AUDIT-002 (MediaFileService Upload-Gaps). Kann gemeinsam regressiert werden.

## Admin-Track Observations (keine neuen Tasks)

| Datei | Signale | Bemerkung |
|---|---|---|
| `app/Http/RequestHandlers/TreePreferencesAction.php` | 41 inputs, 3 db, manager | Alle via setPreference mit View-side e()-Escaping |
| `app/Http/RequestHandlers/RenumberTreeAction.php` | 1 input, 43 db, manager | Mass xref-renumber via query builder |
| `app/Http/RequestHandlers/GedcomLoad.php` | 1 input, 20 db, manager | Import via query builder |
| `app/Http/RequestHandlers/UserEditAction.php` | 20 inputs, 0 db, admin | Admin-only user edit, setPreference-Pfad |

## Dropped in dieser Runde

Keine — alle Kandidaten wurden in T1 gelesen und landen entweder unterhalb der Cutoff-Schwelle oder sind bereits durch bestehende Tasks abgedeckt.

## Task-Erzeugung

- Keine neuen Task-Dateien erzeugt.
- `docs/security-audit/tasks/INDEX.md` bleibt unverändert (1 `fix_verified` SEC-AUDIT-001, 3 `queued` Spin-offs 002/003/004).

## Abweichungen vom vorigen Run (2026-04-08T19-01-49)

### Scope erweitert

- `app/Http/Exceptions/*.php` neu mit aufgenommen (war zuvor nicht im SCOPE_GLOBS). Ergibt 14 Dateien, alle ohne Signals.
- `app/Auth.php` + `app/Validator.php` als "core"-Typ aufgenommen.

### T0-Signale korrigiert

**Kritische Korrektur:** `ImageFactory.php` hatte im vorigen Run `dangerous_count=3` (`system(`) — dies war ein False Positive: Der naive Regex `system\(` matched den Substring in `mediaFilesystem(`. Der neue T0-Scan verwendet `\bsystem\(` mit Word-Boundary und liefert korrekt `dangerous_count=0`.

**Implikation:** T0-Pattern-Matching alleine hätte SEC-AUDIT-001 NICHT als Top-Candidate identifiziert — der vorige Run erwischte es nur, weil der FP `system(` die Datei in die Top-Liste schob, und dann die T1-Lesung den eigentlichen `str_contains`-Bug aufdeckte. Das bestätigt, dass **T1 essentiell ist und T0 allein unzureichend wäre**.

### Scope-Counts

| Run | Total-Files | Signal-Files |
|---|---|---|
| 2026-04-08T19-01-49 | 371 | — |
| 2026-04-08T20-58-28 | 679 | 464 |

Die Verbreiterung kommt durch zusätzliche Glob-Patterns (Exceptions, Core-Files) sowie dadurch, dass der alte Scan laut `t0_signals.json` nur 371 Dateien enumeriert hatte — vermutlich nur Scope-Dateien mit Handler/Service/Module-Typ **und** mindestens einem Signal. Der neue Scan zählt auch Dateien mit 0 Signals zur Transparenz.
