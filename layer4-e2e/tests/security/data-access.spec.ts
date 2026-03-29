// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '@playwright/test';

/**
 * Sicherheitstest: HTTP-Zugriffskontrolle auf data/-Verzeichnis
 * Vorbedingung: Wizard wurde durchlaufen (SEC-WZ02 bestanden)
 *
 * @see docs/security_plan.md Abschnitt 7.2.2
 */

// @see SEC-H03
test('SEC-H03: GET /data/ blockiert', async ({ request }) => {
  const response = await request.get('/data/');
  expect(response.status()).toBe(403);
});

// @see SEC-H04
test('SEC-H04: GET /data/config.ini.php blockiert', async ({ request }) => {
  const response = await request.get('/data/config.ini.php');
  expect(response.status()).toBe(403);
  const body = await response.text();
  expect(body).not.toContain('dbpass');
  expect(body).not.toContain('dbuser');
});

// @see SEC-H05
test('SEC-H05: GET /data/media/ blockiert', async ({ request }) => {
  const response = await request.get('/data/media/');
  expect(response.status()).toBe(403);
});

// @see SEC-H06
test.describe('SEC-H06: URL-Encoding umgeht .htaccess nicht', () => {
  const variants = [
    { name: 'Percent-Encoding', url: '/data/config%2Eini%2Ephp' },
    { name: 'Double-Encoding', url: '/data/config%252Eini%252Ephp' },
    { name: 'Trailing-Slash', url: '/data/config.ini.php/' },
    { name: 'Null-Byte', url: '/data/config.ini.php%00' },
    { name: 'Backslash', url: '/data%5Cconfig.ini.php' },
    { name: 'Unicode Zero-Width Space', url: '/data/config.ini%E2%80%8B.php' },
    { name: 'Case-Variation', url: '/Data/config.ini.php' },
  ];

  for (const variant of variants) {
    test(`${variant.name}: ${variant.url}`, async ({ request }) => {
      const response = await request.get(variant.url);
      // 403 (blocked) oder 404 (not found, z.B. bei Case-Variation auf Linux)
      // Hauptsache kein 200 mit Dateiinhalt
      expect([403, 404]).toContain(response.status());
      const body = await response.text();
      expect(body).not.toContain('dbpass');
      expect(body).not.toContain('dbuser');
    });
  }
});
