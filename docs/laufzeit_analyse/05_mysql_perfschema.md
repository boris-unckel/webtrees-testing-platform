<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# A5: MySQL Performance Schema — Auswertung und OTel-Korrelation — Analyse

## 1. Fakten

### 2.6.1 Performance Schema im Container-Kontext

#### Performance Schema: Default-Status in `mysql:8.4` (mysql:lts)

**Performance Schema ist standardmäßig aktiviert.** Das gilt für alle MySQL-Installationen seit MySQL 5.6.6, einschließlich der offiziellen Docker-Images auf Docker Hub (`docker.io/library/mysql:lts` = MySQL 8.4.x).

Verifizierung im laufenden Container:

```sql
SHOW VARIABLES LIKE 'performance_schema';
-- Erwartetes Ergebnis: ON
```

**Kein `--performance-schema=ON` in `compose.yaml` nötig.** Das Default reicht aus.

#### Speicherverbrauch und Overhead

**Memory-Modell: Dynamisch und autoskalierend.**

| Eigenschaft | Verhalten |
|---|---|
| Initiale Allokation | Minimal (Puffer starten leer) |
| Wachstum | Dynamisch, proportional zur tatsächlichen Last |
| Obergrenze | Unbegrenzt bei Default-Konfiguration (`-1` = autoscaled) |
| Freigabe | Nie während des Betriebs, nur bei Shutdown |
| Recycling | Ja (freigegebene Einträge werden wiederverwendet) |

**Memory-Monitoring** im laufenden Container:

```sql
SHOW ENGINE PERFORMANCE_SCHEMA STATUS;
-- Letzte Zeile: performance_schema.memory = Gesamtverbrauch in Bytes
```

**Erwarteter Speicherverbrauch für den Testkontext:** 5–20 MB (1–5 Connections, wenige Hundert Queries, ~30 Tabellen). Bei Container-Limits von 256–512 MB RAM vernachlässigbar.

**Observer Effect:**

- Statement-Instrumentierung (Default ON): < 5% Overhead
- Wait-Instrumentierung (Default OFF): 5–15% Overhead bei voller Aktivierung
- Stage-Instrumentierung (Default OFF): 5–10% Overhead

Für den Testkontext ist der Statement-Overhead akzeptabel, da er für alle Testläufe konstant ist.

#### Relevante Performance Schema Tabellen

##### Default aktiviert (keine Konfigurationsänderung nötig)

| Tabelle | Inhalt | Default-Instruments | Default-Consumer |
|---|---|---|---|
| `events_statements_summary_by_digest` | Aggregierte SQL-Profile | `statement/%` = **YES** | `statements_digest` = **YES** |
| `events_statements_summary_global_by_event_name` | Statement-Typen global | `statement/%` = **YES** | `events_statements_current` = **YES** |
| `events_statements_current` | Aktuell laufende Statements | `statement/%` = **YES** | `events_statements_current` = **YES** |
| `events_statements_history` | Letzte 10 Statements pro Thread | `statement/%` = **YES** | `events_statements_history` = **YES** |
| `events_transactions_summary_global_by_event_name` | Transaktions-Aggregate | `transaction` = **YES** | `events_transactions_current` = **YES** |
| `table_io_waits_summary_by_table` | I/O-Wartezeiten pro Tabelle | `wait/io/table/sql/handler` = **YES** (zu verifizieren) | `global_instrumentation` = **YES** |
| `file_summary_by_instance` | Datei-I/O pro Datei | `wait/io/file/%` = teilweise ON | `global_instrumentation` = **YES** |
| `threads` | Thread-zu-Connection-Mapping | Immer aktiv | Immer aktiv |
| `session_connect_attrs` | Client-Connection-Attribute | Immer aktiv | Immer aktiv |

##### Erfordert explizite Aktivierung

| Tabelle | Inhalt | Zu aktivierendes Instrument | Zu aktivierender Consumer |
|---|---|---|---|
| `events_stages_summary_global_by_event_name` | Query-Phasen (Parsing, Optimizing, Executing) | `stage/sql/%` = **NO** (Default) | `events_stages_current` = **NO** (Default) |
| `events_waits_summary_global_by_event_name` | I/O-Waits, Lock-Waits, Mutex-Waits | `wait/%` = **NO** (Default) | `events_waits_current` = **NO** (Default) |

