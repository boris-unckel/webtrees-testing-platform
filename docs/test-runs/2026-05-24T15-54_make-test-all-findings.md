<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Test-Run-Protokoll: `make test-all` nach Upstream-Refresh + Cluster-A/B/B+

**Zeitstempel:** Start 2026-05-24T15:54:23+02:00 · Ende ca. 2026-05-24T16:45:59+02:00 (≈ 52 min)
**Branch:** `main` (Commit-Stand: docs-Updates `tds_conditions_ref.md` / `tds_coverage_ref.md` aus dieser Session — noch uncommitted)
**Umgebung:** Frischer Stack (`make clean && make up && make setup`), `OTEL_SDK_DISABLED` Default (= aktiv).
**Vollständiges Log:** `artifacts/test-all-2026-05-24T15-54-23.log` (5 327 Zeilen)
**Aufgabenstellung:** Findings prüfen, persistent festhalten, **noch nicht fixen**, solange kein harter Layer-Abbruch.

---

## 1. Gesamtbilanz pro Layer

| Layer | Tool                          | Tests | Assertions | Failures | Warnings | Notices | Deprec. | Exit | Status                                                   |
|------:|-------------------------------|------:|-----------:|---------:|---------:|--------:|--------:|-----:|----------------------------------------------------------|
| 1     | PHPStan                       | —     | —          | 0        | —        | —       | —       | 0    | OK                                                       |
| 1     | PHPCS (PSR-12, informell)     | —     | —          | 2 148    | —        | —       | —       | 0    | OK (informell, Upstream-Code)                            |
| 1     | Trivy (vuln/misconfig/secret) | —     | —          | —        | —        | —       | —       | 0    | OK                                                       |
| 2     | PHPUnit `layer2-unit`         | 3 289 | 150 424    | 0        | 70       | —       | —       | 0    | **OK, but there were issues** (Lang-Includes)            |
| 3     | PHPUnit `layer3-integration`  | 1 327 | 5 358      | **4**    | —        | 30      | 1       | 1    | **FAILURES** (kein harter Abbruch — alle Tests gelaufen) |
| 4     | Playwright `layer4-e2e`       | —     | —          | —        | —        | —       | —       | —    | **NICHT GESTARTET** (durch L3-Fehler in `make`-Kette)    |
| 5     | Playwright `layer5-performance` | —   | —          | —        | —        | —       | —       | —    | **NICHT GESTARTET** (durch L3-Fehler in `make`-Kette)    |

**Wichtig zur Einordnung „kein harter Layer-Abbruch":**

- L3 lief mit 1 327 / 1 327 Tests **vollständig** durch (45:51 min). Die 4 Failures stoppten den Test-Runner nicht; PHPUnit-Exit 1 hat lediglich `make test-all` an Stelle `Makefile:112` abbrechen lassen, bevor L4/L5 starten konnten.
- Das ist kein „Layer-Abbruch" im Sinne der User-Direktive (Container weggebrochen, PHP-Fatal, Setup-Fehler), sondern eine reguläre Test-Aussage. → Findings werden hier **nur dokumentiert**, kein Fix.
- Konsequenz: L4 und L5 sind in diesem Lauf **ungetestet**. Sobald die L3-Failures-FAILURE_PIN-Politik gewünscht (oder L3 wieder grün) ist, müssen L4/L5 nachgezogen werden.

---

## 2. L3-Failures im Detail

### 2.1 NEU — `AutoCompleteIntegrationTest::test_autocomplete_citation_returns_json_for_valid_source`

**Datei/Zeile:** `/tests/layer3-integration/tests/AutoCompleteIntegrationTest.php:254`

**Symptom (Test-Botschaft, Original):**

> Schnittstellenvertrag: Body muss JSON-Array (`[`) sein. Aktuell `{` wegen gappy keys nach `uniqueStrict()` — Upstream-Bug in `AutoCompleteCitation::search()` (`values()` vor `json_encode()` fehlt).
> Failed asserting that two strings are identical. — Expected `'['` / Actual `'{'`.

**Befund (was passiert wirklich):**

