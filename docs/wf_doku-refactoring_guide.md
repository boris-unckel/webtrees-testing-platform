<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Workflow: Dokumentations-Refactoring

## Ziel

Die Testdokumentation der webtrees-testing-platform wird konsolidiert und in inhaltlich
kohärente Einzeldateien aufgeteilt. Ein Rahmendokument (`tp_overview_spec.md`) dient als
Navigationseinstieg. Zukünftige Konversationen können gezielt Einzeldateien laden, ohne den
Gesamtkontext zu benötigen.

---

## Entscheidungen (alle geklärt, keine offenen Punkte)

### Zielverzeichnis
`docs/` — alle neuen Dateien landen dort. Kein separates `doc/`-Verzeichnis.

### Namenskonvention
Schema: `<präfix>_<sprechender-name>_<suffix>.md`

**Präfix — ISTQB-Artefaktklasse:**

| Präfix | Bedeutung |
|---|---|
| `tp_` | Testplan-Inhalte: Strategie, Entscheidungen, Konventionen, Risiken, Infrastruktur |
| `tds_` | Testdesign-Spezifikation: Testbedingungen (Feature-Matrix), Methodik, Abdeckung |
| `wf_` | Workflow/Vorgehen: operative Prompts und Anleitungen |
| `ref_` | Referenz/Nachschlagewerk: Glossare (Lizenz-getrennt, Inhalt unverändert) |

**Suffix — Nutzungsart:**

| Suffix | Bedeutung |
|---|---|
| `_spec` | normative Spezifikation (stabil, wird referenziert) |
| `_guide` | prozedurale Anleitung (wird ausgeführt) |
| `_ref` | Nachschlagewerk (wird konsultiert, nicht normativ) |

### Feature-Matrix vs. Abdeckungsmatrix: getrennt (ISTQB-konform)
- `tds_conditions_ref.md` — Testbedingungen (Feature-Matrizen): stabil, ändert sich nur bei
  neuen Features/Domänen; entspricht ISTQB-Testdesign-Spezifikation
- `tds_coverage_ref.md` — Abdeckungsmatrix: dynamisch, ändert sich mit jedem Iterationszyklus;
  entspricht ISTQB-Testergebnis/Rückverfolgbarkeit

### Granularität
Thematisch zusammenführen — nicht jedes H2 als eigene Datei:
- Alle 7 Feature-Matrizen (G/S/P/SEC/E/A/K) in einer Datei (`tds_conditions_ref.md`)
- Alle Infrastruktur-Entscheidungen N1–N7 + Container-Stack in einer Datei
- RE-Methodik, Testentwurfsverfahren und Testorakel als thematische Einheit

### Historische Inhalte
Änderungshistorie + abgeschlossener Implementierungsfahrplan (12 Phasen) werden archiviert:
1. In Archiv-Datei überführen (`archive_bigpicture-history.md`)
2. Commit (sichtbar im git-Log)
3. Archiv-Datei löschen (zweiter Commit)

### Code-Abgleich
Systematisch: `compose.yaml`, `Makefile`, `setup-webtrees.sh`, `tests/layer3-integration/`
gegen alle neuen Dokumente prüfen. Bekannte Lücke: OtelSpansModule fehlt in den
Setup-Voraussetzungen für Komponentenintegrationstests.

### Konkrete Durchlauf-Dokumente
`component-integration-coverage_full_analysis.md`, `component-integration-coverage_full_impl_plan.md`,
`testquality_improve_common2.md`, `testquality_improve_A08.md`, `testquality_improve_E01.md`:
Wo inhaltlich verallgemeinerbar: in neutrale Vorlagen überführen. Ansonsten:
1. Einmal committen (sichtbar im git-Log)
2. Löschen (zweiter Commit)

### Workflow-Prompts
`coverage-iteration/` und `testquality_improve_*` werden inhaltlich reorganisiert, konsolidiert
und als Referenz-Anleitungen (`wf_*_guide.md`) brauchbar gemacht — keine bloße Umbenennung.

**Pflicht bei der Reorganisation:** Alle hartcodierten Verweise auf `docs/testing-bigpicture.md`
in den Quelldateien müssen auf die neuen Zieldateien umgeschrieben werden:

| Referenzierter Inhalt | Alter Pfad | Neuer Pfad |
|---|---|---|
| Feature-Matrix-IDs / FM-IDs lesen | `docs/testing-bigpicture.md` | `docs/tds_conditions_ref.md` |
| Abdeckungsmatrix aktualisieren | `docs/testing-bigpicture.md` | `docs/tds_coverage_ref.md` |
| Ratchet-Werte aktualisieren | `docs/testing-bigpicture.md` | `docs/tp_ratchet_spec.md` |
| Abschnittsname "testing-bigpicture.md Diff-Vorschläge" | Kapitelüberschrift | umbenennen in "Dokumentations-Diff-Vorschläge" |

### AP-Dateien (Coverage-Iteration-Artefakte)
`coverage-iteration/ap-*.md` sind dynamisch pro Iteration generierte Arbeitspakete.
Da `coverage-iteration/` aufgelöst wird, landen künftige AP-Dateien in:
```
docs/coverage-runs/<ap-name>.md
```
`docs/coverage-runs/` ist ein dauerhaftes Verzeichnis für iterationsspezifische Artefakte.
`wf_coverage-to-test_guide.md` muss diesen Pfad explizit dokumentieren.

### Glossare
`istqb_glossar_de_DE.md` und `webtrees_glossar_de_DE.md`: Inhalt unverändert (Lizenz ISTQB/CC BY 4.0).
Nur Umbenennen via `git mv`:
- `docs/istqb_glossar_de_DE.md` → `docs/ref_istqb-glossar_ref.md`
- `docs/webtrees_glossar_de_DE.md` → `docs/ref_webtrees-glossar_ref.md`

---

## Eingabe-Dokumente

| Datei | Zeilen | Behandlung |
|---|---|---|
| `docs/testing-bigpicture.md` | ~1834 | aufteilen auf neue Strukturdateien + archivieren |
| `docs/coverage-iteration/entry.md` | ~78 | reorganisieren → `wf_coverage-to-test_guide.md` |
| `docs/coverage-iteration/prep-01-env-coverage.md` | ~49 | reorganisieren → `wf_coverage-to-test_guide.md` |
| `docs/coverage-iteration/prep-02-analysis.md` | ~88 | reorganisieren → `wf_coverage-to-test_guide.md` |
| `docs/coverage-iteration/prep-03-impl-plan.md` | ~163 | reorganisieren → `wf_coverage-to-test_guide.md` |
| `docs/coverage-iteration/post-01-finalize.md` | ~103 | reorganisieren → `wf_coverage-to-test_guide.md` |
| `docs/coverage-iteration/sample-analysis.md` | ~109 | Strukturvorlage einbetten in `wf_coverage-to-test_guide.md` |
| `docs/coverage-iteration/sample-impl-plan.md` | ~109 | Strukturvorlage einbetten in `wf_coverage-to-test_guide.md` |
| `docs/testquality_improve_analyse_prompt.md` | ~274 | reorganisieren → `wf_code-to-test_guide.md` |
| `docs/testquality_improve_common.md` | ~340 | reorganisieren → `wf_code-to-test_guide.md` |
| `docs/testquality_improve_common2.md` | ~169 | Vorlage extrahieren, dann löschen |
| `docs/testquality_improve_plan.md` | ~179 | reorganisieren → `wf_code-to-test_guide.md` |
| `docs/testquality_improve_plan2.md` | ~131 | Vorlage extrahieren, dann löschen |
| `docs/testquality_improve_A08.md` | ~67 | Vorlage extrahieren (Detailkonzept-Schema), dann löschen |
| `docs/testquality_improve_E01.md` | ~58 | Vorlage extrahieren (Detailkonzept-Schema), dann löschen |
| `docs/component-integration-coverage_full_analysis.md` | ~256 | Vorlage extrahieren, dann löschen |
| `docs/component-integration-coverage_full_impl_plan.md` | ~78 | Vorlage extrahieren, dann löschen |
| `docs/istqb_glossar_de_DE.md` | ~4039 | umbenennen (git mv) |
| `docs/webtrees_glossar_de_DE.md` | ~557 | umbenennen (git mv) |

---

