// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Fakten bearbeiten — Fakt hinzufügen, Edit-Seite
 *
 * @see docs/tds_conditions_ref.md E02
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    test(`E02 — Fakt-hinzufügen-Seite rendert korrekt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/add-fact/X1030/BIRT');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
      await expect(page.locator('form').last()).toBeVisible();
    });

    test(`E02 — Personenseite zeigt bestehende Fakten [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/individual/X1030');
      await expect(page.locator('body')).toBeVisible();
      // Fakten-Tabelle oder Tab sichtbar
      const factsVisible = await page.locator('.wt-facts-table, .wt-tab-facts, table').first().isVisible();
      expect(factsVisible).toBeTruthy();
    });

    test(`E02 — Edit-Links auf Personenseite vorhanden [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/individual/X1030');
      const editLinks = page.locator('a[href*="edit"], a[href*="add-fact"], .wt-icon-edit');
      const count = await editLinks.count();
      expect(count).toBeGreaterThan(0);
    });
  });
}
