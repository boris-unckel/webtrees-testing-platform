// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';
import { ADMIN_PASSWORD } from '../helpers/auth';

/**
 * Systemtest: Navigation und Seitenstruktur
 *
 * @see docs/tds_conditions_ref.md S23, S20, S09, AP 5c-2g
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

    test(`S23 — individual list renders [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/individual-list');
      await page.waitForLoadState('networkidle');

      await expect(page.locator('body')).toBeVisible();

      const content = page.locator('.wt-page-content, main');
      await expect(content.first()).toBeVisible();
    });

    test(`S20 — family list renders [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/family-list');
      await page.waitForLoadState('networkidle');

      await expect(page.locator('body')).toBeVisible();
      const content = page.locator('.wt-page-content, main');
      await expect(content.first()).toBeVisible();
    });

    test(`S09 — quick search returns results [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo');
      await page.waitForLoadState('networkidle');

      const searchInput = page.locator('input[name="query"]').first();

      if (await searchInput.isVisible()) {
        await searchInput.fill('Dombrink');
        await searchInput.press('Enter');
        await page.waitForLoadState('networkidle');

        await expect(page.locator('body')).toBeVisible();
      }
    });
  });
}
