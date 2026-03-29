// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../../helpers/otel-fixture';

/**
 * Sicherheitstest: Setup-Wizard-Durchlauf und Wizard-Lock
 *
 * @see docs/security_plan.md Abschnitt 7.2.1
 * @see SEC-WZ01 Wizard erscheint bei Erstaufruf
 * @see SEC-WZ02 Wizard-Durchlauf komplett (6 Schritte)
 * @see SEC-WZ03 config.ini.php existiert nach Wizard (Layer-4-Anteil)
 * @see SEC-WZ04 Wizard sperrt sich selbst nach Abschluss
 */

test.describe.serial('Setup-Wizard', () => {

  // @see SEC-WZ01
  test('SEC-WZ01: Wizard erscheint bei Erstaufruf', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Setup wizard/i);
    await expect(page.locator('select#lang')).toBeVisible();
  });

  // @see SEC-WZ02
  test('SEC-WZ02: Wizard-Durchlauf komplett', async ({ page }) => {
    // Schritt 1: Sprache wählen
    await page.goto('/');
    await page.selectOption('select#lang', 'en-US');
    await page.click('button[name="step"][value="2"]');

    // Schritt 2: Server-Checks — keine Fehler, weiter
    await expect(page.locator('button[name="step"][value="3"]')).toBeVisible();
    await page.click('button[name="step"][value="3"]');

    // Schritt 3: Datenbank-Typ — MySQL (Default)
    await expect(page.locator('button[name="step"][value="4"]')).toBeVisible();
    await page.click('button[name="step"][value="4"]');

    // Schritt 4: DB-Verbindung
    await expect(page.locator('input[name="dbhost"]')).toBeVisible();
    await page.fill('input[name="dbhost"]', 'mysql-security');
    await page.fill('input[name="dbport"]', '3306');
    await page.fill('input[name="dbuser"]', 'webtrees');
    await page.fill('input[name="dbpass"]', 'security_test');
    await page.fill('input[name="dbname"]', 'webtrees_security');
    await page.fill('input[name="tblpfx"]', 'wt_');
    await page.click('button[name="step"][value="5"]');

    // Schritt 5: Admin-Account
    await expect(page.locator('input[name="wtname"]')).toBeVisible();
    await page.fill('input[name="wtname"]', 'Security Admin');
    await page.fill('input[name="wtuser"]', 'secadmin');
    await page.fill('input[name="wtpass"]', 'sectest123');
    await page.fill('input[name="wtemail"]', 'sec@test.local');

    await page.click('button[name="step"][value="6"]');

    // Schritt 6: Base-URL + Installation
    await page.waitForLoadState('networkidle');
    const baseUrlInput = page.locator('input[name="baseurl"]');
    if (await baseUrlInput.isVisible({ timeout: 2000 }).catch(() => false)) {
      await page.fill('input[name="baseurl"]', 'http://webtrees-security:80');
      await page.click('button[name="step"][value="7"]');
      await page.waitForLoadState('networkidle');
    }

    // Nach Wizard: normale webtrees-Seite (kein Setup-Wizard mehr)
    await expect(page).not.toHaveTitle(/Setup wizard/i);
  });

  // @see SEC-WZ03 (Layer-4-Anteil — Layer-3-Anteil in security-filesystem-checks.sh)
  test('SEC-WZ03: config.ini.php existiert nach Wizard', async ({ request }) => {
    // Indirekt prüfen: GET / liefert keine Wizard-Seite mehr → config.ini.php existiert
    const response = await request.get('/');
    const body = await response.text();
    expect(body).not.toContain('Setup wizard');
  });

  // @see SEC-WZ04
  test('SEC-WZ04: Wizard gesperrt nach Abschluss', async ({ page }) => {
    await page.goto('/');
    // Wizard-Selektoren dürfen NICHT sichtbar sein
    await expect(page.locator('select#lang')).not.toBeVisible();
    await expect(page).not.toHaveTitle(/Setup wizard/i);
  });
});
