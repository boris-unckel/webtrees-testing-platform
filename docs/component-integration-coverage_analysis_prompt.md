<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Coverage-Analyse-Prompt — Komponentenintegrationstest (Teststufe 2)

> Milestone-Prompt für die Analyse und Erweiterung der Code-Coverage in Layer 3
> (`layer3-integration/`). Wird nach einem `make test-integration-quick`-Lauf
> eingesetzt, wenn das Coverage-Artefakt frisch in `artifacts/layer3/coverage.xml`
> vorliegt.

---

## Kontext

Das Projekt `webtrees-testing-platform` testet den webtrees Core auf 3 Teststufen
(ISTQB-Terminologie ist führend, Layer-Bezeichnungen stehen in Klammern):

| Teststufe | Verzeichnis | Tool |
|---|---|---|
| Teststufe 1 — Komponententest | `layer2-unit/` | SQLite in-memory |
| **Teststufe 2 — Komponentenintegrationstest** | `layer3-integration/` | MySQL im Container |
| Teststufe 3 — Systemtest | `layer4-e2e/` | Playwright |

Die Coverage wird via `pcov` im Clover-XML-Format gemessen. Strategisch gilt das
**Ratchet-Prinzip**: Anweisungsüberdeckung darf nur steigen, niemals sinken. Kein
absoluter Zielwert — jeder neue Test ist ein Gewinn.

Die fachliche Grundlage sind Feature-Matrizen in `docs/testing-bigpicture.md`:

| Domäne | IDs | Teststufe 2 (aktiv) |
|---|---|---|
| GEDCOM Import/Export | G01–G23 | G01–G04, G07–G10, G12–G16 |
| Suche & Navigation | S01–S40 | S01–S03, S05–S08, S10–S12, S19, S21, S22 |
| Datenschutz & Zugriffskontrolle | P01–P29 | P01–P24, P27–P29 |

---

## Eingaben — Vor der Analyse einzulesen

1. **`artifacts/layer3/coverage.xml`** — Clover-XML des letzten Laufs.
   Enthält pro Datei: Pfad, `statements`, `coveredstatements`, `methods`,
   `coveredmethods`, `complexity`, `crap` (CRAP-Score pro Methode).

2. **`layer3-integration/tests/*.php`** — Alle 19 vorhandenen Testklassen.
   Relevant: `@covers`-Annotationen, `@see`-Referenzen auf Feature-Matrix-IDs.

3. **`docs/testing-bigpicture.md`** — Teststrategie mit Feature-Matrizen,
   Endekriterien (Abschnitt "Endekriterien pro Teststufe") und
   Überdeckungsstrategie (Abschnitt "Überdeckungsstrategie — Ratchet").
   Die Datei ist groß (>1.500 Zeilen) — gezielt die folgenden Abschnitte lesen:
   - `## Überdeckungsstrategie — Ratchet`
   - `## Endekriterien pro Teststufe`
   - `### Feature-Matrix: GEDCOM Import/Export`
   - `### Feature-Matrix: Suche und Navigation`
   - `### Feature-Matrix: Datenschutz & Zugriffskontrolle`
   - `### Testfall-Verteilung nach Teststufe`

4. **`upstream/webtrees/app/`** — webtrees-Source. Gezielt für Klassen,
   die als Kandidaten identifiziert werden (nur bei Bedarf).

---

## Analyseschritte

### Schritt 1 — Gesamtüberblick aus coverage.xml

Aus coverage.xml die folgenden Kennzahlen extrahieren und ausgeben:

```
Gesamt-Anweisungsüberdeckung:  X.X%  (covered / total statements)
Gesamt-Methodenüberdeckung:    X.X%  (covered / total methods)
Dateien mit 0%-Coverage:       XXX von XXXX
```

Aufschlüsselung nach Paketen (= erste Verzeichnisebene unterhalb `app/`):

| Paket | Dateien | Statements | Cov% | Methods | MthCov% |
|---|---|---|---|---|---|
| (root) | … | … | … | … | … |
| Services | … | … | … | … | … |
| Http | … | … | … | … | … |
| Module | … | … | … | … | … |
| … | … | … | … | … | … |

### Schritt 2 — CRAP-Score-Ranking (Layer-2/3 noch nicht getrennt)

**CRAP (Change Risk Anti-Patterns)** = `complexity² × (1 − coverage)³ + complexity`
(PHPUnit-Näherung: hohe Complexity + niedrige Coverage = hohes Risiko).

Das Clover-XML enthält `crap` pro Methode (`<line type="method" crap="...">`).

