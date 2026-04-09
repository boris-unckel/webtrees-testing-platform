<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# V1b — Raw-SQL Audit Across Services + RequestHandlers

**Verification target**: The sweep 2026-04-08T20-58-28 claimed `grep -E 'whereRaw|orderByRaw|DB::raw|->raw\(' in app/Services/SearchService.php → 0 Treffer` and generalized this as "durchgängig bound-parameter DB-Zugriffe". This V1b round checks (a) whether the generalization holds across the whole `app/` tree, and (b) whether the grep missed other raw-SQL primitives.

## Scope expansion

**Sweep scope**: Only `SearchService.php`.
**Sweep patterns**: `whereRaw|orderByRaw|DB::raw|->raw\(`.

**V1b scope**: `app/Services/`, `app/Module/`, `app/Http/`, `app/Statistics*.php`.
**V1b patterns**: sweep patterns + `selectRaw|havingRaw|groupByRaw|fromRaw|new Expression\(|Expression\(`.

**Rationale for adding `new Expression(...)`**: Laravel's query builder has TWO ways to inject raw SQL. `DB::raw()` and `new Illuminate\Database\Query\Expression(...)` are equivalent — `DB::raw()` literally returns a new `Expression`. When an `Expression` object is passed as a column, value, or filter, its contents are inlined verbatim into the SQL without parameter binding. The sweep's grep missed every `new Expression(...)` usage.

## Results

### Category 1: `*Raw` methods

| File | Line | Expression | Verdict |
|---|---|---|---|
| `app/StatisticsData.php` | 138 | `->orderByRaw('COUNT(n_surn) DESC')` | Hardcoded constant. **Safe.** |
| `app/Statistics.php` | 408 | `->selectRaw('ROUND((d_year + 49) / 100, 0) AS century')` | Hardcoded constant. **Safe.** |
| `app/Statistics.php` | 409 | `->selectRaw('COUNT(*) AS total')` | Hardcoded constant. **Safe.** |
| `app/Statistics.php` | 1525 | `->selectRaw('AVG(f_numchil) AS total')` | Hardcoded constant. **Safe.** |
| `app/Statistics.php` | 1526 | `->selectRaw('ROUND((d_year + 49) / 100, 0) AS century')` | Hardcoded constant. **Safe.** |

5/5 safe. No variable interpolation.

### Category 2: `new Expression(...)` in `app/Services/`

| File | Lines | Interpolated? | Source | Verdict |
|---|---|---|---|---|
| `AdminService.php` | 125, 126, 149, 150, 160-174, 200-209 | No | Hardcoded literals + `DB::groupConcat(...)` with hardcoded column names | **Safe.** |
| `GedcomExportService.php` | 381-443 | No | `'LENGTH(x_id)'` literals | **Safe.** |
| `DatatablesService.php` | 156 | `1 + $value['column']` | User input (datatables column index) **coerced to int via `+`** | **Safe** — PHP numeric coercion forces the result to int/float regardless of user input. |
| `DatatablesService.php` | 161 | No | `new Expression(1)` | **Safe.** |
| `MapDataService.php` | 240, 241 | `DB::prefix('p1')`, `$expression` | `$expression` is built at lines 202-213 from `DB::prefix(...)` and hardcoded SQL literals only | **Safe.** |
| `MediaFileService.php` | 281, 284, 288, 289, 327 | `$path` | `$path = DB::concat(['media_folder', 'multimedia_file_refn'])` — hardcoded column names | **Safe.** |
| `SearchService.php` | 126, 1124 | No | `DB::prefix(...)` + `DB::concat([...])` with hardcoded columns | **Safe.** |
| `PendingChangesService.php` | 261 | No | Literal | **Safe.** |
| `LinkedRecordService.php` | 58 | No | `'MAX(change_id)'` literal | **Safe.** |
| `SiteLogsService.php` | 60 | No | Literal | **Safe.** |
| `TreeService.php` | 166, 176 | `$tree->id()` | Tree ID is an **int** (from DB PK) and serialized as numeric literal | **Safe.** |

### Category 3: `new Expression(...)` in `app/Module/`

