<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# A8: Automatisierte Auswertung — Script-Design + PerfSchema-Integration — Analyse

## 1. Fakten

### 1.1 Datenquellen (2.7.1)

#### 1.1.1 Primaere Datenquelle: `/artifacts/traces.json` (OTel Collector File-Exporter)

**Format:** OTLP JSON (OpenTelemetry Protocol, JSON-Encoding). Der OTel Collector File-Exporter schreibt Spans im OTLP-JSON-Format, wobei jede Zeile ein komplettes `ExportTraceServiceRequest`-Objekt darstellt (JSON Lines / NDJSON).

**Aktuelle Collector-Konfiguration** (aus `otel/otel-collector-config.yaml`):

```yaml
exporters:
  file:
    path: /artifacts/traces.json
```

**OTLP JSON Struktur (pro Zeile):**

```json
{
  "resourceSpans": [
    {
      "resource": {
        "attributes": [
          {"key": "service.name", "value": {"stringValue": "webtrees"}},
          {"key": "telemetry.sdk.language", "value": {"stringValue": "php"}}
        ]
      },
      "scopeSpans": [
        {
          "scope": {
            "name": "io.opentelemetry.contrib.php.pdo",
            "version": "1.0.0"
          },
          "spans": [
            {
              "traceId": "abcdef1234567890abcdef1234567890",
              "spanId": "1234567890abcdef",
              "parentSpanId": "fedcba0987654321",
              "name": "PDO::query",
              "kind": 3,
              "startTimeUnixNano": "1743260400000000000",
              "endTimeUnixNano": "1743260400045000000",
              "attributes": [
                {"key": "db.statement", "value": {"stringValue": "SELECT ..."}},
                {"key": "db.system", "value": {"stringValue": "mysql"}},
                {"key": "test.run_id", "value": {"stringValue": "a1b2c3d4-..."}},
                {"key": "test.case_id", "value": {"stringValue": "homepage loads without errors"}}
              ],
              "status": {"code": 0}
            }
          ]
        }
      ]
    }
  ]
}
```

**Wichtige Felder fuer die Auswertung:**

| Feld | Pfad im JSON | Zweck |
|---|---|---|
| Service-Name | `.resourceSpans[].resource.attributes[] | select(.key=="service.name")` | Layer-Zuordnung |
| SDK-Sprache | `.resourceSpans[].resource.attributes[] | select(.key=="telemetry.sdk.language")` | Browser vs. Server |
| Span-Name | `.resourceSpans[].scopeSpans[].spans[].name` | Operationsname |
| Trace-ID | `.resourceSpans[].scopeSpans[].spans[].traceId` | Trace-Gruppierung |
| Span-ID | `.resourceSpans[].scopeSpans[].spans[].spanId` | Hierarchie |
| Parent-Span-ID | `.resourceSpans[].scopeSpans[].spans[].parentSpanId` | Hierarchie |
| Start/End | `.startTimeUnixNano` / `.endTimeUnixNano` | Dauer in Nanosekunden |
| Scope-Name | `.resourceSpans[].scopeSpans[].scope.name` | Instrumentierungs-Paket |
| Attribute | `.resourceSpans[].scopeSpans[].spans[].attributes[]` | test.run_id, test.case_id, webtrees.action, db.statement |

**Zeiteinheit:** Nanosekunden (Unix-Epoch). Umrechnung in Millisekunden: `(endTimeUnixNano - startTimeUnixNano) / 1000000`.

**Dateigroesse:** Die Datei waechst unbegrenzt ueber alle Testlaeufe hinweg. Der File-Exporter hat keinen eingebauten Rotationsmechanismus. Fuer die Auswertung muss nach `test.run_id` gefiltert werden.

**Besonderheit NDJSON:** Jede Zeile ist ein eigenstaendiges JSON-Objekt. Standard-`jq` kann dies mit `--slurp` oder zeilenweise verarbeiten. Die Datei ist **kein** gueltiges JSON-Array.

#### 1.1.2 Ergaenzende Datenquelle: Performance Schema JSON

**Pfad:** `/artifacts/layer{3,4,5}/perfschema/*.json`

**Format:** JSON-Arrays, erzeugt durch das in A5 empfohlene Extraktions-Script `scripts/extract-perfschema.sh`. Das Format ist ein flaches Array of Objects (siehe A5, Abschnitt 2.6.4).

**Dateien pro Layer:**

| Datei | Inhalt |
|---|---|
| `statements_by_digest.json` | Top-50 Queries nach Gesamtzeit |
| `table_io_waits.json` | I/O-Wartezeiten pro Tabelle |
| `stages_global.json` | Query-Phasen (Parsing, Optimizing, Executing) |
| `transactions_global.json` | Transaktions-Statistiken |

**Zeiteinheit:** Bereits in Millisekunden umgerechnet (das Extraktions-Script aus A5 rechnet Picosekunden in ms um: `ROUND(SUM_TIMER_WAIT/1000000000, 2)`).

#### 1.1.3 Alternative Datenquelle: Jaeger API

**Endpunkt:** `http://jaeger:16686/api/traces`

**Relevante API-Aufrufe:**

```
# Traces nach Service und Tag filtern
GET http://jaeger:16686/api/traces?service=webtrees&tags=test.run_id%3Da1b2c3d4

# Einzelnen Trace abrufen
GET http://jaeger:16686/api/traces/{traceId}

# Services auflisten
GET http://jaeger:16686/api/services
```

**Jaeger API Antwortformat (Auszug):**

```json
{
  "data": [
    {
      "traceID": "abcdef1234567890",
      "spans": [
        {
          "traceID": "abcdef1234567890",
          "spanID": "1234567890abcdef",
          "operationName": "GET /tree/{tree}",
          "references": [
            {"refType": "CHILD_OF", "traceID": "...", "spanID": "..."}
          ],
          "startTime": 1743260400000000,
          "duration": 280000,
          "tags": [
            {"key": "test.run_id", "value": "a1b2c3d4-...", "type": "string"}
          ],
          "process": {
            "serviceName": "webtrees",
            "tags": [...]
          }
        }
      ]
    }
  ]
}
```

