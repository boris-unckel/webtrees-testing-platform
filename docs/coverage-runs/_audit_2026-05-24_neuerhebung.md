<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Audit-Log — Coverage-Runs-Neuerhebung 2026-05-24

**Charakter:** Append-only chronologische Liste von Aktionen und Ergebnissen
während der Erhebung. Die Erhebung wurde in einer Session abgeschlossen; der
ursprünglich angelegte Plan (`_plan_2026-05-24_neuerhebung.md`) und der
Wiedereinstiegs-Prompt (`_resume_2026-05-24_neuerhebung.md`) sind nach
Abschluss entfernt worden. Dieses Audit ist die persistente Spur des
Vorgehens (Phasen 1–6 mit Aktion und Ergebnis pro Schritt).

Schema pro Eintrag:

```
### [YYYY-MM-DDTHH:MM±ZZZZ] [PHASE.X] AUDIT-ID
**Aktion:** <konkreter Schritt, mit Befehl falls maschinell>
**Ergebnis:** <faktischer Befund — Exit-Code, Diff-Zähler, gelesene/geschriebene Datei, Zahl, Fehler>
**Beobachtung:** <optional, kurze Anmerkung wenn etwas Unerwartetes auftrat>
```

Audit-IDs sind aufsteigend `A001` … `A034`. Phasen-Zuordnung im Audit-Header
jedes Eintrags (Format `[PHASE.SCHRITT]`).

**Phasen-Übersicht (zur Orientierung):**

| Phase | Inhalt | Audit-IDs |
|---|---|---|
| 1 | Archivierung der April-Reports nach `historical/`, Refs umgebogen | A001–A009 |
| 2 | Erhebungs-Skripte `scripts/coverage/` geschrieben, shellcheck, Probelauf | A010–A014 |
| 3 | Inventar-CSVs (L2/L3/L4) erzeugt, Aggregate, Feature-ID-Abdeckung | A015–A019 |
| 4 | Coverage-Aggregate aus L2/L3-Clover-XML | A020–A024 |
| 5 | Drei neue Reports geschrieben (`2026-05-24_*.md`) | A025–A028 |
| 6 | Outbound-Refs in `tds_*`-Docs umgebogen, Memory-Update, Final-Check | A029–A034 |

Phase 7 (Commit) wird durch den Auftraggeber initiiert; dieses Audit
dokumentiert nur Phasen 1–6.

---

<!-- Einträge folgen nachfolgend in chronologischer Reihenfolge. -->

### [2026-05-24T20:38+0200] [1.1] A001
**Aktion:** `git mv docs/coverage-runs/2026-04-11_abdeckung-snapshot.md docs/coverage-runs/historical/`
**Ergebnis:** Exit 0. `git status` zeigt `R  …2026-04-11_abdeckung-snapshot.md -> …historical/2026-04-11_abdeckung-snapshot.md` (Rename erkannt).

### [2026-05-24T20:38+0200] [1.2] A002
**Aktion:** `git mv docs/coverage-runs/2026-04-11_gap-analyse-fork.md docs/coverage-runs/historical/`
**Ergebnis:** Exit 0. Rename in `git status` registriert. Inbound-Refs noch nicht umgebogen (siehe Phase 1.5–1.8).

### [2026-05-24T20:38+0200] [1.3] A003
**Aktion:** `git mv docs/coverage-runs/2026-04-11_gap-analyse-fork_l2.csv|_l3.csv|_l4.csv docs/coverage-runs/historical/` (drei aufeinanderfolgende Aufrufe; Glob-Expansion via einzelne Pfade, da `git mv` keine Brace-Expansion auf Shell-Ebene nutzt).
**Ergebnis:** Exit 0 für alle drei. Renames in `git status` registriert.

### [2026-05-24T20:38+0200] [1.4] A004
**Aktion:** `git mv docs/coverage-runs/2026-04-11_layer2-vs-layer3.md docs/coverage-runs/historical/`
**Ergebnis:** Exit 0. Rename in `git status` registriert.

### [2026-05-24T20:42+0200] [1.5] A005
**Aktion:** `sed -i 's|coverage-runs/2026-04-11_|coverage-runs/historical/2026-04-11_|g' docs/tds_coverage_ref.md`
**Ergebnis:** Exit 0. Z. 80 / 140 / 365 zeigen jetzt auf `coverage-runs/historical/2026-04-11_*`.

