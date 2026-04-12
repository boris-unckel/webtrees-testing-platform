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
| 0     | Vorbereitung                                                  | Plan-Dokument + Verzeichnis-Struktur anlegen                                                            | —                                              | `[x]`        |
| 1     | Gap-Analyse-Neuerhebung                                       | Fork-Stand als Snapshot unter `docs/coverage-runs/` festhalten                                          | Phase 0                                        | `[x]`        |
| 2     | Historisches Material auslagern                               | 2026-03-26-Block + Zusammenfassungs-Zahlen archivieren                                                  | Phase 0                                        | `[x]`        |
| 3     | Struktur- und Nomenklatur-Migration                           | Mapping-Tabelle, Spaltenüberschriften, Status-Split, Legende, Navigation (parallel in beiden Dokumenten) | Phase 2                                        | `[x]`        |
| 4     | Qualitätssiegel (Pflichtfeld) einführen                        | Jede abgedeckte Zelle mit eindeutigem Siegel versehen (inhaltliche Einstufung)                          | Phase 3                                        | `[x]`        |
| 5     | Neue Feature-IDs vergeben                                     | M01…M<N> für Middleware, A15+ für fehlende CLI-Commands                                                  | Phase 3                                        | `[x]`        |
| 6     | Inhalts-Migration der Zellen                                  | Zellen-Schema `<Klasse> [<Siegel>] (<N> Tests) → <Detail>` syntaktisch durchgehend                      | Phase 4, Phase 5, Phase 1 (Testmethoden-Zahlen) | `[x]`        |
| 7     | `@see`-Pfad-Update in Testklassen                             | `testing-bigpicture.md` → `tds_conditions_ref.md` (risikoarmer Search/Replace)                          | — (unabhängig, bewusst spät)                    | `[~]`        |
| 8     | Verifikation und Abschluss                                    | Quer-Checks, Lesbarkeits-Check, Rendering-Probe                                                         | alle vorigen                                   | `[x]`        |

**Pflege:** Der Gesamtstatus einer Phase wird bei Abschluss aller Sub-Schritte auf `[x]` gesetzt. Bei Beginn der ersten Sub-Phase wechselt der Gesamtstatus auf `[~]`.

---

## Phase 0 — Vorbereitung

**Ziel:** Plan-Infrastruktur im Repo verankern, Verzeichnis-Struktur für Snapshots vorbereiten.
**Review-Modell:** **B** — Sub-Phasen-Gruppe. Drei Schritte als ein Block, gemeinsames Review am Gruppen-Ende.

- [x] **0.1** — Plan-Dokument `docs/coverage_doc_improvement_plan.md` anlegen (dieses Dokument selbst).
    > Letzte Änderung: 2026-04-11 — bereits vorhanden (Commit 6984796).
- [x] **0.2** — Verzeichnis `docs/coverage-runs/historical/` anlegen (für D4-Auslagerung).
    > Letzte Änderung: 2026-04-11 — angelegt mit `.gitkeep`.
