<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — P38: Account-Selbstverwaltung

**Referenz:** P38 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/my-account` (GET Edit + POST Update) → `AccountEdit`, `AccountUpdate`, `AccountDelete`
**L3-Referenztest:** AccountSelfManagementIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Account-Selbstverwaltung existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (AccountSelfManagementIntegrationTest) decken die Handler-Ebene ab (4 Tests: Edit→200, Update E-Mail→302+DB-Prüfung, Delete Admin→302+nicht gelöscht, Delete Non-Admin→302+gelöscht), prüfen aber nicht die tatsächliche Formular-Interaktion und das visuelle Feedback im Browser. Der L4-Test beschränkt sich auf das Edit-Formular und die Update-Funktion; der Delete-Guard (Admin kann sich nicht selbst löschen) ist durch L3 ausreichend abgedeckt.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/my-account` | GET | `AccountEdit` |
| `/tree/{tree}/my-account` | POST | `AccountUpdate` |
| `/tree/{tree}/delete-account/{user_id}` | POST | `AccountDelete` |

AccountEdit und AccountUpdate sind für eingeloggte Benutzer zugänglich (keine Admin-Berechtigung erforderlich). Der GET-Handler rendert ein Formular mit den aktuellen Account-Einstellungen (E-Mail, Sprache, Zeitzone, Kontaktdaten). Der POST-Handler aktualisiert die Einstellungen und leitet mit 302 zurück. AccountDelete wird im L4-Test nicht geprüft (L3-Abdeckung ausreichend).

### View-Analyse

Das Formular enthält Eingabefelder für: E-Mail, Realname, Sprache (Select), Zeitzone (Select), Kontaktmethode. Die Felder nutzen Bootstrap-Standard-Markup. Nach erfolgreichem Update erfolgt ein Redirect mit Bestätigungsmeldung (Flash-Message).

### Theme-Abhängigkeit

Kein Theme-Loop erforderlich. Die Account-Selbstverwaltung ist eine user-spezifische Funktion, deren fachliche Funktionalität nicht theme-abhängig variiert. Das Formular-Rendering nutzt Standard-Bootstrap-Elemente.

---

## L3-Referenz-Analyse

**AccountSelfManagementIntegrationTest** — 4 Tests:

1. AccountEdit GET → 200 (Account-Formular wird gerendert)
2. AccountUpdate POST mit geänderter E-Mail → 302 (Redirect) + DB-Prüfung bestätigt neue E-Mail
3. AccountDelete POST als Admin → 302 (Redirect) + Admin-User ist nicht gelöscht (Guard)
4. AccountDelete POST als Non-Admin → 302 (Redirect) + User ist gelöscht

Die L3-Tests validieren die HTTP-Ebene und DB-Zustand. Sie prüfen nicht das DOM-Rendering des Formulars, die vorausgefüllten Feldwerte (E-Mail, Sprache, Zeitzone) und die visuelle Bestätigungsmeldung nach Update.

---

## Bestehende L4-Muster-Analyse

Als Referenz dient der bestehende L4-Test `user-pages.spec.ts` (User-Seiten-Rendering). Das Formular-Submit-Verification-Pattern (Konzept 1) definiert den Ablauf: Formular laden → Felder prüfen/ändern → Submit → Redirect → Bestätigung verifizieren. Die Login-Strategie folgt dem bestehenden `loginAsAdmin`-Pattern.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Account-Seite lädt korrekt (Formular mit E-Mail, Sprache, Zeitzone sichtbar) | Admin | Seite lädt (200), Formularfelder sichtbar und mit aktuellen Werten vorausgefüllt | Nein |
| T2 | E-Mail ändern via Formular-Submit → Redirect, Bestätigungsmeldung | Admin | POST→302, Redirect zur Account-Seite, geänderte E-Mail im Formular sichtbar | Nein |
| T3 | Sprache/Zeitzone ändern → Einstellung wirkt | Admin | POST→302, Redirect zur Account-Seite, geänderte Sprache/Zeitzone im Formular sichtbar | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Formular-Submit-Verification (Konzept 1)
**Begründung:** Die Account-Selbstverwaltung ist eine authentifizierte Benutzer-Funktion mit klassischem Formular-Submit-Workflow. T1 ist Smoke-Level (Formular lädt mit vorausgefüllten Feldern), T2/T3 sind Spec-C (fachlicher Effekt — Einstellungsänderung nach Submit verifiziert). Kein Theme-Loop, da user-spezifische Funktionalität ohne theme-abhängiges Rendering.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `account-self-management.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein — User-spezifische Funktionalität, nicht theme-abhängig |
| **Login-Strategie** | Admin-Login (eingeloggt als beliebiger User, Admin der Einfachheit halber) |
| **Baum** | demo |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `account-self-management.spec.ts` [Spec-C] ✅ *(3 Tests)* |
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
