<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S47: Interaktiver Stammbaum

**Referenz:** S47 | **SUT:** `app/Module/InteractiveTree/TreeView.php`  
**Aktueller Test:** `InteractiveTreeIntegrationTest` (2 Tests: getDetails, getIndividuals)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Zwei Smoke-Tests: `getDetails()` gibt HTML-String zurück, `getIndividuals()` für Eltern/Kinder gibt HTML zurück. Keine strukturelle Prüfung des erzeugten HTML, keine Edge-Cases.

---

## SUT-Kernbefunde

`TreeView` generiert interaktives HTML-Baumdiagramm:

| Methode / Branch | Bedingung | Bisher getestet? |
|---|---|---|
| `getIndividuals()` — Eltern | `$state === 1` (aufsteigende Linie) | ✅ (Smoke) |
| `getIndividuals()` — Kinder | `$state === -1` (absteigende Linie) | ✅ (Smoke) |
| `drawPerson()` — mit Partner | Person hat Ehepartner | ❌ |
| `drawPerson()` — ohne Partner | Unverheiratet | ❌ |
| `drawPerson()` — RTL | Rechtsbündige Sprache (z.B. Arabisch) | ❌ |
| `drawChildren()` — mehrere Kinder | 2+ Kinder | ❌ |
| `drawChildren()` — kein Kind | Person ohne Nachkommen | ❌ |
| `getDetails()` — unbekannte XREF | Person nicht in Baum | ❌ |
| Generation-Counter | `$count > 0` → weitere Generationen | ❌ |

**Besonderheiten:**
- Generation-Counter steuert Rekursionstiefe des Diagramms
- `drawPerson()` generiert HTML-`div`-Elemente mit AJAX-Links für weitere Generationen
- Die Ausgabe enthält XREF-basierte CSS-IDs für JavaScript-Interaktivität

---

## Äquivalenzklassen (EP)

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP1 | Person mit 2 Elternteilen | HTML enthält 2 Parent-Boxes |
| EP2 | Person ohne Eltern (root) | HTML enthält leere Parent-Placeholder |
| EP3 | Person mit 3+ Kindern | HTML enthält N Child-Boxes |
| EP4 | Person ohne Kinder | HTML enthält Placeholder oder leer |
| EP5 | Person mit Partner | Partner-Box im HTML |
| EP6 | Person ohne Partner | Keine Partner-Box |
| EP7 | Unbekannte XREF | Graceful degradation (kein Fehler) |

---

## Grenzwerte (BVA)

- Kind-Anzahl: 0, 1, 2, viele (HTML-Struktur konsistent?)
- Generations-Counter: 0 (nur aktuelle Person), 1, 3 (typisch), sehr tief
- XREF: Gültige XREF, leerer String, Nicht-XREF-Format

---

## Empfohlene Strategie

**Pragmatisch C** — kein strenger GEDCOM-Standard für HTML-Ausgabe. Wichtigster Gewinn: **HTML-Strukturvalidierung** (enthält xref-ID im Output? Enthält Links für weitere Generationen?).

**ISTQB B** für EP1 vs. EP2 (mit vs. ohne Eltern) — das ist der wichtigste Äquivalenzklassen-Split.

---

## Konkrete Testideen

```
test_get_individuals_returns_html_containing_person_xref()
test_get_individuals_handles_person_without_parents()
test_get_individuals_handles_person_with_multiple_children()
test_get_details_handles_unknown_xref_gracefully()
test_draw_person_with_partner_includes_partner_in_output()
```

---

## Aufwand

**Mittel** — HTML-Struktur-Assertionen via `assertStringContainsString()` auf bekannte XREF-IDs und CSS-Klassen aus dem Demo-Fixture.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | Test 1 (getDetails): assertNotEmpty vorhanden, aber kein XREF-Inhalt geprüft; Test 2+3 (getIndividuals): nur assertIsString (trivial). Upgrade assertions: assertNotEmpty+HTML-Assertion für Test 2+3, XREF-Assertion für Test 1. Kein neuer Test nötig (Pragmatisch C). EP7 (unknown XREF) nicht direkt testbar ohne null-Individual. |
| P2: Soll-Design | ✅ DONE | Test 1: +assertStringContainsString('X1030', $result); Test 2: assertIsString→assertNotEmpty+HTML; Test 3: assertIsString→assertNotEmpty+HTML; Docblocks um EP-Referenzen ergänzt |
| P3: Test-Coding | ✅ DONE | InteractiveTreeIntegrationTest.php: Test1 +assertStringContainsString('X1030'); Test2+3 assertIsString→assertNotEmpty+assertStringContainsString('<'); Docblocks mit EP-Refs |
| P4: Ausführung + Fixing | ✅ DONE | 3/3 grün, 13 Assertions; kein Fixing nötig; assertStringContainsString('X1030') bestätigt XREF im Output |
| P5: Big-Picture | ✅ DONE | Feature-Matrix Pragmatisch C, Äquivalenzklassen-Eintrag S47, CRAP-Zeile korrigiert (S47 entfernt → S45, S48), Endekriterien, Abdeckungsmatrix, Zusammenfassung 131→132 spec / 8→7 struct, Changelog aktualisiert |
