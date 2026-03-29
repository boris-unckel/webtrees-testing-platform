// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/otel-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';
import { ADMIN_PASSWORD } from '../helpers/auth';

/**
 * Systemtest: Stammbaum / Ahnentafel (PedigreePage)
 *
 * @see docs/testing-bigpicture.md S14, AP 5c-3b
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

    test(`S14 — pedigree chart loads without errors [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/pedigree');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
    });

    test(`S14 — pedigree chart area visible [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/pedigree');
      await page.waitForLoadState('networkidle');

      // Chart-Bereich (Canvas, SVG oder Chart-Container)
      const chart = page.locator('.wt-chart-pedigree, .wt-chart, canvas, svg, .wt-page-content');
      await expect(chart.first()).toBeVisible();
    });
  });
}
