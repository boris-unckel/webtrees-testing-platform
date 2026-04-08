<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Scope und Tracks — Whitebox Security Audit

**Teil von:** [webtrees_security_audit_prompt.md](../webtrees_security_audit_prompt.md) — Whitebox Security Audit für webtrees Upstream

---

## 1 Zielsystem

Der Audit läuft **ausschließlich gegen den Fachtest-Container** (`webtrees`, Port 8080 am Host).

| Aspekt | Eigenschaft |
|---|---|
| Image | `Containerfile.webtrees` (PHP 8.5 + Apache mod_php) |
| Source | Bind-Mount `${WEBTREES_SOURCE:-./upstream/webtrees}` read-only, volle Whitebox-Lesbarkeit |
| Datenbank | `mysql` (MySQL LTS 8.4), Collation `utf8mb4_bin`, PerfSchema `stage/%=ON` |
| OTel | Auto-Instrumentation aktiv (PDO/PSR-15/PSR-18), OtelSpansModule, Jaeger-UI `:16686`, File-Exporter `artifacts/traces.json` |
| Coverage | Named Volume `coverage-data` mit Clover-XML in `artifacts/layer3/coverage.xml` |
| Fixtures | 3 Bäume: `demo`, `muster`, `privacy` — vollständig importiert, 6 Test-User angelegt |

**Nicht im Scope des Audits:** Der Distribution-Container (`webtrees-security`, `:8082`, Compose-Profil `security`). Dieser wird weiterhin ausschließlich für den bestehenden SEC-*-Track (Filesystem-Härtung, Wizard-Sperrung, HTTP-Header) genutzt und bleibt vom neuen Whitebox-Audit unberührt. Begründung: Der Whitebox-Ansatz braucht die volle OTel-Instrumentation und PerfSchema-Anreicherung, die nur der Fachtest-Container liefert.

Die **bestehenden SEC-Features** (SEC-H01–SEC-HDR04, SEC-BOT01, SEC-UTL01 — siehe [`tds_conditions_ref.md`](../tds_conditions_ref.md) §Sicherheit) sind damit orthogonal zum neuen Audit. Der Audit vergibt eine **eigene ID-Familie** `SEC-AUDIT-<NNN>`, die sich nicht mit den bestehenden SEC-*-IDs überschneidet.

---

## 2 Rollen-Taxonomie

Webtrees kennt sechs Rollen mit sehr unterschiedlichem Schadpotenzial. Der Audit teilt sie in zwei Gruppen.

### Gruppe A — Nicht-Admin-Rollen (Audit-Primärziel)

| Rolle | Login? | Typische Rechte | Erwartete Angriffsfläche |
|---|---|---|---|
| **Visitor** | nein | Öffentliche Seiten je nach Tree-Privacy-Setting | Maximale Angriffsfläche, keine Credentials nötig |
| **Member** | ja | Profil, Kommentare, ggf. beschränkter Tree-Lesezugriff | Cross-User-Lesen, Cross-Tree-Lesen, Profil-Tampering |
| **Editor** | ja | Tree-Schreibrechte (in zugewiesenen Trees) | Cross-Tree-Schreiben, privilegierte Felder (z. B. RESN-Manipulation) |
| **Moderator** | ja | Anfragen-Freigabe, Kommentar-Moderation | Moderation-Bypass, Manipulation freizugebender Inhalte |
| **Manager** | ja | Admin **eines** Baums (nicht der Site) | Tree-Grenzen-Bypass, Privilege Escalation zum **Site-Admin** |

### Gruppe B — Admin-Rolle (Audit-Sekundärziel, stark eingeschränkt)

| Rolle | Login? | Typische Rechte | Scope des Audits |
|---|---|---|---|
| **Admin** (Site-Administrator) | ja | Voller Zugriff auf alle Trees, User, Konfiguration, Module, Themes, Datenbank | **Nur** PHP-Sandbox-Ausbruch, siehe Track 2 unten |

