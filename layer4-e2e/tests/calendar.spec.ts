import { test, expect } from '@playwright/test';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Kalenderansicht (Monat + Jahr)
 *
 * @see docs/testing-bigpicture.md S31, AP 5c-2d
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    test.beforeEach(async ({ page }) => {
      await page.goto('/login/demo');
      await page.fill('input[name="username"]', 'admin');
      await page.fill('input[name="password"]', 'admin');
      await page.locator('button[type="submit"]').last().click();
      await page.waitForLoadState('networkidle');
    });

    test(`S31 — month calendar renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/calendar/month');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const content = page.locator('main, .wt-page-content');
      await expect(content.first()).toBeVisible();
    });

    test(`S31 — year calendar renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/calendar/year');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const content = page.locator('main, .wt-page-content');
      await expect(content.first()).toBeVisible();
    });
  });
}
