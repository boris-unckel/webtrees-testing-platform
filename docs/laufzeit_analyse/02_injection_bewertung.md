<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# A2: Boomerang Injection — Modul vs. mod_substitute — Bewertung

## 1. Fakten

### 1.1 Ansatz A: webtrees-Modul (ModuleGlobalInterface)

#### 1.1.1 Verfügbare Interfaces für HTML-Injection

webtrees bietet zwei relevante Interfaces für das Einfügen von Inhalten in die Seitenstruktur:

**`ModuleGlobalInterface`** (`app/Module/ModuleGlobalInterface.php`):
- `headContent(): string` — Roh-HTML am Ende von `<head>`, VOR `</head>`
- `bodyContent(): string` — Roh-HTML am Ende von `<body>`, VOR `</body>`

**`ModuleFooterInterface`** (`app/Module/ModuleFooterInterface.php`):
- `getFooter(ServerRequestInterface $request): string` — HTML im `<footer>`-Bereich, INNERHALB des `<body>`
- Hat Zugriff auf den `ServerRequestInterface` (und damit auf Tree, User, etc.)
- Wird zwischen `</main>` und den `<script>`-Tags gerendert — also VOR dem JavaScript

**Fazit:** `ModuleGlobalInterface` ist das korrekte Interface für Script-Injection. `headContent()` ermöglicht Einbettung VOR `</head>` (synchron, vor allem anderen JS). `bodyContent()` ermöglicht Einbettung am Ende des `<body>`, NACH `vendor.min.js`, `webtrees.min.js` und dem `View::stack('javascript')`.

#### 1.1.2 Rendering-Reihenfolge in den Layouts

**`layouts/default.phtml`** (Hauptlayout, alle normalen Seiten):
```
<head>
  ... Meta, CSS ...
  <?= View::stack('styles') ?>
  <?= ...ModuleGlobalInterface...->headContent() ?>     ← HIER (Zeile 69)
</head>
<body>
  ... Header, Main, Footer ...
  <script src="vendor.min.js"></script>
  <script src="webtrees.min.js"></script>
  <script>/* Colorbox/Gallery init */</script>
  <?= View::stack('javascript') ?>
  <?= ...ModuleGlobalInterface...->bodyContent() ?>     ← HIER (Zeile 185)
</body>
```

**`layouts/administration.phtml`** (Admin-Panel):
- Gleiche Struktur, ABER mit Filter (Zeile 40 und 89):
  ```php
  $module instanceof ModuleCustomInterface || $module instanceof CustomCssJsModule ? '' : $module->headContent()
  ```
- Custom-Module (die `ModuleCustomInterface` implementieren) werden im Admin-Panel **ausgeschlossen**
- Für Boomerang irrelevant: RUM-Tracing im Admin-Panel ist nicht prioritär

**`layouts/setup.phtml`**, **`layouts/error.phtml`**, **`layouts/offline.phtml`**:
- Enthalten KEIN ModuleGlobalInterface-Rendering
- Boomerang würde auf diesen Seiten nicht geladen
- Für den Test-Kontext irrelevant (Setup ist einmalig, Error/Offline sind Randfälle)

**`layouts/ajax.phtml`**, **`layouts/report.phtml`**:
- Kein ModuleGlobalInterface-Rendering
- ajax.phtml: Nur Content + JavaScript-Stack (AJAX-Responses)
- report.phtml: Reine Report-Ausgabe ohne JS

#### 1.1.3 Existierende Referenzimplementierungen

**Google Analytics Modul** (`app/Module/GoogleAnalyticsModule.php`):
- Implementiert: `ModuleAnalyticsInterface`, `ModuleConfigInterface`, `ModuleExternalUrlInterface`, `ModuleGlobalInterface`
- Injiziert `<script async src="...gtag.js">` + Inline-Config via `headContent()`
- Hat Zugriff auf Tree-Name, User, Access-Level via `Registry::container()->get(ServerRequestInterface::class)` + `Validator::attributes($request)`
- Snippet wird als `.phtml`-Template gerendert

