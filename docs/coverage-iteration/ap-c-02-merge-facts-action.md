<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP C-02 — MergeFactsAction

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 1 Test, 3 Assertions, Exit 0

---

## Ziel

| | |
|---|---|
| Klasse | `MergeFactsAction` |
| Methode | `handle` |
| CRAP | 240 |
| cx | 15 |
| Paket | Http/RequestHandlers |
| Quellpfad | `upstream/webtrees/app/Http/RequestHandlers/MergeFactsAction.php` |

DB-abhängig. Merged GEDCOM-Facts zweier Records.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Http/RequestHandlers/MergeFactsAction.php`

Konstruktor:
- `LinkedRecordService $linked_record_service`

`handle()` liest aus `parsedBody`:
- `xref1`: XREF des ersten Records
- `xref2`: XREF des zweiten Records (wird nach Merge gelöscht)
- `keep1[]`: Fact-IDs, die von Record 1 behalten werden

### PHP-Testskelett

Erstelle `layer3-integration/tests/MergeFactsIntegrationTest.php`.

Basis: `MysqlTestCase` + `createTreeWithGedcom()` mit zwei INDI-Records.

Leere Testmethoden:
- `testMergeIndividuals`: INDI1 + INDI2 → INDI1 bleibt, INDI2 gelöscht

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'MergeFactsIntegrationTest' \
  /tests/layer3-integration/tests/MergeFactsIntegrationTest.php
```

### Verifikation

- Response 302 Redirect nach erfolgreichem Merge
- INDI2 nicht mehr in Tree vorhanden
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
