// SPDX-License-Identifier: AGPL-3.0-or-later

import { defineConfig } from '@playwright/test';

/**
 * Teststufe 3 — Systemtest (Playwright)
 * Browser: Chromium only, headless
 * Basis-URL: http://webtrees:80 (Container-Netzwerk)
 *
 * @see docs/testing-bigpicture.md Teststufe 3
 */
export default defineConfig({
  testDir: './tests',
  testIgnore: '**/security/**',
  timeout: 30_000,
  retries: 1,
  workers: 1, // Sequentiell — shared State (Login-Session)
  reporter: [
    ['html', { outputFolder: '/artifacts/layer4/playwright-report', open: 'never' }],
    ['json', { outputFile: '/artifacts/layer4/playwright-results.json' }],
    ['line'],
  ],
  use: {
    baseURL: process.env.BASE_URL || 'http://webtrees:80',
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
    headless: true,
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
  outputDir: '/artifacts/layer4/test-results',
});
