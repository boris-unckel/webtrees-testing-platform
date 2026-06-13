// SPDX-License-Identifier: AGPL-3.0-or-later

import { chromium, type FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import * as fs from 'fs';

/**
 * Playwright globalSetup — wird einmal vor allen Tests ausgefuehrt.
 *
 * 1. Pflicht-Fixture-Trees pruefen (demo/muster/privacy) — fail-fast mit klarer
 *    Meldung, falls "make setup" nicht lief (verhindert >150 kryptische Folgefehler)
 * 2. Rate-Limits in der DB loeschen (verhindert HTTP 429 bei vielen Tests)
 * 3. Admin-Login durchfuehren und storageState speichern
 *
 * @see docs/tds_conditions_ref.md AP 5c-1
 */
async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL = process.env.BASE_URL || 'http://webtrees:80';
  const adminPassword = process.env.WEBTREES_ADMIN_PASSWORD;
  if (!adminPassword) {
    throw new Error('WEBTREES_ADMIN_PASSWORD nicht gesetzt — wurde "make setup" ausgefuehrt?');
  }

  const host = process.env.MYSQL_HOST ?? 'mysql';
  const pwd = process.env.MYSQL_ROOT_PASSWORD ?? '';
  const db = process.env.MYSQL_DATABASE ?? 'webtrees_test';

  // Pflicht-Fixture-Trees pruefen, BEVOR Tests laufen. Diese Baeume legt
  // "make setup" an (demo/muster/privacy); "make test-e2e" selbst tut das NICHT.
  // Fehlen sie, scheitern >150 Tests mit kryptischen Assertion-Fehlern, weil
  // /tree/demo/... und /tree/privacy/... ins Leere laufen (Tree nicht gefunden).
  const requiredTrees = ['demo', 'muster', 'privacy'];
  let existingTrees: string[] | null = null;
  try {
    const out = execSync(
      `mysql -h "${host}" -u root -p"${pwd}" "${db}" -N -B -e "SELECT gedcom_name FROM wt_gedcom;"`,
      { stdio: ['pipe', 'pipe', 'pipe'] },
    ).toString();
    existingTrees = out.split('\n').map((s) => s.trim()).filter((s) => s !== '');
  } catch {
    // DB nicht erreichbar / mysql-Client fehlt — analog Rate-Limit-Clearing nicht kritisch.
    console.warn('globalSetup: Fixture-Tree-Pruefung uebersprungen (DB-Query fehlgeschlagen).');
  }
  if (existingTrees !== null) {
    const present = existingTrees;
    const missingTrees = requiredTrees.filter((t) => !present.includes(t));
    if (missingTrees.length > 0) {
      throw new Error(
        `globalSetup: E2E-Fixture-Trees fehlen in DB '${db}': [${missingTrees.join(', ')}]. ` +
          `Vorhanden: [${present.join(', ') || 'keine'}]. Die Specs navigieren auf /tree/demo/... ` +
          `und /tree/privacy/... — ohne diese Baeume scheitern >150 Tests. Behebung: "make setup" ` +
          `ausfuehren (legt demo/muster/privacy idempotent an), dann E2E wiederholen.`,
      );
    }
  }

  // Rate-Limits loeschen (Site- und User-Ebene)
  try {
    execSync(
      `mysql -h "${host}" -u root -p"${pwd}" "${db}" -e ` +
      `"DELETE FROM wt_site_setting WHERE setting_name LIKE 'rate-limit-%'; ` +
      `DELETE FROM wt_user_setting WHERE setting_name LIKE 'rate-limit-%';"`,
      { stdio: 'pipe' }
    );
  } catch {
    // Rate-Limit-Clearing ist optional — Tests funktionieren auch ohne
    console.warn('Rate-Limit-Clearing fehlgeschlagen (nicht kritisch)');
  }

  // storageState-Verzeichnis anlegen
  const stateDir = '/tmp/.auth';
  fs.mkdirSync(stateDir, { recursive: true });

  // Admin-Login und storageState speichern
  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();

  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', adminPassword);
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');

  await context.storageState({ path: `${stateDir}/admin.json` });
  await browser.close();
}

export default globalSetup;
