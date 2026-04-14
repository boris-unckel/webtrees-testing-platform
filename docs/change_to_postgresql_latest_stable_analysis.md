<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Analyse: Migration von MySQL 8.4 LTS auf PostgreSQL Latest Stable (18.x)

**Datum:** 2026-04-13
**Status:** Analyse abgeschlossen — Empfehlung: bei MySQL bleiben (siehe Abschnitt 9)

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

### 1.5  Datenbanktreiber und PHP-Extensions

| Komponente | Aktuell | Kontext |
|---|---|---|
| `Containerfile.webtrees` Z. 23 | `pdo_mysql` | PHP-Extension für MySQL-Zugriff |
| `Containerfile.webtrees` Z. 16 | `default-mysql-client` | CLI-Tool für Skripte (`mysql` Befehl) |
| `layer3-integration/tests/MysqlTestCase.php` Z. 113 | `DB::MYSQL` | webtrees-Treiberkonstante |
| `scripts/setup-webtrees.sh` Z. 122 | `dbtype = "mysql"` | config.ini.php-Konfiguration |
| `layer4-e2e/helpers/perfschema-fixture.ts` Z. 9 | `mysql -h ...` | Direkter MySQL-CLI-Aufruf |

---

## 2  Versionslage PostgreSQL (Stand April 2026)

| Release | Tag (Docker Hub) | Support |
|---|---|---|
| **18.x** | `postgres:18`, `postgres:latest` | Bis ~November 2030 (5 Jahre) |
| 17.x | `postgres:17` | Bis ~November 2029 |
| 16.x | `postgres:16` | Bis ~November 2028 |
| 15.x | `postgres:15` | Bis ~November 2027 |
| 14.x | `postgres:14` | Bis ~November 2026 |

**Aktuelle stabile Version:** PostgreSQL 18.3 (Release Februar 2026).

**Kein LTS-Modell:** PostgreSQL hat kein LTS-Konzept wie MySQL. Jede Major-Version
wird exakt 5 Jahre unterstützt. Es gibt keinen Docker-Tag `:lts` — stattdessen
muss ein expliziter Major-Tag (`postgres:18`) oder `:latest` verwendet werden.

**Headline-Feature PostgreSQL 18:** Asynchrones I/O-Subsystem (AIO) mit bis zu 3×
Performance-Verbesserung bei Leseoperationen; neue Systemview `pg_aios` zur
AIO-Überwachung.

---

## 3  Performance-Monitoring: PostgreSQL vs. MySQL Performance Schema

PostgreSQL hat kein Performance Schema. Stattdessen bietet es ein Ökosystem aus
statistischen System-Views und Extensions, die verschiedene Teilaspekte des
MySQL Performance Schema abdecken.

### 3.1  pg_stat_statements (Äquivalent zu `events_statements_summary_by_digest`)

Extension; muss in `shared_preload_libraries` geladen und mit
`CREATE EXTENSION pg_stat_statements` aktiviert werden.

**Spalten-Mapping MySQL → PostgreSQL:**

| MySQL P_S-Spalte | PostgreSQL pg_stat_statements | Status |
|---|---|---|
| `DIGEST` | `queryid` (bigint Hash) | ✅ Funktional äquivalent |
| `DIGEST_TEXT` | `query` (repräsentativer Query-Text) | ✅ Besser — enthält tatsächlichen Query-Text |
| `COUNT_STAR` | `calls` | ✅ Äquivalent |
| `SUM_TIMER_WAIT` | `total_exec_time` (ms) | ✅ Äquivalent (andere Einheit: ms statt ps) |
| `AVG_TIMER_WAIT` | `mean_exec_time` (ms) | ✅ Äquivalent |
| `MAX_TIMER_WAIT` | `max_exec_time` (ms) | ✅ Äquivalent |
| **`QUANTILE_95`** | — | ❌ **Nicht vorhanden** |
| **`QUANTILE_99`** | — | ❌ **Nicht vorhanden** |
| **`QUERY_SAMPLE_TEXT`** | `query` | ✅ Vorhanden (repräsentativer Text mit Platzhaltern `$1`, `$2` statt `?`) |
| `SUM_ROWS_EXAMINED` | `shared_blks_hit` + `shared_blks_read` (Block-basiert, nicht Row-basiert) | ⚠️ Anderes Granularitätsmodell |
| `SUM_ROWS_SENT` | `rows` (Summe betroffener Zeilen) | ✅ Annähernd äquivalent |
| `SUM_SELECT_SCAN` | — (ableitbar aus `pg_stat_user_tables.seq_scan`) | ⚠️ Andere Granularität (pro Tabelle, nicht pro Digest) |
| `SUM_NO_INDEX_USED` | — | ❌ Nicht vorhanden (ableitbar aus `seq_scan` vs. `idx_scan`) |
| `SUM_CREATED_TMP_DISK_TABLES` | `temp_blks_read` + `temp_blks_written` | ⚠️ Block-basiert statt Tabellen-Count |
| `SUM_LOCK_TIME` | — | ❌ Nicht vorhanden (Lock-Timing nur in `pg_stat_activity`) |
| `FIRST_SEEN` / `LAST_SEEN` | `stats_since` / `minmax_stats_since` | ⚠️ Nur Reset-Zeitpunkte, nicht First/Last |

**Zusätzliche Spalten in pg_stat_statements (ohne MySQL-Äquivalent):**

