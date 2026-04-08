<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-003
title: replacementImageResponse() sets no Content-Security-Policy header
created: 2026-04-08
last_updated: 2026-04-08
status: queued
track: non-admin
file: app/Factories/ImageFactory.php
contributing_files:
  - app/Factories/ImageFactory.php
verticals_hit:
  - V4_xss
final_score: 0.0
llm_score: 0
t0_signals:
  crap: 0
  crap_coverage_pct: 0.0
  input_sinks: []
  db_sinks: []
  dangerous_functions: []
  routing_entry_points:
    - GET /media-thumbnail/{...}  (MediaFileDownload → ImageFactory::fileResponse → replacementImageResponse)
  reachability: visitor
  type_weight: 0.3
  auth_requirement: visitor
  loc: 0
hypotheses: []
current_hypothesis: null
probe_iteration_count: 0
validation_failure_count: 0
fixture_rev: 0
fix_branch: null
disclosure_state: not_ready
blocked_by: []
notes_for_opus: |
  Spin-off aus SEC-AUDIT-001 Validation (Phase D7, artifacts/security-audit/deepdive/001/validation.md).

  **Beobachtung:** `ImageFactory::imageResponse()` setzt für alle regulären
  Image-Responses einen `Content-Security-Policy`-Header
  (`script-src none;frame-src none`). Die Placeholder-Response
  `replacementImageResponse()` (derselbe Factory, aufgerufen bei Upload-
  Fehlern, nicht existierenden Dateien, blockierten SVG-Uploads) setzt
  diesen Header **nicht**.

  **Warum das ein Defense-in-Depth-Gap ist:**
  - Der Placeholder-Body ist selbst ein SVG (`image/svg+xml`), konstruiert
    aus statischem Text ohne benutzerkontrollierte Inhalte — also kein
    direkter XSS-Vektor.
  - ABER: Nach dem SEC-AUDIT-001-Fix ist `replacementImageResponse()` die
    Fallback-Response für geblockte XSS-Uploads. Dass ein erfolgreicher
    Block keine CSP setzt, erzeugt eine subtile Inkonsistenz: falls eine
    zukünftige Änderung benutzerkontrollierten Text in den Placeholder
    einbaut (Fehlermeldung, Originaldateiname, …), fehlt die L2-Barriere.
  - Zudem entzieht der fehlende Header Browser-Telemetrie/Violation-
    Reports für den geblockten Fall — ein Defender verliert Signal.

  **Impact-Einschätzung:** Sehr niedrig. Keine bekannte ausnutzbare Lücke
  im jetzigen Codestand. Das Ziel ist Symmetrie mit `imageResponse()` und
  Zukunftssicherheit gegen unbedachte Erweiterungen des Placeholder-Bodies.

  **Fix-Skizze:**
    return response(
        $this->replacementImage('XSS'),
        StatusCodeInterface::STATUS_OK,
        [
            'content-type'            => 'image/svg+xml',
            'content-security-policy' => 'script-src none;frame-src none',
            'x-image-exception'       => 'SVG image blocked due to XSS.',
        ],
    );

  (und analog für die anderen `replacementImageResponse`-Aufrufstellen
  — `NOT FOUND`, `ERROR`, etc.)

  **Quer-Referenz:**
  - SEC-AUDIT-001 Validation §Out of scope:
    `artifacts/security-audit/deepdive/001/validation.md`
  - `ImageFactory::imageResponse()` vs. `ImageFactory::replacementImageResponse()`
---

# SEC-AUDIT-003 — replacementImageResponse() fehlender CSP-Header

## Triage-Kontext

- **Warum queued:** Spin-off aus SEC-AUDIT-001 D7-Validation. Kosmetische/präventive Härtung: Nach dem Fix ist `replacementImageResponse` der Fallback-Pfad für geblockte SVG-XSS-Uploads, setzt aber keinen CSP-Header. Kein aktueller Exploit, aber Inkonsistenz zur regulären `imageResponse`, die CSP setzt.
- **Verticals:** V4_xss (defense-in-depth, keine aktuelle Ausnutzbarkeit)
- **Track-Assignment:** non-admin (Pfad ist für Visitor erreichbar via MediaFileDownload)

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/deepdive/003/context.md`
- generated_at: <YYYY-MM-DD HH:MM>

### Phase D2 — Hypothesen
- hypotheses_file: `artifacts/security-audit/deepdive/003/hypotheses.md`
- hypothesen_count: 0
- highest_confidence: <low|medium|high>

### Phase D3/D4 — Probe-Loop
- iteration_log:
  - iter1: <pending>

### Phase D5 — Regression
- regression_file: `layer3-integration/tests/Security/SecAudit003Test.php`
- fixture_file: `fixtures/security/payloads/sec_audit_003.json`

### Phase D6 — Fix-Draft
- fix_branch: `security-audit-003-<slug>`
- fix_commit: <hash>
- diff_size: <N lines>

### Phase D7 — Validation
- validation_file: `artifacts/security-audit/deepdive/003/validation.md`
- gesamturteil: <fix_verified | fix_rejected | validation_incomplete>

## Finding Summary

<nach Phase D7>

## Offene Punkte

- [ ] D1 Context-Extraktion ausführen (scripts/security-audit-deepdive.sh 003)
- [ ] D2 Prüfen, ob der Fix trivial genug ist, um D2/D3 zu überspringen (Add-Header-Only, keine Hypothesen-Loop nötig) — Entscheidung im D1-Review
- [ ] Alle `replacementImageResponse`-Aufrufstellen in `ImageFactory` auflisten (grep) und sicherstellen, dass alle den CSP-Header konsistent setzen
- [ ] Regressionstest: Placeholder-Response muss `content-security-policy`-Header mit `script-src none` enthalten

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-08 22:40 | queued | Spin-off aus SEC-AUDIT-001 D7-Validation — manuell angelegt |