## Zielbild: neue Dateistruktur

```
docs/
├── tp_overview_spec.md            Rahmendokument / Navigationseinstieg
├── tp_decisions_spec.md           35 Designentscheidungen, Layer↔ISTQB-Mapping, Architektur-Diagramm
├── tp_infrastructure_spec.md      N1–N7 Infrastruktur-Entscheidungen + Container-Stack-Spezifikation
├── tp_conventions_spec.md         Testkonventionen (AAA, FIRST, Namenskonvention, Data Provider, Verfolgbarkeit)
├── tp_risks_spec.md               Produktrisiken, Projektrisiken, Fehlermanagement, bekannte Fehler
├── tp_ratchet_spec.md             Überdeckungsstrategie, Ratchet, Endekriterien
├── tp_upstream_spec.md            Upstream-Contribution: Stubs, Scope, Redundanz, Status
├── tds_conditions_ref.md          Alle Feature-Matrizen G/S/P/SEC/E/A/K + RE-Methodik + Domänenbeschreibungen
├── tds_coverage_ref.md            Abdeckungsmatrix + Teststatus (164/168 abgedeckt, 4 offen)
├── tds_methodik_spec.md           Testentwurfsverfahren, Testorakel, Testfall-Verteilung, Prioritätsverteilung
├── wf_coverage-to-test_guide.md   Workflow: Coverage-Messung → CRAP-Score → Neue Tests (aus coverage-iteration/)
├── wf_code-to-test_guide.md       Workflow: Code-Analyse → Testkonzept → Implementierung (aus testquality_improve_*)
└── coverage-runs/                 Verzeichnis für AP-Dateien je Coverage-Iteration (dynamisch befüllt)
├── ref_istqb-glossar_ref.md       ISTQB-Glossar DE, 589 Begriffe (CC BY 4.0, Inhalt unverändert)
└── ref_webtrees-glossar_ref.md    Webtrees-Domänenglossar (Inhalt unverändert)
```

**Temporär (archivieren, dann löschen):**
```
docs/archive_bigpicture-history.md   Änderungshistorie + Implementierungsfahrplan (12 Phasen)
```

---

## Kapitelstruktur Big-Picture → Mapping auf Zieldateien

| Big-Picture-Kapitel | Zeilen (ca.) | Zieldatei |
|---|---|---|
| Getroffene Designentscheidungen (35 Stück) | Z.12–Z.52 | `tp_decisions_spec.md` |
| Zuordnung Layer ↔ ISTQB-Teststufe | Z.53–Z.70 | `tp_decisions_spec.md` |
| Mermaid-Architektur-Diagramm | Z.71–Z.183 | `tp_decisions_spec.md` |
| Infrastruktur-Entscheidungen N1–N7 | Z.184–Z.579 | `tp_infrastructure_spec.md` |
| Container-Stack-Spezifikation | Z.580–Z.648 | `tp_infrastructure_spec.md` |
| RE-Methodik (4 Schritte) | Z.657–Z.700 | `tds_conditions_ref.md` |
| Gap-Analyse existierender Tests | Z.701–Z.710 | `tds_conditions_ref.md` |
| Domäne GEDCOM Import/Export | Z.711–Z.740 | `tds_conditions_ref.md` |
| Domäne Suche und Navigation | Z.741–Z.774 | `tds_conditions_ref.md` |
| Feature-Matrix G01–G30 | Z.775–Z.816 | `tds_conditions_ref.md` |
| Feature-Matrix S01–S53 | Z.817–Z.879 | `tds_conditions_ref.md` |
| Feature-Matrix P01–P41 | Z.880–Z.947 | `tds_conditions_ref.md` |
| Feature-Matrix SEC-* | Z.948–Z.988 | `tds_conditions_ref.md` |
| Feature-Matrix E01–E08 | Z.989–Z.1008 | `tds_conditions_ref.md` |
| Feature-Matrix A01–A11 | Z.1009–Z.1030 | `tds_conditions_ref.md` |
| Feature-Matrix K01–K02 | Z.1031–Z.1042 | `tds_conditions_ref.md` |
| Testfall-Verteilung nach Teststufe | Z.1043–Z.1054 | `tds_methodik_spec.md` |
| Prioritätsverteilung | Z.1055–Z.1064 | `tds_methodik_spec.md` |
| Entscheidung: RE-Quellen | Z.1065–Z.1078 | `tds_conditions_ref.md` |
| Endekriterien pro Teststufe | Z.1079–Z.1094 | `tp_ratchet_spec.md` |
| Testorakel — Orakelquellen | Z.1095–Z.1121 | `tds_methodik_spec.md` |
| Testentwurfsverfahren pro Domäne | Z.1122–Z.1173 | `tds_methodik_spec.md` |
| Produktrisiken und Projektrisiken | Z.1174–Z.1212 | `tp_risks_spec.md` |
| Überdeckungsstrategie — Ratchet | Z.1213–Z.1270 | `tp_ratchet_spec.md` |
| Fehlermanagement | Z.1271–Z.1288 | `tp_risks_spec.md` |
| Testkonventionen | Z.1289–Z.1380 | `tp_conventions_spec.md` |
| Verfolgbarkeit | Z.1381–Z.1406 | `tp_conventions_spec.md` |
| Implementierungs-Fahrplan (12 Phasen) | Z.1407–Z.1432 | `archive_bigpicture-history.md` |
| Upstream-Contribution | Z.1433–Z.1510 | `tp_upstream_spec.md` |
| Abdeckungsmatrix (alle Domänen) | Z.1511–Z.1732 | `tds_coverage_ref.md` |
| Bekannte Fehler im Teststack | Z.1733–Z.1783 | `tp_risks_spec.md` |
| Änderungshistorie | Z.1784–Ende | `archive_bigpicture-history.md` |

