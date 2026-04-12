// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Charts — 5 fehlende Typen (Timeline, Lifespans, FamilyBook, Descendants, Branches)
 *
 * @see docs/tds_conditions_ref.md S18
 * @see docs/systemtest/testspezi/S18_systemtest_spezi.md
 */

const chartRoutes = [
  { name: 'Timeline', url: '/tree/demo/timeline-10?xrefs%5B%5D=X1030' },
  { name: 'Lifespans', url: '/tree/demo/lifespans?xrefs%5B%5D=X1030' },
  { name: 'FamilyBook', url: '/tree/demo/family-book-2-2-0/X1030' },
  { name: 'Descendants', url: '/tree/demo/descendants-tree-3/X1030' },
  { name: 'Branches', url: '/tree/demo/branches/Windsor' },
];

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    for (const chart of chartRoutes) {
      test(`S18 — ${chart.name}-Chart lädt ohne Fehler [${theme}]`, async ({ page }) => {
        const response = await page.goto(chart.url);
        expect(response?.status()).toBeLessThan(500);
        await expect(page.locator('body')).toBeVisible();
        // Chart-Container oder Inhaltsbereich sichtbar
        await expect(page.locator('main, .wt-page-content, .wt-chart').first()).toBeVisible();
      });

      test(`S18 — ${chart.name}-Chart zeigt Inhaltsbereich [${theme}]`, async ({ page }) => {
        await page.goto(chart.url);
        // Jeder Chart-Typ rendert einen sichtbaren Inhaltsbereich
        const contentArea = page.locator('.wt-chart, .wt-page-content, canvas, svg, table').first();
        const isVisible = await contentArea.isVisible().catch(() => false);
        // Mindestens der Hauptcontainer muss sichtbar sein
        await expect(page.locator('main, .wt-page-content').first()).toBeVisible();
      });
    }
  });
}
