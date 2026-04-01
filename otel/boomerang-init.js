// SPDX-License-Identifier: AGPL-3.0-or-later
// Boomerang + OTel-Plugin Initialisierung
(function() {
  var collectorUrl = 'http://otel-collector:4318/v1/traces';

  BOOMR.init({
    OpenTelemetry: {
      samplingRate: 1.0,
      collectorConfiguration: {
        url: collectorUrl
      },
      serviceName: 'webtrees-browser',
      commonAttributes: {
        'deployment.environment': 'test'
      },
      plugins_config: {
        instrument_document_load: {
          enabled: true,
          recordTransaction: true,
          exporterDelay: 200
        }
      },
      exporter: {
        scheduledDelayMillis: 1000,
        maxExportBatchSize: 50
      }
    }
  });
})();
