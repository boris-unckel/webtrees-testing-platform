import { test, expect } from '@playwright/test';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Record-Seiten (Source, Media, Repository, Submitter)
 *
 * S28 (Notizseite) übersprungen — demo.ged enthält keine separaten NOTE-Records.
 *
 * @see docs/testing-bigpicture-prompt.md S26–S30, AP 5c-2c
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

    test(`S26 — source page renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/source/X1102');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const heading = page.locator('h2, h3, .wt-page-title');
      await expect(heading.first()).toBeVisible();
    });

    test(`S27 — media page renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/media/X1104');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const heading = page.locator('h2, h3, .wt-page-title');
      await expect(heading.first()).toBeVisible();
    });

    test(`S29 — repository page renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/repository/X1165');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const heading = page.locator('h2, h3, .wt-page-title');
      await expect(heading.first()).toBeVisible();
    });

    test(`S30 — submitter page renders [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/submitter/X1166');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const heading = page.locator('h2, h3, .wt-page-title');
      await expect(heading.first()).toBeVisible();
    });
  });
}
