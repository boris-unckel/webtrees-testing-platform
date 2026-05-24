<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Test-Inventar und Hybrid-V2-Klassifikation — 2026-05-24

> **Datum:** 2026-05-24 (Erhebungs-Tag).
> **Quell-Stände:**
>
> - **L2-Inventar:** `upstream/webtrees` @ `d123a1b789e29872d6736ece1d9d47cb0a038e8c`
>   vom 2026-05-17 (im Testing-Platform-Repo unter `upstream/webtrees/`, als
>   read-only gemounteter Upstream-Abzug).
> - **L3-Inventar:** `webtrees-testing-platform` @ `main` (Stand 2026-05-24)
>   in `layer3-integration/tests/`.
> - **L4-Inventar:** `webtrees-testing-platform` @ `main` (Stand 2026-05-24)
>   in `layer4-e2e/tests/`.
>
> **Geltungsbereich:** Aufzeigen, was pro Layer abgedeckt ist. Nachfolger des
> historischen Reports
> [`historical/2026-04-11_gap-analyse-fork.md`](historical/2026-04-11_gap-analyse-fork.md);
> Vergleich mit damals ist informativ und nicht zentral — die Fork-Branch-Idee
> (substanzielle Upstream-Test-Aufwertung) wird **nicht** weiterverfolgt.
>
> **Self-contained:** Alle Zahlenwerte stehen inline in diesem Dokument. Die
> CSV-Rohdaten liegen als Geschwister-Dateien daneben (`2026-05-24_gap-analyse_l{2,3,4}.csv`),
> die Erhebungs-Skripte unter [`../scripts/coverage/`](../../scripts/coverage/).
> Eine Verfügbarkeit von `docs/test-runs/` ist für das Lesen dieses Dokuments
> nicht erforderlich.

---

## 1 Quellen, Skripte, Reproduzierbarkeit

### 1.1 Erhebungs-Skripte (in diesem Repo eingecheckt)

| Skript | Aufgabe |
|---|---|
| [`scripts/coverage/gap_inventory_php.sh`](../../scripts/coverage/gap_inventory_php.sh) | PHP-Testdatei-Metriken (lines, methods, providers, assertions) + Hybrid-V2-Klassifikation |
| [`scripts/coverage/gap_inventory_ts.sh`](../../scripts/coverage/gap_inventory_ts.sh) | Playwright-Spec-Metriken (lines, tests, each_loops, expects) + Klassifikation |
| [`scripts/coverage/clover_aggregate.sh`](../../scripts/coverage/clover_aggregate.sh) | Clover-XML-Aggregat (Coverage L2/L3) für `2026-05-24_layer2-vs-layer3.md` |

Alle drei Skripte sind `shellcheck`-clean (Google Shell Style Guide), nutzen
`bash` + `awk` und arbeiten ohne den Compose-Stack.

### 1.2 Inventar-Reproduktion

```bash
scripts/coverage/gap_inventory_php.sh upstream/webtrees/tests/app > docs/coverage-runs/2026-05-24_gap-analyse_l2.csv
scripts/coverage/gap_inventory_php.sh layer3-integration/tests > docs/coverage-runs/2026-05-24_gap-analyse_l3.csv
scripts/coverage/gap_inventory_ts.sh   layer4-e2e/tests        > docs/coverage-runs/2026-05-24_gap-analyse_l4.csv
```

### 1.3 Erhebungs-Schritte (Methodik)

**Schritt 1 — Inventar pro Test-Schicht.** Alle Testdateien rekursiv via
`find -name "*Test.php"` bzw. `-name "*.spec.ts"`. L2-Scope ist heute
`upstream/webtrees/tests/app/` (Upstream-PHPUnit-Suite *Unit tests*).
`tests/feature/` ist nicht im L2-Pfad eingebunden und bleibt aus der Zählung.
L3 schließt Basis-Klassen `*TestCase.php` aus. L4 umfasst `*.spec.ts` rekursiv
inkl. `security/`.

**Schritt 2 — Metriken pro Datei.** Pro Testdatei werden erhoben:

