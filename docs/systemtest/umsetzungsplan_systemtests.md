<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Umsetzungsplan: Systemtests (L4 Playwright) — Runde 2

**Erstellt:** 2026-04-12
**Basis:** [`testcov_komponentenintegration_systemtests_delta.md`](../testcov_komponentenintegration_systemtests_delta.md)
**Workflow:** [`wf_code-to-systemtest_guide.md`](../wf_code-to-systemtest_guide.md)
**Übergreifende Konzepte:** [`uebergreifende_konzepte_l4.md`](uebergreifende_konzepte_l4.md)

---

## 1 Überblick

**3 Features** ohne L4-Abdeckung, identifiziert in der Delta-Analyse (Abschnitt 5).

**Vorheriger Stand:** Runde 1 abgeschlossen (Commit 679ef1b) — 29 Features, 513 Tests.
Die bestehenden 29 Testspezifikationen unter `testspezi/` und die implementierten
Specs unter `layer4-e2e/tests/` bleiben erhalten.

**Schritte pro Feature** (-> wf_code-to-systemtest_guide.md Abschnitt 2):

| Kürzel | Schritt | Beschreibung |
|---|---|---|
| S1 | Kontext lesen | `tp_overview_spec.md` — Doku-/Code-Vorgaben (einmalig/shared) |
| S2 | Feature-Analyse | Upstream-Code: Handler, Views, Routen, Auth-Anforderung |
| S3 | L3-Referenz analysieren | L3-Tests: EP/BVA-Muster, Guards, Fixtures extrahieren |
| S4 | L4-Muster analysieren | Bestehende Playwright-Specs: Patterns, Helper-Nutzung |
| S5 | Spezifikation erstellen | `testspezi/<ID>_systemtest_spezi.md` (-> Vorlage Abschnitt 5) |
| S6 | Tests implementieren | P3: Test-Coding + P4: Ausführung + Fixing |
| S7 | Doku-Update | **Je Feature einzeln** (nicht erst am Gesamtabschluss): `tds_coverage_ref.md` (L4-Spalte), `tds_conditions_ref.md` (Teststufe), `tp_ratchet_spec.md` (Endekriterien Teststufe 3), `tds_methodik_spec.md` (Verfahren) |
| S8 | Abschluss | Einzeltest grün, Konsistenzprüfung |

**S1 ist einmalig** pro Iterations-Serie.

**Gesamtabschluss** nach allen Features: Voll-Lauf (`make test-e2e`), Ratchet-Update,
Dokumenten-Konsistenzprüfung (-> wf_test-iteration_guide.md Abschnitt 10).

---

## 2 Zusammenfassung

| # | ID | Feature | Pattern | Aufwand | L3-Referenz | Spec-Datei (Vorschlag) |
|---|---|---|---|---|---|---|
| 1 | M16 | Error-Page-Rendering (403/404/500) | Spec-C (provozierte Fehler) | Niedrig | `HandleExceptionsMiddlewareIntegrationTest` (neu) | `error-handling.spec.ts` |
| 2 | A08 | Medienverwaltung Admin | Admin-Only + DataTable | Niedrig-Mittel | `AdminMediaManagementIntegrationTest` (neu) | `media-admin.spec.ts` |
| 3 | S53 | Legacy-URL-Weiterleitungen | API-Only (Redirect 301/410) | Niedrig-Mittel | `LegacyUrlRedirectIntegrationTest` (neu) | `legacy-url-redirects.spec.ts` |

**Aufwandsverteilung:** 1x Niedrig, 2x Niedrig-Mittel

---

## 3 Feature-Details

### Voraussetzung: S1 — Kontext lesen (einmalig)

| Schritt | Status | Quelle |
|---|---|---|
| S1: Kontext lesen | ⬜ | `docs/tp_overview_spec.md` -> Doku-/Code-Vorgaben, Subdokumente |

---

### 3.1 M16: Error-Page-Rendering (403/404/500)

**SUT:** `app/Http/Middleware/HandleExceptions.php` | **Priorität:** Hoch
**L3-Referenz:** `HandleExceptionsMiddlewareIntegrationTest` (noch nicht implementiert)
**Pattern:** Spec-C (provozierte Fehler) — kein Theme-Loop nötig
**Aufwand:** Niedrig | **Spec-Datei:** `error-handling.spec.ts`
**L4-Testidee:** Nicht-existierende URLs, Admin-Seiten ohne Login, ungültige XREFs aufrufen —
Error-Pages mit `.alert.alert-danger` und korrektem HTTP-Status prüfen.

**Testszenarien:**

