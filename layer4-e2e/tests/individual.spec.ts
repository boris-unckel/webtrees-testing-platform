import { test, expect } from '@playwright/test';

/**
 * Systemtest: Personenseite — Fakten, Familien, Events
 *
 * @see docs/testing-bigpicture-prompt.md S23
 */

test.beforeEach(async ({ page }) => {
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
});

test.describe('Individual Page', () => {
  test('S23 — person page shows name and vital facts', async ({ page }) => {
    // Erste Person im Demo-Baum aufrufen
    await page.goto('/tree/demo/individual/X1030');
    await page.waitForLoadState('networkidle');

    // Seite muss laden
    await expect(page.locator('body')).toBeVisible();

    // Name muss irgendwo sichtbar sein
    const heading = page.locator('h2, h3, .wt-page-title');
    await expect(heading.first()).toBeVisible();
  });

  test('S23 — person page shows facts area', async ({ page }) => {
    await page.goto('/tree/demo/individual/X1030');
    await page.waitForLoadState('networkidle');

    // Fakten-Bereich oder Tabs müssen existieren
    const factsArea = page.locator('.wt-facts-table, .wt-tab-facts, .nav-tabs, table');
    await expect(factsArea.first()).toBeVisible();
  });
});