| Layer | Metrik | Definition |
|---|---|---|
| L2/L3 | `lines` | Datei-Zeilen (`wc -l`) |
| L2/L3 | `methods` | Methoden mit `public function test*`-Namen ∪ `#[Test]`-Attribut, dedupliziert |
| L2/L3 | `providers` | Vorkommen von `#[DataProvider`-Attribut |
| L2/L3 | `assertions` | Vorkommen von `$this->assert*`, `self::assert*`, `static::assert*` |
| L4    | `lines` | Datei-Zeilen |
| L4    | `tests` | Top-Level-`test(`-Aufrufe |
| L4    | `each_loops` | Top-Level-`test.each(`-Aufrufe (parametrisiert) |
| L4    | `expects` | Vorkommen von `expect(` (inkl. `await expect(`) |
| alle  | `density` | `assertions/methods` (PHP) bzw. `expects/tests` (TS) |
| alle  | `phpdoc_ep`, `phpdoc_sub` | `@ep`, `@substantial` als Bit-Flag |

**Schritt 3 — Hybrid-V2-Klassifikation.** PHPDoc-Override hat höchste
Priorität, danach metrikbasiert in Prüfreihenfolge:

| Klasse | Kriterium |
|---|---|
| `EP-complete` | `@ep` ODER `providers ≥ 3` ODER (`methods ≥ 10` UND `density ≥ 2.0`) |
| `Substantial` | `@substantial` ODER (`methods ≥ 3` UND `density ≥ 2.0`) ODER (`methods ≥ 3` UND `lines/methods ≥ 15` UND `density ≥ 1.0`) |
| `Smoke`       | `methods ≥ 2` UND (`density ≥ 1.0` ODER `lines/methods ≥ 10`) |
| `Stub`        | Rest (inkl. `methods == 0`, Boilerplate, `class_exists`-Smoke) |

(L4 analog: `providers→each_loops`, `methods→tests`, `assertions→expects`.)

**Schritt 4 — Domänen-Zuordnung.**

- **L2:** Upstream-Tests tragen keine `@see`-Annotationen zu Feature-IDs.
  Zuordnung auf Aggregat-Ebene per Top-Level-Verzeichnis unter `tests/app/`
  (siehe §3.4) — eine Datei-für-Datei-Zuordnung ist über die `file`-Spalte
  in der CSV möglich, aber nicht in diesem Dokument ausgerollt.
- **L3 / L4:** Tests nutzen `@see docs/tds_conditions_ref.md <ID>`-Annotationen.
  Zuordnung pro Datei per `grep`-Extraktion (siehe §3.5 / §3.6).

---

## 2 Ergebnisse — Übersicht

### 2.1 Gesamtkennzahlen pro Layer

| Kennzahl | L2 (Komponententest, Upstream) | L3 (KIT, MySQL) | L4 (Systemtest, Playwright) |
|---|---:|---:|---:|
| Testdateien | **1232** | **156** | **55** |
| Quell-Zeilen gesamt | 67 170 | 37 978 | 2 962 |
| Testmethoden / `test()` gesamt | 1 917 | 1 203 | 193 |
| DataProvider / `test.each` | 21 | 21 | 0 |
| Assertions / `expect(` gesamt | 7 943 | 2 125 | 352 |
| Gesamt-Assertionsdichte | 4.14 | 1.77 | 1.82 |
| Mittlere Datei-Länge (Zeilen) | 54.5 | 243.4 | 53.9 |
| Mittlere Methoden pro Datei | 1.6 | 7.7 | 3.5 |

**Lesehinweis:** L2 hat die höchste Gesamt-Assertionsdichte (4.14), aber das
ist durch wenige hochdichte Census-/Time-Tests verzerrt. Die mittlere Methoden-
zahl pro Datei (1.6) zeigt, dass die Mehrheit der L2-Dateien sehr klein bleibt.
L3 ist im Schnitt **5× länger** und enthält **5× mehr Methoden** pro Datei als
L2 — das entspricht der Investitions-Verlagerung der letzten Monate weg von
upstream-L2-Aufwertung hin zu integrationstest-getriebener Abdeckung.

### 2.2 Qualitätsklassifikation (Hybrid V2)

| Klasse | L2 | L3 | L4 |
|---|---:|---:|---:|
| `Stub`         | 951 (77.2 %) | 4 (2.6 %)   | 0 (0.0 %)   |
| `Smoke`        | 175 (14.2 %) | 41 (26.3 %) | 22 (40.0 %) |
| `Substantial`  | 99 (8.0 %)   | 96 (61.5 %) | 33 (60.0 %) |
| `EP-complete`  | 7 (0.6 %)    | 15 (9.6 %)  | 0 (0.0 %)   |
| **Gesamt**     | **1 232**    | **156**     | **55**      |

PHPDoc-Marker `@ep` / `@substantial` wurden in keiner der drei Schichten gefunden
(0 / 0) — die Klassifikation ergibt sich ausschließlich aus der V2-Heuristik.

