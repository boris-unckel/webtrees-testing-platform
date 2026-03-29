# Implementierungsplan — Sicherheitstests webtrees-Upstream (Distribution + Wizard)

> **Zweck:** Vollständiger, eigenständiger Implementierungsplan für Sicherheitstests, die prüfen,
> ob die Schutzmechanismen des **webtrees-Upstream-Codes** in einer produktionsidentischen Instanz
> greifen. Enthält alle Analyse-Ergebnisse, Infrastruktur-Spezifikationen, Testfall-Definitionen
> und ISTQB-konforme Strukturelemente.
>
> **Pipeline-Position:** Idee → Analyseprompt (abgeschlossen) → Planprompt (abgeschlossen) → **Plan (you are here)** → Implementierung → Ergebnisdokumentation
>
> **Datum:** 2026-03-28
>
> **ISTQB-Terminologie:** Glossar de_DE v4.7.1 durchgängig.
>
> **Statuskonzept:** Jede Phase und jeder Prüfpunkt trägt einen Status. Bei Systemabstürzen
> oder Unterbrechungen kann die Implementierung anhand dieses Dokuments nahtlos fortgesetzt werden.

---

## Statuskonzept

### Phasen-Status

| Status | Bedeutung |
|--------|-----------|
| **Offen** | Noch nicht begonnen |
| **In Arbeit** | Implementierung läuft |
| **Implementiert** | Code geschrieben, noch nicht verifiziert |
| **Verifiziert** | Tests laufen grün (oder rot bei dokumentiertem Upstream-Befund) |
| **Blockiert** | Abhängigkeit nicht erfüllt |

### Prüfpunkt-Status

| Status | Bedeutung |
|--------|-----------|
| **Offen** | Test noch nicht implementiert |
| **Implementiert** | Testcode geschrieben |
| **Grün** | Test läuft erfolgreich |
| **Rot (Upstream-Befund)** | Test schlägt fehl, Ursache im Upstream-Code, Issue erstellt |
| **Rot (Eigenfehler)** | Test schlägt fehl, Ursache in eigener Infrastruktur |

### Aktualisierungsregel

Bei jeder Statusänderung wird die betroffene Zeile in diesem Dokument aktualisiert.
Das Dokument ist die Single Source of Truth für den Implementierungsfortschritt.

---

## 1. Kontext und Architektur-Entscheidungen

### 1.1 Zwei-Track-Architektur

Die webtrees-testing-platform betreibt **zwei strikt getrennte** Test-Tracks:

| Track | Zweck | Instanz | Setup-Pfad | Container |
|-------|-------|---------|------------|-----------|
| **Fachtest** (bestehend) | Funktionale Tests, Regression, Privacy | Dev-Source (Git-Checkout, read-only Mount) | `setup-webtrees.sh` (Wizard umgangen) | `webtrees` |
| **Sicherheitstest** (neu) | Upstream-Schutzmechanismen in Produktion | **Distribution-Build** (ZIP entpackt) | **Upstream-Setup-Wizard** (Playwright) | `webtrees-security` |

**Begründung der Trennung:**
- Fachtests profitieren vom kurzen Roundtrip (Mount, kein Build) — bewährt und stabil
- Sicherheitstests müssen das **produktionsidentische** Ergebnis prüfen: Distribution + Wizard
- Vermeidung von Scheinsicherheit: Kein "drumherum bauen" um Upstream-Defizite
- Bestehende Fachtest-Infrastruktur wird **nicht angefasst**

### 1.2 Testphilosophie

**Was wir testen:** Das Ergebnis des Upstream-Produktionspfads — Distribution-Dateien,
Wizard-generierte Konfiguration, `.htaccess`, PHP-Middleware, Routing, Security-Headers.

**Was wir NICHT testen:** Unser eigenes Test-Setup (`setup-webtrees.sh`). Das gehört zum
Fachtest-Track und hat eigene Qualitätssicherung.

**Konsequenz:** Der Sicherheitstest-Container bildet ein Produktions-Deployment ab.
Der Setup-Wizard ist Teil des Testszenarios, nicht Vorbedingung.

**Whitebox-Ansatz:** Tests kennen die Upstream-Interna (Code-Pfade, Schutzmechanismen,
Middleware-Stack), prüfen aber von außen (HTTP-Requests, Dateisystem-State).

**Upstream-Befunde:** Findet ein Test eine Schwäche, bleibt er **rot**. Kein `@expectedFailure`,
kein Skip. Das Ergebnis wird als Issue bei `fisharebest/webtrees` dokumentiert.

### 1.3 ISTQB-Einordnung

| Dimension | Zuordnung |
|-----------|-----------|
| **Testart** | Sicherheitstest (nicht-funktionale Testart, ISTQB-Glossar de_DE v4.7.1) |
| **Teststufe** | Komponentenintegrationstest (Layer 3, Dateisystem) + Systemtest (Layer 4, HTTP/Playwright) |
| **Domäne** | Sicherheit (4. Domäne neben GEDCOM G, Suche/Navigation S, Privacy P) |
| **ID-Präfix** | `SEC-` (global eindeutig, keine Kollision mit G01–G23, S01–S40, P01–P29) |
| **Code-Verzeichnis** | `layer4-e2e/tests/security/` (Layer 4) + `scripts/security-filesystem-checks.sh` (Layer 3) |
| **Makefile-Target** | `make test-security` |

### 1.4 ID-Schema

Alle Prüfpunkte tragen den Domänen-Präfix `SEC-` gefolgt von einer Subkategorie und
zweistelliger Nummer. Das Schema ist konsistent mit den bestehenden Domänen (G01–G23,
S01–S40, P01–P29) und vermeidet Kollisionen:

| Subkategorie | Bedeutung | IDs |
|--------------|-----------|-----|
| `SEC-H` | `.htaccess`-Schutzschicht | SEC-H01–SEC-H06 |
| `SEC-D` | `data/index.php` Redirect-Fallback | SEC-D01–SEC-D02 |
| `SEC-C` | `config.ini.php` Wizard-Erzeugung | SEC-C01–SEC-C03 |
| `SEC-M` | Media-Zugriffskontrolle | SEC-M01–SEC-M03 |
| `SEC-PUB` | `public/`-Verzeichnis | SEC-PUB01–SEC-PUB04 |
| `SEC-W` | Setup-Wizard-Lock | SEC-W01 |
| `SEC-WZ` | Setup-Wizard-Durchlauf | SEC-WZ01–SEC-WZ04 |
| `SEC-HDR` | Security-Headers | SEC-HDR01–SEC-HDR04 |

---

## 2. Upstream-Analyse (Befunde)

### 2.1 Defense-in-Depth-Modell

webtrees implementiert drei Schutzebenen für das `data/`-Verzeichnis:

| Ebene | Mechanismus | Datei/Code | Schutzwirkung | Ausfallverhalten |
|-------|-------------|------------|---------------|------------------|
| **1. Apache** | `Require all denied` | `data/.htaccess` (statisch im Repo) | Blockiert jeden HTTP-Zugriff auf `data/` | Wenn `AllowOverride None` → wirkungslos |
| **2. PHP-Redirect** | `header('Location: ../index.php')` | `data/index.php` (statisch im Repo) | Redirect, falls `.htaccess` nicht greift | Nur für Directory-Request, nicht Datei-Pfade |
| **3. PHP-Guard** | `; <?php return; ?>` (erste Zeile) | `data/config.ini.php` (vom Wizard erzeugt) | Leere Ausgabe, falls als PHP ausgeführt | Schützt nur `config.ini.php` |

### 2.2 Setup-Wizard-Lock

**Mechanismus:** `ReadConfigIni`-Middleware (3. im Stack, vor Routing und DB):
- `file_exists(Webtrees::CONFIG_FILE)` → ja: Config laden, normaler Request
- `file_exists(...)` → nein: `SetupWizard`-Handler fängt **alle** Requests ab

**Datei:** `app/Http/Middleware/ReadConfigIni.php`
**Konstante:** `Webtrees::CONFIG_FILE` = `data/config.ini.php`

### 2.3 Setup-Wizard (6 Schritte)

| Schritt | Inhalt | View | Formularfelder |
|---------|--------|------|----------------|
| 1 | Sprachauswahl | `setup/step-1-language` | `lang` |
| 2 | Server-Checks | `setup/step-2-server-checks` | — (nur Anzeige) |
| 3 | Datenbank-Typ | `setup/step-3-database-type` | `dbtype` (default: `mysql`) |
| 4 | DB-Verbindung | `setup/step-4-database-{dbtype}` | `dbhost`, `dbport`, `dbuser`, `dbpass`, `dbname`, `tblpfx` |
| 5 | Admin-Account | `setup/step-5-administrator` | `wtname`, `wtuser`, `wtpass` (min 6 Zeichen), `wtemail` |
| 6 | Installation | Redirect bei Erfolg | `baseurl` |

**POST-Endpoint:** Einziger Handler `SetupWizard::handle()`, Step-Parameter `step` im Body.
**`createConfigFile()`:** Rendert Template `resources/views/setup/config.ini.phtml` → `file_put_contents(Webtrees::CONFIG_FILE)` — **ohne explizites `chmod`**.

### 2.4 Config-Template (exakte Ausgabe)

```ini
; <?php return; ?> DO NOT DELETE THIS LINE
dbtype="mysql"
dbhost="..."
dbport="3306"
dbuser="..."
dbpass="..."
dbname="..."
tblpfx="wt_"
base_url="..."
rewrite_urls="0"
```

Alle Werte werden mit `addcslashes($value, '"')` escaped.

### 2.5 Media-Serving

Nicht direkt per Apache. Zwei Request-Handler mit Zugriffskontrolle:
- `MediaFileDownload` → `Auth::checkMediaAccess($media)` (rollenbasiert)
- `MediaFileThumbnail` → HMAC-Signaturprüfung (`glide-key`, `md5`)

Direkter HTTP-Zugriff auf `data/media/` wird durch `.htaccess` (Ebene 1) blockiert.

### 2.6 `public/`-Handling

`PublicFiles`-Middleware (7. im Stack):
- Served statische Dateien aus `/public/` via `file_get_contents()` + Content-Type
- Path-Traversal-Schutz: `!str_contains($path, '..')`
- Kein PHP-Execution — Dateien werden als statischer Inhalt ausgeliefert

`public/index.php` ist ein Loader (`require __DIR__ . '/../index.php'`) für das empfohlene
Deployment-Modell (DocumentRoot = `/public/`). In unserem Container (DocumentRoot = `/var/www/html`)
wird es über die `PublicFiles`-Middleware als statische Datei behandelt.

### 2.7 Security-Headers

