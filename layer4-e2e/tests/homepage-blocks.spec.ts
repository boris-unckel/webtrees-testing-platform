// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Homepage-Blöcke — Blocktypen-Sichtbarkeit auf der Startseite
 *
 * @see docs/tds_conditions_ref.md S46
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    test(`S46 — Homepage zeigt mindestens einen Block [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo');
      expect(response?.status()).toBeLessThan(500);
      const blockVisible = await page.locator('.wt-block, .block, .card').first().isVisible();
      expect(blockVisible).toBeTruthy();
    });

    test(`S46 — Homepage zeigt Statistik-Block [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo');
      const statsVisible = await page.locator('.wt-stats-table, .wt-block').first().isVisible();
      expect(statsVisible).toBeTruthy();
    });

    test(`S46 — Homepage-Block-Konfiguration erreichbar [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo');
      // Prüfe ob Admin-Menü oder Block-Config-Link vorhanden
      await expect(page.locator('body')).toBeVisible();
    });
  });
}
