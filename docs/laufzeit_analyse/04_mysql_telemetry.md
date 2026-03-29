<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# A4: MySQL 9.x — Telemetry Trace Plugin — Analyse

## 1. Fakten

### 2.3.1 Versionsauswahl

#### Verfügbare Docker-Images

Ermittelt via `skopeo list-tags docker://docker.io/library/mysql`:

| Tag | MySQL-Version | Release-Typ |
|---|---|---|
| `mysql:8.0` | 8.0.x | Innovation (End-of-Life April 2026) |
| `mysql:8.4` | 8.4.8 | **LTS** (Support bis 2032) |
| `mysql:9.0` | 9.0.1 | Innovation |
| `mysql:9.1` | 9.1.0 | Innovation |
| `mysql:9.2` | 9.2.0 | Innovation |
| `mysql:9.3` | 9.3.0 | Innovation |
| `mysql:9.4` | 9.4.0 | Innovation |
| `mysql:9.5` | 9.5.0 | Innovation |
| `mysql:9.6` | 9.6.0 | Innovation |
| `mysql:latest` | 9.6.0 | Innovation (`MYSQL_MAJOR=innovation`) |
| `mysql:lts` | 8.4.8 | LTS |
| `mysql:innovation` | 9.6.0 | Innovation |

Alle 9.x-Versionen (9.0 bis 9.6) sind als offizielle Docker-Images verfügbar. Die nächste geplante LTS-Version ist **MySQL 9.7**.

#### Innovation vs. LTS Release-Modell

| Aspekt | LTS (8.4) | Innovation (9.x) |
|---|---|---|
| Support-Dauer | 5 Jahre Premier + 3 Jahre Extended = 8 Jahre | Nur bis zum nächsten Innovation-Release |
| Feature-Änderungen | Nur Bug-Fixes, keine Verhaltensänderungen | Neue Features, Verhaltensänderungen, Deprecation-Removals |
| Upgrade-Pfad | In-Place-Upgrade innerhalb der Serie | Downgrade erfordert logischen Dump/Load |
| Empfehlung (Oracle) | Produktion, stabile Umgebungen | Entwicklung/Testing, schnelle Zyklen |

**Fazit:** MySQL 9.x ist explizit als Innovation-Release positioniert. Für eine Testplattform ist das akzeptabel, aber für Produktion wird MySQL 8.4 LTS empfohlen.

#### webtrees-Kompatibilität mit MySQL 9.x

**Analyse der webtrees-Quellen (`upstream/webtrees/`):**

1. **composer.json:** Kein `ext-pdo_mysql` in `require`, sondern nur in `suggest`. Keine MySQL-Versionsanforderung spezifiziert. Die Datenbank-Abstraktion erfolgt über `illuminate/database` 12.50.0 (Laravel 12.x), das MySQL 5.7+ unterstützt (keine Obergrenze dokumentiert).

2. **DB.php (app/DB.php):** Die Initialisierung setzt:
   ```sql
   SET NAMES utf8mb4, sql_mode := 'ANSI,STRICT_ALL_TABLES',
       TIME_ZONE := '+00:00', SQL_BIG_SELECTS := 1,
       GROUP_CONCAT_MAX_LEN := 1048576
   ```
   Diese Einstellungen sind MySQL-8.x- und 9.x-kompatibel. `ANSI` und `STRICT_ALL_TABLES` sind in 9.x weiterhin gültige sql_mode-Werte.

3. **ServerCheckService.php:** Keine MySQL-Versionsprüfung. Nur PHP-Version, PHP-Extensions und SQLite-Version werden geprüft.

4. **SetupWizard.php:** Verwendet `CREATE DATABASE IF NOT EXISTS ... COLLATE utf8mb4_unicode_ci` — kompatibel mit 9.x.

5. **Schema-Migrationen:** Verwenden illuminate/database Blueprint-API (kein rohes DDL). Keine MySQL-versionsspezifischen Konstrukte außer in Migration44, die explizit auf `DB::MYSQL` prüft und standard-kompatibles SQL verwendet.

6. **Keine `INSERT IGNORE`-Nutzung** in der webtrees-Codebasis (relevant, da MySQL 9.0 `ER_SUBQUERY_NO_1_ROW` bei `IGNORE` nicht mehr unterdrückt).

#### Kritische Änderung: `mysql_native_password` entfernt in MySQL 9.0

