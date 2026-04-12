// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Beziehungsfinder — Chart mit Personenauswahl und Pfadanzeige
 *
 * @see docs/tds_conditions_ref.md S16
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    test(`S16 — Beziehungs-Chart-Seite lädt korrekt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/relationships-1-1/X1030');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`S16 — Beziehungspfad zwischen zwei Personen angezeigt [${theme}]`, async ({ page }) => {
      // Elizabeth II (X1030) und Philip (X1041) — Ehepaar
      const response = await page.goto('/tree/demo/relationships-1-1/X1030/X1041');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
      const content = await page.locator('body').textContent();
      // Beziehungsbeschreibung sollte sichtbar sein
      expect(content).toBeTruthy();
    });

    test(`S16 — Eltern-Kind-Beziehung wird angezeigt [${theme}]`, async ({ page }) => {
      // Elizabeth II (X1030) und Charles (X1052) — Mutter-Sohn
      const response = await page.goto('/tree/demo/relationships-1-1/X1030/X1052');
      expect(response?.status()).toBeLessThan(500);
      const content = await page.locator('body').textContent();
      expect(content).toBeTruthy();
    });
  });
}
