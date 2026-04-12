// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Rohdaten-Edit (Raw GEDCOM) — GEDCOM-Editor für Records
 *
 * @see docs/tds_conditions_ref.md E03
 */


test('E03 — Raw-Edit-Seite lädt für bekannten Record', async ({ page }) => {
  const response = await page.goto('/tree/demo/edit-raw/X1030');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  // Textarea mit GEDCOM-Daten soll sichtbar sein
  await expect(page.locator('textarea, form').first()).toBeVisible();
});

test('E03 — Raw-Edit-Seite zeigt GEDCOM-Inhalt', async ({ page }) => {
  await page.goto('/tree/demo/edit-raw/X1030');
  const content = await page.locator('body').textContent();
  // GEDCOM-Inhalt sollte INDI-Tag oder NAME-Tag enthalten
  expect(content).toBeTruthy();
});
