<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Workflow: Coverage-Messung → CRAP-Score → Neue Tests

Prozeduraler Leitfaden für eine Coverage-Erweiterungs-Iteration
(Teststufe 2 — Komponentenintegrationstest, Layer 3, MySQL).

Dieser Guide beschreibt, *was* getestet werden soll — basierend auf Coverage-Lücken und
CRAP-Score-Analyse. Für Methodik, 5-Phasen-Arbeitsablauf und Abschlussschritte:
→ [Gemeinsamer Workflow](wf_test-iteration_guide.md)

---

## 1 Übersicht und Ablauf

### 1.1 Schrittfolge

| Schritt | Zweck | Ausführung |
|---|---|---|
| 0 | Stack starten, Coverage erzeugen, CRAP-Report | Einmalig zu Beginn |
| 1 | Analyse erstellen (`docs/coverage-runs/*_full_analysis.md`) | Nach Schritt 0 |
| 2 | Implementierungsplan + AP-Dateien erstellen | Nach Schritt 1 |
| 3 | APs umsetzen: → [5-Phasen-Arbeitsablauf](wf_test-iteration_guide.md#7-arbeitsablauf-je-feature-5-phasen) | Nach Schritt 2 |
| 4 | Abschluss: → [Voll-Lauf, Ratchet, Commit](wf_test-iteration_guide.md#10-abschluss-voll-lauf-ratchet-konsistenzprüfung-commit) | Nach allen APs |

### 1.2 AP-Datei-Namenskonvention

```
ap-{gruppe}-{nn}-{kurzname}.md
```

| Segment | Bedeutung |
|---|---|
| `gruppe` | `a` (CRAP > 1.000), `b` (CRAP 300-1.000), `c` (CRAP 100-300) |
| `nn` | Zweistellig, nullgefuellt: 01, 02, ... |
| `kurzname` | Klassenname in kebab-case, max. 30 Zeichen |

Beispiele: `ap-a-01-right-to-left-support.md`, `ap-b-03-search-general-page.md`

AP-Dateien werden in Schritt 2 fuer die aktuelle Iteration generiert und in
`docs/coverage-runs/` abgelegt (siehe Abschnitt 1.3).

### 1.3 Ablage iterationsspezifischer Artefakte

Das Verzeichnis `docs/coverage-runs/` ist der permanente Ablageort fuer alle
iterationsspezifischen Artefakte:

- `*_full_analysis.md` -- Coverage-Analyse (Schritt 1)
- `*_full_impl_plan.md` -- Implementierungsplan (Schritt 2)
- `ap-{gruppe}-{nn}-{kurzname}.md` -- AP-Dateien (Schritt 2/3)

Jede Iteration erzeugt dort ihre eigenen Dateien.

---

## 2 Schritt 0: Umgebung starten und Coverage erzeugen

### 2.1 Stack starten

```bash
# 1. Laufende Testprozesse pruefen
pgrep -a phpunit && echo "Aktiver Lauf -- erst warten oder per kill beenden"

# 2. Alte Coverage entfernen
rm -f artifacts/layer3/coverage.xml

# 3. Stack starten -- IMMER make up, NIEMALS make _compose-up direkt
#    (make up ruft intern generate-passwords auf -- ohne das bleibt .env leer)
make up

# 4. webtrees einrichten (falls nicht bereits geschehen)
make setup
```

### 2.2 Coverage erzeugen

```bash
# run_in_background: true -- laeuft deutlich laenger als 10 Minuten
# Kein timeout-Parameter setzen
make test-integration
```

Ergebnis: `artifacts/layer3/coverage.xml`

Auf die Fertigmeldung warten, bevor der naechste Schritt beginnt.

### 2.3 CRAP-Report erzeugen

```bash
make crap-report
```

Ausgabe: Tabelle aller Methoden mit CRAP > 100 und 0% Coverage, absteigend nach CRAP.

Diese Ausgabe ist die Datenbasis fuer Schritt 1. Die Tabelle in der Konversation
behalten (nicht aus dem Kontext verlieren) -- Schritt 1 liest sie direkt.

---

## 3 Schritt 1: Analyse erstellen

**Ausgabe:** `docs/coverage-runs/<iterations-praefix>_full_analysis.md`

Struktur und Tiefe folgen der Vorlage in Anhang A (fiktive Beispieldaten).
Alle Abschnitte 3.1-3.7 mit den aktuellen Werten aus `coverage.xml`
und der `make crap-report`-Ausgabe aus Schritt 0 befuellen.

### 3.1 Gesamtueberblick

Aus `artifacts/layer3/coverage.xml` extrahieren:
- Gesamt-Anweisungsueberdeckung: covered / total, Prozent
- Gesamt-Methodenueberdeckung: covered / total, Prozent

Paket-Aufschluesselungs-Tabelle:

| Paket | Statements | Cov% | Methoden | MthCov% | Bewertung |

Bewertungskategorien: Sehr gut (>80%), Gut (50-80%), Partiell (10-50%),
Gering (1-10%), Marginal (<1%), Keine Coverage (0%).

Vergleich zur vorherigen Baseline als Delta-Spalte.

### 3.2 CRAP-Score-Ranking (alle CRAP > 100, 0%-Coverage)

Aus der `make crap-report`-Ausgabe (Kontext aus Schritt 0):

| Rang | CRAP | Paket | Klasse | Methode | cx |

Vollstaendige Liste -- nicht auf 30 begrenzen.

### 3.3 Klassifikation: DB-abhaengig vs. Bootstrap-only

Fuer jeden Kandidaten aus 3.2: Quellcode-Analyse -- benoetigt die Methode
`DB::table()`, `Tree`, oder `Registry::individualFactory()`? Oder reicht
webtrees-Bootstrap (I18N, Registry)?

**DB-abhaengig (braucht `createTreeWithGedcom()`):**

| CRAP | Klasse | Methode | DB-Zugriff | Feature-Matrix-Bezug |

**Bootstrap-only (kein DB-Aufruf, trotzdem Layer-3):**

| CRAP | Klasse | Methode | Begruendung | Testbarkeit |

Grenzfaelle explizit begruenden (z. B. Klasse hat `DB::table()`, aber die zu
testende Methode nicht).

Feature-Matrix-IDs aus `docs/tds_conditions_ref.md` lesen.

### 3.4 Gap-Analyse Feature-Matrix x Coverage

FM-IDs aus `docs/tds_conditions_ref.md` lesen.
Fuer jede ID: Testklasse(n), Coverage-Status (gruen/partiell/rot), Bemerkung.

### 3.5 Priorisierter Handlungsplan (ohne Code)

Aktionspunkte nach CRAP absteigend, gruppiert:

```
Gruppe A: CRAP > 1.000 (hoechste Prioritaet)
Gruppe B: CRAP 300-1.000
Gruppe C: CRAP 100-300
```

Fuer jeden Punkt: Zielklasse, Methode, CRAP/cx, Begruendung (1-2 Saetze),
Umsetzungsidee (kein Code), Einschaetzung Testaufwand (niedrig/mittel/hoch).

### 3.6 Dokumentations-Diff-Vorschlaege

Fuer jede Aenderung an Ratchet-Ist-Stand, FM-Tabelle, Abdeckungsmatrix:
diff-Block mit Kontext (Tabelle/Abschnitt).

Referenzen:
- Ratchet-Werte: `docs/tp_ratchet_spec.md`
- Feature-Matrix-IDs: `docs/tds_conditions_ref.md`
- Abdeckungsmatrix: `docs/tds_coverage_ref.md`

### 3.7 Einschraenkungen und Messartefakte

| Einschraenkung | Auswirkung | Empfehlung |

Pflichteintraege:
- pcov statement-level (keine Branch-Coverage)
- Bootstrap-only-Tests: zaehlen zur Layer-3-Coverage, aber kein DB-Beweis
- Methoden, die nur in E2E- oder Performance-Lauf triggerbar sind
- Sehr grosse Klassen (cx > 50): hoher CRAP, aber Klasse komplex

---

## 4 Schritt 2: Implementierungsplan und AP-Dateien erstellen

Dieser Schritt erzeugt zwei Ausgaben:

1. `docs/coverage-runs/<iterations-praefix>_full_impl_plan.md`
2. AP-Prompt-Dateien in `docs/coverage-runs/` (eine Datei pro AP)

### 4.1 Implementierungsplan

Struktur und Tiefe folgen der Vorlage in Anhang B (fiktive Beispieldaten).
Alle strukturellen Elemente uebernehmen.

#### Status-Konzept

```
**Status:** OFFEN | IN ARBEIT | ABGESCHLOSSEN | BLOCKIERT
**Abgeschlossen:** --
**Ergebnis:** --
```

#### AP-Priorisierung

Gruppe A vollstaendig vor Gruppe B. Gruppe B vollstaendig vor Gruppe C.
Innerhalb einer Gruppe: CRAP absteigend.

#### Keine Zwischencommits

Erst nach Abschluss aller APs + `make test-integration` Exit 0 committen.

Stack-Regeln, Container-Pfade und Konstruktor-Verifikation: → [Pflicht-Constraints](wf_test-iteration_guide.md#9-pflicht-constraints)

### 4.2 AP-Dateien erstellen

Fuer jeden AP aus dem Plan eine Datei in `docs/coverage-runs/` anlegen.

#### Dateiname

```
ap-{gruppe}-{nn}-{kurzname}.md
```

#### Dateiinhalt-Template

```markdown
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP {Gruppe}-{nn} -- {Klassenname}

**Status:** OFFEN
**Abgeschlossen:** --
**Ergebnis:** --

---

## Ziel

| | |
|---|---|
| Klasse | `{ClassName}` |
| Methode | `{methodName}` |
| CRAP | {value} |
| cx | {complexity} |
| Paket | {package} |
| Quellpfad | `upstream/webtrees/app/{Pfad}/{ClassName}.php` |

---

## Phase 1 -- Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/{Pfad}/{ClassName}.php`

Erwartete Konstruktor-Parameter:
- `{TypA} ${paramA}` -- {Beschreibung}
- `{TypB} ${paramB}` -- {Beschreibung}

### PHP-Testskelett

Erstelle `layer3-integration/tests/{ClassName}IntegrationTest.php`.

Skeleton: extends MysqlTestCase (falls DB benoetigt) oder direkt MysqlTestCase
(falls Bootstrap-only, kein createTreeWithGedcom() noetig).

Leere Testmethoden, korrekte Imports, SPDX-Header.

Keine Testausfuehrung in Phase 1.

---

## Phase 2 -- Ausfuehrung (sequenziell)

### Einzeltest-Befehl

```bash
# Vorher: kein laufender Testprozess
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter '{ClassName}IntegrationTest' \
  /tests/layer3-integration/tests/{ClassName}IntegrationTest.php
```

### Iteratives Fixing

Root Cause aus Fehlerausgabe lesen -> gezielt fixen -> Einzeltest wiederholen.

Regeln:
- Nicht Methode tauschen, wenn der Konstruktor das Problem ist
- Nicht blind wiederholen -- Fehler verstehen, dann handeln
- Keine Annahmen ueber Abhaengigkeiten ohne Quellcode-Pruefung

### Verifikation

- Assertion: `{expectedAssertion}`
- Nach gruenem Test: Status dieser AP-Datei auf ABGESCHLOSSEN setzen.
```

### 4.3 Naechste Schritte nach Erstellung

AP-Dateien sind erstellt. Jetzt:

1. **Phasen-Ausführung** gemäss dem [5-Phasen-Arbeitsablauf](wf_test-iteration_guide.md#7-arbeitsablauf-je-feature-5-phasen) im gemeinsamen Workflow
2. Nach allen APs: → Schritt 4 (Abschluss gemäss [Voll-Lauf, Ratchet, Commit](wf_test-iteration_guide.md#10-abschluss-voll-lauf-ratchet-konsistenzprüfung-commit))

---

## 5 Schritt 3: APs umsetzen

Umsetzung erfolgt gemäss dem [5-Phasen-Arbeitsablauf](wf_test-iteration_guide.md#7-arbeitsablauf-je-feature-5-phasen) im gemeinsamen Workflow.

Besonderheiten der Coverage-Iteration:

**Phase 1 — Skelette (parallelisierbar):**
Alle Skelette einer Gruppe können parallel erstellt werden. Keine Testausführung
in dieser Phase. Konstruktor-Verifikation ist Pflicht vor jedem Skelett.

**Phase 2 — Ausführung (strikt sequenziell):**
APs innerhalb einer Gruppe werden nacheinander ausgeführt. Vor jedem Testlauf
mit `pgrep -a phpunit` prüfen, dass kein anderer Testprozess läuft.
Iteratives Fixing bis zum grünen Einzeltest, dann AP-Status auf ABGESCHLOSSEN setzen.

Gruppenreihenfolge: A (CRAP > 1.000) → B (CRAP 300–1.000) → C (CRAP 100–300).

---

## Anhang A: Strukturvorlage Analyse

> Fiktive Beispieldaten -- zeigt Struktur und Tiefe der Coverage-Analyse.
> Dieses Template dient als Vorlage fuer Schritt 1.

---

### A.1 Gesamtueberblick

```
Gesamt-Anweisungsueberdeckung:   22,5%  (9.914 / 44.066 Statements)
Gesamt-Methodenueberdeckung:     19,2%  (853 / 4.441 Methoden)
```

| Metrik | Vorher (296 Tests) | Nachher (312 Tests) | Delta |
|---|---|---|---|
| Anweisungsueberdeckung | 19,8% (8.716 / 44.066) | 22,5% (9.914 / 44.066) | +2,7 Pp |
| Methodenueberdeckung | 17,7% (787 / 4.441) | 19,2% (853 / 4.441) | +1,5 Pp |

#### Paket-Aufschluesselung (Ausschnitt)

| Paket | Statements | Cov% | Methoden | MthCov% | Bewertung |
|---|---|---|---|---|---|
| Http\Routes | 370 | 99,7% | 2 | 50,0% | Sehr gut |
| Services | 5.727 | 41,3% | 297 | 24,6% | Partiell |
| Http\RequestHandlers | 14.280 | 3,1% | 1.834 | 2,7% | Gering |
| Report | 2.104 | 0,0% | 198 | 0,0% | Keine Coverage |

### A.2 CRAP-Score-Ranking (alle CRAP > 100, 0%-Coverage)

| Rang | CRAP | Paket | Klasse | Methode | cx |
|---|---|---|---|---|---|
| 1 | 6.972 | Report | RightToLeftFormatter | format | 83 |
| 2 | 2.256 | Report | HtmlTextBox | render | 47 |
| 3 | 1.722 | Http\RequestHandlers | SearchGeneralPage | handle | 41 |

### A.3 Klassifikation: DB-abhaengig vs. Bootstrap-only

**DB-abhaengig (braucht `createTreeWithGedcom()`):**

| CRAP | Klasse | Methode | DB-Zugriff | FM-Bezug |
|---|---|---|---|---|
| 1.722 | SearchGeneralPage | handle | `DB::table('individuals')` | FM-S03 |

**Bootstrap-only (kein DB-Aufruf, trotzdem Layer-3):**

| CRAP | Klasse | Methode | Begruendung | Testbarkeit |
|---|---|---|---|---|
| 6.972 | RightToLeftFormatter | format | Kein DB-Zugriff, nur String-Ops | Hoch |
| 2.256 | HtmlTextBox | render | Reines String-Rendering | Mittel |

### A.4 Gap-Analyse Feature-Matrix x Coverage

| FM-ID | Testklasse | Status | Bemerkung |
|---|---|---|---|
| FM-S01 | SearchIntegrationTest | gruen | Vollstaendig |
| FM-S03 | -- | rot | SearchGeneralPage::handle fehlt |
| FM-C01 | CalendarIntegrationTest | partiell | getAnniversaryEvents abgedeckt, handle nicht |

### A.5 Priorisierter Handlungsplan

**Gruppe A (CRAP > 1.000):**

| AP | Klasse | Methode | CRAP/cx | Begruendung | Aufwand |
|---|---|---|---|---|---|
| AP1 | RightToLeftFormatter | format | 6972/83 | Bootstrap-only, maximale CRAP-Wirkung | niedrig |
| AP2 | SearchGeneralPage | handle | 1722/41 | DB-abhaengig, FM-S03 kritisch | mittel |

**Gruppe B (CRAP 300-1.000):**

| AP | Klasse | Methode | CRAP/cx | Begruendung | Aufwand |
|---|---|---|---|---|---|
| AP3 | HtmlTextBox | render | 2256/47 | Bootstrap-only, grosser CRAP-Block | mittel |

### A.6 Dokumentations-Diff-Vorschlaege

```diff
 ## Ist-Stand (Teststufe 2, Stand: YYYY-MM-DD, nach AP1-APn)
-Anweisungsueberdeckung: 19,8% (8.716 / 44.066)
+Anweisungsueberdeckung: 22,5% (9.914 / 44.066)
-Methodenueberdeckung:   17,7% (787 / 4.441)
+Methodenueberdeckung:   19,2% (853 / 4.441)
-Tests: 296, Assertions: 899, Testklassen: 21
+Tests: 312, Assertions: 1.034, Testklassen: 23
```

### A.7 Einschraenkungen und Messartefakte

| Einschraenkung | Auswirkung | Empfehlung |
|---|---|---|
| pcov statement-level | Keine Branch-Coverage | Als bekannte Luecke dokumentieren |
| Bootstrap-only-Tests | Kein DB-Beweis | Im Testkommentar kennzeichnen |
| Methoden nur in E2E erreichbar | Im Integrations-Layer nicht triggerbar | FM-Eintrag als "E2E only" |
| Sehr grosse Klassen (cx > 50) | Hoher CRAP, aber Klasse komplex | Prioritaet trotzdem nach CRAP |

---

## Anhang B: Strukturvorlage Implementierungsplan

> Fiktive Beispieldaten -- zeigt Struktur und Tiefe des Implementierungsplans.
> Dieses Template dient als Vorlage fuer Schritt 2.

---

### B.1 Gesamtstatus

| AP | Titel | Status | Ergebnis |
|---|---|---|---|
| AP1 | RightToLeftFormatter Bootstrap-Test | ABGESCHLOSSEN | 5 Tests, 12 Assertions, Exit 0 |
| AP2 | SearchGeneralPage::handle | IN ARBEIT | -- |
| AP3 | HtmlTextBox::render | OFFEN | -- |

### B.2 Ausgangslage

| Metrik | Wert |
|---|---|
| Anweisungsueberdeckung | 19,8% (8.716 / 44.066) |
| Methodenueberdeckung | 17,7% (787 / 4.441) |
| Testklassen | 21 (296 Tests, 899 Assertions) |

### B.3 Pflicht-Constraints

→ [Pflicht-Constraints](wf_test-iteration_guide.md#9-pflicht-constraints)

### B.4 AP-Beispiel: RightToLeftFormatter

**Status:** ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 5 Tests, 12 Assertions, Exit 0

| | |
|---|---|
| Klasse | `RightToLeftFormatter` |
| Methode | `format` |
| CRAP | 6.972 |
| cx | 83 |
| Paket | Report |
| Quellpfad | `upstream/webtrees/app/Report/RightToLeftFormatter.php` |

Bootstrap-only. Konstruktor ohne Parameter. Testklasse: `RightToLeftSupportIntegrationTest`.

### B.5 AP-Beispiel: SearchGeneralPage

**Status:** IN ARBEIT
**Abgeschlossen:** --
**Ergebnis:** --

| | |
|---|---|
| Klasse | `SearchGeneralPage` |
| Methode | `handle` |
| CRAP | 1.722 |
| cx | 41 |
| Paket | Http\RequestHandlers |
| Quellpfad | `upstream/webtrees/app/Http/RequestHandlers/SearchGeneralPage.php` |

DB-abhaengig: benoetigt `createTreeWithGedcom()`. FM-S03.

### B.6 AP-Beispiel: HtmlTextBox

**Status:** OFFEN
**Abgeschlossen:** --
**Ergebnis:** --

| | |
|---|---|
| Klasse | `HtmlTextBox` |
| Methode | `render` |
| CRAP | 2.256 |
| cx | 47 |
| Paket | Report |
| Quellpfad | `upstream/webtrees/app/Report/HtmlTextBox.php` |

Bootstrap-only. Kein DB-Zugriff, reines String-Rendering.
