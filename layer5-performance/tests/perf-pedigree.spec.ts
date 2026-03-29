// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Performanztest: Stammbaum-Ansicht (Pedigree Chart)
 *
 * @see docs/testing-bigpicture.md Performanztest, S14
 */

const BASELINE_DIR = '/tests/performance/baselines';
const ARTIFACTS_DIR = '/artifacts/layer5';
const THRESHOLD = 1.2;
const RUNS = 3;

test.describe('Performance — Pedigree Chart', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login/demo');
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'admin');
    await page.locator('button[type="submit"]').last().click();
    await page.waitForLoadState('networkidle');
  });

  test('pedigree chart load time within threshold', async ({ page }) => {
    const loadTimes: number[] = [];

    for (let i = 0; i < RUNS; i++) {
      const start = Date.now();
      await page.goto('/tree/demo/pedigree');
      await page.waitForLoadState('networkidle');
      const elapsed = Date.now() - start;
      loadTimes.push(elapsed);
    }

    const avg = loadTimes.reduce((a, b) => a + b, 0) / loadTimes.length;

    const result = {
      scenario: 'pedigree',
      loadTimeMs: loadTimes,
      avgLoadTimeMs: Math.round(avg),
      timestamp: new Date().toISOString(),
    };

    fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
    fs.writeFileSync(
      path.join(ARTIFACTS_DIR, 'perf-pedigree.json'),
      JSON.stringify(result, null, 2)
    );

    const baselineFile = path.join(BASELINE_DIR, 'pedigree.json');
    if (fs.existsSync(baselineFile)) {
      const baseline = JSON.parse(fs.readFileSync(baselineFile, 'utf-8'));
      const ratio = avg / baseline.avgLoadTimeMs;
      console.log(`Pedigree: ${Math.round(avg)}ms (Baseline: ${baseline.avgLoadTimeMs}ms, Ratio: ${ratio.toFixed(2)})`);
      expect(ratio).toBeLessThan(THRESHOLD);
    } else {
      console.log(`Pedigree: ${Math.round(avg)}ms (keine Baseline)`);
    }
  });
});
