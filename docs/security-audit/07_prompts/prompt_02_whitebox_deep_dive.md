<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt 02 — Whitebox Deep-Dive (Opus)

**Rolle in der Pipeline:** D2 in `06_agentic_loop_driver.md` §4 (Deep-Dive-Modus, Phase D2).
**Modell:** `claude-opus-4-6` (gründlich, code-lesend, hypothesis-generierend).
**Vorgelagert:** T1 Sonnet-Triage (`prompt_01_triage_llm.md`), T3 Task-Erzeugung (`04_triage_pipeline.md` §4).

## 1. Zweck

Für **genau eine** Task `SEC-AUDIT-NNN` eine strukturierte, whitebox-basierte Sicherheits-Analyse durchführen:

1. Quellcode der betroffenen Datei vollständig lesen (nicht nur Ausschnitte).
2. **Hypothesen** formulieren: Angriffsvektor, Vorbedingungen, erwarteter Datenfluss, erwartete SecurityTraceMiddleware-Signatur.
3. **Probe-Spezifikation** erzeugen: konkrete HTTP-Requests / GEDCOM-Payloads / Medien-Uploads mit allen Header- und Body-Feldern.
4. **Regression-Coverage-Spezifikation** erzeugen: welche Layer-3- oder Layer-4-Testklasse soll nach Fix den Angriff blockieren.
5. Task-Status nach D2 von `in_analysis` → `in_progress` **sofort** aktualisieren.

Opus liefert **keine** ausführbaren Exploits direkt — das ist Aufgabe von `prompt_03_exploit_attempt.md`. Opus liefert nur die Hypothesen-Tabelle und Probe-Specs.

## 2. Eingabe

Ein Kontext-File wird vom Deep-Dive-Driver (`06_agentic_loop_driver.md` §4 Phase D1) in `artifacts/security-audit/deepdive/<NNN>/context.md` zusammengestellt:

```markdown
# SEC-AUDIT-<NNN> — <kurztitel>

## Task-Metadaten
- file: <pfad>
- track: <non-admin|sandbox-escape|both>
- verticals_hit: [<V1..V12>]
- final_score: <float>
- llm_score: <int>
- llm_reason: <string>
- notes_for_opus: <string>

## Quellcode der Zieldatei
<vollständiger PHP-Inhalt>

## Routing-Kontext
<auszug aus Route-Definitionen, die auf die Datei zeigen>

## PSR-15 Middleware-Kette vor dem Controller
<auszug aus RequestHandler + Middleware-Reihenfolge>

## Bekannte existierende Tests (Layer 2/3)
<liste der Test-Dateien, die bereits Methoden der Zieldatei abdecken>

## Bekannte SEC-* Features (aus tds_conditions_ref.md)
<liste mit kurzer Beschreibung, nur die, die auf den Pfad oder die Vertikalen zeigen>
```

Das Kontext-File wird vom Driver **kumulativ** geführt — bei Iterationen innerhalb einer Task wird der bisherige Analysestand angehängt (Status-Lifecycle aus `06_agentic_loop_driver.md` §4).

## 3. Ausgabe

Datei: `artifacts/security-audit/deepdive/<NNN>/hypotheses.md`

**Pflichtstruktur:**

```markdown
# SEC-AUDIT-<NNN> — Hypothesen

## Vertical-Zuordnung (verfeinert)
<begründete Bestätigung oder Korrektur der verticals_hit aus T1>

## Hypothesen-Tabelle

| ID | Angriffsvektor | Vorbedingungen | Erwarteter Datenfluss | Erwartete Trace-Signatur | Impact | Confidence |
|---|---|---|---|---|---|---|
| H1 | <kurz> | <zustand, auth, config> | <quelle → sinke> | `security_audit.*` Attribute | <visitor-sandbox-escape, non-admin-rce, ...> | <low/med/high> |
| H2 | ... | ... | ... | ... | ... | ... |

## Pro Hypothese: Probe-Spezifikation

### H1 Probe-Spec
- **Methode:** <HTTP-Verb + Pfad>
- **Header:** `X-Audit-Probe: SEC-AUDIT-<NNN>-H1`, `Cookie: ...`
- **Body/Parameter:** <exakt, copy-pasteable>
- **Erwarteter HTTP-Status:** <code>
- **Erwarteter Response-Substring:** <string>
- **Erwartetes SecurityTrace-Artefakt:** `artifacts/security-audit/traces/<NNN>_H1_<ts>.json` mit `security_audit.hypothesis_id = "H1"`, `security_audit.expected_sink_hit = true`

### H2 Probe-Spec
... (analog)

## Regression-Coverage-Spezifikation

Pro bestätigter Hypothese: eine Layer-3- oder Layer-4-Testklasse.

### H1 Regression
- **Layer:** 3
- **Testklasse:** `layer3-integration/tests/Security/SecAudit<NNN>H1Test.php`
- **Extends:** `SecurityAuditTestCase` (siehe `08_layer_integration.md`)
- **Daten-Provider:** `fixtures/security/payloads/sec_audit_<NNN>.json`
- **Erwartung:** `assertResponseBlocked()` oder `assertTraceAbsent('security_audit.expected_sink_hit')`

### H2 Regression
... (analog)

## Offene Fragen an den Operator
<nur wenn unbedingt nötig — jede Frage blockiert die Automatisierung>

## Nächster Schritt
- [ ] Probe-Loop starten (`prompt_03_exploit_attempt.md`) für Hypothesen in Prioritäts-Reihenfolge H_n
- [ ] Status-Update: `in_analysis` → `in_progress`
```

## 4. Aufruf

