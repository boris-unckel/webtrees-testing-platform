<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# A3: Apache httpd — OpenTelemetry-Modul — Analyse

## 1. Fakten

### 1.1 Zwei separate Projekte in opentelemetry-cpp-contrib

Das Repository `open-telemetry/opentelemetry-cpp-contrib` enthält **zwei separate Apache-httpd-Instrumentierungs-Module** mit grundlegend verschiedener Architektur, Wartungsstatus und Verfügbarkeit:

#### A) `instrumentation/httpd` (mod_otel) — das "leichtgewichtige" Modul

- **Letzter Code-Commit:** 2022-01-11 (über 4 Jahre alt, effektiv aufgegeben)
- **Release:** `httpd/v0.1.0` (2021-05-13), markiert als **prerelease**
- **Pre-built Binaries:** 3 `.so`-Dateien für Ubuntu 16.04, 18.04 und 20.04
- **Kein Debian-Bookworm-Binary**, kein Update seit 2021
- **Architektur:** Reines C++ Apache-Modul mit opentelemetry-cpp SDK
- **Build-System:** Bazel 3.7.x
- **Apache-Version:** Nur mit Apache 2.4.x auf Ubuntu 18.04/20.04 getestet
- **Propagation:** W3C trace-context, B3 single, B3 multi-header
- **Exporter:** OTLP (gRPC) oder Datei
- **Besonderheit:** Setzt Umgebungsvariablen `OTEL_SPANID`, `OTEL_TRACEID`, `OTEL_TRACEFLAGS`, `OTEL_TRACESTATE`, zugänglich via `%{OTEL_SPANID}e` in LogFormat
- **Context-Propagation zu PHP:** Eingehende `traceparent`/`tracestate`-Header bleiben in `headers_in` (Apaches Request-Header-Tabelle) — PHP/mod_php sieht sie als `$_SERVER['HTTP_TRACEPARENT']`. Das Modul liest und extrahiert Context aus diesen Headern (wenn `OpenTelemetryIgnoreInbound off` gesetzt ist), injiziert aber **keine** neuen Header in den internen Request. Es füllt nur `subprocess_env`-Variablen.
- **Baggage-Support:** Nicht explizit implementiert. Der W3C `baggage`-Header wird im Source-Code nicht erwähnt.

#### B) `instrumentation/otel-webserver-module` — das "Enterprise"-Modul (Cisco-maintained)

- **Letzter Code-Commit:** 2025-04-24 (aktiv gewartet)
- **Letzter Release:** `webserver/v1.1.0` (2024-05-02)
- **Pre-built Binary:** Einzelnes Artefakt `opentelemetry-webserver-sdk-x64-linux.tgz` (~15 MB)
- **Architektur:** Komplexes SDK mit mehreren Shared Libraries, log4cxx-Dependency, Gradle-Build-System
- **Build-Plattformen:** CentOS 6, CentOS 7, AlmaLinux 8, Ubuntu 20.04 (alle x86-64)
- **Apache-Version:** Gebaut gegen Apache 2.2.31 und 2.4.23 Header-Dateien; erzeugt `libmod_apache_otel22.so` (2.2) und `libmod_apache_otel.so` (2.4)
- **glibc-Anforderung:** >= 2.17 (gebaut auf CentOS 6)
- **Propagation:** W3C trace-context (`traceparent`, `tracestate`, `baggage`-Header explizit in `httpHeaders`-Initialisierungsliste in `ApacheHooks.cpp` Zeile 42–45)
- **Context zu PHP:** Die `otel_payload_decorator`-Funktion (Zeile ~260 in ApacheHooks.cpp) schreibt Propagation-Header zurück in `request->headers_in` via `apr_table_set`. PHP über mod_php oder php-fpm würde den aktualisierten `traceparent` als `$_SERVER['HTTP_TRACEPARENT']` sehen.
- **Bekannte Issues:**
  - Issue #339: "Max hooks for stage"-Fehler bei vielen geladenen Apache-Modulen
  - Issue #463: CentOS 7 EOL, AlmaLinux 9 Support angefragt aber nicht implementiert
  - Issue #343/#400: ARM64 Support nicht gemergt (PR offen seit 2024-03)
  - Issue #302: Alpine (musl libc) nicht unterstützt
  - Issue #589: Versions-Datei-Mismatch in v1.1.0 Release
- **Laufzeit-Abhängigkeiten:** 7+ Shared Libraries in `sdk_lib/lib/`

