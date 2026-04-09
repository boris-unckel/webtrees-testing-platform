<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

---
id: SEC-AUDIT-004
title: Audit other serve paths that deliver SVG bypassing ImageFactory::imageResponse()
created: 2026-04-08
last_updated: 2026-04-09
status: no_finding
track: non-admin
file: app/Factories/ImageFactory.php
contributing_files:
  - app/Factories/ImageFactory.php
  - app/Http/RequestHandlers/MediaFileDownload.php
  - app/Http/RequestHandlers/MediaFileThumbnail.php
  - app/Http/RequestHandlers/AdminMediaFileDownload.php
  - app/Http/RequestHandlers/AdminMediaFileThumbnail.php
  - app/Services/GedcomExportService.php
  - app/Report/HtmlRenderer.php
  - app/Report/ReportParserGenerate.php
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
    - GET /media-{filename} (MediaFileDownload)
    - GET /media-thumbnail-{...} (MediaFileThumbnail)
    - GET /admin/media-file-download (AdminMediaFileDownload)
    - GET /admin/media-file-thumbnail (AdminMediaFileThumbnail)
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
disclosure_state: not_applicable
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
  - Fix-Commit (Fork): `c15b95fef48b9ee96eb0a77bfe9a48d7dbbfb3be` (branch `security-audit-001-svg-filter-hardening-clean`)
---

# SEC-AUDIT-004 — Audit weiterer SVG-Serve-Pfade außerhalb imageResponse()

## Triage-Kontext

- **Warum queued:** Spin-off aus SEC-AUDIT-001 D7-Validation. Vollständigkeits-Audit: Nach dem SEC-AUDIT-001-Fix ist `ImageFactory::imageResponse()` gegen SVG-XSS gehärtet, aber es ist unklar, ob es weitere Pfade gibt, die SVG-Daten an den Browser liefern, ohne den neuen DOM-Blocker zu durchlaufen.
- **Verticals:** V4_xss (bedingt — nur falls ungeschützte Serve-Pfade gefunden werden)
- **Track-Assignment:** non-admin (SVG-Serving ist grundsätzlich Visitor-erreichbar)
- **Task-Typ:** Search-&-Enumerate (kein klassischer Deep-Dive mit Probe-Loop)

## Analyse-Verlauf

### Phase D1 — Context
- context_file: `artifacts/security-audit/sec-audit-004/enumeration.md`
- generated_at: 2026-04-09
- Fokus: Enumeration aller Serve-Pfade statt Single-Function-Analyse

### Phase D2 — Hypothesen
- hypothesen_count: 0 (keine Hypothesen gebildet, da Enumeration keinen Bypass-Pfad aufgedeckt hat)
- Bemerkung: Diese Task ist Search-&-Enumerate; Hypothesen werden pro gefundenem Bypass-Pfad gebildet. Kein Pfad gefunden → keine Hypothesen.

### Phase D3/D4 — Probe-Loop (nicht erforderlich)
Skipping — keine Bypass-Pfade zu sondieren.

### Phase D5 — Regression (nicht erforderlich)
Skipping — ohne Bypass-Pfad kein Regressions-Contract. Der bestehende Contract (alle SVG-Responses durchlaufen ImageFactory) wird implizit durch SEC-AUDIT-001 + SEC-AUDIT-003 Tests gehalten.

### Phase D6 — Fix-Draft (nicht erforderlich)
Skipping — kein Fix erforderlich.

### Phase D7 — Validation
- validation_artifacts: `artifacts/security-audit/sec-audit-004/enumeration.md`
- gesamturteil: **no_finding** — Enumeration belegt, dass `ImageFactory::imageResponse()` / `replacementImageResponse()` der einzige SVG-Serve-Choke-Point in webtrees ist. Keine weitere Härtung erforderlich.

## Finding Summary

Nach SEC-AUDIT-001 (SVG-XSS Blocker in `imageResponse()`) und SEC-AUDIT-003
(CSP-Header-Symmetrie in `replacementImageResponse()`) ist der gesamte
`ImageFactory`-Response-Pfad gehärtet. Die Enumeration in `enumeration.md`
zeigt, dass **alle** Serve-Pfade mit `Content-Type: image/svg+xml` durch
`ImageFactory` laufen:

- `MediaFileDownload` / `MediaFileThumbnail` / `AdminMediaFileDownload` /
  `AdminMediaFileThumbnail` → direkt `ImageFactory`
- `GedcomExportService` → liefert nur ZIP, keine direkte SVG-Response
- `HtmlRenderer::createImage` → Regex-Gate `(jpg|jpeg|png|gif)` blockt SVG
- `HtmlRenderer::createImageFromObject` → Intervention (raster-only) blockt SVG
- `ReportParserGenerate` highlighted-image → `imagecreatefromstring` (GD raster-only) blockt SVG
- `StatisticsChartModule` → returniert HTML-Views, keine SVG-Bytes
- `app/Module/*.php` → 0 Treffer für `svg`
- `modules_v4/*` → nur Test-Module

Sekundär-Observation (nicht SEC-AUDIT-004 Scope): `HtmlRenderer::createImage`
hat einen latenten Render-Bug (computed `$src` data-URI wird nicht an
`ReportHtmlImage` übergeben — stattdessen ein lokaler Dateisystem-Pfad).
Dies ist ein Qualitäts-Bug im HTML-Report-Pfad, kein Security-Befund.

## Offene Punkte

- [x] D1 Context: Enumerate alle Serve-Pfade für SVG-Daten
- [x] grep `image/svg+xml` in `app/` — nur Mime/ImageFactory/CompressResponse
- [x] grep `mediaFilesystem` in `app/Http/RequestHandlers` und `app/Services` — alle abgedeckt
- [x] grep `response(.*svg` in `app/` — 0 Treffer außerhalb ImageFactory
- [x] Module-Scan: `modules_v4/*/` und `app/Module/*.php` — 0 SVG-Treffer
- [x] D2: Pro gefundenen Pfad entscheiden — alle Pfade gehen durch ImageFactory oder erreichen SVG nie
- [x] Task als `no_finding` schließen mit dokumentierter Enumeration

## Rückkopplung

### Status-Lifecycle (dieser Task)
| Zeitpunkt | Status | Grund |
|---|---|---|
| 2026-04-08 22:40 | queued | Spin-off aus SEC-AUDIT-001 D7-Validation — manuell angelegt |
| 2026-04-09 | no_finding | Enumeration in `artifacts/security-audit/sec-audit-004/enumeration.md` belegt: alle SVG-Serve-Pfade flowen durch `ImageFactory::imageResponse()` bzw. `replacementImageResponse()`. Keine Bypass-Pfade, kein Fix nötig. |
