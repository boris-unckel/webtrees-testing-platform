<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Teststrategie — webtrees-testing-platform

Dieses Dokument ist der Einstiegspunkt in die Teststrategie-Dokumentation.
Es verlinkt alle Subdokumente und ordnet sie inhaltlich ein.

**Projekt:** webtrees-testing-platform — Test-Infrastruktur für den webtrees-Core
(Genealogie-Webapplikation, PHP 8.5, Podman Compose Stack).

**Testumfang:** 7 Domänen (GEDCOM Import/Export, Suche & Navigation, Privacy & Zugriffskontrolle,
Sicherheit, Editing, Administration, Kommunikation). 5 Teststufen (Statische Analyse,
Komponententest, Komponentenintegrationstest, Systemtest, Performanztest).
Aktueller Stand: siehe [Testbedingungen](tds_conditions_ref.md) und [Abdeckungsmatrix](tds_coverage_ref.md).

---

## Testplan (tp_*)

| Dokument | Inhalt |
|---|---|
| [Designentscheidungen](tp_decisions_spec.md) | 35 architektonische Designentscheidungen, Layer↔ISTQB-Teststufenzuordnung, Mermaid-Architekturdiagramm. Definiert *warum* die Testinfrastruktur so aufgebaut ist. |
| [Infrastruktur](tp_infrastructure_spec.md) | 7 Infrastruktur-Entscheidungen (N1–N7): Podman-Runtime, Verzeichnisstruktur, Container-Stack (6+2 Container, 1 Netzwerk), Setup-Automatisierung, OTel-Tracing, Sicherheitstest-Container. |
| [Testkonventionen](tp_conventions_spec.md) | AAA-Pattern, FIRST-Prinzipien, Namenskonventionen, DataProvider-Regeln, Verfolgbarkeit via `@see`-Annotationen. Verbindliche Regeln für alle Teststufen. |
| [Risiken & Fehlermanagement](tp_risks_spec.md) | 21 Produktrisiken (R1–R21), Projektrisiken, Fehlermanagement-Prozess, 3 bekannte Fehler im Teststack. |
| [Überdeckung & Endekriterien](tp_ratchet_spec.md) | Ratchet-Strategie (Statement-Coverage darf nur steigen), Endekriterien pro Teststufe, Ist-Stand der Überdeckung (32,4% Statements), Sicherheitstest-Metriken. |
| [Upstream-Contribution](tp_upstream_spec.md) | Plan zur Befüllung von webtrees-Core Test-Stubs. Scope, Redundanz-Bewertung, Status (137 Tests / 450 Assertions, abgeschlossen). |

---

## Testdesign-Spezifikation (tds_*)

| Dokument | Inhalt |
|---|---|
| [Testbedingungen & Feature-Matrizen](tds_conditions_ref.md) | Alle 168 Features in 7 Domänen (GEDCOM G01–G30, Suche/Navigation S01–S53, Privacy P01–P41, Sicherheit SEC-*, Editing E01–E08, Administration A01–A11, Kommunikation K01–K02). RE-Methodik, Gap-Analyse, Domänenbeschreibungen. |
| [Testentwurfsverfahren & Orakel](tds_methodik_spec.md) | 43 Einträge Testentwurfsverfahren (Äquivalenzklassen, Grenzwertanalyse, Zustandsübergänge), 17 Orakelquellen, Testfall-Verteilung nach Teststufe, Prioritätsverteilung. |
| [Abdeckungsmatrix](tds_coverage_ref.md) | Vollständige Abdeckungsmatrix aller 168 Features: Testklassen, Qualitätsstufe (A–C + strukturbasiert), Teststatus. Nachschlagewerk für den aktuellen Teststand. |

---

## Workflows (wf_*)

| Dokument | Inhalt |
|---|---|
| [Test-Iteration (gemeinsam)](wf_test-iteration_guide.md) | Gemeinsamer Kern aller Test-Iterationen: Methodik (EP/BVA), Mock-Infrastruktur, Patterns, 5-Phasen-Arbeitsablauf, Pflicht-Constraints, Abschlussschritte (Voll-Lauf, Ratchet, Commit). |
| [Coverage → Test](wf_coverage-to-test_guide.md) | Entry: Testziele aus Coverage/CRAP-Analyse. Stack starten, Coverage messen, CRAP-Score analysieren, Implementierungsplan erstellen. Strukturvorlagen in Anhang A/B. AP-Dateien in `docs/coverage-runs/`. |
| [Code → Test](wf_code-to-test_guide.md) | Entry: Testziele aus Upstream-Code-Analyse. Handler-Inventarisierung, Feature-Analyse, Detailkonzepte. Vorlagen für Analyse-Prompt und Feature-Detailkonzepte. |
| [Code → Systemtest](wf_code-to-systemtest_guide.md) | Entry: Systemtests (L4 Playwright) aus Code-Analyse. L3-Referenzanalyse, Playwright-Patterns (Theme-Loop, Privacy-Role, Admin-Only, API-Only, Security-Audit), Feature-Spezifikationen unter `docs/systemtest/testspezi/`. |

---

## Referenzdokumente (ref_*)

| Dokument | Inhalt |
|---|---|
| [ISTQB-Glossar DE](ref_istqb-glossar_ref.md) | 589 Begriffe, deutsche Übersetzung des ISTQB-Glossars v4.7.1 (Lizenz: CC BY 4.0). Terminologische Grundlage für alle Testdokumente. |
| [webtrees-Glossar](ref_webtrees-glossar_ref.md) | Domänenglossar für webtrees-spezifische Begriffe (GEDCOM, Stammbäume, Module, Handler). |

---

## Security-Audit (tp_security-audit)

| Dokument | Inhalt |
|---|---|
| [Security-Audit-Framework](tp_security-audit_spec.md) | Whitebox-Security-Audit-Framework: Multi-Signal-Triage-Pipeline (mechanisch + LLM-Overlay), Bedrohungsmodell mit 7 Angriffsdomänen (D-AUTH…D-INFRA) und 12 vertikalen Hypothesen, SecurityTraceMiddleware (OTel-integriert), Agentic Loop Driver (Sweep/Deep-Dive), Fork-Repo-Kopplung. 11 Subdokumente unter `security-audit/`, 5 Prompt-Templates, statusbasierte Task-Persistenz. |

---

## Schnelleinstieg

```bash
make up                       # Stack starten
make setup                    # webtrees installieren (einmalig)
make test-all                 # Alle 5 Teststufen sequenziell
make crap-report              # CRAP-Score-Tabelle (Coverage-Analyse)
make down                     # Stack herunterfahren
```

Vollständige Make-Targets und Layer-Architektur: siehe [CLAUDE.md](../CLAUDE.md).
