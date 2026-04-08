<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-004
title: Audit other serve paths that deliver SVG bypassing ImageFactory::imageResponse()
created: 2026-04-08
last_updated: 2026-04-08
status: queued
track: non-admin
file: app/Factories/ImageFactory.php
contributing_files:
  - app/Factories/ImageFactory.php
  - app/Http/RequestHandlers/MediaFileDownload.php
  - app/Http/RequestHandlers/MediaFileThumbnail.php
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
    - <to be enumerated in D1>
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
  Spin-off aus SEC-AUDIT-001 D7-Validation (artifacts/security-audit/deepdive/001/validation.md §Folge-Aktionen).

  **Scope dieser Task ist eine Code-Suche**, kein konkretes Finding.
  Ausgangspunkt: Der SEC-AUDIT-001-Fix härtet genau einen Pfad —
  `ImageFactory::imageResponse()` (über `fileResponse()` aufgerufen aus
  `MediaFileDownload`). Der DOM-Walker
  `svgContainsActiveContent()` blockt dort bekannte SVG-XSS-Varianten.

  **Offene Frage:** Gibt es **andere** Serve-Pfade in webtrees, die
  SVG-Daten aus dem Media-Filesystem (oder anderen benutzerkontrollierten
  Quellen) an den Browser ausliefern, ohne durch `imageResponse()` zu
  laufen? Wenn ja, sind diese Pfade dann ebenfalls durch den L1-Blocker
  geschützt, oder umgehen sie ihn?

  **Kandidaten für die Suche (Startpunkte, nicht abschließend):**
    1. `app/Http/RequestHandlers/MediaFileThumbnail.php` — Thumbnail-Pfad;
       verwendet vermutlich `thumbnailResponse()` oder ruft `imageResponse()` auf
    2. `app/Http/RequestHandlers/MediaFileDownload.php` — Haupt-Pfad für
       `/media-thumbnail/...` — bereits durch den SEC-AUDIT-001-Fix abgedeckt
    3. Suche nach allen Aufrufstellen von
       `Tree::mediaFilesystem()` in Kombination mit Response-Rückgabe
       (grep `mediaFilesystem` in `app/Http/RequestHandlers` und `app/Services`)
    4. Suche nach allen Direkt-Responses mit `image/svg+xml` Content-Type
       (grep `image/svg+xml` in `app/`)
    5. Report-/PDF-Export-Pfade, die SVG einbetten (z.B. Statistik-Charts) —
       unwahrscheinlich benutzerkontrolliert, aber prüfen
    6. Module-Pfade, die eigene Media-Delivery implementieren (z.B. Album-
       oder Gallery-Module) — eventuelles Bypass-Potential
    7. `assets/`-Auslieferung statischer SVGs (theme-icons etc.) — wahrscheinlich
       irrelevant, da nicht benutzerkontrolliert, aber zur Vollständigkeit prüfen

  **Kriterium für „Serve-Pfad von Interesse":**
    - Quelle: Benutzerkontrollierter Inhalt (Upload, Media-Filesystem,
      DB-Blob, externe URL)
    - Senke: HTTP-Response mit Content-Type `image/svg+xml` (oder ein
      Content-Type, den der Browser als SVG rendert)
    - Pfad: Durchläuft `ImageFactory::svgContainsActiveContent()` **nicht**

  **Erwartetes Ergebnis dieser Task:**
    - Liste aller gefundenen Serve-Pfade (Datei:Line)
    - Pro Pfad: Verweist der Pfad auf `imageResponse()` oder nicht?
    - Falls nicht: Eigenes Follow-Up-Ticket SEC-AUDIT-<NNN> mit konkretem
      Hypothesen-Set für einen Deep-Dive

  **Dies ist explizit eine Search-&-Enumerate-Task**, kein klassischer
  Deep-Dive mit Exploit-Hypothesen. Wenn die Enumeration keine weiteren
  Pfade findet, wird die Task mit `status: no_finding` geschlossen —
  was dann den Dokumentationswert hat, dass nach SEC-AUDIT-001 kein
  weiterer Serve-Pfad gefunden wurde.

  **Quer-Referenzen:**
  - SEC-AUDIT-001: `docs/security-audit/tasks/SEC-AUDIT-001_svg_xss_media_upload.md`
  - SEC-AUDIT-001 Validation §Folge-Aktionen:
    `artifacts/security-audit/deepdive/001/validation.md`
  - Fix-Commit (Fork): `b2dc869b90407bb5129dbd768c9364dc863482b2`
