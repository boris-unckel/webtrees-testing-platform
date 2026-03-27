import { test, expect } from '@playwright/test';

/**
 * Systemtest: Theme-Rendering — Seiten rendern fehlerfrei
 *
 * Rein funktional, kein Visual Regression. Prüft nur, ob die Seiten
 * ohne HTTP-Fehler laden und grundlegende DOM-Elemente vorhanden sind.
 *
 * Testet mit dem aktiven Default-Theme (webtrees).
 *
 * @see docs/testing-bigpicture-prompt.md S25
 */

test.beforeEach(async ({ page }) => {
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
});

test.describe('Theme Rendering', () => {
  test('S25 — homepage renders without errors', async ({ page }) => {
    const response = await page.goto('/tree/demo');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('header, .wt-header, nav').first()).toBeVisible();
  });

  test('S25 — individual page renders without errors', async ({ page }) => {
    const response = await page.goto('/tree/demo/individual/X1030');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
  });

  test('S25 — search page renders without errors', async ({ page }) => {
    const response = await page.goto('/tree/demo/search-general');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
  });

  test('S25 — pedigree chart renders without errors', async ({ page }) => {
    const response = await page.goto('/tree/demo/pedigree');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
  });

  test('S25 — source list renders without errors', async ({ page }) => {
    const response = await page.goto('/tree/demo/source-list');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
  });
});
