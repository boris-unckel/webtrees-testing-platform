// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { ADMIN_PASSWORD } from '../helpers/auth';

/**
 * Systemtest: Login-Funktionalität
 *
 * @see docs/tds_conditions_ref.md S23
 */

test.describe('Login', () => {
  test('should show login page', async ({ page }) => {
    await page.goto('/login/demo');

    // webtrees zeigt Login-Formular
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });

  test('should login with valid credentials', async ({ page }) => {
    await page.goto('/login/demo');

    // Login-Formular ausfüllen (Admin-User aus setup-webtrees.sh)
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await page.locator('button[type="submit"]').last().click();

    // Nach Login: Dashboard oder Baumseite
    await page.waitForLoadState('networkidle');
    await expect(page).not.toHaveURL(/login/);
  });

  test('should reject invalid credentials', async ({ page }) => {
    await page.goto('/login/demo');

    await page.fill('input[name="username"]', 'invalid');
    await page.fill('input[name="password"]', 'invalid');
    await page.locator('button[type="submit"]').last().click();

    await page.waitForLoadState('networkidle');

    // Fehlermeldung oder Login-Seite bleibt
    const errorVisible = await page.locator('.alert-danger, .alert-warning').isVisible();
    const stillOnLogin = page.url().includes('login');

    expect(errorVisible || stillOnLogin).toBeTruthy();
  });
});