**Dies ist die wichtigste Breaking Change:**

- MySQL 9.0 hat das `mysql_native_password`-Authentication-Plugin **komplett entfernt** (war in 8.0 deprecated).
- Standard-Authentifizierung ist `caching_sha2_password` (bereits Default seit MySQL 8.0).
- **Auswirkung auf die Testplattform:** Der aktuelle `compose.yaml` erstellt User über die `MYSQL_USER`/`MYSQL_PASSWORD` Environment-Variablen des Docker-Images. Das offizielle MySQL-Docker-Image erstellt User seit MySQL 8.0 bereits mit `caching_sha2_password`. **Kein Problem.**
- **PHP PDO:** PHP 8.x `pdo_mysql` mit `mysqlnd` unterstützt `caching_sha2_password`. Der Containerfile verwendet PHP 8.5 — kompatibel.
- **setup-webtrees.sh:** Verbindet sich via `new PDO('mysql:host=...')` — Standard-PDO, kein Plugin-Override. Kompatibel.

**Showstopper-Check: Kein harter Blocker für MySQL 9.x mit webtrees.**

#### Upgrade-Pfad Docker-Volume

| Von | Nach | Direkt möglich? |
|---|---|---|
| MySQL 8.0 | MySQL 8.4 | Ja (In-Place-Upgrade) |
| MySQL 8.0 | MySQL 9.x | **Nein** — erfordert Zwischenschritt über 8.4 |
| MySQL 8.4 | MySQL 9.x | Ja (In-Place-Upgrade) |

**Für die Testplattform:** Da `make clean` die Volumes löscht und `make setup` die DB neu erstellt, ist der Upgrade-Pfad irrelevant. Ein `make clean && make up && make setup` genügt.

---

### 2.3.2 Telemetry Trace Plugin

#### SHOWSTOPPER: Enterprise Edition Only

> **"OpenTelemetry support is a component included in MySQL Enterprise Edition, a commercial product."**
> — MySQL 9.6 Reference Manual, Chapter 35; MySQL 9.2 Reference Manual, Chapter 35; MySQL 8.4 Reference Manual, Chapter 35

**Das `component_telemetry`-Plugin ist NICHT in der Community Edition enthalten.** Die Docker-Images auf Docker Hub (`docker.io/library/mysql`) sind **Community Edition**. Das Plugin lässt sich dort nicht installieren.

Dies gilt für alle Versionen: MySQL 8.4, 9.0, 9.1, 9.2, ..., 9.6.

#### Plugin-Details (akademisch, da Enterprise-only)

| Eigenschaft | Wert |
|---|---|
| Komponenten-Name | `component_telemetry` |
| Installation | `INSTALL COMPONENT 'file://component_telemetry'` |
| Export-Protokoll | **OTLP HTTP/Protobuf** (nicht gRPC!) |
| Default Traces-Endpoint | `http://localhost:4318/v1/traces` |
| Default Metrics-Endpoint | `http://localhost:4318/v1/metrics` |
| Default Logs-Endpoint | `http://localhost:4318/v1/logs` |
| Kompression | `none` (optional `gzip`) |
| Systemvariablen | 50+ (`telemetry.trace_enabled`, `telemetry.otel_exporter_otlp_traces_endpoint`, etc.) |
| Span-Typen | Control, Session, Statement (`stmt`) |
| Statement-Attribute | `mysql.sql_text`, `mysql.rows_affected`, `mysql.lock_time`, `mysql.cpu_time`, etc. |

**Wichtig:** Das Plugin verwendet Port **4318** (OTLP HTTP), nicht 4317 (OTLP gRPC). Der aktuelle OTel Collector in der Testplattform lauscht nur auf 4317 (gRPC). Eine Anpassung wäre erforderlich.

#### Konfiguration (falls Enterprise)

```ini
# my.cnf oder Docker command
[mysqld]
# Plugin wird per SQL installiert, nicht per command-line
# Danach konfigurierbar via:
SET GLOBAL telemetry.trace_enabled = ON;
SET GLOBAL telemetry.otel_exporter_otlp_traces_endpoint = 'http://otel-collector:4318/v1/traces';
```

---

### 2.3.3 Trace Propagation MySQL ↔ PHP

#### Aktueller Stand: Kein automatisches Context Propagation

Die MySQL-Dokumentation beschreibt **keine Mechanismen für W3C Trace Context Propagation** (traceparent/tracestate) zwischen Client und Server:

1. **Connection Attributes:** MySQL unterstützt Client Connection Attributes (Key-Value-Paare beim Connect), aber es gibt **kein dokumentiertes `traceparent`-Attribut**. Clients könnten theoretisch ein Custom-Attribut setzen, aber das Enterprise-Plugin wertet dieses nicht aus.

2. **opentelemetry-auto-pdo (PHP):** Dieses Package (bereits in `setup-webtrees.sh` installiert) instrumentiert PDO-Aufrufe auf PHP-Seite. Es erstellt Spans für `PDO::query()`, `PDO::exec()`, `PDOStatement::execute()` etc. Es propagiert **keinen Trace-Context an MySQL**.

3. **MySQL Enterprise Telemetry:** Die Server-Spans (Session, Statement) werden **unabhängig** von Client-Spans erzeugt. Es gibt keine dokumentierte Korrelation zwischen PHP-Client-Spans und MySQL-Server-Spans.

4. **SQL-Kommentar-Propagation (z.B. `/*traceparent=...*/`):** Nicht von MySQL Enterprise Telemetry unterstützt. Manche Drittanbieter-Lösungen (z.B. ProxySQL, MySQL Router) bieten dies an, aber nicht das native Plugin.

**Fazit:** Auch mit Enterprise-Lizenz gäbe es keine End-to-End-Traces von PHP durch MySQL. Die Traces wären disjunkt: PHP-Spans (via opentelemetry-auto-pdo) und MySQL-Spans (via component_telemetry) ohne Korrelation.

---

### 2.3.4 compose.yaml-Änderungen (theoretisch)

Falls nur der MySQL-Upgrade (ohne Telemetry) umgesetzt wird:

```yaml
mysql:
  image: docker.io/library/mysql:lts    # = 8.4.x
  # Alternativ: docker.io/library/mysql:8.4
  # Rest bleibt identisch
  command: >
    --character-set-server=utf8mb4
    --collation-server=utf8mb4_bin
  # KEIN --early-plugin-load nötig (Community hat kein Telemetry-Plugin)
```

Volume-Kompatibilität: `mysql-data` muss bei Versionswechsel **gelöscht und neu erstellt** werden (`make clean`).

---

## 2. Bewertung

### Machbarkeit

| Vorhaben | Machbar? | Aufwand | Risiko |
|---|---|---|---|
| MySQL 8.0 → 8.4 LTS Upgrade | **Ja** | Gering (Image-Tag ändern + `make clean`) | Niedrig |
| MySQL 8.0 → 9.6 Innovation Upgrade | **Ja** | Gering (Image-Tag ändern + `make clean`) | Mittel (kurzlebiger Support) |
| Telemetry Trace Plugin aktivieren | **Nein** | Nicht möglich (Enterprise-only) | Showstopper |
| End-to-End Trace Propagation PHP→MySQL | **Nein** | Selbst mit Enterprise nicht möglich | Architektonische Grenze |

### Risiken

1. **Enterprise-Lock-in:** Der einzige Weg zum MySQL Telemetry Plugin führt über eine kommerzielle Lizenz. Für ein Open-Source-Testprojekt nicht vertretbar.

2. **Innovation-Release-Instabilität:** MySQL 9.x hat keinen Langzeit-Support. Jedes Quartals-Release kann Verhaltensänderungen bringen. Für eine Testplattform akzeptabel, aber die Tests könnten durch MySQL-Upgrades brechen.

3. **PHP PDO `caching_sha2_password`:** Funktioniert mit PHP 8.x/mysqlnd. Der `default-mysql-client` im Containerfile (Debian-Paket) könnte aber eine ältere libmysqlclient verwenden. Der `mysqladmin ping`-Healthcheck in `compose.yaml` könnte bei 9.x fehlschlagen. **Zu prüfen.**

---

## 3. Empfehlung

### MySQL-Version: **8.4 LTS statt 9.x**

**Begründung:**
- 8-Jahres-Support (bis ~2032) vs. Quartals-Lebensdauer bei Innovation
- Telemetry-Plugin ist in beiden Fällen nicht verfügbar (Enterprise-only)
- webtrees ist mit 8.4 vollständig kompatibel
- `mysql:lts` Docker-Tag garantiert automatische Patch-Updates innerhalb der 8.4-Serie
- Kein Risiko durch überraschende Verhaltensänderungen zwischen Innovation-Releases

