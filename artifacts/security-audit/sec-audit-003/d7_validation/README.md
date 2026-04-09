<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-003 — D7 Validation Artifacts

Defense-in-depth CSP hardening for `ImageFactory::replacementImageResponse()`.

## Scope

- Severity: LOW (defense-in-depth / API symmetry with `imageResponse()`)
- Not exploitable today (placeholder body is static text, no user input)
- Goal: symmetric CSP so a future change introducing user-controlled
  placeholder text cannot bypass the CSP barrier that the regular
  `imageResponse()` path already enforces.

## Fix

- 5 lines added / 1 replaced inside `app/Factories/ImageFactory.php` —
  only `replacementImageResponse()` is touched.
- No call-site changes: all 13 callers (ImageFactory internal,
  `MediaFileThumbnail`, `MediaFileDownload`) inherit the header for free.

## Authoritative commits (Fork branch `security-audit-003-replacement-image-csp`)

- Test-first: `32e541249e` (asserts header present, initially failing)
- Fix:       `26cbc493a4`
- Branch base: Fork-`main` @ `c338276a5a`
- Both GPG-signed (EDDSA `C3800666AD9815724DDAF7495E6039E5B765BCA4`)

## Volatile (non-authoritative) commits

- Test: `399c1747f2`
- Fix:  `1b4a0bd56b`

## Validation matrix

| Layer | Command | Expected | Evidence |
|---|---|---|---|
| Layer 1 | `php -l app/Factories/ImageFactory.php` | `No syntax errors detected` | `php_lint.txt` |
| Layer 2 | `phpunit tests/app/Factories/ImageFactoryTest.php` | `OK (2 tests, 5 assertions)` | `layer2_image_factory_green.txt` |

### Run commands

```bash
podman-compose exec -T webtrees php -l /var/www/html/app/Factories/ImageFactory.php
podman-compose exec -T webtrees vendor/bin/phpunit tests/app/Factories/ImageFactoryTest.php
```

## Code-read review

- `imageResponse()` (line 276) uses `withHeader('content-security-policy', 'script-src none;frame-src none')` imperatively.
- `replacementImageResponse()` (now lines 257–270) sets the same value declaratively in the `response()` `headers:` array — different construction style, identical effective header.
- All 13 `replacementImageResponse()` call sites (10 in `ImageFactory`, 2 in `MediaFileThumbnail`, 1 in `MediaFileDownload`) inherit the header without modification.
- No behaviour change for existing placeholder SVG bodies, which are produced from the `errors/image-svg.phtml` view with static status text only.
