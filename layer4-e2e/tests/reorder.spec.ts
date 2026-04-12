// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Sortierung (Reorder) — Kinder, Namen, Familien
 *
 * @see docs/tds_conditions_ref.md E06
 */


test('E06 — Reorder-Children-Seite lädt für Familie', async ({ page }) => {
  const response = await page.goto('/tree/demo/reorder-children/f1');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
});

test('E06 — Reorder-Names-Seite lädt für Person', async ({ page }) => {
  const response = await page.goto('/tree/demo/reorder-names/X1030');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
});

test('E06 — Reorder-Families-Seite lädt für Person', async ({ page }) => {
  const response = await page.goto('/tree/demo/reorder-spouses/X1030');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
});