### [2026-05-24T20:42+0200] [1.6] A006
**Aktion:** `sed -i 's|coverage-runs/2026-04-11_|coverage-runs/historical/2026-04-11_|g' docs/tds_conditions_ref.md`
**Ergebnis:** Exit 0. Z. 93 / 282 umgebogen.

### [2026-05-24T20:42+0200] [1.7] A007
**Aktion:** `sed -i 's|\.\./2026-04-11_|2026-04-11_|g' docs/coverage-runs/historical/2026-03-26_gap-analyse.md`
**Ergebnis:** Exit 0. Z. 10: `../2026-04-11_gap-analyse-fork.md` → `2026-04-11_gap-analyse-fork.md` (Geschwister-Pfad).
**Beobachtung:** Z. 9 enthält Datums-Text `(Commit ..., 2026-04-11)` — kein Pfad, bleibt unverändert.

### [2026-05-24T20:42+0200] [1.8] A008
**Aktion:** Dito für `docs/coverage-runs/historical/2026-03-27_e2e-gap.md` (im selben sed-Aufruf wie A007).
**Ergebnis:** Exit 0. Z. 9 umgebogen. Z. 7 enthält wieder nur Text-Datum, bleibt unverändert.

### [2026-05-24T20:43+0200] [1.9] A009
**Aktion:** `grep -rn "coverage-runs/2026-04-11" docs/ | grep -v 'historical/'` und `grep -rn '\.\./2026-04-11' docs/coverage-runs/historical/`.
**Ergebnis:** Keine Treffer außerhalb `historical/`. Keine `../`-Refs aus `historical/` mehr offen. Phase 1 abgeschlossen.

### [2026-05-24T20:46+0200] [2.1] A010
**Aktion:** `scripts/coverage/gap_inventory_php.sh` geschrieben — bash + awk, V2-Hybrid-Klassifikation. SPDX-Header AGPL-3.0-or-later, Google Shell Style Guide.
**Ergebnis:** Datei angelegt (4.3 KB).
**Beobachtung:** Erster Versuch hatte Bash-Variable `sub`, die mit awk-Builtin kollidiert (Syntax-Error). Umbenannt auf `psub`. Beim breitseitigen `replace_all` `sub→psub` wurden mehrere `gsub`/`@substantial`/Header-Strings kaputtgeschrieben — gezielte Rück-Edits in A010 nachgezogen.

### [2026-05-24T20:46+0200] [2.2] A011
**Aktion:** `scripts/coverage/gap_inventory_ts.sh` geschrieben — bash + awk, V2-Hybrid für Playwright-Specs.
**Ergebnis:** Datei angelegt (3.8 KB). Variablen-Name `psub` von Anfang an statt `sub` gewählt.

### [2026-05-24T20:46+0200] [2.3] A012
**Aktion:** `scripts/coverage/clover_aggregate.sh` geschrieben mit drei Subkommandos: `totals`, `by-prefix`, `files-csv`.
**Ergebnis:** Datei angelegt (4.7 KB).
**Beobachtung:** Erste `totals`-Implementierung war awk-basiert und lieferte leere Ausgabe — Projekt-Wurzel-`<metrics>` steht in Clover am ENDE von `<project>`, nicht am Anfang. Auf `xmllint --xpath` umgestellt; funktioniert.

### [2026-05-24T20:47+0200] [2.4] A013
**Aktion:** `shellcheck scripts/coverage/*.sh`.
**Ergebnis:** Exit 0. Drei SC2155-Warnings (readonly + command-substitution) initial gemeldet, durch getrennte Zuweisung behoben.

