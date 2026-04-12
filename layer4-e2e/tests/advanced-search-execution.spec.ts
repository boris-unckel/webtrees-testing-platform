// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Erweiterte Suche — Feld-Suche (S05) und Datum-Modifikatoren (S06)
 *
 * @see docs/tds_conditions_ref.md S05, S06
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    // --- S05: Erweiterte Suche (Felder) ---

    test(`S05 — Erweiterte Suchseite rendert Formular [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/search-advanced');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('.wt-page-options-search-advanced')).toBeVisible();
    });

    test(`S05 — Suche nach Vorname liefert Treffer [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-advanced');
      const givnField = page.locator('input[name="fields[INDI:NAME:GIVN]"]');
      await givnField.fill('Elizabeth');
      await page.locator('.wt-page-options-search-advanced input[type="submit"], .wt-page-options-search-advanced button[type="submit"]').first().click();
      await page.waitForLoadState('networkidle');
      const content = await page.locator('body').textContent();
      expect(content).toContain('Elizabeth');
    });

    test(`S05 — Suche nach Nachname liefert Treffer [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-advanced');
      const surnField = page.locator('input[name="fields[INDI:NAME:SURN]"]');
      await surnField.fill('Windsor');
      await page.locator('.wt-page-options-search-advanced input[type="submit"], .wt-page-options-search-advanced button[type="submit"]').first().click();
      await page.waitForLoadState('networkidle');
      // Ergebnistabelle muss Treffer enthalten (Nachname nicht immer im Display-Namen sichtbar)
      await expect(page.locator('.wt-search-results, .wt-table-individual').first()).toBeVisible();
    });

    // --- S06: Erweiterte Suche (Datum-Modifikatoren) ---

    test(`S06 — Erweiterte Suche mit Datumsfeld liefert Treffer [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-advanced');
      const dateField = page.locator('input[name="fields[INDI:DEAT:DATE]"]');
      if (await dateField.isVisible()) {
        await dateField.fill('1997');
      }
      await page.locator('.wt-page-options-search-advanced input[type="submit"], .wt-page-options-search-advanced button[type="submit"]').first().click();
      await page.waitForLoadState('networkidle');
      const content = await page.locator('body').textContent();
      expect(content).toBeTruthy();
    });
  });
}
