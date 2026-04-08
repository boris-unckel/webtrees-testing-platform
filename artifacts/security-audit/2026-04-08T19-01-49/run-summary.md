<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Sweep Run Summary — 2026-04-08T19-01-49

- **Started:** 2026-04-08T19:01:49Z
- **Finished:** 2026-04-08T21:12:00Z (local CEST)
- **Status:** S0–S8 abgeschlossen; Deep-Dive auf SEC-AUDIT-001 in Vorbereitung
- **Run dir:** `artifacts/security-audit/2026-04-08T19-01-49`
- **Initiator:** scripts/security-audit-sweep.sh (mechanisch) + interaktive Claude-Code-Phasen

## Pre-Flight (S0)

- [x] Halt-Flag: absent
- [x] Advisory-Lock: claimed and released after run
- [x] webtrees: up (podman-compose)
- [x] mysql: up
- [x] Fork-Repo: clean (`/home/borisunckel/phpprojects/webtrees-upstream/webtrees`, branch `5349_add_tests`)

## S1 — Trace-Middleware-Verifikation

- SecurityTraceModule mountet via compose.yaml, aktiviert mit `WEBTREES_SECURITY_TRACE=1`.
- Double-Guard: ENV + Header `X-Audit-Probe`. Für Sweep nicht global gesetzt (Deep-Dive setzt pro Probe).

## S2 — T0 Inventarisierung (mechanisch)

- Output: `t0_signals.json` (371 Dateien, 172 KB)
- Scanner: Grep-basiert auf Validator-Pattern, DB-Pattern, Dangerous-Function-Pattern laut `04_triage_pipeline.md §2`.
- Scope: `app/Http/RequestHandlers/*.php`, `app/Http/Middleware/*.php`, `app/Services/*.php`, `app/Module/*.php`, `app/Factories/*.php`, `app/Http/Exceptions/*.php`, `app/Auth.php`, `app/Validator.php`.
- **Bemerkung:** Mehrere false positives identifiziert (z. B. `system(` → "filesystem", `exec(` → `DB::exec`, `assert(` → runtime type assertion). Diese wurden in T1 manuell eliminiert.

## S3 — T1 LLM-Triage

- Output: `t1_llm_scores.json`
- **Modus:** Direkt-Triage durch Opus-Session (kein separater Sonnet-Call in V1).
- **Top-Kandidaten nach Dateiinspektion:**
  1. `app/Factories/ImageFactory.php:270` — `str_contains($data, '<script')` trivially bypassable → **llm_score 95**
  2. `app/Services/MediaFileService.php:126` — `uploadFile()` ohne Extension-Blocklist (Inkonsistenz zu `UploadMediaAction`) → **llm_score 88**
- **Eliminiert:** SetupWizard (unreachable post-install), UpgradeWizardStep (T0 false positives), ContactAction (well-guarded).

## S4 — T2 Track-Zuordnung

- Output: `t2_tracks.json`
- Finding zu **non-admin** Track (OWASP A03/A07) zugewiesen.
- Impact-Klasse: `stored-xss`.
- Sekundärer Eskalations-Pfad (Admin-Session-Hijack → privilege escalation) **nicht** in einen zweiten Track promoted — das passiert erst nach D4 Bestätigung.

## S5 — T3 Priorisierung

- Output: `priorities.md`, `task_deltas.json`
- final_score = **0.443** (über Cutoff 0.25).
- Formel-Breakdown:
  - crap: 0.25 × 0.20 = 0.050
  - inputs: 0.15 × 1.00 = 0.150
  - db: 0.15 × 0.00 = 0.000
  - danger×reach: 0.25 × 0.24 = 0.060
  - llm: 0.20 × 0.915 = 0.183

## S6 — Task-Sync

- **Neu erzeugt:** `docs/security-audit/tasks/SEC-AUDIT-001_svg_xss_media_upload.md`
- **Index aktualisiert:** `docs/security-audit/tasks/INDEX.md` (1 Task in Queue, 3 dropped)

## S7 — Erste Hypothesen-Runde (Vorschau für Deep-Dive D2)

Drei Hypothesen in SEC-AUDIT-001 dokumentiert:
- **H1:** Case-Bypass (`<SCRIPT>`) — highest confidence (direkte Schwäche von str_contains)
- **H2:** Event-Handler (`onload="..."`) — high confidence
- **H3:** `javascript:` URL in `xlink:href` — medium confidence (User-Interaction)

## S8 — Summary (diese Datei)

- Alle Phasen S0–S8 abgeschlossen.
- Advisory-Lock freigegeben.
- Bereit für `./scripts/security-audit-deepdive.sh 001` (Deep-Dive D0).

## Next Steps

1. Task #21 als completed markieren.
2. Task #22 (§3.3 Deep-Dive) starten.
3. `./scripts/security-audit-deepdive.sh 001` → D1 Context, D2 Hypothesen, D3 Probe-Loop, D4 Trace-Korrelation, D5 Regression, D6 Fix-Draft, D7 Validation bis `fix_verified`.
