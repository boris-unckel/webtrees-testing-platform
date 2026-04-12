<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M07: Datenbank-Verbindung

**Referenz:** M07 | **SUT:** `app/Http/Middleware/UseDatabase.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware liest Datenbank-Verbindungsparameter aus
Request-Attributen und ruft `DB::connect()` auf. Es existiert lediglich ein L2-Stub
(`assertTrue(class_exists(...))`), der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware nutzt keine Dependency-Injection, sondern `DB::connect()` (statisch) und
`Validator` zur Parameter-Extraktion. Alle Parameter stammen aus Request-Attributen.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Alle Parameter gültig (MySQL) → `DB::connect()` aufrufen | Nein |
| B2 | `dbtype` Default-Wert → MySQL als Fallback | Nein |
| B3 | Postgres-Treiber (`pgsql`) → andere DSN-Erzeugung | Nein |
| B4 | Optionale Parameter leer → Defaults verwenden | Nein |
| B5 | `dbverify` `false` → SSL-Verifikation deaktiviert | Nein |
| B6 | `dbverify` `true` → SSL-Verifikation aktiviert | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Alle Parameter gültig (MySQL, Standard-Port) | Verbindung wird hergestellt, Handler aufgerufen |
| EP2 | `dbtype` nicht gesetzt → Default greift | MySQL-Verbindung mit Default-Treiber |
| EP3 | Postgres-Treiber (`pgsql`) | PostgreSQL-DSN wird korrekt erzeugt |
| EP4 | Optionale Parameter (`dbprefix`, `dbkey`, `dbcert`, `dbca`) leer | Verbindung ohne SSL-Optionen |
| EP5 | `dbverify` explizit `false` | SSL-Verifikation deaktiviert in PDO-Optionen |
| EP6 | `dbverify` explizit `true` | SSL-Verifikation aktiviert in PDO-Optionen |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| `dbtype` MySQL | `mysql` | MySQL-DSN |
| `dbtype` PostgreSQL | `pgsql` | PostgreSQL-DSN |
| `dbtype` SQLite | `sqlite` | SQLite-DSN |
| `dbport` MySQL-Standard | `3306` | Standard-MySQL-Port |
| `dbport` PostgreSQL-Standard | `5432` | Standard-PostgreSQL-Port |
| `dbport` Custom | `33060` | Custom-Port in DSN |

---

## Empfohlene Strategie

- **Testklasse:** `UseDatabaseMiddlewareIntegrationTest`
- **Strategie:** EP (Äquivalenzklassenpartitionierung)
- **Priorität:** Mittel
- **Fixtures:** Request-Objekte mit definierten DB-Attributen; für MySQL-Tests die
  bestehende Test-Datenbank nutzen
- **Mocking:** `DB::connect()` ist statisch — entweder realer DB-Aufruf (bevorzugt in L3)
  oder Wrapper-Mocking. Für Postgres/SQLite-Pfade ggf. partielle Mocks nötig, wenn nur
  MySQL im Test-Stack verfügbar ist.
- **Hinweis:** Der Test-Stack stellt nur MySQL bereit. Postgres- und SQLite-Pfade
  erfordern entweder Mock-basierte Verifizierung der DSN-Erzeugung oder einen erweiterten
  Stack.

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