| PostgreSQL-Spalte | Beschreibung | Relevanz |
|---|---|---|
| `total_plan_time` / `mean_plan_time` | Planungszeit separat erfasst | ⬆ Hoch — MySQL mischt Planning in Timer |
| `stddev_exec_time` / `stddev_plan_time` | Standardabweichung | ⬆ Hoch — ermöglicht statistische Perzentil-Approximation |
| `shared_blks_hit` / `shared_blks_read` | Buffer-Cache-Effizienz pro Query | ⬆ Hoch — kein MySQL-Äquivalent pro Digest |
| `wal_records` / `wal_bytes` | WAL-I/O pro Query | Mittel |
| `jit_*` (8 Spalten) | JIT-Kompilierungsdetails | Gering |
| `parallel_workers_launched` | Parallelisierungs-Monitoring | Mittel |

### 3.2  Perzentil-Lücke — Analyse und Workarounds

MySQL 8.4 bietet `QUANTILE_95` und `QUANTILE_99` **nativ** in
`events_statements_summary_by_digest`. PostgreSQL hat **keine** Perzentil-Spalten in
`pg_stat_statements`.

**Workaround A: Statistische Approximation aus `mean` + `stddev`**

PostgreSQL liefert `mean_exec_time` und `stddev_exec_time`. Unter Annahme einer
Normalverteilung (die für Query-Latenzen typischerweise **nicht** zutrifft):

```
P95 ≈ mean + 1.645 × stddev
P99 ≈ mean + 2.326 × stddev
```

Qualität: Grobe Annäherung, systematisch ungenau bei schiefen Verteilungen (typisch für
DB-Workloads mit gelegentlichen Ausreißern).

**Workaround B: pg_stat_monitor (Percona)**

Die Open-Source-Extension `pg_stat_monitor` von Percona bietet:
- Time-Bucket-basierte Statistiken statt kumulativer Werte
- Histogramm-Funktion für Latenzverteilungen
- Tatsächliche Query-Parameter (statt Platzhalter)
- Execution-Plan pro Statement

Die Histogramm-Funktion ermöglicht **Perzentil-Approximation**, ist aber nicht exakt
vergleichbar mit MySQL's nativem `QUANTILE_95`/`QUANTILE_99`.

**Workaround C: Eigenes Query-Logging + SQL-Aggregation**

PostgreSQL bietet die Ordered-Set-Aggregatfunktionen `percentile_cont()` und
`percentile_disc()`:

```sql
SELECT percentile_cont(0.95) WITHIN GROUP (ORDER BY exec_time) AS p95,
       percentile_cont(0.99) WITHIN GROUP (ORDER BY exec_time) AS p99
FROM query_log;
```

Diese können **nicht** auf `pg_stat_statements` angewendet werden (nur Aggregate
vorhanden, keine Einzelmessungen). Voraussetzung wäre ein eigenes Logging mit
`pg_stat_monitor`-Time-Buckets oder `log_min_duration_statement`.

**Bewertung:** Kein PostgreSQL-Workaround erreicht die Qualität von MySQL's nativen
Perzentilen. Die beste Annäherung liefert `pg_stat_monitor`, erfordert aber eine
zusätzliche Extension und bietet nur Histogramm-basierte Approximation.

### 3.3  Table-I/O-Monitoring (Äquivalent zu `table_io_waits_summary_by_table`)

PostgreSQL bietet zwei Views als Teilersatz:

**pg_stat_user_tables** — Zugriffsstatistiken pro Tabelle:

| MySQL P_S-Spalte | PostgreSQL-Spalte | Status |
|---|---|---|
| `COUNT_READ` | `seq_tup_read` + `idx_tup_fetch` | ✅ Funktional äquivalent |
| `COUNT_WRITE` | `n_tup_ins` + `n_tup_upd` + `n_tup_del` | ✅ Funktional äquivalent |
| `COUNT_FETCH` | `idx_tup_fetch` | ✅ Äquivalent |
| `COUNT_INSERT` | `n_tup_ins` | ✅ Äquivalent |
| `COUNT_UPDATE` | `n_tup_upd` | ✅ Äquivalent |
| `COUNT_DELETE` | `n_tup_del` | ✅ Äquivalent |
| **`SUM_TIMER_WAIT`** | — | ❌ **Keine Zeitmessung pro Tabelle** |

**pg_statio_user_tables** — I/O-Statistiken (Block-basiert):
- `heap_blks_read` / `heap_blks_hit` — Heap-Block-Reads/Cache-Hits
- `idx_blks_read` / `idx_blks_hit` — Index-Block-Reads/Cache-Hits

**Bewertung:** Operationen-Zähler sind äquivalent. Die **kritische Lücke** ist das
fehlende Zeitmessungs-Tracking pro Tabelle (`SUM_TIMER_WAIT`). Die `extract-perfschema.sh`-
Summary (Z. 117–130) sortiert nach `SUM_TIMER_WAIT` — dieses Ranking ist mit PostgreSQL
nicht direkt reproduzierbar.

### 3.4  Stage-Monitoring (Äquivalent zu `events_stages_summary_global_by_event_name`)

| MySQL P_S | PostgreSQL | Status |
|---|---|---|
| Kumulative Stage-Timer (global) | `pg_stat_activity.wait_event_type` + `wait_event` | ⚠️ Nur Realtime-Snapshot, **nicht kumulativ** |

PostgreSQL zeigt Wait-Events als Live-Snapshot in `pg_stat_activity`. Es gibt **keine**
kumulative Zusammenfassung wie MySQL's `events_stages_summary_global_by_event_name`.
Für kumulative Daten müsste ein externer Sampling-Prozess eingerichtet werden.

### 3.5  Transaktions-Monitoring (Äquivalent zu `events_transactions_summary_global_by_event_name`)

| MySQL P_S | PostgreSQL | Status |
|---|---|---|
| Kumulative Transaktions-Timer | `pg_stat_database` (`xact_commit`, `xact_rollback`) | ⚠️ Nur Zähler, **keine Timer** |

