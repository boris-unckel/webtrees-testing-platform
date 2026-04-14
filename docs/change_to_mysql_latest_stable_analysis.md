<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Analyse: Migration von MySQL 8.4 LTS auf MySQL Latest Stable (9.x)

**Datum:** 2026-04-13
**Status:** Analyse abgeschlossen — Empfehlung: abwarten bis MySQL 9.7 LTS

---

## 1  Ist-Zustand

Der Stack verwendet `docker.io/library/mysql:lts`, was aktuell auf **MySQL 8.4.x LTS**
auflöst (Support bis April 2032).

### 1.1  Image-Tag-Verdrahtung

| Datei | Zeile(n) | Wert | Kontext |
|---|---|---|---|
| `compose.yaml` | 76 | `docker.io/library/mysql:lts` | Test-DB (`mysql`) |
| `compose.yaml` | 181 | `docker.io/library/mysql:lts` | Security-Track-DB (`mysql-security`) |

Weitere Dateien referenzieren die Version **nur dokumentarisch** (kein Steuereffekt):

| Datei | Zeile(n) | Inhalt |
|---|---|---|
| `README.md` | 66 | `MySQL LTS (8.4)` |
| `docs/tp_infrastructure_spec.md` | 424, 430 | `mysql:lts — MySQL LTS 8.4` |
| `docs/security-audit/01_scope_and_tracks.md` | 17 | `MySQL LTS 8.4` |
| `docs/security-audit/03_infrastructure_usage.md` | 71 | `MySQL 8.4 mit --performance-schema-instrument` |
| `docs/systemtest_perf_improve_plan.md` | 243, 284, 434 | Versionsspezifische PerfSchema-Analyse (8.4) |

### 1.2  MySQL-Server-Konfiguration (compose.yaml)

```yaml
command: >
  --character-set-server=utf8mb4
  --collation-server=utf8mb4_bin
  --performance-schema-instrument='stage/%=ON'
  --performance-schema-consumer-events-stages-current=ON
  --performance-schema-consumer-events-stages-history=ON
```

- `utf8mb4` / `utf8mb4_bin` — unverändert in 9.x
- Performance-Schema-Flags — unverändert in 9.x

### 1.3  Authentifizierung

Kein `mysql_native_password` im gesamten Repo konfiguriert.
`caching_sha2_password` ist bereits der Default — 9.x-kompatibel.

---

## 2  Versionslage MySQL 9.x (Stand April 2026)

| Release | Tag (Docker Hub) | Support |
|---|---|---|
| 8.4.x LTS | `mysql:lts`, `mysql:8.4` | Bis April 2032 |
| 9.6.0 Innovation | `mysql:latest`, `mysql:9.6`, `mysql:9` | ~3 Monate (Innovation-Zyklus) |
| 9.7 LTS | noch nicht erschienen | Erwartet H2 2026 |

**`mysql:lts` bleibt auf 8.4.** Für 9.x gibt es noch keinen LTS-Tag.

---

## 3  SQL- und Performance-Schema-Abhängigkeiten

### 3.1  Performance-Schema-Queries

Die folgenden Dateien enthalten direkte SQL-Queries gegen `performance_schema`:

#### `scripts/truncate-perfschema.sh` (Zeilen 9–12)

```sql
TRUNCATE TABLE performance_schema.events_statements_summary_by_digest;
TRUNCATE TABLE performance_schema.events_stages_summary_global_by_event_name;
TRUNCATE TABLE performance_schema.table_io_waits_summary_by_table;
TRUNCATE TABLE performance_schema.events_transactions_summary_global_by_event_name;
```

**9.x-Kompatibilität:** ✅ Alle vier Tabellen existieren unverändert.

#### `scripts/extract-perfschema.sh` (Zeilen 22–144)