#### Default-Konfiguration: Consumer

```
global_instrumentation            = YES
thread_instrumentation            = YES
events_waits_current              = NO
events_waits_history              = NO
events_waits_history_long         = NO
events_stages_current             = NO
events_stages_history             = NO
events_stages_history_long        = NO
events_statements_current         = YES
events_statements_history         = YES
events_statements_history_long    = NO
events_statements_cpu             = NO
events_transactions_current       = YES
events_transactions_history       = YES
events_transactions_history_long  = NO
statements_digest                 = YES
```

#### Default-Konfiguration: Instruments

| Instrument-Pattern | Default ENABLED | Default TIMED |
|---|---|---|
| `statement/sql/*` | YES | YES |
| `statement/com/*` | YES | YES |
| `transaction` | YES | YES |
| `stage/sql/*` | **NO** | **NO** |
| `wait/synch/mutex/*` | **NO** | **NO** |
| `wait/synch/rwlock/*` | **NO** | **NO** |
| `wait/io/file/*` | Teilweise YES | Teilweise YES |
| `wait/io/table/sql/handler` | **YES** (zu verifizieren) | **YES** |
| `memory/*` | YES | NULL (nicht zeitgemessen) |

#### Empfohlene Konfiguration für den Testkontext

**Minimale Erweiterung: Stages aktivieren, Waits deaktiviert lassen.**

```yaml
mysql:
  image: docker.io/library/mysql:lts
  command: >
    --character-set-server=utf8mb4
    --collation-server=utf8mb4_bin
    --performance-schema-instrument='stage/%=ON'
    --performance-schema-consumer-events-stages-current=ON
    --performance-schema-consumer-events-stages-history=ON
```

**Begründung gegen Wait-Instrumentierung:** Höchster Overhead (15%+), primär für DBA-Tuning relevant, nicht für Anwendungstests. Bei Bedarf per SQL zur Laufzeit aktivierbar.

#### Spalten der Schlüsseltabellen

##### `events_statements_summary_by_digest` — Primäre Datenquelle

| Spalte | Beschreibung | Einheit |
|---|---|---|
| `SCHEMA_NAME` | Datenbankname | String |
| `DIGEST` | SHA-256 Hash des normalisierten Statements | Hex-String |
| `DIGEST_TEXT` | Normalisiertes Statement (Parameter durch `?` ersetzt) | String |
| `COUNT_STAR` | Ausführungshäufigkeit | Integer |
| `SUM_TIMER_WAIT` | Gesamte Ausführungszeit | Picosekunden |
| `AVG_TIMER_WAIT` | Durchschnittliche Ausführungszeit | Picosekunden |
| `MAX_TIMER_WAIT` | Maximale Ausführungszeit | Picosekunden |
| `QUANTILE_95` | 95. Perzentil der Latenz | Picosekunden |
| `QUANTILE_99` | 99. Perzentil der Latenz | Picosekunden |
| `SUM_ROWS_EXAMINED` | Gesamte untersuchte Zeilen | Integer |
| `SUM_ROWS_SENT` | Gesamte zurückgegebene Zeilen | Integer |
| `SUM_SELECT_FULL_JOIN` | Full Joins (ohne Index) | Integer |
| `SUM_SELECT_SCAN` | Full Table Scans | Integer |
| `SUM_NO_INDEX_USED` | Queries ohne Index | Integer |
| `SUM_CREATED_TMP_DISK_TABLES` | Temp-Tabellen auf Disk | Integer |
| `SUM_LOCK_TIME` | Gesamte Lock-Wartezeit | Picosekunden |
| `QUERY_SAMPLE_TEXT` | Beispiel-SQL (max 1024 Bytes) | String |
| `FIRST_SEEN` / `LAST_SEEN` | Zeitbezug | Timestamp |

**Einheitenumrechnung:** 1 ms = 1.000.000.000 Picosekunden. Division durch 1e9 für ms.

##### `table_io_waits_summary_by_table`

