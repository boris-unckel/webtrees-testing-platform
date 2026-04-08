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

## Sweep-Ergebnis: `clean_post_fix`

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
