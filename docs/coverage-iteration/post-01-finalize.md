<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# post-01 — Abschluss: Voll-Lauf, Ratchet, Konsistenz, Commit

Voraussetzung: Alle APs im Implementierungsplan sind ✅.

---

## Schritt 1 — Voll-Lauf

```bash
# Sicherstellen, dass kein Testprozess läuft
pgrep -a phpunit && echo "Warten oder per kill beenden"

# run_in_background: true — kein timeout-Parameter
make test-integration
```

Auf die Fertigmeldung warten.

**Erwartetes Ergebnis:** Exit 0, alle Tests grün.

Falls Tests rot: Fehler analysieren, gezielt fixen, Voll-Lauf wiederholen.
Kein Commit bei rotem Voll-Lauf.

---

## Schritt 2 — Ratchet-Werte aktualisieren

Aus der frischen `artifacts/layer3/coverage.xml` die aktuellen Werte lesen:
- Anweisungsüberdeckung: X.X% (covered / total)
- Methodenüberdeckung: X.X% (covered / total)
- Testanzahl: N Tests, M Assertions, N Testklassen

In `docs/testing-bigpicture.md` aktualisieren:

**Abschnitt „Ist-Stand (Teststufe 2, Stand: YYYY-MM-DD, nach AP1–APn)":**
- Datum auf heute setzen
- Baseline-Verweis auf vorherigen Stand aktualisieren
- Neue Werte eintragen (Anweisungsüberdeckung, Methodenüberdeckung, Testanzahl)
- Pakete mit >50%-Coverage und 0%-Coverage prüfen — wenn neue Pakete hinzugekommen

Falls FM-Tabelle oder Abdeckungsmatrix durch neue Tests erweitert wurde:
entsprechende Zeilen in `docs/testing-bigpicture.md` aktualisieren (Diff-Vorschläge
aus `prep-02` Abschnitt 2.6 als Vorlage).

---

## Schritt 3 — Dokumenten-Konsistenzprüfung

### CLAUDE.md

Prüfen: Sind Stack-Regeln, Make-Targets und die Layer-Tabelle noch aktuell?
Neues Target `crap-report` ist bereits eingetragen — kein weiterer Handlungsbedarf
sofern kein anderer Stack-Befehl geändert wurde.

### README.md

Prüfen: Einstieg, Schnellstart, Teststufen-Tabelle, Container-Liste.
Typisch: kein Handlungsbedarf (keine strukturellen Änderungen pro Coverage-Iteration).

### docs/testing-bigpicture.md

Bereits in Schritt 2 aktualisiert. Abschließend prüfen:
- Endekriterien (Ratchet-Basis) korrekt?
- FM-Abdeckungsmatrix vollständig?
- Versions-Footer am Ende der Datei auf heute gesetzt?

---

## Schritt 4 — Commit

```bash
# Änderungen prüfen
git status
git diff --staged

# Commit (GPG-signiert)
# Commit-Nachricht enthält alle umgesetzten APs (z. B. "AP1–AP15")
# und die neuen Coverage-Werte
```

Commit-Inhalt:
- `layer3-integration/tests/*.php` — neue/erweiterte Testdateien
- `docs/component-integration-coverage_full_analysis.md` — neue Analyse
- `docs/testing-bigpicture.md` — aktualisierter Ratchet-Stand
- `docs/coverage-iteration/ap-*.md` — AP-Dateien dieser Iteration (Status ✅)

Beispiel-Commit-Nachricht:

```
test(layer3): Coverage-Erweiterung Voll-Lauf — AP1–APn abgeschlossen

N Tests, M Assertions (vorher: N' Tests, M' Assertions).
Anweisungsüberdeckung: X.X% (vorher: Y.Y%).
Methodenüberdeckung: X.X% (vorher: Y.Y%).

AP1: KlasseA — methodA
AP2: KlasseB — methodB
...

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
```
