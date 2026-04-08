<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-002
title: Missing extension/folder allowlist in MediaFileService::uploadFile() (inconsistent with UploadMediaAction)
created: 2026-04-08
last_updated: 2026-04-08
status: queued
track: non-admin
file: app/Services/MediaFileService.php
contributing_files:
  - app/Http/RequestHandlers/CreateMediaObjectAction.php
  - app/Http/RequestHandlers/AddMediaFileAction.php
  - app/Http/RequestHandlers/UploadMediaAction.php
verticals_hit:
  - V4_xss
  - V9_arbitrary_file_write
final_score: 0.0
llm_score: 0
t0_signals:
  crap: 0
  crap_coverage_pct: 0.0
  input_sinks:
    - Validator::parsedBody->string('folder')
    - Validator::parsedBody->string('auto')
    - Validator::parsedBody->string('new_file')
    - getUploadedFiles['file']->getClientFilename
  db_sinks: []
  dangerous_functions: []
  routing_entry_points:
    - POST /create-media-object -> CreateMediaObjectAction
    - POST /tree/{tree}/add-media-file/{xref} -> AddMediaFileAction
  reachability: editor
  type_weight: 0.6
  auth_requirement: editor
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
  Spin-off aus SEC-AUDIT-001 (Deep-Dive D1/D2 Context-Extraktion):
  `MediaFileService::uploadFile()` hat weder eine Extension-Blockliste noch
  eine Folder-Allowlist. Die Schwester-Action `UploadMediaAction::handle()`
  enforcet beide — klare Inkonsistenz zwischen zwei parallelen Upload-Pfaden.

  UploadMediaAction blockt per regex
    /\.(php|pl|cgi|bash|sh|bat|exe|com|htm|html|shtml)$/i
  und prüft `$all_folders->contains($folder)` bevor ein Upload geschrieben wird.
  MediaFileService::uploadFile() ruft direkt `$tree->mediaFilesystem()->writeStream()`
  ohne diese Checks. Editor-Rolle ist via AuthEditor-Middleware vorausgesetzt;
  ein Editor kann also beliebige Dateinamen hochladen — potenziell mit
  doppelter Extension (`payload.php.jpg`), mit `.htaccess`-Content oder
  mit gefährlichen MIME-Typen.

  **Potentielle Verticals:**
    V4_xss              — HTML-Upload (Stored XSS via `.htm`/`.html`/`.shtml`)
    V9_arbitrary_file_write — Upload außerhalb erlaubter Media-Folder
    V1_rce (bedingt)    — falls die Upload-Destination unter einem
                          PHP-ausführenden Pfad liegt und `.htaccess`-Override
                          aktiv ist (Apache-Default ist aber
                          `AllowOverride None` außerhalb data/, und data/
                          hat ein `Require all denied`)

  **T3-Scoring nicht gelaufen:** Diese Task wurde nicht vom regulären
  Sweep identifiziert sondern als Spin-off aus dem SEC-AUDIT-001 Deep-Dive
  angelegt. `final_score` und `llm_score` bleiben auf 0.0 / 0, bis ein
  manueller Re-Score oder der nächste Sweep-Lauf die Werte befüllt.

  **Erste Hypothesen-Kandidaten für D2:**
    H1: `.htm`-Upload erreicht Browser über MediaFileDownload, wird als
        HTML gerendert (keine CSP-Barriere für HTML-Responses).
    H2: `.htaccess`-Upload in Media-Folder aktiviert PHP-Handler für das
        Verzeichnis (setzt voraus, dass Upload-Pfad unter einem
        Apache-AllowOverride-Bereich liegt).
    H3: Doppelte Extension `exploit.php.jpg` — falls Apache MultiViews
        aktiv ist oder der Webserver PHP anhand der ersten passenden
        Extension ausführt.
    H4: Folder-Traversal via `../` im `folder`-Parameter (Flysystem 3.x
        WhitespacePathNormalizer blockt `..`, also wahrscheinlich nicht
        erreichbar — aber Probe-Run nötig zur Bestätigung).

  **Quer-Referenz:** SEC-AUDIT-001 `artifacts/security-audit/deepdive/001/context.md`
  dokumentiert den Code-Kontext für `uploadFile()` im Detail; für D1 dieses
  Tasks kann dieser Kontext als Ausgangspunkt wiederverwendet werden.
---

# SEC-AUDIT-002 — MediaFileService::uploadFile() extension/folder allowlist gap

## Triage-Kontext

- **Warum queued:** Spin-off aus SEC-AUDIT-001 Deep-Dive. Enabling-Gap für die SVG-XSS-Finding, aber breiter gelagert — MediaFileService::uploadFile() akzeptiert jeden Dateinamen und jeden Zielordner, während die Schwester-Action UploadMediaAction beide Checks enforcet. Die Inkonsistenz eröffnet weitere XSS- und potentielle File-Write-Vektoren unabhängig vom SVG-spezifischen Fix in SEC-AUDIT-001.
- **Verticals:** V4_xss (primär, via HTML-Upload), V9_arbitrary_file_write (sekundär)
- **Track-Assignment:** non-admin (Editor-Rolle via AuthEditor)

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/deepdive/002/context.md`
- generated_at: <YYYY-MM-DD HH:MM>

### Phase D2 — Hypothesen
- hypotheses_file: `artifacts/security-audit/deepdive/002/hypotheses.md`
- hypothesen_count: 0
- highest_confidence: <low|medium|high>

### Phase D3/D4 — Probe-Loop
- iteration_log:
  - iter1: <pending>

### Phase D5 — Regression
- regression_file: `layer3-integration/tests/Security/SecAudit002Test.php`
- fixture_file: `fixtures/security/payloads/sec_audit_002.json`

### Phase D6 — Fix-Draft
- fix_branch: `security-audit-002-<slug>`
- fix_commit: <hash>
- diff_size: <N lines>

### Phase D7 — Validation
- validation_file: `artifacts/security-audit/deepdive/002/validation.md`
- gesamturteil: <fix_verified | fix_rejected | validation_incomplete>

## Finding Summary

<nach Phase D7>

## Offene Punkte

- [ ] D1 Context-Extraktion ausführen (scripts/security-audit-deepdive.sh 002)
- [ ] D2 Hypothesen aus den vier Kandidaten aus `notes_for_opus` destillieren
- [ ] D3 Probe-Loop gegen H1–H4
- [ ] Fix-Strategie: Extension-Blocklist oder -Allowlist ins uploadFile() ziehen? UploadMediaAction als Referenzimplementation verwenden. Alternative: beide Upload-Pfade auf eine gemeinsame Helper-Methode reduzieren (Refactor, größerer Scope).

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-08 22:40 | queued | Spin-off aus SEC-AUDIT-001 D1/D2 — manuell angelegt |
