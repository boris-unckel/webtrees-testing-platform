<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt — Vollständige Coverage-Erweiterung Komponentenintegrationstest (Teststufe 2)

> Ziel: Auf Basis einer vollständigen `make test-integration`-Coverage eine
> Analyse und einen ausführbaren Umsetzungsplan erstellen und umsetzen.

---

## Kontext und Vorwissen

### Was bisher existiert

- `docs/component-integration-coverage_analysis.md` — Analyse der Quick-Lauf-Coverage
  (3 Testklassen: SearchIntegrationTest, PrivacyVisibilityTest, TreeOperationsTest).
  Baseline: 9,0% Statement-, 7,4% Methodenüberdeckung.
- `docs/component-integration-coverage_impl_plan.md` — Daraus abgeleiteter Plan,
  der 5 Arbeitspakete (AP1–AP5) erfolgreich umgesetzt hat:
  - AP1: S19 Nachnamen-Collation (ListModuleIntegrationTest)
  - AP2: G16 Export-Privacy-Regressions-Guard (TreeOperationsTest)
  - AP3: S16 legacyNameAlgorithm Pfad-Tests (RelationshipServiceIntegrationTest)
  - AP4: G24 CheckTree Referenzintegrität (CheckTreeIntegrationTest — neu)
  - AP5: IndividualFactsService relativeFacts (IndividualFactsIntegrationTest — neu)

Der bisherige Plan basierte auf einer **verfälschten Baseline** (Quick-Lauf ≠ alle 17
Testklassen). Dieser Prompt erzeugt eine Folge-Erweiterung auf Basis der echten
Vollabdeckung aller Layer-3-Tests.

### CRAP-Score-Formel

```
CRAP = complexity² × (1 − coverage)³ + complexity
```

Ein CRAP von 0% Coverage und cx=N ergibt N² + N. Ein CRAP von 100% Coverage ergibt N.

---

## Schritt 0 — Umgebung zurücksetzen und Coverage erzeugen

### 0a — Stack sauber starten

**Zwingend in dieser Reihenfolge:**

```bash
# 1. Laufende Testprozesse prüfen
pgrep -f "phpunit" && echo "Aktiver Lauf — erst warten oder per kill beenden"

# 2. Alte Coverage-Artefakte entfernen
rm -f artifacts/layer3/coverage.xml artifacts/layer3/coverage-quick.xml
# Optional: kompletten artifacts/layer3/-Ordner bereinigen
rm -rf artifacts/layer3/

# 3. Stack starten — IMMER make up, NIEMALS make _compose-up direkt
#    (make up ruft intern generate-passwords auf — ohne das bleibt .env leer
#    und MySQL startet nicht)
make up

# 4. webtrees installieren (falls nicht bereits geschehen)
make setup
```

### 0b — Vollständige Coverage erzeugen

```bash
# run_in_background: true — läuft deutlich länger als 10 Minuten
# Kein timeout-Parameter setzen
make test-integration
```

Ergebnis: `artifacts/layer3/coverage.xml` mit Coverage aller 17 Testklassen.

---

## Schritt 1 — Analyse erstellen: `docs/component-integration-coverage_full_analysis.md`

Die Analyse folgt exakt dem Aufbau von `docs/component-integration-coverage_analysis.md`.
Jeder der folgenden 6 Unterschritte wird gegen die neue `artifacts/layer3/coverage.xml`
ausgeführt.

### 1.1 — Gesamtüberblick

Extrahiere aus `coverage.xml`:

- Gesamt-Anweisungsüberdeckung: `statements` covered / total, Prozent
- Gesamt-Methodenüberdeckung: `methods` covered / total, Prozent
- Anzahl Dateien mit 0%-Coverage

Erstelle eine Paket-Aufschlüsselung-Tabelle:

| Paket | Dateien | Statements | Cov% | Methoden | MthCov% | Bewertung |

