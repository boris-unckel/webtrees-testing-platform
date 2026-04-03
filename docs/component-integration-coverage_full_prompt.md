<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt — Iterative Coverage-Erweiterung Komponentenintegrationstest (Teststufe 2)

> Ziel: Auf Basis der aktuellen `make test-integration`-Coverage eine neue
> Analyse und einen ausführbaren Umsetzungsplan erstellen und umsetzen.
> Ausgabedateien überschreiben die bisherigen Versionen — Versionshistorie liegt in git.

---

## Kontext und Vorwissen

### Aktueller Stand

- **296 Tests, 899 Assertions** in 21 Testklassen (Stand nach letztem Commit)
- **Statement-Coverage: 17,9%** (7.882 / 44.066 Statements, Voll-Lauf-Baseline)
- **Methoden-Coverage: 17,3%** (767 / 4.441 Methoden)
- Coverage-Quelle: `make test-integration` (alle Layer-3-Testklassen)
- Ratchet-Basis: beide Werte dürfen nur steigen

Bisherige Iterationen haben erfolgreich CRAP-Scores im Bereich 14.042–380 adressiert.
Diese Iteration erweitert den Scope auf **alle verbleibenden CRAP-Kandidaten > 100**
(cx ≥ 10, 0% Coverage).

### Aufbau-Vorlagen

Die folgenden Dokumente definieren das erwartete Format für Analyse und Plan.
**Struktur und Tiefe übernehmen — konkreten Inhalt (Klassennamen, CRAP-Zahlen,
iterationsspezifische Hinweise) nicht übernehmen:**

- `docs/component-integration-coverage_full_analysis.md` — Aufbau der Analyse
  (Gesamtüberblick, CRAP-Ranking, Klassifikation, Gap-Analyse, Diff-Vorschläge,
  Einschränkungstabelle)
- `docs/component-integration-coverage_full_impl_plan.md` — Aufbau des Plans
  (Gesamtstatus-Tabelle, Stack-Regeln, Iterationskonzept, AP-Struktur mit Status-Tracking)

### Layer-Regel

**Alle neuen Tests gehen in `layer3-integration/tests/`** — unabhängig davon, ob
die Zielklasse DB-Zugriff hat oder nicht. Layer-2 (`make test-unit`, Upstream-Tests)
bleibt vollständig unangetastet.

Das bedeutet: Kandidaten aus den Paketen `Report/`, `Census/`, `SurnameTradition/`,
`Encodings/` werden im Layer-3-Kontext (`MysqlTestCase`) getestet. Viele dieser
Klassen benötigen nur webtrees-Bootstrap (I18N, Registry), kein DB. Sie können
trotzdem in `MysqlTestCase` instanziiert und aufgerufen werden ohne
`createTreeWithGedcom()` zu benötigen.

### CRAP-Score-Formel

```
CRAP = complexity² × (1 − coverage)³ + complexity
```

Bei 0% Coverage vereinfacht: `CRAP = cx² + cx`. Scope dieser Iteration: **CRAP > 100**
(entspricht cx ≥ 10 bei 0% Coverage).

---

## Schritt 0 — Umgebung zurücksetzen und Coverage erzeugen

### 0a — Stack sauber starten

```bash
# 1. Laufende Testprozesse prüfen
pgrep -a phpunit && echo "Aktiver Lauf — erst warten oder per kill beenden"

# 2. Alte Coverage entfernen
rm -f artifacts/layer3/coverage.xml

# 3. Stack starten — IMMER make up, NIEMALS make _compose-up direkt
#    (make up ruft intern generate-passwords auf — ohne das bleibt .env leer)
make up

# 4. webtrees installieren (falls nicht bereits geschehen)
make setup
```

### 0b — Coverage erzeugen

```bash
# run_in_background: true — läuft deutlich länger als 10 Minuten
# Kein timeout-Parameter setzen
make test-integration
```

Ergebnis: `artifacts/layer3/coverage.xml` mit Coverage aller Testklassen.

---

## Schritt 1 — Analyse erstellen

