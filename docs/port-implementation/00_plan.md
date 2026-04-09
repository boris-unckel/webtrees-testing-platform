<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# 00 — Implementierungsplan: Layer-2-Komponentest-Portierung

**Erstellt:** 2026-04-09
**Eingabe:** `docs/port_analysis_strategy.md` (Analyse), `docs/port_analysis_start.md` (Prompt)
**Ziel:** 292 Stub-only-Tests in substanzielle Komponentests mit Test Doubles umwandeln,
41 bestehende substanzielle Tests verbessern, SUT-Bugs identifizieren.

---

## 1. Kontext

Der Upstream-Maintainer hat den PR `5349_add_tests` abgelehnt, weil die Tests reale Services,
DB und `importTree()` verwenden statt Test Doubles. Die Analyse (`port_analysis_strategy.md`)
identifiziert:

- **292 Stub-Tests** (nur `class_exists()`) als Portierungskandidaten
- **41 substanzielle Tests** mit punktuellem Verbesserungspotenzial
- **4 Templates** (Service-Dep, Simple, Registry, Module) als Implementierungsmuster
- **~36 Feature-IDs** als "nur Layer 3" (explizit ausgeschlossen)

## 2. Repo-Workflow

Siehe `01_repo_setup.md` im Detail. Kurzfassung:

1. **Fork-Repo:** `/home/borisunckel/phpprojects/webtrees-upstream/webtrees`
2. **Branch:** Neuer Branch `port-layer2-test-doubles` von Fork-`main`
3. **Validierung:** `make test-unit` im Testing-Platform-Container
   (`WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees`)
4. **Commits:** Manuell am Ende durch den User, nicht automatisch während des Laufs

## 3. Phasen

| Phase | Beschreibung | Artefakte |
|-------|-------------|-----------|
| **P0** | Repo-Setup | Branch erstellt, `WEBTREES_SOURCE` konfiguriert |
| **P1** | Batch-Ausführung (8 Kategorien) | Portierte Tests in `tests/app/Http/RequestHandlers/`, `tests/app/Module/`, `tests/app/Services/` |
| **P2** | Verbesserung der 41 bestehenden Tests | Modifizierte Tests |
| **P3** | Validierung | `make test-unit` grün, keine Regressionen |
| **P4** | Dokumentation | `docs/tp_upstream_spec.md` neu geschrieben |

### P1: Batch-Ausführung — Reihenfolge

Die 8 Batches werden nach Priorität (D.1) innerhalb eines Durchlaufs abgearbeitet.
Jeder Batch deckt eine Feature-Kategorie ab:

| Batch | Kategorie | Feature-IDs | Stubs (ca.) | Prio-Schwerpunkt |
|-------|-----------|------------|-------------|------------------|
| `batch_SEC` | Sicherheit | SEC-* | ~14 | Prio 1 (Sicherheitsrelevanz) |
| `batch_P` | Datenschutz & Zugriff | P01–P41 | ~20 | Prio 1+2 (Sicherheit + Komplexität) |
| `batch_S` | Suche & Navigation | S01–S53 | ~37 | Prio 2+3 (Komplexität + Deps) |
| `batch_G` | GEDCOM Import/Export | G01–G30 | ~23 | Prio 3 (Dependencies) |
| `batch_A` | Administration | A01–A11 | ~6 | Prio 3+4 (Deps + CRAP) |
| `batch_E` | Datenpflege | E01–E08 | ~8 | Prio 3 (Dependencies) |
| `batch_K` | Kommunikation | K01–K02 | ~2 | Prio 5 (einfach) |
| `batch_U` | Utilities | U01–U02 | ~1 | Prio 5 (einfach) |

**Gesamt:** ~111 Stubs mit konkretem Template-Mapping. Zusätzliche Stubs ohne
Feature-ID-Mapping werden in Phase P1 per Batch-Discovery erfasst.

### P2: Bestandsverbesserung

Die 41 bestehenden substanziellen Tests werden in derselben Runde mitbehandelt.
Siehe `05_existing_improvements.md` für die konkrete Liste.

### P3: Validierung

```bash
cd /home/borisunckel/phpprojects/webtrees-testing-platform
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees make test-unit
```

