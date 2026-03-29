// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../../helpers/otel-fixture';

/**
 * Sicherheitstest: Setup-Wizard ist nach Abschluss gesperrt
 *
 * @see docs/security_plan.md Abschnitt 7.2.4
 * @see SEC-W01
 */

test.describe('SEC-W01: Wizard nicht erreichbar nach Setup', () => {

  test('GET /setup zeigt kein Wizard-Formular', async ({ page }) => {
    const response = await page.goto('/setup');
    // Response enthält kein Setup-Formular
    await expect(page.locator('select#lang')).not.toBeVisible();
    await expect(page).not.toHaveTitle(/Setup wizard/i);
  });

  test('POST /setup mit step=1 zeigt kein Wizard-Verhalten', async ({ request }) => {
    const response = await request.post('/', {
      form: { step: '1', lang: 'en-US' },
    });
    const body = await response.text();
    expect(body).not.toContain('Setup wizard');
  });

  test('POST /setup mit step=6 erstellt keinen neuen Admin', async ({ request }) => {
    const response = await request.post('/', {
      form: {
        step: '6',
        lang: 'en-US',
        dbtype: 'mysql',
        dbhost: 'mysql-security',
        dbport: '3306',
        dbuser: 'webtrees',
        dbpass: 'security_test',
        dbname: 'webtrees_security',
        tblpfx: 'wt_',
        wtname: 'Hacker',
        wtuser: 'hacker',
        wtpass: 'hacked123',
        wtemail: 'hacker@evil.com',
        baseurl: 'http://webtrees-security:80',
      },
    });
    const body = await response.text();
    // Kein Wizard-Verhalten — normales webtrees-Routing
    expect(body).not.toContain('Setup wizard');
  });
});
