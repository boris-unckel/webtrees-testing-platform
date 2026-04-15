<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Prompt: Dokumentationsbereinigung — webtrees-testing-platform

**Ziel:** Die Dokumentation unter `docs/` redundanzfrei, aktuell und systematisch
aufräumen. Stabile Referenzdokumente bleiben, task-orientierte Einmal-Artefakte
werden gelöscht (git-History reicht als Archiv). Der Bedienungsleitfaden
(CLAUDE.md + docs/) muss danach vollständig, korrekt und konsistent sein.

**Prinzipien:**

- Weniger ist mehr. Im Zweifel löschen.
- Löschung = Datei entfernen + alle Querverweise auf diese Datei bereinigen.
- Stabile Doku darf keine toten Links enthalten.
- CLAUDE.md muss alles enthalten, was ein LLM-Agent braucht, um Tests
  fehlerfrei auszuführen — `make help` bleibt nicht immer im Kontext.
- Kein Dokument darf nach der Bereinigung Feature-IDs, Testklassen oder
  Umsetzungsplan-Reste enthalten, die nur für eine einmalige Iteration relevant
  waren (außer in security-audit/).

---

## Ausführungsstrategie

Diese Bereinigung verändert ~100 Dateien. Um die Ergebnisqualität zu maximieren
und Kontextüberladung zu vermeiden, gelten folgende Regeln:

### Kontextökonomie

- **Lazy Reading:** Dateien nur lesen, wenn sie für die aktuelle Phase
  gebraucht werden. Nicht alle Dokumente vorab lesen.
- **Phasen-Isolierung:** Jede Phase arbeitet nur mit den Dateien, die sie
  betrifft. Nach Abschluss einer Phase keine gelöschten/verarbeiteten Dateien
  erneut lesen.
- **Große Dateien budgetieren:** `CLAUDE.md` (191 Zeilen) und `Makefile`
  (219 Zeilen) werden in Phase 4 jeweils einmal gelesen. Die
  Ziel-Dokumente in Phase 3 (`wf_test-iteration_guide.md` 626 Z.,
  `wf_code-to-systemtest_guide.md` 509 Z., `tp_conventions_spec.md` 118 Z.)
  werden nur gelesen, wenn die jeweilige Integration stattfindet.
- **Querverweis-Suche:** Für Querverweis-Prüfungen `grep` verwenden, nicht
  die referenzierenden Dateien vollständig lesen.

### Qualitätssicherung — Chain-of-Verification

Jede Phase endet mit einem **Verifikationsschritt**, der explizit am Ende
der Phase beschrieben ist. Erst wenn die Verifikation bestanden ist,
mit der nächsten Phase fortfahren.

### Benutzer-Rücksprache

An drei Stellen dem Benutzer den Zwischenstand vorlegen:

1. **Nach Phase 1** — Liste der tatsächlich gelöschten Dateien + Querverweis-Status.
2. **Nach Phase 3** — Zusammenfassung der integrierten Patterns, Benutzer prüft
   die Ziel-Dokumente bevor die Quelldateien gelöscht werden.
3. **Nach Phase 4** — CLAUDE.md-Entwurf vorlegen bevor Phase 5 beginnt.

### Fehlerbehandlung

- Wenn beim Löschen ein Querverweis aus einem **stabilen** Dokument gefunden
  wird, der nicht in dieser Anleitung berücksichtigt ist: **Stopp**, Benutzer
  informieren.
- Wenn eine Datei, die gelöscht werden soll, **offene Punkte** enthält (nicht
  abgehakte Checklisten-Items, "TODO", "OFFEN"): **Stopp**, Benutzer
  informieren. Nicht eigenmächtig löschen.

---

## Phase 1 — Löschung volatiler Dokumente

Folgende Dateien/Verzeichnisse löschen und **sämtliche Querverweise** in
verbleibenden Dokumenten entfernen oder anpassen.

**Vorgehen pro Unterphase:**
1. Vor dem Löschen jede Datei auf offene Punkte scannen (`grep -l 'TODO\|OFFEN\|\[ \]'`).
2. Dateien löschen.
3. `grep -r` nach dem Dateinamen (ohne Pfad) in `docs/` und `CLAUDE.md` — alle Treffer bereinigen.

### 1.1 Portierungs-Vorhaben (Layer 2, abgeschlossen)

| Datei / Verzeichnis | Grund |
|---|---|
| `docs/port_analysis_start.md` | Einmalige Voranalyse |
| `docs/port_analysis_strategy.md` | Einmalige Strategie-Analyse |
| `docs/port-implementation/` (komplett, rekursiv — 18 Dateien) | Umsetzungsplan + Prompts + Batches + Tasks — abgearbeitet |

### 1.2 Testspezifikationen (65 Dateien, abgearbeitet)

| Datei / Verzeichnis | Anzahl | Grund |
|---|---|---|
| `docs/komponentenintegrationstest/testspezi/` (komplett) | 33 | Einmalige Feature-Detailkonzepte, Tests implementiert |
| `docs/systemtest/testspezi/` (komplett) | 32 | Einmalige Feature-Detailkonzepte, Tests implementiert |

### 1.3 Umsetzungspläne (abgearbeitet)

| Datei | Grund |
|---|---|
| `docs/komponentenintegrationstest/umsetzungsplan_komponentenintegrationstests.md` | Einmaliger Umsetzungsplan |
| `docs/systemtest/umsetzungsplan_systemtests.md` | Einmaliger Umsetzungsplan |