Pakete aus dem `name`-Attribut der `<package>`-Elemente in der XML ableiten.
Bewertungskategorien: Gut abgedeckt (>50%), Partiell (10–50%), Gering (1–10%),
Marginal (<1%), Keine Coverage (0%).

**Hinweis:** In der Voll-Coverage fallen Pakete, die im Quick-Lauf mit 0% erschienen,
jetzt ggf. in die Partiell-Kategorie. Besonders: `Services/RelationshipService`,
`CheckTree` (jetzt durch die neuen Tests abgedeckt).

### 1.2 — CRAP-Score-Ranking Top 30

Alle Methoden mit `count=0` (ungetestet im Voll-Lauf), absteigend nach CRAP-Score.

Tabelle:

| Rang | CRAP | Paket | Klasse | Methode | cx |

CRAP berechnen aus Clover-XML: `complexity` (ccn) und Coverage (covered/statements).
Methoden mit 0% Coverage: `count=0` auf Statement-Ebene.

**Wichtig:** Methoden, die im Quick-Lauf mit CRAP 516.242 erschienen aber jetzt
abgedeckt sind (z.B. `legacyNameAlgorithm`), müssen aus der Liste verschwunden sein.
Falls sie noch aufgeführt sind → Coverage-Wert aus XML prüfen.

### 1.3 — Layer-Abgrenzung

Für jeden der Top-30-CRAP-Kandidaten: Klassifikation Layer-3 oder Layer-2.

**Layer-3-Kriterien (MySQL + Container nötig):**
- Direkte `DB::table()`-Aufrufe im Code der Methode
- `Tree`-Objekt als Parameter oder Property
- `Registry::individualFactory()`, `Registry::container()` etc.
- `RequestHandler`-Interface (`handle(ServerRequestInterface $request)`)

**Layer-2-Kriterien (SQLite in-memory oder kein DB):**
- Pakete `Report/`, `Census/`, `Encodings/`, `SurnameTradition/`
- Reine String/DOM-Manipulationsmethoden
- Algorithmen ohne Datenbankzugriff

Erstelle zwei Tabellen:

**Layer-3-Kandidaten:**
| CRAP | Klasse | Methode | Begründung Layer-3 | Feature-Matrix-Bezug |

**Layer-2-Kandidaten:**
| CRAP | Klasse | Methode | Begründung Layer-2 | Anmerkung |

Bei Grenzfällen (z.B. `StatisticsData` im Paket `(root)` aber mit `DB::table()`):
explizit kennzeichnen und Layer-3 zuordnen.

### 1.4 — Gap-Analyse Feature-Matrix × Coverage

Teststufe-2-IDs aus `docs/testing-bigpicture.md` lesen:
G01–G04, G07–G10, G12–G16, G24, S01–S03, S05–S08, S10–S12, S19, S21, S22,
P01–P24, P27–P29.

Für jede ID:
- Welche Testklasse(n) decken sie ab?
- Coverage-Status: grün (>50% stmt der Zielklasse) / partiell / rot (kein Test)
- Bemerkung zu bekannten Lücken

Tabellen pro Gruppe (GEDCOM G01–G16/G24, Suche S01–S22, Datenschutz P01–P29).

### 1.5 — Priorisierter Handlungsplan (in der Analyse, ohne Code)

5–10 priorisierte Aktionspunkte, nach diesem Schema:

```
Priorität 1: Feature-Matrix-Lücken (Status "partiell" oder "rot")
Priorität 2: Nicht-FM-Kandidaten mit CRAP > 1.000 (Layer-3)
Priorität 3: Layer-2-Kandidaten mit CRAP > 5.000 (für Upstream-Contribution)
```

Für jeden Punkt:
- ID/Name
- Zielklasse + Methode
- CRAP vorher / cx
- Begründung (1–3 Sätze)
- Grobe Umsetzungsidee (kein Code)

### 1.6 — Testing-bigpicture.md Diff-Vorschläge

Für jede Änderung an `docs/testing-bigpicture.md`, die sich aus der Analyse ergibt:

