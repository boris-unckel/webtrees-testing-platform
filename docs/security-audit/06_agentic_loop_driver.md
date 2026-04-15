<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Agentic Loop Driver — Spezifikation

**Teil von:** [tp_security-audit_spec.md](../tp_security-audit_spec.md)
**Vorangehend:** [05_security_trace_middleware.md](05_security_trace_middleware.md)

---

## 1 Zweck

Die vorherigen Dokumente legen Scope, Threat-Model, Infrastruktur-Kanäle, Triage und Trace-Middleware fest. Dieses Dokument beschreibt, **wie ein Audit-Lauf konkret abläuft**: Welches Skript ruft welchen Claude-Subagent in welcher Reihenfolge auf, wie sieht die Schleife über einen einzelnen Audit-Task aus, wie wird Kontext persistiert und rekonstruiert, wann bricht der Lauf ab.

Das Skript selbst (`scripts/security-audit-loop.sh` + Sub-Komponenten) wird **nicht in dieser Runde implementiert**. Dieses Dokument ist die vollständige Spezifikation, die eine spätere Implementierungssitzung unverändert umsetzen kann.

---

## 2 Zweiteiliger Betriebsmodus

Aus F7c: Der Lauf kombiniert ein headless Sweep-Skript mit einer interaktiven Deep-Dive-Sitzung.

| Modus | Skript | LLM-Invokation | Zweck |
|---|---|---|---|
| **Sweep** | `scripts/security-audit-loop.sh sweep` | `claude --print` im Loop, headless | Triage-Pipeline + erste Hypothesen-Runde pro Task |
| **Deep-Dive** | `scripts/security-audit-loop.sh deep-dive SEC-AUDIT-<NNN>` | Interaktive `claude`-Sitzung, vom User geführt | Exploit-Verfeinerung pro Task, mit Human-in-the-Loop |

Die zwei Modi teilen sich den gesamten Artefakt-Baum unter `artifacts/security-audit/<run-id>/` und die Task-Frontmatter unter `docs/security-audit/tasks/`. Jeder Status-Übergang eines Tasks ist für den jeweils anderen Modus sichtbar.

---

## 3 Sweep-Modus — Phasenablauf

```
$ scripts/security-audit-loop.sh sweep [--run-id YYYY-MM-DDTHH-MM-SS] [--max-tasks N] [--budget-usd X]

  Phase S0:  Pre-Flight-Checks
             ├── podman-compose ps            → webtrees + mysql healthy
             ├── kein laufender phpunit       → Lock-Check
             ├── artifacts/layer3/coverage.xml existent + neuer als 24h
             ├── Advisory-Lock setzen         → artifacts/security-audit/.lock
             └── Run-Directory anlegen        → artifacts/security-audit/<run-id>/

  Phase S1:  Trace-Middleware aktivieren
             ├── WEBTREES_SECURITY_TRACE=1 in .env setzen
             ├── make down && make up         (Env-Reload)
             └── Warten bis webtrees-Health ok

  Phase S2:  T0 Inventarisierung (mechanisch)
             ├── scripts/security-audit/t0-scan.php
             │     → app/Http/{RequestHandlers,Middleware}, app/Services, …
             │     → JSON nach artifacts/security-audit/<run-id>/t0_signals.json
             └── Dauer-Ziel: < 30 s, kein LLM

  Phase S3:  T1 LLM-Triage
             ├── Für jede T0-Datei mit Cache-Miss:
             │     claude --print --model sonnet \
             │            --append-system-prompt 07_prompts/prompt_01_triage_llm.md \
             │            < t0-slice-for-file.json
             │     → t1_llm_scores.json (inkrementell angehängt)
             └── Parallelisierung: max 4 gleichzeitige Subprozesse (Rate-Limit-Safety)

  Phase S4:  T2 Track-Zuordnung (mechanisch)
             └── scripts/security-audit/t2-tracks.py
                   → t2_tracks.json

  Phase S5:  T3 Priorisierung (mechanisch)
             └── scripts/security-audit/t3-score.py
                   → priorities.md + task_deltas.json

  Phase S6:  Task-Sync
             ├── Neue Tasks anlegen       (SEC-AUDIT-NNN_<slug>.md aus _template.md)
             ├── Bestehende Tasks updaten  (nur Frontmatter-Felder aus T0/T1/T2/T3)
             └── tasks/INDEX.md regenerieren

  Phase S7:  Erste Hypothesen-Runde pro neuem Task (wenn --hypotheses flag)
             ├── Für jeden neuen Task mit priority >= 0.40:
             │     claude --print --model opus \
             │            --append-system-prompt 07_prompts/prompt_02_whitebox_deep_dive.md \
             │            < task-context.md
             │     → Hypothesen-Liste in Task-Datei unter "## Hypothesen" hinzufügen
             │     → Status: queued → in_analysis
             └── Parallelisierung: max 2 Opus-Subprozesse gleichzeitig

  Phase S8:  Run-Zusammenfassung
             ├── run-summary.md generieren
             ├── Advisory-Lock entfernen
             ├── Exit-Code 0 bei Erfolg
             └── Notification an User (stdout)
```

