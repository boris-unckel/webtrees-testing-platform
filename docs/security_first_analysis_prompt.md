# Analyseprompt — Sicherheit des webtrees-Upstream nach Fresh Install

> **Zweck:** Strukturierte Analyse der Sicherheitseigenschaften, die der
> **webtrees-Upstream-Code** in einer produktionsidentischen Instanz gewährleistet.
> Ergebnis ist ein belastbares Planprompt für Sicherheitstests, die prüfen, ob
> Upstream-Schutzmechanismen in Produktion greifen.
>
> **Pipeline-Position:** Idee → **Analyseprompt (you are here)** → Planprompt → Plan → Implementierung → Ergebnisdokumentation
>
> **Datum:** 2026-03-28
>
> **Aktualisiert:** 2026-03-28 — Upstream-Analyse abgeschlossen, offene Fragen beantwortet,
> Scope-Korrektur, Zwei-Track-Architektur beschlossen

---

## 1. Kontext und Zwei-Track-Architektur

### 1.1 Ausgangslage

Die webtrees-testing-platform betreibt einen Podman-Compose-Stack mit Apache + PHP 8.5
und MySQL 8. Die **bestehenden Fachtests** (Layer 1–5) nutzen die webtrees-Source per
read-only-Mount aus `../webtrees-upstream/webtrees/`. Das Setup-Skript
(`scripts/setup-webtrees.sh`) erzeugt programmatisch eine lauffähige Instanz — ohne den
Browser-Setup-Wizard.

Bestehende Testinfrastruktur:
- **Layer 3 (Komponentenintegrationstest):** 19 Testklassen, 274 Testfälle (PHPUnit + MySQL)
- **Layer 4 (Systemtest):** 20 Playwright-Testdateien (Browser, Chromium)
- Privacy- und Zugriffskontrolltests existieren bereits (Rollen, RESN, Relationship Privacy)

### 1.2 Problem: Dev-Source ≠ Produktions-Distribution

Der bestehende Fachtest-Track mountet den **Git-Checkout** (Entwicklungs-Source) in den
Container. In Produktion wird eine **Distribution** (`webtrees-x.y.z.zip`) entpackt und
der **Setup-Wizard** im Browser durchlaufen. Die Unterschiede sind sicherheitsrelevant:

| Aspekt | Fachtest-Track (Dev-Source) | Produktion (Distribution) |
|--------|---------------------------|--------------------------|
| **Quelle** | Git-Checkout, read-only Mount | `composer webtrees:build` → ZIP entpacken |
| **`data/`-Verzeichnis** | Named Volume überlagert Upstream → **Schutzdateien fehlen** | Aus ZIP entpackt → `.htaccess` + `index.php` vorhanden |
| **Ersteinrichtung** | `setup-webtrees.sh` (Wizard umgangen) | 6-Schritt-Setup-Wizard im Browser |
| **`config.ini.php`** | Von unserem Skript (ohne PHP-Guard) | Vom Wizard-Template (mit PHP-Guard `; <?php return; ?>`) |
| **`vendor/`** | `composer install` inkl. Dev-Dependencies | Vorgebundelt, nur Prod-Dependencies |
| **`composer.json`** | Vorhanden | **Nicht in Distribution** (`.gitattributes` `export-ignore`) |
| **`tests/`** | Vorhanden | **Nicht in Distribution** |

### 1.3 Entscheidung: Zwei getrennte Tracks

| Track | Zweck | Instanz | Setup-Pfad |
|-------|-------|---------|------------|
| **Fachtest** (bestehend) | Funktionale Tests, Regression, Privacy | Dev-Source (read-only Mount) | `setup-webtrees.sh` |
| **Sicherheitstest** (neu) | Upstream-Schutzmechanismen in Produktion | **Distribution-Build** (ZIP → Container) | **Upstream-Setup-Wizard** (via Playwright) |

**Begründung der Trennung:**
- Fachtests profitieren vom extrem kurzen Roundtrip (Mount, kein Build) — bewährt und stabil
- Sicherheitstests müssen das **produktionsidentische** Ergebnis prüfen: Distribution + Wizard
- Vermeidung von Scheinsicherheit: Kein „drumherum bauen" um Upstream-Defizite

### 1.4 Ziel

Prüfen, ob die Sicherheitsmechanismen des **webtrees-Upstream-Codes** eine Instanz
schützen, die **exakt so aufgesetzt wurde, wie es ein Anwender in Produktion tun würde:**
Distribution entpacken → Setup-Wizard durchlaufen → Instanz betreiben.

### 1.5 Testphilosophie

