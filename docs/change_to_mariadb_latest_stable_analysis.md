<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Analyse: Migration von MySQL 8.4 LTS auf MariaDB Latest Stable (11.8 LTS)

**Datum:** 2026-04-13
**Status:** Analyse abgeschlossen — Empfehlung: bei MySQL bleiben (siehe Abschnitt 8)

---

## 1  Ist-Zustand

Der Stack verwendet `docker.io/library/mysql:lts`, was aktuell auf **MySQL 8.4.x LTS**
auflöst (Support bis April 2032).

### 1.1  Image-Tag-Verdrahtung

| Datei | Zeile(n) | Wert | Kontext |
|---|---|---|---|
| `compose.yaml` | 76 | `docker.io/library/mysql:lts` | Test-DB (`mysql`) |
| `compose.yaml` | 181 | `docker.io/library/mysql:lts` | Security-Track-DB (`mysql-security`) |

### 1.2  MySQL-Server-Konfiguration (compose.yaml)

```yaml
command: >
  --character-set-server=utf8mb4
  --collation-server=utf8mb4_bin
  --performance-schema-instrument='stage/%=ON'
  --performance-schema-consumer-events-stages-current=ON
  --performance-schema-consumer-events-stages-history=ON
```

### 1.3  Performance-Schema-Nutzung im Stack

Vier Skripte/Dateien greifen direkt auf `performance_schema` zu:

| Datei | Genutzte P_S-Tabellen | Kritische Spalten |
|---|---|---|
| `scripts/extract-perfschema.sh` | `events_statements_summary_by_digest`, `table_io_waits_summary_by_table`, `events_stages_summary_global_by_event_name`, `events_transactions_summary_global_by_event_name` | **`QUANTILE_95`**, **`QUANTILE_99`**, **`QUERY_SAMPLE_TEXT`**, `JSON_ARRAYAGG`, `ROW_NUMBER() OVER(...)` |
| `scripts/truncate-perfschema.sh` | Alle vier Summary-Tabellen | Nur `TRUNCATE TABLE` |
| `layer4-e2e/helpers/perfschema-fixture.ts` | `events_statements_summary_by_digest`, `table_io_waits_summary_by_table` | `JSON_ARRAYAGG`, `JSON_OBJECT` |
| `scripts/trace-report.py` | Indirekt (liest JSON-Artefakte) | Keine direkte SQL-Abhängigkeit |

### 1.4  OTel-Integration (Ist-Zustand)

Die OTel-Instrumentierung ist vollständig **client-seitig** implementiert:

| Komponente | Mechanismus | Server-Abhängigkeit |
|---|---|---|
| PHP-Auto-Instrumentation | `open-telemetry/sdk` + `auto-pdo` | Keine — PDO-Spans werden vom PHP-Client erzeugt |
| Playwright-Tracing | `@opentelemetry/sdk-trace-node` | Keine — HTTP-Spans vom Node-Client |
| Browser-RUM | Boomerang + OTel-Plugin | Keine — Browser-Spans via `mod_substitute` injiziert |
| PerfSchema-Extraktion | SQL-Queries gegen P_S-Tabellen | Liest nur P_S-Tabellen — kein OTel-Export vom DB-Server |

**Kein Feature im Stack nutzt MySQL-Server-seitige OTel-Telemetrie** (die ohnehin nur
in MySQL Enterprise verfügbar wäre).

---

## 2  Versionslage MariaDB (Stand April 2026)

| Release | Tag (Docker Hub) | Support |
|---|---|---|
| **11.8.x LTS** | `mariadb:lts`, `mariadb:11.8` | 3 Jahre (bis ~2029) |
| 11.4.x LTS | `mariadb:11.4` | 5 Jahre (bis ~2029) |
| 12.1 Rolling | `mariadb:latest` | Laufend (kein fester EOL) |
| 10.11.x LTS | `mariadb:10.11` | Bis Februar 2028 |
| 12.3 (geplant) | — | Wird nächstes LTS (~Q2 2026) |