**Budget-Kontrolle:** `--budget-usd X` setzt einen harten Schnitt. Wenn die summierten Kosten aus den `claude`-Subprozessen (via `--output-format json` + Token-Zählung) den Wert überschreiten, bricht die Phase sauber ab, persistiert Zwischenstand und markiert die unerledigten Tasks als `queued`.

---

## 4 Deep-Dive-Modus — Interaktiver Ablauf pro Task

```
$ scripts/security-audit-loop.sh deep-dive SEC-AUDIT-042

  Phase D0:  Task laden, Status in_analysis → in_progress
             ├── tasks/SEC-AUDIT-042_*.md lesen
             ├── Frontmatter → Environment-Variablen für die Session
             └── Konsistenz-Check: Status muss in ['queued','in_analysis']

  Phase D1:  Kontext-File erzeugen (kumulativ)
             └── artifacts/security-audit/<run-id>/tasks/SEC-AUDIT-042/context.md
                   enthält:
                   ── Task-Frontmatter
                   ── Aktuelle Hypothesen
                   ── Relevante Upstream-Quelldateien (Auszüge, nicht vollständig)
                   ── Bereits ausprobierte Probes + Responses (redigiert)
                   ── Relevante bestehende Layer-3-Tests
                   ── Link-Liste zu Prompt-Templates 07_prompts/*.md

  Phase D2:  Interaktive Claude-Sitzung starten
             $ claude --continue --model opus \
                      --append-system-prompt 07_prompts/prompt_02_whitebox_deep_dive.md \
                      artifacts/security-audit/<run-id>/tasks/SEC-AUDIT-042/context.md

             Der User führt die Sitzung. Der Agent hat Zugriff auf:
             ├── Read/Edit/Grep für Upstream-Source (read-only durch Konvention)
             ├── Bash für curl, podman-compose exec, psql/mysql
             ├── Schreibrechte nur in artifacts/security-audit/<run-id>/tasks/SEC-AUDIT-042/
             └── Task-Update-Command zum Status-Vorwärts-Schieben

  Phase D3:  Probe-Loop innerhalb der Sitzung
             Für jeden Exploit-Versuch:
             ├── Probe-ID vergeben: SEC-AUDIT-042-r<N>
             ├── PerfSchema truncate (scripts/truncate-perfschema.sh)
             ├── Trace-File leeren (artifacts/traces.json)
             ├── Probe senden mit Header X-Audit-Probe: SEC-AUDIT-042-r<N>
             ├── SecurityTraceMiddleware-Artefakt lesen
             │     → artifacts/security-trace/SEC-AUDIT-042/*.json
             ├── OTel-Trace lesen
             │     → artifacts/traces.json + trace-report.py
             ├── PerfSchema-Auszug
             │     → scripts/extract-perfschema.sh
             ├── Feedback-Zusammenfassung im Chat
             │     → Matched-Handler, Middleware-Chain, DB-Queries, Status
             └── Hypothese bestätigen, verfeinern oder verwerfen

  Phase D4:  Status-Transition
             ├── exploit_attempted → Probe läuft
             ├── exploit_refuted  → Hypothese widerlegt, neue Hypothese aus D3
             ├── exploit_confirmed → PoC reproduzierbar, mit Trace-Korrelation
             └── (Bei confirmed) Deep-Dive-Sitzung kann enden, User wechselt zu D5

  Phase D5:  Regression + Fixture (nach confirmed)
             ├── Claude erzeugt Layer-3-Testklasse oder Layer-4-Spec
             │     → layer3-integration/tests/security/<name>SecurityAuditTest.php
             │     → oder layer4-e2e/tests/audit/<name>.spec.ts
             ├── Fixture extrahieren  → fixtures/security/payloads/sec-audit-042.json
             ├── Task-Status: exploit_confirmed → regression_drafted
             └── make test-integration-filter auf den neuen Test — muss ROT sein

  Phase D6:  Fix-Entwurf (optional, im separaten Fork-Repo)
             ├── cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
             ├── git checkout main && git pull upstream main
             ├── git checkout -b security-audit-042-<slug>
             ├── Patch entwickeln
             ├── Task-Status: regression_drafted → fix_in_progress
             └── (Diese Phase ist für eine separate Sitzung — Deep-Dive endet normalerweise vor D6)

  Phase D7:  Fix-Verifikation (zurück im Testing-Platform)
             ├── WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees make setup
             ├── make test-integration-filter auf den Security-Audit-Test
             ├── Muss GRÜN werden — sonst fix_in_progress bleibt
             ├── Task-Status: fix_in_progress → fix_verified
             └── ENDE des Workflows (kein automatischer PR — User macht das manuell)
```

