<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# V1a — ExpressionLanguage + ReportParserGenerate Deep-Verify

**Verification target**: The sweep 2026-04-08T20-58-28 claimed the ReportParserGenerate/ExpressionLanguage path was "no exploit today" based on a `[stristr]` function allowlist and `addcslashes` escape correctness. This V1a round checks whether those claims actually hold up against the source code.

## Claims re-examined

### Claim A: "ExpressionLanguage function allowlist = `[stristr]`"

**Status: REFUTED**

Source: `/var/www/html/vendor/symfony/expression-language/ExpressionLanguage.php:151-170` (Symfony EL 7.x, copied to `/tmp/verify-vendor/` for inspection).

```php
protected function registerFunctions()
{
    $basicPhpFunctions = ['constant', 'min', 'max'];
    foreach ($basicPhpFunctions as $function) {
        $this->addFunction(ExpressionFunction::fromPhp($function));
    }
    $this->addFunction(new ExpressionFunction('enum', ...));
}
```

`ExpressionLanguage::__construct` **unconditionally** calls `registerFunctions()` (line 40) **before** registering the user-provided `ReportExpressionLanguageProvider`. The provider's `stristr` function is **added on top** of the defaults, not a replacement.

**Actual allowlist**: `[constant, min, max, enum, stristr]`

**Why this matters**:
- `constant($name)` returns the value of any PHP-defined constant. Webtrees defines e.g. `Webtrees::DATA_DIR`, `Webtrees::VERSION`. PHP itself defines `PHP_VERSION`, `PHP_BINARY`, etc. If `constant()` is reachable with attacker-controlled argument, that's info disclosure of the PHP runtime environment.
- `enum($str)` internally calls `\constant($str)` and checks `instanceof \UnitEnum`. On constants that are not enums, it throws. Limited to enum types.
- `min`/`max` are pure numeric, not a primitive escalation.
- `stristr` is the provider's only addition.

### Claim B: "`addcslashes($val, \"'\")` correctly escapes for EL string literals"

**Status: CONFIRMED** (after careful lexer analysis)

Source: `/tmp/verify-vendor/symfony/expression-language/Lexer.php:68`:
```regex
"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'
```

The single-quoted string grammar is `'([^'\\]*(?:\\.[^'\\]*)*)'`. Inside the string, escape sequences are `\\` followed by any char. The Lexer uses `stripcslashes()` on the captured content.

**Attack attempt 1** — inject unescaped `'` to break out:
- Payload: `pid = ' || constant("FOO") || '`
- After `addcslashes($payload, "'")`: `\' || constant("FOO") || \'`
- Wrapped in substituteVars(quote=true): `'\\' || constant("FOO") || \\''`
- Lexer greedy-matches the entire wrapped string as ONE string literal (content = `' || constant("FOO") || '` after stripcslashes). **No token break**, no injection.

**Attack attempt 2** — trailing backslash (abuse `\\.` regex consuming more than intended):
- Payload: `pid = abc\\`
- After `addcslashes`: `abc\\` (backslash is not in escape list)
- Wrapped: `'abc\\'`
- Lexer: `'` open, `[^'\\]*` = `abc`, `\\.` matches `\\'` (backslash + quote = one escape sequence). No closing `'` found. Regex fails, falls through to "unlexable" → **SyntaxError**.
- Result: parser rejects, no injection, no DoS beyond a caught exception.

**Why `addcslashes` is sufficient**: EL string-literal escape only requires escaping the `'` character inside single-quoted strings. `addcslashes` does exactly that. The only edge case (trailing backslash) triggers a parser SyntaxError rather than silent injection.

**Caveat**: This correctness relies on the wrapping `'...'` being applied consistently. Any future change that substitutes the wrapping with double quotes would require re-evaluating the escape list.

### Claim C: "Raw-substitution path only feeds bound-parameter LIKE queries → no SQLi"

**Status: CONFIRMED with refinement**

The `substituteVars($value, false)` raw path has 2 call-sites:
- `ReportParserGenerate.php:1366` (individual list filter)
- `ReportParserGenerate.php:1452` (family list filter)

Both feed `$value` through `preg_match` patterns and then into Laravel query builder `->where(column, 'LIKE', value)` with bound parameters. Checked filter patterns:

| Regex | SQL sink | Bound? | Wildcard escape | Verdict |
|---|---|---|---|---|
| `^(\w+):DATE (LTE|GTE) (.+)$` | `->where('d_julianday1', ...)` with `Date()->minimumJulianDay()` (integer) | Bound | N/A (integer) | Safe |
| `^NAME CONTAINS (.+)$` | `LIKE '%' . addcslashes($name, '\\%_') . '%'` | Bound | **Yes** | Safe |
| `^LIKE /(.+)/$` | `LIKE $match[1]` **directly** | Bound | **No** | **DoS-only** (see below) |
| `^(?:\w*):PLAC CONTAINS (.+)$` | `LIKE '%' . addcslashes($match[1], '\\%_') . '%'` | Bound | Yes | Safe |
| `^(\w*):(\w+) CONTAINS (.+)$` | Escaped via `strtr(['\\'=>'\\\\', '%'=>'\\%', '_'=>'\\_', ' '=>'%'])` + bound LIKE | Bound | Yes | Safe |
| `^(\w+) CONTAINS (.*)$` | Same `strtr` escape + bound LIKE | Bound | Yes | Safe |

**Refinement**: The `^LIKE /(.+)/$` branch at `ReportParserGenerate.php:1405` passes `$match[1]` (post-newline-decoding) directly as the LIKE value:
```php
$query->where('i_gedcom', 'LIKE', $match[1]);
```
This is a bound parameter (no SQL injection), but the **LIKE pattern itself** is attacker-controlled without wildcard escaping. An attacker-supplied `%%%%%%`-style pattern would cause a catastrophic full-table LIKE scan on `individuals.i_gedcom`. This is a **denial-of-service** primitive, not SQLi.

**Reachability for the DoS**: the `^LIKE /(.+)/$` path requires an XML template containing `<filter value="LIKE /$uservar/"/>`. None of the 16 bundled reports in `resources/xml/reports/` match this pattern (grep-verified). Third-party modules could introduce it.

### Claim D: "The arithmetic-regex trigger at ReportParserGenerate.php:1071 makes the EL path brittle — future allowlist growth would be dangerous"

**Status: CONFIRMED as architectural smell, plus one NEW observation**

The regex is non-anchored: `preg_match("/(\d+)\s*([-+*\/])\s*(\d+)/", $value)`. ANY substring like `1+1` anywhere in `$value` triggers `ExpressionLanguage::evaluate($value)` on the **full** `$value`.

**Reachability analysis in bundled templates** — grep for `<SetVar .* value="\$(input_name)`:
- `bdm_report.xml:103`: `<SetVar name="filter1" value=":PLAC CONTAINS $bdmplace"/>` — user `$bdmplace` substituted into a **filter string**. If the user supplies `bdmplace=foo1+1bar`, `$value` becomes `:PLAC CONTAINS foo1+1bar`, arithmetic regex matches `1+1`, EL eval triggered on the full string. EL lexer chokes on the leading `:` (only valid as ternary/punctuation, not expression start) → **SyntaxError → caught exception, not exploit**.
- `fact_sources.xml:30,35`: `<SetVar name="namefam" value="$nameindi + $datebirth"/>`. `$nameindi` and `$datebirth` are set to `200` and `120` by prior `<SetVar>` lines in the SAME template, which **overwrite** any user-supplied values. Not reachable.
- `occupation_report.xml:30`: same pattern — `$namewidth` and `$datewidth` are set to `180`/`95` by prior lines. Not reachable.

**All other `<SetVar value="$x + $y"/>` or `$x + N` expressions in bundled templates use internal counter variables (`$num`, `$personNumber`, `$my`, `$nrevent`, `$nrmissingevent`, `$printedFamilies`, `$numberOfChildren`, `$familyChildNumber`, `$childNumber`, `$printedoccupation`, `$printedsource`) — none of which appear in any `<Input name="...">` element across all 16 bundled reports.** Grep-verified.

**Conclusion on exploitability in stock webtrees**: **NOT exploitable**. The combination of:
- Attacker cannot reach `constant()` in EL eval via the ifStartHandler path (addcslashes-quoted substitution is airtight)
- Attacker cannot reach EL eval in varSetStartHandler path with user-controlled value (bundled templates overwrite any user-supplied variable before reusing it in arithmetic)

… means there is no exploit path **today**. But the protection is entirely incidental — the sweep was correct that there is no exploit, but **for different reasons than claimed**. The actual protection is:
1. `addcslashes` escape is correct for the quoted path (sweep claim: correct).
2. Bundled templates happen not to use user-input in `<SetVar value="$x + ...">` (sweep did not verify this).
3. The `constant()` primitive IS reachable in principle, but no attacker-controllable string reaches it.

