// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Performanztest: Personensuche
 *
 * @see docs/testing-bigpicture.md Performanztest
 */

const BASELINE_DIR = '/tests/performance/baselines';
const ARTIFACTS_DIR = '/artifacts/layer5';
const THRESHOLD = 1.2;
const RUNS = 3;

test.describe('Performance — Search', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login/demo');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'admin');
    await page.locator('button[type="submit"]').last().click();
    await page.waitForLoadState('networkidle');
  });

  test('search load time within threshold', async ({ page }) => {
    const loadTimes: number[] = [];

    for (let i = 0; i < RUNS; i++) {
      const start = Date.now();
      await page.goto('/tree/demo/search-general?query=Dombrink');
      await page.waitForLoadState('networkidle');
      const elapsed = Date.now() - start;
      loadTimes.push(elapsed);
    }

    const avg = loadTimes.reduce((a, b) => a + b, 0) / loadTimes.length;

    const result = {
      scenario: 'search',
      loadTimeMs: loadTimes,
      avgLoadTimeMs: Math.round(avg),
      timestamp: new Date().toISOString(),
    };

    fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
    fs.writeFileSync(
      path.join(ARTIFACTS_DIR, 'perf-search.json'),
      JSON.stringify(result, null, 2)
    );

    const baselineFile = path.join(BASELINE_DIR, 'search.json');
    if (fs.existsSync(baselineFile)) {
      const baseline = JSON.parse(fs.readFileSync(baselineFile, 'utf-8'));
      const ratio = avg / baseline.avgLoadTimeMs;
      console.log(`Search: ${Math.round(avg)}ms (Baseline: ${baseline.avgLoadTimeMs}ms, Ratio: ${ratio.toFixed(2)})`);
      expect(ratio).toBeLessThan(THRESHOLD);
    } else {
      console.log(`Search: ${Math.round(avg)}ms (keine Baseline)`);
    }
  });
});
