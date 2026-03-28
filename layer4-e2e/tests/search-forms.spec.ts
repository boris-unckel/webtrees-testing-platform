import { test, expect } from '@playwright/test';

/**
 * Systemtest: Suchformulare (erweiterte + phonetische Suche)
 *
 * @see docs/testing-bigpicture-prompt.md S38, S39, AP 5b-2d
 */

test.beforeEach(async ({ page }) => {
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
});

test.describe('Search Forms', () => {
  test('S38 — advanced search form renders', async ({ page }) => {
    const response = await page.goto('/tree/demo/search-advanced');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
    // Formular mit Suchfeldern
    const form = page.locator('form');
    await expect(form.first()).toBeVisible();
  });

  test('S39 — phonetic search form renders', async ({ page }) => {
    const response = await page.goto('/tree/demo/search-phonetic');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
    // Formular mit Suchfeldern
    const form = page.locator('form');
    await expect(form.first()).toBeVisible();
  });
});
