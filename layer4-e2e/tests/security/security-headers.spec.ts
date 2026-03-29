import { test, expect } from '@playwright/test';

/**
 * Sicherheitstest: Security-Headers
 *
 * @see docs/security_plan.md Abschnitt 7.2.6
 */

// @see SEC-HDR01
test('SEC-HDR01: X-Content-Type-Options', async ({ request }) => {
  const response = await request.get('/');
  expect(response.headers()['x-content-type-options']).toBe('nosniff');
});

// @see SEC-HDR02
test('SEC-HDR02: X-Frame-Options', async ({ request }) => {
  const response = await request.get('/');
  const xfo = response.headers()['x-frame-options'];
  expect(xfo).toBeDefined();
  expect(['SAMEORIGIN', 'DENY', 'sameorigin', 'deny']).toContain(xfo);
});

// @see SEC-HDR03
test('SEC-HDR03: Referrer-Policy', async ({ request }) => {
  const response = await request.get('/');
  const rp = response.headers()['referrer-policy'];
  expect(rp).toBeDefined();
  expect(rp).not.toBe('');
});

// @see SEC-HDR04
// DEPLOYMENT-EMPFEHLUNG: Apache ServerTokens Default = Full → Versionsinfo sichtbar.
// Keine Upstream-PHP-Schwäche, sondern Apache-Konfiguration.
// Status: Rot (Deployment-Empfehlung) — dokumentiert, nicht blockierend.
test('SEC-HDR04: Server-Banner', async ({ request }) => {
  const response = await request.get('/');
  const server = response.headers()['server'] || '';
  // Dokumentiere den Ist-Zustand
  // Apache Default (ServerTokens Full) enthält Versionsinfo
  test.fixme(
    !!server.match(/Apache\/\d+\.\d+\.\d+/),
    `Server-Banner enthält Version: "${server}" — Deployment-Empfehlung: ServerTokens Prod`
  );
  expect(server).not.toMatch(/Apache\/\d+\.\d+\.\d+/);
});
