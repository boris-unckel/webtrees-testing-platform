<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M08: Datenbank-Schema-Migration

**Referenz:** M08 | **SUT:** `app/Http/Middleware/UpdateDatabaseSchema.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware delegiert die Schema-Migration an den
injizierten `MigrationService`. Es existiert lediglich ein L2-Stub
(`assertTrue(class_exists(...))`), der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware erhält `MigrationService` per Dependency-Injection und ruft
`updateSchema()` auf. Der Ablauf ist sequenziell ohne echte Branches.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Schema aktuell (keine ausstehenden Migrationen) → Handler direkt aufrufen | Nein |
| B2 | Schema veraltet (Migrationen ausstehend) → `updateSchema()` ausführen, dann Handler | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Schema ist aktuell, keine Migration nötig | `updateSchema()` wird aufgerufen (No-Op), Handler wird ausgeführt |
| EP2 | Schema ist veraltet, Migration nötig | `updateSchema()` führt Migrationen aus, Handler wird ausgeführt |

---

## Grenzwerte (BVA)

Keine sinnvollen Grenzwerte — der Ablauf ist binär (Schema aktuell oder nicht).

---

## Empfohlene Strategie

- **Testklasse:** `UpdateDatabaseSchemaMiddlewareIntegrationTest`
- **Strategie:** Smoke (minimaler Durchlauftest)
- **Priorität:** Niedrig
- **Testbarkeit:** Sehr gut — `MigrationService` wird per DI injiziert und kann
  gemockt werden
- **Fixtures:** Für EP1: Mock-MigrationService, der keine Migrationen meldet.
  Für EP2: Mock-MigrationService, der eine ausstehende Migration simuliert.
- **Mocking:** `MigrationService` mocken, um Migrationsverhalten zu steuern.
  Alternativ: realer MigrationService gegen die Test-DB (Schema bereits aktuell).
- **Hinweis:** Da der Test-Stack die DB bereits mit aktuellem Schema aufbaut, ist EP1
  der natürliche Happy-Path. EP2 erfordert Mock oder DB-Manipulation.

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `<Testklasse> [<Siegel>] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen |
| `docs/tds_methodik_spec.md` | Ggf. Middleware-Pipeline-Testing als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
