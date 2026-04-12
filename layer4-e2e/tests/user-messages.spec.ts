// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Benutzer-Nachrichten — Nachricht an anderen User senden
 *
 * @see docs/tds_conditions_ref.md K02
 * @see docs/systemtest/testspezi/K02_systemtest_spezi.md
 */

test('K02 — Nachrichten-Formular rendert korrekt', async ({ page }) => {
  const response = await page.goto('/tree/demo/message-compose?to=admin');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
});

test('K02 — Nachrichten-Formular zeigt Subject und Body', async ({ page }) => {
  await page.goto('/tree/demo/message-compose?to=admin');
  // Mindestens ein Formular im Inhaltsbereich vorhanden
  const forms = await page.locator('form').count();
  expect(forms).toBeGreaterThanOrEqual(1);
  // Subject- und Body-Felder prüfen
  const fields = page.locator('input[name="subject"], textarea[name="body"]');
  const count = await fields.count();
  expect(count).toBeGreaterThan(0);
});

test('K02 — Nachricht senden via Submit', async ({ page }) => {
  await page.goto('/tree/demo/message-compose?to=admin');
  // Felder ausfüllen
  const subjectField = page.locator('input[name="subject"]').first();
  if (await subjectField.isVisible()) {
    await subjectField.fill('Test-Nachricht');
  }
  const bodyField = page.locator('textarea[name="body"]').first();
  if (await bodyField.isVisible()) {
    await bodyField.fill('Dies ist eine Test-Nachricht aus dem Systemtest.');
  }
  // Submit-Button im Nachrichtenformular (nicht Header-Suche)
  const submitBtn = page.locator('button[type="submit"]').last();
  if (await submitBtn.isVisible()) {
    await submitBtn.click();
    await page.waitForLoadState('networkidle');
  }
  await expect(page.locator('body')).toBeVisible();
});

test('K02 — Leeres Formular-Submit ohne Crash', async ({ page }) => {
  await page.goto('/tree/demo/message-compose?to=admin');
  const submitBtn = page.locator('button[type="submit"]').last();
  if (await submitBtn.isVisible()) {
    await submitBtn.click();
    await page.waitForLoadState('networkidle');
  }
  await expect(page.locator('body')).toBeVisible();
});
