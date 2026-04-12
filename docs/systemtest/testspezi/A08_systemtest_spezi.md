<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — A08: Medienverwaltung Admin

**Referenz:** A08 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/admin/media` → `ManageMediaPage`, `/admin/fix-level-0-media` → `FixLevel0MediaPage`
**L3-Referenztest:** `AdminMediaManagementIntegrationTest` (noch nicht implementiert)
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L4-Tests für Admin-Medienverwaltung. `ManageMediaDataIntegrationTest.php` deckt
S49 (API-Endpoint für DataTable-Daten) ab, nicht A08 (Admin-UI-Seite).
Die Admin-Media-Seiten sind vollständig ungetestet auf L4-Ebene.

---

## Upstream-Analyse

### Route und Handler

| Route | Method | Handler | Auth |
|---|---|---|---|
| `/admin/media` | GET | `ManageMediaPage` | Admin |
| `/admin/media` | POST | `ManageMediaAction` | Admin |
| `/admin/media-data` | GET | `ManageMediaData` (AJAX) | Admin |
| `/admin/fix-level-0-media` | GET | `FixLevel0MediaPage` | Admin |
| `/admin/fix-level-0-media` | POST | `FixLevel0MediaAction` | Admin |
| `/admin/fix-level-0-media-data` | GET | `FixLevel0MediaData` (AJAX) | Admin |
| `/admin/media-file` | GET | `AdminMediaFileDownload` | Admin |

Alle Routen erfordern `AuthAdministrator`-Middleware (Route-Gruppe `/admin/`).

### View-Analyse

**ManageMedia-Seite** (`admin/media.phtml`):
- Formular: `form#admin-media-form`
- Radio-Buttons: `input[name="files"]` (Werte: `local`, `external`, `unused`)
- Select: `select[name="media_folder"]`
- Radio-Buttons: `input[name="subfolders"]` (Werte: `include`, `exclude`)
- DataTable: `table#wt-datatables-admin-media`
- Modal: `view('modals/create-media-from-file')`

**FixLevel0Media-Seite** (`admin/fix-level-0-media.phtml`):
- DataTable: `table#wt-datatables-fix-level-0-media`
- Fix-Buttons: `button.wt-fix-button` (mit `data-confirm`, `data-fact-id` etc.)

### Theme-Abhängigkeit

**Optional** — Admin-Seiten nutzen `layouts/administration`, das ein eigenes CSS
(`administration.min.css`) lädt. Die Kernfunktionalität ist Bootstrap-basiert und
nicht theme-sensitiv. Theme-Loop ist nicht empfohlen.

---

## L3-Referenz-Analyse

L3-Test noch nicht implementiert. Die Szenarien werden direkt aus dem Upstream-Code
abgeleitet. Die Handler-Logik ist:
- `ManageMediaPage`: Validierung der Radio-Button-Werte, Folder-Listing → View-Response
- `ManageMediaAction`: POST → Redirect zu ManageMediaPage mit validierten Parametern
- `FixLevel0MediaPage`: Einfache View-Response (immer 200)
- `FixLevel0MediaAction`: GEDCOM-Fact-Updates basierend auf XREF-Paaren

---

## Bestehende L4-Muster-Analyse

- `user-admin.spec.ts`: Admin-Only-Pattern, Seite laden + Content-Assertions.
- `tree-management.spec.ts`: Admin-Formular-Interaktion, POST + Redirect.
- `upload-validation.spec.ts`: Datei-Upload-Pattern (für Medien relevant).

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | ManageMedia-Seite lädt | Admin | HTTP 200, `form#admin-media-form` sichtbar | Nein |
| T2 | Radio "local" auswählen + Submit | Admin | DataTable zeigt lokale Media-Dateien | Nein |
| T3 | Radio "external" auswählen + Submit | Admin | DataTable zeigt externe Referenzen | Nein |
| T4 | Radio "unused" auswählen + Submit | Admin | DataTable zeigt unbenutzte Dateien | Nein |
| T5 | FixLevel0Media-Seite lädt | Admin | HTTP 200, DataTable-Container sichtbar | Nein |
| T6 | Non-Admin Zugriff auf /admin/media | Visitor | HTTP 403 oder Redirect zu Login | Nein |
| T7 | Subfolder-Filter umschalten | Admin | DataTable refresht | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only (→ wf_code-to-systemtest_guide.md 4.5)
mit DataTable-Verification (→ uebergreifende_konzepte_l4.md Abschnitt 2).

**Begründung:** Alle Routen erfordern Admin-Auth. Die Hauptkomplexität liegt
in der DataTable-AJAX-Interaktion nach Formular-Änderungen.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `media-admin.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `auth` (ADMIN_PASSWORD) |
| **Theme-Loop** | Nein |
| **Login-Strategie** | Admin |
| **Baum** | `demo` |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `media-admin.spec.ts [Spec-C] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2, 3` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 3 prüfen |
| `docs/tds_methodik_spec.md` | Ggf. DataTable-Verification als neues Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
