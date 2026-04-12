<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Analyse: Verbesserung der Feature- und Coverage-Dokumentation

> **Scope:** Methodische Analyse der Dokumente [`tds_conditions_ref.md`](tds_conditions_ref.md)
> und [`tds_coverage_ref.md`](tds_coverage_ref.md) mit dem Ziel, aus dieser Analyse später
> einen Umsetzungsplan ableiten zu können. **Kein** Umsetzungsplan, **keine** Änderungen
> an den Zieldokumenten oder am Testcode im Rahmen dieser Analyse.
>
> **Leitprinzip:** Vorhandene Feature-Referenz-IDs (G01, S05, P12, SEC-H01 …) sind über
> `@see`-Annotationen in Testklassen verankert. Die Analyse respektiert diese Anker und
> empfiehlt **keine** Änderungen, die ein breit angelegtes Test-Refactoring erforderlich
> machen würden.

**Datum:** 2026-04-11
**Commit-Referenz (Testing-Platform):** `f672a96` (main)
**Commit-Referenz (Upstream-Fork, L2-Quelle):** `841616f4b5` (`port-layer2-test-doubles`, noch nicht im Upstream-main)
**Commit-Referenz (Anwendungscode):** `security-audit-consolidated` in `upstream/webtrees/`
**Umsetzungsplan:** [`coverage_doc_improvement_plan.md`](coverage_doc_improvement_plan.md)

---

## 0 Entscheidungen dieser Analyse (Vorab-Fixierung)

Die Analyse wurde vor dem Schreiben mit vier Entscheidungen pragmatisch eingerahmt, damit sie
auf ein konkretes Zielbild hinarbeitet und der spätere Umsetzungsplan dicht daran ansetzen kann:

| # | Entscheidung | Begründung |
|---|---|---|
| E1 | **Zielbild mit Empfehlungen** statt neutraler Inventarisierung | Damit der spätere Plan direkt andocken kann, ohne erneut über die Richtung zu diskutieren. |
| E2 | **Duale Nomenklatur mit Mapping** (ISTQB-Teststufen 1–3 **und** Layer L1–L5 nebeneinander) | Beide Bezugssysteme existieren bereits (ISTQB in `tp_decisions_spec.md`, Layer in `CLAUDE.md` / Makefile). Sie sind **komplementär**, nicht widersprüchlich. Die Harmonisierung erfolgt über eine Mapping-Tabelle am Dokumentanfang. |
| E3 | **Gap-Analyse neu erheben** (als Methode beschrieben, nicht in dieser Analyse ausgeführt) | Der historische Befund „95 % Stub-Tests, 1233 Testdateien" aus `tds_conditions_ref.md` datiert vom 2026-03-26 und ist durch den Fork-Branch `port-layer2-test-doubles` (278 substanziell aufgewertete Tests) überholt. Neu-Methodik wird beschrieben, Zahlen werden hier **nicht** neu erhoben. |
| E4 | **Feature-Zeile bleibt atomar** — ein Feature = eine Matrix-Zeile | Die Feature-IDs sind als `@see`-Anker in Testklassen verankert. Eine feinere Matrix-Granularität (pro EP, pro Testmethode) würde zu einer Brechung dieser Anker oder zu erzwungenem Test-Refactoring führen. Details (EP-IDs, Qualitätsstufe, Testanzahl) bleiben als informeller Klammer-Kommentar innerhalb der Zeile oder wandern in externe Feature-Detailkonzepte. |

---

## 1 Ist-Zustand in Zahlen

### 1.1 Struktur der beiden Dokumente

| Dokument | Zeilen | Inhalte |
|---|---|---|
| `tds_conditions_ref.md` | 422 | RE-Methodik (4 Schritte), historische Gap-Analyse (Stand 2026-03-26), 7 Feature-Matrizen (G/S/P/SEC/E/A/K/U), insgesamt 168+ Feature-IDs |
| `tds_coverage_ref.md` | 240 | Abdeckungsmatrix mit Spalten `Upstream (SQLite)` / `Eigene Infra (MySQL)` / `Eigene Infra (Playwright)` / `Status`; Zusammenfassung mit Zahlen 165/5/170 |

### 1.2 Tatsächliche Teststände (verifiziert am 2026-04-11)

