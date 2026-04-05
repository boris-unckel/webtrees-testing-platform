<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — SEC-BOT01: UA-basierte Bot-Blockierung

**Referenz:** SEC-BOT01 | **SUT:** `app/Http/Middleware/BadBotBlocker.php`  
**Aktueller Test:** `BadBotBlockerIntegrationTest` (3 Tests: leerer UA, Bad-Robot-UA, legitimer UA)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

3 Tests decken die simpelsten Branches ab: leer → 406, bekannter Bot → 406, legitimer UA → 200. Die Middleware hat aber 8 Hauptbranches — davon sind 5 vollständig ungetestet.

---

## SUT-Kernbefunde

`BadBotBlocker::process()` hat folgende Entscheidungsstruktur:

| Branch | Bedingung | Testbar? | Bisher getestet? |
|---|---|---|---|
| B1 | `$ua === ''` → 406 | ✅ | ✅ |
| B2 | UA enthält Eintrag aus `BAD_ROBOTS` (~705 Einträge) → 406 | ✅ | ✅ (1 UA: '008') |
| B3 | UA ist Google/Bing/Yandex + DNS Rev+Fwd Valid → erlaubt | ⚠️ DNS-abhängig | ❌ |
| B3-fail | UA ist Google/Bing + DNS ungültig → 406 | ✅ (via PHP-Mock) | ❌ |
| B4 | UA ist Baidu/Seznam + DNS Rev-only Valid → erlaubt | ⚠️ DNS-abhängig | ❌ |
| B4-fail | DNS Rev-only ungültig → 406 | ✅ (via PHP-Mock) | ❌ |
| B5 | UA ist Facebook/Twitter + IP in ASN → erlaubt | ⚠️ WHOIS-abhängig | ❌ |
| B5-fail | ASN-Prüfung schlägt fehl → 406 | ✅ (NetworkService mockbar) | ❌ |
| B6 | Block-ASN: IP in gesperrtem ASN → 406 | ✅ | ❌ |
| B7 | Cookie-Heuristik: Browser ohne Cookies → Set-Cookie | ✅ | ❌ |
| B7 | Cookie-Heuristik: Browser mit Cookies → 200 | ✅ | ❌ |
| B8 | WordPress-Scanner-Pfad `/wp-*` → 406 | ✅ | ❌ |

**`BAD_ROBOTS` Liste:** ~705 UA-Substring-Muster. Aktuell nur `'008'` getestet (1 von 705).

---

## Äquivalenzklassen (EP)

### UA-Partitionen (BAD_ROBOTS — B2)

| Klasse | UA-Beispiel | Gruppe | Erwartung |
|---|---|---|---|
| EP1 | `'AhrefsBot/3.0'` | SEO-Crawler | 406 |
| EP2 | `'SemrushBot/1.2'` | SEO-Crawler | 406 |
| EP3 | `'GPTBot/1.0'` | AI-Crawler | 406 |
| EP4 | `'anthropic-ai/1.0'` | AI-Crawler | 406 |
| EP5 | `'CensysInspect'` | Security-Scanner | 406 |
| EP6 | `'facebookexternalhit/1.0'` | Social-Bot | Sonderfall (auch in DNS-Liste) |
| EP7 | `'Mozilla/5.0 Chrome/...'` | Legit Browser | Weiterleitung an Handler |

**Empfehlung:** 6–8 Stichproben aus verschiedenen Gruppen (KI, SEO, Security, Social, Monitoring) per DataProvider.

### Cookie-Heuristik (B7)

| Klasse | Cookies | Header-Anzahl | Claims Browser | Erwartung |
|---|---|---|---|---|
| EP8 | Vorhanden | egal | Ja | 200 OK |
| EP9 | Fehlen | ≤11 | Ja (Chrome/Firefox) | Set-Cookie + Refresh |
| EP10 | Fehlen | >11 | Ja | 200 OK (genug Header) |
| EP11 | Fehlen | ≤11 | Nein (kein Browser-UA) | 200 OK (suspected_bot, kein Redirect) |

### WordPress-Pfade (B8)

| Klasse | Path | Erwartung |
|---|---|---|
| EP12 | `/wp-admin/` | 406 |
| EP13 | `/wp-login.php` | 406 |
| EP14 | `/xmlrpc.php` | 406 |
| EP15 | `/` | Weitergeleitet (kein WP-Pfad) |
| EP16 | `/wp-content/themes/` | 406 (Prefix-Match) |

### DNS-Fehlerszenarien (B3/B4 — per PHP Function Mock)