Kompakte Tabelle (~30 Zeilen für webtrees). Spalten: `OBJECT_NAME`, `COUNT_STAR/READ/WRITE/FETCH/INSERT/UPDATE/DELETE` mit jeweiligen Timer-Spalten (Picosekunden).

---

### 2.6.2 Korrelation Performance Schema ↔ OpenTelemetry

#### TRACE_ID in Performance Schema: Existiert NICHT in Community Edition

**Entscheidendes Ergebnis:** Die Tabelle `events_statements_current` in MySQL 8.4 Community Edition hat **keine** Spalte `TRACE_ID` oder `SPAN_ID`. Die `threads`-Tabelle enthält `TELEMETRY_ACTIVE`, aber das ist Enterprise-only.

**Kein TRACE_ID, kein SPAN_ID, kein TRACEPARENT.**

#### Thread-basierte Korrelation: Der machbare Weg

**Ansatz: `PROCESSLIST_ID` (= Connection ID) als Korrelationsschlüssel.**

```sql
SELECT THREAD_ID, PROCESSLIST_ID, PROCESSLIST_USER, PROCESSLIST_HOST
FROM performance_schema.threads
WHERE TYPE = 'FOREGROUND' AND PROCESSLIST_USER = 'webtrees';
```

PHP-seitig: `$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()`

**`opentelemetry-auto-pdo` setzt KEINEN `db.connection_id` als Span-Attribut.** Müsste durch eigene Erweiterung hinzugefügt werden (niedrige Priorität).

#### Indirekte Korrelation statt direkter Trace-ID-Propagation

Die Korrelation erfolgt auf **Aggregatebene**:

```
PHP-Seite (OTel):                    MySQL-Seite (PerfSchema):
+---------------------------+        +----------------------------------+
| Span: PDO::query()        |        | events_statements_summary_by_    |
|   db.statement = "SELECT  |  <-->  |   digest:                        |
|     ... FROM wt_individ..." |      |   DIGEST_TEXT = "SELECT ...      |
|   duration = 4.2ms        |        |     FROM `wt_individuals` ..."   |
|   trace_id = abc123       |        |   AVG_TIMER_WAIT = 4200000000   |
+---------------------------+        +----------------------------------+

Korrelation: Gleicher normalisierter Query-Text
Mehrwert MySQL-Seite: Rows Examined, Temp Tables, Lock Time,
                      Full Scans, Quantile (p95/p99)
```

---

### 2.6.3 Daten-Extraktion am Ende des Testlaufs

#### Bewertung der Extraktionsmethoden

| Option | Methode | Machbar? | Aufwand | Vorteile | Nachteile |
|---|---|---|---|---|---|
| **A** | `mysqldump` auf `performance_schema` | **Nein** | — | — | Performance Schema ist nicht dumpbar |
| **B** | SQL via `podman-compose exec mysql mysql -e "..."` | **Ja** | Gering | Einfach, kein PHP nötig | Output-Formatierung limitiert |
| **C** | PHP-Skript im webtrees-Container via PDO | **Ja** | Mittel | Sauberes JSON | Erfordert separates Skript |
| **D** | Dediziertes Bash-Skript (mysql-Client) | **Ja** | Gering | Kein PHP-Overhead | JSON nur über SQL-Konstruktion |

#### Empfohlener Ansatz: Option B — SQL via mysql-Client mit JSON-Konstruktion

**Begründung:**
1. Konsistenz mit bestehendem `db-dump`-Pattern
2. Kein PHP-Overhead — Extraktion nach dem Testlauf
3. JSON-Format über SQL-seitige `JSON_ARRAYAGG(JSON_OBJECT(...))`
4. Integration in `run.sh` oder als separates Skript

**Konkreter Mechanismus:**