**Aktuelle LTS-Empfehlung:** MariaDB 11.8 (`mariadb:lts`).

---

## 3  Performance Schema: MariaDB vs. MySQL

### 3.1  Tabellenumfang

| Aspekt | MySQL 8.4 | MariaDB 11.8 |
|---|---|---|
| Anzahl P_S-Tabellen | ~126 | ~86 |
| P_S standardmäßig aktiv | **Ja** | **Nein** — muss beim Start mit `--performance-schema` aktiviert werden |
| Laufzeit-Aktivierung möglich | Nein (beidseitig gleich) | Nein |

MariaDB hat einen Rückstand von ~40 P_S-Tabellen gegenüber MySQL 8.x. Die
MariaDB-Implementierung entspricht weitgehend dem MySQL-5.6/5.7-Stand mit
selektiven Erweiterungen.

### 3.2  Vom Stack genutzte Tabellen — Kompatibilität

| P_S-Tabelle | MySQL 8.4 | MariaDB 11.8 | Status |
|---|---|---|---|
| `events_statements_summary_by_digest` | ✅ | ✅ Vorhanden | ⚠️ **Spaltenunterschiede** (s. 3.3) |
| `table_io_waits_summary_by_table` | ✅ | ✅ Vorhanden | ✅ Kompatibel |
| `events_stages_summary_global_by_event_name` | ✅ | ✅ Vorhanden | ✅ Kompatibel |
| `events_transactions_summary_global_by_event_name` | ✅ | ✅ Vorhanden | ✅ Kompatibel |

### 3.3  Kritische Spaltenunterschiede in `events_statements_summary_by_digest`

| Spalte | MySQL 8.4 | MariaDB 11.8 | Genutzt in |
|---|---|---|---|
| **`QUANTILE_95`** | ✅ Vorhanden | ❌ **Nicht vorhanden** | `extract-perfschema.sh` Z. 31 |
| **`QUANTILE_99`** | ✅ Vorhanden | ❌ **Nicht vorhanden** | `extract-perfschema.sh` Z. 32 |
| **`QUERY_SAMPLE_TEXT`** | ✅ Vorhanden | ❌ **Nicht vorhanden** | `extract-perfschema.sh` Z. 39 |
| `FIRST_SEEN` | ✅ | ✅ | `extract-perfschema.sh` Z. 40 |
| `LAST_SEEN` | ✅ | ✅ | `extract-perfschema.sh` Z. 41 |
| `SUM_ROWS_EXAMINED` | ✅ | ✅ | Beide Skripte |
| `SUM_SELECT_SCAN` | ✅ | ✅ | Beide Skripte |
| `SUM_NO_INDEX_USED` | ✅ | ✅ | Beide Skripte |
| `SUM_CREATED_TMP_DISK_TABLES` | ✅ | ✅ | `extract-perfschema.sh` Z. 37 |

**Fazit:** Drei Spalten, die in `extract-perfschema.sh` genutzt werden, existieren in
MariaDB nicht. Die Perzentil-Werte (`QUANTILE_95`/`QUANTILE_99`) und der
Sample-Query-Text (`QUERY_SAMPLE_TEXT`) sind MySQL-exklusive Erweiterungen.

### 3.4  Fehlende P_S-Tabellen in MariaDB (nicht direkt im Stack genutzt, aber relevant)

| Kategorie | Fehlende Tabellen | Relevanz für Testplattform |
|---|---|---|
| **Data Locks** | `data_locks`, `data_lock_waits` | ⬆ Hoch — Lock-Diagnose bei Parallelitätsproblemen in Layer-3-Tests |
| **Error Log** | `error_log` | ⬆ Hoch — die MySQL-9.x-Analyse empfiehlt diese Tabelle als neuen Prüfschritt |
| **Statement-Histogramme** | `events_statements_histogram_by_digest`, `*_global` | ⬆ Mittel — Latenzverteilungsanalyse |
| **Error Summary** | 5 `events_errors_summary_*`-Tabellen | Mittel — Fehlerklassifikation |