**Was wir testen:** Das Ergebnis des Upstream-Produktionspfads — Distribution-Dateien,
Wizard-generierte Konfiguration, `.htaccess`, PHP-Middleware, Routing, Security-Headers.

**Was wir NICHT testen:** Unser eigenes Test-Setup (`setup-webtrees.sh`). Das gehört zum
Fachtest-Track und hat eigene Qualitätssicherung.

**Konsequenz:** Der Sicherheitstest-Container bildet eine Produktions-Deployment ab.
Der Setup-Wizard ist Teil des Testszenarios, nicht Vorbedingung.

---

## 2. Bedrohungsmodell (Setup-spezifisch)

Fokus: Was kann nach einem frischen Setup falsch sein, das zu einer Sicherheitslücke führt?

### 2.1 Angriffsvektoren

| ID | Vektor | Beschreibung | Betroffene Ressource |
|----|--------|--------------|----------------------|
| V1 | **Direkter HTTP-Zugriff auf `data/`** | Angreifer ruft `http://host/data/config.ini.php` direkt auf → DB-Credentials geleakt | `data/config.ini.php` |
| V2 | **Direkter HTTP-Zugriff auf `data/media/`** | Mediendateien ohne webtrees-Zugriffskontrolle abrufbar (Umgehung der Privacy-Logik) | `data/media/*` |
| V3 | **Datei-Permissions zu offen** | `config.ini.php` world-readable → andere Prozesse/User lesen Credentials | `data/config.ini.php` |
| V4 | **Directory Listing** | Apache zeigt Verzeichnisinhalt von `data/`, `public/` oder Root → Informationsleck | Alle Verzeichnisse |
| V5 | **Setup-Wizard nach Setup erreichbar** | Erneuter Setup-Durchlauf möglich, obwohl config.ini.php existiert → Admin-Takeover | Setup-Wizard-Route |
| V6 | **Schwache Default-Credentials** | Hart kodierte Admin-/Test-Passwörter in Produktion → Credential-Stuffing | User-Tabelle |
| V7 | **Fehlende `.htaccess`-Dateien** | `data/.htaccess` nicht vorhanden oder wirkungslos → V1/V2 greifen | `.htaccess`-Dateien |
| V8 | **`public/`-Verzeichnis: unerwünschte Dateitypen** | Hochgeladene oder platzierte Dateien in `public/` (z.B. `.php`) werden ausgeführt | `public/` |

### 2.2 Abgrenzung

**In Scope:**
- Zustand des Dateisystems nach Wizard-Setup (Distribution + Wizard-Durchlauf)
- HTTP-Erreichbarkeit von Verzeichnissen/Dateien
- Apache-Konfiguration (`.htaccess`, `AllowOverride`, `FallbackResource`)
- webtrees-eigene Schutzmechanismen (Setup-Wizard, Setup-Lock, Security-Headers)
- Verhalten der Upstream-PHP-Logik und statischen Schutzdateien
- Korrektheit der vom Wizard erzeugten `config.ini.php` (PHP-Guard, Inhalt)

**Nicht in Scope (vorerst):**
- SQL-Injection, XSS, CSRF (separate Testdomäne, nicht Setup-spezifisch)
- TLS/HTTPS-Konfiguration (Testumgebung ist HTTP-only)
- Netzwerk-Isolation zwischen Containern (Podman-Ebene, nicht webtrees-Ebene)
- Fachtest-Track (`setup-webtrees.sh`, Dev-Source-Mount) — eigener Qualitätspfad

---

## 3. Upstream-Analyse (abgeschlossen 2026-03-28)

> Ergebnis der Code-Inspektion von `../webtrees-upstream/webtrees/`.
> Diese Befunde sind die Grundlage für die Prüfpunkte.

### 3.1 Mehrstufiges Schutzmodell von webtrees

webtrees implementiert **Defense in Depth** mit drei Ebenen:

| Ebene | Mechanismus | Schutzwirkung | Ausfallverhalten |
|-------|-------------|---------------|------------------|
| **1. Apache** | `data/.htaccess` → `Require all denied` | Blockiert jeden HTTP-Zugriff auf `data/` | Wenn `AllowOverride` deaktiviert → wirkungslos |
| **2. PHP-Fallback** | `data/index.php` → `header('Location: ../index.php')` | Redirect, falls `.htaccess` nicht greift | Nur für Directory-Request, nicht für Datei-Pfade |
| **3. PHP-Guard** | `config.ini.php` erste Zeile: `; <?php return; ?>` | Wenn Datei als PHP ausgeführt wird → leere Ausgabe | Schützt nur config.ini.php, nicht andere Dateien in `data/` |

