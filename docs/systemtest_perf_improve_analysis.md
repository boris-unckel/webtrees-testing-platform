<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Analyse: Testfall-korrelierte DB-Performance-Daten in Layer 4

**Datum:** 2026-04-11  
**Kontext:** Layer 4 (Playwright E2E) — Verbindung zwischen perfschema-Daten und Testfällen  
**Ausgangsproblem:** `artifacts/layer4/perfschema/statements_by_digest.json` enthält nur 5 Connection-Setup-Queries (USE, SET NAMES, START TRANSACTION, COMMIT, ROLLBACK). Anwendungsqueries fehlen. Keine Zuordnung zu Testfällen möglich.

---

## Aktueller Ist-Zustand

### Was funktioniert

| Komponente | Status | Details |
|---|---|---|
| OTel Playwright-Fixture | Aktiv | `otel-fixture.ts` injiziert `traceparent` + `baggage` (test.run_id, test.case_id) in jeden HTTP-Request |
| OtelSpansModule | Aktiv | Empfängt Baggage, setzt `test.run_id`/`test.case_id` als Span-Attribute |
| trace-report.py | Aktiv | Gruppiert Spans nach `test.case_id`, ordnet Browser-Spans via trace_id zu |
| OTel Collector | Aktiv | Exportiert nach Jaeger + `artifacts/traces.json` |
| PerfSchema TRUNCATE | Aktiv | `scripts/truncate-perfschema.sh` läuft einmalig vor dem gesamten Testlauf |
| PerfSchema-Extraktion | Aktiv | `scripts/extract-perfschema.sh` läuft einmalig nach dem gesamten Testlauf |

### Was fehlt

**Problem A — NULL-Digest-Overflow:**  
`performance_schema.events_statements_summary_by_digest` hat einen begrenzten Puffer
(`performance_schema_digests_size`). Wenn er voll läuft, gehen alle weiteren Query-Typen in
den NULL-Digest-Bucket. Die Extraktionsquery filtert `DIGEST_TEXT IS NOT NULL` — overflow-Queries
werden unsichtbar. Prüfbar mit:
```sql
SHOW VARIABLES LIKE 'performance_schema_digests_size';
SELECT COUNT(*) FROM performance_schema.events_statements_summary_by_digest WHERE DIGEST_TEXT IS NULL;
```

**Problem B — Kein Test-Fenster:**  
Ein globaler Snapshot über ~2.587 Transaktionen lässt keine Zuordnung zu einzelnen Testfällen zu.

**Problem C — PDO-Spans werden in `trace-report.py` herausgefiltert (zwei Stellen):**  
`opentelemetry-auto-pdo` ist bereits in `scripts/setup-webtrees.sh` [1b/4] installiert — PDO-Spans
entstehen. Sie sind jedoch in den Reports unsichtbar, weil `trace-report.py` sie an zwei Stellen
verliert:

1. **`parse_traces()` filtert zu früh:** Die Funktion prüft `attrs.get("test.run_id") == run_id`
   direkt auf dem Span. PDO-Child-Spans erben das Attribut nicht — `OtelSpansModule` setzt
   `test.run_id` nur auf dem `webtrees.action`-Parent. Ergebnis: PDO-Spans werden vollständig
   verworfen, bevor sie `group_by_test_case()` erreichen.

2. **`group_by_test_case()` traversiert keine Eltern:** `test.case_id` liegt nur auf dem
   Parent-Span. Child-Spans, die `parse_traces()` passieren würden, landen in `"(unbekannt)"`.

`classify_span()` ist für PDO bereits vorbereitet (`"pdo" in span.scope` → `"DB Query"`);
diese Funktion braucht keine Änderung.

---

## Ansatz 1: Per-Test PerfSchema-Snapshots

### Funktionsprinzip

Jeder Playwright-Test bekommt ein eigenes Zeitfenster in der PerfSchema:

1. Playwright `beforeEach` → TRUNCATE der 4 PerfSchema-Tabellen
2. Test läuft (alle SQL-Queries landen im frischen Puffer)
3. Playwright `afterEach` → Extraktion der PerfSchema-Daten in Test-spezifisches Artefakt

Ergebnis: `artifacts/layer4/perfschema/<test-case-id>/statements_by_digest.json` etc.

### Notwendige Änderungen

#### 1. MySQL-Client im Playwright-Container

`Containerfile.playwright` muss `mysql-client` (oder `default-mysql-client`) erhalten:

