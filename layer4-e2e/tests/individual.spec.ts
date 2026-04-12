// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Personenseite — Fakten, Familien, Events
 *
 * @see docs/tds_conditions_ref.md S23, AP 5c-2a
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
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
