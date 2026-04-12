// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { loginAsRole, logoutRole } from '../helpers/privacy-roles';

/**
 * Systemtest: Privacy in Suchergebnissen (P24)
 *
 */

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Privacy Search', () => {
  test.afterEach(async ({ page }) => {
    await logoutRole(page);
  });

  test('P24 — visitor search for living person returns no results', async ({ page }) => {
    await logoutRole(page);
    await page.goto('/tree/privacy/search-general?query=Engel');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    // Lebende Person (P_ALIVE_YOUNG / Thomas Engel) sollte nicht auftauchen
    // Pruefe dass kein Suchergebnis-Link zu P_ALIVE_YOUNG existiert
    const resultLink = page.locator('a[href*="P_ALIVE_YOUNG"]');
    await expect(resultLink).toHaveCount(0);
  });

  test('P24 — member search for living person returns results', async ({ page }) => {
    await loginAsRole(page, 'member');
    await page.goto('/tree/privacy/search-general?query=Engel');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).toContain('Engel');
  });

  test('P24 — visitor search for RESN none person returns results', async ({ page }) => {
    await logoutRole(page);
    await page.goto('/tree/privacy/search-general?query=Peters');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).toContain('Peters');
  });

  test('P24 — visitor search for RESN confidential returns no results', async ({ page }) => {
    await logoutRole(page);
    await page.goto('/tree/privacy/search-general?query=Schreiber');
    await page.waitForLoadState('networkidle');

    const resultLink = page.locator('a[href*="P_RESN_CONFIDENTIAL"]');
    await expect(resultLink).toHaveCount(0);
  });
});
