<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-006
title: RenumberTreeAction — raw Expression() string interpolation, REGEX_XREF-only defense (defense-in-depth gap)
created: 2026-04-09
last_updated: 2026-04-09
status: fix_verified
track: admin
file: app/Http/RequestHandlers/RenumberTreeAction.php
contributing_files:
  - app/Gedcom.php
  - app/Services/AdminService.php
  - app/Factories/XrefFactory.php
verticals_hit:
  - V5_sqli_readwrite
final_score: 0.263
llm_score: 55
t0_signals:
  crap: 0
  crap_coverage_pct: 0.0
  input_sinks:
    - Validator::parsedBody / attributes (tree selection)
  db_sinks:
    - 31× new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', ...)")
    - i_gedcom, f_gedcom, s_gedcom, m_gedcom, o_gedcom, i_id, f_id, s_id, m_id, o_id
  dangerous_functions:
    - new Illuminate\Database\Query\Expression with PHP string interpolation
  routing_entry_points:
    - POST /admin/trees-renumber (manager-track)
  reachability: manager
  type_weight: 0.4
  auth_requirement: manager
  loc: ~500
hypotheses:
  - H1_regex_xref_defense_holds_today
  - H2_refactor_required_for_future_robustness
current_hypothesis: H1
probe_iteration_count: 0
validation_failure_count: 0
fixture_rev: 0
fix_branch: security-audit-006-renumber-xref-guard
disclosure_state: ready_for_manual_pr
blocked_by: []
notes_for_opus: |
  Discovered in V1b / re-verified in V1e.1 of verification run
  verify-2026-04-08T21-45-10. Sweep T1 had misclassified the file as
  "mass xref-renumber via query builder" — the actual code uses 31×
  `new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', ...)")`
  with PHP string interpolation (NOT parameter binding).

  **Not exploitable today**: xref values originate either from
  `Registry::xrefFactory()->make($type)` (factory-constructed, safe by
  construction) or from DB columns `i_id`/`f_id`/`s_id`/`m_id`/`o_id`,
  which are only written by the GEDCOM parser through
  `Gedcom::REGEX_XREF = '[A-Za-z0-9:_.-]{1,20}'` (app/Gedcom.php:243).
  None of those characters can terminate a single-quoted SQL string,
  so no SQLi primitive exists at present.

  **Why defense-in-depth gap**:
    - Safety depends on an invariant held by a different file
      (Gedcom.php REGEX_XREF).
    - Any loosening of REGEX_XREF or any alternative DB-write path
      that bypasses the parser (plugin, admin tool, schema migration)
      turns this into an immediate manager-reach SQLi primitive.
    - The column types VARCHAR(20) also enforce length; relaxing that
      without tightening xref validation would enable richer payloads.

  **Scoring (V1b computation)**:
    inputs_n 0.021 · 0.15 = 0.003
    db_n 1.000 · 0.15     = 0.150
    danger_n 0.000 · 0.25 = 0.000
    llm_n 0.550 · 0.20    = 0.110
    final_score ≈ 0.263 (marginally over 0.25 cutoff)

  **Fix direction**:
    Replace every `new Expression("REPLACE(...)")` with code-side string
    manipulation: load the affected GEDCOM record via the tree service,
    perform `str_replace()` in PHP, write back via parameter-bound
    update. Removes the dependency on REGEX_XREF as sole line of defense
    and is simpler to test.

  **Alternative (minimum)**:
    Keep raw Expression but add a local `preg_match(Gedcom::REGEX_XREF_STRICT, $old_xref)`
    assertion at the top of each method body. Prevents future non-GEDCOM
    write paths from poisoning the Renumber action.

  **Track assignment**: admin (manager is authenticated, non-trivial
  privilege but not non-admin visitor reach). Do NOT escalate to
  non-admin track.

  **V3 decision record**: queued with label defense-in-depth per user
  decision 2026-04-09 (Conversation 1 post-crash).

  **Test-first regression requirement**: the regression test should
  attempt to write a crafted xref containing SQL metacharacters directly
  to `i_id` (bypassing GEDCOM parser via raw DB insert in the fixture
  setup), then invoke RenumberTreeAction and assert that the DB is
  either rejected (fix branch: local regex assertion) or sanitized
  (fix branch: PHP str_replace). The key: the test must prove the
  defense still holds if REGEX_XREF ever weakens.