### 3.2 Setup-Wizard-Lock

**Mechanismus:** `ReadConfigIni`-Middleware (3. im Stack, vor Routing und DB):
- `file_exists(Webtrees::CONFIG_FILE)` → ja: config laden, normaler Request
- `file_exists(...)` → nein: `SetupWizard`-Handler fängt **alle** Requests ab

**Bewertung:** Solide. Kein Bypass ohne Datei-Löschung. Kein separater Lock-Mechanismus nötig.

### 3.3 Media-Serving

**Nicht direkt per Apache.** Zwei Request-Handler mit Zugriffskontrolle:
- `MediaFileDownload` → `Auth::checkMediaAccess($media)` (rollenbasiert)
- `MediaFileThumbnail` → zusätzlich HMAC-Signaturprüfung (`glide-key`, `md5`)

**Bewertung:** Korrekte Zugriffskontrolle auf Applikationsebene. Direkter HTTP-Zugriff auf
`data/media/` wird durch `.htaccess` (Ebene 1) blockiert.

### 3.4 `public/`-Handling

**`public/index.php`:** Nur Loader (`require __DIR__ . '/../index.php'`).

**`PublicFiles`-Middleware:** Served statische Dateien aus `/public/`:
- Path-Traversal-Schutz: `!str_contains($path, '..')`
- MIME-Type-Mapping über Extension
- Kein PHP-Execution — Dateien werden als `file_get_contents()` + Content-Type ausgeliefert

**Bewertung:** `public/index.php` tut, was es soll — es ist der Einstiegspunkt, wenn
`/public/` als DocumentRoot konfiguriert ist (empfohlenes Deployment-Modell). In unserem
Container ist `/var/www/html` das DocumentRoot (nicht `/var/www/html/public`), daher greift
`FallbackResource /index.php` und `public/index.php` wird über die `PublicFiles`-Middleware
als statische Datei behandelt — **es wird NICHT als PHP ausgeführt**, sondern per
`file_get_contents()` als `text/html` ausgeliefert. Das ist ein sicheres Verhalten: kein
PHP-Code wird dadurch exponiert, weil der Source nur ein `require`-Statement enthält.

### 3.5 Security-Headers

Die `SecurityHeaders`-Middleware setzt:
- `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`
- `Content-Security-Policy`, `Strict-Transport-Security` (wenn HTTPS)

### 3.6 Statische Schutzdateien im Repo

| Datei | Status | Inhalt |
|-------|--------|--------|
| `data/.htaccess` | **Im Repo** (statisch, nicht generiert) | `Require all denied` (Apache 2.4) + Legacy-Fallback (Apache 2.2) |
| `data/index.php` | **Im Repo** (statisch) | `header('Location: ../index.php')` |
| `public/index.php` | **Im Repo** (statisch) | `require __DIR__ . '/../index.php'` |
| `public/.htaccess` | **Existiert nicht** | — |
| Root `.htaccess` | **Existiert nicht** | — |

---

## 4. Beantwortete Fragen

| ID | Frage | Antwort | Implikation |
|----|-------|---------|-------------|
| O1 | Upstream-Befund: Rot oder Workaround? | **Rot bleiben.** Test dokumentiert Upstream-Problem → Issue bei `fisharebest/webtrees`. | Kein `@expectedFailure`, kein Skip. Test ist bewusst rot. |
| O2 | Zielumgebung? | **Container**, aber Test prüft Upstream-Logik (PHP, `.htaccess`), nicht unser Setup-Skript. | Layer-3-Tests laufen im Container, Layer-4-Tests senden HTTP an den Container. |
| O3 | Shallow oder Deep? | **Whitebox von außen.** Echte HTTP-Requests mit Insider-Wissen über interne Pfade und Schutzmechanismen. | Path-Traversal, URL-Encoding, Case-Variation gehören dazu. |
| O4 | Layer-Zuordnung? | **Layer 3** (Dateisystem) + **Layer 4** (HTTP). | Kein neuer Querschnitt. |
| O5 | `public/`-Bedrohung? | Minimalrechte prüfen. `public/index.php` muss tun, was es soll — und ist das sinnvoll? | Test prüft: (a) kein Directory Listing, (b) `index.php` wird korrekt behandelt, (c) keine unerwarteten Dateien. |
| O6 | Credentials in Testumgebung? | **Nicht beantwortet** — wird als Out-of-Scope behandelt (reine Testumgebung). | Keine Tests für Produktions-Credential-Stärke. |

---

## 5. Prüfpunkte — Upstream-Schutzmechanismen (Testscope)

