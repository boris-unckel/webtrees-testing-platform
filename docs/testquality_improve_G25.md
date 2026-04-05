<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — G25: GedcomLoad CLI-Import

**Referenz:** G25 | **SUT:** `app/Http/RequestHandlers/GedcomLoad.php`  
**Aktueller Test:** `GedcomLoadIntegrationTest` (1 Test: chunk-in-progress, 1 Test: all-complete)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Der aktuelle Test prüft nur: (a) ein laufender Import gibt HTTP < 500 zurück, (b) ein abgeschlossener Import gibt HTTP < 500 zurück. Es wird weder der Response-Inhalt geprüft noch ein Datenbankzustand verifiziert.

---

## SUT-Kernbefunde

`GedcomLoad::handle()` hat 12 relevante Entscheidungspunkte:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| A1a | `offset === total` AND `!tree->imported()` → Fail-View | ❌ |
| A1b | `offset === total` AND `tree->imported()` → Success-View | ✅ (implizit) |
| B1 | `offset === 0` → Altdaten löschen | ❌ |
| B1a | `keep_media === '1'` → Media-Tabellen nicht löschen | ❌ |
| B1b | `keep_media !== '1'` → alle Tabellen löschen | ❌ |
| C2a | Erster Chunk beginnt mit UTF-8 BOM → BOM entfernen | ❌ |
| C2b | Erster Chunk beginnt nicht mit `0 HEAD` → Fail-View | ❌ |
| C3a | `GedcomErrorException` beim Record-Import → Fehler sammeln | ❌ |
| C4 | `isTimeLimitUp()` mid-import → Progress-View, Loop abbrechen | ❌ |
| D1a | Concurrency-Exception → Progress-View (Retry-Signal) | ❌ |
| D1b | Andere Exception → Fail-View | ❌ |

**Invarianten:** Erster Chunk muss `0 HEAD` beginnen; `keep_media`-Präferenz steuert Löschverhalten bei Reimport; Race-Condition via `imported`-Flag in DB.

---

## Äquivalenzklassen (EP)

| Klasse | Parameter / Zustand | Erwartetes Verhalten |
|---|---|---|
| EP1 | `offset=0`, `total=10`, `keep_media='0'` | Altdaten gelöscht, Chunk verarbeitet |
| EP2 | `offset=0`, `total=10`, `keep_media='1'` | Medien-Tabellen behalten, Rest gelöscht |
| EP3 | Erster Chunk mit BOM (`\xEF\xBB\xBF0 HEAD`) | BOM entfernt, Header erkannt |
| EP4 | Erster Chunk mit `0 INDI` statt `0 HEAD` | Fail-View mit Fehlermeldung |
| EP5 | `offset=total`, `tree->imported()=false` | Fail-View „kein Trailer" |
| EP6 | `offset=total`, `tree->imported()=true` | Success-View |

---

## Grenzwerte (BVA)

- `keep_media`: `'1'` (exakt) vs. `'true'` / `'2'` (nicht erkannt) → Branch-Grenze
- Chunk-Count: 0 Chunks (Division by zero-Risiko?), 1 Chunk, viele Chunks
- `chunk_data`: leerer String, Chunk exakt `0 HEAD`, Chunk mit BOM + `0 HEAD`

---

## Empfohlene Strategie

**Hybrid B+C:** Für die klar spezifizierten Zweige (keep_media, Header-Validierung, BOM) → ISTQB B (Äquivalenzklassen). Für Fehler-/Ausnahme-Pfade (Exception-Handler, Concurrency) → Pragmatisch C.

---

## Konkrete Testideen

```
test_handle_deletes_old_data_when_keep_media_is_zero()
test_handle_keeps_media_tables_when_keep_media_is_one()
test_handle_strips_utf8_bom_from_first_chunk()
test_handle_returns_fail_view_when_first_chunk_missing_header()
test_handle_returns_fail_view_when_trailer_missing()
test_handle_accumulates_gedcom_errors_on_malformed_record()
```

---

## Aufwand

**Hoch** — BOM-Test und keep_media-Test benötigen direkten DB-Insert für `gedcom_chunk`; `GedcomImportService` muss ggf. für Exception-Tests gemockt werden.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | Alle 11 Branches bestätigt — Spec stimmt exakt mit GedcomLoad::handle() überein |
| P2: Soll-Design | ✅ DONE | 6 neue Tests: EP1 (keep_media=0), EP2 (keep_media=1), EP3 (BOM-Strip), EP4 (kein 0 HEAD), EP5 (kein Trailer), EP6 (Complete-View via Tree::fromDB re-fetch) |
| P3: Test-Coding | ✅ DONE | 6 neue Testmethoden in GedcomLoadIntegrationTest.php ergänzt |
| P4: Ausführung + Fixing | ✅ DONE | Voll-Lauf: 552/552 grün, 1804 Assertions. GedcomLoad::handle nicht mehr in CRAP-Liste. |
| P5: Big-Picture | ✅ DONE | Feature-Matrix G25 auf spezifikationsbasiert, 8 Tests; Abdeckungsmatrix, Endekriterien, CRAP-Zeile aktualisiert |
