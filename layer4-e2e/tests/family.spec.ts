// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/otel-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Familienseite (FamilyPage)
 *
 * @see docs/testing-bigpicture.md S24, AP 5c-2b
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    test.beforeEach(async ({ page }) => {
      await page.goto('/login/demo');
      await page.fill('input[name="username"]', 'admin');
      await page.fill('input[name="password"]', 'admin');
      await page.locator('button[type="submit"]').last().click();
      await page.waitForLoadState('networkidle');
    });

    test(`S24 — family page loads without errors [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/family/f1');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
    });

    test(`S24 — family page shows family header [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/family/f1');
      await page.waitForLoadState('networkidle');

      const heading = page.locator('h2, h3, .wt-page-title');
      await expect(heading.first()).toBeVisible();
    });

    test(`S24 — family page shows facts area [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/family/f1');
      await page.waitForLoadState('networkidle');

      const factsArea = page.locator('.wt-facts-table, table');
      await expect(factsArea.first()).toBeVisible();
    });
  });
}