> **Scope-Regel:** Jeder Prüfpunkt testet das Ergebnis des Upstream-Produktionspfads
> (Distribution + Setup-Wizard). Der Sicherheitstest-Container bildet eine
> produktionsidentische Instanz ab — Distribution entpackt, Wizard durchlaufen.
>
> Alle Dateisystem-Assertions (Layer 3) und HTTP-Tests (Layer 4) laufen gegen
> diese Distribution-Instanz, nicht gegen den Dev-Source-Mount.

### 5.1 `data/`-Verzeichnis — Apache-Schutzschicht (Upstream: `data/.htaccess`)

**Upstream-Mechanismus:** Statische `.htaccess` im Repo mit `Require all denied`.
Schützt das gesamte `data/`-Verzeichnis inkl. aller Unterverzeichnisse.

| ID | Aspekt | Erwartung | Upstream-Mechanismus | Prüfmethode | Layer |
|----|--------|-----------|----------------------|-------------|-------|
| H1 | `.htaccess` existiert im Upstream | Ja | Statische Datei: `data/.htaccess` | Dateisystem-Assertion (Upstream-Datei im Container) | 3 |
| H2 | Inhalt korrekt | `Require all denied` (Apache 2.4) + Legacy | Statischer Dateiinhalt | Dateiinhalt prüfen | 3 |
| H3 | Apache blockiert `GET /data/` | 403 | `.htaccess` + `AllowOverride All` | HTTP GET `/data/` | 4 |
| H4 | Apache blockiert `GET /data/config.ini.php` | 403 (NICHT 200, NICHT Dateiinhalt) | `.htaccess` blockiert den gesamten Pfad | HTTP GET `/data/config.ini.php` | 4 |
| H5 | Apache blockiert `GET /data/media/` | 403 | `.htaccess` gilt für Unterverzeichnisse | HTTP GET `/data/media/` | 4 |
| H6 | URL-Encoding umgeht `.htaccess` nicht | 403 | Apache dekodiert vor `.htaccess`-Prüfung | HTTP GET mit Encoding-Varianten | 4 |

### 5.2 `data/index.php` — Redirect-Fallback (Upstream: Defense-in-Depth)

**Upstream-Mechanismus:** `header('Location: ../index.php')` — greift, wenn Apache
`.htaccess` nicht auswertet (z.B. nginx, `AllowOverride None`).

| ID | Aspekt | Erwartung | Upstream-Mechanismus | Prüfmethode | Layer |
|----|--------|-----------|----------------------|-------------|-------|
| D1 | Existiert im Upstream | Ja | Statische Datei: `data/index.php` | Dateisystem-Assertion | 3 |
| D2 | Enthält Redirect-Logik | `header('Location: ../index.php')` | Statischer Dateiinhalt | Dateiinhalt prüfen | 3 |

**Anmerkung:** Der Redirect (D3) ist in unserem Teststack nicht erreichbar, weil `.htaccess`
vorher greift. Das ist korrektes Verhalten — `.htaccess` ist die primäre Schutzschicht,
`data/index.php` ist Fallback für nicht-Apache-Server. Nicht testen, aber dokumentieren.

### 5.3 `config.ini.php` — Wizard-Erzeugung + PHP-Guard (Upstream: Defense-in-Depth)

**Upstream-Mechanismus:** Der Setup-Wizard erzeugt `config.ini.php` über das Template
`resources/views/setup/config.ini.phtml`. Erste Zeile: `; <?php return; ?>` — wenn die
Datei trotz `.htaccess` als PHP ausgeführt wird, gibt sie eine leere Antwort zurück.

| ID | Aspekt | Erwartung | Upstream-Mechanismus | Prüfmethode | Layer |
|----|--------|-----------|----------------------|-------------|-------|
| C1 | Wizard-erzeugte `config.ini.php` enthält PHP-Guard | `; <?php return; ?>` als erste Zeile | `SetupWizard::createConfigFile()` → Template | Datei im Container lesen + Pattern-Match | 3 |
| C2 | `config.ini.php` enthält DB-Credentials | Ja (dbhost, dbuser, dbpass, dbname) | Wizard-Template interpoliert Formularwerte | Dateiinhalt prüfen (Werte vorhanden, Format korrekt) | 3 |
| C3 | `config.ini.php` Permissions nach Wizard | Prüfen, was `file_put_contents()` erzeugt | Upstream nutzt `file_put_contents()` **ohne chmod** | `stat()` → Permissions dokumentieren | 3 |

