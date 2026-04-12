<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — E04: Nebenrecords anlegen (NOTE/SOUR/REPO/SUBM)

**Referenz:** E04 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/create-note-object` (GET Modal), `/tree/{tree}/create-note-object` (POST), `/tree/{tree}/create-source` (GET), `/tree/{tree}/create-repository` (GET) → `CreateNoteModal`, `CreateNoteAction`, `CreateSourceModal`, `CreateRepositoryModal`
**L3-Referenztest:** CreateSubrecordIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für das Anlegen von Nebenrecords (Notizen, Quellen, Archive, Übermittler) existieren bisher keine L4-Systemtests. Die L3-Tests (CreateSubrecordIntegrationTest) decken die Modal-Endpoints ab (GET→200 für Modals, POST→JSON mit XREF für Actions). Die tatsächliche Modal-Dialog-Interaktion im Browser ist nicht getestet.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/create-note-object` | GET | `CreateNoteModal` |
| `/tree/{tree}/create-note-object` | POST | `CreateNoteAction` |
| `/tree/{tree}/create-source` | GET | `CreateSourceModal` |
| `/tree/{tree}/create-repository` | GET | `CreateRepositoryModal` |

Die Handler erfordern Editor-Berechtigung. Die GET-Handler liefern HTML-Fragmente für Bootstrap-Modale. Die POST-Handler erzeugen neue GEDCOM-Records und antworten mit JSON (enthält die neue XREF).

### View-Analyse

Die Modale nutzen Bootstrap-Standard-Markup (`.modal`, `.modal-dialog`, `.modal-content`). Eingabefelder: `textarea[name="note"]` (Note), `input[name="title"]` (Source), `input[name="name"]` (Repository). Der aktive Modal-Selektor ist `.modal.show`. Die konkreten `data-bs-target`-Werte werden über Trigger-Buttons auf den Edit-Seiten ausgelöst.

### Theme-Abhängigkeit

Bootstrap-Modale sind grundsätzlich theme-übergreifend konsistent (gleiche Struktur), können aber in Styling-Details (Farben, Abstände, Button-Varianten) variieren. Theme-Loop ist sinnvoll zur Absicherung der Modal-Sichtbarkeit.

---

## L3-Referenz-Analyse

**CreateSubrecordIntegrationTest** — 4 Tests:

1. CreateNoteModal GET → 200 (Modal-HTML wird gerendert)
2. CreateNoteAction POST mit gültigem Note-Text → JSON mit XREF
3. CreateSourceModal GET → 200
4. CreateRepositoryModal GET → 200

Die L3-Tests validieren die HTTP-Ebene (Modal-Fragment-Rendering, JSON-Antwort). Es fehlt die Prüfung der tatsächlichen Modal-Interaktion: Modal öffnen, Felder ausfüllen, Submit, Modal-Schließung, XREF-Rückgabe verifizieren.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Test-Pattern für Modal-Dialog-Interaktion. Das Modal-Dialog-Interaktions-Pattern (Konzept 5) aus den übergreifenden Konzepten definiert den Ablauf: Trigger-Button → `.modal.show` warten → Felder ausfüllen → Submit → Modal-Schließung → Ergebnis prüfen.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Note-Modal öffnet sich korrekt (`.modal.show` sichtbar) | Admin | Modal wird angezeigt, Textarea für Note-Text sichtbar | Ja |
| T2 | Note anlegen via Modal-Submit, XREF zurück | Admin | Submit → JSON-Antwort mit XREF, Modal schließt sich | Ja |
| T3 | Source-Modal öffnet sich korrekt | Admin | Modal wird angezeigt, Eingabefelder für Quellen-Titel sichtbar | Ja |
| T4 | Repository-Modal öffnet sich korrekt | Admin | Modal wird angezeigt, Eingabefeld für Archiv-Name sichtbar | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Modal-Dialog-Interaktion (Konzept 5)
**Begründung:** Die Nebenrecord-Erstellung erfolgt über Bootstrap-Modale, die theme-abhängig gestylt werden. Die Interaktion folgt dem Modal-Dialog-Pattern: Trigger → Modal sichtbar → Felder ausfüllen → Submit → Schließung verifizieren. T1/T3/T4 sind Smoke-Level (Modal öffnet sich), T2 ist Spec-C (fachlicher Effekt nach Submit).

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `subrecord-create.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login (Editor-Berechtigung erforderlich) |
| **Baum** | demo |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `subrecord-create.spec.ts` [Spec-C] ✅ *(4 Tests)* |
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