### [2026-05-24T20:48+0200] [2.5] A014
**Aktion:** Probelaeufe gegen `layer3-integration/tests`, `layer4-e2e/tests`, `docs/test-runs/2026-05-24T15-54_run/layer{2,3}/coverage.xml`.
**Ergebnis:**
- `gap_inventory_php.sh layer3-integration/tests` -> 156 Datenzeilen. Bit-identische Werte fuer unveraenderte April-Dateien (Spot-Check `AccessControlTest.php`, `BadBotBlockerIntegrationTest.php`).
- `gap_inventory_ts.sh layer4-e2e/tests` -> 55 Datenzeilen. April-Spot-Check `access-control.spec.ts` 86->88 Zeilen (Datei gewachsen), restliche Spalten identisch.
- `clover_aggregate.sh totals` L2 -> statements=44067, coveredstatements=13203 (29.96 %), methods=4434, coveredmethods=1198 (27.02 %), elements=48501, coveredelements=14401 (29.69 %) — matchen Snapshot-README.
- `clover_aggregate.sh totals` L3 -> 49.65 % / 45.40 % / 49.26 % — matchen ebenfalls.
- `clover_aggregate.sh by-prefix /var/www/html/app/Http/` L2 -> files_matched=381, statements=9013, coveredstatements=1850.
- `clover_aggregate.sh files-csv` L2 -> 1365 Datenzeilen (matcht totals `files=1365`).
**Beobachtung:** April-Heuristik und heutige Heuristik liefern identische Klassen fuer unveraenderte Dateien — V2-Bitkompatibilitaet bestaetigt.

### [2026-05-24T20:52+0200] [3.1] A015
**Aktion:** `scripts/coverage/gap_inventory_php.sh upstream/webtrees/tests/app > docs/coverage-runs/2026-05-24_gap-analyse_l2.csv`
**Ergebnis:** 1232 Datenzeilen. Pfade relativ zu `tests/app/` (ohne `app/`-Prefix wie in April). Erste Zeilen: `AgeTest.php,160,11,0,44,4.00,0,0,EP-complete`, `AuthTest.php,31,1,0,1,1.00,0,0,Stub`, ...
**Beobachtung:** Pfad-Konvention weicht bewusst von April ab (April: `app/AgeTest.php` mit `tests/` als Base, heute `AgeTest.php` mit `tests/app/` als Base) — heutige L2-PHPUnit-Scope ist `tests/app/` (kein `tests/feature/`).

### [2026-05-24T20:52+0200] [3.2] A016
**Aktion:** `scripts/coverage/gap_inventory_php.sh layer3-integration/tests > docs/coverage-runs/2026-05-24_gap-analyse_l3.csv`
**Ergebnis:** 156 Datenzeilen (April: 82, +90 %).

### [2026-05-24T20:52+0200] [3.3] A017
**Aktion:** `scripts/coverage/gap_inventory_ts.sh layer4-e2e/tests > docs/coverage-runs/2026-05-24_gap-analyse_l4.csv`
**Ergebnis:** 55 Datenzeilen (April: 26, +112 %).

### [2026-05-24T20:55+0200] [3.4] A018
**Aktion:** Aggregat-AWK ueber alle drei CSV-Dateien.
**Ergebnis:**
- **L2:** 1232 files, 67170 lines, 1917 methods, 21 providers, 7943 assertions, density 4.14. Klassen: Stub 951 (77.2 %), Smoke 175 (14.2 %), Substantial 99 (8.0 %), EP-complete 7 (0.6 %). PHPDoc-Marker: 0 ep / 0 sub.
- **L3:** 156 files, 37978 lines, 1203 methods, 21 providers, 2125 assertions, density 1.77. Klassen: Stub 4 (2.6 %), Smoke 41 (26.3 %), Substantial 96 (61.5 %), EP-complete 15 (9.6 %). PHPDoc-Marker: 0 / 0.
- **L4:** 55 files, 2962 lines, 193 tests, 0 each_loops, 352 expects, density 1.82. Klassen: Stub 0, Smoke 22 (40.0 %), Substantial 33 (60.0 %), EP-complete 0.
**Beobachtung:** L2-Stub-Quote 77.2 % gegenueber Fork-Stand 55.1 % — ohne Fork-Branch-Aufwertung dominieren Boilerplate-Tests. L3- und L4-Qualitaet stabil bis leicht verbessert ggue. April (Density-Werte aehnlich, Klassen-Mix vergleichbar).

