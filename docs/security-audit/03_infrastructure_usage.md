<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Infrastruktur-Nutzung — Feedback-Kanäle für den Audit

**Teil von:** [tp_security-audit_spec.md](../tp_security-audit_spec.md)
**Vorangehend:** [02_threat_model.md](02_threat_model.md)

---

## 1 Zielbild

Der Audit ist **whitebox** — der Agent sieht Source, Tests und Laufzeit-Zustand. Das Alleinstellungsmerkmal gegenüber der Vorlage [`docs/php_security_audit_suggestion.md`](../php_security_audit_suggestion.md) ist, dass jeder Exploit-Versuch durch **mehrere parallele Rückkanäle** aus dem laufenden Fachtest-Container instrumentiert wird. Dieses Dokument listet die Kanäle auf und nennt pro Kanal: Datenquelle, Zugriff, Pre-Probe-Setup, Extraktion nach dem Probe, Integration in den Agentic-Loop.

| # | Kanal | Antwortet auf die Frage |
|---|---|---|
| 1 | OTel-Traces (Auto-Instrumentation + OtelSpansModule) | Welcher Handler, welche Middleware-Kette, welche PDO-Queries hat mein Probe ausgelöst? |
| 2 | PerfSchema-Extraktion | Welche SQL-Statements hat MySQL gesehen, in welchen Stages, mit welchen Parametern? |
| 3 | Coverage-Delta | Welche PHP-Code-Zeilen hat mein Probe neu erreicht (im Vergleich zu einem Baseline-Lauf)? |
| 4 | SecurityTraceMiddleware-Artefakte | Wie sah Request/Response auf PHP-Ebene aus, inkl. Redaction? |
| 5 | Apache-Access-Log / Error-Log | Wie sieht mein Probe aus Webserver-Sicht aus, inkl. Redirect-Verhalten und Fehlercodes? |
| 6 | webtrees-Application-Log (`data/log.txt`) | Welche Application-Level-Events hat mein Probe erzeugt? |
| 7 | MySQL `general_log` (optional, per Probe aktiviert) | Fallback bei PerfSchema-Lücken |
| 8 | Clover-XML-Coverage nach Layer-3-Test-Promotion | Für Regressionsnachweis: Deckt mein neuer Security-Test den Fix-Pfad ab? |

---

## 2 Kanal 1 — OTel-Traces

**Quelle:** Der Fachtest-Container sendet OTel-Traces via OTLP HTTP/Protobuf auf `http://otel-collector:4318`. Der Collector hat zwei Exporter:

- **Jaeger** (`:16686` Host) — interaktive Timeline-Ansicht
- **File** — `artifacts/traces.json` als NDJSON (ein JSON-Objekt pro Zeile, Schema: OTLP Protobuf → JSON)

Die Trace-Kette hat vier Schichten (siehe [`tp_infrastructure_spec.md`](../tp_infrastructure_spec.md) §N6):

1. PHP Auto-Instrumentation (PDO, PSR-15, PSR-18) — Service `webtrees`
2. OtelSpansModule — semantische Spans `webtrees.<action>`, Scope `otel-spans`
3. Browser-RUM (Boomerang) — Service `webtrees-browser` (nur relevant, wenn der Probe einen Browser involviert)
4. Playwright Root-Spans — Service `playwright-tests`

**Zugriff während eines Probe:**

```bash
# Vor dem Probe: File-Exporter-Datei leeren, sodass nur der Probe-Trace übrig bleibt
podman-compose exec webtrees sh -c 'truncate -s 0 /artifacts/traces.json'

# Probe ausführen (z. B. curl gegen den Endpunkt)
curl -s -o /dev/null -H "X-Audit-Probe: SEC-AUDIT-001-r1" http://localhost:8080/tree/demo/individual/X42

# Nach dem Probe: Datei einlesen
podman-compose exec webtrees cat /artifacts/traces.json > /tmp/trace-SEC-AUDIT-001-r1.json

# Oder direkt aus dem Host lesen (ist via Bind-Mount sichtbar)
cat artifacts/traces.json
```

**Extraktions-Skizze:** `scripts/trace-report.py` (bereits im Repo vorhanden) bietet eine 4-Stufen-Hierarchie. Der Security-Audit baut darauf eine Variante, die pro `trace_id` die Spans aufbereitet zu:

- Middleware-Kette (welche PSR-15-Middlewares haben den Request gesehen, in welcher Reihenfolge)
- Matched-Handler (der erste Span vom Typ `webtrees.<action>`)
- PDO-Queries (alle Spans vom Typ `pdo.*` innerhalb dieses Traces)
- Exception-Chain (Spans mit `status.code=ERROR`)
- Gesamtdauer (Root-Span-`duration_ns`)

