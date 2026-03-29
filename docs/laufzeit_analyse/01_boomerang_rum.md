<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# A1: Boomerang RUM mit OpenTelemetry Plugin — Analyse

## 1. Fakten

### 1.1 Boomerang (Akamai)

**Repository:** `akamai/boomerang` (GitHub, aktiv — letzter Commit 2026-03-23)
**Hauptentwickler:** nicjansma (Nic Jansma, 525 Commits), bluesmoon (Philip Tellis, 820 Commits)
**Lizenz:** BSD License
**Aktueller stabiler Release:** `1.815.1` (veröffentlicht 2025-08-15)

**Verfügbarkeit als Package:**
- **npm:** `boomerangjs@1.815.1` — enthält Roh-Sourcen (boomerang.js + plugins/*.js), KEIN fertiges Bundle
- **CDN:** Kein offizielles CDN. Kein pre-built Bundle in den GitHub Releases (Release 1.815.1 hat null Assets)
- **GitHub Release:** Nur Tag, keine Build-Artifacts

**Build-Pipeline:**
- Grunt-basiert (`grunt clean build`)
- Liest `plugins.json` (oder `plugins.user.json`) für Plugin-Auswahl
- Verkettung via `concat`-Task, Minifikation via UglifyJS
- Ergebnis: `build/boomerang-<version>.min.js` (einzelne Datei)
- Alternativ: Synchrones Laden ohne Build möglich (`<script src="boomerang.js">` + einzelne Plugin-Dateien)
- Build-Flavors: `minimal`, `default`, `default-errors`, `default-spa` etc.

**Relevante Plugins für RUM im Test-Kontext:**

| Plugin | Datei | Funktion |
|---|---|---|
| RT (Round-Trip) | `plugins/rt.js` | Seitenlade-Zeiten, Page Load Timer |
| NavigationTiming | `plugins/navtiming.js` | W3C Navigation Timing API Daten |
| ResourceTiming | `plugins/restiming.js` | Waterfall-Daten aller Ressourcen |
| PaintTiming | `plugins/painttiming.js` | FCP, LCP |
| EventTiming | `plugins/eventtiming.js` | FID (First Input Delay) |
| Errors | `plugins/errors.js` | JavaScript Error Tracking |

**Minimale Konfiguration:**
```javascript
BOOMR.init({
  beacon_url: "http://example.com/beacon/"
});
```

`beacon_url` ist die URL, an die Boomerang seine Beacons (Performance-Metriken als Query-Parameter oder POST-Body) sendet. Dies ist ein proprietäres Boomerang-Format — NICHT OTLP.

### 1.2 Boomerang-OpenTelemetry-Plugin (inspectIT)

**Repository:** `inspectIT/boomerang-opentelemetry-plugin` (GitHub, aktiv — letzter Commit 2025-07-15)
**Hauptentwickler:** EddeCCC (30 Commits), mariusoe (20 Commits), JonasKunz (4 Commits) + dependabot
**Lizenz:** Apache-2.0
**Aktueller stabiler Release:** `2.0.0-2` (veröffentlicht 2025-07-14, "Security patch")
**npm:** NICHT auf npm veröffentlicht
**Sterne:** 10

**Verfügbarkeit:**
- **GitHub Release Assets (pre-built):**
  - `boomerang-opentelemetry.js` (639 KB, minified) — Produktions-Bundle
  - `boomerang-opentelemetry.dev.js` (7.3 MB, unminified) — Debug-Bundle
  - `boomerang-opentelemetry-sboms.zip` (23 KB) — SBOM (CycloneDX)
- Das Bundle enthält NICHT Boomerang selbst, nur das OTel-Plugin + Abhängigkeiten (zone.js, OTel JS SDK, regenerator-runtime)

**Interne Abhängigkeiten (in `2.0.0-2`):**

| Paket | Version |
|---|---|
| `@opentelemetry/api` | ^1.9.0 |
| `@opentelemetry/sdk-trace-web` | ^2.0.0 |
| `@opentelemetry/exporter-trace-otlp-http` | ^0.200.0 (gelockt auf 0.200.0) |
| `@opentelemetry/instrumentation-document-load` | ^0.45.0 |
| `@opentelemetry/instrumentation-xml-http-request` | ^0.200.0 |
| `@opentelemetry/instrumentation-fetch` | ^0.200.0 |
| `@opentelemetry/instrumentation-user-interaction` | ^0.45.0 |
| `@opentelemetry/propagator-b3` | ^2.0.0 |
| `@opentelemetry/opentelemetry-browser-detector` | ^0.200.0 |
| `zone.js` | ^0.13.3 |

**Protokoll:** Das Plugin verwendet `@opentelemetry/exporter-trace-otlp-http`, welches **OTLP/HTTP mit JSON-Encoding** (Content-Type: `application/json`) auf den Pfad `/v1/traces` sendet. KEIN Zipkin, KEIN proprietäres Format.

**Collector-URL-Auflösung (Priorität):**
1. Explizit gesetzt via `collectorConfiguration.url`
2. Abgeleitet aus `beacon_url`: Wenn `beacon_url` auf `/beacon` endet, wird `/beacon` durch `/spans` ersetzt
3. Wenn beides fehlt: `undefined` (der OTel-JS-SDK-Default greift: `http://localhost:4318/v1/traces`)

**Entscheidend:** Für direkte Kommunikation mit dem OTel Collector MUSS `collectorConfiguration.url` explizit gesetzt werden, z.B.:
```javascript
BOOMR.init({
  beacon_url: '/beacon/',
  OpenTelemetry: {
    collectorConfiguration: {
      url: 'http://localhost:4318/v1/traces'
    }
  }
});
```

**Beziehung Boomerang-Beacons vs. OTel-Traces:**
- Boomerang-Beacons (beacon_url): Proprietäres Format, NICHT für OTel-Collector gedacht. Braucht einen Beacon-Receiver/Server (z.B. inspectIT Ocelot EUM Server). Für reines OTel-Tracing im Test-Kontext **nicht zwingend benötigt**.
- OTel-Traces: Gehen direkt via OTLP/HTTP an den Collector. Das ist der relevante Datenfluss.

**Features des Plugins:**
- Automatische Instrumentierung: Document Load, XHR, Fetch, User Interactions
- W3C Trace Context Propagation (auch B3 konfigurierbar)
- Transaction Recording (Document Load als Root-Span, Server-Timing Header Integration)
- Manuelle Instrumentierung via `BOOMR.plugins.OpenTelemetry.getTracer()`

**Bekannte offene Issues (relevant):**
- #40: "Propagation not working with xhr and fetch" (CORS-bezogen, seit 2022 offen)
- #89: zone.js noch auf 0.13.3 (veraltet)
- #110–#113: Diverse OTel-Dependency-Updates ausstehend

### 1.3 OTel Collector Konfiguration

**Aktuelle Konfiguration im Stack:**
- Nur gRPC-Receiver auf Port `0.0.0.0:4317`
- Kein HTTP-Receiver konfiguriert
- Port 4318 wird nicht exponiert in `compose.yaml`

**Benötigte Änderungen für Browser-Traces:**
Der Collector benötigt einen **OTLP/HTTP Receiver** auf Port 4318 mit CORS-Konfiguration, da der Browser (Host-Maschine) Cross-Origin-Requests an den Collector (Container) sendet.

```yaml
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317
      http:
        endpoint: 0.0.0.0:4318
        cors:
          allowed_origins:
            - "http://localhost:8080"
          allowed_headers:
            - "*"
          max_age: 7200
```

Zusätzlich muss in `compose.yaml` Port 4318 exponiert werden:
```yaml
otel-collector:
  ports:
    - "4317:4317"
    - "4318:4318"
```

**Collector-Version:** Aktuell `otel/opentelemetry-collector-contrib:latest` — sollte für Reproduzierbarkeit auf eine spezifische Version gepinnt werden.

---

## 2. Bewertung

### 2.1 Machbarkeit: HOCH

Die Kombination Boomerang + OTel-Plugin + OTel-Collector ist technisch solide:
- Das OTel-Plugin sendet Standard-OTLP/HTTP JSON — kein proprietäres Format, kein Proxy nötig
- Der OTel Collector unterstützt OTLP/HTTP nativ mit CORS
- Pre-built JS Bundles des OTel-Plugins sind als GitHub Release Assets verfügbar

### 2.2 Aufwand

| Arbeitsschritt | Aufwand |
|---|---|
| Collector-Config: HTTP-Receiver + CORS hinzufügen | Gering (YAML-Änderung) |
| compose.yaml: Port 4318 exponieren | Trivial |
| Boomerang bauen oder synchron laden | Mittel (Grunt-Build oder Multi-Script-Einbindung) |
| OTel-Plugin-Bundle herunterladen und einbinden | Gering (wget + Script-Tag) |
| Boomerang + OTel-Plugin in webtrees-Seiten injizieren | Mittel (Apache-Config oder PHP-Template) |
| Testen und Debugging | Mittel bis Hoch |

**Geschätzter Gesamtaufwand:** 2–4 Personentage für eine funktionierende Integration.

### 2.3 Risiken

| Risiko | Schwere | Wahrscheinlichkeit | Mitigation |
|---|---|---|---|
| **Boomerang hat kein fertiges Bundle** — Grunt-Build-Pipeline muss aufgesetzt oder synchrones Multi-File-Loading verwendet werden | Mittel | Sicher | Grunt-Build in Build-Container oder synchrones Laden mit wenigen Plugins |
| **CORS-Probleme** Browser-zu-Collector | Mittel | Hoch | CORS-Config im Collector, Alternativ: Reverse-Proxy via Apache |
| **OTel-Plugin-Projekt hat geringe Community** (10 Sterne, 3–4 aktive Entwickler) | Mittel | Möglich | Funktional ausgereift, OTel-JS-SDK dahinter ist stabil |
| **zone.js-Konflikte** (das Plugin lädt zone.js, was globale Patches macht) | Niedrig | Niedrig | webtrees ist kein SPA, zone.js sollte harmlos sein |
| **Offener Bug #40** (Propagation mit XHR/Fetch) | Niedrig | Möglich | Für reines RUM-Monitoring irrelevant, nur für verteiltes Tracing kritisch |
| **Beacon-Endpoint-Verwirrung** — Boomerang braucht beacon_url, aber wir brauchen keinen Beacon-Receiver | Niedrig | Möglich | beacon_url auf /dev/null oder nicht-existenten Endpoint setzen; nur collectorConfiguration.url ist relevant |

---

## 3. Empfehlung

### Empfohlener Ansatz: Synchrones Laden + Pre-Built OTel-Plugin

**Schritt 1 — Boomerang ohne Build-Pipeline:**
Statt die Grunt-Build-Pipeline aufzusetzen, die 68 devDependencies (inkl. Bower, Selenium, WebDriver) hat, empfehle ich synchrones Laden der benötigten Dateien direkt aus dem npm-Paket:

```html
<script src="/rum/boomerang.js"></script>
<script src="/rum/plugins/rt.js"></script>
<script src="/rum/plugins/navtiming.js"></script>
<script src="/rum/plugins/restiming.js"></script>
<script src="/rum/plugins/painttiming.js"></script>
<script src="/rum/plugins/eventtiming.js"></script>
<script src="/rum/boomerang-opentelemetry.js"></script>
<script>
BOOMR.init({
  beacon_url: '/dev/null',
  OpenTelemetry: {
    samplingRate: 1.0,
    collectorConfiguration: {
      url: 'http://localhost:4318/v1/traces'
    },
    serviceName: 'webtrees-browser',
    commonAttributes: {
      'deployment.environment': 'test'
    }
  }
});
</script>
```

**Begründung:**
- Vermeidet die schwere Grunt/Bower-Build-Pipeline
- Die Roh-JS-Dateien aus dem npm-Paket sind eigenständig lauffähig (IIFE-Pattern, kein Module-Bundling nötig)
- Die relevanten Plugins (RT, NavTiming, ResourceTiming, PaintTiming, EventTiming) sind wenige hundert KB unminifiziert
- In einem Test-Kontext ist Minifikation nicht kritisch

**Schritt 2 — OTel-Plugin als GitHub Release Asset:**
Download von `boomerang-opentelemetry.js` (639 KB, minified) aus Release `2.0.0-2`:
```
https://github.com/inspectIT/boomerang-opentelemetry-plugin/releases/download/2.0.0-2/boomerang-opentelemetry.js
```
SHA256-Checksum zur Verifikation generieren und pinnen.

**Schritt 3 — Collector-Config erweitern:**
HTTP-Receiver mit CORS aktivieren, Port 4318 exponieren.

**Schritt 4 — JS-Injection in webtrees:**
Via Apache `mod_substitute` oder webtrees-Modul den Script-Block vor `</head>` injizieren, ohne den webtrees-Sourcecode zu verändern (→ siehe A2: Injection-Bewertung).

### Gepinnte Versionen

| Komponente | Version | Quelle |
|---|---|---|
| Boomerang | `1.815.1` | npm: `boomerangjs@1.815.1` |
| Boomerang-OTel-Plugin | `2.0.0-2` | GitHub Release: `inspectIT/boomerang-opentelemetry-plugin` |
| OTel Collector Contrib | zu pinnen | Docker: `otel/opentelemetry-collector-contrib` |

### Alternativer Ansatz (falls RUM-Beacons AUCH benötigt werden)

Falls neben OTel-Traces auch die klassischen Boomerang-Beacons gewünscht sind (für Beacon-Analyse), würde ein Beacon-Receiver benötigt. Der inspectIT Ocelot EUM Server könnte das, ist aber ein zusätzlicher Container und erhöht die Komplexität deutlich. Für den Test-Kontext ist dies NICHT empfohlen — die OTel-Traces reichen aus.

---

## 4. Offene Punkte

### 4.1 Vor Implementierung zu klären

1. **JS-Injection-Methode:** Wie werden die Script-Tags in die webtrees-HTML-Seiten injiziert? Optionen:
   - Apache `mod_substitute` (Container-seitig, kein Eingriff in webtrees-Source)
   - Custom webtrees-Modul (sauberer, aber mehr Aufwand)
   - Playwright-seitige Injection (`page.addScriptTag()` — nur für E2E-Tests, nicht für manuelles Browsen)
   → Bewertung in A2 (Injection-Bewertung)

2. **beacon_url Handling:** Boomerang erfordert `beacon_url`. Wenn kein Beacon-Receiver läuft, erzeugt Boomerang 404-Fehler im Browser-Netzwerk-Tab und möglicherweise Konsolen-Warnungen. Zu prüfen: Kann `beacon_url` auf `about:blank` oder leer gesetzt werden, ohne dass Boomerang abbricht?

3. **CORS im Container-Netzwerk:** Wenn Tests innerhalb des Containers laufen (Playwright-Container → webtrees-Container), ist der Browser-Origin `http://webtrees:80`. Die CORS-Config muss beide Szenarien abdecken (Host-Browser `http://localhost:8080` UND Container-Browser).

4. **Server-Timing Header für verteiltes Tracing:** Das OTel-Plugin kann `Server-Timing`-Response-Header parsen, um Browser-Traces mit Backend-Traces zu verknüpfen. Der webtrees-Container sendet bereits OTel-Traces via PHP. Zu klären: Emittiert das PHP-OTel-SDK automatisch `Server-Timing`-Header? Falls nicht, wäre eine Apache-Config oder PHP-Middleware nötig.

5. **zone.js Seiteneffekte:** Das OTel-Plugin-Bundle enthält zone.js (Angular-Abhängigkeit), das `setTimeout`, `Promise`, `EventTarget.addEventListener` und andere Browser-APIs global patcht. In einem webtrees-Kontext (jQuery-basiert, kein SPA-Framework) sollte das harmlos sein, muss aber verifiziert werden.

6. **Größe der Boomerang-Plugins:** Unminifizierte synchrone Ladung von boomerang.js (5360 Zeilen) + 5 Plugins + OTel-Bundle (639 KB) ergibt erheblichen Overhead. Für einen Test-Stack akzeptabel, aber die Seitenlade-Performance wird beeinflusst — die RUM-Messungen messen also eine durch RUM-Instrumentation veränderte Performance. Zu entscheiden: Ist das für den Anwendungsfall akzeptabel?

7. **Collector-Image-Pinning:** Das aktuelle `compose.yaml` verwendet `otel/opentelemetry-collector-contrib:latest`. Dies sollte unabhängig von Boomerang auf eine spezifische Version gepinnt werden.