```diff
- alte Zeile
+ neue Zeile
```

Mit Kontext: Welche Tabelle / Welcher Abschnitt.

### 1.7 — Einschränkungen und Messartefakte

Tabelle:

| Einschränkung | Auswirkung | Empfehlung |

Pflichteinträge:
- Scope des Voll-Laufs (alle 17 Testklassen vs. Quick)
- pcov statement-level Coverage (keine Branch-Coverage)
- I18N-Kontext (legacyNameAlgorithm gibt je nach Locale-Initialisierung unterschiedliche Strings zurück)
- Methoden, die nur in E2E-Lauf getriggert werden

---

## Schritt 2 — Umsetzungsplan erstellen: `docs/component-integration-coverage_full_impl_plan.md`

Der Plan folgt exakt dem Aufbau von `docs/component-integration-coverage_impl_plan.md`.
Zusätzlich: **Status-Tracking pro Arbeitspaket**.

### Pflichtbestandteile des Plans

#### Status-Konzept

Jedes Arbeitspaket erhält einen Status-Block am Anfang:

```markdown
**Status:** ⬜ OFFEN | 🔄 IN ARBEIT | ✅ ABGESCHLOSSEN | ❌ BLOCKIERT

**Abgeschlossen:** —  
**Ergebnis:** —
```

Der Status wird nach jeder abgeschlossenen AP-Bearbeitung aktualisiert — nicht erst
am Ende aller Arbeitspakete.

#### Stack-Voraussetzungen (analog bisherigem Plan)

- `make up` (nie `make _compose-up`) → `make setup`
- Alle lang laufenden Tests mit `run_in_background: true`
- `pgrep -f "phpunit"` vor jedem neuen Lauf
- Exklusivität: Niemals parallele Testläufe

#### Iterationskonzept

Testausführung nach **jedem einzelnen Arbeitspaket**:

```bash
# Einzelnen Test ausführen (schneller Feedback-Loop während Entwicklung):
# run_in_background: true — auch Einzeltests können > 2 min dauern
make test-integration-quick
# Falls der neue Test nicht im Quick-Subset ist:
# Temporäres Skript oder direkter PHPUnit-Aufruf im Container:
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /var/www/html/phpunit-integration.xml \
  --filter 'MeinNeuerTestClass' \
  /var/www/html/layer3-integration/tests/MeinNeuerTestClass.php
```

**Wann make test-integration (Voll-Lauf) nötig:**
- Am Ende jedes AP (Coverage-Änderung verifizieren)
- Vor dem finalen Commit (alle Tests grün bestätigen)

**Wann make test-integration-quick ausreicht:**
- Schneller Smoke-Check während Entwicklung
- Nur wenn der neue Test im Quick-Subset liegt

**Coverage-Verschiebung beachten:**
Jeder neue Test verändert die `coverage.xml`. Die CRAP-Scores der Analyse gelten
für den Stand vor der Implementierung. Nach jedem abgeschlossenen AP den CRAP
des nächsten Kandidaten nicht blindlings aus der initialen Analyse übernehmen,
sondern ggf. aus der neuen coverage.xml nachvollziehen.

#### Keine Zwischencommits

Kein `git commit` vor Abschluss aller Arbeitspakete. Erst wenn alle APs
✅ ABGESCHLOSSEN sind und `make test-integration` Exit 0 liefert:

```bash
git add layer3-integration/tests/ docs/testing-bigpicture.md
git commit -m "test(layer3): Coverage-Erweiterung Voll-Lauf ..."
```

#### Arbeitspakete (AP1–APn)

Für jedes Arbeitspaket:

```markdown
## AP N — [Name] ([Testklasse])

**Status:** ⬜ OFFEN
**Abgeschlossen:** —
**Ergebnis:** —

**Priorität:** N (Begründung)
**Datei:** layer3-integration/tests/XxxTest.php
**Feature-Matrix-ID:** Gxx / Sxx / Pxx / — (kein FM-Eintrag)
**Ziel-Klasse:** \Fisharebest\Webtrees\...\ClassName
**CRAP vorher:** N.NNN (cx=N, Cov=N%)

### Was zu ändern ist

[Beschreibung der Änderung]

### PHP-Skelett

```php
/**
 * [Docblock mit FM-ID und @see]
 */
