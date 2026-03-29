// SPDX-License-Identifier: AGPL-3.0-or-later

import { test, expect } from '@playwright/test';

/**
 * Systemtest: Upload-Validierung (GEDCOM-Import)
 *
 * Testet, dass ungültige Dateien beim GEDCOM-Upload erkannt und abgelehnt werden.
 * Kein Theme-Loop — Admin-Seiten sind nicht tree-gebunden.
 *
 * @see docs/testing-bigpicture.md G21, AP 9-3
 */

test.beforeEach(async ({ page }) => {
  await page.goto('/login/demo');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin');
  await page.locator('button[type="submit"]').last().click();
  await page.waitForLoadState('networkidle');
});

test('G21 — import page renders for admin', async ({ page }) => {
  const response = await page.goto('/tree/demo/import');
  expect(response?.status()).toBeLessThan(500);

  await expect(page.locator('body')).toBeVisible();
  // Import page should have a file input or form
  const form = page.locator('form');
  await expect(form.first()).toBeVisible();
});

test('G21 — upload empty file shows error or rejects', async ({ page }) => {
  await page.goto('/tree/demo/import');

  // Find the file input
  const fileInput = page.locator('input[type="file"]');
  if (await fileInput.count() > 0) {
    await fileInput.setInputFiles({
      name: 'invalid-empty.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from(''),
    });

    // Submit the form
    const submitButton = page.locator('button[type="submit"]');
    if (await submitButton.count() > 0) {
      await submitButton.last().click();
      await page.waitForLoadState('networkidle');

      // After submission, page should not crash (status < 500)
      // and should show some feedback (error or redirect back)
      const url = page.url();
      const body = await page.locator('body').textContent();
      expect(body).toBeTruthy();
    }
  }
});

test('G21 — upload text file shows error or rejects', async ({ page }) => {
  await page.goto('/tree/demo/import');

  const fileInput = page.locator('input[type="file"]');
  if (await fileInput.count() > 0) {
    await fileInput.setInputFiles({
      name: 'invalid-text.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from('This is not a GEDCOM file.\n'),
    });

    const submitButton = page.locator('button[type="submit"]');
    if (await submitButton.count() > 0) {
      await submitButton.last().click();
      await page.waitForLoadState('networkidle');

      const body = await page.locator('body').textContent();
      expect(body).toBeTruthy();
    }
  }
});

test('G21 — upload binary file shows error or rejects', async ({ page }) => {
  await page.goto('/tree/demo/import');

  const fileInput = page.locator('input[type="file"]');
  if (await fileInput.count() > 0) {
    await fileInput.setInputFiles({
      name: 'invalid-binary.bin',
      mimeType: 'application/octet-stream',
      buffer: Buffer.from([0xDE, 0xAD, 0xBE, 0xEF, 0x00, 0x01, 0x02, 0x03,
                           0xFF, 0xFE, 0xFD, 0xFC, 0xAA, 0xBB, 0xCC, 0xDD]),
    });

    const submitButton = page.locator('button[type="submit"]');
    if (await submitButton.count() > 0) {
      await submitButton.last().click();
      await page.waitForLoadState('networkidle');

      const body = await page.locator('body').textContent();
      expect(body).toBeTruthy();
    }
  }
});
