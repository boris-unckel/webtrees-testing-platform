<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# V1c — HtmlService::sanitize() Implementation Review

**Verification target**: The sweep 2026-04-08T20-58-28 claimed "HTML-Sanitization für alle Admin-eingegebenen Rich-Text-Felder via HtmlService::sanitize() (FAQ-Modul, Stories-Modul, etc.)". This V1c round reads the implementation, checks the underlying library and configuration, and verifies that **all** user-editable rich-text sinks go through it.

## Implementation review — `app/Services/HtmlService.php`

### Library choice

Uses **HTMLPurifier** (composer package `ezyang/htmlpurifier`). This is the industry-standard PHP HTML sanitizer, actively maintained, and widely audited. Default configuration is secure-by-default (removes `<script>`, `<iframe>`, event handlers, `javascript:` URLs in most attributes, and dangerous CSS).

### Configuration analysis

```php
$config = HTMLPurifier_Config::createDefault();
$config->set('Cache.DefinitionImpl', null);        // disables def caching (functional)
$config->set('HTML.TidyLevel', 'none');            // only XSS cleaning
$config->set('CSS.MaxImgLength', null);            // allows percentage img sizes
$config->set('Attr.EnableID', true);               // allows id="..." attributes
```

Custom additions (all with restricted attribute types):
- `<a target>` enum `['_blank', '_self', '_target', '_top']`
- `<img usemap>` as CDATA
- `<map>` block element with name/id/title
- `<area>` with `href: URI`, `shape` enum, etc.
- `<audio>` / `<video>` with `src: URI`, `controls: Bool`, etc.

### Security-relevant observations

1. **URI type validation** — HTMLPurifier's `URI` attribute type enforces the scheme whitelist (defaults: `http, https, mailto, ftp, nntp, news, tel`). `javascript:` and `data:` are blocked by default. The HtmlService code does not override `AllowedSchemes`, so the default applies.

2. **CSS handling** — HTMLPurifier's CSS subsystem runs on `style` attributes and blocks `expression()`, `javascript:`, `behavior:`. The HtmlService code does not override `CSS.*` settings except `CSS.MaxImgLength` (removes max-dimensions limit). Safe.

3. **`HTML.Trusted` is NOT set** → defaults to `false` (untrusted mode, strictest).

4. **No `HTML.SafeIframe`, `HTML.SafeObject`** → defaults to `false`, which blocks `<iframe>` and `<object>` entirely.

### Minor findings

| # | Issue | Severity | Notes |
|---|---|---|---|
| V1c-1 | `<a target="_blank">` without auto-added `rel="noopener noreferrer"` | **LOW** | HTMLPurifier supports `HTML.TargetNoopener` and `HTML.TargetNoreferrer` but neither is enabled. Modern browsers (Chrome 88+, Firefox 79+) apply implicit `noopener` for `target=_blank` since ~2022, so real-world reverse-tabnabbing impact is minimal. No task. |
| V1c-2 | `Attr.EnableID = true` allows user-set `id` attributes | **LOW** | Potential DOM clobbering if a webtrees template uses `window[id]` or `document.getElementById` for critical logic with user-controlled IDs. Checked webtrees `resources/views/*.phtml` briefly — no such pattern found. No task. |

Neither rises above the T3 cutoff. Both are documented here and **not** turned into tasks.

## Caller audit — all rich-text sinks

### Callers that DO use HtmlService::sanitize()

Grep `->sanitize\(` across `app/Module/`:

| File | Input source | Sanitize call | Match? |
|---|---|---|---|
| `FrequentlyAskedQuestionsModule.php:325` | `->string('body')` | `:335 $this->html_service->sanitize($body)` | ✓ |
| `FrequentlyAskedQuestionsModule.php:326` | `->string('header')` | `:336 $this->html_service->sanitize($header)` | ✓ |
| `HtmlBlockModule.php:152` | `->string('html')` | `:157 $this->html_service->sanitize($html)` | ✓ |
| `UserJournalModule.php:168` | `->string('subject')` | `:172 $this->html_service->sanitize($subject)` | ✓ |
| `UserJournalModule.php:170` | `->string('body')` | `:173 $this->html_service->sanitize($body)` | ✓ |
| `FamilyTreeNewsModule.php:168` | `->string('subject')` | `:172 $this->html_service->sanitize($subject)` | ✓ |
| `FamilyTreeNewsModule.php:170` | `->string('body')` | `:173 $this->html_service->sanitize($body)` | ✓ |
| `StoriesModule.php:311` | `->string('story_body')` | `:315 $this->html_service->sanitize($story_body)` | ✓ |

