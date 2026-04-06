<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — P40: PendingChanges-Aktionen (Accept/Reject Change/Record)

**Status:** ⬜ OPEN  
**Aufwand:** Mittel  
**Qualitätsziel:** Spezifikationsbasiert (ISTQB B) — EP-Matrix mit DB-Postconditions

---

## Status quo

Kein dedizierter Test für die PendingChanges-Handler. Möglicherweise als Teil anderer Tests gestreift, aber keine direkte EP-Abdeckung.

**Betroffene Handler:**
- `PendingChangesAcceptChange` — nimmt einzelne Change an
- `PendingChangesRejectChange` — lehnt einzelne Change ab
- `PendingChangesAcceptRecord` — nimmt alle Changes eines Records an
- `PendingChangesRejectRecord` — lehnt alle Changes eines Records ab
- `PendingChanges` — GET: zeigt ausstehende Changes an

---

## SUT-Kernbefunde

### PendingChangesAcceptRecord

**DI:** `PendingChangesService $pending_changes_service`

| Branch | Bedingung | Ergebnis |
|---|---|---|
| B1 | `$record instanceof GedcomRecord` = false (XREF ungültig) | Kein `acceptRecord()`-Aufruf, `response()` 200 |
| B2 | Record ist pending deletion | FlashMessage "deleted" + `acceptRecord()` + 200 |
| B3 | Record hat pending changes (nicht deletion) | FlashMessage "accepted" + `acceptRecord()` + 200 |

### PendingChangesRejectRecord

| Branch | Bedingung | Ergebnis |
|---|---|---|
| B1 | `$record instanceof GedcomRecord` = false | Kein `rejectRecord()`-Aufruf, `response()` 200 |
| B2 | Record existiert | FlashMessage "rejected" + `rejectRecord()` + 200 |

### PendingChanges (GET)

Kein komplexer Branch — liest `pendingXrefs()` und `pendingChanges()`, gibt View zurück.

---

## EP-Matrix (AcceptRecord / RejectRecord)

| EP | Handler | Partition | Eingabe | Erwartetes Ergebnis |
|---|---|---|---|---|
| EP1 | AcceptRecord | B1: ungültige XREF | XREF nicht in DB | 200, kein DB-Write |
| EP2 | AcceptRecord | B3: pending change | Existierender Record mit pending edit | 200, Change in DB als accepted |
| EP3 | RejectRecord | B1: ungültige XREF | XREF nicht in DB | 200, kein DB-Write |
| EP4 | RejectRecord | B2: pending change | Existierender Record mit pending edit | 200, Change in DB als rejected |
| EP5 | PendingChanges | Happy Path | GET mit tree | 200, View rendered |

---

## Strategie

**Neue Testklasse:** `PendingChangesIntegrationTest extends MysqlTestCase`

- `setUp()`: `createAndLoginAdmin()`, `createTreeWithGedcom('demo', 'Demo', '/fixtures/demo.ged')`
- Pending Change erzeugen: `$tree->getPreference('REQUIRE_APPROVAL') = '1'` + einen Record editieren → erzeugt pending change
- DB-Postcondition: `DB::table('change')->where('status', 'accepted')` prüfen
- Handler instanziieren mit `new PendingChangesService()` oder via Registry

**Fixture-Bedarf:** demo.ged enthält editierbare Records (INDI, FAM). Pending Changes werden durch Editieroperationen erzeugt — erfordert ein Baum mit `REQUIRE_APPROVAL='1'`.

---

## Phasenstatus

| Phase | Status |
|---|---|
| P1: Konsistenzcheck | ✅ |
| P2: Soll-Design | ✅ |
| P3: Test-Coding | ✅ |
| P4: Ausführung + Fixing | ✅ |
| P5: Big-Picture | ✅ | `testing-bigpicture.md` Abdeckungsmatrix P40 aktualisiert |