`pg_stat_database` liefert kumulative Commit/Rollback-Zähler pro Datenbank, aber
**keine Zeitmessungen**. Transaktionsdauer ist nur über `pg_stat_activity.xact_start`
als Realtime-Momentaufnahme verfügbar.

### 3.6  PostgreSQL-exklusive Monitoring-Features (kein MySQL-Äquivalent)

| PostgreSQL-Feature | Version | Beschreibung | Relevanz |
|---|---|---|---|
| **`pg_stat_io`** | 16+ | Globale I/O-Statistiken nach Backend-Typ, Objekt und Kontext — mit **Zeitmessungen** (`read_time`, `write_time`, `fsync_time`) | ⬆ Hoch — detaillierteres I/O-Profiling als MySQL P_S |
| **`pg_stat_checkpointer`** | 17+ | Checkpoint-Monitoring (Schreib-/Sync-Zeiten, Buffer-Zähler) | Mittel |
| **`pg_stat_wal`** | 14+ | WAL-Generierungsstatistiken | Mittel |
| **`pg_aios`** | 18+ | Async-I/O-Subsystem-Monitoring | Mittel |
| **Planungszeit-Separation** | alle | `total_plan_time` separat von `total_exec_time` in `pg_stat_statements` | ⬆ Hoch — MySQL mischt beides |
| **Buffer-Cache-Effizienz pro Query** | alle | `shared_blks_hit` / `shared_blks_read` in `pg_stat_statements` | ⬆ Hoch — MySQL hat dies nicht pro Digest |

### 3.7  Mapping-Gesamtübersicht

| MySQL Performance Schema | PostgreSQL Äquivalent | Vollständigkeit |
|---|---|---|
| `events_statements_summary_by_digest` | `pg_stat_statements` (Extension) | ⚠️ 80 % — kein `QUANTILE_*`, kein `LOCK_TIME`, anderes Row-Tracking |
| `QUANTILE_95` / `QUANTILE_99` | — (`pg_stat_monitor` Histogramme als Approximation) | ❌ Kein direktes Äquivalent |
| `QUERY_SAMPLE_TEXT` | `query` Spalte in `pg_stat_statements` | ✅ Vorhanden |
| `table_io_waits_summary_by_table` | `pg_stat_user_tables` + `pg_statio_user_tables` | ⚠️ 70 % — kein `SUM_TIMER_WAIT` |
| `events_stages_summary_global_by_event_name` | `pg_stat_activity` (Realtime-Snapshot) | ❌ Nicht kumulativ |
| `events_transactions_summary_global_by_event_name` | `pg_stat_database` (Zähler) | ⚠️ 40 % — keine Timer |
| (kein Äquivalent) | `pg_stat_io` (PG 16+) | PostgreSQL stärker |
| (kein Äquivalent) | Planungszeit-Separation | PostgreSQL stärker |
| (kein Äquivalent) | Buffer-Cache pro Query | PostgreSQL stärker |

---

## 4  OpenTelemetry: PostgreSQL vs. MySQL

### 4.1  MySQL Enterprise Telemetry (Referenz — kommerziell)

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

### 4.2  PostgreSQL Community: Server-seitiges Distributed Tracing

PostgreSQL Community bietet mit **pg_tracing** eine Fähigkeit, die MySQL Community
**nicht** hat: server-seitiges Distributed Tracing mit OTel-Export.

#### pg_tracing (DataDog, MIT-Lizenz)

| Aspekt | Details |
|---|---|
| Repository | https://github.com/DataDog/pg_tracing |
| Version | 0.1.3 (März 2025) |
| Lizenz | MIT (Open Source) |
| Status | Frühe Entwicklung — „may be unstable" |
| PostgreSQL-Versionen | 14, 15, 16 (**nicht** 17/18) |

**Funktionsumfang:**

- Erzeugt server-seitige Distributed-Tracing-Spans für gesamplete Queries
- Instrumentiert PostgreSQL-interne Funktionen: Planner, ExecutorRun, ExecutorFinish,
  ProcessUtility
- Execution-Plan-Nodes als Child-Spans: SeqScan, NestedLoop, HashJoin etc.
- Nested Queries, Trigger, Parallel Worker
- Transaction Commits

**Trace-Context-Propagation (W3C `traceparent`):**

```sql
/*traceparent='00-<trace-id>-<span-id>-01'*/ SELECT 1;
```

pg_tracing extrahiert den `traceparent` aus SQL-Kommentaren (SQLCommenter-Konvention).
Dies ermöglicht **End-to-End-Tracing vom Playwright-Test über PHP bis in die
Datenbank** — ein Feature, das bei MySQL Community **nicht existiert** und bei
MySQL Enterprise nur mit Query Attributes (ab 8.3.0) möglich ist.

**OTLP-Export:**

Direkt konfigurierbar über `pg_tracing.otel_endpoint`. Ein Background-Worker sendet
Spans per **OTLP HTTP/JSON** an einen OTel Collector. Alternativ können Spans über
die Funktionen `pg_tracing_consume_spans()` / `pg_tracing_peek_spans()` extern
abgeholt werden.

**Einschränkungen:**
- Frühe Entwicklungsphase, nicht produktionsreif
- Kein Support für PostgreSQL 17 oder 18
- Von DataDog entwickelt — kein PostgreSQL-Core/Contrib-Paket
- Erfordert `shared_preload_libraries`-Konfiguration

#### pgotel (OnGres)

Infrastruktur-Extension, die das OpenTelemetry C++ SDK für PostgreSQL-Extensions
bereitstellt. Sehr früh (16 Commits), nicht direkt für Query-Monitoring nutzbar.

### 4.3  OTel Collector PostgreSQL Receiver

