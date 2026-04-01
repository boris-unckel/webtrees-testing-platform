// SPDX-License-Identifier: AGPL-3.0-or-later
import { test as base } from '@playwright/test';
import { randomUUID } from 'crypto';

export const test = base.extend<{}>({
  page: async ({ page }, use, testInfo) => {
    const runId = process.env.TEST_RUN_ID || randomUUID();
    const caseId = testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_');

    await page.setExtraHTTPHeaders({
      'baggage': `test.run_id=${runId},test.case_id=${caseId}`,
    });

    await use(page);
  },
});

export { expect } from '@playwright/test';