**Anmerkung zu C3:** Der Upstream-Wizard setzt **keine expliziten Permissions** auf
`config.ini.php`. Die Datei erhält die Default-Permissions aus der PHP-Prozess-umask.
Das ist ein potenzieller Upstream-Befund: Wenn die Datei world-readable ist, wäre das
eine Schwäche (zusätzlich zu `.htaccess` geschützt, aber nicht defense-in-depth auf
Dateisystem-Ebene). Test dokumentiert den Ist-Zustand.

### 5.4 `data/media/` — Media-Zugriffskontrolle (Upstream: Routing + Auth)

**Upstream-Mechanismus:** Mediendateien liegen in `data/media/`, werden aber ausschließlich
über PHP-Handler ausgeliefert: `MediaFileDownload` (mit `Auth::checkMediaAccess()`) und
`MediaFileThumbnail` (zusätzlich HMAC-Signaturprüfung via `glide-key`).

| ID | Aspekt | Erwartung | Upstream-Mechanismus | Prüfmethode | Layer |
|----|--------|-----------|----------------------|-------------|-------|
| M1 | Direkter HTTP-Zugriff blockiert | 403 (nicht 200) | `.htaccess` blockiert `data/` komplett | HTTP GET `/data/media/<bekannte-datei>` | 4 |
| M2 | Media über App-Route ohne Auth | Kein Zugriff (403 oder Redirect zu Login) | `Auth::checkMediaAccess()` | HTTP GET auf Media-Route als Visitor | 4 |
| M3 | Media über App-Route mit Auth | 200 | `MediaFileDownload` liefert nach Auth-Check | HTTP GET auf Media-Route als Member | 4 |

### 5.5 `public/` — Statische Assets (Upstream: `PublicFiles`-Middleware)

**Upstream-Mechanismus:** Die `PublicFiles`-Middleware served Dateien aus `/public/` als
statische Inhalte via `file_get_contents()`. Path-Traversal-Schutz: `!str_contains($path, '..')`.
`public/index.php` ist ein Loader für das empfohlene Deployment-Modell (DocumentRoot = `/public/`).

| ID | Aspekt | Erwartung | Upstream-Mechanismus | Prüfmethode | Layer |
|----|--------|-----------|----------------------|-------------|-------|
| P1 | `public/index.php` existiert | Ja | Statische Datei im Repo | Dateisystem-Assertion | 3 |
| P2 | `/public/index.php` wird nicht als PHP ausgeführt | Statischer Inhalt (kein Side-Effect) | `PublicFiles` liefert via `file_get_contents()` | HTTP GET `/public/index.php` → Response-Body prüfen | 4 |
| P3 | Kein Directory Listing auf `/public/` | Kein Listing (404 oder Redirect) | `PublicFiles` matched nur Dateien, kein Verzeichnis | HTTP GET `/public/` | 4 |
| P4 | Path-Traversal über `/public/` blockiert | Kein Zugriff auf Dateien außerhalb `/public/` | `!str_contains($path, '..')` | HTTP GET `/public/../data/config.ini.php` | 4 |

### 5.6 Setup-Wizard-Lock (Upstream: `ReadConfigIni`-Middleware)

**Upstream-Mechanismus:** Die `ReadConfigIni`-Middleware (3. im Stack) prüft
`file_exists(Webtrees::CONFIG_FILE)`. Wenn die Datei existiert, wird normal geroutet.
Wenn nicht, fängt der `SetupWizard`-Handler **alle** Requests ab.

| ID | Aspekt | Erwartung | Upstream-Mechanismus | Prüfmethode | Layer |
|----|--------|-----------|----------------------|-------------|-------|
| W1 | Wizard nicht erreichbar nach Setup | Kein Setup-Formular, normales Routing | `ReadConfigIni`: `file_exists()` → normaler Handler | HTTP GET auf Setup-URL | 4 |

### 5.7 Security-Headers (Upstream: `SecurityHeaders`-Middleware)

**Upstream-Mechanismus:** Die `SecurityHeaders`-Middleware (5. im Stack) setzt
Sicherheits-Header auf jede Response.

| ID | Aspekt | Erwartung | Upstream-Mechanismus | Prüfmethode | Layer |
|----|--------|-----------|----------------------|-------------|-------|
| S1 | `X-Content-Type-Options` | `nosniff` | `SecurityHeaders`-Middleware | HTTP Response-Header prüfen | 4 |
| S2 | `X-Frame-Options` | `SAMEORIGIN` oder `DENY` | `SecurityHeaders`-Middleware | HTTP Response-Header prüfen | 4 |
| S3 | `Referrer-Policy` | Gesetzt | `SecurityHeaders`-Middleware | HTTP Response-Header prüfen | 4 |
| S4 | Server-Banner | Kein detaillierter Versionsstring | Apache-Konfiguration (nicht Upstream-PHP) | HTTP Response-Header `Server:` prüfen | 4 |