| Quelle | Menge | Notiz |
|---|---|---|
| Upstream-Fork L2 (`webtrees-upstream/webtrees`, Branch `port-layer2-test-doubles`) | **1232** Testdateien unter `tests/app/` + **5** unter `tests/feature/` = 1237 Gesamttestdateien | Branch nur lesend. 276 Test-Dateien modifiziert/neu im Delta zu `main` (Commit `841616f4b5`: *„Add substantive component tests for 278 test files"*) |
| Upstream-Fork L2 Delta (Commit `841616f4b5`) | +14.346 Zeilen, −77 Zeilen in 276 Dateien | Substanzieller Aufwertungs-Commit |
| Layer 3 Integrationstests (`layer3-integration/tests/`) | **84** `*Test.php`-Dateien (exkl. `MysqlTestCase.php`) | Branch `main` |
| Layer 4 E2E-Tests (`layer4-e2e/tests/`) | **26** `*.spec.ts`-Dateien (inkl. `tests/security/`) | Branch `main` |
| Anwendungscode Handler (`upstream/webtrees/app/Http/RequestHandlers/`) | **335** `.php`-Handler-Klassen | Branch `security-audit-consolidated` |
| Anwendungscode Services (`upstream/webtrees/app/Services/`) | **38** Service-Klassen | Branch `security-audit-consolidated` |
| Anwendungscode Routen (`upstream/webtrees/app/Http/Routes/WebRoutes.php`) | 746 Zeilen Route-Registrierung | Branch `security-audit-consolidated` |
| CLI-Commands (`upstream/webtrees/app/Cli/Commands/`) | 13 CLI-Commands | `TreeExport`, `TreeImport`, `TreeList`, `SiteSetting`, `TreeSetting`, `UserSetting`, `UserTreeSetting`, `UserEdit`, `UserList`, `CompilePoFiles`, `ConfigIni`, `SiteOffline`, `SiteOnline` |

### 1.3 Historischer Layer-2-Coverage-Snapshot

`docs/coverage-runs/2026-04-11_layer2-vs-layer3.md` dokumentiert eine verifizierte
Gesamt-Coverage von **39,82 %** (L2) zu **39,83 %** (L3) — nahezu identisch im Gesamtwert,
aber strukturell komplementär (L2 stark bei Census, Elements, SurnameTraditions;
L3 stark bei Services, CustomTags, Date, CLI). Dies ist ein wichtiges methodisches Signal:
**eine Spaltenaggregation darf die Komplementarität nicht verdecken.**

---

## 2 Frage 1a — Historische vs. methodisch geeignete Beschreibungen: Feature-Ermittlung

### 2.1 Abschnitte mit rein historischem Charakter

| Abschnitt in `tds_conditions_ref.md` | Warum historisch | Empfehlung |
|---|---|---|
| **„Befund: Gap-Analyse der existierenden webtrees-Tests"** (Zeilen ≈ 58–130) mit dem Lead-In *„Stand: webtrees 2.2.6-dev. Analyse vom 2026-03-26"* | Die Aussagen *„1233 Testdateien, ~95 % Stub-Tests, ~1 % substanzielle Tests"* beziehen sich auf den damaligen Upstream-main. Der Fork-Branch `port-layer2-test-doubles` hat seitdem 276 Test-Dateien substanziell aufgewertet (Commit `841616f4b5`). Die Quote ist damit in der Fork-Betrachtung nicht mehr aussagekräftig. | **Neu erheben** gegen die Fork-Basis (`port-layer2-test-doubles`, gesamthaft). Alt-Abschnitt mit Datum und Stand-Commit kennzeichnen und in einem separaten Archiv-Abschnitt lassen, bis die Neuerhebung fertig ist. Methode siehe §4.3. |
| **„Ungetestete Kernlogik (Import/Export/Suche/Navigation)"** (Auflistungen im Gap-Analyse-Block) | Die Aussagen *„kein einziger Chart-Rendering-Test", „Chart-Parameter und -Optionen → nicht getestet", „AutoComplete/TomSelect-AJAX-Endpoints (16 Stück)"* sind im heutigen Stand teilweise erledigt: Charts S14–S18 haben Playwright-Specs, AutoComplete-Tests existieren im Fork. | **Streichen** oder **als Historienartefakt markieren**. Die aktuelle Abdeckung zeigt `tds_coverage_ref.md`. |
| **Testzahlen-Stichproben** in den Feature-Matrix-Tabellen (z. B. *„SearchService: 20 Suchmethoden — Minimal — 1 Testmethode, prüft nur 'Collection nicht leer'"*) | Diese Einzelangaben beziehen sich auf den Upstream-main-Stand. Im Fork sind die Tests substanziell erweitert. | Aus der Matrix entfernen oder gegen den **aktuellen Fork-Stand** re-messen. |

### 2.2 Methodisch geeignete Beschreibungen

Folgende Abschnitte sind **methodisch stabil** und nicht vom Zeitpunkt abhängig — sie eignen
sich unverändert als Grundlage für Feature-Ermittlung:

| Abschnitt / Methodik | Qualität als Feature-Quelle | Beurteilung |
|---|---|---|
| **„RE-Methodik Schritt 1: Code-Topologie erfassen"** (Route → Handler → Service → DB) | **Sehr hoch** | Methodisch sauber: Die *öffentlichen Methoden von Service-Klassen* sind die fachlichen Fähigkeiten. Das ist ein stabiles Prinzip, unabhängig vom Code-Stand. Anwendbar auf *jeden* Branch-Stand. |
| **„RE-Methodik Schritt 3: GEDCOM-Standard-Abgleich"** (Tags, Encoding, Date-Formate, CONC/CONT) | **Sehr hoch** | Der GEDCOM-5.5.1-Standard ist eine externe, zeitstabile Orakelquelle (→ `tds_methodik_spec.md`). |
| **„RE-Methodik Schritt 4: Feature-Matrix aufbauen"** (Code-Stelle → Anforderung → Testart → Priorität → Teststufe) | **Sehr hoch** | Das Zuordnungsschema bleibt, auch wenn einzelne Zellen neu befüllt werden müssen. |
| **Feature-Matrix-Struktur (G/S/P/SEC/E/A/K/U — 7 fachliche Domänen)** | **Hoch** | Die fachliche Gliederung nach Domänen entspricht der webtrees-Architektur und ist methodisch tragfähig. User-Rückmeldung: *„Die Grundidee, dass fachliche Bereiche gegliedert werden und Feature fachlich genannt werden, ist ausgezeichnet."* |
| **Feature-Matrix-Domänen-Beschreibungen** (Abgrenzungskommentare wie *„G = Datenformat, S = Ansicht, P = Zugriffskontrolle"*) | **Hoch** | Tragfähige Abgrenzungslogik. Nur punktuell nachschärfen. |
| **Feature-Referenz-ID-System (G01 … U02)** | **Sehr hoch** | Durch `@see`-Anker in Testklassen (siehe §3.2) stabil verankert. **Keine Änderungen** der IDs empfohlen (Refactoring-Risiko). |
| **„RE-Methodik Schritt 2: Gap-Analyse der existierenden Tests"** (Assertionsdichte als Metrik) | **Mittel (methodisch gut, Zahlen veraltet)** | Die *Methodik* (Assertionsdichte = Proxy für Testsubstanz) bleibt gültig. Die *Zahlen* (95 % Stub) sind veraltet. → Als Methode behalten, Ergebnisse neu erheben. |

### 2.3 Zwischenfazit — Feature-Ermittlung

Die Feature-Ermittlung ist methodisch **heute schon sauber** aufgestellt. Sie leidet nur darunter,
dass die bestehende Dokumentation einen 2026-03-26-Schnappschuss als *Begründung* für die
Priorisierung enthält. Die Priorisierung selbst (Hoch/Mittel/Niedrig) ist unabhängig davon
tragfähig, weil sie auf Code-Topologie und Domänen-Kritikalität beruht, nicht auf der
historischen Lücke.

---

## 3 Frage 1b — Historische vs. methodisch geeignete Beschreibungen: Coverage-Beschreibung

### 3.1 Spaltenstruktur von `tds_coverage_ref.md` — Ist-Analyse

Die heutige Matrix verwendet für die meisten Domänen folgende Spalten:

```
| # | Feature | Upstream (SQLite) | Eigene Infra (MySQL) | Eigene Infra (Playwright) | Status |
```

**Befunde:**

| Befund | Bewertung |
|---|---|
| Die Spaltenüberschriften nennen **keine** ISTQB-Teststufen (Komponententest / KIT / Systemtest) und **keine** Layer-Nummern (L2 / L3 / L4). | Für den Leser, der CLAUDE.md und Makefile kennt, ist die Zuordnung implizit: `Upstream (SQLite)` = L2-unit = Komponententest; `Eigene Infra (MySQL)` = L3-integration = KIT; `Eigene Infra (Playwright)` = L4-e2e = Systemtest. **Historisch gewachsen, methodisch unklar.** |
| Die Sicherheits-Submatrix verwendet eine **andere** Spaltenstruktur: `Shell-Assertions` / `Playwright-Security` / `Status`. | Der Grund liegt in der Distribution-Container-Architektur (Filesystem-Assertions statt PHPUnit). Funktional korrekt, aber bricht die einheitliche Lesart. |
| Die Privacy/E/A/K/U-Submatrizen lassen die **Upstream-Spalte ganz weg**, obwohl es Upstream-Tests gibt (z. B. für Privacy-Logik via Mocks). | Historisch gewachsen: Für P/E/A wurde ursprünglich angenommen, dass nur L3/L4 sinnvoll sind. Der Fork-Branch `port-layer2-test-doubles` hat aber auch Privacy-Tests in L2 substanziell aufgewertet. |
| In einigen Zellen wird die **Anzahl Testmethoden und EP-IDs** als Klammer-Kommentar geführt (z. B. *„(spezifikationsbasiert, 13 Tests: EP1 keep_media=0 …)"*) — in anderen nicht. | Historisch gewachsen, inkonsistent. Methodisch ein guter Informationskanal, der aber uneinheitlich genutzt wird. |
| Die Spalte `Status` trägt Werte wie `**Abgedeckt**`, `**Nicht abgedeckt**`, `**Upstream-Befund**`, `**Deployment-Empfehlung**`, `**SKIP — deprecated**`. | Semantisch gemischt. *Abdeckungs-Status* und *Findings-Status* werden in derselben Spalte gepflegt. Methodisch unsauber. |
| Es fehlt eine explizite Verlinkung zur **Quell-Datei** pro Zelle. Der Leser liest *„`GedcomImportServiceTest` ✅"* und muss raten, wo diese Datei liegt (`tests/app/Services/GedcomImportServiceTest.php`? im Fork? im Upstream-main?). | Navigationsschwäche. Besonders problematisch, weil die L2-Tests im Fork (anderes Repo) liegen. |

### 3.2 Der `@see`-Anker-Befund (kritisch)

Die `grep`-Prüfung in `layer3-integration/tests/` zeigt, dass **Feature-IDs** via `@see`-Annotationen
in Testklassen verankert sind, der Pfad aber auf ein **nicht mehr existierendes Dokument**
(`testing-bigpicture.md`) verweist. Beispiele (keine Vollständigkeit):

```text
layer3-integration/tests/CheckTreeIntegrationTest.php:23:        * @see docs/testing-bigpicture.md G24
layer3-integration/tests/StatisticsDataIntegrationTest.php:19:   * @see docs/testing-bigpicture.md S41
layer3-integration/tests/ResnPrivacyTest.php:17:                 * @see docs/testing-bigpicture.md P16, P17, P18, P19, P20, P21
layer3-integration/tests/IsDeadTest.php:18:                      * @see docs/testing-bigpicture.md P08, P09, P10, P11, P12, P13
layer3-integration/tests/AccessControlTest.php:20:               * @see docs/testing-bigpicture.md P27, P28, P29
```

**Interpretation:**

- `docs/testing-bigpicture.md` existiert in der Gegenwart **nicht mehr** (verifiziert via Glob auf `docs/**/*.md`).
- Die **Feature-IDs** (G24, S41, P16–P21 …) selbst sind stabil und im heutigen `tds_conditions_ref.md` exakt gleich vorhanden.
- Die historische Aufspaltung eines Vorgänger-Dokuments (`testing-bigpicture.md`) in `tds_conditions_ref.md` + `tds_coverage_ref.md` wurde bei den Testklassen **nicht nachgezogen**. Das sind stale Pfade, aber konzeptuell intakte Verweise.
- **Konsequenz für das Refactoring-Risiko:** Eine spätere Aktualisierung des Pfads (`testing-bigpicture.md` → `tds_conditions_ref.md`) ist ein **reiner Search/Replace-Vorgang** über ca. 10–30 Test-Dateien, der die **IDs unverändert** lässt. Das ist risikoarm. Was dagegen **vermieden** werden muss: jegliche Umbenennung von Feature-IDs (G24 → G24a) oder Granularitätsänderung (G24 → G24-EP1, G24-EP2) — das würde die Anker inhaltlich brechen.

### 3.3 Abschnitte mit historischem Charakter in `tds_coverage_ref.md`

| Abschnitt | Warum historisch | Empfehlung |
|---|---|---|
| **Header-Zeile „165 abgedeckt (164 spezifikationsbasiert + 1 strukturbasiert), 5 nicht abgedeckt / SKIP"** | Momentaufnahme zum Commit `f672a96`. Jede neue Runde verschiebt die Zahl. | **Als Snapshot mit Datum und Commit** markieren, analog zu `coverage-runs/`. Oder aus dem Dokument in einen separaten Snapshot-Abschnitt auslagern (siehe §4.5). |
| **„E2E-Gap-Analyse (2026-03-27)"** (Zeile ≈ 295) | Datiert, bezieht sich auf 170 GET-Routen und 8 abgedeckte URLs. Die aktuellen L4-Specs sind seitdem deutlich gewachsen (26 Spec-Dateien). | **Datum-markiert als historisch** oder **neu erheben**. |
| **Die Abdeckung-Zusammenfassung** (Zeile ≈ 233, Tabelle mit *Abgedeckt / Davon mit Einschränkung / Deployment-Empfehlung / Strukturbasiert / Nicht abgedeckt*) | Momentaufnahme. | Aus dem Abdeckungsdokument auslagern in einen versionierten Snapshot (analog zu `docs/coverage-runs/`). Im Haupt-Dokument nur ein automatisch aktualisierbarer Verweis. |

### 3.4 Methodisch geeignete Strukturelemente

- **Zuordnung Feature-ID → Testklasse** als zentrales Suchregister — methodisch tragfähig und vom User explizit gewünscht.
- **Status-Spalte** als Leser-Überblick (✅ / — / SKIP) — methodisch tragfähig, wenn *Abdeckungsstatus* und *Findings-Status* getrennt werden (siehe §4.2).
- **Die domänenweise Gliederung (G/S/P/SEC/E/A/K/U)** — identisch mit `tds_conditions_ref.md`, daher strukturelles Spiegelbild. Methodisch korrekt.

---

## 4 Frage 2 — Zusätzliche Methoden und Quellen

### 4.1 Zusätzliche Quellen für Feature-Ermittlung (Frage 2a)

Die heutige Feature-Ermittlung stützt sich auf Code-Topologie (Handler → Service → DB) und den
GEDCOM-Standard. Folgende Quellen sind *zusätzlich* methodisch tragfähig und heute noch
**nicht strukturiert** einbezogen:

| Zusatzquelle | Pfad / Referenz | Verwendung für Feature-Ermittlung |
|---|---|---|
| **`WebRoutes.php` (Route-Inventar)** | `upstream/webtrees/app/Http/Routes/WebRoutes.php` (746 Zeilen) | Jede HTTP-Route ist ein Außen-Kontrakt. Systematisches Extrahieren aller Routes → Abgleich mit Feature-Matrix liefert *direkt* alle unabgedeckten Einstiegspunkte. Besonders wertvoll für die Domänen E (Editing), A (Administration), K (Kommunikation). |
| **`RequestHandlers/` (Handler-Inventar)** | `upstream/webtrees/app/Http/RequestHandlers/` (335 Klassen) | Pro Handler ein Feature-Kandidat. Cross-Referenz: Handler → Route → Service → Feature-ID. Methode der 2. Schritt in `wf_code-to-test_guide.md` ist dafür angelegt; eine **vollständige Inventarisierung** als Quelle fehlt in `tds_conditions_ref.md`. |
| **`app/Services/` (Service-Inventar)** | `upstream/webtrees/app/Services/` (38 Services) | Die public Methods je Service sind heute implizit als Feature-Kandidaten verwendet (Schritt 1 der RE-Methodik), aber nicht als vollständige Quellenliste geführt. |
| **`app/Cli/Commands/` (CLI-Inventar)** | `upstream/webtrees/app/Cli/Commands/` (13 Commands) | CLI-Commands sind Außen-Kontrakte wie HTTP-Routes. Heute sind nur einige adressiert (G25 `GedcomLoad`, G26 `TreeExport`, P35 `UserEdit`, P36 `CliSettings`). **Lücke:** `CompilePoFiles`, `ConfigIni`, `SiteOffline/Online`, `TreeList`, `UserList` sind nicht in der Feature-Matrix. Manche sind trivial (Smoke), andere sind sicherheitsrelevant (`SiteOffline`). |
| **`app/Http/Middleware/`** | `upstream/webtrees/app/Http/Middleware/` (34 Middlewares laut Coverage-Snapshot) | Middleware ist Querschnitts-Feature (Request-Pipeline), heute nur partiell adressiert (SEC-*, BadBotBlocker). Vollständige Inventarisierung fehlt. |
| **`app/Module/` (Modul-Inventar)** | ~260 Module-Klassen | Modules definieren UI-Blöcke, Charts, Listen, Themes. Heute sind S14–S18 (Charts), S19–S20 (Lists), S46 (Block-Module), S47 (InteractiveTree) adressiert — die Module-Liste selbst (was existiert) ist **nicht** als Quelle geführt. |
| **`resources/views/` (Template-Inventar)** | Twig-/PHTML-Views | Indirekte Feature-Quelle: Jede View entspricht einem Rendering-Feature. Für L4-Systemtest-Abdeckung relevant. |
| **`tests/feature/*.php` im Fork** | `webtrees-upstream/webtrees/tests/feature/` (5 Dateien, Branch `port-layer2-test-doubles`) | Diese Tests existieren oberhalb der Unit-Test-Ebene (`IndividualListTest`, `Privacy.php`, `RelationshipNamesTest`, `EmbeddedVariablesTest`, `ImportGedcomTest`) und zeigen *akzeptierte Feature-Testbedingungen* aus Upstream-Sicht. Wertvoll als „externe" Feature-Quelle, heute nicht verknüpft. |
| **Security-Audit-Befunde** (`docs/security-audit/tasks/`) | SEC-AUDIT-001 … SEC-AUDIT-008 | Gefundene Sicherheitslücken sind implizite Features („Guard muss greifen"). Heute gibt es eine separate Feature-Matrix-Domäne SEC mit SEC-H01 … SEC-UTL01, aber keine Verknüpfung zu den neueren Audit-Tasks. |
| **CRAP-Report** (`make crap-report`) | Aus `artifacts/layer3/coverage.xml` generiert | Bereits als Zusatzmethode in `wf_coverage-to-test_guide.md` dokumentiert (strukturbasiertes Testen). **In der Feature-Matrix markiert als *strukturbasiert***, z. B. G27 und S45. Als Quelle für *Feature-Neuentdeckung* nur begrenzt tragfähig (liefert Code-Pfade, keine fachlichen Features), aber methodisch anerkannt. |
| **OTel-Traces** (`artifacts/layer4/perfschema/`, Jaeger) | OTLP/Trace-Artefakte | Indirekte Feature-Quelle: Welche Routes werden in welchem E2E-Test wie oft aufgerufen? Identifiziert Feature-Überschneidungen und fehlende Lücken. Heute als Performance-Quelle genutzt, nicht als Feature-Ermittlungs-Quelle. |

### 4.2 Zusätzliche Quellen für Coverage-Beschreibung (Frage 2b)

Die heutige Coverage-Beschreibung verwendet drei Spalten (Upstream/MySQL/Playwright) plus
Status. Folgende Quellen sind *zusätzlich* tragfähig und heute **nicht** durchgängig genutzt:

| Zusatzquelle | Nutzen für Coverage-Beschreibung |
|---|---|
| **`artifacts/layer2/coverage.xml`** (PHPUnit Clover, L2 Fork-Tests) | Zeigt pro Feature (sofern Feature auf Klasse/Methode abbildbar) die *reale* Statement-Abdeckung. Methodisch *in Scope*, nicht als Vorgabe. Aktuell leer im Dateisystem — muss für die Neuerhebung einmal erzeugt werden. |
| **`artifacts/layer3/coverage.xml`** (PHPUnit Clover, L3 Integrationstests) | Gleiches für L3. Gemeinsam mit L2 lassen sich komplementäre Stärken darstellen (siehe `coverage-runs/2026-04-11_layer2-vs-layer3.md`). |
| **`artifacts/layer4/perfschema/`** (MySQL Performance-Schema) | Pro Systemtest welche Queries auf welche Tabelle treffen → Rückschluss auf welche Features durchlaufen werden. Heute ausschließlich für Performance genutzt. |
| **`artifacts/layer4/playwright-report/`** und **`test-results/`** | Pro Spec welche Testmethoden grün/rot sind → pro Feature-Zelle automatisch Status ableitbar. |
| **`@see`-Rückwärtsindex** (Grep über alle Tests) | Für jede Feature-ID eine automatisch aktualisierbare Liste *„welche Testklassen referenzieren diese ID"*. Das ist die direkte Inversion der heutigen Zuordnungsrichtung (Feature → Testklasse) und liefert **Verfolgbarkeit** in beiden Richtungen. Methode: `grep -rE '@see.*(G|S|P|SEC-|E|A|K|U)\d+' layer3-integration layer4-e2e upstream/webtrees/tests`. |
| **Upstream-Fork-Branch `port-layer2-test-doubles` gesamthaft** | Heute als „Upstream-Quelle" in der Coverage-Spalte referenziert, aber die **Branch-Herkunft** ist nicht erkennbar. Für den Leser unklar, ob der Test bereits in Upstream-main ist (= stabil verfügbar) oder nur im Fork (= nicht im Upstream-Release). |
| **Anzahl Testmethoden pro Testklasse** (via `grep -c 'function test_' …`) | Machbar automatisch. Heute nur in manchen Zellen als Klammer-Kommentar eingetragen. |
| **Qualitätsstufe pro Testzelle** (Smoke / Substanziell / EP-vollständig / Strukturbasiert) | In der Feature-Matrix teils vorhanden (*„spezifikationsbasiert"*, *„CRAP-Smoke"*, *„strukturbasiert"*). Heute uneinheitlich — als eigenes **Qualitätssiegel** pro Zelle strukturierbar. Siehe §5.2. |
| **Feature-Detailkonzept-Dateien** (`docs/testquality_improve_*.md`, `docs/coverage-runs/`, `docs/port-implementation/`) | Heute teils verlinkt via `@see`-Annotationen in Tests (z. B. `EditRawGedcomIntegrationTest.php` → `docs/testquality_improve_E03.md`). In `tds_coverage_ref.md` werden diese Verweise **nicht** geführt. Tragfähige Quelle für Detail-Lesebene. |

### 4.3 Methode für die Gap-Analyse-Neuerhebung (keine Ausführung in dieser Analyse)

Die Neuerhebung der Gap-Analyse (heute in `tds_conditions_ref.md` mit Stand 2026-03-26) lässt
sich reproduzierbar mit folgender Methode durchführen:

```text
1. Basis definieren:
   - L2: Branch `port-layer2-test-doubles` im Repo webtrees-upstream/webtrees,
     nur lesend, gesamthaft (nicht Delta).
   - L3: layer3-integration/tests auf testing-platform main.
   - L4: layer4-e2e/tests auf testing-platform main.
   - Anwendungscode: upstream/webtrees auf security-audit-consolidated.

2. Metriken pro Testklasse:
   - Zeilen gesamt (wc -l)
   - Testmethoden (grep -c 'function test_' oder PHPUnit XML-Output)
   - Assertions pro Testmethode (Durchschnitt, Median, Min/Max via grep -c assert)
   - Qualitätseinstufung:
       * Stub        = 1-2 Assertions, nur Klassen-Instanziierung
       * Smoke       = 3-5 Assertions, kein fachlicher Pfad
       * Substantial = fachliche Assertions, Fixtures, Datenprüfung
       * EP-complete = explizite EP-Markierung oder DataProvider mit ≥3 Partitionen

3. Domänen-Zuordnung:
   - Pro Testklasse per SUT-Klasse → Feature-ID.
   - Mehrfachzuordnung zulässig (eine Klasse deckt mehrere Features ab).

4. Auswertung:
   - Pro Domäne: Testdateien, davon substanziell, davon EP-complete.
   - Pro Feature-ID: Anzahl verknüpfter Testklassen, aggregierte Qualität.
   - Keine Erhebung als "Teil dieses Dokuments" — die Erhebung gehört als Resultat
     in ein Snapshot-Dokument unter docs/coverage-runs/ (analog zum bestehenden Snapshot).

5. Verknüpfung zur Feature-Matrix:
   - Ergebnis der Erhebung wird als Coverage-Matrix gespiegelt (Feature → Testklasse).
   - Keine Änderung der Feature-IDs.
```

**Wichtig:** Diese Methode wird in dieser Analyse *beschrieben*, aber **nicht ausgeführt**.
Ihre Ausführung gehört in den späteren Umsetzungsplan als eigener Arbeitsschritt.

---

## 5 Zielbild (Empfehlungen)

### 5.1 Neue Spaltenstruktur der Abdeckungsmatrix

**Empfehlung:** Die Spalten benennen ausdrücklich *Teststufe + Layer-Namen* und sind
einheitlich über alle Domänen (G/S/P/SEC/E/A/K/U). Beispiel für den Header:

```
| # | Feature | L2 — Komponententest (Upstream-Fork) | L3 — KIT (MySQL) | L4 — Systemtest (Playwright) | Status |
|---|---------|--------------------------------------|------------------|------------------------------|--------|
```

**Strukturelle Änderungen:**

| Änderung | Begründung |
|---|---|
| **L1 (Statische Analyse) und L5 (Performance) werden bewusst nicht als eigene Matrix-Spalten aufgenommen.** | L1 arbeitet auf Dateiebene (PHPStan, PHPCS, Trivy), nicht feature-bezogen. L5 hängt an OTel-Traces, nicht an Feature-IDs. Beide werden in `tp_ratchet_spec.md` und in einer kurzen Fußnote unter der Matrix erwähnt. |
| **Die Sicherheits-Submatrix (SEC-H01 … SEC-UTL01) wird auf die Einheitsstruktur migriert**, mit Zusatzhinweis auf `security-filesystem-checks.sh` (Shell-Assertions als eigene Annotation im Klammer-Kommentar, nicht als eigene Spalte). | Einheitliche Lesart. Shell-Assertions sind ein Implementierungsdetail des L4-Containers. |
| **Die Spalte `Eigene Infra (Playwright)` heißt künftig `L4 — Systemtest (Playwright)`** und die Spalte `Eigene Infra (MySQL)` künftig `L3 — KIT (MySQL)`. | Eindeutige Layer-Zuordnung für den Leser. |
| **Die Spalte `Upstream (SQLite)` heißt künftig `L2 — Komponententest (Upstream-Fork)`** mit Fußnote *„Stand Branch `port-layer2-test-doubles`, im Upstream-main noch nicht akzeptiert"*. | Transparente Quellen-Kennzeichnung. |

### 5.2 Inhalt der Zellen — einheitliches Schema

**Empfehlung:** Jede abgedeckte Zelle enthält einheitlich vier Informationen:

```
<TestklassenName> [<QualitätsSiegel>] (<AnzahlTestmethoden> Tests) → <DetailkonzeptLink>
```

| Komponente | Beispiel | Quelle | Automatisierbar? |
|---|---|---|---|
| Testklassen-Name | `GedcomImportServiceTest` | Fork-Pfad (bei L2), `layer3-integration/tests/` (bei L3) | Ja — aus bestehendem Inhalt extrahierbar |
| Qualitätssiegel | `[EP]`, `[Smoke]`, `[CRAP]`, `[Spec-B]`, `[Spec-C]` | Manuelle Einstufung nach Schema in §4.3 Schritt 2 | Teilweise — Klammer-Kommentare enthalten das heute schon in Textform |
| Testmethoden-Zahl | `(8 Tests)` | `grep -c 'function test_'` oder PHPUnit JUnit-XML | Ja |
| Detailkonzept-Link | `→ docs/testquality_improve_G27.md` | Bei Features mit expliziter Detail-Dokumentation | Ja — via Vorhandensein der Datei |

Das **Qualitätssiegel** ersetzt die heutigen Freitext-Kommentare *„(spezifikationsbasiert)"*,
*„(CRAP-Smoke)"*, *„(strukturbasiert)"* durch ein konsistentes Kürzel. Für die Detailbeschreibung
bleibt der Klammer-Kommentar in der Zeile erlaubt (weil das atomare Feature-Zeilen-Prinzip
das so vorsieht), wird aber **nicht** zur EP-Ebene verfeinert.

**Wichtig (Randbedingung E4):** Die **Zeile pro Feature bleibt atomar**. Keine Zeilen pro EP-ID,
keine Zeilen pro Testmethode. Alle zusätzlichen Informationen liegen *innerhalb* der Zelle.

### 5.3 Duale Nomenklatur — Mapping-Tabelle am Dokumentanfang

**Empfehlung:** Beide Dokumente (`tds_conditions_ref.md` und `tds_coverage_ref.md`) bekommen
am Anfang dieselbe Mapping-Tabelle:

```markdown
## Teststufen und Layer — Nomenklatur

Dieses Projekt verwendet zwei Bezugssysteme, die sich gegenseitig ergänzen:

| ISTQB-Teststufe               | Layer (Makefile/Verzeichnis)         | Pfad                    |
|-------------------------------|--------------------------------------|-------------------------|
| —                             | L1 — Statische Analyse               | `layer1-static/`        |
| Teststufe 1 — Komponententest | L2 — `make test-unit`                | `layer2-unit/` (Upstream-Fork-Testbasis) |
| Teststufe 2 — KIT             | L3 — `make test-integration`         | `layer3-integration/`   |
| Teststufe 3 — Systemtest      | L4 — `make test-e2e`                 | `layer4-e2e/`           |
| — (Querschnitt)               | L5 — `make test-performance`         | `layer5-performance/`   |

In den Feature-Matrizen wird die Teststufen-Spalte als ISTQB-Nummer (1–3) geführt
(historisch gewachsen, Bezug: `tp_decisions_spec.md`). In der Abdeckungsmatrix werden
die Spalten per Layer (L2/L3/L4) benannt, weil die Layer die physische
Testinfrastruktur beschreiben, in der die Tests laufen.
```

### 5.4 Umgang mit historischem Gap-Analyse-Block

**Empfehlung:** Der bisherige Abschnitt *„Befund: Gap-Analyse der existierenden webtrees-Tests"*
in `tds_conditions_ref.md` wird:

1. **Aus dem Hauptfluss herausgelöst** in einen Anhang *„Historischer Befund 2026-03-26"*.
2. Mit einem **Hinweis-Lead-In** versehen: *„Dieser Befund ist mit Stand 2026-03-26 gegen Upstream-main erhoben. Er ist durch den Fork-Branch `port-layer2-test-doubles` (Commit `841616f4b5`) in Teilen überholt. Für den aktuellen Stand siehe `docs/coverage-runs/<datum>.md`."*
3. **Nicht gelöscht**, weil die Historie den methodischen Weg dokumentiert und der User kleinteiliges Review wünscht.
4. In einem separaten Umsetzungsplan-Schritt wird der Abschnitt durch eine **Neuerhebung** nach der Methode aus §4.3 ergänzt (als eigener Snapshot unter `docs/coverage-runs/`).

### 5.5 Trennung Abdeckungsstatus ↔ Findings-Status

**Empfehlung:** Die heutige `Status`-Spalte vermischt zwei Dimensionen. Zukünftig:

| Spalte | Wertebereich | Bedeutung |
|---|---|---|
| `Abdeckung` | `OK` / `Teil` / `—` / `SKIP` | Ist das Feature durch Tests belegt? |
| `Befund` | `—` / `Upstream-Bug` / `Deployment-Empfehlung` / `Deprecated` | Gibt es einen dokumentationswürdigen Befund? |

Beispiel bisher: `G16 … | **Abgedeckt** (mit Upstream-Bug)`
Beispiel neu:   `G16 … | `OK` | `Upstream-Bug``

### 5.6 Verlinkung Quell-Datei pro Testklasse

**Empfehlung:** Die Zellinhalte bekommen einen **relativen Pfad-Verweis** oder eine Fußnote,
die den Leser zur Quell-Datei führt. Dabei werden drei Quellen unterschieden:

- L2 Fork: `webtrees-upstream/webtrees/tests/app/Services/GedcomImportServiceTest.php` (externes Repo)
- L3: `layer3-integration/tests/GedcomImportTest.php` (innerhalb Testing-Platform)
- L4: `layer4-e2e/tests/upload-validation.spec.ts` (innerhalb Testing-Platform)

Ein leichtgewichtiger Weg: Eine **Legende** am Anfang des Abdeckungsdokuments definiert
die drei Präfixe (`L2:`, `L3:`, `L4:`), und die Zellen nennen nur den Dateinamen ohne Pfad.
Alternativ voller Pfad pro Zelle (verbose, aber unabhängig vom Leser-Wissen).

### 5.7 Umgang mit gebrochenen `@see`-Annotationen im Testcode

**Empfehlung:** Als *separates, risikoarmes Refactoring* (nicht Teil der
Dokumentationsmaßnahme selbst) folgende Aktion im Umsetzungsplan aufnehmen:

```text
Umsetzungsschritt (optional, separater Commit):
- Grep über layer3-integration/, layer4-e2e/ und — falls gewünscht — Upstream-Fork:
     @see docs/testing-bigpicture.md → @see docs/tds_conditions_ref.md
- Feature-IDs bleiben unverändert. Reiner Pfad-Update.
- Verifikationsschritt: Grep für Restvorkommen 'testing-bigpicture' muss 0 liefern.
```

Dieser Schritt erzeugt *keine* Testverhaltens-Änderung, nur eine Dokumentations-Konsistenz.

---

## 6 Randbedingungen für den späteren Umsetzungsplan

| Randbedingung | Quelle | Wirkung auf den Plan |
|---|---|---|
| **Feature-IDs unverändert** | E4 + `@see`-Ankerbefund §3.2 | Der Plan darf keine Feature-ID umbenennen, spalten oder auflösen. Neue Features erhalten neue IDs (fortlaufend), alte IDs werden nicht recycelt. |
| **Feature-Zeile atomar** | E4 + User-Rückmeldung | Keine EP- oder Testmethoden-Ebene in der Matrix. Details bleiben im Klammer-Kommentar oder wandern in externe Detailkonzepte. |
| **Fork-Repo bleibt read-only** | User-Vorgabe | Keine Commits am `webtrees-upstream/webtrees`-Repo. Die L2-Spalte zeigt Fork-Stand, ohne den Fork selbst zu modifizieren. |
| **Keine automatische Live-Aggregation** | `coverage.xml` ist „in Scope, nicht Vorgabe" | Der Plan darf `coverage.xml` als Quelle nutzen, aber die Matrix muss auch *ohne* verfügbare Artefakte lesbar bleiben. |
| **Duale Nomenklatur** | E2 | Der Plan muss beide Nomenklaturen in beiden Dokumenten konsistent halten und die Mapping-Tabelle synchron pflegen. |
| **Neu-Erhebung der Gap-Analyse separat** | E3 | Die Neuerhebung ist ein eigener Umsetzungsschritt mit eigenem Snapshot-Artefakt unter `docs/coverage-runs/`. Sie wird im Plan nicht in `tds_conditions_ref.md` inline gepflegt. |
| **Keine Änderungen am Testcode durch diese Dokumentationsmaßnahme** | Risiko-Begrenzung | Ausnahme: der optionale `@see`-Pfad-Update-Schritt (§5.7) als separater, risikoarmer Commit. Nicht als Voraussetzung der Doku-Arbeit. |
| **Sprache de_DE** | CLAUDE.md | Alle Dokumentationsänderungen auf Deutsch. Commit-Messages auf Deutsch. |
| **SPDX-Header** | CLAUDE.md | Neue .md-Dateien erhalten `<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->` als erste Zeile. |
| **Kleinteiliges Review** | Feedback-Memory | Der Plan sollte in **mehreren Runden** arbeiten können — z. B. Runde 1: Spaltenstruktur + Nomenklatur; Runde 2: Qualitätssiegel einführen; Runde 3: Neuerhebung + Snapshot. Keine „Big-Bang"-Migration. |

---

## 7 Offene Fragen für den Umsetzungsplan

Diese Fragen sind bewusst *nicht* in dieser Analyse beantwortet und sollen in der
Plan-Phase entschieden werden. Sie sind hier aufgezählt, damit der Planer sie gezielt aufgreifen kann.

| # | Offene Frage | Abhängigkeit / Implikation |
|---|---|---|
| O1 | Soll der historische Gap-Analyse-Block (Stand 2026-03-26) im Haupt-Dokument verbleiben (als markierter Anhang) oder in ein separates Archivdokument unter `docs/coverage-runs/historical/` verschoben werden? | Beide Varianten sind plan-kompatibel. Variante 1 (verbleiben) hält die Historie sichtbar; Variante 2 (archivieren) entlastet das Hauptdokument. |
| O2 | Soll die Neuerhebung (§4.3) als *erste* Runde des Plans erfolgen (Daten-Basis schaffen), oder als *letzte* Runde (nach Strukturmigration der Dokumente)? | Reihenfolge-Entscheidung. „Erst" ist methodisch sauber, „zuletzt" ist pragmatisch. |
| O3 | Soll das Qualitätssiegel (§5.2) ein *Pflichtfeld* werden (jede Zelle bekommt eines) oder ein *optionales Feld* (wo es einen klaren EP-basierten Test gibt, bekommt es `[EP]`, sonst bleibt die Zelle ohne Siegel)? | Beeinflusst den Aufwand der Doku-Migration. Pflichtfeld = höherer Aufwand pro Zelle; Optional = leichter Einstieg. |
| O4 | Soll die Verknüpfung Feature → Testdatei via relativer Pfade (in der Zelle) oder via Fußnoten-Mapping am Dokumentende erfolgen? | Beide Varianten sind funktional. Relative Pfade = direkter; Fußnote = kompaktere Zeilen. |
| O5 | Sollen neue Feature-IDs für bisher nicht erfasste Bereiche (z. B. `app/Http/Middleware/`, neue CLI-Commands wie `SiteOffline`, `UserList`) schon im Rahmen der Dokumentations-Migration vergeben werden, oder erst in einer Folgerunde nach Abschluss der Strukturmigration? | E4 (Feature-IDs unverändert) erlaubt *neue* IDs, nur keine Änderung alter. Frage ist, wann sie vergeben werden. |
| O6 | Sollen die Abdeckungs-Zusammenfassungs-Zahlen (`165 abgedeckt / 5 nicht abgedeckt`) weiterhin im Hauptdokument gepflegt oder ausschließlich in `docs/coverage-runs/` als Snapshot gehalten werden? | Beeinflusst die Pflegefrequenz des Hauptdokuments. Snapshot-Ausgliederung entkoppelt. |
| O7 | Soll die Feature-Matrix-Datei `tds_conditions_ref.md` in mehrere Dateien pro Domäne aufgespalten werden (z. B. `tds_conditions_gedcom.md`, `tds_conditions_privacy.md`)? | **Nicht empfohlen**, weil die `@see`-Annotationen im Testcode heute auf *eine* Datei zeigen — eine Aufteilung würde die Anker zweimal brechen. Frage offen halten, falls der Planer trotzdem motivieren will. |
| O8 | Soll die Coverage-Matrix-Datei eine eigene Domänen-Navigation (Anker-Links) bekommen, damit der Leser gezielt zu G/S/P/SEC/E/A/K/U springen kann? | Usability-Entscheidung. Kein Aufwand in der Struktur, nur Metadaten. |

---

## 8 Zusammenfassung der Empfehlungen (Lesehilfe für den Planer)

| # | Empfehlung | Aufwand | Risiko | Wert |
|---|---|---|---|---|
| R1 | **Mapping-Tabelle Teststufen ↔ Layer** an den Anfang beider Dokumente einfügen (§5.3) | Niedrig | Keines | Hoch — entfernt die größte Verwirrungsquelle für menschliche Leser |
| R2 | **Spaltenüberschriften der Abdeckungsmatrix** auf `L2 — Komponententest (Upstream-Fork)` / `L3 — KIT (MySQL)` / `L4 — Systemtest (Playwright)` migrieren (§5.1) | Niedrig | Keines | Hoch — unmittelbar sichtbare Verbesserung |
| R3 | **Qualitätssiegel** als Kennzeichnungssystem einführen (`[EP]`, `[Smoke]`, `[CRAP]`, `[Spec-B]`, `[Spec-C]`) (§5.2) | Mittel — pro Zelle manuelles Einstufen | Niedrig — semantische Verdichtung bestehender Informationen | Hoch — methodische Transparenz |
| R4 | **Trennung Abdeckungsstatus ↔ Findings-Status** (§5.5) — zwei Spalten statt einer | Niedrig | Keines | Mittel — saubere Trennung |
| R5 | **Neuerhebung der Gap-Analyse** gegen Fork-Stand als separaten Snapshot unter `docs/coverage-runs/` (§4.3) | Hoch — erfordert aktive Testdatei-Inventur und Metriken-Erhebung | Niedrig — reiner Read-Only-Vorgang | Hoch — saubere Baseline für alle künftigen Gap-Diskussionen |
| R6 | **`@see`-Pfad-Update** `testing-bigpicture.md → tds_conditions_ref.md` als separaten, risikoarmen Commit (§5.7) | Sehr niedrig — reiner Search/Replace | Sehr niedrig — Feature-IDs bleiben gleich | Mittel — konsistente Referenzen |
| R7 | **Historischer Gap-Analyse-Block** als markierter Anhang behalten oder archivieren (§5.4) | Niedrig | Keines | Mittel — Entlastung des Hauptflusses |
| R8 | **Datei-Verlinkung pro Testklasse** per Legende oder relativem Pfad (§5.6) | Mittel | Niedrig | Mittel — Navigationsverbesserung |
| R9 | **Zusatzquellen erschließen** (Routes, Handler-Inventar, CLI-Commands, Middleware, `@see`-Rückwärtsindex) (§4.1, §4.2) | Hoch — erfordert eigene Arbeitsschritte | Niedrig | Hoch — erhöht Vollständigkeit der Feature-Ermittlung |

---

## 9 Was diese Analyse bewusst NICHT adressiert

- **Keine Neuerhebung der 2026-03-26-Zahlen.** Nur die Methode wird beschrieben (§4.3).
- **Keine Umbenennung oder Spaltung von Feature-IDs.** Die bestehenden IDs bleiben die atomaren Anker.
- **Keine Änderungen am Testcode** (Ausnahme: optionaler Pfad-Update in §5.7 als separater, risikoarmer Commit).
- **Keine Auflistung aller 170+ Features neu.** Die fachliche Gliederung (G/S/P/SEC/E/A/K/U) bleibt.
- **Keine Entscheidung zu den offenen Fragen (§7).** Die gehören in den Plan.
- **Keine Änderungen an `tp_decisions_spec.md`, `tds_methodik_spec.md`, `tp_ratchet_spec.md` oder anderen Nachbardokumenten.** Die Analyse fokussiert strikt auf `tds_conditions_ref.md` + `tds_coverage_ref.md`. Abhängigkeiten werden nur genannt, nicht gelöst.

---

## Anhang A — Verifizierte Quelldateien (Belegstellen für diese Analyse)

| Information | Belegstelle | Verifiziert |
|---|---|---|
| `testing-bigpicture.md` existiert nicht | `glob docs/**/*.md` (2026-04-11) | ✓ |
| 10 `@see docs/testing-bigpicture.md`-Verweise in Tests | `grep testing-bigpicture` in Testing-Platform (2026-04-11, 10 Treffer) | ✓ |
| 276 Test-Dateien im Fork-Delta | `git diff --stat main...port-layer2-test-doubles -- tests/` (2026-04-11) | ✓ |
| Fork-Commit `841616f4b5`: „Add substantive component tests for 278 test files" | `git log --oneline main..port-layer2-test-doubles -- tests/` | ✓ |
| 1232 Testdateien in `tests/app/` + 5 in `tests/feature/` (Fork, gesamthaft) | `find tests/app -name '*Test.php'` auf `port-layer2-test-doubles` | ✓ |
| 84 Layer-3-Integrationstests | `find layer3-integration/tests -name '*.php' -not -name 'MysqlTestCase*' -not -name 'bootstrap*'` | ✓ |
| 26 Layer-4-E2E-Specs | `find layer4-e2e/tests -name '*.spec.ts'` | ✓ |
| 335 Handler-Klassen | `ls upstream/webtrees/app/Http/RequestHandlers/` | ✓ |
| 38 Service-Klassen | `ls upstream/webtrees/app/Services/` | ✓ |
| 13 CLI-Commands | `ls upstream/webtrees/app/Cli/Commands/` | ✓ |
| ISTQB-Teststufen ↔ Layer-Mapping in `tp_decisions_spec.md` Zeilen 66–78 | `grep -n` auf dieses Dokument | ✓ |
| Layer-2-vs-Layer-3-Coverage 39,82 % / 39,83 % | `docs/coverage-runs/2026-04-11_layer2-vs-layer3.md` | ✓ (existiert im Repo) |

---

## Anhang B — Glossar der in dieser Analyse benutzten Kürzel

| Kürzel | Bedeutung |
|---|---|
| L1 … L5 | Layer-Nummer nach Makefile/Verzeichnis-Struktur in `CLAUDE.md`. L1 = Statische Analyse, L2 = Komponententest, L3 = KIT, L4 = Systemtest, L5 = Performance. |
| Teststufe 1/2/3 | ISTQB-Teststufen nach `tp_decisions_spec.md`. Teststufe 1 = Komponententest, 2 = KIT, 3 = Systemtest. **Mappt auf L2/L3/L4.** |
| Feature-ID | Referenz der Form G01, S05, P12, SEC-H01, E03, A07, K01, U01. Verankert via `@see` in Testklassen. |
| KIT | Komponentenintegrationstest (ISTQB). |
| EP | Äquivalenzklasse (ISTQB-Testentwurfsverfahren). |
| BVA | Boundary Value Analysis, Grenzwertanalyse (ISTQB). |
| CRAP | Change Risk Analysis and Predictions — Metrik aus Cyclomatic Complexity × Covered-Ratio. Genutzt in `make crap-report`. |
| Spec-B / Spec-C | Spezifikationsbasiertes Testen nach ISTQB-Kategorie. B = strikt, C = pragmatisch. |
| `@see` | PHPDoc-Annotation, die Verfolgbarkeit von Testklasse zu Feature-ID oder Detailkonzept herstellt. |