**Zeiteinheit Jaeger API:** Mikrosekunden (nicht Nanosekunden wie OTLP). `duration` ist direkt in Mikrosekunden angegeben.

#### 1.1.4 Bewertung: File-Exporter vs. Jaeger API

| Kriterium | File-Exporter (`traces.json`) | Jaeger API |
|---|---|---|
| Verfuegbarkeit | Immer (nach Collector-Flush) | Nur bei laufendem Jaeger-Container |
| Filterung nach `test.run_id` | Manuell (jq) | Nativ (Query-Parameter `tags=`) |
| Datenformat | OTLP JSON (NDJSON) | Jaeger-eigenes JSON |
| Hierarchie-Aufloesung | Manuell (parentSpanId matching) | Bereits aufgeloest (`references`) |
| Vollstaendigkeit | Garantiert (alle Spans) | Abhaengig von Jaeger-Speicher-Limits |
| Offline-Faehigkeit | Ja | Nein |
| Komplexitaet des Parsings | Hoeher (NDJSON, verschachtelte Struktur) | Geringer (flachere Struktur) |
| Abhaengigkeit | Keine (nur Dateisystem) | Laufender Jaeger-Container |
| `make down`-Resistenz | Ja (Datei bleibt) | Nein (In-Memory-Storage verloren) |

**Empfehlung: File-Exporter als primaere Quelle.** Die Offline-Faehigkeit und `make down`-Resistenz sind entscheidend fuer den Testkontext. Der Report kann nach dem Testlauf auch bei gestopptem Stack generiert werden. Die Jaeger API bleibt als optionale Alternative fuer interaktive Analyse (via Jaeger UI) erhalten.

### 1.2 Span-Quellen und Layer-Zuordnung (2.7.3)

Basierend auf den Ergebnissen von A1 (Boomerang), A3 (Apache OTel), A4 (MySQL Telemetry), A6 (Baggage) und A7 (PHP-Instrumentierung) ergeben sich folgende aktiven Span-Quellen:

| Quelle | service.name | telemetry.sdk.language | Scope-Name | Span-Beispiele |
|---|---|---|---|---|
| PHP Auto-PSR15 | `webtrees` | `php` | `io.opentelemetry.contrib.php.psr15` | `GET /tree/{tree}`, `POST /login` |
| PHP Auto-PDO | `webtrees` | `php` | `io.opentelemetry.contrib.php.pdo` | `PDO::query`, `PDO::exec` |
| PHP OTel-Spans-Modul | `webtrees` | `php` | `otel-spans` (custom) | Custom Spans mit `webtrees.action` |
| Boomerang Browser | `webtrees-browser` | `webjs` | `@opentelemetry/instrumentation-document-load` | `documentLoad`, `resourceFetch` |

**Nicht verfuegbare Span-Quellen** (gesicherte Ergebnisse aus A3, A4):

- **Apache httpd:** Kein OTel-Modul verfuegbar als Pre-Built Binary fuer Debian Bookworm (A3). Kein `service.name = "apache-httpd"`.
- **MySQL Server:** Telemetry Plugin ist Enterprise-only (A4). Kein `service.name = "mysql"`. MySQL-Observability ausschliesslich ueber Performance Schema (A5).

#### Korrelations-Attribute (aus A6)

Die Zuordnung von Spans zu Testlaeufen/Testfaellen erfolgt ueber Span-Attribute, die vom PHP OTel-Spans-Modul aus dem W3C Baggage-Header extrahiert und als Span-Attribute gesetzt werden:

| Attribut | Quelle | Scope |
|---|---|---|
| `test.run_id` | Baggage → OTel-Spans-Modul → setAttribute | Pro Testlauf (UUID) |
| `test.case_id` | Baggage → OTel-Spans-Modul → setAttribute | Pro Testfall (Percent-Encoded Title) |

**Wichtig:** Diese Attribute sind nur auf PHP-Spans (`service.name = "webtrees"`) vorhanden. Boomerang-Spans (`service.name = "webtrees-browser"`) haben diese Attribute **nicht** (siehe A6, Abschnitt 2.5, Risiko "Boomerang-Spans nicht korrelierbar"). Boomerang-Spans koennen nur ueber temporale Korrelation (Zeitfenster) einem Testfall zugeordnet werden.

### 1.3 OTLP JSON Parsing mit jq

#### Alle Spans eines Testlaufs extrahieren

```bash
# Aus NDJSON (traces.json) alle Spans mit test.run_id=X extrahieren
jq -s '
  [.[] | .resourceSpans[] |
    .resource as $res |
    .scopeSpans[] |
    .scope as $scope |
    .spans[] |
    select(.attributes[]? | select(.key == "test.run_id" and .value.stringValue == "'"$RUN_ID"'")) |
    {
      traceId: .traceId,
      spanId: .spanId,
      parentSpanId: .parentSpanId,
      name: .name,
      startNano: (.startTimeUnixNano | tonumber),
      endNano: (.endTimeUnixNano | tonumber),
      durationMs: (((.endTimeUnixNano | tonumber) - (.startTimeUnixNano | tonumber)) / 1000000),
      serviceName: ($res.attributes[] | select(.key == "service.name") | .value.stringValue),
      scope: $scope.name,
      attributes: [.attributes[] | {(.key): .value.stringValue}] | add
    }
  ]
' /artifacts/traces.json
```

**Anmerkung:** `startTimeUnixNano` und `endTimeUnixNano` werden im OTLP JSON als Strings serialisiert (nicht als Zahlen), da JSON-Number nur 53 Bit Praezision hat und Nanosekunden-Timestamps 64-Bit-Integer erfordern. `jq` konvertiert mit `tonumber` und verliert bei Werten >2^53 an Praezision. In der Praxis sind aktuelle Unix-Nanosekunden-Timestamps (~1.7e18) innerhalb der sicheren Range von IEEE 754 double precision (~9.0e18).

#### Hierarchische Darstellung aufbauen

```bash
# Root-Spans identifizieren (parentSpanId leer oder absent)
jq '[.[] | select(.parentSpanId == "" or .parentSpanId == null)]' spans.json

# Children eines Spans finden
jq '[.[] | select(.parentSpanId == "'"$PARENT_SPAN_ID"'")]' spans.json
```

