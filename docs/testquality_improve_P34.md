<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P34: Stammbaum-Umnummerierung

**Referenz:** P34 | **SUT:** `app/Http/RequestHandlers/RenumberTreeAction.php`  
**Aktueller Test:** `RequestHandlerBatchBIntegrationTest` (1 Test: Redirect)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Ein Smoke-Test: Redirect erhalten. Weder die DB-Änderungen noch die Guard-Clause für Pending-Edits sind getestet.

---

## SUT-Kernbefunde

`RenumberTreeAction::handle()` vergibt alle XREFs sequenziell neu:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `$xrefs !== [] && $tree->hasPendingEdit()` → Flash-Error, Redirect | ❌ |
| B2 | Keine Duplikate (`$xrefs === []`) → kein Processing, Redirect | ❌ |
| B3 | `switch($type)` auf INDI | ❌ |
| B4 | `switch($type)` auf FAM | ❌ |
| B5 | `switch($type)` auf SOUR, REPO, NOTE, OBJE | ❌ |
| B6 | Timeout mid-rename | ❌ (dauerhaft ausgeklammert, → Common Abschnitt 10) |
| Post-Condition | XREFs in allen Tabellen konsistent aktualisiert | ❌ |

**Invarianten:** `AdminService::duplicateXrefs()` muss VOR der Umnummerierung leere Liste zurückgeben, sonst wird der Aufruf abgelehnt. Nach Umnummerierung muss `duplicateXrefs()` leer sein.

---

## Äquivalenzklassen (EP)

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP1 | Baum hat keine Duplikate | Redirect, kein Umbenennen |
| EP2 | Baum hat doppelte INDI-XREFs | XREFs umbenannt, kein Fehler |
| EP3 | Baum hat doppelte FAM-XREFs | XREFs umbenannt |
| EP4 | Pending-Edits vorhanden | Flash-Fehlermeldung, keine Umnummerierung |
| EP5 | Baum hat Duplikate in mehreren Typen | Alle Typen umbenannt |

---

## Grenzwerte (BVA)

- Anzahl Duplikate: 0 (keine Aktion), 1, viele
- Record-Typen: alle 6 Typen (INDI, FAM, SOUR, REPO, NOTE, OBJE) abdecken

---

## Empfohlene Strategie

**Pragmatisch C** — Die Umnummerierung ist operationell (Admin-Funktion, selten genutzt). Wichtigster Test: **Pending-Edits-Guard** und **DB-Konsistenz nach Umnummerierung**.

**Hinweis:** Um einen Baum mit Duplikaten herzustellen, muss `gedcom_id`-Tabelle manuell einen Konflikt-XREF erhalten — das ist mit direktem DB-Insert machbar.

---

## Konkrete Testideen

```
test_renumber_tree_blocked_when_pending_edits_exist()
test_renumber_tree_no_action_when_no_duplicates()
test_renumber_tree_removes_duplicate_individual_xrefs()  ← DB-Postcondition
test_renumber_tree_updates_all_linked_tables()           ← Cross-Table
```

---

## Aufwand

**Hoch** — Duplikate müssen manuell via DB-Insert erzeugt werden; Cross-Table-Konsistenz (families, links, hit_counter) erfordert umfangreiche Postconditions.

---

## P1-Korrekturen (Konsistenzcheck)

- `duplicateXrefs()` findet **Cross-Tree**-XREF-Kollisionen (nicht innerhalb eines Baums): Subquery1 = XREFs in tree1; Subquery2 = XREFs in allen anderen Bäumen (individuals/families/sources/media/other + change-Tabelle). JOIN → Schnittmenge.
- EP1-Setup: leerer/neuer Baum ohne andere Bäume → `duplicateXrefs()` = [] ✅
- EP2-Setup: Baum1 + Baum2 mit gleicher INDI-XREF via direktem DB-Insert → `duplicateXrefs(tree1)` = `['DUPXREF' => 'INDI']` ✅
- EP4-Setup: EP2-Setup + `DB::table('change')->insert(['status' => 'pending', ...])` → `hasPendingEdit()` = true ✅
- B6 (Timeout): `TimeoutService::isTimeNearlyUp()` — `new TimeoutService(new PhpService(), PHP_INT_MAX)` verhindert vorzeitigen Abbruch ✅
- `switch(default)` für sonstige Typen: Repository → 'other'-Tabelle; nicht im Fokus für Pragmatisch C.

## P2-Soll-Design

Neue Klasse `RenumberTreeActionIntegrationTest` (3 Tests):

| Test | Methode | Branch/EP |
|---|---|---|
| Keine Duplikate | `test_renumber_tree_no_action_when_no_cross_tree_duplicates()` | B2 / EP1 |
| INDI-Rename | `test_renumber_tree_renames_duplicate_individual_xref()` | B3 / EP2 + Postcondition |
| Pending-Edits-Guard | `test_renumber_tree_blocked_when_pending_edits_and_duplicates()` | B1 / EP4 |

**Setup EP2/EP4:** `DB::table('individuals')->insert(['i_file' => $tree2Id, 'i_id' => 'DUPXREF', 'i_gedcom' => '...'])` in Tree1 UND Tree2.  
**Postcondition EP2:** `DB::table('individuals')->where('i_file', tree1Id)->where('i_id', 'DUPXREF')->count() === 0` (XREF umbenannt).

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | duplicateXrefs = Cross-Tree-Kollision (nicht innerhalb-Baum). Setup via direktem DB-Insert in 2 Bäume. |
| P2: Soll-Design | ✅ DONE | 3 neue Tests in neuer Klasse RenumberTreeActionIntegrationTest (B2/EP1, B3/EP2 Postcondition, B1/EP4 Guard) |
| P3: Test-Coding | ✅ DONE | Neue Klasse RenumberTreeActionIntegrationTest: 3 Tests (EP1 keine Duplikate, EP2 INDI-Rename Postcondition, EP4 Pending-Edit-Guard) |
| P4: Ausführung + Fixing | ✅ DONE | Fix: i_rin+i_sex Pflichtfelder; uniqid() für tree2-Namen; count=1-Assertion entfernt (treeService->create legt @X1@ an → immer ≥2 Individuen). Voll-Lauf: 556/556 grün, 1823 Assertions |
| P5: Big-Picture | ✅ DONE | Feature-Matrix P34 auf spezifikationsbasiert, 3+1 Tests; Abdeckungsmatrix, Endekriterien, Zusammenfassung aktualisiert |
