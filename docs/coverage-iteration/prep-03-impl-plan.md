<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# prep-03 — Implementierungsplan und AP-Dateien erstellen

Dieser Schritt erzeugt zwei Ausgaben:

1. `docs/component-integration-coverage_full_impl_plan.md` (überschreibt bestehende Datei)
2. AP-Prompt-Dateien in `docs/coverage-iteration/` (eine Datei pro AP)

---

## Ausgabe 1 — Implementierungsplan

Struktur und Tiefe folgen `docs/coverage-iteration/sample-impl-plan.md` (fiktive Beispieldaten —
nicht überschreiben). Alle strukturellen Elemente übernehmen.

### Pflichtbestandteile

#### Status-Konzept

```
**Status:** ⬜ OFFEN | 🔄 IN ARBEIT | ✅ ABGESCHLOSSEN | ❌ BLOCKIERT
**Abgeschlossen:** —
**Ergebnis:** —
```

#### Stack-Regeln

- `make up` (nie `make _compose-up`) → `make setup`
- Lang laufende Tests: `run_in_background: true`, kein `timeout`-Parameter
- `pgrep -a phpunit` vor jedem neuen Testlauf
- Niemals parallele Testläufe

#### Korrekte Container-Pfade

```bash
# PHPUnit-Konfiguration im Container:
/tests/layer3-integration/phpunit-integration.xml

# Testdateien im Container:
/tests/layer3-integration/tests/MeineTestklasse.php

# Einzeltest-Befehl:
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'MeineTestklasse' \
  /tests/layer3-integration/tests/MeineTestklasse.php
```

#### AP-Priorisierung

Gruppe A vollständig vor Gruppe B. Gruppe B vollständig vor Gruppe C.
Innerhalb einer Gruppe: CRAP absteigend.

#### Konstruktor-Verifikation vor Skelett

Bevor ein PHP-Skelett erstellt wird: Konstruktor-Argumente aus dem webtrees-Source
verifizieren (`upstream/webtrees/app/`). Kein `new Foo()` ohne Konstruktor-Prüfung.

#### Keine Zwischencommits

Erst nach Abschluss aller APs + `make test-integration` Exit 0 committen.

---

## Ausgabe 2 — AP-Dateien erstellen

Für jeden AP aus dem Plan eine Datei in `docs/coverage-iteration/` anlegen.

### Dateiname

```
ap-{gruppe}-{nn}-{kurzname}.md
```

### Dateiinhalt-Template

```markdown
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP {Gruppe}-{nn} — {Klassenname}

**Status:** ⬜ OFFEN
**Abgeschlossen:** —
**Ergebnis:** —

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

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/{Pfad}/{ClassName}.php`

Erwartete Konstruktor-Parameter:
- `{TypA} ${paramA}` — {Beschreibung}
- `{TypB} ${paramB}` — {Beschreibung}

### PHP-Testskelett

Erstelle `layer3-integration/tests/{ClassName}IntegrationTest.php`.

Skeleton: extends MysqlTestCase (falls DB benötigt) oder direkt MysqlTestCase
(falls Bootstrap-only, kein createTreeWithGedcom() nötig).

Leere Testmethoden, korrekte Imports, SPDX-Header.

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

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

Root Cause aus Fehlerausgabe lesen → gezielt fixen → Einzeltest wiederholen.

Regeln:
- Nicht Methode tauschen, wenn der Konstruktor das Problem ist
- Nicht blind wiederholen — Fehler verstehen, dann handeln
- Keine Annahmen über Abhängigkeiten ohne Quellcode-Prüfung

### Verifikation

- Assertion: `{expectedAssertion}`
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
```

---

## Weiter

AP-Dateien sind erstellt. Jetzt:

1. **Phase 1 (Skelette)** aller APs einer Gruppe parallel ausführen
2. **Phase 2 (Ausführung)** sequenziell: ap-a-01 → ap-a-02 → ...
3. Nach Gruppe A fertig: Gruppe B starten
4. Nach allen APs ✅: → `post-01-finalize.md`
