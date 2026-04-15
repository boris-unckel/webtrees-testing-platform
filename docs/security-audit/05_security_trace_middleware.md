<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SecurityTraceMiddleware — Spezifikation

**Teil von:** [tp_security-audit_spec.md](../tp_security-audit_spec.md)
**Vorangehend:** [04_triage_pipeline.md](04_triage_pipeline.md)

---

## 1 Motivation

Der Audit braucht einen **Whitebox-Rückkanal** auf PHP-Ebene pro Probe. Die OTel-Traces (Kanal 1) liefern Timing- und Tree-Struktur; sie sind aber nicht auf Security-Attribute zugeschnitten: Session-Zustand, effektive Rolle, Matched-Route-Klasse, redigierter Request-Body, Exception-Chain nach `ErrorHandler`.

Die Lösung: eine **eigene PSR-15-Middleware** analog zum bestehenden `OtelSpansModule`, die in `modules/security-trace/` liegt, per Compose-Bind-Mount als Custom-Modul in den Fachtest-Container eingehängt wird und auf die Markierung `WEBTREES_SECURITY_TRACE=1` aktiviert wird.

Das Modul wird **nicht in dieser Runde implementiert**. Dieses Dokument ist die vollständige Spezifikation, die eine spätere Implementierungssitzung unverändert umsetzen kann.

---

## 2 Dateistruktur

```
modules/
└── security-trace/
    ├── module.php                     ← webtrees-Modul-Einstiegspunkt (Convention)
    ├── SecurityTraceModule.php        ← AbstractModule + ModuleCustomInterface + MiddlewareInterface
    ├── Redactor.php                   ← Secret-Redaction-Helfer
    ├── ProbeArtifactWriter.php        ← JSON-Schreiber (fsync-sicher, atomic rename)
    └── README.md                      ← Kurzbeschreibung, SPDX-Header
```

**Analogie:** Identische Struktur wie `modules/otel-spans/`. Der bestehende `OtelSpansModule.php` implementiert das Pattern bereits (siehe `modules/otel-spans/OtelSpansModule.php`) und kann als Vorlage dienen.

---

## 3 Compose-Integration

In `compose.yaml` wird ein neuer Bind-Mount ergänzt (nach dem bestehenden OtelSpans-Mount):

```yaml
services:
  webtrees:
    volumes:
      - ./modules/otel-spans:/var/www/html/modules_v4/otel_spans:ro,z
      - ./modules/security-trace:/var/www/html/modules_v4/security_trace:ro,z
      - ./artifacts/security-trace:/artifacts/security-trace:rw,z   # Output-Artefakte
    environment:
      WEBTREES_SECURITY_TRACE: ${WEBTREES_SECURITY_TRACE:-}
```

`WEBTREES_SECURITY_TRACE` ist im Normal-Betrieb leer (Middleware ist inaktiv, Zero-Overhead). Für einen Audit-Lauf setzt der Loop-Driver sie temporär auf `1` vor dem Start des Loops und stoppt ggf. den Container-Stack kurz, um das Env neu einzulesen. Alternative ohne Compose-Restart: die Middleware prüft bei **jedem** Request `getenv('WEBTREES_SECURITY_TRACE')` — schwach-cached, aber Zero-Overhead wenn leer.

---

## 4 PSR-15-Position im Middleware-Stack

webtrees baut seinen Middleware-Stack in `Webtrees::bootstrap()` auf. Module, die `MiddlewareInterface` implementieren, werden von `ModuleService::findByInterface(MiddlewareInterface::class)` aufgesammelt und im Stack eingeordnet. Der `OtelSpansModule` läuft bereits in dieser Position.

Die `SecurityTraceMiddleware` **muss** im Stack an einer Position liegen, an der sie:

1. den **endgültigen** Response sieht (inklusive der Änderungen nachgelagerter Middlewares),
2. die **effektive Auth-Entscheidung** kennt (nach `UseSession`, `AuthMember` o. ä.),
3. Exceptions aus dem Handler noch sieht, bevor `ErrorHandler` sie in eine HTML-Fehlerseite umwandelt.

Konkret: **so früh wie möglich im Stack**, nach `ErrorHandler` aber vor allen fachlichen Middlewares. Da `OtelSpansModule` diese Position bereits hat, reicht die Konvention „direkt nach OtelSpansModule". Verifizierung zur Laufzeit: Die Middleware loggt beim ersten Request die Anzahl der nachfolgenden Middlewares, damit der Audit sehen kann, wo sie tatsächlich gelandet ist.

**Exception-Handling:** Der `process()`-Aufruf wird in einem äußeren `try { ... } catch (\Throwable $t) { ... throw $t; }` ausgeführt, um Exceptions mitzuschreiben und dann weiter nach oben zu propagieren. Die Middleware fängt **nichts** — sie beobachtet nur.