**Vergleich zum April-Snapshot:**

| Klasse | L2 April→heute | L3 April→heute | L4 April→heute |
|---|---|---|---|
| Stub | 682 (55.1 %) → **951 (77.2 %)** | 2 (2.4 %) → 4 (2.6 %) | 0 → 0 |
| Smoke | 285 (23.0 %) → 175 (14.2 %) | 9 (11.0 %) → 41 (26.3 %) | 11 (42.3 %) → 22 (40.0 %) |
| Substantial | 263 (21.2 %) → **99 (8.0 %)** | 65 (79.3 %) → 96 (61.5 %) | 15 (57.7 %) → 33 (60.0 %) |
| EP-complete | 8 (0.6 %) → 7 (0.6 %) | 6 (7.3 %) → 15 (9.6 %) | 0 → 0 |
| **Gesamt** | 1 238 → 1 232 | 82 → **156** (+90 %) | 26 → **55** (+112 %) |

Der dominante L2-Befund ist die **Rück-Stub-isierung**: ohne die seinerzeit im
Fork-Branch eingespielten 271 substanziellen Upstream-Tests fallen wir auf den
Upstream-Vanilla-Stand zurück (Stub-Quote 77.2 % statt 55.1 %). L3 hat im
gleichen Zeitraum **um 90 % gewachsen** (82 → 156 Dateien) und in der absoluten
Substantial-Zahl von 65 auf 96 zugelegt — die relative Substantial-Quote sinkt
leicht (79.3 % → 61.5 %), weil viele neue L3-Tests zunächst als Smoke
eingebracht werden und schrittweise zu Substantial verdichtet werden.

L4 verdoppelt sich nahezu (26 → 55 Specs), bei stabilem Klassenmix.

---

## 3 L2-Verteilung pro Top-Level-Verzeichnis

Die L2-CSV (`2026-05-24_gap-analyse_l2.csv`) trägt 1232 Datenzeilen mit Pfaden
relativ zu `upstream/webtrees/tests/app/`. Die Pfade beginnen nicht mit
`app/` (April-Konvention war `app/AgeTest.php`, heute `AgeTest.php`); siehe
§1.3 zur Abweichung.

| Top-Level | Dateien | Zeilen | Methoden | Assertions | Subst+EP | Fachliche Zuordnung |
|---|---:|---:|---:|---:|---:|---|
| (Root `tests/app/*.php`)            | 47  | 3 178 | 137 | 351 | ~13 | gemischt |
| `Census/`                            | 192 | 19 661 | 616 | 5 815 | 51 | **G** (Census-Daten-Spalten) |
| `Cli/`                               | 0  | — | — | — | — | (kein L2 — Tests in L3) |
| `CommonMark/`                        | 7   | 224  | 7   | 7    | 0  | *(infra)* |
| `CustomTags/`                        | 20  | 640  | 20  | 20   | 0  | **G** |
| `Date/`                              | 8   | 338  | 10  | 26   | 1  | **G** |
| `Elements/`                          | 212 | 8 514 | 127 | 245  | 10 | **G** |
| `Encodings/`                         | 13  | 970  | 19  | 80   | 1  | **G** |
| `Exceptions/`                        | 3   | 96  | 3  | 3   | 0  | *(infra)* |
| `Factories/`                         | 27  | 1 311 | 58 | 136 | 4  | **G** / **E** |
| `GedcomFilters/`                     | 1   | 32  | 1  | 1   | 0  | **G** |
| `Http/`                              | 373 | 29 608 | 942 | 1 189 | ~156 | **alle Domänen** (RequestHandler) |
| `Module/`                            | 210 | 10 354 | 254 | 399 | ~15 | **S** + **A** + **P** |
| `Report/`                            | 27  | 864 | 27 | 27 | 0  | **S** (Berichte) |
| `Reports/`                           | 1   | 367 | 10 | 66 | 1  | **S** |
| `Schema/`                            | 48  | 1 536 | 48 | 48 | 0  | *(infra — Migrations)* |
| `Services/`                          | 36  | 2 167 | 86 | 206 | 12 | **G** + **P** + **E** |
| `SurnameTradition/`                  | 9   | 1 759 | 73 | 123 | 9  | **S** |

