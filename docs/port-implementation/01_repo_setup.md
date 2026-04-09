<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# 01 — Repo-Setup für Layer-2-Portierung

## 1. Branch-Erstellung

Alle portierten Tests landen auf einem einzigen neuen Branch im authoritativen Fork:

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees

# Sicherstellen, dass main aktuell ist
git checkout main
git pull origin main

# Portierungs-Branch erstellen
git checkout -b port-layer2-test-doubles
```

**Branch-Base:** Fork-`main` (analog Security-Audit, siehe `10_fixing_and_disclosure.md` §1).
Nicht `5349_add_tests`, nicht der volatile Clone.

## 2. WEBTREES_SOURCE konfigurieren

Damit `make test-unit` gegen den Fork-Branch läuft:

```bash
cd /home/borisunckel/phpprojects/webtrees-testing-platform
export WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees
```

**Achtung:** Der volatile Clone unter `upstream/webtrees` wird NICHT verwendet.
Stattdessen mountet der Container die Fork-Source direkt.

## 3. Validierung

Nach jeder Batch-Runde:

```bash
make test-unit
```

Erwartung:
- Alle bestehenden Tests grün (keine Regression)
- Alle neuen/geänderten Tests grün
- Testanzahl >= bisherige Anzahl (Stub-Methoden `testClass()` bleiben erhalten,
  neue Testmethoden kommen hinzu)

## 4. Referenz auf Branch `5349_add_tests`

Die Testszenarien aus `5349_add_tests` dienen als Inspiration für Test-Double-Fassungen.
Der Branch selbst wird nicht gemerged — seine Commits werden nicht cherry-picked,
sondern die Testlogik wird in neuen Test-Double-basierten Implementierungen nachgebaut.

Zum Lesen der Branch-Tests:

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
git show 5349_add_tests:tests/app/Http/RequestHandlers/AutoCompletePlaceTest.php
```

## 5. Kein automatischer Commit

Commits werden **nicht** automatisch erstellt. Der User committet manuell am Ende
der Portierungsrunde. GPG-Signatur ist Pflicht (`commit.gpgsign=true` global).

## 6. Preflight-Check

Vor Beginn der Portierung:

```bash
# 1. Fork-main ist aktuell?
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
git rev-parse --abbrev-ref HEAD   # erwarte: main oder port-layer2-test-doubles
git log --oneline -3

# 2. Container läuft?
cd /home/borisunckel/phpprojects/webtrees-testing-platform
make status

# 3. Baseline-Tests grün?
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees make test-unit
```
