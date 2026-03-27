import { defineConfig } from '@playwright/test';

/**
 * Performanztest — Playwright-Metrics + Baseline-Vergleich
 *
 * Misst Ladezeiten und vergleicht mit gespeicherten Baselines.
 * Schwellwert: +20% Ladezeit = Warnung
 *
 * @see docs/testing-bigpicture-prompt.md Performanztest
 */
export default defineConfig({
  testDir: './tests',
  timeout: 60_000,
  retries: 0, // Keine Retries bei Performance-Tests
  workers: 1, // Sequentiell für konsistente Messungen
  reporter: [
    ['html', { outputFolder: '/artifacts/layer5/playwright-report', open: 'never' }],
    ['json', { outputFile: '/artifacts/layer5/performance-results.json' }],
    ['list'],
  ],
  use: {
    baseURL: process.env.BASE_URL || 'http://webtrees:80',
    headless: true,
  },
  projects: [
    {
      name: 'performance',
      use: { browserName: 'chromium' },
    },
  ],
  outputDir: '/artifacts/layer5/test-results',
});
