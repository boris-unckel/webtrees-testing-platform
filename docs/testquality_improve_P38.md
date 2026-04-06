<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P38: Account-Selbstverwaltung

**Referenz:** P38 | **SUT:** `app/Http/RequestHandlers/AccountEdit.php` (GET), `AccountUpdate.php` (POST), `AccountDelete.php` (POST-Bestätigung)
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test. `AccountEdit` ist ein einfacher GET-Handler. `AccountUpdate` und `AccountDelete` sind POST-Handler für den eingeloggten Benutzer (Self-Edit, nicht Admin-Edit).

---

## SUT-Kernbefunde

### AccountEdit (GET)

**Konstruktor-DI:** (kein Konstruktor — prüfen bei P1)

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `treeOptional()` — Tree vorhanden | View mit tree-spezifischen Optionen | Nein |
| B2 | `treeOptional()` — Tree null | View ohne tree-spezifische Optionen | Nein |
| B3 | `$user != Admin` | `show_delete_option = true` | Nein |
| B4 | `$user == Admin` | `show_delete_option = false` | Nein |

### AccountUpdate (POST)

**DI:** `UserService $user_service`

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Passwort nicht leer → setPassword() | Nein |
| B2 | Passwort leer → setPassword() nicht aufgerufen | Nein |
| B3 | Email geändert + andere User hat diese Email → FlashMessage 'danger', keine Änderung | Nein |
| B4 | Email geändert + Email frei → Email wird geändert | Nein |
| B5 | Username geändert + anderer User hat diesen Namen → FlashMessage 'danger' | Nein |
| B6 | Username geändert + Name frei → Username wird geändert | Nein |

Gibt immer redirect(route(HomePage::class)) zurück (302).

### AccountDelete (POST)

**DI:** `UserService $user_service`

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | User ist Administrator (PREF_IS_ADMINISTRATOR='1') → kein Löschen, redirect zurück | Nein |
| B2 | User ist kein Administrator → delete() + Auth::logout() + redirect | Nein |

---

## Äquivalenzklassen (EP)

### AccountEdit GET

| EP | Partition | Eingabe | Erwartetes Ergebnis |
|---|---|---|---|
| EP1 | B1+B4: Admin mit Tree | Admin-User + Tree im Attribut | 200, show_delete_option = false |
| EP2 | B2+B4: Admin ohne Tree | Admin-User, kein Tree | 200, show_delete_option = false |
| EP3 | B1+B3: Non-Admin mit Tree | Normaler User + Tree | 200, show_delete_option = true |
| EP4 | B2+B3: Non-Admin ohne Tree | Normaler User, kein Tree | 200, show_delete_option = true |

### AccountUpdate POST

| EP | Partition | Eingabe | Erwartetes Ergebnis |
|---|---|---|---|
| EP5 | B3: Duplikat-Email | Andere Email eines existierenden Users | 302, Flash 'danger', Email unverändert |
| EP6 | B4: Email-Update | Freie neue Email | 302, Email in DB aktualisiert |
| EP7 | B5: Duplikat-Username | Name eines anderen Users | 302, Flash 'danger', Username unverändert |
| EP8 | B1+B4+B6: Happy Path | Valide Daten, neues Passwort | 302, alle Updates in DB |

### AccountDelete POST

| EP | Partition | Eingabe | Erwartetes Ergebnis |
|---|---|---|---|
| EP9 | B1: Admin-Schutz | Admin-User versucht Selbstlöschung | 302, User noch in DB |
| EP10 | B2: Non-Admin-Löschung | Regulärer User | 302, User nicht mehr in DB, Auth::id() == null |

---

## Empfohlene Strategie

**Neue Testklasse:** `AccountSelfManagementIntegrationTest extends MysqlTestCase`

- Für AccountEdit-Tests: `createAndLoginAdmin()` + separater Non-Admin-User
- Für AccountUpdate-Tests: Zweiten User anlegen für Duplikat-Szenarien
- Für AccountDelete-Tests: Non-Admin-User einloggen, Delete-Handler aufrufen, DB-Postcondition: User nicht mehr vorhanden
- Auth::id() nach AccountDelete: muss null sein

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Big-Picture | ⬜ | |