| # | Szenario | Rolle | Erwartung |
|---|---|---|---|
| T1 | 404 — Nicht existierende URL | Visitor | HTTP 404 + `.alert.alert-danger` sichtbar |
| T2 | 403 — Admin-Seite ohne Login | Visitor | HTTP 403 oder Redirect zu Login |
| T3 | 410 — Gelöschter/nicht existierender Record | Visitor | HTTP 410 + Fehlermeldung |
| T4 | 405 — POST auf GET-only-Route | Visitor | HTTP 405 + Fehlermeldung |
| T5 | AJAX-Request mit Fehler | Visitor (XHR) | HTTP 200 + Alert im AJAX-Layout |

**Konzept:** -> `testspezi/M16_systemtest_spezi.md`

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | HandleExceptions-Middleware, Error-Views, Layouts |
| S3 | ⬜ | L3-Referenz noch nicht implementiert — Upstream-Ableitung |
| S4 | ⬜ | Referenz: `access-control.spec.ts` (Zugriffsverweigerungen) |
| S5 | ⬜ | -> `docs/systemtest/testspezi/M16_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |
| S8 | ⬜ | Einzeltest grün |

---

### 3.2 A08: Medienverwaltung Admin

**SUT:** `AdminMediaFileDownload`, `FixLevel0MediaPage/Action`, `ManageMediaPage/Action`
**Priorität:** Niedrig
**L3-Referenz:** `AdminMediaManagementIntegrationTest` (noch nicht implementiert)
**Pattern:** Admin-Only + DataTable-Verification (-> Konzept 2)
**Aufwand:** Niedrig-Mittel | **Spec-Datei:** `media-admin.spec.ts`
**L4-Testidee:** Admin-Media-Seiten laden, Formular-Interaktion (Radio-Buttons,
Folder-Auswahl), DataTable-Rendering prüfen, FixLevel0-Seite laden.

**Testszenarien:**

| # | Szenario | Rolle | Erwartung |
|---|---|---|---|
| T1 | ManageMedia-Seite lädt | Admin | HTTP 200 + `form#admin-media-form` sichtbar |
| T2 | Radio-Button "local" auswählen | Admin | DataTable lädt mit lokalen Media-Dateien |
| T3 | Radio-Button "external" auswählen | Admin | DataTable lädt mit externen Referenzen |
| T4 | FixLevel0Media-Seite lädt | Admin | HTTP 200 + DataTable-Container sichtbar |
| T5 | Non-Admin Zugriff auf /admin/media | Editor | HTTP 403 oder Redirect |
| T6 | Subfolder-Filter umschalten | Admin | DataTable refresht mit gefilterten Daten |