#### Boomerang-Spans ueber Zeitfenster korrelieren

Da Boomerang-Spans kein `test.run_id`-Attribut haben, muessen sie ueber den Zeitraum des Testlaufs gefiltert werden:

```bash
# Zeitfenster des Testlaufs bestimmen (aus PHP-Spans)
START=$(jq -s '[.[] | .startNano] | min' php_spans.json)
END=$(jq -s '[.[] | .endNano] | max' php_spans.json)

# Boomerang-Spans im Zeitfenster
jq -s --argjson start "$START" --argjson end "$END" '
  [.[] | .resourceSpans[] |
    .resource as $res |
    select($res.attributes[] | select(.key == "service.name" and .value.stringValue == "webtrees-browser")) |
    .scopeSpans[].spans[] |
    select((.startTimeUnixNano | tonumber) >= $start and (.startTimeUnixNano | tonumber) <= $end)
  ]
' /artifacts/traces.json
```

### 1.4 OTLP JSON Parsing mit Python

```python
import json
from dataclasses import dataclass
from typing import Optional

@dataclass
class Span:
    trace_id: str
    span_id: str
    parent_span_id: Optional[str]
    name: str
    start_ns: int
    end_ns: int
    duration_ms: float
    service_name: str
    scope: str
    attributes: dict

def parse_otlp_ndjson(path: str, run_id: str) -> list[Span]:
    """Parse OTLP NDJSON und filtere nach test.run_id."""
    spans = []
    with open(path) as f:
        for line in f:
            data = json.loads(line)
            for rs in data.get("resourceSpans", []):
                resource_attrs = {
                    a["key"]: a["value"].get("stringValue", "")
                    for a in rs.get("resource", {}).get("attributes", [])
                }
                service_name = resource_attrs.get("service.name", "unknown")

                for ss in rs.get("scopeSpans", []):
                    scope_name = ss.get("scope", {}).get("name", "unknown")
                    for span_data in ss.get("spans", []):
                        attrs = {
                            a["key"]: a["value"].get("stringValue", "")
                            for a in span_data.get("attributes", [])
                        }
                        if attrs.get("test.run_id") != run_id:
                            continue

                        start_ns = int(span_data["startTimeUnixNano"])
                        end_ns = int(span_data["endTimeUnixNano"])
                        spans.append(Span(
                            trace_id=span_data["traceId"],
                            span_id=span_data["spanId"],
                            parent_span_id=span_data.get("parentSpanId"),
                            name=span_data["name"],
                            start_ns=start_ns,
                            end_ns=end_ns,
                            duration_ms=(end_ns - start_ns) / 1_000_000,
                            service_name=service_name,
                            scope=scope_name,
                            attributes=attrs,
                        ))
    return spans
```

### 1.5 Performance Schema JSON Format (aus A5)

Das durch `scripts/extract-perfschema.sh` erzeugte JSON hat folgende Struktur:

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

**Korrelation PerfSchema ↔ Trace:** Siehe Abschnitt 1.7.

### 1.6 Gewuenschtes Ausgabeformat (2.7.2)

Angepasst an die tatsaechlich verfuegbaren Datenquellen (kein Apache-Span, kein MySQL-Server-Span):

```
=== Testlauf: a1b2c3d4 (2026-03-29T14:30:00Z) ===

Testfall: homepage loads without errors
  Browser (RUM):       120ms  [webtrees-browser / documentLoad]
  PHP Backend:         280ms  [webtrees / opentelemetry-auto-psr15]
    +-- webtrees.action: tree_page  [otel-spans]
    +-- DB Query:        45ms  SELECT ... FROM wt_individuals  [auto-pdo]
    +-- DB Query:        12ms  SELECT ... FROM wt_name         [auto-pdo]
    +-- DB Query:         8ms  SELECT ... FROM wt_dates        [auto-pdo]

Testfall: search results load time
  Browser (RUM):        85ms  [webtrees-browser / documentLoad]
  PHP Backend:         520ms  [webtrees / opentelemetry-auto-psr15]
    +-- webtrees.action: search_general  [otel-spans]
    +-- DB Query:       180ms  SELECT ... FROM wt_individuals  [auto-pdo]
    +-- DB Query:        95ms  SELECT ... FROM wt_name         [auto-pdo]
    +-- DB Query:        42ms  SELECT ... FROM wt_places       [auto-pdo]

--- Performance Schema (Testlauf-Aggregat) ---
Top SQL by Latenz:
  1. SELECT ... FROM wt_individuals WHERE ...  avg=12ms  calls=847  rows=4230
  2. SELECT ... FROM wt_name WHERE ...         avg=8ms   calls=1203 rows=2406
  3. SELECT ... FROM wt_places WHERE ...       avg=6ms   calls=312  rows=1560

Table I/O:
  wt_individuals:  reads=4230  writes=0  total_wait=9.8s
  wt_name:         reads=2406  writes=0  total_wait=5.2s
  wt_dates:        reads=1890  writes=0  total_wait=3.1s

Warnungen:
  - 3 Queries mit Full Table Scan
  - 0 Queries ohne Index
```

**Unterschiede zum Prompt-Beispiel (2.7.2):**

1. **Kein "Apache httpd"-Layer** — kein Apache OTel-Modul verfuegbar (A3).
2. **Kein "MySQL Server"-Layer** — kein Telemetry Plugin in Community Edition (A4).
3. **Kein "Total (E2E)"** als Summe ueber Layer — da Browser und PHP separate Traces sind (nicht Parent-Child), waere eine Summierung irrefuehrend. Die PHP-Backend-Dauer ist der zuverlaessige E2E-Indikator auf Server-Seite.
4. **Performance Schema als Testlauf-Aggregat** — nicht pro Testfall, da PerfSchema keine Testfall-Korrelation hat (kein `test.case_id` in MySQL).

### 1.7 Korrelation Performance Schema ↔ Traces (2.7.5)

#### Korrelationsstufen