### 1.4 Delta-Analysen und Improvement-Pläne (abgearbeitet)

**Prüfpflicht:** Vor dem Löschen jede Datei auf offene Punkte scannen.
Falls etwas offen ist: dem Benutzer melden, nicht eigenmächtig löschen.

| Datei | Grund |
|---|---|
| `docs/testcov_systemtests_delta.md` | Einmalige Gap-Analyse |
| `docs/testcov_komponentenintegration_systemtests_delta.md` | Einmalige Gap-Analyse |
| `docs/coverage_doc_improvement_analysis.md` | Einmalige Analyse |
| `docs/coverage_doc_improvement_plan.md` | Einmaliger Verbesserungsplan |

### 1.5 Performance-, Infrastruktur- und Log-Pläne (abgearbeitet)

| Datei | Grund |
|---|---|
| `docs/skript_log_analysis.md` | Einmalige Log-Infrastruktur-Analyse |
| `docs/skript_log_plan.md` | Einmaliger Log-Infrastruktur-Plan (Referenz auf skript_log_analysis.md) |
| `docs/phase_a_bench_2026-04-11.md` | Einmalige Performance-Baseline-Messung |
| `docs/systemtest_perf_improve_analysis.md` | Einmalige Performance-Analyse |
| `docs/systemtest_perf_improve_plan.md` | Einmaliger Performance-Verbesserungsplan |

### 1.6 DB-Plattform-Analysen (abgeschlossene Einmalanalysen)

| Datei | Grund |
|---|---|
| `docs/change_to_mariadb_latest_stable_analysis.md` | Einmalige Analyse — Ergebnis: bei MySQL bleiben |
| `docs/change_to_mysql_latest_stable_analysis.md` | Einmalige Analyse — Ergebnis: MySQL LTS-Tag beibehalten |
| `docs/change_to_postgresql_latest_stable_analysis.md` | Einmalige Analyse — Ergebnis: PostgreSQL nicht geeignet |

### 1.7 Leergeräumte Verzeichnisse

Wenn nach den Löschungen Verzeichnisse leer sind (z. B.
`docs/komponentenintegrationstest/` nach Entfernung von `testspezi/` und
`umsetzungsplan_*`), prüfen ob noch Dateien verbleiben. Leere Verzeichnisse
ebenfalls entfernen.

**Achtung:** `docs/komponentenintegrationstest/uebergreifende_konzepte_l3.md`
und `docs/systemtest/uebergreifende_konzepte_l4.md` werden in Phase 3
verarbeitet — daher erst nach Phase 3 prüfen, ob die Verzeichnisse leer sind.

### Verifikation Phase 1

```bash
# 1. Prüfen, dass keine gelöschte Datei noch existiert
for f in port_analysis_start.md port_analysis_strategy.md \
         skript_log_analysis.md skript_log_plan.md \
         phase_a_bench_2026-04-11.md systemtest_perf_improve_analysis.md \
         systemtest_perf_improve_plan.md coverage_doc_improvement_analysis.md \
         coverage_doc_improvement_plan.md testcov_systemtests_delta.md \
         testcov_komponentenintegration_systemtests_delta.md \
         change_to_mariadb_latest_stable_analysis.md \
         change_to_mysql_latest_stable_analysis.md \
         change_to_postgresql_latest_stable_analysis.md; do
  [ -f "docs/$f" ] && echo "NOCH DA: $f"
done
[ -d docs/port-implementation ] && echo "NOCH DA: port-implementation/"
[ -d docs/komponentenintegrationstest/testspezi ] && echo "NOCH DA: L3 testspezi/"
[ -d docs/systemtest/testspezi ] && echo "NOCH DA: L4 testspezi/"

# 2. Prüfen, dass keine verbleibende Datei noch auf gelöschte Dateien verweist
grep -rl 'port_analysis_start\|port_analysis_strategy\|port-implementation' docs/ || true
grep -rl 'skript_log_analysis\|skript_log_plan\|phase_a_bench' docs/ || true
grep -rl 'systemtest_perf_improve\|coverage_doc_improvement' docs/ || true
grep -rl 'testcov_systemtests_delta\|testcov_komponentenintegration_systemtests_delta' docs/ || true
grep -rl 'change_to_mariadb\|change_to_mysql\|change_to_postgresql' docs/ || true
# Treffer in prompt_doku_bereinigung.md selbst sind akzeptabel (Selbstreferenz).
# Alle anderen Treffer müssen bereinigt werden.
```

**→ Benutzer-Rücksprache:** Ergebnis der Verifikation vorlegen.

---

## Phase 2 — Security-Audit-Integration

### 2.1 Einstiegsdatei umbenennen

| Alter Name | Neuer Name | Begründung |
|---|---|---|
| `docs/webtrees_security_audit_prompt.md` | `docs/tp_security-audit_spec.md` | Konvention: `tp_`-Präfix (Testplan), Bindestrich-Notation wie Verzeichnis `security-audit/` |

### 2.2 Querverweise anpassen

Folgende 6 Dateien enthalten Rückverweise auf den alten Namen — aktualisieren:

- `docs/security-audit/01_scope_and_tracks.md`
- `docs/security-audit/02_threat_model.md`
- `docs/security-audit/03_infrastructure_usage.md`
- `docs/security-audit/04_triage_pipeline.md`
- `docs/security-audit/05_security_trace_middleware.md`
- `docs/security-audit/06_agentic_loop_driver.md`

