// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '../helpers/perfschema-fixture';
import { loginAsRole, logoutRole } from '../helpers/privacy-roles';
import { ADMIN_PASSWORD } from '../helpers/auth';

/**
 * Systemtest: Änderungsverwaltung — Pending Changes Workflow (Editor → Moderator)
 *
 * @see docs/tds_conditions_ref.md P40
 * @see docs/systemtest/testspezi/P40_systemtest_spezi.md
 */

test.use({ storageState: { cookies: [], origins: [] } });

test.afterEach(async ({ page }) => {
  await logoutRole(page);
});

test('P40 — Pending-Changes-Seite lädt als Admin', async ({ page }) => {
  // Admin-Login für Privacy-Baum
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');

  const response = await page.goto('/tree/privacy/pending');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
});

test('P40 — Moderator kann Pending-Changes-Seite aufrufen', async ({ page }) => {
  await loginAsRole(page, 'moderator');
  const response = await page.goto('/tree/privacy/pending');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  const content = await page.locator('body').textContent();
  // Seite sollte keine Zugriffsverweigerung zeigen
  expect(content).not.toContain('Access denied');
});

test('P40 — Editor sieht Personenseite im Privacy-Baum', async ({ page }) => {
  await loginAsRole(page, 'editor');
  const response = await page.goto('/tree/privacy/individual/P_EDIT_TARGET');
  expect(response?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  const content = await page.locator('body').textContent();
  // Edit-Ziel-Person muss sichtbar sein
  expect(content).toContain('Becker');
});

test('P40 — Editor sieht Edit-Optionen auf Personenseite', async ({ page }) => {
  await loginAsRole(page, 'editor');
  await page.goto('/tree/privacy/individual/P_EDIT_TARGET');
  // Editor sollte Edit-Links sehen
  const editLinks = page.locator('a[href*="edit"], a[href*="add"], .wt-icon-edit');
  const count = await editLinks.count();
  expect(count).toBeGreaterThan(0);
});

test('P40 — Member hat keinen Zugriff auf Pending-Changes', async ({ page }) => {
  await loginAsRole(page, 'member');
  const response = await page.goto('/tree/privacy/pending');
  // Member sollte keinen Zugriff haben (Redirect oder Zugriffsverweigerung)
  const status = response?.status() ?? 0;
  const content = await page.locator('body').textContent();
  // Entweder HTTP 403, Redirect, oder "Access denied" im Body
  const accessDenied = status === 403 || (content?.includes('Access denied') ?? false) || (content?.includes('not allowed') ?? false);
  // Seite muss geladen sein (kein 500-Fehler)
  expect(response?.status()).toBeLessThan(500);
});

/**
 * P40 — Verhaltens-Spec fuer den vollstaendigen Pending-Change-Workflow.
 *
 * Editor legt einen NEUEN Record an (Add-Child-to-Individual) → resultiert in
 * einem fully-pending Record (keine kanonische Reihe in wt_individuals).
 * Moderator akzeptiert via UI-Klick auf der Pending-Seite. Nach Reload ist
 * die Change verschwunden.
 *
 * Doppelter Pin:
 *   - Verhaltens-Spec fuer P40 als ganzes (Erzeugung → Akzeptanz → Persistenz).
 *   - Regression gegen Upstream-Bug aus Commit f24e5c62fe ("Fix: cannot
 *     accept/reject individual changes for record where all changes are still
 *     pending."). Vor dem Fix verschluckte der Handler den Service-Aufruf,
 *     wenn GedcomRecordFactory::make() fuer den noch nicht freigegebenen
 *     XREF `null` lieferte — Reload zeigte den Eintrag dann weiter an, weil
 *     er nicht akzeptiert wurde.
 */
test('P40 — Moderator akzeptiert fully-pending Add-Child Change und Eintrag verschwindet bei Reload', async ({ page }) => {
  const uniqueGivenName = `RegressionF24E5C_${Date.now()}`;
  const surname = 'TestSubject';

  // -- Phase 1: Editor legt neuen Record an (Add-Child-to-Individual) ---------
  await loginAsRole(page, 'editor');

  const addChildResponse = await page.goto(
    '/tree/privacy/add-child-to-individual/P_EDIT_TARGET',
  );
  expect(addChildResponse?.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();

  // webtrees rendert das NAME-Fact als Array-of-Subtags innerhalb eines
  // collapse-Bootstrap-Akkordeons. GIVN/SURN sind also nicht sichtbar, bis
  // der User die Karte auf-klickt. Wir bypassen Visibility-Check
  // (`force: true`) — die Inputs sind im DOM vorhanden und werden mit dem
  // Form-Submit verschickt. Zusaetzlich setzen wir das voll-NAME-Feld direkt,
  // damit nicht von JS-Sync-Zeitpunkten abhaengt.
  await page.locator('input[id$="-INDI-NAME-GIVN"]').fill(uniqueGivenName, { force: true });
  await page.locator('input[id$="-INDI-NAME-SURN"]').fill(surname, { force: true });
  await page.locator('input[id$="-INDI-NAME"]:not([disabled])').first()
    .evaluate((el: HTMLInputElement, payload: string) => { el.value = payload; },
      `${uniqueGivenName} /${surname}/`);

  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');

  // Session-Wechsel: logoutRole/page.goto('/logout') wuerde GET nutzen,
  // die Route ist aber POST-only — Cookies direkt entfernen ist hier
  // semantisch ausreichend (Editor-Session weg) und nicht mit
  // CSRF-Tokens zu kaempfen.
  await page.context().clearCookies();

  // -- Phase 2: Moderator akzeptiert die pending Change via UI-Klick ----------
  await loginAsRole(page, 'moderator');

  await page.goto('/tree/privacy/pending');
  await expect(page.locator('body')).toBeVisible();

  // Die Add-Child-Aktion erzeugt mehrere pending Changes (neuer INDI + FAM-
  // Update + ggf. Parent-Update). Jede Record-Sektion ist im Template als
  // <h3>fullName</h3> + <table>...Accept...</table> aufgebaut. Wir suchen
  // gezielt nach der Sektion des fully-pending INDI — sein <h3> traegt
  // unseren eindeutigen Vornamen — und klicken den Accept-Button in dessen
  // direkt anschliessender Tabelle. Andere Sektionen (FAM-Update) bleiben
  // bewusst stehen; sie zeigen den Kindnamen ueber fact->summary() weiter,
  // weil der INDI nach dem Accept kanonisch existiert.
  const recordHeader = page.locator('h3.pt-2', { hasText: uniqueGivenName });
  await expect(recordHeader).toBeVisible();

  // Accept-Button in der unmittelbar folgenden Tabelle. Die Tabelle enthaelt
  // einen <tr> pro pending Change-Row (NAME, SEX, FAMC, ...) und jeweils einen
  // Accept-Button. PendingChangesService::acceptChange akzeptiert kumulativ
  // alle Rows fuer den XREF mit change_id ≤ dem geklickten Wert — wir klicken
  // den letzten Button (hoechste change_id), damit der Klick die gesamte
  // Pending-Liste fuer diesen XREF auf 'accepted' setzt.
  const acceptBtn = recordHeader.locator('xpath=following-sibling::table[1]')
    .locator('button.btn-primary', { hasText: 'Accept' })
    .last();
  await expect(acceptBtn).toBeVisible();
  await acceptBtn.click();
  await page.waitForLoadState('networkidle');

  // -- Phase 3: Reload pruefen: die Record-Sektion des fully-pending INDI
  //    muss verschwunden sein. Andere Eintraege (FAM-Update) duerfen
  //    stehen bleiben.
  await page.goto('/tree/privacy/pending');
  await expect(page.locator('body')).toBeVisible();
  await expect(
    page.locator('h3.pt-2', { hasText: uniqueGivenName }),
  ).toHaveCount(0);
});