| Stufe | Methode | Machbar? | Genauigkeit | Aufwand |
|---|---|---|---|---|
| Direkte Trace-ID | `TRACE_ID` in PerfSchema | **Nein** (Enterprise-only, A5) | Exakt | N/A |
| Thread-ID | `CONNECTION_ID()` ↔ `PROCESSLIST_ID` | **Ja, aber aufwaendig** (A5) | Pro Connection | Hoch |
| Temporal | Zeitfenster Testlauf ↔ `FIRST_SEEN`/`LAST_SEEN` | **Ja** | Testlauf-Aggregat | Gering |
| Digest-Text | `db.statement` (PDO-Span) ↔ `DIGEST_TEXT` (PerfSchema) | **Ja** | Pro Query-Typ | Mittel |
| Nur Aggregat | PerfSchema = Gesamtstatistik des Testlaufs | **Ja** | Kein Testfall-Bezug | Minimal |

**Empfehlung: Aggregat + Digest-Text-Matching.**

Die Performance-Schema-Daten werden als Testlauf-Aggregat dargestellt (Bottom-Section des Reports). Der `TRUNCATE` vor Testlauf-Beginn (A5, Abschnitt 2.6.3) garantiert, dass die PerfSchema-Daten exakt den Testlauf abdecken.

Zusaetzlich: Das Digest-Text-Matching ermoeglicht eine Verknuepfung zwischen PDO-Span-Attributen (`db.statement`) und PerfSchema-Eintraegen (`DIGEST_TEXT`). MySQL normalisiert Query-Texte (Parameter durch `?` ersetzt), waehrend PDO-Spans den vollstaendigen Query-Text enthalten. Ein Exact-Match ist daher nicht moeglich — stattdessen Substring-Matching auf dem Tabellennamen.

**Korrelation via Digest-Text (Beispiel):**

```
PDO-Span:     db.statement = "SELECT `i_id`, `i_gedcom` FROM `wt_individuals` WHERE `i_file` = 1"
PerfSchema:   DIGEST_TEXT  = "SELECT `i_id` , `i_gedcom` FROM `wt_individuals` WHERE `i_file` = ?"
```

Matching-Strategie: Extraktion des Tabellennamens aus `db.statement` (Regex `FROM\s+\x60?(\w+)\x60?`) und Abgleich mit `DIGEST_TEXT`.

---

## 2. Bewertung

### 2.1 Script-Technologie: bash+jq vs. Python (2.7.4)

#### bash+jq

**Vorteile:**

- Keine zusaetzliche Runtime-Abhaengigkeit (jq ist in den meisten Containern/Systemen verfuegbar oder trivial zu installieren)
- Konsistent mit den bestehenden Scripts im Repo (`scripts/*.sh`)
- Direkte Integration in Makefile-Targets
- Streaming-faehig (jq verarbeitet NDJSON zeilenweise)

**Nachteile:**

- Komplexe JSON-Transformationen werden in jq schnell unleserlich
- Hierarchische Span-Aufloesung (Parent-Child-Baum) ist in jq umstaendlich
- Fehlerbehandlung in Bash ist fragil
- String-Manipulation (Percent-Decoding von `test.case_id`) ist in Bash muehsam
- Kein natuerlicher Datentyp fuer Baumstrukturen

**Aufwand:** ~200–300 Zeilen Bash/jq fuer den Basis-Report.

#### Python

**Vorteile:**

- Native JSON-Verarbeitung mit `json`-Standardbibliothek
- Datenstrukturen (Dictionaries, Listen, Dataclasses) fuer Span-Hierarchien
- Einfache String-Manipulation (`urllib.parse.unquote`)
- Bessere Fehlerbehandlung (Exceptions)
- Lesbarerer Code bei komplexer Logik

**Nachteile:**

- Zusaetzliche Abhaengigkeit (Python 3 muss verfuegbar sein)
- Nicht konsistent mit bestehenden Scripts (alle `.sh`)
- Aufwaendigerer Aufruf aus Makefile (`python3 scripts/trace-report.py`)

**Aufwand:** ~150–200 Zeilen Python fuer den Basis-Report.

#### Python-Verfuegbarkeit im Stack

| Container | Python vorhanden? | Version |
|---|---|---|
| `webtrees` (php:8.5-apache) | Nein (Debian Bookworm Minimal) | — |
| `playwright` (node:22-bookworm) | Ja (Debian Bookworm) | Python 3.11 |
| Host-System (Fedora) | Ja | Python 3.12+ |

Das Auswertungs-Script laeuft **auf dem Host** (nicht im Container), da es nach dem Testlauf auf `/artifacts/` zugreift, das als Host-Verzeichnis gemountet ist. Auf Fedora ist Python 3 immer verfuegbar.

**Alternative:** Das Script koennte auch im `playwright`-Container laufen, da dort `/artifacts` als Volume gemountet ist und Python 3 verfuegbar ist.

#### Bewertungsmatrix

| Kriterium | bash+jq | Python |
|---|---|---|
| Lesbarkeit | Mittel | Hoch |
| Wartbarkeit | Niedrig | Hoch |
| Abhaengigkeiten | Minimal (jq) | Python 3 |
| Konsistenz mit Repo | Hoch | Niedrig |
| Hierarchische Span-Verarbeitung | Schwierig | Einfach |
| Ausfuehrungsgeschwindigkeit | Hoch (Streaming) | Hoch (In-Memory) |
| Erweiterbarkeit (Baseline-Vergleich) | Gering | Hoch |

### 2.2 Machbarkeit

| Vorhaben | Machbar? | Aufwand | Risiko |
|---|---|---|---|
| Spans nach `test.run_id` filtern | **Ja** | Gering | Niedrig (Attribut muss gesetzt sein → A7-Abhaengigkeit) |
| Layer-Zuordnung via `service.name` | **Ja** | Gering | Niedrig |
| Hierarchische Darstellung (Parent-Child) | **Ja** | Mittel | Niedrig |
| Boomerang-Spans temporal korrelieren | **Ja** | Mittel | Mittel (Zeitfenster-Ungenauigkeit) |
| PerfSchema-Daten integrieren | **Ja** | Gering | Niedrig (JSON-Dateien lesen) |
| Digest-Text-Matching (PDO ↔ PerfSchema) | **Ja** | Mittel | Mittel (Normalisierungsdifferenzen) |
| Makefile-Target | **Ja** | Minimal | Niedrig |
| JSON-Ausgabe unter `/artifacts/` | **Ja** | Gering | Niedrig |