### 3.5  MariaDB-exklusive P_S- und Observability-Features

MariaDB bietet einige Features, die MySQL Community **nicht** hat:

| Feature | Beschreibung | Relevanz |
|---|---|---|
| **Extended User Statistics** | `TABLE_STATISTICS`, `INDEX_STATISTICS`, `CLIENT_STATISTICS`, `USER_STATISTICS` in `information_schema` | ⬆ Mittel — zusätzliche Per-Table/Index-Metriken; ergänzend zu P_S `table_io_waits` |
| **Query Response Time Plugin** | Histogramm-artige Latenzverteilung (Percona-kompatibel, erweitert in 11.8) | ⬆ Mittel — könnte als Ersatz für fehlende `QUANTILE_95`/`QUANTILE_99` dienen |
| **Erweiterte Slow-Query-Log-Optionen** | `log_slow_verbosity` (Query Plan, InnoDB-Details), `log_slow_rate_limit` (Sampling), `log_slow_disabled_statements` | ⬆ Mittel — ergänzende Diagnosedaten |
| **`FLUSH GLOBAL STATUS`** (11.8+) | Status-Counter-Reset ohne Neustart | Gering — nützlich für isolierte Messungen |
| **InnoDB Async I/O Statistics** (11.8+) | Erweiterte I/O-Metriken | Gering — nur für Performance-Tuning |

---

## 4  OpenTelemetry: MariaDB vs. MySQL

### 4.1  MySQL Enterprise Telemetry (Referenz)

MySQL bietet seit Version 8.1.0 in der **Enterprise Edition** (kommerziell):

| Feature | Seit | Beschreibung |
|---|---|---|
| `component_telemetry` | 8.1.0 | Server-Komponente für OTel-Traces, -Metriken, -Logs |
| Trace-Generierung | 8.1.0 | Spans für `COM_QUERY` und andere MySQL-Kommandos |
| Metriken-Export | 8.1.0 | 300+ MySQL-Gauges und -Counter im OTLP-Format |
| Query Attributes (`traceparent`) | 8.3.0 | Trace-Context-Propagation vom Client zum Server |
| Telemetry Logging API | 9.6 | API für eigene Telemetrie-Komponenten (auch Community) |

**Wichtig:** `component_telemetry` ist **ausschließlich MySQL Enterprise Edition**.
MySQL Community hat nur die Telemetry Logging API (seit 9.6), nicht den eigentlichen
Telemetrie-Export.

### 4.2  MariaDB: Kein natives OpenTelemetry

MariaDB Community/Open Source bietet **keinerlei native OpenTelemetry-Integration**:

- ❌ Kein `component_telemetry` oder Äquivalent
- ❌ Keine `OTEL_RESOURCE` / `OTEL_SERVICE`-Instrumentation server-seitig
- ❌ Keine Query Attributes für Trace-Context-Propagation
- ❌ Kein `traceparent`-Attribut in der Client/Server-Kommunikation
- ❌ Keine nativen Metriken-/Traces-/Logs-Exporter im OTLP-Format
- ❌ Keine Telemetry Logging API

### 4.3  Verfügbare Alternativen für MariaDB + OTel

| Ansatz | Funktioniert mit MariaDB? | Bereits im Stack? | Beschreibung |
|---|---|---|---|
| **OTel Collector MySQL Receiver** | ✅ Ja | ❌ Nein | Sammelt Metriken aus `SHOW GLOBAL STATUS`, `information_schema`, `performance_schema`. Funktioniert mit MariaDB, da das Protokoll kompatibel ist. |
| **SQL Commenter** | ✅ Ja | ❌ Nein | Trace-Context als SQL-Kommentar (`/* traceparent=... */`). Kein Server-seitiges Parsing, aber im Slow Query Log und in `DIGEST_TEXT` sichtbar. |
| **Client-seitige PDO-Instrumentation** | ✅ Ja | ✅ **Ja** | `open-telemetry/auto-pdo` erzeugt Spans client-seitig. Funktioniert identisch mit MySQL und MariaDB. |
| **Slow Query Log + Log-Exporter** | ✅ Ja (besser als MySQL) | ❌ Nein | MariaDB's `log_slow_verbosity` liefert reichere Daten als MySQL Community. |