### [2026-05-24T20:56+0200] [3.5] A019
**Aktion:** `grep '@see' layer3-integration/tests/*.php layer4-e2e/tests/**/*.spec.ts` mit anschliessender ID-Extraktion `[GPSMEA][0-9]+`.
**Ergebnis:**
- **L3:** 156/156 Dateien mit `@see`-Annotation (100 %). Distinct Feature-IDs: 170. Verteilung: A 19, E 9, G 25, M 25, P 42, S 50.
- **L4:** 49/55 Dateien mit `@see` (89 %). Distinct Feature-IDs: 48. Verteilung: A 5, E 7, G 1, M 1, P 5, S 29.
- **L4 ohne `@see`:** `access-control.spec.ts`, `privacy-charts.spec.ts`, `privacy-relationship.spec.ts`, `privacy-resn.spec.ts`, `privacy-search.spec.ts`, `privacy-visibility.spec.ts` (alle Privacy-Bereich + access-control).

### [2026-05-24T21:00+0200] [4.1] A020
**Aktion:** `clover_aggregate.sh totals` fuer L2- und L3-Clover-XML.
**Ergebnis:** L2 statements=13203/44067 (29.96 %), methods=1198/4434 (27.02 %), elements=14401/48501 (29.69 %). L3 statements=21882/44072 (49.65 %), methods=2013/4434 (45.40 %), elements=23895/48506 (49.26 %). Werte stimmen mit Snapshot-README ueberein.

### [2026-05-24T21:01+0200] [4.2] A021
**Aktion:** `clover_aggregate.sh by-prefix` fuer 19 Top-Level-Verzeichnisse unter `/var/www/html/app/` und Awk-Filter ueber `files-csv` fuer das `app/`-Root.
**Ergebnis:** Datei-/Statement-Zahlen pro Bereich erhoben — z. B. `app/Http` (381 Dateien, 9013 stmt; L2 1850 cov / L3 6576 cov), `app/Module` (259 Dateien, 10531 stmt; L2 1294 / L3 3438), `app/Services` (37 Dateien, 5732 stmt; L2 786 / L3 3390), `app/Census` (197 Dateien, 2552 stmt; L2 2546 / L3 55), `app/Statistics` (1 Datei, 504 stmt; L2 0 / L3 0). App-Root: 50 Dateien, 6612 stmt; L2 2208 cov / L3 3243 cov.
**Beobachtung:** L3-Stmt-Zahlen weichen minimal von L2 ab (Http: 9013 vs 9018, +5; Schema: 625 vs 625, identisch), weil L2 und L3 leicht unterschiedliche Coverage-Filter erfassen koennen. Project-Total: 44067 vs 44072 (+5).

### [2026-05-24T21:02+0200] [4.3] A022
**Aktion:** Http-Untergruppen via `by-prefix /var/www/html/app/Http/<sub>/`. Module-Subgruppen via Pattern-Match auf `files-csv`-Ausgabe.
**Ergebnis:**
- **Http-Subgruppen:** RequestHandlers (335 Dts, 8065 stmt; L2 1395 / L3 5774), Middleware (34 Dts, 545 stmt; L2 81 / L3 404), Routes (2 Dts, 370 stmt; L2 369 / L3 370), Exceptions (8 Dts, 13 stmt; L2 0 / L3 9).
- **Module-Patterns:** Language (73 Dts, 1627 stmt; L2 0 / L3 339), Trait (22 Dts, 528 stmt; L2 93 / L3 197), Abstract (2 Dts, 402 stmt; L2 13 / L3 194), History/Royals (5 Dts, 301 stmt; L2 284 / L3 10), InteractiveTree-Subdir (1 Dts, 155 stmt; L2 0 / L3 134).
**Beobachtung:** Sub-`Module/Language/` und `Module/HistoryData/` aus April existieren heute nicht mehr als Verzeichnisse — die Files heissen heute `LanguageEnUs.php`, `BritishSocialHistory.php` etc. (flach in `Module/`). Pattern-Match liefert vergleichbare Aggregate.