### 2.3 Risiken

1. **`test.run_id` nicht gesetzt:** Wenn das OTel-Spans-Modul (A7) nicht installiert ist oder Baggage-Propagation nicht funktioniert, sind keine Spans dem Testlauf zuordenbar. Das Auswertungs-Script muss diesen Fall erkennen und eine klare Fehlermeldung ausgeben.

2. **Grosse `traces.json`:** Die Datei waechst ueber alle Testlaeufe hinweg. Bei vielen Testlaeufen kann das Parsing langsam werden. Mitigation: `traces.json` vor jedem Testlauf loeschen oder rotieren (optional via Makefile-Target).

3. **NDJSON-Inkompatibilitaet:** Der OTel Collector File-Exporter schreibt NDJSON (eine JSON-Zeile pro Batch). Aeltere `jq`-Versionen (< 1.6) unterstuetzen `--slurp` mit NDJSON nicht zuverlaessig. Python `json.loads()` pro Zeile ist robust.

4. **Nanosekunden-Praezision:** Bei jq kann `tonumber` auf Nanosekunden-Timestamps an IEEE-754-Praezisionsgrenzen stossen. In der Praxis sind aktuelle Timestamps (~1.7e18) noch sicher, aber bei Differenzbildung (Dauer) koennen Rundungsfehler im Sub-Mikrosekunden-Bereich auftreten. Fuer Millisekunden-Darstellung irrelevant.

5. **PerfSchema-Dateien fehlen:** Wenn `scripts/extract-perfschema.sh` nicht gelaufen ist, fehlen die PerfSchema-JSON-Dateien. Das Auswertungs-Script muss den PerfSchema-Abschnitt dann ueberspringen (nicht abbrechen).

6. **Boomerang-Spans ohne Korrelation:** Temporale Korrelation ist ungenau. Bei kurz aufeinander folgenden Testfaellen koennen Boomerang-Spans dem falschen Testfall zugeordnet werden. Da `workers: 1` konfiguriert ist (A6), ist Ueberlappung unwahrscheinlich, aber nicht ausgeschlossen.

---

## 3. Empfehlung

### 3.1 Script-Technologie: Python

**Beggruendung:**

1. Die Kern-Aufgabe (Hierarchische Span-Verarbeitung, JSON-Transformation, String-Matching) ist in Python signifikant lesbarer und wartbarer als in bash+jq.
2. Python 3 ist auf dem Host-System (Fedora) und im Playwright-Container verfuegbar.
3. Die spaetere Erweiterbarkeit (Baseline-Vergleich, A5 Phase 3) profitiert stark von Python-Datenstrukturen.
4. Keine externen Python-Pakete noetig — nur Standardbibliothek (`json`, `sys`, `os`, `datetime`, `urllib.parse`, `dataclasses`, `collections`).

**Kompromiss zur Konsistenz:** Das Makefile-Target ruft ein Bash-Wrapper-Script auf, das Python 3 aufruft:

```bash
#!/usr/bin/env bash
# scripts/trace-report.sh
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

exec python3 "$(dirname "$0")/trace-report.py" "$@"
```

### 3.2 Architektur des Auswertungs-Scripts

```
scripts/trace-report.py
  |
  +-- Eingabe:
  |     --run-id <UUID>                   (erforderlich)
  |     --traces-file /artifacts/traces.json  (Default)
  |     --perfschema-dir /artifacts/layerN/perfschema/  (optional)
  |     --output-json /artifacts/trace-report.json  (optional)
  |     --layer <3|4|5>                   (bestimmt PerfSchema-Pfad)
  |
  +-- Verarbeitung:
  |     1. OTLP NDJSON parsen
  |     2. Spans nach test.run_id filtern
  |     3. Spans nach test.case_id gruppieren
  |     4. Hierarchie aufloesen (parentSpanId -> Children)
  |     5. Layer zuordnen (service.name + scope)
  |     6. Boomerang-Spans temporal korrelieren
  |     7. PerfSchema-Daten laden (falls vorhanden)
  |     8. Digest-Text-Matching (optional)
  |
  +-- Ausgabe:
        1. Konsole (formatierter Text-Report)
        2. JSON-Datei unter /artifacts/ (maschinenlesbar)
```

### 3.3 Konkretes Script-Design

#### Hauptfunktionen