### 4.4  Bewertung: OTel-Situation für den webtrees-Stack

**Kein Verlust bei Migration zu MariaDB:**
Der Stack nutzt ausschließlich client-seitige OTel-Instrumentierung (PHP auto-pdo,
Playwright SDK, Boomerang RUM). Server-seitige MySQL-Enterprise-Telemetrie wird weder
genutzt noch wäre sie mit MySQL Community verfügbar. Ein Wechsel zu MariaDB ändert an
der OTel-Situation **nichts**.

**Kein Gewinn bei Migration zu MariaDB:**
MariaDB bietet keine zusätzlichen OTel-Features gegenüber MySQL Community. Die
erweiterten Slow-Query-Log-Optionen (`log_slow_verbosity`) sind kein OTel-Ersatz,
sondern ein ergänzendes Diagnosewerkzeug.

**Potenzielle Erweiterung (sowohl MySQL als auch MariaDB):**
Der OTel Collector MySQL Receiver könnte als zusätzliche Metriken-Quelle konfiguriert
werden — funktioniert mit beiden Datenbanken. Dies ist eine Stack-Erweiterung,
keine Migrationsentscheidung.

---

## 5  SQL-Kompatibilität

### 5.1  JSON-Funktionen

| Funktion | MySQL 8.4 | MariaDB 11.8 | Genutzt in |
|---|---|---|---|
| `JSON_ARRAYAGG()` | ✅ | ✅ (seit 10.5) | `extract-perfschema.sh`, `perfschema-fixture.ts` |
| `JSON_OBJECT()` | ✅ | ✅ (seit 10.2) | `extract-perfschema.sh`, `perfschema-fixture.ts` |

### 5.2  Window-Funktionen

| Funktion | MySQL 8.4 | MariaDB 11.8 | Genutzt in |
|---|---|---|---|
| `ROW_NUMBER() OVER(...)` | ✅ | ✅ (seit 10.2) | `extract-perfschema.sh` Z. 104–105, 119–120 |

### 5.3  Authentifizierung

| Feature | MySQL 8.4 | MariaDB 11.8 |
|---|---|---|
| `caching_sha2_password` | Default | ✅ Unterstützt (seit 10.4), aber nicht Default |
| Default-Auth-Plugin | `caching_sha2_password` | `mysql_native_password` |

**Anpassung nötig:** MariaDB nutzt standardmäßig `mysql_native_password`. Da der Stack
mit PHP-PDO und `caching_sha2_password` arbeitet, muss entweder der MariaDB-Default
geändert oder die PDO-Konfiguration angepasst werden. In der Praxis funktioniert
`mysql_native_password` mit PDO problemlos — die Anpassung ist trivial.

### 5.4  Collation

`utf8mb4_bin` ist in MariaDB verfügbar und verhält sich identisch. Kein Änderungsbedarf.

### 5.5  Healthcheck

Der bestehende Healthcheck `mysqladmin ping -h localhost` funktioniert mit MariaDB
unverändert — `mysqladmin` ist im MariaDB-Container-Image enthalten.

---

## 6  Erforderliche Änderungen bei Migration

### 6.1  compose.yaml — Image und Startparameter

**Test-DB (`mysql`, Zeile 75–99):**

```yaml
# Alt (MySQL 8.4 LTS):
mysql:
  image: docker.io/library/mysql:lts
  command: >
    --character-set-server=utf8mb4
    --collation-server=utf8mb4_bin
    --performance-schema-instrument='stage/%=ON'
    --performance-schema-consumer-events-stages-current=ON
    --performance-schema-consumer-events-stages-history=ON

# Neu (MariaDB 11.8 LTS):
mysql:
  image: docker.io/library/mariadb:lts
  command: >
    --character-set-server=utf8mb4
    --collation-server=utf8mb4_bin
    --performance-schema                            # NEU: P_S explizit aktivieren
    --performance-schema-instrument='stage/%=ON'
    --performance-schema-consumer-events-stages-current=ON
    --performance-schema-consumer-events-stages-history=ON
```

