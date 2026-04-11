<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Umsetzungsplan: Testfall-korrelierte DB-Performance-Daten (Layer 4)

**Datum:** 2026-04-11  
**Basis:** `docs/systemtest_perf_improve_analysis.md`  
**Ziel:** SQL-Queries und Aggregat-Metriken einzelnen Playwright-Testfällen zuordnen

---

## Statuslegende

| Symbol | Bedeutung |
|--------|-----------|
| `[ ]`  | Offen     |
| `[~]`  | In Bearbeitung |
| `[x]`  | Abgeschlossen |
| `[!]`  | Blockiert |

---

## Aufgaben-Übersicht

| ID   | Phase          | Aufgabe                                     | Status |
|------|----------------|---------------------------------------------|--------|
| D1   | Diagnose       | NULL-Digest-Query ausführen                 | `[ ]`  |
| T1.1 | Phase 1        | `compose.yaml`: PDO-Statement-Flag          | `[ ]`  |
| T1.2 | Phase 1        | `trace-report.py`: Zwei-Pass + Parent-Traversal | `[ ]`  |
| T1.3 | Phase 1        | Verprobung `make test-e2e-quick`            | `[ ]`  |
| T1.4 | Phase 1        | Artefakt-Kontrolle PDO-Spans                | `[ ]`  |
| T2.1 | Phase 2        | `compose.yaml`: MySQL-Root-Credentials playwright-Service | `[ ]`  |
| T2.2 | Phase 2        | `layer4-e2e/helpers/perfschema-fixture.ts` erstellen | `[ ]`  |
| T2.3 | Phase 2        | 20 Testdatei-Imports umstellen              | `[ ]`  |
| T2.4 | Phase 2        | Verprobung `make test-e2e-quick`            | `[ ]`  |
| T2.5 | Phase 2        | Artefakt-Kontrolle per-test PerfSchema      | `[ ]`  |
| T3.1 | Phase 3 (opt.) | `compose.yaml`: `digests-size=10000`        | `[ ]`  |
| TF.1 | Abschluss      | `make clean`                                | `[ ]`  |
| TF.2 | Abschluss      | `make setup`                                | `[ ]`  |
| TF.3 | Abschluss      | `make test-e2e`                             | `[ ]`  |
| TF.4 | Abschluss      | Abnahme: Artefakte vollständig prüfen       | `[ ]`  |

---

## D — Vorab-Diagnose: NULL-Digest-Problem

Vor Phase 1 prüfen, ob der NULL-Digest-Overflow das eigentliche Sichtbarkeitsproblem ist.

### D1 — Diagnostische Abfrage

```bash
podman-compose exec -e MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql \
  mysql -u root -e "
  SHOW VARIABLES LIKE 'performance_schema_digests_size';
  SELECT COUNT(*) AS overflow_count
  FROM performance_schema.events_statements_summary_by_digest
  WHERE DIGEST_TEXT IS NULL;
  SELECT SCHEMA_NAME, COUNT(*) AS buckets
  FROM performance_schema.events_statements_summary_by_digest
  GROUP BY SCHEMA_NAME;"
```

**Interpretation:**

| Ergebnis | Bedeutung | Aktion |
|----------|-----------|--------|
| `overflow_count = 0` | Kein Overflow — Queries fehlen aus anderem Grund | Phase 1 und 2 lösen das Problem |
| `overflow_count > 0` | Digest-Buffer zu klein | T3.1 vorziehen oder gleichzeitig angehen |
| `SCHEMA_NAME = webtrees_test` nicht in Ergebnis | Queries laufen unter anderem Schema oder Consumer deaktiviert | Separate Diagnose nötig |

---

## Phase 1: OTel DB-Spans

**Ziel:** SQL-Queries als Spans in Jaeger sichtbar machen, über `trace-report.py` Testfällen zugeordnet.

**Schlüsselbefund:** `opentelemetry-auto-pdo` ist **bereits** in `scripts/setup-webtrees.sh` [1b/4]
(Zeile 62) installiert. Die Analyse war an diesem Punkt überholt. Phase 1 reduziert sich auf zwei
Dateien.