public function test_xxx(): void
{
    // ...
}
```

### Dokumentationsupdate (testing-bigpicture.md)

[diff-Block falls nötig]

### Verifikation

```bash
# Einzeltest (schnell):
podman-compose exec webtrees php vendor/bin/phpunit \
  --filter 'TestClassName' \
  /var/www/html/layer3-integration/tests/TestClassName.php

# Voll-Lauf (Coverage-Verifikation):
# run_in_background: true
make test-integration
```
```

---

## Schritt 3 — Plan ausführen

Führe `docs/component-integration-coverage_full_impl_plan.md` aus.

### Ausführungsregeln

1. **Status nach jedem AP aktualisieren** — nicht erst am Ende.
   Nach AP-Abschluss: Status auf ✅ ABGESCHLOSSEN setzen, Datum und Testergebnis eintragen.

2. **Vor jedem Testlauf prüfen:**
   ```bash
   pgrep -f "phpunit" && echo "Lauf noch aktiv"
   ```

3. **Iteratives Vorgehen bei Fehlern:**
   - Fehlermeldung lesen und verstehen
   - Root Cause identifizieren (Konstruktor-Argumente? Fehlende Imports? Falsche XREF?)
   - Gezielt fixen — nicht Methode austauschen, wenn der Konstruktor das Problem ist
   - Nicht denselben fehlgeschlagenen Aufruf blind wiederholen

4. **Kein `make test-integration` als Entwicklungs-Feedback-Loop** — zu langsam.
   Einzeltest-Aufrufe über `podman-compose exec` verwenden.

5. **Finaler Commit erst wenn alle APs ✅:**
   ```bash
   # Alle Layer-3-Tests nochmals vollständig:
   # run_in_background: true
   make test-integration
   # Danach:
   git add ...
   git commit
   ```

### Was im Plan stehen muss, bevor mit der Ausführung begonnen wird

- Stack-Startsequenz (make up → make setup)
- Status-Block für jedes AP
- PHP-Skelette (mindestens auf Klassen-/Methoden-Ebene, Konstruktor-Argumente aus
  dem webtrees-Source-Code geprüft, nicht geraten)
- Verifikations-Kommandos (Einzeltest + Voll-Lauf)

---

## Ausgabedateien

| Datei | Inhalt |
|---|---|
| `docs/component-integration-coverage_full_analysis.md` | Analyse-Ergebnis (Schritte 1.1–1.7) |
| `docs/component-integration-coverage_full_impl_plan.md` | Umsetzungsplan mit Status-Tracking |
| `docs/testing-bigpicture.md` | Aktualisiert (neue Zeilen FM-Tabelle, Endekriterien, Ratchet) |
| `layer3-integration/tests/*.php` | Neue/erweiterte Testdateien |

---

## Qualitätssicherung

**Analyse:**
- Jede CRAP-Zahl muss aus der `coverage.xml` ableitbar sein (nicht geraten)
- Layer-3/Layer-2-Klassifikation muss mit Quellcode-Zitat begründet sein
- Kein FM-Eintrag darf ohne Begründung von "grün" auf "partiell" rutschen

**Plan:**
- Jedes PHP-Skelett muss Konstruktor-Argumente enthalten, die aus dem webtrees-Source
  verifiziert wurden (kein `new Foo()` für Klassen mit Pflichtparametern)
- Jedes AP muss einen Einzeltest-Befehl enthalten
- Status muss nach jedem AP aktualisiert werden — nicht erst am Ende

**Ausführung:**
- Kein AP als ✅ markieren, bevor der Verifikations-Test grün ist
- Kein finaler Commit, bevor `make test-integration` Exit 0 liefert
