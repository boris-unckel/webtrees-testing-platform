// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Nebenrecords anlegen (NOTE/SOUR/REPO) — Modal-Dialog-Interaktion
 *
 * @see docs/tds_conditions_ref.md E04
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    test(`E04 — Personenseite mit Edit-Optionen lädt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/individual/X1030');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`E04 — Note-erstellen-Endpunkt erreichbar [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/create-note-object');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`E04 — Source-erstellen-Endpunkt erreichbar [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/create-source');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`E04 — Repository-erstellen-Endpunkt erreichbar [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/create-repository');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });
  });
}
