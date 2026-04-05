<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P35: CLI Benutzer-Verwaltung

**Referenz:** P35 | **SUT:** `app/Cli/Commands/UserEdit.php`  
**Aktueller Test:** `UserEditCommandIntegrationTest` (3 Tests: create, edit, delete → SUCCESS)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Die 3 Tests decken die Happy-Path-Fälle gut ab (Create, Edit, Delete mit SUCCESS-Code und Existenz-Verifizierung). Allerdings sind alle **15 Validierungs-Branches** ungetestet.

---

## SUT-Kernbefunde

`UserEdit::execute()` hat 15 klar definierte Guard-Branches:

| Branch | Bedingung | Erwartung | Bisher getestet? |
|---|---|---|---|
| B1 | `username=''` → INVALID | ✅ bekannt, ❌ ungetestet |
| B2 | `--create` + `--delete` → INVALID | ❌ |
| B3 | `--delete` + `--real-name` → INVALID | ❌ |
| B4 | `--delete` + `--email` → INVALID | ❌ |
| B5 | `--delete` + `--password` → INVALID | ❌ |
| B6 | `--create` + User existiert → FAILURE | ❌ |
| B7 | `--create` + `real-name=''` → FAILURE | ❌ |
| B8 | `--create` + `email=''` → FAILURE | ❌ |
| B9 | `--create` + `password=''` → Random-Passwort generiert | ❌ |
| B10 | Edit + User nicht gefunden → FAILURE | ❌ |
| B11 | Edit + alle Felder leer → INVALID | ❌ |
| B12 | Edit + `real-name` gesetzt | ✅ |
| B13 | Edit + `email` gesetzt | ❌ |
| B14 | Edit + `password` gesetzt | ❌ |
| B15 | Delete + User gefunden | ✅ |

---

## Äquivalenzklassen (EP)

### Konflikt-Detection

| Klasse | Flags | Erwartung |
|---|---|---|
| EP1 | `--create --delete` | INVALID, Fehlermeldung |
| EP2 | `--delete --real-name=X` | INVALID |
| EP3 | `--delete --email=x@y.z` | INVALID |
| EP4 | `--delete --password=abc` | INVALID |

### Create-Validierung

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP5 | User existiert bereits | FAILURE |
| EP6 | `--real-name=''` bei Create | FAILURE |
| EP7 | `--email=''` bei Create | FAILURE |
| EP8 | `--password=''` bei Create | SUCCESS + Random-Passwort in Output |
| EP9 | Alle Felder gesetzt | SUCCESS |

### Edit-Validierung

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP10 | User nicht gefunden | FAILURE |
| EP11 | Alle Felder leer | INVALID |
| EP12 | Nur `--email` gesetzt | SUCCESS, nur Email geändert |

---

## Grenzwerte (BVA)

- `username`: Leerstring (B1-Grenze), 1 Zeichen, 255 Zeichen
- `--password`: `''` (Random-Generierung-Grenze), 1 Zeichen, langer Wert
- Zusammenspiel: `--create` + `--real-name=''` + `--email=''` (beide Fehler gleichzeitig)

---

## Empfohlene Strategie

**ISTQB B** — Alle Branches sind klar dokumentiert, Rückgabecodes sind definiert (SUCCESS/FAILURE/INVALID). Dies ist ein **idealer Kandidat** für Entscheidungstabellen-basierte Tests (→ Common Abschnitt 7, DataProvider).

---

## Konkrete Testideen

```
// Konflikt-Tests (DataProvider)
test_create_and_delete_flags_together_returns_invalid()
test_delete_with_other_options_returns_invalid(string $option)  ← DataProvider

// Create-Validierung
test_create_fails_when_user_already_exists()
test_create_fails_when_real_name_empty()
test_create_fails_when_email_empty()
test_create_with_empty_password_generates_random_password()

// Edit-Validierung
test_edit_fails_when_user_not_found()
test_edit_fails_when_no_fields_provided()
test_edit_updates_email_only()
test_edit_updates_password()
```

---

## Aufwand

**Niedrig** — Bestehende Test-Klasse erweitern. CLI-Command-Test-Infrastruktur ist bereits vorhanden. Alle Guards sind durch Return-Code-Assertion (`assertSame(Command::INVALID, $result)`) prüfbar.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt mit Spec überein, keine Korrekturen |
| P2: Soll-Design | ✅ DONE | 11 neue Testmethoden + DataProvider für B3/B4/B5 |
| P3: Test-Coding | ✅ DONE | UserEditCommandIntegrationTest.php erweitert: +11 Methoden, DataProvider B3/B4/B5 |
| P4: Ausführung + Fixing | ✅ DONE | 16/16 grün, 44 Assertions, kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren, Abdeckungsmatrix, Endekriterien, Changelog aktualisiert |
