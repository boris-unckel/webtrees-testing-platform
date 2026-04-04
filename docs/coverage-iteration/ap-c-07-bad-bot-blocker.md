<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# AP C-07 — BadBotBlocker (HTTP-Middleware)

**Status:** ✅ ABGESCHLOSSEN
**Abgeschlossen:** 2026-04-03
**Ergebnis:** 3 Tests, 6 Assertions, Exit 0 (Fix: Nyholm kein withServerParams → makeRequestWithUa mit direktem Konstruktor)

---

## Ziel

| | |
|---|---|
| Klasse | `BadBotBlocker` |
| Methode | `process` |
| CRAP | 870 |
| cx | 29 |
| Paket | Http/Middleware |
| Quellpfad | `upstream/webtrees/app/Http/Middleware/BadBotBlocker.php` |

Bootstrap-only. DNS-Lookup-Branches ausklammern — nur UA-String-Pfade testen.
CRAP 870 ist Gruppe-B-Niveau, landet aber in Gruppe C, da DNS-Branches Coverage-Tiefe begrenzen.

---

## Phase 1 — Skelett (parallelisierbar)

### Konstruktor-Verifikation

Lies: `upstream/webtrees/app/Http/Middleware/BadBotBlocker.php`

Konstruktor:
- `NetworkService $network_service`

`process()` prüft in dieser Reihenfolge:
1. UA leer → 406 Not Acceptable
2. UA in `BAD_ROBOTS` → 406
3. UA in `ROBOT_REV_FWD_DNS` oder `ROBOT_REV_ONLY_DNS` → DNS-Check (ausklammern)
4. Hard-codierte IP-Ranges prüfen
5. Weiter an `$handler`

Testbare Pfade (ohne DNS):
- Leerer UA → 406
- UA = `'Googlebot'` (wäre DNS-Branch → ausklammern oder Mock)
- UA in `BAD_ROBOTS` → 406
- Harmloser UA → weiter zu Handler

### PHP-Testskelett

Erstelle `layer3-integration/tests/BadBotBlockerIntegrationTest.php`.

Basis: `MysqlTestCase` (Bootstrap-only, kein createTreeWithGedcom()).

Leere Testmethoden:
- `testEmptyUserAgentBlocked`
- `testBadRobotUserAgentBlocked`
- `testLegitimateUserAgentPasses`

DNS-Branches als bekannte Lücke im Testkommentar dokumentieren.

Keine Testausführung in Phase 1.

---

## Phase 2 — Ausführung (sequenziell)

### Einzeltest-Befehl

```bash
pgrep -a phpunit && echo "Warten oder per kill beenden"

podman-compose exec webtrees php vendor/bin/phpunit \
  --configuration /tests/layer3-integration/phpunit-integration.xml \
  --filter 'BadBotBlockerIntegrationTest' \
  /tests/layer3-integration/tests/BadBotBlockerIntegrationTest.php
```

### Iteratives Fixing

`NetworkService` aus IoC-Container holen oder direkt instantiieren.
Request-Builder mit `HTTP_USER_AGENT` + `client-ip` als Attribute aufbauen.
Handler: minimales `RequestHandlerInterface`-Stub (gibt 200 zurück).

### Verifikation

- Leerer UA → Response-Status 406
- Bad-Robot-UA → Response-Status 406
- Normaler UA → Handler aufgerufen (200)
- Nach grünem Test: Status dieser AP-Datei auf ✅ ABGESCHLOSSEN setzen.
