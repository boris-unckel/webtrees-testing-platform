<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — P40: Änderungsverwaltung (Pending Changes Workflow)

**Referenz:** P40 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/pending` (GET Page), `/tree/{tree}/pending/accept/{xref}` (POST), `/tree/{tree}/pending/reject/{xref}` (POST) → `PendingChanges`, `PendingChangesAcceptChange`/`AcceptRecord`, `PendingChangesRejectChange`/`RejectRecord`
**L3-Referenztest:** PendingChangesIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Änderungsverwaltung (Pending-Changes-Workflow) existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (PendingChangesIntegrationTest) decken die Handler-Ebene ab (3 Tests: Accept unknown XREF→204, Reject unknown XREF→204, Page GET→200), prüfen aber nicht den vollständigen Multi-Role-Workflow: Editor erzeugt Änderung → Moderator sieht/akzeptiert/verwirft Änderung. Dieser Workflow erfordert Login-Wechsel und rollenabhängige Sichtbarkeit.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/pending` | GET | `PendingChanges` |
| `/tree/{tree}/pending/accept/{xref}` | POST | `PendingChangesAcceptChange` |
| `/tree/{tree}/pending/accept-all` | POST | `PendingChangesAcceptRecord` |
| `/tree/{tree}/pending/reject/{xref}` | POST | `PendingChangesRejectChange` |
| `/tree/{tree}/pending/reject-all` | POST | `PendingChangesRejectRecord` |

PendingChanges (GET) erfordert Moderator-Berechtigung. Accept/Reject-Handler erfordern ebenfalls Moderator-Berechtigung. Der GET-Handler zeigt ausstehende Änderungen mit Diff-Ansicht (alte vs. neue GEDCOM-Daten). Die POST-Handler akzeptieren oder verwerfen einzelne Änderungen bzw. alle Änderungen eines Records.

### View-Analyse

Die Pending-Changes-Seite zeigt eine Liste ausstehender Änderungen mit: Record-XREF, Typ der Änderung (neu/geändert/gelöscht), Diff-Ansicht, Accept/Reject-Buttons pro Änderung. Die Darstellung ist tabellarisch mit Bootstrap-Styling.

### Theme-Abhängigkeit

Kein Theme-Loop erforderlich. Der Pending-Changes-Workflow ist ein Rollen-basierter Verwaltungsworkflow, dessen Fokus auf korrekter Rollen-Interaktion liegt, nicht auf theme-abhängigem Rendering.

---

## L3-Referenz-Analyse

**PendingChangesIntegrationTest** — 3 Tests:

1. Accept mit unbekanntem XREF → 204 (No Content, kein Fehler)
2. Reject mit unbekanntem XREF → 204 (No Content, kein Fehler)
3. PendingChanges GET → 200 (Seite wird gerendert)

Die L3-Tests validieren die HTTP-Ebene (Statuscodes). Sie prüfen nicht den vollständigen Workflow: Änderung erzeugen (als Editor) → Änderung sehen (als Moderator) → Änderung akzeptieren/verwerfen → Ergebnis verifizieren. Insbesondere fehlt der Multi-Role-Aspekt (Login-Wechsel zwischen Editor und Moderator).

---

## Bestehende L4-Muster-Analyse

Als Referenz dienen die bestehenden L4-Tests `access-control.spec.ts` (Rollen-Pattern, Zugangskontrollen) und die Privacy-Roles-Helper für den Login-Wechsel. Das Mehrstufiger-Workflow-Pattern (Konzept 4.2) definiert den Ablauf: Editor erzeugt Änderung → Moderator verifiziert/akzeptiert/verwirft. Der Login-Wechsel nutzt die Helper-Kombination `loginAsRole`/`logoutRole`.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Editor erzeugt Pending Change (Fakt bearbeiten → Änderung gespeichert, aber nicht sofort wirksam) | Editor (privacy-Baum) | Änderung wird als Pending Change erfasst, nicht sofort im öffentlichen View sichtbar | Nein |
| T2 | Moderator sieht Pending-Changes-Seite mit ausstehender Änderung | Moderator (privacy-Baum) | Pending-Changes-Seite lädt (200), ausstehende Änderung des Editors sichtbar mit Diff-Ansicht | Nein |
| T3 | Moderator akzeptiert Pending Change → Änderung übernommen, sichtbar | Moderator | POST Accept→Redirect, Änderung ist im Record sichtbar, Pending-Changes-Liste leer | Nein |
| T4 | (Optional) Moderator lehnt Pending Change ab → Änderung verworfen | Moderator | POST Reject→Redirect, Änderung ist verworfen, Record zeigt ursprüngliche Daten | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Mehrstufiger-Workflow + Privacy-Role (Konzept 4.2)
**Begründung:** Der Pending-Changes-Workflow erfordert einen Multi-Role-Test mit Login-Wechsel: Editor erzeugt eine Änderung, Moderator verifiziert und akzeptiert/verwirft sie. T1 erzeugt den Zustand (Pending Change), T2 ist Smoke-Level (Seite zeigt ausstehende Änderung), T3 ist Spec-C (fachlicher Effekt — Änderung wird übernommen), T4 ist optional (Reject-Pfad). Kein Theme-Loop, da Workflow-Test mit Rollen-Wechsel als Fokus.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `pending-changes.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsRole`, `logoutRole`, Privacy-Roles-Helper |
| **Theme-Loop** | Nein — Workflow-Test, Rollen-Wechsel ist Fokus |
| **Login-Strategie** | Multi-Role: `loginAsRole(page, 'editor')` → Aktion → `logoutRole(page)` → `loginAsRole(page, 'moderator')` → Verification |
| **Baum** | privacy (Rollen-User vorhanden: test-editor, test-moderator) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `pending-changes.spec.ts` [Spec-C] ✅ *(4 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Voraussetzungen

- **test-editor** hat `auto_accept=disabled` (in `setup-webtrees.sh` konfiguriert), damit Änderungen als Pending Changes erfasst werden und nicht automatisch akzeptiert werden.
- **test-moderator** hat Moderator-Berechtigung im privacy-Baum, damit Accept/Reject-Aktionen möglich sind.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
