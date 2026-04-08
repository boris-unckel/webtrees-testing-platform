<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt 05 — Validation (Regression + Fork-Fix)

**Rolle in der Pipeline:** D7 in `06_agentic_loop_driver.md` §4 (Deep-Dive-Modus, Phase D7).
**Modell:** `claude-opus-4-6` (finale Validierung, sorgfältige Urteilskraft).
**Vorgelagert:**
- `prompt_04_trace_correlation.md` hat `hypothesis_confirmed` geliefert.
- Driver hat in Phase D5 eine Regression-Testklasse gemäß `08_layer_integration.md` erzeugt und in Phase D6 einen Fix-Draft im Fork `webtrees-upstream/webtrees` auf Branch `security-audit-<NNN>-<slug>` commitiert.
**Nachgelagert:** Task-Status `fix_verified` → User-Sichtung (manueller PR-Schritt, V1-Workflow aus `10_fixing_and_disclosure.md`).

## 1. Zweck

Finale, **unabhängige** Validierung aller Artefakte einer `SEC-AUDIT-<NNN>` Task:

1. Die Regression-Testklasse (`layer3-integration/tests/Security/SecAudit<NNN>H<n>Test.php`) reproduziert den Exploit deterministisch auf dem unpatched webtrees.
2. Die Regression-Testklasse schlägt auf dem **gepatchten** Fork-Branch nicht mehr fehl — der Fix wirkt.
3. Die Regression-Testklasse verursacht **keine** Regressionen in bestehenden Layer-2-Tests.
4. Die Finding-Zusammenfassung ist konsistent mit dem gesamten bisherigen Artefakt-Pfad (`hypotheses.md`, `decision.md`, Regression-Code, Fix-Diff).
5. Task-Status wird **sofort** nach dem Validation-Urteil aktualisiert: `fix_verified` bei Pass, `fix_rejected` bei Fail.

Der Prompt produziert **keinen** Fix und **keinen** zusätzlichen Exploit. Er urteilt nur über das vorhandene Material.

## 2. Eingabe

Kontext-File: `artifacts/security-audit/deepdive/<NNN>/validation_context.md`

```markdown
# SEC-AUDIT-<NNN> — Validation Context

## Task-Metadaten
<frontmatter-dump aus tasks/SEC-AUDIT-<NNN>.md>

## Confirmed Hypothesis
<confirmed H<n> aus hypotheses.md + decision.md>

## Regression-Testklasse (komplett)
<vollständiger PHP-Inhalt von layer3-integration/tests/Security/SecAudit<NNN>H<n>Test.php>

## Fixture(s)
<vollständiger JSON-Inhalt aller referenzierten Payloads aus fixtures/security/payloads/>

## Fix-Diff (aus Fork-Branch)
<git diff Output: webtrees-upstream/webtrees security-audit-<NNN>-<slug> vs. origin-base>

## Test-Run unpatched (Baseline)
- WEBTREES_SOURCE: default (upstream main)
- Command: make test-integration (nur SecAudit<NNN>H<n>Test Klasse)
- Ergebnis: <pass/fail + output-auszug>

## Test-Run patched (Fork)
- WEBTREES_SOURCE: /home/borisunckel/phpprojects/webtrees-upstream/webtrees (branch security-audit-<NNN>-<slug>)
- Command: make test-integration (nur SecAudit<NNN>H<n>Test Klasse)
- Ergebnis: <pass/fail + output-auszug>

## Layer-2 Regression-Check
- Command: make test-unit (volle Suite)
- Ergebnis unpatched: <pass/fail>
- Ergebnis patched: <pass/fail>
- Diff der Testzahlen: <z. B. "+0 neue Failures">
```

**Wichtig:** Der Driver sammelt die Test-Run-Resultate bereits vor dem Prompt-Aufruf. Opus analysiert nur Logs, führt keine Tests aus.

## 3. Ausgabe

Datei: `artifacts/security-audit/deepdive/<NNN>/validation.md` mit Pflichtstruktur:

