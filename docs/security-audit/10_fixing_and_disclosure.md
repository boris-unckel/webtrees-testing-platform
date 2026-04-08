<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# 10 — Fixing & Disclosure Workflow

**Zweck:** Verbindlicher Ablauf, wie ein bestätigter Audit-Befund (`SEC-AUDIT-<NNN>`) im Fork-Repo gefixt, im Testing-Repo regressiert und **manuell** durch den User offengelegt wird. Dies ist der V1-Workflow aus der Planungs-Runde: **kein** automatischer PR, **kein** automatischer `gh`-Befehl.

**Vorgänger:** `prompt_05_validation.md` (Validierung gegen unpatched + patched).
**Nachfolger:** `11_finding_report_template.md` (Report-Struktur für Disclosure).

## 1. Zwei-Repo-Struktur

Der Fix-Workflow koppelt zwei Repos:

| Repo | Pfad | Rolle |
|---|---|---|
| **webtrees-testing-platform** | `/home/borisunckel/phpprojects/webtrees-testing-platform` | Exploit-Reproduktion, Regression-Tests, Audit-Artefakte (Prompts, Traces, Hypothesen) |
| **webtrees-upstream/webtrees** (Fork) | `/home/borisunckel/phpprojects/webtrees-upstream/webtrees` | Patch-Branches, PR-Quelle zum Upstream `fisharebest/webtrees` |

Der Fork hat:
- `origin` → `boris-unckel/webtrees` (GitHub-Remote des Users)
- `upstream` → `fisharebest/webtrees` (Original-Upstream)
- Arbeits-Branch nach Rebase auf upstream/main: `5349_add_tests`