**Custom CSS/JS Modul** (`app/Module/CustomCssJsModule.php`):
- Implementiert: `ModuleConfigInterface`, `ModuleGlobalInterface`
- Gibt frei konfigurierbaren HTML-String zurück (aus DB-Preferences)
- `headContent()` und `bodyContent()` geben gespeicherten HTML-Code zurück

**CKEditor Modul** (`app/Module/CkeditorModule.php`):
- Implementiert: `ModuleExternalUrlInterface`, `ModuleGlobalInterface`
- Injiziert CKEditor-JS via `bodyContent()` (am Ende von body)
- Verwendet `asset()` für statische Dateien im `public/`-Verzeichnis

#### 1.1.4 Custom-Module: Lifecycle und Entdeckung

**Modul-Entdeckung** (`app/Services/ModuleService.php`, Methode `customModules()`):
- Sucht nach `modules_v4/*/module.php` (Glob-Pattern)
- Ordnername darf keine `.`, ` `, `[`, `]` enthalten und max. 30 Zeichen haben
- `module.php` muss via `return` ein Objekt liefern, das `ModuleCustomInterface` implementiert
- Modul-Name wird automatisch als `_<ordnername>_` gesetzt (mit Unterstrichen umrahmt)

**Auto-Aktivierung:**
- Neue Module werden automatisch in die `module`-Tabelle eingetragen
- `isEnabledByDefault()` bestimmt den initialen Status
- `AbstractModule::isEnabledByDefault()` gibt standardmäßig `true` zurück
- Ein Modul mit `isEnabledByDefault() = true` ist sofort nach Installation aktiv

**Asset-Serving für Custom-Module:**
- `ModuleCustomTrait::assetUrl(string $asset): string` — generiert URL via `route('module', ['action' => 'Asset', ...])`
- `ModuleCustomTrait::getAssetAction()` — Liefert Dateien aus `resourcesFolder()` als HTTP-Response
- Die JS-Dateien müssen NICHT im `public/`-Verzeichnis liegen — sie werden durch einen PHP-Handler ausgeliefert

#### 1.1.5 Kontext-Zugriff (User, Tree, XREF)

Ein `ModuleGlobalInterface`-Modul hat über den DI-Container Zugriff auf:
```php
$request = Registry::container()->get(ServerRequestInterface::class);
$tree = Validator::attributes($request)->treeOptional();  // kann null sein
$user = Validator::attributes($request)->user();
$route = Validator::attributes($request)->route();
```

Verfügbare Kontext-Daten:
- **Tree:** `$tree->name()`, `$tree->title()` (null wenn kein Baum selektiert)
- **User:** `$user->userName()`, `$user->id()` (Guest wenn nicht eingeloggt)
- **Access Level:** `Auth::accessLevel($tree, $user)`
- **Route:** `$route->name` (z.B. `individual-page`, `search-quick`, etc.)
- **XREF:** Nicht direkt — müsste aus Route-Attributen extrahiert werden

#### 1.1.6 Mount-Architektur im Stack

Die webtrees-Source ist als **read-only** gemountet:
```yaml
- ${WEBTREES_SOURCE:-./upstream/webtrees}:/var/www/html:ro,z
```

`modules_v4/` liegt innerhalb dieses ro-Mounts. Es gibt bereits einen Mechanismus für optionale Module:
```yaml
- ${MODULE_PATH:-./.empty-module}:/var/www/html/modules_v4/${MODULE_NAME:-_none}:ro,z
```

Dieses Overlay-Mount-Pattern kann für das Boomerang-Modul wiederverwendet werden. Das Modul würde:
1. Als eigenes Verzeichnis in diesem Repo liegen (z.B. `otel/boomerang-module/`)
2. Die Boomerang-JS-Dateien + OTel-Plugin + `module.php` enthalten
3. Via zusätzlichem Volume-Mount in `compose.yaml` nach `modules_v4/boomerang_otel/` gemountet werden

