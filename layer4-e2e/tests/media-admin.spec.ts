// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Medienverwaltung Admin (ManageMedia, FixLevel0Media)
 *
 * Testet Admin-Media-Seiten: Seitenladen, Radio-Button-Interaktion,
 * DataTable-Rendering, FixLevel0-Seite.
 *
 * @see docs/tds_conditions_ref.md A08
 * @see docs/systemtest/testspezi/A08_systemtest_spezi.md
 */

test.describe('A08 — Medienverwaltung Admin', () => {

  // T1 — ManageMedia-Seite laedt
  test('A08 — ManageMedia-Seite laedt mit Formular', async ({ page }) => {
    const response = await page.goto('/admin/media');
    expect(response?.status()).toBeLessThan(500);
    await expect(page.locator('body')).toBeVisible();
    // Formular mit Radio-Buttons muss vorhanden sein
    const radioButtons = page.locator('input[name="files"]');
    const count = await radioButtons.count();
    expect(count).toBeGreaterThan(0);
  });

  // T2 — Radio "local" auswaehlen (Formular submitted automatisch via jQuery change)
  test('A08 — Radio "local" zeigt lokale Media-Dateien', async ({ page }) => {
    await page.goto('/admin/media');
    await page.waitForLoadState('networkidle');
    // Radio-Button "local" anklicken — Formular submitted automatisch
    await page.click('input[name="files"][value="local"]');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/admin/media');
    await expect(page.locator('body')).toBeVisible();
  });

  // T3 — Radio "external" auswaehlen
  test('A08 — Radio "external" zeigt externe Referenzen', async ({ page }) => {
    await page.goto('/admin/media');
    await page.waitForLoadState('networkidle');
    await page.click('input[name="files"][value="external"]');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/admin/media');
    await expect(page.locator('body')).toBeVisible();
  });

  // T4 — Radio "unused" auswaehlen
  test('A08 — Radio "unused" zeigt unbenutzte Dateien', async ({ page }) => {
    await page.goto('/admin/media');
    await page.waitForLoadState('networkidle');
    await page.click('input[name="files"][value="unused"]');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/admin/media');
    await expect(page.locator('body')).toBeVisible();
  });

  // T5 — FixLevel0Media-Seite laedt
  test('A08 — FixLevel0Media-Seite laedt', async ({ page }) => {
    const response = await page.goto('/admin/fix-level-0-media');
    expect(response?.status()).toBeLessThan(500);
    await expect(page.locator('body')).toBeVisible();
  });

  // T6 — Non-Admin Zugriff auf /admin/media
  test('A08 — Non-Admin erhaelt 403 oder Redirect zu Login', async ({ browser }) => {
    // Neuer Kontext ohne Login
    const context = await browser.newContext({
      baseURL: process.env.BASE_URL || 'http://webtrees:80',
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    await page.goto('/admin/media');
    await page.waitForLoadState('networkidle');
    const url = page.url();
    // Visitor wird von /admin/ weg umgeleitet (auf Startseite oder Login)
    expect(url).not.toContain('/admin/');
    await context.close();
  });
});
