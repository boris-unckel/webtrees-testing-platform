<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# webtrees Testing Platform — Features & Capabilities

The **webtrees Testing Platform** is a containerised, multi-layer test infrastructure for the
[webtrees](https://github.com/fisharebest/webtrees) genealogy application and its modules. This
document summarises what the platform offers and what it covers. For how to operate it, see
[REFERENCE_en.md](REFERENCE_en.md).

---

## 1. Why a container platform for testing webtrees?

- **A complete, disposable webtrees stack in one command.** `make setup` brings up Apache +
  PHP 8.5, MySQL (LTS 8.4) and the full observability stack, installs webtrees programmatically
  and imports fixtures — reproducibly, with no pollution of the host and no root privileges
  (rootless Podman).
- **Tests run against the real application and a real database**, not just mocks: real GEDCOM
  import, real SQL, real HTTP request handlers, a real browser. This catches integration and
  system defects that pure unit tests miss.
- **A five-layer model** — static analysis, then component, integration, system and performance
  testing — where each layer adds realism (see §5–§7).
- **Continuous validation against webtrees `main` (latest).** The source is deliberately not
  pinned to a tag, giving module and extension authors an early warning when an upstream change
  would break them — before an official release.
- **Bring your own core or module.** Test your own webtrees fork or branch, or mount your own
  module repository on top of it (see [REFERENCE_en.md §3](REFERENCE_en.md#3-using-your-own-repository-instead-of-the-default-upstream)).
- **End-to-end observability.** A four-layer OpenTelemetry trace chain plus MySQL Performance
  Schema extraction, correlated per test run (§2–§3).
- **A built-in security track** that verifies a fresh distribution install is hardened (setup
  wizard, filesystem and HTTP-access checks).
- **A five-theme UI matrix** in the system tests, so theming regressions surface automatically.
- **AI-assisted failure analysis** (`scripts/analyze-failure.sh`) collects the relevant
  artefacts and hands them to the Claude Code CLI.

---

## 2. Observability — the four-layer OpenTelemetry trace chain

A single test case produces one correlated trace that spans every tier of the application, so a
request can be followed from the browser down to the SQL statement. All four tiers emit to the
same endpoint (OTLP HTTP/Protobuf on port 4318) and are visualised in Jaeger
(http://localhost:16686). Setting `OTEL_SDK_DISABLED=true` disables the whole chain with zero
overhead.

| Tier | Mechanism | Service in Jaeger |
|---|---|---|
| PHP auto-instrumentation | Automatic PDO + PSR-15 + PSR-18 instrumentation (no core change) | `webtrees` |
| PHP semantic spans | `OtelSpansModule` — 40+ named routes, Server-Timing header, baggage correlation | `webtrees` (scope `otel-spans`) |
| Browser RUM | Boomerang + OpenTelemetry plugin, injected via Apache `mod_substitute` | `webtrees-browser` |
| Playwright | One root span per test case; `traceparent` propagated into every webtrees request | `playwright-tests` |

Because all tiers share the trace, a Playwright test case, its browser spans, the PHP request it
triggered and that request's SQL queries all carry the same `trace_id`. Baggage attributes
(`test.run_id`, `test.case_id`) make every span attributable to the exact test run and case.

---

## 3. Performance Schema extraction & trace report

Alongside tracing, the platform captures MySQL **Performance Schema** data per run:

- `make perfschema-truncate` resets the counters before a run; `make perfschema-extract LAYER=…`
  exports four Performance Schema tables plus a summary as JSON.
- `make trace-report RUN_ID=<uuid> [LAYER=3|4|5]` parses the collected OTLP data and produces a
  span hierarchy classified into four tiers (Playwright, browser/RUM, PHP custom, PHP auto).
- The `test-e2e`, `test-e2e-quick` and `test-performance` targets generate a `TEST_RUN_ID`
  automatically and run truncate → extract → report end-to-end; artefacts land under
  `artifacts/layer<N>/`.

This turns each run into measurable evidence: which routes ran, how long each tier took and what
SQL the database actually executed.

---

## 4. The container stack (6 + 2 containers, one network)

The functional stack is six containers on a single network (`webtrees-test-net`). The security
track adds two more via a separate compose profile.

| Container | Role | Host port |
|---|---|---|
| `webtrees` | PHP 8.5 + Apache + webtrees + OTel SDK | 8080 |
| `mysql` | MySQL LTS 8.4 (`utf8mb4_bin`, Performance Schema enabled) | 3306 |
| `playwright` | Node.js 22 + Chromium (headless) + OTel SDK | — |
| `otel-collector` | OpenTelemetry collector (OTLP HTTP :4318, gRPC :4317) | 4317, 4318 |
| `jaeger` | Trace visualisation | 16686 |
| `adminer` | DB admin (debug profile only) | 8081 |
| `webtrees-security` | Distribution build (unpacked release ZIP) + Apache (security profile) | 8082 |
| `mysql-security` | Database for the security track (security profile) | — |

Image versions for the observability components are pinned in `compose.yaml`. The webtrees source
itself is never copied — it is bind-mounted read-only from `WEBTREES_SOURCE`.

---

## 5. Test coverage by area (Layer 2 vs Layer 3, as of 2026-06-13)

The two PHPUnit layers are complementary. **Layer 2** (component tests, SQLite in-memory) tests
classes in isolation and is fast; **Layer 3** (component integration tests, MySQL) exercises the
same code against a real database and real HTTP handlers. Overall statement coverage is
**30.12 % (L2)** versus **49.77 % (L3)** — the integration layer adds roughly **+19.6 percentage
points**.

| Area | Statements | L2 % | L3 % | Δ |
|---|---:|---:|---:|---:|
| `app/Http` | 9 015 | 20.5 % | 72.9 % | +52.3 pp |
| `app/Services` | 5 732 | 13.7 % | 59.0 % | +45.3 pp |
| `app/Module` | 10 531 | 12.3 % | 32.4 % | +20.1 pp |
| `app/` (root) | 6 719 | 33.8 % | 49.7 % | +15.9 pp |
| `app/Cli` | 927 | 0.0 % | 74.8 % | +74.8 pp |
| `app/CustomTags` | 825 | 0.0 % | 97.2 % | +97.2 pp |
| `app/Date` | 735 | 6.7 % | 80.4 % | +73.7 pp |
| `app/Factories` | 736 | 39.5 % | 67.1 % | +27.6 pp |
| `app/Report` | 2 985 | 83.9 % | 61.1 % | −22.8 pp |
| `app/Census` | 2 552 | 99.8 % | 2.2 % | −97.6 pp |
| `app/Elements` | 1 575 | 86.9 % | 34.3 % | −52.5 pp |

**Reading:** wherever real data, a database and HTTP handlers matter — `Http`, `Services`, `Cli`,
`CustomTags`, `Date` — Layer 3 dominates. Conversely `Census`, `Elements` and the report
renderers are pure, data-driven classes that Layer 2 covers directly through data providers and
that Layer 3 only grazes through module discovery. Neither layer is redundant.

Full table, per-file gains and losses, and methodology:
[`docs/coverage-runs/2026-06-13_layer2-vs-layer3.md`](docs/coverage-runs/2026-06-13_layer2-vs-layer3.md).

---

## 6. The coverage matrix (`docs/tds_coverage_ref.md`)

[`docs/tds_coverage_ref.md`](docs/tds_coverage_ref.md) maps **every feature** of webtrees to its
tests. For each feature ID it records which test class or spec covers it at Layer 2 (component),
Layer 3 (integration) and Layer 4 (system), and assigns a **quality seal** that grades the test
depth:

| Seal | Meaning |
|---|---|
| `[EP]` | Equivalence-partition-complete (data provider with ≥3 partitions) |
| `[Spec-B]` | Specification-based, strict (1:1 to an external spec — GEDCOM, RFC, W3C) |
| `[Spec-C]` | Specification-based, pragmatic (business assertions) |
| `[Smoke]` | Smoke (3–5 assertions) |
| `[CRAP]` | Structure-based (derived from CRAP-score analysis) |

It is the place to look up *whether a given feature is tested and how deeply*. As of the latest
snapshot it tracks **219 features in nine domains** — GEDCOM, Search & Navigation,
Privacy & Access control, Security, Data entry, Administration, Communication, Cross-cutting
utilities and Middleware — of which **216 are covered**.

---

## 7. What is covered, by layer (business perspective)

The same feature is often tested at more than one layer, each answering a different question.

### Layer 2 — component tests (isolated, fast): *"Is each building block correct on its own?"*

GEDCOM **element validation and XSS hardening** across 212 element types; **export formatting**
(encoding, CONC/CONT, privacy filtering, headers); **chart and list module** rendering logic;
**name and place autocomplete**; **role-based authorisation** decisions; and **input
validation**.

### Layer 3 — component integration tests (real MySQL): *"Do the genealogy workflows work against a real database?"*

The bulk of the platform's own tests live here:

- **GEDCOM import** — records, relationships, place hierarchies, encodings (UTF-8 / ANSEL /
  CP1252), custom tags, and CLI import/export.
- **Search** — advanced, phonetic (Russell & Daitch-Mokotoff), paginated and cross-tree search.
- **Privacy & access control** — the full model: living-person visibility, `MAX_ALIVE_AGE` and
  keep-alive rules, RESN at record and fact level, relationship privacy, and the
  editor / moderator / manager permission tiers.
- **Data entry** — creating and editing individuals, families, facts, sub-records and media.
- **Administration** — tree management, preferences, user administration and a broad set of CLI
  commands.
- **The complete HTTP middleware stack** — routing, sessions, CSRF, security headers,
  compression, error handling and more.
- **Communication** — contact form and user messaging.

### Layer 4 — system tests (real browser): *"Does the deployed site behave correctly and safely for real users?"*

Login, navigation and record pages exercised across the **five-theme matrix**; the privacy model
as it actually renders in the UI; admin screens; contact forms; and a dedicated **security
suite** that verifies a fresh distribution install is hardened — HTTP access to `data/` and
`config.ini.php` blocked, the setup wizard self-locking after install, OWASP security headers
present, no directory listing, and path traversal blocked.

---

*Snapshot figures above are dated; see `docs/coverage-runs/` for the latest coverage runs and
`docs/tds_coverage_ref.md` for the current feature matrix.*