---

## 5 Aktivierungs-Guards

Die Middleware ist **doppelt guarded**:

1. **ENV-Guard (global):** `getenv('WEBTREES_SECURITY_TRACE') !== '1'` → `process()` ruft direkt `$handler->handle($request)` auf, ohne irgendeine Arbeit zu tun.
2. **Header-Guard (pro Probe):** Wenn der Request keinen `X-Audit-Probe: <id>`-Header hat, ist die Middleware zwar aktiv, aber schreibt kein Artefakt. Der Grund: Während eines Audit-Laufs darf der parallel laufende Browser-Health-Check oder der Playwright-Boot-Request nicht das Trace-Verzeichnis fluten.

Die Guards sind in dieser Reihenfolge zu prüfen (ENV zuerst, Header danach), damit die Middleware in Abwesenheit von ENV `WEBTREES_SECURITY_TRACE` *keinerlei* Overhead verursacht.

---

## 6 Artefakt-Schema

Pro Probe wird **ein** JSON-Artefakt geschrieben: `artifacts/security-trace/<probe-id>/<iso-ts>.json`.

```json
{
  "$schema": "https://example.invalid/webtrees-security-trace.v1.json",
  "probe_id": "SEC-AUDIT-042-r3",
  "task_id": "SEC-AUDIT-042",
  "iteration": 3,
  "timestamp": "2026-04-08T14:12:34.567Z",
  "timestamp_unix_us": 1712585554567890,

  "request": {
    "method": "POST",
    "uri": "/tree/demo/search",
    "path": "/tree/demo/search",
    "query_params": {"filter": "name"},
    "parsed_body_keys": ["query", "advanced"],
    "parsed_body_sha256": "<hex>",
    "parsed_body_excerpt": "<first 512 chars, redacted>",
    "uploaded_files": [
      {"field": "gedcom", "client_filename": "tree.ged", "client_media_type": "text/plain", "size": 12345, "sha256": "<hex>"}
    ],
    "headers": {
      "host": "localhost:8080",
      "user-agent": "curl/8.x",
      "x-audit-probe": "SEC-AUDIT-042-r3",
      "cookie": "<redacted: sess=sha256:abc...>",
      "authorization": "<redacted: Basic sha256:def...>"
    },
    "cookies": {
      "WT_SESSION": "<redacted: sha256:abc...>"
    },
    "remote_addr": "127.0.0.1",
    "forwarded_for_raw": null
  },

  "middleware_chain": [
    {"index": 0, "class": "Fisharebest\\Webtrees\\Http\\Middleware\\ErrorHandler",    "entered_us": 0,  "exited_us": 123},
    {"index": 1, "class": "Fisharebest\\Webtrees\\Http\\Middleware\\BaseUrl",          "entered_us": 12, "exited_us": 110},
    {"index": 2, "class": "Fisharebest\\Webtrees\\Http\\Middleware\\BadBotBlocker",    "entered_us": 25, "exited_us": 95},
    {"index": 3, "class": "Fisharebest\\Webtrees\\Http\\Middleware\\UseSession",       "entered_us": 40, "exited_us": 80},
    {"index": 4, "class": "Fisharebest\\Webtrees\\Http\\Middleware\\AuthLoggedIn",     "entered_us": 55, "exited_us": 72}
  ],

  "matched_route": {
    "handler_class": "Fisharebest\\Webtrees\\Http\\RequestHandlers\\SearchGeneralAction",
    "route_path": "/tree/{tree}/search",
    "route_method": "POST",
    "route_name": "search-general"
  },

  "auth_context": {
    "user_id": null,
    "user_real_name": null,
    "access_level_numeric": 1,
    "access_level_label": "visitor",
    "tree_id": 1,
    "tree_name": "demo",
    "is_robot_flag": false,
    "csrf_token_valid": null
  },

  "db_queries": {
    "count": 5,
    "statements": [
      {"sql": "SELECT * FROM `wt_individuals` WHERE i_id = ?", "bindings_redacted": ["<string len=6>"], "duration_us": 243, "rows_affected": 1}
    ]
  },

  "response": {
    "status": 200,
    "reason_phrase": "OK",
    "headers": {
      "content-type": "text/html; charset=UTF-8",
      "set-cookie": "<redacted>"
    },
    "body_sha256": "<hex>",
    "body_length": 12345,
    "body_excerpt": "<first 512 chars of response body>"
  },

  "exceptions": [
    {
      "class": "Fisharebest\\Webtrees\\Exceptions\\FileUploadException",
      "message": "File too large",
      "file": "app/Services/MediaFileService.php",
      "line": 234,
      "trace_excerpt": ["..."]
    }
  ],

  "otel_trace_id": "abc123deadbeef...",
  "otel_span_id":  "1234beef...",

  "duration_us": 4321,
  "memory_peak_bytes": 8388608,

  "module_self_diag": {
    "stack_position_after": 4,
    "bytes_written": 3012
  }
}
```