| Query | Genutzte Spalten / Features | 9.x-Status |
|---|---|---|
| Statement-Digest-JSON (Z. 22–48) | `JSON_ARRAYAGG`, `JSON_OBJECT`, `QUANTILE_95`, `QUANTILE_99`, `QUERY_SAMPLE_TEXT` | ✅ Unverändert |
| Table-I/O-JSON (Z. 52–67) | `COUNT_READ/WRITE/FETCH/INSERT/UPDATE/DELETE` | ✅ Unverändert |
| Stages-JSON (Z. 70–81) | `events_stages_summary_global_by_event_name` | ✅ Unverändert |
| Transactions-JSON (Z. 84–95) | `events_transactions_summary_global_by_event_name` | ✅ Unverändert |
| Top-10-Queries (Z. 103–115) | `ROW_NUMBER() OVER(...)` Window-Funktion | ✅ Unverändert |
| Top-5-Tables (Z. 118–130) | `ROW_NUMBER() OVER(...)` Window-Funktion | ✅ Unverändert |
| Warning-Counts (Z. 133–144) | `SUM_SELECT_SCAN`, `SUM_NO_INDEX_USED`, `SUM_CREATED_TMP_DISK_TABLES` | ✅ Unverändert |

#### `layer4-e2e/helpers/perfschema-fixture.ts` (Zeilen 28–67)

| Query | Features | 9.x-Status |
|---|---|---|
| Per-Test Statement-Digest (Z. 28–40) | `JSON_ARRAYAGG`, `performance_schema.events_statements_summary_by_digest` | ✅ Unverändert |
| Per-Test Table-I/O (Z. 43–56) | `JSON_ARRAYAGG`, `table_io_waits_summary_by_table` | ✅ Unverändert |
| Truncate (Z. 65–67) | `TRUNCATE TABLE` | ✅ Unverändert |

### 3.2  Anwendungs-SQL (webtrees-Core + Tests)

| Datei | SQL-Typ | 9.x-Status |
|---|---|---|
| `layer4-e2e/helpers/global-setup.ts` (Z. 28–30) | `DELETE … WHERE … LIKE` | ✅ Kein Problem |
| `scripts/setup-webtrees.sh` | PDO-DSN, DB-Builder (UPDATE, DELETE) | ✅ Kein Problem |
| `layer3-integration/tests/RelationshipDbTest.php` | JOIN, DISTINCT, COUNT | ✅ Kein Problem |
| `layer3-integration/tests/SearchIntegrationTest.php` | Bulk DELETE | ✅ Kein Problem |

### 3.3  MD5/SHA1 — Entwarnung

MySQL 9.6 entfernt `MD5()` und `SHA1()` als **SQL-Funktionen** (ohne `classic_hashing`-Komponente).
Webtrees nutzt `md5()` und `sha1()` ausschließlich als **PHP-Funktionen** (z. B.
`GedcomRecord.php:1003`, `Cache.php:55`, `MediaFile.php:79`). Kein SQL-seitiger Aufruf
gefunden — **kein Änderungsbedarf**.

### 3.4  Entfernte Storage-Engines

MySQL 9.0 entfernt MEMORY, ARCHIVE, BLACKHOLE, FEDERATED, MERGE.
Webtrees nutzt ausschließlich InnoDB. Keine `ENGINE=MEMORY`-Referenzen im Repo.
**Kein Änderungsbedarf.**

---

## 4  Weitere Breaking Changes in MySQL 9.x

| Änderung | Relevanz für diesen Stack |
|---|---|
| `mysql_native_password` komplett entfernt (9.0) | ✅ Bereits `caching_sha2_password` — kein Problem |
| `INSERT IGNORE`/`UPDATE IGNORE` strenger bei Subquery-Fehlern | ⚠️ Gering — webtrees nutzt `INSERT IGNORE` selten; testen |
| Strikte String→Numeric-Konvertierung | ⚠️ Gering — testen bei Upgrade |
| `utf8mb3` deprecated (noch nicht entfernt) | ✅ Stack nutzt `utf8mb4` |
| `variables_info.MIN_VALUE/MAX_VALUE` deprecated → `variables_metadata` | ✅ Nicht im Stack genutzt |
| `REPLACE()` Charset-Konvertierung | ⚠️ Gering — testen |

