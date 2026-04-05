<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S44: Report-Parser Erweitert

**Referenz:** S44 | **SUT:** `app/Report/ReportParserGenerate.php`  
**Aktueller Test:** `ReportParserGenerateExtendedIntegrationTest` (3 Tests: relatives/ancestors/descendants)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Die 3 Tests prüfen, dass SAX-Handler ohne Exception durchlaufen. Es wird nicht geprüft, was die Handler ausgeben oder wie viele Personen/Fakten sie verarbeiten.

---

## SUT-Kernbefunde

`ReportParserGenerate` verarbeitet XML-Report-Definitionen via SAX-Parser und ruft dabei spezifische Handler auf:

| Handler / Methode | Beschreibung | Output-Validierung bisher |
|---|---|---|
| `relativesStartHandler()` | Verarbeitet Verwandten-Report | ❌ (nur kein Exception) |
| `addAncestors()` | Rekursiver Vorfahren-Aufbau | ❌ |
| `addDescendancy()` | Rekursiver Nachfahren-Aufbau | ❌ |
| `imageStartHandler()` | Bild-Einbettung in Report | ❌ |
| `individualStartHandler()` | Individuum-Fakten-Ausgabe | ❌ |

**Besonderheiten:**
- `addAncestors()` und `addDescendancy()` sind rekursiv → Tiefe der Rekursion ist durch Demo-Fixture begrenzt
- `imageStartHandler()` versucht Bilddatei zu laden → wenn Datei fehlt, stille Fehlerbehebung vs. Exception?
- Alle Handler mutieren intern den Report-Renderer-Zustand; die Ausgabe ist erst nach `ob_get_clean()` verfügbar

---

## Äquivalenzklassen (EP)

| Klasse | Input-Zustand | Erwartung |
|---|---|---|
| EP1 | Person mit bekannten Vorfahren (2 Generationen) | `addAncestors` läuft 2 Levels tief |
| EP2 | Person ohne Vorfahren (root person) | `addAncestors` terminiert sofort |
| EP3 | Person mit Nachfahren (2 Generationen) | `addDescendancy` korrekt |
| EP4 | Person ohne Nachfahren | `addDescendancy` terminiert sofort |
| EP5 | Person mit Bild (OBJE-Link) | `imageStartHandler` verarbeitet Bild |
| EP6 | Person ohne Bild | `imageStartHandler` überspringt |
| EP7 | Person mit Fakten (BIRT, DEAT, MARR) | `individualStartHandler` gibt Fakten aus |
| EP8 | Ungültige XREF in Report-Input | Handler bricht nicht ab, leere Ausgabe |

---

## Grenzwerte (BVA)

- Rekursionstiefe: 0 (keine Vorfahren), 1, 2 (Demo-Fixture typisch), sehr tief (Stack-Overflow-Risiko)
- Faktenmenge: 0 Fakten, 1 Fakt, viele Fakten pro Person
- `XREF`: Leerer String, ungültiger XREF-Format

---

## Empfohlene Strategie

**Pragmatisch C** für die meisten Branches — die SAX-Handler-Komplexität macht vollständige EP-Abdeckung aufwändig. Wichtigster Gewinn: **Output-Validierung statt nur No-Exception**. Mindestens prüfen, dass die Ausgabe nicht leer ist und einen validen HTML/PDF-Anfang hat.

**ISTQB B** für EP1 vs. EP2 (mit vs. ohne Vorfahren) — das ist der wichtigste Äquivalenzklassen-Split.

---

## Konkrete Testideen

```
test_relatives_report_produces_non_empty_output()
test_ancestors_report_handles_person_without_ancestors()
test_descendants_report_handles_person_without_descendants()
test_individual_report_output_contains_person_name()
test_image_handler_graceful_when_file_missing()
```

---

## Aufwand

**Mittel** — Output-Capture via `ob_start()/ob_get_clean()` ist bereits in bestehenden Tests etabliert. Erweiterung der bestehenden Klasse um 2–3 Tests mit konkreten Output-Assertionen.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | assertIsString($output) trivial (ob_get_clean() immer string). relative_ext_report enthält immer Root-Person → assertNotEmpty sicher. Upgrade auf assertNotEmpty + HTML-Assertion für alle 3 Tests ausreichend; EP2/EP4 Rekursionsabbruch durch Demo-Fixture implizit abgedeckt |
| P2: Soll-Design | ✅ DONE | 3 bestehende Tests upgraden: assertIsString → assertNotEmpty + assertStringContainsString('<', $output); kein neuer Test nötig (Pragmatisch C, EP2/EP4 durch Demo implizit) |
| P3: Test-Coding | ✅ DONE | 3 Assertions upgraded: assertIsString → assertNotEmpty + assertStringContainsString('<', $output); Docblocks um EP-Referenzen ergänzt |
| P4: Ausführung + Fixing | ✅ DONE | 3/3 grün, 14 Assertions; kein Fixing nötig; assertNotEmpty bestätigt non-empty Output für alle 3 Reports |
| P5: Big-Picture | ✅ DONE | Feature-Matrix Pragmatisch C, Äquivalenzklassen-Eintrag S44, CRAP-Zeile korrigiert (S45–S48), Endekriterien, Abdeckungsmatrix, Zusammenfassung 129→130 spec / 10→9 struct, Changelog aktualisiert |
