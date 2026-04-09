<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-001
title: SVG Stored XSS via inadequate <script> substring filter (ImageFactory) + extension blocklist gap (MediaFileService)
created: 2026-04-08
last_updated: 2026-04-08
status: fix_verified
track: non-admin
file: app/Factories/ImageFactory.php
contributing_files:
  - app/Services/MediaFileService.php
verticals_hit:
  - V4_xss
final_score: 0.443
llm_score: 91
t0_signals:
  crap: 12
  crap_coverage_pct: 0.0
  input_sinks:
    - Validator::parsedBody->string('folder')
    - Validator::parsedBody->string('auto')
    - Validator::parsedBody->string('new_file')
    - Validator::parsedBody->string('file_location')
    - Validator::parsedBody->string('remote')
    - Validator::parsedBody->string('unused')
    - getUploadedFiles['file']->getClientFilename
    - Validator::attributes->tree
  db_sinks: []
  dangerous_functions:
    - str_contains($data, '<script')
  routing_entry_points:
    - POST /tree/{tree}/add-media-file/{xref} -> AddMediaFileAction
    - POST /tree/{tree}/create-media-object -> CreateMediaObjectAction
  reachability: editor
  type_weight: 0.6
  auth_requirement: editor
  loc: 365
hypotheses:
  - H1
  - H2
  - H3
  - H4
  - H5
current_hypothesis: null
probe_iteration_count: 5
validation_failure_count: 0
fixture_rev: 1
fix_branch: security-audit-001-svg-filter-hardening-clean
fix_commit: c15b95fef48b9ee96eb0a77bfe9a48d7dbbfb3be
disclosure_state: ready_for_manual_pr
blocked_by: []
notes_for_opus: |
  Two-file finding. Upload entry is MediaFileService::uploadFile(), but the defeatable
  sanitization is in ImageFactory::imageResponse() (line 270). Probe-Loop fokussiert auf
  drei SVG-Payload-Familien:
    H1: <SCRIPT> (Case-Bypass des case-sensitiven str_contains)
    H2: <svg onload="..."> (Event-Handler, kein <script-Substring)
    H3: <a xlink:href="javascript:..."> (javascript: URL)
  Oracle: Response-Body enthält die Payload und Content-Type=image/svg+xml; kein
  `x-image-exception` Header.
---

# SEC-AUDIT-001 — SVG Stored XSS via inadequate <script> substring filter

## Triage-Kontext

- **Warum queued:** Kombinierter final_score 0.443 (T1 LLM-Score 91, Input-Density 1.0, reach=0.6).
  - Root cause in `app/Factories/ImageFactory.php:270` — `str_contains($data, '<script')` ist case-sensitiv und erkennt keine SVG Event-Handler oder `javascript:`-URLs.
  - Enabling gap in `app/Services/MediaFileService.php:126-195` — `uploadFile()` hat weder eine Extension-Blockliste noch eine Folder-Allowlist. Die Schwester-Action `UploadMediaAction` enforcet beide. Klare Inkonsistenz.
- **Verticals:** V4_xss (Stored XSS)
- **Track-Assignment:** non-admin (OWASP A03/A07). Editor-Rolle erforderlich zum Plant-In, Visitor-Rolle ausreichend zum Trigger-Out.

## Hypothesen (Vorschau für D2)

### H1 — Case-Bypass des `<script`-Substring-Filters
**Payload:** `<svg xmlns="http://www.w3.org/2000/svg"><SCRIPT>alert(document.cookie)</SCRIPT></svg>`
**Root-Cause-Logik:** PHP `str_contains()` ist case-sensitiv. `str_contains('<SCRIPT>', '<script')` liefert `false`. HTML/SVG-Parser der Browser sind aber case-insensitiv → `<SCRIPT>` wird als Script-Tag interpretiert und ausgeführt.
**Oracle:** Response-Body enthält `<SCRIPT>`, Content-Type=image/svg+xml, **kein** `x-image-exception` Header.

### H2 — SVG Event-Handler ohne `<script>`-Tag
**Payload:** `<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.cookie)"/>`
**Root-Cause-Logik:** Kein `<script`-Substring vorhanden → Filter greift gar nicht. SVG-Event-Handler werden vom Browser beim Rendern ausgeführt.
**Oracle:** wie H1.