### 1.2 Plattformkompatibilität mit `php:8.5-apache`

Das `php:8.5-apache` Image (wie in `Containerfile.webtrees` verwendet) basiert auf **Debian Bookworm**:
- **glibc:** 2.36
- **Apache:** 2.4.x (Debian-Paket `apache2` 2.4.62 oder ähnlich)
- **Architektur:** x86-64

**Kompatibilitätsmatrix:**

| Faktor | mod_otel (httpd) | otel-webserver-module |
|--------|------------------|-----------------------|
| glibc-Compat | Ubuntu 20.04 Binary → glibc 2.31 (Bookworm hat 2.36, OK) | CentOS 6 → glibc 2.17 (Bookworm hat 2.36, OK) |
| Apache ABI | Gebaut gegen Ubuntu 20.04 Apache (2.4.41) | Gebaut gegen Apache 2.4.23 |
| Apache ABI-Risiko | **HOCH** — Apache-Modul-ABI ist versionsspezifisch; .so für 2.4.41 kann auf 2.4.62 segfaulten | **MITTEL** — ABI zwischen 2.4.23 und 2.4.62 hat potentielle Inkompatibilitäten |
| Architektur | x86-64 only | x86-64 only |
| Laufzeit-Dependencies | Nur libstdc++ | 7 Shared Libraries + log4cxx + APR/APR-util |
| Binary-Alter | 5 Jahre (Mai 2021) | 2 Jahre (Mai 2024) |

### 1.3 Keine apt/rpm-Pakete

Kein Modul bietet OS-Level-Pakete (`.deb`, `.rpm`). Keine Third-Party-PPA oder Repository-Quellen. Einziger Distributionskanal ist GitHub Releases.

### 1.4 Keine offiziellen Docker-Images

Kein Modul publiziert ein Ready-to-Use Docker-Image. Die `docker-compose.yml` im Webserver-Modul baut aus Source innerhalb des Dockerfile.

### 1.5 Nginx-Alternative (Hinweis)

Das `opentelemetry-cpp-contrib`-Projekt bietet pre-built **Nginx**-Module (`nginx/v0.1.1`, Januar 2025) mit Binaries für Debian 11, Ubuntu 24.04, Alpine, Amazon Linux. Diese sind aktiv gewartet. Allerdings nutzt die webtrees-Testing-Plattform Apache, nicht Nginx.

---

## 2. Bewertung

### 2.1 Binary-Verfügbarkeit — Ergebnis: KEIN nutzbares Pre-Built Binary vorhanden

**mod_otel (`instrumentation/httpd`):**
- Einzige Pre-built Binaries sind von 2021, für Ubuntu 16.04/18.04/20.04
- **5 Jahre alt**, gebaut gegen alte Apache-Header, altes OpenTelemetry SDK, alte glibc
- Projekt hat seit Januar 2022 keine Code-Änderungen mehr
- Release `httpd/v0.1.0` ist als **prerelease** markiert — war nie GA
- Ein 5 Jahre altes Prerelease-Binary von Ubuntu 20.04 auf Debian Bookworm mit neuerem Apache einzusetzen ist ein **ernstes ABI-Kompatibilitätsrisiko**

**otel-webserver-module:**
- Pre-built `opentelemetry-webserver-sdk-x64-linux.tgz` (v1.1.0) ist aktueller (Mai 2024)
- Behauptet Kompatibilität mit "any linux distribution on x86-64 with glibc >= 2.17"
- Apache-Modul-.so wurde gegen Apache 2.4.23 Header kompiliert
- Apache-Modul-ABI-Kompatibilität zwischen 2.4.23 und 2.4.62 ist **nicht garantiert**
- **Schwere Laufzeit-Abhängigkeiten** (7+ Shared Libraries, log4cxx, APR/APR-util)
- Installation erfordert `install.sh` mit Permissions und Log4cxx-Konfiguration

**Kompilieren aus Source ist ausgeschlossen** per Anforderung.

### 2.2 Apache-Integration — Komplexität

Selbst wenn ein Binary verfügbar wäre, würde die Integration signifikante Komplexität erzeugen:

```apache
# Benötigte Apache-Config (otel-webserver-module)
LoadFile /opt/opentelemetry-webserver-sdk/sdk_lib/lib/libopentelemetry_common.so
LoadFile /opt/opentelemetry-webserver-sdk/sdk_lib/lib/libopentelemetry_resources.so
LoadFile /opt/opentelemetry-webserver-sdk/sdk_lib/lib/libopentelemetry_trace.so
LoadFile /opt/opentelemetry-webserver-sdk/sdk_lib/lib/libopentelemetry_otlp_recordable.so
LoadFile /opt/opentelemetry-webserver-sdk/sdk_lib/lib/libopentelemetry_exporter_ostream_span.so
LoadFile /opt/opentelemetry-webserver-sdk/sdk_lib/lib/libopentelemetry_exporter_otlp_grpc.so
LoadFile /opt/opentelemetry-webserver-sdk/sdk_lib/lib/libopentelemetry_webserver_sdk.so
LoadModule otel_apache_module /opt/opentelemetry-webserver-sdk/WebServerModule/Apache/libmod_apache_otel.so

ApacheModuleEnabled ON
ApacheModuleOtelSpanExporter otlp
ApacheModuleOtelExporterEndpoint otel-collector:4317
ApacheModuleServiceName webtrees-apache
ApacheModuleServiceNamespace webtrees-testing
ApacheModuleResolveBackends ON
```

### 2.3 Trace-Context-Propagation

**W3C Trace Context:** Beide Module unterstützen `traceparent` + `tracestate`.

**W3C Baggage:**
- mod_otel: **NICHT unterstützt** — kein Baggage-Propagator im Source-Code
- otel-webserver-module: `httpHeaders`-Liste enthält explizit `"baggage"` neben `"traceparent"` und `"tracestate"`. Ob das SDK Baggage downstream propagiert (zurück in headers_in schreibt), ist **unklar**.

**Wichtig für custom Baggage (`test.run_id`, `test.case_id`):**
- Auch ohne Apache OTel-Modul wird Baggage-Propagation **nicht blockiert** — der `baggage` HTTP-Header ist ein Standard-Header, den Apache transparent an PHP weiterleitet.
- PHP OTel Auto-Instrumentation liest `baggage` aus eingehenden Headern nativ.
- **Baggage-Propagation funktioniert ohne das Apache-Modul.**

### 2.4 Fallback ohne Apache-Modul

**Frage:** Kann Apache-zu-PHP Trace-Korrelation ohne Apache OTel-Modul funktionieren?

**Antwort: Ja, mit Einschränkungen.**

**Was ohne das Modul funktioniert:**
1. Browser (Boomerang/Fetch) sendet `traceparent`-Header in Requests
2. Apache leitet `traceparent`-Header unverändert an PHP weiter (Standard-Verhalten)
3. PHP OTel Auto-Instrumentation liest `traceparent` aus `$_SERVER['HTTP_TRACEPARENT']`
4. PHP erstellt einen Server-Span mit dem Browser-Span als Parent
5. End-to-End-Korrelation: Browser-Span → PHP-Span (Apache als transparenter Proxy)
6. `baggage`-Header fließt ebenfalls transparent durch

**Was ohne das Modul verloren geht:**
- **Kein Apache-Server-Span:** Keine Sichtbarkeit für:
  - Apache Request-Parsing, Header-Verarbeitung
  - mod_rewrite-Verarbeitungszeit
  - Apache Output-Filter-Verarbeitung
  - Apache-interne Queue-/Connection-Handling-Zeit
- **Keine Modul-Level-Granularität:** Das Webserver-Modul kann Sub-Spans für einzelne Apache-Module erzeugen (mod_proxy, mod_php, mod_rewrite etc.)
- **Keine TLS-Handshake-Sichtbarkeit** (in Container-Test-Umgebung mit HTTP irrelevant)

**Was NICHT verloren geht:**
- Browser-zu-PHP-Korrelation (traceparent fließt durch)
- PHP Span-Hierarchie (Controller → DB → Template)
- PHP-Level-Timing (die dominierende Komponente für webtrees)
- Baggage-Propagation (`test.run_id`, `test.case_id`)
- Collector empfängt alle PHP-Spans korrekt

---

## 3. Empfehlung

### Primärempfehlung: Apache OTel-Modul NICHT verwenden. Fallback-Ansatz nutzen.

**Begründung:**

1. **Kein kompatibles Pre-built Binary existiert** für `php:8.5-apache` (Debian Bookworm, Apache 2.4.62). Die httpd-Modul-Binaries sind 5 Jahre alte Prereleases für Ubuntu 16.04–20.04. Das Webserver-Modul-Binary ist theoretisch glibc-kompatibel, birgt aber Apache-ABI-Risiko (2.4.23 vs. 2.4.62).

2. **Kompilieren aus Source ist ausgeschlossen** per Anforderung.

