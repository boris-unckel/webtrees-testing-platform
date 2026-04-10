<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Port-Implementation Task Index

Tracking-Index für die Layer-2-Komponentest-Portierung.
Quelle der Wahrheit: Status-Tabellen in den Batch-Dateien unter `03_batches/`.

## Legende — Status

| Status | Bedeutung |
|--------|-----------|
| `pending` | Noch nicht begonnen |
| `in_progress` | Portierung läuft |
| `completed` | Test portiert und grün |
| `bug_candidate` | Test grün, aber SUT-Bug vermutet |
| `excluded` | Explizit Layer-3-only (siehe `04_exclusions.md`) |
| `improved` | Bestehender substanzieller Test verbessert |
| `skipped` | Übersprungen mit Begründung |

## Batch-Status

| Batch | Kategorie | Feature-IDs | Portierbar (ca.) | Ausgeschlossen | Status | Letzte Änderung |
|-------|-----------|------------|-----------------|----------------|--------|----------------|
| `batch_SEC` | Sicherheit | SEC-* | 14 | 0 | `completed` | 2026-04-10 |
| `batch_P` | Datenschutz & Zugriff | P01–P41 | 17 | 24 | `completed` | 2026-04-10 |
| `batch_S` | Suche & Navigation | S01–S53 | 37 | 8 | `completed` | 2026-04-10 |
| `batch_G` | GEDCOM Import/Export | G01–G30 | 12 | 18 | `completed` | 2026-04-10 |
| `batch_A` | Administration | A01–A11 | 10 | 1 | `completed` | 2026-04-10 |
| `batch_E` | Datenpflege | E01–E08 | 8 | 0 | `completed` | 2026-04-10 |
| `batch_K` | Kommunikation | K01–K02 | 2 | 0 | `completed` | 2026-04-10 |
| `batch_U` | Utilities | U01–U02 | 1 | 1 | `completed` | 2026-04-10 |

## Bestandsverbesserung (Phase P2)

| Bereich | Testdateien | Status | Letzte Änderung |
|---------|-------------|--------|----------------|
| Redirect-Tests (29) | `05_existing_improvements.md` §1 | `pending` | 2026-04-09 |
| UpgradeWizardStepTest | `05_existing_improvements.md` §2 | `pending` | 2026-04-09 |
| LoginPageTest | `05_existing_improvements.md` §3 | `improved` | 2026-04-10 |
| BroadcastPageTest | `05_existing_improvements.md` §4 | `skipped` | 2026-04-10 |
| SelectLanguageTest | `05_existing_improvements.md` §5 | `improved` | 2026-04-10 |

## Dokumentation (Phase P4)

| Artefakt | Status | Letzte Änderung |
|----------|--------|----------------|
| `docs/tp_upstream_spec.md` Neufassung | `pending` | 2026-04-10 |

## Aggregat

- Batches gesamt: 8
- Batches abgeschlossen: 8
- Tests portiert: 264 Dateien, +478 Testmethoden (3296 → 3774)
- Assertions: 150475 → 154609 (+4134)
- Bug-Kandidaten: 0
- Ausgeschlossen (L3-only): ~52 Feature-IDs
- Bestehende Tests verbessert: 2/5 Bereiche (LoginPage, SelectLanguage)
- Validierung (`make test-unit`): **bestanden** — 0 neue Failures, 3 vorbestehende (MaintenanceModeService)

## Cross-Referenzen

- Batch-Definitionen: `../03_batches/`
- Ausschlüsse: `../04_exclusions.md`
- Bestandsverbesserungen: `../05_existing_improvements.md`
- Prompt-Templates: `../02_prompts/`
- Master-Plan: `../00_plan.md`