---

# SEC-AUDIT-006 — RenumberTreeAction raw Expression() interpolation (defense-in-depth)

## Triage-Kontext

- **Warum queued**: V3-Entscheidung nach Verification-Runde `verify-2026-04-08T21-45-10` — das Finding liegt marginal über dem 0.25-Cutoff (`final_score ≈ 0.263`) und ist defense-in-depth, nicht live exploitable.
- **Verticals**: V5_sqli_readwrite (latent, nicht aktiv)
- **Track-Assignment**: admin (manager-reach, nicht visitor)
- **Label**: `defense-in-depth` (nicht `active exploit`)

## Vulnerability Description

### Root Cause

`app/Http/RequestHandlers/RenumberTreeAction.php` verwendet 31× das Muster:

```php
'i_gedcom' => new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', '0 @$new_xref@ INDI')"),
```

`new Illuminate\Database\Query\Expression(...)` inlined seinen String-Inhalt **verbatim** in das generierte SQL **ohne Parameter-Binding**. `$old_xref` und `$new_xref` werden per PHP-String-Interpolation in ein Raw-SQL-Literal eingefügt.

Die Methode iteriert über Dubletten-xrefs aus der Datenbank (`AdminService::duplicateXrefs($tree)`) und ersetzt sie in allen GEDCOM-Feldern (`i_gedcom`, `f_gedcom`, `s_gedcom`, `m_gedcom`, `o_gedcom`) sowie allen Key-Spalten (`i_id`, `f_id`, ...). Außerdem werden Tags (`'CHIL'`, `'ASSO'`, `'_ASSO'`, `'FAMC'`, `'FAMS'`, `'ALIA'`) hardkodiert eingesetzt — diese sind safe by construction.

### Quellen der interpolierten Variablen

| Variable | Quelle | Trust |
|---|---|---|
| `$old_xref` | DB-Spalten `i_id` / `f_id` / `s_id` / `m_id` / `o_id` via `AdminService::duplicateXrefs()` | DB-stored, regelrecht validiert durch GEDCOM-Parser |
| `$new_xref` | `Registry::xrefFactory()->make($type)` | **Safe by construction** (Factory generiert alphanumerisch) |
| `$type` | Hardcoded Konstanten (`Individual::RECORD_TYPE = 'INDI'`) oder `o_type`-Spalte im Default-Zweig | Hardcoded safe; `o_type` regulär GEDCOM-Tag-normalisiert |
| `$tag` | Hardcoded String-Literale im `foreach`-Kontext | Safe by construction |

### Die Defense-in-Depth-Kette

Die Sicherheit der Interpolation hängt heute **ausschließlich** von einem Invariant in `app/Gedcom.php:243` ab:

```php
public const REGEX_XREF = '[A-Za-z0-9:_.-]{1,20}';
```

