// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Benutzerverwaltung Admin — User-Liste, Filter, Cleanup
 *
 * @see docs/tds_conditions_ref.md A07
 */


test('A07 — User-Liste lädt korrekt', async ({ page }) => {
  const response = await page.goto('/admin/admin-users');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  const content = await page.locator('body').textContent();
  expect(content).toContain('admin');
});

test('A07 — User-Liste mit Filter', async ({ page }) => {
  const response = await page.goto('/admin/admin-users?filter=admin');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
});

test('A07 — Cleanup-Seite lädt', async ({ page }) => {
  const response = await page.goto('/admin/users-cleanup');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
});
