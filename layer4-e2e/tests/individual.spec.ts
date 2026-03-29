// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/otel-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Personenseite — Fakten, Familien, Events
 *
 * @see docs/testing-bigpicture.md S23, AP 5c-2a
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

    test(`S23 — person page shows name and vital facts [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/individual/X1030');
      await page.waitForLoadState('networkidle');

      await expect(page.locator('body')).toBeVisible();

      const heading = page.locator('h2, h3, .wt-page-title');
      await expect(heading.first()).toBeVisible();
    });

    test(`S23 — person page shows facts area [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/individual/X1030');
      await page.waitForLoadState('networkidle');

      const factsArea = page.locator('.wt-facts-table, .wt-tab-facts, .nav-tabs, table');
      await expect(factsArea.first()).toBeVisible();
    });
  });
}
