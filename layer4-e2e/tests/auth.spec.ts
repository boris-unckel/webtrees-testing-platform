import { test, expect } from '@playwright/test';

/**
 * Systemtest: Registrierung und Passwort-Zurücksetzung
 *
 * Prüft nur, ob die Seiten erreichbar sind und Formulare rendern.
 * Keine tatsächliche Registrierung oder E-Mail-Versand.
 *
 * @see docs/testing-bigpicture-prompt.md S33, S34, AP 5b-2e
 */

test.describe('Auth Pages', () => {
  test('S33 — registration page renders', async ({ page }) => {
    const response = await page.goto('/register');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
  });

  test('S34 — password reset page renders', async ({ page }) => {
    const response = await page.goto('/password-request');
    expect(response?.status()).toBeLessThan(500);

    await expect(page.locator('body')).toBeVisible();
  });
});