---

## 5  Änderungsbedarf bei Migration

### 5.1  Konfiguration (Image-Tag)

| Datei | Zeile | Alt | Neu | Anmerkung |
|---|---|---|---|---|
| `compose.yaml` | 76 | `mysql:lts` | `mysql:9` oder `mysql:9.7` (wenn LTS erscheint) | Hauptänderung |
| `compose.yaml` | 181 | `mysql:lts` | analog | Security-Track |

### 5.2  Dokumentation aktualisieren

| Datei | Zeile(n) | Änderung |
|---|---|---|
| `README.md` | 66 | Versionsnummer anpassen |
| `docs/tp_infrastructure_spec.md` | 424, 430 | Image-Tag + Versionsnummer |
| `docs/security-audit/01_scope_and_tracks.md` | 17 | Versionsnummer |
| `docs/security-audit/03_infrastructure_usage.md` | 71 | Versionsnummer |
| `docs/systemtest_perf_improve_plan.md` | 243, 284, 434 | PerfSchema-Verhalten ggf. verifizieren |

### 5.3  SQL-Anpassungen

**Keine erforderlich.** Alle genutzten Performance-Schema-Tabellen, -Spalten und
SQL-Konstrukte (Window-Funktionen, JSON-Aggregation) sind in MySQL 9.x unverändert
verfügbar.

### 5.4  Container-Build

Die Containerfiles (`Containerfile.webtrees`, `Containerfile.playwright`) installieren
`default-mysql-client` als Systempaket. Der Client ist versionstolerant und verbindet
sich problemlos mit MySQL 9.x.

### 5.5  PHP-Kompatibilität

PHP 8.x mit `mysqlnd` (Default-Treiber) unterstützt MySQL 9.x vollständig.
`caching_sha2_password` wird nativ unterstützt.

---

## 6  Neuerungen in MySQL 9.x — Relevanz für webtrees und Testplattform

### 6.1  InnoDB-Verbesserungen

| Feature | Version | Relevanz |
|---|---|---|
| **Parallele Index-Builds** (`ALTER TABLE … ADD INDEX` multi-threaded) | 9.2 | ⬆ Hoch — webtrees hat große Tabellen (`individuals`, `name`, `dates`, `link`). GEDCOM-Re-Importe mit nachgelagertem Index-Rebuild profitieren direkt. |
| `innodb_doublewrite_pages` dynamisch änderbar | 9.1 | Gering — Testumgebung, kein Prod-Tuning nötig |
| Redo-Log-Recovery parallelisiert (~15 % schnellerer Container-Start) | 9.0 | ⬆ Mittel — schnellerer `make up`-Zyklus im Entwickleralltag |
| `ROW_FORMAT=COMPRESSED` deprecated | 9.0 | ✅ Kein Problem — webtrees nutzt `DYNAMIC` (InnoDB Default) |

### 6.2  Performance-Schema-Erweiterungen

| Feature | Version | Relevanz |
|---|---|---|
| **`performance_schema.error_log`-Tabelle** — Server-Fehlerlog per SQL abfragbar | 9.0 | ⬆ Hoch — ermöglicht automatisierte Test-Assertions auf MySQL-Warnungen/Fehler ohne Log-Parsing; direkt nutzbar in `extract-perfschema.sh` oder als neuer Layer-3/4-Prüfschritt |
| `QUERY_SAMPLE_TIMER_WAIT` in `events_statements_*` | 9.1 | ⬆ Mittel — bessere Slow-Query-Korrelation in `extract-perfschema.sh`; neue Spalte für JSON-Export ergänzbar |
| `variables_info.SET_TIME` / `SET_USER` | 9.2 | Gering — nützlich zur Konfigurationsdrift-Diagnose, aber kein Kernbedarf |
| `tls_channel_status`-Tabelle | 9.1 | Gering — TLS nicht im Teststack aktiv |

