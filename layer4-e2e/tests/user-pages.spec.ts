import { test, expect } from '@playwright/test';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Benutzerseiten (Meine Seite, Kontakt, Berichte)
 *
 * @see docs/testing-bigpicture-prompt.md S35, S36, S37, AP 5c-2f
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

    test(`S35 — my page renders with blocks [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/my-page');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const content = page.locator('main, .wt-page-content');
      await expect(content.first()).toBeVisible();
    });

    test(`S36 — contact page renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/contact');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
    });

    test(`S37 — report list renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/report-list');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const content = page.locator('main, .wt-page-content');
      await expect(content.first()).toBeVisible();
    });
  });
}