### H3 — `javascript:` URL in `xlink:href`
**Payload:** `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="javascript:alert(document.cookie)"><text x="10" y="20">click</text></a></svg>`
**Root-Cause-Logik:** Kein `<script`-Substring. `javascript:`-URL wird beim Klick evaluiert → XSS (User-Interaction benötigt).
**Oracle:** wie H1.

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/deepdive/001/context.md`
- generated_at: <YYYY-MM-DD HH:MM>

### Phase D2 — Hypothesen
- hypotheses_file: `artifacts/security-audit/deepdive/001/hypotheses.md`
- hypothesen_count: 3
- highest_confidence: high

### Phase D3/D4 — Probe-Loop
- iteration_log:
  - H4_iter1: hypothesis_confirmed (Baseline-Pipeline funktioniert)
  - H1_iter1: hypothesis_confirmed (Case-Bypass `<SCRIPT>` passiert L1-Filter unverändert)
  - H2_iter1: hypothesis_confirmed (onload= Event-Handler passiert L1-Filter unverändert)
  - H3_iter1: hypothesis_confirmed (javascript:-URL in xlink:href passiert L1-Filter unverändert)
  - H5_iter1: hypothesis_confirmed (lowercase `<script` wird korrekt blockiert — Filter nicht komplett kaputt)
- trace_correlation: Keine Trace-Middleware-Artefakte erforderlich — der Bypass ist direkt im Response-Body beobachtbar (PHPUnit-Integration-Test deckt Service+Factory direkt ab).
- overall_decision: **L1-Filter (str_contains <script) trivially bypassbar; L2-Verteidigung (CSP script-src none) ist intakt und blockiert den real-world Exploit**. Finding = Defense-in-Depth-Gap, LOW severity.

### Phase D5 — Regression
- regression_file: `layer3-integration/tests/Security/SecAudit001Test.php`
- fixture_file: `fixtures/security/payloads/sec_audit_001.json`

### Phase D6 — Fix-Draft
- fix_branch: `security-audit-001-svg-filter-hardening-clean`
- fix_commit: `c15b95fef48b9ee96eb0a77bfe9a48d7dbbfb3be`
- diff_size: 1 file changed, 97 insertions(+), 2 deletions(-)
- approach: `str_contains`-Filter durch DOM-basierten Walker in `ImageFactory::svgContainsActiveContent()` ersetzt. Blockt `script`/`foreignObject`/`iframe`/`object`/`embed`/`handler`-Elemente (case-insensitiv), alle `on*`-Event-Handler und normalisierte `javascript:`-URLs. Malformed XML → konservativer Block. `LIBXML_NONET` schützt gegen XXE/SSRF.

### Phase D7 — Validation
- validation_file: `artifacts/security-audit/deepdive/001/validation.md`
- pre_fix_run: 3 Failures (H1/H2/H3), 2 Passes (H4/H5) — Test ist diagnostisch
- post_fix_run: 5 Tests, 66 Assertions, 0 Failures
- gesamturteil: **fix_verified**

## Confirmed Vectors

**L1-Filter-Bypässe (alle bestätigt):**

1. **H1 — Case-Bypass**: `<SCRIPT>alert(1)</SCRIPT>` passiert `str_contains($data, '<script')` wegen Case-Sensitivität von PHP.
2. **H2 — Event-Handler ohne Script-Tag**: `<svg onload="alert(1)"/>` enthält keinen `<script`-Substring und umgeht den Filter vollständig.
3. **H3 — `javascript:` URL**: `<a xlink:href="javascript:alert(1)">` umgeht den Filter ebenfalls; Exploit erfordert zusätzlich User-Interaction.

**L2-Verteidigung (CSP) bleibt intakt:** Alle Responses enthalten `content-security-policy: script-src none;frame-src none`. Diese Direktive blockiert nach CSP Level 2/3 Spec:
- `<script>`-Tags (inline + extern, unabhängig von Case)
- Inline Event-Handler-Attribute (`onload`, `onclick`, …)
- `javascript:`-URLs bei Navigation

→ In modernen Browsern wird der Exploit durch CSP blockiert. Finding bleibt valide als Defense-in-Depth-Gap.

## Finding Summary

- **Schweregrad:** Defense-in-Depth-Gap (LOW). Der serverseitige L1-Blocker in `ImageFactory::imageResponse()` war trivial umgehbar (`str_contains($data, '<script')` ist case-sensitiv und deckt weder Event-Handler noch `javascript:`-URLs ab). Real-world Exploit wurde jedoch durch die L2-Verteidigung (`content-security-policy: script-src none;frame-src none`) in modernen Browsern blockiert.
- **Klasse:** OWASP A03 Injection / A07 XSS
- **Angriffsvoraussetzungen:** Editor-Rolle für Upload (AuthEditor-Middleware), Visitor-Rolle für Trigger-Out, SVG-Upload über `CreateMediaObjectAction` oder `AddMediaFileAction`.
- **Betroffene Dateien:**
  - `app/Factories/ImageFactory.php:270` — Root-Cause (L1-Filter)
  - `app/Services/MediaFileService.php:126-195` — Enabling Gap (keine Extension-Allowlist; bleibt offen als Follow-Up)
- **Fix:** `ImageFactory::svgContainsActiveContent()` + `svgElementIsDangerous()` — DOM-basierter Walker, der Script-Elemente, Event-Handler und `javascript:`-URLs rekursiv erkennt und blockt. Malformed XML wird konservativ zurückgewiesen. `LIBXML_NONET` aktiviert, um XXE/SSRF vorzubeugen.
- **Regressionstest:** `layer3-integration/tests/Security/SecAudit001Test.php` (5 Methoden, 66 Assertions post-fix). Test ist diagnostisch: pre-fix rot auf H1/H2/H3, post-fix grün.
- **Commit (Fork):** `c15b95fef48b9ee96eb0a77bfe9a48d7dbbfb3be` auf `security-audit-001-svg-filter-hardening-clean`.
- **Disclosure:** Bereit für manuelle PR-Eröffnung durch den User (V1-Workflow).
- **Offene Folge-Tasks:** SEC-AUDIT-002 (Extension-Allowlist in `MediaFileService::uploadFile()`), SEC-AUDIT-003 (CSP-Header auf `replacementImageResponse()`), SEC-AUDIT-004 (Audit weiterer SVG-Serve-Pfade außerhalb `imageResponse()`).

## Offene Punkte

- [x] Bestätigung H1 (Case-Bypass) via Probe-Run — confirmed in `probe_H1_iter1/decision.md`
- [x] Bestätigung H2 (Event-Handler) — confirmed in `probe_H2_iter1/decision.md`
- [x] Bestätigung H3 (javascript:-URL) — confirmed in `probe_H3_iter1/decision.md`
- [x] Fix-Strategie entschieden: DOM-basierter Walker (`DOMDocument` + rekursiver Element/Attribut-Walk), kein externer Sanitizer benötigt. Commit `c15b95fef4`.
- [ ] **Follow-Up SEC-AUDIT-002**: Extension-Allowlist in `MediaFileService::uploadFile()` (Defense-in-Depth, verhindert Upload anderer gefährlicher Dateiformate). Siehe `SEC-AUDIT-002_mediafile_service_upload_allowlist.md`.
- [ ] **Follow-Up SEC-AUDIT-003**: `replacementImageResponse` setzt keinen CSP-Header — prüfen, ob das aus Defense-in-Depth-Gründen ergänzt werden sollte. Siehe `SEC-AUDIT-003_replacement_image_response_csp.md`.
- [ ] **Follow-Up SEC-AUDIT-004**: Prüfen, ob es weitere Serve-Pfade in webtrees gibt, die SVG an den Browser ausliefern, ohne `imageResponse()` zu durchlaufen. Siehe `SEC-AUDIT-004_svg_serve_path_audit.md`.
- [ ] **Manuelle Aktion durch User**: PR gegen fisharebest/webtrees eröffnen (V1-Workflow).

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-08 21:12 | queued | Erzeugt in T3 (scripts/security-audit-sweep.sh S5/S6) |
| 2026-04-08 21:18 | in_analysis | Deep-Dive D1 gestartet (scripts/security-audit-deepdive.sh) |
| 2026-04-08 21:28 | in_progress | D2 Hypothesen (H1–H5) dokumentiert |
| 2026-04-08 21:32 | exploit_attempted | D3 Probe-Loop: 5 Iterationen durchgeführt |
| 2026-04-08 21:32 | exploit_confirmed | D4: H1+H2+H3 L1-Bypass empirisch bestätigt, L2-CSP intakt |
| 2026-04-08 21:32 | regression_drafted | D5: SecAudit001Test (5 Methoden, 70 Assertions) grün |
| 2026-04-08 22:20 | fix_committed | D6: Commit b2dc869b90 auf security-audit-001-svg-filter-hardening (GPG-signed), rebased to c15b95fef4 on security-audit-001-svg-filter-hardening-clean (off main) |
| 2026-04-08 22:23 | fix_verified | D7: pre-fix 3F/2P, post-fix 5P/66A, Test-Suite diagnostisch |
