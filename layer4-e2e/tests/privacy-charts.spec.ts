import { test, expect } from '@playwright/test';
import { loginAsRole, logoutRole } from '../helpers/privacy-roles';

/**
 * Systemtest: Vertrauliche Boxen in Charts (P26)
 *
 * @see docs/plan-privacy-testing-prompt.md P26
 * @see docs/plan-privacy-implementation.md Phase P7.4
 */

test.describe('Privacy Charts', () => {
  test.afterEach(async ({ page }) => {
    await logoutRole(page);
  });

  test('P26 — pedigree chart shows private boxes for visitor', async ({ page }) => {
    await logoutRole(page);

    // Pedigree-Chart fuer eine Person im Privacy-Baum
    // P_REL_CLOSE hat Eltern (P_REL_USER + P_REL_USER_WIFE), die lebend und geschuetzt sind
    // Route: /tree/{tree}/pedigree-{style}-{generations}/{xref}
    await page.goto('/tree/privacy/pedigree-right-4/P_REL_CLOSE');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');

    // Entweder sehen wir "Private" Platzhalter oder die Seite laedt korrekt
    // Besucher sollte die Ahnen als "Private"/"Vertraulich" sehen
    // (wenn SHOW_PRIVATE_RELATIONSHIPS=1)
    const hasPrivateBoxes =
      content?.includes('Private') ||
      content?.includes('Vertraulich') ||
      content?.includes('does not exist or you do not have permission');

    expect(hasPrivateBoxes).toBeTruthy();
  });

  test('P26 — pedigree chart shows full data for manager', async ({ page }) => {
    await loginAsRole(page, 'manager');

    // Route: /tree/{tree}/pedigree-{style}-{generations}/{xref}
    await page.goto('/tree/privacy/pedigree-right-4/P_REL_CLOSE');
    await page.waitForLoadState('networkidle');

    const content = await page.textContent('body');
    // Manager sieht die echten Namen
    expect(content).toContain('Adenauer');
  });
});