**Ausgabe:** `docs/component-integration-coverage_full_analysis.md` (überschreibt bestehende Datei)

Aufbau folgt exakt `docs/component-integration-coverage_full_analysis.md` (aktuelle Version
vor dem Überschreiben lesen). Jeder Unterschritt wird gegen die neue `coverage.xml` ausgeführt.

### 1.1 — Gesamtüberblick

Extrahiere aus `coverage.xml`:
- Gesamt-Anweisungsüberdeckung: covered / total, Prozent
- Gesamt-Methodenüberdeckung: covered / total, Prozent

Erstelle Paket-Aufschlüsselung-Tabelle:

| Paket | Statements | Cov% | Methoden | MthCov% | Bewertung |

Bewertungskategorien: Sehr gut (>80%), Gut (50–80%), Partiell (10–50%),
Gering (1–10%), Marginal (<1%), Keine Coverage (0%).

Vergleich zur Vorgänger-Baseline (17,9% / 17,3%) als Delta-Spalte aufnehmen.

### 1.2 — CRAP-Score-Ranking (alle CRAP > 100, 0%-Coverage)

Alle Methoden mit `count=0` und `CRAP > 100`, absteigend nach CRAP.
Nicht auf Top 30 begrenzen — vollständige Liste.

| Rang | CRAP | Paket | Klasse | Methode | cx |

### 1.3 — Klassifikation: DB-abhängig vs. Bootstrap-only

Für jeden Kandidaten: benötigt die Methode `DB::table()`, `Tree`, oder
`Registry::individualFactory()` — oder reicht webtrees-Bootstrap (I18N, Registry)?

Zwei Tabellen:

**DB-abhängig (braucht `createTreeWithGedcom()`):**
| CRAP | Klasse | Methode | DB-Zugriff | Feature-Matrix-Bezug |

**Bootstrap-only (kein DB-Aufruf, trotzdem Layer-3):**
| CRAP | Klasse | Methode | Begründung | Testbarkeit |

Bei Grenzfällen (Klasse hat `DB::table()` aber die zu testende Methode nicht):
explizit begründen.

### 1.4 — Gap-Analyse Feature-Matrix × Coverage

FM-IDs aus `docs/testing-bigpicture.md` lesen (aktuell: G01–G04, G07–G10,
G12–G16, G24, S01–S03, S05–S08, S10–S12, S19, S21, S22, P01–P24, P27–P29).

Für jede ID: Testklasse(n), Coverage-Status (grün/partiell/rot), Bemerkung.

### 1.5 — Priorisierter Handlungsplan (ohne Code)

Aktionspunkte nach CRAP absteigend, gruppiert:

```
Gruppe A: CRAP > 1.000 (höchste Priorität)
Gruppe B: CRAP 300–1.000
Gruppe C: CRAP 100–300
```

Für jeden Punkt: Zielklasse, Methode, CRAP/cx, Begründung (1–2 Sätze),
Umsetzungsidee (kein Code), Einschätzung Testaufwand (niedrig/mittel/hoch).

### 1.6 — testing-bigpicture.md Diff-Vorschläge

Für jede Änderung an Ratchet-Ist-Stand, FM-Tabelle, Abdeckungsmatrix:
diff-Block mit Kontext (Tabelle/Abschnitt).

### 1.7 — Einschränkungen und Messartefakte

| Einschränkung | Auswirkung | Empfehlung |

Pflichteinträge:
- pcov statement-level (keine Branch-Coverage)
- Bootstrap-only-Tests: zählen zur Layer-3-Coverage, aber kein DB-Beweis
- Methoden, die nur in E2E- oder Performance-Lauf triggerbar sind
- Sehr große Klassen (cx > 50): Einzelmethode hat hohen CRAP, aber Klasse komplex

---

## Schritt 2 — Umsetzungsplan erstellen

**Ausgabe:** `docs/component-integration-coverage_full_impl_plan.md` (überschreibt bestehende Datei)

