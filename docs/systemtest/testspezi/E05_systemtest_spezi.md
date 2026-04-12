<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — E05: Medienobjekte anlegen & verknüpfen

**Referenz:** E05 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/create-media-object` (GET Modal), `/tree/{tree}/link-media-to-record` (POST) → `CreateMediaObjectModal`, `LinkMediaToRecordAction`, `LinkMediaToIndividualModal`
**L3-Referenztest:** MediaObjectIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für das Anlegen und Verknüpfen von Medienobjekten existieren bisher keine L4-Systemtests. Die L3-Tests (MediaObjectIntegrationTest) decken Modal-Rendering und Link-Aktionen auf HTTP-Ebene ab. Die tatsächliche Datei-Upload-Interaktion und Media-Verknüpfung im Browser sind nicht getestet.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/create-media-object` | GET | `CreateMediaObjectModal` |
| `/tree/{tree}/create-media-object` | POST | `CreateMediaObjectAction` |
| `/tree/{tree}/link-media-to-record` | POST | `LinkMediaToRecordAction` |
| `/tree/{tree}/link-media-to-individual` | GET | `LinkMediaToIndividualModal` |

Die Handler erfordern Editor-Berechtigung. `CreateMediaObjectModal` zeigt ein Upload-Formular (Dateiauswahl, Titel, Typ). `LinkMediaToRecordAction` verknüpft ein bestehendes Medienobjekt mit einem INDI-/FAM-Record. `LinkMediaToIndividualModal` zeigt ein Modal zur Auswahl der Zielperson.

### View-Analyse

Das Media-Modal enthält: `input[type="file"]` (Datei-Upload), `input[name="title"]` (Titel), `select[name="type"]` (Medientyp: photo, document etc.). Das Link-Modal enthält ein TomSelect-Widget zur Personenauswahl. Relevanter Upload-Mechanismus: `setInputFiles()` in Playwright.

### Theme-Abhängigkeit

Die Modale sind Bootstrap-basiert und in allen Themes strukturell identisch. Das Upload-Feld und die Media-Anzeige auf der Personenseite können theme-abhängig variieren. Theme-Loop empfohlen.

---

## L3-Referenz-Analyse

**MediaObjectIntegrationTest** — 3 Tests:

1. CreateMediaObjectModal GET → 200 (Modal-HTML wird gerendert)
2. LinkMediaToRecordAction POST mit gültigem XREF → 302 (Redirect, Verknüpfung erstellt)
3. LinkMediaToIndividualModal GET → 200 (Modal-HTML wird gerendert)

Die L3-Tests validieren die HTTP-Ebene. Es fehlt die Prüfung des Datei-Uploads, der Media-Erstellung und der Sichtbarkeit des Medienobjekts auf der Personenseite.

---

## Bestehende L4-Muster-Analyse

**Referenz-Spec:** `upload-validation.spec.ts` — enthält das Datei-Upload-Pattern mit `setInputFiles()`. Dieses Pattern ist direkt übertragbar für den Media-Upload. Die Modal-Dialog-Interaktion folgt dem Konzept 5 aus den übergreifenden Konzepten.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Media-Modal öffnet sich korrekt | Admin | Modal wird angezeigt, Datei-Upload-Feld und Titel-Feld sichtbar | Ja |
| T2 | Media-Datei hochladen und verknüpfen, Media auf Personenseite sichtbar | Admin | Upload via `setInputFiles()`, Submit, Medienobjekt auf Personenseite sichtbar | Ja |
| T3 | Link-Media-Modal öffnet sich mit XREF | Admin | Modal wird angezeigt, TomSelect-Widget zur Personenauswahl sichtbar | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Modal-Dialog-Interaktion (Konzept 5)
**Begründung:** Die Media-Erstellung und -Verknüpfung erfolgt über Bootstrap-Modale mit Datei-Upload. Die Interaktion kombiniert das Modal-Dialog-Pattern (Konzept 5) mit dem Datei-Upload-Pattern aus `upload-validation.spec.ts`. T1/T3 sind Smoke-Level (Modal öffnet sich), T2 ist Spec-C (Datei-Upload und fachliche Verknüpfung).

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `media-object.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper, `setInputFiles` (Playwright-API) |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login (Editor-Berechtigung erforderlich) |
| **Baum** | demo (XREF X247 Medienobjekt, X1030 Person) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `media-object.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