---

## Anforderungen an die neuen Dokumente

### Rahmendokument (`tp_overview_spec.md`)
- Navigationseinstieg mit Kurzbeschreibung jedes Subdokuments und Direktlinks
- Kein reiner TOC — kurze inhaltliche Einordnung pro Verlinkung
- Muss ohne Laden der Subdokumente orientieren, was wo zu finden ist

### Alle Subdokumente
- Inhaltlich kohärent und in sich abgeschlossen (keine impliziten Abhängigkeiten)
- Querverweise auf andere Subdokumente via relativer Markdown-Links mit Anker
- SPDX-Header erste Zeile: `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->`
- Ausnahme: `ref_istqb-glossar_ref.md` behält bestehenden Header (Lizenz CC BY 4.0)

### ISTQB-Konformität
- Terminologie aus `ref_istqb-glossar_ref.md` verwenden
- Webtrees-spezifische Begriffe in `ref_webtrees-glossar_ref.md` referenzieren
- Kapitelstruktur an ISTQB-Konzepte anlehnen

### Nicht ändern
- Feature-Matrix-IDs (G01–G30 etc.) — verankert in `@see`-Annotationen im Testcode
- Inhalt der Glossare (nur Dateiname ändert sich)
- Grundsätzliche ISTQB-Methodik und Entscheidungen

---

## Code-Abgleich (systematisch)

Folgende Dateien gegen alle neuen Dokumente prüfen:

| Quelle | Prüfinhalt |
|---|---|
| `compose.yaml` | Container-Namen, Ports, Volumes, Netzwerke gegen `tp_infrastructure_spec.md` |
| `Makefile` | Make-Targets, Abhängigkeiten gegen `tp_overview_spec.md` und `wf_*` |
| `setup-webtrees.sh` | Setup-Schritte (inkl. OtelSpansModule) gegen `tp_infrastructure_spec.md` |
| `tests/layer3-integration/` | Testklassen gegen Feature-Matrix-IDs in `tds_conditions_ref.md` |
| `phpunit-integration.xml` | Konfiguration gegen `tp_infrastructure_spec.md` |
| `tests/layer4-e2e/` | E2E-Struktur gegen `tp_decisions_spec.md` (Layer4-Entscheidungen) |

**Bekannte Lücke (zwingend schließen):** OtelSpansModule fehlt in den Setup-Voraussetzungen
für Komponentenintegrationstests (`tp_infrastructure_spec.md`). Derzeit nur inhaltlich in N6b
erwähnt, nicht als Setup-Schritt dokumentiert.

---

## Ausführungsschritte