**Problem:** Der aktuelle `compose.yaml` hat nur einen `MODULE_PATH`/`MODULE_NAME`-Slot. Für Boomerang müsste ein zweiter fester Volume-Mount hinzugefügt werden.

### 1.2 Ansatz B: Apache mod_substitute

#### 1.2.1 Verfügbarkeit im Container-Image

Das Container-Image basiert auf `php:8.5-apache` (Debian Bookworm):

- **`mod_substitute`**: Im Apache2-Paket enthalten, standardmäßig NICHT aktiviert. Aktivierung: `a2enmod substitute`
- **`mod_sed`**: Alternative mit voller sed-Syntax. Ebenfalls verfügbar, nicht aktiviert.
- **`mod_filter`**: Für bedingte Output-Filter. Standardmäßig aktiviert.

Aktuell aktiviert der Containerfile nur: `a2enmod rewrite` (Zeile 37).

#### 1.2.2 Funktionsweise von mod_substitute

```apache
LoadModule substitute_module modules/mod_substitute.so

<Location "/">
    AddOutputFilterByType SUBSTITUTE text/html
    Substitute "s|</head>|<script src=\"/rum/boomerang.js\"></script></head>|i"
</Location>
```

**Wichtige Eigenschaften:**
- Arbeitet auf dem **Output-Stream** (Response Body) — modifiziert HTML bevor es an den Client geht
- `AddOutputFilterByType` beschränkt den Filter auf `text/html` Responses (JSON, CSS, JS, Bilder werden ignoriert)
- Pattern-Matching ist **zeilenbasiert** (nicht multi-line) — `</head>` muss in einer einzelnen Zeile stehen
- Default-Zeilenlänge: 1 MB (`SubstituteMaxLineLength`)
- Mehrere `Substitute`-Direktiven werden sequenziell angewendet

#### 1.2.3 Multi-line Script-Block Injection

Das Boomerang-Snippet ist ein Multi-Line-Block (~30 Zeilen). **Problem:** Die Apache-Config-Syntax unterstützt keine Mehrzeilen-Strings in `Substitute`-Direktiven. Der gesamte Ersetzungsstring muss in eine Zeile passen.

**Lösung:** Den gesamten BOOMR.init()-Block in eine externe JS-Datei auslagern:
```apache
Substitute "s|</head>|<script src=\"/rum/boomerang.js\"></script><script src=\"/rum/plugins/rt.js\"></script><script src=\"/rum/plugins/navtiming.js\"></script><script src=\"/rum/plugins/restiming.js\"></script><script src=\"/rum/plugins/painttiming.js\"></script><script src=\"/rum/plugins/eventtiming.js\"></script><script src=\"/rum/boomerang-opentelemetry.js\"></script><script src=\"/rum/boomerang-init.js\"></script></head>|i"
```

Das sind ~8 Script-Tags in einer langen Zeile. Funktioniert, ist aber fragil.

#### 1.2.4 Interaktion mit Content-Encoding

- `mod_deflate` (gzip-Kompression) muss NACH `mod_substitute` in der Filterkette laufen
- Standard: `mod_deflate` ist in `php:8.5-apache` NICHT aktiviert — kein Konflikt
- Falls `mod_deflate` später aktiviert wird: `mod_substitute` muss VOR `mod_deflate` stehen

#### 1.2.5 Performance-Impact

- Scannt jede HTML-Response zeilenweise nach dem Pattern
- Bei einfachen Patterns (String-Match `</head>`) ist der Overhead minimal (< 1ms pro Response)
- Nur `text/html` wird gefiltert, statische Assets sind nicht betroffen
- In der Praxis vernachlässigbar im Vergleich zu PHP-Ausführung und DB-Abfragen

### 1.3 Ansatz C: Playwright-seitige Injection

#### 1.3.1 Verfügbare APIs

- **`page.addScriptTag({url: '...'})`** — Fügt `<script>` dynamisch ein (asynchron!)
- **`page.addInitScript({path: '...'})`** — Führt JS im Page-Kontext aus, BEVOR andere Scripts laufen
- **`page.route()`** — Kann Responses abfangen und modifizieren