3. **Der Mehrwert ist gering für eine Testing-Plattform.** Die Testing-Plattform braucht Trace-Korrelation zwischen Browser, PHP-Anwendungscode und Datenbank — nicht Apache-Interna. Apache fügt vernachlässigbare Verarbeitungszeit für webtrees-Requests hinzu verglichen mit PHP/MySQL.

4. **Der Fallback ist ausreichend und einfach.** Der Browser sendet `traceparent` via Fetch-Header, Apache leitet transparent weiter, PHP Auto-Instrumentation übernimmt als Parent-Context. Keine zusätzliche Infrastruktur nötig.

5. **Risiko vs. Nutzen:** Ein potentiell ABI-inkompatibles natives Modul in den Apache-Prozess zu laden riskiert Segfaults und Container-Instabilität — inakzeptabel für eine Testing-Plattform, die zuverlässig sein muss.

### Konkreter Implementierungsansatz (Fallback)

```
Browser (Boomerang/Playwright)
  |— setzt traceparent + baggage Header in Fetch/XHR Requests
  |
  v
Apache httpd (transparent, kein OTel-Modul)
  |— leitet Header unverändert weiter
  |
  v
PHP (OTel Auto-Instrumentation, bereits konfiguriert)
  |— liest traceparent aus $_SERVER['HTTP_TRACEPARENT']
  |— liest baggage aus $_SERVER['HTTP_BAGGAGE']
  |— erstellt Server-Span mit Browser-Span als Parent
  |— extrahiert test.run_id, test.case_id aus Baggage
  |
  v
OTel Collector (otel-collector:4317, läuft bereits)
  |— empfängt PHP-Spans
  |
  v
Jaeger (läuft bereits auf Port 16686)
```

Keine Änderungen an `compose.yaml`, `Containerfile.webtrees` oder OTel-Collector-Config nötig. Die Arbeit liegt rein auf der Browser-Instrumentierungs-Seite (Senden von `traceparent` + `baggage` in Requests) und optional auf der PHP-Seite (Extraktion von Baggage-Attributen in Span-Attribute).

---

## 4. Offene Punkte

### 4.1 Vor Implementierung zu klären

1. **PHP OTel Auto-Instrumentation Verhalten:** Verifizieren, dass die `opentelemetry` PECL-Extension (installiert in `Containerfile.webtrees`) tatsächlich `traceparent` aus eingehenden HTTP-Headern liest und als Parent-Context für den Root-Server-Span verwendet. Dies ist erwartetes Verhalten, sollte aber empirisch mit der aktuellen Version bestätigt werden.

2. **Baggage-Extraktion in PHP:** Das PHP OTel SDK sollte automatisch Baggage-Context aus dem `baggage` HTTP-Header propagieren. Verifizieren, dass `test.run_id` und `test.case_id` aus dem Baggage-Header in PHP-Code zugänglich sind (z.B. via `Baggage::getCurrent()->getEntry('test.run_id')`) und als Span-Attribute gesetzt werden können.

3. **Browser-seitige traceparent-Erzeugung:** Boomerang kann `traceparent`-Header für Navigation-Requests injizieren, aber für Playwright-Tests mit Fetch/XHR muss der Test-Code explizit den `traceparent`-Header setzen. Browser-Instrumentierungs-Strategie klären (→ A6: Baggage-Propagation).

### 4.2 Zukunftsüberlegungen

4. **Falls Apache-Level-Spans später benötigt werden:** Das OpenTelemetry Nginx-Modul (`nginx/v0.1.1`) bietet proper Pre-built Binaries für multiple Plattformen inklusive Debian. Ein Wechsel von `php:8.5-apache` zu `php:8.5-fpm` hinter einem Nginx Reverse-Proxy würde saubere Webserver-Instrumentierung ermöglichen. Dies ist eine größere architektonische Änderung, hat aber deutlich bessere Tooling-Unterstützung.

5. **ARM64-Support:** Keines der Apache OTel-Module unterstützt ARM64. Falls die Testing-Plattform auf Apple Silicon (via Podman) laufen soll, wäre das ein Blocker.

---

*Analyse basiert auf GitHub-API-Daten vom 2026-03-29 aus dem `open-telemetry/opentelemetry-cpp-contrib` Repository. Source-Code-Review von `ApacheHooks.cpp`, `mod_otel.cpp`, `opentelemetry.cpp`, `opentelemetry.h` und zugehörigen Konfigurationsdateien.*