```bash
# Deep-Dive-Driver, interaktive Claude-Session (einmal pro Task, nicht pro Hypothese):
claude \
  --model claude-opus-4-6 \
  --append-system-prompt "$(cat docs/security-audit/07_prompts/prompt_02_whitebox_deep_dive.md | sed -n '/## 6. Prompt-Körper/,/## 7. Nachbedingungen/p' | head -n -1)"
```

Nach Start der Session: User-Input = Inhalt von `artifacts/security-audit/deepdive/<NNN>/context.md` wird gepastet.

Alternative non-interaktiv:

```bash
claude --print \
  --model claude-opus-4-6 \
  --append-system-prompt "$(cat docs/security-audit/07_prompts/prompt_02_whitebox_deep_dive.md | sed -n '/## 6. Prompt-Körper/,/## 7. Nachbedingungen/p' | head -n -1)" \
  < artifacts/security-audit/deepdive/<NNN>/context.md \
  > artifacts/security-audit/deepdive/<NNN>/hypotheses.md
```

Parallelität: max. **2** Opus-Calls gleichzeitig (`06_agentic_loop_driver.md` §5).

## 5. Vorbedingungen

- Task-Datei `tasks/SEC-AUDIT-<NNN>.md` existiert mit Status `in_analysis`.
- Kontext-File `artifacts/security-audit/deepdive/<NNN>/context.md` ist vollständig.
- Kein paralleler Deep-Dive auf derselben Task-ID (Driver prüft Lock).

## 6. Prompt-Körper

*(Dieser Abschnitt wird via `--append-system-prompt` an den Opus-Call angehängt.)*

---

Du bist ein Senior-Sicherheitsforscher mit Fokus auf PHP-Web-Frameworks. Du führst eine **Whitebox-Analyse** für genau eine Datei im webtrees-Projekt durch. Du hast Zugriff auf den vollständigen Quellcode der Zieldatei, Routing-Kontext, Middleware-Kette und existierende Tests — alles im übergebenen Kontext-File.

### Harte Regeln

1. **Lies den Quellcode vollständig.** Überspringe keine Methode, keine `use`-Klausel, keine Schleife. Wenn Teile fehlen, vermerke das in „Offene Fragen".
2. **Formuliere Hypothesen, keine Behauptungen.** Jede Hypothese ist eine falsifizierbare Aussage über einen Angriffsvektor.
3. **Jede Hypothese braucht eine Probe-Spec, die ausführbar ist.** Keine vagen „sende präparierte Daten" — exakte Header, Body, Parameter, erwarteter Status.
4. **Jede Hypothese braucht eine Regression-Coverage-Spec.** Wenn du sie nicht spezifizieren kannst, ist die Hypothese nicht reif — verwirf sie oder markiere sie als „not_regressable".
5. **Benutze nur vorhandene Infrastruktur.** Die Regression muss mit `SecurityAuditTestCase` (Layer 3) oder einem Playwright-Flow (Layer 4) umsetzbar sein. Keine neuen Test-Frameworks vorschlagen.
6. **Track-Respekt:** Wenn die Task `track: non-admin`, dann darfst du keine Hypothesen mit `auth_requirement: admin` formulieren. Wenn `track: sandbox-escape`, dann müssen alle Hypothesen auf PHP→Shell, PHP→Filesystem-außerhalb-webroot, PHP→Netzwerk-außerhalb-Container oder vergleichbare Ausbrüche zielen.
7. **Ehrlichkeit über Confidence:** `high` nur wenn der Code-Fluss vollständig lesbar ist und keine schützenden Middlewares dazwischen sind. `low` bei Heuristik.
8. **Keine Fix-Vorschläge.** Fix-Drafting passiert später in `06_agentic_loop_driver.md` Phase D6 und ist **nicht** Aufgabe dieses Prompts.

### Ausgabeformat

Markdown, exakt die Struktur aus `prompt_02_whitebox_deep_dive.md` §3. Keine Meta-Kommentare. Keine Meinungen über das Gesamtprojekt. Keine Vorschläge zur Verbesserung dieses Prompts.

### Verbote

- **Keine** Erfindung von Code-Zeilen, die nicht im Kontext-File stehen.
- **Keine** Übertragung von Erkenntnissen zwischen Tasks (jede Task ist isoliert).
- **Kein** Hypothesen-Pool jenseits von max. **5** H_n pro Task — lieber wenige starke als viele schwache.
- **Kein** Ignorieren von bereits existierenden SEC-* Features — wenn eine Hypothese durch ein existierendes Feature abgedeckt ist, vermerke das explizit und verwirf die Hypothese.

---

## 7. Nachbedingungen

Nach Rückkehr muss der Deep-Dive-Driver:

1. Die Ausgabe-Datei `artifacts/security-audit/deepdive/<NNN>/hypotheses.md` gegen die Pflichtstruktur in §3 prüfen (Headings vorhanden, mindestens eine Hypothese, jede mit Probe-Spec + Regression-Spec).
2. Task-Status **sofort** von `in_analysis` → `in_progress` setzen (`tasks/SEC-AUDIT-<NNN>.md` Frontmatter + `tasks/INDEX.md` Zeile aktualisieren).
3. Hypothesen-IDs H1..H_n in die Task-Metadaten schreiben (Feld `hypotheses: [H1, H2, ...]`).
4. Deep-Dive-Lock freigeben und Phase D3 (`prompt_03_exploit_attempt.md`) für die erste Hypothese triggern.

Bei strukturellem Fehler (fehlende Pflichtabschnitte): Driver setzt Task auf `in_analysis` zurück und markiert sie mit `retry_d2: true` im Frontmatter. Max. **2** Retries, danach manueller Eingriff.
