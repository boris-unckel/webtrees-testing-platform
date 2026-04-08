<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt 04 — Trace-Korrelation

**Rolle in der Pipeline:** D4 in `06_agentic_loop_driver.md` §4 (Deep-Dive-Modus, Phase D4).
**Modell:** `claude-sonnet-4-6` (Trace-Analyse ist strukturiert und volumenstark → Sonnet kostet weniger).
**Vorgelagert:** `prompt_03_exploit_attempt.md` hat Probe-Run ausgeführt, Trace-Artefakt liegt vor.
**Nachgelagert:** `prompt_05_validation.md` bei bestätigter Hypothese; erneut `prompt_03_exploit_attempt.md` bei `need_iteration`.

## 1. Zweck

Den nach dem Probe-Run entstandenen `SecurityTraceMiddleware`-JSON-Ausgabe **gegen die Hypothesen-Erwartung aus `prompt_02_whitebox_deep_dive.md` abgleichen** und eine harte Entscheidung treffen:

- **`hypothesis_confirmed`** — Die Trace-Signatur stimmt überein, der erwartete Sink wurde getroffen, der Angriffsvektor ist plausibel funktionsfähig.
- **`hypothesis_rejected`** — Die Trace zeigt eindeutig eine schützende Middleware oder einen abweichenden Pfad; die Hypothese ist im aktuellen Codestand falsch.
- **`need_iteration`** — Die Trace zeigt teilweise den erwarteten Pfad, aber nicht alle Pflicht-Felder; konkrete Anpassung des Probe-Skripts wird vorgeschlagen.

Die Entscheidung schreibt der Driver in `probe_H<n>_iter<i>/decision.md`. Prompt 04 liefert den **Inhalt** dieser Datei.

## 2. Eingabe

Der Driver erzeugt `artifacts/security-audit/deepdive/<NNN>/correlation_context_H<n>_iter<i>.md` mit:

```markdown
# SEC-AUDIT-<NNN> H<n> iter<i> — Correlation Context

## Erwartung (aus expected.md, kopiert)
<vollständiger Inhalt von probe_H<n>_iter<i>/expected.md>

## Probe-Run-Resultate
### HTTP
- Status: <code>
- Response Headers: <auszug>
- Response Body (max 2KB): <body>

### Stderr-Log des Skripts
<inhalt von run.log>

## SecurityTrace-Artefakte
### Artefakt 1: /artifacts/security-audit/traces/<NNN>_H<n>_iter<i>_<ts1>.json
<vollständiger JSON-Inhalt>

### Artefakt 2 (falls mehrere Requests): /artifacts/security-audit/traces/<NNN>_H<n>_iter<i>_<ts2>.json
<vollständiger JSON-Inhalt>

## Hypothesen-Tabellenzeile H<n>
<kopiert aus hypotheses.md>

## Iteration-Kontext
- Iteration: <i>
- Max-Iterations: 5
- Vorherige Entscheidungen:
  - iter1: <kurz>
  - iter2: <kurz>
  ...
```

## 3. Ausgabe

Der LLM-Output ist der **vollständige Inhalt** von `probe_H<n>_iter<i>/decision.md`. Pflichtstruktur:

```markdown
# Decision — SEC-AUDIT-<NNN> H<n> iter<i>

## Status
<hypothesis_confirmed | hypothesis_rejected | need_iteration>

## Trace-Auswertung

### Pflicht-Felder aus expected.md
| Feld | Erwartet | Gefunden | Match |
|---|---|---|---|
| `security_audit.hypothesis_id` | "H<n>" | "H<n>" oder "—" | ja/nein |
| `security_audit.expected_sink_hit` | true | true/false | ja/nein |
| `security_audit.branches_taken` enthält | [...] | [...] | ja/teilweise/nein |
| HTTP-Status | <code> | <code> | ja/nein |
| Response-Substring | "<s>" | "<found>" | ja/nein |

### Beobachtete zusätzliche Signale
<z. B. security_audit.middleware_short_circuit, CsrfGuardianHit, etc.>

## Begründung der Entscheidung
<2–5 Sätze, warum confirmed/rejected/need_iteration. Konkret, mit Referenz auf Felder der Tabelle.>

## Bei `need_iteration`: Vorgeschlagene Anpassung für iter<i+1>
- **Änderung am Probe-Skript:** <exakte Zeile oder exakte Ersetzung>
- **Begründung:** <warum diese Änderung das erwartete Feld triggern sollte>
- **Erwartete Änderung im Trace:** <welche Pflicht-Felder-Zeile wird beim nächsten Run grün>

## Bei `hypothesis_rejected`: Residual-Risiko
<Gibt es dennoch Teilhypothesen, die verbleiben? Oder ist H<n> vollständig abzuhaken?>

## Bei `hypothesis_confirmed`: Kurz-Summary für Finding-Report
- **Vector:** <1 Satz>
- **Impact:** <visitor-sandbox-escape | non-admin-rce | ...>
- **Confidence:** <high/medium/low>
- **Nächster Schritt:** Validation via `prompt_05_validation.md`
```

## 4. Aufruf

```bash
claude --print \
  --model claude-sonnet-4-6 \
  --append-system-prompt "$(cat docs/security-audit/07_prompts/prompt_04_trace_correlation.md | sed -n '/## 6. Prompt-Körper/,/## 7. Nachbedingungen/p' | head -n -1)" \
  < artifacts/security-audit/deepdive/<NNN>/correlation_context_H<n>_iter<i>.md \
  > artifacts/security-audit/deepdive/<NNN>/probe_H<n>_iter<i>/decision.md
```

