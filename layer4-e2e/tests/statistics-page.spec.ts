// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Statistikdaten-Abfragen
 *
 * @see docs/tds_conditions_ref.md S41
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    test(`S41 — Statistik-Seite lädt ohne Fehler [${theme}]`, async ({ page }) => {
      const response = await page.goto('/module/statistics_chart/Chart/demo');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`S41 — Statistik-Seite zeigt Inhaltsbereich [${theme}]`, async ({ page }) => {
      await page.goto('/module/statistics_chart/Chart/demo');
      await expect(page.locator('main').first()).toBeVisible();
    });

    test(`S41 — Statistik-Seite zeigt Diagramm-/Tabellenbereich [${theme}]`, async ({ page }) => {
      await page.goto('/module/statistics_chart/Chart/demo');
      const hasChart = await page.locator('.wt-stats-table, table, canvas, svg, .wt-chart').first().isVisible();
      expect(hasChart).toBeTruthy();
    });
  });
}