**Human-in-the-Loop-Entscheidungspunkte:**
- Nach D3 (Probe-Feedback): Weiter iterieren oder Hypothese aufgeben?
- Vor D5 (Regression schreiben): Genug Evidenz für Finding?
- Vor D6 (Fork-Branch): Fix lokal entwickeln oder erst melden?
- Nach D7 (Fix verifiziert): PR-bereit oder noch Diskussion?

Jeder Punkt ist vom Skript als expliziter Prompt im Chat formuliert. Der Agent darf keine dieser Entscheidungen autonom treffen.

---

## 5 Task-Status-Lifecycle

Alle Status-Übergänge werden **sofort nach Erledigung** in der Task-Frontmatter persistiert — nicht am Ende einer Phase. Das ist vom User explizit gefordert und verhindert inkonsistente Zustände bei Abbruch.

```
queued
  │
  │ sweep S7: Hypothesen-Runde startet
  ▼
in_analysis
  │
  │ deep-dive D0: User übernimmt Task
  ▼
in_progress
  │
  │ deep-dive D3: erster Probe gesendet
  ▼
exploit_attempted
  │
  ├─ deep-dive D3 (Fehlschlag): Hypothese widerlegt nach N Iterationen
  │  ▼
  │  exploit_refuted    (terminal-neg, User markiert als closed mit Begründung)
  │
  └─ deep-dive D3 (Erfolg): Probe reproduzierbar, Trace korreliert
     ▼
     exploit_confirmed
       │
       │ deep-dive D5: Regressionstest und Fixture geschrieben, rot
       ▼
       regression_drafted
         │
         │ deep-dive D6: Fork-Branch angelegt, Patch entworfen
         ▼
         fix_in_progress
           │
           │ deep-dive D7: WEBTREES_SOURCE umschalten, Regression grün
           ▼
           fix_verified    (terminal-pos, User schließt manuell nach PR-Vorbereitung)
```