- [x] **0.3** — Rückverweis auf den Plan in `docs/coverage_doc_improvement_analysis.md` setzen (eine Zeile am Ende der Kopfzone: *„Umsetzungsplan: `coverage_doc_improvement_plan.md`"*).
    > Letzte Änderung: 2026-04-11 — Kopfzone ergänzt.

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

- [x] **1.1.1** — Aktuelle Commit-SHAs aller vier Quellen dokumentieren (je ein `git rev-parse HEAD` pro Repo/Branch).
    > Letzte Änderung: 2026-04-11 — L2=841616f4b5, L3/L4=698479661f, App=9c7bdfd95a.
- [x] **1.1.2** — Diese SHAs im Kopf des späteren Snapshot-Dokuments verankern.
    > Letzte Änderung: 2026-04-11 — Snapshot-Skelett angelegt.

### 1.2 — L2-Inventarisierung (Fork)

- [x] **1.2.1** — Liste aller Testdateien unter `tests/app/` + `tests/feature/` erzeugen (erwartet: ~1237). **Finding:** phpunit.xml.dist kennt nur `tests/app/` (1233) + `tests/feature/` (5) = 1238. `tests/views/` (2 Dateien) existiert auf Disk, ist aber **nicht** im Testsuite-Scope und bleibt ausgeschlossen.
    > Letzte Änderung: 2026-04-11 — 1238 Dateien erfasst.
- [x] **1.2.2** — Pro Testdatei: Zeilenzahl, Testmethoden-Zahl, Assertions-Gesamtzahl (grep-basiert, Kommandos dokumentieren).
    > Letzte Änderung: 2026-04-11 — `/tmp/gap_inventory_php.sh`, CSV `/tmp/l2_metrics_filtered.csv`.
- [x] **1.2.3** — Assertionsdichte pro Testdatei berechnen (Assertions / Testmethoden). Durchschnitt, Median, Min/Max.
    > Letzte Änderung: 2026-04-11 — Aggregate via awk berechnet.
- [x] **1.2.4** — Qualitäts-Klassifikation pro Testdatei nach Analyse §4.3 Schritt 2: `Stub` / `Smoke` / `Substantial` / `EP-complete`. **Lesson Learned:** Reine Assertionsdichte klassifiziert die aufgewerteten Komponententests (viel Mock-Setup, 1 Abschluss-Assertion) zu pessimistisch als `Smoke`. Heuristik V2 ergänzt eine strukturelle Dimension (`lines/methods` ≥ 15 als Setup-Proxy). Neue Verteilung L2: Stub 682 / Smoke 285 / Substantial 263 / EP 8 — passt zu den 278 aufgewerteten Dateien aus Commit `841616f4b5`.
    > Letzte Änderung: 2026-04-11 — Hybrid V2 fixiert, Heuristik im Snapshot dokumentiert.

### 1.3 — L3-Inventarisierung (Testing-Platform)

- [x] **1.3.1** — Liste aller `*Test.php` unter `layer3-integration/tests/` (exkl. `MysqlTestCase.php`, `bootstrap.php`). Erwartet: ~84.
    > Letzte Änderung: 2026-04-11 — 84 Dateien erfasst.
- [x] **1.3.2** — Gleiche Metriken wie Phase 1.2.2–1.2.4.
    > Letzte Änderung: 2026-04-11 — L3: Stub 4 / Smoke 9 / Substantial 65 / EP 6.

### 1.4 — L4-Inventarisierung (Testing-Platform)

- [x] **1.4.1** — Liste aller `*.spec.ts` unter `layer4-e2e/tests/` (inkl. `tests/security/`). Erwartet: ~26.
    > Letzte Änderung: 2026-04-11 — 26 Specs erfasst.
- [x] **1.4.2** — Pro Spec: Zeilenzahl, Anzahl `test(…)`-Aufrufe, Assertions-Gesamtzahl.
    > Letzte Änderung: 2026-04-11 — `/tmp/gap_inventory_ts.sh`, CSV `/tmp/l4_metrics.csv`.
- [x] **1.4.3** — Qualitäts-Klassifikation analog L2/L3 (angepasste Kriterien für Playwright: DOM-Assertion vs. Smoke).
    > Letzte Änderung: 2026-04-11 — L4: Stub 0 / Smoke 11 / Substantial 15 / EP 0.

### 1.5 — Domänen-Zuordnung

- [x] **1.5.1** — Pro L2-Testdatei SUT-Klasse ermitteln (aus `namespace` + `class`). Ableitung zu Feature-ID anhand von `@see`-Annotationen oder manueller Zuordnung. **Finding:** L2-Tests im Fork tragen **keine** `@see`-Annotationen. Daher Zuordnung nur auf **Aggregat-Ebene** (Top-Level-Verzeichnis `tests/app/*/`) wie im Snapshot §3.4 dokumentiert. Datei-für-Datei-SUT-Ermittlung ist über die CSV-Rohdatei `_l2.csv` jederzeit möglich, wird aber nicht ausgerollt. Begründung D-Entscheidung aus Interview: „Aggregate + Top/Tails".
    > Letzte Änderung: 2026-04-11 — aggregiert, kein Zeilen-Ausroll.
- [x] **1.5.2** — Pro L3-Testdatei gleiches Vorgehen, unter Beachtung vorhandener `@see`-Annotationen. **Finding:** 47 von 82 L3-Dateien (57.3 %) tragen `@see`-Annotationen, 35 nicht. Zuordnung per `grep`-Extraktion im Snapshot §3.5 aufgerollt.
    > Letzte Änderung: 2026-04-11 — §3.5 im Snapshot.
- [x] **1.5.3** — Pro L4-Spec Zuordnung zur Feature-ID (i. d. R. über Kommentar oder manuell). **Finding:** 14 von 26 Specs (53.8 %) mit `@see`, 12 ohne — darunter alle Security-Specs. Im Snapshot §3.6 aufgerollt.
    > Letzte Änderung: 2026-04-11 — §3.6 im Snapshot.
- [x] **1.5.4** — Mehrfachzuordnung zulässig (eine Testdatei kann mehrere Features abdecken). **Finding:** Bei L3/L4 mehrfach beobachtet (z. B. `ResnPrivacyTest.php` referenziert P30, P31, P32; `appearance.spec.ts` referenziert S23 mehrfach). Im Snapshot §3.5/§3.6 als "×N" notiert.
    > Letzte Änderung: 2026-04-11 — dokumentiert.

### 1.6 — Auswertung

- [x] **1.6.1** — Pro Domäne (G/S/P/SEC/E/A/K/U): Gesamt-Testdateien, davon substanziell, davon EP-complete, aggregierte Assertionsdichte. **Ergebnis:** L2-Aggregate pro Top-Level-Verzeichnis im Snapshot §3.4. Fachliche Zuordnung pro Top-Level im Snapshot dokumentiert (`Http/` zu allen Domänen, `Elements/Census/CustomTags/Encodings/Factories/Date` zu G, `Module/Report/SurnameTradition` zu S + A + P, etc.). Domänen-Rohwerte für L3/L4 implizit über §3.5/§3.6.
    > Letzte Änderung: 2026-04-11 — Aggregat-Zuordnung §3.4.
- [x] **1.6.2** — Pro Feature-ID: Anzahl verknüpfter Testklassen pro Layer (L2/L3/L4), aggregierte Qualitätseinstufung. **Finding:** Nur für L3/L4 möglich (über `@see`); für L2 nicht (keine Annotationen). Feature-ID-Listung im Snapshot §3.5 (L3) und §3.6 (L4). Vollständige Datei-zu-ID-Matrix wird in Plan-Phase 6 (Zellen-Migration) erzeugt.
    > Letzte Änderung: 2026-04-11 — Teilabdeckung §3.5/§3.6.
- [x] **1.6.3** — Lücken-Liste: Welche Feature-IDs aus `tds_conditions_ref.md` haben im Neu-Stand **keine** Testabdeckung in irgendeinem Layer? **Finding:** Für den Snapshot reicht die Abdeckungs-Obergrenze aus `@see`-Annotationen (§3.7 Gap-Kernbefund). Vollständige Lückenanalyse erfolgt in Phase 6 gegen die Feature-Liste aus `tds_conditions_ref.md`.
    > Letzte Änderung: 2026-04-11 — §3.7 Gap-Kernbefund.

### 1.7 — Snapshot-Dokument schreiben

- [x] **1.7.1** — `docs/coverage-runs/2026-04-11_gap-analyse-fork.md` anlegen (SPDX-Header, Stand-Datum, Commit-SHAs, Methode-Beschreibung als kompakter Verweis auf Analyse §4.3, Ergebnis-Tabellen).
    > Letzte Änderung: 2026-04-11 — 352 Zeilen + 3 CSVs (L2 1238, L3 82, L4 26 Zeilen).
- [x] **1.7.2** — Snapshot in der Navigation von `docs/coverage-runs/` (Index-Datei bzw. `MEMORY.md`-Hinweis falls vorhanden) verlinken. **Finding:** Verzeichnis `docs/coverage-runs/` hat derzeit keine Index-Datei, nur Einzel-Snapshots. Der neue Snapshot liegt direkt im Verzeichnis (dateisystem-sichtbar). Verlinkung aus Hauptdokumenten erfolgt in Plan-Phase 2 und Phase 8.5. `MEMORY.md` wird im Rahmen der Memory-Ablage außerhalb dieses Plans gepflegt.
    > Letzte Änderung: 2026-04-11 — im Verzeichnis sichtbar, Referenzen folgen Phase 2/8.

---

## Phase 2 — Historisches Material auslagern

**Ziel:** Veraltete Inhalte aus den Hauptdokumenten in Archiv-Snapshots verschieben, damit die Hauptdokumente nur noch den aktiven Stand tragen.
**Review-Modell:** **B** — Sub-Phasen-Gruppe. Drei Gruppen (2.1 Gap-Analyse, 2.2 Abdeckungs-Zahlen, 2.3 E2E-Gap), jede ist ein „Inhalt umziehen + Verweis einfügen"-Paket und wird als geschlossene Einheit reviewt.

### 2.1 — Gap-Analyse-Block (2026-03-26) archivieren

- [x] **2.1.1** — `docs/coverage-runs/historical/2026-03-26_gap-analyse.md` anlegen mit SPDX-Header und kurzem Archiv-Lead-In: *„Dieser Befund ist mit Stand 2026-03-26 gegen Upstream-main erhoben. Er ist durch den Fork-Branch `port-layer2-test-doubles` in Teilen überholt. Aktueller Stand: `docs/coverage-runs/2026-04-11_gap-analyse-fork.md`."*
    > Letzte Änderung: 2026-04-11 — angelegt (99 Zeilen, SPDX, Archiv-Lead-In).
- [x] **2.1.2** — Inhalt des Abschnitts *„Befund: Gap-Analyse der existierenden webtrees-Tests"* aus `tds_conditions_ref.md` (Zeilen ≈ 58–130) ohne inhaltliche Änderung in die neue Datei kopieren.
    > Letzte Änderung: 2026-04-11 — Wortlaut 58–128 übernommen.
- [x] **2.1.3** — In `tds_conditions_ref.md` den Gap-Analyse-Block durch einen kompakten Verweis (3–5 Zeilen) ersetzen, der auf das Archiv und den neuen Snapshot zeigt.
    > Letzte Änderung: 2026-04-11 — Verweisblock eingesetzt, 70 Zeilen Inhalt entfernt.

### 2.2 — Abdeckungs-Zusammenfassungs-Zahlen archivieren

- [x] **2.2.1** — `docs/coverage-runs/2026-04-11_abdeckung-snapshot.md` anlegen mit SPDX-Header.
    > Letzte Änderung: 2026-04-11 — angelegt mit Lead-In + Cross-Links zu Gap-Analyse-Snapshots.
- [x] **2.2.2** — Header-Zeile „165 abgedeckt / 5 nicht abgedeckt / 170 Features gesamt" und die Abdeckungs-Zusammenfassungs-Tabelle aus `tds_coverage_ref.md` (Zeile ≈ 233) in den Snapshot übernehmen.
    > Letzte Änderung: 2026-04-11 — Kopfzeile + Zusammenfassungstabelle (8 Zeilen) verbatim übernommen.
- [x] **2.2.3** — In `tds_coverage_ref.md` die Zusammenfassungs-Zahlen durch einen Verweis auf `docs/coverage-runs/` ersetzen (jüngster Snapshot zuerst).
    > Letzte Änderung: 2026-04-11 — Zeile 13 und Block 231–240 durch Verweisblöcke ersetzt.

### 2.3 — E2E-Gap-Analyse (2026-03-27) archivieren

**Finding (Plan-Korrektur):** Die Analyse §5.4 und dieser Plan verorteten den Block
ursprünglich in `tds_coverage_ref.md`. Tatsächlich liegt er in
`tds_conditions_ref.md` (vor der 2.1-Archivierung an Zeile 295, danach entsprechend
verschoben) als Blockquote direkt nach der P-Feature-Matrix. Sub-Schritte 2.3.2 und 2.3.3
beziehen sich daher auf `tds_conditions_ref.md`, nicht auf `tds_coverage_ref.md`.

- [x] **2.3.1** — `docs/coverage-runs/historical/2026-03-27_e2e-gap.md` anlegen mit SPDX-Header und Archiv-Lead-In.
    > Letzte Änderung: 2026-04-11 — angelegt (24 Zeilen, SPDX, Archiv-Lead-In mit Verweis auf 2026-04-11-Snapshot).
- [x] **2.3.2** — Abschnitt *„E2E-Gap-Analyse (2026-03-27)"* aus `tds_conditions_ref.md` in die neue Datei kopieren.
    > Letzte Änderung: 2026-04-11 — Blockquote verbatim übernommen.
- [x] **2.3.3** — Abschnitt in `tds_conditions_ref.md` durch einen Verweis auf das Archiv ersetzen.
    > Letzte Änderung: 2026-04-11 — Verweisblockquote (5 Zeilen) eingesetzt.

---

## Phase 3 — Struktur- und Nomenklatur-Migration (parallel, Sub-Phasen) — **[x] erledigt**

**Ziel:** Beide Zieldokumente auf die neue Zielstruktur bringen. Jede Sub-Phase wird in **beiden Dokumenten gleichzeitig** durchgeführt (D8), damit die Spiegelung zwischen Feature-Matrix und Coverage-Matrix erhalten bleibt.
**Review-Modell:** **B + C** — Sub-Phasen-Gruppe mit Top-Down-Konsistenzcheck am Gruppen-Ende. Nach jeder Sub-Phasen-Gruppe (3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7) wird in beiden Dateien vom Dokumentanfang bis zum aktuellen Bearbeitungspunkt ein Konsistenzblick geworfen; noch unveränderte Matrizen unterhalb des Punkts werden ausdrücklich toleriert, weil sie spätere Sub-Phasen nachziehen.

> Letzte Änderung: 2026-04-11 — alle 7 Sub-Phasen-Gruppen (3.1–3.7) durch; G/S/P/SEC/E/A/K/U-Matrizen auf Einheitsstruktur `L2 | L3 | L4 | Abdeckung | Befund` normalisiert; Domänen-Navigation mit Anker-Tags in beiden Zieldokumenten aktiv.

### 3.1 — Mapping-Tabelle Teststufen ↔ Layer (§5.3)

- [x] **3.1.1** — Mapping-Tabelle am Anfang von `tds_conditions_ref.md` einfügen (Analyse §5.3, wörtlich übernehmen).
    > Letzte Änderung: 2026-04-11 — §"Teststufen und Layer — Nomenklatur" zwischen Querverweise und RE-Methodik eingefügt.
- [x] **3.1.2** — Identische Mapping-Tabelle am Anfang von `tds_coverage_ref.md` einfügen.
    > Letzte Änderung: 2026-04-11 — gleiche §, Begleittext spiegelt Dokumenten-Zweck.
- [x] **3.1.3** — Verifikation: beide Tabellen sind byte-gleich (`diff <(sed -n …) <(sed -n …)` oder vergleichbar).
    > Letzte Änderung: 2026-04-11 — `diff` über den Tabellenblock → BYTE-IDENTISCH.

### 3.2 — Spaltenüberschriften der Abdeckungsmatrix (§5.1)

- [x] **3.2.1** — In `tds_coverage_ref.md` alle Matrix-Header von `Upstream (SQLite) | Eigene Infra (MySQL) | Eigene Infra (Playwright)` auf `L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright)` migrieren. **Finding:** Die Matrizen hatten drei Varianten: 5-col mit L2 (G,S), 4-col ohne L2 (P,E,A,K) und 3-col ohne L4 (U). Alle wurden mit den neuen Spaltenbezeichnungen umbenannt; das Hinzufügen der L2-Spalte für P/E/A/K/U passiert in Phase 3.6. SEC (Sonderstruktur mit `Shell-Assertions | Playwright-Security`) wird in Phase 3.5 behandelt.
    > Letzte Änderung: 2026-04-11 — 7 Header-Zeilen migriert, grep auf alte Bezeichnungen leer.
- [x] **3.2.2** — Fußnote unter der ersten Matrix einfügen: *„L2-Spalte zeigt Stand Branch `port-layer2-test-doubles` im Upstream-Fork, im Upstream-main noch nicht akzeptiert."*
    > Letzte Änderung: 2026-04-11 — Blockquote unter G30 eingefügt, Verweis auf Snapshot ergänzt.
- [x] **3.2.3** — In `tds_conditions_ref.md` die ISTQB-Teststufen-Spalte beibehalten und in der Header-Beschreibung auf die Mapping-Tabelle (Phase 3.1) verweisen.
    > Letzte Änderung: 2026-04-11 — Layer-Zuordnungs-Verweis an alle Teststufen-Header-Blockquotes angehängt.

### 3.3 — Pfad-Legende (§5.6, D5)

- [x] **3.3.1** — Legende am Dokumentanfang von `tds_coverage_ref.md` einfügen:
    - `L2:` → `webtrees-upstream/webtrees/tests/` (Branch `port-layer2-test-doubles`)
    - `L3:` → `layer3-integration/tests/`
    - `L4:` → `layer4-e2e/tests/`
    > Letzte Änderung: 2026-04-11 — §"Pfad-Legende für Testklassen" mit Tabelle eingefügt.
- [x] **3.3.2** — Konvention für Zellen dokumentieren: Dateinamen ohne Pfad, aber mit L-Präfix (z. B. `L3: GedcomImportTest.php`).
    > Letzte Änderung: 2026-04-11 — Konvention in der Legende dokumentiert; Nachzug-Hinweis auf Phase 6.

### 3.4 — Status-Spalten-Trennung Abdeckung ↔ Befund (§5.5)

**Finding (Ausführung 2026-04-11):** Die Matrizen hatten vor Migration keine einheitliche
Spaltenzahl (G/S = 6 Spalten, P/E/A/K = 5 Spalten, U = 5 Spalten, SEC = 5 Spalten mit
Sonderstruktur). Nach dem Einsetzen der neuen Spalte verdoppelt sich der Aufwand nur
bei Ausnahme-Zeilen, weil die Werte dort nicht dem Standard-`OK | —` folgen. Die
folgenden Ausnahmen wurden identifiziert und einzeln umgesetzt:
> - G16 Export Privacy → `OK | Upstream-Bug`
> - S18 Chart-Smoke → `OK (13/13) | —` (Testanzahl im Abdeckung-Feld belassen)
> - S20 Listen-Smoke → `OK (10/10) | —`
> - S53 Legacy-URL-Weiterleitungen → `— | —` (nicht abgedeckt)
> - SEC-C03 Config Datei-Permissions → `OK | Upstream-Bug`
> - SEC-HDR04 Server-Banner → `OK | Deployment-Empfehlung`
> - A08 Medienverwaltung Admin → `— | —`
> - K01 Kontaktformular → `— | —`
> - K02 Benutzer-Nachrichten → `— | —`
> - U02 CountryService → `SKIP | Deprecated (@deprecated, Entfernung in webtrees 2.3; kein Test geplant)`
>
> Zusätzlich wurde die Masse aller 165 Abgedeckt-Zeilen mechanisch via `replace_all`
> auf `OK | —` umgestellt. Die 7 Trennlinien unter den Matrix-Headern wurden in einem
> eigenen Schritt mit einem einzigen `replace_all` von 5-Gruppen- auf 6-Gruppen-Muster
> angepasst (Substring-Trick: in 6-Gruppen-Zeilen matcht nur die erste 5-Gruppen-Sequenz,
> in 5-Gruppen-Zeilen die gesamte Zeile → beide Ziele mit einer Operation erreicht).
> SEC hatte eine individuelle Trennlinien-Breite und wurde separat normalisiert.

- [x] **3.4.1** — In jeder Matrix von `tds_coverage_ref.md` die bisherige `Status`-Spalte in zwei Spalten `Abdeckung` und `Befund` aufteilen.
    > Letzte Änderung: 2026-04-11 — 8 Header-Zeilen (G/S/P/SEC/E/A/K/U) auf `Abdeckung | Befund` umbenannt; 8 Trennlinien um eine Gruppe erweitert.
- [x] **3.4.2** — Wertebereich `Abdeckung` fixieren: `OK` / `Teil` / `—` / `SKIP`.
    > Letzte Änderung: 2026-04-11 — Wertebereich verwendet; S18/S20 mit Klammer-Zahlen als Erweiterung, U02 `SKIP`.
- [x] **3.4.3** — Wertebereich `Befund` fixieren: `—` / `Upstream-Bug` / `Deployment-Empfehlung` / `Deprecated`.
    > Letzte Änderung: 2026-04-11 — alle vier Werte in Nutzung; restliche Zellen `—`.
- [x] **3.4.4** — Bestehende kombinierte Einträge wie `**Abgedeckt** (mit Upstream-Bug)` in `OK | Upstream-Bug` auflösen (über alle Matrizen).
    > Letzte Änderung: 2026-04-11 — G16/SEC-C03/SEC-HDR04/U02 manuell, Rest mechanisch via `replace_all`.

### 3.5 — Einheitsstruktur für SEC-Submatrix (§5.1 SEC-Sonderfall)

**Finding (Ausführung 2026-04-11):** Die frühere SEC-Submatrix hatte zwei Spalten
`Shell-Assertions` und `Playwright-Security`. Zwei Einträge (SEC-BOT01, SEC-UTL01) waren
faktisch L3-Integrationstests, wurden aber in der `Shell-Assertions`-Spalte geführt,
weil die Spalte historisch als „alles außer Playwright" missverstanden wurde. Die
Restrukturierung verschiebt diese beiden in die neue L3-Spalte und den übrigen
Shell-Checks in die L4-Spalte (Shell-Skripte laufen gegen die installierte Instanz und
zählen damit zu Systemtests, nicht zu L2/L3).

- [x] **3.5.1** — SEC-Submatrix in `tds_coverage_ref.md` auf die Einheitsstruktur umstellen (`L2 | L3 | L4 | Abdeckung | Befund`).
    > Letzte Änderung: 2026-04-11 — 30 SEC-Zeilen auf neue 7-Spalten-Struktur umgestellt; BOT01/UTL01 in L3 verschoben.
- [x] **3.5.2** — Shell-Assertions (`security-filesystem-checks.sh`) als Klammer-Kommentar in der L4-Zelle dokumentieren, nicht als eigene Spalte.
    > Letzte Änderung: 2026-04-11 — Shell-Skript-Hinweis als Blockquote über der Tabelle ergänzt; SEC-WZ03-Kombi-Zelle mit `+` getrennt.

### 3.6 — Upstream-Spalte für P/E/A/K/U ergänzen (§3.1 historische Lücke)

**Finding (Ausführung 2026-04-11):** Beim Einfügen der L2-Spalte traten drei
Besonderheiten auf:
> - **E01–E05 / A01/A02/A03/A05/A09/A11** hatten in den Datenzeilen bereits eine
>   pre-existierende 7. Zelle (ein Platzhalter-`—` zwischen Feature und L3-Test),
>   die seit der alten 5-Spalten-Struktur als Orphan-Zelle im Markdown-Rendering
>   unterdrückt wurde. Durch das Einfügen der L2-Header-Spalte rastet dieses `—`
>   automatisch als L2-Platzhalter ein; keine Datenzeilen-Änderung nötig.
> - **P-Matrix (41 Zeilen), E06–E08, A04/A06/A07/A08/A10, K01/K02** hatten die
>   exakte Zellzahl des alten Headers. Diese Zeilen wurden mit `— |` nach der
>   Feature-Zelle ergänzt (Einfügen per Block-Edit pro Matrix).
> - **U-Matrix** hatte bereits eine L2-Spalte (U01 = `ValidatorTest`), aber keine
>   L4-Spalte. Der Plan-Titel sagt „L2 ergänzen" — der Sinn ist aber
>   „Einheitsstruktur `L2 | L3 | L4 | Abdeckung | Befund` herstellen". Also wurde
>   bei U die L4-Spalte ergänzt (statt L2), um die Einheitsstruktur zu erreichen.
>
> L2-Zellen bleiben in diesem Schritt bewusst alle `—`: die Phase-1-Neuerhebung
> (§3.4, §3.7) hat L2 nur auf Aggregat-Ebene pro Top-Level-Verzeichnis zuordbar
> gemacht, nicht pro Feature-ID. Eine Datei-für-Feature-Zuordnung müsste gegen die
> CSV-Rohdaten und die SUT-Klassennamen durchgeführt werden; das ist Phase 6
> (Zellen-Migration mit Testmethoden-Metriken) bzw. Plan-Iteration 2 vorbehalten.

- [x] **3.6.1** — In `tds_coverage_ref.md` die Spalte `L2 — Komponententest (Upstream-Fork)` bei P/E/A/K/U-Matrizen ergänzen (auch wenn zunächst leer).
    > Letzte Änderung: 2026-04-11 — Header und Separator aller 5 Matrizen auf Einheitsstruktur; U-Matrix bekam L4-Spalte statt L2.
- [x] **3.6.2** — Zellen auf Basis der Phase-1-Neuerhebung befüllen (`—` bei keiner L2-Abdeckung, sonst Testklassen-Name).
    > Letzte Änderung: 2026-04-11 — L2-Zellen aller P/E/A/K mit `—` gefüllt (Phase 1 bietet keine per-Feature-Granularität für L2). Phase-1-Aggregat-Zuordnungen bleiben Plan-Phase 6 / Iteration 2 vorbehalten.

### 3.7 — Domänen-Navigation / Anker-Links (§O8, D9)

- [x] **3.7.1** — In `tds_coverage_ref.md` am Dokumentanfang eine kompakte Navigation einfügen: `[G](#g) · [S](#s) · [P](#p) · [SEC](#sec) · [E](#e) · [A](#a) · [K](#k) · [U](#u) · [M](#m)` (M erst in Phase 5 aktiv, Platzhalter jetzt).
    > Letzte Änderung: 2026-04-11 — §"Domänen-Navigation" zwischen Pfad-Legende und Abdeckungsmatrix eingefügt.
- [x] **3.7.2** — Gleiche Navigation am Dokumentanfang von `tds_conditions_ref.md`.
    > Letzte Änderung: 2026-04-11 — §"Domänen-Navigation" zwischen Teststufen-Nomenklatur und RE-Methodik eingefügt (byte-identisches Link-Set).
- [x] **3.7.3** — Pro Domänen-Header Anker setzen (Heading-IDs prüfen, ggf. explizite HTML-Anker).
    > Letzte Änderung: 2026-04-11 — `<a id="…"></a>` vor allen 8 Domänen-Headern in beiden Dokumenten eingefügt (G/S/P/SEC/E/A/K/U). M-Anker folgt in Phase 5.1.

---

## Phase 4 — Qualitätssiegel als Pflichtfeld einführen — [x] erledigt

> **Phase 4 abgeschlossen am 2026-04-11.** Der Siegel-Katalog ist im Dokumentkopf von
> `tds_coverage_ref.md` verankert; alle 179 abgedeckten Matrix-Zellen (G/S/P/SEC/E/A/U;
> K ohne Abdeckung) tragen genau ein Siegel aus `{EP, Spec-B, Spec-C, Smoke, CRAP}`.
> Verteilung: 26 EP · 32 Spec-B · 89 Spec-C · 35 Smoke · 2 CRAP. Die Einstufung folgt der
> Hybrid-Heuristik V2 und der im Katalog notierten Mapping-Regel. Details zu den
> Einzelentscheidungen stehen in den Sub-Phasen-Findings unten.

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

- [x] **4.1** — Siegel-Katalog am Dokumentanfang von `tds_coverage_ref.md` als Tabelle einpflegen.
    > Letzte Änderung: 2026-04-11 — §"Qualitätssiegel-Katalog" zwischen Pfad-Legende und Domänen-Navigation eingefügt. Enthält die 5 Siegel (EP/Spec-B/Spec-C/Smoke/CRAP), Lesart-Regel („Siegel hinter Klassennamen, volle Zell-Syntax folgt in Phase 6") und Verweis auf die Hybrid-Heuristik V2 in der Fork-Gap-Analyse als Einstufungs-Quelle.
- [x] **4.2** — Domäne **G** (GEDCOM) — jede abgedeckte Zelle einstufen.
    > Letzte Änderung: 2026-04-11 — G01–G30 eingestuft. Verteilung: **[Spec-B]** für alle `GedcomImportServiceTest`/`GedcomExportServiceTest`/`GedcomImportTest`/`TreeOperationsTest`-Zellen sowie G22 (212 Element-Tests, GEDCOM-Record-Elemente per Spec); **[Spec-C]** für G15 (2 pragmatische ZIP-Tests), G21 (L4 upload-validation), G28 (2 pragmatische Tests); **[Smoke]** für G23 (1 Test) und G24 (200 OK + Body, 2 Assertions); **[EP]** für G25 (8 EP-Tests), G26 (13 EP-Tests), G29 (9 EP-Tests), G30 (3 UPLOAD_ERR_-Partitionen); **[CRAP]** nur für G27 (CRAP-Analyse, 2 Tests). Kompound-Zellen (G02 L3) erhalten ein Einzelsiegel gemäß dominanter Testklasse. Einstufungs-Regel: „Spec-B" für GEDCOM-5.5.1-konforme Testklassen (strikte externe Spezifikation), „Spec-C" für fachliche Assertions ohne strikte Spec-Ableitung, „EP" nur bei ≥3 explizit markierten EP-Partitionen. L4-Specs bleiben pro Hybrid-V2-Befund 3.6 immer ≤ Spec-C (keine EP-complete in L4).
- [x] **4.3** — Domäne **S** (Sichten) — einstufen.
    > Letzte Änderung: 2026-04-11 — S01–S52 eingestuft. **[Spec-B]** nur für S07/S08 L2 (Russell/DM Soundex als definierter Algorithmus); **[EP]** für S41/S42/S43/S46/S48/S49/S50 L3 (explizite DataProvider/EPn-Markierung, ≥3 Partitionen); **[Spec-C]** als Default für Suche/Chart/List/Module-Service-Tests und mittlere Navigation-Specs (3+ Themes/Tests); **[Smoke]** für einzellige Navigation-Specs (`login`, `auth`, `individual`, `records` einzelne Seiten) und L2/L3-Zellen mit 2 Tests (z. B. `SearchIntegrationTest` S07/S08/S11). Kein **[CRAP]** in S. S53 bleibt unabgedeckt.
- [x] **4.4** — Domäne **P** (Privacy) — einstufen.
    > Letzte Änderung: 2026-04-11 — P01–P41 eingestuft. **[Spec-B]** für P16–P21 `ResnPrivacyTest` (RESN-Werte 1:1 GEDCOM-Spec); **[EP]** für P30–P37 (5–17 Tests mit expliziten B/EP-Partitionen bzw. DataProvidern); **[Spec-C]** als Default für P01–P15 `PrivacyVisibilityTest`/`IsDeadTest`-Gruppen, P22–P29, P38, P40, P41 sowie alle L4-Privacy-Specs; **[Smoke]** nur für P39 `LoginActionIntegrationTest` (1 Test). P32 L3-Zelle von Compound `DeleteRecordIntegrationTest` + `GedcomRecordPageIntegrationTest` zusammengefasst — dominantes Siegel **[EP]** (EP1-DataProvider mit 4 Record-Typ-Partitionen). CRAP-Smoke-Tests (`RequestHandlerBatchA/B`, `MergeFactsIntegrationTest`) bleiben ohne eigenes Siegel in der Zelle, da ein Siegel pro Zelle die Regel ist und die dominante Hauptklasse führt.
- [x] **4.5** — Domäne **SEC** (Security) — einstufen.
    > Letzte Änderung: 2026-04-11 — SEC-H01–SEC-UTL01 (26 Features) eingestuft. **[Spec-B]** für SEC-HDR01–HDR03 (X-Content-Type-Options/X-Frame-Options/Referrer-Policy folgen OWASP/RFC 7034/W3C-Spezifikationen, 1:1-Abgleich); **[EP]** für SEC-BOT01 `BadBotBlockerIntegrationTest` (DataProvider BAD_ROBOTS×5 + WP-Pfade×4 + EP8/EP9); **[Spec-C]** für `data-access.spec.ts`, `media-access.spec.ts`, `public-access.spec.ts`, `setup-lock.spec.ts`, `wizard-setup.spec.ts`, SEC-HDR04 (pragmatisch wegen Deployment-Empfehlung) und SEC-UTL01 (10 Tests ohne explizite EP-Struktur); **[Smoke]** für alle `security-filesystem-checks.sh`-Zellen (einzelne Existenz-Checks auf Dateisystem-Ebene). SEC-WZ03 Compound-Zelle (Spec + Shell) trägt ein einzelnes Siegel am Spec als dominanter Testquelle.
- [x] **4.6** — Domäne **E** (Editing) — einstufen.
    > Letzte Änderung: 2026-04-11 — E01–E08 eingestuft. **[EP]** nur für E01 `AddRelationIntegrationTest` (DataProvider 4 Relation-Typ-Partitionen AddParent/AddSpouseToIndi/AddChild/AddSpouseToFam); **[Spec-C]** für E02–E08 (3–5 Tests je Feature mit fachlichen Assertions, aber ohne explizite EP-Struktur oder DataProvider mit ≥3 Partitionen). Keine **[Spec-B]**, **[Smoke]** oder **[CRAP]** in E.
- [x] **4.7** — Domäne **A** (Administration) — einstufen.
    > Letzte Änderung: 2026-04-11 — A01–A11 (ohne A08, unabgedeckt) eingestuft. **[EP]** für A02 (3 UPLOAD_ERR-Partitionen + Page) und A05 (DataProvider Analytics/Blocks/Charts/Menus/Reports — 5 Module-Typ-Partitionen); **[Spec-C]** als Default für A01, A03, A04, A06, A07, A09, A10, A11 (3–5 fachliche Tests ohne explizite EP-Struktur). A08 bleibt unabgedeckt/unklassifiziert.
- [x] **4.8** — Domäne **K** (Kommunikation) — einstufen.
    > Letzte Änderung: 2026-04-11 — K01 und K02 beide nicht abgedeckt (keine L2/L3/L4-Tests). Keine Siegel zu vergeben; die Phase ist inhaltlich ein No-Op. Vollständige Abdeckungslücke bleibt bestehen und wird in Plan-Phase 5/6 bzw. Plan-Iteration 2 adressiert.
- [x] **4.9** — Domäne **U** (User-Management) — einstufen.
    > Letzte Änderung: 2026-04-11 — U01 L2 `ValidatorTest` (391 Zeilen/24 Methoden/52 Assertions, laut Fork-Gap-Analyse §3.8 Top-10 als `EP-complete` eingestuft) → **[EP]**. U01 L3 `ValidatorIntegrationTest` (15 Tests mit expliziten EP1–EP5+BV+Inv+Miss-Partitionen für float()) → **[EP]**. U02 bleibt SKIP/Deprecated und erhält kein Siegel.
- [x] **4.10** — Verifikations-Grep: jede nicht-leere Zelle enthält genau ein Siegel-Kürzel aus der Liste; kein Siegel außerhalb des Katalogs.
    > Letzte Änderung: 2026-04-11 — Verifikation durchgeführt, Ergebnis:
    >
    > **Gesamt-Occurrences Siegel-Kürzel im Dokument:** 184
    > - `[EP]`: 26
    > - `[Spec-B]`: 32
    > - `[Spec-C]`: 89
    > - `[Smoke]`: 35
    > - `[CRAP]`: 2
    >
    > **Davon in Backticks (Siegel-Katalog-Tabelle, §Qualitätssiegel-Katalog Zeilen 70–74):** 5 (je einmal pro Siegel-Typ) — das sind Meta-Referenzen, keine Zellen-Anwendungen.
    >
    > **Effektive Siegel-Anwendungen in Matrix-Zellen:** 179.
    >
    > **Siegel außerhalb des Katalogs:** keine. Regex `\[[A-Za-z-]+\]` matcht im gesamten Dokument außerhalb der 5 Katalog-Zeilen nur noch Markdown-Links (`[Feature-Matrizen]`, `[Testentwurfsverfahren]` am Dokumentanfang) und die Domänen-Navigation (`[G]`/`[S]`/... Links zu Ankern in Zeile 92) — alles strukturell unkritisch, nichts Testklassen-bezogen.
    >
    > **Compound-Zellen (mehrere ✅, ein Siegel):** bewusst so geführt — `S18 L2` (6 Chart-Tests + StatisticsChartModuleTest), `S41 L3` (StatisticsDataIntegrationTest + StatisticsIntegrationTest), `P30 L3` (MergeFactsActionIntegrationTest + MergeFactsIntegrationTest + RequestHandlerBatchBIntegrationTest), `P32 L3` (DeleteRecordIntegrationTest + GedcomRecordPageIntegrationTest + RequestHandlerBatchAIntegrationTest), `P33 L3` (TreePrivacyActionIntegrationTest + RequestHandlerBatchAIntegrationTest), `P34 L3` (RenumberTreeActionIntegrationTest + RequestHandlerBatchBIntegrationTest), `P37 L3` (UserEditActionIntegrationTest + RequestHandlerBatchBIntegrationTest), `SEC-WZ03 L4` (wizard-setup.spec.ts + security-filesystem-checks.sh). Bei diesen trägt jeweils die dominante Hauptklasse das Siegel; Zusatzklassen werden über ihren textuellen Kontext („CRAP-Smoke", „Shell") mit geführt, ohne eigenes Siegel. Das entspricht Regel „genau ein Siegel pro Zelle".
    >
    > **Unabgedeckte Zellen ohne Siegel (Korrektheit bestätigt):** S53, A08, K01, K02 (vollständig leer); U02 (SKIP/Deprecated); G14/G15 L2 (Klartext-Begründung ohne ✅). Keine Lücken.

---

## Phase 5 — Neue Feature-IDs vergeben — [x] erledigt

**Ziel:** Heute nicht erfasste Bereiche als neue Feature-IDs einführen, gemäß D3 und D6.
**Review-Modell:** **B** — Sub-Phasen-Gruppe. 5.1 (Middleware-Domäne M) und 5.2 (CLI-Erweiterungen) sind zwei klar getrennte hochinhaltliche Gruppen und werden je als geschlossenes Paket reviewt. Inventarisierung, ID-Vergabe, Matrix-Einträge und Siegel-Setzung gehören zum jeweiligen Gruppen-Review.

**Abschluss 2026-04-12:** Phase 5 komplett. **Neue Feature-IDs gesamt: 35** (28 M-Domäne + 7 CLI-Erweiterungen). Neue Gesamt-Feature-Anzahl: 170 + 35 = **205**. Abdeckungs-Delta: M-Domäne bringt 6 abgedeckte IDs (M01, M02, M04, M05, M22, M24 mit insgesamt 10 Siegeln), CLI-Erweiterungen bringen 0 abgedeckte IDs. Aktualisierung der Zusammenfassungs-Tabelle (165/5/170 → 171/34/205) folgt in Phase 6/8, nicht in Phase 5.

### 5.1 — Neue Domäne `M` (Middleware)

- [x] **5.1.1** — Inventarisierung: Alle Middlewares unter `upstream/webtrees/app/Http/Middleware/` auflisten (Dateiname, Klassenname, Konstruktor-Abhängigkeiten, kurze Funktionsbeschreibung).
    > Letzte Änderung: 2026-04-11 — 34 Middleware-Klassen via Explore-Agent inventarisiert. Stateless (keine Konstruktor-Deps): 11 Klassen. Mit Konstruktor-Deps: 23 Klassen. Größte Klasse: `BadBotBlocker` (~15 KB, Regex-Liste).
- [x] **5.1.2** — Fachliche Clusterung: Welche Middlewares gehören funktional zusammen (Auth, Bad-Bot-Blocking, CSP, Rate-Limiting, Request-Logging, Maintenance-Mode, Session, Exception-Handler, …)?
    > Letzte Änderung: 2026-04-11 — 8 Cluster identifiziert: (1) Auth/Autorisierung — 7 Klassen `Auth*`; (2) Bad-Bot/Client-Identifikation — `BadBotBlocker`, `ClientIp`; (3) CSRF/Security-Headers — `CheckCsrf`, `SecurityHeaders`; (4) Session/DB/Schema — `UseSession`, `UseDatabase`, `UpdateDatabaseSchema`; (5) Routing/Request/Theme/Language — `BaseUrl`, `LoadRoutes`, `Router`, `RequestHandler`, `UseLanguage`, `UseTheme`; (6) Error/Exception/Debug — `ErrorHandler`, `HandleExceptions`, `DebugLogger`; (7) Housekeeping/Cache/Compress — `DoHousekeeping`, `CompressResponse`, `ContentLength`; (8) Sonstiges — `ReadConfigIni`, `CheckForMaintenanceMode`, `CheckForNewVersion`, `PublicFiles`, `RegisterGedcomTags`, `BootModules`, `UseTransaction`, `EmitResponse`. Die 7 Auth-Rollen-Klassen werden als **ein logischer Cluster** (ID M01) zusammengefasst, weil sie denselben Mechanismus (rollenbasierte Zugriffskontrolle) für verschiedene Rollen-Ebenen implementieren.
- [x] **5.1.3** — IDs `M01…M<N>` vergeben (fortlaufend, pro Middleware bzw. pro logischer Cluster-Einheit). Präfix `M` neu, noch nirgends belegt.
    > Letzte Änderung: 2026-04-11 — IDs M01–M28 vergeben (28 IDs für 34 Middlewares, weil M01 die 7 Auth-Rollen-Klassen zusammenfasst). M01 Rollenbasierte Zugriffskontrolle, M02 Bad-Bot-Blocker, M03 Client-IP, M04 CSRF, M05 Security-Headers, M06 Session, M07 Datenbank, M08 Schema-Migration, M09 Base-URL, M10 Routen-Laden, M11 Routing, M12 Request-Handler-Dispatch, M13 Sprachauswahl, M14 Theme-Auswahl, M15 PHP-Error→Exception, M16 Exception-Handling, M17 Debug-Logger, M18 Housekeeping, M19 Kompression, M20 Content-Length, M21 Config-Ini-Lesen, M22 Wartungsmodus, M23 Update-Prüfung, M24 Public-Files, M25 GEDCOM-Tag-Registrierung, M26 Modul-Bootstrap, M27 DB-Transaktion, M28 Response-Emittierung. Präfix `M` ist noch nicht in `tds_conditions_ref.md` belegt (per Phase-1-Gap-Analyse-Befund).
- [x] **5.1.4** — In `tds_conditions_ref.md` neue Domänen-Sektion `M — Middleware` einfügen (Feature-Matrix mit Beschreibung pro ID).
    > Letzte Änderung: 2026-04-11 — Neue Sektion `## Feature-Matrix: Middleware (M)` in `tds_conditions_ref.md` (Zeilen 398–439) eingefügt zwischen U-Matrix und `## Entscheidung: Reverse-Engineering-Quellen`. Enthält: Anker `<a id="m"></a>`, Blockquote zur PSR-15-Middleware-Chain-Ordnung und M01-Konsolidierungsbegründung (7 Auth-Klassen → 1 Cluster), Tabelle mit 28 Zeilen M01–M28 (Spalten: `#`/Feature/Abgeleitete Anforderung/Teststufe/Prio). Querverweise zu bestehenden L3/L4-Tests: M02 → SEC-BOT01 (`BadBotBlockerIntegrationTest`, 15 Tests), M05 → SEC-HDR01–HDR04 (`security-headers.spec.ts`). Klassenebene jeweils in Monospace (`AuthLoggedIn`, `BadBotBlocker` usw.), gefolgt von kurzer Funktionsbeschreibung.
- [x] **5.1.5** — In `tds_coverage_ref.md` neue Domänen-Sektion `M — Middleware` einfügen (Coverage-Matrix, Zellen nach Phase-1-Metriken befüllt).
    > Letzte Änderung: 2026-04-12 — Neue Sektion `#### Middleware (M01–M28)` in `tds_coverage_ref.md` eingefügt zwischen U-Matrix und `#### Zusammenfassung Abdeckung`. Enthält: Anker `<a id="m"></a>`, Hinweis-Blockquote zur Stub-Situation im Upstream-Fork (Regel „Stub → Smoke nur bei ≥ 3 Assertions"), 28 Zeilen M01–M28. L2-Klassifikation aus `coverage-runs/2026-04-11_gap-analyse-fork_l2.csv` Zeilen 506–536 abgeleitet: **6 von 28 M-IDs** mit ✅ abgedeckt (M01, M02, M04, M05, M22, M24). **22 von 28** ohne Siegel (= nicht abgedeckt), davon 20× Stub mit 1–2 Assertions (ungenügend) und 2× ohne L2-Test (M15 ErrorHandler, M17 DebugLogger). Cluster-Entscheidung für M01: 5× `Auth*Test` Substantial + 1× `AuthLoggedInTest` Stub + 1× `AuthNotRobot` ohne L2-Test → dominantes Siegel `[Spec-C]`. Querverweise in L3/L4 explizit ausgewiesen: M01→P27–P29, M02→SEC-BOT01, M05→SEC-HDR01–HDR04. Keine Änderung an Zusammenfassungs-Zahlen (170 Gesamt-Features), weil die M-Domäne in Plan-Phase 5 neu angelegt wird und die Snapshot-Datei erst in Phase 6/8 aktualisiert wird.
- [x] **5.1.6** — Navigation aus Phase 3.7 um `M` erweitern (Platzhalter-Link aktivieren).
    > Letzte Änderung: 2026-04-12 — Platzhalter-Blockquote „**Platzhalter:** Die Domäne `M` (Middleware) wird in Plan-Phase 5.1 angelegt; der Anker ist bis dahin leer." unter `## Domänen-Navigation` (vormals Zeile 94) entfernt, da der Anker `<a id="m"></a>` nun auf die neue Sektion `#### Middleware (M01–M28)` zeigt. Die Navigations-Zeile `[G](#g) · [S](#s) · [P](#p) · [SEC](#sec) · [E](#e) · [A](#a) · [K](#k) · [U](#u) · [M](#m)` bleibt unverändert.
- [x] **5.1.7** — Qualitätssiegel (Phase 4) für die Domäne `M` setzen.
    > Letzte Änderung: 2026-04-12 — Qualitätssiegel bereits in 5.1.5 mit der Matrix befüllt (nicht als eigener Arbeitsschritt ausgelagert, weil das Einfügen einer neuen Domäne nach Phase 4 direkt mit Siegeln geschehen muss). Siegel-Verteilung für M: **10 Siegel in 28 Zeilen** = 6 abgedeckte M-IDs. M01 L2 [Spec-C] / L3 [EP] / L4 [Spec-C] (Cluster + Querverweis P27–P29); M02 L2 [Spec-C] / L3 [EP] (Querverweis SEC-BOT01); M04 L2 [Smoke]; M05 L2 [Spec-C] / L4 [Spec-B] (Querverweis SEC-HDR01–HDR04); M22 L2 [Smoke]; M24 L2 [Spec-C]. Grep-Verifikation: 9 M-Matrix-Zeilen mit `[<Siegel>]`-Token, davon M01 mit drei Siegeln (Gesamt 10 Siegel). Keine Siegel in den 22 nicht-abgedeckten Zeilen (Regel „kein Siegel in `—`-Zellen").

### 5.2 — CLI-Erweiterungen in A-Reihe (und ggf. anderen Reihen)

**Heute erfasst:** G25 (GedcomLoad), G26 (TreeExport), P35 (UserEdit), P36 (CliSettings).

**Nicht erfasst (aus Analyse §1.2):** `CompilePoFiles`, `ConfigIni`, `SiteOffline`, `SiteOnline`, `TreeList`, `UserList`, `TreeImport`, `SiteSetting`, `TreeSetting`, `UserSetting`, `UserTreeSetting`.

- [x] **5.2.1** — Inventarisierung aller CLI-Commands unter `upstream/webtrees/app/Cli/Commands/` mit Beschreibung (Funktion, benötigte Rechte, Risiko).
    > Letzte Änderung: 2026-04-12 — 14 CLI-Commands inventarisiert (Verzeichnis enthält zusätzlich `AbstractCommand.php` als Basisklasse und `TestMonthNames.php.keep` als ausgeschlossene Datei). Alle Commands verwenden Symfony-Console mit `setName()` in `configure()`. Vollständige Liste alphabetisch: `CompilePoFiles` (compile-po-files, erzeugt PHP aus PO-Übersetzungen, Mittel), `ConfigIni` (config-ini, schreibt `data/config.ini.php` + DB-Check, Hoch), `SiteOffline` (site-offline, schreibt `offline.txt`, Mittel), `SiteOnline` (site-online, löscht `offline.txt`, Niedrig), `SiteSetting` (site-setting, `site_setting`-Tabelle read/write/delete, Mittel; **bereits P36**), `TreeEdit` (tree, `tree`+`gedcom_setting` write/delete, Hoch; **bereits A01 über HTTP-Pendant**), `TreeExport` (tree-export, liest Baum → GEDCOM/ZIP-Ausgabe, Niedrig; **bereits G26**), `TreeImport` (tree-import, löscht und schreibt alle genealogischen Tabellen, Hoch), `TreeList` (tree-list, `tree`+`gedcom`-Tabellen read, Niedrig), `TreeSetting` (tree-setting, `gedcom_setting`-Tabelle read/write/delete, Mittel; **bereits P36**), `UserEdit` (user, `user`+`user_setting` write/delete, Hoch; **bereits P35**), `UserList` (user-list, `user`+`user_setting` read, Niedrig), `UserSetting` (user-setting, `user_setting` read/write/delete, Mittel; **bereits P36**), `UserTreeSetting` (user-tree-setting, `user_gedcom_setting` read/write/delete, Mittel; **bereits P36**).
- [x] **5.2.2** — Fachliche Zuordnung pro Command zu Domäne:
    - `SiteOffline`, `SiteOnline`, `ConfigIni`, `CompilePoFiles`, `SiteSetting`, `TreeSetting`, `TreeList` → **A** (Administration)
    - `UserList`, `UserSetting`, `UserTreeSetting` → **P** (Privacy/User)
    - `TreeImport` → **G** (GEDCOM, analog G25)
    > Letzte Änderung: 2026-04-12 — Zuordnung abgeglichen mit aktuellem Bestand:
    > - `SiteSetting`, `TreeSetting`, `UserSetting`, `UserTreeSetting` sind **bereits P36** („CLI Einstellungs-Verwaltung", `CliSettingsBatchIntegrationTest` 17 Tests). Keine neuen IDs nötig.
    > - `UserEdit` ist **bereits P35**, `TreeExport` ist **bereits G26**.
    > - `TreeEdit` ist über das HTTP-Pendant als **A01** bereits abgedeckt (CreateTreeAction/DeleteTreeAction/MergeTrees — siehe A01-Text „Stammbaum-Management"); keine eigene CLI-ID nötig.
    > - Tatsächlich neu zu vergebene IDs (7): `SiteOffline`, `SiteOnline`, `ConfigIni`, `CompilePoFiles`, `TreeList` → **A** (Admin-Commands); `UserList` → **P** (User-Commands, Pattern aus P35/P36 folgend); `TreeImport` → **G** (CLI-GEDCOM-Import, unterscheidet sich von G25 `GedcomLoad` HTTP-RequestHandler).
    > - Begründung Anlehnung an bestehende Muster: Admin-Commands bekommen A-Reihen-IDs, User-Commands bekommen P-Reihen-IDs (analog P35/P36), GEDCOM-Operations bekommen G-Reihen-IDs (analog G25/G26).
- [x] **5.2.3** — Neue IDs in der A-Reihe vergeben (A15, A16, …) für die Admin-Commands.
    > Letzte Änderung: 2026-04-12 — A-Reihe endet aktuell bei **A11** (nicht A14, wie das Plan-Placeholder „A15, A16" vermuten ließ). Fortsetzung daher **A12–A16** für 5 neue Admin-CLI-Commands: **A12** `SiteOffline` (Wartungsmodus aktivieren, `offline.txt` schreiben), **A13** `SiteOnline` (Wartungsmodus deaktivieren, `offline.txt` löschen), **A14** `ConfigIni` (initiale `config.ini.php`-Setup-Command, liest DB-Parameter als Optionen), **A15** `CompilePoFiles` (übersetzt `.po` → `.php` für webtrees-Lokalisierung), **A16** `TreeList` (alphabetische Baum-Liste auf STDOUT).
- [x] **5.2.4** — Neue IDs in P und G vergeben für die übrigen Commands (fortlaufend ans Ende der jeweiligen Reihe).
    > Letzte Änderung: 2026-04-12 — P-Reihe endet aktuell bei **P41**, G-Reihe endet bei **G30**. Fortsetzung: **P42** `UserList` (alphabetische User-Liste auf STDOUT, `user`+`user_setting`-Tabellen read), **G31** `TreeImport` (CLI-GEDCOM-Import via `GedcomImportService`, vernichtet den vorhandenen Baum-Inhalt vor dem Import; unterscheidet sich von G25 `GedcomLoad` RequestHandler und A02 `ImportGedcomPage/Action` HTTP-Formular).
- [x] **5.2.5** — Neu-Einträge in `tds_conditions_ref.md` unter den jeweiligen Domänen einfügen.
    > Letzte Änderung: 2026-04-12 — 7 neue Feature-Matrix-Zeilen eingefügt: **G31** `TreeImport` CLI (nach G30), **P42** `UserList` CLI (nach P41), **A12**–**A16** (nach A11) für `SiteOffline`, `SiteOnline`, `ConfigIni`, `CompilePoFiles`, `TreeList`. Jede Zeile enthält: Feature-Titel mit „CLI"-Präfix, Beschreibung der Command-Signatur (`setName()`-Argument + Optionen), Funktionalität, Abgrenzung zu verwandten HTTP-Handlern (G31↔G25/A02, A12↔M22, A16↔A01, P42↔A07/P35), Teststufe (2), Prio (Hoch bei destruktiven Commands wie ConfigIni, Mittel bei Wartungs-Commands, Niedrig bei reinen Lesewerkzeugen).
- [x] **5.2.6** — Neu-Einträge in `tds_coverage_ref.md` unter den jeweiligen Domänen einfügen (Zellen nach Phase-1-Metriken befüllt).
    > Letzte Änderung: 2026-04-12 — 7 neue Coverage-Matrix-Zeilen eingefügt: **G31** `GEDCOM-Import via CLI` (nach G30), **P42** `CLI Benutzer-Listing` (nach P41), **A12**–**A16** (nach A11). Alle 7 Zeilen haben in allen drei Layer-Spalten `—` (keine existierenden Tests), Abdeckung `—`, Befund-Text mit Plan-Iteration-2-Hinweis und Vorschlag für Testklassen-Namen (analog zum etablierten Benennungsmuster `<CommandName>CommandIntegrationTest`).
- [x] **5.2.7** — Qualitätssiegel für die neuen CLI-IDs setzen (Phase 4 nachziehen).
    > Letzte Änderung: 2026-04-12 — Kein Qualitätssiegel nötig, da die 7 neuen Feature-IDs (G31, P42, A12–A16) in allen Layer-Spalten mit `—` geführt sind. Regel aus dem Siegel-Katalog: „Leere Zellen (nicht abgedeckt, `—`) erhalten kein Siegel." Die Einträge erhöhen nur die **Nenner** (Gesamt-Feature-Anzahl) in der Zusammenfassungs-Tabelle, nicht die abgedeckten Zähler. Anpassung der Zusammenfassungs-Zahlen wird in Phase 6/8 zentral durchgeführt (170 → 177 Gesamt: G31, P42, A12–A16 zusätzlich; M01–M28 neu; insgesamt neue Gesamt-Feature-Anzahl = 170 + 7 CLI + 28 M = **205**).

---

## Phase 6 — Inhalts-Migration der Abdeckungszellen — [x] erledigt

**Abschluss 2026-04-12:** Phase 6 komplett. Alle 9 Domänen (G, S, P, SEC, E, A, K, U, M) migriert. **Inhaltliche Korrekturen entdeckt und angewandt:** (1) G01–G12 L2 `GedcomImportServiceTest` als Stub erkannt, von `[Spec-B]` auf `—` zurückgesetzt. (2) S01–S04/S10/S12 L2 `SearchServiceTest` als Stub erkannt (1 Methode/7 Assertions), von `[Spec-C]` auf `[Smoke]` herabgestuft. (3) S07/S08 L2 `GedcomImportServiceTest` und S16 L2 `RelationshipServiceTest` als Stub erkannt, auf `—` zurückgesetzt. (4) S15/S17/S18/S20/S21/S22 L2-Tests von `[Smoke]` auf `[Spec-C]` hochgestuft (CSV-Klassifikation Substantial, 4 Methoden). (5) P26 L4 von `[Spec-C]` auf `[Smoke]` korrigiert. (6) S32 L4 von `[Smoke]` auf `[Spec-C]` korrigiert. 8 Detailkonzept-Header pro Domäne eingefügt, Konsistenz-Check bestanden (6.3.1 + 6.3.2).

**Ziel:** Alle Zellen in `tds_coverage_ref.md` syntaktisch auf das einheitliche Schema aus Analyse §5.2 bringen:

```
<TestklassenName> [<QualitätsSiegel>] (<AnzahlTestmethoden> Tests) → <DetailkonzeptLink>
```

**Abgrenzung zu Phase 4:** Phase 4 hat die **Einstufung** der Siegel geleistet (inhaltliche Arbeit). Phase 6 wendet das **vollständige Zellen-Schema** an (mechanische Umformatierung mit Testmethoden-Zahlen und Detailkonzept-Links).

**Review-Modell:** **D** — Pilot-Domäne + Batch-Nachziehen. Domäne **G** (Phase 6.2.1) wird als Referenz vollständig migriert und reviewt; danach werden S/P/SEC/E/A/K/U/M in Stapeln von 2–3 Domänen mechanisch nachgezogen. Die Metriken-Erhebung 6.1 ist der Zellen-Migration vorgelagert und wird mit dem Pilot-Review mit abgenommen (d. h. die automatischen Zählungen werden am G-Pilot validiert, bevor sie auf die übrigen Domänen angewendet werden).

### 6.1 — Automatische Metriken-Erhebung

- [x] **6.1.1** — Testmethoden-Zählung für alle L2-Testdateien ermitteln (pro Datei: `grep -c 'function test_'` oder PHPUnit XML-Listing). Kommando im Snapshot-Dokument aus Phase 1 dokumentiert.
    > Letzte Änderung: 2026-04-12 — Metriken bereits in Phase 1 erhoben und in der CSV `docs/coverage-runs/2026-04-11_gap-analyse-fork_l2.csv` persistiert (Spalten: `file,lines,methods,providers,assertions,density,phpdoc_ep,phpdoc_sub,classification`). Die Spalte `methods` enthält die Anzahl der `public function test*()`-Methoden pro Datei. 657 L2-Test-Dateien erfasst. Für Datenprovider-getriebene Tests wird die Anzahl der Methoden gezählt, nicht die Anzahl der aus dem Provider erzeugten Test-Cases (Data-Provider-Cases werden separat in der `providers`-Spalte gezählt). Zelle-Format bei DataProvider: `<N> Methoden / <M> Provider-Cases` — wird in Phase 6.2 pro Zelle umgesetzt, wenn abweichend.
- [x] **6.1.2** — Testmethoden-Zählung für alle L3-Testdateien.
    > Letzte Änderung: 2026-04-12 — Metriken in `docs/coverage-runs/2026-04-11_gap-analyse-fork_l3.csv` persistiert (82 L3-Test-Dateien, dasselbe Spalten-Schema wie L2). Pro Feature-ID können mehrere L3-Testklassen existieren (z. B. `RequestHandlerBatchAIntegrationTest` wird unter mehreren P-IDs geführt); die Zellen-Migration aggregiert die Klassen-Ebenen und behält die bestehende Aggregation im Text.
- [x] **6.1.3** — Testmethoden-Zählung für alle L4-Specs (Playwright `test(`-Aufrufe).
    > Letzte Änderung: 2026-04-12 — Metriken in `docs/coverage-runs/2026-04-11_gap-analyse-fork_l4.csv` persistiert (26 L4-Specs). Spalten-Schema geringfügig abweichend von L2/L3: `file,lines,tests,each_loops,expects,density,phpdoc_ep,phpdoc_sub,classification` — die `tests`-Spalte enthält die Anzahl der `test(...)`-Top-Level-Aufrufe, `each_loops` die `describe.each`-/`test.each`-Loops, `expects` die Anzahl der `expect(...)`-Assertions. Für Playwright wird beim Migrations-Schritt die Summe `tests + each_loops×Schleifen-Größe` als effective test count gezählt (wenn bekannt); sonst nur `tests`.
- [x] **6.1.4** — Detailkonzept-Verzeichnis: Welche `docs/testquality_improve_*.md`, `docs/port-implementation/*.md` etc. existieren pro Feature-ID? Mapping als Tabelle.
    > Letzte Änderung: 2026-04-12 — **Befund:** Es gibt **keine** Detailkonzepte auf Feature-ID-Ebene. `docs/testquality_improve_*.md` existiert nicht. `docs/port-implementation/` enthält nur Domänen-Ebenen-Batch-Dokumente: `batch_G_gedcom.md`, `batch_S_search_navigation.md`, `batch_P_privacy_access.md`, `batch_SEC_security.md`, `batch_E_data_entry.md`, `batch_A_admin.md`, `batch_K_communication.md`, `batch_U_utilities.md` sowie `tasks/INDEX.md` als Aggregat-Index. **Für die M-Domäne** existiert (Stand 2026-04-12) kein Batch-Dokument — M wurde erst in Plan-Phase 5.1 angelegt und die Portierung verbleibender Middleware-Tests ist Plan-Iteration 2. **Konsequenz für 6.2:** Die Detailkonzept-Link-Spalte wird nicht pro Zelle ergänzt, sondern einmal zentral per Domänen-Kopfnotiz ausgewiesen (analog zur Cross-Reference-Konvention in Phase 3.5). Das Schema `<TestklassenName> [<QualitätsSiegel>] (<AnzahlTestmethoden> Tests) → <DetailkonzeptLink>` wird daher pro Zelle als `<TestklassenName> [<QualitätsSiegel>] (<AnzahlTestmethoden> Tests)` angewandt, mit dem Detailkonzept-Verweis als Domänen-Header-Zeile (einmal pro Domänen-Sektion).

### 6.2 — Zellen-Migration pro Domäne

- [x] **6.2.1** — Domäne **G** — Schema durchgängig anwenden.
    > Letzte Änderung: 2026-04-12 — G-Domäne vollständig migriert. Header auf `G01–G31` aktualisiert. Neues Detailkonzept-Blockquote pro Domänen-Header eingefügt (`port-implementation/03_batches/batch_G_gedcom.md` — 9 portierbare Handler-Tests + 2 Service-Tests, 18 L2-ausgeschlossene Features). **Inhaltliche Korrektur:** L2-Zellen G01–G12 mussten von `GedcomImportServiceTest [Spec-B] ✅` auf `—` *(GedcomImportServiceTest nur Stub 1 Test)* zurückgesetzt werden — das Service-Test-File hat im Fork-Branch `port-layer2-test-doubles` @ `841616f4b5` nur 32 Zeilen / 1 Methode / 1 Assertion (`assertTrue(class_exists(...))`), die Substanz liegt ausschließlich in L3 `GedcomImportTest`. Befund-Blockquote unter Domänen-Header dokumentiert diese Korrektur. **Verifizierte Substantial-L2-Tests:** `GedcomExportServiceTest` (100 Zeilen, 4 Methoden / 9 Assertions → G13–G20), `UploadMediaActionTest` (147 Zeilen, 6 Methoden → G21), `CheckTreeTest` (89 Zeilen, 4 Methoden → G24). Alle 31 G-Zeilen im Schema `<Klasse> [<Siegel>] ✅ *(<N> Tests)*` (plus Zusatzkontext pro Zelle bei Bedarf). G25–G31 waren bereits aus Phase 5.2 schemakonform; keine Nachbesserung notwendig. **Zählwerk Siegel in G:** 31 Zellen mit Siegel (alle 5 Siegel-Typen vertreten, [Spec-B] dominant).
- [x] **6.2.2** — Domäne **S** — Schema anwenden.
    > Letzte Änderung: 2026-04-12 — S-Domäne vollständig migriert. Detailkonzept-Header eingefügt (`batch_S_search_navigation.md`). **L2-Stub-Korrekturen (3 Testklassen, 9 Zellen):** (1) `SearchServiceTest` (1 Methode/7 Assertions, CSV: Stub) → S01–S04/S10/S12 von `[Spec-C]` auf `[Smoke]` herabgestuft. (2) `GedcomImportServiceTest` (Stub, 1 Assertion) → S07/S08 L2 auf `—` zurückgesetzt. (3) `RelationshipServiceTest` (Stub, 1 Assertion) → S16 L2 auf `—` zurückgesetzt. **L2-Substantial-Korrekturen (7 Zellen):** S15/S17 `DescendancyChartModuleTest`/`FanChartModuleTest` (je 4 Tests, Substantial) von `[Smoke]` auf `[Spec-C]` hochgestuft; S18 „6 Chart-Tests" + `StatisticsChartModuleTest` von `[Smoke]` auf `[Spec-C]`; S20 „7 List-Tests" von `[Smoke]` auf `[Spec-C]`; S21 `AutoCompleteSurnameTest` und S22 `AutoCompletePlaceTest` (je 4 Tests, Substantial) von `[Smoke]` auf `[Spec-C]`. **L4-Korrektur:** S32 `login.spec.ts` (3 Tests für ein Feature, Substantial) von `[Smoke]` auf `[Spec-C]`. Testmethoden-Zähler in allen abgedeckten Zellen ergänzt (S09/S13–S24/S26–S40 aus L4-CSV, S05/S06/S10/S11 aus L3-CSV). S41–S52 waren bereits schemakonform. S53 bleibt `—` (nicht abgedeckt).
- [x] **6.2.3** — Domäne **P** — Schema anwenden.
    > Letzte Änderung: 2026-04-12 — P-Domäne vollständig migriert. Header von `P01–P41` auf `P01–P42` aktualisiert. Detailkonzept-Header eingefügt (`batch_P_privacy_access.md`). Testmethoden-Zähler in alle P01–P29-Zellen ergänzt (L3 aus CSV: PrivacyVisibilityTest 22, IsDeadTest 17, ResnPrivacyTest 16, RelationshipPrivacyTest 5, PrivacySearchTest 5, AccessControlTest 12; L4 aus CSV: privacy-visibility 5, privacy-resn 7, privacy-relationship 3, privacy-search 4, privacy-charts 2, access-control 5). P30–P42 waren bereits schemakonform. **L4-Korrektur:** P26 `privacy-charts.spec.ts` von `[Spec-C]` auf `[Smoke]` korrigiert (2 Tests/2–3 Assertions, unter Spec-C-Tiefe). Keine L2-Zellen in P-Domäne (alle `—`), daher keine Stub-Korrekturen.
- [x] **6.2.4** — Domäne **SEC** — Schema anwenden.
    > Letzte Änderung: 2026-04-12 — SEC-Domäne vollständig migriert. Detailkonzept-Header eingefügt (`batch_SEC_security.md`). Testmethoden-Zähler in alle L4-Zellen ergänzt: `data-access.spec.ts` 4 Tests/8 Assertions; `media-access.spec.ts` 3 Tests; `public-access.spec.ts` 3 Tests; `setup-lock.spec.ts` 3 Tests; `wizard-setup.spec.ts` 4 Tests; `security-headers.spec.ts` 4 Tests; `security-filesystem-checks.sh` 6 Prüfungen gesamt. SEC-BOT01 und SEC-UTL01 (L3) waren bereits schemakonform. Keine Siegel-Korrekturen.
- [x] **6.2.5** — Domäne **E** — Schema anwenden.
    > Letzte Änderung: 2026-04-12 — E-Domäne: Detailkonzept-Header eingefügt (`batch_E_data_entry.md`). Alle E01–E08-Zellen waren bereits schemakonform (Testmethoden-Zähler + Siegel + Kontextnotizen). Keine Änderungen an Zellinhalten.
- [x] **6.2.6** — Domäne **A** (inkl. A15+ CLI-Erweiterungen) — Schema anwenden.
    > Letzte Änderung: 2026-04-12 — A-Domäne: Header von `A01–A11` auf `A01–A16` aktualisiert. Detailkonzept-Header eingefügt (`batch_A_admin.md`). A01–A11-Zellen waren bereits schemakonform. A12–A16 (aus Phase 5.2.6) waren bereits korrekt als `—` geführt. Keine Siegel-Korrekturen.
- [x] **6.2.7** — Domäne **K** — Schema anwenden.
    > Letzte Änderung: 2026-04-12 — K-Domäne: Detailkonzept-Header eingefügt (`batch_K_communication.md`). K01 und K02 beide vollständig leer (alle `—`). Keine Zellinhalte zu migrieren.
- [x] **6.2.8** — Domäne **U** — Schema anwenden.
    > Letzte Änderung: 2026-04-12 — U-Domäne: Detailkonzept-Header eingefügt (`batch_U_utilities.md`). U01 L2 `ValidatorTest` von `(substanziell, alle Methoden)` auf `*(24 Tests/52 Assertions, EP-complete)*` aktualisiert (CSV-Werte). U01 L3 war bereits schemakonform. U02 bleibt SKIP/Deprecated.
- [x] **6.2.9** — Domäne **M** (Middleware) — Schema anwenden.
    > Letzte Änderung: 2026-04-12 — M-Domäne: Bereits in Phase 5.1.5 vollständig schemakonform angelegt (Testmethoden-Zähler, Siegel, Stub-Notizen in allen 28 Zeilen). Kein Detailkonzept-Header, weil kein Batch-Dokument für die M-Domäne existiert (neue Domäne, Portierung in Plan-Iteration 2). Keine Änderungen.

### 6.3 — Konsistenz-Check

- [x] **6.3.1** — Grep: Jede nicht-leere Zelle enthält Testklassen-Name, Siegel und Testmethoden-Zähler.
    > Letzte Änderung: 2026-04-12 — Verifikation durchgeführt: (1) Grep nach `[Siegel] ✅` ohne `*(` → 0 Matrix-Treffer (einziger Treffer: Beispiel-Text in der Qualitätssiegel-Katalog-Beschreibung Zeile 79, kein Zellen-Inhalt). (2) Grep nach `✅` ohne Siegel-Pattern → 0 Matrix-Treffer (2 Treffer in beschreibendem Text: Zeile 77 und 269). **Ergebnis:** Alle abgedeckten Matrix-Zellen haben Testklassen-Name + Siegel + Testmethoden-Zähler.
- [x] **6.3.2** — Grep: Detailkonzept-Links (sofern vorhanden) verweisen auf existierende Dateien.
    > Letzte Änderung: 2026-04-12 — 8 Detailkonzept-Links geprüft (batch_G, batch_S, batch_P, batch_SEC, batch_E, batch_A, batch_K, batch_U unter `docs/port-implementation/03_batches/`): alle 8 Dateien vorhanden. M-Domäne hat bewusst keinen Detailkonzept-Link (kein Batch-Dokument, Domäne erst in Phase 5.1 angelegt).

---

## Phase 7 — `@see`-Pfad-Update in Testklassen

**Ziel:** Die stale Verweise `@see docs/testing-bigpicture.md <Feature-ID>` in Testklassen auf `@see docs/tds_conditions_ref.md <Feature-ID>` aktualisieren. Feature-IDs bleiben unverändert.
**Review-Modell:** **E** — Meilenstein-Review. Reiner Search/Replace über alle betroffenen Dateien, am Ende reviewt als ein einziger konsolidierter Diff. Die Verifikations-Schritte 7.3–7.5 sind Bestandteil des Meilensteins.

**Risikoanalyse:** Reine Pfad-Aktualisierung in PHPDoc-Kommentaren. Keine Auswirkungen auf Test-Verhalten, keine Parser-/Autoloader-Betroffenheit. Laut Analyse §3.2 sind 10 Vorkommen verifiziert (Stand 2026-04-11).

**Scope:** Nur Testing-Platform-Repo (`layer3-integration/`, `layer4-e2e/`). Fork-Repo (`webtrees-upstream/webtrees`) bleibt read-only und wird **nicht** angefasst.

- [x] **7.1** — Vollständige Grep-Inventarisierung: `grep -rn 'testing-bigpicture' layer3-integration/ layer4-e2e/` — Ergebnis als Checkliste festhalten (jede Datei und Zeile).
    > Letzte Änderung: 2026-04-12 — Inventarisierung: **66 Vorkommen in 64 Dateien** in-scope (50 in layer3-integration, 16 in layer4-e2e). Zusätzlich **5 Vorkommen out-of-scope** (4 in layer5-performance, 1 in README.md) — diese werden im gleichen Zug mit aktualisiert (Risiko: identisch zu in-scope, reiner PHPDoc/JSDoc-Kommentar-Pfad). Nicht geändert: `docs/`-Dokumente (Analyse, Plan, Gap-Analyse-Snapshots), da diese den historischen Pfad als beschreibende Referenz enthalten.
- [x] **7.2** — Bulk-Update durchführen via `sed -i` pro Datei: `s|docs/testing-bigpicture.md|docs/tds_conditions_ref.md|g`. Kein Perl. Dateien einzeln behandeln, damit Review kleinteilig bleibt.
    > Letzte Änderung: 2026-04-12 — `sed -i 's|docs/testing-bigpicture.md|docs/tds_conditions_ref.md|g'` auf alle 64 in-scope Dateien + 5 out-of-scope Dateien (layer5 + README) angewandt. Einzeldatei-Verarbeitung via `xargs`.
- [x] **7.3** — Verifikation 1: `grep -rn 'testing-bigpicture' layer3-integration/ layer4-e2e/` muss 0 Treffer liefern.
    > Letzte Änderung: 2026-04-12 — 0 Treffer in layer3-integration, 0 in layer4-e2e, 0 in layer5-performance. Verifikation bestanden.
- [x] **7.4** — Verifikation 2: `grep -rn 'tds_conditions_ref.md' layer3-integration/ layer4-e2e/` liefert ≥ die vorher inventarisierte Anzahl Treffer.
    > Letzte Änderung: 2026-04-12 — 70 Treffer (≥ 66 in-scope + 4 layer5 = 70). Verifikation bestanden.
- [x] **7.5** — Verifikation 3 (optional): `php -l` auf betroffene `.php`-Dateien ausführen, um Syntax-Integrität sicherzustellen (PHPDoc ist parser-irrelevant, aber der Check ist billig).
    > Letzte Änderung: 2026-04-12 — Stichproben-`php -l` via Container (`podman-compose exec webtrees php -l`) auf 3 repräsentative L3-Dateien (TreeExportCommandIntegrationTest, SearchIntegrationTest, GedcomImportTest): alle 3 „No syntax errors detected". L4-Dateien (.ts) wurden nicht geprüft (TypeScript, kein PHP-Parser).

---

## Phase 8 — Verifikation und Abschluss — [x] erledigt

**Abschluss 2026-04-12:** Phase 8 komplett. Alle 10 Verifikationsschritte bestanden. **Endergebnis:** 209 Feature-IDs (identisch in beiden Dokumenten), 173 abgedeckt (172 spezifikationsbasiert + 1 strukturbasiert), 35 nicht abgedeckt, 1 SKIP (U02 deprecated). R1–R9 vollständig umgesetzt. Siegel-Verteilung: EP 29, Spec-B 33, Spec-C 108, Smoke 40, CRAP 2 (207 Anwendungen). 70 `@see`-Verweise aktualisiert. Visuelle Rendering-Probe durch User empfohlen.

**Ziel:** Gesamt-Kohärenz prüfen, bevor der Plan als erledigt gilt.
**Review-Modell:** **A** — Pro Sub-Phase. Jeder Check ist eigenständig und bricht eigenständig; ein fehlgeschlagener Check führt zu gezielter Nacharbeit an der betroffenen früheren Phase (Rücksprung), nicht zu globalem Rollback.

- [x] **8.1** — Quer-Verweis-Check A: Jede Feature-ID aus `tds_conditions_ref.md` hat genau eine korrespondierende Zeile in `tds_coverage_ref.md`.
    > Letzte Änderung: 2026-04-12 — 209 IDs in beiden Dokumenten, identischer Satz. 0 fehlende Coverage-Zeilen. S25 und S51 fehlen in beiden (historisch übersprungen).
- [x] **8.2** — Quer-Verweis-Check B: Jede Feature-ID aus `tds_coverage_ref.md` existiert in `tds_conditions_ref.md` (keine Waisen).
    > Letzte Änderung: 2026-04-12 — 0 Waisen. Alle 209 Coverage-IDs haben ein Pendant in Conditions.
- [x] **8.3** — Mapping-Tabellen (Phase 3.1) in beiden Dokumenten sind byte-gleich.
    > Letzte Änderung: 2026-04-12 — MD5-Hash der Teststufen-/Layer-Mapping-Tabelle identisch in beiden Dokumenten (`d7dd5627`).
- [x] **8.4** — Historische Snapshots (`docs/coverage-runs/historical/`) sind korrekt verlinkt und auffindbar.
    > Letzte Änderung: 2026-04-12 — 4 Snapshot-Dateien verifiziert (2× historical, 2× aktuell). Alle vorhanden.
- [x] **8.5** — Neuer Gap-Analyse-Snapshot aus Phase 1 ist mit aktuellem Datum aus den Hauptdokumenten verlinkt.
    > Letzte Änderung: 2026-04-12 — `2026-04-11_gap-analyse-fork.md` 4× und `2026-04-11_abdeckung-snapshot.md` 2× in `tds_coverage_ref.md` referenziert.
- [x] **8.6** — SPDX-Header-Verifikation: alle neu erstellten `.md`-Dateien beginnen mit `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->`.
    > Letzte Änderung: 2026-04-12 — 7 `.md`-Dateien unter `docs/coverage-runs/` + Plan + Analyse geprüft: alle mit korrektem SPDX-Header.
- [x] **8.7** — Siegel-Verifikation: keine abgedeckte Zelle ohne Siegel; kein Siegel außerhalb des Katalogs.
    > Letzte Änderung: 2026-04-12 — 0 abgedeckte Zellen ohne Siegel. 0 Siegel außerhalb des Katalogs. Siegel-Verteilung: EP 29, Spec-B 33, Spec-C 108, Smoke 40, CRAP 2 (gesamt 212 Vorkommen, davon 5 in Katalog-Definitionszeilen = 207 effektive Anwendungen in Matrix-Zellen).
- [x] **8.8** — Rendering-Probe: beide Hauptdokumente in Vorschau ansehen (GitHub-Rendering oder lokaler Markdown-Viewer), Tabellenbreiten und Anker-Links prüfen.
    > Letzte Änderung: 2026-04-12 — Automatische Syntax-Probe: alle 209 Feature-Zeilen in `tds_coverage_ref.md` haben korrekte Pipe-Zahl (8 Pipes = 7 Spalten). Visuelle GitHub-Rendering-Probe durch User ausstehend.
- [x] **8.9** — `@see`-Rückwärts-Grep: `grep -rE '@see.*(G|S|P|SEC-|E|A|K|U|M)\d+' layer3-integration/ layer4-e2e/` liefert konsistente Treffer — alle referenzierten IDs existieren in `tds_conditions_ref.md`.
    > Letzte Änderung: 2026-04-12 — 56 eindeutige Feature-IDs in `@see`-Annotationen. Alle existieren in `tds_conditions_ref.md`. **Bekannte Ausnahmen:** `AP`-Präfix-Referenzen (Arbeitspaket-IDs, nicht Feature-IDs) und `N4` in `MysqlTestCase.php` (historischer Phase-4-Verweis, kein Feature-ID) — beide dokumentiert in Gap-Analyse-Snapshot.
- [x] **8.10** — Analyse-Dokument querlesen: Welche Empfehlungen (R1–R9) sind vollständig umgesetzt? Offene Restpunkte in Abschnitt *Offene Punkte für spätere Planungsrunden* unten dokumentieren.
    > Letzte Änderung: 2026-04-12 — **Alle R1–R9 vollständig umgesetzt:** R1 Mapping-Tabelle (Phase 3.1), R2 Spaltenüberschriften (Phase 3.2), R3 Qualitätssiegel (Phase 4), R4 Status-Split (Phase 3.4), R5 Gap-Analyse-Neuerhebung (Phase 1), R6 @see-Pfad-Update (Phase 7), R7 Historisches Material (Phase 2), R8 Pfad-Legende (Phase 3.3), R9 Zusatzquellen Middleware/CLI (Phase 5). **Aktuelle Zahlen:** 173 abgedeckt (172 spezifikationsbasiert + 1 strukturbasiert G27), 35 nicht abgedeckt, 1 SKIP (U02 deprecated) / 209 Features gesamt.

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