Vorgehen:
1. Alle Methoden mit `count="0"` (ungetestet) aus coverage.xml extrahieren.
2. CRAP-Score pro Methode aus dem `crap`-Attribut auslesen.
3. Nach CRAP-Score absteigend sortieren.
4. Die **Top 30** ausgeben:

| Rang | CRAP | Klasse | Methode | Statements | Datei |
|---|---|---|---|---|---|
| 1 | … | … | … | … | … |
| … | … | … | … | … | … |

### Schritt 3 — Layer-Abgrenzung: Layer-3-geeignet vs. Layer-2-geeignet

Für jeden Kandidaten aus Schritt 2 (und grundsätzlich für alle Null-Coverage-Pakete)
die Frage beantworten: **Braucht diese Klasse MySQL und webtrees-Laufzeit,
oder ist sie isoliert testbar?**

**Kriterien für Layer-3 (Komponentenintegrationstest, braucht MySQL + Container):**
- Klasse ruft `DB::table()`, `DB::select()`, o. ä. auf (direkte DB-Abhängigkeit)
- Klasse nutzt `Tree`, `GedcomRecord`, `Individual`, `Family` aus der DB
- Klasse ist ein `RequestHandler` (Slim/PSR-15, braucht Router-Bootstrap)
- Klasse ist ein `Module` mit DB-abhängiger Render-Logik
- Klasse nutzt `Registry::` (Container-Abhängigkeiten, webtrees-Bootstrap nötig)

**Kriterien für Layer-2 (Komponententest, kein Container nötig):**
- Paket `Census/` — pure Datentabellen, keine DB-Abhängigkeit
- Paket `Report/` — Render-Logik, in-memory DOM
- Paket `Elements/` — GEDCOM-Tag-Parsing, reine String-Verarbeitung
- Paket `Encodings/` — Zeichensatz-Konvertierung, kein State
- Paket `SurnameTradition/` — algorithmische Logik, kein State
- Paket `CommonMark/` — Markdown-Rendering
- Paket `Exceptions/` — triviale Value-Objekte
- Paket `Date/` — Datumsformate (bereits in upstream `tests/` abgedeckt — prüfen)
- Paket `Statistics/` — kann DB brauchen; im Einzelfall prüfen

Ausgabe als zwei Listen:

**Layer-3-Kandidaten (Top 15 nach CRAP, mit MySQL-Abhängigkeit bestätigt):**

| CRAP | Klasse (gekürzt) | Methode | Begründung Layer-3 | Feature-Matrix-Bezug |
|---|---|---|---|---|
| … | … | … | … | … |

**Layer-2-Kandidaten (Top 15 nach CRAP, isoliert testbar):**

| CRAP | Klasse (gekürzt) | Methode | Begründung Layer-2 | Anmerkung |
|---|---|---|---|---|
| … | … | … | … | … |

### Schritt 4 — Gap-Analyse: Feature-Matrix vs. Coverage

Für jede Feature-Matrix-ID, die laut `testing-bigpicture.md` der Teststufe 2
zugeordnet ist (G01–G04, G07–G10, G12–G16, S01–S03, S05–S08, S10–S12,
S19, S21, S22, P01–P24, P27–P29), feststellen:

a) **Ist ein Test vorhanden?** (via `@see`-Annotationen in den Testdateien,
   Dateiname, Klassenname — nicht durch Code-Lesen)
b) **Ist die Ziel-Klasse/-Methode in coverage.xml als covered markiert?**
   (Statement-Coverage > 0 für die `@covers`-Klasse)
c) **Status** = `grün` / `partiell` / `rot (kein Test)` / `rot (Test vorhanden, aber 0% Coverage)`

Ausgabe als Tabelle:

| ID | Bezeichnung | Testklasse | Coverage-Status | Bemerkung |
|---|---|---|---|---|
| G01 | Record-Import (INDI) | GedcomImportTest | grün / partiell / rot | … |
| … | … | … | … | … |

Anschließend: **Lücken-Zusammenfassung** — welche Feature-Matrix-IDs haben noch
keinen Test in Teststufe 2, obwohl sie dort zugeordnet sind?

### Schritt 5 — Priorisierte Empfehlungsliste (Handlungsplan)

Aus den Ergebnissen von Schritt 3 und 4 eine priorisierte Liste der nächsten
sinnvollen Testklassen ableiten. Priorisierungslogik:

1. **Höchste Priorität:** Feature-Matrix-IDs in Teststufe 2 ohne Test (Gap-Analyse, Schritt 4, Status "rot") — hier fehlt Tracking.
2. **Zweite Priorität:** Layer-3-Kandidaten mit hohem CRAP-Score (Schritt 3, Layer-3-Liste) — Risiko + kein Test.
3. **Dritte Priorität:** Layer-3-Kandidaten mit partieller Coverage und hohem CRAP — bestehende Tests erweitern.