### [2026-05-24T21:03+0200] [4.4] A023
**Aktion:** Awk-Join `clover_aggregate.sh files-csv` L2 vs L3 pro Datei; Sort nach `(L3_cov - L2_cov)` desc/asc.
**Ergebnis:**
- **Top-Gewinne (L3 deckt deutlich mehr als L2):** Gedcom.php (+636), Services/RelationshipService (+515), Services/IndividualFactsService (+464), Services/GedcomImportService (+408), Http/RequestHandlers/RenumberTreeAction (+361), Individual.php (+327), Module/RelationshipsChartModule (+249), Module/FanChartModule (+199), CustomTags/GedcomL (+197), Module/AbstractIndividualListModule (+174).
- **Top-Verluste (L2 deckt deutlich mehr als L3):** StatisticsData.php (-881), Module/StatisticsChartModule (-319), Elements/GovIdType (-277), Statistics.php (-261), Report/ReportParserGenerate (-252), Elements/TempleCode (-161), Module/CzechMonarchsAndPresidents (-99), Module/FrenchHistory (-94), Report/RightToLeftSupport (-93), Module/BritishPrimeMinisters (-79).
**Beobachtung:** Top-10-Listen weitgehend identisch zu April (gleiche Dateien, geringfuegige Zahl-Drift). Verlust-Profil ist dominiert von Statistics-Komplex und Modulen mit konstanten Datentabellen (Census-/History-Module), Gewinn-Profil von Services und Domain-Objekten.

### [2026-05-24T21:04+0200] [4.5] A024
**Aktion:** Blinde-Flecken-Filter: `L2_cov == 0 AND L3_cov == 0 AND stmt > 50`.
**Ergebnis:**
- `app/Statistics/Service/CountryService.php` — 504 stmt, 0/0.
- `app/Schema/Migration0.php` — 298 stmt, 0/0.
- `app/Schema/Migration44.php` — 105 stmt, 0/0.
Daneben `Statistics.php` (1087 stmt) mit L2 264 / L3 3 — quasi blind in L3, hauptsaechlich in L2 abgedeckt; `StatisticsData.php` (1781 stmt) mit L2 1134 / L3 253 — beide Layer decken weit unter 50 %, in Summe ca. 78 % wenn beides zusammenfaellt. Schema-Migrationen sind in keinem Layer abgedeckt.

### [2026-05-24T21:10+0200] [5.1] A025
**Aktion:** `docs/coverage-runs/2026-05-24_abdeckung-snapshot.md` geschrieben.
**Ergebnis:** Self-contained Snapshot mit Zusammenfassungstabelle (per Domäne G/S/P/SEC/M/E/A/K/U), Vergleich gegen April, Anschlussverweisen. 215 spezifikationsbasiert + 1 strukturbasiert (G27) abgedeckt; 2 nicht abgedeckt (G05, G06); 1 SKIP (U02). Gesamt 219 Features.
**Beobachtung:** M-Reihe ist neu seit April (+29 Features) — sie ist im April-Snapshot in der Tabelle gar nicht enthalten.

### [2026-05-24T21:18+0200] [5.2] A026
**Aktion:** `docs/coverage-runs/2026-05-24_gap-analyse.md` geschrieben.
**Ergebnis:** Self-contained, mit Methodik (§1.3 Erhebungs-Schritte 1–4 inkl. V2-Heuristik-Tabelle), Gesamtkennzahlen (§2.1), Klassifikation (§2.2 mit April-Vergleich), L2-Verzeichnis-Verteilung (§3, 18 Top-Level), L3-/L4-Feature-ID-Abdeckung (§4 + §5). CSVs als Geschwister-Dateien verlinkt.
**Beobachtung:** L2-Pfad-Konvention bewusst von April abweichend dokumentiert (heute basenamen-relativ zu `tests/app/`, April war `app/`-prefixed relativ zu `tests/`).

### [2026-05-24T21:25+0200] [5.3] A027
**Aktion:** `docs/coverage-runs/2026-05-24_layer2-vs-layer3.md` geschrieben.
**Ergebnis:** Self-contained Coverage-Vergleich mit Gesamt-Tabelle (§1), April-Vergleich (§2, L2 −9,86 pp / L3 +9,82 pp), Per-Verzeichnis-Tabelle (§3, 19 Top-Level + app-Root), Http-Untergruppen (§3.1), Module-Patterns (§3.2), Top-10 Verluste (§4) und Gewinne (§5), Blinde Flecken (§6), Methodik (§7 inkl. Reproduktions-Snippets).
**Beobachtung:** Http-L2-Coverage stuerzt 62,5 % → 20,5 %, Http-L3-Coverage steigt 36,1 % → 72,9 % — strukturelle Verlagerung der Test-Investition von L2 nach L3 quantitativ belegt. Sub-Aggregat Http/Middleware sprintet L3 9 % → 74 %.

