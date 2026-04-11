// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';
import { ADMIN_PASSWORD } from '../helpers/auth';

/**
 * Systemtest: Suchformulare (erweiterte + phonetische Suche)
 *
 * @see docs/testing-bigpicture.md S38, S39, AP 5c-2e
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

    test(`S38 — advanced search form renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/search-advanced');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const form = page.locator('form');
      await expect(form.first()).toBeVisible();
    });

    test(`S39 — phonetic search form renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/search-phonetic');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const form = page.locator('form');
      await expect(form.first()).toBeVisible();
    });
  });
}
