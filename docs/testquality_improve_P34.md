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

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ⬜ OPEN | — |
| P2: Soll-Design | ⬜ OPEN | — |
| P3: Test-Coding | ⬜ OPEN | — |
| P4: Ausführung + Fixing | ⬜ OPEN | — |
| P5: Big-Picture | ⬜ OPEN | — |