### New finding D.1: Latent footgun for third-party module authors

Any third-party webtrees module that ships an XML report template with a pattern like:
```xml
<Input name="age" type="text"/>
...
<SetVar name="doubled_age" value="$age + $age"/>
```
…would directly expose `constant()` via EL eval. The attacker payload:
```
?vars[age]=constant("DB_PASSWORD")~"1"
```
→ `$value = constant("DB_PASSWORD")~"1" + constant("DB_PASSWORD")~"1"`
→ arithmetic regex matches the `1` digit-ops (actually, no — let me re-check, the regex needs digit+op+digit. `"1"+constant("DB_PASSWORD")` has `1"+c` which doesn't match. We need to craft more carefully.)

Let me redo the payload:
```
?vars[age]=1+1
```
→ `$value = 1+1 + 1+1` → regex matches `1+1` → EL eval → `4`. Not useful.

```
?vars[age]=constant("X")~"a1+1b"
```
→ `$value = constant("X")~"a1+1b" + constant("X")~"a1+1b"` → regex matches `1+1` → EL eval → `(constant("X") . "a1+1b") + (constant("X") . "a1+1b")` → numeric coercion → `0 + 0 = 0`. Not useful for direct exfil because `+` is numeric, not string concat.

But: the RESULT `$value` is captured and stored in `$this->vars[$name]`. If the attacker can use EL's `~` concat operator somewhere else, the constant leaks. Alternatively: use `min()` or `max()` with a constant and see the constant value in the output. Or: craft the XML such that `<SetVar name="x" value="constant($uservar)"/>` (without arithmetic), but then the arithmetic regex doesn't trigger.

**Net assessment of the third-party footgun**: a naive module template COULD expose `constant()`, but crafting a string-returning exfil requires:
- A template that concatenates user input into an arithmetic expression
- User input that includes `constant("...")` + something that produces an arithmetic-regex match
- An output sink that renders the resulting `$this->vars[$name]`

This is **plausible but contorted**. Documenting it as a footgun is appropriate; creating a task to ship a hardened template-lint is overkill given the sweep cutoff.

## Summary of V1a claim verification

| Sweep claim | Verified? | Refinement |
|---|---|---|
| "Function allowlist = [stristr]" | **REFUTED** | Actual allowlist = `[constant, min, max, enum, stristr]`. Symfony EL defaults are always registered. |
| "`addcslashes($val, \"'\")` is correct escape for EL string literal" | **CONFIRMED** | Lexer grammar analysis confirms no breakout possible. |
| "Raw-path bound parameters prevent SQLi in filter paths" | **CONFIRMED with refinement** | The `^LIKE /(.+)/$` branch allows user-controlled LIKE patterns without wildcard escape — DoS vector (catastrophic scan), not SQLi. Not reached by bundled reports. |
| "No exploit path today" | **CONFIRMED** | Not reachable in stock webtrees, but the protection is incidental (template design, not explicit validation). Latent footgun for third-party module authors. |

## New follow-up observations (no tasks created)

1. **Sweep allowlist claim was wrong**: priorities.md line 50 text `"Der Function-Allowlist ist *absichtlich* auf [stristr] beschränkt"` should be corrected. The allowlist IS NOT only stristr; webtrees inherits the Symfony EL defaults (`constant`, `min`, `max`, `enum`).

2. **Doc recommendation**: if webtrees ever produces module-developer docs for report XML, add a security warning that `<SetVar value="$uservar + N"/>` patterns trigger EL eval and can leak PHP constants. This is upstream-webtrees-maintainer scope, not our task.

3. **Defense-in-depth suggestion** (still below cutoff): wrap `ExpressionLanguage::evaluate($value)` at `ReportParserGenerate.php:1077` with a try/catch that logs suspicious inputs (not a correctness fix, just observability). Informational only.

## Score impact

The sweep recorded `ReportGenerate` at `final_score = 0.112`. Nothing in V1a increases that score:
- The `constant()` reachability refutes a documentation claim but does not change exploitability.
- The LIKE-DoS refinement is below cutoff (editor-reach only for reports that use the pattern; no bundled report does).
- The allowlist correction is a documentation fix, not a new signal.

**No new task** created. **priorities.md allowlist text needs correction** in V3.