Aufbau folgt exakt der aktuellen Version dieser Datei (vor dem Überschreiben lesen).
Alle strukturellen Elemente übernehmen: Gesamtstatus-Tabelle, Stack-Regeln,
Iterationskonzept, AP-Struktur mit Status-Block.

### Pflichtbestandteile

#### Status-Konzept

```
**Status:** ⬜ OFFEN | 🔄 IN ARBEIT | ✅ ABGESCHLOSSEN | ❌ BLOCKIERT
**Abgeschlossen:** —
**Ergebnis:** —
```

Status nach jeder abgeschlossenen AP-Bearbeitung aktualisieren.

#### Stack-Regeln

- `make up` (nie `make _compose-up`) → `make setup`
- Alle lang laufenden Tests mit `run_in_background: true`
- `pgrep -a phpunit` vor jedem neuen Lauf
- Niemals parallele Testläufe

#### Korrekte Container-Pfade

```bash
# PHPUnit-Konfiguration liegt im Container unter:
/tests/layer3-integration/phpunit-integration.xml

# Testdateien liegen unter:
/tests/layer3-integration/tests/MeineTestklasse.php

# Einzeltest-Befehl:
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'MeineTestklasse' \
  /tests/layer3-integration/tests/MeineTestklasse.php
```

#### Iterationskonzept

Feedback-Loop: Einzeltest via `podman-compose exec` (schnell, kein Coverage-Overhead).
Coverage-Verifikation: `make test-integration` (langsam, nach AP-Abschluss).

**Coverage-Verschiebung:** Nach jedem AP können CRAP-Scores sinken.
Werte aus der initialen Analyse gelten nur für den Stand vor Implementierungsbeginn.

#### AP-Priorisierung

Gruppe A vollständig vor Gruppe B. Gruppe B vollständig vor Gruppe C.
Innerhalb einer Gruppe: CRAP absteigend — aber wenn ein niedrigerer Kandidat
deutlich einfacher zu testen ist, darf er vorgezogen werden, mit Begründung im AP.

#### Konstruktor-Verifikation vor Skelett

Bevor das PHP-Skelett erstellt wird: Konstruktor-Argumente aus dem webtrees-Source
verifizieren. Für `Report/`-Klassen: prüfen ob sie reinen Bootstrap benötigen oder
über einen Handler erreichbar sind. Kein `new Foo()` ohne Konstruktor-Prüfung.

#### Keine Zwischencommits

Erst nach Abschluss aller APs + `make test-integration` Exit 0 committen.

---

## Schritt 3 — Plan ausführen

### Ausführungsregeln

1. Plan vollständig erstellen (inkl. aller PHP-Skelette) bevor AP1 gestartet wird
2. Status nach jedem AP aktualisieren
3. Fehler: Root Cause lesen, gezielt fixen — nicht Methode tauschen wenn
   Konstruktor das Problem ist; nicht blind wiederholen
4. AP als ✅ erst markieren wenn Verifikations-Test grün
5. Finaler Commit erst wenn alle APs ✅ und `make test-integration` Exit 0

### Qualitätssicherung

**Analyse:**
- CRAP-Zahlen aus `coverage.xml` ableitbar (nicht geraten)
- DB-abhängig/Bootstrap-only-Klassifikation mit Quellcode-Zitat begründet
- Vollständige Liste (CRAP > 100), nicht auf 30 begrenzt

**Plan:**
- PHP-Skelette mit verifizierten Konstruktor-Argumenten
- Jedes AP mit Einzeltest-Befehl (korrekter Container-Pfad)
- Report/-Kandidaten: Einstiegspunkt-Analyse vor Skelett

---

## Ausgabedateien

| Datei | Aktion |
|---|---|
| `docs/component-integration-coverage_full_analysis.md` | Überschreiben |
| `docs/component-integration-coverage_full_impl_plan.md` | Überschreiben |
| `docs/testing-bigpicture.md` | Aktualisieren (Ratchet, ggf. FM-Einträge) |
| `layer3-integration/tests/*.php` | Neue/erweiterte Testdateien |
