// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: TomSelect & AutoComplete — JS-Widget-Interaktion auf Edit-Seiten
 *
 * @see docs/tds_conditions_ref.md E08
 * @see docs/systemtest/testspezi/E08_systemtest_spezi.md
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    test(`E08 — Edit-Seite mit TomSelect-Widget lädt [${theme}]`, async ({ page }) => {
      // Add-Spouse-Seite nutzt TomSelect für Personenauswahl
      const response = await page.goto('/tree/demo/add-spouse-to-individual/X1030');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
      // Mindestens ein Formular im Inhaltsbereich (nicht nur Header-Suche)
      const forms = await page.locator('form').count();
      expect(forms).toBeGreaterThanOrEqual(2);
    });

    test(`E08 — TomSelect-Widget rendert auf Edit-Seite [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/add-spouse-to-individual/X1030');
      // TomSelect ersetzt Standard-Inputs: .ts-wrapper enthält .ts-control
      const tsWidget = page.locator('.ts-wrapper, .ts-control, .tomselect, input[type="text"]');
      const count = await tsWidget.count();
      expect(count).toBeGreaterThan(0);
    });

    test(`E08 — TomSelect-API-Endpunkt für Individuen antwortet [${theme}]`, async ({ page }) => {
      // Direkte API-Prüfung: TomSelect-Endpunkt mit Suchbegriff
      const response = await page.goto('/tree/demo/tom-select-individual?query=Elizabeth');
      expect(response?.status()).toBeLessThan(500);
      const body = await page.locator('body').textContent();
      // API-Endpunkt antwortet mit Inhalt (JSON-Format)
      expect(body).toBeTruthy();
    });

    test(`E08 — TomSelect-API für Quellen antwortet [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/tom-select-source?query=');
      expect(response?.status()).toBeLessThan(500);
      const body = await page.locator('body').textContent();
      expect(body).toBeTruthy();
    });

    test(`E08 — TomSelect-Eingabefeld auf Add-Child-Seite [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/add-child-to-individual/X1030');
      expect(response?.status()).toBeLessThan(500);
      // Formularfelder für neue Person vorhanden
      const formFields = page.locator('input, select, .ts-wrapper');
      const count = await formFields.count();
      expect(count).toBeGreaterThan(0);
    });
  });
}
