<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Sweep Run Summary — 2026-04-08T20-58-28

- **Started:** 2026-04-08T20:58:28Z
- **Finished:** 2026-04-08T22:00Z (approx., Claude-interactive)
- **Status:** `clean_post_fix` — kein neuer Task
- **Run dir:** `artifacts/security-audit/2026-04-08T20-58-28/`
- **Initiator:** `scripts/security-audit-sweep.sh` + interaktive Claude-Code-Sitzung
- **Upstream-HEAD:** `34dff096c21652db243cda862afee224fd6ca92a` (main, unverändert seit vorherigem Run 2026-04-08T19-01-49)
- **Fork-Branch unter Review:** `security-audit-001-svg-filter-hardening` (commit `b2dc869b90`, `fix_verified`)

## Pre-Flight (S0)

- [x] Halt-Flag: absent
- [x] Advisory-Lock: claimed (`$$` in `.lock`, Trap-released nach Driver-Exit)
- [x] webtrees: up
- [x] mysql: up
- [x] Fork-Repo: clean working tree (webtrees-upstream/webtrees)
- [x] Driver-Skript Bugfix: `scripts/security-audit-sweep.sh` patched für podman-compose ps ohne Service-Argument (siehe Git-Diff — uncommitted)

## Phase S1 — Trace-Middleware

- SecurityTraceMiddleware nur bei Bedarf via `WEBTREES_SECURITY_TRACE=1` aktiviert (nicht global). Für diesen Sweep nicht benötigt — keine Probes gefahren.

## Phase S2 — T0 Inventarisierung (mechanisch)

- Scanner: Ad-hoc Python-Helper `/tmp/t0_scan.py` (Subagent-Delegation scheiterte 2× an API 529 Overloaded → direkter Host-Lauf)
- Scope-Globs:
  - `app/Http/RequestHandlers/*.php`
  - `app/Http/Middleware/*.php`
  - `app/Http/Exceptions/*.php` *(neu gegenüber vorigem Run)*
  - `app/Services/*.php`
  - `app/Module/*.php`
  - `app/Factories/*.php`
  - `app/Auth.php`, `app/Validator.php` *(neu als `core`-Typ)*

**Metriken:**

| Metrik | Wert |
|---|---|
| `scope_files_total` | 679 |
| `files_with_signals` | 464 |
| handler | 333 |
| middleware | 20 |
| service | 25 |
| factory | 15 |
| module | 70 |
| core | 1 |

**Regex-Korrekturen gegenüber altem Scanner:**

- `Validator::attributes(`/`::parsedBody(`/`::queryParams(`/`::serverParams(` jetzt als statische Calls erkannt (webtrees nutzt die static-Factory-API, nicht Request-Objekt-Methoden).
- `\bsystem\(` mit Word-Boundary → korrigiert das frühere False-Positive `mediaFilesystem(` als "dangerous system() call". **Dies war der Grund, warum ImageFactory.php im alten Scan `dangerous_count=3` hatte.** Der neue Scan liefert korrekt `dangerous_count=0`, da die SVG-XSS-Findung nicht über Pattern-Matching, sondern über T1-Lesung entdeckt wurde.

**Route-Reachability-Parser:**

- Stack-basierter Aura-Router-Parser für `WebRoutes.php` + `ApiRoutes.php`
- Erkennt `$router->attach()` Scope-Nesting und `$router->extras(['middleware' => [...]])` Auth-Marker
- Role-Ranking (admin=6, manager=5, moderator=4, editor=3, member=2, visitor=0) zur Ermittlung der niedrigsten Privilege-Stufe pro Handler

## Phase S3 — T1 LLM-Triage

- **Modell:** Opus 4.6 (direct, interactive) — Sonnet-Subagent-Delegation bei S2 gescheitert, daher T1 direkt im Main-Context gefahren
- **Methode:** Top-Down-Lesung der höchstgewichteten T0-Kandidaten (Pre-Score = `inputs*0.35 + db*0.25 + danger*0.40` skaliert mit reach/type)
- **Gelesene Dateien:**
  - Handler: `SearchGeneralPage`, `RegisterAction`, `CalendarAction`, `CalendarEvents`, `CalendarPage`, `AccountUpdate`, `SearchGeneralAction`, `ContactAction`, `MessageAction`, `VerifyEmail`, `ReportGenerate`, `EditFactAction`, `EditMediaFileAction`, `TreePrivacyAction`, `AddSpouseToFamilyAction`, `TreePage`, `ContactPage`, `ReportSetupAction`, `ReportListAction`, `AutoCompleteSurname`
  - Services: `SearchService` (whereSearch-Helper analysiert)
  - Reports: `ReportParserGenerate` (substituteVars + ExpressionLanguage Deep-Dive), `ReportExpressionLanguageProvider`
  - Modules: Grep-Probes gegen `ClippingsCartModule`, `FrequentlyAskedQuestionsModule`, `StatisticsChartModule` (Sink-Suche)
  - Routes: `WebRoutes.php` Lines 660-700 (Report-Scope-Verifikation)
- **Output:** `t1_llm_scores.json` mit 30 Kandidaten-Einträgen, 1 exkludiert (ImageFactory — SEC-AUDIT-001 bereits `fix_verified`)

**Wichtigster Befund dieser T1-Runde:**

