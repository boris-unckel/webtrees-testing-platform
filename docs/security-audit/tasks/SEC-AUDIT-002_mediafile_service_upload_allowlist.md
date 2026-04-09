<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-002
title: Missing extension/folder allowlist in MediaFileService::uploadFile() (inconsistent with UploadMediaAction)
created: 2026-04-08
last_updated: 2026-04-09
status: fix_verified
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
hypotheses:
  - H1_htm_upload_stored_html_injection
  - H2_htaccess_php_handler (rejected, infra-mitigated)
  - H3_double_extension (out-of-scope, same gap in UploadMediaAction)
  - H4_folder_traversal (rejected, Flysystem blocks ..)
current_hypothesis: H1
probe_iteration_count: 0
validation_failure_count: 0
fixture_rev: 0
fix_branch: security-audit-002-upload-blocklist
disclosure_state: ready_for_manual_pr
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
- generated_at: 2026-04-09
- Fokus: Disparity zwischen `MediaFileService::uploadFile()` (keine Guards) und `UploadMediaAction::handle()` (Extension-Blocklist + Folder-Allowlist). Serve-Pfad-Analyse: `imageResponse()` CSP `script-src none` blockt JS, aber HTML-Rendering/Phishing bleibt möglich.

### Phase D2 — Hypothesen
- hypotheses_file: `artifacts/security-audit/deepdive/002/hypotheses.md`
- hypothesen_count: 4 (H1 probe, H2 rejected, H3 out-of-scope, H4 rejected)
- highest_confidence: high (H1)
- H1: `.htm`-Upload → stored HTML injection/phishing (CSP blockt JS, HTML rendert)
- H2: `.htaccess`-Upload → rejected (Apache `AllowOverride None` unter `data/`)
- H3: Doppelextension `.php.jpg` → out-of-scope (UploadMediaAction hat denselben blinden Fleck)
- H4: Folder-Traversal `../` → rejected (Flysystem `WhitespacePathNormalizer` blockt `..`)

### Phase D3/D4 — Probe-Loop (Code-Read, kein Container-Probe)
- Skipping formalen Container-Probe — H1 ist vollständig per Code-Read nachvollziehbar:
  `uploadFile()` → `writeStream()` ohne Check → `MediaFileDownload` → `fileResponse()` → `imageResponse(data, text/html, ...)` → CSP `script-src none` blockt JS, HTML rendert.
- H2/H4 auf Infrastruktur/Framework-Layer geblockt.
- H3 ist UploadMediaAction-Parität, nicht SEC-AUDIT-002 Scope.

### Phase D5 — Regression
- regression_file: `upstream/webtrees/tests/app/Services/MediaFileServiceTest.php`
- test_count: 6 (4x dangerousExtensionProvider + 1x safeExtension + 1x autoRenameBypass)
- assertions: 36
- Test-first: Commit `211d309ad0` (volatile) / `7b6fb9fc8f` (Fork, GPG) — 4 Tests RED

### Phase D6 — Fix-Draft
- **fix_branch (authoritativ):** `security-audit-002-upload-blocklist` in `/home/borisunckel/phpprojects/webtrees-upstream/webtrees`, abgezweigt von Fork-`main` @ `c338276a5a`
- **Fix-Commits (authoritativ, Fork):**
  - Test: `7b6fb9fc8f` (GPG) — RED-Test für Extension-Blocklist
  - Fix: `3bb05b15d4` (GPG) — Extension-Blocklist vor writeStream
  - Bypass-Fix: `775478141e` (GPG) — Check nach auto-rename verschoben + Bypass-Test
- **Fix-Commits (volatile, non-authoritative):**
  - Test: `211d309ad0`
  - Fix: `b9681ea2f2`
  - Bypass-Fix: `4d6b61d877`
- diff_size: MediaFileService.php +8 Zeilen (Blocklist + preg_match import), MediaFileServiceTest.php +139 Zeilen (6 Tests)

### Phase D7 — Validation
- validation_artifacts: `artifacts/security-audit/deepdive/002/d7_validation/`
- Layer 1 `php -l`: OK (`php_lint.txt`)
- Layer 2 `MediaFileServiceTest`: OK (6 tests, 36 assertions) (`layer2_green.txt`)
- Code-read: Regex string-identisch mit UploadMediaAction line 93; Check nach auto-rename deckt beide Extension-Quellen ab; Caller-Handling von `return ''` korrekt.
- gesamturteil: **fix_verified**

## Finding Summary

Defense-in-depth-Fix: `MediaFileService::uploadFile()` fehlte die Extension-Blocklist, die die Schwester-Action `UploadMediaAction::handle()` bereits enforced. Ein Editor konnte `.htm`/`.html`/`.php`/etc.-Dateien über `CreateMediaObjectAction` oder `AddMediaFileAction` hochladen, obwohl die Blocklist in `UploadMediaAction` genau dies unterbinden sollte. Der Serve-Pfad (`MediaFileDownload` → `ImageFactory::imageResponse()`) setzt zwar CSP `script-src none;frame-src none`, was JS-Execution blockt — aber HTML-Rendering, Phishing-Forms und CSS-Injection bleiben möglich. Der Fix spiegelt die Extension-Blocklist in `uploadFile()` und positioniert den Check **nach** dem Auto-Rename-Block, um einen Bypass via `auto=1` zu schließen, bei dem die Extension aus `getClientFilename()` statt aus `$new_file` abgeleitet wird.

## Offene Punkte

- [x] D1 Context-Extraktion — Disparity-Analyse in `artifacts/security-audit/deepdive/002/context.md`
- [x] D2 Hypothesen — H1–H4 formalisiert in `artifacts/security-audit/deepdive/002/hypotheses.md`
- [x] D3 Probe-Loop — Code-Read genügt (kein Container-Probe, H1 vollständig nachvollziehbar)
- [x] D5 Regression — 6 Tests in `MediaFileServiceTest` (4x dangerous-ext, 1x safe-ext, 1x auto-rename-bypass)
- [x] D6 Fix — Extension-Blocklist in `uploadFile()`, nach auto-rename positioniert
- [x] D7 Validation — Layer 1+2 green, Code-read bestätigt Regex-Identität + Caller-Handling
- [x] Mirror in authoritativen Fork — Branch `security-audit-002-upload-blocklist` @ Fork-`main`

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-08 22:40 | queued | Spin-off aus SEC-AUDIT-001 D1/D2 — manuell angelegt |
| 2026-04-09 | fix_committed | Test-First `211d309ad0` + Fix `b9681ea2f2` + Bypass-Fix `4d6b61d877` im volatilen Scratch-Clone (GPG) |
| 2026-04-09 | fix_verified | Layer 1+2 green, Code-read bestätigt Fix-Wirksamkeit |
| 2026-04-09 | fix_verified (Mirror) | 3 Commits per `git cherry-pick -S` in authoritativen Fork — Test `7b6fb9fc8f`, Fix `3bb05b15d4`, Bypass-Fix `775478141e`, Branch `security-audit-002-upload-blocklist` @ Fork-`main`. Dies sind die PR-relevanten Hashes. |
