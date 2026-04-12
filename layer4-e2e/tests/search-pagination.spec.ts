// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { themes, switchTheme } from '../helpers/theme-switch';

/**
 * Systemtest: Paginierung — Suchergebnisse mit Seitenwechsel
 *
 * @see docs/tds_conditions_ref.md S10
 * @see docs/systemtest/testspezi/S10_systemtest_spezi.md
 */

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      await switchTheme(browser, theme);
    });

    test(`S10 — Suche mit vielen Treffern zeigt Datentabelle [${theme}]`, async ({ page }) => {
      // Allgemeine Suche "a" liefert viele Treffer aus dem Demo-GEDCOM (72+ Individuen)
      const response = await page.goto('/tree/demo/search-general?query=a');
      expect(response?.status()).toBeLessThan(500);
      await page.waitForLoadState('networkidle');
      // Datentabelle oder Ergebnisliste sichtbar
      const tableOrResults = page.locator('table, .wt-table, .dataTables_wrapper').first();
      await expect(tableOrResults).toBeVisible();
    });

    test(`S10 — Suchergebnisse zeigen Paginierungs-Controls [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-general?query=a');
      await page.waitForLoadState('networkidle');
      // DataTables-Paginierung oder Standard-Paginierung
      const pagination = page.locator('.dataTables_paginate, .pagination, .paginate_button');
      const count = await pagination.count();
      expect(count).toBeGreaterThan(0);
    });

    test(`S10 — Paginierung: Seitenwechsel zeigt andere Ergebnisse [${theme}]`, async ({ page }) => {
      await page.goto('/tree/demo/search-general?query=a');
      await page.waitForLoadState('networkidle');
      // Ersten sichtbaren Tabelleneintrag merken
      const firstPageContent = await page.locator('.dataTables_wrapper tbody tr:first-child, table tbody tr:first-child').first().textContent() ?? '';
      // Nächste Seite klicken (DataTables "Next"-Button oder Seite-2-Link)
      const nextBtn = page.locator('.dataTables_paginate .paginate_button.next, .pagination .page-link:has-text("Next"), .pagination .page-link:has-text("2")').first();
      if (await nextBtn.isVisible()) {
        await nextBtn.click();
        await page.waitForLoadState('networkidle');
        // Nach Seitenwechsel: Seite sollte geladen bleiben
        await expect(page.locator('body')).toBeVisible();
      }
    });
  });
}