| Klasse | DNS-Verhalten | Erwartung |
|---|---|---|
| EP17 | `gethostbyaddr()` gibt `false` zurück | 406 (DNS-Lookup fehlgeschlagen) |
| EP18 | Hostname nicht in valider Domain | 406 (falsche Domain) |
| EP19 | Forward-DNS gibt andere IP zurück | 406 (Round-Trip fehlgeschlagen) |

---

## Grenzwerte (BVA)

- UA-Länge: 0 (leer, B1-Grenze), 1 Zeichen, sehr lange UA
- Header-Anzahl: 11 (Heuristik-Grenze), 12 (knapp drüber), 0, 1
- BAD_ROBOTS-Substring-Länge: kürzeste Einträge sind z.B. `'aa'`, `'008'` — testen ob versehentlich legitime UAs getroffen werden

---

## Empfohlene Strategie

**ISTQB B** für:
- BAD_ROBOTS-Sampling (EP1–EP7) per DataProvider
- WordPress-Pfade (EP12–EP16) — klar spezifiziert
- Cookie-Heuristik (EP8–EP11) — deterministische Logik

**Pragmatisch C (mit PHP Function Mock)** für:
- DNS-Fehlerpfade (EP17–EP19) — via `php-mock/php-mock-phpunit`

**ASN/WHOIS-Branches:** `NetworkService` ist per DI injizierbar → direkt mockbar ohne PHP-Function-Mock:
```php
$mockNetwork = $this->createMock(NetworkService::class);
$mockNetwork->method('findIpRangesForAsn')->willReturn([]);
$blocker = new BadBotBlocker($mockNetwork);
```

**Dauerhaft ausgeklammert:** DNS-Round-Trip-Happy-Path (B3/B4 valide) — erfordert echten DNS oder Mock-DNS-Server (→ Common Abschnitt 10).

---

## Konkrete Testideen

```
// BAD_ROBOTS-Sampling (DataProvider — niedrig)
test_bad_robots_blocked_by_category(string $ua)  ← DataProvider(EP1-EP6)

// WordPress-Pfade (niedrig)
test_wordpress_paths_blocked(string $path)        ← DataProvider(EP12-EP16)
test_normal_path_not_blocked()

// Cookie-Heuristik (mittel)
test_browser_with_cookies_passes_through()
test_browser_without_cookies_gets_set_cookie_redirect()
test_request_with_many_headers_not_suspected_as_bot()

// DNS-Fehler via PHP-Mock (hoch — neue Dependency)
test_google_bot_with_invalid_dns_blocked()        ← php-mock
test_bing_bot_with_wrong_domain_blocked()         ← php-mock

// ASN-Mock (mittel — NetworkService bereits injizierbar)
test_facebook_bot_with_invalid_asn_blocked()
test_facebook_bot_with_empty_asn_ranges_blocked()
```

---

## Aufwand

| Test-Gruppe | Aufwand |
|---|---|
| BAD_ROBOTS-Sampling per DataProvider | **Niedrig** |
| WordPress-Pfade | **Niedrig** |
| Cookie-Heuristik | **Mittel** |
| ASN-Mock via NetworkService | **Mittel** |
| DNS-Fehler via php-mock | **Hoch** (neue Dev-Dependency) |

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt überein; Cookie-Check antwortet 406 (nicht 200); WordPress-Pfad-Prüfung via `str_starts_with($path, '/wp-')` deckt alle `/wp-*` ab; gewählte BAD_ROBOTS-UAs (AhrefsBot, SemrushBot, GPTBot, ClaudeBot, CensysInspect) nicht in DNS/ASN-Listen |
| P2: Soll-Design | ✅ DONE | 5 neue Tests: BAD_ROBOTS-Sampling DataProvider (EP1-EP5, 5 Einträge), WP-Pfade DataProvider (EP12–EP14/EP16, 4 Einträge), Normal-Pfad (EP15), Cookie-vorhanden (EP8), Cookie-fehlend-Chrome (EP9 → 406+set-cookie) |
| P3: Test-Coding | ✅ DONE | BadBotBlockerIntegrationTest: makeRequest()-Helper (UA+Pfad+Cookies), makeRequestWithUa() delegiert daran; +5 Testmethoden (+12 Test-Cases: 5 BAD_ROBOTS-DataProvider + 4 WP-Pfade-DataProvider + Normal-Pfad + Cookie-vorhanden + Cookie-fehlend) |
| P4: Ausführung + Fixing | ✅ DONE | 15/15 grün, 31 Assertions, kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix (spezifikationsbasiert, DNS/ASN ausgeklammert), Testentwurfsverfahren (+Äquivalenzklassen SEC-BOT01, CRAP-Zeile ohne SEC-BOT01), Abdeckungsmatrix, Endekriterien, Zusammenfassung (123 spec + 16 strukturbasiert), Changelog aktualisiert |
