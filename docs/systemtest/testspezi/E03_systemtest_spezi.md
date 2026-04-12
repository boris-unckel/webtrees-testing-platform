<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — E03: Rohdaten-Edit (Raw GEDCOM)

**Referenz:** E03 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/edit-raw-record/{xref}` (GET), `/tree/{tree}/edit-raw-fact/{xref}` (POST) → `EditRawRecordPage`, `EditRawFactPage`, `EditRawFactAction`
**L3-Referenztest:** EditRawGedcomIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Rohdaten-Bearbeitung (Raw GEDCOM Editor) existieren bisher keine L4-Systemtests. Die L3-Tests (EditRawGedcomIntegrationTest) decken Guard-Verhalten und grundlegendes Rendering ab. Der Raw-Editor ist eine Admin-Funktion, die direkten GEDCOM-Text in einer Textarea bearbeiten lässt.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/edit-raw-record/{xref}` | GET | `EditRawRecordPage` |
| `/tree/{tree}/edit-raw-fact/{xref}` | GET | `EditRawFactPage` |
| `/tree/{tree}/edit-raw-fact/{xref}` | POST | `EditRawFactAction` |

Die Handler erfordern Admin-Berechtigung. `EditRawRecordPage` zeigt den vollständigen GEDCOM-Record in einer Textarea. `EditRawFactPage` zeigt einen einzelnen Fakt als GEDCOM-Text. `EditRawFactAction` speichert die Änderung.

### View-Analyse

Die Seite enthält eine `<textarea>` mit dem rohen GEDCOM-Text des Records. Der Editor zeigt den vollständigen GEDCOM-Block (Level 0–n). Relevante Selektoren: `textarea` (GEDCOM-Inhalt), Submit-Button. Das Layout ist minimalistisch und kaum theme-abhängig.

### Theme-Abhängigkeit

Der Raw-Editor ist eine Admin-Funktion mit minimalem Theme-Einfluss. Die Textarea und der Submit-Button sind in allen Themes funktional identisch. Kein Theme-Loop erforderlich.

---

## L3-Referenz-Analyse

**EditRawGedcomIntegrationTest** — 3 Tests:

1. EditRawFactPage mit unbekannter fact_id → 302 (Redirect, Fakt nicht gefunden)
2. EditRawRecordPage mit gültigem XREF → 200 (Seite wird gerendert)
3. EditRawFactAction POST mit unbekanntem XREF → 302 (Redirect, Guard)

Die L3-Tests prüfen primär Guard-Verhalten und grundlegendes Seiten-Rendering. Es fehlt die Prüfung, ob die Textarea den korrekten GEDCOM-Inhalt zeigt und ob eine Bearbeitung korrekt gespeichert wird.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Test-Pattern für Raw-GEDCOM-Bearbeitung. Das Admin-Only-Pattern (wf_code-to-systemtest_guide.md 4.5) in Kombination mit Formular-Submit-Verification (Konzept 1) ist anwendbar. Kein Theme-Loop nötig.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Raw-Edit-Seite lädt für bekannten Record (textarea mit GEDCOM sichtbar) | Admin | Seite lädt, Textarea enthält GEDCOM-Text (beginnt mit Level-0-Tag) | Nein |
| T2 | GEDCOM bearbeiten und speichern, Änderung auf Personenseite sichtbar | Admin | POST→302, Redirect, geänderte Daten auf Personenseite sichtbar | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only + Formular-Submit-Verification (Konzept 1)
**Begründung:** Der Raw-Editor ist eine reine Admin-Funktion ohne signifikante Theme-Abhängigkeit. Die Interaktion folgt dem Formular-Submit-Pattern: Textarea laden, GEDCOM-Text modifizieren, Submit, Ergebnis verifizieren. Kein Theme-Loop, da die Textarea in allen Themes identisch rendert.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `raw-gedcom-edit.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein (Admin-Seite, nicht theme-abhängig im Kern) |
| **Login-Strategie** | Admin-Login (Admin-Berechtigung erforderlich) |
| **Baum** | demo (XREF X1030) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `raw-gedcom-edit.spec.ts` [Spec-C] ✅ *(2 Tests)* |
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