- Alle portierten Tests müssen grün sein
- Alle bestehenden Tests müssen weiterhin grün sein
- Kein Delta in der Gesamtzahl der passierten Tests (außer Zuwachs)

### P4: Dokumentation

`docs/tp_upstream_spec.md` ist veraltet und wird am Ende auf Basis der erreichten
Ergebnisse grundlegend neu geschrieben. Siehe `06_tp_upstream_rewrite.md`.

## 4. Template-Zuordnung

Jeder Stub wird genau einem Template zugeordnet. Die Zuordnung erfolgt durch
Inspektion der SUT-Klasse:

| Entscheidung | Template |
|-------------|----------|
| SUT hat Service-Konstruktor-Dependencies | **Template 1** (`prompt_template_1_handler_service.md`) |
| SUT hat keine Konstruktor-Dependencies | **Template 2** (`prompt_template_2_handler_simple.md`) |
| SUT greift auf `Registry::*Factory()` zu | **Template 3** (`prompt_template_3_handler_registry.md`) |
| SUT ist ein Module mit `handle()` | **Template 4** (`prompt_template_4_module_handle.md`) |

Kombinations-Fälle (z.B. Service-Dep + Registry) → Template 3 (superset).

## 5. Bug-Erkennung

Jeder Prompt enthält einen expliziten Schritt zur Bug-Erkennung:

> **Prüfe:** Ist der Test grün, aber die SUT-Implementierung eigentlich fehlerhaft?
> Ein grüner Test mit einem Bug im SUT-Code ist gefährlicher als ein fehlender Test.

Indikatoren:
- Exception-Typ stimmt nicht zum HTTP-Kontext (z.B. `\RuntimeException` statt `HttpNotFoundException`)
- Fehlende Input-Validierung (Request-Attribut ohne Typ-Check)
- Unreachable Code (Dead Branches, die kein Test-Double-Setup triggern kann)
- Inkonsistenz mit verwandten Handlern (z.B. ein Admin-Handler ohne Auth-Check)

Befunde werden im Batch-Status als `bug_candidate` markiert und im INDEX dokumentiert.

## 6. Ausschlüsse

Feature-IDs, deren Tests nur mit realer Datenbank sinnvoll sind, werden **nicht**
portiert. Siehe `04_exclusions.md` für die vollständige Liste mit Begründung.

## 7. Ordnerstruktur

```
docs/port-implementation/
├── 00_plan.md                              ← dieses Dokument
├── 01_repo_setup.md                        Branch-Setup, Validierung
├── 02_prompts/                             Prompt-Templates (je 1 pro Template-Typ)
│   ├── prompt_template_1_handler_service.md
│   ├── prompt_template_2_handler_simple.md
│   ├── prompt_template_3_handler_registry.md
│   └── prompt_template_4_module_handle.md
├── 03_batches/                             Batch-Definitionen (je 1 pro Kategorie)
│   ├── batch_G_gedcom.md
│   ├── batch_S_search_navigation.md
│   ├── batch_P_privacy_access.md
│   ├── batch_SEC_security.md
│   ├── batch_E_data_entry.md
│   ├── batch_A_admin.md
│   ├── batch_K_communication.md
│   └── batch_U_utilities.md
├── 04_exclusions.md                        Explizit ausgeschlossene L3-only Tests
├── 05_existing_improvements.md             Verbesserungen der 41 bestehenden Tests
├── 06_tp_upstream_rewrite.md               Plan für tp_upstream_spec.md Neufassung
└── tasks/
    └── INDEX.md                            Master-Tracking
```

## 8. Cross-Referenzen

| Dokument | Zweck |
|----------|-------|
| `docs/port_analysis_start.md` | Ursprünglicher Analyse-Prompt |
| `docs/port_analysis_strategy.md` | Analyse-Ergebnis (Taxonomie, Patterns, Matrix) |
| `docs/tds_coverage_ref.md` | Layer-3-Coverage-Referenz (Feature-IDs) |
| `docs/security-audit/10_fixing_and_disclosure.md` §1 | Drei-Repo-Struktur (Vorbild) |
| `CLAUDE.md` | Infrastruktur, Testausführung, Konventionen |