**Security-Track-DB (`mysql-security`, Zeile 180–199):** Analog `mariadb:lts` +
`--performance-schema` (falls P_S dort benötigt wird, ansonsten weglassen).

**Environment-Variablen:** MariaDB-Container-Images verwenden die gleichen
`MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`-Variablen.
Kein Änderungsbedarf.

### 6.2  scripts/extract-perfschema.sh — SQL-Anpassungen

Drei Spalten müssen entfernt oder ersetzt werden:

| Zeile | Alt (MySQL) | Neu (MariaDB) | Anmerkung |
|---|---|---|---|
| 31 | `'p95_ms', ROUND(QUANTILE_95/1000000000, 2),` | Entfernen oder durch berechneten Wert ersetzen | MariaDB hat kein `QUANTILE_95` |
| 32 | `'p99_ms', ROUND(QUANTILE_99/1000000000, 2),` | Entfernen oder durch berechneten Wert ersetzen | MariaDB hat kein `QUANTILE_99` |
| 39 | `'sample_text', LEFT(QUERY_SAMPLE_TEXT, 500),` | Entfernen | MariaDB hat kein `QUERY_SAMPLE_TEXT` |

**Optionaler Ersatz für Perzentile:** MariaDB bietet das `QUERY_RESPONSE_TIME`-Plugin
(Histogramm-artige Latenzverteilung). Dieses liefert Bucket-basierte Verteilungsdaten
statt exakter Perzentile, ist aber als grobe Annäherung nutzbar:

```sql
-- MariaDB Query Response Time Plugin (muss aktiviert werden):
INSTALL SONAME 'query_response_time';
SET GLOBAL query_response_time_stats = ON;
SELECT * FROM INFORMATION_SCHEMA.QUERY_RESPONSE_TIME;
```

Die Granularität ist gröber als MySQL's `QUANTILE_95`/`QUANTILE_99`, aber besser als
gar keine Verteilungsinformation.

**Optionaler Ersatz für `QUERY_SAMPLE_TEXT`:** MariaDB bietet keinen direkten Ersatz
in der P_S-Digest-Tabelle. Alternativen:

- `DIGEST_TEXT` (bereits genutzt) liefert den normalisierten Query-Text (Parameter
  durch `?` ersetzt)
- Slow Query Log mit `log_slow_verbosity=query_plan` liefert vollständige Queries,
  aber nicht in P_S abrufbar

### 6.3  layer4-e2e/helpers/perfschema-fixture.ts

Keine Änderung erforderlich — die Per-Test-Fixture nutzt weder `QUANTILE_*` noch
`QUERY_SAMPLE_TEXT`. Alle referenzierten Spalten (`DIGEST_TEXT`, `COUNT_STAR`,
`AVG_TIMER_WAIT`, `SUM_TIMER_WAIT`, `SUM_ROWS_EXAMINED`, `SUM_SELECT_SCAN`,
`SUM_NO_INDEX_USED`) existieren in MariaDB.

### 6.4  scripts/truncate-perfschema.sh

Keine Änderung erforderlich — alle vier `TRUNCATE TABLE`-Statements referenzieren
Tabellen, die in MariaDB existieren.

### 6.5  Dokumentation aktualisieren

| Datei | Zeile(n) | Änderung |
|---|---|---|
| `README.md` | 66 | `MySQL LTS (8.4)` → `MariaDB LTS (11.8)` |
| `docs/tp_infrastructure_spec.md` | 424, 430 | Image-Tag + Versionsnummer |
| `docs/security-audit/01_scope_and_tracks.md` | 17 | Versionsnummer |
| `docs/security-audit/03_infrastructure_usage.md` | 71 | Versionsnummer + P_S-Konfiguration |
| `docs/systemtest_perf_improve_plan.md` | 243, 284, 434 | Versionsspezifische P_S-Analyse |
| `CLAUDE.md` | OTel-Stack-Abschnitt | Falls datenbankspezifische Hinweise enthalten |

