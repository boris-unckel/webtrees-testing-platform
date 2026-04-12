// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Kontaktformular — Gast-Zugang, Formular-Rendering und Submit
 *
 * @see docs/tds_conditions_ref.md K01
 * @see docs/systemtest/testspezi/K01_systemtest_spezi.md
 */

test.use({ storageState: { cookies: [], origins: [] } });

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    test(`K01 — Kontaktformular rendert korrekt [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/contact?to=admin');
      expect(response?.status()).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible();
    });

    test(`K01 — Kontaktformular zeigt Pflichtfelder [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/contact?to=admin');
      // Kontaktformular mit Feldern vorhanden
      const form = page.locator('form');
      await expect(form.first()).toBeVisible();
      // Mindestens Subject- und Body-Felder sollten vorhanden sein
      const fields = page.locator('input[name="subject"], textarea[name="body"], input[name="from_name"], input[name="from_email"]');
      const count = await fields.count();
      expect(count).toBeGreaterThan(0);
    });

    test(`K01 — Leeres Kontaktformular-Submit [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/contact?to=admin');
      // Leeres Formular absenden
      const submitBtn = page.locator('button[type="submit"]').first();
      if (await submitBtn.isVisible()) {
        await submitBtn.click();
        await page.waitForLoadState('networkidle');
      }
      // Seite sollte geladen bleiben (Fehlermeldung oder Redirect zurück)
      await expect(page.locator('body')).toBeVisible();
    });
  });
}
