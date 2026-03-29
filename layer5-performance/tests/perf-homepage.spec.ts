// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Performanztest: Startseite
 *
 * Misst Ladezeit der Startseite und vergleicht mit Baseline.
 * Schwellwert: +20% = Warnung
 *
 * @see docs/testing-bigpicture.md Performanztest
 */

const BASELINE_DIR = '/tests/performance/baselines';
const ARTIFACTS_DIR = '/artifacts/layer5';
const THRESHOLD = 1.2; // +20%
const RUNS = 3; // Mehrfachmessung für Stabilität

interface PerfResult {
  scenario: string;
  loadTimeMs: number[];
  avgLoadTimeMs: number;
  timestamp: string;
}

test.describe('Performance — Homepage', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login/demo');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'admin');
    await page.locator('button[type="submit"]').last().click();
    await page.waitForLoadState('networkidle');
  });

  test('homepage load time within threshold', async ({ page }) => {
    const loadTimes: number[] = [];

    for (let i = 0; i < RUNS; i++) {
      const start = Date.now();
      await page.goto('/tree/demo');
      await page.waitForLoadState('networkidle');
      const elapsed = Date.now() - start;
      loadTimes.push(elapsed);
    }

    const avg = loadTimes.reduce((a, b) => a + b, 0) / loadTimes.length;

    const result: PerfResult = {
      scenario: 'homepage',
      loadTimeMs: loadTimes,
      avgLoadTimeMs: Math.round(avg),
      timestamp: new Date().toISOString(),
    };

    // Ergebnis speichern
    fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
    fs.writeFileSync(
      path.join(ARTIFACTS_DIR, 'perf-homepage.json'),
      JSON.stringify(result, null, 2)
    );

    // Baseline-Vergleich
    const baselineFile = path.join(BASELINE_DIR, 'homepage.json');
    if (fs.existsSync(baselineFile)) {
      const baseline: PerfResult = JSON.parse(fs.readFileSync(baselineFile, 'utf-8'));
      const ratio = avg / baseline.avgLoadTimeMs;

      console.log(`Homepage: ${Math.round(avg)}ms (Baseline: ${baseline.avgLoadTimeMs}ms, Ratio: ${ratio.toFixed(2)})`);

      expect(ratio).toBeLessThan(THRESHOLD);
    } else {
      console.log(`Homepage: ${Math.round(avg)}ms (keine Baseline vorhanden — erste Messung)`);
    }
  });
});
