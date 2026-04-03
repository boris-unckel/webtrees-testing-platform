<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Refactoring-Plan: Prompt-Architektur Coverage-Iteration

> Status: Planungsdokument — noch keine Umsetzung.  
> Basis: Analyse + Klärungsgespräch 2026-04-03.

---

## Problem

Die aktuelle Architektur lädt in einer einzigen Claude-Session:

| Datei | Größe (Token) |
|---|---|
| `component-integration-coverage_full_prompt.md` | ~5 k |
| `component-integration-coverage_full_analysis.md` | ~11 k |
| `component-integration-coverage_full_impl_plan.md` | ~17 k |
| Entstehende PHP-Testdateien (kumulativ pro AP) | ~1–3 k / AP |

Ab AP8 ist der Kontext so voll, dass Qualität und Zuverlässigkeit sinken. Das Ziel ist eine
Architektur, die Kontext-Überladung systematisch vermeidet und gleichzeitig
Parallelisierung der Skelett-Phase ermöglicht.

---

## Entscheidungen (aus Klärungsgespräch)

| Nr. | Frage | Antwort |
|---|---|---|
| F1 | AP-Granularität | Eigene Datei pro AP; mit geeigneter Namenskonvention |
| F2 | Analyse in Vorbereitung? | Ja; Vorbereitung darf in Einzeldateien aufgeteilt werden |
| F3 | Re-Analyse zwischen Gruppen? | Nein; alle Gruppen arbeiten auf demselben initialen Snapshot |
| F4 | Skills = Claude Code Skills? | Besprochen und verworfen — kein Mehrwert für seltene Iterationen |
| F5 | Agenten-Nutzen bewerten? | Zurückgestellt |
| F6 | Skripting-Schmerzpunkt? | Kein spezifischer; Empfehlung des Assistenten |
| F7 | MCP konkret oder offen? | Offene Frage |
| F8 | Skelett-Parallelisierung erlaubt? | Ja — solange Testausführung sequenziell bleibt |

---

## Geplante Dateistruktur

Alle Prompt-Dateien leben unter `docs/coverage-iteration/`.

```
docs/coverage-iteration/
  entry.md                          # Navigationspunkt, kurz
  prep-01-env-coverage.md           # Schritt 0: Stack + make test-integration
  prep-02-analysis.md               # Schritt 1: Analysis-Dokument erstellen
  prep-03-impl-plan.md              # Schritt 2: Implementierungsplan erstellen
  ap-a-01-<kurzname>.md             # Gruppe A, AP 1
  ap-a-02-<kurzname>.md             # Gruppe A, AP 2
  ...
  ap-b-01-<kurzname>.md             # Gruppe B, AP 1
  ...
  ap-c-01-<kurzname>.md             # Gruppe C, AP 1
  ...
  post-01-finalize.md               # Abschluss: Voll-Lauf, Ratchet, Commit
```

### Namenskonvention AP-Dateien

```
ap-{gruppe}-{nn}-{kurzname}.md
```

- `gruppe`: `a` (CRAP > 1.000), `b` (CRAP 300–1.000), `c` (CRAP 100–300)
- `nn`: zweistellig, nullgefüllt (01, 02, ...)
- `kurzname`: Klassenname in kebab-case, max. 30 Zeichen
  (z. B. `ap-a-01-right-to-left-support.md`, `ap-b-03-search-general-page.md`)

Die Kurzname-Komponente orientiert sich an der Zielklasse, nicht an der Methode —
eine AP-Datei kann mehrere Methoden einer Klasse abdecken.

---

## Inhalt der Datei-Typen

### `entry.md`

- Kurzbeschreibung des Gesamtablaufs (max. 1 Seite)
- Tabelle: Datei → Zweck → Ausführungsreihenfolge
- Verweis auf CLAUDE.md für Stack-Regeln
- Kein AP-spezifischer Inhalt

### `prep-{nn}-*.md`

**prep-01-env-coverage.md:**
- Stack-Startsequenz (`make up`, `make setup`)
- Coverage erzeugen (`make test-integration`, `run_in_background: true`)
- Ausgabe: `artifacts/layer3/coverage.xml`

**prep-02-analysis.md:**
- Liest `coverage.xml` (ggf. Ergebnis von `make crap-report` einlesen)
- Erstellt `docs/component-integration-coverage_full_analysis.md`
- Alle Analyseschritte (Gesamtüberblick, CRAP-Ranking, Klassifikation, Gap-Analyse, Diff-Vorschläge)

**prep-03-impl-plan.md:**
- Liest die frisch erstellte Analysis
- Erstellt `docs/component-integration-coverage_full_impl_plan.md`
- Definiert Gruppen A/B/C, AP-Struktur mit Status-Blocks
- Erstellt gleichzeitig die AP-Prompt-Dateien in `docs/coverage-iteration/`

### `ap-{gruppe}-{nn}-{name}.md`

Jede AP-Datei hat zwei explizite Phasen:

```
## Phase 1 — Skelett (parallelisierbar)
## Phase 2 — Ausführung (sequenziell)
```

**Phase 1 — Skelett:**
- Zielklasse, Methode, CRAP/cx
- Konstruktor-Verifikation (Quellcode-Pfad + erwartete Argumente)
- PHP-Testskelett (Klasse, leere Testmethoden, Imports)
- Keine Testausführung in dieser Phase

**Phase 2 — Ausführung:**
- Einzeltest-Befehl (korrekte Container-Pfade)
- Iteratives Fixing: Root Cause lesen → gezielt fixen → wiederholen
- Verifikations-Assertion
- Status auf ✅ setzen in `_impl_plan.md`

### `post-01-finalize.md`