```markdown
# Validation — SEC-AUDIT-<NNN>

## Gesamturteil
<fix_verified | fix_rejected | validation_incomplete>

## Prüfpunkte

### P1 — Regression reproduziert Exploit unpatched
- Status: <pass/fail>
- Evidence: <konkrete Assertion oder Failure-Zeile aus Test-Output>
- Bemerkung: <falls edge-case: kurze Erklärung>

### P2 — Regression schlägt auf Fork-Branch nicht mehr an
- Status: <pass/fail>
- Evidence: <Test-Run-Resultat patched, relevanter Output>
- Bemerkung:

### P3 — Keine Layer-2-Regressionen durch Fix
- Status: <pass/fail>
- Evidence: <Diff der Layer-2 Test-Counts>
- Bemerkung:

### P4 — Konsistenz der Artefakt-Kette
- Status: <pass/fail>
- Evidence: Prüfe, ob:
  - Hypothese in `hypotheses.md` zum Regression-Test passt (gleiche Methode/Pfad/Payload-Klasse)
  - Fix-Diff den in der Hypothese vermuteten Sink tatsächlich adressiert
  - Fixture-Payload die in der Probe-Spec benutzten Parameter trägt
- Bemerkung:

### P5 — Impact-Kategorie bestätigt
- Status: <pass/fail>
- Erklärung: Ist der im `decision.md` genannte Impact (`visitor-sandbox-escape`, `non-admin-rce`, …) durch die Trace-Evidence haltbar, oder war die Hypothese übertrieben?

## Findings-Summary (für finding_report_template.md)
- **Titel:** <prägnant, 1 Zeile>
- **Vector:** <1–2 Sätze>
- **Precondition:** <authstatus, config, daten>
- **Impact:** <kategorie>
- **Affected Path(s):** <liste>
- **Fix-Branch (Fork):** `security-audit-<NNN>-<slug>` auf `webtrees-upstream/webtrees`
- **Regression-Test:** `layer3-integration/tests/Security/SecAudit<NNN>H<n>Test.php`

## Offene Punkte für manuelle Sichtung
<alles, was Opus nicht automatisch entscheiden kann — z. B. „Embargo-Bewertung" oder „Release-Scheduling">

## Empfehlung für User-Workflow
- [ ] User reviewt `hypotheses.md` + `validation.md`
- [ ] User entscheidet über PR-Öffnung im Fork (`10_fixing_and_disclosure.md` V1-Workflow)
- [ ] User entscheidet über Disclosure-Timing
```

## 4. Aufruf

```bash
claude --print \
  --model claude-opus-4-6 \
  --append-system-prompt "$(cat docs/security-audit/07_prompts/prompt_05_validation.md | sed -n '/## 6. Prompt-Körper/,/## 7. Nachbedingungen/p' | head -n -1)" \
  < artifacts/security-audit/deepdive/<NNN>/validation_context.md \
  > artifacts/security-audit/deepdive/<NNN>/validation.md
```

Kein Parallelismus auf derselben Task. Systemweit max. **2** Opus-Calls gleichzeitig (konsistent mit Deep-Dive-Budget).

## 5. Vorbedingungen

- Task ist im Status `fix_in_progress` (Phase D6 abgeschlossen).
- Regression-Testklasse existiert und ist unter `layer3-integration/tests/Security/` abgelegt.
- Fork-Branch `security-audit-<NNN>-<slug>` existiert im lokalen `webtrees-upstream/webtrees` Checkout.
- Test-Runs (unpatched + patched) sind bereits durchgeführt und die Outputs liegen im Kontext-File.
- Layer-2-Regression-Check ist durchgeführt und im Kontext-File dokumentiert.

## 6. Prompt-Körper

*(Dieser Abschnitt wird via `--append-system-prompt` angehängt.)*

---

Du bist Review-Lead für einen abschließenden Security-Fix-Gate. Du erhältst ein Kontext-File mit: Task-Metadaten, bestätigter Hypothese, Regression-Testklasse, Fixture-Payloads, Fix-Diff aus dem Fork-Branch, Test-Run-Resultaten (unpatched/patched) und einem Layer-2-Regression-Check. Dein Job: **Pass oder Fail**, auf Basis eindeutiger Evidenz.

### Harte Regeln

