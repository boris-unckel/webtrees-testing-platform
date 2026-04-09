<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-006 — D2 Hypothesen

## H1: SQL-Injection via `$old_xref` in Expression()

- **Vektor:** Ein xref-Wert mit SQL-Metazeichen (z.B. `'`, `;`, `--`) in `AdminService::duplicateXrefs()` bricht aus dem String-Literal in der `REPLACE()`-Expression aus.
- **Voraussetzung:** `$old_xref` enthält ein Single-Quote → SQL-Parse-Error oder SQLi
- **Aktuelle Mitigation:** `Gedcom::REGEX_XREF = '[A-Za-z0-9:_.-]{1,20}'` — keine Quotes, Backslashes, Semikolons in erlaubter Zeichenklasse. GEDCOM-Import-Parser validiert alle Xrefs beim Import.
- **Confidence:** REJECTED (heute). LATENT (falls REGEX_XREF jemals gelockert wird).
- **Bewertung:** Nicht exploitierbar, solange L1 (REGEX_XREF) intakt bleibt. Kein direkter Angriffsweg für Admin oder Nicht-Admin.

## H2: SQL-Injection via `$type` im default-Case

- **Vektor:** Der `default`-Case (Zeilen 424–498) interpoliert `$type` in Expression. `$type` kommt aus `o_type`-DB-Spalte.
- **Voraussetzung:** Ein manipulierter `o_type`-Wert mit SQL-Metazeichen in der DB.
- **Aktuelle Mitigation:** `o_type` wird vom GEDCOM-Parser geschrieben und enthält nur GEDCOM-Recordtypen (4-Buchstaben-Uppercase-Tags). VARCHAR-Constraint limitiert Länge.
- **Confidence:** REJECTED. Gleiche Cross-File-Dependency wie H1.

## H3: Second-Order-Injection via Data-Corruption

- **Vektor:** Wenn ein anderer Codepfad (Plugin, Migration, direkter DB-Zugriff) ungefilterte Daten in Xref-Spalten schreibt, könnten diese bei der nächsten Renumber-Operation zur Injection führen.
- **Voraussetzung:** Umgehung des GEDCOM-Import-Parsers.
- **Aktuelle Mitigation:** Alle bekannten Schreibpfade validieren gegen REGEX_XREF.
- **Confidence:** REJECTED (aktuell). Aber Defense-in-Depth-relevant: lokale Validierung schließt das Risiko unabhängig von externen Schreibpfaden.

## Zusammenfassung

| Hypothese | Status | Exploitierbar heute | Latentes Risiko |
|---|---|---|---|
| H1 | rejected | Nein | Ja, falls REGEX_XREF gelockert |
| H2 | rejected | Nein | Ja, falls o_type ungefiltert beschreibbar |
| H3 | rejected | Nein | Ja, falls Xref-Spalten direkt beschrieben |

## Empfohlener Fix

**Minimum (lokale Assertion):** `preg_match('/\A' . Gedcom::REGEX_XREF . '\z/', $old_xref)` am Anfang der foreach-Schleife. Malformed Xrefs werden übersprungen.

**Warum nicht str_replace auf PHP-Seite:** Das wäre eine architektonische Änderung (31 DB-Updates → 31 Reads + PHP-Replace + 31 Writes), ändert Performance-Charakteristik und Behavior, geht über Defense-in-Depth hinaus.