Die Kopplung zwischen beiden Repos läuft über `WEBTREES_SOURCE` (siehe `08_layer_integration.md` §2.6 und CLAUDE.md „Optionales Modul-Mounting"-Konzept, analog angewendet auf die webtrees-Source selbst).

## 2. Branch-Naming

Pro Task wird **genau ein** Fix-Branch im Fork-Repo erzeugt:

```
security-audit-<NNN>-<slug>
```

- `<NNN>` = zero-padded Task-Nummer (z. B. `042`)
- `<slug>` = kurzer, lowercased, bindestrich-getrennter Themenstring (z. B. `gedcom-note-xss`)

**Beispiele:**
- `security-audit-042-gedcom-note-xss`
- `security-audit-017-media-upload-polyglot`
- `security-audit-103-search-filter-sqli`

**Regeln:**

1. Ein Branch pro Task, auch bei mehreren bestätigten Hypothesen (`H1`, `H2`, …). Mehrere Hypothesen derselben Task werden als **zusammenhängender** Fix committet.
2. Branch wird **vom User gewählten** Base-Branch abgezweigt. Default: `5349_add_tests` (aus der Session-Doku als aktueller Arbeitszweig des Forks). Der Driver nutzt `git rev-parse --abbrev-ref HEAD` in `webtrees-upstream/webtrees` zum Zeitpunkt der Branch-Erzeugung — wenn das nicht `5349_add_tests` ist, warnt der Driver den User **vor** der Branch-Erzeugung.
3. Branches bleiben bestehen bis der User sie manuell löscht. Kein automatisches Cleanup.

## 3. Fix-Draft durch den Driver (Phase D6)

`06_agentic_loop_driver.md` §4 Phase D6 lässt Opus einen Fix-Draft als Diff erzeugen. Regeln:

1. **Minimaler Scope:** Der Fix adressiert **nur** den in der Hypothese identifizierten Sink. Keine stilistischen Änderungen, keine Refactorings außerhalb der betroffenen Methode.
2. **Kein neuer Test-Workaround:** Der Fix darf **nicht** auf die Probe-Header (`X-Audit-Probe`) oder Test-Fixtures reagieren. Das würde den Finding verschleiern, nicht fixen (`prompt_05_validation.md` Regel 6).
3. **Diff-Format:** Der Driver extrahiert den Diff aus der Opus-Antwort und wendet ihn im Fork-Repo via `git apply --index` an. Wenn der Diff nicht sauber angewendet werden kann, Status zurück auf `exploit_confirmed` und Phase D6 wird mit Kontext-Update wiederholt (max. 2 Retries).
4. **Commit-Nachricht:** Der Driver erzeugt die Commit-Nachricht nach folgendem Muster:

   ```
   security: fix SEC-AUDIT-<NNN> — <kurzer, nicht-alarmierender Titel>

   Addresses hypothesis H<n> confirmed in webtrees-testing-platform.
   Regression test: layer3-integration/tests/Security/SecAudit<NNN>Test.php::test_h<n>_<name>

   Task-Details und Exploit-Artefakte bleiben im webtrees-testing-platform
   repository (privat, solange embargoed).

   Signed-off-by: <user-git-config>
   ```

5. **Kein Co-Author-Tag.** Fix-Commits im Fork bekommen **nicht** das `Co-Authored-By: Claude Opus 4.6`-Trailer, da der User den Fix noch manuell sichtet und die Urheberschaft klar dem User zugeordnet bleiben soll. (Ausnahme zur generellen Commit-Regel in CLAUDE.md; gilt ausschließlich für Audit-Fix-Commits im Fork-Repo.)
6. **GPG-Signing:** Der Fork-Repo hat dieselbe Global-Config wie das Testing-Repo (`commit.gpgsign=true`). Wenn Signing fehlschlägt, bricht Phase D6 ab — **kein** `--no-gpg-sign`-Fallback.

## 4. Fix-Verification (Phase D7)

Driver führt zwei Test-Runs durch, wie in `08_layer_integration.md` §2.6 beschrieben:

```bash
# 1) Baseline: Regression reproduziert Exploit auf unpatched webtrees
WEBTREES_SOURCE=./upstream/webtrees \
  make test-integration-security-<NNN>
# Erwartet: FAILURE (Regression-Test failt, weil der Exploit funktioniert)

# 2) Patched: Regression schlägt auf Fork-Branch nicht mehr an
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees \
  make test-integration-security-<NNN>
# Erwartet: PASS (Fix wirkt)

# 3) Layer-2-Regressions-Check: keine neuen Failures in der Full-Suite
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees \
  make test-unit
# Erwartet: keine NEW FAILURES gegenüber Baseline
```

**Wichtig:** Zwischen Step 1 und Step 2 darf der Test-Code **nicht** verändert werden. Beide Runs laufen gegen **identischen** Test-Quellcode — nur `WEBTREES_SOURCE` unterscheidet sich. Andernfalls ist der Vergleich nicht aussagekräftig.

Der Driver loggt alle drei Run-Outputs in `artifacts/security-audit/deepdive/<NNN>/verification/` und speist sie in `prompt_05_validation.md` als Kontext-File.

## 5. Status-Lifecycle (Fix-relevant)

Nur die fix-relevanten Status-Übergänge (komplette Lifecycle in `06_agentic_loop_driver.md` §4):

```
exploit_confirmed
  → fix_in_progress          (Phase D6 aktiv)
  → fix_drafted              (Diff erzeugt, noch nicht committet)
  → fix_committed            (git commit im Fork erfolgreich)
  → fix_verified             (Phase D7 Validation hat pass gegeben)
  → awaiting_user_review     (Driver hat NEEDS_USER_REVIEW.md ergänzt)

Rückwärts:
  fix_rejected (aus D7) → fix_in_progress (mit validation_failure_count++)
  Bei validation_failure_count >= 2 → needs_manual_review
```

Jeder Status-Wechsel wird **sofort** in `tasks/SEC-AUDIT-<NNN>.md` Frontmatter und `tasks/INDEX.md` gespiegelt (User-Vorgabe aus Planungs-Runde 2: „Status immer direkt nach Erledigung aktualisieren").

## 6. Manuelle User-Schritte nach `awaiting_user_review`

Der Driver **beendet** den Deep-Dive an diesem Punkt. Die restlichen Schritte macht der User manuell:

### 6.1 Review im Testing-Repo

```bash
cd /home/borisunckel/phpprojects/webtrees-testing-platform
# Artefakte sichten
less artifacts/security-audit/deepdive/<NNN>/validation.md
less artifacts/security-audit/deepdive/<NNN>/hypotheses.md
cat tasks/SEC-AUDIT-<NNN>.md
# Regression lokal nachvollziehen
make test-integration-security-<NNN>
```

### 6.2 Review im Fork-Repo

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
git log -1 security-audit-<NNN>-<slug>
git diff 5349_add_tests..security-audit-<NNN>-<slug>
# Lokale Linting/Style-Prüfung, falls gewünscht
```

### 6.3 PR-Öffnung (optional, manuell)

Wenn der User zufrieden ist und der Fix zur Upstream-Einreichung geeignet ist:

```bash
cd /home/borisunckel/phpprojects/webtrees-upstream/webtrees
git push origin security-audit-<NNN>-<slug>
gh pr create \
  --base main \
  --head boris-unckel:security-audit-<NNN>-<slug> \
  --title "Security fix for SEC-AUDIT-<NNN>" \
  --body "$(cat <<'EOF'
## Summary
<1–2 Sätze aus validation.md §Findings-Summary>

## Regression
Test suite: layer3-integration/tests/Security/SecAudit<NNN>Test.php

## Trace-Artefakte
Privat im webtrees-testing-platform Audit-Trail.

## Disclosure
<public-ready | embargoed mit Koordination>
EOF
)"
```

**Nicht** vom Driver ausgeführt. Der User entscheidet über Timing und Kommunikation.

### 6.4 Task-Abschluss

Nach User-Review:

```bash
cd /home/borisunckel/phpprojects/webtrees-testing-platform
# Task-Status final setzen
<editor> tasks/SEC-AUDIT-<NNN>.md
# Frontmatter: status: done
#             user_reviewed_at: 2026-04-08
#             disclosure_state: pr_opened | private | embargoed
#             pr_url: <optional>
# Index-Update:
<editor> tasks/INDEX.md
# Eintrag von awaiting_user_review → done
```

Alternativ kann der User den Driver per Kommando erneut triggern: `./scripts/security-audit-mark-done.sh <NNN>` — der Driver setzt dann die Felder selbst. Dieses Skript wird bei Initial-Setup vom Driver erzeugt (idempotent).

## 7. Disclosure-States

| Wert | Bedeutung | Fixture-Policy-Kopplung |
|---|---|---|
| `embargoed` | Fix noch nicht public, Koordination mit Upstream-Maintainer nötig | Fixtures müssen `redaction_policy: embargoed` oder `internal-only` haben |
| `pr_opened` | User hat manuell PR geöffnet, Upstream-Review läuft | Fixtures bleiben `embargoed` bis Upstream-Merge |
| `merged` | Upstream hat gemerged und Release geschnitten | Fixtures dürfen auf `public-ready` gesetzt werden |
| `private` | Fix bleibt nur im Fork, kein PR beabsichtigt (z. B. bei auditspezifischen Härtungen) | Fixtures nach User-Ermessen |
| `dropped` | Finding wird nicht gefixt (z. B. akzeptiertes Residual-Risiko) | Fixtures nach User-Ermessen, Begründung in Task-Frontmatter |

## 8. Halt-Flag für kritische Befunde

Aus `prompt_04_trace_correlation.md` §7: Bei bestätigtem Visitor→Sandbox-Escape setzt der Driver den globalen Halt-Flag `artifacts/security-audit/HALT_CRITICAL.flag`. Bedeutung:

1. Sweep-Modus pausiert sofort (Phase S0 prüft vor jedem neuen Lauf).
2. Laufende Deep-Dives auf **anderen** Tasks dürfen zu Ende laufen, aber keine neuen starten.
3. Die betroffene Task wird bevorzugt auf `awaiting_user_review` gebracht.
4. User wird via `NEEDS_USER_REVIEW.md` informiert (append-only).
5. Der Flag wird **nur** durch manuellen User-Eingriff entfernt: `rm artifacts/security-audit/HALT_CRITICAL.flag`. Der Driver entfernt ihn **nicht** automatisch, auch nicht nach `fix_verified` — der User entscheidet, ob die Findings einzeln durchgearbeitet werden oder ein Embargo-Release auf einmal gemacht wird.

## 9. Kein automatischer Disclosure-Kanal

- **Kein** automatisches Posten in öffentliche Security-Advisories.
- **Kein** automatisches Füllen von GitHub Security Advisories (GSA) im Fork oder Upstream.
- **Kein** automatisches E-Mail-Verschicken an `security@webtrees.net` oder vergleichbar.

Der Grund: Alle Disclosure-Kanäle haben Timing-, Formulierungs- und Beziehungs-Aspekte, die nicht aus dem LLM-Kontext erschließbar sind. Der User macht diese Schritte manuell und nutzt `11_finding_report_template.md` als Vorlage für Kommunikation.

## 10. Rollback-Szenario

Falls sich nach `fix_verified` herausstellt, dass der Fix einen Regression in einem **nicht** durch `make test-unit` oder `make test-integration` abgedeckten Pfad erzeugt (z. B. ein Playwright-E2E failing, den der Driver nicht im Gate hat), dann:

1. User triggert `./scripts/security-audit-rollback.sh <NNN>` (vom Driver initial erzeugt, idempotent).
2. Skript setzt Task-Status zurück auf `fix_in_progress`, löscht den letzten Commit im Fork-Branch via `git reset --soft HEAD~1` (kein `--hard`, damit der Diff erhalten bleibt), und löscht die `verification/`-Artefakte.
3. Sweep-Driver kann die Task re-queuen.

**Hinweis:** Das Skript nutzt `--soft` bewusst, um keine Arbeit zu zerstören. Der User kann dann manuell am Fix weiterarbeiten oder den Driver erneut anstoßen.

## 11. Artefakt-Retention

Alle Audit-Artefakte bleiben **dauerhaft** im Testing-Repo:

- `artifacts/security-audit/deepdive/<NNN>/` — Kontext-Files, Hypothesen, Decisions, Validation
- `artifacts/security-audit/traces/<NNN>_*` — SecurityTraceMiddleware-JSON-Artefakte
- `artifacts/security-audit/triage/` — T0/T1-Batches
- `tasks/SEC-AUDIT-<NNN>.md` — Task-Frontmatter und Summary

Diese Pfade sind **nicht** in `.gitignore`. Der User entscheidet pro Commit, was ins Git eingecheckt wird und was nicht (insbesondere große Trace-Batches können separat ignoriert werden — der Driver sollte beim Initial-Setup einen Vorschlag für `.gitignore`-Einträge ausgeben, aber **nicht** automatisch committen).

Weiter: `11_finding_report_template.md` (Standardisierte Report-Struktur).
