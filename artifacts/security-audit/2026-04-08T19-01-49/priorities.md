<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# T3 Priorisierung — Run 2026-04-08T19-01-49

## Formel

```
final_score = 0.25 * crap_norm
            + 0.15 * inputs_norm
            + 0.15 * db_norm
            + 0.25 * danger * reach
            + 0.20 * llm_norm
```

Cutoff: `final_score < 0.25` → kein Task.

Normierung: `*_norm = value / max_value_in_run`. `danger * reach` ist bereits 0..1 skaliert (dangerous_count gewichtet mit reachability: visitor=1.0, member=0.7, editor=0.6, manager=0.4, admin=0.2).

## Top Candidates (nach T1-Triage)

### 1. SEC-AUDIT-001 — SVG Stored XSS via ImageFactory substring filter + MediaFileService upload gap

**Primär-Datei:** `app/Factories/ImageFactory.php` (Zeile 270)
**Contributing-Datei:** `app/Services/MediaFileService.php` (`uploadFile()`)

| Metrik | Rohwert | Normiert | Gewichtet |
|---|---|---|---|
| crap (ImageFactory.php) | 12 | 0.20 | 0.050 |
| inputs (MediaFileService.php) | 8 | 1.00 | 0.150 |
| db (ImageFactory) | 0 | 0.00 | 0.000 |
| danger * reach | 0.4 * 0.6 | 0.24 | 0.060 |
| llm (kombiniert ImageFactory 95 + MediaFileService 88) | 91.5 | 0.915 | 0.183 |
| **final_score** | | | **0.443** |

**Status:** Über Cutoff. Wird als SEC-AUDIT-001 erzeugt.

**Warum kombinierte Betrachtung:** Die beiden Dateien bilden zusammen den Exploit-Pfad. ImageFactory ist die Root-Cause-Datei (defekte Sanitization), MediaFileService ist die Upload-Entry. Der Task referenziert beide, aber die Probe-Konstruktion (D3) fokussiert auf ImageFactory, weil der dortige Fix den Exploit direkt neutralisiert.

### 2. SEC-AUDIT-NEXT — (wird in späterem Run erzeugt)

Alle weiteren Kandidaten aus T1 hatten `llm_score < 20` oder sind in dieser Phase nicht in Scope (SetupWizard / UpgradeWizard / ContactAction — siehe t1_llm_scores.json).

## Dropped (< Cutoff)

| Datei | Grund |
|---|---|
| `app/Http/RequestHandlers/SetupWizard.php` | Post-install unreachable (ReadConfigIni middleware gating) |
| `app/Http/RequestHandlers/UpgradeWizardStep.php` | T0-Signale waren false positives (filesystem-Substring, runtime-assert) |
| `app/Http/RequestHandlers/ContactAction.php` | Well-guarded: Captcha, RateLimit, isLocalUrl, preg_match, validContacts |

## Task-Erzeugung

- [x] `docs/security-audit/tasks/SEC-AUDIT-001_svg_xss_media_upload.md` — erzeugt (Phase S6)
- [x] `docs/security-audit/tasks/INDEX.md` — Queue-Tabelle mit SEC-AUDIT-001 aktualisiert