Die Felder `parsed_body_excerpt`, `body_excerpt` und `trace_excerpt` sind jeweils auf 512 Zeichen begrenzt. Der volle Body wird nur als `sha256`-Hash persistiert — es sei denn, der Probe-Header enthält zusätzlich `X-Audit-Probe-Full-Body: 1`, dann wird der gesamte Body unter `<iso-ts>.body-request.bin` bzw. `.body-response.bin` abgelegt. Dieser Opt-in-Modus ist für kleine Exploit-Replay-Sessions gedacht, nicht für Sweep-Fuzzing.

---

## 7 Redaction-Regeln

Der `Redactor` wendet folgende Regeln **in dieser Reihenfolge** an:

| # | Regel | Warum |
|---|---|---|
| 1 | `Cookie`- und `Set-Cookie`-Header: Name bleibt, Wert wird zu `sha256:<hex>` | Session-Hijacking-Schutz im Artefakt |
| 2 | `Authorization`-Header: Schema bleibt (Basic/Bearer), Token wird zu `sha256:<hex>` | Credentials-Leak-Schutz |
| 3 | `X-CSRF-Token`, `X-XSRF-TOKEN`: Wert wird zu `sha256:<hex>` | CSRF-Bypass-Schutz |
| 4 | Body-Felder mit Keys aus der Redaction-Liste: `password`, `password_confirm`, `old_password`, `dbpass`, `api_key`, `apikey`, `secret`, `token`, `totp_code` → Wert zu `sha256:<hex>` | Credentials im POST-Body |
| 5 | DB-Bindings: keine Werte, nur Typ und Länge (`<string len=16>`, `<int>`, `<null>`) | Parameter können selbst sensitiv sein |
| 6 | Response-Body: Wenn der Content-Type `application/json` ist, werden die Felder aus Regel 4 rekursiv im JSON redigiert | Secrets in JSON-Antworten |
| 7 | `config.ini.php`-Inhalt: Bei jedem Auftreten von `dbhost`, `dbuser`, `dbpass`, `dbname`, `tblpfx` in Body-Excerpt → Wert zu `sha256:<hex>` | Config-Exfiltration-Schutz |

Die Redaction findet **vor** dem `sha256`-Hashing statt. Das heißt: Der Hash im Artefakt ist der Hash des **redigierten** Bodys, nicht des Originals. Das macht Hashes zwischen verschiedenen Probes vergleichbar, **solange** die zu redigierenden Felder gleich benannt sind. Bei Bedarf kann der Audit den Probe-Header `X-Audit-Probe-Raw-Hash: 1` setzen — dann wird zusätzlich ein `raw_sha256`-Feld (ohne Redaction) geschrieben. Dies ist nur für kurzzeitige Hash-Diff-Sessions gedacht.

---

## 8 OTel-Integration

Die Middleware hängt pro Request folgende Attribute an den aktuellen Span (aus dem Auto-Instrumentation-Stack):

```
security_audit.probe_id           = "SEC-AUDIT-042-r3"
security_audit.task_id            = "SEC-AUDIT-042"
security_audit.iteration          = 3
security_audit.access_level       = "visitor"
security_audit.tree_id            = 1
security_audit.matched_handler    = "Fisharebest\\Webtrees\\Http\\RequestHandlers\\SearchGeneralAction"
security_audit.response_status    = 200
security_audit.exception_count    = 0
security_audit.artifact_path      = "/artifacts/security-trace/SEC-AUDIT-042/2026-04-08T14-12-34-567Z.json"
```

Die `otel_trace_id` und `otel_span_id` aus dem aktuellen Span-Context werden in das Artefakt geschrieben (siehe §6). Damit lassen sich Artefakt, Jaeger-Trace und Kanal-2-PerfSchema per `probe_id` + `trace_id` eindeutig verknüpfen.

---

## 9 Atomic-Write-Semantik

Der `ProbeArtifactWriter` schreibt das Artefakt in drei Schritten:

1. Vollständiger JSON-Build im Speicher (nicht streamend — Probes sind klein).
2. Schreiben nach `<iso-ts>.json.tmp` mit `fwrite` + `fsync` + `fclose`.
3. Atomisches `rename` auf `<iso-ts>.json`.

**Warum atomic?** Der Agentic-Loop kann das Verzeichnis während eines Probe polling durchgehen. Halbfertige Dateien wären verwirrend. Das `rename`-Pattern garantiert, dass der Reader entweder nichts oder das vollständige Artefakt sieht.