```python
#!/usr/bin/env python3
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Trace-Report: Auswertung von OTel-Traces und PerfSchema-Daten pro Testlauf."""

import json
import sys
import os
from dataclasses import dataclass, field, asdict
from datetime import datetime, timezone
from urllib.parse import unquote
from collections import defaultdict
from typing import Optional


@dataclass
class Span:
    trace_id: str
    span_id: str
    parent_span_id: Optional[str]
    name: str
    start_ns: int
    end_ns: int
    duration_ms: float
    service_name: str
    scope: str
    attributes: dict = field(default_factory=dict)
    children: list = field(default_factory=list)


def parse_traces(traces_path: str, run_id: str) -> list[Span]:
    """Parse OTLP NDJSON, filtere nach test.run_id."""
    spans = []
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
                        if attrs.get("test.run_id") != run_id:
                            continue
                        start = int(s["startTimeUnixNano"])
                        end = int(s["endTimeUnixNano"])
                        spans.append(Span(
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
    return spans


def _extract_attrs(attr_list: list) -> dict:
    """OTLP-Attribute-Liste in flaches dict konvertieren."""
    result = {}
    for a in attr_list:
        val = a.get("value", {})
        result[a["key"]] = (
            val.get("stringValue")
            or val.get("intValue")
            or val.get("doubleValue")
            or val.get("boolValue")
            or str(val)
        )
    return result


def group_by_test_case(spans: list[Span]) -> dict[str, list[Span]]:
    """Spans nach test.case_id gruppieren."""
    groups = defaultdict(list)
    for span in spans:
        case_id = span.attributes.get("test.case_id", "(unbekannt)")
        case_id = unquote(case_id)
        groups[case_id].append(span)
    return dict(groups)


def build_hierarchy(spans: list[Span]) -> list[Span]:
    """Span-Liste in Baumstruktur umwandeln. Gibt Root-Spans zurueck."""
    by_id = {s.span_id: s for s in spans}
    roots = []
    for span in spans:
        if span.parent_span_id and span.parent_span_id in by_id:
            by_id[span.parent_span_id].children.append(span)
        else:
            roots.append(span)
    # Sortiere Children nach Startzeit
    for span in spans:
        span.children.sort(key=lambda s: s.start_ns)
    roots.sort(key=lambda s: s.start_ns)
    return roots


def classify_span(span: Span) -> str:
    """Span einem Display-Layer zuordnen."""
    if span.service_name == "webtrees-browser":
        return "Browser (RUM)"
    if span.scope and "pdo" in span.scope:
        return "DB Query"
    if span.scope and "psr15" in span.scope:
        return "PHP Backend"
    if span.scope and "otel-spans" in span.scope:
        return "webtrees Custom"
    if span.service_name == "webtrees":
        return "PHP"
    return "Unknown"


def load_perfschema(perfschema_dir: str) -> dict:
    """PerfSchema-JSON-Dateien laden."""
    result = {}
    for name in ["statements_by_digest", "table_io_waits"]:
        path = os.path.join(perfschema_dir, f"{name}.json")
        if os.path.exists(path):
            with open(path) as f:
                result[name] = json.load(f)
    return result


def format_report(
    run_id: str,
    test_cases: dict[str, list[Span]],
    perfschema: dict,
) -> str:
    """Formatierten Text-Report erzeugen."""
    lines = []

    # Zeitstempel aus fruehestem Span
    all_spans = [s for spans in test_cases.values() for s in spans]
    if all_spans:
        earliest = min(s.start_ns for s in all_spans)
        ts = datetime.fromtimestamp(earliest / 1e9, tz=timezone.utc)
        ts_str = ts.strftime("%Y-%m-%dT%H:%M:%SZ")
    else:
        ts_str = "(unbekannt)"

    lines.append(f"=== Testlauf: {run_id} ({ts_str}) ===")
    lines.append("")

    for case_id, spans in test_cases.items():
        lines.append(f"Testfall: {case_id}")
        roots = build_hierarchy(spans)
        for root in roots:
            _format_span_tree(root, lines, indent=2)
        lines.append("")

    # PerfSchema-Abschnitt
    if perfschema:
        lines.append("--- Performance Schema (Testlauf-Aggregat) ---")
        stmts = perfschema.get("statements_by_digest", [])
        if stmts:
            lines.append("Top SQL by Latenz:")
            for i, s in enumerate(stmts[:10], 1):
                digest = _truncate(s.get("digest_text", "?"), 55)
                avg = s.get("avg_ms", 0)
                count = s.get("count", 0)
                rows = s.get("rows_examined", 0)
                lines.append(
                    f"  {i:2d}. {digest:<55s}  "
                    f"avg={avg:.1f}ms  calls={count}  rows={rows}"
                )
            lines.append("")

        table_io = perfschema.get("table_io_waits", [])
        if table_io:
            lines.append("Table I/O:")
            for t in table_io[:10]:
                name = t.get("table_name", t.get("object_name", "?"))
                reads = t.get("count_read", t.get("count_fetch", 0))
                writes = t.get("count_write", 0)
                total = t.get("total_wait_ms", t.get("total_ms", 0))
                lines.append(
                    f"  {name:<25s}  reads={reads:<6d}  "
                    f"writes={writes:<5d}  total_wait={total:.1f}ms"
                )
            lines.append("")

        # Warnungen
        warnings = []
        for s in stmts:
            if s.get("full_scans", 0) > 0:
                warnings.append(
                    f"Full Table Scan: {_truncate(s.get('digest_text', ''), 60)}"
                )
            if s.get("no_index", 0) > 0:
                warnings.append(
                    f"Query ohne Index: {_truncate(s.get('digest_text', ''), 60)}"
                )
            if s.get("tmp_disk_tables", 0) > 0:
                warnings.append(
                    f"Temp-Tabelle auf Disk: "
                    f"{_truncate(s.get('digest_text', ''), 60)}"
                )
        if warnings:
            lines.append("Warnungen:")
            for w in warnings:
                lines.append(f"  - {w}")
        else:
            lines.append("Warnungen: keine")

    return "\n".join(lines)


def _format_span_tree(span: Span, lines: list, indent: int) -> None:
    """Span-Baum rekursiv formatieren."""
    prefix = " " * indent
    layer = classify_span(span)
    dur = f"{span.duration_ms:.0f}ms"
    scope_short = span.scope.rsplit(".", 1)[-1] if span.scope else ""

    if layer == "DB Query":
        stmt = span.attributes.get("db.statement", span.name)
        stmt = _truncate(stmt, 50)
        lines.append(f"{prefix}+-- DB Query:  {dur:>8s}  {stmt}  [{scope_short}]")
    elif layer == "webtrees Custom":
        action = span.attributes.get("webtrees.action", span.name)
        lines.append(f"{prefix}+-- webtrees.action: {action}  [{scope_short}]")
    elif layer == "PHP Backend":
        lines.append(f"{prefix}{layer}:  {dur:>8s}  [{scope_short}]")
    elif layer == "Browser (RUM)":
        lines.append(f"{prefix}{layer}:  {dur:>8s}  [{scope_short}]")
    else:
        lines.append(f"{prefix}{span.name}:  {dur:>8s}  [{scope_short}]")

    for child in span.children:
        _format_span_tree(child, lines, indent + 4)


def _truncate(s: str, maxlen: int) -> str:
    return s[:maxlen] + "..." if len(s) > maxlen else s
```

#### CLI-Interface