**Korrelation mit dem Probe:** Jeder Probe setzt einen HTTP-Header `X-Audit-Probe: <task-id>-r<iteration>`. Die SecurityTraceMiddleware liest diesen Header und hängt ihn als Baggage an den Request-Root-Span (siehe [`05_security_trace_middleware.md`](05_security_trace_middleware.md)). Dadurch sind Probe und Trace eindeutig verknüpfbar.

---

## 3 Kanal 2 — PerfSchema-Extraktion

**Quelle:** MySQL 8.4 mit `--performance-schema-instrument='stage/%=ON'` und `events_stages_current`/`events_stages_history` aktiv (siehe `compose.yaml`, mysql-Service).

**Pre-Probe:** PerfSchema leeren, damit anschließend nur die vom Probe ausgelösten Events sichtbar sind:

```bash
podman-compose exec mysql mysql -uroot -pwebtrees_test webtrees_test -e '
  TRUNCATE performance_schema.events_statements_history;
  TRUNCATE performance_schema.events_statements_history_long;
  TRUNCATE performance_schema.events_stages_history;
  TRUNCATE performance_schema.events_stages_history_long;
'
```

Im Repo existiert bereits `scripts/truncate-perfschema.sh` mit Make-Target `make perfschema-truncate`.

**Post-Probe:** Die ausgeführten Statements extrahieren:

```bash
podman-compose exec mysql mysql -uroot -pwebtrees_test webtrees_test -e '
  SELECT SQL_TEXT, ROWS_EXAMINED, TIMER_WAIT/1000000 AS ms
  FROM performance_schema.events_statements_history
  WHERE SQL_TEXT IS NOT NULL
  ORDER BY EVENT_ID;' > /tmp/perfschema-SEC-AUDIT-001-r1.txt
```

Im Repo existiert bereits `scripts/extract-perfschema.sh` mit Make-Target `make perfschema-extract` — das Skript schreibt vier JSON-Dateien (`events_statements_history.json`, `events_stages_history.json`, `events_waits_history.json`, `events_transactions_history.json`) plus `summary.txt` nach `artifacts/mysql-perfschema/`.

**Warum wichtig für den Audit:** Die PHP-OTel-Auto-Instrumentation zeichnet prepared Statements auf, aber der Parameterwert ist oft `?`. PerfSchema zeigt das `SQL_TEXT` *nach* Parameter-Substitution (soweit MySQL das tut) und erlaubt, Second-Order-Injection zu erkennen — d. h. einen Payload, der in Probe A in die DB geschrieben wurde, und in Probe B beim späteren Lesen in einen unsafen Kontext fließt.

---

## 4 Kanal 3 — Coverage-Delta

**Quelle:** Das bestehende Coverage-Setup schreibt `artifacts/layer3/coverage.xml` (Clover-Format) nach einem Layer-3-Lauf. Die Datei enthält pro Source-Datei eine `<line>`-Liste mit `count`-Attribut.

**Nutzung im Audit:**

1. Baseline: Einmal `make test-integration` laufen lassen. Das Ergebnis ist `coverage_baseline.xml`.
2. Vor einem Probe: Coverage-State reset — Löschen der Clover-Datei, `Xdebug` im Coverage-Modus starten.
3. Probe ausführen.
4. Nach dem Probe: Aktuelle Clover-Datei extrahieren (`coverage_probe.xml`).
5. Diff berechnen: Welche Zeilen haben `count > 0` in `coverage_probe.xml`, aber `count == 0` in `coverage_baseline.xml`?

**Einschränkung:** Coverage-Instrumentierung verlangsamt die Anwendung deutlich. Der Audit-Loop schaltet Coverage-Delta standardmäßig aus und aktiviert es nur auf expliziten Wunsch pro Deep-Dive, wenn der Agent frageorientiertes Fuzzing machen will ("welche Payload-Variante erreicht Zeile X").

**Regressionsnutzung:** Nach einem bestätigten Finding wird der daraus generierte Layer-3-Test mit Coverage ausgeführt. Der Regressionstest gilt nur dann als gültig, wenn er die Zeilen abdeckt, die der Exploit ursprünglich erreicht hat (diese Zeilen werden in der Task-Frontmatter unter `coverage_lines` festgehalten).

---

## 5 Kanal 4 — SecurityTraceMiddleware-Artefakte

Vollständig spezifiziert in [`05_security_trace_middleware.md`](05_security_trace_middleware.md). Kurzfassung:

**Aktivierung:** ENV `WEBTREES_SECURITY_TRACE=1` im `webtrees`-Container + Probe-Header `X-Audit-Probe: <task-id>-r<iter>`.

**Output:** Pro Request ein JSON-File unter `artifacts/security-trace/<task-id>/<iter>_<timestamp>.json`:

```json
{
  "probe_id": "SEC-AUDIT-001-r1",
  "timestamp_unix": 1712577600.123,
  "request": {
    "method": "GET",
    "uri": "/tree/demo/individual/X42?debug=1",
    "headers": { "...": "..." },
    "cookies": { "...": "..." },
    "body_sha256": "<sha256>",
    "body_excerpt": "<first 512 bytes, redacted>"
  },
  "middleware_chain": [
    {"class": "ErrorHandler", "entered_ns": 0, "exited_ns": 123456789},
    {"class": "BadBotBlocker", "entered_ns": 120, "exited_ns": 450},
    {"class": "UseSession", "entered_ns": 500, "exited_ns": 1200},
    "..."
  ],
  "matched_route": "Fisharebest\\Webtrees\\Http\\RequestHandlers\\IndividualPage",
  "auth_context": {
    "user_id": null,
    "access_level": "visitor",
    "tree_id": 1
  },
  "db_queries_count": 4,
  "response": {
    "status": 200,
    "headers": { "...": "..." },
    "body_sha256": "<sha256>",
    "body_length": 12345,
    "body_excerpt": "<first 512 bytes, redacted>"
  },
  "exceptions": [],
  "otel_trace_id": "abc123...",
  "duration_ms": 42.1
}
```

**Redaction:** Secrets (Session-Cookies außer Namen, Authorization-Header, `config.ini.php`-Werte, DB-Passwörter) werden durch SHA256-Präfixe ersetzt. Redaction-Regeln sind in der Middleware-Spec fixiert.

**Gleichbleibendes Interface zu Kanal 1 (OTel):** Das `otel_trace_id`-Feld erlaubt den Join mit Kanal 1 — ein Artefakt aus Kanal 4 plus die Traces aus Kanal 1 ergeben ein vollständiges Bild.

---

## 6 Kanal 5 — Apache Access-/Error-Log

**Quelle:** Standard-Apache-Logs im Container. Zugriff:

```bash
podman-compose logs webtrees 2>&1 | grep -F "SEC-AUDIT-001-r1"
# oder direkt in die Logdatei
podman-compose exec webtrees tail -n 100 /var/log/apache2/access.log
podman-compose exec webtrees tail -n 100 /var/log/apache2/error.log
```

**Wofür nützlich:** 
- Erkennung von `AH`-Fehlercodes (Apache-Interne Fehler, z. B. `AH01797` für `Require all denied`)
- Sichtbarkeit der `.htaccess`-Wirksamkeit (`data/` → 403)
- `mod_rewrite`-Loops oder unerwartete Redirects
- PHP-Fatal-Errors, die nicht im `ErrorHandler` gefangen werden (direkter Aufruf von `trigger_error`, Parse-Fehler)

**Hinweis:** Podman-Compose leitet die Container-Logs an Journald weiter. `podman-compose logs webtrees` reicht für die meisten Zwecke.

---

## 7 Kanal 6 — webtrees-Application-Log

**Quelle:** `/var/www/html/data/log.txt` — die Application-Log-Datei, die webtrees selbst schreibt.

```bash
podman-compose exec webtrees tail -f /var/www/html/data/log.txt
```

**Wofür nützlich:**
- Login-Events (Erfolg/Fehler)
- Tree-Modifikationen
- Import-/Export-Events
- Manuelle `Log::addAuthenticationLog()`, `Log::addUserLog()`, `Log::addErrorLog()`-Calls

**Sicherheitsrelevanz:** Wenn ein Probe einen der oben genannten Code-Pfade triggert, erscheint ein Eintrag. Fehlt er bei einem kritischen Event, ist das ein A09-Finding.

---

## 8 Kanal 7 — MySQL general_log (on-demand)

**Aktivierung pro Probe:**

```bash
podman-compose exec mysql mysql -uroot -pwebtrees_test -e "
  SET GLOBAL general_log = 'ON';
  SET GLOBAL general_log_file = '/tmp/gen.log';
"

# Probe ausführen

podman-compose exec mysql mysql -uroot -pwebtrees_test -e "SET GLOBAL general_log = 'OFF';"
podman-compose exec mysql cat /tmp/gen.log > /tmp/gen-SEC-AUDIT-001-r1.log
podman-compose exec mysql sh -c 'echo > /tmp/gen.log'
```