### Telemetry: **Nicht über MySQL, sondern über PHP-Seite**

Der aktuelle Stack hat bereits die richtige Architektur:
- `opentelemetry-auto-pdo` instrumentiert PDO-Aufrufe PHP-seitig
- PHP-Spans enthalten Query-Texte und Ausführungsdauern
- Diese werden via gRPC (Port 4317) an den OTel Collector gesendet
- Der Collector exportiert an Jaeger und die Traces-Datei

**Das deckt den wesentlichen Observability-Bedarf ab**, da die PHP-Seite die einzige Stelle ist, an der Trace-Context verfügbar ist.

### Konkreter Umsetzungsplan

```yaml
# compose.yaml: MySQL-Image ändern
mysql:
  image: docker.io/library/mysql:lts    # = 8.4.x
  # Alternativ: docker.io/library/mysql:8.4
  # Rest identisch
```

Danach: `make clean && make up && make setup && make test-all`

### Optionale MySQL-Server-Observability (ohne Enterprise)

Für MySQL-seitige Metriken ohne Enterprise-Lizenz gibt es Alternativen:
- **mysqld_exporter** (Prometheus): Exportiert Performance-Schema-Metriken
- **Performance Schema** direkt: Abfragbar via SQL (`events_statements_summary_by_digest`)
- **Slow Query Log**: Konfigurierbar via `--slow-query-log` und `--long-query-time`

Diese könnten als separate Erweiterung in den Stack integriert werden (→ siehe A5: Performance Schema).

---

## 4. Offene Punkte — Entscheidungsstatus

### 4.1 Entschieden

1. **`mysql-security`-Service:** → **Parallel aktualisieren** auf `mysql:lts` (A9, Abschnitt 1.6). Aenderung in `compose.yaml` gemeinsam mit dem Haupt-MySQL-Service.

2. **InnoDB-Default-Aenderungen in 8.4:** → **Akzeptiert.** Fuer funktionale Tests irrelevant. Performance-Tests (Layer 5) muessen mit 8.4-Baselines arbeiten — alte 8.0-Baselines sind nach Upgrade nicht mehr gueltig.

### 4.2 Bei Implementierung zu verifizieren

3. **Healthcheck-Kompatibilitaet:** `mysqladmin ping` mit Root-Passwort und `caching_sha2_password` in MySQL 8.4 pruefen. Alternative: `mysqladmin ping --protocol=tcp -h localhost`.

4. **Security-Service Healthcheck:** Zeile 172 verwendet `mysqladmin ping -h localhost` ohne Passwort. Bei 8.4+ verifizieren.

5. **Upstream-webtrees-Kompatibilitaet:** Upstream-CI auf getestete MySQL-Versionen pruefen.

### 4.3 Nicht weiter zu verfolgen

- **MySQL Telemetry Trace Plugin:** Enterprise-only, keine Community-Alternative.
- **MySQL 9.x fuer die Testplattform:** Kein Vorteil gegenueber 8.4 LTS.
- **End-to-End Trace Propagation PHP→MySQL:** Architektonisch nicht unterstuetzt.

---

## Quellen

- MySQL 9.6 Reference Manual, Chapter 35 — Telemetry: https://dev.mysql.com/doc/refman/9.6/en/telemetry.html
- MySQL 9.2 Reference Manual, Telemetry Trace Install: https://dev.mysql.com/doc/refman/9.2/en/telemetry-trace-install.html
- MySQL 8.4 Reference Manual, Telemetry: https://dev.mysql.com/doc/refman/8.4/en/telemetry.html
- MySQL 8.4 Reference Manual, Upgrade Paths: https://dev.mysql.com/doc/refman/8.4/en/upgrade-paths.html
- MySQL Release Model (Innovation vs LTS): https://dev.mysql.com/doc/refman/9.2/en/mysql-releases.html
- MySQL 9.0.0 Release Notes (mysql_native_password removal): https://dev.mysql.com/doc/relnotes/mysql/9.0/en/news-9-0-0.html
- Docker Hub mysql tags: via `skopeo list-tags docker://docker.io/library/mysql`
- webtrees Source: `upstream/webtrees/app/DB.php`, `app/Services/ServerCheckService.php`, `app/Http/RequestHandlers/SetupWizard.php`
- Laravel 12.x Database Documentation: https://laravel.com/docs/12.x/database