**Konzept:** -> `testspezi/A08_systemtest_spezi.md`

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | 5 Handler, Admin-Routes, DataTable-Views |
| S3 | ⬜ | L3-Referenz noch nicht implementiert — Upstream-Ableitung |
| S4 | ⬜ | Referenz: `user-admin.spec.ts`, `tree-management.spec.ts` |
| S5 | ⬜ | -> `docs/systemtest/testspezi/A08_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |
| S8 | ⬜ | Einzeltest grün |

---

### 3.3 S53: Legacy-URL-Weiterleitungen

**SUT:** ~27 `Redirect*Php`-Handler | **Priorität:** Niedrig
**L3-Referenz:** `LegacyUrlRedirectIntegrationTest` (noch nicht implementiert)
**Pattern:** API-Only (Redirect 301/410-Prüfung) (-> Konzept 3)
**Aufwand:** Niedrig-Mittel | **Spec-Datei:** `legacy-url-redirects.spec.ts`
**L4-Testidee:** Legacy-URLs aufrufen, HTTP 301 + Location-Header prüfen.
Ungültige XREFs → HTTP 410. Stichprobe von 5–8 repräsentativen Handlern.

**Testszenarien:**

| # | Szenario | Route | Erwartung |
|---|---|---|---|
| T1 | Individual Redirect (gültig) | `/individual.php?ged=demo&pid=I1` | HTTP 301 + Location enthält `/tree/demo/individual/I1` |
| T2 | Individual Redirect (ungültig) | `/individual.php?ged=demo&pid=INVALID_XREF` | HTTP 410 Gone |
| T3 | Family Redirect | `/family.php?ged=demo&famid=F1` | HTTP 301 + Location |
| T4 | Source Redirect | `/source.php?ged=demo&sid=S1` | HTTP 301 + Location |
| T5 | Calendar Redirect | `/calendar.php?ged=demo&view=month` | HTTP 301 + Location |
| T6 | Pedigree Redirect | `/pedigree.php?ged=demo&rootid=I1` | HTTP 301 + Location |
| T7 | Tree nicht gefunden | `/individual.php?ged=INVALID_TREE&pid=I1` | HTTP 410 Gone |
| T8 | Canonical Link Header | `/individual.php?ged=demo&pid=I1` | `Link: <...>; rel="canonical"` |

**Konzept:** -> `testspezi/S53_systemtest_spezi.md`

| Schritt | Status | Notizen |
|---|---|---|
| S1 | ⬜ | shared |
| S2 | ⬜ | 27 Redirect-Handler, einheitliches Pattern |
| S3 | ⬜ | L3-Referenz noch nicht implementiert — Upstream-Ableitung |
| S4 | ⬜ | Referenz: `security-headers.spec.ts` (API-Only-Pattern) |
| S5 | ⬜ | -> `docs/systemtest/testspezi/S53_systemtest_spezi.md` |
| S6 | ⬜ | Coding + Ausführung |
| S7 | ⬜ | `tds_coverage_ref.md`, `tds_conditions_ref.md`, `tp_ratchet_spec.md`, `tds_methodik_spec.md` |
| S8 | ⬜ | Einzeltest grün |

---

## 4 Empfohlene Implementierungsreihenfolge

| Runde | Feature | Pattern | Begründung |
|---|---|---|---|
| R1 | M16 | Spec-C (Error-Pages) | Hoch-Prio, niedrigster Aufwand |
| R2 | S53 | API-Only (Legacy-Redirects) | Niedrig-Prio, aber L3-Synergie |
| R3 | A08 | Admin-Only (Media-Admin) | Niedrig-Prio, DataTable-Komplexität |

---

## 5 Referenz: Abgeschlossene Features (Runde 1)

Die folgenden 29 Features wurden in Runde 1 abgeschlossen (Commit 679ef1b,
513 Tests). Ihre Testspezifikationen liegen weiterhin unter `testspezi/`.

| ID | Feature | Spec-Datei | Status |
|---|---|---|---|
| E01 | Person/Familie anlegen | `person-family-create.spec.ts` | ✅ |
| E02 | Fakten bearbeiten | `fact-edit.spec.ts` | ✅ |
| E03 | Rohdaten-Edit | `raw-gedcom-edit.spec.ts` | ✅ |
| E04 | Nebenrecords | `subrecord-create.spec.ts` | ✅ |
| E05 | Medienobjekte | `media-object.spec.ts` | ✅ |
| E06 | Sortierung | `reorder.spec.ts` | ✅ |
| E08 | TomSelect/AutoComplete | `tomselect-autocomplete.spec.ts` | ✅ |
| S05 | Erweiterte Suche (Felder) | `advanced-search-execution.spec.ts` | ✅ |
| S06 | Erweiterte Suche (Datum) | `advanced-search-execution.spec.ts` | ✅ |
| S07 | Phonetische Suche (Russell) | `phonetic-search-execution.spec.ts` | ✅ |
| S08 | Phonetische Suche (DM) | `phonetic-search-execution.spec.ts` | ✅ |
| S10 | Paginierung | `search-pagination.spec.ts` | ✅ |
| S16 | Beziehungsfinder | `relationship-chart.spec.ts` | ✅ |
| S18 | Charts (5 Typen) | `chart-types.spec.ts` | ✅ |
| S41 | Statistikdaten | `statistics-page.spec.ts` | ✅ |
| S46 | Homepage-Blöcke | `homepage-blocks.spec.ts` | ✅ |
| S47 | Interaktiver Stammbaum | `interactive-tree.spec.ts` | ✅ |
| S50 | Hilfetexte | `help-texts.spec.ts` | ✅ |
| P30 | Datensatz-Zusammenführung (Auswahl) | `merge-records.spec.ts` | ✅ |
| P37 | Benutzer-Bearbeitung (Admin) | `user-edit-admin.spec.ts` | ✅ |
| P38 | Account-Selbstverwaltung | `account-self-management.spec.ts` | ✅ |
| P40 | Änderungsverwaltung | `pending-changes.spec.ts` | ✅ |
| P41 | Datensatz-Zusammenführung (vollständig) | `merge-records.spec.ts` | ✅ |
| A01 | Stammbaum-Management | `tree-management.spec.ts` | ✅ |
| A04 | Stammbaum-Präferenzen | `tree-preferences.spec.ts` | ✅ |
| A05 | Modul-Konfiguration | `module-configuration.spec.ts` | ✅ |
| A07 | Benutzerverwaltung Admin | `user-admin.spec.ts` | ✅ |
| K01 | Kontaktformular | `contact-form.spec.ts` | ✅ |
| K02 | Benutzer-Nachrichten | `user-messages.spec.ts` | ✅ |
