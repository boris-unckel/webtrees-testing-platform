<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SecurityTraceModule

Whitebox-Security-Trace-Middleware für den webtrees Audit-Lauf.

**Spec:** `docs/security-audit/05_security_trace_middleware.md`

## Aktivierung

Doppelt guarded — beide Bedingungen müssen erfüllt sein:

1. Container-ENV `WEBTREES_SECURITY_TRACE=1`
2. Request-Header `X-Audit-Probe: SEC-AUDIT-<NNN>[-r<iter>]`

Ohne beide Bedingungen macht `process()` einen `getenv`-Call + Header-Check und delegiert — Zero-Overhead.

## Artefakte

Pro Probe wird ein JSON-Artefakt nach `/artifacts/security-trace/SEC-AUDIT-<NNN>/<iso-ts>-<us>.json` geschrieben. Der Schreiber nutzt `tmp + rename` für Atomarität.

## Abgedeckt

- Request (method, URI, path, query, body-excerpt, headers, cookies-keys)
- Matched Route
- Auth-Kontext (user_id, is_admin)
- Response (status, headers, body-excerpt, sha256)
- Exceptions (Klasse, message, file, line)
- Dauer in Mikrosekunden

## Nicht abgedeckt (V1)

- `middleware_chain` (pro Middleware Zeiten) — erfordert separate Instrumentierung
- `db_queries` — OTel-PDO-Auto-Instrumentation liefert das bereits via Jaeger
- `otel_trace_id` / `otel_span_id` — Korrelation erfolgt über X-Audit-Probe + timestamp

## Rückbau

Mount in `compose.yaml` entfernen, `make down && make up`. Keine Persistenz außerhalb der Artefakt-Dateien.