**Seitenpfade:**
- `exploit_confirmed → closed` direkt, wenn der User das Finding als "wontfix" oder "bereits bekannt" klassifiziert.
- `fix_in_progress → exploit_confirmed` zurück, wenn der Patch sich als unwirksam herausstellt (Regression bleibt rot nach `WEBTREES_SOURCE`-Wechsel).

**Persistenz:** Jeder Übergang wird in einem Append-only-Log `docs/security-audit/tasks/SEC-AUDIT-NNN_*.md` unter dem Abschnitt `## Status-Historie` festgehalten, inkl. Zeitstempel und Auslöser-Kommando:

```markdown
## Status-Historie

| Zeitpunkt | Von | Nach | Ausgelöst durch |
|---|---|---|---|
| 2026-04-08T14:12:34Z | queued | in_analysis | sweep S7 |
| 2026-04-08T15:01:02Z | in_analysis | in_progress | deep-dive D0 |
| 2026-04-08T15:34:11Z | in_progress | exploit_attempted | deep-dive D3 (r1) |
| 2026-04-08T15:47:22Z | exploit_attempted | exploit_confirmed | deep-dive D3 (r4) |
```

---

## 6 Kontext-Management

**Problem:** Ein Deep-Dive-Task kann über viele Iterationen gehen. Jede Iteration produziert Probe-Artefakte, Trace-Dumps, PerfSchema-Ausschnitte. Der Kontext-Window von Claude ist begrenzt.

**Strategie:**

1. **Kontext-File wächst kumulativ** (`artifacts/security-audit/<run-id>/tasks/SEC-AUDIT-042/context.md`), aber nur neue Iteration wird komplett angehängt; ältere Iterationen werden nach 5 Iterationen auf Zusammenfassungen verdichtet.
2. **Verdichtung erfolgt durch einen separaten Subagent-Call** (Sonnet, billig), der alte Iterationsblöcke zu 200-Wort-Zusammenfassungen kondensiert und die Original-Probe-Artefakte unter `iterations/<N>.json.gz` komprimiert.
3. **Pinnung kritischer Informationen:** Die Task-Frontmatter + die letzten 2 Iterationen werden nie verdichtet — sie bleiben immer volltextverfügbar.
4. **Bei Kontext-Überlauf:** Der Agent kann jederzeit `Read` auf `iterations/<N>.json.gz` aufrufen (nach vorheriger Dekompression) und eine alte Iteration auffrischen.

**Referenz-Dokumente** werden nicht dupliziert: Wenn der Agent `docs/security-audit/02_threat_model.md` braucht, liest er die Datei per `Read`. Der Kontext-File enthält nur **neue** Erkenntnisse, die aus dem Audit entstehen.

---

## 7 Parallelisierung

**Innerhalb eines Sweep-Laufs:**

- Phase S3 (T1 LLM-Triage): max 4 Subprozesse gleichzeitig (API-Rate-Limit)
- Phase S7 (Erste Hypothesen): max 2 Subprozesse gleichzeitig (Opus ist teurer)

**Zwischen Läufen:** Nur ein Audit-Lauf gleichzeitig, enforced durch Advisory-Lock unter `artifacts/security-audit/.lock`.

**Zwischen Audit und anderen Testläufen:** Der Advisory-Lock verhindert `make test-integration`/`make test-e2e` während des Audit-Laufs nicht direkt (das sind andere Scripts), aber der Pre-Flight-Check in S0 bricht ab, wenn PHPUnit/Playwright-Prozesse laufen. Umgekehrt: `make test-integration` selbst prüft den Audit-Lock nicht. Die Regel muss daher **manuell** befolgt werden (CLAUDE.md §"Parallelitäts- und Timeout-Regeln").

---

## 8 Abbruch-Szenarien