Parallelität: max. **4** Sonnet-Aufrufe gleichzeitig, aber an Probe-Run-Exklusivität gekoppelt: pro Task läuft immer nur **ein** Probe-Iter + Correlation-Paar zur Zeit.

## 5. Vorbedingungen

- `probe_H<n>_iter<i>/run.sh` wurde ausgeführt, `run.log` existiert.
- Mindestens **ein** Trace-Artefakt im Erwartungs-Pfadmuster wurde erzeugt (oder der Driver hat den Zustand `trace_missing` gesetzt — dann ist die Ausgabe automatisch `need_iteration` mit Vorschlag, den Header `X-Audit-Probe` zu prüfen).
- `expected.md` und `hypotheses.md` sind unverändert seit D2 (der Driver prüft via mtime, dass keine manuelle Edit zwischendurch passiert ist).

## 6. Prompt-Körper

*(Dieser Abschnitt wird via `--append-system-prompt` angehängt.)*

---

Du bist Sicherheits-Analyst im Korrelations-Modus. Du erhältst ein Kontext-File mit einer Hypothese, einer Erwartungs-Signatur (`expected.md`), den Probe-Run-Resultaten (HTTP-Status, Response, Stderr) und den JSON-Artefakten der `SecurityTraceMiddleware`. Deine Aufgabe: Eine **harte, nachvollziehbare Entscheidung**, ob die Hypothese bestätigt, abgelehnt oder iteriert werden muss.

### Harte Regeln

1. **Kein Reasoning außerhalb der Tabelle.** Jede Pflicht-Feld-Zeile aus `expected.md` bekommt genau eine Tabellen-Zeile. Übersprungene Felder = sofort `need_iteration` mit Begründung „expected.md unvollständig übernommen".
2. **Match-Regel streng:** `security_audit.branches_taken` darf „teilweise" sein (Untermenge ist erlaubt, solange alle erwarteten Branches enthalten sind). Alle anderen Felder sind booleanes ja/nein.
3. **`hypothesis_confirmed` nur bei vollständiger Übereinstimmung.** Ein einziges „nein" in einer Pflicht-Zeile → `need_iteration` oder `hypothesis_rejected`.
4. **`hypothesis_rejected` nur bei strukturellem Gegenbeweis.** Z. B. `security_audit.middleware_short_circuit` zeigt CSRF-Block, oder ein bekannter SEC-* Feature-Hit ist in den Branches — die Hypothese ist damit im aktuellen Code **nicht** ausnutzbar.
5. **`need_iteration` nur mit konkreter, ausführbarer Anpassung.** Kein vages „Probe verstärken" — sondern „Zeile 42: `-d title=X` ersetzen durch `-d title='<?php system(\"id\"); ?>'` weil der Parser XML-Entities decodiert vor Validation".
6. **Trust the trace, not the HTTP response alone.** HTTP-Status 200 mit leerem Body kann trotzdem einen gefährlichen Sink getroffen haben. Primäre Evidenz = `SecurityTrace` JSON, HTTP nur sekundär.
7. **Iteration-Bewusstsein:** Ab iter3 wächst der Verdacht, dass die Hypothese strukturell falsch ist. Ab iter4 muss die Entscheidung `need_iteration` einen expliziten „letzter Versuch"-Satz enthalten. iter5 darf nicht mehr `need_iteration` sein — entweder confirmed oder rejected.
8. **Keine neue Hypothese erfinden.** Wenn die Analyse eine **andere** potentielle Schwachstelle sichtbar macht, vermerke sie in einem separaten Abschnitt „Side-Observations" — aber H_n-Entscheidung bleibt binär.

### Ausgabeformat

Markdown exakt nach §3. Kein Preamble, keine Abschluss-Floskel. Keine Empfehlung, den Prompt zu verbessern.

### Verbote

- **Kein** Zitieren von Code aus der webtrees-Source, der nicht im Kontext-File steht.
- **Kein** Spekulieren über nicht-beobachtete Middlewares.
- **Keine** pauschale „tiefergehende Untersuchung nötig"-Empfehlung ohne konkreten Änderungsvorschlag.
- **Kein** Mischen zwischen mehreren Hypothesen derselben Task — jede H_n wird isoliert bewertet.

---

## 7. Nachbedingungen

1. Driver speichert die Ausgabe als `probe_H<n>_iter<i>/decision.md`.
2. Driver liest das Feld `## Status` und verzweigt:
   - `hypothesis_confirmed` → Phase D5 (Regression-Draft) starten, Task-Status **sofort** auf `exploit_attempted` → `exploit_confirmed` setzen.
   - `need_iteration` → Phase D3 erneut mit `iter<i+1>`, aber nur wenn `i < 5`.
   - `hypothesis_rejected` → Task-Frontmatter: `hypothesis_status[H<n>] = rejected`. Wenn es weitere Hypothesen gibt, weiter mit `H<n+1>`. Wenn alle Hypothesen rejected sind, Task-Status auf `no_finding` setzen und aus der Deep-Dive-Queue nehmen.
3. `probe_iteration_count` im Task-Frontmatter **sofort** aktualisieren.
4. Bei `hypothesis_confirmed` zusätzlich den Summary-Block an `tasks/SEC-AUDIT-<NNN>.md` anhängen (Abschnitt `## Confirmed Vectors`).
5. Bei Abort-Bedingung „Visitor→Sandbox-Escape bestätigt" (`track: sandbox-escape` + `Impact: visitor-sandbox-escape` + `Confidence: high`): Driver setzt globalen Halt-Flag `artifacts/security-audit/HALT_CRITICAL.flag`, Sweep/Deep-Dive pausieren, User-Benachrichtigung.
