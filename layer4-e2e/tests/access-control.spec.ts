// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { loginAsRole, logoutRole } from '../helpers/privacy-roles';

/**
 * Systemtest: Zugriffskontrolle im Browser (P27–P29)
 *
 * Testet Edit-Buttons, Pending Changes und Rollensperre.
 *
 */

test.describe('Access Control', () => {
  test.afterEach(async ({ page }) => {
    await logoutRole(page);
  });

  // P27 — Editor sieht Edit-Formular
  test('P27 — editor sees edit page for person', async ({ page }) => {
    await loginAsRole(page, 'editor');
    await page.goto('/tree/privacy/individual/P_EDIT_TARGET');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).toContain('Becker');

    // Editor sollte Edit-Buttons/Links sehen
    const editLinks = page.locator('a[href*="edit"], a[href*="add"], .wt-icon-edit');
    const editCount = await editLinks.count();
    // Mindestens ein Edit-Link sollte vorhanden sein
    expect(editCount).toBeGreaterThan(0);
  });

  // P29 — Visitor sieht keine Edit-Buttons
  test('P29 — visitor sees no edit buttons on person page', async ({ page }) => {
    await logoutRole(page);
    await page.goto('/tree/privacy/individual/P_DEAD_HISTORIC');
    await page.waitForLoadState('networkidle');

    // Besucher sollte keine Edit-Links sehen
    const editIcons = page.locator('.wt-icon-edit');
    await expect(editIcons).toHaveCount(0);
  });

  // P29 — Member sieht keine Edit-Buttons
  test('P29 — member sees no edit buttons on person page', async ({ page }) => {
    await loginAsRole(page, 'member');
    await page.goto('/tree/privacy/individual/P_DEAD_HISTORIC');
    await page.waitForLoadState('networkidle');

    const editIcons = page.locator('.wt-icon-edit');
    await expect(editIcons).toHaveCount(0);
  });

  // P29 — Editor auf RESN locked
  test('P29 — editor cannot edit RESN locked person', async ({ page }) => {
    await loginAsRole(page, 'editor');
    await page.goto('/tree/privacy/individual/P_RESN_LOCKED');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).toContain('Wagner');

    // RESN locked: Das Record-Level Edit-Menue (Dropdown) ist fuer Editor nicht sichtbar.
    // GedcomRecord::canEdit() gibt false zurueck → individual-page-menu wird nicht gerendert.
    // Hinweis: Fakt-Level Edit-Links (edit-fact) bleiben sichtbar, da Fact::canEdit()
    // nur den RESN des jeweiligen Fakts prueft, nicht den Record-Level RESN.
    const editMenuButton = page.locator('.wt-page-menu-button');
    await expect(editMenuButton).toHaveCount(0);
  });

  // P28 — Moderator sieht Pending Changes
  test('P28 — moderator can access pending changes page', async ({ page }) => {
    await loginAsRole(page, 'moderator');
    await page.goto('/tree/privacy/pending');
    await page.waitForLoadState('networkidle');

    // Seite sollte ladbar sein (kein Access Denied)
    const content = await page.textContent('body');
    const isAccessible =
      !content?.includes('does not exist or you do not have permission') &&
      !content?.includes('Access denied');

    expect(isAccessible).toBeTruthy();
  });
});
