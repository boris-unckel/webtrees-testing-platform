// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Account-Selbstverwaltung — Eigenes Profil bearbeiten
 *
 * @see docs/tds_conditions_ref.md P38
 */


test('P38 — Account-Seite lädt korrekt', async ({ page }) => {
  const response = await page.goto('/my-account/demo');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
});

test('P38 — Account-Formular zeigt E-Mail-Feld', async ({ page }) => {
  await page.goto('/my-account/demo');
  const emailField = page.locator('#email, input[name="email"]').first();
  await expect(emailField).toBeVisible();
});

test('P38 — Account-Formular zeigt Sprachauswahl', async ({ page }) => {
  await page.goto('/my-account/demo');
  const langSelect = page.locator('select[name="language"]').first();
  const langVisible = await langSelect.isVisible();
  expect(langVisible || await page.locator('.wt-page-options-my-account').isVisible()).toBeTruthy();
});
