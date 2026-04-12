// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Phonetische Suche — Russell Soundex (S07) und Daitch-Mokotoff (S08)
 *
 * @see docs/tds_conditions_ref.md S07, S08
 * @see docs/systemtest/testspezi/S07_systemtest_spezi.md
 * @see docs/systemtest/testspezi/S08_systemtest_spezi.md
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    // --- S07: Russell Soundex ---

    test(`S07 — Phonetische Suchseite rendert Formular [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/search-phonetic');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
      // Phonetisches Suchformular vorhanden (nicht Header-Suchformular)
      await expect(page.locator('.wt-page-options')).toBeVisible();
    });

    test(`S07 — Russell-Soundex-Suche nach phonetischer Variante liefert Treffer [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-phonetic');
      // "Elisabeth" ist phonetisch äquivalent zu "Elizabeth" (Russell: E421)
      await page.locator('input#firstname').fill('Elisabeth');
      // Russell-Radiobutton prüfen
      await page.locator('#russell').check();
      // Submit-Button innerhalb des Suchformulars klicken (nicht Header-Suche)
      await page.locator('.wt-page-options button[type="submit"], .wt-page-options input[type="submit"]').first().click();
      await page.waitForLoadState('networkidle');
      const content = await page.locator('body').textContent();
      // Phonetische Variante "Elisabeth" sollte "Elizabeth" aus Demo-GEDCOM finden
      expect(content).toContain('Elizabeth');
    });

    test(`S07 — Russell-Soundex-Suche ohne Treffer [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-phonetic');
      // Name ohne phonetische Entsprechung im Demo-GEDCOM
      await page.locator('input#firstname').fill('Zzyzx');
      await page.locator('#russell').check();
      await page.locator('.wt-page-options button[type="submit"], .wt-page-options input[type="submit"]').first().click();
      await page.waitForLoadState('networkidle');
      // Keine Ergebnisse erwartet — Seite ohne Treffer-Tabelle
      const content = await page.locator('body').textContent();
      expect(content).not.toContain('Queen Elizabeth');
    });

    // --- S08: Daitch-Mokotoff Soundex ---

    test(`S08 — DM-Soundex-Suche nach phonetischer Variante liefert Treffer [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-phonetic');
      // "Elisabeth" über DM-Soundex suchen
      await page.locator('input#firstname').fill('Elisabeth');
      await page.locator('#d-m').check();
      await page.locator('.wt-page-options button[type="submit"], .wt-page-options input[type="submit"]').first().click();
      await page.waitForLoadState('networkidle');
      const content = await page.locator('body').textContent();
      // DM-Soundex: "Elisabeth" → "Elizabeth" phonetisch äquivalent
      expect(content).toContain('Elizabeth');
    });

    test(`S08 — DM-Soundex-Suche ohne Treffer [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-phonetic');
      await page.locator('input#firstname').fill('Zzyzx');
      await page.locator('#d-m').check();
      await page.locator('.wt-page-options button[type="submit"], .wt-page-options input[type="submit"]').first().click();
      await page.waitForLoadState('networkidle');
      const content = await page.locator('body').textContent();
      expect(content).not.toContain('Queen Elizabeth');
    });
  });
}
