<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — A04: TreePreferencesPage / TreePreferencesAction (Baum-Präferenzen)

**Status:** ⬜ OPEN  
**Aufwand:** Mittel  
**Qualitätsziel:** Spezifikationsbasiert (ISTQB B) — EP-Matrix für Admin-Check und Duplikat-Name

---

## Status quo

Kein dedizierter Test. `TreePreferencesPage` ist ein komplexer GET-Handler (Kalender, Module, Benutzer, Fakten-Listen). `TreePreferencesAction` schreibt viele Präferenzen in DB.

---

## SUT-Kernbefunde

### TreePreferencesPage (GET)

Sehr viel Assembling-Logik (Kalender-Formate, Module-Liste, Benutzer-Liste). Kein Branch außer bei `pedigree_individual`. Gibt immer View 200 zurück.

### TreePreferencesAction (POST)

| Branch | Bedingung | Ergebnis |
|---|---|---|
| B1 | `Auth::isAdmin()` = true | MEDIA_DIRECTORY und gedcom-Rename erlaubt |
| B2 | `Auth::isAdmin()` = false | MEDIA_DIRECTORY aus Tree-Präferenz, kein Rename |
| B3 | Duplikat-Gedcom-Name (Admin) | FlashMessage 'danger', kein Rename |
| B4 | `$tree->name() !== $gedcom` | Site-Setting DEFAULT_GEDCOM wird aktualisiert |
| B5 | `all_trees = true` | Zusätzliche Flash-Message |
| B6 | `new_trees = true` | Zusätzliche Flash-Message |

---

## EP-Matrix

| EP | Partition | Eingabe | Erwartetes Ergebnis |
|---|---|---|---|
| EP1 | GET: Admin | Admin + Tree | 200, View |
| EP2 | POST B1: Admin-Update | Admin, neuer Gedcom-Name | 302, Name in DB aktualisiert |
| EP3 | POST B3: Duplikat | Admin, bereits existierender Name | 302, Flash 'danger', Name unverändert |
| EP4 | POST B2: Non-Admin | Normaler Manager, MEDIA_DIR geändert | 302, MEDIA_DIR unverändert (aus Tree) |

---

## Strategie

**Neue Testklasse:** `TreePreferencesIntegrationTest extends MysqlTestCase`

- `setUp()`: `createAndLoginAdmin()`, `createTreeWithGedcom('demo', 'Demo', '/fixtures/demo.ged')`
- `TreePreferencesAction` hat keinen Konstruktor → einfach instanziierbar
- Für Duplikat-Test: zweiten Baum anlegen, dann ersten auf Namen des zweiten umbenennen versuchen

---

## Phasenstatus

| Phase | Status |
|---|---|
| P1: Konsistenzcheck | ⬜ |
| P2: Soll-Design | ⬜ |
| P3: Test-Coding | ⬜ |
| P4: Ausführung + Fixing | ⬜ |
| P5: Big-Picture | ⬜ |
