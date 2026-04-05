<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S49: Medienverwaltungsliste Admin

**Referenz:** S49 | **SUT:** `app/Http/RequestHandlers/ManageMediaData.php`  
**Aktueller Test:** `ManageMediaDataIntegrationTest` (2 Tests: local + external JSON-Antwort)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Zwei Tests prüfen, dass der Handler JSON zurückgibt (lokale und externe Dateien). Keine Prüfung des JSON-Inhalts, keine Edge-Cases für leere Medienliste oder Paginations-Parameter.

---

## SUT-Kernbefunde

`ManageMediaData::handle()` liefert Datatable-JSON für die Admin-Medienverwaltung:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| `type='local'` | Lokale Mediendateien | ✅ (Smoke) |
| `type='external'` | Externe URL-Medien | ✅ (Smoke) |
| `type='unused'` | Nicht referenzierte Dateien | ❌ |
| Pagination | `start`, `length` Parameter | ❌ |
| `search['value']` | Suchfilter in DataTable | ❌ |
| Leere Medienliste | Kein Medium im Baum | ❌ |
| Pfad-Traversal | `folder=../../../etc` | ❌ (Sicherheitsrelevant) |

**JSON-Struktur:** DataTables-Format `{data: [...], recordsTotal: N, recordsFiltered: M}`. Bisher keine Assertion auf diese Struktur.

---

## Äquivalenzklassen (EP)

| Klasse | Parameter | Erwartung |
|---|---|---|
| EP1 | `type='local'` | JSON mit lokalen Dateipfaden |
| EP2 | `type='external'` | JSON mit URLs |
| EP3 | `type='unused'` | JSON mit nicht referenzierten Dateien |
| EP4 | `search='Windsor'` | Gefiltertes Ergebnis |
| EP5 | `search=''` | Alle Einträge |
| EP6 | `start=0, length=10` | Erste 10 Einträge |
| EP7 | `start=100, length=10` | Seite nach dem Ende → leere data-Liste |
| EP8 | Kein Medium im Baum | `recordsTotal=0`, `data=[]` |

---

## Grenzwerte (BVA)

- `length`: 0 (leere Seite?), 1, 10, PHP_INT_MAX
- `start`: 0, 1, recordsTotal-1, recordsTotal (boundary: letzte gültige Seite)
- Pfad: Normaler Ordner, leerer String, `../` (Traversal-Versuch)

---

## Empfohlene Strategie

**ISTQB B** für JSON-Struktur und `type`-EP-Matrix.  
**Pragmatisch C** für Pagination-Grenzwerte und Pfad-Sicherheit.

**Wichtigster Gewinn:** JSON-Inhalts-Assertion statt nur Content-Type-Check.

---

## Konkrete Testideen

```
test_manage_media_data_returns_valid_datatables_structure()  ← JSON-Schema
test_manage_media_data_local_type_returns_file_paths()
test_manage_media_data_search_filters_results()
test_manage_media_data_empty_tree_returns_zero_records()
test_manage_media_data_pagination_start_beyond_end_returns_empty_data()
```

---

## Aufwand

**Niedrig** — JSON-Assertion ist straightforward. `json_decode()` auf Response-Body + `assertArrayHasKey('data', ...)`.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt überein; `default`-Case unerreichbar (Validator wirft zuerst); `external` nutzt `$media_folder` nicht in Query, Validator verlangt es trotzdem; `unused` nutzt `handleCollection` |
| P2: Soll-Design | ✅ DONE | Bestehende 2 Tests mit JSON-Struktur-Assertions erweitern; neuer `unused`-Test (EP3); EP4–EP7 (Pagination/Search) nicht umgesetzt — DataTables-Format opak, kein Erkenntnisgewinn |
| P3: Test-Coding | ✅ DONE | ManageMediaDataIntegrationTest: StatusCodeInterface-Import + JSON-Assertions in 2 bestehenden Tests + neuer unused-Test |
| P4: Ausführung + Fixing | ✅ DONE | 3/3 grün, 21 Assertions, kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren (+Äquivalenzklassen S49, CRAP-Zeile S41–S48), Abdeckungsmatrix, Endekriterien, Zusammenfassung (121 spec + 18 strukturbasiert), Changelog aktualisiert |