**Zweiter Befund:** `parse_traces()` in `scripts/trace-report.py` filtert Spans direkt nach
`attrs.get("test.run_id") == run_id`. PDO-Child-Spans haben dieses Attribut **nicht** — es wird
von `OtelSpansModule` nur auf den `webtrees.action`-Parent-Span geschrieben. PDO-Spans werden
daher vollständig rausgefiltert, nicht nur falsch gruppiert. Zwei Fixes nötig:
1. Zwei-Pass-Filter in `parse_traces()`: erst `trace_id`s von Matching-Spans sammeln, dann alle
   Spans dieser Traces zurückgeben
2. Parent-Traversal in `group_by_test_case()` für `test.case_id`

---

### T1.1 — `compose.yaml`: PDO-Statement-Flag

- [ ] **T1.1**

**Datei:** `compose.yaml`, webtrees-Service, `environment`-Block — nach `OTEL_LOGS_EXPORTER`:

```yaml
OTEL_PHP_INSTRUMENTATION_PDO_DB_STATEMENT: "true"
```

Ohne dieses Flag erzeugt `opentelemetry-auto-pdo` PDO-Spans ohne `db.statement`-Attribut.
Query-Texte wären in Jaeger nicht sichtbar.

---

### T1.2 — `scripts/trace-report.py`: Zwei-Pass-Filter + Parent-Traversal

- [ ] **T1.2**

**Datei:** `scripts/trace-report.py`

#### Änderung 1: `parse_traces()` — Zwei-Pass-Ansatz

Die aktuelle Implementierung (Zeile 50–83) filtert `attrs.get("test.run_id") != run_id`
direkt beim Einlesen. PDO-Child-Spans haben kein `test.run_id`-Attribut und werden
vollständig verworfen.

**Ersatz:**

```python
def parse_traces(traces_path: str, run_id: str) -> list:
    all_spans = []
    with open(traces_path) as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            data = json.loads(line)
            for rs in data.get("resourceSpans", []):
                res_attrs = _extract_attrs(
                    rs.get("resource", {}).get("attributes", [])
                )
                svc = res_attrs.get("service.name", "unknown")
                for ss in rs.get("scopeSpans", []):
                    scope = ss.get("scope", {}).get("name", "unknown")
                    for s in ss.get("spans", []):
                        attrs = _extract_attrs(s.get("attributes", []))
                        start = int(s["startTimeUnixNano"])
                        end = int(s["endTimeUnixNano"])
                        all_spans.append(Span(
                            trace_id=s["traceId"],
                            span_id=s["spanId"],
                            parent_span_id=s.get("parentSpanId") or None,
                            name=s["name"],
                            start_ns=start,
                            end_ns=end,
                            duration_ms=round((end - start) / 1_000_000, 2),
                            service_name=svc,
                            scope=scope,
                            attributes=attrs,
                        ))

    # Pass 1: trace_ids von Spans mit passender test.run_id sammeln
    matched_trace_ids = {
        s.trace_id for s in all_spans
        if s.attributes.get("test.run_id") == run_id
    }

    # Pass 2: alle Spans dieser Traces zurückgeben (inkl. PDO-Child-Spans)
    return [s for s in all_spans if s.trace_id in matched_trace_ids]
```

#### Änderung 2: `resolve_test_case()` + `group_by_test_case()` — Parent-Traversal

Die aktuelle `group_by_test_case()` (Zeile 133–138) liest `test.case_id` nur direkt vom Span.
PDO-Child-Spans haben das Attribut nicht — sie landen in `"(unbekannt)"`.

**Neue Hilfsfunktion und ersetzte `group_by_test_case()`:**

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


def group_by_test_case(spans: list) -> dict:
    by_id = {s.span_id: s for s in spans}
    groups = defaultdict(list)
    for span in spans:
        case_id = resolve_test_case(span, by_id)
        groups[case_id].append(span)
    return dict(groups)
```

`resolve_test_case` einzufügen **vor** `group_by_test_case` (aktuelle Zeile 133).

---

### T1.3 — Verprobung Phase 1

- [ ] **T1.3**

```bash
make test-e2e-quick
```

**Prüfpunkte nach dem Lauf:**

```bash
# Sind PDO-Spans in traces.json?
grep -c '"pdo"' artifacts/traces.json

# Wieviele Spans sind im letzten trace-report?
ls -t artifacts/trace-report-*.json | head -1 | xargs jq '.total_spans'

