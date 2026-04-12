// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Person/Familie anlegen & verknüpfen
 *
 * @see docs/tds_conditions_ref.md E01
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    test(`E01 — Formular Kind hinzufügen lädt korrekt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/add-child-to-individual/X1030');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`E01 — Formular Elternteil hinzufügen lädt korrekt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/add-parent-to-individual/X1030/M');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`E01 — Formular Ehepartner hinzufügen lädt korrekt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/add-spouse-to-individual/X1030');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`E01 — Formular Kind zu Familie hinzufügen lädt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/add-child-to-family/f1/M');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });
  });
}
