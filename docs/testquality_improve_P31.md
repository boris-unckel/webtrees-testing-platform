<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P31: Familienmitglieder bearbeiten

**Referenz:** P31 | **SUT:** `app/Http/RequestHandlers/ChangeFamilyMembersAction.php`  
**Aktueller Test:** `RequestHandlerBatchBIntegrationTest` (1 Test: Redirect)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Ein Smoke-Test: Request → 3xx-Redirect. Weder DB-Zustand noch die genealogisch kritische Datumsreihenfolge-Logik ist getestet.

---

## SUT-Kernbefunde

`ChangeFamilyMembersAction::handle()` hat komplexe Familien-Update-Logik:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Alter Vater ≠ neuer Vater → alte FAMS/HUSB-Links entfernen | ❌ |
| B2 | Alte Mutter ≠ neue Mutter → alte FAMS/WIFE-Links entfernen | ❌ |
| B3 | Altes Kind nicht in neuer Liste → FAMC/CHIL-Links entfernen | ❌ |
| B4 | Neues Kind nicht in alter Liste → FAMC/CHIL-Links hinzufügen | ❌ |
| B5 | Neuer Vater ist Individual → FAMS/HUSB-Links erstellen | ❌ |
| B6 | Neue Mutter ist Individual → FAMS/WIFE-Links erstellen | ❌ |
| B7 | Einfügung nach Heiratsdatum geordnet | ❌ (datumsbasierte Logik) |
| B8 | Kind-Einfügung nach Geburtsdatum geordnet | ❌ (datumsbasierte Logik) |
| HUSB/WIFE leer | `$xref = ''` → kein Elternteil | ❌ |

**Kritisch:** B7 und B8 sind genealogische Datumsreihenfolge-Logik. Bei mehreren Ehen oder Geschwistern muss die Reihenfolge chronologisch korrekt sein.

---

## Äquivalenzklassen (EP)

| Klasse | Aktion | Erwartung |
|---|---|---|
| EP1 | Vater austauschen (alt → neu) | Alte Links gelöscht, neue erstellt |
| EP2 | Mutter auf leer setzen | WIFE-Link gelöscht |
| EP3 | Kind hinzufügen (war nicht in Familie) | FAMC/CHIL-Links erstellt |
| EP4 | Kind entfernen | FAMC/CHIL-Links gelöscht |
| EP5 | Keine Änderung (alles gleich) | Kein DB-Update, Redirect |
| EP6 | Mehrere Kinder, Datumsreihenfolge | Kinder chronologisch nach Geburt |
| EP7 | Elternteile mit bekannten Heiratsdaten | Einfügung in FAMS chronologisch |

---

## Grenzwerte (BVA)

- Kinder-Array: `[]` (leer), `[1 Kind]`, `[viele Kinder]`
- Heiratsdatum: bekanntes Datum, unbekanntes Datum, vor/nach anderer Ehe
- Geburtsdatum für Geschwister: gleiches Datum (Zwillinge), chronologisch klar, unbekannt

---

## Empfohlene Strategie

**ISTQB B** für Member-Änderungs-Branches (EP1–EP5) — klar spezifiziert.  
**Pragmatisch C** für die Datums-Reihenfolge (B7, B8) — wichtig für Datenkorrektheit, aber komplex zu setup.

**Aufsplittung:** Eigene `ChangeFamilyMembersActionIntegrationTest`-Klasse (→ Common Abschnitt 6).

---

## Konkrete Testideen

```
test_change_family_members_replaces_husband()
test_change_family_members_removes_wife_when_empty()
test_change_family_members_adds_new_child()
test_change_family_members_removes_child()
test_change_family_members_no_change_does_not_update_db()
test_change_family_members_children_sorted_chronologically()
```

---

## Aufwand

**Mittel** — FAMS/FAMC/HUSB/WIFE-Fakten nach Aktion prüfen via `$individual->facts(['FAMS'])`. Datums-Reihenfolge-Test braucht Fixture mit bekannten Geburtsdaten.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt mit Spec überein; HUSB/WIFE='' → Validator returns '' → individualFactory::make('') → null → B1/B2 ausgelöst; family f1: HUSB X1041, WIFE X1030, CHIL X1052/X1063/X1074/X1085 stabil in demo.ged; CHIL-Array unvalidiert (kein isXref); Auth::checkFamilyAccess erfordert canEdit() → Admin OK |
| P2: Soll-Design | ✅ DONE | 5 Tests: EP1/B1+B5 replace-husband, EP2/B2 remove-wife, EP3/B4 add-child, EP4/B3 remove-child, EP5 no-change; Assertion via change-Tabelle (exists() je betroffenem xref); EP5: change-count=0; B7/B8 Datumsreihenfolge ausgeklammert (Pragmatisch C, komplex) |
| P3: Test-Coding | ✅ DONE | Neue ChangeFamilyMembersActionIntegrationTest.php: makeRequest()-Helper + hasChangeFor()-Helper + 5 Testmethoden |
| P4: Ausführung + Fixing | ✅ DONE | 5/5 grün, 25 Assertions; change-Tabellen-Asserts korrekt; no-change-Test: 0 change-Einträge bestätigt |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren (+Äquivalenzklassen P31, CRAP-Zeile P31→P32–P34), Abdeckungsmatrix, Endekriterien, Zusammenfassung (126 spec + 13 strukturbasiert), Changelog aktualisiert |
