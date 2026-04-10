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
| `batch_SEC` | Sicherheit | SEC-* | 12 | 11 | 0 | 1 | `completed` | 2026-04-10 |
| `batch_P` | Datenschutz & Zugriff | P01–P41 | 17 | 17 | 0 | 0 | `completed` | 2026-04-10 |
| `batch_S` | Suche & Navigation | S01–S53 | 42 | 41 | 0 | 1 | `completed` | 2026-04-10 |
| `batch_G` | GEDCOM Import/Export | G01–G30 | 11 | 9 | 0 | 2 | `completed` | 2026-04-10 |
| `batch_A` | Administration | A01–A11 | 14 | 13 | 0 | 1 | `completed` | 2026-04-10 |
| `batch_E` | Datenpflege | E01–E08 | 51 | 46 | 0 | 5 | `completed` | 2026-04-10 |
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
- Batches vollständig abgeschlossen: 8 (SEC, P, S, G, A, E, K, U)
- Batches mit Restposten: 0
- Tests portiert: 278 Dateien, +517 Testmethoden (3283 → 3800)
- Assertions: 150396 → 152711 (+2315)
- **Verbleibende Stubs (pending):** 0
- **Skipped:** 10 Tests (8× Testdatei fehlt im Upstream, 2× L3-only: SetupWizard, GedcomLoad)
- Bug-Kandidaten: 0
- Ausgeschlossen (L3-only): ~52 Feature-IDs
- Bestehende Tests verbessert: 5/8 Bereiche (Redirect, UpgradeWizard, LoginPage, BroadcastPage, SelectLanguage)
- Bestehende Tests geprüft/übersprungen: 3/8 Bereiche (PingTest, ModuleActionTest, DeleteUserTest — bereits vollständig)
- Validierung (`make test-unit`): **bestanden** — 0 Failures, 3800 Tests, 152711 Assertions
- Dokumentation (P4): **abgeschlossen** — tp_upstream_spec.md neu gefasst

## Cross-Referenzen

- Batch-Definitionen: `../03_batches/`
- Ausschlüsse: `../04_exclusions.md`
- Bestandsverbesserungen: `../05_existing_improvements.md`
- Prompt-Templates: `../02_prompts/`
- Master-Plan: `../00_plan.md`