1. **Keine eigenen Test-Läufe, keine Code-Änderungen.** Du urteilst über Vorhandenes.
2. **Alle 5 Prüfpunkte durchgehen.** Überspringen = `validation_incomplete`, nicht `fix_verified`.
3. **`fix_verified` nur wenn P1..P5 alle `pass`.** Ein einziges `fail` → `fix_rejected`.
4. **P4 (Konsistenz) ist die strengste Prüfung.** Wenn die Regression-Testklasse einen **anderen** Angriffsvektor testet als die Hypothese beschreibt (z. B. Testklasse macht SQL-Injection-Probe, Hypothese war XSS), ist das `fail` — egal wie grün die Tests sind.
5. **P5 (Impact) darf `pass` sein, auch wenn die Kategorie heruntergestuft wird.** Beispiel: Hypothese sagte `visitor-sandbox-escape`, aber die Evidence stützt nur `visitor-rce-without-shell`. Vermerke das im Bemerkungs-Feld, setze P5 auf `pass` wenn der Downgrade korrekt dokumentiert wurde, auf `fail` wenn der Confirmed-Block in `decision.md` weiterhin die höhere Kategorie behauptet.
6. **Fix-Diff-Minimum:** Der Diff muss den in der Hypothese genannten Sink oder eine klar äquivalente Schutz-Schicht adressieren. Ein Fix, der nur den Probe-Header blockiert (`X-Audit-Probe`), ist **kein** valider Fix — er ist ein Test-Workaround.
7. **Keine Kosmetik-Fixes akzeptieren.** Wenn der Fix-Diff nur Whitespace/Renames enthält, Status = `fix_rejected` mit Begründung „no semantic change".
8. **Report-Summary ist Pflicht, auch bei `fix_rejected`.** Der User braucht die Zusammenfassung, um die nächste Iteration zu planen.

### Ausgabeformat

Markdown exakt nach §3. Keine Vorbemerkungen, keine Nachbemerkungen, keine Meta-Kommentare. Jeder Prüfpunkt bekommt `Status`, `Evidence`, `Bemerkung` — auch wenn Bemerkung leer ist („—").

### Verbote

- **Kein** Fix-Vorschlag, wenn `fix_rejected`. Nur Diagnose.
- **Keine** Änderung der Impact-Kategorie, ohne sie in P5 zu dokumentieren.
- **Kein** Zitieren von Code, der nicht im Kontext-File steht.
- **Keine** Empfehlung an den Agenten-Loop (`06_agentic_loop_driver.md`), einen Schritt zu überspringen.

---

## 7. Nachbedingungen

Nach Rückkehr:

1. Driver liest das Feld `## Gesamturteil` und setzt Task-Status **sofort**:
   - `fix_verified` → Task-Status `fix_verified`, Eintrag in `tasks/INDEX.md` aktualisieren, Task aus aktiver Queue entfernen.
   - `fix_rejected` → Task-Status zurück auf `fix_in_progress`, Frontmatter-Feld `validation_failure_count` inkrementieren. Bei `validation_failure_count >= 2` → Status `needs_manual_review`.
   - `validation_incomplete` → Status bleibt, Frontmatter `validation_retry: true`, Driver wiederholt Phase D7 max. 1 Mal.
2. Finding-Summary aus §3 wird in `tasks/SEC-AUDIT-<NNN>.md` im Abschnitt `## Finding Summary` gespeichert (Überschreiben erlaubt).
3. Bei `fix_verified`: Task-Verzeichnis `artifacts/security-audit/deepdive/<NNN>/` wird **nicht** gelöscht — es bleibt als Audit-Trail für spätere Disclosure (`10_fixing_and_disclosure.md`).
4. Driver benachrichtigt User (Schreibt in `artifacts/security-audit/NEEDS_USER_REVIEW.md`, append-only), dass Task SEC-AUDIT-NNN auf User-Sichtung wartet. **Kein** automatisches `gh pr create` — der User öffnet PRs manuell (V1-Workflow-Entscheidung aus Runde 2 des Planungs-Prompts).
5. Bei globalem Halt-Flag (`HALT_CRITICAL.flag` aus D4): Auch nach `fix_verified` bleibt der Sweep-Driver pausiert, bis der User den Flag entfernt.