### 6.3  Query-Optimizer-Verbesserungen

| Feature | Version | Relevanz |
|---|---|---|
| **Verbesserte Derived-Table-Materialisierung und Condition-Pushdown** | 9.0 | ⬆ Hoch — webtrees nutzt intensiv `joinSub()` und `fromSub()` (z. B. `AdminService.php:68–107`, `RecentChangesModule.php:295`, `StatisticsData.php:385–409`). Der Optimizer kann Bedingungen nun tiefer in Subqueries schieben. |
| **Hash-Joins für Anti-/Semi-Joins erweitert** | 9.0 | ⬆ Mittel — webtrees hat viele `EXISTS`/`NOT EXISTS`-Muster in Statistik-Queries |
| **Verbessertes Index-Merge** (mehrere Indizes pro Tabelle gleichzeitig) | 9.2 | ⬆ Mittel — `SearchService.php` nutzt Multi-Spalten-WHERE auf `individuals`/`name` |
| **Bessere Kosten-Schätzung bei Histogrammen** (`ANALYZE TABLE … UPDATE HISTOGRAM AUTO`) | 9.1 | ⬆ Mittel — bei schiefer Datenverteilung (typisch für Genealogie: wenige Nachnamen, viele Individuen) profitieren die Statistik-Queries |
| **Verbesserter `IN`→`EXISTS`-Transform** für korrelierte Subqueries | 9.2 | Gering bis Mittel — abhängig von konkreten Queryplänen |

### 6.4  SQL-Sprachfeatures

| Feature | Version | Relevanz |
|---|---|---|
| **`INTERSECT` / `EXCEPT` Set-Operatoren** (stabilisiert) | 9.0 | ⬆ Mittel — webtrees nutzt intensiv `UNION`/`UNION ALL` (`AdminService.php`, `LinkedRecordService.php`, `Statistics.php`). `INTERSECT`/`EXCEPT` könnten Duplikat-Erkennung und Mengenvergleiche vereinfachen. |
| **`EXPLAIN INTO @variable`** | 9.2 | ⬆ Mittel — ermöglicht programmatische Query-Plan-Inspektion in der Testinfrastruktur; nutzbar für automatisierte Regressionsanalyse von Queryplänen |
| **`EXPLAIN ANALYZE` mit `actual_rows`/`actual_loop_count`** im JSON-Format | 9.0 | ⬆ Mittel — besseres Profiling in Performance-Tests (Layer 5) |
| `ANY_VALUE()` in mehr Kontexten bei `ONLY_FULL_GROUP_BY` | 9.0 | Gering — webtrees nutzt explizite `groupBy()`-Klauseln |

### 6.5  Relevanz für webtrees-Codepatterns

Die folgenden bestehenden webtrees-Muster profitieren besonders:

**Tief verschachtelte LEFT-JOIN-Ketten (MapDataService.php:161–188)**
webtrees bildet die Orts-Hierarchie über 8 sequenzielle LEFT JOINs auf `place_location` ab.
MySQL 9.x verbessert die Kostenabschätzung bei langen JOIN-Ketten und könnte durch
rekursive CTEs (seit 8.0 verfügbar, in 9.x optimiert) ersetzt werden.

**UNION-basierte Record-Aggregation (AdminService.php:62–107, 199–230)**
Mehrere Tabellen (`individuals`, `families`, `sources`, `media`, `other`) werden per UNION
zusammengeführt und anschließend via `fromSub()` aggregiert. Der verbesserte
Derived-Table-Optimizer in 9.x kann Filterbedingungen tiefer in die UNION-Zweige schieben.

**Statistik-Queries mit GROUP BY + HAVING (StatisticsData.php, AdminService.php)**
Webtrees nutzt `COUNT(DISTINCT …)`, `AVG(…)`, `MIN/MAX`-Aggregate mit `HAVING`-Klauseln
an zahlreichen Stellen (AdminService Z. 117–173, StatisticsData passim). Die optimierte
Aggregate-Verarbeitung in 9.x kann diese Queries beschleunigen.