```dockerfile
# nach den bestehenden npm/playwright-Installationen:
RUN apt-get install -y --no-install-recommends default-mysql-client
```

Umgebungsvariablen im Playwright-Container (bereits in `compose.yaml`-Abschnitt `playwright`
vorhanden, aber MySQL-Credentials fehlen):

```yaml
# compose.yaml playwright-Service ergänzen:
environment:
  MYSQL_HOST: mysql
  MYSQL_PORT: "3306"
  MYSQL_DATABASE: ${MYSQL_DATABASE}
  MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
```

#### 2. Playwright Global Fixture erweitern

Neues Helper-Modul `layer4-e2e/helpers/perfschema-fixture.ts`:

```typescript
// SPDX-License-Identifier: AGPL-3.0-or-later
import { test as otelBase } from './otel-fixture';
import { execSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';

function mysqlRoot(sql: string): void {
  execSync(
    `mysql -h mysql -u root -p"${process.env.MYSQL_ROOT_PASSWORD}" -e "${sql}"`,
    { stdio: 'pipe' }
  );
}

function extractPerfschema(dir: string): void {
  const db = process.env.MYSQL_DATABASE ?? 'webtrees_test';
  // statements_by_digest
  const result = execSync(
    `mysql -h mysql -u root -p"${process.env.MYSQL_ROOT_PASSWORD}" \
     --batch --raw --skip-column-names -e "
     SELECT JSON_ARRAYAGG(JSON_OBJECT(
       'digest_text', DIGEST_TEXT,
       'count', COUNT_STAR,
       'avg_ms', ROUND(AVG_TIMER_WAIT/1000000000,2),
       'total_ms', ROUND(SUM_TIMER_WAIT/1000000000,2),
       'rows_examined', SUM_ROWS_EXAMINED,
       'full_scans', SUM_SELECT_SCAN,
       'no_index', SUM_NO_INDEX_USED
     ))
     FROM performance_schema.events_statements_summary_by_digest
     WHERE SCHEMA_NAME = '${db}' AND DIGEST_TEXT IS NOT NULL
     ORDER BY SUM_TIMER_WAIT DESC LIMIT 30"`,
    { stdio: 'pipe' }
  ).toString().trim();
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'statements.json'), result || '[]');
}

export const test = otelBase.extend<{ _perfschema: void }>({
  _perfschema: [async ({}, use, testInfo) => {
    // TRUNCATE vor dem Test
    mysqlRoot(
      'TRUNCATE TABLE performance_schema.events_statements_summary_by_digest; ' +
      'TRUNCATE TABLE performance_schema.table_io_waits_summary_by_table;'
    );

    await use();

    // Extraktion nach dem Test
    const safeId = testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_');
    const dir = `/artifacts/layer4/perfschema/per-test/${safeId}`;
    extractPerfschema(dir);
  }, { auto: true }],
});

export { expect } from '@playwright/test';
```

#### 3. Testdateien auf neues Fixture umstellen

Alle `import { test, expect } from '../helpers/otel-fixture'` auf
`import { test, expect } from '../helpers/perfschema-fixture'` ändern.

Da `perfschema-fixture.ts` intern `otel-fixture` erweitert, bleibt die OTel-Funktionalität erhalten.

#### 4. TRUNCATE-Overhead berücksichtigen

Mit `workers: 1` (sequentiell) und ~30 Tests: ca. 30 × TRUNCATE-Overhead.
TRUNCATE auf PerfSchema-Tabellen dauert typisch < 5 ms — vernachlässigbar.

### Vorteile

- Exakte Zuordnung Query ↔ Test: kein Rauschen durch andere Tests
- Kein NULL-Digest-Overflow pro Test (frischer Puffer, weniger verschiedene Digest-Typen)
- Funktioniert ohne PHP-SDK-Änderungen
- Counter akkurat: `SUM_TIMER_WAIT`, `SUM_ROWS_EXAMINED`, `SUM_NO_INDEX_USED` pro Test auswertbar
- Artefakte persistent: vergleichbar über Testläufe hinweg

### Nachteile und Risiken

| Risiko | Bewertung | Mitigation |
|---|---|---|
| MySQL-Client im Playwright-Container | Containerfile-Änderung notwendig | Einmalig |
| MYSQL_ROOT_PASSWORD in Playwright-Env | Credentials-Ausweitung im Container | Separater Read-only-User für perfschema denkbar |
| execSync aus Playwright-Fixture | Blockierend, aber < 10 ms | Akzeptabel bei workers:1 |
| TRUNCATE löscht globale Counter | Kein Lauf-Aggregat mehr verfügbar | Separater Gesamt-Snapshot nach Testlauf (wie bisher) |
| NULL-Digest-Overflow bleibt möglich | Abhängig von performance_schema_digests_size | `--performance-schema-digests-size=10000` in compose.yaml ergänzen |

---

## Ansatz 2: OTel DB-Spans (PDO-Instrumentation)

### Funktionsprinzip

Das PHP OTel SDK kann PDO-Aufrufe automatisch als Spans instrumentieren.
Die Trace-Kontextkette lautet dann:

```
Playwright-Test-Span (traceparent=00-traceId-spanId-01)
  └── PHP Root-Span (auto-psr15, W3C Trace Context propagiert)
       └── OtelSpansModule-Span (webtrees.action)
            └── PDO-Span (db.statement = SELECT ..., db.system = mysql)
            └── PDO-Span (db.statement = SELECT ..., db.system = mysql)
            └── ...