#### 1.3.2 Einschränkungen

- `addScriptTag()`: Asynchrones Laden — Boomerang wird NACH dem Page Load initialisiert, verpasst Navigation Timing
- `addInitScript()`: Läuft im Page-Kontext, aber BEVOR der DOM geladen ist — kann Boomerang nicht korrekt initialisieren
- Funktioniert NUR für Playwright-gesteuerte Tests — nicht für manuelles Browsen

### 1.4 Layout-Abdeckung im Vergleich

| Layout | ModuleGlobalInterface (A) | mod_substitute (B) | Playwright (C) |
|---|---|---|---|
| `default.phtml` (normal) | headContent + bodyContent | Ja (hat `</head>`) | Ja |
| `administration.phtml` | **Nein** (ModuleCustomInterface gefiltert) | Ja (hat `</head>`) | Ja |
| `setup.phtml` | Nein | Ja (hat `</head>`) | Ja |
| `error.phtml` | Nein | Ja (hat `</head>`) | Ja |
| `offline.phtml` | Nein | Ja (hat `</head>`) | Ja |
| `ajax.phtml` | Nein | Nein (kein `</head>`) | Bedingt |
| `report.phtml` | Nein | Ja (hat `</head>`) | Ja |

**Kritischer Fund:** Module in `modules_v4/` MÜSSEN `ModuleCustomInterface` implementieren (sonst werden sie von `customModules()` verworfen). Ein `ModuleCustomInterface`-Modul wird im Admin-Layout von `headContent()`/`bodyContent()` **ausgeschlossen** (Zeile 40, 89 in `administration.phtml`). Das ist eine systemische Einschränkung des Modul-Ansatzes.

---

## 2. Bewertung

### 2.1 Bewertungsmatrix

| Kriterium | Modul (A) | mod_substitute (B) | Playwright (C) |
|---|---|---|---|
| **Implementierungsaufwand** | Mittel (PHP-Modul + Asset-Management) | Gering (2–3 Apache-Direktiven + Containerfile-Zeile) | Gering (Config-Änderung) |
| **Wartbarkeit bei webtrees-Updates** | Gut (Module API ist stabil) | Sehr gut (völlig unabhängig von webtrees) | Sehr gut |
| **Kontext-Zugriff (User, Tree, XREF)** | Ja — voller Zugriff auf Request, Tree, User, Route | Nein — blinder String-Replace | Nein — Browser-Kontext |
| **Synchrones Laden garantiert** | Ja — `headContent()` synchron im HTML-Output | Ja — Script-Tags vor `</head>` | Nein — `addScriptTag()` ist asynchron |
| **Unabhängigkeit von Upstream** | Gut — keine Upstream-Änderung | Sehr gut — reine Apache-Konfiguration | Sehr gut |
| **Performance-Impact** | Minimal (PHP-Methode pro Request) | Minimal (String-Scan <1ms) | Keiner (Browser-seitig) |
| **Funktioniert für manuelles Browsen** | Ja | Ja | **Nein** |
| **Funktioniert für automatisierte Tests** | Ja | Ja | Ja (nur Playwright) |
| **Admin-Panel-Abdeckung** | **Nein** (ModuleCustomInterface gefiltert) | Ja | Ja |
| **Setup/Error/Offline-Seiten** | Nein | Ja | Ja |
| **Aktivierung** | Automatisch (isEnabledByDefault=true), erfordert DB | Sofort (Apache-Config) | Sofort |
| **Dynamische Attribute (Tree, User)** | Ja — BOOMR.init() mit PHP-Werten parametrisierbar | Nein — statischer Ersetzungsstring | Bedingt |

### 2.2 Detailbewertung Ansatz A (webtrees-Modul)

