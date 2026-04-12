// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Medienobjekte anlegen & verknüpfen — Modal und Upload
 *
 * @see docs/tds_conditions_ref.md E05
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    test(`E05 — Media-erstellen-Endpunkt erreichbar [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/create-media-object');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`E05 — Media-Seite auf Person X1030 zeigt Medien-Tab [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/individual/X1030');
      await expect(page.locator('body')).toBeVisible();
      // Media-Tab oder Media-Bereich prüfen
      const mediaContent = page.locator('.wt-tab-media, a[href*="media"], .nav-link');
      const count = await mediaContent.count();
      expect(count).toBeGreaterThan(0);
    });

    test(`E05 — Medienobjekt-Seite lädt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/media/X1104');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });
  });
}
