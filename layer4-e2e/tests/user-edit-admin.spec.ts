// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Benutzer-Bearbeitung (Admin) — User-Edit-Formular
 *
 * @see docs/tds_conditions_ref.md P37
 */


test('P37 — User-Liste zeigt Benutzer', async ({ page }) => {
  const response = await page.goto('/admin/admin-users');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  const content = await page.locator('body').textContent();
  expect(content).toContain('admin');
});

test('P37 — User-Edit-Seite lädt für Admin-User', async ({ page }) => {
  // Admin-User-ID ist typischerweise 1
  const response = await page.goto('/admin/admin-users-edit?user_id=1');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  await expect(page.locator('form')).toBeVisible();
});

test('P37 — User-Edit-Formular zeigt E-Mail-Feld', async ({ page }) => {
  await page.goto('/admin/admin-users-edit?user_id=1');
  const emailField = page.locator('input[name="email"], input[type="email"]').first();
  await expect(emailField).toBeVisible();
});

test('P37 — User-Edit-Formular zeigt Username-Feld', async ({ page }) => {
  await page.goto('/admin/admin-users-edit?user_id=1');
  const usernameField = page.locator('input[name="username"], input[name="user_name"]').first();
  await expect(usernameField).toBeVisible();
});