| Aspekt | MySQL Receiver | PostgreSQL Receiver |
|---|---|---|
| Repository | `opentelemetry-collector-contrib` | `opentelemetry-collector-contrib` |
| Reife | Stabil | Stabil |
| Scraping-Methode | `SHOW GLOBAL STATUS`, `information_schema`, `performance_schema` | `pg_stat_*` System-Views |

**PostgreSQL-Receiver-Metriken (Auswahl):**

| Metrik | Beschreibung |
|---|---|
| `postgresql.backends` | Aktive Connections |
| `postgresql.commits` / `postgresql.rollbacks` | Committed/Rolled-back Transactions |
| `postgresql.db_size` | Datenbank-Größe |
| `postgresql.blocks_read` | Block-Reads |
| `postgresql.index.scans` | Index-Scans |
| `postgresql.operations` | Row-Operationen (INSERT/UPDATE/DELETE/SELECT) |
| `postgresql.table.size` | Tabellen-Größe |
| `postgresql.deadlocks` | Deadlocks (optional) |
| `postgresql.sequential_scans` | Sequentielle Scans (optional) |
| `postgresql.temp_files` | Temporäre Dateien (optional) |
| `postgresql.replication.data_delay` | Replikations-Verzögerung (optional) |

**Bewertung:** Funktional gleichwertig mit dem MySQL Receiver. Beide sind Teil der
offiziellen OTel-Contrib-Distribution und produktionsreif. Der PostgreSQL Receiver
nutzt die statistischen System-Views statt Performance Schema.

### 4.4  pg_stat_monitor (Percona, Open Source)

| Aspekt | Details |
|---|---|
| Lizenz | PostgreSQL-Lizenz (permissiv, Open Source) |
| PostgreSQL-Versionen | 14, 15, 16, 17, 18 |
| Status | Produktionsreif |

**Zusatzfeatures gegenüber pg_stat_statements:**

1. **Time Buckets:** Statistiken in konfigurierbaren Zeitintervallen (nicht kumulativ)
2. **Query-Parameter:** Wahlweise Platzhalter oder echte Parameterwerte
3. **Ausführungspläne:** Tatsächlicher Query-Plan zu jedem Statement
4. **Tabellen-Zugriff:** Identifikation welche Queries auf welche Tabellen zugreifen
5. **Histogramme:** Timing-Daten als Histogramm (ermöglicht Perzentil-Approximation)

**Bewertung:** pg_stat_monitor ist der beste verfügbare Ersatz für MySQL's
Performance-Schema-Statement-Digests. Die Histogramm-Funktion schließt die
Perzentil-Lücke teilweise. Open Source und aktiv maintained.

### 4.5  Vergleich: MySQL Community vs. PostgreSQL Community — OTel-Support

| Aspekt | MySQL Community | PostgreSQL Community | Gewinner |
|---|---|---|---|
| Nativer OTLP-Export (Server-seitig) | ❌ Nein (nur Enterprise) | ⚠️ pg_tracing (experimentell, MIT) | **PostgreSQL** |
| Trace-Context-Propagation (Client→Server) | ❌ Nein (nur Enterprise ab 8.3) | ⚠️ pg_tracing via SQLCommenter | **PostgreSQL** |
| OTel Collector Receiver | ✅ `mysqlreceiver` (stabil) | ✅ `postgresqlreceiver` (stabil) | Gleichstand |
| Client-seitige PDO-Instrumentation | ✅ `auto-pdo` | ✅ `auto-pdo` | Gleichstand |
| Enhanced Query Monitoring | ✅ Performance Schema (eingebaut) | ✅ pg_stat_monitor (Percona, Open Source) | MySQL (umfangreicher) |
| Audit Extension | Keine offizielle (Community) | pgaudit (Open Source) | **PostgreSQL** |
| End-to-End DB-Tracing (Open Source) | ❌ Nicht möglich | ⚠️ pg_tracing (experimentell) | **PostgreSQL** |
| Telemetry Logging API | ✅ (ab 9.6) | ❌ Nein | MySQL |

### 4.6  Bewertung: OTel-Situation für den webtrees-Stack

**Potenzielle Gewinne bei PostgreSQL:**

1. **Server-seitiges Distributed Tracing (pg_tracing):** Ermöglicht theoretisch
   End-to-End-Traces vom Playwright-Browser über PHP/PDO bis in die PostgreSQL-
   Query-Execution. Der bestehende OTel-Stack (Collector, Jaeger, Boomerang) könnte
   die PostgreSQL-Spans direkt aufnehmen. **Einschränkung:** pg_tracing ist
   experimentell und unterstützt PostgreSQL 18 noch nicht.

2. **SQLCommenter-basierte Trace-Korrelation:** Der Stack injiziert bereits
   `traceparent`-Header in HTTP-Requests (via `otel-fixture.ts`). Mit pg_tracing
   könnte der `traceparent` als SQL-Kommentar an PostgreSQL weitergereicht werden,
   was eine durchgehende Trace-Kette ergibt — ohne dass MySQL Enterprise benötigt wird.

3. **OTel Collector PostgreSQL Receiver:** Könnte als zusätzliche Metriken-Quelle
   den bestehenden Traces-only-Pipeline um Database-Metriken erweitern.

**Kein Verlust gegenüber MySQL Community:**

Der Stack nutzt ausschließlich client-seitige OTel-Instrumentierung. MySQL Enterprise
Telemetry ist nicht im Einsatz und wäre auch nicht nutzbar. Ein Wechsel zu PostgreSQL
entfernt **keine** bestehende OTel-Fähigkeit.

**Fazit:** PostgreSQL bietet im Open-Source-Bereich **mehr** OTel-Potenzial als
MySQL Community, insbesondere durch pg_tracing. Die Reife von pg_tracing (experimentell,
kein PG 18-Support) limitiert den praktischen Nutzen derzeit stark.

