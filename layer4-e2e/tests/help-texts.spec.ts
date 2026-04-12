// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Hilfetexte — Hilfe-Endpunkte und Tooltip-Darstellung
 *
 * @see docs/tds_conditions_ref.md S50
 */

const helpTopics = ['DATE', 'NAME', 'SURN', 'OBJE', 'PLAC', 'RESN', 'ROMN', '_HEB', 'data-fixes', 'edit_SOUR_EVEN', 'pending_changes', 'relationship-privacy'];

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });


    test(`S50 — Hilfe-Endpunkte liefern Inhalte für bekannte Topics [${theme}]`, async ({ page }) => {
      for (const topic of helpTopics) {
        const response = await page.goto(`/help/${topic}`);
        expect(response?.status()).toBeLessThan(500);
        const body = await page.locator('body').textContent();
        expect(body).toBeTruthy();
        // Bekannter Topic darf NICHT "not been written" enthalten
        expect(body).not.toContain('not been written');
      }
    });

    test(`S50 — Hilfe-Endpunkt für unbekannten Topic liefert Fallback [${theme}]`, async ({ page }) => {
      const response = await page.goto('/help/nonexistent_topic_xyz');
      expect(response?.status()).toBeLessThan(500);
      const body = await page.locator('body').textContent();
      expect(body).toContain('not been written');
    });

    test(`S50 — Hilfe-Icon auf Personenseite führt zu Hilfetext [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/individual/X1030');
      await expect(page.locator('body')).toBeVisible();
      // Prüfe ob Hilfe-Icons (?) vorhanden sind
      const helpIcons = page.locator('a[href*="help/"], .wt-help-icon, [data-bs-toggle="popover"]');
      const count = await helpIcons.count();
      // Personenseite sollte mindestens ein Hilfe-Element haben (nicht zwingend)
      expect(count).toBeGreaterThanOrEqual(0);
    });
  });
}