---

# SEC-AUDIT-004 — Audit weiterer SVG-Serve-Pfade außerhalb imageResponse()

## Triage-Kontext

- **Warum queued:** Spin-off aus SEC-AUDIT-001 D7-Validation. Vollständigkeits-Audit: Nach dem SEC-AUDIT-001-Fix ist `ImageFactory::imageResponse()` gegen SVG-XSS gehärtet, aber es ist unklar, ob es weitere Pfade gibt, die SVG-Daten an den Browser liefern, ohne den neuen DOM-Blocker zu durchlaufen.
- **Verticals:** V4_xss (bedingt — nur falls ungeschützte Serve-Pfade gefunden werden)
- **Track-Assignment:** non-admin (SVG-Serving ist grundsätzlich Visitor-erreichbar)
- **Task-Typ:** Search-&-Enumerate (kein klassischer Deep-Dive mit Probe-Loop)

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/deepdive/004/context.md`
- generated_at: <YYYY-MM-DD HH:MM>
- Fokus: Enumeration aller Serve-Pfade statt Single-Function-Analyse

### Phase D2 — Hypothesen
- hypotheses_file: `artifacts/security-audit/deepdive/004/hypotheses.md`
- hypothesen_count: 0
- highest_confidence: <low|medium|high>
- Bemerkung: Hypothesen werden **pro gefundenen Serve-Pfad** gebildet; wenn kein Pfad gefunden → no_finding

### Phase D3/D4 — Probe-Loop
- iteration_log:
  - iter1: <pending — wird nur ausgeführt, wenn D2 konkrete Hypothesen liefert>

### Phase D5 — Regression
- regression_file: `layer3-integration/tests/Security/SecAudit004Test.php`
- fixture_file: `fixtures/security/payloads/sec_audit_004.json`
- Bemerkung: Regression nur sinnvoll, wenn ein konkreter zweiter Serve-Pfad gefunden und gehärtet wird

### Phase D6 — Fix-Draft
- fix_branch: `security-audit-004-<slug>`
- fix_commit: <hash>
- diff_size: <N lines>

### Phase D7 — Validation
- validation_file: `artifacts/security-audit/deepdive/004/validation.md`
- gesamturteil: <fix_verified | no_finding | validation_incomplete>

## Finding Summary

<nach Phase D7 — entweder Liste gefundener ungeschützter Serve-Pfade, oder no_finding mit Enumeration-Ergebnis>

## Offene Punkte

- [ ] D1 Context: Enumerate alle Serve-Pfade für SVG-Daten
  - [ ] grep `image/svg+xml` in `app/`
  - [ ] grep `mediaFilesystem` in `app/Http/RequestHandlers` und `app/Services`
  - [ ] grep `response(.*svg` in `app/`
  - [ ] Module-Scan: `modules_v4/*/` für custom media delivery
- [ ] D2: Pro gefundenen Pfad entscheiden: durchläuft `svgContainsActiveContent()` oder nicht?
- [ ] D3: Für jeden ungeschützten Pfad: konkrete XSS-Probe konstruieren (parallel zu SEC-AUDIT-001 H1/H2/H3)
- [ ] D5: Regressionstests für jeden neu gefundenen Pfad
- [ ] D6: Fix-Strategie: pro Pfad einzeln härten, oder zentrale Serve-Funktion in ImageFactory refactoren?
- [ ] Falls keine weiteren Pfade gefunden werden: Task als `no_finding` schließen mit dokumentierter Enumeration

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-08 22:40 | queued | Spin-off aus SEC-AUDIT-001 D7-Validation — manuell angelegt |