**Fehlerverhalten:** Wenn Schreiben fehlschlägt (Disk full, EINVAL), wird der Fehler als Warning in `error_log` geschrieben und der Request läuft normal weiter. Die Middleware verursacht **niemals** einen 5xx für den User, selbst wenn das Tracing kaputt ist. Der Audit-Loop bemerkt fehlende Artefakte und kann den Probe wiederholen.

---

## 10 Performance-Budget

**Im aktiven Zustand** (ENV `WEBTREES_SECURITY_TRACE=1` + Header `X-Audit-Probe`):

- Ziel: ≤ 2 ms Overhead pro Request bei einer Standardseite.
- Artefakt-Größe: ≤ 8 KB für einen typischen Probe.
- Keine Datenbank-Zusatzaufrufe.
- Keine Netzwerk-I/O.

**Im inaktiven Zustand** (ENV leer):

- Ziel: ≤ 0.01 ms pro Request (einziger Overhead: `getenv`-Call + `if`-Check).
- Zero Allocations.
- Kein Disk-I/O.

Die Werte sind in der Modul-`README.md` als Einstiegspunkt für Regressions-Performance-Tests zu dokumentieren, sobald die Implementierung erfolgt.

---

## 11 Bezug zum OtelSpansModule

Die `SecurityTraceMiddleware` **ergänzt** das `OtelSpansModule`, ersetzt es nicht. Arbeitsteilung:

| Aspekt | OtelSpansModule | SecurityTraceMiddleware |
|---|---|---|
| Zweck | Semantische Spans, Server-Timing-Header, Baggage | Security-Audit-Artefakte mit vollem Kontext |
| Aktivierung | Immer (Teil des Fachtest-Setups) | Nur wenn ENV + Header gesetzt |
| Output | In-process Spans + Server-Timing-Header | JSON-Artefakte auf Disk + OTel-Attribute auf vorhandenem Span |
| Kostenbudget im Normalbetrieb | < 0.5 ms | 0 ms (ENV-guarded) |
| Dauerhaft im Produktiv-Stack | Ja | Nein (nur für Audit) |

Die zwei Module koexistieren problemlos, weil sie disjunkte Stack-Positionen einnehmen und sich gegenseitig in keinem Attribut-Namespace überschneiden. Die `SecurityTraceMiddleware` schreibt ihre Attribute unter `security_audit.*`, das `OtelSpansModule` unter `webtrees.*`.

---

## 12 Ausschlüsse

Die Middleware macht explizit **nicht**:

- **Kein Exploit-Detection.** Sie beobachtet, sie klassifiziert nicht. Muster-Erkennung (SQLi-Signaturen etc.) ist Aufgabe des Deep-Dive-Prompts, nicht der Middleware.
- **Kein Bannen / Blocken.** Sie reagiert nicht auf verdächtige Requests — sonst würde sie den Agent beim Fuzzing behindern.
- **Kein Rate-Limiting.** Der Audit-Loop hält seine eigene Cadence.
- **Keine globale Sammlung aller Requests.** Ohne `X-Audit-Probe`-Header ist jeder Request unsichtbar.
- **Kein Write von Request-Body außer Excerpt.** Full-Body-Write nur per explizitem Opt-in-Header (§6).
- **Keine Modifikation von Request/Response.** Die Middleware ruft `$handler->handle($request)` 1:1 auf und gibt die erhaltene Response unverändert zurück. Kein Hinzufügen von Headers, kein Rewriting.

---

## 13 Migrationspfad und Rückbau

Da die Middleware ein **Custom-Modul** ist, reicht ein Entfernen des Bind-Mounts in `compose.yaml` und ein `make down && make up`, um sie vollständig zurückzubauen. Es gibt keine Datenbankänderungen, keine Schema-Migrationen, keine Persistenz außer den Artefakt-Dateien (die als gitignored unter `artifacts/security-trace/` liegen).

Das Modul darf **niemals** in die produktive Distribution eingehen. Die Compose-Integration ist bewusst nur für den Fachtest-Container konfiguriert.

---

## 14 Querverweise

- [03_infrastructure_usage.md](03_infrastructure_usage.md) §5 — Konsumentensicht auf die Artefakte
- [06_agentic_loop_driver.md](06_agentic_loop_driver.md) — Wie der Loop die Middleware pro Probe aktiviert und deaktiviert
- [07_prompts/prompt_03_exploit_attempt.md](07_prompts/prompt_03_exploit_attempt.md) — Wie der Deep-Dive-Prompt die Artefakte liest
- `modules/otel-spans/OtelSpansModule.php` — Vorlage für die Implementierung
- `compose.yaml` — Mount-Integration
