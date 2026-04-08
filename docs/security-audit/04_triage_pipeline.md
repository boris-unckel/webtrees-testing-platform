<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Triage-Pipeline — Vier-stufige Priorisierung

**Teil von:** [webtrees_security_audit_prompt.md](../webtrees_security_audit_prompt.md)
**Vorangehend:** [03_infrastructure_usage.md](03_infrastructure_usage.md)

---

## 1 Zweck

Die Vorlage [`docs/php_security_audit_suggestion.md`](../php_security_audit_suggestion.md) macht Priorisierung in einem einzelnen LLM-Schritt (Phase 1 Triage, Skala 1–5). Das ist kostenbillig, aber:
- Blind gegenüber objektiven Signalen (CRAP-Score, Input-Sink-Dichte, Auth-Gap)
- Bindet Token-Budget an Dateien, die schon per Statik aussortiert werden könnten
- Reproduziert nicht zwischen Läufen (LLM-Temperatur)

Diese Pipeline zerlegt die Priorisierung in **vier Stufen**, drei mechanische und eine LLM-basierte, mit deterministischer Gewichtung am Ende. Jede Stufe erzeugt ein persistiertes Artefakt, das die nachfolgende Stufe konsumiert.

```
  T0  ───►  T1  ───►  T2  ───►  T3  ───►  Task-Liste
  │         │         │         │
  │         │         │         └─ 0.25·CRAP + 0.15·Inputs + 0.15·DB + 0.25·Danger·r + 0.20·LLM
  │         │         │            = Final-Score pro Datei
  │         │         │
  │         │         └─ Reachability-Filter:
  │         │              Dangerous-Fn und nicht-erreichbar → Admin-only
  │         │              Keine Dangerous-Fn und nicht-erreichbar → deprioritized
  │         │
  │         └─ LLM-Triage Sonnet 1..5, Kontext = T0-Signale
  │
  └─ Statik: CRAP, Sinks, Routing, Dangerous-Fn, Datei-Typ-Gewicht
```

---

## 2 Voraussetzungen

Vor Start der Pipeline sicherstellen:

1. `make up && make setup` ist durchgelaufen — Container läuft, webtrees ist installiert.
2. `make test-integration` ist mindestens einmal durchgelaufen — `artifacts/layer3/coverage.xml` existiert und ist aktuell.
3. Es läuft **kein anderer Testlauf** (siehe [`03_infrastructure_usage.md`](03_infrastructure_usage.md) §11).
4. Arbeitsverzeichnis für den Audit-Lauf anlegen: `artifacts/security-audit/<run-id>/` (wobei `run-id` ein ISO-Timestamp wie `2026-04-08T14-00-00` ist).

---

## 3 Stufe T0 — Mechanische Signalsammlung

**Zweck:** Für jede relevante Quelldatei im Upstream ein objektives Signal-Tupel erheben, komplett ohne LLM.

**Scope (welche Dateien werden inventarisiert):**

```
upstream/webtrees/app/Http/RequestHandlers/*.php       ← primäre Handler
upstream/webtrees/app/Http/Middleware/*.php            ← PSR-15-Middlewares
upstream/webtrees/app/Services/*.php                   ← Geschäftslogik
upstream/webtrees/app/Module/*.php                     ← Module / Theme
upstream/webtrees/app/Factories/*.php                  ← Factories, häufig Filesystem
upstream/webtrees/app/Http/Exceptions/*.php            ← Error-Surface
upstream/webtrees/app/Auth.php                         ← zentral für D-AUTH
upstream/webtrees/app/Validator.php                    ← zentral für Input-Validation
```

Nicht im Scope: `app/Helpers/*`, `app/Contracts/*`, `app/*Interface.php`, Tests, Templates (`resources/`).

**Signale pro Datei:**

