<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — P37: Benutzer-Bearbeitung (Admin)

**Referenz:** P37 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/admin/users/edit/{user_id}` (GET Page), `/admin/users/edit` (POST Action) → `UserEditPage`, `UserEditAction`
**L3-Referenztest:** UserEditActionIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Admin-seitige Benutzer-Bearbeitung existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (UserEditActionIntegrationTest) decken die Handler-Ebene umfassend ab (7 Tests: ungültige user_id, Duplikat-E-Mail, Duplikat-Username, Self-Edit-Admin-Guard, Passwort-Update, leeres Passwort, GedcomId-Reset), prüfen aber nicht die tatsächliche Formular-Interaktion und das visuelle Feedback im Browser.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/admin/users/edit/{user_id}` | GET | `UserEditPage` |
| `/admin/users/edit` | POST | `UserEditAction` |

Beide Handler erfordern Admin-Berechtigung. Der GET-Handler rendert ein vollständiges Benutzer-Bearbeitungsformular mit vorausgefüllten Feldern (Username, E-Mail, Realname, Rollen, Passwort). Der POST-Handler validiert die Eingaben, prüft auf Duplikate (E-Mail, Username) und leitet bei Erfolg/Fehler jeweils mit 302 zurück.

### View-Analyse

Das Formular enthält Eingabefelder für: Username, E-Mail, Realname, Passwort (optional), Sprache, Zeitzone, Admin-Flag, Baum-Rollen. Die Felder nutzen Bootstrap-Standard-Markup. Bei Validierungsfehlern (Duplikat-E-Mail, Duplikat-Username) erfolgt ein Redirect zurück zur Edit-Seite mit Flash-Message.

### Theme-Abhängigkeit

Kein Theme-Loop erforderlich. Die Admin-Benutzerverwaltung nutzt ein standardisiertes Admin-Layout, das nicht zwischen Themes variiert.

---

## L3-Referenz-Analyse

**UserEditActionIntegrationTest** — 7 Tests:

1. Ungültige user_id → HttpNotFoundException
2. Duplikat-E-Mail → 302 Redirect zurück zur UserEditPage (Fehler-Flash)
3. Duplikat-Username → 302 Redirect zurück zur UserEditPage (Fehler-Flash)
4. Self-Edit: Admin kann sich selbst nicht die Admin-Rolle entziehen → Admin-Flag bleibt gesetzt
5. Passwort-Update mit neuem Passwort → 302 (Passwort geändert)
6. Leeres Passwort-Feld → kein Passwort-Update (bestehendes Passwort bleibt)
7. GedcomId gelöscht → path_length wird zurückgesetzt

Die L3-Tests validieren die HTTP-Ebene und Geschäftslogik (Statuscodes, DB-Zustand). Sie prüfen nicht das DOM-Rendering des Formulars, die vorausgefüllten Feldwerte und das visuelle Feedback bei Validierungsfehlern.

---

## Bestehende L4-Muster-Analyse

Als Referenz dienen die bestehenden L4-Tests `auth.spec.ts` (Login-Flow, Formular-Submit) und `access-control.spec.ts` (Admin-Zugangskontrollen). Das Formular-Submit-Verification-Pattern (Konzept 1) definiert den Ablauf: Formular laden → Felder ausfüllen → Submit → Redirect → Ergebnis verifizieren.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | User-Edit-Seite lädt für existierenden User (Formular sichtbar, Felder vorbelegt) | Admin | Seite lädt (200), Formularfelder (Username, E-Mail, Realname) sichtbar und vorausgefüllt | Nein |
| T2 | E-Mail ändern und speichern → Redirect, neue E-Mail bestätigt | Admin | POST→302, Redirect zur User-Edit-Seite, geänderte E-Mail im Formular sichtbar | Nein |
| T3 | Duplikat-E-Mail → Fehlermeldung/Redirect zurück zur Edit-Seite | Admin | POST→302, Redirect zur UserEditPage, Fehlermeldung/Flash sichtbar | Nein |
| T4 | Duplikat-Username → Fehlermeldung/Redirect | Admin | POST→302, Redirect zur UserEditPage, Fehlermeldung/Flash sichtbar | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only + Formular-Submit-Verification (Konzept 1)
**Begründung:** Die Benutzer-Bearbeitung ist eine Admin-Only-Funktion mit klassischem Formular-Submit-Workflow. T1 ist Smoke-Level (Formular lädt mit vorausgefüllten Feldern), T2 ist Spec-C (fachlicher Effekt — E-Mail-Änderung nach Submit verifiziert), T3/T4 prüfen Fehlerbehandlung (Duplikat-Validierung). Kein Theme-Loop, da Admin-Seite mit standardisiertem Layout.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `user-edit-admin.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein — Admin-Seite, nicht theme-abhängig |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo (Admin-Bereich, nicht baumgebunden) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `user-edit-admin.spec.ts` [Spec-C] ✅ *(4 Tests)* |
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