```bash
mysql -h "${MYSQL_HOST:-mysql}" -u root -p"${MYSQL_ROOT_PASSWORD:-webtrees_test}" \
  --batch --raw --skip-column-names -e "
SELECT JSON_ARRAYAGG(JSON_OBJECT(
  'schema', SCHEMA_NAME,
  'digest', DIGEST,
  'digest_text', DIGEST_TEXT,
  'count', COUNT_STAR,
  'total_ms', ROUND(SUM_TIMER_WAIT/1000000000, 2),
  'avg_ms', ROUND(AVG_TIMER_WAIT/1000000000, 2),
  'max_ms', ROUND(MAX_TIMER_WAIT/1000000000, 2),
  'p95_ms', ROUND(QUANTILE_95/1000000000, 2),
  'p99_ms', ROUND(QUANTILE_99/1000000000, 2),
  'rows_examined', SUM_ROWS_EXAMINED,
  'rows_sent', SUM_ROWS_SENT,
  'full_scans', SUM_SELECT_SCAN,
  'no_index', SUM_NO_INDEX_USED,
  'tmp_disk_tables', SUM_CREATED_TMP_DISK_TABLES,
  'lock_time_ms', ROUND(SUM_LOCK_TIME/1000000000, 2),
  'sample_text', LEFT(QUERY_SAMPLE_TEXT, 500),
  'first_seen', FIRST_SEEN,
  'last_seen', LAST_SEEN
))
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = '${MYSQL_DATABASE:-webtrees_test}'
  AND DIGEST_TEXT IS NOT NULL
ORDER BY SUM_TIMER_WAIT DESC
LIMIT 50
" > "${PERFSCHEMA_DIR}/statements_by_digest.json"
```

#### Timing und Datentrennung

**Empfehlung: TRUNCATE vor jedem Testlauf (Clean-Slate).**

```sql
TRUNCATE TABLE performance_schema.events_statements_summary_by_digest;
TRUNCATE TABLE performance_schema.events_stages_summary_global_by_event_name;
TRUNCATE TABLE performance_schema.table_io_waits_summary_by_table;
TRUNCATE TABLE performance_schema.events_transactions_summary_global_by_event_name;
```

**Begründung gegen Snapshot-Differenz:** Mehr Komplexität, TRUNCATE ist atomar und garantiert saubere Testlauf-Daten.

**Sequenz im run.sh:**

```
1. TRUNCATE Performance Schema Tabellen    ← Neu
2. PHPUnit / Playwright Testlauf           ← Bestehend
3. Extrahiere Performance Schema Daten     ← Neu
4. Generiere Summary                       ← Neu
5. Exit mit Testlauf-Exit-Code             ← Bestehend
```

**Wichtig:** TRUNCATE erfordert Root-Zugriff. Der `webtrees`-User hat keine TRUNCATE-Rechte auf `performance_schema`.

#### Wo läuft die Extraktion?

**Empfehlung: Dediziertes Skript `scripts/extract-perfschema.sh`**, das über `podman-compose exec mysql mysql -e "..."` arbeitet — kein Volume-Mount nötig.

---

### 2.6.4 Artefaktstruktur und Ausgabeformat

#### Verzeichnisstruktur

```
artifacts/
├── layer3/
│   └── perfschema/
│       ├── statements_by_digest.json    — Top-50 Queries nach Gesamtzeit
│       ├── table_io_waits.json          — I/O pro Tabelle
│       ├── stages_global.json           — Query-Phasen (wenn aktiviert)
│       ├── transactions_global.json     — Transaktions-Statistiken
│       └── summary.txt                  — Menschenlesbare Zusammenfassung
├── layer4/
│   └── perfschema/
│       └── ...
└── layer5/
    └── perfschema/
        └── ...
```

#### JSON-Format: Flach (Array of Objects)

```json
[
  {
    "schema": "webtrees_test",
    "digest": "abc123...",
    "digest_text": "SELECT ... FROM `wt_individuals` WHERE ...",
    "count": 142,
    "total_ms": 1234.56,
    "avg_ms": 8.69,
    "max_ms": 45.23,
    "p95_ms": 22.10,
    "p99_ms": 38.50,
    "rows_examined": 28400,
    "rows_sent": 142,
    "full_scans": 0,
    "no_index": 0,
    "tmp_disk_tables": 0,
    "lock_time_ms": 12.34,
    "sample_text": "SELECT `i_id`, `i_gedcom` FROM `wt_individuals` WHERE ...",
    "first_seen": "2026-03-29 14:22:01",
    "last_seen": "2026-03-29 14:25:47"
  }
]
```

#### Summary-Format (`summary.txt`)