| Signal | Extraktion | Typ |
|---|---|---|
| `path` | Relativer Pfad ab `upstream/webtrees/` | string |
| `kind` | `handler` / `middleware` / `service` / `module` / `factory` / `exception` / `core` | enum |
| `loc` | Lines of Code (ohne Leer- und Kommentarzeilen) | int |
| `crap` | Max CRAP-Score aus `artifacts/layer3/coverage.xml` über alle Methoden der Datei | float or null |
| `coverage_pct` | Line-Coverage in Prozent aus Clover | float or null |
| `input_sinks` | Anzahl `Grep`-Treffer (siehe §3.1) | int |
| `db_sinks` | Anzahl DB-Aufrufe (siehe §3.2) | int |
| `dangerous_fns` | Anzahl Aufrufe gefährlicher Funktionen (siehe §3.3) | int |
| `route_paths` | Liste von Routen-Strings aus `WebRoutes.php`, die auf diese Klasse zeigen | list of string |
| `route_middlewares` | Middleware-Stack jeder Route (aus `WebRoutes.php` oder Default) | list of list |
| `reachable_by` | Aus `route_middlewares` abgeleitete Rollen-Menge | set of role |
| `type_weight` | Gewicht aus `kind` (siehe §3.4) | float |

### 3.1 Input-Sink-Extraktion

Grep-Muster (alle Case-sensitive, alle innerhalb der Datei gezählt):

```
Validator::\|->queryParams(\|->parsedBody(\|->serverParams(\|->attributes(
->getQueryParams\|->getParsedBody\|->getUploadedFiles\|->getAttribute\|->getCookieParams
\$_GET\[\|\$_POST\[\|\$_REQUEST\[\|\$_FILES\[\|\$_COOKIE\[\|\$_SERVER\[
```

Jeder Treffer erhöht `input_sinks` um 1. Doppel-Treffer auf einer Zeile zählen einmal.

### 3.2 DB-Sink-Extraktion

```
DB::\|->query(\|->raw(\|->whereRaw(\|->orderByRaw(\|->havingRaw(
Statement::\|::select(\|::insert(\|::update(\|::delete(
```

Zusätzliche Gewichtung: Jeder `Raw`-Treffer (`raw`, `whereRaw`, `orderByRaw`, `havingRaw`) zählt **doppelt** — manuell konstruiertes SQL ist die primäre SQLi-Quelle.

### 3.3 Dangerous-Function-Extraktion