# Gibt es "DB Query"-Einträge im Report?
ls -t artifacts/trace-report-*.json | head -1 | xargs jq '[.test_cases[] | .spans[] | select(.layer == "DB Query")] | length'
```

---

### T1.4 — Artefakt-Kontrolle Phase 1

- [ ] **T1.4**

**Erwarteter Zustand:**

| Prüfpunkt | Soll |
|-----------|------|
| `artifacts/traces.json` enthält Spans mit `scope` ~ `"pdo"` | ja |
| `trace-report-<RUN_ID>.json` → `test_cases.<id>.spans` enthält Einträge mit `"layer": "DB Query"` | ja |
| Jaeger UI http://localhost:16686 → Trace zeigt `webtrees.action → PDO`-Hierarchie | ja |
| Kein Testfall unter `"(unbekannt)"` für webtrees-Queries | ja |

---

## Phase 2: Per-Test PerfSchema-Snapshots

**Ziel:** Aggregierte Metriken (`rows_examined`, `full_scans`, `no_index`) pro Testfall als
Datei-Artefakt unter `artifacts/layer4/perfschema/per-test/`.

**Artefakt-Pfad-Hinweis:** `./artifacts:/artifacts:rw,z` ist im playwright-Container bereits
gemountet (`compose.yaml` Zeile 105). Schreibzugriff auf `/artifacts/layer4/perfschema/per-test/`
ist direkt möglich — kein separater `podman cp`-Schritt nötig.

**Abhängigkeit:** Kann unabhängig von Phase 1 implementiert werden. `perfschema-fixture.ts`
erweitert `otel-fixture` — OTel-Funktionalität bleibt vollständig erhalten.

**Scope:** Nur die 20 Testdateien unter `layer4-e2e/tests/*.spec.ts` werden umgestellt.
Die 6 Security-Tests (`layer4-e2e/tests/security/*.spec.ts`) laufen gegen `webtrees-security`
+ `mysql-security` — sie bleiben bei `otel-fixture`.

---

### T2.1 — `compose.yaml`: MySQL-Root-Credentials playwright-Service

- [ ] **T2.1**

**Datei:** `compose.yaml`, playwright-Service, `environment`-Block ergänzen:

```yaml
MYSQL_HOST: mysql
MYSQL_DATABASE: ${MYSQL_DATABASE}
MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
```

Die Root-Credentials sind ausschließlich in der Test-Umgebung. `make clean` rotiert sie
bei jedem Neuaufbau — kein dauerhaftes Secret.

---

### T2.2 — `layer4-e2e/helpers/perfschema-fixture.ts` erstellen

- [ ] **T2.2**

**Pfad:** `layer4-e2e/helpers/perfschema-fixture.ts`

```typescript
// SPDX-License-Identifier: AGPL-3.0-or-later
import { test as otelBase, expect } from './otel-fixture';
import { execSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';

function mysqlRoot(sql: string): void {
  execSync(
    `mysql -h "${process.env.MYSQL_HOST ?? 'mysql'}" -u root` +
    ` -p"${process.env.MYSQL_ROOT_PASSWORD}" -e "${sql}"`,
    { stdio: 'pipe' }
  );
}

function extractPerfschema(dir: string): void {
  const host = process.env.MYSQL_HOST ?? 'mysql';
  const pwd  = process.env.MYSQL_ROOT_PASSWORD ?? '';
  const db   = process.env.MYSQL_DATABASE ?? 'webtrees_test';
  const result = execSync(
    `mysql -h "${host}" -u root -p"${pwd}" --batch --raw --skip-column-names -e "` +
    `SELECT JSON_ARRAYAGG(JSON_OBJECT(` +
    `  'digest_text', DIGEST_TEXT,` +
    `  'count', COUNT_STAR,` +
    `  'avg_ms', ROUND(AVG_TIMER_WAIT/1000000000,2),` +
    `  'total_ms', ROUND(SUM_TIMER_WAIT/1000000000,2),` +
    `  'rows_examined', SUM_ROWS_EXAMINED,` +
    `  'full_scans', SUM_SELECT_SCAN,` +
    `  'no_index', SUM_NO_INDEX_USED` +
    `)) FROM performance_schema.events_statements_summary_by_digest` +
    ` WHERE SCHEMA_NAME = '${db}' AND DIGEST_TEXT IS NOT NULL` +
    ` ORDER BY SUM_TIMER_WAIT DESC LIMIT 30"`,
    { stdio: 'pipe' }
  ).toString().trim();
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'statements.json'), result || '[]');
}

export const test = otelBase.extend<{ _perfschema: void }>({
  _perfschema: [async ({}, use, testInfo) => {
    mysqlRoot(
      'TRUNCATE TABLE performance_schema.events_statements_summary_by_digest; ' +
      'TRUNCATE TABLE performance_schema.table_io_waits_summary_by_table;'
    );

    await use();

    const safeId = testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_');
    extractPerfschema(`/artifacts/layer4/perfschema/per-test/${safeId}`);
  }, { auto: true }],
});

export { expect };
```

---

### T2.3 — 20 Testdatei-Imports umstellen

- [ ] **T2.3**

In jeder der folgenden 20 Dateien Zeile 3 ändern:

```typescript
// Alt:
import { test, expect } from '../helpers/otel-fixture';
// Neu:
import { test, expect } from '../helpers/perfschema-fixture';
```

**Dateien** (alle unter `layer4-e2e/tests/`):

```
access-control.spec.ts
auth.spec.ts
calendar.spec.ts
family.spec.ts
homepage.spec.ts
individual.spec.ts
login.spec.ts
navigation.spec.ts
pedigree.spec.ts
privacy-charts.spec.ts
privacy-relationship.spec.ts
privacy-resn.spec.ts
privacy-search.spec.ts
privacy-visibility.spec.ts
records.spec.ts
search-forms.spec.ts
search-replace.spec.ts
source-list.spec.ts
upload-validation.spec.ts
user-pages.spec.ts
```

Die 6 Security-Testdateien unter `tests/security/` bleiben unverändert bei `otel-fixture`.

---

### T2.4 — Verprobung Phase 2

- [ ] **T2.4**

```bash
make test-e2e-quick
```

**Prüfpunkte nach dem Lauf:**

```bash
# Existieren per-test-Unterordner?
ls artifacts/layer4/perfschema/per-test/

# Enthält ein Unterordner webtrees-Queries (nicht nur Connection-Setup)?
cat artifacts/layer4/perfschema/per-test/*/statements.json | \
  python3 -c "import json,sys; d=json.load(sys.stdin); print(len(d), 'Queries')"