```
=== Performance Schema Summary (Layer 3 — Integration) ===
Zeitraum: 2026-03-29 14:22:01 — 2026-03-29 14:25:47
Gesamt Statements: 4823
Gesamt Ausführungszeit: 12.45s
Unique Digests: 87

--- Top 10 Queries (nach Gesamtzeit) ---
 #  | Count |  Total  |   Avg   |  p95   | FullScan | Digest
  1 |   142 | 1234ms  |  8.7ms  | 22.1ms |    0     | SELECT ... FROM wt_individuals ...
  2 |   891 |  987ms  |  1.1ms  |  3.2ms |    0     | SELECT ... FROM wt_name ...
  ...

--- Top 5 Tabellen (nach I/O-Gesamtzeit) ---
 # | Table              | Reads  | Writes | Total I/O
 1 | wt_individuals     |  28400 |    142 | 2345ms
 2 | wt_name            |  15200 |     89 |  987ms
 ...

--- Warnungen ---
- 3 Queries mit Full Table Scan (SUM_SELECT_SCAN > 0)
- 1 Query mit Temp-Tabelle auf Disk (SUM_CREATED_TMP_DISK_TABLES > 0)
- 0 Queries ohne Index (SUM_NO_INDEX_USED > 0)
```

---

### 2.6.5 Baseline-Vergleich und Regression

**Empfehlung: Ja, aber nur für `statements_by_digest` und nur relative Schwellwerte.**

Absolute Schwellwerte sind in containerisierten Testumgebungen unzuverlässig. Relative Schwellwerte sind robuster.

**Sinnvolle Baseline-Metriken:**

| Metrik | Baseline-Typ | Schwellwert | Begründung |
|---|---|---|---|
| `COUNT_STAR` pro Digest | Absolut | +20% Toleranz | N+1-Query-Regression |
| `SUM_SELECT_SCAN` pro Digest | Absolut | 0 (Null-Toleranz für neue Full Scans) | Performance-Killer |
| `SUM_NO_INDEX_USED` pro Digest | Absolut | 0 | Neue Queries ohne Index = Regression |
| `SUM_CREATED_TMP_DISK_TABLES` | Absolut | 0 (Warnung) | Fehlende Indizes |
| `AVG_TIMER_WAIT` pro Digest | Relativ | +50% | Latenz-Regression |
| Rows Examined / Rows Sent Ratio | Relativ | +100% | Query-Effizienz-Verschlechterung |

**Baseline-Speicherort:** `layer5-performance/baselines/perfschema/`

**Phase 1 (Quick Win):** Nur Extraktion und manuelle Inspektion, kein automatischer Vergleich.

---

### 2.6.6 Makefile-Integration

#### Empfehlung: Separater Post-Step

**Begründung:**
1. Trennung von Test und Messung (Export-Fehler verfälscht nicht den Exit-Code)
2. Optionalität (nicht jeder Testlauf braucht PerfSchema-Daten)
3. Wiederholbarkeit (Export nachträglich möglich)

**Vorgeschlagene Targets:**

```makefile
perfschema-extract: ## Performance Schema Daten extrahieren
    scripts/extract-perfschema.sh layer3

perfschema-report: perfschema-extract ## PerfSchema Report generieren
    @cat artifacts/layer3/perfschema/summary.txt 2>/dev/null || echo "Kein Report"
```

**Optionale Integration in Test-Targets (später):**

```makefile
test-integration:
    $(COMPOSE) exec webtrees /bin/bash /tests/layer3-integration/run.sh
    -scripts/extract-perfschema.sh layer3    # '-' ignoriert Fehler
```

---

## 2. Bewertung

### Machbarkeit

| Vorhaben | Machbar? | Aufwand | Risiko |
|---|---|---|---|
| PerfSchema-Daten extrahieren (Digest, Table I/O) | **Ja** | Gering (~80 Zeilen Bash) | Niedrig |
| Stages-Instrumentierung aktivieren | **Ja** | Minimal (3 Zeilen compose.yaml) | Niedrig (5–10% Overhead) |
| Wait-Instrumentierung aktivieren | **Ja, aber nicht empfohlen** | Minimal | Mittel (15%+ Overhead) |
| Direkte Trace-ID-Korrelation | **Nein** | — | Enterprise-only |
| Indirekte Korrelation (Digest-Text) | **Ja** | Mittel | Niedrig |
| Thread-basierte Korrelation (Connection ID) | **Ja, aber aufwändig** | Hoch | Mittel |
| Baseline-Vergleich | **Ja** | Mittel | Mittel (False Positives) |

