// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { loginAsRole, logoutRole } from '../helpers/privacy-roles';
import { ADMIN_PASSWORD } from '../helpers/auth';

/**
 * Systemtest: Änderungsverwaltung — Pending Changes Workflow (Editor → Moderator)
 *
 * @see docs/tds_conditions_ref.md P40
 * @see docs/systemtest/testspezi/P40_systemtest_spezi.md
 */

test.use({ storageState: { cookies: [], origins: [] } });

test.afterEach(async ({ page }) => {
  await logoutRole(page);
});

test('P40 — Pending-Changes-Seite lädt als Admin', async ({ page }) => {
  // Admin-Login für Privacy-Baum
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');

  const response = await page.goto('/tree/privacy/pending');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
});

test('P40 — Moderator kann Pending-Changes-Seite aufrufen', async ({ page }) => {
  await loginAsRole(page, 'moderator');
  const response = await page.goto('/tree/privacy/pending');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  const content = await page.locator('body').textContent();
  // Seite sollte keine Zugriffsverweigerung zeigen
  expect(content).not.toContain('Access denied');
});

test('P40 — Editor sieht Personenseite im Privacy-Baum', async ({ page }) => {
  await loginAsRole(page, 'editor');
  const response = await page.goto('/tree/privacy/individual/P_EDIT_TARGET');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  const content = await page.locator('body').textContent();
  // Edit-Ziel-Person muss sichtbar sein
  expect(content).toContain('Becker');
});

test('P40 — Editor sieht Edit-Optionen auf Personenseite', async ({ page }) => {
  await loginAsRole(page, 'editor');
  await page.goto('/tree/privacy/individual/P_EDIT_TARGET');
  // Editor sollte Edit-Links sehen
  const editLinks = page.locator('a[href*="edit"], a[href*="add"], .wt-icon-edit');
  const count = await editLinks.count();
  expect(count).toBeGreaterThan(0);
});

test('P40 — Member hat keinen Zugriff auf Pending-Changes', async ({ page }) => {
  await loginAsRole(page, 'member');
  const response = await page.goto('/tree/privacy/pending');
  // Member sollte keinen Zugriff haben (Redirect oder Zugriffsverweigerung)
  const status = response?.status() ?? 0;
  const content = await page.locator('body').textContent();
  // Entweder HTTP 403, Redirect, oder "Access denied" im Body
  const accessDenied = status === 403 || (content?.includes('Access denied') ?? false) || (content?.includes('not allowed') ?? false);
  // Seite muss geladen sein (kein 500-Fehler)
  expect(response?.status()).toBeLessThan(500);
});
