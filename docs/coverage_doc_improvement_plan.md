<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Umsetzungsplan: Verbesserung der Feature- und Coverage-Dokumentation

> **Scope:** Überführung der Dokumente [`tds_conditions_ref.md`](tds_conditions_ref.md) und
> [`tds_coverage_ref.md`](tds_coverage_ref.md) in die Zielstruktur aus
> [`coverage_doc_improvement_analysis.md`](coverage_doc_improvement_analysis.md).
>
> **Leitprinzipien**
> - Feature-IDs (G01, S05, P12, SEC-H01 …) bleiben unverändert — `@see`-Anker in Testklassen schützen (Analyse §3.2, E4).
> - **Arbeits- und Review-Granularität sind getrennt:** Gearbeitet wird sub-phasen-fein (kleinteilig, keine Big-Bang-Migration). Das **Review-Modell** wird pro Phase gewählt und ist im jeweiligen Phasen-Header unter `Review-Modell:` notiert — siehe Abschnitt [*Review-Modelle*](#review-modelle) unten.
> - Commits werden manuell erstellt. Der Plan listet **keine** Commit-Punkte.
> - Statustracking wird **sofort nach Erledigung** einer Checkbox aktualisiert, nicht erst am Ende der Phase oder der Session. So bleibt der Plan nach Kontext-Compacts oder Systemabstürzen wiederaufsetzbar.

**Datum Planerstellung:** 2026-04-11
**Basis-Analyse:** `docs/coverage_doc_improvement_analysis.md`
**Aktiver Branch (Testing-Platform):** `main`
**Aktiver Branch (Upstream-Fork, L2-Quelle, read-only):** `port-layer2-test-doubles`

---

## 0 Getroffene Entscheidungen (aus Planungs-Interview 2026-04-11)

Die folgenden Entscheidungen ergänzen die Vorfixierungen der Analyse (E1–E4) und beantworten
die dort aufgeworfenen offenen Fragen (O1–O8 aus §7 der Analyse).

| #   | Thema                                                                                  | Entscheidung                                                                      | Bezug             |
|-----|----------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------|-------------------|
| D1  | Reihenfolge der Gap-Analyse-Neuerhebung                                                | **Erste Phase** des Plans (methodisch sauber, nicht die pragmatische Variante)    | O2, §4.3, R5      |
| D2  | Qualitätssiegel in Coverage-Matrix                                                     | **Pflichtfeld** für jede abgedeckte Zelle                                         | O3, §5.2, R3      |
| D3  | Neue Feature-IDs für Middleware / fehlende CLI-Commands                                | **Im Rahmen dieses Plans** vergeben (Middleware + CLI-Erweiterungen)              | O5, §4.1, R9      |
| D4  | Historisches Material (2026-03-26-Gap-Analyse, Zusammenfassungs-Zahlen 165/5/170)      | **Beides auslagern** nach `docs/coverage-runs/historical/` bzw. `docs/coverage-runs/` | O1 + O6, §5.4, R7 |
| D5  | Pfad-Darstellung zu Testklassen in Abdeckungsmatrix                                    | **Legende + Dateiname** (Präfixe `L2:`/`L3:`/`L4:` am Dokumentanfang)              | O4, §5.6, R8      |
| D6  | Präfix-Schema für neue IDs                                                             | **Neue Domäne `M` für Middleware, A-Reihe erweitern für CLI-Commands**            | Interview-Ergänzung |
| D7  | `@see`-Pfad-Update in Testklassen (`testing-bigpicture.md` → `tds_conditions_ref.md`) | **Letzte Arbeitsphase** vor der Schluss-Verifikation                              | R6, §5.7          |
| D8  | Migration der beiden Zieldokumente                                                     | **Parallel in kleinen Sub-Phasen** (strukturelle Konsistenz gewahrt)              | Interview-Ergänzung |

### Zusätzliche Defaults (ohne Interview pragmatisch gesetzt — Korrekturen per Plan-Update jederzeit möglich)

| #   | Thema                                                              | Entscheidung                                                                                    | Begründung                                                       |
|-----|--------------------------------------------------------------------|--------------------------------------------------------------------------------------------------|------------------------------------------------------------------|
| D9  | Domänen-Navigation (Anker-Links) in Coverage-Matrix                 | **Ja**, am Dokumentanfang                                                                        | Niedriger Aufwand, hoher Lesegewinn (O8)                         |
| D10 | Aufspaltung von `tds_conditions_ref.md` pro Domäne                  | **Nein**                                                                                          | Würde `@see`-Anker brechen; widerspricht E4 (O7)                  |
| D11 | Statustracking-Format                                              | Checkbox-Marker `[ ]` / `[~]` / `[x]` / `[-]` plus `Letzte Änderung`-Zeile bei Statuswechseln     | Minimalistisch, Compact-robust, manuell wartbar                   |
| D12 | Phase 7 als separater Schritt vor Schlussverifikation              | **Ja**                                                                                            | Risikoarm, aber außerhalb der Doku-Substanz (§5.7)                |

---

## Status-Legende

| Marker | Bedeutung |
|--------|-----------|
| `[ ]`  | Offen — noch nicht begonnen |
| `[~]`  | In Arbeit — aktuell bearbeitet (Wiederaufsetz-Stand siehe `Letzte Änderung`) |
| `[x]`  | Erledigt — Ergebnis im Repo verfügbar |
| `[-]`  | Übersprungen / nicht relevant (mit Begründung in der Zeile darunter) |

**Pflege-Regel:** Jeder Sub-Punkt wird beim Statuswechsel **sofort** editiert — nicht erst am Ende der Phase, nicht erst am Ende der Session. Beim Wechsel auf `[~]` oder `[x]` wird zusätzlich eine Zeile `> Letzte Änderung: YYYY-MM-DD — <kurze Notiz>` direkt unter dem Sub-Punkt ergänzt. Bei `[x]` kann die Notiz auf ein oder zwei Wörter reduziert werden.

---

## Review-Modelle

Jede Phase verwendet eines der folgenden Review-Modelle (oder eine Kombination). Der Arbeitsfluss bleibt immer sub-phasen-granular; das Review-Modell bestimmt nur, **wann** und **wie breit** geprüft wird.

| Code | Name | Wie wird geprüft | Gut geeignet für |
|---|---|---|---|
| **A** | Pro Sub-Phase | Jede einzelne Sub-Checkbox wird sofort nach Erledigung geprüft. | Eigenständige Verifikations-Schritte, unabhängige Einzelchecks. |
| **B** | Sub-Phasen-Gruppe | Eine logisch zusammengehörige Gruppe (z. B. `3.1.1`–`3.1.3`) wird gemeinsam reviewt. Gruppen-Grenze ist hart. | Strukturelle Änderungen, die als geschlossene Einheit wirken. |
| **C** | Top-Down-Konsistenzcheck | Nach einer Sub-Phasen-Gruppe wird vom Dokumentanfang bis zum aktuellen Bearbeitungspunkt geprüft. Noch unveränderte Sektionen dahinter werden toleriert. | In Kombination mit **B**, wenn Konsistenz über mehrere Gruppen hinweg wachsen muss. |
| **D** | Pilot-Domäne + Batch-Nachziehen | Eine Domäne (typisch **G**) wird komplett durchmigriert und reviewt. Nach Abnahme werden die übrigen Domänen in Stapeln von 2–3 mechanisch nachgezogen. | Phasen mit wiederholter Operation pro Domäne (Qualitätssiegel, Zellen-Schema). |
| **E** | Meilenstein-Review | Innerhalb der Phase wird frei gearbeitet; ein konsolidiertes Review erfolgt am Phasen-Ende über das Gesamtergebnis. | Kurze, klar umrissene Phasen mit einem einzigen Zielartefakt oder rein mechanischen Bulk-Operationen. |

**Pflege:** Das Review-Modell einer Phase wird während des Arbeitsflusses **nicht gewechselt**. Wenn sich herausstellt, dass ein anderes Modell passender ist, wird die Phase bewusst zurückgesetzt und mit dem neuen Modell neu begonnen — ein Mid-Phase-Wechsel ist ausdrücklich nicht vorgesehen, weil er den Review-Status ambig macht.

**Kombinationen:** Einzige erlaubte Kombination ist `B + C` (Sub-Phasen-Gruppe mit Top-Down-Konsistenzcheck am Gruppen-Ende). Alle anderen Modelle werden einzeln verwendet.

---

## Randbedingungen

Aus der Analyse §6 übernommen, mit Plan-spezifischen Ergänzungen:

- **Feature-IDs unverändert** — bestehende IDs werden nicht umbenannt, gespalten oder zusammengefasst. Neue IDs sind fortlaufend (z. B. M01–M<N>, A15+).
- **Feature-Zeile atomar** — eine Feature-ID = eine Matrix-Zeile. Keine EP-Zeilen, keine Testmethoden-Zeilen.
- **Fork-Repo `webtrees-upstream/webtrees` bleibt read-only** — die Neuerhebung ist ein reiner Lesevorgang gegen `port-layer2-test-doubles`.
- **Keine Commits geplant** — der Plan listet Arbeitspakete, nicht Commits. Der User commitet manuell nach Bedarf, typischerweise nach abgeschlossener Sub-Phase.
- **Sprache de\_DE** — alle Dokumentations-Änderungen auf Deutsch.
- **SPDX-Header** — jede neu erstellte `.md`-Datei erhält `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->` als erste Zeile.
- **Kein Perl** — Text-Transformationen mit Bash-Nativem oder `sed` (CLAUDE.md).
- **Keine Testläufe durch den Plan** — der Plan ist reine Dokumentations- und Metadaten-Arbeit. Ausnahme: optionaler `php -l`-Syntax-Check in Phase 7.

---

## Phasen-Übersicht

| Phase | Titel                                                         | Ziel                                                                                                    | Abhängigkeit                                   | Gesamtstatus |
|-------|---------------------------------------------------------------|---------------------------------------------------------------------------------------------------------|------------------------------------------------|--------------|
| 0     | Vorbereitung                                                  | Plan-Dokument + Verzeichnis-Struktur anlegen                                                            | —                                              | `[ ]`        |
| 1     | Gap-Analyse-Neuerhebung                                       | Fork-Stand als Snapshot unter `docs/coverage-runs/` festhalten                                          | Phase 0                                        | `[ ]`        |
| 2     | Historisches Material auslagern                               | 2026-03-26-Block + Zusammenfassungs-Zahlen archivieren                                                  | Phase 0                                        | `[ ]`        |
| 3     | Struktur- und Nomenklatur-Migration                           | Mapping-Tabelle, Spaltenüberschriften, Status-Split, Legende, Navigation (parallel in beiden Dokumenten) | Phase 2                                        | `[ ]`        |
| 4     | Qualitätssiegel (Pflichtfeld) einführen                        | Jede abgedeckte Zelle mit eindeutigem Siegel versehen (inhaltliche Einstufung)                          | Phase 3                                        | `[ ]`        |
| 5     | Neue Feature-IDs vergeben                                     | M01…M<N> für Middleware, A15+ für fehlende CLI-Commands                                                  | Phase 3                                        | `[ ]`        |
| 6     | Inhalts-Migration der Zellen                                  | Zellen-Schema `<Klasse> [<Siegel>] (<N> Tests) → <Detail>` syntaktisch durchgehend                      | Phase 4, Phase 5, Phase 1 (Testmethoden-Zahlen) | `[ ]`        |
| 7     | `@see`-Pfad-Update in Testklassen                             | `testing-bigpicture.md` → `tds_conditions_ref.md` (risikoarmer Search/Replace)                          | — (unabhängig, bewusst spät)                    | `[ ]`        |
| 8     | Verifikation und Abschluss                                    | Quer-Checks, Lesbarkeits-Check, Rendering-Probe                                                         | alle vorigen                                   | `[ ]`        |

**Pflege:** Der Gesamtstatus einer Phase wird bei Abschluss aller Sub-Schritte auf `[x]` gesetzt. Bei Beginn der ersten Sub-Phase wechselt der Gesamtstatus auf `[~]`.

---

## Phase 0 — Vorbereitung

**Ziel:** Plan-Infrastruktur im Repo verankern, Verzeichnis-Struktur für Snapshots vorbereiten.
**Review-Modell:** **B** — Sub-Phasen-Gruppe. Drei Schritte als ein Block, gemeinsames Review am Gruppen-Ende.

- [ ] **0.1** — Plan-Dokument `docs/coverage_doc_improvement_plan.md` anlegen (dieses Dokument selbst).
- [ ] **0.2** — Verzeichnis `docs/coverage-runs/historical/` anlegen (für D4-Auslagerung).
- [ ] **0.3** — Rückverweis auf den Plan in `docs/coverage_doc_improvement_analysis.md` setzen (eine Zeile am Ende der Kopfzone: *„Umsetzungsplan: `coverage_doc_improvement_plan.md`"*).

---

## Phase 1 — Gap-Analyse-Neuerhebung

**Ziel:** Die in Analyse §4.3 beschriebene Neuerhebung gegen den Fork-Stand durchführen. Ergebnis = **eigener Snapshot** unter `docs/coverage-runs/2026-04-11_gap-analyse-fork.md`, **kein** Inline-Update von `tds_conditions_ref.md`.
**Review-Modell:** **E** — Meilenstein-Review. Die Neuerhebung ist ein reiner Lese-/Inventur-Prozess; das konsolidierte Review erfolgt am fertigen Snapshot-Dokument (nach Phase 1.7). Zwischenstände der Metriken-Erhebung (1.2–1.6) sind nicht review-relevant, solange Phase 1.7 den Snapshot als geschlossenes Artefakt vorlegt.

**Eingangsdaten**
- L2: `webtrees-upstream/webtrees` @ `port-layer2-test-doubles` (read-only, gesamthaft, nicht Delta)
- L3: `layer3-integration/tests/` @ Testing-Platform `main`
- L4: `layer4-e2e/tests/` @ Testing-Platform `main`
- Anwendungscode: `upstream/webtrees/` @ `security-audit-consolidated`

### 1.1 — Basis fixieren

- [ ] **1.1.1** — Aktuelle Commit-SHAs aller vier Quellen dokumentieren (je ein `git rev-parse HEAD` pro Repo/Branch).
- [ ] **1.1.2** — Diese SHAs im Kopf des späteren Snapshot-Dokuments verankern.

### 1.2 — L2-Inventarisierung (Fork)

- [ ] **1.2.1** — Liste aller Testdateien unter `tests/app/` + `tests/feature/` erzeugen (erwartet: ~1237).
- [ ] **1.2.2** — Pro Testdatei: Zeilenzahl, Testmethoden-Zahl, Assertions-Gesamtzahl (grep-basiert, Kommandos dokumentieren).
- [ ] **1.2.3** — Assertionsdichte pro Testdatei berechnen (Assertions / Testmethoden). Durchschnitt, Median, Min/Max.
- [ ] **1.2.4** — Qualitäts-Klassifikation pro Testdatei nach Analyse §4.3 Schritt 2: `Stub` / `Smoke` / `Substantial` / `EP-complete`.

### 1.3 — L3-Inventarisierung (Testing-Platform)

- [ ] **1.3.1** — Liste aller `*Test.php` unter `layer3-integration/tests/` (exkl. `MysqlTestCase.php`, `bootstrap.php`). Erwartet: ~84.
- [ ] **1.3.2** — Gleiche Metriken wie Phase 1.2.2–1.2.4.

### 1.4 — L4-Inventarisierung (Testing-Platform)

- [ ] **1.4.1** — Liste aller `*.spec.ts` unter `layer4-e2e/tests/` (inkl. `tests/security/`). Erwartet: ~26.
- [ ] **1.4.2** — Pro Spec: Zeilenzahl, Anzahl `test(…)`-Aufrufe, Assertions-Gesamtzahl.
- [ ] **1.4.3** — Qualitäts-Klassifikation analog L2/L3 (angepasste Kriterien für Playwright: DOM-Assertion vs. Smoke).

### 1.5 — Domänen-Zuordnung

- [ ] **1.5.1** — Pro L2-Testdatei SUT-Klasse ermitteln (aus `namespace` + `class`). Ableitung zu Feature-ID anhand von `@see`-Annotationen oder manueller Zuordnung.
- [ ] **1.5.2** — Pro L3-Testdatei gleiches Vorgehen, unter Beachtung vorhandener `@see`-Annotationen.
- [ ] **1.5.3** — Pro L4-Spec Zuordnung zur Feature-ID (i. d. R. über Kommentar oder manuell).
- [ ] **1.5.4** — Mehrfachzuordnung zulässig (eine Testdatei kann mehrere Features abdecken).

### 1.6 — Auswertung

- [ ] **1.6.1** — Pro Domäne (G/S/P/SEC/E/A/K/U): Gesamt-Testdateien, davon substanziell, davon EP-complete, aggregierte Assertionsdichte.
- [ ] **1.6.2** — Pro Feature-ID: Anzahl verknüpfter Testklassen pro Layer (L2/L3/L4), aggregierte Qualitätseinstufung.
- [ ] **1.6.3** — Lücken-Liste: Welche Feature-IDs aus `tds_conditions_ref.md` haben im Neu-Stand **keine** Testabdeckung in irgendeinem Layer?

### 1.7 — Snapshot-Dokument schreiben

- [ ] **1.7.1** — `docs/coverage-runs/2026-04-11_gap-analyse-fork.md` anlegen (SPDX-Header, Stand-Datum, Commit-SHAs, Methode-Beschreibung als kompakter Verweis auf Analyse §4.3, Ergebnis-Tabellen).
- [ ] **1.7.2** — Snapshot in der Navigation von `docs/coverage-runs/` (Index-Datei bzw. `MEMORY.md`-Hinweis falls vorhanden) verlinken.

---

## Phase 2 — Historisches Material auslagern

**Ziel:** Veraltete Inhalte aus den Hauptdokumenten in Archiv-Snapshots verschieben, damit die Hauptdokumente nur noch den aktiven Stand tragen.
**Review-Modell:** **B** — Sub-Phasen-Gruppe. Drei Gruppen (2.1 Gap-Analyse, 2.2 Abdeckungs-Zahlen, 2.3 E2E-Gap), jede ist ein „Inhalt umziehen + Verweis einfügen"-Paket und wird als geschlossene Einheit reviewt.

### 2.1 — Gap-Analyse-Block (2026-03-26) archivieren

- [ ] **2.1.1** — `docs/coverage-runs/historical/2026-03-26_gap-analyse.md` anlegen mit SPDX-Header und kurzem Archiv-Lead-In: *„Dieser Befund ist mit Stand 2026-03-26 gegen Upstream-main erhoben. Er ist durch den Fork-Branch `port-layer2-test-doubles` in Teilen überholt. Aktueller Stand: `docs/coverage-runs/2026-04-11_gap-analyse-fork.md`."*
- [ ] **2.1.2** — Inhalt des Abschnitts *„Befund: Gap-Analyse der existierenden webtrees-Tests"* aus `tds_conditions_ref.md` (Zeilen ≈ 58–130) ohne inhaltliche Änderung in die neue Datei kopieren.
- [ ] **2.1.3** — In `tds_conditions_ref.md` den Gap-Analyse-Block durch einen kompakten Verweis (3–5 Zeilen) ersetzen, der auf das Archiv und den neuen Snapshot zeigt.

### 2.2 — Abdeckungs-Zusammenfassungs-Zahlen archivieren

- [ ] **2.2.1** — `docs/coverage-runs/2026-04-11_abdeckung-snapshot.md` anlegen mit SPDX-Header.
- [ ] **2.2.2** — Header-Zeile „165 abgedeckt / 5 nicht abgedeckt / 170 Features gesamt" und die Abdeckungs-Zusammenfassungs-Tabelle aus `tds_coverage_ref.md` (Zeile ≈ 233) in den Snapshot übernehmen.
- [ ] **2.2.3** — In `tds_coverage_ref.md` die Zusammenfassungs-Zahlen durch einen Verweis auf `docs/coverage-runs/` ersetzen (jüngster Snapshot zuerst).

### 2.3 — E2E-Gap-Analyse (2026-03-27) archivieren

- [ ] **2.3.1** — `docs/coverage-runs/historical/2026-03-27_e2e-gap.md` anlegen mit SPDX-Header und Archiv-Lead-In.
- [ ] **2.3.2** — Abschnitt *„E2E-Gap-Analyse (2026-03-27)"* aus `tds_coverage_ref.md` in die neue Datei kopieren.
- [ ] **2.3.3** — Abschnitt in `tds_coverage_ref.md` durch einen Verweis auf das Archiv ersetzen.

---

## Phase 3 — Struktur- und Nomenklatur-Migration (parallel, Sub-Phasen)

**Ziel:** Beide Zieldokumente auf die neue Zielstruktur bringen. Jede Sub-Phase wird in **beiden Dokumenten gleichzeitig** durchgeführt (D8), damit die Spiegelung zwischen Feature-Matrix und Coverage-Matrix erhalten bleibt.
**Review-Modell:** **B + C** — Sub-Phasen-Gruppe mit Top-Down-Konsistenzcheck am Gruppen-Ende. Nach jeder Sub-Phasen-Gruppe (3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7) wird in beiden Dateien vom Dokumentanfang bis zum aktuellen Bearbeitungspunkt ein Konsistenzblick geworfen; noch unveränderte Matrizen unterhalb des Punkts werden ausdrücklich toleriert, weil sie spätere Sub-Phasen nachziehen.

### 3.1 — Mapping-Tabelle Teststufen ↔ Layer (§5.3)

- [ ] **3.1.1** — Mapping-Tabelle am Anfang von `tds_conditions_ref.md` einfügen (Analyse §5.3, wörtlich übernehmen).
- [ ] **3.1.2** — Identische Mapping-Tabelle am Anfang von `tds_coverage_ref.md` einfügen.
- [ ] **3.1.3** — Verifikation: beide Tabellen sind byte-gleich (`diff <(sed -n …) <(sed -n …)` oder vergleichbar).

### 3.2 — Spaltenüberschriften der Abdeckungsmatrix (§5.1)

- [ ] **3.2.1** — In `tds_coverage_ref.md` alle Matrix-Header von `Upstream (SQLite) | Eigene Infra (MySQL) | Eigene Infra (Playwright)` auf `L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright)` migrieren.
- [ ] **3.2.2** — Fußnote unter der ersten Matrix einfügen: *„L2-Spalte zeigt Stand Branch `port-layer2-test-doubles` im Upstream-Fork, im Upstream-main noch nicht akzeptiert."*
- [ ] **3.2.3** — In `tds_conditions_ref.md` die ISTQB-Teststufen-Spalte beibehalten und in der Header-Beschreibung auf die Mapping-Tabelle (Phase 3.1) verweisen.

### 3.3 — Pfad-Legende (§5.6, D5)

- [ ] **3.3.1** — Legende am Dokumentanfang von `tds_coverage_ref.md` einfügen:
    - `L2:` → `webtrees-upstream/webtrees/tests/` (Branch `port-layer2-test-doubles`)
    - `L3:` → `layer3-integration/tests/`
    - `L4:` → `layer4-e2e/tests/`
- [ ] **3.3.2** — Konvention für Zellen dokumentieren: Dateinamen ohne Pfad, aber mit L-Präfix (z. B. `L3: GedcomImportTest.php`).

### 3.4 — Status-Spalten-Trennung Abdeckung ↔ Befund (§5.5)

- [ ] **3.4.1** — In jeder Matrix von `tds_coverage_ref.md` die bisherige `Status`-Spalte in zwei Spalten `Abdeckung` und `Befund` aufteilen.
- [ ] **3.4.2** — Wertebereich `Abdeckung` fixieren: `OK` / `Teil` / `—` / `SKIP`.
- [ ] **3.4.3** — Wertebereich `Befund` fixieren: `—` / `Upstream-Bug` / `Deployment-Empfehlung` / `Deprecated`.
- [ ] **3.4.4** — Bestehende kombinierte Einträge wie `**Abgedeckt** (mit Upstream-Bug)` in `OK | Upstream-Bug` auflösen (über alle Matrizen).

### 3.5 — Einheitsstruktur für SEC-Submatrix (§5.1 SEC-Sonderfall)

- [ ] **3.5.1** — SEC-Submatrix in `tds_coverage_ref.md` auf die Einheitsstruktur umstellen (`L2 | L3 | L4 | Abdeckung | Befund`).
- [ ] **3.5.2** — Shell-Assertions (`security-filesystem-checks.sh`) als Klammer-Kommentar in der L4-Zelle dokumentieren, nicht als eigene Spalte.

### 3.6 — Upstream-Spalte für P/E/A/K/U ergänzen (§3.1 historische Lücke)

- [ ] **3.6.1** — In `tds_coverage_ref.md` die Spalte `L2 — Komponententest (Upstream-Fork)` bei P/E/A/K/U-Matrizen ergänzen (auch wenn zunächst leer).
- [ ] **3.6.2** — Zellen auf Basis der Phase-1-Neuerhebung befüllen (`—` bei keiner L2-Abdeckung, sonst Testklassen-Name).

### 3.7 — Domänen-Navigation / Anker-Links (§O8, D9)

- [ ] **3.7.1** — In `tds_coverage_ref.md` am Dokumentanfang eine kompakte Navigation einfügen: `[G](#g) · [S](#s) · [P](#p) · [SEC](#sec) · [E](#e) · [A](#a) · [K](#k) · [U](#u) · [M](#m)` (M erst in Phase 5 aktiv, Platzhalter jetzt).
- [ ] **3.7.2** — Gleiche Navigation am Dokumentanfang von `tds_conditions_ref.md`.
- [ ] **3.7.3** — Pro Domänen-Header Anker setzen (Heading-IDs prüfen, ggf. explizite HTML-Anker).

---

## Phase 4 — Qualitätssiegel als Pflichtfeld einführen

**Ziel:** Jede **abgedeckte** Zelle in `tds_coverage_ref.md` bekommt genau ein Qualitätssiegel. Leere Zellen (nicht abgedeckt) erhalten **kein** Siegel. Diese Phase ist **inhaltliche Einstufung** — die syntaktische Zellen-Umformatierung erfolgt erst in Phase 6.
**Review-Modell:** **D** — Pilot-Domäne + Batch-Nachziehen. Domäne **G** (Phase 4.2) wird als Referenz vollständig eingestuft und reviewt. Erst nach Abnahme des Pilots werden die Domänen S/P/SEC/E/A/K/U in Stapeln von 2–3 Domänen pro Review-Runde nachgezogen. Falls der Siegel-Katalog am Pilot revidiert werden muss, wird der Pilot nachgezogen, **bevor** die restlichen Domänen starten.

**Siegel-Katalog**

| Siegel      | Bedeutung                              | Kriterium                                                                   |
|-------------|----------------------------------------|-----------------------------------------------------------------------------|
| `[EP]`      | EP-complete                            | DataProvider mit ≥3 Partitionen oder explizite EP-Markierung in der Klasse |
| `[Spec-B]`  | Spezifikationsbasiert, strikt          | Testmethoden 1:1 einer externen Spezifikation folgend (GEDCOM, RFC, W3C)    |
| `[Spec-C]`  | Spezifikationsbasiert, pragmatisch     | Fachliche Assertions, aber ohne strikte Spec-Ableitung                      |
| `[Smoke]`   | Smoke                                  | 3–5 Assertions, kein fachlicher Pfad                                        |
| `[CRAP]`    | Strukturbasiert                        | Aus CRAP-Report abgeleitete Tests (CRAP > 100 oder 0 %-Branch)              |

- [ ] **4.1** — Siegel-Katalog am Dokumentanfang von `tds_coverage_ref.md` als Tabelle einpflegen.
- [ ] **4.2** — Domäne **G** (GEDCOM) — jede abgedeckte Zelle einstufen.
- [ ] **4.3** — Domäne **S** (Sichten) — einstufen.
- [ ] **4.4** — Domäne **P** (Privacy) — einstufen.
- [ ] **4.5** — Domäne **SEC** (Security) — einstufen.
- [ ] **4.6** — Domäne **E** (Editing) — einstufen.
- [ ] **4.7** — Domäne **A** (Administration) — einstufen.
- [ ] **4.8** — Domäne **K** (Kommunikation) — einstufen.
- [ ] **4.9** — Domäne **U** (User-Management) — einstufen.
- [ ] **4.10** — Verifikations-Grep: jede nicht-leere Zelle enthält genau ein Siegel-Kürzel aus der Liste; kein Siegel außerhalb des Katalogs.

---

## Phase 5 — Neue Feature-IDs vergeben

**Ziel:** Heute nicht erfasste Bereiche als neue Feature-IDs einführen, gemäß D3 und D6.
**Review-Modell:** **B** — Sub-Phasen-Gruppe. 5.1 (Middleware-Domäne M) und 5.2 (CLI-Erweiterungen) sind zwei klar getrennte hochinhaltliche Gruppen und werden je als geschlossenes Paket reviewt. Inventarisierung, ID-Vergabe, Matrix-Einträge und Siegel-Setzung gehören zum jeweiligen Gruppen-Review.

### 5.1 — Neue Domäne `M` (Middleware)

- [ ] **5.1.1** — Inventarisierung: Alle Middlewares unter `upstream/webtrees/app/Http/Middleware/` auflisten (Dateiname, Klassenname, Konstruktor-Abhängigkeiten, kurze Funktionsbeschreibung).
- [ ] **5.1.2** — Fachliche Clusterung: Welche Middlewares gehören funktional zusammen (Auth, Bad-Bot-Blocking, CSP, Rate-Limiting, Request-Logging, Maintenance-Mode, Session, Exception-Handler, …)?
- [ ] **5.1.3** — IDs `M01…M<N>` vergeben (fortlaufend, pro Middleware bzw. pro logischer Cluster-Einheit). Präfix `M` neu, noch nirgends belegt.
- [ ] **5.1.4** — In `tds_conditions_ref.md` neue Domänen-Sektion `M — Middleware` einfügen (Feature-Matrix mit Beschreibung pro ID).
- [ ] **5.1.5** — In `tds_coverage_ref.md` neue Domänen-Sektion `M — Middleware` einfügen (Coverage-Matrix, Zellen nach Phase-1-Metriken befüllt).
- [ ] **5.1.6** — Navigation aus Phase 3.7 um `M` erweitern (Platzhalter-Link aktivieren).
- [ ] **5.1.7** — Qualitätssiegel (Phase 4) für die Domäne `M` setzen.

### 5.2 — CLI-Erweiterungen in A-Reihe (und ggf. anderen Reihen)

**Heute erfasst:** G25 (GedcomLoad), G26 (TreeExport), P35 (UserEdit), P36 (CliSettings).

**Nicht erfasst (aus Analyse §1.2):** `CompilePoFiles`, `ConfigIni`, `SiteOffline`, `SiteOnline`, `TreeList`, `UserList`, `TreeImport`, `SiteSetting`, `TreeSetting`, `UserSetting`, `UserTreeSetting`.

- [ ] **5.2.1** — Inventarisierung aller CLI-Commands unter `upstream/webtrees/app/Cli/Commands/` mit Beschreibung (Funktion, benötigte Rechte, Risiko).
- [ ] **5.2.2** — Fachliche Zuordnung pro Command zu Domäne:
    - `SiteOffline`, `SiteOnline`, `ConfigIni`, `CompilePoFiles`, `SiteSetting`, `TreeSetting`, `TreeList` → **A** (Administration)
    - `UserList`, `UserSetting`, `UserTreeSetting` → **P** (Privacy/User)
    - `TreeImport` → **G** (GEDCOM, analog G25)
- [ ] **5.2.3** — Neue IDs in der A-Reihe vergeben (A15, A16, …) für die Admin-Commands.
- [ ] **5.2.4** — Neue IDs in P und G vergeben für die übrigen Commands (fortlaufend ans Ende der jeweiligen Reihe).
- [ ] **5.2.5** — Neu-Einträge in `tds_conditions_ref.md` unter den jeweiligen Domänen einfügen.
- [ ] **5.2.6** — Neu-Einträge in `tds_coverage_ref.md` unter den jeweiligen Domänen einfügen (Zellen nach Phase-1-Metriken befüllt).
- [ ] **5.2.7** — Qualitätssiegel für die neuen CLI-IDs setzen (Phase 4 nachziehen).

---

## Phase 6 — Inhalts-Migration der Abdeckungszellen

**Ziel:** Alle Zellen in `tds_coverage_ref.md` syntaktisch auf das einheitliche Schema aus Analyse §5.2 bringen:

```
<TestklassenName> [<QualitätsSiegel>] (<AnzahlTestmethoden> Tests) → <DetailkonzeptLink>
```

**Abgrenzung zu Phase 4:** Phase 4 hat die **Einstufung** der Siegel geleistet (inhaltliche Arbeit). Phase 6 wendet das **vollständige Zellen-Schema** an (mechanische Umformatierung mit Testmethoden-Zahlen und Detailkonzept-Links).

**Review-Modell:** **D** — Pilot-Domäne + Batch-Nachziehen. Domäne **G** (Phase 6.2.1) wird als Referenz vollständig migriert und reviewt; danach werden S/P/SEC/E/A/K/U/M in Stapeln von 2–3 Domänen mechanisch nachgezogen. Die Metriken-Erhebung 6.1 ist der Zellen-Migration vorgelagert und wird mit dem Pilot-Review mit abgenommen (d. h. die automatischen Zählungen werden am G-Pilot validiert, bevor sie auf die übrigen Domänen angewendet werden).

### 6.1 — Automatische Metriken-Erhebung

- [ ] **6.1.1** — Testmethoden-Zählung für alle L2-Testdateien ermitteln (pro Datei: `grep -c 'function test_'` oder PHPUnit XML-Listing). Kommando im Snapshot-Dokument aus Phase 1 dokumentiert.
- [ ] **6.1.2** — Testmethoden-Zählung für alle L3-Testdateien.
- [ ] **6.1.3** — Testmethoden-Zählung für alle L4-Specs (Playwright `test(`-Aufrufe).
- [ ] **6.1.4** — Detailkonzept-Verzeichnis: Welche `docs/testquality_improve_*.md`, `docs/port-implementation/*.md` etc. existieren pro Feature-ID? Mapping als Tabelle.

### 6.2 — Zellen-Migration pro Domäne

- [ ] **6.2.1** — Domäne **G** — Schema durchgängig anwenden.
- [ ] **6.2.2** — Domäne **S** — Schema anwenden.
- [ ] **6.2.3** — Domäne **P** — Schema anwenden.
- [ ] **6.2.4** — Domäne **SEC** — Schema anwenden.
- [ ] **6.2.5** — Domäne **E** — Schema anwenden.
- [ ] **6.2.6** — Domäne **A** (inkl. A15+ CLI-Erweiterungen) — Schema anwenden.
- [ ] **6.2.7** — Domäne **K** — Schema anwenden.
- [ ] **6.2.8** — Domäne **U** — Schema anwenden.
- [ ] **6.2.9** — Domäne **M** (Middleware) — Schema anwenden.

### 6.3 — Konsistenz-Check

- [ ] **6.3.1** — Grep: Jede nicht-leere Zelle enthält Testklassen-Name, Siegel und Testmethoden-Zähler.
- [ ] **6.3.2** — Grep: Detailkonzept-Links (sofern vorhanden) verweisen auf existierende Dateien.

---

## Phase 7 — `@see`-Pfad-Update in Testklassen

**Ziel:** Die stale Verweise `@see docs/testing-bigpicture.md <Feature-ID>` in Testklassen auf `@see docs/tds_conditions_ref.md <Feature-ID>` aktualisieren. Feature-IDs bleiben unverändert.
**Review-Modell:** **E** — Meilenstein-Review. Reiner Search/Replace über alle betroffenen Dateien, am Ende reviewt als ein einziger konsolidierter Diff. Die Verifikations-Schritte 7.3–7.5 sind Bestandteil des Meilensteins.

**Risikoanalyse:** Reine Pfad-Aktualisierung in PHPDoc-Kommentaren. Keine Auswirkungen auf Test-Verhalten, keine Parser-/Autoloader-Betroffenheit. Laut Analyse §3.2 sind 10 Vorkommen verifiziert (Stand 2026-04-11).

**Scope:** Nur Testing-Platform-Repo (`layer3-integration/`, `layer4-e2e/`). Fork-Repo (`webtrees-upstream/webtrees`) bleibt read-only und wird **nicht** angefasst.

- [ ] **7.1** — Vollständige Grep-Inventarisierung: `grep -rn 'testing-bigpicture' layer3-integration/ layer4-e2e/` — Ergebnis als Checkliste festhalten (jede Datei und Zeile).
- [ ] **7.2** — Bulk-Update durchführen via `sed -i` pro Datei: `s|docs/testing-bigpicture.md|docs/tds_conditions_ref.md|g`. Kein Perl. Dateien einzeln behandeln, damit Review kleinteilig bleibt.
- [ ] **7.3** — Verifikation 1: `grep -rn 'testing-bigpicture' layer3-integration/ layer4-e2e/` muss 0 Treffer liefern.
- [ ] **7.4** — Verifikation 2: `grep -rn 'tds_conditions_ref.md' layer3-integration/ layer4-e2e/` liefert ≥ die vorher inventarisierte Anzahl Treffer.
- [ ] **7.5** — Verifikation 3 (optional): `php -l` auf betroffene `.php`-Dateien ausführen, um Syntax-Integrität sicherzustellen (PHPDoc ist parser-irrelevant, aber der Check ist billig).

---

## Phase 8 — Verifikation und Abschluss

**Ziel:** Gesamt-Kohärenz prüfen, bevor der Plan als erledigt gilt.
**Review-Modell:** **A** — Pro Sub-Phase. Jeder Check ist eigenständig und bricht eigenständig; ein fehlgeschlagener Check führt zu gezielter Nacharbeit an der betroffenen früheren Phase (Rücksprung), nicht zu globalem Rollback.

- [ ] **8.1** — Quer-Verweis-Check A: Jede Feature-ID aus `tds_conditions_ref.md` hat genau eine korrespondierende Zeile in `tds_coverage_ref.md`.
- [ ] **8.2** — Quer-Verweis-Check B: Jede Feature-ID aus `tds_coverage_ref.md` existiert in `tds_conditions_ref.md` (keine Waisen).
- [ ] **8.3** — Mapping-Tabellen (Phase 3.1) in beiden Dokumenten sind byte-gleich.
- [ ] **8.4** — Historische Snapshots (`docs/coverage-runs/historical/`) sind korrekt verlinkt und auffindbar.
- [ ] **8.5** — Neuer Gap-Analyse-Snapshot aus Phase 1 ist mit aktuellem Datum aus den Hauptdokumenten verlinkt.
- [ ] **8.6** — SPDX-Header-Verifikation: alle neu erstellten `.md`-Dateien beginnen mit `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->`.
- [ ] **8.7** — Siegel-Verifikation: keine abgedeckte Zelle ohne Siegel; kein Siegel außerhalb des Katalogs.
- [ ] **8.8** — Rendering-Probe: beide Hauptdokumente in Vorschau ansehen (GitHub-Rendering oder lokaler Markdown-Viewer), Tabellenbreiten und Anker-Links prüfen.
- [ ] **8.9** — `@see`-Rückwärts-Grep: `grep -rE '@see.*(G|S|P|SEC-|E|A|K|U|M)\d+' layer3-integration/ layer4-e2e/` liefert konsistente Treffer — alle referenzierten IDs existieren in `tds_conditions_ref.md`.
- [ ] **8.10** — Analyse-Dokument querlesen: Welche Empfehlungen (R1–R9) sind vollständig umgesetzt? Offene Restpunkte in Abschnitt *Offene Punkte für spätere Planungsrunden* unten dokumentieren.

---

## Wiederaufsetz-Hinweise (für Compact / Systemabsturz)

Dieser Plan ist so ausgelegt, dass er nach einem Kontext-Compact oder Systemabsturz reproduzierbar fortgesetzt werden kann. Vorgehen beim Wiederaufsetzen:

1. Diesen Plan öffnen und nach der ersten `[~]`-Checkbox suchen (= laufende Sub-Phase). Falls keine: nach der ersten `[ ]` suchen.
2. Abschnitt der zugehörigen Phase lesen, Eingangsdaten und Abhängigkeiten prüfen.
3. Bei `[~]`: die `Letzte Änderung`-Zeile darunter beachten und den dort dokumentierten Zwischenstand wiederherstellen.
4. Nach Erledigung eines Punkts **sofort** auf `[x]` setzen und die `Letzte Änderung`-Zeile aktualisieren.
5. Nach Abschluss aller Sub-Punkte einer Phase den `Gesamtstatus` der Phase in der Übersichtstabelle auf `[x]` setzen.

**Wichtig:** Statusaktualisierung erfolgt nach **jeder** einzelnen Sub-Phase, nicht gesammelt am Ende. Eine einzelne verlorene Session darf maximal eine Sub-Phase Arbeit kosten.

---

## Anhang A — Quer-Referenzen zur Analyse

| Plan-Phase | Bezug zur Analyse `coverage_doc_improvement_analysis.md`                    |
|------------|-----------------------------------------------------------------------------|
| Phase 0    | —                                                                           |
| Phase 1    | §4.3 (Methode), R5, §1.2 (Ist-Zahlen), §2.3 (Zwischenfazit Feature-Ermittlung) |
| Phase 2    | §5.4, §3.3, R7, D4                                                           |
| Phase 3.1  | §5.3, R1                                                                     |
| Phase 3.2  | §5.1, R2                                                                     |
| Phase 3.3  | §5.6, R8, D5                                                                  |
| Phase 3.4  | §5.5, R4                                                                     |
| Phase 3.5  | §5.1 SEC-Sonderfall, §3.1                                                    |
| Phase 3.6  | §3.1 (P/E/A/K/U fehlende L2-Spalte)                                          |
| Phase 3.7  | O8, D9                                                                       |
| Phase 4    | §5.2, R3, D2                                                                  |
| Phase 5    | §4.1 (Zusatzquellen Middleware/CLI), §1.2 (Inventar-Zahlen), O5, R9, D3+D6    |
| Phase 6    | §5.2 (Zellen-Schema)                                                         |
| Phase 7    | §5.7, §3.2, R6, D7                                                            |
| Phase 8    | §6 (Randbedingungen), Anhang A der Analyse                                   |

---

## Anhang B — Dateiliste (Neue und geänderte Artefakte)

**Neue Dateien, die vom Plan erzeugt werden:**

- `docs/coverage_doc_improvement_plan.md` (dieses Dokument)
- `docs/coverage-runs/2026-04-11_gap-analyse-fork.md` (Phase 1)
- `docs/coverage-runs/historical/2026-03-26_gap-analyse.md` (Phase 2)
- `docs/coverage-runs/historical/2026-03-27_e2e-gap.md` (Phase 2)
- `docs/coverage-runs/2026-04-11_abdeckung-snapshot.md` (Phase 2)

**Geänderte Dateien:**

- `docs/tds_conditions_ref.md` (Phasen 2, 3, 5)
- `docs/tds_coverage_ref.md` (Phasen 2, 3, 4, 5, 6)
- `docs/coverage_doc_improvement_analysis.md` (Phase 0.3, nur Rückverweis am Kopf)
- `layer3-integration/tests/*.php` (nur Phase 7, PHPDoc-Pfad-Update)
- `layer4-e2e/tests/*.spec.ts` (nur Phase 7, falls Vorkommen)

**Unberührt (Leitprinzip):**

- `webtrees-upstream/webtrees/**` — Fork-Repo bleibt read-only
- `upstream/webtrees/**` — Anwendungscode wird nur *gelesen* (Phase 5 Inventarisierung)
- Alle anderen Dokumente unter `docs/` (insbesondere `tp_decisions_spec.md`, `tds_methodik_spec.md`, `tp_ratchet_spec.md`, `tp_overview_spec.md`, `wf_coverage-to-test_guide.md`)

---

## Anhang C — Offene Punkte für spätere Planungsrunden

Diese Punkte sind bewusst **nicht** Teil dieses Plans. Sie werden hier festgehalten, damit sie in Folgerunden aufgegriffen werden können, ohne erneut durch die Analyse zu müssen.

| #   | Punkt                                                                                     | Begründung der Vertagung                                                                          |
|-----|-------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------|
| L1  | Aufspaltung `tds_conditions_ref.md` pro Domäne (O7)                                        | Würde `@see`-Anker brechen; methodisch E4-konträr — nicht empfohlen, aber formal offen.           |
| L2  | Automatisierte Coverage-Matrix-Generierung aus `coverage.xml` (§4.2)                      | „In Scope, nicht Vorgabe" laut Analyse — Matrix muss auch ohne Artefakte lesbar bleiben.          |
| L3  | Verknüpfung der SEC-Audit-Tasks (SEC-AUDIT-001 … SEC-AUDIT-008) mit der SEC-H-Reihe (§4.1) | Eigener Scope: betrifft den Audit-Workflow, nicht die Doku-Struktur.                              |
| L4  | Modul-Inventar (`app/Module/`, ~260 Klassen) als Feature-Quelle (§4.1)                     | Umfang zu groß für diesen Plan; eigene Iteration nötig.                                            |
| L5  | Template/View-Inventar (`resources/views/`) als Feature-Quelle (§4.1)                      | Umfang zu groß; eigene Iteration nötig.                                                            |
| L6  | OTel-Traces als Feature-Ermittlungs-Quelle statt nur Performance (§4.1, §4.2)              | Methodisch reizvoll, separat zu planen.                                                            |
| L7  | `tests/feature/*.php` im Fork als strukturierte Feature-Quelle (§4.1)                      | Nur 5 Dateien, aber eigener Analyseaufwand — verschoben.                                           |
| L8  | Rückwärtsindex Feature-ID → Testklasse als automatisch generiertes Artefakt (§4.2)         | Heute im Plan durch Grep-Verifikation in Phase 8.9 abgedeckt, aber nicht als persistentes Artefakt. |
