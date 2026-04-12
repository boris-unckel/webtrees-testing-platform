// SPDX-License-Identifier: AGPL-3.0-or-later

import { Browser } from '@playwright/test';

/**
 * Theme-Switching-Helper fuer Systemtests.
 *
 * Erstellt einen temporaeren Browser-Context mit gespeichertem Admin-Login
 * (storageState) und setzt das Theme via GET-Parameter. webtrees persistiert
 * das Theme in den User-Preferences, sodass nachfolgende Requests im selben
 * Login das Theme beibehalten.
 *
 * @see docs/tds_conditions_ref.md AP 5c-1
 */

export const themes = ['webtrees', 'clouds', 'colors', 'fab', 'xenea'] as const;

export async function switchTheme(browser: Browser, theme: string): Promise<void> {
  const ctx = await browser.newContext({
    baseURL: process.env.BASE_URL || 'http://webtrees:80',
    storageState: '/tmp/.auth/admin.json',
  });
  const page = await ctx.newPage();
  await page.goto(`/tree/demo?theme=${theme}`);
  await page.waitForLoadState('networkidle');
  await ctx.close();
}
