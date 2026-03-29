// SPDX-License-Identifier: AGPL-3.0-or-later

import { defineConfig } from '@playwright/test';

/**
 * Sicherheitstests — Playwright-Konfiguration
 * Läuft gegen den Security-Container (Distribution + Wizard)
 *
 * Zwei Phasen:
 *   1. "setup" — Wizard-Durchlauf (muss zuerst laufen)
 *   2. "security-tests" — alle HTTP-/Header-/Media-Tests (nach Wizard)
 *
 * @see docs/security_plan.md Abschnitt 6.5
 */
export default defineConfig({
  testDir: './tests/security',
  timeout: 60_000,
  retries: 0,
  workers: 1,
  reporter: [
    ['html', { outputFolder: '/artifacts/security/playwright-report', open: 'never' }],
    ['list'],
  ],
  use: {
    baseURL: process.env.SECURITY_BASE_URL || 'http://webtrees-security:80',
    screenshot: 'only-on-failure',
    trace: 'on',
    headless: true,
  },
  projects: [
    {
      name: 'setup',
      testMatch: 'wizard-setup.spec.ts',
      use: { browserName: 'chromium' },
    },
    {
      name: 'security-tests',
      testIgnore: 'wizard-setup.spec.ts',
      dependencies: ['setup'],
      use: { browserName: 'chromium' },
    },
  ],
  outputDir: '/artifacts/security/test-results',
});
