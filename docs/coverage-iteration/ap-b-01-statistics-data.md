<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP B-01 — StatisticsData (usersLoggedIn / centuryName)

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 4 Tests, 8 Assertions, Exit 0

---

## Ziel

| | |
|---|---|
| Klassen | `StatisticsData` |
| Methoden | `centuryName` (private, CRAP 600/cx=24), `usersLoggedInQuery` (private, CRAP 420/cx=20) |
| Öffentliche Wrapper | `usersLoggedIn()`, `usersLoggedInList()` + Statistik-Methode, die `centuryName` intern ruft |
| Paket | (root) |
| Quellpfad | `upstream/webtrees/app/StatisticsData.php` |

**Besonderheit:** Beide Zielmethoden sind `private`. Coverage nur über öffentliche API.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/StatisticsData.php` — `__construct`:
- `Tree $tree`
- `UserService $user_service`

Für `usersLoggedIn()`: ruft `usersLoggedInQuery('nolist')` intern.
Für `centuryName()`: wird von `statsMarriageDecades()` o.ä. Methode intern gerufen (in der Analyse
den konkreten öffentlichen Aufrufer ermitteln via `grep centuryName`).

### PHP-Testskelett

Erstelle `layer3-integration/tests/StatisticsDataIntegrationTest.php`.

Basis: `MysqlTestCase` + `createTreeWithGedcom()` (wegen `usersLoggedIn` → UserService-DB).

Leere Testmethoden:
- `testUsersLoggedIn`: Smoke — `usersLoggedIn()` gibt String zurück
- `testUsersLoggedInList`: Smoke — `usersLoggedInList()` gibt String zurück
- `testCenturyNameViaCenturyStats`: Smoke — öffentliche Methode, die `centuryName` intern ruft

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'StatisticsDataIntegrationTest' \
  /tests/layer3-integration/tests/StatisticsDataIntegrationTest.php
```

### Iteratives Fixing

Root Cause aus Fehlerausgabe lesen → gezielt fixen → Einzeltest wiederholen.

Besonderheit: `usersLoggedIn()` prüft `Auth::isAdmin()` — ggf. Admin-Session nötig oder
Auth-Kontext mocken. Alternativ: ohne Auth testen (gibt "No signed-in users" zurück).

### Verifikation

- `usersLoggedIn()` und `usersLoggedInList()` geben nicht-null String zurück
- Öffentliche Statistik-Methode (die `centuryName` ruft) gibt nicht-leeres Array/String zurück
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