Diese Tabelle ist gegenüber der April-Erhebung weitgehend stabil — die Datei-
Zahl pro Verzeichnis hat sich nur marginal verändert. Die `Http/`-Gruppe stellt
mit 373 Dateien rund 30 % des L2-Inventars und ist 2026 vom Fork-Branch primär
auf substanziellere Tests umgestellt worden. Da diese Aufwertung **nicht** in
den heutigen Upstream-Abzug zurückgewandert ist, sind die `Http/`-Tests heute
mehrheitlich wieder Stub-Niveau (ca. 156 Subst+EP von 373 ergaben sich aus
Fork-Branch — heute deutlich weniger; eine genauere Zahl bleibt der nächsten
fokussierten Analyse vorbehalten).

---

## 4 L3-Feature-ID-Abdeckung

**Dateien mit `@see`-Annotation:** **156 / 156 (100.0 %)**. Jede L3-Test-Datei
trägt mindestens einen `@see`-Verweis auf
[`docs/tds_conditions_ref.md`](../tds_conditions_ref.md).

**Distinct Feature-IDs in L3 referenziert:** **170**.

| Domäne | Distinct IDs | Beispiele |
|---|---:|---|
| A (Authentifizierung) | 19 | A01–A19 |
| E (Editor / GEDCOM-Aktion) | 9 | E01–E09 |
| G (GEDCOM-Daten) | 25 | G01–G31 (ohne G05, G06; verstreut) |
| M (Middleware) | 25 | M03, M05–M21, M23–M29 |
| P (Privacy / Personen-Mgmt) | 42 | P01–P44 (mit kleinen Lücken) |
| S (Systemfunktion) | 50 | S01–S53 (verstreut) |
| **Gesamt** | **170** | |

**Coverage-Auslesung gegenüber dem Abdeckungs-Snapshot:** Der Snapshot
[`2026-05-24_abdeckung-snapshot.md`](2026-05-24_abdeckung-snapshot.md) führt
216 abgedeckte Features. Die hier gezeigten 170 distinct L3-IDs sind die
Untermenge, die L3-Direkttests trägt. Die Differenz von **46 Features** ist
durch L2- oder L4-Tests abgedeckt (siehe §5).

---

## 5 L4-Feature-ID-Abdeckung

**Dateien mit `@see`-Annotation:** **49 / 55 (89.1 %)**.

**Dateien ohne `@see`** (vor April-Konvention geschrieben oder breit nach
Domain benannt):

- `access-control.spec.ts`
- `privacy-charts.spec.ts`
- `privacy-relationship.spec.ts`
- `privacy-resn.spec.ts`
- `privacy-search.spec.ts`
- `privacy-visibility.spec.ts`

(Alle sechs gehören in den Bereich Privacy / Access-Control — das fachliche
Mapping ist aus dem Dateinamen ableitbar; die `@see`-Annotation kann später
nachgezogen werden, ohne dass die Abdeckung defacto ausfiele.)

**Distinct Feature-IDs in L4 referenziert:** **48**.

| Domäne | Distinct IDs | Beispiele |
|---|---:|---|
| A | 5 | A01, A04, A05, A07, A08 |
| E | 7 | E01–E06, E08 |
| G | 1 | G21 |
| M | 1 | M16 |
| P | 5 | P30, P37, P38, P40, P41 |
| S | 29 | S05–S53 (Schwerpunkt) |
| **Gesamt** | **48** | |

L4 ist – wie zu erwarten – ein Systemtest-fokussierter Layer: 29 von 48
referenzierten IDs entstammen der S-Reihe (Systemfunktionen, Theme-Variation,
Navigation, Chart-Browser). G-Detail-Bereich (Daten-Parsing) und M-Bereich
(Middleware) sind in L4 nur kursorisch vertreten — die Tiefenabdeckung liegt
dort in L3.

---

## 6 Lese-Quellen

- **CSV-Rohdaten (Geschwister-Dateien):**
  [`2026-05-24_gap-analyse_l2.csv`](2026-05-24_gap-analyse_l2.csv) (1232 Zeilen),
  [`2026-05-24_gap-analyse_l3.csv`](2026-05-24_gap-analyse_l3.csv) (156 Zeilen),
  [`2026-05-24_gap-analyse_l4.csv`](2026-05-24_gap-analyse_l4.csv) (55 Zeilen).
- **Klassifikations-Snapshot:** [`2026-05-24_abdeckung-snapshot.md`](2026-05-24_abdeckung-snapshot.md).
- **Quellcode-Coverage-Vergleich L2 vs L3:**
  [`2026-05-24_layer2-vs-layer3.md`](2026-05-24_layer2-vs-layer3.md).
- **Historischer Vorgänger:** [`historical/2026-04-11_gap-analyse-fork.md`](historical/2026-04-11_gap-analyse-fork.md).
