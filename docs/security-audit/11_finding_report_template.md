<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# 11 — Finding Report Template

**Zweck:** Standardisierte Struktur für den finalen Finding-Report eines `SEC-AUDIT-<NNN>` Befunds. Der Report wird vom User manuell aus den Audit-Artefakten zusammengefügt und für (a) interne Dokumentation, (b) Upstream-Kommunikation und (c) spätere Disclosure genutzt.

**Vorgänger:** `10_fixing_and_disclosure.md` §9 (Kein automatischer Disclosure-Kanal → Template als manuelle Vorlage).

## 1. Nutzung

Dieses Template ist **kein** automatisch erzeugtes Artefakt des Drivers. Der User kopiert die Struktur manuell in eine neue Datei:

```
docs/security-audit/findings/FINDING-SEC-AUDIT-<NNN>.md
```

und befüllt sie auf Basis von:

- `artifacts/security-audit/deepdive/<NNN>/hypotheses.md`
- `artifacts/security-audit/deepdive/<NNN>/validation.md`
- `tasks/SEC-AUDIT-<NNN>.md` Frontmatter
- `fixtures/security/payloads/sec_audit_<NNN>.json` (falls `public-ready`)

Der Driver kann eine **Vorbefüllung** leisten, wenn der User ihn explizit triggert (`./scripts/security-audit-draft-finding.sh <NNN>`). Standardmäßig aber bleibt diese Datei Hand-Arbeit — Reports gehen an externe Parteien und brauchen menschliche Sichtung.

## 2. Template

```markdown
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# FINDING SEC-AUDIT-<NNN> — <kurzer, neutraler Titel>

| Feld | Wert |
|---|---|
| Finding-ID | SEC-AUDIT-<NNN> |
| Projekt | webtrees |
| Upstream-Repo | fisharebest/webtrees |
| Auditor | <Name des Users> |
| Report-Datum | <YYYY-MM-DD> |
| Status | <embargoed \| pr_opened \| merged \| private \| dropped> |
| Track | <non-admin \| sandbox-escape \| both> |
| Impact | <visitor-sandbox-escape \| visitor-rce \| non-admin-rce \| ...> |
| Confidence | <high \| medium \| low> |
| Severity (CVSSv3.1) | <vector-string> / <score> |
| Affected Versions | <z. B. all up to 2.1.21> |
| Fixed in (Fork) | Branch `security-audit-<NNN>-<slug>` on `boris-unckel/webtrees` |
| Fixed in (Upstream) | <PR-URL oder „pending"> |

## Zusammenfassung (1 Absatz)

<2–4 Sätze, die das Problem ohne Jargon erklären. Ziel-Lesergruppe: Upstream-Maintainer, Release-Manager, ggf. Journalisten. Kein Copy-Paste von Probe-Headern.>

## Technische Beschreibung

### Angriffsvektor

<Vollständige Beschreibung der Kette: Welcher Endpunkt, welche Vorbedingungen, welche Parameter, welcher Sink. Verweise auf konkrete Dateien und Methoden mit Zeilenangabe: `app/Http/Controllers/X.php:123`.>

### Vorbedingungen

- Auth: <z. B. „kein Login erforderlich" oder „Member-Rolle mit Tree-Zugriff">
- Config: <z. B. „Registration enabled">
- Daten: <z. B. „mindestens ein Individual-Record im Tree">
- Timing: <z. B. „kein Rate-Limit-Cooldown aktiv">

### Reproduktion

<Schritt-für-Schritt, aus der Sicht des Upstream-Maintainers. Keine Referenz auf `SecurityTraceMiddleware` — das ist Audit-intern. Stattdessen curl-Befehle oder Browser-Schritte, die auch ohne Audit-Infrastruktur nachvollziehbar sind.>

```bash
# 1. Starte eine frische webtrees-Instanz
# 2. Logge dich ein als Standard-Member
# 3. Navigiere zu /tree/<default>/note/N1/edit
# 4. Setze Note-Content auf:
curl -X POST https://webtrees.example/tree/default/note/N1/save \
  -H "Cookie: WT_SESSION=..." \
  -d "note_raw=<payload>" \
  ...
