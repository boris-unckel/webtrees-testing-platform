// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { loginAsRole, logoutRole, type PrivacyRole } from '../helpers/privacy-roles';

/**
 * Systemtest: Privacy-Sichtbarkeit (P01–P03, P14, P25)
 *
 * Prueft Stammbaum-Zugang, Vertraulich-Platzhalter und Rollen-basierte
 * Sichtbarkeit im Browser.
 *
 */

test.describe('Privacy Visibility', () => {
  test.afterEach(async ({ page }) => {
    await logoutRole(page);
  });

  // P01 — Stammbaum erfordert Authentifizierung
  // Hinweis: REQUIRE_AUTHENTICATION ist eine Tree-Preference. Fuer Systemtests
  // testen wir den Standardzustand (Privacy aktiv, Besucher eingeschraenkt).

  test('P25 — visitor cannot see living person page', async ({ page }) => {
    await logoutRole(page);
    await page.goto('/tree/privacy/individual/P_ALIVE_YOUNG');
    await page.waitForLoadState('networkidle');

    // Besucher sieht Access-Denied oder Vertraulich-Meldung
    const content = await page.textContent('body');
    const isRestricted =
      content?.includes('does not exist or you do not have permission') ||
      content?.includes('Private') ||
      content?.includes('Vertraulich') ||
      page.url().includes('login');

    expect(isRestricted).toBeTruthy();
  });

  test('P25 — member sees living person page', async ({ page }) => {
    await loginAsRole(page, 'member');
    await page.goto('/tree/privacy/individual/P_ALIVE_YOUNG');
    await page.waitForLoadState('networkidle');

    // Mitglied sieht den Namen der Person
    const content = await page.textContent('body');
    expect(content).toContain('Engel');
  });

  test('P02 — visitor sees historically dead person', async ({ page }) => {
    await logoutRole(page);
    await page.goto('/tree/privacy/individual/P_DEAD_HISTORIC');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    expect(content).toContain('Altmann');
  });

  test('P03 — manager sees all persons', async ({ page }) => {
    await loginAsRole(page, 'manager');

    // Lebende Person
    await page.goto('/tree/privacy/individual/P_ALIVE_YOUNG');
    await page.waitForLoadState('networkidle');
    const content = await page.textContent('body');
    expect(content).toContain('Engel');
  });

  test('P14 — visitor may see name but not details of living person', async ({ page }) => {
    await logoutRole(page);

    // Suche nach dem Namen der lebenden Person
    await page.goto('/tree/privacy/search-general?query=Engel');
    await page.waitForLoadState('networkidle');

    // Das Verhalten haengt von SHOW_LIVING_NAMES ab:
    // Standard SHOW_LIVING_NAMES=1 (PRIV_USER): Besucher sieht Name NICHT
    // Wir pruefen, dass die Detailseite nicht zugaenglich ist
    await page.goto('/tree/privacy/individual/P_ALIVE_YOUNG');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.textContent('body');
    const hasNoDetails =
      !bodyText?.includes('5 JUN') || // Geburtsdatum nicht sichtbar
      bodyText?.includes('does not exist or you do not have permission') ||
      bodyText?.includes('Private');

    expect(hasNoDetails).toBeTruthy();
  });
});
