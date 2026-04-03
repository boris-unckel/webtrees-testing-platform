<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# sample-impl-plan.md — Strukturvorlage (fiktive Beispieldaten)

> Dieses Dokument zeigt Struktur und Tiefe des Implementierungsplans.
> Alle Daten sind fiktiv. Es wird **nicht** überschrieben.
> Vorlage für `prep-03-impl-plan.md`.

---

## Gesamtstatus

| AP | Titel | Status | Ergebnis |
|---|---|---|---|
| AP1 | RightToLeftSupport Bootstrap-Test | ✅ ABGESCHLOSSEN | 5 Tests, 12 Assertions, Exit 0 |
| AP2 | SearchGeneralPage::handle | 🔄 IN ARBEIT | — |
| AP3 | ReportHtmlTextBox::render | ⬜ OFFEN | — |

---

## Ausgangslage

| Metrik | Wert |
|---|---|
| Anweisungsüberdeckung | 19,8% (8.716 / 44.066) |
| Methodenüberdeckung | 17,7% (787 / 4.441) |
| Testklassen | 21 (296 Tests, 899 Assertions) |

---

## Stack-Regeln

- `make up` (nie `make _compose-up`) → `make setup`
- Lang laufende Tests: `run_in_background: true`, kein `timeout`-Parameter
- `pgrep -a phpunit` vor jedem neuen Testlauf
- Niemals parallele Testläufe

### Container-Pfade

```bash
# PHPUnit-Konfiguration:
/tests/layer3-integration/phpunit-integration.xml

# Testdateien:
/tests/layer3-integration/tests/MeineTestklasse.php

# Einzeltest-Befehl:
podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'MeineTestklasse' \
  /tests/layer3-integration/tests/MeineTestklasse.php
```

---

## AP1 — RightToLeftSupport

**Status:** ✅ ABGESCHLOSSEN  
**Abgeschlossen:** 2026-04-03  
**Ergebnis:** 5 Tests, 12 Assertions, Exit 0

| | |
|---|---|
| Klasse | `RightToLeftSupport` |
| Methode | `spanLtrRtl` |
| CRAP | 6.972 |
| cx | 83 |
| Paket | (root) |
| Quellpfad | `upstream/webtrees/app/RightToLeftSupport.php` |

Bootstrap-only. Konstruktor ohne Parameter. Testklasse: `RightToLeftSupportIntegrationTest`.

---

## AP2 — SearchGeneralPage

**Status:** 🔄 IN ARBEIT  
**Abgeschlossen:** —  
**Ergebnis:** —

| | |
|---|---|
| Klasse | `SearchGeneralPage` |
| Methode | `handle` |
| CRAP | 1.722 |
| cx | 41 |
| Paket | Http\RequestHandlers |
| Quellpfad | `upstream/webtrees/app/Http/RequestHandlers/SearchGeneralPage.php` |

DB-abhängig: benötigt `createTreeWithGedcom()`. FM-S03.

---

## AP3 — ReportHtmlTextBox

**Status:** ⬜ OFFEN  
**Abgeschlossen:** —  
**Ergebnis:** —

| | |
|---|---|
| Klasse | `ReportHtmlTextBox` |
| Methode | `render` |
| CRAP | 2.256 |
| cx | 47 |
| Paket | Report |
| Quellpfad | `upstream/webtrees/app/Report/ReportHtmlTextBox.php` |

Bootstrap-only. Kein DB-Zugriff, reines String-Rendering.
