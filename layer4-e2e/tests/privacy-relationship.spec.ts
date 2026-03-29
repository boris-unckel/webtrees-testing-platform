// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/otel-fixture';
import { loginAsRelationshipUser, logoutRole } from '../helpers/privacy-roles';

/**
 * Systemtest: Relationship Privacy im Browser (P22)
 *
 * Testet den Relationship-User (test-relationship) mit
 * PREF_TREE_ACCOUNT_XREF=P_REL_USER und PREF_TREE_PATH_LENGTH=2.
 *
 */

test.describe('Privacy Relationship', () => {
  test.afterEach(async ({ page }) => {
    await logoutRole(page);
  });

  test('P22 — relationship user sees close relative', async ({ page }) => {
    await loginAsRelationshipUser(page);
    await page.goto('/tree/privacy/individual/P_REL_CLOSE');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    // P_REL_CLOSE ist Kind von P_REL_USER (2 GEDCOM-Schritte, innerhalb PATH_LENGTH=2 → Distanz 4)
    expect(content).toContain('Clara');
  });

  test('P22 — relationship user cannot see far relative', async ({ page }) => {
    await loginAsRelationshipUser(page);
    await page.goto('/tree/privacy/individual/P_REL_FAR');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    // P_REL_FAR ist 6 GEDCOM-Schritte entfernt (ausserhalb PATH_LENGTH=2 → Distanz 4)
    const isRestricted =
      content?.includes('does not exist or you do not have permission') ||
      content?.includes('Private') ||
      content?.includes('Vertraulich');

    expect(isRestricted).toBeTruthy();
  });

  test('P22 — relationship user cannot see unrelated person', async ({ page }) => {
    await loginAsRelationshipUser(page);
    await page.goto('/tree/privacy/individual/P_REL_UNRELATED');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    const isRestricted =
      content?.includes('does not exist or you do not have permission') ||
      content?.includes('Private') ||
      content?.includes('Vertraulich');

    expect(isRestricted).toBeTruthy();
  });
});
