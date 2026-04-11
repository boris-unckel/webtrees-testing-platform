// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { loginAsRole, logoutRole } from '../helpers/privacy-roles';

/**
 * Systemtest: RESN-Tags im Browser (P16–P19)
 *
 */

test.describe('Privacy RESN', () => {
  test.afterEach(async ({ page }) => {
    await logoutRole(page);
  });

  // P16 — RESN none
  test('P16 — visitor sees person with RESN none despite being alive', async ({ page }) => {
    await logoutRole(page);
    await page.goto('/tree/privacy/individual/P_RESN_NONE');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).toContain('Peters');
  });

  // P17 — RESN privacy
  test('P17 — visitor cannot see RESN privacy person', async ({ page }) => {
    await logoutRole(page);
    await page.goto('/tree/privacy/individual/P_RESN_PRIVACY');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    const isRestricted =
      content?.includes('does not exist or you do not have permission') ||
      content?.includes('Private') ||
      content?.includes('Vertraulich');

    expect(isRestricted).toBeTruthy();
  });

  test('P17 — member sees RESN privacy person', async ({ page }) => {
    await loginAsRole(page, 'member');
    await page.goto('/tree/privacy/individual/P_RESN_PRIVACY');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).toContain('Richter');
  });

  // P18 — RESN confidential
  test('P18 — member cannot see RESN confidential person', async ({ page }) => {
    await loginAsRole(page, 'member');
    await page.goto('/tree/privacy/individual/P_RESN_CONFIDENTIAL');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    const isRestricted =
      content?.includes('does not exist or you do not have permission') ||
      content?.includes('Private') ||
      content?.includes('Vertraulich');

    expect(isRestricted).toBeTruthy();
  });

  test('P18 — manager sees RESN confidential person', async ({ page }) => {
    await loginAsRole(page, 'manager');
    await page.goto('/tree/privacy/individual/P_RESN_CONFIDENTIAL');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).toContain('Schreiber');
  });

  // P19 — Fact-Level RESN
  test('P19 — visitor sees person but not RESN-privacy birth date', async ({ page }) => {
    await logoutRole(page);
    await page.goto('/tree/privacy/individual/P_FACT_RESN_BIRT');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    // Person (verstorben) sichtbar, aber BIRT mit RESN privacy nicht fuer Besucher
    expect(content).toContain('Thiel');
    // Geburtsdatum sollte nicht sichtbar sein
    expect(content).not.toContain('30 AUG 1985');
  });

  test('P19 — member sees RESN-privacy birth date', async ({ page }) => {
    await loginAsRole(page, 'member');
    await page.goto('/tree/privacy/individual/P_FACT_RESN_BIRT');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).toContain('Thiel');
    // Mitglied sieht das Geburtsdatum
    expect(content).toContain('1985');
  });
});
