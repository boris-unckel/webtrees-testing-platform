// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/otel-fixture';

/**
 * Systemtest: Legacy-URL-Weiterleitungen (Redirect*Php-Handler)
 *
 * Testet HTTP 301-Redirects fuer alte webtrees-1.x-URLs auf neue Pretty-URLs.
 * API-Only-Pattern: Keine Browser-Interaktion, nur Status- und Header-Pruefung.
 *
 * Voraussetzung: Apache-Rewrite-Regel muss .php-URLs an index.php weiterleiten,
 * da mod_php diese sonst als 404 behandelt (FallbackResource greift nicht fuer .php).
 *
 * @see docs/tds_conditions_ref.md S53
 * @see docs/systemtest/testspezi/S53_systemtest_spezi.md
 */

test.describe('S53 — Legacy-URL-Weiterleitungen', () => {

  // T1 — Individual Redirect (gueltig)
  test('S53 — Individual-Redirect liefert 301 mit Location', async ({ request }) => {
    const response = await request.get('/individual.php?ged=demo&pid=X1030', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(301);
    const location = response.headers()['location'];
    expect(location).toBeDefined();
    expect(location).toContain('/tree/demo/individual/X1030');
  });

  // T2 — Individual Redirect (ungueltige XREF)
  test('S53 — Individual-Redirect mit ungueltiger XREF liefert 410', async ({ request }) => {
    const response = await request.get('/individual.php?ged=demo&pid=NONEXIST999', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(410);
  });

  // T3 — Family Redirect (gueltig)
  test('S53 — Family-Redirect liefert 301 mit Location', async ({ request }) => {
    const response = await request.get('/family.php?ged=demo&famid=f1', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(301);
    const location = response.headers()['location'];
    expect(location).toBeDefined();
    expect(location).toContain('/family/f1');
  });

  // T4 — Source Redirect (gueltig)
  test('S53 — Source-Redirect liefert 301 mit Location', async ({ request }) => {
    const response = await request.get('/source.php?ged=demo&sid=X1102', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(301);
    const location = response.headers()['location'];
    expect(location).toBeDefined();
    expect(location).toContain('/source/X1102');
  });

  // T5 — Calendar Redirect (gueltig)
  test('S53 — Calendar-Redirect liefert 301', async ({ request }) => {
    const response = await request.get('/calendar.php?ged=demo&view=month', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(301);
    const location = response.headers()['location'];
    expect(location).toBeDefined();
    expect(location).toContain('/calendar/');
  });

  // T6 — Pedigree Redirect (gueltig)
  test('S53 — Pedigree-Redirect liefert 301', async ({ request }) => {
    const response = await request.get('/pedigree.php?ged=demo&rootid=X1030', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(301);
    const location = response.headers()['location'];
    expect(location).toBeDefined();
  });

  // T7 — Tree nicht gefunden
  test('S53 — ungueltiger Baumname liefert 410', async ({ request }) => {
    const response = await request.get('/individual.php?ged=INVALID_TREE_NAME&pid=X1030', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(410);
  });

  // T8 — Canonical Link Header
  test('S53 — Redirect-Response enthaelt Link-Header mit rel=canonical', async ({ request }) => {
    const response = await request.get('/individual.php?ged=demo&pid=X1030', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(301);
    const linkHeader = response.headers()['link'];
    expect(linkHeader).toBeDefined();
    expect(linkHeader).toContain('rel="canonical"');
  });
});
