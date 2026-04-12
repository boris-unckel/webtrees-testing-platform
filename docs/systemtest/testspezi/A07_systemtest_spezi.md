<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — A07: Benutzerverwaltung Admin

**Referenz:** A07 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/admin/users` (GET), `/admin/users/cleanup` (GET) → `UserListPage`, `UsersCleanupPage`
**L3-Referenztest:** UserAdminIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Benutzerverwaltung im Admin-Bereich existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (UserAdminIntegrationTest) decken die Handler-Ebene ab (3 Tests: UserList→200, UserList mit Filter→200, Cleanup→200), prüfen aber nicht das DOM-Rendering der Benutzertabelle, die Filterfunktion im Browser und nicht die Cleanup-Seite im visuellen Kontext.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/admin/users` | GET | `UserListPage` |
| `/admin/users/cleanup` | GET | `UsersCleanupPage` |

Beide Handler erfordern Administrator-Berechtigung. `UserListPage` zeigt eine Tabelle aller registrierten Benutzer mit Filtermöglichkeit (Suchfeld). `UsersCleanupPage` zeigt inaktive oder nicht verifizierte Benutzer, die bereinigt werden können.

### View-Analyse

Die User-Liste enthält eine Tabelle mit Spalten: Benutzername, vollständiger Name, E-Mail, Registrierungsdatum, letzter Login, Rolle. Ein Suchfeld (`input` oder DataTables-Filter) ermöglicht die Filterung nach Benutzername. Der admin-User ist immer in der Liste vorhanden. Die Cleanup-Seite zeigt eine separate Tabelle oder Meldung (je nach Vorhandensein inaktiver Benutzer).

### Theme-Abhängigkeit

Admin-Seiten nutzen ein festes Layout. Kein Theme-Loop erforderlich.

---

## L3-Referenz-Analyse

**UserAdminIntegrationTest** — 3 Tests:

1. UserList GET ohne Filter → 200 (Benutzerliste wird gerendert)
2. UserList GET mit filter='admin' → 200 (Gefilterte Benutzerliste wird gerendert)
3. UsersCleanup GET → 200 (Cleanup-Seite wird gerendert)

Die L3-Tests validieren die HTTP-Ebene (Statuscodes). Sie prüfen nicht, ob der admin-User tatsächlich in der Tabelle aufgelistet ist, ob der Filter funktional die Ergebnisse einschränkt und ob die Cleanup-Seite den korrekten Inhalt zeigt.

**EP/BVA-Analyse:**

- EP1: UserList ohne Filter (→200) — Lesefall, alle Benutzer
- EP2: UserList mit filter='admin' (→200) — Lesefall, gefilterte Liste
- EP3: UsersCleanup (→200) — Lesefall, Cleanup-Ansicht

---

## Bestehende L4-Muster-Analyse

**Referenz-Spec:** `upload-validation.spec.ts` — enthält das Admin-Pattern (Login als Admin, Admin-Seite aufrufen). Die User-Liste und Cleanup-Seite sind reine GET-Seiten ohne Formular-Submit — das Smoke-Pattern (Seite laden, DOM-Inhalt prüfen) ist ausreichend.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | User-Liste lädt korrekt (Tabelle mit Benutzern sichtbar, admin-User aufgelistet) | Admin | Seite lädt, Benutzertabelle sichtbar, Eintrag "admin" vorhanden | Nein |
| T2 | Filter funktioniert (Filter auf "admin" → gefilterte Liste) | Admin | Filterfeld befüllen, Tabelle zeigt gefilterten Inhalt mit admin-User | Nein |
| T3 | Cleanup-Seite lädt korrekt | Admin | Seite lädt, Cleanup-Inhalt sichtbar (Tabelle oder Hinweismeldung) | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Admin-Only (wf_code-to-systemtest_guide.md 4.5)
**Begründung:** Alle Testszenarien sind Smoke-Level: Admin-Seite laden, DOM-Inhalt prüfen. T2 geht minimal über reines Smoke hinaus (Filterfeld befüllen, Ergebnis prüfen), bleibt aber im Smoke-Siegel, da keine Datenmanipulation stattfindet. Kein Theme-Loop (Admin-Bereich).

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `user-admin.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin` |
| **Theme-Loop** | Nein (Admin-Seite) |
| **Login-Strategie** | Admin-Login |
| **Baum** | Kein spezifischer Baum — Admin-Bereich |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `user-admin.spec.ts` [Smoke] ✅ *(3 Tests)* |
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