---

## 5  webtrees PostgreSQL-Unterstützung

### 5.1  Offizielle Treiber-Unterstützung

webtrees unterstützt PostgreSQL **offiziell** seit Version 2.0. Die Klasse
`Fisharebest\Webtrees\DB` definiert die Treiberkonstanten:

```php
public const string MYSQL      = 'mysql';
public const string POSTGRESQL = 'pgsql';
public const string SQLITE     = 'sqlite';
public const string SQL_SERVER = 'sqlsrv';
```

### 5.2  Setup-Wizard

Der Setup-Wizard (`SetupWizard.php`) bietet PostgreSQL als wählbare Option:
- Default-Port: 5432
- Eigene View-Datei: `resources/views/setup/step-4-database-pgsql.phtml`
- Versionsanforderung (webtrees 2.2): PostgreSQL 10.0+

### 5.3  Illuminate Database Layer

webtrees nutzt `Illuminate\Database\Capsule\Manager` (Laravel Database Component),
das PostgreSQL nativ über den `pgsql`-Treiber unterstützt. Die DB-spezifischen
Anpassungen in webtrees:

| Aspekt | MySQL | PostgreSQL |
|---|---|---|
| Collation | `utf8mb4_bin` | `und-x-icu` |
| Regex-Operator | `REGEXP` | `~` |
| Group Concat | `GROUP_CONCAT(%s)` | `STRING_AGG(%s, ',')` |
| Case-insensitive Suche | `LIKE` | `ILIKE` |
| Init-SQL | `SET NAMES utf8mb4 COLLATE utf8mb4_bin` | (leer) |

### 5.4  PHP-Extension

`pdo_pgsql` ist im `composer.json` als `suggest` (nicht `require`) gelistet.
Der `ServerCheckService` prüft bei PostgreSQL-Auswahl auf die Extension `pdo_pgsql`.

### 5.5  Bewertung

webtrees unterstützt PostgreSQL vollständig auf Anwendungsebene. Die
Schema-Migrationen nutzen den Illuminate Schema Builder, der PostgreSQL-DDL
korrekt generiert. **Die Testplattform hat jedoch keine PostgreSQL-Tests** — alle
Layer-3-Integrationstests sind hardcoded auf `DB::MYSQL`.

---

## 6  SQL-Kompatibilität: PerfSchema-Skripte

Die PerfSchema-Skripte nutzen MySQL-spezifische Syntax. Bei PostgreSQL müssten sie
**vollständig** neu geschrieben werden — nicht angepasst, sondern gegen ein
völlig anderes Schema-Set (`pg_stat_*` statt `performance_schema`).

### 6.1  extract-perfschema.sh — Kompletter Rewrite erforderlich

| Funktion | MySQL (aktuell) | PostgreSQL (Ziel) | Aufwand |
|---|---|---|---|
| Statement-Digests (Z. 22–48) | `performance_schema.events_statements_summary_by_digest` mit `JSON_ARRAYAGG`/`JSON_OBJECT`, `QUANTILE_95`, `QUANTILE_99`, `QUERY_SAMPLE_TEXT` | `pg_stat_statements` mit `json_agg`/`json_build_object`, **ohne Perzentile**, `query` statt `QUERY_SAMPLE_TEXT` | ⬆ Hoch — Neuschreiben + Feature-Verlust |
| Table-I/O (Z. 52–67) | `table_io_waits_summary_by_table` mit `SUM_TIMER_WAIT` | `pg_stat_user_tables` + `pg_statio_user_tables`, **ohne Timer** | ⬆ Hoch — anderes Datenmodell |
| Stages (Z. 70–81) | `events_stages_summary_global_by_event_name` (kumulativ) | `pg_stat_activity` (Realtime) oder entfallen | ⬆ Hoch — kein äquivalentes kumulatives System |
| Transactions (Z. 84–95) | `events_transactions_summary_global_by_event_name` (kumulativ) | `pg_stat_database` (nur Zähler) | Mittel — Zähler vorhanden, Timer fehlen |
| Summary/Top-10 (Z. 103–130) | `ROW_NUMBER() OVER(...)`, Sort nach `SUM_TIMER_WAIT` | `ROW_NUMBER()` verfügbar, Sort nach `total_exec_time` | Mittel — Syntax-Anpassung |
| Warnungen (Z. 133–144) | `SUM_SELECT_SCAN`, `SUM_NO_INDEX_USED`, `SUM_CREATED_TMP_DISK_TABLES` | Ableitung aus `pg_stat_user_tables.seq_scan`, `temp_blks_written` | Mittel — andere Semantik |

### 6.2  truncate-perfschema.sh — Äquivalente Reset-Funktionen

| MySQL (aktuell) | PostgreSQL |
|---|---|
| `TRUNCATE TABLE performance_schema.events_statements_summary_by_digest` | `SELECT pg_stat_statements_reset()` |
| `TRUNCATE TABLE events_stages_summary_global_by_event_name` | `SELECT pg_stat_reset()` (setzt **alle** stat-Views zurück) |
| `TRUNCATE TABLE table_io_waits_summary_by_table` | `SELECT pg_stat_reset()` |
| `TRUNCATE TABLE events_transactions_summary_global_by_event_name` | `SELECT pg_stat_reset()` |

**Problem:** `pg_stat_reset()` ist eine globale Reset-Funktion — sie setzt **alle**
statistischen Zähler zurück, nicht nur einzelne. Eine granulare Trennung wie bei
MySQL Performance Schema (`TRUNCATE` pro Tabelle) ist nicht möglich.
`pg_stat_statements_reset()` setzt nur `pg_stat_statements` zurück.

