// SPDX-License-Identifier: AGPL-3.0-or-later
import { test as base } from '@playwright/test';
import { randomUUID } from 'crypto';
import { trace } from '@opentelemetry/api';
import { NodeTracerProvider, SimpleSpanProcessor } from '@opentelemetry/sdk-trace-node';
import { OTLPTraceExporter } from '@opentelemetry/exporter-trace-otlp-http';
import { Resource } from '@opentelemetry/resources';
import { ATTR_SERVICE_NAME } from '@opentelemetry/semantic-conventions';

export const test = base.extend<{}, { _otelProvider: NodeTracerProvider }>({
  // Worker-scoped: einmal pro Worker initialisiert, bei workers:1 = einmal gesamt
  _otelProvider: [async ({}, use) => {
    const provider = new NodeTracerProvider({
      resource: new Resource({
        [ATTR_SERVICE_NAME]: 'playwright-tests',
      }),
      spanProcessors: [
        new SimpleSpanProcessor(
          new OTLPTraceExporter({
            url: 'http://otel-collector:4318/v1/traces',
          })
        ),
      ],
    });
    provider.register();
    await use(provider);
    await provider.shutdown();
  }, { scope: 'worker' }],

  page: async ({ page, _otelProvider }, use, testInfo) => {
    const tracer = trace.getTracer('playwright-tests');
    const runId = process.env.TEST_RUN_ID || randomUUID();
    const caseId = testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_');

    const rootSpan = tracer.startSpan(`test: ${caseId}`, {
      attributes: {
        'test.run_id': runId,
        'test.case_id': caseId,
      },
    });
    const spanContext = rootSpan.spanContext();
    const traceparent = `00-${spanContext.traceId}-${spanContext.spanId}-01`;

    // NUR webtrees-Requests — nicht otel-collector:4318 (Boomerang OTLP)
    await page.route(/^http:\/\/webtrees(:\d+)?\//, async (route) => {
      await route.continue({
        headers: {
          ...route.request().headers(),
          'traceparent': traceparent,
          'baggage': `test.run_id=${runId},test.case_id=${caseId}`,
        },
      });
    });

    await use(page);
    // Browser-Spans exportieren: beforeunload feuert nicht beim Context-Close,
    // daher explizit navigieren um Boomerang-Flush auszuloesen
    await page.goto('about:blank').catch(() => {});
    await page.waitForTimeout(500);
    rootSpan.end();
  },
});

export { expect } from '@playwright/test';
