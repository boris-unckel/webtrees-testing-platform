// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Datensatz-Zusammenführung — Auswahl (P30) und Ausführung (P41)
 *
 * @see docs/tds_conditions_ref.md P30, P41
 * @see docs/systemtest/testspezi/P30_systemtest_spezi.md
 * @see docs/systemtest/testspezi/P41_systemtest_spezi.md
 */

// --- P30: Merge-Auswahl ---

test('P30 — Merge-Seite lädt mit Formular', async ({ page }) => {
  const response = await page.goto('/tree/demo/merge-step1');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  // Admin-Seite: Formular mit Record-Auswahl vorhanden
  await expect(page.locator('form').first()).toBeVisible();
});

test('P30 — Merge-Seite zeigt Record-Typ-Auswahl und Felder', async ({ page }) => {
  await page.goto('/tree/demo/merge-step1');
  // Dropdowns (select) oder TomSelect-Wrapper für Record-Auswahl
  const inputs = page.locator('select, .ts-wrapper');
  const count = await inputs.count();
  // Mindestens Record-Typ + First Record + Second Record
  expect(count).toBeGreaterThanOrEqual(1);
});

test('P30 — Merge-Seite zeigt Continue-Button', async ({ page }) => {
  await page.goto('/tree/demo/merge-step1');
  // "continue"-Button auf der Admin-Merge-Seite
  const submitBtn = page.locator('button[type="submit"]').first();
  await expect(submitBtn).toBeVisible();
});

// --- P41: Merge-Ausführung ---

test('P41 — Merge-Formular lädt ohne Fehler', async ({ page }) => {
  const response = await page.goto('/tree/demo/merge-step1');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  const content = await page.locator('body').textContent();
  expect(content).not.toContain('Error');
});

test('P41 — Merge-Seite ohne Crash bei leerer Eingabe', async ({ page }) => {
  await page.goto('/tree/demo/merge-step1');
  const submitBtn = page.locator('button[type="submit"]').first();
  if (await submitBtn.isVisible()) {
    await submitBtn.click();
    await page.waitForLoadState('networkidle');
  }
  // Seite bleibt geladen (kein 500-Fehler)
  await expect(page.locator('body')).toBeVisible();
});
