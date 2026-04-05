<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P32: Record-Ansicht und -Löschung

**Referenz:** P32 | **SUT:** `app/Http/RequestHandlers/GedcomRecordPage.php` + `DeleteRecord.php`  
**Aktueller Test:** `RequestHandlerBatchAIntegrationTest` + `RequestHandlerBatchBIntegrationTest`  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

`GedcomRecordPage`: 2 Tests (SOUR, REPO → 200). `DeleteRecord`: 1 Test (SOUR → 204). Kein Berechtigungs-Test, keine Kaskadenprüfung.

---

## SUT-Kernbefunde

### `GedcomRecordPage::handle()`

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Record sichtbar | `canShow()=true` | ✅ (Smoke) |
| Record nicht sichtbar | `canShow()=false` | ❌ |
| Verschiedene Record-Typen | INDI, FAM, SOUR, REPO, NOTE, OBJE | ✅ (SOUR, REPO) |
| Raw-GEDCOM-Ausgabe | Format des angezeigten GEDCOM | ❌ |

### `DeleteRecord::handle()`

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Kein Editor | `!Auth::isEditor()` → Skip | ❌ |
| Record nicht sichtbar | `!canShow()` → Skip | ❌ |
| Record nicht editierbar | `!canEdit()` → Skip | ❌ |
| Standard-Löschung | Kein Linker → 204, Record weg | ✅ (Smoke, kein DB-Check) |
| Mit Linker (non-Family) | Linker-Record wird aktualisiert | ❌ |
| Familie mit 1 Mitglied + keine Fakten | Familie wird mitgelöscht | ❌ (kritisch) |
| Familie mit Fakten | Familie wird behalten | ❌ (kritisch) |
| Regex-Level-Matching | GEDCOM Level 1–5 in `removeLinks()` | ❌ |

**Besonderheit `DeleteRecord`:** Die `removeLinks()`-Methode verwendet Regex, um GEDCOM-Verweise auf den gelöschten Record zu entfernen. Dieser Regex ist bei verschiedenen Level-Tiefen unterschiedlich und ist ein klarer BVA-Kandidat.

---

## Äquivalenzklassen (EP)

### `DeleteRecord`

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP1 | Standard-Record, kein Linker | 204, DB-Eintrag entfernt |
| EP2 | Record mit 1 Linker (INDI) | 204, Linker-GEDCOM aktualisiert |
| EP3 | Record mit 3+ Linkern | Alle Linker aktualisiert |
| EP4 | Individual in Familie mit Geschwistern | Familie behalten (>1 Mitglied) |
| EP5 | Individual als einziges Familienmitglied + keine MARR/EVEN | Familie mitgelöscht |
| EP6 | Individual als einziges Familienmitglied + hat MARR | Familie behalten (hat Fakten) |
| EP7 | Kein Editor-Recht | 204, aber Record bleibt in DB |
| EP8 | Record `canShow()=false` | 204, Record bleibt |

---

## Grenzwerte (BVA)

- Familienmitglieder: 1 (Lösch-Grenze), 2 (Behalten-Grenze)
- Genealogy-Fakten: 0 (Familie löschbar), 1 (Familie behalten)
- GEDCOM-Ebene in `removeLinks()`: Level 1, 2, 3, 4, 5

---

## Empfohlene Strategie

**ISTQB B** für die Guard-Clauses und Familie-Lösch-Bedingung (klar spezifiziert, kritisch für Datenkorrektheit).  
**Aufsplittung:** `DeleteRecordIntegrationTest` + `GedcomRecordPageIntegrationTest` (→ Common Abschnitt 6).

---

## Konkrete Testideen

```
test_delete_record_removes_entry_from_database()              ← DB-Postcondition
test_delete_record_updates_linking_individual()               ← Cross-Table
test_delete_individual_deletes_single_member_family_without_facts()
test_delete_individual_keeps_family_with_genealogy_facts()
test_delete_record_no_action_without_editor_role()
test_gedcom_record_page_all_record_types(string $xref, ...)   ← DataProvider
```

---

## Aufwand

**Mittel** — Familie-Lösch-Test braucht spezifisches Fixture-Setup (Individual als einziges Familienmitglied).

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | KRITISCH: GedcomRecordPage: Alle STANDARD_RECORDS (INDI, FAM, SOUR, REPO, NOTE, OBJE, SUBM usw.) → 302 Redirect; Smoke-Tests prüften nur < 400 → verdeckten tatsächliches Verhalten; DeleteRecord: Kaskade-Bedingung: instanceof Family + keine Genealogie-Fakten + genau 1 Mitglied nach Entfernen → Familie wird mitgelöscht; change-Tabelle: deleteRecord() schreibt immer new_gedcom='' |
| P2: Soll-Design | ✅ DONE | DeleteRecordIntegrationTest (neue Klasse): EP1 test_delete_source_creates_pending_change_in_change_table (SOUR X1102 + change-Postcondition); EP5 test_delete_individual_cascades_to_empty_family (p32-delete-test.ged: P1 löschen → F1 kaskadiert); GedcomRecordPageIntegrationTest (neue Klasse): EP1 DataProvider 4 Standard-Typen → 302; EP2 _CUST-Insert in other-Tabelle → 200 + Link-Header |
| P3: Test-Coding | ✅ DONE | DeleteRecordIntegrationTest.php: 2 Tests (EP1 SOUR, EP5 Kaskade); GedcomRecordPageIntegrationTest.php: DataProvider 4×EP1 + EP2 _CUST-DB-Insert; Fixture p32-delete-test.ged bereits erstellt |
| P4: Ausführung + Fixing | ✅ DONE | 7/7 grün (4 DataProvider-Cases + 1 _CUST-Test + 2 Delete-Tests), 34 Assertions; kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix spezifikationsbasiert (7 Tests), Testentwurfsverfahren Äquivalenzklassen P32 + CRAP-Zeile (P32 entfernt → P34), Endekriterien (P32 in Spec-Liste), Abdeckungsmatrix (neue Klassen), Zusammenfassung 133→134 spec / 6→5 struct, Changelog aktualisiert |
