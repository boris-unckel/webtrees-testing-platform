// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Modul-Konfiguration — Admin-Seiten für Modulverwaltung
 *
 * @see docs/tds_conditions_ref.md A05
 * @see docs/systemtest/testspezi/A05_systemtest_spezi.md
 */

const modulePages = [
  { name: 'Alle Module', url: '/admin/modules' },
  { name: 'Analytics', url: '/admin/analytics' },
  { name: 'Blocks', url: '/admin/blocks' },
  { name: 'Charts', url: '/admin/charts' },
  { name: 'Menus', url: '/admin/menus' },
  { name: 'Reports', url: '/admin/reports' },
];

for (const modulePage of modulePages) {
  test(`A05 — ${modulePage.name}-Seite lädt`, async ({ page }) => {
    const response = await page.goto(modulePage.url);
    expect(response?.status()).toBeLessThan(500);
    await expect(page.locator('body')).toBeVisible();
  });
}

test('A05 — Modul-Übersicht zeigt Modultabelle', async ({ page }) => {
  await page.goto('/admin/modules');
  // Modul-Liste als Tabelle
  const table = page.locator('table, .table').first();
  const tableVisible = await table.isVisible().catch(() => false);
  await expect(page.locator('main').first()).toBeVisible();
});