```

---

### T2.5 — Artefakt-Kontrolle Phase 2

- [ ] **T2.5**

**Erwarteter Zustand:**

| Prüfpunkt | Soll |
|-----------|------|
| `artifacts/layer4/perfschema/per-test/` hat ≥ 3 Unterordner (je 1 pro quick-Testfall) | ja |
| Jedes `statements.json` enthält > 5 Einträge (mehr als nur Connection-Setup) | ja |
| `digest_text` enthält webtrees-Anwendungsqueries (SELECT aus `wt_*`-Tabellen) | ja |
| `rows_examined` > 0 für mindestens eine Query | ja |

---

## Phase 3 (optional): NULL-Digest-Overflow beheben

Nur ausführen, wenn Diagnose D1 `overflow_count > 0` ergibt.

- [ ] **T3.1**

**Datei:** `compose.yaml`, mysql-Service, `command`-Block — ans Ende anfügen:

```yaml
--performance-schema-digests-size=10000
```

Erhöht den Digest-Puffer von Default (10.000 bei MySQL 8.x, oft 1.000 bei älteren Versionen)
auf garantiert 10.000 Einträge. Wirkt erst nach Container-Neustart (`make down && make up`).

---

## Abschlusslauf

Nach erfolgreicher Verprobung beider Phasen einen vollständigen Neulauf von Null:

- [ ] **TF.1** — `make clean`  
  Stoppt den Stack, löscht alle Volumes und Passwörter.

- [ ] **TF.2** — `make setup`  
  Startet neu, generiert frische Passwörter, installiert webtrees inkl. `opentelemetry-auto-pdo`.

- [ ] **TF.3** — `make test-e2e`  
  Vollständiger Layer-4-Lauf mit allen Testfällen.

- [ ] **TF.4** — Abnahme

  | Artefakt | Erwarteter Inhalt |
  |----------|-------------------|
  | `artifacts/traces.json` | PDO-Spans mit `"scope": "io.opentelemetry.contrib.php.pdo"` o. ä. |
  | `artifacts/trace-report-<RUN_ID>.json` | `"layer": "DB Query"` in Testfall-Spans vorhanden |
  | `artifacts/layer4/perfschema/per-test/` | Unterordner für jeden Testfall |
  | `artifacts/layer4/perfschema/` | Globaler Snapshot weiterhin vorhanden (parallele Extraktion) |
  | Jaeger UI | Vollständige Trace-Hierarchie: Playwright → HTTP → PHP → PDO |
