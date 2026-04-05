<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P30: Datensätze zusammenführen

**Referenz:** P30 | **SUT:** `app/Http/RequestHandlers/MergeFactsAction.php`  
**Aktueller Test:** `MergeFactsIntegrationTest` + `RequestHandlerBatchBIntegrationTest` (je 1 Test)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Ein Happy-Path-Test: 2 Individuen zusammenführen → Response < 500. Keine DB-Postcondition, keine Guard-Tests.

---

## SUT-Kernbefunde

`MergeFactsAction::handle()` hat 6 Guard-Clauses vor der eigentlichen Merge-Logik:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `$record1 === null` → Redirect | ❌ |
| B2 | `$record2 === null` → Redirect | ❌ |
| B3 | `$record1 === $record2` → Redirect | ❌ |
| B4 | `$record1->tag() !== $record2->tag()` → Redirect | ❌ |
| B5 | `$record1->isPendingDeletion()` → Redirect | ❌ |
| B6 | `$record2->isPendingDeletion()` → Redirect | ❌ |
| Happy Path | Merge erfolgreich, `$record2` gelöscht | ✅ (Smoke, kein DB-Check) |
| Verlinkung | Andere Records referenzieren `$record2` → werden auf `$record1` umgestellt | ❌ |
| `keep1`/`keep2` | Welche Fakten behalten werden | ❌ |

**Invarianten:** Beide Records müssen gleichen GEDCOM-Tag haben; keiner darf `pending_deletion` sein; `keep1`/`keep2` sind Arrays von Fact-IDs.

---

## Äquivalenzklassen (EP)

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP1 | Beide Records valide, gleicher Tag | Merge, record2 gelöscht |
| EP2 | `xref1` ungültig (null) | Redirect |
| EP3 | `xref2` ungültig (null) | Redirect |
| EP4 | `xref1 === xref2` | Redirect (gleicher Record) |
| EP5 | Record1=INDI, Record2=SOUR | Redirect (Tag-Mismatch) |
| EP6 | Record1 hat `pending_deletion` | Redirect |
| EP7 | `keep1=[]`, `keep2=[]` (keine Fakten behalten) | Merge, alle Fakten von record2 verworfen |
| EP8 | `keep1` mit spezifischen Fact-IDs | Nur diese Fakten behalten |

---

## Grenzwerte (BVA)

- `keep1`/`keep2`: `[]` (leer), `[single_id]`, `[alle_fact_ids]`
- xref1 === xref2 (Gleichheits-Grenze)
- Record2 hat 0 Verlinkungen, 1, viele

---

## Empfohlene Strategie

**ISTQB B** — Die 6 Guard-Clauses sind klar spezifiziert und testen direkt. DB-Postcondition nach Merge (record2 gelöscht, Verlinkungen umgestellt) ist der wichtigste fehlende Test.

**Aufsplittung:** Eigene `MergeFactsActionIntegrationTest`-Klasse statt Batch (→ Common Abschnitt 6).

---

## Konkrete Testideen

```
test_merge_redirects_when_record1_not_found()
test_merge_redirects_when_same_record()
test_merge_redirects_when_records_have_different_tags()
test_merge_redirects_when_record_pending_deletion()
test_merge_deletes_record2_from_database()          ← DB-Postcondition
test_merge_updates_links_from_record2_to_record1()  ← Cross-Table-Postcondition
test_merge_respects_keep_facts_selection()
```

---

## Aufwand

**Mittel** — DB-Postcondition-Checks erfordern `DB::table('individuals')->where('i_id', 'I2')->count()`. Verlinkungstest erfordert `DB::table('link')->where('l_to', 'I2')`.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt mit Spec überein; alle 6 Guard-Branches in einem if-Block; Happy Path→ManageTrees, Guards→MergeRecordsPage; SOUR X1102/X1103, INDI X1030+ verfügbar |
| P2: Soll-Design | ✅ DONE | 5 Tests: B1/EP2 record-not-found, B3/EP4 same-record, B4/EP5 tag-mismatch (INDI+SOUR), B5/EP6 pending-deletion (DB-Insert), EP1 DB-Postcondition (PREF_AUTO_ACCEPT_EDITS='1'); makeRequest()-Helper; Location-Unterscheidung guard('xref1') vs. happy-path(kein 'xref1') |
| P3: Test-Coding | ✅ DONE | Neue MergeFactsActionIntegrationTest.php: makeRequest()-Helper + 5 Testmethoden |
| P4: Ausführung + Fixing | ✅ DONE | 5/5 grün, 21 Assertions; test_merge_deletes_record2_from_database umgebaut → change-Tabellen-Assert (Auth::user()-Cache-Problem, kein auto_accept möglich ohne Cache-Invalidierung) |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren (+Äquivalenzklassen P30, CRAP-Zeile P30→P31–P34), Abdeckungsmatrix, Endekriterien, Zusammenfassung (125 spec + 14 strukturbasiert), Changelog aktualisiert |