- `pgrep -a phpunit` Guard
- `make test-integration` (`run_in_background: true`)
- Ratchet-Werte aktualisieren in `docs/testing-bigpicture.md`
- Dokumenten-Konsistenzprüfung:
  - `CLAUDE.md` — Stack-Regeln, Make-Targets, Layer-Tabelle noch aktuell?
  - `README.md` — Einstieg, Voraussetzungen, Testaufruf noch korrekt?
  - `docs/testing-bigpicture.md` — FM-Tabelle, Abdeckungsmatrix, Ratchet-Stand
- Commit (GPG-signiert, alle APs erwähnen)

---

## Parallelisierungsstrategie

```
prep-01 → prep-02 → prep-03
                         ↓
         ap-a-01 [Skelett]  ap-a-02 [Skelett]  ap-a-03 [Skelett]  ...parallel...
                         ↓
         ap-a-01 [Ausführung] → ap-a-02 [Ausführung] → ...sequenziell...
                         ↓
         ap-b-01 [Skelett]  ap-b-02 [Skelett]  ...parallel...
                         ↓
         ap-b-01 [Ausführung] → ...sequenziell...
                         ↓
         post-01
```

Alle Gruppen arbeiten auf demselben initialen Coverage-Snapshot (kein Re-Analyse
zwischen Gruppen). CRAP-Werte können nach AP-Abschlüssen sinken — das ist erwartet
und ändert nichts an der Prioritätsreihenfolge innerhalb der Gruppe.

---

## Skripting-Empfehlung

**Neues Makefile-Target: `make crap-report`**

Zweck: Deterministisches XML-Parsing von `artifacts/layer3/coverage.xml`.  
Ausgabe: Sortierte CRAP-Tabelle (Rang | CRAP | Paket | Klasse | Methode | cx | Sichtbarkeit).

Nutzen:
- Claude muss `coverage.xml` nicht mehr direkt im Kontext halten
- `prep-02-analysis.md` liest nur noch die kompakte Tabellen-Ausgabe
- Das Script ist unabhängig testbar und reproduzierbar

**Implementierungssprache: PHP** (nicht Shell)

Shell-XML-Parsing (`xmllint --xpath`) scheidet aus:
- Fragil bei Namespaces in PHPUnit-Coverage-XML
- CRAP-Arithmetik (cx²+cx) ohne nativen Float-Support in Bash umständlich
- Mehrspaltige Sortierung fehleranfällig
- Schwer wartbar

PHP ist die richtige Wahl:
- Projektsprache — kein Kontextwechsel, gleiche Konventionen
- `SimpleXML` / `DOMXPath` macht XML-Traversal robust und lesbar
- Arithmetik und Sortierung (`usort`) trivial
- Erweiterbar (Paketfilter, Sichtbarkeits-Spalte, Top-N-Limit)
- Passt zum etablierten Muster: `podman-compose exec webtrees php ...`

```makefile
crap-report: ## CRAP-Score-Report aus artifacts/layer3/coverage.xml
    podman-compose exec webtrees php /tests/scripts/crap-report.php
```

Skript: `scripts/crap-report.php` im Repo-Root (Container-Pfad `/tests/scripts/crap-report.php`
zu verifizieren — abhängig davon, ob `scripts/` gemountet ist).

---

## Offene Fragen

| Thema | Status | Nächster Schritt |
|---|---|---|
| Agenten | Zurückgestellt | Erst nach erster Iteration mit neuem Prompt-System bewerten |
| MCP | Offen | Kandidat: Coverage-Analyse-Server (parst XML, gibt CRAP-Daten als strukturierte API zurück). Aufwand für dieses Projekt vermutlich nicht gerechtfertigt — Scripting-Lösung reicht. |

---

## Constraints (dürfen nicht verloren gehen)

Diese Regeln müssen in jeder Prompt-Datei präsent sein, die Testausführung auslöst:

| Constraint | Quelle | Wo präsent |
|---|---|---|
| `pgrep -a phpunit` vor jedem Lauf | CLAUDE.md | `prep-01`, `post-01`, jede AP-Phase-2 |
| Lang laufende Tests: `run_in_background: true` | CLAUDE.md | `prep-01`, `post-01` |
| Kein `timeout`-Parameter | CLAUDE.md | `prep-01`, `post-01` |
| `make up` (nie `make _compose-up`) | Prompt-Erfahrung | `prep-01`, `entry.md` |
| Konstruktor-Verifikation vor Skelett | Prompt-Erfahrung | Jede AP-Phase-1 |
| Kein Commit vor allen APs ✅ + Exit 0 | Prompt-Erfahrung | `post-01` |
| Layer-3 für alle neuen Tests | CLAUDE.md | `prep-03`, `entry.md` |
| GPG-signierte Commits | CLAUDE.md | `post-01` |

---

## Nächste Schritte (nicht Teil dieses Dokuments)

1. `make crap-report`-Script und Makefile-Target erstellen
2. Verzeichnis `docs/coverage-iteration/` anlegen
3. `entry.md` und `prep-01` bis `prep-03` erstellen
4. `post-01-finalize.md` erstellen
5. **Dokumenten-Konsistenz herstellen** (einmalig als Teil des Refactorings):
   - `CLAUDE.md` auf neue Prompt-Struktur (`docs/coverage-iteration/`) aktualisieren
   - `README.md` auf Vollständigkeit und Aktualität prüfen
   - `docs/testing-bigpicture.md` auf aktuellen Ratchet-Stand und FM-Tabelle prüfen
6. Neue Coverage-Iteration starten: Session öffnen, `entry.md` lesen, `prep-01` ausführen
   → `prep-03` erzeugt automatisch die AP-Dateien für die aktuelle Iteration
