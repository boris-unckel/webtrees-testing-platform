<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P37: HTTP Benutzer-Bearbeitung

**Referenz:** P37 | **SUT:** `app/Http/RequestHandlers/UserEditAction.php`  
**Aktueller Test:** `RequestHandlerBatchBIntegrationTest` (1 Test: Admin-Update → Redirect)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Ein Test: Admin-User-Update → 3xx-Redirect. Alle kritischen Branches (Duplikat-Checks, Approval-Email, Permission-Guards) sind ungetestet.

---

## SUT-Kernbefunde

`UserEditAction::handle()` hat komplexe Validierungslogik:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `$edit_user === null` → HttpNotFoundException | ❌ |
| B2 | `$approved` + User nicht vorher approved → Email senden | ❌ |
| B3 | `$password !== ''` → Passwort aktualisieren | ❌ |
| B4 | Self-Edit + `canadmin` → Ignoriert (Selbst-Ausschluss) | ❌ |
| B5 | Neue Email ≠ alte Email | Duplikat-Check auslösen | ❌ |
| B6 | Email bereits von anderem User → Flash-Error, Redirect | ❌ |
| B7 | Neuer Username ≠ alter Username | Duplikat-Check auslösen | ❌ |
| B8 | Username bereits von anderem User → Flash-Error, Redirect | ❌ |
| Happy Path | Alle Felder valide → Update + Redirect | ✅ (Smoke) |
| Per-Tree | `RELATIONSHIP_PATH_LENGTH`, `gedcomid`, `canedit` pro Baum | ❌ |
| Path-Reset | `gedcomid=''` → path_length auf 0 | ❌ |

**Besonderheit B4:** Ein Admin kann sich selbst nicht aus der Admin-Gruppe entfernen — der `canadmin`-Parameter wird ignoriert wenn `edit_user->id() === current_user->id()`.

---

## Äquivalenzklassen (EP)

### Validierungs-Branches

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP1 | `user_id` nicht gefunden | HttpNotFoundException |
| EP2 | Email-Änderung, Email bereits vergeben | Flash-Error, Redirect |
| EP3 | Email-Änderung, Email noch frei | Update erfolgreich |
| EP4 | Username-Änderung, Username vergeben | Flash-Error, Redirect |
| EP5 | Username-Änderung, Username frei | Update erfolgreich |
| EP6 | Self-Edit, `canadmin=false` | `canadmin` ignoriert, bleibt true |
| EP7 | Edit anderer User, `canadmin=true` | `canadmin` gesetzt |
| EP8 | `approved=true` (erstmalig) | Approval-Email gesendet |
| EP9 | `password=''` | Passwort bleibt unverändert |
| EP10 | `password='neuespasswort'` | Passwort aktualisiert |

### Per-Tree-Einstellungen

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP11 | `gedcomid='I1'`, `path_length=3` | Beide gesetzt |
| EP12 | `gedcomid=''` | `path_length` auf 0 gesetzt |

---

## Grenzwerte (BVA)

- Email: gleiche Email (kein Duplikat-Check), um 1 Zeichen andere Email
- Username: gleicher Username (kein Duplikat-Check), anderer Username
- `password`: `''` (kein Update), 1 Zeichen, lange Passwort
- `canadmin` bei Self-Edit: `true` (ignoriert) vs. bei anderem User: `true` (gesetzt)

---

## Empfohlene Strategie

**ISTQB B** für Duplikat-Checks und Permission-Branches — klar spezifiziert, kritisch für Datenkonsistenz.  
**Pragmatisch C** für Approval-Email (E-Mail-Versand im Test schwer verifizierbar).

**Aufsplittung:** Eigene `UserEditActionIntegrationTest`-Klasse (→ Common Abschnitt 6).

---

## Konkrete Testideen

```
test_user_edit_throws_not_found_for_invalid_user_id()
test_user_edit_rejects_duplicate_email()
test_user_edit_rejects_duplicate_username()
test_user_edit_self_edit_cannot_remove_admin_role()
test_user_edit_updates_password_when_provided()
test_user_edit_does_not_update_password_when_empty()
test_user_edit_resets_path_length_when_gedcomid_cleared()
```

---

## Aufwand

**Mittel** — Duplikat-Tests benötigen zweiten User in der DB. Self-Edit-Test benötigt Login als Admin-User.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt mit Spec überein; B2 (Approval-Email) ausgeklammert (kein SMTP-Mock); Email/Username-Duplikat-Redirect enthält user_id-Parameter (Unterschied zu UserListPage-Redirect) |
| P2: Soll-Design | ✅ DONE | 7 neue Tests: B1 HttpNotFoundException, B5/B6 Duplikat-Email, B7/B8 Duplikat-Username, B4 Self-Edit-Admin-Guard, B3 Passwort-Update (2×), EP12 Path-Length-Reset |
| P3: Test-Coding | ✅ DONE | Neue UserEditActionIntegrationTest.php: makeEditRequest()-Helper + 7 Testmethoden |
| P4: Ausführung + Fixing | ✅ DONE | 7/7 grün, 25 Assertions; getUserPreference Objekt-Cache umgangen → direkter DB::table-Assert für path_length |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren (+Äquivalenzklassen P37, CRAP-Zeile korrigiert), Abdeckungsmatrix, Endekriterien, Zusammenfassung (124 spec + 15 strukturbasiert), Changelog aktualisiert |