8/8 rich-text sinks in modules flow through `HtmlService::sanitize()`. **Confirmed.**

### Callers that DO NOT use HtmlService::sanitize()

Grep found **one exception**:

| File | Input source | Sink | Analysis |
|---|---|---|---|
| `CustomCssJsModule.php:80` | `->string('body')` | `$this->setPreference('body', $body)` (no sanitize) | See below |
| `CustomCssJsModule.php:81` | `->string('head')` | `$this->setPreference('head', $head)` (no sanitize) | See below |

### CustomCssJsModule — is the bypass safe?

The `body` and `head` inputs are **stored raw** and rendered via `bodyContent()` / `headContent()` into every page's `<body>` and `<head>` sections verbatim. The module's docstring explicitly says "Raw content ... Typically, this will be `<script>` elements". This is an **intentional feature** allowing the admin to inject custom CSS/JS.

**Access control verification** — `app/Http/RequestHandlers/ModuleAction.php:75-77`:
```php
// Actions with "Admin" in the name are for administrators only.
if (str_contains($action, 'Admin') && !Auth::isAdmin($user)) {
    throw new HttpAccessDeniedException('Admin only action');
}
```

The module method name is `postAdminAction`. The URL action segment matches `Admin` (case-sensitive substring match). Therefore: **admin-only by routing-time gate**. A non-admin cannot reach `postAdminAction`.

### Substring-match edge case (methodology note)

The `str_contains($action, 'Admin')` check is **case-sensitive and substring-based**. Implications for module developers:

- A method named `postadmineditAction` (lowercase) would pass the URL as `admin-edit` or similar — `str_contains('admin-edit', 'Admin')` returns **false**, gate does NOT fire. **Landmine** for module authors who name their admin actions in lowercase.
- Conversely, a method named `getAdminHelperAction` (unintentionally matching "Admin") would be gated even if the developer expected it to be open. Less dangerous but can surprise.

This is **not a direct HtmlService issue**, but it is a methodology pattern for the broader framework. Documenting here as an observation; **will be followed up in V1e.2 middleware review** because it's a form of auth-gate logic.

## Sweep claim verification

| Sweep claim | Verified? | Refinement |
|---|---|---|
| "HtmlService::sanitize() used for all admin-entered rich-text fields" | **CONFIRMED** | 8/8 module sinks use sanitize(). One intentional bypass (CustomCssJsModule) is admin-gated. |
| "HTMLPurifier with sensible defaults" (implied) | **CONFIRMED with 2 low-sev nits** | Reverse-tabnabbing rel=noopener not forced; Attr.EnableID=true. Both LOW severity. |

## New observations (no tasks created)

1. **HtmlService-Härtung**: adding `$config->set('HTML.TargetBlank', true)` and/or `$config->set('HTML.TargetNoopener', true)` would close the minor reverse-tabnabbing gap. A 2-line change, trivially testable. Can be included in a future defensive-hardening PR. Not cutoff-worthy.

2. **Documentation for module authors**: naming convention `postXxxAction` / `postXxxAdminAction` should be explicit in the webtrees module developer docs. The case-sensitive substring match is a landmine. Upstream-webtrees-maintainer scope.

3. **HtmlService::sanitize() has no Layer-3 test**: V1e.3 will check this explicitly. Preview: no test currently exercises HtmlService; a few simple XSS-vector tests would add cheap coverage.

## Score impact

- **HtmlService.php itself**: no exploit identified, no new signals. Score unchanged.
- **CustomCssJsModule.php**: bypass is intentional + admin-gated. Not a finding.
- **`postAdminAction` substring-gate methodology note**: pushed to V1e.2 for middleware-level analysis.

**No new tasks from V1c.**