### 6.6  Volume-Name

Der Volume-Name `mysql-data` in `compose.yaml` ist funktional irrelevant (MariaDB
schreibt ebenfalls nach `/var/lib/mysql`). Eine Umbenennung zu `mariadb-data` wäre
kosmetisch sauber, erfordert aber `podman volume rm mysql-data` und Neuinitialisierung.

---

## 7  Vergleichsmatrix: MySQL 8.4 LTS vs. MariaDB 11.8 LTS

### 7.1  Performance Schema

| Feature | MySQL 8.4 LTS | MariaDB 11.8 LTS | Gewinner |
|---|---|---|---|
| P_S-Tabellen gesamt | ~126 | ~86 | MySQL |
| P_S standardmäßig aktiv | ✅ Ja | ❌ Nein | MySQL |
| `events_statements_summary_by_digest` | ✅ Vollständig | ✅ Ohne Perzentile/Samples | MySQL |
| `QUANTILE_95` / `QUANTILE_99` | ✅ | ❌ | MySQL |
| `QUERY_SAMPLE_TEXT` | ✅ | ❌ | MySQL |
| `data_locks` / `data_lock_waits` | ✅ | ❌ | MySQL |
| `error_log`-Tabelle | ✅ (seit 8.0.22) | ❌ | MySQL |
| Statement-Histogramme | ✅ | ❌ | MySQL |
| Stage/Transaction/Wait Events | ✅ | ✅ | Gleichstand |
| Table I/O Waits | ✅ | ✅ | Gleichstand |
| Extended User Statistics | ❌ | ✅ | MariaDB |
| Query Response Time Plugin | ❌ (Community) | ✅ | MariaDB |
| `FLUSH GLOBAL STATUS` | ❌ | ✅ (11.8+) | MariaDB |

### 7.2  OpenTelemetry

| Feature | MySQL 8.4 Community | MySQL Enterprise | MariaDB 11.8 |
|---|---|---|---|
| Native OTel-Traces | ❌ | ✅ `component_telemetry` | ❌ |
| Native OTel-Metriken | ❌ | ✅ 300+ Metriken | ❌ |
| Query Attributes (`traceparent`) | ❌ | ✅ (8.3.0+) | ❌ |
| Telemetry Logging API | ✅ (9.6+, nicht 8.4) | ✅ | ❌ |
| OTel Collector MySQL Receiver | ✅ | ✅ | ✅ |
| Client-seitige PDO-Instrumentation | ✅ | ✅ | ✅ |

**Ergebnis:** MySQL Enterprise hat einen klaren OTel-Vorsprung. Zwischen MySQL Community
und MariaDB besteht **kein Unterschied** — beide bieten keine native Server-seitige
OTel-Integration. Der webtrees-Stack nutzt ausschließlich client-seitige
Instrumentierung, die mit beiden Datenbanken identisch funktioniert.

### 7.3  Observability insgesamt

| Feature | MySQL 8.4 Community | MariaDB 11.8 |
|---|---|---|
| sys Schema | ✅ Vollständig (seit 5.7) | ⚠️ Eingeschränkt (Fork von MySQL-5.6-sys) |
| Slow Query Log | Basis | ⬆ Erweitert (`log_slow_verbosity`, Sampling) |
| Extended Statistics | ❌ | ✅ `TABLE_STATISTICS`, `INDEX_STATISTICS` |
| Pool of Threads (Metriken) | ❌ (Community) | ✅ |

### 7.4  Sonstige Aspekte

