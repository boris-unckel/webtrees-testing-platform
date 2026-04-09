<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Batch U — Querschnitts-Utilities

**Priorität:** 5 (einfach, zustandslos)
**Feature-IDs:** U01–U02

---

## Portierbare Tests

| # | Test-Datei | SUT-Klasse | Template | Dependencies | Status | Bemerkung |
|---|-----------|------------|----------|-------------|--------|-----------|
| 1 | — | — | — | — | — | U01 ValidatorTest bereits **vollständig substanziell** (24 Methoden, 391 LoC) |

### Bestehende substanzielle Tests

| Test-Datei | Methoden | Verbesserungspotenzial |
|-----------|----------|----------------------|
| `ValidatorTest.php` | 24 | Umfangreichster Unit-Test — vollständig, kein Handlungsbedarf |

## Ausgeschlossen

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| U02 | CountryService | Deprecated |

## Statistik

- Portierbar: 0 (bereits vollständig)
- Ausgeschlossen: 1 (U02 deprecated)
- Bereits substanziell: ValidatorTest (24 Methoden)