```
shell_exec(\|exec(\|system(\|passthru(\|proc_open(\|popen(\|`\|escapeshellcmd
eval(\|assert(\|create_function(\|call_user_func(
include \$\|include_once \$\|require \$\|require_once \$\|include("/.*\$\|include "
unserialize(\|phar://
file_put_contents(\|fwrite(\|move_uploaded_file(
mail(\|mb_send_mail(\|imap_open(
file_get_contents("http\|curl_exec(\|fsockopen(\|stream_socket_client(
ldap_search(\|ldap_bind(
imagecreatefromjpeg(\|imagecreatefrompng(\|imagecreatefromwebp(\|finfo_open(
simplexml_load_string(\|simplexml_load_file(\|DOMDocument::\|xml_parse(
```

Jeder Treffer erhöht `dangerous_fns` um 1. Dateien mit `dangerous_fns > 0` kommen automatisch in den Track-2-Kandidatenpool, unabhängig von Rollen-Erreichbarkeit.

### 3.4 Datei-Typ-Gewichtung

| `kind` | `type_weight` | Begründung |
|---|---|---|
| `handler` | 1.00 | HTTP-Entry-Point, höchste Exposition |
| `middleware` | 1.00 | Läuft *vor* dem Handler, kann alle Requests sehen/blockieren |
| `service` | 0.70 | Über Handler erreichbar, aber indirekter |
| `factory` | 0.60 | Meist nur durch Services getriggert |
| `module` | 0.60 | Module können Middleware registrieren, aber nur bei Admin-Install |
| `exception` | 0.30 | Error-Surface, kann Info-Leak verursachen, aber selten Exploit-Kern |
| `core` | 0.80 | `Auth.php`, `Validator.php` — hohe Indirektheit, aber wenn hier etwas kaputt ist, sind alle Handler betroffen |

### 3.5 Reachability-Ableitung

Aus `route_middlewares` wird `reachable_by` berechnet:

```
middleware_stack          → reachable_by
─────────────────────────────────────────────────
[kein Auth]               → {visitor, member, editor, moderator, manager, admin}
[AuthLoggedIn]            → {member, editor, moderator, manager, admin}
[AuthMember]              → {member, editor, moderator, manager, admin}
[AuthEditor]              → {editor, manager, admin}
[AuthModerator]           → {moderator, admin}
[AuthManager]             → {manager, admin}
[AuthAdministrator]       → {admin}
```

Hinweise:
- Mehrere Auth-Middlewares auf derselben Route → intersect.
- `AuthNotRobot` ist keine Auth-Middleware, sondern ein Bot-Filter → wird ignoriert.
- Routen ohne explizite Auth-Middleware gelten als visitor-erreichbar. Das `WebRoutes`-Default-Set muss vom T0-Runner gelesen werden.

### 3.6 T0-Artefakt

Ergebnis: `artifacts/security-audit/<run-id>/t0_signals.json`.

```json
[
  {
    "path": "app/Http/RequestHandlers/SearchGeneralAction.php",
    "kind": "handler",
    "loc": 142,
    "crap": 38.5,
    "coverage_pct": 12.3,
    "input_sinks": 7,
    "db_sinks": 3,
    "dangerous_fns": 0,
    "route_paths": ["/tree/{tree}/search"],
    "route_middlewares": [[]],
    "reachable_by": ["visitor", "member", "editor", "moderator", "manager", "admin"],
    "type_weight": 1.00
  },
  "..."
]
```

**Implementierungshinweis:** T0 ist ein Shell-/PHP-Skript, das keine LLM-Aufrufe macht. Die Implementierung selbst kommt in einer späteren Runde (siehe [`webtrees_security_audit_prompt.md`](../webtrees_security_audit_prompt.md) §Meta). Dieses Dokument spezifiziert nur das Format und die Regeln.

---

## 4 Stufe T1 — LLM-Triage

**Zweck:** Dem strukturellen T0-Urteil eine semantische Plausibilitätsprüfung hinzufügen. Der LLM-Agent liest pro Datei den Code (nicht nur Grep-Treffer) und gibt einen Score 1–5 ab, **mit den T0-Signalen als Kontext**.

**Modell:** Sonnet (kosteneffizient). Opus nur, wenn Sonnet widersprüchliche oder unsichere Scores produziert.

**Prompt:** Vollständig spezifiziert in [`07_prompts/prompt_01_triage_llm.md`](07_prompts/prompt_01_triage_llm.md).

**Iteration:** Der Triage-Lauf verarbeitet Dateien in absteigender `dangerous_fns + type_weight * input_sinks`-Reihenfolge (sodass die plausibel interessanten zuerst kommen und ein frühzeitiger Abbruch trotzdem die heißesten Kandidaten erfasst).

**Caching:** Der LLM-Score pro Datei wird anhand eines Hashes `sha256(path + file_content + t0_entry)` gecached. Ein erneuter Audit-Lauf mit unverändertem Upstream überspringt unveränderte Dateien.

**T1-Artefakt:** `artifacts/security-audit/<run-id>/t1_llm_scores.json`.

```json
[
  {
    "path": "app/Http/RequestHandlers/SearchGeneralAction.php",
    "t0_hash": "<sha256>",
    "llm_score": 4,
    "llm_rationale": "Nutzt Validator::queryParams für 'query' und leitet ungetrimmt in SearchService::search() weiter. SearchService baut LIKE-Pattern via whereRaw — zweitrangige Quelle für SQLi. Sichtbar visitor-erreichbar.",
    "hypotheses": [
      "SQLi via 'query' in SearchService::search",
      "Regex-DoS über Soundex-Anchor",
      "Info-Leak: Suchergebnisse ignorieren canShow()"
    ],
    "suggested_track": "non_admin_owasp"
  }
]
```

**Wichtig:** Der LLM gibt im Prompt-Response ein **explizites** `suggested_track`-Feld ab (`non_admin_owasp`, `sandbox_escape`, oder `both`). Das ist der erste Brücken-Schritt zur Track-Zuordnung und wird in T2 mit den Reachability-Daten kombiniert.

---

## 5 Stufe T2 — Reachability-Filter und Track-Zuordnung

**Zweck:** Die in T0 berechnete `reachable_by`-Menge und das in T1 vorgeschlagene `suggested_track` zu einer finalen Track-Zuordnung und einem `reachability_weight` konsolidieren.

**Reachability-Gewicht:** Jede Rolle hat ein Gewicht, das in den Final-Score einfließt:

```
visitor   → 1.00     (Maximum-Impact-Kandidat)
member    → 0.80
editor    → 0.60
moderator → 0.50
manager   → 0.40
admin     → 0.05     (außer Track-2)
```

Für eine Datei ist `reachability_weight = max(weight(r) for r in reachable_by)`. Das garantiert, dass Visitor-erreichbare Dateien automatisch den höchsten Reach-Bonus bekommen.

**Track-Zuordnungs-Regeln (aus [`01_scope_and_tracks.md`](01_scope_and_tracks.md) §6):**

```python
if dangerous_fns > 0 and "visitor" in reachable_by:
    track = {"sandbox_escape"}
    impact = "maximum"            # Visitor → Sandbox-Ausbruch = MAX
    if input_sinks > 0 or db_sinks > 0:
        track.add("non_admin_owasp")

elif dangerous_fns > 0 and any(r in reachable_by for r in ["member","editor","moderator","manager"]):
    track = {"sandbox_escape"}
    impact = "critical"
    if input_sinks > 0 or db_sinks > 0:
        track.add("non_admin_owasp")

elif dangerous_fns > 0 and reachable_by == {"admin"}:
    track = {"sandbox_escape"}
    impact = "critical"           # Admin-Sandbox-Escape bleibt critical

elif dangerous_fns == 0 and any(r in reachable_by for r in ["visitor","member","editor","moderator","manager"]):
    track = {"non_admin_owasp"}
    impact = "high" if "visitor" in reachable_by else "medium"

elif dangerous_fns == 0 and reachable_by == {"admin"}:
    track = set()                 # deprioritized
    impact = "info"

else:
    track = {"non_admin_owasp"}   # Fallback bei leerem reachable_by (interne Service-Klasse)
    impact = "medium"
```

**T2-Artefakt:** `artifacts/security-audit/<run-id>/t2_tracks.json`. Enthält pro Datei `track` (Liste), `impact` (enum), `reachability_weight` (float).

---

## 6 Stufe T3 — Finale Score-Aggregation

**Zweck:** Alle Signale zu einem einzigen `final_score` verrechnen, der die Sortierung der Audit-Tasks bestimmt.

**Formel:**

```
crap_n      = min(crap / 100.0, 1.0)           # 100 = harter Cutoff-Maximum
inputs_n    = min(input_sinks / 10.0, 1.0)     # 10 Sinks = maximal
db_n        = min(db_sinks / 5.0, 1.0)         # 5 DB-Sinks = maximal
danger_n    = min(dangerous_fns / 5.0, 1.0)    # 5 gefährliche Funktionen = maximal
llm_n       = (llm_score - 1) / 4.0            # 1..5 → 0..1
reach_w     = reachability_weight              # bereits 0..1

final_score = (
    0.25 * crap_n        * type_weight
  + 0.15 * inputs_n      * type_weight
  + 0.15 * db_n          * type_weight
  + 0.25 * danger_n      * reach_w       # Dangerous-Fn skaliert mit Reach!
  + 0.20 * llm_n         * type_weight
)
```

**Eigenschaften:**
- `final_score ∈ [0, 1]` (normiert)
- Eine Visitor-erreichbare Datei mit `dangerous_fns = 5` kriegt alleine aus dem Danger-Term `0.25`
- Eine Admin-only-Datei mit `dangerous_fns = 5` kriegt aus dem Danger-Term nur `0.25 * 0.05 = 0.0125` — der CRAP/Input/DB/LLM-Anteil muss den Rest tragen, sonst fällt sie unter den Priority-Cutoff
- `type_weight` multipliziert alle "strukturellen" Anteile (CRAP, Inputs, DB, LLM) → ein Helper mit `type_weight=0.3` hat automatisch maximal `0.3·(0.25+0.15+0.15+0.20) = 0.225` Gesamt-Score aus strukturellen Signalen, plus den (reach-gewichteten) Danger-Term

**Cutoff-Regel:** Dateien mit `final_score < 0.25` werden **nicht** in die Task-Liste aufgenommen. Dateien im Status `track = {}` (Admin-only ohne Dangerous-Fn) fallen ebenfalls heraus.

**T3-Artefakt:** `artifacts/security-audit/<run-id>/priorities.md`.

Beispiel-Tabelle:

```markdown
# Audit-Priorisierung — Run 2026-04-08T14-00-00

| Rank | Score | Datei | Track | Impact | Reach | Dangerous-Fn | Rationale |
|---|---|---|---|---|---|---|---|
|  1 | 0.89 | app/Http/RequestHandlers/GedcomLoad.php | sandbox_escape,non_admin_owasp | maximum | visitor | 3 | move_uploaded_file mit GEDCOM-Pfad, 7 input sinks, CRAP 142 |
|  2 | 0.81 | app/Http/RequestHandlers/MediaFileThumbnail.php | sandbox_escape | maximum | visitor | 2 | imagecreatefromjpeg auf user-upload, CRAP 88 |
|  3 | 0.72 | app/Services/SearchService.php | non_admin_owasp | high | visitor | 0 | whereRaw × 4, 6 input sinks, CRAP 54 |
| …  | …    | … | … | … | … | … | … |
```

Diese Datei ist das **Eingabe-Dokument** für die Deep-Dive-Phase (siehe [`06_agentic_loop_driver.md`](06_agentic_loop_driver.md)).

---

## 7 Task-Erzeugung

Für jede Zeile in `priorities.md` mit `final_score ≥ 0.25` wird **automatisch** ein Task angelegt:

```
docs/security-audit/tasks/SEC-AUDIT-<NNN>_<slug>.md
```

Die Task-Datei wird aus dem Template [`tasks/_template.md`](tasks/_template.md) geklont und mit Frontmatter-Werten aus T0/T1/T2/T3 vorgeflüllt:

```yaml
---
id: SEC-AUDIT-001
title: GEDCOM-Upload move_uploaded_file ohne Extension-Whitelist
sut: app/Http/RequestHandlers/GedcomLoad.php
track: [sandbox_escape, non_admin_owasp]
impact: maximum
reachable_by: [visitor, member, editor, moderator, manager, admin]
priority: 0.89
crap: 142.0
coverage_pct: 5.2
input_sinks: 7
db_sinks: 1
dangerous_fns: 3
status: queued
created: 2026-04-08
updated: 2026-04-08
---
```

Status ist initial `queued`. Der Agentic-Loop (siehe [`06_agentic_loop_driver.md`](06_agentic_loop_driver.md)) hebt den Status auf `in_triage` bzw. `in_analysis`, sobald er den Task abholt.

**Hinweis zum Slug:** Der Slug wird aus dem `title` per kleinschreibung + `[^a-z0-9]+ → -`-Normalisierung erzeugt, ohne Umlaute (deutsche Umlaute werden zu `ae`/`oe`/`ue`/`ss`).

**Hinweis zur Nummerierung:** Die höchste existierende `SEC-AUDIT-NNN` im Verzeichnis `tasks/` wird gelesen; neue Tasks zählen von dort weiter. Bei Parallel-Läufen hält der Loop das Advisory-Lock aus [`03_infrastructure_usage.md`](03_infrastructure_usage.md) §11.

---

## 8 Regenerierung und Inkrementalität

Ein Audit-Lauf kann inkrementell sein:

- **Voll-Lauf:** Alle T0/T1/T2/T3-Artefakte werden neu berechnet. Wird manuell angefordert oder automatisch, wenn seit dem letzten Lauf mehr als `X` Upstream-Dateien verändert wurden (default `X = 50`).
- **Inkrementeller Lauf:** T0 wird nur für geänderte Dateien neu berechnet (Hash-Vergleich auf `file_content`). T1-Cache greift für unveränderte T0-Signale. T2/T3 laufen immer vollständig (schnell, da nur JSON-Verarbeitung).

Bestehende Tasks bleiben in ihren Statusständen erhalten. Nur **neue** Dateien, die in der Priorisierungstabelle oberhalb des Cutoffs landen, erzeugen neue Tasks. Dateien, die vormals triagiert waren und nach einem Upstream-Update unter den Cutoff rutschen, behalten ihren bestehenden Task — dieser wird nicht automatisch gelöscht, sondern sollte vom User manuell auf `closed` (mit Begründung) gesetzt werden.

---

## 9 Kennzahlen pro Lauf

Jeder Lauf erzeugt eine zusammenfassende `artifacts/security-audit/<run-id>/run-summary.md`:

```markdown
# Audit-Lauf 2026-04-08T14-00-00 — Zusammenfassung

## T0 — Inventarisierung
- Dateien gescannt: 487
- Davon Handler: 143 / Middleware: 34 / Service: 89 / Module: 56 / Core: 2 / Exception: 19 / Factory: 28 / …
- Dateien mit dangerous_fns > 0: 24
- Dateien mit visitor-reachability: 61

## T1 — LLM-Triage
- LLM-Aufrufe: 312 (Cache-Hits: 175)
- Token-Verbrauch: ~1.4M input / ~85k output
- Modell: claude-sonnet-4-6

## T2 — Track-Zuordnung
- Track non_admin_owasp: 54
- Track sandbox_escape: 18
- Track sandbox_escape ∩ non_admin_owasp: 11
- Deprioritized (Admin-only, no danger): 29

## T3 — Priorisierung
- Über Cutoff (0.25): 67
- Davon impact=maximum: 4
- Davon impact=critical: 9
- Davon impact=high: 22
- Davon impact=medium: 32

## Tasks
- Neu angelegt: 12 (SEC-AUDIT-034..045)
- Bestehend aktualisiert: 55
- Gesamt im Index: 67
```

Diese Datei ist der erste Anlaufpunkt für den User nach einem Lauf.

---

## 10 Querverweise

- [01_scope_and_tracks.md](01_scope_and_tracks.md) §6 — Track-Allocation-Regeln
- [02_threat_model.md](02_threat_model.md) §4 — Reachability-Matrix
- [03_infrastructure_usage.md](03_infrastructure_usage.md) §10 — Statische Seed-Quellen
- [07_prompts/prompt_01_triage_llm.md](07_prompts/prompt_01_triage_llm.md) — T1-Prompt
- [06_agentic_loop_driver.md](06_agentic_loop_driver.md) — Was mit `priorities.md` passiert
- [tasks/_template.md](tasks/_template.md) — Zielformat für generierte Tasks