| Situation | Reaktion |
|---|---|
| User drückt Ctrl+C während Sweep | SIGINT → Sub-Subprozesse beenden, Advisory-Lock entfernen, Zwischenstände persistieren, Exit-Code 130 |
| Budget-Limit erreicht | Saubere Beendigung: Aktueller Task zu Ende, danach Stop, Exit-Code 0 mit Warnung in run-summary.md |
| `webtrees`-Container crasht | Deep-Dive-Session bekommt HTTP-Fehler → Agent erkennt via Feedback, user muss Container neu starten und Session fortsetzen (Kontext bleibt erhalten) |
| Visitor-Sandbox-Escape bestätigt | **Sofort-Stop** des Sweep-Modus. Der Task wird auf `exploit_confirmed` gesetzt und in den Embargo-Modus (siehe [`10_fixing_and_disclosure.md`](10_fixing_and_disclosure.md)) überführt. Keine weiteren Probes bis manueller Freigabe durch User |
| `make setup` schlägt fehl nach WEBTREES_SOURCE-Wechsel | Phase D7 bricht ab, Task bleibt in `fix_in_progress`, User muss manuell recovern (`make down && make up && make setup`) |
| API-Rate-Limit | Exponentieller Backoff innerhalb des Loops, max 3 Retries, danach Task auf `queued` zurück und nächsten Task nehmen |

---

## 9 Invocation-Beispiele

**Erster Audit-Lauf ohne vorherige Daten:**

```bash
make up
make setup
make test-integration                # Baseline-Coverage
scripts/security-audit-loop.sh sweep --budget-usd 50
```

**Inkrementelles Re-Triage nach Upstream-Update:**

```bash
cd upstream/webtrees && git pull && cd -
scripts/security-audit-loop.sh sweep --incremental
```

**Deep-Dive in einen priorisierten Task:**

```bash
scripts/security-audit-loop.sh deep-dive SEC-AUDIT-042
```

**Nachverifikation eines Fixes im Fork-Repo:**

```bash
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees make setup
podman-compose exec webtrees vendor/bin/phpunit \
    --configuration=/tests/layer3-integration/phpunit-integration.xml \
    --filter='SEC_AUDIT_042'
# Test muss grün werden
scripts/security-audit-loop.sh verify SEC-AUDIT-042   # Status → fix_verified
```

**Liste aller offenen Tasks:**

```bash
scripts/security-audit-loop.sh list --status in_progress,exploit_confirmed,regression_drafted
```

---

## 10 Trockenlauf und Dry-Run-Modus

Der Sweep-Modus akzeptiert `--dry-run`. Dann werden:

- T0/T2/T3 normal ausgeführt (mechanisch, kein LLM)
- T1 nur für die Top-5-Dateien (statt für alle mit Cache-Miss) durchgeführt
- S6 produziert einen **Preview** der neuen Tasks, ohne sie anzulegen
- S7 wird übersprungen

Das dient zum schnellen Smoke-Test nach Änderungen an der Triage-Pipeline oder der Score-Formel, ohne einen vollen LLM-Lauf zu zahlen.

---

## 11 Querverweise

- [01_scope_and_tracks.md](01_scope_and_tracks.md) §7 — Stop-Kriterien im Kontext Visitor-Sandbox-Escape
- [03_infrastructure_usage.md](03_infrastructure_usage.md) §11 — Exklusivitätsregel
- [04_triage_pipeline.md](04_triage_pipeline.md) — Was T0/T1/T2/T3 mechanisch tun
- [05_security_trace_middleware.md](05_security_trace_middleware.md) §5 — ENV-Guard, den Phase S1 setzt
- [07_prompts/prompt_02_whitebox_deep_dive.md](07_prompts/prompt_02_whitebox_deep_dive.md) — System-Prompt für Deep-Dive-Sitzungen
- [10_fixing_and_disclosure.md](10_fixing_and_disclosure.md) — Embargo-Modus und Fix-Workflow
- [tasks/_template.md](tasks/_template.md) — Initialwerte der Task-Frontmatter
