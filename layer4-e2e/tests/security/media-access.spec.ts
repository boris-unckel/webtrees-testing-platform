// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../../helpers/otel-fixture';

/**
 * Sicherheitstest: Media-Zugriffskontrolle
 * Vorbedingung: Wizard wurde durchlaufen (SEC-WZ02 bestanden)
 *
 * @see docs/security_plan.md Abschnitt 7.2.5
 */

// @see SEC-M01
test('SEC-M01: Direkter Media-Zugriff blockiert', async ({ request }) => {
  // data/media/ existiert ggf. noch nicht als Unterverzeichnis,
  // aber .htaccess blockiert den gesamten data/-Pfad
  const response = await request.get('/data/media/test.jpg');
  expect(response.status()).toBe(403);
});

// @see SEC-M02
test('SEC-M02: Media-Route ohne Auth → Redirect oder 403', async ({ request }) => {
  // Versuche auf eine Media-Route als Visitor (nicht eingeloggt) zuzugreifen.
  // Da nach dem Wizard noch kein Tree mit Media existiert, erwarten wir
  // einen Redirect zu Login (302) oder eine Fehlerseite (403/404).
  const response = await request.get('/tree/tree1/media-file/M1', {
    maxRedirects: 0,
  });
  // Nicht 200 mit Dateiinhalt — Redirect oder Fehler
  expect([302, 403, 404]).toContain(response.status());
});

// @see SEC-M03
test('SEC-M03: Media-Route mit Auth → funktioniert (oder 404 ohne Media)', async ({ request }) => {
  // Login als Admin (Wizard-Admin-Account)
  const loginResponse = await request.post('/index.php?route=%2Flogin', {
    form: {
      username: 'secadmin',
      password: 'sectest123',
      url: '/',
    },
  });

  // Nach Login: Media-Route testen
  // Da kein Tree mit Media existiert, ist 404 akzeptabel
  // Wichtig: kein 403 (Auth funktioniert)
  const mediaResponse = await request.get('/tree/tree1/media-file/M1');
  // 200 (Media vorhanden) oder 404 (kein Tree/Media) — beides OK
  // Nur 403 wäre problematisch (Auth-Fehler trotz Login)
  expect(mediaResponse.status()).not.toBe(403);
});