**Anmerkung zu S4:** `ServerTokens`/`ServerSignature` sind Apache-Konfiguration, nicht
webtrees-Upstream-Code. Prüfpunkt S4 testet daher die Deployment-Konfiguration, nicht
den Upstream. In die Kategorie „Empfehlung für Produktionsbetrieb" einordnen, nicht als
Upstream-Schutzmechanismus.

### 5.8 Setup-Wizard-Durchlauf (Upstream: `SetupWizard`-Handler)

**Upstream-Mechanismus:** Der 6-Schritt-Wizard wird in Produktion genau einmal durchlaufen.
Im Sicherheitstest-Track wird der Wizard per Playwright automatisiert — er ist Teil des
Testszenarios, nicht Vorbedingung.

| ID | Aspekt | Erwartung | Upstream-Mechanismus | Prüfmethode | Layer |
|----|--------|-----------|----------------------|-------------|-------|
| WZ1 | Wizard erscheint bei erster Anfrage | Setup-Formular (Schritt 1: Sprache) | `ReadConfigIni`: kein `config.ini.php` → `SetupWizard` | HTTP GET auf `/` → Wizard-HTML prüfen | 4 |
| WZ2 | Wizard prüft Schreibrechte auf `data/` | Schritt 2 zeigt Erfolg | `SetupWizard::checkFolderIsWritable()` | Playwright: Wizard Schritt 2 durchlaufen | 4 |
| WZ3 | Wizard erzeugt `config.ini.php` | Datei existiert nach Schritt 6 | `SetupWizard::createConfigFile()` | Dateisystem-Assertion nach Wizard | 3 |
| WZ4 | Wizard sperrt sich nach Abschluss selbst | Kein erneuter Setup möglich | `ReadConfigIni` → `file_exists()` → normales Routing | HTTP GET nach Wizard → kein Setup-Formular | 4 |

**Anmerkung:** WZ1–WZ4 sind der Kern des Distribution-Tracks. Sie testen den
Upstream-Ersteinrichtungspfad end-to-end: Frische Distribution → Wizard → lauffähige,
geschützte Instanz. Die übrigen Prüfpunkte (H*, D*, C*, M*, P*, S*) prüfen danach den
Zustand der durch den Wizard erzeugten Instanz.

---

## 6. Distribution-Container-Architektur (neu)

### 6.1 Build-Prozess

```
webtrees-upstream/webtrees/          (Git-Checkout, unstable)
        │
        ▼  composer webtrees:build
webtrees-<version>.zip               (Distribution-Paket)
        │
        ▼  Containerfile.security (entpacken + Apache + PHP)
Security-Test-Container               (produktionsidentisch)
        │
        ▼  Playwright: Setup-Wizard durchlaufen
Lauffähige Instanz                    (wie Produktion nach Ersteinrichtung)
        │
        ▼  Sicherheitstests (Layer 3 + Layer 4)
Testergebnisse
```

### 6.2 Neues Containerfile (`Containerfile.security`)

Basis: Identisch zum bestehenden `Containerfile.webtrees` (Apache + PHP 8.5), aber:
- **Kein** read-only Mount der Git-Source
- Distribution-ZIP wird im Build-Step entpackt (`COPY` oder Multi-Stage-Build)
- `data/` ist ein beschreibbares Verzeichnis (kein Volume-Overlay nötig — Dateien aus ZIP)
- `vendor/` ist bereits aus der Distribution vorgebundelt
- **Kein** `composer install` im Container — alles kommt aus dem ZIP

### 6.3 Neues Compose-Profil

```yaml
# compose.yaml — Security-Track (Profil)
services:
  webtrees-security:
    build:
      context: .
      dockerfile: Containerfile.security
    profiles:
      - security
    # ... MySQL, Netzwerk etc. teilen sich mit Fachtest-Track
```

### 6.4 Neues Makefile-Target

```makefile
test-security: ## Sicherheitstest (Distribution + Wizard + Prüfpunkte)
	# 1. Distribution bauen (aus Upstream-Source)
	# 2. Security-Container starten
	# 3. Wizard via Playwright durchlaufen
	# 4. Sicherheitstests ausführen (Layer 3 + Layer 4)
```

### 6.5 Abgrenzung Fachtest-Track ↔ Sicherheitstest-Track

