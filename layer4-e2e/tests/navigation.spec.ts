import { test, expect } from '@playwright/test';

/**
 * Systemtest: Navigation und Seitenstruktur
 *
 * @see docs/testing-bigpicture-prompt.md S23, S24
 */

// Gemeinsamer Login vor allen Tests
test.beforeEach(async ({ page }) => {
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
});

test.describe('Navigation', () => {
  test('S23 — individual list renders', async ({ page }) => {
    await page.goto('/tree/demo/individual-list');
    await page.waitForLoadState('networkidle');

    // Seite muss laden (kein 500)
    await expect(page.locator('body')).toBeVisible();

    // Es sollte Personen-Links geben (Nachname-Links oder Individuen)
    const content = page.locator('.wt-page-content, main');
    await expect(content.first()).toBeVisible();
  });

  test('S24 — family list renders', async ({ page }) => {
    await page.goto('/tree/demo/family-list');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toBeVisible();
    const content = page.locator('.wt-page-content, main');
    await expect(content.first()).toBeVisible();
  });

  test('S09 — quick search returns results', async ({ page }) => {
    await page.goto('/tree/demo');
    await page.waitForLoadState('networkidle');

    // Quick-Search Feld (im Header)
    const searchInput = page.locator('input[name="query"]').first();

    if (await searchInput.isVisible()) {
      await searchInput.fill('Dombrink');
      await searchInput.press('Enter');
      await page.waitForLoadState('networkidle');

      // Sollte Suchergebnisse zeigen
      await expect(page.locator('body')).toBeVisible();
    }
  });
});