**Vorgehen:** `grep -r 'webtrees_security_audit_prompt' docs/` — ALLE Treffer
korrigieren (nicht nur die 6 bekannten — es könnten weitere existieren).

### 2.3 Referenz in tp_overview_spec.md aufnehmen

In `docs/tp_overview_spec.md` eine neue Sektion **Security-Audit** ergänzen
(nach der Sektion „Referenzdokumente"), die auf
`tp_security-audit_spec.md` verweist. Kurzbeschreibung:
Whitebox-Security-Audit-Framework mit Threat-Model, Triage-Pipeline,
8 Angriffsdomänen, 11 Subdokumenten unter `security-audit/` und
7 abgeschlossenen Findings.

**Hinweis:** Das Security-Audit-Framework und seine Durchführung waren eine
Claude Opus Fähigkeitsdemonstration auf Excellence-Niveau. Sämtliche Inhalte
unter `docs/security-audit/` und `docs/tp_security-audit_spec.md` vollständig
aufbewahren — keine Kürzungen, keine Zusammenfassungen, keine Löschungen.

### Verifikation Phase 2

```bash
# 1. Alter Name darf nirgends mehr auftauchen
grep -r 'webtrees_security_audit_prompt' docs/
# Erwartung: 0 Treffer (außer ggf. in prompt_doku_bereinigung.md)

# 2. Neue Datei existiert
[ -f docs/tp_security-audit_spec.md ] && echo "OK" || echo "FEHLT"

# 3. tp_overview_spec.md enthält die neue Sektion
grep -c 'security-audit' docs/tp_overview_spec.md
# Erwartung: mindestens 1 Treffer
```

---

## Phase 3 — Übergreifende Konzepte: Generische Inhalte integrieren

### Arbeitsprinzip

Die zwei Quelldateien enthalten sowohl **generische Patterns** (unabhängig von
konkreten Features wiederverwendbar) als auch **feature-spezifische Referenzen**
(Feature-IDs, Testklassen-Namen, konkrete Test-Bezüge). Nur die generischen
Patterns werden in die Standard-Doku integriert.

**Kriterium „generisch":** Ein Abschnitt ist generisch, wenn er nach Entfernung
aller Feature-IDs (z. B. M03, M16), aller Testklassen-Namen und aller
Verweise auf konkrete Tests eine eigenständige, wiederverwendbare Anleitung
ergibt.

**Workflow pro Quelldatei (sequenziell):**
1. Quelldatei vollständig lesen.
2. Pro Abschnitt entscheiden: generisch → integrieren, spezifisch → verwerfen.
3. Ziel-Dokument lesen (nur den relevanten Bereich, nicht vollständig, wenn es
   >200 Zeilen hat).
4. Generischen Inhalt einfügen — ohne Feature-IDs, ohne Testklassen-Namen,
   ohne Bezüge zu konkreten Tests. Als abstraktes Pattern formulieren.
5. **Sofort-Verifikation:** Das geänderte Ziel-Dokument im betroffenen
   Abschnitt ±20 Zeilen nochmals lesen und prüfen, ob der neue Inhalt
   sich konsistent in den bestehenden Text einfügt.

### 3.1 `docs/komponentenintegrationstest/uebergreifende_konzepte_l3.md` (321 Zeilen)

| Quell-Abschnitt | Ziel-Dokument | Integrationsform |
|---|---|---|
| Middleware-Pipeline-Testing: Grundmuster (Mock-Handler, Request-Attribute-Verifikation, Handler-Not-Called-Assertion) | `wf_test-iteration_guide.md` | Neuer Abschnitt oder Ergänzung bestehender Mock-Infrastruktur-Sektion |
| CLI-Command-Testing: CommandTester-Grundmuster, Format-Output-Verifikation | `wf_test-iteration_guide.md` | Neuer Abschnitt |
| Batch-Testing mit DataProvider: Strategie für homogene Handler-Gruppen | `wf_test-iteration_guide.md` | Neuer Abschnitt |
| Messaging-Handler-Testing ohne SMTP: Mock-Pattern für E-Mail-Services | `wf_test-iteration_guide.md` | Neuer Abschnitt |
| Path-Security-Testing: Directory-Traversal-Schutz | `wf_test-iteration_guide.md` | Neuer Abschnitt |
| Umgang mit nicht-mockbaren globalen Funktionen: PhpService-Kapseln, try/finally | `wf_test-iteration_guide.md` | Neuer Abschnitt |
| Test-Datei-Benennung nach Feature-Gruppe | `tp_conventions_spec.md` | Ergänzung der Namenskonventionen |

### 3.2 `docs/systemtest/uebergreifende_konzepte_l4.md` (168 Zeilen)

| Quell-Abschnitt | Ziel-Dokument | Integrationsform |
|---|---|---|
| Error-Page-Verification: Fehler-Provokations-Tabelle, DOM-Assertions | `wf_code-to-systemtest_guide.md` | Neuer Abschnitt |
| Admin-DataTable-Interaktion: Wait-Pattern, AJAX-Reload-Pattern | `wf_code-to-systemtest_guide.md` | Neuer Abschnitt |
| API-basierte Redirect-Verification: maxRedirects-Pattern, Stichproben-Ansatz | `wf_code-to-systemtest_guide.md` | Neuer Abschnitt |

### 3.3 Quelldateien nach Integration löschen

**Erst nach Benutzer-Rücksprache** die Quelldateien löschen:
- `docs/komponentenintegrationstest/uebergreifende_konzepte_l3.md`
- `docs/systemtest/uebergreifende_konzepte_l4.md`

Danach prüfen, ob die Verzeichnisse `docs/komponentenintegrationstest/` und
`docs/systemtest/` leer sind, und leere Verzeichnisse entfernen.

### Verifikation Phase 3

1. **Ziel-Dokumente lesen** (die geänderten Abschnitte) — sind die neuen
   Abschnitte frei von Feature-IDs und Testklassen-Namen?
2. **Quelldatei-Referenzen prüfen:**
   ```bash
   grep -r 'uebergreifende_konzepte' docs/
   # Erwartung: 0 Treffer (nach Löschung der Quell- und aller referenzierenden Dateien)
   ```

**→ Benutzer-Rücksprache:** Zusammenfassung der integrierten Patterns vorlegen.
Benutzer bestätigt, bevor die Quelldateien gelöscht werden.

---

## Phase 4 — Bedienungsleitfaden-Review

Ziel: Nach dieser Phase ist **jedes** Make-Target, **jeder** Parameter, **jede**
Abhängigkeit und **jedes** Do/Don't dokumentiert — korrekt, aktuell, konsistent,
redundanzfrei.

### Arbeitsablauf Phase 4

Diese Phase ist die komplexeste. Arbeitsablauf in 5 Teilschritten:

**Schritt A — Bestandsaufnahme (nur lesen, nichts ändern):**
1. `Makefile` vollständig lesen (219 Zeilen).
2. `CLAUDE.md` vollständig lesen (191 Zeilen).
3. `make help` ausgeben lassen (falls verfügbar, Dry-Run genügt).
4. Differenz-Liste erstellen: Was steht im Makefile, aber nicht in CLAUDE.md?

**Schritt B — Lücken schließen:**
- Tabelle 4.2 abarbeiten — alle fehlenden Make-Targets und Variablen in
  CLAUDE.md ergänzen.

**Schritt C — Inkonsistenzen prüfen:**
- Tabelle 4.3 abarbeiten.

**Schritt D — Redundanzen auflösen:**
- Tabelle 4.4 abarbeiten.

**Schritt E — Struktur und Do's/Don'ts:**
- Sektionen 4.5 und 4.6 abarbeiten.
- `tp_overview_spec.md` anpassen (Sektion 4.7).

**Schritt F — Selbst-Review (CLAUDE.md komplett nochmals lesen):**
- Gegen das Makefile gegenprüfen: Fehlt noch ein Target?
- Gegen `make help` gegenprüfen: Stimmen Beschreibungen?
- Liest sich CLAUDE.md für einen LLM-Agenten ohne Vorwissen verständlich?

### 4.1 Bestandsaufnahme — Ist-Zustand der Bedienungsdokumentation

Die Bedienungsinformationen sind über drei Dokumente verteilt:

- **CLAUDE.md** (Root): Primärer Einstieg für LLM-Agenten und Entwickler.
  Enthält kanonische Testaufrufe, Layer-Architektur, SELinux-Warnung, OTel-Stack,
  Coverage-Iteration, Diagnose-Befehle.
- **docs/tp_overview_spec.md**: Schnelleinstieg (5 Make-Targets), Verweis auf
  CLAUDE.md für vollständige Targets.
- **docs/tp_infrastructure_spec.md**: Tiefe Infrastruktur-Details (Container,
  Netzwerk, OTel), einige Make-Targets dokumentiert (PerfSchema, Trace-Report).

### 4.2 Identifizierte Lücken in CLAUDE.md — ALLE schließen

| Fehlendes Element | Makefile-Zeile(n) | Schweregrad |
|---|---|---|
| **`make test-security`** — Security-Tests (Distribution + Wizard + Prüfpunkte) | Target `test-security` | KRITISCH |
| **`make security-build`** — Security-Image bauen | Target `security-build` | KRITISCH |
| **`make security-up`** — Security-Stack starten | Target `security-up` | KRITISCH |
| **`make security-down`** — Security-Stack stoppen | Target `security-down` | HOCH |
| **`make security-clean`** — Security-Stack + Volumes löschen | Target `security-clean` | HOCH |
| **`make test-integration-security-%`** — Security-Audit-Einzeltask (parameterisiert, `WEBTREES_SECURITY_TRACE=1`) | Target `test-integration-security-%` | KRITISCH |
| **`make clean`** — Stack stoppen + Volumes + Passwörter löschen | Target `clean` | HOCH |
| **`make up-debug`** — Stack mit Adminer (Debug-Profil) | Target `up-debug` | MITTEL |
| **`make trace-report`** — Manueller Trace-Report (`RUN_ID=...`, `LAYER=3\|4\|5`) | Target `trace-report` | MITTEL |
| **`make perfschema-truncate`** — PerfSchema-Daten zurücksetzen | Target `perfschema-truncate` | MITTEL |
| **`make perfschema-extract`** — PerfSchema-Daten extrahieren (`LAYER=...`) | Target `perfschema-extract` | MITTEL |
| **`make mysql-shell`** — MySQL-Shell öffnen | Target `mysql-shell` | GERING |
| **`make php-shell`** — PHP-Shell im Container | Target `php-shell` | GERING |
| **`make db-dump`** — Testdatenbank dumpen | Target `db-dump` | GERING |
| **`make generate-passwords`** — Passwort-Generierung (Verhalten, `.env`) | Target `generate-passwords` | MITTEL |
| **Variable `TRIVY_VERSION`** — Trivy-Version konfigurierbar | Makefile Zeile 14 | GERING |
| **Variable `OTEL_SDK_DISABLED`** — Nutzung via `make`/`.env` | CLAUDE.md dokumentiert Existenz, nicht Nutzungsweise | GERING |
| **`TEST_RUN_ID` / `RUN_ID`** — UUID-Generierung für Trace-Korrelation | Automatisch in `test-e2e*`, `test-performance` | GERING |

### 4.3 Identifizierte Inkonsistenzen — prüfen und ggf. beheben

**Wichtig:** Das Projekt verwendet bewusst zwei Nummerierungssysteme:
- **Layer 1–5** = organisatorische Code-Einheiten (Makefile/Verzeichnisse)
- **ISTQB-Teststufe 1–3** = fachliche Teststufen nach ISTQB-Standard

Das Mapping ist in `tp_decisions_spec.md:66–78` dokumentiert:
Layer 1 + 5 = Querschnitte (Statik, Performance), Layer 2/3/4 = Teststufe 1/2/3.
Diese Dualität ist **kein Fehler** — bei der Bereinigung nicht „vereinheitlichen".

| Prüfpunkt | Betroffene Stelle | Aktion |
|---|---|---|
| **"Layer 2 — Upstream-Tests"**: Formulierung missverständlich — es sind keine Tests aus dem Upstream-Repo, sondern die im Fork gepflegten Unit-Tests, die auf dem Upstream-Code arbeiten. | CLAUDE.md Zeile 18 | Prüfen ob die Bezeichnung präziser sein sollte (z. B. "Komponententest" analog zur Layer-Tabelle). |
| **Durchgängige Verwendung**: Wird überall korrekt zwischen Layer-Nummer und ISTQB-Teststufe unterschieden? | Alle Dokumente | Stichprobenartig prüfen, dass nirgends Layer- und Teststufen-Nummern verwechselt werden. |

### 4.4 Identifizierte Redundanzen — auflösen

| Redundanz | Stellen | Auflösung |
|---|---|---|
| **Layer-Architektur-Tabelle** steht in CLAUDE.md UND tp_overview_spec.md | CLAUDE.md Zeile 49–57, tp_overview nicht als Tabelle aber im Fließtext | Prüfen ob tp_overview auf CLAUDE.md verweisen kann statt zu duplizieren. |
| **OTel-Stack** in CLAUDE.md und tp_infrastructure_spec.md | CLAUDE.md Zeile 166–175, tp_infrastructure ausführlich | Akzeptabel — CLAUDE.md hat die Kurzform, tp_infrastructure die Details. Sicherstellen, dass kein Widerspruch besteht. |
| **Diagnose-Befehle** in CLAUDE.md teilweise redundant zu Make-Targets | CLAUDE.md Zeile 88–116 | Prüfen: Können `podman-compose exec`-Befehle durch `make status`, `make logs` etc. ersetzt werden? |

### 4.5 Do's and Don'ts / Empfehlungen

Prüfen, ob folgende Regeln in CLAUDE.md vollständig und aktuell sind:

- **SELinux-Falle**: Ist die `:Z`-Warnung noch korrekt und ausreichend?
- **Parallelitäts-Regeln**: Nur eine Teststufe gleichzeitig — ist das klar
  genug formuliert?
- **Timeout-Regeln**: `run_in_background: true` für lange Tests — vollständig?
- **Iteratives Fixing**: PID-im-Container-Regel — aktuell?
- **Einzeltest-Ausführung**: Syntax für Filter — aktuell?
- **Modul-Mounting**: Do/Don't für `MODULE_PATH`/`MODULE_NAME` — vollständig?
  Gilt das auch für andere Targets außer `test-integration`?

### 4.6 Strukturierung in CLAUDE.md

Nach der Bereinigung sollte CLAUDE.md folgende Abschnitte vollständig abdecken
(Reihenfolge ist Empfehlung, nicht Vorgabe):

1. **Kontext** — Was ist dieses Repo?
2. **Kanonischer Testaufruf** — Schnellstart mit den häufigsten Targets
3. **Vollständige Make-Target-Referenz** — JEDES Target mit Parametern und
   Abhängigkeiten. Gruppiert nach Kategorie:
   - Stack-Management (up, down, clean, setup, status, logs, up-debug)
   - Testausführung (test-static, test-unit, test-integration,
     test-integration-quick, test-e2e, test-e2e-quick, test-performance,
     test-all)
   - Security-Tests (security-build, security-up, security-down,
     security-clean, test-security, test-integration-security-%)
   - Diagnose & Analyse (crap-report, trace-report, perfschema-truncate,
     perfschema-extract, db-dump, mysql-shell, php-shell)
   - Passwörter & Setup (generate-passwords, clone-upstream)
4. **Konfigurationsvariablen** — Alle Variablen (`MODULE_PATH`, `MODULE_NAME`,
   `WEBTREES_SOURCE`, `OTEL_SDK_DISABLED`, `TRIVY_VERSION`, `LAYER`, `RUN_ID`,
   `WEBTREES_SECURITY_TRACE`) mit Beschreibung und Standardwert
5. **Optionales Modul-Mounting** (bestehend)
6. **SELinux-Falle** (bestehend)
7. **Layer-Architektur** (bestehend, Inkonsistenzen bereinigt)
8. **Abhängigkeiten** (bestehend, ggf. Security-Container ergänzen)
9. **Parallelitäts- und Timeout-Regeln** (bestehend)
10. **Status-Diagnose und Einzeltest** (bestehend)
11. **OTel-Stack** (bestehend)
12. **Coverage-Iteration** (bestehend)
13. **Teststrategie-Dokumentation** — Verweis auf tp_overview_spec.md
14. **Sprache** (bestehend)
15. **Lizenz-Header** (bestehend)
16. **Kein Perl** (bestehend)
17. **Git** (bestehend)

### 4.7 tp_overview_spec.md anpassen

Nach der Bereinigung in Phase 1–3 prüfen:

- Verweise auf gelöschte Dokumente entfernen
- Security-Audit-Sektion ergänzen (Phase 2.3)
- Schnelleinstieg: Sicherstellen, dass er auf CLAUDE.md verweist für die
  vollständige Target-Referenz, nicht dupliziert
- Prüfen ob `tp_upstream_spec.md`-Verweis noch korrekt ist (Datei bleibt,
  Neufassung ist separat)

### Verifikation Phase 4

1. **CLAUDE.md komplett nochmals lesen.** Gegen `Makefile` gegenprüfen:
   - Jedes öffentliche Target (ohne `_`-Präfix) ist dokumentiert.
   - Jede dokumentierte Variable existiert im Makefile.
   - Kein Widerspruch zwischen Beschreibung und Implementierung.
2. **tp_overview_spec.md lesen.** Alle Links anklickbar? Keine Duplikation
   von CLAUDE.md-Inhalten?
3. **tp_infrastructure_spec.md stichprobenartig prüfen.** Kein Widerspruch
   zu CLAUDE.md?

**→ Benutzer-Rücksprache:** CLAUDE.md-Entwurf vorlegen. Benutzer bestätigt
bevor Phase 5 beginnt.

---

## Phase 5 — Querverweise-Validierung

Abschließend alle verbleibenden Markdown-Dateien auf tote Links prüfen.

**Vorgehen:** Da Markdown-Links relative Pfade verwenden, muss die
Prüfung das Verzeichnis der referenzierenden Datei als Basis nehmen.

```bash
# Alle Markdown-Dateien unter docs/ und CLAUDE.md auf tote relative Links prüfen
find docs/ -name '*.md' -print0 | while IFS= read -r -d '' mdfile; do
  dir=$(dirname "$mdfile")
  grep -oP '\[[^\]]*\]\(\K[^)#][^)]*' "$mdfile" | while read -r link; do
    # Nur relative Links prüfen (kein http/https)
    case "$link" in
      http://*|https://*) continue ;;
    esac
    # Anker-Teil entfernen
    target="${link%%#*}"
    [ -z "$target" ] && continue
    # Relativ zum Verzeichnis der Quelldatei auflösen
    resolved="$dir/$target"
    [ ! -e "$resolved" ] && echo "TOTER LINK in $mdfile → $link (aufgelöst: $resolved)"
  done
done
```

Jeden toten Link entweder korrigieren oder die Referenz entfernen.

---

## Phase 6 — Abschluss-Review, Commit und Validierung

### 6.1 Diff-Review

Vor dem Commit den **gesamten Diff** lesen (`git diff --stat` + `git diff`
für die geänderten Dateien). Prüfpunkte:

- **Keine versehentlichen Löschungen:** Sind nur die geplanten Dateien gelöscht?
- **Keine inhaltlichen Regressionen:** Wurden bei Querverweis-Bereinigungen
  versehentlich Inhalte entfernt, die bleiben sollten?
- **Keine Feature-ID-Reste:** `grep -r '[A-Z][0-9]\{2\}_' docs/` — außerhalb
  von `security-audit/`, `tds_conditions_ref.md`, `tds_coverage_ref.md` und
  `tp_risks_spec.md` sollten keine Feature-IDs mehr auftauchen.
- **Konsistente Sprache:** Alle geänderten Abschnitte in de_DE.

### 6.2 Commit

1. **Einen einzigen Commit** für die gesamte Bereinigung erstellen.
2. **Commit-Message** (de_DE): Zusammenfassung der Bereinigung mit Angabe
   der Anzahl gelöschter Dateien und der wesentlichen Änderungen.

### 6.3 Post-Commit-Verifikation

1. `tp_overview_spec.md` lesen — Navigation zu allen verbleibenden Dokumenten funktioniert.
2. `CLAUDE.md` lesen — alle Abschnitte vollständig, kein toter Link.
3. Phase-5-Linkchecker nochmals laufen lassen — 0 tote Links.

---

## Zusammenfassung: Was bleibt, was geht

### Bleibt (stabile Standard-Dokumentation)

| Kategorie | Dateien |
|---|---|
| Testplan | `tp_overview_spec.md`, `tp_decisions_spec.md`, `tp_infrastructure_spec.md`, `tp_conventions_spec.md`, `tp_risks_spec.md`, `tp_ratchet_spec.md`, `tp_upstream_spec.md` |
| Testdesign | `tds_conditions_ref.md`, `tds_methodik_spec.md`, `tds_coverage_ref.md` |
| Workflows | `wf_test-iteration_guide.md`, `wf_coverage-to-test_guide.md`, `wf_code-to-test_guide.md`, `wf_code-to-systemtest_guide.md` |
| Referenzen | `ref_istqb-glossar_ref.md`, `ref_webtrees-glossar_ref.md` |
| Security-Audit | `tp_security-audit_spec.md` (umbenannt), `security-audit/` (komplett) |
| Coverage-History | `coverage-runs/` (komplett) |
| Bedienungsleitfaden | `CLAUDE.md` (aktualisiert) |

### Geht (~102 Dateien + Verzeichnisse)

| Kategorie | Dateien |
|---|---|
| Portierung | `port-implementation/` (18 .md), `port_analysis_*.md` (2) |
| Testspezifikationen | `testspezi/` L3 (33) + L4 (32) = 65 |
| Umsetzungspläne | 2 |
| Delta-Analysen | 2 |
| Improvement-Pläne | 2 |
| Performance-/Infra-/Log-Pläne | 5 |
| DB-Plattform-Analysen | 3 |
| Übergreifende Konzepte | 2 (nach Integration in Phase 3) |
| Dieses Prompt | 1 (selbst löschbar nach Abarbeitung) |

### Verändert

| Datei | Änderung |
|---|---|
| `CLAUDE.md` | Lücken geschlossen, Inkonsistenzen bereinigt, vollständige Target-Referenz |
| `tp_overview_spec.md` | Security-Audit-Sektion, tote Links bereinigt |
| `wf_test-iteration_guide.md` | Generische Patterns aus L3-Konzepten integriert |
| `wf_code-to-systemtest_guide.md` | Generische Patterns aus L4-Konzepten integriert |
| `tp_conventions_spec.md` | Test-Datei-Benennungskonventionen ergänzt |
| 6 × `security-audit/*.md` | Rückverweis auf umbenannten Einstieg aktualisiert |

---

## Änderungsprotokoll — Ausführung

> Jede abgeschlossene Phase wird hier mit Datum, Ergebnis und Verifikationsstatus dokumentiert.

### Phase 1 — Löschung volatiler Dokumente (2026-04-15)

**Ergebnis:** ~97 Dateien und 3 Verzeichnisbäume gelöscht, 4 verbleibende Dokumente bereinigt.

| Kategorie | Gelöscht | Details |
|---|---|---|
| Portierung | 20 | `port_analysis_start.md`, `port_analysis_strategy.md`, `port-implementation/` (18 Dateien) |
| Testspezifikationen | 65 | `komponentenintegrationstest/testspezi/` (33), `systemtest/testspezi/` (32) |
| Umsetzungspläne | 2 | `umsetzungsplan_komponentenintegrationstests.md`, `umsetzungsplan_systemtests.md` |
| Delta-Analysen / Improvement | 4 | `testcov_*_delta.md` (2), `coverage_doc_improvement_*.md` (2) |
| Performance / Infra / Log | 5 | `skript_log_*.md` (2), `phase_a_bench_*.md`, `systemtest_perf_improve_*.md` (2) |
| DB-Plattform-Analysen | 3 | `change_to_{mariadb,mysql,postgresql}_latest_stable_analysis.md` |

**Querverweis-Bereinigung:**

| Datei | Änderung |
|---|---|
| `tp_upstream_spec.md` | Portierungs-Plan-Link, Analyse-Referenz, Ausschluss-Liste und Cross-Referenzen-Sektion entfernt |
| `tds_coverage_ref.md` | 8 Detailkonzept-Blockquotes entfernt (G, S, P, SEC, E, A, K, U) |
| `coverage-runs/2026-04-11_abdeckung-snapshot.md` | Plan-Bezug auf archiviert umformuliert |
| `coverage-runs/2026-04-11_gap-analyse-fork.md` | 2 Analyse-/Plan-Verweise auf archiviert umformuliert |

**Verifikation:** Existenz-Check 0 Restdateien, Querverweis-Scan 0 tote Verweise (exkl. Commit-Zitate und Phase-3-Dateien). ✅

**Offene-Punkte-Scan:** `skript_log_plan.md` (8 Phase-B-Items) und `systemtest_perf_improve_plan.md` (2 Items) enthielten offene Checkboxen — als bewusst nicht weiter verfolgte Restpunkte eingestuft und gelöscht.

### Phase 2 — Security-Audit-Integration (2026-04-15)

**Ergebnis:** Einstiegsdatei umbenannt, 7 Querverweise in 6 Dateien aktualisiert, neue Sektion in `tp_overview_spec.md` ergänzt.

| Aktion | Details |
|---|---|
| Umbenennung | `webtrees_security_audit_prompt.md` → `tp_security-audit_spec.md` (via `git mv`) |
| Querverweise | `01_scope_and_tracks.md`, `02_threat_model.md`, `03_infrastructure_usage.md`, `04_triage_pipeline.md` (2×), `05_security_trace_middleware.md`, `06_agentic_loop_driver.md` |
| Neue Sektion | `tp_overview_spec.md` — „Security-Audit (tp_security-audit)" mit Tabelle und Kurzbeschreibung |

**Verifikation:** Alter Name repo-weit 0 Treffer (exkl. Selbstreferenz in `prompt_doku_bereinigung.md`), neue Datei existiert, `tp_overview_spec.md` enthält Sektion. ✅

### Phase 3 — Übergreifende Konzepte integriert (2026-04-15)

**Ergebnis:** Generische Patterns aus 2 Quelldateien in 3 Zieldokumente integriert, Quelldateien und 2 leere Verzeichnisse gelöscht.

**Quelle L3** (`uebergreifende_konzepte_l3.md`, 321 Zeilen):

| Integriert in | Neue Abschnitte |
|---|---|
| `wf_test-iteration_guide.md` §3 | f.5 Mocking externer Services, f.6 Globale PHP-Funktionen |
| `wf_test-iteration_guide.md` §4 | Homogene Handler-Gruppen (Uniform-Interface-Pattern) |
| `wf_test-iteration_guide.md` §5 | i.4 Middleware-Pipeline-Testing, i.5 CLI-Command-Testing, i.6 Path-Security-Testing |
| `tp_conventions_spec.md` | Testklassen-Organisation (Namensschema, Batch-Ausnahme) |

**Quelle L4** (`uebergreifende_konzepte_l4.md`, 168 Zeilen):

| Integriert in | Neue Abschnitte |
|---|---|
| `wf_code-to-systemtest_guide.md` §4 | 4.9 Error-Page-Verification, 4.10 DataTable-Wait-Pattern, 4.11 API-basierte Redirect-Verification |

**Verworfen (feature-spezifisch):** L3 §1.4, §3.3, §5.1, §6.2–6.3 (bereits in i.1/i.3), §7 Feature-Tabelle. L4 §1.3, §2.2–2.3, §3.2–3.4, §4.

**Aufräumung:** Quelldateien gelöscht, Verzeichnisse `docs/komponentenintegrationstest/` und `docs/systemtest/` entfernt (leer nach Phase-1- und Phase-3-Löschungen).

**Verifikation:** `uebergreifende_konzepte` repo-weit 0 Treffer (exkl. Selbstreferenz). Neue Abschnitte frei von Feature-IDs (Regex-Scan bestätigt). ✅

### Phase 4 — Bedienungsleitfaden-Review (2026-04-15)

**Ergebnis:** CLAUDE.md restrukturiert — 192 → 205 Zeilen. Target-Katalog (59 Zeilen) durch Discovery-Pointer + Lifecycle-Regeln (~28 Zeilen) ersetzt, Lizenz-Header kondensiert (30 → 10 Zeilen), 4 Redundanzen aufgelöst, 2 Inkonsistenzen bereinigt.

**Designentscheidung — Discovery-Pointer statt Katalog:**
Target-Beschreibungen liefert `make help`. CLAUDE.md dokumentiert stattdessen das operative Wissen, das `make help` *nicht* zeigt: Lifecycle-Abhängigkeiten (`clean` → `up` + `setup` nötig), Diagnose-Guidance (`make status`/`make logs` als erste Anlaufstelle), versteckte Pattern-Targets (`test-integration-security-<NNN>`), Auto-UUID-Generierung. Beobachteter Fehler: Agent nutzte `make status` nicht und wusste nicht, dass nach `make clean` ein `make setup` nötig ist — beides jetzt explizit in Lifecycle-Sektion.

**Neue / ersetzte Sektionen:**

| Sektion | Inhalt |
|---|---|
| Make-Targets | Discovery-Pointer `make help` + 4 Subsektionen: Lifecycle/Abhängigkeiten, Diagnose, Parametrisierte/versteckte Targets, Automatisierung |
| Konfigurationsvariablen | 7 Variablen mit Standardwert und Beschreibung |

**Behobene Inkonsistenzen:**

| Stelle | Vorher | Nachher |
|---|---|---|
| Kanonischer Testaufruf + Layer-Tabelle | „Upstream-Tests" / „Upstream-Unit-Tests" | „Komponententest — PHPUnit (SQLite in-memory)" |
| Timeout-Regeln | `test-security` und `test-performance` fehlten | Beide in `run_in_background`-Liste ergänzt |

**Aufgelöste Redundanzen:**

| Redundanz | Auflösung |
|---|---|
| Status-Diagnose wiederholte `make status`/`make logs` | In Make-Targets → Diagnose konsolidiert; Sektion zu „Einzeltest-Ausführung" umbenannt |
| OTel-Stack wiederholte Auto-PerfSchema-Info | In Make-Targets → Automatisierung konsolidiert |
| Coverage-Iteration wiederholte `crap-report`-Beispiel | Auf Einzeiler mit Workflow-Verweis + Target-Referenz reduziert |
| Lizenz-Header: 2 Dateityp-Tabellen (7+3 Zeilen) | Durch 4 kompakte Regeln ersetzt (Syntax, Platzierung, Fork-Ausnahme) |

**Sonstige Änderungen:**
- Modul-Mounting: Klarstellung, dass Mount für alle Container-Targets gilt
- Veralteter Hinweis „Phase B des Log-Plans" entfernt
- `test-e2e-quick` und `test-integration-quick`: Zahlenwerte (30 Tests) durch stabile Beschreibung ersetzt

**Verifikation (Schritt F):**
1. Makefile-Gegenprüfung: 31 öffentliche Targets — 8 kanonisch, 3 parametrisiert/versteckt, 3 Diagnose, restliche via `make help` abrufbar ✅
2. Variablen-Gegenprüfung: 7/7 Variablen existieren im Makefile/compose.yaml ✅
3. Lifecycle-Abdeckung: `make clean` → `make setup`-Abhängigkeit explizit dokumentiert ✅
4. `tp_overview_spec.md`: Schnelleinstieg verweist auf CLAUDE.md (Zeile 79), keine Duplikation ✅
5. `tp_infrastructure_spec.md`: Stichprobe PerfSchema/OTel — kein Widerspruch ✅
