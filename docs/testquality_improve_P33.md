<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P33: Stammbaum-Privacy-Einstellungen

**Referenz:** P33 | **SUT:** `app/Http/RequestHandlers/TreePrivacyAction.php`  
**Aktueller Test:** `RequestHandlerBatchAIntegrationTest` (1 Test: POST leer → Redirect)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Ein Test: POST mit leeren Arrays → Redirect. Weder Datenbank-Änderungen noch die Validierungslogik für parallele Arrays sind geprüft.

---

## SUT-Kernbefunde

`TreePrivacyAction::handle()` akzeptiert Arrays `$xrefs`, `$tag_types`, `$resns` (müssen gleich lang sein) sowie diverse Privacy-Einstellungs-Parameter:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Array-Längen ungleich | `count_xrefs ≠ count_tag_types` → HttpBadRequestException | ❌ |
| B2: `tag+xref` beide gefüllt | Bestehende Regel für (tag, xref) löschen | ❌ |
| B3: `tag` nur | Regel für (tag, kein xref) löschen | ❌ |
| B4: `xref` nur | Regel für (kein tag, xref) löschen | ❌ |
| B5: Einfügen | `tag !== '' || xref !== ''` → neuen default_resn-Eintrag | ❌ |
| Privacy-Einstellungen | HIDE_LIVE_PEOPLE, MAX_ALIVE_AGE, etc. persistiert | ❌ |
| DB-Postcondition | `default_resn`-Tabelle korrekt | ❌ |

**Invariante:** Alle drei Arrays müssen parallel sein (gleicher Index = zusammengehörige Regel). Bei Ungleichheit → Exception.

---

## Äquivalenzklassen (EP)

### Array-Validierung

| Klasse | Arrays | Erwartung |
|---|---|---|
| EP1 | Alle leer (`[]`) | Redirect ohne DB-Änderung |
| EP2 | Gleich lang (3 Einträge) | Verarbeitung |
| EP3 | `xrefs` länger als `tag_types` | HttpBadRequestException |
| EP4 | `tag_types` länger als `resns` | HttpBadRequestException |

### Rule-Typ (B2–B5)

| Klasse | `tag` | `xref` | Erwartung |
|---|---|---|---|
| EP5 | `'BIRT'` | `'I1'` | (tag, xref)-Regel erstellt |
| EP6 | `'BIRT'` | `''` | Nur-Tag-Regel erstellt |
| EP7 | `''` | `'I1'` | Nur-XREF-Regel erstellt |
| EP8 | `''` | `''` | Keine Regel eingefügt (B5 false) |

### Privacy-Einstellungen

| Klasse | Parameter | Erwartung |
|---|---|---|
| EP9 | `HIDE_LIVE_PEOPLE=1` | Tree-Preference gesetzt |
| EP10 | `MAX_ALIVE_AGE=0` | Grenzfall Nullwert |
| EP11 | `MAX_ALIVE_AGE=150` | Normaler Wert |

---

## Grenzwerte (BVA)

- Array-Länge: 0, 1, viele; `count_xrefs` vs `count_tag_types` um ±1
- `MAX_ALIVE_AGE`: 0 (ungültig?), 1, 120 (typisch), 999
- `tag` + `xref` beide leer vs. einer leer

---

## Empfohlene Strategie

**ISTQB B** für Array-Validierung und Rule-Typ-Matrix — das sind die klar spezifizierten, testbaren Branches.  
**Aufsplittung:** Eigene `TreePrivacyActionIntegrationTest` Klasse (→ Common Abschnitt 6).

---

## Konkrete Testideen

```
test_tree_privacy_throws_on_mismatched_array_lengths()
test_tree_privacy_inserts_tag_xref_rule()
test_tree_privacy_inserts_tag_only_rule()
test_tree_privacy_inserts_xref_only_rule()
test_tree_privacy_does_not_insert_when_both_empty()
test_tree_privacy_saves_hide_live_people_setting()     ← DB-Postcondition
```

---

## Aufwand

**Mittel** — `DB::table('default_resn')` nach POST prüfen. Exception-Test via `assertThrows` oder Response-Status-Check.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt mit Spec überein; alle Privacy-Params mandatory (kein Default → Exception wenn fehlt); delete-Array vor Längencheck; Exception vor Privacy-Param-Lesen; tree-Objekt via attributes ist $this->tree → setPreference-Assertion direkt auf $this->tree möglich |
| P2: Soll-Design | ✅ DONE | 6 Tests: EP3/EP4 mismatched-arrays→HttpBadRequestException, EP5 tag+xref insert, EP6 tag-only insert (xref=null), EP7 xref-only insert (tag=null), EP8 beide-leer kein Insert (count=0), EP9 HIDE_LIVE_PEOPLE=1 saved; makeRequest()-Helper mit allen 8 mandatory Privacy-Params |
| P3: Test-Coding | ✅ DONE | Neue TreePrivacyActionIntegrationTest.php: makeRequest()-Helper + 6 Testmethoden |
| P4: Ausführung + Fixing | ✅ DONE | 6/6 grün, 23 Assertions; EP8 (both-empty) Fix: TreeService::create() kopiert default_resn von gedcom_id=-1 → countBefore/After statt assertSame(0) |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren (+Äquivalenzklassen P33, CRAP-Zeile P33→P32/P34), Abdeckungsmatrix, Endekriterien, Zusammenfassung (127 spec + 12 strukturbasiert), Changelog aktualisiert |
