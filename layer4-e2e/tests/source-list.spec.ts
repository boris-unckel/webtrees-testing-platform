// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/otel-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Quellenliste (SourceListPage)
 *
 * @see docs/testing-bigpicture.md S20, AP 5c-3c
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

    test(`S20 — source list loads without errors [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/source-list');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const content = page.locator('main, .wt-page-content');
      await expect(content.first()).toBeVisible();
    });

    test(`S20 — source list shows entries or table [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/source-list');
      await page.waitForLoadState('networkidle');

      // Listeneinträge oder Tabelle
      const list = page.locator('table, .list-group, .wt-page-content a');
      await expect(list.first()).toBeVisible();
    });
  });
}