**Wichtig:** Alle Fälle, in denen ein Admin bestehende legitime Funktionalität destruktiv nutzt (Baum löschen, User deaktivieren, Konfiguration ändern, Dump ziehen, Modul installieren), gelten **nicht** als Schwachstelle. Ein Admin hat per Designentscheidung vollständige Macht über die Anwendungsebene. Erst der Ausbruch *aus* der Anwendungsebene in das darunterliegende System ist ein Befund.

---

## 3 Die zwei Audit-Tracks

### Track 1 — Non-Admin OWASP

**Ziel:** Alle Angriffe, die **ohne** Site-Admin-Rechte funktionieren, gegen die vollständige OWASP-Top-10-Matrix aus Sicht einer der fünf Nicht-Admin-Rollen. Inkludiert explizit die **Manager-↑-Site-Admin**-Privilege-Escalation (Manager ist nicht Site-Admin im vollen Sinne).

**Umfang:**

- OWASP Top 10 vollständig: Injection, Broken Auth, Broken Access Control, IDOR, Insecure Deserialization, XSS, CSRF, SSRF, XXE, Crypto-Schwächen, Security Misconfig, Open-Redirect, Mass-Assignment, Race-Conditions, Path-Traversal, Business-Logik-Fehler.
- Webtrees-spezifische Vertikalen: GEDCOM-Import-Abuse, Privacy-Model-Bypass, Relationship-Privacy-Umgehung, `tree_id`/`xref`-Parameter-Tampering, Media-Route-Path-Traversal, Wizard-Re-Run-Race, Modul-Trigger ohne Auth.
- **Manager-Escalation-Subclass:** Manager versucht, Site-Admin-Rechte zu erlangen, ohne die PHP-Sandbox zu verlassen. Bleibt in Track 1, weil das Ergebnis ein App-Level-Privilege-Escalation ist.

**Erfolgsdefinition eines Findings in Track 1:**
- Der Angreifer erreicht aus einer der fünf Nicht-Admin-Rollen heraus einen Zustand, den er aus dieser Rolle heraus **nicht erreichen dürfte**. Beispiele: lesen privater Datensätze, schreiben in fremde Bäume, Session-Übernahme, Identitäts-Wechsel, Datenexfiltration über Suche, CSRF-Trigger auf Admin-Aktionen.

### Track 2 — PHP-Sandbox-Ausbruch

**Ziel:** Angriffe, die zu einer **Ausführung außerhalb der PHP-Anwendungsgrenze** führen — unabhängig davon, wie der Angriff initiiert wurde (Visitor, Member, Admin oder kompromittiertes Admin-Konto).

**Umfang — Ausbruchs-Vektoren:**

| Klasse | Indikator im Code |
|---|---|
| Command Injection | `shell_exec`, `exec`, `system`, `passthru`, `proc_open`, `popen`, Backtick-Operator |
| Code Evaluation | `eval`, `assert($string)`, `create_function`, `call_user_func(...)` mit externem Namen |
| Dynamisches Include | `include $var`, `require $var`, `include "tpl/$name.php"`, `data://`-Wrapper |
| File-Write in executable Pfad | `file_put_contents` / `fwrite` in DocumentRoot oder `data/`, `.htaccess`-Überschreibung, Modul-Verzeichnis |
| Upload-Bypass | `move_uploaded_file` mit schwacher Extension-/MIME-/Magic-Byte-Validierung |
| Insecure Deserialization | `unserialize($user_data)`, `phar://` mit magic-method-triggered `__wakeup`, Gadget-Chains aus Composer-Tree |
| SQL → File | `SELECT ... INTO OUTFILE`, `LOAD_FILE()` bei gesetztem `FILE`-Privileg des DB-Users |
| `mail()`-Injection | `mail()` mit User-steuerbarem fünften Parameter (`additional_parameters`) → sendmail-Argument-Injection |
| XXE → File-Read → SSRF | Libxml ohne `LIBXML_NONET`, externe Entities aktiv, `file://`-/`http://`-Wrapper |
| Image-Library-CVE | `imagecreatefromjpeg`/`imagecreatefrompng`/`finfo` auf user-geliefertem Binary |
| Modul-/Theme-Installation | Manipuliertes Custom-Modul als persistente Backdoor (auch als Admin ein interessanter Vektor: existiert ein Nicht-Admin-Pfad, der die Installation triggert?) |
| Composer-Autoload-Drift | Hook in `composer.json`-Script-Section bei user-triggered Refresh |