**Einschränkung:** `general_log` schreibt alle Queries unformatiert, ohne Parameterbindung. Für die meisten Probes ist PerfSchema (Kanal 2) präziser. Kanal 7 ist nur dann sinnvoll, wenn PerfSchema eine Query komplett übersprungen hat oder wenn Admin-interne Queries aus `mysql`-System-Tables interessant sind.

---

## 9 Kanal 8 — Regression-Coverage

Nach einem bestätigten Finding wird ein Regressionstest geschrieben (siehe [`08_layer_integration.md`](08_layer_integration.md)). Dieser Test muss:

1. Im Ausgangszustand (unpatched Upstream) **rot** sein.
2. Nach Anwendung des Fix-Branches im Fork (`WEBTREES_SOURCE=/…/webtrees-upstream/webtrees` + Branch-Checkout) **grün** sein.
3. Mit Coverage ausgeführt werden — die Clover-Datei muss die in der Task-Frontmatter festgehaltenen `coverage_lines` des SUT abdecken.

Das dritte Kriterium verhindert Placebo-Regressionstests, die zwar den Test-Flow ausführen, aber nicht die tatsächliche Exploit-Bruchstelle durchlaufen.

---

## 10 Nutzung bestehender Artefakte als Seeds

Neben den Laufzeit-Kanälen verwendet der Audit vier **statische** Wissensquellen im Repo, die als Seeds in die Triage einfließen:

| Quelle | Nutzung |
|---|---|
| `upstream/webtrees/app/Http/Routes/WebRoutes.php` | Vollständige Routen-Tabelle — Eingabe für Reachability-Matrix (siehe T0.2 in [`04_triage_pipeline.md`](04_triage_pipeline.md)) |
| `layer3-integration/tests/*IntegrationTest.php` | 80 bestehende Layer-3-Testklassen — Eingabe für Flow-Kenntnis: Welche Setup-Patterns existieren schon, welche Handler sind bereits auf EP/BVA getestet, welche Lücken sind für den Audit attraktiv |
| `docs/tds_conditions_ref.md` §SEC | 27 bestehende SEC-Features — Abgrenzung, damit der Audit keine Duplikate produziert |
| `artifacts/layer3/coverage.xml` (aus letzter CRAP-Report-Session) | CRAP-Score pro SUT-Methode — direkt als Signal in T0.4 |

Diese Seeds sind alle **ohne Scripts** nutzbar — sie werden direkt vom Triage- bzw. Deep-Dive-Prompt gelesen, in dem die Dateipfade im Prompt-Template referenziert sind.

---

## 11 Parallel-Sicherheit und Exklusivität

Ein wichtiger Aspekt aus [`CLAUDE.md`](../../CLAUDE.md): **Immer nur ein Testlauf gleichzeitig**. Das gilt auch für den Audit-Loop:

- Vor dem Start eines Audit-Laufs prüfen, dass weder `make test-integration` noch `make test-e2e` noch ein anderer Audit-Probe läuft (`podman-compose exec webtrees pgrep -a -f phpunit`).
- Der Loop-Driver [`06_agentic_loop_driver.md`](06_agentic_loop_driver.md) hält ein Advisory-Lock unter `artifacts/security-audit/.lock`, das den gleichzeitigen Start zweier Audit-Loops verhindert.
- Während eines Audit-Laufs dürfen keine manuellen Test-Commands laufen — MySQL- und OTel-State sind geteilt.

---

## 12 Ausschluss: Distribution-Container

Der Distribution-Container (`webtrees-security`, `:8082`) wird vom Whitebox-Audit **nicht angefasst**:

- Kein OTel, keine Auto-Instrumentation → kein Kanal 1/2.
- Kein bind-gemounteter Source-Code → keine Whitebox-Lesbarkeit.
- Keine `modules/security-trace/`-Bind-Mount-Möglichkeit → kein Kanal 4.

Begründung siehe [`01_scope_and_tracks.md`](01_scope_and_tracks.md) §1. Der bestehende SEC-Track nutzt weiterhin den Distribution-Container für seine Blackbox-Härtungstests, unabhängig von diesem Audit.

---

## 13 Querverweise

- [`tp_infrastructure_spec.md`](../tp_infrastructure_spec.md) §N6 — OTel-Trace-Kette, ausführlich
- [`04_triage_pipeline.md`](04_triage_pipeline.md) — Wie die Kanäle in die Priorisierung einfließen
- [`05_security_trace_middleware.md`](05_security_trace_middleware.md) — Kanal-4-Spec
- [`06_agentic_loop_driver.md`](06_agentic_loop_driver.md) — Wie der Loop die Kanäle Iteration für Iteration konsumiert