Jeder Schritt mit `Review durch User` ist ein expliziter Haltepunkt — erst nach Freigabe weitermachen.

### Phase A: Vorbereitung und Archivierung
1. Archiv-Datei erstellen: `docs/archive_bigpicture-history.md`
   (Änderungshistorie + Implementierungsfahrplan aus Big-Picture extrahieren)
2. Commit: Archiv-Datei (GPG-signiert)
3. Konkrete Durchlauf-Dokumente sichten, Vorlagen extrahieren wo sinnvoll
4. Commit: Vorlagenerweiterungen in `wf_coverage-to-test_guide.md` und `wf_code-to-test_guide.md`

### Phase B: Subdokumente erstellen
**Review durch User nach der Dateiliste — erst dann inhaltlich befüllen**

5. `tds_conditions_ref.md` — Feature-Matrizen + RE-Methodik + Domänenbeschreibungen
6. `tds_methodik_spec.md` — Testentwurfsverfahren, Testorakel, Verteilungen
7. `tds_coverage_ref.md` — Abdeckungsmatrix + Teststatus
8. `tp_decisions_spec.md` — Designentscheidungen, Layer-Mapping, Diagramm
9. `tp_infrastructure_spec.md` — N1–N7, Container-Stack
10. `tp_conventions_spec.md` — Testkonventionen, Verfolgbarkeit
11. `tp_risks_spec.md` — Risiken, Fehlermanagement, bekannte Fehler
12. `tp_ratchet_spec.md` — Überdeckungsstrategie, Endekriterien
13. `tp_upstream_spec.md` — Upstream-Contribution
14. `wf_coverage-to-test_guide.md` — reorganisiert aus `coverage-iteration/`;
    - `sample-analysis.md` und `sample-impl-plan.md` als eingebettete Strukturvorlagen
    - alle `testing-bigpicture.md`-Verweise auf neue Zieldateien umschreiben (s. Tabelle oben)
    - Pfad `docs/coverage-runs/` für künftige AP-Dateien definieren
15. `wf_code-to-test_guide.md` — reorganisiert aus `testquality_improve_*`;
    - alle `testing-bigpicture.md`-Verweise auf neue Zieldateien umschreiben (s. Tabelle oben)

### Phase C: Code-Abgleich
16. Systematischen Code-Abgleich durchführen (s. Tabelle oben)
17. Lücken in den Subdokumenten schließen

### Phase D: Rahmendokument + Glossare
18. `tp_overview_spec.md` — Rahmendokument mit allen Links erstellen
19. Glossare umbenennen: `git mv istqb_glossar_de_DE.md ref_istqb-glossar_ref.md`
20. Glossare umbenennen: `git mv webtrees_glossar_de_DE.md ref_webtrees-glossar_ref.md`

### Phase E: Aufräumen
21. Alte Dateien löschen (git rm):
    - `docs/testing-bigpicture.md`
    - `docs/testquality_improve_*.md` (alle)
    - `docs/coverage-iteration/` (ganzes Verzeichnis — inkl. `sample-analysis.md`, `sample-impl-plan.md`)
    - `docs/component-integration-coverage_full_*.md`
    - `docs/archive_bigpicture-history.md`
    - `docs/coverage-runs/` bleibt bestehen (Zielverzeichnis für künftige AP-Dateien)
22. `CLAUDE.md` aktualisieren:
    - Einstiegspunkt `coverage-iteration/entry.md` → `wf_coverage-to-test_guide.md`
    - `docs/testing-bigpicture.md`-Referenz aktualisieren

### Phase F: Abschluss
23. `docs/wf_doku-refactoring_guide.md` (diese Datei) löschen — Aufgabe abgeschlossen
24. Finaler Commit (GPG-signiert): gesamte neue Dokumentationsstruktur

---

## Invarianten (niemals ändern)

- Feature-Matrix-IDs G01–G30, S01–S53, P01–P41, SEC-*, E01–E08, A01–A11, K01–K02
- Lizenzangabe im ISTQB-Glossar (CC BY 4.0)
- SPDX-Header-Pflicht für alle neuen Dateien (außer Glossar-Umbenennung)
- GPG-Signierpflicht für alle Commits
