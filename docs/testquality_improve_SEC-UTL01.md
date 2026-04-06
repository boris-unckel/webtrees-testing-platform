<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — SEC-UTL01: Web-Assets & Utility-Endpoints

**Referenz:** SEC-UTL01 | **SUT:** `app/Http/RequestHandlers/RobotsTxt.php`, `FaviconIco.php`, `WebmanifestJson.php`, `BrowserconfigXml.php`, `AppleTouchIconPng.php`, `AdsTxt.php`, `AppAdsTxt.php`, `Ping.php`
**Aktueller Test:** kein Test — neu anlegen
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md), [testquality_improve_common2.md](testquality_improve_common2.md)

---

## Status quo

Kein dedizierter Test für diese Handler. RobotsTxt ist sicherheitsrelevant. Die anderen Handler liefern statische Web-Assets oder Utility-Responses ohne Authentifizierung.

---

## SUT-Kernbefunde

### RobotsTxt

**DI:** `ModuleService $module_service`, `TreeService $tree_service`

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | Trees vorhanden → Disallow-Einträge für Baum-URLs | Nein |
| B2 | Kein Tree vorhanden → Minimale robots.txt | Nein |
| B3 | Module mit robots.txt-Einträgen → zusätzliche Disallow-Zeilen | Nein |

Response: 200, Content-Type `text/plain`.

### FaviconIco

Kein Konstruktor. GET → image/x-icon Response (binary). Cache-Control: public,max-age=31536000.

### WebmanifestJson

GET → application/json Response mit Web-App-Manifest.

### BrowserconfigXml

GET → XML Response für Microsoft-Browser.

### AppleTouchIconPng

GET → image/png Response.

### AdsTxt / AppAdsTxt

GET → text/plain (Werbepartner-Datei, meist leer).

### Ping

GET → leere 200-Response für Health-Check.

---

## Äquivalenzklassen (EP)

### RobotsTxt

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Mit Tree | 200, text/plain, enthält "Disallow:" |
| EP2 | Kein Tree | 200, text/plain, valide robots.txt |
| EP3 | Content-Type | Beliebige Eingabe | Content-Type === 'text/plain' |
| EP4 | Inhalt-Check | Enthält "User-agent: *" |

### Alle anderen Handler (Batch-Strategie)

| Klasse | Handler | URL | Erwarteter Content-Type |
|---|---|---|---|
| EP5 | FaviconIco | /favicon.ico | image/x-icon |
| EP6 | WebmanifestJson | /site.webmanifest | application/json |
| EP7 | BrowserconfigXml | /browserconfig.xml | application/xml oder text/xml |
| EP8 | AppleTouchIconPng | /apple-touch-icon.png | image/png |
| EP9 | AdsTxt | /ads.txt | text/plain |
| EP10 | AppAdsTxt | /app-ads.txt | text/plain |
| EP11 | Ping | /ping | 200, leere Response |

---

## Batch-Strategie

**DataProvider über alle 8 Handler** (URL + erwarteter Content-Type):

```php
public static function utilityHandlerProvider(): array {
    return [
        'favicon'        => [FaviconIco::class,        'image/x-icon'],
        'webmanifest'    => [WebmanifestJson::class,    'application/json'],
        'browserconfig'  => [BrowserconfigXml::class,  'application/xml'],
        'apple-touch'    => [AppleTouchIconPng::class,  'image/png'],
        'ads'            => [AdsTxt::class,             'text/plain'],
        'app-ads'        => [AppAdsTxt::class,          'text/plain'],
        'ping'           => [Ping::class,               ''],
        'robots'         => [RobotsTxt::class,          'text/plain'],
    ];
}
```

RobotsTxt benötigt DI (`ModuleService`, `TreeService`). Alle anderen: `new HandlerClass()` ohne DI.
Kein Auth nötig — alle Handler sind öffentlich zugänglich.

---

## Empfohlene Strategie

**Neue Testklasse:** `UtilityEndpointsIntegrationTest extends MysqlTestCase`

- DataProvider-Smoke für Content-Type aller 8 Handler (GET → 200)
- RobotsTxt zusätzlich: Inhalt-Check "User-agent: *" und "Disallow:"
- Aufwand niedrig: kein Auth, kein Tree-Attribut nötig

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | SUT gelesen: alle 8 Handler, DI-Analyse |
| P2: Soll-Design | ✅ | DataProvider-Batch + 4 EP-Methoden |
| P3: Test-Coding | ✅ | `UtilityEndpointsIntegrationTest` (10 Tests) |
| P4: Ausführung + Fixing | ✅ | 10/10 grün |
| P5: Big-Picture | ✅ | testing-bigpicture.md aktualisiert |
