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

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ⬜ OPEN | — |
| P2: Soll-Design | ⬜ OPEN | — |
| P3: Test-Coding | ⬜ OPEN | — |
| P4: Ausführung + Fixing | ⬜ OPEN | — |
| P5: Big-Picture | ⬜ OPEN | — |