# 5. Öffne das Note-Display: /tree/default/note/N1
# 6. Beobachte, dass <script>alert(1)</script> ausgeführt wird.
```

### Root Cause

<Welche Zeile(n) verursachen das Problem und warum. Referenz auf den Sink im Fix-Diff. Höchstens 1 Absatz.>

### Impact-Begründung

<Warum fällt das Finding in die angegebene Impact-Kategorie. Begründung mit Bezug auf:
- Welche Rolle kann den Angriff auslösen (Visitor, Member, Editor, Admin)
- Welche Rolle wird kompromittiert (Lesen, Schreiben, Escape)
- Welche Daten sind betroffen (Baum-Daten, Session-Tokens, Filesystem, Shell)>

## Fix

### Patch-Beschreibung

<1–3 Sätze über die Fix-Strategie. Kein Code-Dump.>

### Patch (inline)

\`\`\`diff
<Fix-Diff aus dem Fork-Branch, auf max. 30 Zeilen gekürzt wenn nötig — ungekürzte Version ist im Fork-Repo verfügbar.>
\`\`\`

### Warum dieser Fix minimal ist

<Begründung, warum der Fix den Sink gezielt adressiert und keine Regressionen in angrenzender Funktionalität erzeugt. Bezug auf P3 aus `validation.md` (Layer-2-Regression-Check).>

## Regression-Test

### Testklasse

`layer3-integration/tests/Security/SecAudit<NNN>Test.php`

### Testmethoden

| Methode | Hypothese | Oracle |
|---|---|---|
| `test_h1_<name>` | H1 | <assertResponseBlocked \| assertNoSecurityTraceArtifact \| ...> |
| `test_h2_<name>` | H2 | ... |

### Ausführung

\`\`\`bash
# Unpatched (reproduziert Angriff): Test schlägt fehl
WEBTREES_SOURCE=./upstream/webtrees make test-integration-security-<NNN>

# Patched (Fix wirkt): Test läuft grün
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees make test-integration-security-<NNN>
\`\`\`

## Zeitachse

| Datum | Ereignis |
|---|---|
| <YYYY-MM-DD> | Finding bestätigt in webtrees-testing-platform (Status `exploit_confirmed`) |
| <YYYY-MM-DD> | Fix-Draft committet in Fork `boris-unckel/webtrees` |
| <YYYY-MM-DD> | Validation abgeschlossen (Status `fix_verified`) |
| <YYYY-MM-DD> | User-Review abgeschlossen |
| <YYYY-MM-DD> | PR geöffnet: <URL> |
| <YYYY-MM-DD> | Upstream-Merge: <commit-hash> |
| <YYYY-MM-DD> | Disclosure / Advisory publiziert |

## Danksagungen

<Credits an Bibliotheken, die beim Audit halfen: PHPUnit, Playwright, OpenTelemetry, Claude Code. Keine Personenzuweisungen ohne Absprache.>

## Embargo-Hinweis

<Nur ausfüllen, wenn Finding embargoed ist.>

Dieser Report ist bis <YYYY-MM-DD> oder Upstream-Release <Version>, je nachdem was zuerst eintritt, **embargoed**. Weitergabe nur an:
- Upstream-Maintainer (fisharebest/webtrees)
- Security-Reporter-Infrastruktur (sofern vorhanden)

## Referenzen

- Internal Audit-Trail: `artifacts/security-audit/deepdive/<NNN>/` (nicht öffentlich)
- Hypothesen-Dokument: `artifacts/security-audit/deepdive/<NNN>/hypotheses.md`
- Validation: `artifacts/security-audit/deepdive/<NNN>/validation.md`
- Fork-Branch: `boris-unckel/webtrees` `security-audit-<NNN>-<slug>`
- CWE: <z. B. CWE-79 Cross-site Scripting>
- OWASP: <z. B. A03:2021 Injection>
```

## 3. CVSSv3.1-Vektor-Hilfe

Der User kann den CVSS-Vektor aus der Impact-Kategorie ableiten. Grobe Mapping-Tabelle (nur als Orientierung, nicht als Ersatz für eine saubere CVSS-Analyse):

| Impact-Kategorie | Vorgeschlagener Vektor | Score |
|---|---|---|
| `visitor-sandbox-escape` | `AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H` | 10.0 Critical |
| `visitor-rce` | `AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H` | 9.8 Critical |
| `non-admin-rce` | `AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H` | 8.8 High |
| `authenticated-xss-stored` | `AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N` | 5.4 Medium |
| `authenticated-sqli-read-only` | `AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:N/A:N` | 6.5 Medium |
| `auth-bypass-to-member` | `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:L/A:N` | 6.5 Medium |
| `info-disclosure-public-data` | `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` | 5.3 Medium |
| `csrf-state-change` | `AV:N/AC:L/PR:N/UI:R/S:U/C:N/I:H/A:N` | 6.5 Medium |

