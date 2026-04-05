<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — G28: OBJE-Metadaten bearbeiten

**Referenz:** G28 | **SUT:** `app/Http/RequestHandlers/EditMediaFileAction.php`  
**Aktueller Test:** `EditMediaFileIntegrationTest` (1 Test: Redirect ohne HTTP 500)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Ein einziger Happy-Path-Test: POST-Request mit gültigem OBJE-XREF → Redirect. Kein DB-Zustand geprüft, keine Fehlerszenarien.

---

## SUT-Kernbefunde

`EditMediaFileAction::handle()` extrahiert aus dem POST-Body: `xref`, `fact_id`, `type`, `title` und führt GEDCOM-Update durch.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Record gefunden | xref → OBJE existiert in DB | ✅ (Smoke) |
| Record nicht gefunden | xref → kein OBJE | ❌ |
| Fact nicht gefunden | fact_id existiert nicht im Record | ❌ |
| Kein Edit-Recht | User ist kein Editor | ❌ |
| Leerer Titel | `title=''` | ❌ |
| Ungültiger Medientyp | `type` nicht in GEDCOM-Enum | ❌ |
| Datenbankzustand | GEDCOM-String tatsächlich aktualisiert | ❌ |

**Invarianten:** xref muss gültiges OBJE-Record sein; User muss Editor sein; `type` ist GEDCOM-Medientyp (photo, tombstone, etc.); Änderung landet in `gedcom_record` als Update.

---

## Äquivalenzklassen (EP)

| Klasse | Input | Erwartung |
|---|---|---|
| EP1 | Gültiger xref, gültige type+title | Redirect, GEDCOM aktualisiert |
| EP2 | Ungültiger xref (kein OBJE) | Exception oder Redirect-Fehler |
| EP3 | `type=''` (leer) | Valide (kein Typ gesetzt) oder Fehler |
| EP4 | `title=''` (leer) | Valide (kein Titel) |
| EP5 | `title` mit Sonderzeichen / HTML | Escaping korrekt |

---

## Grenzwerte (BVA)

- `type`: Erster gültiger GEDCOM-Typ (`photo`), letzter (`tombstone`), ungültiger Typ
- `title`: Leerstring, sehr langer String, String mit `\n`-Zeichen

---

## Empfohlene Strategie

**Pragmatisch C** — Der Hauptgewinn liegt nicht in EP-Matrizen (die Eingaben sind einfach), sondern in der **Post-Condition-Verifizierung** (GEDCOM-String tatsächlich geändert) und den Guard-Clause-Tests (xref nicht gefunden, kein Recht). → Common Abschnitt 4.1 und 4.2.

---

## Konkrete Testideen

```
test_edit_media_file_updates_gedcom_string()         ← DB-Postcondition
test_edit_media_file_fails_with_invalid_xref()
test_edit_media_file_fails_without_editor_role()
test_edit_media_file_handles_empty_title_gracefully()
```

---

## Aufwand

**Mittel** — GEDCOM-Verifizierung benötigt `$individual->facts(['FILE'])` oder direkten DB-Lese-Check.

---

## P1-Korrekturen (Konsistenzcheck)

- "Record nicht gefunden": `Validator->isXref()` validiert Format, dann `Auth::checkMediaAccess(null, true)` → HttpNotFoundException. ✅
- "Fact nicht gefunden" (`$media_file === null`): bereits durch bestehenden Test (fact_id='') abgedeckt. ✅
- **Fehlend in Spec:** `$remote !== ''` vs. Folder/File-Pfad-Konstruktion; Filesystem-Move-Exception-Pfade; `acceptRecord` nur wenn `$old !== $new && !isExternal()`.
- Pragmatisch C: Fokus auf DB-Postcondition — change-Tabelle nach `updateFact()`. Fact-not-found-Guard bereits getestet.
- Wenn `new_file=''`, bleibt Dateiname unverändert (`$old === $new`) → `acceptRecord` NICHT aufgerufen → change bleibt pending. Ideal für Postcondition-Check.

## P2-Soll-Design

| Test | Methode | Begründung |
|---|---|---|
| EP1 Happy Path DB-Postcondition | `test_edit_media_file_happy_path_creates_pending_change_with_updated_title()` | Gültige fact_id + title='Updated Title', type='photo' → change-Tabelle hat pending new_gedcom mit Titel |

**Fixture:** demo.ged (setUp); fact_id dynamisch via `$media->mediaFiles()->first()->factId()`.  
**Postcondition:** `DB::table('change')->where('status', 'pending')->value('new_gedcom')` enthält 'Updated Title'.

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | Filesystem-Branches dokumentiert; Pragmatisch C auf change-Postcondition fokussiert; fact_id=''-Guard bereits getestet |
| P2: Soll-Design | ✅ DONE | 1 neuer Test: Happy Path DB-Postcondition (change-Tabelle), Gesamt: 2 Tests |
| P3: Test-Coding | ✅ DONE | 1 neuer Test in EditMediaFileIntegrationTest: happy_path_creates_pending_change_with_updated_title |
| P4: Ausführung + Fixing | ✅ DONE | Voll-Lauf: 556/556 grün, 1823 Assertions |
| P5: Big-Picture | ✅ DONE | Feature-Matrix G28 auf spezifikationsbasiert, 2 Tests; Abdeckungsmatrix, Endekriterien, Zusammenfassung aktualisiert |