### Risiken

1. **Root-Zugriff nötig:** TRUNCATE auf `performance_schema` erfordert Root. Root-Passwort ist in `compose.yaml` als Umgebungsvariable — im Testkontext akzeptabel.

2. **Picosekunden-Einheiten:** Ohne korrekte Umrechnung entstehen uninterpretierbare Zahlenwerte. Extraktionsskript muss konsistent umrechnen.

3. **Container-Neustart:** Performance Schema-Daten sind in-memory. Nach `make down && make up` verloren. Extraktion muss vor Stack-Shutdown erfolgen.

---

## 3. Empfehlung

### Phase 1: Minimale Extraktion (Quick Win)

**Aufwand: ~2 Stunden**

1. **compose.yaml:** MySQL-Image auf `mysql:lts` ändern (Prerequisite aus A4), Stage-Instruments aktivieren
2. **Neues Skript `scripts/extract-perfschema.sh`:** Extrahiert Digest + Table I/O als JSON
3. **Neues Skript `scripts/truncate-perfschema.sh`:** Reset vor Testlauf
4. **Makefile:** Neues Target `perfschema-report`

### Phase 2: Erweitertes Reporting

**Aufwand: ~4 Stunden**

1. Summary-Generierung im Extraktionsskript
2. Konsolenausgabe Top-10-Queries
3. Warnungen für Full Table Scans, No-Index-Queries

### Phase 3: Baseline-Vergleich (abhängig von A8)

**Aufwand: ~8 Stunden**

1. Baseline-Format und initialen Baseline generieren
2. Vergleichsskript mit konfigurierbaren Schwellwerten
3. CI-Gate-Integration

---

## 4. Offene Punkte

### Vor Implementierung zu klären

1. **Root-Passwort als Variable:** Umgebungsvariable `${MYSQL_ROOT_PASSWORD:-webtrees_test}` — konsistent mit compose.yaml.

2. **`wait/io/table/sql/handler` Default-Status:** Im laufenden Container verifizieren:
   ```sql
   SELECT NAME, ENABLED, TIMED FROM performance_schema.setup_instruments
   WHERE NAME = 'wait/io/table/sql/handler';
   ```

3. **Artefakt-Volume:** Extraktion über Stdout-Redirect von `podman-compose exec` (kein zusätzlicher Volume-Mount).

4. **Layer-Parameter:** `scripts/extract-perfschema.sh layer3` — CLI-Argument bestimmt Zielverzeichnis.

5. **TRUNCATE-Zeitpunkt:** Separates Skript `scripts/truncate-perfschema.sh`, aufgerufen vor dem Testlauf.

6. **Security-Track:** `mysql-security`-Service nutzt separate MySQL-Instanz. Extraktionsskript muss Container-Namen als Parameter akzeptieren.

### Nicht weiter zu verfolgen

- **Direkte Trace-ID-Propagation PHP→MySQL:** Nicht möglich in Community Edition.
- **Custom Connection Attributes via PDO:** PHP/mysqlnd bietet keine API dafür.
- **mysqld_exporter (Prometheus):** Überflüssig — SQL-Zugriff auf Performance Schema liefert dieselben Daten.

---

## Quellen

- MySQL 8.4 Reference Manual, Performance Schema: https://dev.mysql.com/doc/refman/8.4/en/performance-schema.html
- MySQL 8.4 Reference Manual, Statement Summary Tables: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-statement-summary-tables.html
- MySQL 8.4 Reference Manual, Stage Summary Tables: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-stage-summary-tables.html
- MySQL 8.4 Reference Manual, Table Wait Summary Tables: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-table-wait-summary-tables.html
- MySQL 8.4 Reference Manual, Consumer Configurations: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-consumer-configurations.html
- MySQL 8.4 Reference Manual, Runtime Configuration: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-runtime-configuration.html
- A4-Analyse: `docs/laufzeit_analyse/04_mysql_telemetry.md`