**Vorteile:**
1. **Kontext-Integration:** Einziger Ansatz, der webtrees-interne Daten (Tree-Name, User, Access-Level, Route) direkt als OTel-Attribute in die BOOMR.init()-Konfiguration einbetten kann
2. **Architektonisch sauber:** Folgt dem etablierten webtrees-Modul-Pattern (identisch zu Google Analytics)
3. **Asset-Serving via PHP:** JS-Dateien müssen nicht im DocumentRoot liegen
4. **Versionierung:** Modul kann in Git versioniert werden

**Nachteile / Risiken:**
1. **Admin-Panel-Lücke:** Custom-Module werden im Admin-Layout ausgefiltert
2. **Setup/Error-Seiten-Lücke:** Diese Layouts rendern kein ModuleGlobalInterface
3. **Modul-Mount erfordert compose.yaml-Änderung:** Zweiter Volume-Mount nötig
4. **Erster Request nach Container-Start:** Modul wird erst beim ersten HTTP-Request erkannt

**Aufwand:** ~0.5–1 Personentag

### 2.3 Detailbewertung Ansatz B (mod_substitute)

**Vorteile:**
1. **Maximale Abdeckung:** Funktioniert auf ALLEN Layouts mit `</head>` — inklusive Admin, Setup, Error
2. **Völlige Unabhängigkeit:** Kein PHP-Code, kein webtrees-Wissen nötig
3. **Minimaler Aufwand:** 1 Zeile im Containerfile + wenige Zeilen Apache-Config
4. **Keine DB-Abhängigkeit:** Funktioniert sofort

**Nachteile / Risiken:**
1. **Kein Kontext-Zugriff:** BOOMR.init() bekommt nur statische Werte
2. **Fragile Config-Syntax:** ~8 Script-Tags in einer Apache-Directive-Zeile
3. **Statische JS-Dateien brauchen einen Pfad:** Erfordert Apache-Alias oder COPY im Containerfile

**Aufwand:** ~0.25–0.5 Personentag

### 2.4 Detailbewertung Ansatz C (Playwright-Injection)

**Nicht als primärer Ansatz geeignet** — verpasst Navigation Timing Events. Möglich als Ergänzung für leichtgewichtige Custom-Metriken in Performance-Tests.

---

## 3. Empfehlung

### 3.1 Primärer Ansatz: mod_substitute (B) — ergänzt durch Modul-Option (A) für später

**Begründung:**

1. **Einstiegskosten:** mod_substitute ist in weniger als einer Stunde implementiert und getestet. Das Modul braucht länger.

2. **Abdeckung:** mod_substitute deckt ALLE Layouts ab (inklusive Admin). Das Modul hat eine systemische Lücke im Admin-Panel.

3. **Kontext ist initial verzichtbar:** Für den ersten Einsatz (E2E-Performance-Baseline, Trace-Sichtbarkeit in Jaeger) reichen statische OTel-Attribute (`service.name: webtrees-browser`, `deployment.environment: test`). Die webtrees-Seitenstruktur enthält im HTML bereits genug Information (Route im `<body class="wt-route-...">`).

4. **Upgrade-Pfad:** Falls später dynamische Attribute benötigt werden, kann das webtrees-Modul parallel implementiert werden.

### 3.2 Implementierungsskizze für mod_substitute

**Containerfile.webtrees — Änderungen:**

```dockerfile
# Apache-Module
RUN a2enmod rewrite substitute

# Boomerang + OTel-Plugin installieren
RUN mkdir -p /opt/rum/plugins
# Boomerang aus npm (Version gepinnt)
# OTel-Plugin von GitHub Release (SHA256 verifiziert)
# Init-Script
COPY otel/boomerang-init.js /opt/rum/

# Apache Alias + Substitute
RUN { \
    echo 'Alias /rum /opt/rum'; \
    echo '<Directory /opt/rum>'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
    echo '<Location "/">'; \
    echo '    AddOutputFilterByType SUBSTITUTE text/html'; \
    echo '    Substitute "s|</head>|<script src=\"/rum/boomerang.js\"></script>...</head>|i"'; \
    echo '</Location>'; \
    } > /etc/apache2/conf-available/boomerang.conf \
    && a2enconf boomerang
```

