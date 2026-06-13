<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# webtrees Testing Platform — Reference Manual

This is the operational reference for the **webtrees Testing Platform** — a containerised test
infrastructure for the [webtrees](https://github.com/fisharebest/webtrees) genealogy
application and its modules. For *what* the platform tests and *why* it is useful, see
[FEATURES_en.md](FEATURES_en.md).

> The canonical, most detailed documentation is German and lives in `README.md` and under
> `docs/`. This English manual is a concise operational summary for international users.

## Contents

1. [Prerequisites & quick start](#1-prerequisites--quick-start)
2. [Command reference (`make help` in English)](#2-command-reference-make-help-in-english)
3. [Using your own repository instead of the default upstream](#3-using-your-own-repository-instead-of-the-default-upstream)
4. [Known test failures](#4-known-test-failures)
5. [Running a single integration test](#5-running-a-single-integration-test)
6. [Troubleshooting](#6-troubleshooting)

---

## 1. Prerequisites & quick start

The platform runs on **Podman** (rootless) with **podman-compose** — no Docker daemon and no
root privileges are required. It was developed on Fedora; any Linux host with Podman 5.x and
podman-compose will work.

```bash
git clone https://github.com/boris-unckel/webtrees-testing-platform.git
cd webtrees-testing-platform
make setup        # clones upstream webtrees, generates passwords, starts the stack, installs webtrees
```

After `make setup` the stack is reachable at:

| Service | URL |
|---|---|
| webtrees | http://localhost:8080 |
| Jaeger (traces) | http://localhost:16686 |
| Adminer (DB, debug profile only) | http://localhost:8081 |

All passwords (MySQL, webtrees admin, test users) are generated on the first `make up` /
`make setup` and written to `.env`. Manual edits survive subsequent runs; `make clean` resets
them.

---

## 2. Command reference (`make help` in English)

`make help` prints every target with a short German description. The same targets in English:

### Lifecycle

| Target | Description |
|---|---|
| `make up` | Start the stack (all containers) |
| `make up-debug` | Start the stack including Adminer (debug profile, DB inspection on :8081) |
| `make setup` | Provision webtrees in the container (DB migration, fixtures, admin & role users). Implies `make up`. |
| `make down` | Stop the stack |
| `make clean` | Stop the stack, delete volumes and reset all passwords |

### Test layers

| Target | Layer | Description |
|---|---|---|
| `make test-static` | L1 | Static analysis — PHPStan + PHPCS + Trivy |
| `make test-unit` | L2 | Component tests — PHPUnit (SQLite in-memory) |
| `make test-integration` | L3 | Component integration tests — PHPUnit (MySQL) |
| `make test-integration-quick` | L3 | Quick run — 3 representative cases |
| `make test-e2e` | L4 | System tests — Playwright (Chromium headless), OTel correlation |
| `make test-e2e-quick` | L4 | Quick run — 3 representative spec files |
| `make test-performance` | L5 | Performance tests — Playwright metrics + baseline comparison |
| `make test-all` | — | Run all layers sequentially (**see caveat below**) |
| `make test-security` | — | Security track — distribution build + setup wizard + filesystem/HTTP checks (own lifecycle) |

### Diagnostics

| Target | Description |
|---|---|
| `make status` | Container status (up/down + health) |
| `make logs` | Live container logs |
| `make mysql-shell` | Open a MySQL shell |
| `make php-shell` | Open a shell in the webtrees container |
| `make db-dump` | Dump the test database to `artifacts/` |

### Observability & reporting

| Target | Description |
|---|---|
| `make perfschema-truncate` | Reset MySQL Performance Schema data |
| `make perfschema-extract LAYER=layer3\|layer4\|layer5` | Extract Performance Schema data as JSON |
| `make trace-report RUN_ID=<uuid> [LAYER=3\|4\|5]` | Generate an OTel trace report |
| `make crap-report` | CRAP-score report from the L3 coverage XML |

### Security lifecycle (manual)

`make test-security` runs the full build → test → teardown cycle in one go. For manual
debugging of the distribution container: `make security-build` → `make security-up` → … →
`make security-down` / `make security-clean`.

### Three things to know before you run tests

> **`make test-all` does not currently run to completion.**
> `test-all` chains the layers as prerequisites
> (`setup → test-static → test-unit → test-integration → test-e2e → test-performance`).
> Make stops at the **first** target that exits non-zero. Because Layer 2 has a known,
> environment-dependent failure (see §4), Make aborts there and Layers 3–5 never start.
> **Run the layers individually instead.**

> **Re-run `make setup` before each of Layers 3, 4 and 5.**
> The integration and system tests create, rename and delete trees, so the mandatory fixture
> trees (`demo`, `muster`, `privacy`) may be gone or modified after a run. The test targets do
> **not** recreate them — only `make setup` does, idempotently. Layer 4's Playwright
> `globalSetup` fails fast with a clear message if any required tree is missing, to avoid
> 150+ cryptic follow-up failures.

> **Known failures stop automatic progression.**
> Even when run individually, Layers 2 and 3 exit non-zero because of deliberately pinned known
> failures (see §4). This is expected; coverage and reports are still produced.

---

## 3. Using your own repository instead of the default upstream

By default the platform clones `fisharebest/webtrees@main` into `./upstream/webtrees` and
bind-mounts it read-only into the container. Three environment variables — set in `.env` or as
a command-line prefix — control the source, giving **two** ways to use your own repository.

### Option A — bind-mount a local checkout (`WEBTREES_SOURCE`)

Point `WEBTREES_SOURCE` at a webtrees working tree you already have on disk:

```env
WEBTREES_SOURCE=/path/to/your/webtrees-checkout
```

Any path other than the default `./upstream/webtrees` is treated as an **external working
tree**: it is **not** cloned and its git configuration is left untouched — you manage its
branch and state yourself. The directory must already exist. `WEBTREES_REPO` / `WEBTREES_REF`
are ignored in this mode.

### Option B — let the platform clone your fork (`WEBTREES_REPO` + `WEBTREES_REF`)

Leave `WEBTREES_SOURCE` at its default and point the clone at your fork instead:

```env
# WEBTREES_SOURCE stays at the default ./upstream/webtrees
WEBTREES_REPO=https://github.com/<you>/webtrees.git
WEBTREES_REF=my-feature-branch        # branch, tag or commit; default: main
```

On `make setup` the platform clones your fork at the given ref into `./upstream/webtrees`.

| Variable | Default | Effect |
|---|---|---|
| `WEBTREES_SOURCE` | `./upstream/webtrees` | Directory bind-mounted as the webtrees source |
| `WEBTREES_REPO` | `https://github.com/fisharebest/webtrees.git` | Clone URL (used only for the default source path) |
| `WEBTREES_REF` | `main` | Branch / tag / commit to clone |

> **Optional module mounting.** A webtrees module is always mounted *on top of* a core — it
> cannot be tested on its own. To add your module repository to whichever core is active, set
> `MODULE_PATH` (the module repo root) and `MODULE_NAME` (its folder name under `modules_v4/`);
> see the German `CLAUDE.md` for details.

To switch cleanly between sources, run `make clean`, adjust `.env`, then `make setup` again.

---

## 4. Known test failures

The platform tests against the moving `main` branch of webtrees (no pinned tag) so that modules
and extensions are validated against the latest upstream. As a result, a small, **known** set
of tests is red. These are **not** defects of the platform or of a module under test — they are
either toolchain differences or deliberate *failure pins* that encode the expected behaviour and
stay red until upstream changes. They are listed here because they stop `make test-all` from
progressing (see §2).

### Layer 2 (component tests)

- **`ReportRegressionTest::testReportHtmlOutputMatchesSnapshot`** (`@individual_report`,
  `@individual_ext_report`).
  The byte-exact snapshot comparison fails solely on the embedded JPEG: the container's
  GD/libjpeg writes the comment segment `CREATOR: gd-jpeg v1.0 (using IJG JPEG v62), quality
  = 70`, which the stored snapshot does not contain. This is a pure libjpeg toolchain difference
  versus the webtrees snapshot environment — no content or layout error.

### Layer 3 (component integration tests)

`make test-integration` ends with **exit code 2** — four pinned failures, **zero errors**.
Coverage is produced regardless of the exit code. Each encodes required behaviour that upstream
`main` does not yet implement (the latter three originate from this repository's security-audit
work):

| Test | What it pins |
|---|---|
| `AutoCompleteIntegrationTest::test_autocomplete_citation_returns_json_for_valid_source` | The citation-autocomplete endpoint must return a JSON **array**. Upstream `AutoCompleteCitation::search()` keeps the gappy numeric keys left by `uniqueStrict()`, so `json_encode()` emits a JSON **object** (`{"0":…,"2":…}`). Red until upstream calls `values()` before encoding. |
| `LoginActionIntegrationTest` — per-user rate limit | 10 failed logins for one account must yield HTTP 429 / `HttpTooManyRequestsException`. Upstream `main` has no `RateLimitService`. (SEC-AUDIT-008) |
| `LoginActionIntegrationTest` — site-wide rate limit | 20 failed logins against unknown users must yield HTTP 429 (anti-enumeration). Same missing `RateLimitService`. (SEC-AUDIT-008) |
| `RenumberTreeActionIntegrationTest` — malformed-XREF guard | A malformed XREF must be skipped, not renamed. Upstream `RenumberTreeAction` builds the SQL with an inline-concatenated raw expression instead of parameter binding, so a malformed XREF breaks the statement. (SEC-AUDIT-006) |

The pins follow the project's test-iteration convention; each turns green automatically once
upstream is fixed.

---

## 5. Running a single integration test

There is no `make` target for single tests — `make test-integration` always runs the full suite
with coverage. Run individual tests directly in the container:

```bash
# A single test class (without coverage)
podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter='SearchIntegrationTest'

# A single test method
podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter='SearchIntegrationTest::test_search_by_surname'
```

All PHPUnit processes run **inside the `webtrees` container**, never on the host. To check for
or stop a running run:

```bash
podman-compose exec webtrees pgrep -a -f phpunit   # is a run active?
podman-compose exec webtrees kill <PID>            # stop it
```

Only one test layer — and only one run of that layer — may execute at a time; the containers
share state (MySQL, webtrees data), so parallel runs cause race conditions.

---

## 6. Troubleshooting

- **Container health:** `make status` (up/down + health), `make logs` (live logs).
- **SELinux trap (Fedora / rootless Podman):** never use `podman run -v <path>:...:Z` (capital
  `Z`) on directories the compose stack mounts simultaneously — the private SELinux label locks
  the compose container out. Use `podman-compose exec webtrees …` for ad-hoc commands.
- **Recovery after a broken state:** `make down && make up && make setup`.