```

Jeder PDO-Span erbt den Trace-Kontext, damit automatisch auch `test.case_id` (via Baggage →
OtelSpansModule setzt es als Span-Attribut auf dem Parent).

### Aktueller OTel-Setup

Das PHP-SDK ist bereits aktiv (`OTEL_PHP_AUTOLOAD_ENABLED: true`). Die `auto-psr15`-Extension
stellt bereits Root-Spans für jeden HTTP-Request bereit. Es fehlt ausschließlich das PDO-Paket.

### Notwendige Änderungen

#### 1. Composer-Abhängigkeit: opentelemetry-auto-pdo

**Bereits erledigt.** `open-telemetry/opentelemetry-auto-pdo` wird in
`scripts/setup-webtrees.sh` [1b/4] (Zeile 62) zusammen mit den übrigen OTel-Paketen
installiert. Kein zusätzlicher Schritt notwendig.

Das Paket registriert sich über den OpenTelemetry PHP-Hook automatisch und wird über die
bereits gesetzte Variable `OTEL_PHP_AUTOLOAD_ENABLED: true` aktiviert.

#### 2. MySQL-Endpoint-Konfiguration in `php.ini` oder OTel-Env

`opentelemetry-auto-pdo` konfiguriert sich über die Standard-OTel-Env-Variablen. Zusätzlich
kann `db.statement`-Erfassung explizit aktiviert werden:

```yaml
# compose.yaml webtrees-Service, environment-Block ergänzen:
OTEL_PHP_INSTRUMENTATION_PDO_DB_STATEMENT: "true"
```

#### 3. trace-report.py: `classify_span` bereits vorbereitet

Die Funktion `classify_span()` prüft bereits auf `"pdo" in span.scope`:

```python
if span.scope and "pdo" in span.scope:
    return "DB Query"
```

Keine Änderung an `trace-report.py` notwendig.

#### 4. `trace-report.py`: Zwei Fixes für PDO-Sichtbarkeit

PDO-Spans scheitern in `trace-report.py` an zwei unabhängigen Stellen (siehe Problem C):

**Fix A — `parse_traces()` auf Zwei-Pass umstellen:**  
Statt direkt nach `test.run_id`-Attribut zu filtern, zuerst alle `trace_id`s von
Matching-Spans sammeln, dann alle Spans dieser Traces zurückgeben:

```python
# Pass 1: trace_ids von Spans mit passender test.run_id sammeln
matched_trace_ids = {
    s.trace_id for s in all_spans
    if s.attributes.get("test.run_id") == run_id
}
# Pass 2: alle Spans dieser Traces zurückgeben (inkl. PDO-Child-Spans)
return [s for s in all_spans if s.trace_id in matched_trace_ids]
```

**Fix B — `group_by_test_case()` mit Parent-Traversal:**  
`test.case_id` liegt nur auf dem `webtrees.action`-Parent. Child-Spans müssen über
`parent_span_id` aufwärts traversiert werden:

```python
def resolve_test_case(span: Span, by_id: dict) -> str:
    """Geht die Span-Hierarchie aufwärts bis test.case_id gefunden."""
    current = span
    while current is not None:
        case_id = current.attributes.get("test.case_id")
        if case_id:
            return case_id
        current = by_id.get(current.parent_span_id)
    return "(unbekannt)"