**otel/boomerang-init.js:**
```javascript
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
```

### 3.3 Optionaler Upgrade-Pfad: webtrees-Modul (A)

Falls dynamische Attribute benötigt werden, kann später ein Modul unter `otel/boomerang-module/` erstellt werden. Das Modul würde NEBEN mod_substitute existieren können, wenn die mod_substitute-Regel per Environment-Variable deaktivierbar ist.

### 3.4 Playwright-Ergänzung (C)

Unabhängig von A oder B empfohlen für Layer 5 (Performance-Tests): minimale Playwright-Integration mit nativer Performance API (`performance.getEntriesByType('navigation')`), unabhängig von Boomerang.

---

## 4. Offene Punkte

### 4.1 Vor Implementierung zu klären

1. **beacon_url `/dev/null`:** Verifizieren, dass Boomerang mit einer nicht-existierenden beacon_url keine Fehler wirft. Alternative: `beacon_url: 'about:blank'` oder `beacon_url: false`.

2. **Collector-URL im Container-Netzwerk vs. Host:** Browser im Playwright-Container → `http://otel-collector:4318/v1/traces` (Container-Netzwerk). Manuelles Browsen vom Host → `http://localhost:4318/v1/traces`. Die `boomerang-init.js` muss die URL dynamisch bestimmen oder es werden zwei Konfigurationen gepflegt.

3. **mod_substitute und FallbackResource:** Prüfen, ob mod_substitute korrekt mit `FallbackResource /index.php` interagiert (interner Subrequest).

4. **JS-Dateien im Container:** Entscheidung: COPY im Containerfile (reproduzierbar) ODER Volume-Mount aus dem Repo (flexibler).

5. **OTel Collector HTTP-Receiver:** Voraussetzung für beide Ansätze: HTTP-Receiver auf Port 4318 mit CORS. Änderung an `otel/otel-collector-config.yaml` und `compose.yaml` nötig.

6. **Deaktivierbarkeit:** Soll Boomerang per Environment-Variable deaktivierbar sein?

7. **Admin-Panel-Abdeckung (bei Modul-Ansatz):** Falls später gewählt, muss die Admin-Layout-Einschränkung akzeptiert werden. Workaround ist nicht möglich, da `modules_v4/`-Module `ModuleCustomInterface` implementieren müssen.

### 4.2 Nicht-blockierend

8. **Server-Timing Header:** PHP OTel SDK emittiert standardmäßig KEINEN `Server-Timing`-Response-Header. Für Browser↔Server-Trace-Verknüpfung müsste dies nachgerüstet werden. Priorität: Niedrig.

9. **Content-Security-Policy:** Aktuell kein CSP-Header gesetzt. Falls später gesetzt, müssen `/rum/`-Script-Quellen erlaubt werden.

10. **Boomerang-Version-Pinning:** npm-Paket `boomerangjs@1.815.1` muss vor dem Container-Build heruntergeladen werden (npm-ci in Build-Stage oder wget aus npm-Registry-Tarball).

---

## Quelldateien (Referenzen)

| Datei | Relevanz |
|---|---|
| `upstream/webtrees/app/Module/ModuleGlobalInterface.php` | Das Injection-Interface |
| `upstream/webtrees/resources/views/layouts/default.phtml` (Zeile 69, 185) | Hauptlayout — rendert Module-Content |
| `upstream/webtrees/resources/views/layouts/administration.phtml` (Zeile 40, 89) | Admin-Layout mit ModuleCustomInterface-Filter |
| `upstream/webtrees/app/Services/ModuleService.php` (Zeile 682–714) | Modul-Discovery erfordert ModuleCustomInterface |
| `upstream/webtrees/app/Module/GoogleAnalyticsModule.php` | Referenzimplementierung für Script-Injection mit Kontext-Zugriff |
| `Containerfile.webtrees` | Basiert auf `php:8.5-apache`, aktuell nur `rewrite`-Modul aktiviert |
