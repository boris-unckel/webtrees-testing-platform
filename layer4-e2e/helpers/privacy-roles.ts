// SPDX-License-Identifier: AGPL-3.0-or-later

import { Page } from '@playwright/test';
import { TEST_USER_PASSWORD } from './auth';

/**
 * Rollen-Login-Helper fuer Privacy-Systemtests.
 *
 * Die User werden in setup-webtrees.sh angelegt:
 * - test-member (Mitglied)
 * - test-editor (Bearbeiter)
 * - test-moderator (Moderator)
 * - test-manager (Verwalter)
 * - test-relationship (Mitglied mit Relationship-Privacy)
 *
 * Passwort: WEBTREES_TEST_USER_PASSWORD (generiert durch scripts/generate-passwords.sh)
 * Besucher (visitor): kein Login, Abmeldung falls noetig.
 *
 */

export const privacyRoles = ['visitor', 'member', 'editor', 'moderator', 'manager'] as const;
export type PrivacyRole = (typeof privacyRoles)[number];

/**
 * Meldet sich mit der angegebenen Rolle am Privacy-Baum an.
 *
 * Fuer 'visitor' wird eine Abmeldung durchgefuehrt (bzw. kein Login).
 */
export async function loginAsRole(page: Page, role: PrivacyRole): Promise<void> {
  if (role === 'visitor') {
    await logoutRole(page);
    return;
  }

  await page.goto('/login/privacy');
  await page.fill('input[name="username"]', `test-${role}`);
  await page.fill('input[name="password"]', TEST_USER_PASSWORD);
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
}

/**
 * Meldet sich mit dem Relationship-Privacy-User an.
 * Dieser User hat PREF_TREE_ACCOUNT_XREF=P_REL_USER und PREF_TREE_PATH_LENGTH=2.
 */
export async function loginAsRelationshipUser(page: Page): Promise<void> {
  await page.goto('/login/privacy');
  await page.fill('input[name="username"]', 'test-relationship');
  await page.fill('input[name="password"]', TEST_USER_PASSWORD);
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
}

/**
 * Meldet den aktuellen User ab.
 */
export async function logoutRole(page: Page): Promise<void> {
  await page.goto('/logout');
  await page.waitForLoadState('networkidle');
}