`SecurityHeaders`-Middleware (5. im Stack) setzt auf jede Response:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy` (gesetzt)
- `Content-Security-Policy`
- `Strict-Transport-Security` (nur bei HTTPS)

### 2.8 Statische Schutzdateien in der Distribution

| Datei | In Distribution | Inhalt |
|-------|-----------------|--------|
| `data/.htaccess` | **Ja** (statisch, nicht generiert) | `Require all denied` (Apache 2.4) + Legacy (Apache 2.2) |
| `data/index.php` | **Ja** (statisch) | `header('Location: ../index.php')` |
| `public/index.php` | **Ja** (statisch) | `require __DIR__ . '/../index.php'` |
| `data/config.ini.php` | **Nein** (vom Wizard erzeugt) | INI mit PHP-Guard |

### 2.9 Distribution-Build-Prozess

`composer webtrees:build` im Upstream:
1. `rm -Rf webtrees/` — Clean
2. `git archive --prefix=webtrees/ HEAD --format=tar | tar -x` — Export (`.gitattributes` `export-ignore` wirkt)
3. `composer install --no-dev --quiet` — Prod-Dependencies
4. `cp -r vendor/ webtrees/vendor/` — Vendor in Build-Dir
5. Laravel-Workaround (Conditionable.php)
6. `php index.php compile-po-files` — PO → PHP-Übersetzungen
7. `for FILE in resources/lang/*/messages.php; do cp $FILE webtrees/$FILE; done`
8. `zip --quiet --recurse-paths --move -9 webtrees-$(git describe).zip webtrees`

**Ausgeschlossen** (`.gitattributes` `export-ignore`):
`tests/`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`,
`webpack.mix.js`, `resources/css/`, `resources/js/`, `.github/`, `phpunit.xml.dist`,
`phpstan.neon.dist`, `phpstan-baseline.neon`, `.coveralls.yml`, `.travis.yml`

**Voraussetzungen:** `composer`, `node` + `npm` (Asset-Compilation), `git`, `zip`, `perl` (optional)

### 2.10 Upstream-Middleware-Stack (Reihenfolge)

1. `ErrorHandler`
2. `EmitResponse`
3. **`ReadConfigIni`** — Setup-Wizard-Lock
4. `BaseUrl`
5. **`SecurityHeaders`** — X-Frame-Options, CSP, HSTS
6. `HandleExceptions`
7. **`PublicFiles`** — Statische Dateien, Path-Traversal-Schutz
8. `ClientIp`
9. `ContentLength`
10. `CompressResponse`
11. `BadBotBlocker`
12. `UseDatabase`
13–25. Session, Language, Theme, Routing, etc.

---

## 3. Bedrohungsmodell

### 3.1 Angriffsvektoren

| ID | Vektor | Beschreibung | Betroffene Ressource |
|----|--------|--------------|----------------------|
| V1 | Direkter HTTP-Zugriff auf `data/` | `GET /data/config.ini.php` → DB-Credentials geleakt | `data/config.ini.php` |
| V2 | Direkter HTTP-Zugriff auf `data/media/` | Mediendateien ohne Zugriffskontrolle abrufbar | `data/media/*` |
| V3 | Datei-Permissions zu offen | `config.ini.php` world-readable → Credential-Leak | `data/config.ini.php` |
| V4 | Directory Listing | Apache zeigt Verzeichnisinhalt → Informationsleck | Alle Verzeichnisse |
| V5 | Setup-Wizard nach Setup erreichbar | Erneuter Wizard-Durchlauf → Admin-Takeover | Setup-Wizard-Route |
| V6 | Fehlende `.htaccess`-Dateien | Schutzdateien fehlen oder wirkungslos → V1/V2 | `.htaccess` |
| V7 | Path-Traversal über `public/` | `/public/../data/config.ini.php` → Dateizugriff | `public/`-Routing |
| V8 | Fehlende Security-Headers | Clickjacking, MIME-Sniffing, Referrer-Leak | HTTP-Responses |

### 3.2 Scope-Abgrenzung

**In Scope:** Dateisystem nach Wizard-Setup, HTTP-Erreichbarkeit, Apache `.htaccess`,
PHP-Middleware, Setup-Wizard-Lock, Security-Headers, Wizard-erzeugte `config.ini.php`.

**Nicht in Scope:** SQL-Injection, XSS, CSRF, TLS/HTTPS, Netzwerk-Isolation,
Fachtest-Track, Credential-Stärke in Testumgebung.

---

## 4. Feature-Matrix: Sicherheit (SEC)

> 4. Domäne neben GEDCOM Import/Export (G01–G23), Suche & Navigation (S01–S40) und
> Datenschutz & Zugriffskontrolle (P01–P29). Abgeleitet aus Code-Analyse des Upstream
> Defense-in-Depth-Modells, der Middleware-Kette und des Setup-Wizards.
>
> Teststufen: 2 = Komponentenintegrationstest (Dateisystem), 3 = Systemtest (HTTP/Playwright).

### 4.1 Priorisierung (risikobasiert)

| Priorität | Kriterium | IDs | Anzahl |
|-----------|-----------|-----|--------|
| **Hoch** | Direkter Credential-/Daten-Leak oder Admin-Takeover | SEC-H01–SEC-H06, SEC-C01–SEC-C03, SEC-W01, SEC-WZ01–SEC-WZ04 | 14 |
| **Mittel** | Defense-in-Depth, Zugriffskontrolle | SEC-D01–SEC-D02, SEC-M01–SEC-M03, SEC-PUB01–SEC-PUB04 | 8 |
| **Niedrig** | Informationsleck, Härtungsempfehlung | SEC-HDR01–SEC-HDR04 | 4 |

### 4.2 Feature-Matrix (mit Implementierungsstatus)

| # | Feature | Abgeleitete Anforderung | Upstream-Mechanismus | Teststufe | Prio | Status |
|---|---------|-------------------------|----------------------|-----------|------|--------|
| SEC-H01 | `.htaccess` Existenz | `data/.htaccess` in Distribution vorhanden | Statische Datei im Repo | 2 | Hoch | Grün |
| SEC-H02 | `.htaccess` Inhalt | Enthält `Require all denied` (Apache 2.4) + Legacy | Statischer Dateiinhalt | 2 | Hoch | Grün |
| SEC-H03 | HTTP-Zugriff `data/` blockiert | `GET /data/` → HTTP 403 | `.htaccess` + `AllowOverride All` | 3 | Hoch | Grün |
| SEC-H04 | HTTP-Zugriff `config.ini.php` blockiert | `GET /data/config.ini.php` → 403 (nicht 200, kein Dateiinhalt) | `.htaccess` blockiert gesamten Pfad | 3 | Hoch | Grün |
| SEC-H05 | HTTP-Zugriff `data/media/` blockiert | `GET /data/media/` → 403 | `.htaccess` gilt für Unterverzeichnisse | 3 | Hoch | Grün |
| SEC-H06 | URL-Encoding umgeht `.htaccess` nicht | Encoding-Varianten → jeweils 403 | Apache dekodiert vor `.htaccess`-Prüfung | 3 | Hoch | Grün |
| SEC-D01 | `data/index.php` Existenz | Datei in Distribution vorhanden | Statische Datei im Repo | 2 | Mittel | Grün |
| SEC-D02 | `data/index.php` Redirect-Logik | Enthält `header('Location: ../index.php')` | Statischer Dateiinhalt | 2 | Mittel | Grün |
| SEC-C01 | Config PHP-Guard | Wizard-erzeugte `config.ini.php` hat `; <?php return; ?>` als erste Zeile | `SetupWizard::createConfigFile()` → Template | 2 | Hoch | Grün |
| SEC-C02 | Config DB-Credentials | `config.ini.php` enthält dbhost, dbuser, dbpass, dbname | Wizard-Template interpoliert Formularwerte | 2 | Hoch | Grün |
| SEC-C03 | Config Datei-Permissions | Ist-Zustand dokumentieren; world-readable = Upstream-Befund | `file_put_contents()` ohne `chmod` | 2 | Hoch | Rot (Upstream-Befund) |
| SEC-M01 | Direkter Media-Zugriff blockiert | `GET /data/media/<datei>` → 403 | `.htaccess` blockiert `data/` komplett | 3 | Mittel | Grün |
| SEC-M02 | Media-Route ohne Auth | App-Route als Visitor → 403 oder Redirect zu Login | `Auth::checkMediaAccess()` | 3 | Mittel | Grün |
| SEC-M03 | Media-Route mit Auth | App-Route als Member → 200 | `MediaFileDownload` nach Auth-Check | 3 | Mittel | Grün |
| SEC-PUB01 | `public/index.php` Existenz | Datei in Distribution vorhanden | Statische Datei im Repo | 2 | Mittel | Grün |
| SEC-PUB02 | `public/index.php` keine PHP-Execution | `GET /public/index.php` → statischer Inhalt (Source sichtbar) | `PublicFiles` liefert via `file_get_contents()` | 3 | Mittel | Grün |
| SEC-PUB03 | Kein Directory Listing `/public/` | `GET /public/` → kein Datei-Listing | `PublicFiles` matched nur Dateien | 3 | Mittel | Grün |
| SEC-PUB04 | Path-Traversal blockiert | `GET /public/../data/config.ini.php` → kein Dateiinhalt | `!str_contains($path, '..')` | 3 | Mittel | Grün |
| SEC-W01 | Wizard nach Setup gesperrt | `GET` auf Setup-URL → kein Setup-Formular | `ReadConfigIni`: `file_exists()` → normaler Handler | 3 | Hoch | Grün |
| SEC-WZ01 | Wizard erscheint bei Erstaufruf | `GET /` auf frischer Instanz → Setup-Formular | `ReadConfigIni`: kein `config.ini.php` → `SetupWizard` | 3 | Hoch | Grün |
| SEC-WZ02 | Wizard prüft Schreibrechte | Schritt 2 zeigt Erfolg (data/ beschreibbar) | `SetupWizard::checkFolderIsWritable()` | 3 | Hoch | Grün |
| SEC-WZ03 | Wizard erzeugt `config.ini.php` | Datei existiert nach Wizard-Abschluss | `SetupWizard::createConfigFile()` | 2+3 | Hoch | Grün |
| SEC-WZ04 | Wizard sperrt sich selbst | Kein erneuter Setup nach Abschluss | `ReadConfigIni` → `file_exists()` | 3 | Hoch | Grün |
| SEC-HDR01 | `X-Content-Type-Options` | Header = `nosniff` | `SecurityHeaders`-Middleware | 3 | Niedrig | Grün |
| SEC-HDR02 | `X-Frame-Options` | Header = `SAMEORIGIN` oder `DENY` | `SecurityHeaders`-Middleware | 3 | Niedrig | Grün |
| SEC-HDR03 | `Referrer-Policy` | Header gesetzt (nicht leer) | `SecurityHeaders`-Middleware | 3 | Niedrig | Grün |
| SEC-HDR04 | Server-Banner | Kein detaillierter Apache-Versionsstring | Apache-Konfiguration (nicht Upstream-PHP) | 3 | Niedrig | Rot (Deployment-Empfehlung) |

**Anmerkung SEC-D01/SEC-D02:** Der HTTP-Redirect ist in unserem Stack nicht erreichbar, weil
`.htaccess` vorher greift. Das ist korrektes Verhalten. Nicht als HTTP-Test, nur als
Dateisystem-Assertion.

**Anmerkung SEC-C03:** Upstream-Wizard nutzt `file_put_contents()` **ohne `chmod`**. Wenn
world-readable → potenzieller Upstream-Befund (kein Defense-in-Depth auf Dateisystem-Ebene).

**Anmerkung SEC-HDR04:** `ServerTokens`/`ServerSignature` ist Apache-Konfiguration, nicht
Upstream-PHP. Einordnung als Deployment-Empfehlung, nicht Upstream-Schutzmechanismus.

### 4.3 Testfall-Verteilung nach Teststufe

| Teststufe | IDs | Anzahl |
|-----------|-----|--------|
| Teststufe 2 — Komponentenintegrationstest (Dateisystem) | SEC-H01, SEC-H02, SEC-D01, SEC-D02, SEC-C01–SEC-C03, SEC-PUB01, SEC-WZ03 | 9 |
| Teststufe 3 — Systemtest (HTTP/Playwright) | SEC-H03–SEC-H06, SEC-M01–SEC-M03, SEC-PUB02–SEC-PUB04, SEC-W01, SEC-WZ01–SEC-WZ04, SEC-HDR01–SEC-HDR04 | 18 |
| Beide Teststufen | SEC-WZ03 | 1 |
| **Summe** | | **26** |

### 4.4 Prioritätsverteilung

| Priorität | Anzahl | Anteil |
|-----------|--------|--------|
| Hoch | 14 | 54% |
| Mittel | 8 | 31% |
| Niedrig | 4 | 15% |

---

## 5. Whitebox-Angriffsmuster

### 5.1 URL-Encoding-Varianten (für SEC-H06)

Alle Varianten gegen `/data/config.ini.php` und `/data/media/`:

| Muster | URL | Erwartung |
|--------|-----|-----------|
| Direkt | `/data/config.ini.php` | 403 |
| Percent-Encoding | `/data/config%2Eini%2Ephp` | 403 |
| Double-Encoding | `/data/config%252Eini%252Ephp` | 403 |
| Path-Segment `..` | `/public/../data/config.ini.php` | 403 oder kein Dateiinhalt |
| Case-Variation | `/Data/Config.ini.php` | 403 (Linux: 404 akzeptabel, da Case-sensitiv) |
| Trailing-Slash | `/data/config.ini.php/` | 403 |
| Null-Byte (historisch) | `/data/config.ini.php%00` | 403 |
| Backslash | `/data\config.ini.php` | 403 |
| Unicode-Normalisierung | `/data/config.ini%E2%80%8B.php` (Zero-Width Space) | 403 |

### 5.2 Path-Traversal-Varianten (für SEC-PUB04)

| Muster | URL | Erwartung |
|--------|-----|-----------|
| Einfach | `/public/../data/config.ini.php` | Kein Dateiinhalt |
| Encoded Dots | `/public/%2e%2e/data/config.ini.php` | Kein Dateiinhalt |
| Double-Encoded | `/public/%252e%252e/data/config.ini.php` | Kein Dateiinhalt |
| Mixed | `/public/..%2fdata/config.ini.php` | Kein Dateiinhalt |
| Overlong UTF-8 | `/public/%c0%ae%c0%ae/data/config.ini.php` | Kein Dateiinhalt |

### 5.3 Setup-Wizard-Bypass-Versuche (für SEC-W01)

| Muster | Methode | Erwartung |
|--------|---------|-----------|
| Direkte POST an Wizard | `POST /setup` mit Step-Daten | Kein Wizard-Verhalten |
| GET mit Step-Parameter | `GET /?step=6` | Normales Routing |
| Anderer HTTP-Verb | `PUT /setup` | Kein Wizard |

---

## 6. Infrastruktur-Spezifikation

### 6.1 Distribution-Build-Prozess

```
webtrees-upstream/webtrees/          (Git-Checkout)
        |
        v  composer webtrees:build   (Makefile-Step, Host oder Build-Container)
webtrees-<version>.zip               (Distribution)
        |
        v  Containerfile.security    (entpacken + Apache + PHP)
webtrees-security Container          (produktionsidentisch)
        |
        v  Playwright: Wizard        (6 Schritte automatisiert)
Lauffaehige Instanz                  (wie Produktion nach Ersteinrichtung)
        |
        v  Sicherheitstests          (Layer 3 + Layer 4)
Testergebnisse
```

### 6.2 `Containerfile.security`

```dockerfile
# === Stage 1: Distribution bauen ===
FROM php:8.5-cli AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip nodejs npm perl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/bin/composer

# Upstream-Source wird per Build-Arg/Context uebergeben
ARG WEBTREES_SOURCE=../webtrees-upstream/webtrees
COPY ${WEBTREES_SOURCE} /build/webtrees-src
WORKDIR /build/webtrees-src

# Asset-Compilation (npm)
RUN npm ci && npm run production

# Distribution bauen (composer webtrees:build)
RUN composer webtrees:build

# ZIP entpacken
RUN unzip -q webtrees-*.zip -d /build/dist

# === Stage 2: Produktionsidentischer Container ===
FROM php:8.5-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev \
    libfreetype6-dev libxml2-dev libgd-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install intl pdo pdo_mysql gd zip exif xml \
    && rm -rf /var/lib/apt/lists/*

# Apache-Konfiguration (identisch zum Fachtest-Container)
RUN a2enmod rewrite
COPY <<'VHOST' /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
        FallbackResource /index.php
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
VHOST

# Distribution aus Stage 1 kopieren
COPY --from=builder /build/dist/webtrees/ /var/www/html/

# data/ beschreibbar fuer Wizard (www-data)
RUN chown -R www-data:www-data /var/www/html/data

EXPOSE 80
```

**Kernunterschiede zum Fachtest-Container:**
- Kein read-only Mount — Distribution wird ins Image kopiert
- Kein Volume-Overlay auf `data/` — Upstream-Schutzdateien bleiben erhalten
- Kein `composer install` im Container — Vendor aus Distribution
- Keine Dev-Dependencies, keine OTel-Instrumentation, kein pcov
- Multi-Stage-Build: Stage 1 baut Distribution, Stage 2 ist produktionsidentisch

### 6.3 Compose-Erweiterung (`compose.yaml`)

```yaml
services:
  # --- Bestehende Services bleiben unveraendert ---

  webtrees-security:
    build:
      context: .
      dockerfile: Containerfile.security
      args:
        WEBTREES_SOURCE: ../webtrees-upstream/webtrees
    container_name: webtrees-security
    profiles:
      - security
    ports:
      - "8082:80"
    depends_on:
      mysql-security:
        condition: service_healthy
    networks:
      - webtrees-test-net
    healthcheck:
      test: ["CMD", "php", "-r", "echo 'ok';"]
      interval: 5s
      timeout: 3s
      retries: 10

  mysql-security:
    image: docker.io/library/mysql:8.0
    container_name: mysql-security
    profiles:
      - security
    environment:
      MYSQL_ROOT_PASSWORD: security_test
      MYSQL_DATABASE: webtrees_security
      MYSQL_USER: webtrees
      MYSQL_PASSWORD: security_test
    command: >
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_bin
    networks:
      - webtrees-test-net
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 3s
      retries: 10
    volumes:
      - mysql-security-data:/var/lib/mysql

volumes:
  # --- Bestehende Volumes bleiben unveraendert ---
  mysql-security-data:
```

**Eigene MySQL-Instanz:** Sauberer Zustand für den Wizard. Keine Kollision mit
Fachtest-DB. Eigene Credentials (`security_test`), eigene Datenbank (`webtrees_security`).

**Port 8082:** Kein Konflikt mit Fachtest (8080) oder Adminer (8081).

### 6.4 Makefile-Erweiterung

```makefile
COMPOSE_SECURITY = podman-compose -f compose.yaml --profile security

test-security: ## Sicherheitstest (Distribution + Wizard + Pruefpunkte)
	$(COMPOSE_SECURITY) up -d --build
	scripts/security-filesystem-checks.sh
	$(COMPOSE_SECURITY) exec playwright npx playwright test \
	    --config=/tests/e2e/playwright-security.config.ts
	$(COMPOSE_SECURITY) down

security-up: ## Security-Stack starten (ohne Tests)
	$(COMPOSE_SECURITY) up -d --build

security-down: ## Security-Stack stoppen
	$(COMPOSE_SECURITY) down

security-clean: ## Security-Stack stoppen + Volumes loeschen
	$(COMPOSE_SECURITY) down -v
```

### 6.5 Playwright-Konfiguration (`layer4-e2e/playwright-security.config.ts`)

```typescript
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/security',
  timeout: 60_000,  // Wizard braucht mehr Zeit
  retries: 0,       // Keine Retries — Sicherheitstests muessen beim ersten Mal passen
  workers: 1,       // Sequenziell (Wizard-State)
  reporter: [
    ['html', { outputFolder: '/artifacts/security/playwright-report' }],
    ['list'],
  ],
  use: {
    baseURL: process.env.SECURITY_BASE_URL || 'http://webtrees-security:80',
    screenshot: 'only-on-failure',
    trace: 'on',     // Immer Traces fuer Sicherheitstests
    headless: true,
  },
  projects: [
    { name: 'chromium', use: { browserName: 'chromium' } },
  ],
  outputDir: '/artifacts/security/test-results',
});
```

---

## 7. Testfall-Spezifikationen

### 7.1 Layer-3-Tests (Dateisystem, Shell-Assertions)

**Datei:** `scripts/security-filesystem-checks.sh`

**Vorbedingung:** `webtrees-security`-Container läuft. Wizard wurde noch NICHT durchlaufen
(SEC-H01, SEC-H02, SEC-D01, SEC-D02, SEC-PUB01 prüfen Distribution-Zustand).
SEC-C01–SEC-C03 und SEC-WZ03 laufen NACH dem Wizard-Durchlauf (via Playwright oder
separatem Aufruf).

```
# @see SEC-H01
podman exec webtrees-security test -f /var/www/html/data/.htaccess

# @see SEC-H02
podman exec webtrees-security grep -q 'Require all denied' /var/www/html/data/.htaccess

# @see SEC-D01
podman exec webtrees-security test -f /var/www/html/data/index.php

# @see SEC-D02
podman exec webtrees-security grep -q "header('Location:" /var/www/html/data/index.php

# @see SEC-PUB01
podman exec webtrees-security test -f /var/www/html/public/index.php

# Nach Wizard-Durchlauf:

# @see SEC-C01
podman exec webtrees-security head -1 /var/www/html/data/config.ini.php
# Assert: beginnt mit '; <?php return; ?>'

# @see SEC-C02
podman exec webtrees-security php -r \
  "print_r(parse_ini_file('/var/www/html/data/config.ini.php'));"
# Assert: dbhost, dbuser, dbname gesetzt und nicht leer

# @see SEC-C03
podman exec webtrees-security stat -c '%a' /var/www/html/data/config.ini.php
# Assert: world-readable Bit (xx4, xx5, xx6, xx7) → UPSTREAM-BEFUND

# @see SEC-WZ03
podman exec webtrees-security test -f /var/www/html/data/config.ini.php
```

**Begründung Shell statt PHPUnit:** Der `webtrees-security`-Container enthält keine
Dev-Dependencies (produktionsidentisch). PHPUnit nachinstallieren würde den Container
verändern. `podman exec` prüft das Dateisystem von außen — minimaler Eingriff.

### 7.2 Layer-4-Tests (HTTP, Playwright)

#### 7.2.1 Wizard-Durchlauf (`wizard-setup.spec.ts`)

**Zweck:** SEC-WZ01–SEC-WZ04. Erster Test, der läuft — danach ist die Instanz eingerichtet.

```
// @see SEC-WZ01
Test: Wizard erscheint bei erster Anfrage
    GET /
    Assert: Response enthält Setup-Wizard-HTML (Schritt 1: Sprachauswahl)
    Assert: Selektor für Sprachauswahl sichtbar

// @see SEC-WZ02
Test: Wizard-Durchlauf komplett (6 Schritte)
    Schritt 1: Sprache wählen (lang=en-US), Submit
    Schritt 2: Server-Checks — keine Fehler angezeigt, Weiter
    Schritt 3: Datenbank-Typ = MySQL, Weiter
    Schritt 4: DB-Verbindung:
        dbhost = mysql-security
        dbport = 3306
        dbuser = webtrees
        dbpass = security_test
        dbname = webtrees_security
        tblpfx = wt_
        Submit
    Schritt 5: Admin-Account:
        wtname = Security Admin
        wtuser = secadmin
        wtpass = sectest123
        wtemail = sec@test.local
        Submit
    Schritt 6: base_url = http://webtrees-security:80, Submit
    Assert: Redirect zur Startseite (kein Wizard mehr)

// @see SEC-WZ03 (Layer-4-Anteil, Layer-3-Anteil in Shell-Script)
Test: config.ini.php existiert nach Wizard
    Assert: Dateisystem-Prüfung (via Container-exec)

// @see SEC-WZ04
Test: Wizard gesperrt nach Abschluss
    GET /
    Assert: Response enthält KEIN Setup-Formular
    Assert: Response ist normale webtrees-Seite (Login oder Homepage)
```

#### 7.2.2 HTTP-Zugriffstests (`data-access.spec.ts`)

**Vorbedingung:** Wizard wurde durchlaufen (SEC-WZ02 bestanden).

```
// @see SEC-H03
Test: GET /data/ blockiert
    GET /data/
    Assert: status === 403

// @see SEC-H04
Test: GET /data/config.ini.php blockiert
    GET /data/config.ini.php
    Assert: status === 403
    Assert: Body enthält NICHT 'dbpass'
    Assert: Body enthält NICHT 'dbuser'

// @see SEC-H05
Test: GET /data/media/ blockiert
    GET /data/media/
    Assert: status === 403

// @see SEC-H06
Test: URL-Encoding umgeht nicht
    Für jede Variante aus 5.1:
        GET <variante>
        Assert: status === 403 ODER Body enthält keine Credentials
```

#### 7.2.3 Public-Verzeichnis-Tests (`public-access.spec.ts`)

```
// @see SEC-PUB02
Test: public/index.php wird nicht als PHP ausgeführt
    GET /public/index.php
    Assert: Body enthält 'require' (Source-Text sichtbar)
    Assert: Body enthält NICHT dynamische PHP-Ausgabe

// @see SEC-PUB03
Test: Kein Directory Listing auf /public/
    GET /public/
    Assert: Body enthält NICHT '<a href=' (kein Datei-Listing)
    Assert: status !== 200 ODER Response ist webtrees-Seite

// @see SEC-PUB04
Test: Path-Traversal blockiert
    Für jede Variante aus 5.2:
        GET <variante>
        Assert: Body enthält NICHT 'dbpass'
        Assert: Body enthält NICHT 'dbuser'
```

#### 7.2.4 Setup-Lock-Test (`setup-lock.spec.ts`)

```
// @see SEC-W01
Test: Wizard nicht erreichbar nach Setup
    GET /setup
    Assert: Response enthält KEIN Setup-Formular
    Assert: Kein Schritt-1-Selektor sichtbar

    POST /setup mit step=1
    Assert: Response ist KEIN Wizard-Verhalten

    POST /setup mit step=6 und Wizard-Daten
    Assert: Kein neuer Admin erstellt
```

#### 7.2.5 Media-Zugriffstests (`media-access.spec.ts`)

**Vorbedingung:** Bekannte Mediendatei im `data/media/`-Verzeichnis.
Falls keine vorhanden: Test muss via Admin-Login eine hochladen oder der Test
dokumentiert "keine Mediendateien vorhanden" und überspringt SEC-M02/SEC-M03.

```
// @see SEC-M01
Test: Direkter Media-Zugriff blockiert
    GET /data/media/<bekannte-datei>
    Assert: status === 403

// @see SEC-M02
Test: Media-Route ohne Auth
    GET /tree/<name>/media-file/<xref>
    Assert: status === 302 (Redirect zu Login) ODER status === 403

// @see SEC-M03
Test: Media-Route mit Auth
    Login als Member
    GET /tree/<name>/media-file/<xref>
    Assert: status === 200
```

#### 7.2.6 Security-Header-Tests (`security-headers.spec.ts`)

```
// @see SEC-HDR01
Test: X-Content-Type-Options
    GET /
    Assert: Response-Header 'X-Content-Type-Options' === 'nosniff'

// @see SEC-HDR02
Test: X-Frame-Options
    GET /
    Assert: Response-Header 'X-Frame-Options' ist 'SAMEORIGIN' oder 'DENY'

// @see SEC-HDR03
Test: Referrer-Policy
    GET /
    Assert: Response-Header 'Referrer-Policy' ist gesetzt und nicht leer

// @see SEC-HDR04
Test: Server-Banner
    GET /
    $server = Response-Header 'Server'
    Assert: $server enthält NICHT vollständige Apache-Version (z.B. 'Apache/2.4.62')
    // Anmerkung: Deployment-Empfehlung, nicht Upstream
```

---

## 8. Testorakel

> Ein **Testorakel** (ISTQB) ist die Informationsquelle zur Ermittlung erwarteter Ergebnisse.
> Konkrete erwartete Werte werden im Testcode definiert, nicht in diesem Dokument.

| Orakel | Gilt für Feature-Matrix-IDs | Methode |
|--------|----------------------------|---------|
| Upstream-Quellcode: `data/.htaccess` (statische Datei) | SEC-H01, SEC-H02 | Dateiinhalt als Referenz: `Require all denied` |
| Upstream-Quellcode: `data/index.php` (statische Datei) | SEC-D01, SEC-D02 | Dateiinhalt als Referenz: `header('Location: ../index.php')` |
| Upstream-Quellcode: `resources/views/setup/config.ini.phtml` | SEC-C01, SEC-C02 | Template definiert erwartetes Format (PHP-Guard, INI-Keys) |
| Apache HTTP-Spezifikation (RFC 7231, Status 403) | SEC-H03–SEC-H06, SEC-M01 | HTTP 403 = Zugriff verboten; Body darf keine Credentials enthalten |
| Upstream-Quellcode: `ReadConfigIni.php`, `SetupWizard.php` | SEC-W01, SEC-WZ01–SEC-WZ04 | Middleware-Logik: `file_exists()` → Lock; Wizard-HTML-Selektoren |
| Upstream-Quellcode: `PublicFiles.php` | SEC-PUB02–SEC-PUB04 | `file_get_contents()` statt PHP-Execution; `!str_contains($path, '..')` |
| Upstream-Quellcode: `SecurityHeaders.php` | SEC-HDR01–SEC-HDR03 | Middleware setzt Header-Werte direkt im Code |
| Upstream-Quellcode: `Auth::checkMediaAccess()`, `MediaFileDownload` | SEC-M02, SEC-M03 | Rollenbasierte Zugriffskontrolle: Visitor → kein Zugriff, Member → Zugriff |
| Dateisystem-Semantik: `stat()` Permissions | SEC-C03 | umask-Default des PHP-Prozesses; world-readable = potenzielle Schwäche |
| Apache-Konfiguration: `ServerTokens` Default | SEC-HDR04 | Default `ServerTokens Full` → Versionsinfo; gehärtete Config → `Prod` |

---

## 9. Testentwurfsverfahren

> ISTQB-Testentwurfsverfahren beschreiben, **wie** Testbedingungen und Testfälle
> systematisch abgeleitet werden. Zuordnung pro Prüfpunkt-Gruppe, nicht pro Einzeleintrag.

| Verfahren (ISTQB) | Feature-Matrix-IDs | Begründung |
|--------------------|-------------------|------------|
| **Entscheidungstabellentest** | SEC-H03–SEC-H06, SEC-M01–SEC-M03 | Kombination URL-Pfad x HTTP-Methode x erwarteter Status (403/200/302). Entscheidungstabelle mit binären Bedingungen: `.htaccess` greift ja/nein x Auth vorhanden ja/nein |
| **Erfahrungsbasierter Test** | SEC-H06, SEC-PUB04 | URL-Encoding-Varianten und Path-Traversal-Muster entstammen OWASP Testing Guide und Praxiswissen. Keine formale Spezifikation für Umgehungsversuche — Whitebox-Wissen leitet die Testfälle |
| **Anwendungsfall-Test** | SEC-WZ01–SEC-WZ04 | End-to-End-Szenario: Frische Distribution → Wizard durchlaufen → lauffähige Instanz. Nutzerinteraktion (6 Wizard-Schritte) mit definiertem Zielzustand |
| **Äquivalenzklassenbildung** | SEC-HDR01–SEC-HDR04, SEC-PUB02–SEC-PUB03 | Header: vorhanden/korrekt vs. fehlend/falsch. `public/`-Zugriff: Datei vs. Verzeichnis vs. Traversal |
| **Grenzwertanalyse** | SEC-C03 | Datei-Permissions: Grenze bei world-readable-Bit (0644 vs. 0640 vs. 0600) |

---

## 10. Endekriterien

> Eingangskriterium: Distribution-Container startet und Wizard ist erreichbar (`GET /` →
> Setup-HTML). Ohne dieses Eingangskriterium sind alle Sicherheitstests blockiert.

| Priorität | Endekriterium |
|-----------|---------------|
| **Hoch (MUSS)** | Alle 14 MUSS-Prüfpunkte grün: SEC-H01–SEC-H06, SEC-C01–SEC-C03, SEC-W01, SEC-WZ01–SEC-WZ04. Kein einziger darf rot sein ohne dokumentierten Upstream-Befund. |
| **Mittel (SOLL)** | Alle 8 SOLL-Prüfpunkte grün oder als Upstream-Befund mit Issue bei `fisharebest/webtrees` dokumentiert. |
| **Niedrig (KANN)** | Alle 4 KANN-Prüfpunkte (SEC-HDR01–SEC-HDR04) ausgeführt und Ist-Zustand dokumentiert. Rot ist akzeptabel (Deployment-Empfehlung, nicht Upstream-Code). |
| **Gesamt** | 26/26 Prüfpunkte implementiert und ausgeführt. Kein Prüfpunkt übersprungen oder als `@expectedFailure` markiert. |

**Integration in bestehende Endekriterien:** In `docs/testing-bigpicture.md` wird eine
neue Zeile in der Endekriterien-Tabelle ergänzt:

> | Sicherheitstest | Alle MUSS-Prüfpunkte (SEC-H*, SEC-C*, SEC-W01, SEC-WZ*) grün; SOLL-Prüfpunkte grün oder als Upstream-Befund dokumentiert; KANN-Prüfpunkte dokumentiert |

---

## 11. Produktrisiken

> Fortsetzung der bestehenden Produktrisiken R1–R13 aus `docs/testing-bigpicture.md`.
> Risikobasiertes Testen (ISTQB): Wahrscheinlichkeit (W) x Auswirkung (A) → Priorität.

| Risiko-ID | Risiko | W | A | Maßnahme (Feature-Matrix-IDs) |
|-----------|--------|---|---|-------------------------------|
| R14 | DB-Credentials über HTTP zugänglich (`data/config.ini.php`) | Niedrig | Kritisch | SEC-H03, SEC-H04, SEC-H06 |
| R15 | Setup-Wizard nach Ersteinrichtung erneut aufrufbar (Admin-Takeover) | Niedrig | Kritisch | SEC-W01, SEC-WZ04 |
| R16 | Mediendateien ohne Zugriffskontrolle per Direkt-URL abrufbar | Niedrig | Hoch | SEC-M01–SEC-M03, SEC-H05 |
| R17 | Path-Traversal ermöglicht Dateizugriff außerhalb `/public/` | Niedrig | Kritisch | SEC-PUB04 |
| R18 | Fehlende Security-Headers ermöglichen Clickjacking/MIME-Sniffing | Mittel | Mittel | SEC-HDR01–SEC-HDR03 |
| R19 | `config.ini.php` world-readable (fehlender `chmod` im Wizard) | Mittel | Hoch | SEC-C03 |
| R20 | Schutzdateien (`data/.htaccess`, `data/index.php`) fehlen in Distribution | Niedrig | Kritisch | SEC-H01, SEC-H02, SEC-D01, SEC-D02 |
| R21 | Server-Banner verrät Apache-Version (Information Disclosure) | Hoch | Niedrig | SEC-HDR04 |

---

## 12. Überdeckungsstrategie

> Anweisungsüberdeckung (pcov) ist für den Sicherheitstest-Track **nicht anwendbar**:
> Der Distribution-Container enthält kein pcov, keine Dev-Dependencies und keinen
> PHPUnit-Runner. Die Tests prüfen von außen (HTTP, Dateisystem), nicht von innen.

| Aspekt | Entscheidung |
|--------|--------------|
| **Code Coverage (pcov)** | Nicht anwendbar — produktionsidentischer Container ohne Instrumentierung |
| **Prüfpunkt-Abdeckung** | Primäre Metrik: 26/26 Prüfpunkte implementiert und ausgeführt |
| **Angriffsmuster-Abdeckung** | Sekundäre Metrik: Alle Varianten aus 5.1 (URL-Encoding, 9 Muster) und 5.2 (Path-Traversal, 5 Muster) durchlaufen |
| **Vektor-Abdeckung** | Alle 8 Angriffsvektoren (V1–V8) durch mindestens einen Prüfpunkt adressiert |
| **Ratchet** | Nicht anwendbar — Prüfpunkt-Abdeckung ist binär (implementiert oder nicht), kein stetiges Wachstum |

**Vektor-zu-Prüfpunkt-Mapping:**

| Vektor | Adressiert durch |
|--------|-----------------|
| V1 (Direktzugriff `data/`) | SEC-H03, SEC-H04, SEC-H06 |
| V2 (Direktzugriff `data/media/`) | SEC-H05, SEC-M01 |
| V3 (Datei-Permissions) | SEC-C03 |
| V4 (Directory Listing) | SEC-PUB03 |
| V5 (Wizard nach Setup) | SEC-W01, SEC-WZ04 |
| V6 (Fehlende `.htaccess`) | SEC-H01, SEC-H02 |
| V7 (Path-Traversal) | SEC-PUB04 |
| V8 (Security-Headers) | SEC-HDR01–SEC-HDR04 |

---

## 13. Fehlermanagement

> Erweiterung des bestehenden Fehlermanagements aus `docs/testing-bigpicture.md`.
> Prinzip: CI-Gate = Fehlermanagement. Rot = blockiert, Grün = freigegeben.

| Fehlerzustand in... | Vorgehen |
|---------------------|----------|
| **Eigener Testinfrastruktur** (Containerfile, Playwright-Script, Shell-Script) | Direkt im Code beheben (Fix-Commit), kein separater Issue-Tracker |
| **webtrees Upstream** (Sicherheitsmechanismus fehlt oder ist wirkungslos) | Test bleibt rot. Annotation `// UPSTREAM-BEFUND: <Beschreibung> — siehe fisharebest/webtrees#NNN`. Issue bei Upstream erstellen (Template in 13.2). |
| **Apache-Konfiguration** (z.B. SEC-HDR04 Server-Banner) | Dokumentieren als Deployment-Empfehlung. Kein Upstream-Issue, da nicht webtrees-Code. |

### 13.1 Bekannte potenzielle Upstream-Befunde (aus Analyse)

| ID | Potenzieller Befund | Schwere | Risiko-ID |
|----|---------------------|---------|-----------|
| SEC-C03 | `config.ini.php` world-readable (kein `chmod` im Wizard) | Medium | R19 |
| SEC-HDR04 | Apache Server-Banner enthält Versionsinfo (Deployment-Ebene) | Low | R21 |

### 13.2 Upstream-Issue-Template

```markdown
### Security: <Kurzbezeichnung>

**Affected version:** <git describe>
**Check point:** <SEC-ID> (z.B. SEC-C03)
**Severity:** <Low/Medium/High>

**Description:**
<Was wurde gefunden, was ist das erwartete Verhalten>

**Reproduction:**
1. Extract distribution ZIP
2. Run Setup Wizard
3. <Schritte zur Reproduktion>

**Expected:** <Erwartetes sicheres Verhalten>
**Actual:** <Tatsächliches Verhalten>

**Recommendation:** <Vorgeschlagener Fix>
```

---

## 14. Testkonventionen und Verfolgbarkeit

### 14.1 Testkonventionen

> Die bestehenden Testkonventionen aus `docs/testing-bigpicture.md` gelten auch für den
> Sicherheitstest-Track, soweit anwendbar.

| Konvention | Anwendbarkeit im Security-Track | Anpassung |
|------------|--------------------------------|-----------|
| **AAA-Pattern** (Arrange-Act-Assert) | Ja — gilt für Playwright-Tests | Arrange = Vorbedingung (Container, Wizard-State), Act = HTTP-Request, Assert = Status/Body/Header |
| **FIRST-Prinzipien** | Teilweise | **Fast:** eingeschränkt (Container-Start + Wizard). **Independent:** ja (jeder Spec unabhängig nach Wizard). **Repeatable:** ja (`security-clean` + Neuaufbau). **Self-validating:** ja (grün/rot). **Timely:** ja. |
| **Naming (PHPUnit)** | Nicht anwendbar | Kein PHPUnit im Security-Track |
| **Naming (Playwright)** | Ja | `test('SEC-H03: GET /data/ blockiert', ...)` — ID im Testnamen |
| **Naming (Shell)** | Ja | Kommentar `# @see SEC-H01` vor jeder Assertion |
| **Data Provider** | Ja — für Encoding-Varianten | SEC-H06 und SEC-PUB04 nutzen Schleifen über Varianten-Arrays (Playwright `test.describe` + Loop) |

### 14.2 Verfolgbarkeit

> Bidirektionale Verfolgbarkeit zwischen Feature-Matrix-ID und Testcode, konsistent
> mit dem bestehenden `@see`-Mechanismus aus `docs/testing-bigpicture.md`.

| Richtung | Mechanismus | Beispiel |
|----------|-------------|---------|
| Feature-Matrix → Testcode | `grep -r 'SEC-H01' layer4-e2e/ scripts/` | Findet alle Testdateien, die SEC-H01 implementieren |
| Testcode → Feature-Matrix | `// @see SEC-H01` (Playwright) oder `# @see SEC-H01` (Shell) | Annotation im Test verweist auf dieses Dokument, Abschnitt 4.2 |

**Konvention:** Jeder Testfall (Playwright `test()` oder Shell-Assertion) trägt genau
eine `@see SEC-<ID>`-Annotation. Bei SEC-H06 und SEC-PUB04 (Varianten-Schleifen) steht
die Annotation auf der umschließenden `test.describe`-Ebene.

---

## 15. Differenz zum Fachtest-Track

| Aspekt | Fachtest-Track (bestehend) | Sicherheitstest-Track (neu) |
|--------|---------------------------|---------------------------|
| **Container** | `webtrees` (Dev-Source Mount) | `webtrees-security` (Distribution) |
| **Source** | `../webtrees-upstream/webtrees/:ro` | Distribution-ZIP, entpackt im Image |
| **Setup** | `setup-webtrees.sh` (Wizard umgangen) | Playwright → Setup-Wizard |
| **`data/`** | Named Volume (überlagert Upstream) | Aus ZIP (`.htaccess`, `index.php` vorhanden) |
| **`vendor/`** | `composer install` inkl. Dev-Dependencies | Vorgebundelt, nur Prod-Dependencies |
| **MySQL** | `mysql` Container, DB `webtrees_test` | `mysql-security` Container, DB `webtrees_security` |
| **Makefile** | `make test-unit`, `test-integration`, `test-e2e` | `make test-security` |
| **Compose-Profil** | Default | `security` |
| **OTel** | Aktiv (PDO/PSR-18 Auto-Instrumentation) | Deaktiviert (Produktion hat kein OTel) |
| **Test-Runner** | PHPUnit (Container) + Playwright | Shell-Assertions + Playwright |
| **Code Coverage** | pcov (Ratchet-Strategie) | Nicht anwendbar (Prüfpunkt-Abdeckung stattdessen) |

---

## 16. Abgrenzung: Was dieser Plan NICHT abdeckt

- **Fixen des Fachtest-Tracks:** `setup-webtrees.sh` erzeugt `config.ini.php` ohne PHP-Guard
  und das Volume überlagert Schutzdateien. Das ist bekannt, aber eigener Scope (niedrige Prio).
- **SQL-Injection, XSS, CSRF:** Separate Testdomäne, nicht Setup-spezifisch.
- **TLS/HTTPS:** Testumgebung ist HTTP-only.
- **Performance des Security-Containers:** Kein Performanztest für den Security-Track.
- **CI/CD-Integration:** Wird nach lokaler Validierung in separater Phase ergänzt.

---

## 17. ISTQB-Terminologie — Einordnung und Abgrenzung

> Referenz: ISTQB-Glossar de_DE v4.7.1 (vereinbartes führendes Glossar).

**"Sicherheitstest"** ist im Glossar definiert als: *Testen, um die Sicherheit eines
Softwareprodukts festzustellen.* Einordnung: **nicht-funktionale Testart**. Das ist
die korrekte Klassifikation für diesen Test-Track.

Das ISTQB-Foundation-Glossar v4.7.1 liefert für Security Testing keine tiefere Methodik
als die Definition der Testart. Es gibt ein ISTQB-Spezialistenmodul (CT-SEC, "Security
Tester") mit eigenem Syllabus, das Attack-Taxonomien (STRIDE), OWASP und formale
Verwundbarkeitsklassifikation behandelt — dieses Modul liegt **außerhalb des vereinbarten
Glossar-Scopes**.

Die methodische Lücke wird durch die bestehenden Testentwurfsverfahren (Abschnitt 9)
geschlossen: Entscheidungstabellentest, erfahrungsbasierter Test, Anwendungsfall-Test,
Äquivalenzklassenbildung und Grenzwertanalyse sind im Foundation-Glossar definiert und
für die Sicherheitstests anwendbar. Die Whitebox-Angriffsmuster (Abschnitt 5) sind
methodisch dem erfahrungsbasierten Test zuzuordnen — sie basieren auf Praxiswissen
(OWASP Testing Guide), nicht auf einer formalen ISTQB-Taxonomie.

**Nicht übernommen aus CT-SEC:**
- STRIDE-Modell (zu formal für den Scope)
- CVSS-Scoring (keine extern kommunizierten Schwachstellen — Upstream-Befunde werden als
  Issues mit einfacher Schwere-Klassifikation Low/Medium/High gemeldet)
- Formale Penetrationstest-Methodik (die HTTP-Tests in SEC-H03–SEC-H06 und SEC-PUB04
  sind methodisch Penetrationstests, werden aber nicht als eigenständiges Verfahren
  eingeordnet, sondern als Entscheidungstabellentest + erfahrungsbasierter Test)

---

## 18. Implementierungsplan

Kleinteilig, jede Phase einzeln reviewbar. Status wird in diesem Abschnitt und in der
Feature-Matrix (Abschnitt 4.2) synchron aktualisiert.

### Phase S1 — Infrastruktur (Containerfile + Compose + Makefile)

| Aspekt | Detail |
|--------|--------|
| **Status** | **Verifiziert** |
| **Prüfpunkte** | — (Infrastruktur, keine Feature-Matrix-IDs) |
| **Deliverables** | `Containerfile.security` (Multi-Stage-Build), `scripts/build-security-image.sh` (Build-Helper), Compose-Erweiterung (Profil `security`, `webtrees-security`, `mysql-security`), Makefile-Targets (`security-build`, `test-security`, `security-up`, `security-down`, `security-clean`) |
| **Abnahmekriterium** | `make security-up` startet den Security-Stack, `GET /` zeigt Setup-Wizard |
| **Ergebnis** | Verifiziert: HTTP 200, "Setup wizard for webtrees" auf http://localhost:8082/ |

### Phase S2 — Wizard-Automatisierung (SEC-WZ01–SEC-WZ04)

| Aspekt | Detail |
|--------|--------|
| **Status** | **Verifiziert** |
| **Prüfpunkte** | SEC-WZ01, SEC-WZ02, SEC-WZ03, SEC-WZ04 |
| **Deliverables** | `layer4-e2e/playwright-security.config.ts`, `layer4-e2e/tests/security/wizard-setup.spec.ts` |
| **Abnahmekriterium** | Wizard-Durchlauf automatisiert, SEC-WZ01–SEC-WZ04 grün |
| **Ergebnis** | 4/4 Tests grün |

### Phase S3 — Dateisystem-Assertions (SEC-H01, SEC-H02, SEC-D01, SEC-D02, SEC-C01–SEC-C03, SEC-PUB01)

| Aspekt | Detail |
|--------|--------|
| **Status** | **Verifiziert** |
| **Prüfpunkte** | SEC-H01, SEC-H02, SEC-D01, SEC-D02, SEC-C01, SEC-C02, SEC-C03, SEC-PUB01, SEC-WZ03 |
| **Deliverables** | `scripts/security-filesystem-checks.sh` (9 Prüfpunkte) |
| **Abnahmekriterium** | 9 Layer-3-Assertions grün (oder rot bei Upstream-Befund → dokumentieren) |
| **Ergebnis** | 8/9 grün, SEC-C03 rot (Upstream-Befund: config.ini.php world-readable 644) |

### Phase S4 — HTTP-Zugriffstests (SEC-H03–SEC-H06, SEC-PUB02–SEC-PUB04, SEC-W01)

| Aspekt | Detail |
|--------|--------|
| **Status** | **Verifiziert** |
| **Prüfpunkte** | SEC-H03, SEC-H04, SEC-H05, SEC-H06, SEC-PUB02, SEC-PUB03, SEC-PUB04, SEC-W01 |
| **Deliverables** | `layer4-e2e/tests/security/data-access.spec.ts`, `layer4-e2e/tests/security/public-access.spec.ts`, `layer4-e2e/tests/security/setup-lock.spec.ts` |
| **Abnahmekriterium** | 11+ Testfälle grün (Encoding-Varianten zählen mehrfach) |
| **Ergebnis** | 20/20 Tests grün (inkl. 7 URL-Encoding-Varianten, 5 Path-Traversal-Varianten) |

### Phase S5 — Media + Security-Headers (SEC-M01–SEC-M03, SEC-HDR01–SEC-HDR04)

| Aspekt | Detail |
|--------|--------|
| **Status** | **Verifiziert** |
| **Prüfpunkte** | SEC-M01, SEC-M02, SEC-M03, SEC-HDR01, SEC-HDR02, SEC-HDR03, SEC-HDR04 |
| **Deliverables** | `layer4-e2e/tests/security/media-access.spec.ts`, `layer4-e2e/tests/security/security-headers.spec.ts` |
| **Abnahmekriterium** | 7 Testfälle grün (oder rot bei Upstream-Befund) |
| **Ergebnis** | 6/7 grün, SEC-HDR04 rot (Deployment-Empfehlung: Apache Server-Banner enthält Version) |

### Phase S6 — Dokumentation + Integration

| Aspekt | Detail |
|--------|--------|
| **Status** | **Verifiziert** |
| **Prüfpunkte** | — (Dokumentation, keine Tests) |
| **Deliverables** | `docs/testing-bigpicture.md` vollständig und eigenständig um Sicherheitstest-Domäne erweitern (14 Kapitel + 3 Kleinänderungen, siehe Arbeitspakete). Kein Verweis auf `docs/security_plan.md` im Bigpicture. |
| **Abnahmekriterium** | Bigpicture enthält Sicherheitsdomäne eigenständig. `make test-security` im Implementierungs-Fahrplan. Alle 26/26 Prüfpunkte in Abdeckungsmatrix. Charakter aller bestehenden Kapitel bleibt erhalten. |
| **Ergebnis** | 17 APs umgesetzt. Bigpicture eigenständig: Designentscheidung, Layer-Zuordnung, Mermaid-Subgraph, N2, Container-Stack (6+2), Feature-Matrix SEC (26), Testfall-/Prioritätsverteilung, Endekriterien, 10 Testorakel, 5 Testentwurfsverfahren, R14–R21, Überdeckung (Vektor-Mapping), Fehlermanagement, Fahrplan Phase 12, Verfolgbarkeit, Known Bugs (SEC-C03, SEC-HDR04), Abdeckungsmatrix (117 Features). |

#### Arbeitspakete S6

> Jedes AP ändert ein Kapitel in `docs/testing-bigpicture.md`. Reihenfolge = Dokumentreihenfolge.
> **Grundregel:** Das Bigpicture muss eigenständig und vollständig sein — alle Inhalte inline,
> keine Verweise auf `docs/security_plan.md`.

**AP S6-01 — Getroffene Designentscheidungen**

Neue Zeile in Tabelle:

| Dimension | Entscheidung |
|---|---|
| **Sicherheitstest** | Zwei-Track-Architektur: Fachtest (Dev-Source, Mount) vs. Sicherheitstest (Distribution-ZIP, produktionsidentisch). Eigener Container-Build (`Containerfile.security`), Upstream-Setup-Wizard via Playwright, Dateisystem-Assertions via Shell |

---

**AP S6-02 — Zuordnung Layer ↔ ISTQB-Teststufe**

Neue Zeile in Tabelle:

| Code (Makefile / Verzeichnis) | ISTQB-Teststufe / Querschnitt |
|---|---|
| `layer4-e2e/tests/security/` + `scripts/security-filesystem-checks.sh` / `make test-security` | Querschnitt — Sicherheitstest |

---

**AP S6-03 — Mermaid-Diagramm**

Neuer Subgraph einfügen (zwischen PERF und den Verbindungen):

```mermaid
    subgraph SEC["Querschnitt — Sicherheitstest (Distribution-Container)"]
        secbuild["Distribution-Build\nContainerfile.security"]
        secwiz["Wizard-Durchlauf\nPlaywright"]
        secfs["Dateisystem-Assertions\nShell-Script"]
        sechttp["HTTP-Zugriffstests\nPlaywright"]
        secbuild --> secwiz --> secfs
        secwiz --> sechttp
    end
```

Neue Verbindungen:

```
    INFRA --> SEC
    SEC -->|"Fehler-Artefakt"| d3
    SEC -.->|"Job"| ci3
```

Anpassung CI-Kette: `ci3["systemtest"]` erweitern zu `ci3["systemtest\n+ sicherheitstest"]` oder separaten Job `ci3b["sicherheitstest"]` nach `ci3` einfügen.

---

**AP S6-04 — N2 Verzeichnisstruktur**

Neue Einträge ergänzen:

```
├── Containerfile.security          # Distribution-Container (Multi-Stage Build)
├── scripts/
│   ├── build-security-image.sh    # Build-Helper (podman build --volume)
│   ├── security-filesystem-checks.sh # 9 Dateisystem-Assertions (pre/post-wizard)
│   ...
├── layer4-e2e/
│   ├── playwright-security.config.ts  # Security-Playwright-Config (Distribution-Container)
│   └── tests/
│       ├── security/                  # Sicherheitstests (getrennt von funktionalen E2E)
│       │   ├── wizard-setup.spec.ts   # SEC-WZ01–WZ04 (Setup-Projekt, läuft zuerst)
│       │   ├── data-access.spec.ts    # SEC-H03–H06
│       │   ├── public-access.spec.ts  # SEC-PUB02–PUB04
│       │   ├── setup-lock.spec.ts     # SEC-W01
│       │   ├── media-access.spec.ts   # SEC-M01–M03
│       │   └── security-headers.spec.ts # SEC-HDR01–HDR04
│       ...
```

Anpassung `layer3-integration/tests/`-Zählung: unverändert (Security-Tests laufen nicht im Integration-Layer).

---

**AP S6-05 — Container-Stack-Spezifikation**

Einleitungstext anpassen: "6 Container, 1 Netzwerk" → "6+2 Container, 2 Netzwerke" mit Hinweis:

> Die Security-Container (`webtrees-security`, `mysql-security`) laufen über ein separates
> Compose-Profil (`--profile security`) und werden nur für `make test-security` gestartet.
> Sie teilen weder Netzwerk noch Volumes mit dem Fachtest-Stack.

Zwei neue Zeilen in der Container-Tabelle:

| Container | Image | Zweck | Host-Port | Volume-Mounts |
|---|---|---|---|---|
| `webtrees-security` | `Containerfile.security` | Distribution-Build (ZIP entpackt) + Apache | 8082:80 | Named Vol → `/var/www/html/data/` (rw) |
| `mysql-security` | `docker.io/library/mysql:8.0` | Datenbank (Security-Track) | 3307:3306 | Named Vol → `/var/lib/mysql` |

Netzwerk-Topologie erweitern:

```
webtrees-security-net (Bridge, Profil: security)
├── webtrees-security ←→ mysql-security  (PDO, Port 3306)
└── playwright        →  webtrees-security (HTTP, Port 80)
```

---

**AP S6-06 — Feature-Matrix: Sicherheit (SEC)**

Neues Kapitel nach der bestehenden Feature-Matrix "Datenschutz & Zugriffskontrolle" einfügen.
Spaltenstruktur angepasst (kein Upstream-SQLite, stattdessen Shell-Assertions und Playwright-Security):

```markdown
### Feature-Matrix: Sicherheit (SEC)

> Sicherheitstests prüfen, ob die Schutzmechanismen des webtrees-Upstream-Codes in einer
> produktionsidentischen Distribution-Instanz greifen. Eigener Container-Build, eigene
> Datenbank, Setup-Wizard via Playwright. Zwei Testverfahren: Shell-Assertions (Dateisystem)
> und Playwright-HTTP-Tests (Zugriffskontrolle, Header).

| # | Feature | Abgeleitete Anforderung | Prio | Status |
|---|---------|-------------------------|------|--------|
| SEC-H01 | `.htaccess` Existenz | `data/.htaccess` in Distribution vorhanden | Hoch | Grün |
| SEC-H02 | `.htaccess` Inhalt | Enthält `Require all denied` (Apache 2.4) | Hoch | Grün |
| SEC-H03 | HTTP-Zugriff `data/` blockiert | `GET /data/` → HTTP 403 | Hoch | Grün |
| SEC-H04 | HTTP-Zugriff `config.ini.php` blockiert | `GET /data/config.ini.php` → 403 | Hoch | Grün |
| SEC-H05 | HTTP-Zugriff `data/media/` blockiert | `GET /data/media/` → 403 | Hoch | Grün |
| SEC-H06 | URL-Encoding umgeht `.htaccess` nicht | Encoding-Varianten → jeweils 403 | Hoch | Grün |
| SEC-D01 | `data/index.php` Existenz | Datei in Distribution vorhanden | Mittel | Grün |
| SEC-D02 | `data/index.php` Redirect-Logik | Enthält `header('Location: ../index.php')` | Mittel | Grün |
| SEC-C01 | Config PHP-Guard | `config.ini.php` hat `; <?php return; ?>` als erste Zeile | Hoch | Grün |
| SEC-C02 | Config DB-Credentials | `config.ini.php` enthält dbhost, dbuser, dbpass, dbname | Hoch | Grün |
| SEC-C03 | Config Datei-Permissions | world-readable (644) — kein `chmod` im Wizard | Hoch | Rot (Upstream-Befund) |
| SEC-M01 | Direkter Media-Zugriff blockiert | `GET /data/media/<datei>` → 403 | Mittel | Grün |
| SEC-M02 | Media-Route ohne Auth | App-Route als Visitor → 302 (Redirect zu Login) | Mittel | Grün |
| SEC-M03 | Media-Route mit Auth | App-Route als Member → 200 | Mittel | Grün |
| SEC-PUB01 | `public/index.php` Existenz | Datei in Distribution vorhanden | Mittel | Grün |
| SEC-PUB02 | `public/index.php` keine PHP-Execution | Statischer Inhalt (Source sichtbar, nicht ausgeführt) | Mittel | Grün |
| SEC-PUB03 | Kein Directory Listing `/public/` | `GET /public/` → kein Datei-Listing | Mittel | Grün |
| SEC-PUB04 | Path-Traversal blockiert | `GET /public/../data/config.ini.php` → kein Dateiinhalt | Mittel | Grün |
| SEC-W01 | Wizard nach Setup gesperrt | Setup-URL → kein Setup-Formular | Hoch | Grün |
| SEC-WZ01 | Wizard erscheint bei Erstaufruf | Frische Instanz → Setup-Formular | Hoch | Grün |
| SEC-WZ02 | Wizard prüft Schreibrechte | Schritt 2: data/ beschreibbar | Hoch | Grün |
| SEC-WZ03 | Wizard erzeugt `config.ini.php` | Datei existiert nach Wizard-Abschluss | Hoch | Grün |
| SEC-WZ04 | Wizard sperrt sich selbst | Kein erneuter Setup nach Abschluss | Hoch | Grün |
| SEC-HDR01 | `X-Content-Type-Options` | Header = `nosniff` | Niedrig | Grün |
| SEC-HDR02 | `X-Frame-Options` | Header = `SAMEORIGIN` oder `DENY` | Niedrig | Grün |
| SEC-HDR03 | `Referrer-Policy` | Header gesetzt (nicht leer) | Niedrig | Grün |
| SEC-HDR04 | Server-Banner | Apache-Versionsstring sichtbar | Niedrig | Rot (Deployment-Empfehlung) |
```

---

**AP S6-07 — Testfall-Verteilung / Prioritätsverteilung**

Bestehende Tabelle um SEC-Spalte erweitern:

Testfall-Verteilung:

| Teststufe | … (bestehend) | SEC | Gesamt |
|---|---|---|---|
| Teststufe 2 (Dateisystem) | … | SEC-H01–H02, SEC-D01–D02, SEC-C01–C03, SEC-PUB01, SEC-WZ03 (9) | … |
| Teststufe 3 (HTTP/Playwright) | … | SEC-H03–H06, SEC-M01–M03, SEC-PUB02–PUB04, SEC-W01, SEC-WZ01–WZ04, SEC-HDR01–HDR04 (18) | … |
| Beide | … | SEC-WZ03 (1) | … |

Prioritätsverteilung:

| Priorität | G+S | P | SEC | Gesamt | Anteil |
|---|---|---|---|---|---|
| Hoch | 26 | 19 | 14 | **59** | 50% |
| Mittel | 32 | 10 | 8 | **50** | 43% |
| Niedrig | 4 | 0 | 4 | **8** | 7% |

---

**AP S6-08 — Endekriterien**

Neue Zeile in Tabelle:

| Teststufe / Querschnitt | Endekriterien |
|---|---|
| Sicherheitstest | Alle MUSS-Prüfpunkte (SEC-H01–H06, SEC-C01–C03, SEC-W01, SEC-WZ01–WZ04) grün; SOLL-Prüfpunkte grün oder als Upstream-Befund dokumentiert; KANN-Prüfpunkte (SEC-HDR01–HDR04) dokumentiert |

---

**AP S6-09 — Testorakel**

10 neue Zeilen in Tabelle (1:1 aus security_plan.md §8):

| Orakel | Gilt für Feature-Matrix-IDs | Methode |
|---|---|---|
| Upstream-Quellcode: `data/.htaccess` (statische Datei) | SEC-H01, SEC-H02 | Dateiinhalt als Referenz: `Require all denied` |
| Upstream-Quellcode: `data/index.php` (statische Datei) | SEC-D01, SEC-D02 | Dateiinhalt als Referenz: `header('Location: ../index.php')` |
| Upstream-Quellcode: `resources/views/setup/config.ini.phtml` | SEC-C01, SEC-C02 | Template definiert erwartetes Format (PHP-Guard, INI-Keys) |
| Apache HTTP-Spezifikation (RFC 7231, Status 403) | SEC-H03–SEC-H06, SEC-M01 | HTTP 403 = Zugriff verboten; Body darf keine Credentials enthalten |
| Upstream-Quellcode: `ReadConfigIni.php`, `SetupWizard.php` | SEC-W01, SEC-WZ01–SEC-WZ04 | Middleware-Logik: `file_exists()` → Lock; Wizard-HTML-Selektoren |
| Upstream-Quellcode: `PublicFiles.php` | SEC-PUB02–SEC-PUB04 | `file_get_contents()` statt PHP-Execution; `!str_contains($path, '..')` |
| Upstream-Quellcode: `SecurityHeaders.php` | SEC-HDR01–SEC-HDR03 | Middleware setzt Header-Werte direkt im Code |
| Upstream-Quellcode: `Auth::checkMediaAccess()`, `MediaFileDownload` | SEC-M02, SEC-M03 | Rollenbasierte Zugriffskontrolle: Visitor → kein Zugriff, Member → Zugriff |
| Dateisystem-Semantik: `stat()` Permissions | SEC-C03 | umask-Default des PHP-Prozesses; world-readable = potenzielle Schwäche |
| Apache-Konfiguration: `ServerTokens` Default | SEC-HDR04 | Default `ServerTokens Full` → Versionsinfo; gehärtete Config → `Prod` |

---

**AP S6-10 — Testentwurfsverfahren**

5 neue Zeilen in Tabelle (1:1 aus security_plan.md §9):

| Verfahren (ISTQB) | Domäne / Feature-Matrix-IDs | Begründung |
|---|---|---|
| **Entscheidungstabellentest** | SEC-H03–SEC-H06, SEC-M01–SEC-M03 | Kombination URL-Pfad × HTTP-Methode × erwarteter Status (403/200/302). Entscheidungstabelle: `.htaccess` greift ja/nein × Auth vorhanden ja/nein |
| **Erfahrungsbasierter Test** | SEC-H06, SEC-PUB04 | URL-Encoding-Varianten und Path-Traversal-Muster aus OWASP Testing Guide. Keine formale Spezifikation für Umgehungsversuche |
| **Anwendungsfall-Test** | SEC-WZ01–SEC-WZ04 | End-to-End-Szenario: Frische Distribution → Wizard durchlaufen → lauffähige Instanz (6 Wizard-Schritte) |
| **Äquivalenzklassenbildung** | SEC-HDR01–SEC-HDR04, SEC-PUB02–SEC-PUB03 | Header: vorhanden/korrekt vs. fehlend/falsch. `public/`-Zugriff: Datei vs. Verzeichnis vs. Traversal |
| **Grenzwertanalyse** | SEC-C03 | Datei-Permissions: Grenze bei world-readable-Bit (0644 vs. 0640 vs. 0600) |

---

**AP S6-11 — Produktrisiken**

8 neue Zeilen in Produktrisiken-Tabelle (R14–R21):

| Risiko-ID | Risiko | Wahrscheinlichkeit | Auswirkung | Maßnahme (Feature-Matrix-IDs) |
|---|---|---|---|---|
| R14 | DB-Credentials über HTTP zugänglich (`data/config.ini.php`) | Niedrig | Kritisch | SEC-H03, SEC-H04, SEC-H06 |
| R15 | Setup-Wizard nach Ersteinrichtung erneut aufrufbar (Admin-Takeover) | Niedrig | Kritisch | SEC-W01, SEC-WZ04 |
| R16 | Mediendateien ohne Zugriffskontrolle per Direkt-URL abrufbar | Niedrig | Hoch | SEC-M01–SEC-M03, SEC-H05 |
| R17 | Path-Traversal ermöglicht Dateizugriff außerhalb `/public/` | Niedrig | Kritisch | SEC-PUB04 |
| R18 | Fehlende Security-Headers ermöglichen Clickjacking/MIME-Sniffing | Mittel | Mittel | SEC-HDR01–SEC-HDR03 |
| R19 | `config.ini.php` world-readable (fehlender `chmod` im Wizard) | Mittel | Hoch | SEC-C03 |
| R20 | Schutzdateien (`data/.htaccess`, `data/index.php`) fehlen in Distribution | Niedrig | Kritisch | SEC-H01, SEC-H02, SEC-D01, SEC-D02 |
| R21 | Server-Banner verrät Apache-Version (Information Disclosure) | Hoch | Niedrig | SEC-HDR04 |

---

**AP S6-12 — Überdeckungsstrategie**

Neuen Absatz nach der bestehenden Ratchet-Beschreibung ergänzen:

> **Sicherheitstest-Track:** Anweisungsüberdeckung (pcov) ist für den Sicherheitstest nicht
> anwendbar — der Distribution-Container enthält kein pcov, keine Dev-Dependencies und keinen
> PHPUnit-Runner. Stattdessen gelten drei alternative Metriken:
>
> | Aspekt | Metrik |
> |---|---|
> | Prüfpunkt-Abdeckung | 26/26 Prüfpunkte implementiert und ausgeführt |
> | Angriffsmuster-Abdeckung | URL-Encoding (9 Varianten), Path-Traversal (5 Varianten) durchlaufen |
> | Vektor-Abdeckung | Alle 8 Angriffsvektoren durch mindestens einen Prüfpunkt adressiert |
>
> **Vektor-zu-Prüfpunkt-Mapping:**
>
> | Vektor | Adressiert durch |
> |---|---|
> | V1 — Direktzugriff `data/` | SEC-H03, SEC-H04, SEC-H06 |
> | V2 — Direktzugriff `data/media/` | SEC-H05, SEC-M01 |
> | V3 — Datei-Permissions | SEC-C03 |
> | V4 — Directory Listing | SEC-PUB03 |
> | V5 — Wizard nach Setup | SEC-W01, SEC-WZ04 |
> | V6 — Fehlende `.htaccess` | SEC-H01, SEC-H02 |
> | V7 — Path-Traversal | SEC-PUB04 |
> | V8 — Security-Headers | SEC-HDR01–SEC-HDR04 |

---

**AP S6-13 — Fehlermanagement**

Neue Zeile in Tabelle:

| Fehlerzustand in... | Vorgehen |
|---|---|
| **Apache-Konfiguration** (z.B. Server-Banner) | Dokumentieren als Deployment-Empfehlung. Kein Upstream-Issue, da nicht webtrees-Code. |

---

**AP S6-14 — Implementierungs-Fahrplan**

Neue Phase einfügen (nach Phase 11):

| Phase | Status | Ergebnis |
|---|---|---|
| Phase 12 — Sicherheitstest | **Verifiziert** | 26 Prüfpunkte (SEC-H01–SEC-HDR04). Distribution-Container (`Containerfile.security`), Setup-Wizard via Playwright, 9 Dateisystem-Assertions + 21 Playwright-HTTP-Tests. 24/26 grün, 1 Upstream-Befund (SEC-C03: config.ini.php world-readable), 1 Deployment-Empfehlung (SEC-HDR04: Apache Server-Banner). |

---

**AP S6-15 — Verfolgbarkeit (Kleinänderung)**

Bestehenden Absatz erweitern um SEC-*-IDs:

> **Bidirektionale Abfrage:**
> - Vorwärts: `grep -r "SEC-H01" layer4-e2e/ scripts/`
> - Rückwärts: `// @see SEC-H01` (Playwright) oder `# @see SEC-H01` (Shell)

---

**AP S6-16 — Bekannte Fehler (Kleinänderung)**

Zwei neue Einträge:

**Upstream-Befund: `config.ini.php` world-readable (SEC-C03)**
Setup-Wizard erzeugt `config.ini.php` via `file_put_contents()` ohne anschließendes `chmod`. Datei ist 644 (world-readable). Potenzielle Schwäche auf Shared-Hosting-Umgebungen.

**Deployment-Empfehlung: Apache Server-Banner (SEC-HDR04)**
Apache ServerTokens Default (`Full`) gibt Versionsinfo preis. Kein webtrees-Code, sondern Apache-Konfiguration. Empfehlung: `ServerTokens Prod` in Produktionsumgebungen.

---

**AP S6-17 — Abdeckungsmatrix + Zusammenfassung (Kleinänderung)**

Neuer Abschnitt "Sicherheit (SEC-H01–SEC-HDR04)":

| # | Feature | Shell-Assertions | Playwright-Security | Status |
|---|---------|-----------------|---------------------|--------|
| SEC-H01 | `.htaccess` Existenz | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-H02 | `.htaccess` Inhalt | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-H03 | HTTP-Zugriff `data/` blockiert | — | `data-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-H04 | HTTP-Zugriff `config.ini.php` blockiert | — | `data-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-H05 | HTTP-Zugriff `data/media/` blockiert | — | `data-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-H06 | URL-Encoding umgeht `.htaccess` nicht | — | `data-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-D01 | `data/index.php` Existenz | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-D02 | `data/index.php` Redirect-Logik | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-C01 | Config PHP-Guard | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-C02 | Config DB-Credentials | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-C03 | Config Datei-Permissions | `security-filesystem-checks.sh` ⚠ | — | **Upstream-Befund** |
| SEC-M01 | Direkter Media-Zugriff blockiert | — | `media-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-M02 | Media-Route ohne Auth | — | `media-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-M03 | Media-Route mit Auth | — | `media-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-PUB01 | `public/index.php` Existenz | `security-filesystem-checks.sh` ✅ | — | **Abgedeckt** |
| SEC-PUB02 | `public/index.php` keine PHP-Execution | — | `public-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-PUB03 | Kein Directory Listing `/public/` | — | `public-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-PUB04 | Path-Traversal blockiert | — | `public-access.spec.ts` ✅ | **Abgedeckt** |
| SEC-W01 | Wizard nach Setup gesperrt | — | `setup-lock.spec.ts` ✅ | **Abgedeckt** |
| SEC-WZ01 | Wizard erscheint bei Erstaufruf | — | `wizard-setup.spec.ts` ✅ | **Abgedeckt** |
| SEC-WZ02 | Wizard prüft Schreibrechte | — | `wizard-setup.spec.ts` ✅ | **Abgedeckt** |
| SEC-WZ03 | Wizard erzeugt `config.ini.php` | `security-filesystem-checks.sh` ✅ | `wizard-setup.spec.ts` ✅ | **Abgedeckt** |
| SEC-WZ04 | Wizard sperrt sich selbst | — | `wizard-setup.spec.ts` ✅ | **Abgedeckt** |
| SEC-HDR01 | `X-Content-Type-Options` | — | `security-headers.spec.ts` ✅ | **Abgedeckt** |
| SEC-HDR02 | `X-Frame-Options` | — | `security-headers.spec.ts` ✅ | **Abgedeckt** |
| SEC-HDR03 | `Referrer-Policy` | — | `security-headers.spec.ts` ✅ | **Abgedeckt** |
| SEC-HDR04 | Server-Banner | — | `security-headers.spec.ts` ⚠ | **Deployment-Empfehlung** |

Zusammenfassungstabelle um SEC-Spalte erweitern:

| Status | G | S | P | SEC | Gesamt |
|---|---|---|---|---|---|
| **Abgedeckt** | 23 | 39 | 29 | 24 | **115** (98%) |
| Upstream-Befund | 1 | 0 | 0 | 1 | **2** (2%) |
| Deployment-Empfehlung | 0 | 0 | 0 | 1 | **1** (<1%) |

### Phasen-Übersicht

| Phase | Inhalt | Prüfpunkte | Status |
|-------|--------|------------|--------|
| S1 | Infrastruktur (Containerfile + Compose + Makefile) | — | **Verifiziert** |
| S2 | Wizard-Automatisierung | SEC-WZ01–SEC-WZ04 | **Verifiziert** |
| S3 | Dateisystem-Assertions | SEC-H01, SEC-H02, SEC-D01, SEC-D02, SEC-C01–SEC-C03, SEC-PUB01, SEC-WZ03 | **Verifiziert** |
| S4 | HTTP-Zugriffstests | SEC-H03–SEC-H06, SEC-PUB02–SEC-PUB04, SEC-W01 | **Verifiziert** |
| S5 | Media + Security-Headers | SEC-M01–SEC-M03, SEC-HDR01–SEC-HDR04 | **Verifiziert** |
| S6 | Dokumentation + Integration | — | **Verifiziert** |

### Abhängigkeiten

```
S1 (Infrastruktur)
 └─→ S2 (Wizard) ─── Wizard muss laufen, bevor Post-Wizard-Tests möglich sind
      ├─→ S3 (Dateisystem) ─── SEC-C01–SEC-C03, SEC-WZ03 brauchen Wizard-Ergebnis
      ├─→ S4 (HTTP-Zugriff) ─── Tests gegen laufende Instanz
      └─→ S5 (Media + Headers) ─── Tests gegen laufende Instanz
           └─→ S6 (Doku) ─── Erst nach allen Tests
```

**Anmerkung S3:** Die Dateisystem-Checks in `security-filesystem-checks.sh` haben zwei
Ausführungszeitpunkte: VOR dem Wizard (SEC-H01, SEC-H02, SEC-D01, SEC-D02, SEC-PUB01)
und NACH dem Wizard (SEC-C01–SEC-C03, SEC-WZ03). Das Script muss beide Zeitpunkte
unterstützen (Parametersteuerung oder zwei Aufrufe).

---

## Änderungshistorie

*Erstellt: 2026-03-28 — Eigenständiger Implementierungsplan auf Basis des Planprompts. Enthält Statuskonzept, alle Analyse-Ergebnisse, Infrastruktur-Spezifikationen, 26 Prüfpunkte (Feature-Matrix SEC-H01–SEC-HDR04), ISTQB-konforme Strukturelemente (Testorakel, Endekriterien, Testentwurfsverfahren, Produktrisiken R14–R21, Überdeckungsstrategie, Fehlermanagement, Testkonventionen, Verfolgbarkeit), 6 Implementierungsphasen (S1–S6) mit Abhängigkeitsgraph.*
