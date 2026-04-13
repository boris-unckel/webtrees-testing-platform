// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';

/**
 * Systemtest: Error-Page-Rendering (provozierte HTTP-Fehler)
 *
 * Testet die Darstellung von Error-Pages bei verschiedenen HTTP-Fehlercodes.
 * HandleExceptions-Middleware faengt Exceptions und rendert passende Fehlerseiten.
 *
 * @see docs/tds_conditions_ref.md M16
 * @see docs/systemtest/testspezi/M16_systemtest_spezi.md
 */

// Visitor-Kontext: kein Login, leere Storage-State
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('M16 — Error-Page-Rendering', () => {

  // T1 — Nicht existierende XREF → HTTP-Client-Fehler + Alert sichtbar
  test('M16 — Client-Error bei nicht existierender XREF', async ({ page }) => {
    const response = await page.goto('/tree/demo/individual/NONEXISTENT_XREF_99999');
    const status = response?.status() ?? 0;
    // webtrees gibt 4xx zurueck (400/404/406 je nach Kontext)
    expect(status).toBeGreaterThanOrEqual(400);
    expect(status).toBeLessThan(500);
    await expect(page.locator('.alert.alert-danger')).toBeVisible();
  });

  // T2 — Admin-Seite ohne Login → Redirect (kein Admin-Zugriff)
  test('M16 — Admin-Seite ohne Login wird umgeleitet', async ({ page }) => {
    await page.goto('/admin/control-panel');
    await page.waitForLoadState('networkidle');
    const url = page.url();
    // Visitor wird von /admin/ weg umgeleitet (auf Startseite oder Login)
    expect(url).not.toContain('/admin/');
  });

  // T3 — Error-Page hat Alert-Container mit role="alert" (ARIA)
  test('M16 — Error-Page enthaelt Alert-Container mit ARIA-Rolle', async ({ page }) => {
    await page.goto('/tree/demo/individual/NONEXISTENT_XREF_99999');
    const alert = page.locator('.alert.alert-danger[role="alert"]');
    await expect(alert).toBeVisible();
  });

  // T4 — Error-Page enthaelt nicht-leeren Fehlermeldungstext
  test('M16 — Error-Page enthaelt Fehlermeldung', async ({ page }) => {
    await page.goto('/tree/demo/individual/NONEXISTENT_XREF_99999');
    const alertText = await page.locator('.alert.alert-danger').textContent();
    // Fehlermeldung sollte nicht leer sein
    expect(alertText?.trim().length).toBeGreaterThan(0);
  });

  // T5 — Fehler bei ungueltigem Tree-Namen → HTTP-Client-Fehler
  test('M16 — Client-Error bei ungueltigem Tree-Namen', async ({ page }) => {
    const response = await page.goto('/tree/INVALID_TREE_NAME_99999');
    const status = response?.status() ?? 0;
    expect(status).toBeGreaterThanOrEqual(400);
    expect(status).toBeLessThan(500);
  });
});