- `AutoCompleteCitation::search()` baut die Treffer-Collection per `unique()` oder `uniqueStrict()`. Diese erhalten die ursprünglichen Indizes, also bleiben nach dem Dedup u. U. „Löcher" in der Index-Sequenz (gappy keys).
- `json_encode()` serialisiert eine PHP-Liste mit nicht-fortlaufenden numerischen Keys als JSON-Object (`{"0": ..., "2": ...}`) statt als Array (`[...]`).
- Erwartung des Schnittstellenvertrags ist ein JSON-Array (Frontend ruft Autocomplete-Endpunkt auf, erwartet ein `[ {value: ...}, ... ]`).
- Fehlende `->values()`-Reindexierung vor dem Encode ist die Lücke.

**Status:** **Neuer Upstream-Bug, vermutlich aus dem letzten Refresh.** Test ist **honest red**, kein FAILURE_PIN-Marker hinterlegt — die Aussage gehört in die Befund-Spalte der Feature-Matrix umgehängt, sobald wir die Fix-Wartelogik klären.

**Provenanz:** Keine SEC-AUDIT-Verknüpfung, reiner Interface-Contract-Bug. Test wurde im Zuge des Schnittstellen-Sweeps angelegt.

**Empfohlene Nächst-Schritte (nicht jetzt umsetzen):**

1. Im Fork `webtrees-upstream/webtrees`-main einen Branch `fix/autocomplete-citation-array-shape` anlegen.
2. In `app/Http/RequestHandlers/AutoCompleteCitation::search()` vor der Antwort-Serialisierung `->values()` aufrufen.
3. Test im Testing-Platform-Repo auf FAILURE_PIN-Markup umstellen (oder direkt regressionsfest behalten, sobald Fork gemerged).

---

### 2.2 BEKANNT, FAILURE_PIN — `LoginActionIntegrationTest::test_per_user_rate_limit_fires_after_threshold`

**Datei/Zeile:** `/tests/layer3-integration/tests/LoginActionIntegrationTest.php:294 → assertRateLimitBlocks @ :348`

**Botschaft (Original):**

> Per-user rate-limit: nach 10 fehlgeschlagenen Login-Versuchen muss eine Rate-Limit-Drosselung greifen (HTTP 429 oder `HttpTooManyRequestsException`). Aktuell HTTP 302 — Upstream `main` enthält keine Login-Rate-Limit-Implementierung (`Services\RateLimitService` fehlt). FAILURE_PIN nach `wf_test-iteration_guide.md §i.7`; siehe `docs/security-audit/tasks/SEC-AUDIT-008_login_brute_force_no_rate_limit.md`.

**Status:** **FAILURE_PIN nach §i.7**, korrekt markiert, in Feature-Matrix-Zeile **P44** abgebildet (siehe `docs/tds_coverage_ref.md`).

**Action:** Bleibt rot, bis Upstream `RateLimitService` einführt. Kein Fix in dieser Session.

---

### 2.3 BEKANNT, FAILURE_PIN — `LoginActionIntegrationTest::test_site_wide_rate_limit_fires_for_unknown_users`

**Datei/Zeile:** `/tests/layer3-integration/tests/LoginActionIntegrationTest.php:321 → assertRateLimitBlocks @ :348`

**Botschaft (Original):**

> Site-wide rate-limit für unbekannte User: nach 20 fehlgeschlagenen Login-Versuchen muss eine Rate-Limit-Drosselung greifen (HTTP 429 oder `HttpTooManyRequestsException`). Aktuell HTTP 302 — Upstream `main` enthält keine Login-Rate-Limit-Implementierung. FAILURE_PIN nach `wf_test-iteration_guide.md §i.7`; siehe SEC-AUDIT-008.

**Status:** **FAILURE_PIN nach §i.7**, korrekt markiert, P44-Zeile.

**Action:** Identisch zu 2.2 — bleibt rot, bis Fork-Fix vorliegt.

---

### 2.4 BEKANNT, FAILURE_PIN — `RenumberTreeActionIntegrationTest::test_malformed_xref_is_skipped_not_renamed`

**Datei/Zeile:** `/tests/layer3-integration/tests/RenumberTreeActionIntegrationTest.php:232`

**Botschaft (Original):**

