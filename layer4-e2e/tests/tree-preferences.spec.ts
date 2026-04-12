// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Stammbaum-Präferenzen — Einstellungen für einzelne Bäume
 *
 * @see docs/tds_conditions_ref.md A04
 */


test('A04 — Präferenzen-Seite lädt korrekt', async ({ page }) => {
  const response = await page.goto('/tree/demo/preferences');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  await expect(page.locator('form')).toBeVisible();
});

test('A04 — Präferenzen-Formular zeigt Einstellungsfelder', async ({ page }) => {
  await page.goto('/tree/demo/preferences');
  // Mindestens ein Select- oder Input-Feld in der Präferenz-Form
  const fields = page.locator('form select, form input[type="text"]');
  const count = await fields.count();
  expect(count).toBeGreaterThan(0);
});