### [2026-05-24T21:25+0200] [5.4] A028
**Aktion:** Querverweise zwischen den drei neuen Reports geprueft.
**Ergebnis:** Jeder Report hat in einer Schlussekt-Sektion (`Anschlussverweise`) Links zu den beiden anderen Geschwistern sowie zur passenden Historie-Datei. Im Kopf-Block stehen Geschwister-Links zusaetzlich als Direktverweise. Phase 5 abgeschlossen.

### [2026-05-24T21:30+0200] [6.1] A029
**Aktion:** `docs/tds_coverage_ref.md` Z. 80 / 140: Pointer auf neuen Gap-Analyse-Report ergaenzt. Z. 80 (`Einstufungs-Quelle`): Heuristik-Definition bleibt in `historical/2026-04-11_*.md §2`, aktuelle Anwendung in `2026-05-24_gap-analyse.md §1.3`. Z. 140 (L2-Fußnote): `Aktueller Inventar-Snapshot:` als ersten Pointer hinzugefuegt.
**Ergebnis:** Diffs angewendet, Datei syntaktisch ok.

### [2026-05-24T21:30+0200] [6.2] A030
**Aktion:** `docs/tds_coverage_ref.md` Z. 365: CSV-Pointer angepasst.
**Ergebnis:** Aktueller `2026-05-24_gap-analyse_l2.csv` als primaerer L2-CSV-Verweis, April-CSV als historischer Vergleich.

### [2026-05-24T21:31+0200] [6.3] A031
**Aktion:** `docs/tds_conditions_ref.md` Z. 93 / 282 aktualisiert. Z. 93: `Aktuelle Mess-Basis: coverage-runs/2026-05-24_gap-analyse.md` als Lead-Zeile. Z. 282: L4-Kennzahlen auf heute aktualisiert (55 Specs, Stub 0 / Smoke 22 / Substantial 33) mit Pointer auf neuen Report.
**Ergebnis:** Aktive Mess-Basis-Verweise zeigen jetzt auf den neuen Erhebungstag.

### [2026-05-24T21:32+0200] [6.4] A032
**Aktion:** `memory/project_coverage_verification.md` — neuer Block `**Frozen Snapshot 2026-05-24**` zwischen Live-Ratchet-Werten und `Why:`-Paragraph eingefuegt, mit Werten L2 29,96 % / L3 49,65 % und Verweis auf die drei neuen Coverage-Runs-Reports. Live-Ratchet-Werte (L2 29,84 % / L3 49,05 %) bleiben unveraendert — sie tracken die laufende Ratchet, der Snapshot ist die zitierfaehige Referenz.
**Ergebnis:** Memory-File ergaenzt, kein Konflikt zu bestehenden Eintraegen.

### [2026-05-24T21:33+0200] [6.5] A033
**Aktion:** `memory/MEMORY.md` Z. 6: Hook-Text aktualisiert von `L2: 39,82 %, L3: 39,83 % (Stand 2026-04-11, Commit 72bb731); Snapshot: docs/coverage-runs/` auf `L2 29,96 % / L3 49,65 % (frozen 2026-05-24-Snapshot); Reports: docs/coverage-runs/2026-05-24_*.md`.
**Ergebnis:** Index-Eintrag matcht aktuellen Stand.

### [2026-05-24T21:34+0200] [6.6] A034
**Aktion:** Final-Check via `grep -rn "2026-04-11_" docs/ | grep -v 'historical/' | grep -v '2026-05-24_'` plus Link-Resolution-Check der drei neuen Reports (relative Pfade ab `docs/coverage-runs/`).
**Ergebnis:** Keine 2026-04-11-Refs ausserhalb `historical/` (mit Ausnahme der drei neuen Reports, die historische Pfade gezielt verlinken). Alle Markdown-Links der neuen Reports auflösbar. Phase 6 abgeschlossen.
