<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A07: Benutzerverwaltung Admin

**Referenz:** A07 | **SUT:** `app/Http/RequestHandlers/UserListPage.php`, `UsersCleanupPage.php`, `UsersCleanupAction.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test. Alle drei Handler sind Admin-only. UserListPage: GET → alle User aus DB. UsersCleanupPage: GET → User ohne Tree-Zuordnung und ohne kürzlichen Login.

---

## SUT-Kernbefunde

### UserListPage (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | GET → alle User aus DB, Admin-only | Nein |

### UsersCleanupPage (GET)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Inaktive Schwelle: now() - 6 Monate | Nein |
| B2 | Unverifizierte Schwelle: now() - 7 Tage | Nein |

### UsersCleanupAction (POST)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | POST: User-IDs zum Löschen → delete() + redirect | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | UserListPage GET | 200, View mit Admin-User |
| EP2 | UsersCleanupPage GET: keine Kandidaten | 200, leere Listen |
| EP3 | UsersCleanupPage GET: inaktiver User | 200, User in inaktiver Liste |
| EP4 | UsersCleanupAction POST: User-IDs übergeben | 302, User aus DB gelöscht |

---

## Empfohlene Strategie

**ISTQB B.** Neue Klasse `UserAdminIntegrationTest extends MysqlTestCase`. Admin-Auth. Für EP3: User mit `last_login` vor >6 Monaten anlegen. DB-Postcondition für EP4: User nicht mehr vorhanden.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
