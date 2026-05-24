# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: contact-form.spec.ts >> Theme: fab >> K01 — Kontaktformular zeigt Pflichtfelder [fab]
- Location: tests/contact-form.spec.ts:27:9

# Error details

```
Error: expect(received).toBeGreaterThan(expected)

Expected: > 0
Received:   0
```

# Test source

```ts
  1  | // SPDX-License-Identifier: AGPL-3.0-or-later
  2  | 
  3  | import { test, expect } from '../helpers/perfschema-fixture';
  4  | import { themes, switchTheme } from '../helpers/theme-switch';
  5  | 
  6  | /**
  7  |  * Systemtest: Kontaktformular — Gast-Zugang, Formular-Rendering und Submit
  8  |  *
  9  |  * @see docs/tds_conditions_ref.md K01
  10 |  * @see docs/systemtest/testspezi/K01_systemtest_spezi.md
  11 |  */
  12 | 
  13 | test.use({ storageState: { cookies: [], origins: [] } });
  14 | 
  15 | for (const theme of themes) {
  16 |   test.describe(`Theme: ${theme}`, () => {
  17 |     test.beforeAll(async ({ browser }) => {
  18 |       await switchTheme(browser, theme);
  19 |     });
  20 | 
  21 |     test(`K01 — Kontaktformular rendert korrekt [${theme}]`, async ({ page }) => {
  22 |       const response = await page.goto('/tree/demo/contact?to=admin');
  23 |       expect(response?.status()).toBeLessThan(500);
  24 |       await expect(page.locator('body')).toBeVisible();
  25 |     });
  26 | 
  27 |     test(`K01 — Kontaktformular zeigt Pflichtfelder [${theme}]`, async ({ page }) => {
  28 |       await page.goto('/tree/demo/contact?to=admin');
  29 |       // Kontaktformular mit Feldern vorhanden
  30 |       const form = page.locator('form');
  31 |       await expect(form.first()).toBeVisible();
  32 |       // Mindestens Subject- und Body-Felder sollten vorhanden sein
  33 |       const fields = page.locator('input[name="subject"], textarea[name="body"], input[name="from_name"], input[name="from_email"]');
  34 |       const count = await fields.count();
> 35 |       expect(count).toBeGreaterThan(0);
     |                     ^ Error: expect(received).toBeGreaterThan(expected)
  36 |     });
  37 | 
  38 |     test(`K01 — Leeres Kontaktformular-Submit [${theme}]`, async ({ page }) => {
  39 |       await page.goto('/tree/demo/contact?to=admin');
  40 |       // Leeres Formular absenden
  41 |       const submitBtn = page.locator('button[type="submit"]').first();
  42 |       if (await submitBtn.isVisible()) {
  43 |         await submitBtn.click();
  44 |         await page.waitForLoadState('networkidle');
  45 |       }
  46 |       // Seite sollte geladen bleiben (Fehlermeldung oder Redirect zurück)
  47 |       await expect(page.locator('body')).toBeVisible();
  48 |     });
  49 |   });
  50 | }
  51 | 
```