Ausgabe als nummerierte Liste:

```
1. [Testklassen-Vorschlag] — deckt Feature-Matrix-IDs X, Y ab
   Zu coverende Klassen: ...
   CRAP-Scores: ...
   Begründung: ...

2. ...
```

Maximal 5 konkrete Vorschläge, jeder mit Testklassen-Name, zu coverende
webtrees-Klassen und Feature-Matrix-IDs.

### Schritt 6 — Vorschläge für testing-bigpicture.md

Auf Basis der Analyse konkrete Textvorschläge für `docs/testing-bigpicture.md`
erarbeiten. Sprache: Deutsch, ISTQB-Terminologie, Stil des bestehenden Dokuments.

**6a — Überdeckungsstrategie — Ratchet (Abschnitt aktualisieren):**

Den bestehenden Abschnitt `## Überdeckungsstrategie — Ratchet` um eine
aktuelle Ist-Stand-Tabelle ergänzen:

```markdown
### Ist-Stand (Teststufe 2, Stand: YYYY-MM-DD)

| Metrik | Wert |
|---|---|
| Anweisungsüberdeckung | X.X% (NNNN/NNNNN Statements) |
| Methodenüberdeckung | X.X% (NNN/NNNN Methods) |
| Dateien mit 0%-Coverage | NNNN von NNNN |
| Pakete mit >50%-Coverage | CustomTags (97%), GedcomFilters (81%), … |
| Größte unberührte Pakete | Census (0%), Report (0%), Statistics (0%), … |
```

**6b — Endekriterien (Abschnitt erweitern):**

Falls neue Feature-Matrix-IDs durch neue Tests abgedeckt werden, den
Endekriterien-Eintrag für Teststufe 2 entsprechend erweitern.
Vorschlag als Diff-Block:

```diff
- Alle Feature-Matrix-Integrationstests grün (G01–G04, ...)
+ Alle Feature-Matrix-Integrationstests grün (G01–G04, ..., [neue IDs])
```

**6c — Feature-Matrix-Erweiterungen (falls neue Domänen identifiziert):**

Falls Schritt 3 Layer-3-Kandidaten aus Paketen enthält, die noch keine
Feature-Matrix-IDs haben (z. B. `Statistics/`, `Module/`, `Http/` jenseits
der bestehenden S-IDs), einen Vorschlag für neue Feature-Matrix-Einträge
formulieren — im bestehenden Tabellenformat:

```
| [ID] | [Bezeichnung] | [Testbedingung → erwartetes Ergebnis] | 2 | [Priorität] |
```

**6d — Abdeckungsmatrix (falls vorhanden, aktualisieren):**

Falls `testing-bigpicture.md` eine Abdeckungsmatrix enthält (alle
Feature-Matrix-IDs × Teststufe × Status), diese um neu abgedeckte IDs
aktualisieren.

---

## Ausgabe-Reihenfolge

Der Analyse-Output soll in dieser Reihenfolge präsentiert werden:

1. Gesamtüberblick (Schritt 1) — Kennzahlen und Paket-Tabelle
2. CRAP-Ranking Top 30 (Schritt 2)
3. Layer-Abgrenzung (Schritt 3) — zwei Listen
4. Gap-Analyse Feature-Matrix (Schritt 4) — Tabelle + Zusammenfassung
5. Priorisierter Handlungsplan (Schritt 5) — 5 Vorschläge
6. testing-bigpicture.md Update-Vorschläge (Schritt 6a–d)

---

## Limitierungen — Was diese Analyse NICHT leisten kann

Die folgenden Punkte sind strukturell begrenzt und sollten im Output
explizit benannt werden, wenn sie relevant sind:

