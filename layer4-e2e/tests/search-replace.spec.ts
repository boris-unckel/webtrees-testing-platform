// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/otel-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';
import { ADMIN_PASSWORD } from '../helpers/auth';

/**
 * Systemtest: Search-and-Replace (Bulk-Editor)
 *
 * Tree-gebunden → Theme-Loop (5 Themes).
 *
 * @see docs/testing-bigpicture.md S13, AP 9-4
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    test.beforeEach(async ({ page }) => {
      await page.goto('/login/demo');
      await page.fill('input[name="username"]', 'admin');
      await page.fill('input[name="password"]', ADMIN_PASSWORD);
      await page.locator('button[type="submit"]').last().click();
      await page.waitForLoadState('networkidle');
    });

    test(`S13 — search-and-replace page renders for admin [${theme}]`, async ({ page }) => {
      const response = await page.goto('/tree/demo/search-replace');
      expect(response?.status()).toBeLessThan(500);

      await expect(page.locator('body')).toBeVisible();
      const form = page.locator('form');
      await expect(form.first()).toBeVisible();
    });

    test(`S13 — search-and-replace page shows form fields [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-replace');

      // Search field
      const searchInput = page.locator('input[name="search"], textarea[name="search"]');
      await expect(searchInput.first()).toBeVisible();

      // Replace field
      const replaceInput = page.locator('input[name="replace"], textarea[name="replace"]');
      await expect(replaceInput.first()).toBeVisible();
    });
  });
}

// Visitor test (outside theme loop — no login)
test('S13 — search-and-replace page not accessible for visitor', async ({ page }) => {
  const response = await page.goto('/tree/demo/search-replace');
  const status = response?.status() ?? 0;
  const url = page.url();

  // webtrees leitet nicht-authentifizierte Benutzer auf die Login-Seite um,
  // zeigt eine Fehlermeldung an oder verweigert Zugriff (Status 403/302/200+redirect)
  const isRedirectedToLogin = url.includes('login');
  const isAccessDenied = status === 403 || status === 302;
  const pageContent = await page.locator('body').textContent() ?? '';
  const hasAccessDeniedMessage = pageContent.includes('sign in') ||
    pageContent.includes('login') ||
    pageContent.includes('not authorized') ||
    pageContent.includes('Access denied');

  expect(isRedirectedToLogin || isAccessDenied || hasAccessDeniedMessage).toBeTruthy();
});