### 6.3  perfschema-fixture.ts — Kompletter Rewrite erforderlich

| Aspekt | MySQL (aktuell) | PostgreSQL (Ziel) |
|---|---|---|
| CLI-Tool | `mysql -h ... -u root -p...` | `psql -h ... -U postgres` |
| Truncate | `TRUNCATE TABLE performance_schema.*` | `SELECT pg_stat_statements_reset(); SELECT pg_stat_reset();` |
| Statement-JSON | `JSON_ARRAYAGG(JSON_OBJECT(...))` gegen P_S | `json_agg(json_build_object(...))` gegen `pg_stat_statements` |
| Table-I/O-JSON | `JSON_ARRAYAGG(JSON_OBJECT(...))` gegen `table_io_waits` | `json_agg(json_build_object(...))` gegen `pg_stat_user_tables` |

### 6.4  JSON-Funktionen — Syntax-Mapping

| MySQL | PostgreSQL |
|---|---|
| `JSON_ARRAYAGG(...)` | `json_agg(...)` |
| `JSON_OBJECT('key', val, ...)` | `json_build_object('key', val, ...)` |
| `ROW_NUMBER() OVER (ORDER BY ...)` | `ROW_NUMBER() OVER (ORDER BY ...)` (identisch) |
| `LEFT(text, n)` | `LEFT(text, n)` (identisch) |
| `ROUND(val, n)` | `ROUND(val::numeric, n)` (expliziter Cast nötig) |
| `CONCAT(a, b, c)` | `a || b || c` oder `CONCAT(a, b, c)` (seit PG 9.1) |

---

## 7  Erforderliche Änderungen bei Migration

### 7.1  compose.yaml — Kompletter Service-Umbau

**Test-DB (`mysql` → `postgres`, Zeile 75–99):**

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
  environment:
    MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    MYSQL_DATABASE: ${MYSQL_DATABASE}
    MYSQL_USER: ${MYSQL_USER}
    MYSQL_PASSWORD: ${MYSQL_PASSWORD}
  healthcheck:
    test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]

# Neu (PostgreSQL 18):
postgres:
  image: docker.io/library/postgres:18
  command: >
    -c shared_preload_libraries='pg_stat_statements'
    -c pg_stat_statements.track=all
  environment:
    POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    POSTGRES_USER: ${POSTGRES_USER:-webtrees}
    POSTGRES_DB: ${POSTGRES_DB:-webtrees_test}
  volumes:
    - postgres-data:/var/lib/postgresql/data
    - ./scripts/init-postgres.sql:/docker-entrypoint-initdb.d/01-extensions.sql:ro,z
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U $$POSTGRES_USER -d $$POSTGRES_DB"]
    interval: 5s
    timeout: 3s
    retries: 10
  ports:
    - "5432:5432"
```

**Init-Skript (`scripts/init-postgres.sql`):**

```sql
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
```

**Security-Track-DB (`mysql-security` → `postgres-security`, Zeile 180–199):** Analog.

### 7.2  Environment-Variablen — Umfassende Umbenennung

| Alt (MySQL) | Neu (PostgreSQL) | Betroffene Dateien |
|---|---|---|
| `MYSQL_HOST` | `POSTGRES_HOST` (oder `PGHOST`) | `compose.yaml`, `.env`, alle Test-Konfigurationen |
| `MYSQL_PORT` | `POSTGRES_PORT` (oder `PGPORT`) | `compose.yaml`, `.env`, PHPUnit-XML |
| `MYSQL_DATABASE` | `POSTGRES_DB` | `compose.yaml`, `.env`, Skripte |
| `MYSQL_USER` | `POSTGRES_USER` | `compose.yaml`, `.env`, Skripte |
| `MYSQL_PASSWORD` | `POSTGRES_PASSWORD` | `compose.yaml`, `.env`, Skripte |
| `MYSQL_ROOT_PASSWORD` | `POSTGRES_PASSWORD` (kein separates Root) | `compose.yaml`, `.env`, PerfSchema-Skripte |

**Hinweis:** PostgreSQL hat kein separates Root-Passwort-Konzept. `POSTGRES_USER` ist
der Superuser (Default: `postgres`).

### 7.3  Containerfile.webtrees — PHP-Extensions und System-Pakete

```dockerfile
# Alt:
    default-mysql-client \
    ...
    pdo_mysql \

# Neu:
    libpq-dev \
    postgresql-client \
    ...
    pdo_pgsql \
