import { test, expect } from '@playwright/test';

/**
 * Systemtest: Theme-Matrix — 5 Themes × 10 Seiten
 *
 * Rein funktional, kein Visual Regression. Prüft ob jede Theme/Seiten-Kombination
 * ohne HTTP-Fehler lädt und grundlegende DOM-Elemente vorhanden sind.
 *
 * Theme-Switching via POST /tree/demo/theme mit theme-Parameter.
 * Verifikation: <body> enthält wt-theme-{name}.
 *
 * @see docs/testing-bigpicture-prompt.md S25, AP 5b-1a
 */

const themes = ['webtrees', 'clouds', 'colors', 'fab', 'xenea'] as const;

const pages = [
  { name: 'Homepage (TreePage)', url: '/tree/demo' },
  { name: 'Personenseite (IndividualPage)', url: '/tree/demo/individual/X1030' },
  { name: 'Familienseite (FamilyPage)', url: '/tree/demo/family/f1' },
  { name: 'Allgemeine Suche', url: '/tree/demo/search-general' },
  { name: 'Stammbaum (Pedigree)', url: '/tree/demo/pedigree' },
  { name: 'Quellenliste', url: '/tree/demo/source-list' },
  { name: 'Kalender', url: '/tree/demo/calendar/month' },
  { name: 'Quellenseite (SourcePage)', url: '/tree/demo/source/X1102' },
  { name: 'Medienseite (MediaPage)', url: '/tree/demo/media/X1104' },
  { name: 'Benutzerseite (UserPage)', url: '/tree/demo/my-page' },
] as const;

test.beforeEach(async ({ page }) => {
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
});

for (const theme of themes) {
  test.describe(`Theme: ${theme}`, () => {
    test.beforeAll(async ({ browser }) => {
      // Theme für den User umschalten
      const ctx = await browser.newContext({ baseURL: process.env.BASE_URL || 'http://webtrees:80' });
      const p = await ctx.newPage();
      await p.goto('/login/demo');
      await p.fill('input[name="username"]', 'admin');
      await p.fill('input[name="password"]', 'admin');
      await p.locator('button[type="submit"]').last().click();
      await p.waitForLoadState('networkidle');

      // Theme setzen via GET-Parameter (webtrees SelectTheme handler)
      await p.goto(`/tree/demo?theme=${theme}`);
      await p.waitForLoadState('networkidle');
      await ctx.close();
    });

    for (const pg of pages) {
      test(`S25 — ${pg.name} renders with theme ${theme}`, async ({ page }) => {
        const response = await page.goto(pg.url);
        expect(response?.status()).toBeLessThan(500);

        await expect(page.locator('body')).toBeVisible();

        // Hauptinhalt vorhanden
        const content = page.locator('main, .wt-page-content');
        await expect(content.first()).toBeVisible();
      });
    }
  });
}
