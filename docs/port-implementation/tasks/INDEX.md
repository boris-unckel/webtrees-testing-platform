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

| Batch | Kategorie | Feature-IDs | Portierbar | Completed | Pending (Stub) | Skipped | Status | Letzte Änderung |
|-------|-----------|------------|-----------|-----------|---------------|---------|--------|----------------|
| `batch_SEC` | Sicherheit | SEC-* | 12 | 8 | 4 | 0 | `offen` | 2026-04-10 |
| `batch_P` | Datenschutz & Zugriff | P01–P41 | 17 | 17 | 0 | 0 | `completed` | 2026-04-10 |
| `batch_S` | Suche & Navigation | S01–S53 | 42 | 34 | 7 | 1 | `offen` | 2026-04-10 |
| `batch_G` | GEDCOM Import/Export | G01–G30 | 11 | 4 | 5 | 2 | `offen` | 2026-04-10 |
| `batch_A` | Administration | A01–A11 | 14 | 7 | 5 | 1 | `offen` | 2026-04-10 |
| `batch_E` | Datenpflege | E01–E08 | 51 | 37 | 4 | 5 | `offen` | 2026-04-10 |
| `batch_K` | Kommunikation | K01–K02 | 6 | 6 | 0 | 0 | `completed` | 2026-04-10 |
| `batch_U` | Utilities | U01–U02 | 1 | 1 | 0 | 0 | `completed` | 2026-04-10 |

## Bestandsverbesserung (Phase P2)

| Bereich | Testdateien | Status | Letzte Änderung |
|---------|-------------|--------|----------------|
| Redirect-Tests (29) | `05_existing_improvements.md` §1 | `improved` | 2026-04-10 |
| UpgradeWizardStepTest | `05_existing_improvements.md` §2 | `improved` | 2026-04-10 |
| LoginPageTest | `05_existing_improvements.md` §3 | `improved` | 2026-04-10 |
| BroadcastPageTest | `05_existing_improvements.md` §4 | `improved` | 2026-04-10 |
| SelectLanguageTest | `05_existing_improvements.md` §5 | `improved` | 2026-04-10 |
| PingTest | `05_existing_improvements.md` §6 | `skipped` | 2026-04-10 |
| ModuleActionTest | `05_existing_improvements.md` §7 | `skipped` | 2026-04-10 |
| DeleteUserTest | `05_existing_improvements.md` §8 | `skipped` | 2026-04-10 |

## Dokumentation (Phase P4)

| Artefakt | Status | Letzte Änderung |
|----------|--------|----------------|
| `docs/tp_upstream_spec.md` Neufassung | `completed` | 2026-04-10 |

## Aggregat

- Batches gesamt: 8
- Batches vollständig abgeschlossen: 3 (P, K, U)
- Batches mit Restposten: 5 (SEC, S, G, A, E)
- Tests portiert: 264 Dateien, +478 Testmethoden (3296 → 3774)
- Assertions: 150475 → 154609 (+4134)
- **Verbleibende Stubs (pending):** 25 Tests noch nicht portiert
- **Skipped (Testdatei fehlt):** 9 Tests (Upstream hat keine Testdatei)
- Bug-Kandidaten: 0
- Ausgeschlossen (L3-only): ~52 Feature-IDs
- Bestehende Tests verbessert: 5/8 Bereiche (Redirect, UpgradeWizard, LoginPage, BroadcastPage, SelectLanguage)
- Bestehende Tests geprüft/übersprungen: 3/8 Bereiche (PingTest, ModuleActionTest, DeleteUserTest — bereits vollständig)
- Validierung (`make test-unit`): **bestanden** — 0 neue Failures, 3 vorbestehende (MaintenanceModeService)
- Dokumentation (P4): **abgeschlossen** — tp_upstream_spec.md neu gefasst

## Cross-Referenzen

- Batch-Definitionen: `../03_batches/`
- Ausschlüsse: `../04_exclusions.md`
- Bestandsverbesserungen: `../05_existing_improvements.md`
- Prompt-Templates: `../02_prompts/`
- Master-Plan: `../00_plan.md`
