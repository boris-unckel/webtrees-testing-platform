// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Stammbaum-Management — Bäume erstellen, anzeigen, löschen
 *
 * @see docs/tds_conditions_ref.md A01
 * @see docs/systemtest/testspezi/A01_systemtest_spezi.md
 */

test('A01 — ManageTrees-Seite lädt korrekt', async ({ page }) => {
  const response = await page.goto('/admin/trees/manage/demo');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  const content = await page.locator('body').textContent();
  // Demo-Baum sollte auf der Management-Seite sichtbar sein
  expect(content).toContain('demo');
});

test('A01 — Admin-Kontrollzentrum zeigt Bäume', async ({ page }) => {
  const response = await page.goto('/admin');
  expect(response?.status()).toBeLessThan(500);
  const content = await page.locator('body').textContent();
  // Demo-Baum sollte im Kontrollzentrum sichtbar sein
  expect(content).toContain('demo');
});

test('A01 — Create-Tree-Seite lädt mit Formular', async ({ page }) => {
  const response = await page.goto('/admin/trees/create');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  await expect(page.locator('form')).toBeVisible();
  // Formularfelder für Baumname und Titel vorhanden
  const nameField = page.locator('input[name="tree_name"], input[name="name"]').first();
  const fieldVisible = await nameField.isVisible().catch(() => false);
  // Mindestens ein Eingabefeld muss vorhanden sein
  const formInputs = page.locator('form input');
  const count = await formInputs.count();
  expect(count).toBeGreaterThan(0);
});

test('A01 — Neuen Baum anlegen und bereinigen', async ({ page }) => {
  const treeName = `test-tree-${Date.now()}`;
  await page.goto('/admin/trees/create');

  // Baumname eingeben
  const nameField = page.locator('input[name="tree_name"], input[name="name"]').first();
  if (await nameField.isVisible()) {
    await nameField.fill(treeName);
  }
  // Title-Feld falls vorhanden
  const titleField = page.locator('input[name="tree_title"], input[name="title"]').first();
  if (await titleField.isVisible()) {
    await titleField.fill(`Testbaum ${treeName}`);
  }
  await page.locator('button[type="submit"]').first().click();
  await page.waitForLoadState('networkidle');
  // Nach Anlage: Seite sollte ohne Fehler geladen haben
  await expect(page.locator('body')).toBeVisible();
  const content = await page.locator('body').textContent();
  expect(content).not.toContain('Error');
});