| Aspekt | Fachtest-Track (bestehend) | Sicherheitstest-Track (neu) |
|--------|---------------------------|---------------------------|
| **Container** | `webtrees` (Dev-Source Mount) | `webtrees-security` (Distribution) |
| **Source** | `../webtrees-upstream/webtrees/:ro` | Distribution-ZIP, entpackt im Image |
| **Setup** | `setup-webtrees.sh` (Wizard umgangen) | Playwright → Setup-Wizard (6 Schritte) |
| **`data/`** | Named Volume (überlagert Upstream) | Aus ZIP (`.htaccess`, `index.php` vorhanden) |
| **Makefile** | `make test-unit`, `make test-integration`, `make test-e2e` | `make test-security` |
| **Compose-Profil** | Default | `security` |
| **MySQL** | Geteilte Instanz oder eigene | Eigene DB (sauberer Zustand für Wizard) |
| **Playwright** | Bestehender Container | Derselbe Playwright-Container |

---

## 7. Analyseschritte (Status)

### A1 — Upstream-Code-Inspektion ✅

Abgeschlossen. Befunde in Abschnitt 3 dokumentiert. Wesentliche Erkenntnisse:
- Defense-in-Depth mit 3 Ebenen (Apache → PHP-Redirect → PHP-Guard)
- Media-Serving ausschließlich über PHP mit Auth-Check + HMAC
- `public/index.php` ist sinnvoll als alternativer Einstiegspunkt (DocumentRoot = `/public/`)
- Setup-Wizard-Lock über Middleware, nicht über Dateisystem-Lock
- **Upstream setzt keine Datei-Permissions auf `config.ini.php`** — `file_put_contents()` ohne chmod

### A2 — Container-Konfiguration ✅

Relevante Konfiguration im bestehenden Containerfile (Basis auch für Security-Containerfile):
- `AllowOverride All` → `.htaccess` wird ausgewertet
- `FallbackResource /index.php` → webtrees-Routing aktiv
- Rewrite-Modul aktiviert (`a2enmod rewrite`)
- **Keine explizite Härtung** (ServerTokens, ServerSignature nicht gesetzt)

### A3 — Distribution-Build-Prozess ✅

Abgeschlossen. `composer webtrees:build` erzeugt ein Distribution-ZIP:
- `git archive` → nur tracked Files
- `composer install --no-dev` → nur Prod-Dependencies
- Translations kompilieren (PO → PHP)
- `.gitattributes` `export-ignore` entfernt: `tests/`, `composer.json`, `.github/`,
  `resources/css/`, `resources/js/`, `webpack.mix.js`
- Distribution enthält: `app/`, `public/` (pre-built), `vendor/`, `data/` (mit
  `.htaccess` + `index.php`), `resources/views/`, `resources/lang/*/messages.php`
- **Benötigt Node.js** für `npm run production` (Asset-Compilation)

### A4 — Gap-Analyse bestehende Tests ⏳

Abzugleichen im Planprompt:
- Bestehende Privacy-Tests (P01–P29) decken rollenbasierte Zugriffskontrolle ab
- Bestehende E2E-Tests `auth.spec.ts` decken Login/Logout ab
- M2/M3 (Media-Auth) überschneidet sich teilweise mit bestehenden Privacy-Tests
- **Komplett neue Prüfdomäne:** Upstream-Schutzdateien, HTTP-Zugriff auf `data/`,
  Directory Listing, Setup-Wizard end-to-end, Setup-Lock, Security-Headers,
  Path-Traversal, `PublicFiles`-Middleware, Wizard-erzeugte `config.ini.php`

### A5 — ISTQB-Einordnung ⏳

Zuordnung im Planprompt:
- **Testart:** Sicherheitstest (nicht-funktionale Testart, ISTQB Glossar de_DE v4.7.1)
- **Teststufe:** Komponentenintegrationstest (Layer 3, Dateisystem) + Systemtest (Layer 4, HTTP)
- **Testentwurfsverfahren:** Entscheidungstabellentest (Pfad × Methode × erwarteter Status),
  erfahrungsbasierter Test (Umgehungsversuche, Whitebox-Wissen)

---

## 8. Erwartetes Ergebnis → Planprompt

Das Planprompt wird aus diesem Analyseprompt erstellt und enthält:

1. **Infrastruktur-Spezifikation** für den Security-Track:
   - `Containerfile.security` (Distribution-basiert)
   - Compose-Profil `security`
   - Makefile-Target `test-security`
   - Distribution-Build-Step (`composer webtrees:build`, benötigt Node.js)