Kein Character dieses Sets kann einen einfach-gequoteten SQL-String terminieren (kein `'`, `"`, `\`, `;`, etc.). Jede xref, die durch den GEDCOM-Parser geht, ist damit safe-to-interpolate. Die `VARCHAR(20)` Column-Type-Einschränkung addiert eine zusätzliche Schutzebene gegen Längenüberläufe.

### Warum trotzdem ein Finding

1. **Lokale Abwesenheit jeder Validierung** am Interpolation-Punkt. Die Datei liest einfach `$old_xref` aus dem Array und interpoliert.
2. **Versteckter Dependency-Contract**: Zukünftige Änderungen an `REGEX_XREF` (Loosening für internationale Zeichen, Unicode-Unterstützung, etc.) können diese Datei zu einer Live-SQLi-Primitive machen — ohne dass der Maintainer der Gedcom.php-Änderung davon weiß.
3. **Alternative DB-Write-Pfade**: Wenn ein Admin-Tool, ein Plugin oder eine Schema-Migration jemals xrefs schreibt, ohne den GEDCOM-Parser zu durchlaufen, wird RenumberTreeAction zum SQLi-Primitive.
4. **Schema-Lockerung**: Eine Spalten-Type-Änderung von `VARCHAR(20)` zu `TEXT` ohne gleichzeitige Tightening-Änderung an der Validierung erhöht den Angriffsradius.

**Nicht exploitable heute** — alle diese Fehlermodi sind hypothetisch. Aber die Sicherheit ruht auf einem Contract, der nicht an dieser Datei dokumentiert ist.

## Nicht-exploitable-Nachweis (Probe-Plan)

Vor jeglichem Fix muss empirisch bestätigt werden, dass der aktuelle Pfad defensiv ist:

1. **Upload-Test**: Eine crafted GEDCOM-Datei mit xref `@I1';DROP TABLE wt_individuals--@` hochladen, Import-Verhalten prüfen. **Erwartung**: Parser lehnt die Datei ab oder normalisiert die xref.
2. **Direct-Insert-Test**: In einer Fixture-DB direkt ein `i_id = "evil'value"` per Raw-Insert schreiben (bypasst Parser). Dann RenumberTreeAction ausführen. **Erwartung**: SQL-Fehler oder gebrochene DB — beweist die latente Exploit-Primitive, wenn der GEDCOM-Parser je bypasst wird.
3. **Regex-Review**: `Gedcom::REGEX_XREF` gegen aktuelle i18n-Tickets im upstream-Tracker prüfen — gibt es offene Requests zur Lockerung?

## Fix-Empfehlung

### Empfohlene Variante (architektonisch)

Ersetze jede `new Expression("REPLACE(...)")` durch code-side String-Manipulation:

```php
// Vorher (RenumberTreeAction.php:79):
DB::table('individuals')
    ->where('i_file', '=', $tree->id())
    ->where('i_id', '=', $old_xref)
    ->update([
        'i_id'     => $new_xref,
        'i_gedcom' => new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', '0 @$new_xref@ INDI')"),
    ]);

// Nachher:
$rows = DB::table('individuals')
    ->where('i_file', '=', $tree->id())
    ->where('i_id', '=', $old_xref)
    ->get(['i_gedcom']);

foreach ($rows as $row) {
    $new_gedcom = str_replace(
        "0 @{$old_xref}@ INDI",
        "0 @{$new_xref}@ INDI",
        $row->i_gedcom,
    );

    DB::table('individuals')
        ->where('i_file', '=', $tree->id())
        ->where('i_id', '=', $old_xref)
        ->update([
            'i_id'     => $new_xref,
            'i_gedcom' => $new_gedcom,
        ]);
}
```

Vorteile:
- Keine SQL-Interpolation mehr
- Logik ist unit-testbar ohne DB-Fixture
- Maintainability und Lesbarkeit besser
- Unabhängig von `REGEX_XREF`

Nachteile:
- Mehr DB-Roundtrips (N+1 in ungünstigen Fällen)
- Migration müsste für alle 31 Stellen vollzogen werden
- Performance-Regression bei sehr großen Trees (tausende Dubletten)

### Alternative (minimum fix)

Lokale Assertion am Anfang jeder Methode:

```php
if (!preg_match('/^' . Gedcom::REGEX_XREF . '$/', $old_xref)) {
    throw new RuntimeException('Invalid xref in renumber action');
}
if (!preg_match('/^' . Gedcom::REGEX_XREF . '$/', $new_xref)) {
    throw new RuntimeException('Invalid xref in renumber action');
}
```

Vorteile:
- 1-Block Change
- Macht den Contract explizit
- Kein Performance-Impact

Nachteile:
- Duplication der REGEX-Logik
- Bricht, wenn REGEX_XREF zukünftig lowercased / aliased wird

## Test-First Regression Requirement

**Layer-3 Integration Test** (`layer3-integration/tests/Security/SecAudit006Test.php`):

```php
public function test_renumber_tree_rejects_xref_with_sql_metachar(): void
{
    // Fixture: Seed DB directly with a corrupt xref (bypassing parser)
    DB::table('individuals')->insert([
        'i_file'   => $this->tree->id(),
        'i_id'     => "I1'; DROP TABLE users; --",
        'i_gedcom' => "0 @I1'; DROP TABLE users; --@ INDI\n",
        'i_rin'    => '',
        'i_sex'    => 'U',
    ]);

    $this->expectException(RuntimeException::class);
    // OR assert that users table still exists after the action:
    // (new RenumberTreeAction($admin_service))->handle($request);
    // assertDatabaseTableExists('users');
}
```

Der Test muss **vor** jedem Fix existieren und:
- bei Minimum-Fix → RuntimeException fangen
- bei architektonischem Fix → beweisen, dass str_replace keine SQL-Interpolation triggert (users-Tabelle intakt nach Aktion)

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/deepdive/006/context.md`
- generated_at: 2026-04-09
- Fokus: 31× raw `Expression("REPLACE(...)")` mit PHP-String-Interpolation. Defense-Chain: REGEX_XREF (L1), GEDCOM-Parser (L2), VARCHAR(20) (L3), Admin-MW (L4). Keine lokale Validierung am Interpolation-Punkt.

### Phase D2 — Hypothesen
- hypotheses_file: `artifacts/security-audit/deepdive/006/hypotheses.md`
- hypothesen_count: 3 (H1 rejected, H2 rejected, H3 rejected)
- H1: SQLi via malformed old_xref — rejected (REGEX_XREF blocks)
- H2: SQLi via $type in default case — rejected (GEDCOM parser validates)
- H3: Second-order injection via data corruption — rejected (alle bekannten Write-Pfade validieren)
- Alle latent, nicht exploitierbar heute.

### Phase D3/D4 — Probe-Loop (Code-Read, kein Container-Probe)
- Code-Read bestätigt: REGEX_XREF-Zeichenklasse enthält keine SQL-Metazeichen.
- RED-Test beweist: malformed xref `"I1' OR 1=1--"` → `QueryException` (SQL-Syntax-Fehler) wenn kein Guard.
- Kein Container-Probe nötig — Code-Read + RED-Test genügt.

### Phase D5 — Regression
- regression_file: `upstream/webtrees/tests/app/Http/RequestHandlers/RenumberTreeActionTest.php`
- test_count: 2 (1x noDuplicates + 1x malformedXref)
- assertions: 6
- Test-first: Commit `1b60305289` (volatile) — testRenumberSkipsMalformedXref RED (QueryException)

### Phase D6 — Fix-Draft
- **fix_branch (authoritativ):** `security-audit-006-renumber-xref-guard` in `/home/borisunckel/phpprojects/webtrees-upstream/webtrees`, abgezweigt von Fork-`main` @ `c338276a5a`
- **Fix-Commits (authoritativ, Fork):**
  - Test: `c17c4f6545` (GPG) — RED-Test für xref-Validation
  - Fix: `5735f9e9b1` (GPG) — preg_match Guard vor Expression-Interpolation
- **Fix-Commits (volatile, non-authoritative):**
  - Test: `1b60305289`
  - Fix: `7da28e58b6`
- diff_size: RenumberTreeAction.php +6 Zeilen (Gedcom import, preg_match import, 4 Zeilen Guard), RenumberTreeActionTest.php +38/-2 Zeilen (2 Tests)
- Fix-Ansatz: Minimum local assertion (`preg_match(REGEX_XREF)` Guard am Anfang der foreach-Schleife, `continue` bei Mismatch)

### Phase D7 — Validation
- validation_artifacts: `artifacts/security-audit/deepdive/006/d7_validation/`
- Layer 1 `php -l`: OK (`php_lint.txt`)
- Layer 2 `RenumberTreeActionTest`: OK (2 tests, 6 assertions) (`layer2_green.txt`)
- Code-read: Guard verwendet `\A`/`\z`-Anker (kein `^`/`$` mit PCRE_MULTILINE-Risk), Regex identisch mit Gedcom::REGEX_XREF, `continue` statt Exception (graceful skip, keine Side-Effects).
- gesamturteil: **fix_verified**

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-09 | queued | Erzeugt aus V1b/V1e.1 Findings nach V3-User-Decision |
| 2026-04-09 | fix_committed | Test-First `1b60305289` + Fix `7da28e58b6` im volatilen Scratch-Clone |
| 2026-04-09 | fix_verified | Layer 1+2 green, Code-read bestätigt Fix-Wirksamkeit |
| 2026-04-09 | fix_verified (Mirror) | 2 Commits per `git cherry-pick -S` in authoritativen Fork — Test `c17c4f6545`, Fix `5735f9e9b1`, Branch `security-audit-006-renumber-xref-guard` @ Fork-`main`. |