```python
def main():
    import argparse
    parser = argparse.ArgumentParser(description="Trace-Report Generator")
    parser.add_argument("--run-id", required=True, help="test.run_id UUID")
    parser.add_argument(
        "--traces-file",
        default="/artifacts/traces.json",
        help="Pfad zur OTLP NDJSON Datei",
    )
    parser.add_argument(
        "--layer",
        choices=["3", "4", "5"],
        default=None,
        help="Layer fuer PerfSchema-Pfad",
    )
    parser.add_argument(
        "--perfschema-dir",
        default=None,
        help="Expliziter PerfSchema-Pfad (ueberschreibt --layer)",
    )
    parser.add_argument(
        "--output-json",
        default=None,
        help="Pfad fuer JSON-Report-Ausgabe",
    )
    args = parser.parse_args()

    # PerfSchema-Pfad bestimmen
    ps_dir = args.perfschema_dir
    if ps_dir is None and args.layer:
        ps_dir = f"/artifacts/layer{args.layer}/perfschema"

    # Traces parsen
    if not os.path.exists(args.traces_file):
        print(f"FEHLER: {args.traces_file} nicht gefunden.", file=sys.stderr)
        sys.exit(1)

    spans = parse_traces(args.traces_file, args.run_id)
    if not spans:
        print(
            f"WARNUNG: Keine Spans mit test.run_id={args.run_id} gefunden.",
            file=sys.stderr,
        )
        print("Moegliche Ursachen:")
        print("  - OTel-Spans-Modul (A7) nicht aktiv")
        print("  - Baggage-Propagation nicht konfiguriert (A6)")
        print(f"  - Falscher run_id: {args.run_id}")
        sys.exit(1)

    # Gruppierung
    test_cases = group_by_test_case(spans)

    # PerfSchema laden
    perfschema = {}
    if ps_dir and os.path.isdir(ps_dir):
        perfschema = load_perfschema(ps_dir)

    # Report generieren
    report = format_report(args.run_id, test_cases, perfschema)
    print(report)

    # Optional: JSON-Ausgabe
    if args.output_json:
        json_report = {
            "run_id": args.run_id,
            "test_cases": {
                case_id: [asdict(s) for s in spans_list]
                for case_id, spans_list in test_cases.items()
            },
            "perfschema": perfschema,
        }
        with open(args.output_json, "w") as f:
            json.dump(json_report, f, indent=2, default=str)
        print(f"\nJSON-Report: {args.output_json}")
```

### 3.4 Makefile-Integration (2.7.4)

```makefile
trace-report: ## Trace-Report fuer einen Testlauf generieren (RUN_ID=... LAYER=...)
    @if [ -z "$(RUN_ID)" ]; then \
        echo "Fehler: RUN_ID nicht gesetzt. Aufruf: make trace-report RUN_ID=<uuid> [LAYER=3|4|5]"; \
        exit 1; \
    fi
    python3 scripts/trace-report.py \
        --run-id "$(RUN_ID)" \
        --traces-file artifacts/traces.json \
        $(if $(LAYER),--layer $(LAYER)) \
        --output-json artifacts/trace-report-$(RUN_ID).json
```

**Aufruf:**

```bash
# Nur Trace-Report (ohne PerfSchema)
make trace-report RUN_ID=a1b2c3d4-e5f6-7890-abcd-ef1234567890

# Mit PerfSchema-Daten aus Layer 3
make trace-report RUN_ID=a1b2c3d4 LAYER=3

# Mit explizitem PerfSchema-Pfad
python3 scripts/trace-report.py --run-id a1b2c3d4 --perfschema-dir artifacts/layer4/perfschema
```

**Integration in Test-Targets (spaetere Phase):**

```makefile
test-e2e:
    @RUN_ID=$$(uuidgen); \
    echo "Testlauf: $$RUN_ID"; \
    TEST_RUN_ID=$$RUN_ID $(COMPOSE) exec playwright npx playwright test \
        --config=/tests/e2e/playwright.config.ts; \
    echo "---"; \
    python3 scripts/trace-report.py --run-id $$RUN_ID --layer 4 \
        --output-json artifacts/trace-report-$$RUN_ID.json || true
```

Das `|| true` stellt sicher, dass ein Fehler im Report-Script den Test-Exit-Code nicht beeinflusst.

### 3.5 Sequenz im Testlauf

Die Einbettung des Auswertungs-Scripts in den Gesamtablauf:

```
1. Makefile-Target (z.B. test-e2e):
   a) RUN_ID generieren (uuidgen)
   b) PerfSchema TRUNCATE    [scripts/truncate-perfschema.sh, A5]
   c) Testlauf ausfuehren    [Playwright/PHPUnit, TEST_RUN_ID als Env]
   d) PerfSchema extrahieren [scripts/extract-perfschema.sh layerN, A5]
   e) Trace-Report generieren [scripts/trace-report.py --run-id $RUN_ID]

2. Alternativ als separate Targets:
   a) make test-e2e           (Schritte b+c)
   b) make perfschema-extract (Schritt d, A5)
   c) make trace-report RUN_ID=... LAYER=4  (Schritt e)
```

**Empfehlung: Separate Targets in Phase 1.** Die lose Kopplung ermoeglicht:
- Testlauf ohne Report (schneller Feedback-Loop)
- Report nachtraeglich generieren
- PerfSchema-Extraktion optional

### 3.6 Ausgabedateien

| Datei | Inhalt | Erzeuger |
|---|---|---|
| `/artifacts/traces.json` | Alle Spans (NDJSON, OTel Collector) | OTel Collector File-Exporter |
| `/artifacts/layer{3,4,5}/perfschema/*.json` | PerfSchema-Daten (JSON) | `scripts/extract-perfschema.sh` (A5) |
| `/artifacts/trace-report-<run_id>.json` | Strukturierter Report (JSON) | `scripts/trace-report.py` |
| Konsole (stdout) | Menschenlesbarer Report (Text) | `scripts/trace-report.py` |

### 3.7 Phasenplan

#### Phase 1: Basis-Report (Quick Win)

**Aufwand: ~4 Stunden**

1. `scripts/trace-report.py` — OTLP NDJSON parsen, nach `test.run_id` filtern, hierarchischer Text-Report
2. `scripts/trace-report.sh` — Bash-Wrapper fuer Python-Aufruf
3. Makefile-Target `trace-report`
4. Keine PerfSchema-Integration in dieser Phase

**Voraussetzungen:**
- A7 (OTel-Spans-Modul) muss `test.run_id` und `test.case_id` als Span-Attribute setzen
- A6 (Baggage-Propagation) muss konfiguriert sein
- `traces.json` muss existieren (OTel Collector + File-Exporter)

#### Phase 2: PerfSchema-Integration

**Aufwand: ~3 Stunden**