| File | Lines | Interpolated? | Verdict |
|---|---|---|---|
| `SiteMapModule.php` | 189-228 | No (`'COUNT(*) AS total'`) | **Safe.** |
| `FamilyTreeStatisticsModule.php` | 115 | No | **Safe.** |
| `ReviewChangesModule.php` | 156 | No | **Safe.** |
| `RecentChangesModule.php` | 292 | No | **Safe.** |
| `AbstractIndividualListModule.php` | 543, 575 | No | **Safe.** |
| `TopSurnamesModule.php` | 98 | No | **Safe.** |

### Category 4: `new Expression(...)` in `app/Http/RequestHandlers/`

| File | Lines | Interpolated? | Source | Verdict |
|---|---|---|---|---|
| `TreePage.php` | 61 | `$tree->id()` | DB PK int | **Safe.** |
| `ControlPanel.php` | 227-338 | No | Hardcoded aggregates | **Safe.** |
| `MergeTreesAction.php` | 64, 82, 99, 113, 129, 146, 171, 201, 222 | `$tree2->id()` | DB PK int | **Safe.** |
| `CheckTree.php` | 94, 97, 100, 103, 111 | No | `"'INDI' AS type"` style literals | **Safe.** |
| `FixLevel0MediaData.php` | 81 | `DB::prefix('media')` | Admin-configured prefix (trusted) | **Safe.** |
| `MergeFactsAction.php` | 122 | No | `'SUM(page_count) AS total'` | **Safe.** |
| `UserPage.php` | 60 | `$user->id()` | DB PK int | **Safe.** |
| `ManageMediaData.php` | 101, 164, 167, 188 | No | `DB::concat(...)` with hardcoded columns | **Safe.** |
| **`RenumberTreeAction.php`** | **79, 87, 95, 110, 126, 159, 174, 199, 212, 225, 238, 251, 262, 275, 286, 299, 312, 325, 338, 351, 361, 381, 394, 407, 420, 431, 444, 457, 470, 483, 496** | **`$old_xref`, `$new_xref`, `$type`, `$tag`** | **DB-stored xref/tag values** | **See detailed analysis below.** |

## RenumberTreeAction — detailed analysis

**Claim re-examined**: The sweep priorities.md line 98 classified this file as `"Mass xref-renumber via query builder"` — implying it uses parameter binding. **This is a misread.**

**Actual code** (`app/Http/RequestHandlers/RenumberTreeAction.php:79`):
```php
'i_gedcom' => new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', '0 @$new_xref@ INDI')"),
```

`$old_xref` and `$new_xref` are interpolated via PHP string interpolation into a raw SQL expression that is **not parameter-bound**. This pattern repeats ~31 times across the file for every record-type combination. The `default` branch (lines 424-497) also interpolates `$type` (the raw GEDCOM record-type tag).

### Data-source analysis

1. **`$old_xref`** source: `$xrefs = $this->admin_service->duplicateXrefs($tree);` → queries `i_id`, `f_id`, `s_id`, `m_id`, `o_id` columns from the DB. These columns store xref values.

2. **`$new_xref`** source: `Registry::xrefFactory()->make($type)` — webtrees factory generates a fresh xref. Constrained to safe format (alphanumeric). **Safe by construction.**

3. **`$type`** source: hardcoded constants (`Individual::RECORD_TYPE = 'INDI'` etc.) for the explicit `switch` cases, OR the `o_type` column value in the `default` branch.

4. **`$tag`** source: hardcoded constants (`'CHIL'`, `'ASSO'`, `'_ASSO'`, `'FAMC'`, `'FAMS'`, `'ALIA'`) in `foreach` loops. **Safe by construction.**

### Upstream validation — the defense-in-depth chain

