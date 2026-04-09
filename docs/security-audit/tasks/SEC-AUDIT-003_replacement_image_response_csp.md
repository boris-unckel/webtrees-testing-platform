<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-003
title: replacementImageResponse() sets no Content-Security-Policy header
created: 2026-04-08
last_updated: 2026-04-09
status: fix_verified
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
hypotheses:
  - H1_symmetry_with_imageResponse_sufficient
current_hypothesis: H1
probe_iteration_count: 0
validation_failure_count: 0
fixture_rev: 0
fix_branch: security-audit-003-replacement-image-csp
disclosure_state: ready_for_manual_pr
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
- context_file: inlined (trivial fix, self-contained in `ImageFactory.php`)
- generated_at: 2026-04-09

### Phase D2 — Hypothesen
- **H1_symmetry_with_imageResponse_sufficient**: Das Setzen desselben CSP-Headers, den `imageResponse()` bereits benutzt, genügt als Defense-in-Depth-Maßnahme. Der Fix ist self-evident per Code-Read — keine Probe-Loop notwendig.
- hypothesen_count: 1
- highest_confidence: high

### Phase D3/D4 — Probe-Loop (nicht erforderlich)
Skipping — H1 ist self-evident per Code-Read: `imageResponse()` setzt bereits denselben Header, der Fix kopiert das Muster in `replacementImageResponse()`.

### Phase D5 — Regression
- regression_file: `upstream/webtrees/tests/app/Factories/ImageFactoryTest.php::testReplacementImageResponseSetsContentSecurityPolicyHeader` (Layer 2, nicht Layer 3 — der Test kann rein gegen den `ImageFactory`-Response-Pfad laufen, braucht keinen MySQL-Stack)
- Test-First-Commit: siehe D6

### Phase D6 — Fix-Draft
- **fix_branch (authoritativ)**: `security-audit-003-replacement-image-csp` in `/home/borisunckel/phpprojects/webtrees-upstream/webtrees`, abgezweigt von Fork-`main` @ `c338276a5a`
- **Fix-Commits (authoritativ, Fork)**:
  - Test: `32e541249ee45a02833e3c5a5aa7acb274c453b1` (GPG) — fügt Test hinzu, der bewusst rot wird
  - Fix:  `26cbc493a4d6b6d78b184bc1dd840d4906aa6232` (GPG) — addiert CSP-Header, Test grün
- **Fix-Commits (volatile, non-authoritative, Scratch-Clone `webtrees-testing-platform/upstream/webtrees`)**:
  - Test: `399c1747f2e9b228ca2f7a54b9ba6d7b7ddd0601` (GPG)
  - Fix:  `1b4a0bd56bac1c3f15b2ed667cb7361a6349ab55` (GPG)
- diff_size: Fix 5 Zeilen hinzugefügt / 1 ersetzt, Test 12 Zeilen hinzugefügt

### Phase D7 — Validation
- validation_artifacts: `artifacts/security-audit/sec-audit-003/d7_validation/`
- Layer 1 `php -l`: ✅ green (`php_lint.txt`)
- Layer 2 `ImageFactoryTest`: ✅ 2/2, 5 assertions (`layer2_image_factory_green.txt`)
- Code-read-Review: ✅ Fix wirkt an der Quelle; alle 13 `replacementImageResponse`-Aufrufstellen (10 in `ImageFactory`, 2 in `MediaFileThumbnail`, 1 in `MediaFileDownload`) erben den neuen Header ohne weitere Änderung. Der CSP-Wert ist string-identisch mit dem in `imageResponse()` gesetzten.
- gesamturteil: **fix_verified**

## Finding Summary

Der Defense-in-Depth-Fix schließt eine Symmetrie-Lücke zwischen `imageResponse()` und `replacementImageResponse()` in `ImageFactory`. Keine Exploit-Ausnutzbarkeit zum Zeitpunkt des Fixes — der Placeholder-Body wird aus `errors/image-svg.phtml` mit statischem Status-Text gerendert (keine user-kontrollierte Interpolation). Der Wert des Fixes liegt darin, den CSP-Schutzschild auch auf dem Error-Fallback-Pfad zu etablieren, damit zukünftige Erweiterungen des Placeholder-Bodies (Fehlernachrichten, Originaldateinamen, …) nicht unbemerkt die L2-Barriere verlieren. Der Test pinnt die Symmetrie als Contract.

## Offene Punkte

- [x] D1 Context-Extraktion (inlined, trivial)
- [x] D2 Hypothesen — H1 self-evident, D3/D4 geskippt
- [x] Alle `replacementImageResponse`-Aufrufstellen auflisten — 13 Sites, alle erben Fix automatisch
- [x] Regressionstest (Layer 2) — in `ImageFactoryTest` erweitert
- [x] Mirror in authoritativen Fork

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-08 22:40 | queued | Spin-off aus SEC-AUDIT-001 D7-Validation — manuell angelegt |
| 2026-04-09 | fix_committed | Test-First-Commit `399c1747f2` + Fix-Commit `1b4a0bd56b` im volatilen Scratch-Clone (GPG) |
| 2026-04-09 | fix_verified | Layer 1+2 green, Code-read bestätigt Symmetrie mit `imageResponse()` |
| 2026-04-09 | fix_verified (Mirror) | Beide Commits per `git cherry-pick -S` in den authoritativen Fork gespiegelt — Test `32e541249e`, Fix `26cbc493a4`, Branch `security-audit-003-replacement-image-csp` @ Fork-`main`. Dies sind die PR-relevanten Hashes. |
