// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Search-and-Replace (Bulk-Editor)
 *
 * Tree-gebunden → Theme-Loop (5 Themes).
 *
 * @see docs/tds_conditions_ref.md S13, AP 9-4
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    test(`S13 — search-and-replace page renders for admin [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/search-replace');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const form = page.locator('form');
      await expect(form.first()).toBeVisible();
    });

    test(`S13 — search-and-replace page shows form fields [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-replace');

      // Search field
      const searchInput = page.locator('input[name="search"], textarea[name="search"]');
      await expect(searchInput.first()).toBeVisible();

      // Replace field
      const replaceInput = page.locator('input[name="replace"], textarea[name="replace"]');
      await expect(replaceInput.first()).toBeVisible();
    });
  });
}

// Visitor-Test: eigener Describe-Block mit leerem storageState
test.describe('Visitor', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('S13 — search-and-replace page not accessible for visitor', async ({ page }) => {
    await page.goto('/tree/demo/search-replace');
    await page.waitForLoadState('networkidle');
    const url = page.url();

    // webtrees leitet nicht-authentifizierte Benutzer weg von der geschützten Route
    // (typischerweise auf die Homepage /tree/demo, nicht auf /login)
    expect(url).not.toContain('/search-replace');
  });
});