| Limitierung | Begründung | Konsequenz |
|---|---|---|
| **Coverage-Granularität** | `pcov` misst Statement-Coverage, keine Branch- oder Pfad-Coverage. Ein `count="1"` heißt nicht, dass alle Branches eines `if`-Blocks durchlaufen wurden. | CRAP-Score überschätzt "Sicherheit" bei partiell covered Methoden. |
| **Keine Kausalität** | Coverage sagt, welcher Code ausgeführt wurde — nicht, ob er korrekt getestet wurde. Assertions fehlen im XML. | Hohe Coverage ≠ gute Tests. |
| **Layer-2-Abgrenzung ist heuristisch** | Die Klassifikation "braucht MySQL" basiert auf statischer Analyse von Paket/Imports, nicht auf Laufzeitbeobachtung. Einzelfälle (z. B. `Statistics`) können falsch klassifiziert sein. | Vor Implementierung einzelner Kandidaten den Sourcecode prüfen. |
| **upstream-Tests** | `layer2-unit/` führt die upstream webtrees-Tests aus. Viele Klassen (z. B. `Date/`) sind dort schon in Tests. Ein 0%-Coverage-Eintrag in diesem Report heißt nicht, dass sie gar nicht getestet werden — es heißt, dass sie im Layer-3-Lauf nicht ausgeführt werden. | Layer-2-Kandidaten immer gegen upstream `tests/` abgleichen, bevor neue Tests gebaut werden. |
| **Fixture-Abhängigkeit** | Layer-3-Tests laufen gegen eine feste GEDCOM-Fixture (`demo.ged`, 72 Individuen, 29 Familien). Code-Pfade, die andere Datenkonstellationen brauchen, können nur durch Fixture-Erweiterung oder neue `privacy-test-template.ged`-ähnliche Fixtures abgedeckt werden. | Fixture-Erweiterung ist eine eigene Aufgabe, nicht durch Coverage-Analyse ersetzt. |
| **Module-Paket** | 259 Dateien, 2,0% Coverage — aber `Module/`-Klassen rendern HTML und hängen an webtrees-Routing. Tests dafür sind aufwändiger als Service-Tests. CRAP-Score allein rechtfertigt hier keinen hohen Priorisierungsrang. | Modul-Tests sollten Feature-Matrix-geführt sein (S-IDs), nicht CRAP-getrieben. |
| **Http-Paket** | 381 Dateien, 4,1% Coverage. `RequestHandler`-Tests brauchen den vollständigen PSR-15-Stack. `AutoCompleteIntegrationTest.php` zeigt das Muster — aber der Bootstrapping-Aufwand ist hoch. | Nur Http-Klassen testen, die direkt einer Feature-Matrix-ID zugeordnet sind. |

---

## Hinweise zur Ausführung

- `coverage.xml` hat ~56.000 Zeilen. Nicht komplett einlesen — python3-Skript
  oder gezieltes Parsen via Bash (`grep`, `xmllint`) verwenden.
- Beispiel CRAP-Extraktion (ungetestete Methoden):
  ```bash
  python3 -c "
  import xml.etree.ElementTree as ET, sys
  tree = ET.parse('artifacts/layer3/coverage.xml')
  rows = []
  for f in tree.findall('.//file'):
      fname = f.get('name','').replace('/var/www/html/app/','app/')
      for line in f.findall('line[@type=\"method\"]'):
          if int(line.get('count','0')) == 0:
              crap = float(line.get('crap', 0))
              rows.append((crap, fname, line.get('name',''), line.get('complexity',0)))
  rows.sort(reverse=True)
  for crap, f, m, cx in rows[:30]:
      print(f'{crap:8.1f}  {f}  {m}  (cx={cx})')
  "
  ```
- Paket-Übersicht:
  ```bash
  python3 -c "
  import xml.etree.ElementTree as ET
  root = ET.parse('artifacts/layer3/coverage.xml').getroot()
  pkgs = {}
  for f in root.findall('.//file'):
      path = f.get('name','').replace('/var/www/html/app/','')
      parts = path.split('/')
      pkg = parts[0] if len(parts) > 1 else '(root)'
      m = f.find('metrics')
      if m is None: continue
      d = pkgs.setdefault(pkg, [0,0,0,0,0])
      d[0] += 1; d[1] += int(m.get('statements',0))
      d[2] += int(m.get('coveredstatements',0))
      d[3] += int(m.get('methods',0))
      d[4] += int(m.get('coveredmethods',0))
  print(f'{\"Paket\":<28} {\"Files\":>6} {\"Stmts\":>7} {\"Cov%\":>6} {\"Mths\":>6} {\"MthCov%\":>8}')
  for pkg, d in sorted(pkgs.items(), key=lambda x: x[1][2], reverse=True):
      pc = d[2]/d[1]*100 if d[1] else 0
      mc = d[4]/d[3]*100 if d[3] else 0
      print(f'{pkg:<28} {d[0]:>6} {d[1]:>7} {pc:>6.1f}% {d[3]:>6} {mc:>8.1f}%')
  "
  ```

---

## Verwandte Dokumente

- `docs/testing-bigpicture.md` — Teststrategie, Feature-Matrizen, Endekriterien
- `layer3-integration/tests/*.php` — Bestehende Testklassen
- `artifacts/layer3/coverage.xml` — Eingabe dieser Analyse
- `CLAUDE.md` — Projektregeln (Lizenz-Header, Testausführungsregeln, SELinux-Falle)
