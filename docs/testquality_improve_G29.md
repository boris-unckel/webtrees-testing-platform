<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — G29: GEDCOM-Bearbeitungsservice

**Referenz:** G29 | **SUT:** `app/Services/GedcomEditService.php`  
**Aktueller Test:** `GedcomEditServiceIntegrationTest` (7 Tests: editLinesToGedcom + insertMissingLevels)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

G29 hat von allen CRAP-Analyse-Tests bereits die **stärkste Ausgangsbasis**: Die Tests prüfen konkrete String-Ausgaben (nicht nur „kein Exception"). Mehrere Äquivalenzklassen für `editLinesToGedcom` sind bereits abgedeckt. Verbesserungspotenzial liegt in systematischerer EP/BVA-Abdeckung und Edge Cases.

---

## SUT-Kernbefunde

`GedcomEditService` hat zwei Kernmethoden:

### `editLinesToGedcom(array $tags, array $values, array $subm_tags, ...): string`

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Tag + Wert vorhanden | Normal-Pfad | ✅ |
| Wert leer | `$value = ''` → leere Zeile oder kein Output | ✅ (teilweise) |
| `append=false` | Keine führende Leerzeile | ✅ |
| Sub-Tags vorhanden | `$subm_tags` nicht leer | ❌ |
| `include_hidden=true` | Hidden-Felder einschließen | ✅ (teilweise) |
| Mehrzeilige Werte | `\n` im Wert → Continuation-Lines (`CONC`/`CONT`) | ❌ |
| Sonderzeichen | `@` im Wert → `@@` escaping | ❌ |
| Unicode | Multibyte-Strings | ❌ |

### `insertMissingLevels(string $gedcom): string`

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Level vorhanden | Normale GEDCOM-Zeile | ✅ |
| Level fehlt | Zeile ohne Level-Nummer | ✅ (INDI:NAME) |
| Verschachtelte Levels | Level 2, 3, 4 | ❌ (nur Level 1–2 getestet) |
| Leerer Input | `''` | ❌ |
| Nur Whitespace | `'   '` | ❌ |
| Ungültige Zeile | Kein Tag erkennbar | ❌ |

---

## Äquivalenzklassen (EP)

### `editLinesToGedcom`

| Klasse | Wert | Erwartung |
|---|---|---|
| EP1 | Normaler String-Wert | Zeile `1 TAG value` |
| EP2 | Wert mit `\n` (Zeilenumbruch) | `CONT`-Continuation |
| EP3 | Wert mit `@` | `@@` escaping — delegiert an `element->canonical()`, nicht direkt in `editLinesToGedcom` testbar |
| EP4 | Leerstring | Zeile ohne Wert oder keine Zeile |
| EP5 | Wert mit führenden/nachgestellten Spaces | Trimming oder Erhalt? |
| EP6 | Sub-Tags befüllt | Untergeordnete Zeilen ausgegeben |

### `insertMissingLevels`

| Klasse | Input | Erwartung |
|---|---|---|
| EP7 | GEDCOM mit allen Levels | Unverändert |
| EP8 | GEDCOM mit fehlenden Level-1-Einträgen | Levels eingefügt |
| EP9 | GEDCOM mit fehlenden Level-2-Einträgen | Levels eingefügt |
| EP10 | Leerer String bei Tag mit Subtags | Subtag-Expansion: mindestens eine Zeile je erlaubtem Subtag wird eingefügt (kein leerer String) |
| EP11 | Einzeilig (kein `\n`) | Korrekt behandelt |

---

## Grenzwerte (BVA)

- `insertMissingLevels`: Level 0 (record start), Level 1, Level 5 (tiefstes übliches Level)
- `editLinesToGedcom`: 0 Tags, 1 Tag, viele Tags
- Wertlänge: GEDCOM-Limit 255 Bytes → Continuation-Lines ab 256 Zeichen

---

## Empfohlene Strategie

**ISTQB B** — `GedcomEditService` ist ein purer Service ohne HTTP/DB-Abhängigkeit. Die Eingabe-/Ausgabe-Spezifikation ist klar aus dem GEDCOM-Standard ableitbar. DataProvider für die EP-Matrix ist direkt umsetzbar (→ Common Abschnitt 7). Dies ist der **best geeignete Kandidat** für vollständige spezifikationsbasierte Tests.

---

## Konkrete Testideen

```
// DataProvider für editLinesToGedcom-EP-Matrix
test_edit_lines_with_multiline_value_produces_cont_continuation()
test_edit_lines_escapes_at_sign_in_value()
test_edit_lines_handles_empty_value_correctly()
test_edit_lines_includes_sub_tags()

// insertMissingLevels
test_insert_missing_levels_handles_empty_string()
test_insert_missing_levels_preserves_existing_levels()
test_insert_missing_levels_nested_level2()

// DataProvider für Level-Tiefe
test_insert_missing_levels_at_various_depths(int $depth) ← DataProvider
```

---

## Aufwand

**Niedrig** — Kein HTTP-Setup, kein DB-Setup nötig. Reine Unit-artige Integration mit String-Assertionen. Erweiterung der bestehenden Test-Klasse ausreichend.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | EP10 korrigiert (kein leerer Return), EP3 als n.a. markiert, Testzahl 6→7 |
| P2: Soll-Design | ✅ DONE | 2 neue Tests: CONT-Continuation (EP2) + insertMissingLevels-Expansion (EP9) |
| P3: Test-Coding | ✅ DONE | GedcomEditServiceIntegrationTest.php erweitert: +2 Tests (CONT-Continuation, Subtag-Expansion) |
| P4: Ausführung + Fixing | ✅ DONE | 9/9 grün, 24 Assertions, kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren, Abdeckungsmatrix, Endekriterien, Changelog aktualisiert |