The xref columns are written by the GEDCOM parser, which extracts xrefs via `Gedcom::REGEX_XREF = '[A-Za-z0-9:_.-]{1,20}'` (`app/Gedcom.php:243`). None of the characters in this set are SQL metacharacters — the set does not include `'`, `"`, `\`, `;`, or other quote-escape sequences. Therefore:

- Any xref passing the GEDCOM parser regex is **safe to interpolate** into a single-quoted SQL string literal, because none of its characters can terminate the string.
- Similarly, `REGEX_TAG` (not inspected in detail here) is the normalized GEDCOM tag pattern, which is also restricted to alphanumeric + underscore.

### Verdict on current exploitability: **NOT exploitable today**

- Every reach to the raw interpolation point goes through `Gedcom::REGEX_XREF` and `Gedcom::REGEX_TAG` validation during GEDCOM parsing.
- A manager who uploads a crafted GEDCOM cannot inject SQL metachars because the parser drops any line whose xref/tag doesn't match the regex.
- Direct DB writes by the manager require pre-existing SQLi elsewhere, i.e., are not self-sufficient exploit paths.

### Verdict on defense posture: **FRAGILE**

The safety of this code depends on an **invariant held by a different file**. Specifically:
- Safety depends on `app/Gedcom.php:243` keeping its `REGEX_XREF` restrictive.
- Safety depends on every GEDCOM-writing code path enforcing the same regex.
- No local validation at the interpolation point.

**Failure modes**:
1. Any future change loosening `REGEX_XREF` to include `'` or `\` would turn this into an immediate SQLi (visible at manager-reach).
2. Any new admin tool or plugin that writes to `i_id`/`f_id`/`s_id`/`m_id`/`o_id`/`o_type` without going through the GEDCOM regex would become an exploit primitive via the Renumber action.
3. A column-type change that allows longer strings (e.g., if someone relaxes `VARCHAR(20)` to TEXT) without tightening xref validation would enable richer payloads.

**Methodology gap in the sweep**: grep-based T0 inventory cannot catch `new Expression(...)` patterns by default. The T1 LLM triage could have caught it if it had read the file, but RenumberTreeAction scored below the T1 cut (only 1 input, 43 db — admin-track observation).

## Claim verification summary

| Sweep claim | Verified? | Refinement |
|---|---|---|
| "`whereRaw|orderByRaw|DB::raw|->raw\(` → 0 Treffer in SearchService.php" | **CONFIRMED** | SearchService.php has 0 hits for those patterns. |
| "bound parameter DB-Zugriffe durchgängig" (generalized) | **CONFIRMED with caveat** | No live SQLi found anywhere in `app/`. But the methodology missed `new Expression(...)` primitives. |
| "RenumberTreeAction uses query builder (manager-track admin observation)" | **REFINED** | Uses raw `new Expression(...)` with variable interpolation. Safety depends on upstream `REGEX_XREF` validation, not on parameter binding. Defense-in-depth violation but not currently exploitable. |

## New follow-up observations (no tasks created by V1b itself)

1. **Sweep methodology fix**: future T0 scans should grep for `new Expression\(` in addition to the `*Raw` patterns. Both are equivalent raw-SQL primitives in Laravel query builder.

2. **RenumberTreeAction defense-in-depth recommendation**: replace the raw `REPLACE(...)` expressions with a code-side string manipulation (load the gedcom, do `str_replace` in PHP, save via bound parameter). This removes the dependency on the upstream regex invariant and is more testable. **Not our task** — upstream-webtrees-maintainer scope. V3 can decide whether to file this as SEC-AUDIT-005 or document as observation only.

3. **Documentation correction for sweep priorities.md**: line 98 entry `"RenumberTreeAction.php | 1 input, 43 db, manager | Mass xref-renumber via query builder"` should be corrected to `"Mass xref-renumber via raw Expression() interpolation, safe-by-REGEX_XREF validation only (defense-in-depth gap)"`.

## Score impact on RenumberTreeAction

Recompute with the raw-SQL finding:
- crap: n/a → 0
- inputs_n = 1/48 = 0.021 → **0.003**
- db_n = 43/43 = 1.0 → **0.150**
- danger_n = 0 → **0.000**
- llm_n (revised from ~5 to 55 given the defense-in-depth finding): 0.55 → **0.110**

**final_score ≈ 0.263** — marginally above the 0.25 cutoff.

**Decision deferred to V3**: should this become `SEC-AUDIT-005`? Arguments for:
- Above cutoff by the formula
- Defense-in-depth gap with clear remediation
- Manager-reach is a non-trivial privilege (but not admin)

Arguments against:
- Not currently exploitable (REGEX_XREF enforced upstream)
- Remediation is architectural, not a one-line fix — ownership should lie with upstream webtrees maintainers
- Manager role already has many legitimate mass-data-modification primitives; adding SQLi via this path is marginal privilege escalation

**V1b recommendation to V3**: create task as **`queued`** with `track=admin` (because manager-only, not non-admin) and clearly label it `defense-in-depth` rather than `active exploit`. The task body should include a reproducer attempt to empirically prove the REGEX_XREF defense works today.