1. PerfSchema-JSON laden und in Report integrieren (Bottom-Section)
2. Top-N-Queries, Table I/O, Warnungen
3. JSON-Report-Ausgabe erweitern

**Voraussetzungen:**
- A5 (PerfSchema-Extraktion) muss implementiert sein

#### Phase 3: Boomerang-Korrelation

**Aufwand: ~2 Stunden**

1. Boomerang-Spans (`service.name = "webtrees-browser"`) ueber Zeitfenster korrelieren
2. Browser-RUM-Zeiten in den per-Testfall-Report integrieren

**Voraussetzungen:**
- A1/A2 (Boomerang-Integration) muss implementiert sein

#### Phase 4: Digest-Text-Matching und Baseline-Vergleich

**Aufwand: ~6 Stunden**

1. PDO-Span `db.statement` ↔ PerfSchema `DIGEST_TEXT` Matching
2. Baseline-Vergleich (relative Schwellwerte aus A5, Phase 3)
3. Exit-Code != 0 bei Regression (CI-Gate)

---

## 4. Offene Punkte

### 4.1 Vor Implementierung zu klaeren

1. **`traces.json` Rotation:** Soll die Datei vor jedem Testlauf geleert werden? Aktuell waechst sie unbegrenzt. Optionen:
   - (a) `truncate -s 0 artifacts/traces.json` im Makefile-Target vor dem Testlauf
   - (b) Kein Reset — das Script filtert nach `test.run_id`
   - (c) OTel Collector File-Exporter mit `rotation`-Config (erfordert Collector-Contrib-Features)

   Empfehlung: Option (b) fuer Einfachheit. Die Datei wird durch `make clean` geloescht.

2. **`run_id`-Weitergabe:** Wie wird die in uuidgen erzeugte `RUN_ID` vom Makefile an den Report-Aufruf weitergegeben? Das Makefile muss die UUID in einer Variable halten, die sowohl dem Testlauf als auch dem Report zur Verfuegung steht. Bei separaten `make`-Aufrufen muss der User die UUID manuell uebergeben.

3. **Python-Version:** Das Script nutzt `list[Span]` Type-Hints (Python 3.9+) und `match`-freie Syntax. Fedora hat Python 3.12+, Debian Bookworm (Playwright-Container) hat Python 3.11. Beide sind kompatibel.

4. **jq als Fallback:** Soll ein minimales bash+jq-Script als Fallback bereitgestellt werden, falls Python nicht verfuegbar ist? Empfehlung: Nein — auf Fedora und Debian Bookworm ist Python 3 Standard.

5. **File-Exporter Flush-Timing:** Der OTel Collector File-Exporter flusht Spans moeglicherweise nicht sofort auf Disk. Zwischen Testlauf-Ende und Report-Generierung muss sichergestellt sein, dass alle Spans geschrieben wurden. Der Collector flusht beim Batch-Export (Default: alle 5 Sekunden). In der Praxis reicht der Zeitabstand zwischen Testlauf-Ende und manuellem `make trace-report`-Aufruf aus. Bei automatischer Integration (Phase 2+) koennte ein `sleep 3` noetig sein.

6. **Playwright-HTML-Report-Integration:** Der Prompt fragt, ob der Trace-Report in den Playwright-HTML-Report eingebettet werden soll. Empfehlung: Nein. Der Playwright-Report hat ein eigenes Format und API. Eine Einbettung wuerde eine Playwright-Reporter-Extension erfordern (signifikanter Aufwand, geringer Mehrwert). Der Trace-Report bleibt ein separates Artefakt.

### 4.2 Abhaengigkeiten

7. **A7 (OTel-Spans-Modul):** Muss implementiert sein, damit `test.run_id` und `test.case_id` als Span-Attribute gesetzt werden. Ohne A7 kann das Script keine Spans einem Testlauf zuordnen.

8. **A6 (Baggage-Propagation):** Das Playwright-Fixture muss den `baggage`-Header setzen. Ohne A6 enthaelt kein Span Baggage-Attribute.

9. **A5 (PerfSchema-Extraktion):** Muss fuer die PerfSchema-Integration (Phase 2) implementiert sein. Ohne A5 funktioniert der Report trotzdem — aber ohne PerfSchema-Abschnitt.

10. **`opentelemetry-auto-psr15`:** Muss installiert sein (A7, Schritt 1), damit ein Root-Span pro HTTP-Request existiert. Ohne PSR-15-Instrumentierung gibt es keine Parent-Child-Hierarchie — PDO-Spans waeren alle Root-Spans.

### 4.3 Nicht weiter zu verfolgen

- **Jaeger API als primaere Quelle:** Die File-basierte Auswertung ist offline-faehig und unabhaengig vom Jaeger-Container-Status. Die Jaeger API ist fuer interaktive Analyse (Browser UI) gedacht, nicht fuer automatisierte Reports.
- **Playwright-Reporter-Extension:** Zu hoher Aufwand fuer den Mehrwert. Separate Artefakte sind ausreichend.
- **Real-Time-Streaming:** Ein Live-Report waehrend des Testlaufs (via Collector-Processor oder Webhook) waere technisch moeglich, aber der Aufwand steht in keinem Verhaeltnis zum Nutzen.

---

## Quellen

- OpenTelemetry Protocol (OTLP) Specification: https://opentelemetry.io/docs/specs/otlp/
- OpenTelemetry Collector File Exporter: https://github.com/open-telemetry/opentelemetry-collector-contrib/tree/main/exporter/fileexporter
- Jaeger API Documentation: https://www.jaegertracing.io/docs/latest/apis/
- jq Manual: https://jqlang.github.io/jq/manual/
- A5-Analyse: `docs/laufzeit_analyse/05_mysql_perfschema.md`
- A6-Analyse: `docs/laufzeit_analyse/06_baggage_propagation.md`
- A7-Analyse: `docs/laufzeit_analyse/07_php_instrumentierung.md`
- A4-Analyse: `docs/laufzeit_analyse/04_mysql_telemetry.md`
- A1-Analyse: `docs/laufzeit_analyse/01_boomerang_rum.md`
- A3-Analyse: `docs/laufzeit_analyse/03_apache_otel.md`