2. **Wizard-Automatisierung** (Playwright): 6-Schritt-Setup als Vorbedingung der Tests
3. **Prüfpunkt-Priorisierung** (MUSS / SOLL / KANN) basierend auf Risiko
4. **Konkrete Testfall-Spezifikationen** pro ID (H1–H6, D1–D2, C1–C3, M1–M3, P1–P4, W1, WZ1–WZ4, S1–S4)
5. **Whitebox-Angriffsmuster** für HTTP-Tests (Path Traversal, URL-Encoding, Case-Variation)
6. **Reihenfolge** der Implementierung (kleinteilig, reviewbar)
7. **Upstream-Befund-Protokoll** (was passiert, wenn ein Test rot ist: Issue-Template, Annotation)

---

## Anhang A: Ist-Zustand Setup-Skript

### Sicherheitsrelevante Aktionen in `setup-webtrees.sh`

| Zeile | Aktion | Sicherheitsrelevanz |
|-------|--------|---------------------|
| 82–96 | `config.ini.php` generieren | chmod 600, chown www-data — **einzige explizite Härtung** |
| 176 | Admin-User erstellen | Passwort `admin` hart kodiert |
| 266–296 | Test-User erstellen | Passwort `password` hart kodiert |
| 254–257 | Privacy-Baum öffentlich setzen | `private=0` — bewusste Testentscheidung |

### Nicht im Setup-Skript (potenziell fehlend)

- Keine Prüfung, ob `data/.htaccess` existiert
- Keine Prüfung der Apache-Konfiguration
- Kein Erstellen/Prüfen von `data/media/`
- Kein Setup-Lock-Mechanismus (das übernimmt webtrees selbst via `config.ini.php`-Existenz)

## Anhang B: Upstream-Middleware-Stack (Reihenfolge)

1. `ErrorHandler`
2. `EmitResponse`
3. **`ReadConfigIni`** ← Setup-Wizard-Lock
4. `BaseUrl`
5. **`SecurityHeaders`** ← X-Frame-Options, CSP, HSTS
6. `HandleExceptions`
7. **`PublicFiles`** ← Statische Dateien aus `/public/`, Path-Traversal-Schutz
8. `ClientIp`
9. `ContentLength`
10. `CompressResponse`
11. `BadBotBlocker`
12. `UseDatabase`
13. `DebugLogger`
14. `UpdateDatabaseSchema`
15. `UseSession`
16. `UseLanguage`
17. `CheckForMaintenanceMode`
18. `UseTheme`
19. `DoHousekeeping`
20. `UseTransaction`
21. `CheckForNewVersion`
22. `LoadRoutes`
23. `RegisterGedcomTags`
24. `BootModules`
25. `Router` ← Request-Routing

## Anhang C: Differenz Upstream-Wizard vs. unser Setup-Skript

| Aspekt | Upstream `SetupWizard` | Unser `setup-webtrees.sh` |
|--------|------------------------|--------------------------|
| `config.ini.php` erzeugen | `file_put_contents()` (keine Permissions) | `cat >` + `chmod 600` + `chown www-data` |
| PHP-Guard erste Zeile | `; <?php return; ?>` (via Template) | Eigenes Format: `; webtrees configuration (generated by ...)` — **kein PHP-Guard** |
| DB-Migration | Via `SetupWizard::createConfigFile()` | Via `MigrationService::updateSchema()` |
| Admin-User | Interaktiv (Browser-Formular) | Hart kodiert (`admin`/`admin`) |

## Anhang D: Fachtest-Track — optionaler Fix (eigener Scope)

Das `setup-webtrees.sh` des Fachtest-Tracks erzeugt `config.ini.php` **ohne** die
PHP-Guard-Zeile (`; <?php return; ?>`). Das Volume überlagert `data/.htaccess` und
`data/index.php`. Diese Defizite betreffen den Fachtest-Track — nicht den
Sicherheitstest-Track, der die Distribution + Wizard nutzt.

**Optional für Fachtest-Track:** Guard-Zeile in `setup-webtrees.sh` ergänzen und
Upstream-Schutzdateien ins Volume seeden. Priorisierung: niedrig, da der Fachtest-Track
keine Sicherheitseigenschaften prüft.

## Anhang E: Distribution-Build-Anforderungen

Der `composer webtrees:build`-Befehl im Upstream benötigt:
- `composer` (PHP-Paketmanager)
- `node` + `npm` (für Asset-Compilation via `npm run production` / webpack)
- `git` (für `git archive` und `git describe`)
- `zip` (für ZIP-Erstellung)
- `perl` (für PO-Datei-Verarbeitung, falls vorhanden)

**Relevanz für Containerfile.security:** Der Build-Step kann als Multi-Stage-Build im
Containerfile oder als separater Makefile-Step erfolgen. Die Distribution wird einmal
gebaut und dann ins Security-Container-Image kopiert.
