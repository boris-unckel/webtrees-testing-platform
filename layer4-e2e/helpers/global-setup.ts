// SPDX-License-Identifier: AGPL-3.0-or-later

import { chromium, type FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import * as fs from 'fs';

/**
 * Playwright globalSetup — wird einmal vor allen Tests ausgefuehrt.
 *
 * 1. Rate-Limits in der DB loeschen (verhindert HTTP 429 bei vielen Tests)
 * 2. Admin-Login durchfuehren und storageState speichern
 *
 * @see docs/tds_conditions_ref.md AP 5c-1
 */
async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL = process.env.BASE_URL || 'http://webtrees:80';
  const adminPassword = process.env.WEBTREES_ADMIN_PASSWORD;
  if (!adminPassword) {
    throw new Error('WEBTREES_ADMIN_PASSWORD nicht gesetzt — wurde "make setup" ausgefuehrt?');
  }

  // Rate-Limits loeschen (Site- und User-Ebene)
  const host = process.env.MYSQL_HOST ?? 'mysql';
  const pwd = process.env.MYSQL_ROOT_PASSWORD ?? '';
  const db = process.env.MYSQL_DATABASE ?? 'webtrees_test';
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
