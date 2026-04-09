<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# 06 — Plan: Neufassung `docs/tp_upstream_spec.md`

## Status

**Blockiert bis:** Phase P3 (Validierung) abgeschlossen.

## Kontext

`docs/tp_upstream_spec.md` ist veraltet. Es beschreibt den falschen Ansatz
(reale Services statt Test Doubles), referenziert falsche Pfade und spiegelt
nicht den aktuellen Stand der Portierung wider.

## Vorgehen

Nach Abschluss der Portierungsrunde (Phasen P0–P3) wird `tp_upstream_spec.md`
grundlegend neu geschrieben. Die Neufassung basiert auf:

1. **Erreichte Ergebnisse:** Welche Tests wurden tatsächlich portiert?
   Welche Patterns haben funktioniert? Welche Probleme sind aufgetreten?

2. **Maintainer-Feedback:** Die destillierten Anforderungen aus
   `port_analysis_strategy.md` §A (Test-Double-Taxonomie) und §B.2
   (Anforderungsprofil R1–R11).

3. **Template-Dokumentation:** Die 4 Prompt-Templates aus `02_prompts/`
   als kanonische Referenz für Test-Patterns.

4. **Aktuelle Batch-Ergebnisse:** Status-Tabellen aus `03_batches/`
   und `tasks/INDEX.md`.

5. **Bug-Befunde:** Eventuelle SUT-Bugs, die während der Portierung
   entdeckt wurden.

6. **Ausschlüsse:** Begründete Layer-3-Abgrenzung aus `04_exclusions.md`.

## Erwarteter Inhalt der Neufassung

- Upstream-Contribution-Richtlinien für Tests (Test-Double-Konventionen)
- Vollständige Test-Coverage-Matrix (IST nach Portierung)
- Referenz auf Prompt-Templates für künftige Erweiterungen
- Mapping: Feature-ID → Testklasse → Template-Typ
- Abgrenzung Layer 2 vs. Layer 3
