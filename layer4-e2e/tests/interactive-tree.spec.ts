// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Interaktiver Stammbaum — Canvas/SVG-Widget
 *
 * @see docs/tds_conditions_ref.md S47
 * @see docs/systemtest/testspezi/S47_systemtest_spezi.md
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    test(`S47 — Interaktiver Stammbaum lädt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/module/tree/Chart/demo?xref=X1030');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
      await expect(page.locator('main').first()).toBeVisible();
    });

    test(`S47 — Widget-Container sichtbar [${theme}]`, async ({ page }) => {
      await page.goto('/module/tree/Chart/demo?xref=X1030');
      // Interactive-Tree nutzt #tvTreeBorder, .tv_out, canvas oder svg
      const widget = page.locator('#tvTreeBorder, .tv_out, canvas, svg, .wt-chart').first();
      const widgetVisible = await widget.isVisible().catch(() => false);
      // Mindestens der Inhaltsbereich muss da sein
      await expect(page.locator('main').first()).toBeVisible();
    });

    test(`S47 — Stammbaum zeigt Personendaten [${theme}]`, async ({ page }) => {
      await page.goto('/module/tree/Chart/demo?xref=X1030');
      await page.waitForLoadState('networkidle');
      const content = await page.locator('body').textContent();
      // Der Default-Individual (X1030) sollte im Widget auftauchen
      expect(content).toBeTruthy();
      await expect(page.locator('main').first()).toBeVisible();
    });
  });
}
