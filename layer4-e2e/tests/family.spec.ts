import { test, expect } from '@playwright/test';

/**
 * Systemtest: Familienseite (FamilyPage)
 *
 * @see docs/testing-bigpicture-prompt.md S24, AP 5b-2a
 */

test.beforeEach(async ({ page }) => {
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
});

test.describe('Family Page (S24)', () => {
  test('S24 — family page loads without errors', async ({ page }) => {
    const response = await page.goto('/tree/demo/family/f1');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
  });

  test('S24 — family page shows family header', async ({ page }) => {
    await page.goto('/tree/demo/family/f1');
    await page.waitForLoadState('networkidle');

    // Familienname/Titel im Seitenkopf
    const heading = page.locator('h2, h3, .wt-page-title');
    await expect(heading.first()).toBeVisible();
  });

  test('S24 — family page shows facts area', async ({ page }) => {
    await page.goto('/tree/demo/family/f1');
    await page.waitForLoadState('networkidle');

    // Fakten-Bereich (Ehe, Kinder etc.)
    const factsArea = page.locator('.wt-facts-table, table');
    await expect(factsArea.first()).toBeVisible();
  });
});