> `RenumberTreeAction::handle`: Malformed XREFs (Verstoss gegen `Gedcom::REGEX_XREF`) müssen vom Renumber-Loop übersprungen werden — kein SQL-Crash, kein Rename. Aktuell `QueryException`, weil Upstream `main` in `app/Http/RequestHandlers/RenumberTreeAction.php:86` die XREF per Inline-Concat in `REPLACE(i_gedcom, '0 @{xref}@ INDI', ...)` einsetzt statt per Param-Binding. FAILURE_PIN nach §i.7; siehe `docs/security-audit/tasks/SEC-AUDIT-006_renumber_tree_raw_expression.md`.

> Original-Exception: `SQLSTATE[42000]: Syntax error or access violation: 1064 ... near 'INJECT@ INDI', '0 @X21@ INDI') where i_file = ? and i_id = ?' at line 1`.

**Status:** **FAILURE_PIN nach §i.7**, korrekt markiert, P34-Zeile (Befund: „Upstream-Bug (xref-Format-Guard)").

**Action:** Bleibt rot bis Fork-Fix. **Bemerkenswert sicherheitsrelevant**: der Test demonstriert eine konkrete SQL-Syntax-Verletzung über einen reinen Format-Verstoss. Solange der XREF aus einer authentifizierten Moderator-Eingabe stammt, ist es kein Eskalations-Vektor — aber genau das gehört vor einem produktiven Fork-Release reviewed.

---

## 3. L2-Warnings (70× include() failed)

**Symptom:** Über den L2-Lauf hinweg Hunderte gleicher Warnings:

```
Warning: include(/var/www/html/app/../resources/lang/<locale>/messages.php): Failed to open stream: No such file or directory
in /var/www/html/vendor/fisharebest/localization/src/Translation.php on line 60
```

**Beobachtete Locales (Auszug, nicht vollständig):** `hu`, `nl`, `pl`, `pt`, `sk`, `fi`, `sv`, `vi`, `tr`, `el`, `bg`, `kk`, `ru`, `uk`, `ka`, `he`, `ar`, `hi`, `zh-Hans`, `zh-Hant`. `he` taucht 11× hintereinander auf — vermutlich pro Test-Case der `I18N`-Suite eine Reinitialisierung.

**Befund:**

- Der L2-Container nutzt `webtrees-lang-cache` als named volume (`/var/www/html/resources/lang`) + `${WEBTREES_SOURCE}/resources/lang:/webtrees-lang-seed:ro,z` als Seed-Mount (siehe `compose.yaml`).
- `scripts/setup-webtrees.sh` befüllt das Volume per Seed-Copy. Wenn nach dem Upstream-Refresh entweder neue Locales hinzugekommen oder kompilierte `messages.php`-Dateien nicht im Seed liegen (sondern erst durch ein Build-Step erzeugt würden), fehlen die `messages.php` zur Laufzeit.
- L2-Suite ist trotzdem grün (PHPUnit fängt Warnings ab, der `localization`-Code nimmt vermutlich einen Fallback auf englische Strings).

**Status:** **Nicht abbruchwirksam, aber neu/auffällig.** Vermutlich Folge des Refresh. Kein Fix in dieser Session — separates Item.

**Empfohlene Nächst-Schritte (nicht jetzt):**

1. `make setup` instrumentieren: Nach Seed-Copy `find /webtrees-lang-seed -name 'messages.php'` und `find /var/www/html/resources/lang -name 'messages.php'` vergleichen.
2. Falls Locales-Build erforderlich (z. B. via `composer run-script` o. Ä.) — Build-Step in `setup-webtrees.sh` ergänzen.
3. Sicherheitsmassnahme: Der `:ro`-Mount darf nicht aufgeweicht werden — der Build muss im `webtrees-lang-cache`-Volume passieren, nicht im Seed.

---

## 4. L3-Deprecation (1×)

**Stelle:** `/var/www/html/app/Http/RequestHandlers/CheckTree.php:209`

**Botschaft:** `Using null as an array offset is deprecated, use an empty string instead`

**Befund:** Upstream-Code-Smell in `CheckTree::handle()`. Tritt 2× im Log auf (z. B. zwei Test-Cases triggern denselben Pfad), wird von PHPUnit als 1 Deprecation gezählt.

**Status:** **Kein Fix in dieser Session.** Gehört in einen Fork-Branch `chore/checktree-null-offset`, ohne semantische Änderung am Verhalten.

---

## 5. L3-Notices (30×)

Detail-Block ist im PHPUnit-Output nicht abgedruckt (PHPUnit gruppiert Notices nur unter `PHPUnit Notices: 30`), `--display-notices` ist im Konfig-XML offenbar deaktiviert. Im Live-Output sichtbar sind die `N`-Marker im Progress-Bar — über die Verteilung lässt sich nichts Belastbares sagen, ohne die JUnit-XML zu parsen.

**Befund:** Reine Beobachtung. **Nicht abbruchwirksam.**

**Empfohlene Nächst-Schritte (nicht jetzt):**

1. JUnit-XML unter `artifacts/layer3/junit.xml` parsen und die 30 Notices-Stellen extrahieren.
2. Falls Cluster-Bildung erkennbar (z. B. PHPUnit-eigenes „Test class did not contain at-test annotation" o. Ä.): in eigenem Refactor-Pass adressieren.

---

## 6. Spürnasen-Sweep „stille Tests" (Layer-3)

Der Sweep hat 13 Treffer geliefert (Report: `artifacts/layer3/silent-tests-sweep.txt`). Alle Treffer sind in den jeweiligen Test-Dateien als legitime, kommentar-belegte Fixture-Bedingungen oder gesicherte `assertTrue(true, '<Begründung>')`-Pins dokumentiert (z. B. „Upload rejected — strongest mitigation (L0)" in `MediaFileDeliveryIntegrationTest`, „Block via HttpAccessDeniedException" in `ModuleActionIntegrationTest`, „Rate limit fired via HttpTooManyRequestsException" in `LoginActionIntegrationTest` — Letzteres ist die Alternativ-Branch der FAILURE_PIN-Heuristik). Kein Handlungsbedarf in dieser Session.

---

## 7. Was war NICHT abgedeckt

- **Layer 4 (Playwright/Chromium E2E)** — nicht gestartet. Insbesondere der **neue P40-Klickpfad-Test** (`P40 — Moderator akzeptiert fully-pending Add-Child Change und Eintrag verschwindet bei Reload`) wurde in `make test-all` **nicht ausgeführt**. → muss separat via `make test-e2e` oder `make test-e2e-quick` nachgezogen werden, sobald L3-Failures eingeordnet sind.
- **Layer 5 (Performance/Playwright-Metrics)** — nicht gestartet.

---

## 8. Aktions-Empfehlungen (zur Diskussion, nicht ausgeführt)

1. **AutoCompleteCitation-Bug (Punkt 2.1) klassifizieren**: Echter Upstream-Bug → entweder direkt Fork-PR mit `->values()`-Fix oder Test auf FAILURE_PIN umstellen. **Frage an User:** Wollen wir ihn als SEC-/INTERFACE-Audit-Item formal hochziehen (SEC-AUDIT-NNN), oder als nicht-sicherheitsrelevanten Interface-Contract-Bug direkt patchen?
2. **L4 + L5 nachziehen** (`make test-e2e` und `make test-performance`, beide `run_in_background: true`), sobald entschieden, wie wir mit den L3-Failures-Markern weitermachen.
3. **L2-Lang-Warnings (Punkt 3)**: Ursache aufklären — bevorzugt durch Diff der `resources/lang/`-Struktur vor/nach Upstream-Refresh.
4. **CheckTree-Deprecation (Punkt 4)**: In Fork-Cleanup-Backlog einreihen, kein Eilfall.
5. **Commit des Standes**: Die in dieser Session aktualisierten Docs (`tds_conditions_ref.md`, `tds_coverage_ref.md`) sind noch uncommitted — sinnvoll, sie zusammen mit diesem Protokoll zu committen.

---

## 9. Quellen / Artefakte

- Log: `artifacts/test-all-2026-05-24T15-54-23.log`
- Trivy-Report: `artifacts/layer1/trivy-report.{json,txt}`
- PHPCS-Report: `artifacts/layer1/phpcs.json`
- PHPUnit-Coverage L2: `artifacts/layer2/coverage/`
- PHPUnit-Coverage L3: `artifacts/layer3/coverage/`
- Spürnasen-Sweep: `artifacts/layer3/silent-tests-sweep.txt`
- DB-Dump nach L3-Fail: `artifacts/` (vom Runner auto-erzeugt)