**GROUP_CONCAT (DB.php:60–66, AdminService.php:124)**
webtrees abstrahiert `GROUP_CONCAT` für Multi-DB-Support. In MySQL 9.x ist
`GROUP_CONCAT` intern effizienter bei großen Ergebnismengen.

### 6.6  Container- und Betriebsverbesserungen

| Feature | Version | Relevanz |
|---|---|---|
| **~15 % schnellerer Container-Start** (parallele Redo-Log-Recovery) | 9.0 | ⬆ Mittel — schnellerer `make up`-Zyklus |
| **~50 MB kleineres Base-Image** | 9.1 | Gering — spart Pull-Zeit, kein funktionaler Vorteil |
| `MYSQL_AUTO_GENERATE_RSA_KEYS=0` überspringt RSA-Key-Generierung | 9.1 | ⬆ Mittel — spart Sekunden bei jedem Container-Start; nützlich für CI |
| `COM_RESET_CONNECTION` zuverlässiger für Persistent-PDO | 9.1 | Gering — Teststack nutzt keine persistenten Verbindungen |

### 6.7  Sicherheitsverbesserungen

| Feature | Version | Relevanz |
|---|---|---|
| `mysql_native_password` komplett entfernt | 9.0 | ✅ Bereits migriert (Stack nutzt `caching_sha2_password`) |
| `GRANT … AS`-Syntax für Privilege-Delegation | 9.1 | Gering — Teststack nutzt Root-Credentials |
| `SENSITIVE_VARIABLES_OBSERVER`-Privileg | 9.1 | Gering — kein Bedarf im Testkontext |
| TLS-1.3-Konfiguration vereinfacht | 9.2 | Gering — TLS nicht im Teststack aktiv |

---

## 7  Empfehlung

| Option | Pro | Contra |
|---|---|---|
| **A: Bei `mysql:lts` (8.4) bleiben** | Stabil, Support bis 2032, kein Aufwand | Keine neuen Features |
| **B: Auf `mysql:9` (Innovation) wechseln** | Neueste Features, Optimizer-Verbesserungen sofort nutzbar | Nur ~3 Monate Support pro Release, kein LTS-Tag |
| **C: Auf `mysql:9.7` LTS warten** | LTS-Stabilität + alle 9.x-Features | Noch nicht verfügbar (erwartet H2 2026) |

### Empfehlung: Option C — auf MySQL 9.7 LTS warten

1. **Kein dringender Änderungsbedarf** — alle SQL-Queries und PerfSchema-Nutzung sind 9.x-kompatibel.
2. Innovation-Releases (9.6) haben zu kurzen Support für eine Testplattform.
3. Die attraktivsten Neuerungen (Optimizer-Verbesserungen, `error_log`-Tabelle,
   parallele Index-Builds) laufen nicht weg — sie sind in jedem 9.x-Release enthalten.
4. Sobald `mysql:9-lts` oder `mysql:9.7` erscheint: Image-Tag in `compose.yaml` ändern,
   Dokumentation aktualisieren, vollständigen Testlauf (Layer 2–5) durchführen.

### Vorbereitende Schritte (jetzt möglich)

1. **Smoke-Test mit `mysql:9`:** Einmalig `compose.yaml` lokal auf `mysql:9` umstellen,
   `make test-all` laufen lassen, Ergebnis dokumentieren. Gibt Klarheit über unerwartete
   Inkompatibilitäten.
2. **`INSERT IGNORE`-Audit:** Im webtrees-Core nach `INSERT IGNORE` und `UPDATE IGNORE`
   mit Subqueries suchen — potenzieller Bruchpunkt in 9.x.
3. **PerfSchema-Erweiterung vorplanen:** `extract-perfschema.sh` um
   `QUERY_SAMPLE_TIMER_WAIT` (9.1) und `error_log`-Abfrage (9.0) erweitern —
   als Feature-Branch vorbereiten, Merge nach Upgrade.