`ReportGenerate` → `ReportParserGenerate` (llm_score=45, unter Cutoff): Architektonischer Defense-in-Depth-Gap. XML-Report-Templates enthalten `$var`-Platzhalter, die aus `Validator::queryParams($request)->array('vars')` befüllt werden. `substituteVars()` hat zwei Pfade:

1. **Quote-Pfad** (Zeile 1098): Wrapt in ExpressionLanguage-String-Literal via `addcslashes($val, "'")` → korrekt escaped. ExpressionLanguage-Function-Allowlist ist `[stristr]` → keine PHP-Execution.
2. **Raw-Pfad** (Zeilen 1366, 1452): Substituiert direkt in Filter-Strings, die per `preg_match` in Laravel-Query-Builder `where(col, 'LIKE', val)` fließen → bound parameters, keine SQLi.

**Urteil:** Heute nicht exploitable, aber die non-anchored Arithmetic-Regex bei Zeile 1071 macht den ExpressionLanguage-Pfad brittle. Wenn jemand den Function-Allowlist erweitert ohne Security-Review, wird das sofort gefährlich. Dokumentiert in `priorities.md`, aber keine Task erzeugt (0.112 < 0.25 Cutoff).

## Phase S4 — T2 Track-Zuordnung (mechanisch)

- Keine neuen Assignments. Drei `observations`-Einträge für Follow-Up-Dokumentation:
  - `ReportGenerate` → non-admin (info-disclosure)
  - `ClippingsCartModule` → non-admin (path-traversal theoretical)
  - `EditMediaFileAction` → non-admin (path-traversal theoretical, Flysystem-gated)
- Output: `t2_tracks.json`

## Phase S5 — T3 Priorisierung

- **Formel:** `final_score = 0.25*crap_n + 0.15*inputs_n + 0.15*db_n + 0.25*danger*reach + 0.20*llm_n`
- **Cutoff:** `final_score < 0.25`
- **Höchster Score diesen Run:** ReportGenerate 0.112 → **unter Cutoff**
- **Tasks erzeugt:** 0
- **Outputs:** `priorities.md`, `task_deltas.json`

## Phase S6 — Task-Sync

- `docs/security-audit/tasks/INDEX.md` bleibt unverändert (keine neuen Tasks).
- Bestehende Tasks werden nicht dupliziert:
  - `SEC-AUDIT-001` (`fix_verified`): ImageFactory.php im neuen Scan `dangerous_count=0` — Fix ist intakt
  - `SEC-AUDIT-002` (`queued`): MediaFileService Upload-Allowlist-Gap reconfirmed
  - `SEC-AUDIT-003` (`queued`): replacementImageResponse CSP Gap unchanged
  - `SEC-AUDIT-004` (`queued`): SVG Serve-Path Enumeration unchanged

## Phase S7 — Erste Hypothesen-Runde

- **Übersprungen** — kein Kandidat erreichte den Cutoff.

## Phase S8 — Summary

- Dieses Dokument.
- Lock wird durch Driver-EXIT-Trap released (s. `scripts/security-audit-sweep.sh:134`).

## Deviation-Log (Regel-Änderungen gegenüber altem Sweep)

1. **Word-Boundary für dangerous-function-Patterns**: `\bsystem\(`, `\bexec\(`, `\bpasshru\(`, `\bassert\(`, `\beval\(`. Verhindert FP aus substrings wie `mediaFilesystem(`, `filesystem(`. Das war ein signifikanter Fehler im vorigen Scan.
2. **Validator-Static-API erkannt**: `Validator::attributes\(|->attributes\b|->getAttribute\(` und analog für parsedBody/queryParams/serverParams. webtrees nutzt die static Factory-API, nicht Request-Methoden.
3. **Scope erweitert**: `app/Http/Exceptions/*.php`, `app/Auth.php`, `app/Validator.php`.
4. **`files_with_signals` vs `scope_files_total` getrennt**: Der alte Scan output zählte nach Filter, was die 371 vs 679 Differenz erklärt.
5. **Driver-Skript Stack-Health-Check**: `podman-compose ps webtrees` funktioniert nicht (Positional-Arg nicht unterstützt) — jetzt `podman-compose ps` + `awk '$NF == "webtrees"'`. Uncommitted change.

## Zusammenfassung für Task-Index-Aggregate

- Tasks erzeugt: 0
- Tasks aktualisiert: 0
- Tasks dropped (unter Cutoff): 3 (dokumentiert, nicht queued)
- Halt-Flag: nein
- Advisory-Lock: released (trap)

## Next Action für User

1. **Review `priorities.md`** (dieser Run-Dir) — insbesondere die ReportParserGenerate-Defense-in-Depth-Analyse.
2. **Manuelle PR-Eröffnung für SEC-AUDIT-001** — Commit `b2dc869b90` auf fork branch `security-audit-001-svg-filter-hardening`. Siehe `docs/security-audit/tasks/SEC-AUDIT-001_svg_xss_media_upload.md` § "Offene Punkte".
3. **Entscheidung**: Sollen SEC-AUDIT-002/003/004 im nächsten Deep-Dive-Run abgearbeitet werden, oder bleiben sie queued?
4. **Entscheidung**: Soll `ClippingsCartModule` (48 inputs, höchste Dichte im Module-Scope) im nächsten Sweep-Lauf als strukturierter Audit queued werden?
