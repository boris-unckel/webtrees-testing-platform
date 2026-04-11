// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';
import { ADMIN_PASSWORD } from '../helpers/auth';

/**
 * Systemtest: Homepage / Baumseite (TreePage)
 *
 * @see docs/testing-bigpicture.md S40, AP 5c-3a
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    test.beforeEach(async ({ page }) => {
      await page.goto('/login/demo');
      await page.fill('input[name="username"]', 'admin');
      await page.fill('input[name="password"]', ADMIN_PASSWORD);
      await page.locator('button[type="submit"]').last().click();
      await page.waitForLoadState('networkidle');
    });

    test(`S40 — homepage loads without errors [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const content = page.locator('main, .wt-page-content');
      await expect(content.first()).toBeVisible();
    });

    test(`S40 — homepage shows tree statistics or welcome block [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo');
      await page.waitForLoadState('networkidle');

      // Baumstatistik oder Willkommensblock
      const stats = page.locator('.wt-block, .wt-stats-table, .block, .card');
      await expect(stats.first()).toBeVisible();
    });
  });
}
