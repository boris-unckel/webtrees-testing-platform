import { Browser } from '@playwright/test';

/**
 * Theme-Switching-Helper für Systemtests.
 *
 * Erstellt einen temporären Browser-Context, loggt sich ein und setzt das Theme
 * via GET-Parameter. webtrees persistiert das Theme in den User-Preferences,
 * sodass nachfolgende Requests im selben Login das Theme beibehalten.
 *
 * @see docs/testing-bigpicture.md AP 5c-1
 */

export const themes = ['webtrees', 'clouds', 'colors', 'fab', 'xenea'] as const;

export async function switchTheme(browser: Browser, theme: string): Promise<void> {
  const ctx = await browser.newContext({
    baseURL: process.env.BASE_URL || 'http://webtrees:80',
  });
  const page = await ctx.newPage();
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
  await page.goto(`/tree/demo?theme=${theme}`);
  await page.waitForLoadState('networkidle');
  await ctx.close();
}