**Wichtig:** Das ist eine **Heuristik**. Der User prüft jeden Vektor manuell gegen die tatsächlichen Voraussetzungen des Findings. CVSS ist keine exakte Wissenschaft und die Interaktion von Scope (`S:U` vs. `S:C`) ist für PHP-Sandbox-Escape besonders diskussionswürdig.

## 4. Redaction-Regeln im Report

Analog zu `09_fixture_register.md` §4 gilt auch hier:

### 4.1 Bei `public-ready` Fixture-Policy

- Der Reproduktions-Abschnitt darf einen **vollständigen, aber harmlosen** curl-Befehl enthalten.
- Der Fix-Diff kann inline stehen.
- Der Report darf öffentlich geteilt werden.

### 4.2 Bei `embargoed` Fixture-Policy

- Der Reproduktions-Abschnitt ist **gekürzt**: Schritt 4 und 5 enthalten einen Platzhalter `<EMBARGOED PAYLOAD — see internal audit trail>`.
- Der Fix-Diff kann inline stehen (der Fix ist nicht die Sicherheitslücke, der Payload ist es).
- Der Report darf nur an Upstream-Maintainer und Security-Reporter-Kontakte geteilt werden, nicht publiziert.

### 4.3 Bei `internal-only` Fixture-Policy

- Der Reproduktions-Abschnitt entfällt komplett.
- Der Fix-Diff kann inline stehen.
- Der Report enthält einen Hinweis: „Reproduction details withheld per internal-only classification. Contact auditor for escrow access."
- Teilung ausschließlich bilateral mit Upstream-Maintainer, explizit.

## 5. Verhältnis zu GitHub Security Advisories (GSA)

Der Report-Markdown ist so strukturiert, dass er in ein GSA-Formular überführbar ist:

- **Title** → `<kurzer, neutraler Titel>` aus Header
- **Severity** → CVSS-Score
- **CWE** → Referenzen-Abschnitt
- **Affected versions** → Tabelle
- **Description** → „Technische Beschreibung"
- **Proof of Concept** → „Reproduktion" (nur public-ready)
- **Impact** → „Impact-Begründung"
- **Patches** → „Fix" Abschnitt

Der User übernimmt diese Abbildung manuell — **kein** automatischer `gh` Aufruf zum GSA-Anlegen.

## 6. Mehrere Findings, ein Release

Wenn der User mehrere `SEC-AUDIT-<NNN>`-Befunde gebündelt disclosen will (z. B. alle Critical Findings eines Audit-Runs in einem Release):

1. Jeder Finding hat seinen **eigenen** Report unter `docs/security-audit/findings/`.
2. Zusätzlich ein **Bundle-Report** unter `docs/security-audit/findings/BUNDLE-<name>.md` mit:
   - Kurz-Summary aller Einzel-Findings (Tabelle: ID, Titel, Impact, Fix-Branch)
   - Verweis auf jeden Einzel-Report
   - Eine konsolidierte Zeitachse
3. Der Bundle-Report ist **nicht** ein Ersatz für die Einzel-Reports, sondern ein Wrapper.

## 7. Nicht-Ziele dieses Templates

- **Kein** internes Postmortem-Format (dafür gibt es im Projekt andere Strukturen).
- **Kein** Incident-Response-Report (der Audit ist proaktiv, nicht reaktiv).
- **Kein** Threat-Modeling-Dokument (dafür: `02_threat_model.md`).
- **Kein** Penetration-Test-Report mit breitem Scope — jeder Finding ist eigenständig.

## 8. Freigabe

Ein Report darf erst veröffentlicht werden, wenn:

- [ ] Fix ist im Upstream gemerged (`disclosure_state: merged`)
- [ ] Upstream hat ein Release mit dem Fix veröffentlicht
- [ ] Der Auditor (User) hat den Report gegengelesen
- [ ] Fixture-Policy erlaubt die geplante Verbreitung

Alle vier Punkte manuell im Report-Frontmatter abhaken, bevor die Datei aus `docs/security-audit/findings/` in einen öffentlichen Kanal wandert.

Weiter: `tasks/_template.md` für die Task-Frontmatter-Struktur, auf die dieser Report an mehreren Stellen verweist.