```

`pdo_pgsql` erfordert `libpq-dev` als Build-Dependency. `postgresql-client` ersetzt
`default-mysql-client` für CLI-Zugriff (`psql` statt `mysql`).

### 7.4  scripts/setup-webtrees.sh — Mehrfache Anpassungen

| Zeile(n) | Alt | Neu | Anmerkung |
|---|---|---|---|
| 12–15 | `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER` | PostgreSQL-Variablen | Variable-Namen |
| 95–113 | PDO-Connectivity-Check mit `mysql:host=...` DSN | `pgsql:host=... port=... dbname=...` DSN | Anderer PDO-Treiber |
| 119–131 | `config.ini.php` mit `dbtype = "mysql"`, Port 3306 | `dbtype = "pgsql"`, Port 5432 | Treiber + Port |

### 7.5  layer3-integration/tests/MysqlTestCase.php — Treiber-Umbau

| Zeile | Alt | Neu | Anmerkung |
|---|---|---|---|
| 112–124 | `DB::connect(driver: DB::MYSQL, ...)` | `DB::connect(driver: DB::POSTGRESQL, ...)` | Treiberkonstante |
| 106–110 | `getenv('MYSQL_*')` | `getenv('POSTGRES_*')` | Env-Variablen |

**Zusätzlich:** Klasse umbenennen zu `PostgresTestCase.php` oder `DatabaseTestCase.php`.
PHPUnit-XML-Konfiguration anpassen.

### 7.6  layer4-e2e/helpers/perfschema-fixture.ts — Kompletter Rewrite

Siehe Abschnitt 6.3.

### 7.7  scripts/extract-perfschema.sh — Kompletter Rewrite

Siehe Abschnitt 6.1.

### 7.8  scripts/truncate-perfschema.sh — Rewrite

Siehe Abschnitt 6.2.

### 7.9  Makefile — Targets anpassen

| Target | Änderung |
|---|---|
| `mysql-shell` | Umbenennen zu `db-shell`, `mysql` CLI → `psql` CLI |
| `db-dump` | `mysqldump` → `pg_dump` |
| `perfschema-truncate` | Skript-Aufruf anpassen |
| `perfschema-extract` | Skript-Aufruf anpassen |

### 7.10  .env / .env.example — Variablen ersetzen

Alle `MYSQL_*`-Variablen durch `POSTGRES_*`-Variablen ersetzen.

### 7.11  Dokumentation aktualisieren

| Datei | Änderung |
|---|---|
| `README.md` | MySQL-Referenzen durch PostgreSQL ersetzen |
| `CLAUDE.md` | Stack-Beschreibung, Diagnose-Befehle, `make`-Targets |
| `docs/tp_infrastructure_spec.md` | Image-Tag, Versionsnummer, PerfSchema → pg_stat |
| `docs/security-audit/01_scope_and_tracks.md` | Versionsnummer |
| `docs/security-audit/03_infrastructure_usage.md` | Datenbank-Konfiguration |
| `docs/systemtest_perf_improve_plan.md` | PerfSchema-Analyse → pg_stat-Analyse |

---

## 8  Vergleichsmatrix: MySQL 8.4 LTS vs. PostgreSQL 18

### 8.1  Performance-Monitoring

| Feature | MySQL 8.4 LTS | PostgreSQL 18 | Gewinner |
|---|---|---|---|
| Performance Schema (eingebaut) | ✅ ~126 Tabellen | ❌ Nicht vorhanden | MySQL |
| Statement-Digests | ✅ P_S eingebaut | ✅ pg_stat_statements (Extension) | Gleichstand |
| `QUANTILE_95` / `QUANTILE_99` | ✅ Nativ | ❌ Nur Approximation | MySQL |
| `QUERY_SAMPLE_TEXT` | ✅ | ✅ (`query` in pg_stat_statements) | Gleichstand |
| Table-I/O mit Zeitmessung | ✅ `SUM_TIMER_WAIT` | ❌ Nur Zähler | MySQL |
| Kumulative Stage-Timer | ✅ | ❌ Nur Realtime | MySQL |
| Kumulative Transaktions-Timer | ✅ | ❌ Nur Zähler | MySQL |
| I/O nach Backend-Typ | ❌ | ✅ `pg_stat_io` | **PostgreSQL** |
| Planungszeit separat | ❌ | ✅ `total_plan_time` | **PostgreSQL** |
| Buffer-Cache pro Query | ❌ | ✅ `shared_blks_hit/read` | **PostgreSQL** |
| Checkpoint-Monitoring | Begrenzt | ✅ `pg_stat_checkpointer` | **PostgreSQL** |
| WAL-Monitoring | ❌ | ✅ `pg_stat_wal` | **PostgreSQL** |
| Granulares Statistik-Reset | ✅ `TRUNCATE TABLE` pro P_S-Tabelle | ⚠️ `pg_stat_reset()` global | MySQL |

### 8.2  OpenTelemetry

| Feature | MySQL 8.4 Community | MySQL Enterprise | PostgreSQL 18 Community |
|---|---|---|---|
| Native OTel-Traces | ❌ | ✅ `component_telemetry` | ⚠️ pg_tracing (experimentell, kein PG 18) |
| Native OTel-Metriken | ❌ | ✅ 300+ Metriken | ❌ |
| Trace-Context-Propagation | ❌ | ✅ (ab 8.3) | ⚠️ pg_tracing via SQLCommenter |
| Telemetry Logging API | ❌ (erst 9.6) | ✅ | ❌ |
| OTel Collector Receiver | ✅ mysqlreceiver | ✅ | ✅ postgresqlreceiver |
| Client-seitige PDO-Instrumentation | ✅ auto-pdo | ✅ | ✅ auto-pdo |
| End-to-End DB-Tracing (OSS) | ❌ | ✅ | ⚠️ pg_tracing (experimentell) |

### 8.3  Sonstige Aspekte

| Aspekt | MySQL 8.4 LTS | PostgreSQL 18 |
|---|---|---|
| Lizenz | GPL-2.0 (+ proprietäre Enterprise) | PostgreSQL-Lizenz (permissiv, vollständig Open Source) |
| Support-Dauer | Bis April 2032 (8 Jahre) | Bis ~November 2030 (5 Jahre) |
| Container-Image-Größe | ~550 MB | ~400 MB |
| LTS-Tag verfügbar | ✅ `:lts` | ❌ Kein LTS-Konzept |
| webtrees-Kompatibilität | Offiziell, primäre Testplattform | Offiziell unterstützt, aber nicht primär getestet |
| PHP-PDO-Treiber | `pdo_mysql` (mysqlnd) | `pdo_pgsql` (libpq) |
| Collation | `utf8mb4_bin` | `und-x-icu` |
| Full-Text-Search | InnoDB FTS | tsvector/tsquery (leistungsfähiger) |
| JSON-Support | `JSON`-Typ + Funktionen | `jsonb`-Typ + Operatoren (leistungsfähiger) |
| Window-Funktionen | ✅ (seit 8.0) | ✅ (seit 8.4, ausgereifter) |
| CTEs (Common Table Expressions) | ✅ (seit 8.0) | ✅ (ausgereifter, CTE-Materialisierungskontrolle) |
| Partitionierung | ✅ | ✅ (deklarative Syntax, flexibler) |
| Extensions-Ökosystem | Begrenzt (Plugins) | ⬆ Sehr umfangreich (pg_stat_monitor, pg_tracing, pgaudit, PostGIS, ...) |

---

## 9  Empfehlung

| Option | Pro | Contra |
|---|---|---|
| **A: Bei MySQL 8.4 LTS bleiben** | Kein Aufwand, P_S vollständig (inkl. Perzentile), 8 Jahre Support, eingespielte Toolchain | Kein server-seitiges OTel-Tracing (Community) |
| **B: Auf PostgreSQL 18 migrieren** | Besseres OTel-Potenzial (pg_tracing), permissive Lizenz, stärkere Extensions, pg_stat_io, Planungszeit-Separation | **Massiver Migrationsaufwand**, P_S-Feature-Verlust (Perzentile, Timer, Stage-Monitoring), pg_tracing noch nicht PG-18-kompatibel, kürzerer Support |
| **C: PostgreSQL als zusätzliche Test-DB** | Dual-DB-Kompatibilitätstests, OTel-Features parallel evaluierbar, webtrees-PostgreSQL-Pfad absichern | Doppelter Wartungsaufwand, PerfSchema-Skripte duplizieren |

### Empfehlung: Option A — bei MySQL 8.4 LTS bleiben

**Begründung:**

1. **Der Migrationsaufwand ist unverhältnismäßig hoch.** Anders als bei MariaDB
   (Image-Tag-Wechsel + SQL-Anpassungen) erfordert PostgreSQL einen **Komplettumbau**:
   neue PHP-Extension, neues CLI-Tool, neue Environment-Variablen, komplett
   umgeschriebene PerfSchema-Skripte, neue Containerfiles, angepasste Testinfrastruktur.
   Mindestens 15 Dateien müssen substanziell geändert werden.

2. **Performance Schema bleibt MySQL überlegen für den Stack-Anwendungsfall.**
   Die nativen Perzentile (`QUANTILE_95`/`QUANTILE_99`), die Timer pro Tabelle
   (`SUM_TIMER_WAIT`) und die kumulativen Stage-/Transaktions-Timer haben in
   PostgreSQL kein exaktes Äquivalent. Die PostgreSQL-Alternativen (`pg_stat_monitor`-
   Histogramme, `pg_stat_io`) bieten andere Stärken, decken aber nicht das bestehende
   Extraktionsprofil ab.

3. **pg_tracing ist der einzige klare OTel-Vorteil — und noch nicht nutzbar.**
   Die Extension unterstützt PostgreSQL 18 noch nicht und befindet sich in früher
   Entwicklung. Der MySQL-Stack nutzt ohnehin kein server-seitiges OTel. Der
   theoretische Gewinn (End-to-End-DB-Tracing) ist nicht realisierbar.

4. **webtrees wird primär mit MySQL entwickelt und getestet.** Der PostgreSQL-
   Codepfad in webtrees ist weniger intensiv getestet als der MySQL-Pfad.
   Testinfrastruktur, die auf PostgreSQL basiert, testet einen sekundären
   Codepfad — für das Upstream-Tracking ist MySQL der relevantere Pfad.

5. **Der Support-Zeitraum ist kürzer.** MySQL 8.4 LTS läuft bis 2032,
   PostgreSQL 18 nur bis ~2030. Für eine langlebige Testinfrastruktur
   bietet MySQL mehr Planungssicherheit.

### Szenario für eine Neubewertung

Ein Wechsel zu PostgreSQL würde sinnvoll, wenn:

- **pg_tracing PostgreSQL 18+ unterstützt und als stabil gilt** — dann wäre
  End-to-End-Distributed-Tracing vom Browser bis in die DB ein echter Gewinn
  gegenüber MySQL Community
- **webtrees-Upstream PostgreSQL als gleichwertiges Testziel adoptiert** — dann
  wäre PostgreSQL-Testinfrastruktur für den Upstream-Beitrag relevanter
- **MySQL Community weiter eingeschränkt wird** (z. B. Performance Schema
  in Enterprise verschoben) — dann entfiele der Hauptvorteil von MySQL
- **Der Stack eine Dual-Database-Konfiguration erhält** — PostgreSQL als
  **zusätzliche** Test-DB neben MySQL, um den webtrees-PostgreSQL-Pfad
  abzusichern. Dies wäre die wahrscheinlichste sinnvolle Erweiterung:
  ein separates `compose-postgres.yaml` mit eigenem PerfSchema-Äquivalent,
  ohne den MySQL-Stack zu ersetzen

### Vorbereitende Schritte (optional, ohne Migration)

1. **OTel Collector PostgreSQL Receiver evaluieren:** Die bestehende
   `otel-collector-config.yaml` um den `postgresqlreceiver` erweitern — in einem
   Dual-DB-Szenario wären dann Metriken beider Datenbanken im Collector verfügbar.

2. **pg_tracing beobachten:** GitHub-Repository (`DataDog/pg_tracing`) auf
   PostgreSQL-18-Support und Stabilitäts-Releases watchen. Sobald PG 18
   unterstützt wird, einen Spike mit SQLCommenter-basierter Trace-Propagation
   durchführen.

3. **pg_stat_monitor testen:** In einem lokalen PostgreSQL-Container die
   Histogramm-Funktion evaluieren und mit den MySQL-`QUANTILE_*`-Werten
   vergleichen. Gibt Klarheit über die Qualität der Perzentil-Approximation.