```

### Vorteile

- SQL-Query-Text (`db.statement`) pro Test sichtbar
- Vollständige Span-Hierarchie: Playwright → HTTP → PHP → SQL
- Jaeger-UI zeigt die Traces interaktiv visualisiert
- Kein MySQL-Client in Playwright-Container notwendig
- Keine TRUNCATE-Seiteneffekte auf globale Counter

### Nachteile und Risiken

| Risiko | Bewertung | Mitigation |
|---|---|---|
| Span-Overhead pro PDO-Aufruf | ~0.1–0.5 ms/Query × ~50.000 Calls = messbar | `OTEL_SDK_DISABLED=true` für Laufzeit-Tests; PDO-Spans nur bei Bedarf |
| db.statement enthält normalisierte Query | Parameterwerte werden nicht erfasst (korrekt) | Kein Handlungsbedarf |
| traces.json wächst erheblich | Jede PDO-Query = 1 Span × 2.587 Transaktionen | `artifacts/traces.json` bei jedem Lauf neu anlegen (bereits in `make clean`) |
| parent_span_id-Traversal in trace-report.py | Kleine Code-Änderung | Gut lokalisiert, < 20 Zeilen |
| PDO-Span erzeugt keinen test.case_id-Eintrag direkt | Indirekte Zuordnung über Parent-Traversal | Lösbar (s. o.) |

---

## Vergleich

| Kriterium | Per-Test PerfSchema | OTel DB-Spans |
|---|---|---|
| SQL-Query-Text sichtbar | Normalisierter Digest | Normalisierter Statement-Text |
| Testfall-Zuordnung | Exakt (TRUNCATE-Fenster) | Über Span-Hierarchie (Parent-Traversal) |
| Aggregierte Metriken | Ja (rows_examined, full_scans etc.) | Nein (nur Dauer pro Span) |
| Implementierungsaufwand | Mittel | Gering |
| Infrastruktureingriff | Containerfile.playwright + compose.yaml | Containerfile.webtrees (oder setup-Skript) |
| Persistenz der Daten | Pro-Test-Artefakt (Datei) | traces.json + Jaeger |
| Overhead | TRUNCATE-Overhead pro Test | Span-Overhead pro PDO-Aufruf |
| NULL-Digest-Problem | Entschärft (kleineres Fenster) | Irrelevant (andere Datenquelle) |
| Querversatz zu Layer 3 | Nein | Nein |

---

## Empfehlung: Stufenweise Kombination

Die Ansätze sind komplementär, nicht konkurrierend:

**Stufe 1 (kurzfristig):** OTel DB-Spans aktivieren

- `opentelemetry-auto-pdo` bereits installiert (kein `composer require` nötig)
- 1 Env-Variable in `compose.yaml` + 2 Fixes in `trace-report.py` (Zwei-Pass-Filter, Parent-Traversal)
- Sofortiger Mehrwert: SQL-Queries in Jaeger sichtbar, trace-report.py zeigt DB-Layer
- Risiko: gering

**Stufe 2 (mittelfristig):** Per-Test PerfSchema-Snapshots

- Ergänzt OTel DB-Spans um aggregierte Counter (rows_examined, full_scans)
- Notwendig für Performance-Regression-Tests auf Query-Ebene
- Aufwand: Containerfile + Fixture-Code

**Stufe 3 (optional):** NULL-Digest-Overflow beheben

- `--performance-schema-digests-size=10000` in compose.yaml MySQL-Command ergänzen
- Stellt sicher, dass auch im globalen Snapshot alle Query-Typen erfasst werden
- Betrifft beide Ansätze positiv

---

## Root-Cause des aktuellen NULL-Digest-Problems

Zur Überprüfung vor Implementierungsbeginn:

```bash
podman-compose exec -e MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql \
  mysql -u root -e "
  SHOW VARIABLES LIKE 'performance_schema_digests_size';
  SELECT COUNT(*) as overflow_count 
  FROM performance_schema.events_statements_summary_by_digest 
  WHERE DIGEST_TEXT IS NULL;"
```

Wenn `overflow_count > 0`: Buffer-Größe erhöhen (Stufe 3 vorziehen).  
Wenn `overflow_count = 0`: Die Anwendungsqueries laufen unter einem anderen Schema oder
der PerfSchema-Consumer ist für diesen Statement-Typ deaktiviert.
