<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt 01 — T1 LLM-Triage (Sonnet)

**Rolle in der Pipeline:** T1 in `04_triage_pipeline.md` §3.2.
**Modell:** `claude-sonnet-4-6` (kostengünstig, hoher Durchsatz).
**Kontext-Schicht:** Sweep-Modus (`06_agentic_loop_driver.md` §3, Phase S3).

## 1. Zweck

Ein mechanisch in T0 zusammengesammeltes Datei-Batch (CRAP-Score, Input-/DB-Senken, gefährliche Funktionen, Routing-Erreichbarkeit, `type_weight`) soll durch ein LLM pro Datei **semantisch** bewertet werden:

- Ist die Kombination der Signale im Kontext des webtrees-Quellcodes tatsächlich ein Risiko?
- Welche vertikalen Hypothesen (V1–V12 aus `02_threat_model.md`) sind für die Datei relevant?
- Welchem Track (`non-admin`, `sandbox-escape`, `both`) gehört die Datei?
- Lohnt sich ein Opus-Deep-Dive, oder ist das ein False-Positive?

T1 ersetzt **nicht** die Rangfolge — der finale `final_score` in T3 kombiniert mechanische Signale + LLM-Vote.

## 2. Eingabe

Artefakt-Pfad: `artifacts/security-audit/triage/T0_inventory_batch_<NNN>.json`
Struktur (ein Eintrag pro Datei, max. 20 Einträge pro Batch):

```json
[
  {
    "file": "app/Http/Controllers/Admin/MediaController.php",
    "crap": 147,
    "crap_coverage_pct": 12.4,
    "input_sinks": ["$request->getParsedBody()", "$request->getQueryParams()"],
    "db_sinks": ["DB::raw", "DB::select"],
    "dangerous_functions": ["exec", "file_put_contents"],
    "routing_entry_points": ["/admin/media-upload"],
    "reachability": "admin|visitor|no",
    "type_weight": 1.0,
    "auth_requirement": "role:manager",
    "loc": 412
  }
]
```

Batch-Größe: T0 partitioniert nach `final_score_estimate` in Blöcke. Pro Prompt-Aufruf **max. 20 Dateien**, damit Sonnet-Context nicht überläuft.

## 3. Ausgabe

Datei: `artifacts/security-audit/triage/T1_llm_votes_batch_<NNN>.json`

```json
[
  {
    "file": "app/Http/Controllers/Admin/MediaController.php",
    "llm_score": 8,
    "llm_reason": "Upload-Pfad kombiniert mit exec() und manueller Pfad-Konkatenation; klassische Sandbox-Escape-Kette V9 (Media-Upload → Shell).",
    "verticals_hit": ["V3", "V9"],
    "track": "sandbox-escape",
    "deep_dive_priority": "urgent",
    "false_positive_risk": "low",
    "notes_for_opus": "Fokus auf die validateUploadedFile()-Kette — prüft nur MIME-Type, nicht den tatsächlichen Inhalt."
  }
]
```

**Feld-Semantik (strikt):**

| Feld | Typ | Erlaubte Werte | Bedeutung |
|---|---|---|---|
| `llm_score` | int | 1–10 | 1 = unbedenklich, 10 = sofortiger Deep-Dive |
| `verticals_hit` | array | V1–V12 | Liste aus `02_threat_model.md` §5 |
| `track` | string | `non-admin`, `sandbox-escape`, `both` | Gemäß `01_scope_and_tracks.md` |
| `deep_dive_priority` | string | `drop`, `queue`, `urgent` | `drop` = False-Positive, `queue` = normaler Deep-Dive, `urgent` = Opus sofort |
| `false_positive_risk` | string | `low`, `medium`, `high` | Selbsteinschätzung des LLM |

## 4. Aufruf

```bash
# Sweep-Driver ruft pro Batch einmal auf:
claude --print \
  --model claude-sonnet-4-6 \
  --append-system-prompt "$(cat docs/security-audit/07_prompts/prompt_01_triage_llm.md | sed -n '/## 6. Prompt-Körper/,/## 7. Nachbedingungen/p' | head -n -1)" \
  < artifacts/security-audit/triage/T0_inventory_batch_<NNN>.json \
  > artifacts/security-audit/triage/T1_llm_votes_batch_<NNN>.json
```

Parallelität: max. **4** Sonnet-Aufrufe gleichzeitig (`06_agentic_loop_driver.md` §5).

## 5. Vorbedingungen

