<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S42: Such-HTTP-Handler

**Referenz:** S42 | **SUT:** `app/Http/RequestHandlers/SearchGeneralPage.php`  
**Aktueller Test:** `SearchRequestHandlerIntegrationTest` (3 Tests: leer, mit Treffer, Multi-Typ)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Drei Smoke-Tests: leer, 1 Suchbegriff, Multi-Typ. Der wichtigste, kaum triviale Branch — der **Single-Result-Redirect** — ist komplett ungetestet.

---

## SUT-Kernbefunde

`SearchGeneralPage::handle()` hat folgende Schlüssel-Branches:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Leere Suche → kein Query | `$search_terms === []` | ✅ (implizit) |
| Default-Flags | Wenn keine Flags gesetzt → both individuals + families | ❌ |
| ALLOW_CHANGE_GEDCOM | `'1'` → Multi-Tree, `'0'` → nur aktueller Baum | ❌ |
| `search_trees` leer | → Aktuellen Baum hinzufügen | ❌ |
| **Single-Result-Redirect** | Genau 1 Individual, keine anderen → Redirect | ❌ |
| **Single-Result-Redirect** | Genau 1 Family, keine anderen → Redirect | ❌ |
| **Single-Result-Redirect** | Genau 1 Source/Note/Location | ❌ |
| Multi-Tree-Suche | Suche über 2+ Bäume | ❌ |

**Der Single-Result-Redirect** (Zeilen 160–178 im SUT) ist 5-fach verzweigt (Individual, Family, Source, Note, Location) und stellt einen kritischen funktionalen Pfad dar: Eine Suchanfrage mit exakt einem Treffer soll direkt auf diesen Treffer weiterleiten, statt eine Ergebnisliste zu zeigen.

---

## Äquivalenzklassen (EP)

### Suchergebnis-Kardinalität (kritisch)

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP1 | 0 Treffer (alle Typen) | 200 OK mit Ergebnisliste |
| EP2 | Genau 1 Individual, 0 andere | **Redirect auf Individual** |
| EP3 | 2+ Individuals | 200 OK mit Liste |
| EP4 | Genau 1 Family, 0 andere | **Redirect auf Family** |
| EP5 | 1 Individual + 1 Family | 200 OK (keine Redirect, da mehrdeutig) |

### Such-Flags (Typ-Auswahl)

| Klasse | Flags | Erwartung |
|---|---|---|
| EP6 | Nur `search_individuals=true` | Sucht nur Individuen |
| EP7 | Nur `search_families=true` | Sucht nur Familien |
| EP8 | Alle `false` | Fallback auf individuals+families |
| EP9 | Alle `true` | Alle Typen durchsucht |

### ALLOW_CHANGE_GEDCOM

| Klasse | Preference | Erwartung |
|---|---|---|
| EP10 | `'0'` (Standard) | Nur aktueller Baum |
| EP11 | `'1'` | Multi-Tree-Suche möglich |

---

## Grenzwerte (BVA)

- Anzahl Treffer: 0, 1 (Redirect-Grenze), 2 (kein Redirect mehr)
- Suchbegriff: Leerstring, 1 Zeichen, sehr langer String
- Kombination: 1 Individual + 0 andere = Redirect; 1 Individual + 1 Source = kein Redirect

---

## Empfohlene Strategie

**ISTQB B** für den Single-Result-Redirect — dies ist ein klar spezifiziertes Verhalten mit definierter Grenze (genau 1 Treffer). Die Tests für Flags und ALLOW_CHANGE_GEDCOM sind **Pragmatisch C** (Verhaltens-Verifikation). Dies ist **Priorität 2** (→ Common, Priorität-Roadmap).

---

## Konkrete Testideen

```
test_search_single_individual_result_redirects_to_individual()
test_search_two_individual_results_shows_list()
test_search_single_family_result_redirects_to_family()
test_search_mixed_results_shows_list_not_redirect()
test_search_no_flags_defaults_to_individuals_and_families()
test_search_allow_change_gedcom_enables_multi_tree()
```

---

## Aufwand

**Mittel** — Der Single-Result-Redirect-Test benötigt kontrollierten Demo-Baum mit exakt 1 Person namens X. Der bestehende Demo-Baum enthält viele Windsor-Einträge → spezifischerer Fixture oder gezielter Suchbegriff für Einzel-Ergebnis nötig.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt überein; `redirect()` gibt 302 STATUS_FOUND; `searchFamilyNames` sucht in `n_full` (husb+wife); Default-Fallback (lines 95-98) bestätigt; keine Korrekturen nötig |
| P2: Soll-Design | ✅ DONE | 3 neue Tests: EP2 Individual-Redirect (neues Fixture search-redirect-test.ged), EP4 Family-Redirect (gleiche Fixture), EP8 Default-Flags-Fallback (Demo-Baum, kein Flag, Windsor); existierende 3 Tests bleiben |
| P3: Test-Coding | ✅ DONE | SearchRequestHandlerIntegrationTest: 3 neue Tests (EP2 Individual-Redirect, EP4 Family-Redirect, EP8 Default-Fallback); neues Fixture search-redirect-test.ged |
| P4: Ausführung + Fixing | ✅ DONE | 6/6 grün, 20 Assertions, kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren (+Äquivalenzklassen S42, CRAP-Zeile S41, S43–S48), Abdeckungsmatrix, Endekriterien, Zusammenfassung (122 spec + 17 strukturbasiert), Changelog aktualisiert |