**Visitor → Sandbox-Ausbruch als Maximum Impact:**
Der absolute Worst-Case ist ein **nicht authentifizierter Angreifer, der aus der PHP-Sandbox ausbricht**. Dieser Vektor wird in Track 2 mit der höchsten Dringlichkeitsstufe `maximum` markiert und erhält eine eigene Spalte in der Prioritäten-Tabelle (siehe [`04_triage_pipeline.md`](04_triage_pipeline.md)).

**Erfolgsdefinition eines Findings in Track 2:**
- Der Angreifer erreicht Code-Ausführung, die **nicht** durch den PHP-Interpreter auf das Dateisystem des Webroot oder die Datenbank beschränkt bleibt, sondern:
  - einen Shell-Kommando-Prozess startet, **oder**
  - beliebige Dateien außerhalb des Webroot liest/schreibt, **oder**
  - Netzwerkverbindungen zu Container-internen Services (`mysql`, `otel-collector`, `jaeger`) oder externen Hosts initiiert, die über das normale HTTP-Protokoll hinausgehen, **oder**
  - persistente Backdoors installiert (Cronjob, Hook, Autoload-Änderung, Modul-Injection).

---

## 4 Impact-Hierarchie

Die Priorisierung im Audit folgt dieser Schadensskala (absteigend):

| Stufe | Szenario | Markierung in Task-Frontmatter |
|---|---|---|
| **MAX** | Visitor → PHP-Sandbox-Ausbruch | `impact: maximum`, `track: sandbox_escape` |
| **CRIT** | Member/Editor/Moderator/Manager → PHP-Sandbox-Ausbruch | `impact: critical`, `track: sandbox_escape` |
| **CRIT** | Admin → PHP-Sandbox-Ausbruch (auch kompromittierter Admin) | `impact: critical`, `track: sandbox_escape` |
| **HIGH** | Visitor → Site-Admin-Rechte (App-Level) | `impact: high`, `track: non_admin_owasp` |
| **HIGH** | Manager → Site-Admin-Rechte (App-Level) | `impact: high`, `track: non_admin_owasp` |
| **HIGH** | Visitor → Privat-Daten lesen (Privacy-Bypass) | `impact: high`, `track: non_admin_owasp` |
| **MED** | Member → Cross-User-/Cross-Tree-Daten lesen oder schreiben | `impact: medium`, `track: non_admin_owasp` |
| **MED** | Editor → Cross-Tree-Schreibzugriff | `impact: medium`, `track: non_admin_owasp` |
| **LOW** | Visitor → Information Disclosure ohne Privacy-Relevanz | `impact: low`, `track: non_admin_owasp` |
| **INFO** | Schwache Härtung ohne bekannten Exploit (Header, Cookies, Error-Pages) | `impact: info`, `track: hardening` |

Diese Werte landen direkt in der Task-Frontmatter (siehe [`tasks/_template.md`](tasks/_template.md)) und speisen die Sortierung des Task-Index.

---

## 5 Abgrenzung zu bestehenden SEC-Features

Der Whitebox-Audit **ersetzt nicht** die bestehenden SEC-*-Tests. Beide Tracks koexistieren:

| Dimension | Bestehender SEC-Track | Neuer SEC-AUDIT-Track |
|---|---|---|
| Container | `webtrees-security` (Distribution, Profil `security`) | `webtrees` (Fachtest, Dev-Source) |
| Ansatz | Blackbox-Smoke (Filesystem, HTTP-Header, Wizard) | Whitebox-Deep-Dive (Handler, Services, Middleware, DB-Flows) |
| Testebene | Layer 4 (Playwright) + Shell-Assertions | Layer 3 (PHPUnit) + Layer 4 (Playwright) nach Bedarf |
| Regression | `make test-security` | `make test-integration` / `make test-e2e` nach Fixture-Promotion |
| Feature-ID | `SEC-H01`…`SEC-HDR04`, `SEC-BOT01`, `SEC-UTL01` | `SEC-AUDIT-001`…`SEC-AUDIT-NNN` |
| Quelle | `docs/tds_conditions_ref.md` §Sicherheit | `docs/security-audit/tasks/INDEX.md` |

Wenn ein Audit-Finding zur Härtung in einer Kategorie führt, die bereits SEC-H oder SEC-HDR abdeckt, wird es **als separater SEC-AUDIT-Eintrag** geführt und per `related_features`-Feld in der Task-Frontmatter auf das bestehende SEC-Feature verlinkt.

---

## 6 Track-Allocation-Regeln für Triage

Die Phase T2 (Reachability-Filter in [`04_triage_pipeline.md`](04_triage_pipeline.md)) weist jeder triagierten Datei eine Track-Zuordnung zu. Die Regeln:

1. Datei enthält mindestens eine Dangerous-Function aus Track 2 (shell_exec/exec/eval/unserialize/…) **und** ist aus mindestens einer Nicht-Admin-Rolle erreichbar → **Track 2 (MAX oder CRIT je nach Rolle)**, zusätzlich **Track 1**.
2. Datei enthält Dangerous-Function **und** ist nur Admin-erreichbar → **Track 2 (CRIT)**.
3. Datei enthält keine Dangerous-Function, ist aber aus Nicht-Admin-Rolle erreichbar → **Track 1**.
4. Datei enthält keine Dangerous-Function, ist nur Admin-erreichbar → **deprioritized**, fällt aus dem Audit heraus (außer es handelt sich um einen Modul-/Theme-Installer, der als Backdoor-Vektor interessant bleibt).
5. Datei ist gar nicht über HTTP erreichbar (interne Service-Klasse) → **Nachverfolgung** nur über Caller-Analyse, nicht als eigener Audit-Task.

Die Track-Zuweisung ist **nicht exklusiv**: Eine Datei kann gleichzeitig in Track 1 (wegen Input-Sinks) und Track 2 (wegen Dangerous-Function) laufen, dann entstehen zwei Audit-Tasks (`SEC-AUDIT-NNN` und `SEC-AUDIT-NNN+1`) mit unterschiedlichen Hypothesen.

---

## 7 Stop-Kriterien für einen Audit-Lauf

Ein einzelner Audit-Lauf ist abgeschlossen, wenn **eines** der folgenden Kriterien erfüllt ist:

- Alle Tasks mit `priority ≥ 3.5` sind im Status `exploit_confirmed` oder `exploit_refuted`.
- Das Zeit- oder Token-Budget des Laufs ist erschöpft (in [`06_agentic_loop_driver.md`](06_agentic_loop_driver.md) definiert).
- Eine **maximum-impact** Finding (Visitor → Sandbox-Escape) wurde bestätigt — in diesem Fall stoppt der Lauf sofort, wechselt in den **Embargo-Modus** (siehe [`10_fixing_and_disclosure.md`](10_fixing_and_disclosure.md)) und benachrichtigt den User zur manuellen Entscheidung.

---

## 8 Querverweise

- [02_threat_model.md](02_threat_model.md) — OWASP × Webtrees-Domänen-Matrix (konkretisiert Track 1)
- [04_triage_pipeline.md](04_triage_pipeline.md) — T0/T1/T2/T3 Priorisierung
- [10_fixing_and_disclosure.md](10_fixing_and_disclosure.md) — Embargo-Workflow bei maximum-impact
- [tasks/_template.md](tasks/_template.md) — Task-Frontmatter mit `track`/`impact`-Feldern