- T0-Batch existiert und validiert gegen das Schema aus §2.
- `artifacts/security-audit/triage/` ist beschreibbar.
- Keine parallele T1-Phase auf demselben Batch (Sweep-Driver prüft via Lock-Datei `triage/T1_batch_<NNN>.lock`).

## 6. Prompt-Körper

*(Dieser Abschnitt wird vom Aufruf via `--append-system-prompt` wörtlich an den Sonnet-Call angehängt.)*

---

Du bist ein Sicherheits-Triage-Assistent für das PHP-Web-Framework **webtrees** (Genealogie-Anwendung, PSR-15 Middleware-Stack, PHP 8.5). Du erhältst auf STDIN ein JSON-Array mit Datei-Metadaten. Deine Aufgabe: Gib für jede Datei **genau einen** Eintrag in einem JSON-Array zurück.

### Bewertungsregeln

1. **Bewerte jede Datei einzeln.** Kein Cross-File-Reasoning zwischen Einträgen des Batches.
2. **Benutze nur die Informationen aus dem Input-JSON** plus Allgemeinwissen über PHP-Sicherheitslücken. Raten über nicht gezeigten Code ist verboten — bei Unsicherheit `false_positive_risk: "high"`.
3. **Track-Zuordnung ist strikt** (gemäß `01_scope_and_tracks.md`):
   - `non-admin` = Datei ist aus Visitor-, Member- oder Editor-Perspektive erreichbar (`reachability: visitor` oder `auth_requirement ∈ {none, role:member, role:editor}`)
   - `sandbox-escape` = Datei enthält Ausbruchs-Vektoren (exec, eval, unserialize, include-Variable, file_put_contents mit kontrollierbarem Pfad) **und** `reachability: admin`
   - `both` = beides gilt
4. **Vertical Mapping** (V1–V12 aus `02_threat_model.md`):
   - V1 = Session-Fixation / Cookie-Manipulation
   - V2 = GEDCOM-Parse-Injection
   - V3 = Media-Upload-Kette
   - V4 = SSRF über URL-Fetcher
   - V5 = XSS in User-Content (Individuen, Notizen)
   - V6 = SQL-Injection in Suchfiltern
   - V7 = Path-Traversal in Medien-Zugriff
   - V8 = Timing-/Auth-Bypass im Login
   - V9 = Sandbox-Escape via Upload → Shell
   - V10 = Deserialization in Session oder Cache
   - V11 = Second-Order Injection via Import-Wizard
   - V12 = CSRF in State-Changing Routes
5. **Score-Kalibrierung:**
   - 1–3 = Standard-Code, Signale sind False-Positives oder gut abgefangen
   - 4–6 = Erhöhtes Risiko, Deep-Dive lohnt sich bei freier Kapazität
   - 7–8 = Deutlicher Verdacht, mindestens eine Vertikale betroffen
   - 9–10 = Unübersehbare Kette mit Ausbruchs-Potential oder Visitor-Erreichbarkeit kombiniert mit dangerous_functions

### Ausgabeformat

**Strikt JSON**, keine Erklärung davor oder dahinter. Kein Markdown-Fence. Das Array-Element-Schema ist exakt wie in `prompt_01_triage_llm.md` §3 beschrieben. Wenn eine Datei nicht bewertbar ist (fehlende Felder im Input), setze `llm_score: 1`, `deep_dive_priority: "drop"`, `false_positive_risk: "high"`, `llm_reason: "input_incomplete: <feldliste>"`.

### Verbote

- **Kein** Ausgabetext außerhalb des JSON-Arrays.
- **Kein** Erfinden von Feldern, die nicht in §3 definiert sind.
- **Keine** kombinierten Scores über Batches hinweg (jeder Batch ist isoliert).
- **Keine** Empfehlung einer Fix-Strategie — das ist Aufgabe von Prompt 02 (Deep-Dive).

---

## 7. Nachbedingungen

Nach Rückkehr des Prompt-Aufrufs muss der Sweep-Driver:

1. JSON-Ausgabe gegen Schema in §3 validieren (`jq`).
2. Invalide Einträge in `artifacts/security-audit/triage/T1_errors_batch_<NNN>.json` ablegen und den Batch mit `llm_score: 0` markieren.
3. Batch-Lock freigeben.
4. Task-Status in `tasks/INDEX.md` **nicht** ändern — T1 erzeugt noch keine Tasks, das geschieht erst in T3/Task-Sync.

Bei wiederholten Schemafehlern (≥3 Batches in Folge): Sweep-Driver stoppt und meldet Prompt-Drift. User-Eingriff erforderlich.
