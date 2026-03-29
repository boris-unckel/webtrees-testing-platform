import { test, expect } from '@playwright/test';

/**
 * Sicherheitstest: public/-Verzeichnis — keine PHP-Execution, kein Listing, kein Traversal
 *
 * @see docs/security_plan.md Abschnitt 7.2.3
 */

// @see SEC-PUB02
test('SEC-PUB02: public/index.php wird nicht als PHP ausgeführt', async ({ request }) => {
  const response = await request.get('/public/index.php');
  const body = await response.text();
  // PublicFiles-Middleware liefert Dateiinhalt als statischen Text
  expect(body).toContain('require');
});

// @see SEC-PUB03
test('SEC-PUB03: Kein Directory Listing auf /public/', async ({ request }) => {
  const response = await request.get('/public/');
  const body = await response.text();
  // Kein Apache-Verzeichnislisting
  expect(body).not.toMatch(/<a\s+href=.*>/i);
});

// @see SEC-PUB04
test.describe('SEC-PUB04: Path-Traversal blockiert', () => {
  const variants = [
    { name: 'Einfach', url: '/public/../data/config.ini.php' },
    { name: 'Encoded Dots', url: '/public/%2e%2e/data/config.ini.php' },
    { name: 'Double-Encoded', url: '/public/%252e%252e/data/config.ini.php' },
    { name: 'Mixed', url: '/public/..%2fdata/config.ini.php' },
    { name: 'Overlong UTF-8', url: '/public/%c0%ae%c0%ae/data/config.ini.php' },
  ];

  for (const variant of variants) {
    test(`${variant.name}: ${variant.url}`, async ({ request }) => {
      const response = await request.get(variant.url);
      const body = await response.text();
      expect(body).not.toContain('dbpass');
      expect(body).not.toContain('dbuser');
    });
  }
});