| Aspekt | MySQL 8.4 LTS | MariaDB 11.8 LTS |
|---|---|---|
| Lizenz | GPL-2.0 (+ proprietäre Enterprise) | GPL-2.0 (+ BSL für einige Enterprise-Features) |
| LTS-Support | Bis April 2032 (8 Jahre) | ~3 Jahre (bis ~2029) |
| Container-Image-Größe | ~550 MB | ~400 MB |
| webtrees-Kompatibilität | Offiziell getestet | Funktioniert, aber nicht offiziell getestet |
| PHP-PDO-Kompatibilität | Volle `mysqlnd`-Unterstützung | Volle `mysqlnd`-Unterstützung |
| Optimizer-Qualität | Sehr gut (8.x ausgereift) | Sehr gut (eigene Optimizer-Erweiterungen) |

---

## 8  Empfehlung

| Option | Pro | Contra |
|---|---|---|
| **A: Bei MySQL 8.4 LTS bleiben** | Kein Aufwand, P_S vollständig, 8 Jahre Support, Perzentile + Samples verfügbar | Keine MariaDB-exklusiven Features |
| **B: Auf MariaDB 11.8 LTS migrieren** | Erweiterte Slow-Query-Log-Optionen, Extended Statistics, kleineres Image, vollständig Open Source | P_S-Rückstand (kein `QUANTILE_*`, kein `QUERY_SAMPLE_TEXT`), kürzerer LTS-Support, SQL-Anpassungen nötig |
| **C: Auf MySQL 9.7 LTS warten** (aus MySQL-Analyse) | Alle 9.x-Neuerungen + volle P_S-Kompatibilität | Noch nicht verfügbar |

### Empfehlung: Option A — bei MySQL 8.4 LTS bleiben

**Begründung:**

1. **Performance Schema ist der Kern der Testplattform-Observability.** Der Stack nutzt
   `QUANTILE_95`, `QUANTILE_99` und `QUERY_SAMPLE_TEXT` in der Produktions-Extraktion
   (`extract-perfschema.sh`). Diese Daten gehen bei MariaDB verloren — es gibt keinen
   gleichwertigen Ersatz. Das Query Response Time Plugin liefert nur grobe Buckets,
   keine exakten Perzentile pro Digest.

2. **OTel ist kein Differenzierungsmerkmal.** Weder MySQL Community noch MariaDB bieten
   native Server-seitige OTel-Telemetrie. Die MySQL-Enterprise-Features
   (`component_telemetry`, Query Attributes) sind für den Open-Source-Stack irrelevant.
   Die client-seitige Instrumentierung funktioniert mit beiden Datenbanken identisch.

3. **Der MariaDB LTS-Support ist kürzer.** MySQL 8.4 LTS läuft bis 2032,
   MariaDB 11.8 LTS nur bis ~2029. Für eine langlebige Testinfrastruktur ist der
   längere Supportzeitraum vorteilhaft.

4. **webtrees wird primär mit MySQL getestet.** Der webtrees-Core unterstützt MySQL
   offiziell — MariaDB funktioniert zwar, ist aber nicht die primäre Testplattform
   der Upstream-Entwicklung.

5. **Die Migration hat Aufwand ohne proportionalen Nutzen.** Die MariaDB-exklusiven
   Features (Extended Statistics, Slow-Query-Verbosity) sind nützliche Ergänzungen,
   aber kein zwingender Grund für eine Migration — zumal sie den Verlust der
   P_S-Perzentile nicht kompensieren.

### Szenario für eine Neubewertung

Ein Wechsel zu MariaDB würde sinnvoll, wenn:

- MariaDB `QUANTILE_95`/`QUANTILE_99` und `QUERY_SAMPLE_TEXT` in die
  `events_statements_summary_by_digest` aufnimmt (aktuell kein offener MDEV-Ticket dafür)
- MySQL die Community-Edition in einer Weise einschränkt, die den Teststack betrifft
- webtrees-Upstream MariaDB als primäre Testdatenbank adoptiert
- Der Stack eine Dual-Database-Konfiguration erhält (MySQL + MariaDB parallel),
  um Kompatibilität beider Datenbanken zu validieren — in diesem Fall wäre MariaDB
  eine **zusätzliche** Test-DB, kein Ersatz
